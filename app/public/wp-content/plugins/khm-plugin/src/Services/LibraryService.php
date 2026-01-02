<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;

/**
 * Library Service
 * 
 * Provides bookmark/save-to-library functionality for the KHM membership system.
 * Based on proven CBX Bookmark architecture but adapted for membership integration.
 */
class LibraryService {

    private MembershipRepository $memberships;
    private string $library_table;
    private string $categories_table;

    public function __construct(MembershipRepository $memberships) {
        global $wpdb;
        $this->memberships = $memberships;
        $this->library_table = $wpdb->prefix . 'khm_member_library';
        $this->categories_table = $wpdb->prefix . 'khm_library_categories';
    }

    /**
     * Create database tables for library functionality
     */
    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Library items table
        $library_sql = "CREATE TABLE {$this->library_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            member_id int(11) NOT NULL,
            category_id int(11) DEFAULT 1,
            membership_level varchar(50) DEFAULT '',
            is_favorite tinyint(1) DEFAULT 0,
            read_status enum('unread','reading','read') DEFAULT 'unread',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_member_post (member_id, post_id),
            KEY idx_member_category (member_id, category_id),
            KEY idx_post_id (post_id),
            KEY idx_membership_level (membership_level)
        ) $charset_collate;";

        // Categories table
        $categories_sql = "CREATE TABLE {$this->categories_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            category_name varchar(255) NOT NULL,
            member_id int(11) NOT NULL,
            privacy enum('public','private') DEFAULT 'private',
            is_default tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_member_id (member_id),
            KEY idx_sort_order (sort_order)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($library_sql);
        dbDelta($categories_sql);

        // Create default categories for existing members
        $this->create_default_categories_for_all_members();
    }

    /**
     * Save article to member's library
     */
    public function save_to_library(int $member_id, int $post_id, int $category_id = null): bool {
        global $wpdb;

        // Get member's membership info
        $membership = $this->memberships->get_user_membership($member_id);
        $membership_level = $membership ? $membership->level_name : '';

        // Use default category if none specified
        if (!$category_id) {
            $category_id = $this->get_default_category_id($member_id);
        }

        $result = $wpdb->insert(
            $this->library_table,
            [
                'post_id' => $post_id,
                'member_id' => $member_id,
                'category_id' => $category_id,
                'membership_level' => $membership_level,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );

        if ($result) {
            do_action('khm_article_saved_to_library', $member_id, $post_id, $category_id);
            return true;
        }

        return false;
    }

    /**
     * Remove article from member's library
     */
    public function remove_from_library(int $member_id, int $post_id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->library_table,
            [
                'member_id' => $member_id,
                'post_id' => $post_id
            ],
            ['%d', '%d']
        );

        if ($result) {
            do_action('khm_article_removed_from_library', $member_id, $post_id);
            return true;
        }

        return false;
    }

    /**
     * Get a specific library item for a member
     */
    public function get_library_item(int $member_id, int $post_id): ?object {
        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->library_table} WHERE member_id = %d AND post_id = %d",
            $member_id,
            $post_id
        ));

        return $item ?: null;
    }

    /**
     * Check if article is saved in member's library
     */
    public function is_saved(int $member_id, int $post_id): bool {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->library_table} WHERE member_id = %d AND post_id = %d",
            $member_id,
            $post_id
        ));

        return $count > 0;
    }

    /**
     * Toggle save status (save if not saved, remove if saved)
     */
    public function toggle_save(int $member_id, int $post_id): array {
        if ($this->is_saved($member_id, $post_id)) {
            $success = $this->remove_from_library($member_id, $post_id);
            return [
                'action' => 'removed',
                'success' => $success,
                'is_saved' => false
            ];
        } else {
            $success = $this->save_to_library($member_id, $post_id);
            return [
                'action' => 'saved',
                'success' => $success,
                'is_saved' => true
            ];
        }
    }

    /**
     * Get member's library items
     */
    public function get_member_library(int $member_id, array $args = []): array {
        global $wpdb;

        $defaults = [
            'category_id' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'read_status' => null
        ];

        $args = wp_parse_args($args, $defaults);

        $where_conditions = ["l.member_id = %d"];
        $where_values = [$member_id];

        if ($args['category_id']) {
            $where_conditions[] = "l.category_id = %d";
            $where_values[] = $args['category_id'];
        }

        if ($args['search']) {
            $where_conditions[] = "p.post_title LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if ($args['read_status']) {
            $where_conditions[] = "l.read_status = %s";
            $where_values[] = $args['read_status'];
        }

        $where_clause = implode(' AND ', $where_conditions);
        $order_clause = sprintf(
            "ORDER BY l.%s %s",
            sanitize_sql_orderby($args['orderby']),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $sql = "
            SELECT l.*, p.post_title, p.post_excerpt, p.post_date, c.category_name
            FROM {$this->library_table} l
            LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
            LEFT JOIN {$this->categories_table} c ON l.category_id = c.id
            WHERE {$where_clause}
            {$order_clause}
            LIMIT %d OFFSET %d
        ";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }

    /**
     * Get member's library categories
     */
    public function get_member_categories(int $member_id): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, COUNT(l.id) as item_count 
             FROM {$this->categories_table} c
             LEFT JOIN {$this->library_table} l ON c.id = l.category_id
             WHERE c.member_id = %d
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.category_name ASC",
            $member_id
        ));

        return $results ?: [];
    }

    /**
     * Create a new category for member
     */
    public function create_category(int $member_id, string $category_name, string $privacy = 'private'): int {
        global $wpdb;

        $result = $wpdb->insert(
            $this->categories_table,
            [
                'category_name' => sanitize_text_field($category_name),
                'member_id' => $member_id,
                'privacy' => $privacy,
                'sort_order' => $this->get_next_sort_order($member_id)
            ],
            ['%s', '%d', '%s', '%d']
        );

        return $result ? $wpdb->insert_id : 0;
    }

    /**
     * Create default categories for a member
     */
    public function create_default_categories(int $member_id): void {
        $defaults = [
            ['Reading List', 'private', 1],
            ['Favorites', 'private', 2],
            ['Archive', 'private', 3]
        ];

        foreach ($defaults as $index => [$name, $privacy, $sort]) {
            $this->create_category_if_not_exists($member_id, $name, $privacy, $sort, true);
        }
    }

    /**
     * Get default category ID for member (Reading List)
     */
    private function get_default_category_id(int $member_id): int {
        global $wpdb;

        $category_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->categories_table} 
             WHERE member_id = %d AND category_name = 'Reading List' 
             LIMIT 1",
            $member_id
        ));

        if (!$category_id) {
            // Create default categories if they don't exist
            $this->create_default_categories($member_id);
            $category_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->categories_table} 
                 WHERE member_id = %d AND category_name = 'Reading List' 
                 LIMIT 1",
                $member_id
            ));
        }

        return (int) $category_id;
    }

    /**
     * Create default categories for all existing members
     */
    private function create_default_categories_for_all_members(): void {
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $this->create_default_categories($user_id);
        }
    }

    /**
     * Helper method to create category if it doesn't exist
     */
    private function create_category_if_not_exists(int $member_id, string $name, string $privacy, int $sort_order, bool $is_default = false): void {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->categories_table} WHERE member_id = %d AND category_name = %s",
            $member_id,
            $name
        ));

        if (!$exists) {
            $wpdb->insert(
                $this->categories_table,
                [
                    'category_name' => $name,
                    'member_id' => $member_id,
                    'privacy' => $privacy,
                    'is_default' => $is_default ? 1 : 0,
                    'sort_order' => $sort_order
                ],
                ['%s', '%d', '%s', '%d', '%d']
            );
        }
    }

    /**
     * Get next sort order for member's categories
     */
    private function get_next_sort_order(int $member_id): int {
        global $wpdb;

        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$this->categories_table} WHERE member_id = %d",
            $member_id
        ));

        return ($max_order ?? 0) + 1;
    }

    /**
     * Get library statistics for member
     */
    public function get_library_stats(int $member_id): array {
        global $wpdb;

        $total_saved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->library_table} WHERE member_id = %d",
            $member_id
        ));

        $favorites_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->library_table} WHERE member_id = %d AND is_favorite = 1",
            $member_id
        ));

        $read_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->library_table} WHERE member_id = %d AND read_status = 'read'",
            $member_id
        ));

        return [
            'total_saved' => (int) $total_saved,
            'favorites' => (int) $favorites_count,
            'read' => (int) $read_count,
            'unread' => (int) $total_saved - (int) $read_count
        ];
    }

    /**
     * Get total count of saved items for member
     */
    public function get_library_count(int $member_id): int {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->library_table} WHERE member_id = %d",
            $member_id
        ));

        return (int) $count;
    }

    /**
     * Update item properties (favorite, read status, notes)
     */
    public function update_item(int $member_id, int $post_id, array $updates): bool {
        global $wpdb;

        $allowed_fields = ['is_favorite', 'read_status', 'notes', 'category_id'];
        $update_data = [];
        $format = [];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_data[$field] = $value;
                $format[] = is_int($value) ? '%d' : '%s';
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->library_table,
            $update_data,
            ['member_id' => $member_id, 'post_id' => $post_id],
            $format,
            ['%d', '%d']
        );

        return $result !== false;
    }

    /**
     * Clean up expired membership libraries
     */
    public function cleanup_expired_memberships(): void {
        global $wpdb;

        // This would integrate with your membership expiration logic
        // For now, we'll keep all library items regardless of membership status
        // but you could add logic here to archive or delete items for expired members
        
        do_action('khm_library_cleanup_expired_memberships');
    }
}
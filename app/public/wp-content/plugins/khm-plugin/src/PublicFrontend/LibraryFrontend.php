<?php

namespace KHM\PublicFrontend;

use KHM\Services\LibraryService;
use KHM\Services\MembershipRepository;

/**
 * Library Frontend
 * 
 * Provides frontend interface for members to manage their saved articles
 */
class LibraryFrontend {

    private LibraryService $library;
    private MembershipRepository $memberships;

    public function __construct(LibraryService $library, MembershipRepository $memberships) {
        $this->library = $library;
        $this->memberships = $memberships;
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_shortcode('khm_member_library', [$this, 'render_library_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_khm_load_library_items', [$this, 'ajax_load_library_items']);
        add_action('wp_ajax_khm_update_library_item', [$this, 'ajax_update_library_item']);
        add_action('wp_ajax_khm_create_library_category', [$this, 'ajax_create_category']);
        add_action('wp_ajax_khm_delete_library_item', [$this, 'ajax_delete_library_item']);
        add_action('wp_ajax_khm_share_library_article', [$this, 'ajax_share_library_article']);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets(): void {
        if ($this->is_library_page()) {
            wp_enqueue_style(
                'khm-library-frontend',
                plugin_dir_url(__FILE__) . '../../assets/css/library-frontend.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'khm-library-frontend',
                plugin_dir_url(__FILE__) . '../../assets/js/library-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('khm-library-frontend', 'khmLibrary', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('khm_library_nonce'),
                'strings' => [
                    'loading' => __('Loading...', 'khm-membership'),
                    'error' => __('An error occurred. Please try again.', 'khm-membership'),
                    'saved' => __('Changes saved!', 'khm-membership'),
                    'deleted' => __('Item removed from library', 'khm-membership'),
                    'confirm_delete' => __('Are you sure you want to remove this item?', 'khm-membership'),
                ]
            ]);
        }
    }

    /**
     * Check if current page contains library shortcode
     */
    private function is_library_page(): bool {
        global $post;
        return $post && has_shortcode($post->post_content, 'khm_member_library');
    }

    /**
     * Render library shortcode
     */
    public function render_library_shortcode($atts): string {
        $atts = shortcode_atts([
            'view' => 'grid', // grid or list
            'per_page' => 12,
            'show_categories' => 'true',
            'show_search' => 'true',
            'show_filters' => 'true'
        ], $atts);

        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return '<div class="khm-library-error">Please log in to view your library.</div>';
        }

        // Check if user has active membership
        $membership = $this->memberships->findActive($user_id);
        if (empty($membership)) {
            return '<div class="khm-library-error">Active membership required to access your library.</div>';
        }

        ob_start();
        $this->render_library_interface($user_id, $atts);
        return ob_get_clean();
    }

    /**
     * Render the main library interface
     */
    private function render_library_interface(int $user_id, array $atts): void {
        $categories = $this->library->get_member_categories($user_id);
        $stats = $this->library->get_library_stats($user_id);
        
        ?>
        <div class="khm-library-container" data-user-id="<?= esc_attr($user_id); ?>">
            
            <!-- Library Header -->
            <div class="khm-library-header">
                <h2 class="khm-library-title">My Library</h2>
                <div class="khm-library-stats">
                    <span class="stat-item">
                        <strong><?= $stats['total_saved']; ?></strong> Articles
                    </span>
                    <span class="stat-item">
                        <strong><?= $stats['favorites']; ?></strong> Favorites
                    </span>
                    <span class="stat-item">
                        <strong><?= $stats['unread']; ?></strong> Unread
                    </span>
                </div>
            </div>

            <div class="khm-library-content">
                
                <?php if ($atts['show_categories'] === 'true'): ?>
                <!-- Categories Sidebar -->
                <div class="khm-library-sidebar">
                    <div class="khm-category-list">
                        <h3>Categories</h3>
                        <div class="category-items">
                            <div class="category-item active" data-category-id="all">
                                <span class="category-name">All Articles</span>
                                <span class="category-count"><?= $stats['total_saved']; ?></span>
                            </div>
                            <?php foreach ($categories as $category): ?>
                                <div class="category-item" data-category-id="<?= esc_attr($category->id); ?>">
                                    <span class="category-name"><?= esc_html($category->category_name); ?></span>
                                    <span class="category-count"><?= $category->item_count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button class="btn-add-category" id="add-new-category">
                            <span class="dashicons dashicons-plus"></span> Add Category
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Main Content Area -->
                <div class="khm-library-main">
                    
                    <?php if ($atts['show_search'] === 'true' || $atts['show_filters'] === 'true'): ?>
                    <!-- Search and Filters -->
                    <div class="khm-library-controls">
                        
                        <?php if ($atts['show_search'] === 'true'): ?>
                        <div class="search-box">
                            <input type="text" id="library-search" placeholder="Search your library..." />
                            <span class="dashicons dashicons-search"></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_filters'] === 'true'): ?>
                        <div class="filter-controls">
                            <select id="sort-by">
                                <option value="created_at_desc">Newest First</option>
                                <option value="created_at_asc">Oldest First</option>
                                <option value="post_title_asc">Title A-Z</option>
                                <option value="post_title_desc">Title Z-A</option>
                            </select>
                            
                            <select id="filter-status">
                                <option value="all">All Status</option>
                                <option value="unread">Unread</option>
                                <option value="reading">Reading</option>
                                <option value="read">Read</option>
                            </select>
                            
                            <button class="view-toggle <?= $atts['view'] === 'grid' ? 'active' : ''; ?>" data-view="grid">
                                <span class="dashicons dashicons-grid-view"></span>
                            </button>
                            <button class="view-toggle <?= $atts['view'] === 'list' ? 'active' : ''; ?>" data-view="list">
                                <span class="dashicons dashicons-list-view"></span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Library Items Container -->
                    <div class="khm-library-items <?= esc_attr($atts['view']); ?>-view" 
                         data-per-page="<?= esc_attr($atts['per_page']); ?>">
                        <div class="loading-spinner">
                            <span class="dashicons dashicons-update-alt"></span> Loading your library...
                        </div>
                    </div>

                    <!-- Load More Button -->
                    <div class="load-more-container" style="display: none;">
                        <button class="btn-load-more">Load More Articles</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Add Category -->
        <div id="category-modal" class="khm-modal" style="display: none;">
            <div class="modal-content">
                <h3>Add New Category</h3>
                <form id="category-form">
                    <input type="text" id="category-name" placeholder="Category name" required />
                    <label>
                        <input type="radio" name="privacy" value="private" checked /> Private
                    </label>
                    <label>
                        <input type="radio" name="privacy" value="public" /> Public
                    </label>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-save">Add Category</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal for Item Actions -->
        <div id="item-modal" class="khm-modal" style="display: none;">
            <div class="modal-content">
                <h3>Edit Article</h3>
                <form id="item-form">
                    <input type="hidden" id="item-post-id" />
                    
                    <label>Move to Category:</label>
                    <select id="item-category">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= esc_attr($category->id); ?>">
                                <?= esc_html($category->category_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Reading Status:</label>
                    <select id="item-status">
                        <option value="unread">Unread</option>
                        <option value="reading">Reading</option>
                        <option value="read">Read</option>
                    </select>
                    
                    <label>
                        <input type="checkbox" id="item-favorite" /> Add to Favorites
                    </label>
                    
                    <label>Personal Notes:</label>
                    <textarea id="item-notes" placeholder="Add your notes about this article..."></textarea>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="button" class="btn-delete">Remove from Library</button>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal for Email Sharing -->
        <div id="share-modal" class="khm-modal" style="display: none;">
            <div class="modal-content">
                <h3>📧 Share Article via Email</h3>
                <form id="share-form">
                    <input type="hidden" id="share-post-id" />
                    
                    <label>Recipient Email:</label>
                    <input type="email" id="recipient-email" placeholder="friend@example.com" required />
                    
                    <label>Personal Message (Optional):</label>
                    <textarea id="personal-message" placeholder="I thought you'd find this article interesting..."></textarea>
                    
                    <label>
                        <input type="checkbox" id="include-notes" checked /> Include my personal notes
                    </label>
                    
                    <label>
                        <input type="checkbox" id="include-membership-info" checked /> Include membership information
                    </label>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-send">📧 Send Email</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Load library items
     */
    public function ajax_load_library_items(): void {
        check_ajax_referer('khm_library_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $args = [
            'category_id' => sanitize_text_field($_POST['category_id'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'read_status' => sanitize_text_field($_POST['status'] ?? ''),
            'limit' => intval($_POST['per_page'] ?? 12),
            'offset' => intval($_POST['offset'] ?? 0),
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        // Parse sort parameter
        $sort = sanitize_text_field($_POST['sort'] ?? 'created_at_desc');
        if (strpos($sort, '_') !== false) {
            [$orderby, $order] = explode('_', $sort, 2);
            $args['orderby'] = $orderby;
            $args['order'] = strtoupper($order);
        }

        // Handle "all" category
        if ($args['category_id'] === 'all' || empty($args['category_id'])) {
            unset($args['category_id']);
        }

        $items = $this->library->get_member_library($user_id, $args);
        
        $html = '';
        foreach ($items as $item) {
            $html .= $this->render_library_item($item);
        }

        wp_send_json_success([
            'html' => $html,
            'has_more' => count($items) === $args['limit']
        ]);
    }

    /**
     * Render a single library item
     */
    private function render_library_item($item): string {
        $post = get_post($item->post_id);
        if (!$post) {
            return '';
        }

        $thumbnail = get_the_post_thumbnail_url($post->ID, 'medium') ?: plugin_dir_url(__FILE__) . '../../assets/img/default-article.svg';
        $excerpt = wp_trim_words($post->post_excerpt ?: $post->post_content, 25);
        $read_time = $this->estimate_read_time($post->post_content);

        ob_start();
        ?>
        <div class="library-item" data-post-id="<?= esc_attr($item->post_id); ?>">
            <div class="item-thumbnail">
                <img src="<?= esc_url($thumbnail); ?>" alt="<?= esc_attr($post->post_title); ?>" />
                <div class="item-overlay">
                    <button class="btn-read" onclick="window.open('<?= esc_url(get_permalink($post->ID)); ?>', '_blank')">
                        <span class="dashicons dashicons-visibility"></span> Read
                    </button>
                    <button class="btn-edit" data-post-id="<?= esc_attr($item->post_id); ?>">
                        <span class="dashicons dashicons-edit"></span> Edit
                    </button>
                </div>
                
                <?php if ($item->is_favorite): ?>
                <div class="favorite-badge">
                    <span class="dashicons dashicons-heart"></span>
                </div>
                <?php endif; ?>
                
                <div class="read-status status-<?= esc_attr($item->read_status); ?>">
                    <?= ucfirst($item->read_status); ?>
                </div>
            </div>
            
            <div class="item-content">
                <h3 class="item-title">
                    <a href="<?= esc_url(get_permalink($post->ID)); ?>" target="_blank">
                        <?= esc_html($post->post_title); ?>
                    </a>
                </h3>
                
                <div class="item-meta">
                    <span class="category-name"><?= esc_html($item->category_name); ?></span>
                    <span class="read-time"><?= $read_time; ?> min read</span>
                    <span class="save-date">Saved <?= human_time_diff(strtotime($item->created_at)); ?> ago</span>
                </div>
                
                <p class="item-excerpt"><?= esc_html($excerpt); ?></p>
                
                <?php if ($item->notes): ?>
                <div class="item-notes">
                    <strong>My Notes:</strong> <?= esc_html($item->notes); ?>
                </div>
                <?php endif; ?>
                
                <div class="item-actions">
                    <button class="btn-action btn-edit" data-post-id="<?= esc_attr($item->post_id); ?>">
                        <span class="dashicons dashicons-edit"></span> Edit
                    </button>
                    <button class="btn-action btn-download" data-post-id="<?= esc_attr($item->post_id); ?>">
                        <span class="dashicons dashicons-download"></span> Download PDF
                    </button>
                    <button class="btn-action btn-share" data-post-id="<?= esc_attr($item->post_id); ?>" data-url="<?= esc_attr(get_permalink($post->ID)); ?>">
                        <span class="dashicons dashicons-email"></span> Share via Email
                    </button>
                    <button class="btn-action btn-share-link" data-url="<?= esc_attr(get_permalink($post->ID)); ?>">
                        <span class="dashicons dashicons-share"></span> Copy Link
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Estimate reading time for content
     */
    private function estimate_read_time(string $content): int {
        $word_count = str_word_count(strip_tags($content));
        return max(1, ceil($word_count / 200)); // Assume 200 words per minute
    }

    /**
     * AJAX: Update library item
     */
    public function ajax_update_library_item(): void {
        check_ajax_referer('khm_library_nonce', 'nonce');

        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$user_id || !$post_id) {
            wp_send_json_error('Invalid parameters');
        }

        $updates = [];
        
        if (isset($_POST['category_id'])) {
            $updates['category_id'] = intval($_POST['category_id']);
        }
        
        if (isset($_POST['read_status'])) {
            $updates['read_status'] = sanitize_text_field($_POST['read_status']);
        }
        
        if (isset($_POST['is_favorite'])) {
            $updates['is_favorite'] = $_POST['is_favorite'] === 'true' ? 1 : 0;
        }
        
        if (isset($_POST['notes'])) {
            $updates['notes'] = sanitize_textarea_field($_POST['notes']);
        }

        $success = $this->library->update_item($user_id, $post_id, $updates);

        if ($success) {
            wp_send_json_success('Item updated successfully');
        } else {
            wp_send_json_error('Failed to update item');
        }
    }

    /**
     * AJAX: Create new category
     */
    public function ajax_create_category(): void {
        check_ajax_referer('khm_library_nonce', 'nonce');

        $user_id = get_current_user_id();
        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $privacy = sanitize_text_field($_POST['privacy'] ?? 'private');

        if (!$user_id || !$category_name) {
            wp_send_json_error('Invalid parameters');
        }

        $category_id = $this->library->create_category($user_id, $category_name, $privacy);

        if ($category_id) {
            wp_send_json_success([
                'category_id' => $category_id,
                'category_name' => $category_name,
                'privacy' => $privacy
            ]);
        } else {
            wp_send_json_error('Failed to create category');
        }
    }

    /**
     * AJAX: Delete library item
     */
    public function ajax_delete_library_item(): void {
        check_ajax_referer('khm_library_nonce', 'nonce');

        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$user_id || !$post_id) {
            wp_send_json_error('Invalid parameters');
        }

        $success = $this->library->remove_from_library($user_id, $post_id);

        if ($success) {
            // Get updated stats
            $stats = $this->library->get_library_stats($user_id);
            wp_send_json_success([
                'message' => 'Item removed from library',
                'stats' => $stats
            ]);
        } else {
            wp_send_json_error('Failed to remove item');
        }
    }

    /**
     * AJAX: Share library article via email
     */
    public function ajax_share_library_article(): void {
        check_ajax_referer('khm_library_nonce', 'nonce');

        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id'] ?? 0);
        $recipient_email = sanitize_email($_POST['recipient_email'] ?? '');
        $personal_message = sanitize_textarea_field($_POST['personal_message'] ?? '');
        $include_notes = $_POST['include_notes'] === 'true';
        $include_membership_info = $_POST['include_membership_info'] === 'true';

        if (!$user_id || !$post_id || !$recipient_email) {
            wp_send_json_error('Invalid parameters');
        }

        // Validate email
        if (!is_email($recipient_email)) {
            wp_send_json_error('Invalid email address');
        }

        // Call the marketing suite service to send the email
        $success = khm_call_service(
            'share_library_article',
            $user_id,
            $post_id,
            $recipient_email,
            $personal_message,
            $include_notes,
            $include_membership_info
        );

        if ($success) {
            wp_send_json_success('Article shared successfully!');
        } else {
            wp_send_json_error('Failed to share article. Please try again.');
        }
    }
}
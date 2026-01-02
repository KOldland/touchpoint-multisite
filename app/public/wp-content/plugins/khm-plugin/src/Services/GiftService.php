<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\EmailService;

/**
 * Gift Service
 * 
 * Provides gift functionality for the KHM membership system.
 * Handles gift purchase, email delivery, and token-based redemption.
 */
class GiftService {

    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private EmailService $email;
    private string $gifts_table;
    private string $redemptions_table;

    public function __construct(MembershipRepository $memberships, OrderRepository $orders, EmailService $email) {
        global $wpdb;
        $this->memberships = $memberships;
        $this->orders = $orders;
        $this->email = $email;
        $this->gifts_table = $wpdb->prefix . 'khm_gifts';
        $this->redemptions_table = $wpdb->prefix . 'khm_gift_redemptions';
    }

    /**
     * Create database tables for gift functionality
     */
    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Gifts table
        $gifts_sql = "CREATE TABLE {$this->gifts_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            sender_id int(11) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255) NOT NULL,
            sender_name varchar(255) NOT NULL,
            sender_email varchar(255) NOT NULL,
            gift_message text,
            gift_price decimal(10,2) NOT NULL,
            member_discount decimal(10,2) DEFAULT 0.00,
            order_id int(11) DEFAULT NULL,
            redemption_token varchar(255) NOT NULL,
            payment_method varchar(50) DEFAULT 'stripe',
            transaction_id varchar(255) DEFAULT NULL,
            status enum('pending','sent','redeemed','expired','cancelled') DEFAULT 'pending',
            email_sent_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_token (redemption_token),
            KEY idx_sender_id (sender_id),
            KEY idx_recipient_email (recipient_email),
            KEY idx_post_id (post_id),
            KEY idx_order_id (order_id),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";

        // Gift redemptions table
        $redemptions_sql = "CREATE TABLE {$this->redemptions_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            gift_id int(11) NOT NULL,
            recipient_user_id int(11) DEFAULT NULL,
            redemption_type enum('download','library_save','both') NOT NULL,
            redeemed_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            PRIMARY KEY (id),
            KEY idx_gift_id (gift_id),
            KEY idx_recipient_user_id (recipient_user_id),
            KEY idx_redeemed_at (redeemed_at),
            FOREIGN KEY (gift_id) REFERENCES {$this->gifts_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($gifts_sql);
        dbDelta($redemptions_sql);
    }

    /**
     * Create a gift purchase
     *
     * @param array $gift_data Gift information
     * @return array Success/error response
     */
    public function create_gift(array $gift_data): array {
        global $wpdb;

        try {
            // Validate required fields
            $required_fields = ['post_id', 'sender_id', 'recipient_email', 'recipient_name', 'sender_name', 'gift_price'];
            foreach ($required_fields as $field) {
                if (!isset($gift_data[$field]) || empty($gift_data[$field])) {
                    return [
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ];
                }
            }

            // Validate post exists
            $post = get_post($gift_data['post_id']);
            if (!$post || $post->post_status !== 'publish') {
                return [
                    'success' => false,
                    'error' => 'Invalid article'
                ];
            }

            // Validate emails
            if (!is_email($gift_data['recipient_email']) || !is_email($gift_data['sender_email'] ?? '')) {
                return [
                    'success' => false,
                    'error' => 'Invalid email address'
                ];
            }

            // Generate unique redemption token
            $redemption_token = $this->generate_redemption_token();

            // Set expiration (30 days from now)
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Insert gift record
            $gift_record = [
                'post_id' => intval($gift_data['post_id']),
                'sender_id' => intval($gift_data['sender_id']),
                'recipient_email' => sanitize_email($gift_data['recipient_email']),
                'recipient_name' => sanitize_text_field($gift_data['recipient_name']),
                'sender_name' => sanitize_text_field($gift_data['sender_name']),
                'sender_email' => sanitize_email($gift_data['sender_email'] ?? ''),
                'gift_message' => sanitize_textarea_field($gift_data['gift_message'] ?? ''),
                'gift_price' => floatval($gift_data['gift_price']),
                'member_discount' => floatval($gift_data['member_discount'] ?? 0),
                'redemption_token' => $redemption_token,
                'payment_method' => sanitize_text_field($gift_data['payment_method'] ?? 'stripe'),
                'expires_at' => $expires_at,
                'status' => 'pending'
            ];

            $result = $wpdb->insert($this->gifts_table, $gift_record);

            if ($result === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to create gift record'
                ];
            }

            $gift_id = $wpdb->insert_id;

            return [
                'success' => true,
                'gift_id' => $gift_id,
                'redemption_token' => $redemption_token,
                'expires_at' => $expires_at
            ];

        } catch (Exception $e) {
            error_log('Gift creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create gift'
            ];
        }
    }

    /**
     * Send gift email notification
     *
     * @param int $gift_id Gift ID
     * @return array Success/error response
     */
    public function send_gift_email(int $gift_id): array {
        global $wpdb;

        try {
            // Get gift details
            $gift = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->gifts_table} WHERE id = %d",
                $gift_id
            ));

            if (!$gift) {
                return [
                    'success' => false,
                    'error' => 'Gift not found'
                ];
            }

            // Get post details
            $post = get_post($gift->post_id);
            if (!$post) {
                return [
                    'success' => false,
                    'error' => 'Article not found'
                ];
            }

            // Build redemption URL
            $redemption_url = home_url('/gift-redemption/?token=' . $gift->redemption_token);

            // Prepare email data
            $email_data = [
                'recipient_name' => $gift->recipient_name,
                'sender_name' => $gift->sender_name,
                'post_title' => $post->post_title,
                'post_excerpt' => get_the_excerpt($post),
                'gift_message_section' => !empty($gift->gift_message) 
                    ? '<div style="background: #e8f4f8; padding: 15px; border-radius: 6px; margin: 20px 0;"><p style="margin: 0; font-style: italic; color: #495057;">"' . esc_html($gift->gift_message) . '"</p><p style="margin: 10px 0 0 0; font-size: 14px; color: #6c757d;">— ' . esc_html($gift->sender_name) . '</p></div>'
                    : '',
                'redemption_url' => $redemption_url,
                'expires_at' => date('F j, Y', strtotime($gift->expires_at)),
                'site_name' => get_bloginfo('name'),
                'site_url' => home_url()
            ];

            // Send email using EmailService
            $email_result = $this->email
                ->setSubject('Gift Article: ' . $post->post_title . ' - from ' . $gift->sender_name)
                ->setFrom($gift->sender_email, $gift->sender_name)
                ->send('gift_notification', $gift->recipient_email, $email_data);

            if ($email_result) {
                // Update gift status and email sent timestamp
                $wpdb->update(
                    $this->gifts_table,
                    [
                        'status' => 'sent',
                        'email_sent_at' => current_time('mysql')
                    ],
                    ['id' => $gift_id]
                );

                return [
                    'success' => true,
                    'message' => 'Gift email sent successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to send gift email'
                ];
            }

        } catch (Exception $e) {
            error_log('Gift email error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send gift email'
            ];
        }
    }

    /**
     * Redeem a gift using token
     *
     * @param string $token Redemption token
     * @param string $redemption_type Type of redemption (download|library_save|both)
     * @param int $user_id Optional user ID for logged-in users
     * @return array Success/error response
     */
    public function redeem_gift(string $token, string $redemption_type = 'download', int $user_id = 0): array {
        global $wpdb;

        try {
            // Get gift by token
            $gift = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->gifts_table} WHERE redemption_token = %s",
                $token
            ));

            if (!$gift) {
                return [
                    'success' => false,
                    'error' => 'Invalid gift token'
                ];
            }

            // Check if already redeemed
            if ($gift->status === 'redeemed') {
                return [
                    'success' => false,
                    'error' => 'Gift has already been redeemed'
                ];
            }

            // Check if expired
            if (strtotime($gift->expires_at) < time()) {
                // Update status to expired
                $wpdb->update(
                    $this->gifts_table,
                    ['status' => 'expired'],
                    ['id' => $gift->id]
                );

                return [
                    'success' => false,
                    'error' => 'Gift has expired'
                ];
            }

            // Validate redemption type
            if (!in_array($redemption_type, ['download', 'library_save', 'both'])) {
                $redemption_type = 'download';
            }

            $response = [
                'success' => true,
                'gift_id' => $gift->id,
                'post_id' => $gift->post_id,
                'redemption_type' => $redemption_type
            ];

            // Handle PDF download
            if (in_array($redemption_type, ['download', 'both'])) {
                $pdf_result = $this->generate_gift_pdf($gift->post_id, $user_id);
                if ($pdf_result['success']) {
                    $response['download_url'] = $pdf_result['download_url'];
                    $response['filename'] = $pdf_result['filename'];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Failed to generate PDF: ' . $pdf_result['error']
                    ];
                }
            }

            // Handle library save (only if user is logged in)
            if (in_array($redemption_type, ['library_save', 'both']) && $user_id > 0) {
                $library_result = $this->save_gift_to_library($gift->post_id, $user_id);
                if ($library_result['success']) {
                    $response['saved_to_library'] = true;
                } else {
                    $response['library_error'] = $library_result['error'];
                }
            }

            // Record redemption
            $redemption_record = [
                'gift_id' => $gift->id,
                'recipient_user_id' => $user_id > 0 ? $user_id : null,
                'redemption_type' => $redemption_type,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];

            $wpdb->insert($this->redemptions_table, $redemption_record);

            // Update gift status to redeemed
            $wpdb->update(
                $this->gifts_table,
                ['status' => 'redeemed'],
                ['id' => $gift->id]
            );

            return $response;

        } catch (Exception $e) {
            error_log('Gift redemption error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to redeem gift'
            ];
        }
    }

    /**
     * Get gift details by token (for redemption page)
     *
     * @param string $token Redemption token
     * @return array|null Gift details or null if not found
     */
    public function get_gift_by_token(string $token): ?array {
        global $wpdb;

        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT g.*, p.post_title, p.post_excerpt 
             FROM {$this->gifts_table} g 
             LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID 
             WHERE g.redemption_token = %s",
            $token
        ), ARRAY_A);

        if (!$gift) {
            return null;
        }

        // Add computed fields
        $gift['is_expired'] = strtotime($gift['expires_at']) < time();
        $gift['is_redeemed'] = $gift['status'] === 'redeemed';
        $gift['days_until_expiry'] = max(0, ceil((strtotime($gift['expires_at']) - time()) / DAY_IN_SECONDS));

        return $gift;
    }

    /**
     * Generate unique redemption token
     *
     * @return string Unique token
     */
    private function generate_redemption_token(): string {
        global $wpdb;

        do {
            $token = wp_generate_password(32, false);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->gifts_table} WHERE redemption_token = %s",
                $token
            ));
        } while ($exists > 0);

        return $token;
    }

    /**
     * Generate PDF for gift redemption
     *
     * @param int $post_id Post ID
     * @param int $user_id User ID (0 for anonymous)
     * @return array Success/error response with download URL
     */
    private function generate_gift_pdf(int $post_id, int $user_id): array {
        // Use existing PDF service
        if (function_exists('khm_generate_article_pdf')) {
            $pdf_result = khm_generate_article_pdf($post_id, $user_id);
            
            if ($pdf_result['success']) {
                // Create secure download URL that doesn't require credits
                $download_url = khm_create_download_url($post_id, $user_id, 2); // 2-hour expiry
                
                return [
                    'success' => true,
                    'download_url' => $download_url,
                    'filename' => $pdf_result['filename'] ?? get_the_title($post_id) . '.pdf'
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'PDF generation service not available'
        ];
    }

    /**
     * Save gift article to user's library
     *
     * @param int $post_id Post ID
     * @param int $user_id User ID
     * @return array Success/error response
     */
    private function save_gift_to_library(int $post_id, int $user_id): array {
        // Use existing library service
        if (function_exists('khm_call_service')) {
            try {
                $result = khm_call_service('save_to_library', $user_id, $post_id);
                
                if ($result) {
                    return [
                        'success' => true,
                        'message' => 'Article saved to library'
                    ];
                }
            } catch (Exception $e) {
                // Service call failed
            }
        }

        return [
            'success' => false,
            'error' => 'Library service not available'
        ];
    }

    /**
     * Get gift statistics for admin
     *
     * @param array $filters Optional filters
     * @return array Statistics
     */
    public function get_gift_statistics(array $filters = []): array {
        global $wpdb;

        $where_clauses = [];
        $where_values = [];

        // Apply date filters
        if (!empty($filters['start_date'])) {
            $where_clauses[] = "created_at >= %s";
            $where_values[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where_clauses[] = "created_at <= %s";
            $where_values[] = $filters['end_date'];
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $query = "SELECT 
                    COUNT(*) as total_gifts,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_gifts,
                    SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END) as redeemed_gifts,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_gifts,
                    SUM(gift_price) as total_revenue,
                    AVG(gift_price) as average_gift_value
                  FROM {$this->gifts_table} {$where_sql}";

        if (!empty($where_values)) {
            $stats = $wpdb->get_row($wpdb->prepare($query, ...$where_values), ARRAY_A);
        } else {
            $stats = $wpdb->get_row($query, ARRAY_A);
        }

        // Calculate redemption rate
        $stats['redemption_rate'] = $stats['sent_gifts'] > 0 
            ? round(($stats['redeemed_gifts'] / $stats['sent_gifts']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Get gifts sent by a user
     *
     * @param int $user_id Sender user ID
     * @param int $limit Number of gifts to return
     * @param int $offset Pagination offset
     * @return array List of sent gifts
     */
    public function get_sent_gifts(int $user_id, int $limit = 20, int $offset = 0): array {
        global $wpdb;

        $query = "SELECT g.*, p.post_title, p.post_excerpt
                  FROM {$this->gifts_table} g
                  LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID
                  WHERE g.sender_id = %d
                  ORDER BY g.created_at DESC
                  LIMIT %d OFFSET %d";

        $gifts = $wpdb->get_results(
            $wpdb->prepare($query, $user_id, $limit, $offset),
            ARRAY_A
        );

        // Add formatted data for each gift
        foreach ($gifts as &$gift) {
            $gift['is_expired'] = $this->is_gift_expired($gift);
            $gift['days_until_expiry'] = $this->get_days_until_expiry($gift);
            $gift['formatted_created_date'] = date('F j, Y', strtotime($gift['created_at']));
            $gift['formatted_expires_date'] = date('F j, Y', strtotime($gift['expires_at']));
        }

        return $gifts;
    }

    /**
     * Get gifts received by an email address
     *
     * @param string $email Recipient email
     * @param int $limit Number of gifts to return
     * @param int $offset Pagination offset
     * @return array List of received gifts
     */
    public function get_received_gifts(string $email, int $limit = 20, int $offset = 0): array {
        global $wpdb;

        $query = "SELECT g.*, p.post_title, p.post_excerpt, u.display_name as sender_name
                  FROM {$this->gifts_table} g
                  LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID
                  LEFT JOIN {$wpdb->users} u ON g.sender_id = u.ID
                  WHERE g.recipient_email = %s
                  ORDER BY g.created_at DESC
                  LIMIT %d OFFSET %d";

        $gifts = $wpdb->get_results(
            $wpdb->prepare($query, $email, $limit, $offset),
            ARRAY_A
        );

        // Add formatted data for each gift
        foreach ($gifts as &$gift) {
            $gift['is_expired'] = $this->is_gift_expired($gift);
            $gift['is_redeemed'] = ($gift['status'] === 'redeemed');
            $gift['days_until_expiry'] = $this->get_days_until_expiry($gift);
            $gift['formatted_created_date'] = date('F j, Y', strtotime($gift['created_at']));
            $gift['formatted_expires_date'] = date('F j, Y', strtotime($gift['expires_at']));
        }

        return $gifts;
    }

    /**
     * Check if a gift is expired
     *
     * @param array $gift Gift data
     * @return bool True if expired
     */
    private function is_gift_expired(array $gift): bool {
        return strtotime($gift['expires_at']) < time();
    }

    /**
     * Get days until gift expiry
     *
     * @param array $gift Gift data
     * @return int Days until expiry (negative if expired)
     */
    private function get_days_until_expiry(array $gift): int {
        $expires_timestamp = strtotime($gift['expires_at']);
        $current_timestamp = time();
        $diff = $expires_timestamp - $current_timestamp;
        
        return (int) floor($diff / (24 * 60 * 60));
    }
}
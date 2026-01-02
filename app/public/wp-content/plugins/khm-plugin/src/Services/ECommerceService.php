<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;

/**
 * ECommerce Service
 * 
 * Provides shopping cart and purchase functionality for the KHM membership system.
 * Handles article pricing, cart management, and purchase processing.
 */
class ECommerceService {

    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private string $products_table;
    private string $cart_table;
    private string $purchases_table;

    public function __construct(MembershipRepository $memberships, OrderRepository $orders) {
        global $wpdb;
        $this->memberships = $memberships;
        $this->orders = $orders;
        $this->products_table = $wpdb->prefix . 'khm_article_products';
        $this->cart_table = $wpdb->prefix . 'khm_shopping_cart';
        $this->purchases_table = $wpdb->prefix . 'khm_purchases';
    }

    /**
     * Create database tables for eCommerce functionality
     */
    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Article products table
        $products_sql = "CREATE TABLE {$this->products_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            regular_price decimal(10,2) NOT NULL DEFAULT 5.99,
            member_price decimal(10,2) DEFAULT NULL,
            member_discount_percent int(11) DEFAULT 20,
            is_purchasable tinyint(1) DEFAULT 1,
            purchase_gives_pdf tinyint(1) DEFAULT 1,
            purchase_saves_to_library tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post (post_id),
            KEY idx_price (regular_price),
            KEY idx_purchasable (is_purchasable)
        ) $charset_collate;";

        // Shopping cart table
        $cart_sql = "CREATE TABLE {$this->cart_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            post_id int(11) NOT NULL,
            quantity int(11) DEFAULT 1,
            price decimal(10,2) NOT NULL,
            member_price decimal(10,2) DEFAULT NULL,
            session_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_post (user_id, post_id),
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Purchases table
        $purchases_sql = "CREATE TABLE {$this->purchases_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            post_id int(11) NOT NULL,
            order_id int(11) DEFAULT NULL,
            purchase_price decimal(10,2) NOT NULL,
            member_discount decimal(10,2) DEFAULT 0.00,
            payment_method varchar(50) DEFAULT 'stripe',
            transaction_id varchar(255) DEFAULT NULL,
            status enum('pending','completed','failed','refunded') DEFAULT 'pending',
            pdf_downloaded tinyint(1) DEFAULT 0,
            saved_to_library tinyint(1) DEFAULT 0,
            purchase_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_post_id (post_id),
            KEY idx_order_id (order_id),
            KEY idx_status (status),
            KEY idx_transaction_id (transaction_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($products_sql);
        dbDelta($cart_sql);
        dbDelta($purchases_sql);

        // Set default pricing for existing posts
        $this->set_default_pricing_for_existing_posts();
    }

    /**
     * Get article pricing information
     */
    public function get_article_pricing(int $post_id, int $user_id = null): array {
        global $wpdb;

        // Check if post has custom pricing
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->products_table} WHERE post_id = %d",
            $post_id
        ));

        if (!$product) {
            // Create default product entry
            $product = $this->create_default_product($post_id);
        }

        $regular_price = (float) $product->regular_price;
        $member_discount_percent = (int) $product->member_discount_percent;
        
        // Calculate member price
        $member_price = $product->member_price;
        if ($member_price === null) {
            $member_price = $regular_price * (1 - ($member_discount_percent / 100));
        }

        // Determine which price applies to this user
        $current_price = $regular_price;
        $discount_amount = 0;
        $is_member = false;

        if ($user_id) {
            $membership = $this->memberships->findActive($user_id);
            if (!empty($membership)) {
                $is_member = true;
                $current_price = $member_price;
                $discount_amount = $regular_price - $member_price;
            }
        }

        return [
            'post_id' => $post_id,
            'regular_price' => $regular_price,
            'member_price' => (float) $member_price,
            'current_price' => $current_price,
            'discount_amount' => $discount_amount,
            'discount_percent' => $member_discount_percent,
            'is_member' => $is_member,
            'is_purchasable' => (bool) $product->is_purchasable,
            'purchase_gives_pdf' => (bool) $product->purchase_gives_pdf,
            'purchase_saves_to_library' => (bool) $product->purchase_saves_to_library
        ];
    }

    /**
     * Add item to shopping cart
     */
    public function add_to_cart(int $user_id, int $post_id, int $quantity = 1): bool {
        global $wpdb;

        // Get pricing for this user
        $pricing = $this->get_article_pricing($post_id, $user_id);
        
        if (!$pricing['is_purchasable']) {
            return false;
        }

        // Check if user has already purchased this article
        if ($this->has_purchased($user_id, $post_id)) {
            return false; // Already purchased
        }

        // Get session ID for logged-out users
        $session_id = $user_id ? null : $this->get_session_id();

        $result = $wpdb->replace(
            $this->cart_table,
            [
                'user_id' => $user_id,
                'post_id' => $post_id,
                'quantity' => $quantity,
                'price' => $pricing['regular_price'],
                'member_price' => $pricing['member_price'],
                'session_id' => $session_id,
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%f', '%f', '%s', '%s']
        );

        if ($result) {
            do_action('khm_item_added_to_cart', $user_id, $post_id, $quantity);
            return true;
        }

        return false;
    }

    /**
     * Remove item from shopping cart
     */
    public function remove_from_cart(int $user_id, int $post_id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->cart_table,
            [
                'user_id' => $user_id,
                'post_id' => $post_id
            ],
            ['%d', '%d']
        );

        if ($result) {
            do_action('khm_item_removed_from_cart', $user_id, $post_id);
            return true;
        }

        return false;
    }

    /**
     * Get user's shopping cart
     */
    public function get_cart(int $user_id): array {
        global $wpdb;

        $cart_items = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.post_title, p.post_excerpt
             FROM {$this->cart_table} c
             LEFT JOIN {$wpdb->posts} p ON c.post_id = p.ID
             WHERE c.user_id = %d
             ORDER BY c.created_at DESC",
            $user_id
        ));

        $total = 0;
        $member_total = 0;
        $items = [];

        foreach ($cart_items as $item) {
            $pricing = $this->get_article_pricing($item->post_id, $user_id);
            
            $items[] = [
                'id' => $item->id,
                'post_id' => $item->post_id,
                'title' => $item->post_title,
                'excerpt' => $item->post_excerpt,
                'quantity' => $item->quantity,
                'regular_price' => $pricing['regular_price'],
                'member_price' => $pricing['member_price'],
                'current_price' => $pricing['current_price'],
                'line_total' => $pricing['current_price'] * $item->quantity,
                'discount_amount' => $pricing['discount_amount'],
                'is_member_price' => $pricing['is_member']
            ];

            $total += $pricing['regular_price'] * $item->quantity;
            $member_total += $pricing['current_price'] * $item->quantity;
        }

        return [
            'items' => $items,
            'item_count' => count($items),
            'subtotal' => $total,
            'member_subtotal' => $member_total,
            'total_discount' => $total - $member_total,
            'currency' => 'GBP'
        ];
    }

    /**
     * Get cart count for user
     */
    public function get_cart_count(int $user_id): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->cart_table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Clear user's shopping cart
     */
    public function clear_cart(int $user_id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->cart_table,
            ['user_id' => $user_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Process purchase
     */
    public function process_purchase(int $user_id, array $purchase_data): array {
        global $wpdb;

        $cart = $this->get_cart($user_id);
        
        if (empty($cart['items'])) {
            return ['success' => false, 'error' => 'Cart is empty'];
        }

        // Create order
        $order_data = [
            'user_id' => $user_id,
            'total' => $cart['member_subtotal'],
            'currency' => 'GBP',
            'status' => 'pending',
            'item_type' => 'article_purchase',
            'items' => $cart['items']
        ];

        $order = $this->orders->create($order_data);
        
        if (!$order) {
            return ['success' => false, 'error' => 'Failed to create order'];
        }

        // Process each item
        $purchased_items = [];
        $failed_items = [];

        foreach ($cart['items'] as $item) {
            $purchase_result = $this->create_purchase_record(
                $user_id,
                $item['post_id'],
                $order->id,
                $item['current_price'],
                $item['discount_amount'],
                $purchase_data
            );

            if ($purchase_result) {
                $purchased_items[] = $item;
                
                // Auto-process based on product settings
                $this->auto_process_purchase($user_id, $item['post_id'], $purchase_data);
            } else {
                $failed_items[] = $item;
            }
        }

        if (!empty($purchased_items)) {
            // Clear cart
            $this->clear_cart($user_id);
            
            // Update order status
            $this->orders->update($order->id, ['status' => 'completed']);
            
            do_action('khm_purchase_completed', $user_id, $purchased_items, $order);
        }

        return [
            'success' => !empty($purchased_items),
            'order_id' => $order->id,
            'purchased_items' => $purchased_items,
            'failed_items' => $failed_items,
            'total' => $cart['member_subtotal']
        ];
    }

    /**
     * Check if user has purchased an article
     */
    public function has_purchased(int $user_id, int $post_id): bool {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->purchases_table} 
             WHERE user_id = %d AND post_id = %d AND status = 'completed'",
            $user_id,
            $post_id
        ));

        return $count > 0;
    }

    /**
     * Get user's purchase history
     */
    public function get_purchase_history(int $user_id, array $args = []): array {
        global $wpdb;

        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = array_merge($defaults, $args);

        $order_clause = sprintf(
            "ORDER BY %s %s",
            sanitize_sql_orderby($args['orderby']),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $purchases = $wpdb->get_results($wpdb->prepare(
            "SELECT pr.*, p.post_title, p.post_excerpt
             FROM {$this->purchases_table} pr
             LEFT JOIN {$wpdb->posts} p ON pr.post_id = p.ID
             WHERE pr.user_id = %d
             {$order_clause}
             LIMIT %d OFFSET %d",
            $user_id,
            $args['limit'],
            $args['offset']
        ));

        return $purchases ?: [];
    }

    /**
     * Set product pricing
     */
    public function set_article_pricing(int $post_id, array $pricing): bool {
        global $wpdb;

        $data = [
            'post_id' => $post_id,
            'regular_price' => $pricing['regular_price'] ?? 5.99,
            'member_price' => $pricing['member_price'] ?? null,
            'member_discount_percent' => $pricing['member_discount_percent'] ?? 20,
            'is_purchasable' => $pricing['is_purchasable'] ?? 1,
            'purchase_gives_pdf' => $pricing['purchase_gives_pdf'] ?? 1,
            'purchase_saves_to_library' => $pricing['purchase_saves_to_library'] ?? 1,
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->replace(
            $this->products_table,
            $data,
            ['%d', '%f', '%f', '%d', '%d', '%d', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Create default product entry for a post
     */
    private function create_default_product(int $post_id): object {
        global $wpdb;

        $default_price = get_post_meta($post_id, '_article_price', true) ?: 5.99;

        $data = [
            'post_id' => $post_id,
            'regular_price' => $default_price,
            'member_discount_percent' => 20,
            'is_purchasable' => 1,
            'purchase_gives_pdf' => 1,
            'purchase_saves_to_library' => 1
        ];

        $wpdb->insert($this->products_table, $data, ['%d', '%f', '%d', '%d', '%d', '%d']);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->products_table} WHERE post_id = %d",
            $post_id
        ));
    }

    /**
     * Create purchase record
     */
    private function create_purchase_record(int $user_id, int $post_id, int $order_id, float $price, float $discount, array $purchase_data): bool {
        global $wpdb;

        $result = $wpdb->insert(
            $this->purchases_table,
            [
                'user_id' => $user_id,
                'post_id' => $post_id,
                'order_id' => $order_id,
                'purchase_price' => $price,
                'member_discount' => $discount,
                'payment_method' => $purchase_data['payment_method'] ?? 'stripe',
                'transaction_id' => $purchase_data['transaction_id'] ?? null,
                'status' => 'completed',
                'purchase_data' => json_encode($purchase_data)
            ],
            ['%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Auto-process purchase (PDF download, save to library)
     */
    private function auto_process_purchase(int $user_id, int $post_id, array $purchase_data): void {
        $product = $this->get_article_pricing($post_id, $user_id);

        // Auto-save to library if enabled
        if ($product['purchase_saves_to_library'] && $purchase_data['auto_save_to_library'] ?? true) {
            if (function_exists('khm_call_service')) {
                khm_call_service('save_to_library', $user_id, $post_id);
                
                // Update purchase record
                global $wpdb;
                $wpdb->update(
                    $this->purchases_table,
                    ['saved_to_library' => 1],
                    ['user_id' => $user_id, 'post_id' => $post_id],
                    ['%d'],
                    ['%d', '%d']
                );
            }
        }

        // Auto-download PDF if requested
        if ($product['purchase_gives_pdf'] && $purchase_data['auto_download_pdf'] ?? false) {
            // This would trigger PDF generation and download
            do_action('khm_auto_download_purchased_pdf', $user_id, $post_id);
        }
    }

    /**
     * Set default pricing for existing posts
     */
    private function set_default_pricing_for_existing_posts(): void {
        global $wpdb;

        // Get all published posts that don't have pricing set
        $posts = $wpdb->get_results(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$this->products_table} pr ON p.ID = pr.post_id
             WHERE p.post_status = 'publish' 
             AND p.post_type = 'post'
             AND pr.post_id IS NULL
             LIMIT 100"
        );

        foreach ($posts as $post) {
            $this->create_default_product($post->ID);
        }
    }

    /**
     * Get session ID for cart persistence
     */
    private function get_session_id(): string {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    /**
     * Get shopping cart statistics
     */
    public function get_cart_stats(): array {
        global $wpdb;

        $total_carts = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->cart_table}");
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->cart_table}");
        $total_value = $wpdb->get_var("SELECT SUM(price * quantity) FROM {$this->cart_table}");

        return [
            'total_carts' => (int) $total_carts,
            'total_items' => (int) $total_items,
            'total_value' => (float) $total_value,
            'average_cart_value' => $total_carts > 0 ? ($total_value / $total_carts) : 0
        ];
    }

    /**
     * Clean up abandoned carts
     */
    public function cleanup_abandoned_carts(int $days_old = 30): int {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->cart_table} WHERE created_at < %s",
            $cutoff_date
        ));

        return (int) $deleted;
    }
}
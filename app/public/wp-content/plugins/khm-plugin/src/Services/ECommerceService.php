<?php

namespace KHM\Services;

use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;

/**
 * ECommerce Service
 *
 * Provides article purchase and cart functionality.
 * Handles pricing, session-based cart, checkout, and purchase history.
 */
class ECommerceService {

    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private string $purchases_table;

    public function __construct(MembershipRepository $memberships, OrderRepository $orders) {
        global $wpdb;
        $this->memberships = $memberships;
        $this->orders = $orders;
        $this->purchases_table = $wpdb->prefix . 'khm_purchases';

        if (!session_id() && !headers_sent()) {
    session_save_path(sys_get_temp_dir() . '/khm_sessions');
    if (!is_dir(session_save_path())) {
        mkdir(session_save_path(), 0700, true);
    }
    session_start();
}
    }


    /**
     * Get pricing for a single article.
     */
    public function get_article_pricing(int $post_id, ?int $user_id = null): array {
        $price = get_post_meta($post_id, 'kss_article_price', true);
        return [
            'post_id'  => $post_id,
            'price'    => $price !== '' ? (float) $price : 0.00,
            'currency' => 'GBP',
        ];
    }

    /**
     * Add an article to the session cart.
     */
    public function add_to_cart(int $user_id, int $post_id, int $quantity = 1): bool {
        if (!isset($_SESSION['khm_cart'])) {
            $_SESSION['khm_cart'] = [];
        }
        $_SESSION['khm_cart'][$post_id] = ($_SESSION['khm_cart'][$post_id] ?? 0) + $quantity;
        return true;
    }

    /**
     * Remove an article from the session cart.
     */
    public function remove_from_cart(int $user_id, int $post_id): bool {
        unset($_SESSION['khm_cart'][$post_id]);
        return true;
    }

    /**
     * Return the current cart contents with post and pricing details.
     * Stale or unpublished posts are cleaned out.
     */
    public function get_cart(int $user_id): array {
        if (empty($_SESSION['khm_cart'])) {
            return [];
        }

        $items = [];
        foreach ($_SESSION['khm_cart'] as $post_id => $qty) {
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                unset($_SESSION['khm_cart'][$post_id]);
                continue;
            }
            $pricing = $this->get_article_pricing($post_id, $user_id);
            $items[] = [
                'post_id'  => $post_id,
                'title'    => $post->post_title,
                'quantity' => $qty,
                'price'    => $pricing['price'],
                'subtotal' => $pricing['price'] * $qty,
            ];
        }
        return $items;
    }

    /**
     * Total quantity of items in the cart.
     */
    public function get_cart_count(int $user_id): int {
        return isset($_SESSION['khm_cart']) ? array_sum($_SESSION['khm_cart']) : 0;
    }

    /**
     * Empty the cart.
     */
    public function clear_cart(int $user_id): bool {
        $_SESSION['khm_cart'] = [];
        return true;
    }

    /**
     * Create a pending order from the cart contents, then return a Stripe
     * checkout URL and clear the cart.
     */
    public function process_purchase(int $user_id, array $purchase_data): array {
        $cart_items = $this->get_cart($user_id);

        if (empty($cart_items)) {
            return ['success' => false, 'error' => 'Cart is empty'];
        }

        $total = 0.0;
        $line_items = [];
        foreach ($cart_items as $item) {
            $total += $item['subtotal'];
            $line_items[] = [
                'post_id'  => $item['post_id'],
                'title'    => $item['title'],
                'quantity' => $item['quantity'],
                'price'    => $item['price'],
            ];
        }

        $order = $this->orders->create([
            'user_id'  => $user_id,
            'subtotal' => $total,
            'total'    => $total,
            'currency' => 'GBP',
            'status'   => 'pending',
            'gateway'  => 'stripe',
            'item_type' => 'article',
            'items'     => wp_json_encode($line_items),
        ]);

        if (!$order) {
            return ['success' => false, 'error' => 'Failed to create order'];
        }

        $checkout_url = add_query_arg([
            'khm_action' => 'checkout',
            'order_id'   => $order->id,
            'order_code' => $order->code,
        ], home_url('/'));

        $this->clear_cart($user_id);

        return [
            'success'      => true,
            'order_id'     => $order->id,
            'order_code'   => $order->code,
            'total'        => $total,
            'checkout_url' => $checkout_url,
        ];
    }

    /**
     * Check whether a user has already completed a purchase for an article.
     */
    public function has_purchased(int $user_id, int $post_id): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->purchases_table} 
             WHERE user_id = %d AND post_id = %d AND status = 'completed'",
            $user_id,
            $post_id
        ));
    }

    /**
     * Retrieve a user's purchase history from the purchases table.
     */
    public function get_purchase_history(int $user_id, array $args = []): array {
        global $wpdb;

        $args = array_merge([
            'limit'  => 20,
            'offset' => 0,
            'status' => null,
        ], $args);

        $where = ['p.user_id = %d'];
        $values = [$user_id];

        if ($args['status']) {
            $where[] = 'p.status = %s';
            $values[] = $args['status'];
        }

        $values[] = (int) $args['limit'];
        $values[] = (int) $args['offset'];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, posts.post_title 
             FROM {$this->purchases_table} p
             LEFT JOIN {$wpdb->posts} posts ON p.post_id = posts.ID
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.created_at DESC
             LIMIT %d OFFSET %d",
            $values
        ), ARRAY_A);

        return $results ?: [];
    }
}
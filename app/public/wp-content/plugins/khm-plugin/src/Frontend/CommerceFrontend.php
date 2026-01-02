<?php
/**
 * Commerce Frontend Handler
 * 
 * Handles AJAX requests for the unified commerce modal
 * Leverages existing CheckoutShortcode and ECommerceService
 */

namespace KHM\Frontend;

use KHM\Services\ECommerceService;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\EmailService;
use KHM\Public\CheckoutShortcode;

class CommerceFrontend {

    private ECommerceService $ecommerce;
    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private EmailService $email;
    private CheckoutShortcode $checkout;

    public function __construct() {
        $this->memberships = new MembershipRepository();
        $this->orders = new OrderRepository();
        $this->email = new EmailService(__DIR__ . '/../../');
        $this->ecommerce = new ECommerceService($this->memberships, $this->orders);
        $this->checkout = new CheckoutShortcode($this->memberships, $this->orders, $this->email);
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_khm_get_article_data', [$this, 'ajax_get_article_data']);
        add_action('wp_ajax_khm_get_cart_data', [$this, 'ajax_get_cart_data']);
        add_action('wp_ajax_khm_process_commerce_purchase', [$this, 'ajax_process_purchase']);
        add_action('wp_ajax_kss_remove_from_cart', [$this, 'ajax_remove_from_cart']);
        
        // Auto-download hook
        add_action('khm_auto_download_purchased_pdf', [$this, 'handle_auto_download'], 10, 2);
    }

    /**
     * Enqueue modal assets
     */
    public function enqueue_assets(): void {
        // Only load on pages with social strip
        if (!$this->should_load_assets()) {
            return;
        }

        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        
        wp_enqueue_script(
            'khm-commerce-modal',
            plugin_dir_url(__FILE__) . '../../assets/js/commerce-modal.js',
            ['jquery', 'stripe-js'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'khm-commerce-modal',
            plugin_dir_url(__FILE__) . '../../assets/css/commerce-modal.css',
            [],
            '1.0.0'
        );

        // Localize script with necessary data
        wp_localize_script('khm-commerce-modal', 'khm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_commerce'),
            'stripe_key' => get_option('khm_stripe_publishable_key', ''),
        ]);
    }

    /**
     * Check if we should load assets
     */
    private function should_load_assets(): bool {
        // Load if social strip is active or if specifically requested
        return function_exists('kss_get_enhanced_widget_data') || 
               apply_filters('khm_force_load_commerce_modal', false);
    }

    /**
     * Get article data for quick buy modal
     */
    public function ajax_get_article_data(): void {
        check_ajax_referer('khm_commerce', 'nonce');

        $post_id = intval($_POST['post_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$post_id || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error('Article not found');
        }

        // Get pricing
        $pricing = $this->ecommerce->get_article_pricing($post_id, $user_id);
        if (!$pricing['is_purchasable']) {
            wp_send_json_error('Article not available for purchase');
        }

        // Get user data
        $user = wp_get_current_user();

        $data = [
            'post_id' => $post_id,
            'title' => $post->post_title,
            'member_price' => $pricing['member_price'],
            'regular_price' => $pricing['regular_price'],
            'member_price_formatted' => '£' . number_format($pricing['member_price'], 2),
            'regular_price_formatted' => '£' . number_format($pricing['regular_price'], 2),
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'currency' => 'GBP'
        ];

        wp_send_json_success($data);
    }

    /**
     * Get cart data for cart review modal
     */
    public function ajax_get_cart_data(): void {
        check_ajax_referer('khm_commerce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $cart = $this->ecommerce->get_cart($user_id);

        // Format cart data for frontend
        $formatted_items = [];
        foreach ($cart['items'] as $item) {
            $post = get_post($item['post_id']);
            $formatted_items[] = [
                'post_id' => $item['post_id'],
                'title' => $post ? $post->post_title : 'Unknown Article',
                'price' => $item['current_price'],
                'price_formatted' => '£' . number_format($item['current_price'], 2),
                'quantity' => $item['quantity']
            ];
        }

        $data = [
            'items' => $formatted_items,
            'count' => $cart['item_count'],
            'subtotal' => $cart['member_subtotal'],
            'total_formatted' => '£' . number_format($cart['member_subtotal'], 2),
            'currency' => 'GBP'
        ];

        wp_send_json_success($data);
    }

    /**
     * Process commerce purchase (leverages existing checkout system)
     */
    public function ajax_process_purchase(): void {
        check_ajax_referer('khm_commerce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');
        $purchase_type = sanitize_text_field($_POST['purchase_type'] ?? 'quick-buy');
        $auto_download = isset($_POST['auto_download']) && $_POST['auto_download'] === 'true';
        $auto_save = isset($_POST['auto_save']) && $_POST['auto_save'] === 'true';

        if (!$payment_method_id) {
            wp_send_json_error('Payment method required');
        }

        try {
            // For quick buy, add item to cart first
            if ($purchase_type === 'quick-buy') {
                $post_id = intval($_POST['post_id'] ?? 0);
                if (!$post_id) {
                    wp_send_json_error('Article ID required for quick buy');
                }

                // Clear cart and add this item
                $this->ecommerce->clear_cart($user_id);
                $this->ecommerce->add_to_cart($user_id, $post_id);
            }

            // Process the purchase using ECommerceService
            $purchase_data = [
                'payment_method_id' => $payment_method_id,
                'auto_download_pdf' => $auto_download,
                'auto_save_to_library' => $auto_save,
                'billing_name' => sanitize_text_field($_POST['billing_name'] ?? ''),
                'billing_email' => sanitize_email($_POST['billing_email'] ?? ''),
            ];

            $result = $this->ecommerce->process_purchase($user_id, $purchase_data);

            if ($result['success']) {
                // Prepare success response
                $response_data = [
                    'message' => 'Purchase completed successfully!',
                    'order_id' => $result['order_id'] ?? null,
                    'download_urls' => []
                ];

                // Handle auto-download
                if ($auto_download && !empty($result['purchased_items'])) {
                    foreach ($result['purchased_items'] as $item) {
                        $download_result = $this->generate_download_url($item['post_id'], $user_id);
                        if ($download_result['success']) {
                            $response_data['download_urls'][] = $download_result['download_url'];
                        }
                    }
                }

                wp_send_json_success($response_data);
            } else {
                wp_send_json_error($result['error'] ?? 'Purchase failed');
            }

        } catch (\Exception $e) {
            error_log('Commerce purchase error: ' . $e->getMessage());
            wp_send_json_error('Payment processing failed. Please try again.');
        }
    }

    /**
     * Remove item from cart
     */
    public function ajax_remove_from_cart(): void {
        check_ajax_referer('khm_commerce', 'nonce');

        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$user_id || !$post_id) {
            wp_send_json_error('Invalid parameters');
        }

        $result = $this->ecommerce->remove_from_cart($user_id, $post_id);

        if ($result) {
            $cart_count = $this->ecommerce->get_cart_count($user_id);
            wp_send_json_success([
                'message' => 'Item removed from cart',
                'cart_count' => $cart_count
            ]);
        } else {
            wp_send_json_error('Failed to remove item');
        }
    }

    /**
     * Handle auto-download for purchased articles
     */
    public function handle_auto_download(int $user_id, int $post_id): void {
        $download_result = $this->generate_download_url($post_id, $user_id);
        
        if ($download_result['success']) {
            // Could trigger email with download link or other notifications
            do_action('khm_auto_download_ready', $user_id, $post_id, $download_result['download_url']);
        }
    }

    /**
     * Generate secure download URL for purchased article
     */
    private function generate_download_url(int $post_id, int $user_id): array {
        try {
            if (function_exists('khm_call_service')) {
                $pdf_result = khm_call_service('generate_article_pdf', $post_id, $user_id);
                
                if ($pdf_result['success']) {
                    $download_url = khm_call_service('create_download_url', $post_id, $user_id, 24);
                    
                    return [
                        'success' => true,
                        'download_url' => $download_url,
                        'pdf_path' => $pdf_result['file_path']
                    ];
                }
            }
            
            return ['success' => false, 'error' => 'PDF generation failed'];
        } catch (\Exception $e) {
            error_log('Download URL generation error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Download generation failed'];
        }
    }
}
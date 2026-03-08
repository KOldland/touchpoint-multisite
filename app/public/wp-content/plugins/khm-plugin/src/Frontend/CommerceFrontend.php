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
use KHM\Services\DiscountCodeService;
use KHM\Public\CheckoutShortcode;
use KHM\Gateways\StripeGateway;

class CommerceFrontend {

    private static bool $booted = false;

    private ECommerceService $ecommerce;
    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private EmailService $email;
    private DiscountCodeService $discounts;
    private CheckoutShortcode $checkout;

    public function __construct() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $this->memberships = new MembershipRepository();
        $this->orders = new OrderRepository();
        $this->email = new EmailService(__DIR__ . '/../../');
        $this->discounts = new DiscountCodeService();
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
        add_action('wp_ajax_khm_create_commerce_intent', [$this, 'ajax_create_intent']);
        add_action('wp_ajax_khm_process_commerce_purchase', [$this, 'ajax_process_purchase']);
        add_action('wp_ajax_khm_finalize_commerce_purchase', [$this, 'ajax_finalize_purchase']);
        add_action('wp_ajax_khm_apply_promo_code', [$this, 'ajax_apply_promo_code']);
        add_action('wp_ajax_khm_remove_promo_code', [$this, 'ajax_remove_promo_code']);
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
        wp_localize_script('khm-commerce-modal', 'khmCommerce', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_commerce'),
            'stripe_key' => get_option('khm_stripe_publishable_key', ''),
        ]);
    }

    /**
     * Check if we should load assets
     */
    private function should_load_assets(): bool {
        // Load on singular content to support buy buttons, or when explicitly forced.
        return is_singular() ||
               function_exists('kss_get_enhanced_widget_data') ||
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
            'image_url' => get_the_post_thumbnail_url($post_id, 'large') ?: '',
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
            'discount' => 0.0,
            'total' => $cart['member_subtotal'],
            'total_formatted' => '£' . number_format($cart['member_subtotal'], 2),
            'currency' => 'GBP'
        ];

        $totals = $this->resolve_discounted_totals($user_id, (float) $cart['member_subtotal']);
        $data['discount'] = $totals['discount_amount'];
        $data['total'] = $totals['total'];
        $data['total_formatted'] = '£' . number_format($totals['total'], 2);
        if (!empty($totals['code'])) {
            $data['promo_code'] = $totals['code'];
        }

        wp_send_json_success($data);
    }

    /**
     * Apply a transactional promo code to commerce cart purchases.
     */
    public function ajax_apply_promo_code(): void {
        if (!$this->verify_commerce_nonce()) {
            wp_send_json_error('Invalid security token');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $promo_code = sanitize_text_field($_POST['promo_code'] ?? '');
        if ($promo_code === '') {
            wp_send_json_error('Promo code is required');
        }

        $cart = $this->ecommerce->get_cart($user_id);
        if (empty($cart['items'])) {
            wp_send_json_error('Cart is empty');
        }

        // Transactional promo codes should be global (no level restrictions).
        $validation = $this->discounts->validate_code($promo_code, 0, $user_id);
        if (empty($validation['valid'])) {
            wp_send_json_error($validation['message'] ?? 'Invalid promo code');
        }

        $this->set_applied_promo_code($user_id, $promo_code);
        wp_send_json_success($this->build_promo_cart_response($user_id, $cart));
    }

    /**
     * Remove applied promo code for transactional purchases.
     */
    public function ajax_remove_promo_code(): void {
        if (!$this->verify_commerce_nonce()) {
            wp_send_json_error('Invalid security token');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $this->clear_applied_promo_code($user_id);
        $cart = $this->ecommerce->get_cart($user_id);
        wp_send_json_success($this->build_promo_cart_response($user_id, $cart));
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
        $billing_name = sanitize_text_field($_POST['billing_name'] ?? '');
        $billing_email = sanitize_email($_POST['billing_email'] ?? '');

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

            $cart = $this->ecommerce->get_cart($user_id);
            if (empty($cart['items'])) {
                wp_send_json_error('Cart is empty');
            }

            $subtotal = (float) ($cart['member_subtotal'] ?? 0);
            $totals = $this->resolve_discounted_totals($user_id, $subtotal);
            $total = $totals['total'];
            if ($total <= 0) {
                $result = $this->ecommerce->process_purchase($user_id, [
                    'payment_method' => 'free',
                    'billing_name' => $billing_name,
                    'billing_email' => $billing_email,
                    'discount_code' => $totals['code'],
                    'discount_amount' => $totals['discount_amount'],
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'auto_download_pdf' => true,
                    'auto_save_to_library' => true,
                ]);

                if (!$result['success']) {
                    wp_send_json_error($result['error'] ?? 'Purchase failed');
                }

                $this->track_and_clear_promo_after_purchase($user_id, $totals['code'], $result['order_id'] ?? 0);
                wp_send_json_success($this->build_purchase_response($user_id, $result));
            }

            if ($this->get_stripe_secret() === '') {
                wp_send_json_error('Stripe is not configured.');
            }

            $gateway = $this->get_gateway();
            $primary_title = $cart['items'][0]['title'] ?? 'Article Purchase';
            $order = (object) [
                'user_id' => $user_id,
                'total' => $total,
                'currency' => strtolower($cart['currency'] ?? 'gbp'),
                'payment_method_id' => $payment_method_id,
                'membership_level_name' => $primary_title,
            ];

            $charge = $gateway->charge($order);
            if ($charge->isFailure()) {
                wp_send_json_error($charge->getMessage() ?: 'Payment failed');
            }

            $status = $charge->get('status');
            $payment_intent = $charge->get('payment_intent');

            if ($status === 'requires_action' && $payment_intent && !empty($payment_intent->client_secret)) {
                wp_send_json_success([
                    'requires_action' => true,
                    'client_secret' => $payment_intent->client_secret,
                ]);
            }

            if ($status !== 'succeeded') {
                wp_send_json_error('Payment could not be completed.');
            }

            $result = $this->ecommerce->process_purchase($user_id, [
                'payment_method' => 'stripe',
                'transaction_id' => $charge->get('transaction_id'),
                'billing_name' => $billing_name,
                'billing_email' => $billing_email,
                'discount_code' => $totals['code'],
                'discount_amount' => $totals['discount_amount'],
                'subtotal' => $subtotal,
                'total' => $total,
                'auto_download_pdf' => true,
                'auto_save_to_library' => true,
            ]);

            if (!$result['success']) {
                wp_send_json_error($result['error'] ?? 'Purchase failed');
            }

            $this->track_and_clear_promo_after_purchase($user_id, $totals['code'], $result['order_id'] ?? 0);
            wp_send_json_success($this->build_purchase_response($user_id, $result));

        } catch (\Exception $e) {
            error_log('Commerce purchase error: ' . $e->getMessage());
            wp_send_json_error('Payment processing failed. Please try again.');
        }
    }

    /**
     * Create a PaymentIntent for the commerce modal.
     */
    public function ajax_create_intent(): void {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $nonce_ok = check_ajax_referer('khm_commerce', 'nonce', false);
        if (!$nonce_ok) {
            error_log('KHM Commerce create intent: invalid nonce.');
            wp_send_json_error('Invalid security token. Please refresh and try again.');
        }

        if ($this->get_stripe_secret() === '') {
            wp_send_json_error('Stripe is not configured.');
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        error_log('KHM Commerce create intent: user=' . $user_id . ' post=' . $post_id);
        if ($post_id) {
            $this->ecommerce->clear_cart($user_id);
            $this->ecommerce->add_to_cart($user_id, $post_id);
        }

        $cart = $this->ecommerce->get_cart($user_id);
        if (empty($cart['items'])) {
            error_log('KHM Commerce create intent: cart empty for user=' . $user_id);
            wp_send_json_error('Cart is empty');
        }

        $subtotal = (float) ($cart['member_subtotal'] ?? 0);
        $totals = $this->resolve_discounted_totals($user_id, $subtotal);
        $total = $totals['total'];
        if ($total <= 0) {
            error_log('KHM Commerce create intent: zero total for user=' . $user_id . ' post=' . $post_id);
            wp_send_json_error('Unable to process free purchases here.');
        }

        $primary_title = $cart['items'][0]['title'] ?? 'Article Purchase';
        $order = (object) [
            'user_id' => $user_id,
            'total' => $total,
            'currency' => strtolower($cart['currency'] ?? 'gbp'),
            'membership_level_name' => $primary_title,
            'post_id' => $cart['items'][0]['post_id'] ?? 0,
        ];

        $gateway = $this->get_gateway();
        $result = $gateway->createPaymentIntent($order);

        if ($result->isFailure()) {
            error_log('KHM Commerce create intent: gateway error ' . $result->getMessage());
            wp_send_json_error($result->getMessage() ?: 'Unable to prepare payment.');
        }

        wp_send_json_success([
            'client_secret' => $result->get('client_secret'),
            'intent_id' => $result->get('intent_id'),
        ]);
    }

    /**
     * Finalize a purchase after Stripe requires additional authentication.
     */
    public function ajax_finalize_purchase(): void {
        check_ajax_referer('khm_commerce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        $billing_name = sanitize_text_field($_POST['billing_name'] ?? '');
        $billing_email = sanitize_email($_POST['billing_email'] ?? '');

        if (!$payment_intent_id) {
            error_log('KHM Commerce finalize: missing intent for user=' . $user_id);
            wp_send_json_error('Payment intent required');
        }
        if (!$post_id) {
            error_log('KHM Commerce finalize: missing post_id for user=' . $user_id . ' intent=' . $payment_intent_id);
            wp_send_json_error('Article ID required');
        }

        try {
            if ($this->get_stripe_secret() === '') {
                error_log('KHM Commerce finalize: Stripe not configured');
                wp_send_json_error('Stripe is not configured.');
            }

            $this->ecommerce->clear_cart($user_id);
            $added = $this->ecommerce->add_to_cart($user_id, $post_id);
            if (!$added) {
                error_log('KHM Commerce finalize: add_to_cart failed for user=' . $user_id . ' post=' . $post_id);
                wp_send_json_error('Unable to prepare purchase for this article.');
            }

            $cart = $this->ecommerce->get_cart($user_id);
            if (empty($cart['items'])) {
                wp_send_json_error('Cart is empty');
            }
            $subtotal = (float) ($cart['member_subtotal'] ?? 0);
            $totals = $this->resolve_discounted_totals($user_id, $subtotal);
            $total = $totals['total'];

            $gateway = $this->get_gateway();
            $intent = $gateway->retrievePaymentIntent($payment_intent_id);

            if (!$intent || $intent->status !== 'succeeded') {
                $status = $intent ? $intent->status : 'missing';
                error_log('KHM Commerce finalize: intent not succeeded. status=' . $status . ' intent=' . $payment_intent_id);
                wp_send_json_error('Payment could not be confirmed.');
            }

            $result = $this->ecommerce->process_purchase($user_id, [
                'payment_method' => 'stripe',
                'transaction_id' => $payment_intent_id,
                'billing_name' => $billing_name,
                'billing_email' => $billing_email,
                'discount_code' => $totals['code'],
                'discount_amount' => $totals['discount_amount'],
                'subtotal' => $subtotal,
                'total' => $total,
                'auto_download_pdf' => true,
                'auto_save_to_library' => true,
            ]);

            if (!$result['success']) {
                error_log('KHM Commerce finalize: process_purchase failed for user=' . $user_id . ' post=' . $post_id);
                wp_send_json_error($result['error'] ?? 'Purchase failed');
            }

            $this->track_and_clear_promo_after_purchase($user_id, $totals['code'], $result['order_id'] ?? 0);
            wp_send_json_success($this->build_purchase_response($user_id, $result));
        } catch (\Exception $e) {
            error_log('Commerce finalize error: ' . $e->getMessage());
            wp_send_json_error('Payment processing failed. Please try again.');
        }
    }

    private function build_purchase_response(int $user_id, array $result): array {
        $response_data = [
            'message' => 'Purchase completed successfully!',
            'order_id' => $result['order_id'] ?? null,
            'download_urls' => []
        ];

        if (!empty($result['purchased_items'])) {
            foreach ($result['purchased_items'] as $item) {
                $download_result = $this->generate_download_url($item['post_id'], $user_id);
                if ($download_result['success']) {
                    $response_data['download_urls'][] = $download_result['download_url'];
                }
            }
        }

        return $response_data;
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
                    
                    $response = [
                        'success' => true,
                        'download_url' => $download_url,
                    ];
                    if (!empty($pdf_result['file_path'])) {
                        $response['pdf_path'] = $pdf_result['file_path'];
                    }
                    return $response;
                }
            }
            
            return ['success' => false, 'error' => 'PDF generation failed'];
        } catch (\Exception $e) {
            error_log('Download URL generation error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Download generation failed'];
        }
    }

    private function get_gateway(): StripeGateway {
        return new StripeGateway([
            'secret_key' => $this->get_stripe_secret(),
            'publishable_key' => get_option('khm_stripe_publishable_key', ''),
            'environment' => get_option('khm_stripe_environment', 'sandbox'),
        ]);
    }

    private function get_stripe_secret(): string {
        if (!function_exists('khm_get_stripe_secret')) {
            return '';
        }

        return (string) (khm_get_stripe_secret('KH_STRIPE_SECRET_KEY') ?? '');
    }

    private function build_promo_cart_response(int $user_id, array $cart): array {
        $subtotal = (float) ($cart['member_subtotal'] ?? 0);
        $totals = $this->resolve_discounted_totals($user_id, $subtotal);
        return [
            'items' => $cart['items'] ?? [],
            'subtotal' => $subtotal,
            'tax' => 0.0,
            'shipping' => 0.0,
            'discount' => $totals['discount_amount'],
            'total' => $totals['total'],
            'promo_code' => $totals['code'],
        ];
    }

    private function resolve_discounted_totals(int $user_id, float $subtotal): array {
        $subtotal = max(0.0, $subtotal);
        $code = $this->get_applied_promo_code($user_id);
        if ($code === '') {
            return ['code' => '', 'discount_amount' => 0.0, 'total' => $subtotal];
        }

        $validation = $this->discounts->validate_code($code, 0, $user_id);
        if (empty($validation['valid']) || empty($validation['code'])) {
            $this->clear_applied_promo_code($user_id);
            return ['code' => '', 'discount_amount' => 0.0, 'total' => $subtotal];
        }

        $breakdown = $this->discounts->get_discount_breakdown($subtotal, $validation['code']);
        $discount = max(0.0, (float) ($breakdown['discount'] ?? 0.0));
        $total = max(0.0, (float) ($breakdown['final'] ?? $subtotal));
        return ['code' => $code, 'discount_amount' => $discount, 'total' => $total];
    }

    private function track_and_clear_promo_after_purchase(int $user_id, string $code, int $order_id): void {
        if ($code === '' || $order_id <= 0) {
            return;
        }

        $validation = $this->discounts->validate_code($code, 0, $user_id);
        if (!empty($validation['valid']) && !empty($validation['code']->id)) {
            $this->discounts->track_usage((int) $validation['code']->id, $user_id, $order_id);
        }
        $this->clear_applied_promo_code($user_id);
    }

    private function get_applied_promo_code(int $user_id): string {
        $code = get_user_meta($user_id, 'khm_commerce_promo_code', true);
        return is_string($code) ? sanitize_text_field($code) : '';
    }

    private function set_applied_promo_code(int $user_id, string $code): void {
        update_user_meta($user_id, 'khm_commerce_promo_code', sanitize_text_field($code));
    }

    private function clear_applied_promo_code(int $user_id): void {
        delete_user_meta($user_id, 'khm_commerce_promo_code');
    }

    private function verify_commerce_nonce(): bool {
        $nonce = sanitize_text_field((string) ($_POST['nonce'] ?? ''));
        if ($nonce === '') {
            return false;
        }

        return (bool) (
            wp_verify_nonce($nonce, 'khm_commerce') ||
            wp_verify_nonce($nonce, 'kss_khm_integration')
        );
    }
}

<?php
/**
 * E-commerce Tracking Module
 *
 * @package TouchPoint_MailChimp
 */

defined('ABSPATH') or exit;

class TouchPoint_MailChimp_Ecommerce {
    
    private static $instance = null;
    private $settings;
    private $api;
    private $logger;
    private $store_id;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = TouchPoint_MailChimp_Settings::instance();
        $this->api = TouchPoint_MailChimp_API::instance();
        $this->logger = TouchPoint_MailChimp_Logger::instance();
        $this->store_id = $this->get_store_id();
        
        $this->init();
    }
    
    private function init() {
        // Only initialize if e-commerce tracking is enabled
        if (!$this->is_ecommerce_enabled()) {
            return;
        }
        
        // WooCommerce hooks
        if (class_exists('WooCommerce')) {
            $this->init_woocommerce_hooks();
        }
        
        // Easy Digital Downloads hooks
        if (class_exists('Easy_Digital_Downloads')) {
            $this->init_edd_hooks();
        }
        
        // Admin hooks
        add_action('wp_ajax_tmc_sync_store', array($this, 'ajax_sync_store'));
        add_action('wp_ajax_tmc_sync_orders', array($this, 'ajax_sync_orders'));
    }
    
    /**
     * Initialize WooCommerce hooks
     */
    private function init_woocommerce_hooks() {
        // Order status changes
        add_action('woocommerce_order_status_completed', array($this, 'track_woo_order'));
        add_action('woocommerce_order_status_processing', array($this, 'track_woo_order'));
        add_action('woocommerce_order_status_refunded', array($this, 'track_woo_refund'));
        
        // Customer registration
        add_action('woocommerce_created_customer', array($this, 'track_woo_customer'));
        
        // Cart abandonment
        add_action('woocommerce_cart_updated', array($this, 'track_cart_update'));
    }
    
    /**
     * Initialize Easy Digital Downloads hooks
     */
    private function init_edd_hooks() {
        // Payment completion
        add_action('edd_complete_purchase', array($this, 'track_edd_order'));
        
        // Customer registration
        add_action('edd_insert_customer', array($this, 'track_edd_customer'));
    }
    
    /**
     * Track WooCommerce order
     */
    public function track_woo_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Prepare order data
        $order_data = array(
            'id' => (string) $order_id,
            'customer' => $this->get_woo_customer_data($order),
            'currency_code' => $order->get_currency(),
            'order_total' => (float) $order->get_total(),
            'tax_total' => (float) $order->get_total_tax(),
            'shipping_total' => (float) $order->get_shipping_total(),
            'processed_at_foreign' => $order->get_date_created()->format('Y-m-d\TH:i:s\Z'),
            'lines' => $this->get_woo_order_lines($order)
        );
        
        // Add order to MailChimp
        $result = $this->api->add_order($this->store_id, $order_data);
        
        if ($result['success']) {
            update_post_meta($order_id, '_tmc_synced', time());
            $this->logger->log("WooCommerce order #{$order_id} synced to MailChimp", 'info');
        } else {
            $this->logger->log("Failed to sync WooCommerce order #{$order_id}: " . $result['error'], 'error');
        }
    }
    
    /**
     * Track WooCommerce refund
     */
    public function track_woo_refund($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Cancel order in MailChimp
        $result = $this->api->cancel_order($this->store_id, (string) $order_id);
        
        if ($result['success']) {
            $this->logger->log("WooCommerce order #{$order_id} cancelled in MailChimp", 'info');
        } else {
            $this->logger->log("Failed to cancel WooCommerce order #{$order_id}: " . $result['error'], 'error');
        }
    }
    
    /**
     * Track WooCommerce customer
     */
    public function track_woo_customer($customer_id) {
        $customer = new WC_Customer($customer_id);
        
        $customer_data = array(
            'id' => (string) $customer_id,
            'email_address' => $customer->get_email(),
            'opt_in_status' => false, // Will be updated when they subscribe
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'address' => array(
                'address1' => $customer->get_billing_address(),
                'city' => $customer->get_billing_city(),
                'province' => $customer->get_billing_state(),
                'postal_code' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country()
            )
        );
        
        $result = $this->api->add_customer($this->store_id, $customer_data);
        
        if ($result['success']) {
            update_user_meta($customer_id, '_tmc_customer_synced', time());
            $this->logger->log("WooCommerce customer #{$customer_id} synced to MailChimp", 'info');
        } else {
            $this->logger->log("Failed to sync WooCommerce customer #{$customer_id}: " . $result['error'], 'error');
        }
    }
    
    /**
     * Track EDD order
     */
    public function track_edd_order($payment_id) {
        $payment = new EDD_Payment($payment_id);
        if (!$payment) {
            return;
        }
        
        $customer_data = $this->get_edd_customer_data($payment);
        
        $order_data = array(
            'id' => (string) $payment_id,
            'customer' => $customer_data,
            'currency_code' => $payment->currency,
            'order_total' => (float) $payment->total,
            'tax_total' => (float) $payment->tax,
            'processed_at_foreign' => date('Y-m-d\TH:i:s\Z', strtotime($payment->date)),
            'lines' => $this->get_edd_order_lines($payment)
        );
        
        $result = $this->api->add_order($this->store_id, $order_data);
        
        if ($result['success']) {
            update_post_meta($payment_id, '_tmc_synced', time());
            $this->logger->log("EDD payment #{$payment_id} synced to MailChimp", 'info');
        } else {
            $this->logger->log("Failed to sync EDD payment #{$payment_id}: " . $result['error'], 'error');
        }
    }
    
    /**
     * Track EDD customer
     */
    public function track_edd_customer($customer_id) {
        $customer = new EDD_Customer($customer_id);
        
        $customer_data = array(
            'id' => (string) $customer_id,
            'email_address' => $customer->email,
            'opt_in_status' => false,
            'first_name' => $customer->name,
            'orders_count' => $customer->purchase_count,
            'total_spent' => (float) $customer->purchase_value
        );
        
        $result = $this->api->add_customer($this->store_id, $customer_data);
        
        if ($result['success']) {
            update_user_meta($customer_id, '_tmc_customer_synced', time());
            $this->logger->log("EDD customer #{$customer_id} synced to MailChimp", 'info');
        } else {
            $this->logger->log("Failed to sync EDD customer #{$customer_id}: " . $result['error'], 'error');
        }
    }
    
    /**
     * Track cart abandonment
     */
    public function track_cart_update() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        $cart_data = array(
            'id' => 'cart_' . $user_id . '_' . time(),
            'customer' => array(
                'id' => (string) $user_id,
                'email_address' => $user->user_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ),
            'currency_code' => get_woocommerce_currency(),
            'order_total' => (float) $cart->total,
            'tax_total' => (float) $cart->get_taxes_total(),
            'lines' => $this->get_cart_lines($cart)
        );
        
        $result = $this->api->add_cart($this->store_id, $cart_data);
        
        if ($result['success']) {
            $this->logger->log("Cart abandoned for user #{$user_id}", 'info');
        }
    }
    
    /**
     * Get WooCommerce customer data
     */
    private function get_woo_customer_data($order) {
        return array(
            'id' => (string) $order->get_customer_id(),
            'email_address' => $order->get_billing_email(),
            'opt_in_status' => false,
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'address' => array(
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'province' => $order->get_billing_state(),
                'postal_code' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country()
            )
        );
    }
    
    /**
     * Get WooCommerce order lines
     */
    private function get_woo_order_lines($order) {
        $lines = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            $lines[] = array(
                'id' => (string) $item_id,
                'product_id' => (string) $product->get_id(),
                'product_title' => $product->get_name(),
                'product_variant_id' => (string) $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'price' => (float) $item->get_total()
            );
        }
        
        return $lines;
    }
    
    /**
     * Get EDD customer data
     */
    private function get_edd_customer_data($payment) {
        $user_info = $payment->user_info;
        
        return array(
            'id' => (string) $payment->customer_id,
            'email_address' => $payment->email,
            'opt_in_status' => false,
            'first_name' => $user_info['first_name'],
            'last_name' => $user_info['last_name']
        );
    }
    
    /**
     * Get EDD order lines
     */
    private function get_edd_order_lines($payment) {
        $lines = array();
        $downloads = $payment->downloads;
        
        foreach ($downloads as $download) {
            $lines[] = array(
                'id' => (string) $download['id'],
                'product_id' => (string) $download['id'],
                'product_title' => get_the_title($download['id']),
                'quantity' => 1,
                'price' => (float) $download['price']
            );
        }
        
        return $lines;
    }
    
    /**
     * Get cart lines for abandonment tracking
     */
    private function get_cart_lines($cart) {
        $lines = array();
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            $lines[] = array(
                'id' => $cart_item_key,
                'product_id' => (string) $product->get_id(),
                'product_title' => $product->get_name(),
                'product_variant_id' => (string) $cart_item['variation_id'],
                'quantity' => $cart_item['quantity'],
                'price' => (float) $cart_item['line_total']
            );
        }
        
        return $lines;
    }
    
    /**
     * Get or create store ID
     */
    private function get_store_id() {
        $store_id = $this->settings->get('store_id');
        
        if (!$store_id) {
            $store_id = sanitize_title(get_bloginfo('name')) . '_' . time();
            $this->settings->set('store_id', $store_id);
            
            // Create store in MailChimp
            $this->create_mailchimp_store($store_id);
        }
        
        return $store_id;
    }
    
    /**
     * Create store in MailChimp
     */
    private function create_mailchimp_store($store_id) {
        $store_data = array(
            'id' => $store_id,
            'list_id' => $this->settings->get_default_list(),
            'name' => get_bloginfo('name'),
            'platform' => 'WordPress',
            'domain' => parse_url(home_url(), PHP_URL_HOST),
            'currency_code' => $this->get_default_currency(),
            'primary_locale' => get_locale()
        );
        
        $result = $this->api->create_store($store_data);
        
        if ($result['success']) {
            $this->logger->log("Store created in MailChimp with ID: {$store_id}", 'info');
        } else {
            $this->logger->log("Failed to create store in MailChimp: " . $result['error'], 'error');
        }
    }
    
    /**
     * Get default currency
     */
    private function get_default_currency() {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        
        if (function_exists('edd_get_currency')) {
            return edd_get_currency();
        }
        
        return 'USD';
    }
    
    /**
     * Check if e-commerce tracking is enabled
     */
    private function is_ecommerce_enabled() {
        return $this->settings->get('enable_ecommerce', false);
    }
    
    /**
     * AJAX handler for store sync
     */
    public function ajax_sync_store() {
        check_ajax_referer('tmc_sync_store', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $this->create_mailchimp_store($this->store_id);
        wp_send_json_success(array('message' => 'Store synced successfully'));
    }
    
    /**
     * AJAX handler for orders sync
     */
    public function ajax_sync_orders() {
        check_ajax_referer('tmc_sync_orders', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 50;
        
        // Sync WooCommerce orders
        if (class_exists('WooCommerce')) {
            $orders = wc_get_orders(array(
                'limit' => $per_page,
                'page' => $page,
                'status' => array('completed', 'processing')
            ));
            
            foreach ($orders as $order) {
                $this->track_woo_order($order->get_id());
            }
        }
        
        wp_send_json_success(array('message' => 'Orders synced successfully'));
    }
}

// Initialize the module
TouchPoint_MailChimp_Ecommerce::instance();
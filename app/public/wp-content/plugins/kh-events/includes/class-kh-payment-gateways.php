<?php
/**
 * KH Payment Gateway System
 *
 * Reusable payment processing for 1927MSuite plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class KH_Payment_Gateway {

    protected $gateway_id;
    protected $gateway_name;
    protected $settings;

    public function __construct($gateway_id, $gateway_name) {
        $this->gateway_id = $gateway_id;
        $this->gateway_name = $gateway_name;
        $this->settings = $this->get_settings();
    }

    abstract public function process_payment($payment_data);
    abstract public function refund_payment($transaction_id, $amount = null);
    abstract public function get_settings_fields();

    public function get_gateway_id() {
        return $this->gateway_id;
    }

    public function get_gateway_name() {
        return $this->gateway_name;
    }

    public function is_enabled() {
        return $this->get_setting('enabled') === 'yes';
    }

    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    protected function get_settings() {
        return get_option('kh_payment_' . $this->gateway_id . '_settings', array());
    }

    public function update_settings($settings) {
        update_option('kh_payment_' . $this->gateway_id . '_settings', $settings);
        $this->settings = $settings;
    }

    protected function log($message, $level = 'info') {
        if (class_exists('KH_Payment_Logger')) {
            KH_Payment_Logger::log($this->gateway_id, $message, $level);
        }
    }

    protected function format_amount($amount, $currency = 'USD') {
        // Convert to smallest currency unit (cents for USD)
        $multipliers = array(
            'USD' => 100,
            'EUR' => 100,
            'GBP' => 100,
            'JPY' => 1,
        );

        $multiplier = isset($multipliers[$currency]) ? $multipliers[$currency] : 100;
        return round($amount * $multiplier);
    }
}

class KH_Stripe_Gateway extends KH_Payment_Gateway {

    public function __construct() {
        parent::__construct('stripe', 'Stripe');
    }

    public function process_payment($payment_data) {
        // Validate required data
        if (empty($payment_data['amount']) || empty($payment_data['currency']) || empty($payment_data['token'])) {
            return array(
                'success' => false,
                'error' => 'Missing required payment data'
            );
        }

        // Check if Stripe is available
        if (!class_exists('Stripe\StripeClient')) {
            return array(
                'success' => false,
                'error' => 'Stripe library not loaded'
            );
        }

        try {
            $stripe = new \Stripe\StripeClient($this->get_setting('secret_key'));

            $intent = $stripe->paymentIntents->create([
                'amount' => $this->format_amount($payment_data['amount'], $payment_data['currency']),
                'currency' => strtolower($payment_data['currency']),
                'payment_method' => $payment_data['token'],
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => array(
                    'order_id' => $payment_data['order_id'] ?? '',
                    'customer_email' => $payment_data['customer_email'] ?? '',
                    'description' => $payment_data['description'] ?? '',
                ),
            ]);

            if ($intent->status === 'succeeded') {
                $this->log('Payment processed successfully: ' . $intent->id);

                return array(
                    'success' => true,
                    'transaction_id' => $intent->id,
                    'amount' => $payment_data['amount'],
                    'currency' => $payment_data['currency'],
                    'gateway_response' => $intent
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Payment not completed',
                    'gateway_response' => $intent
                );
            }

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Stripe API error: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_error' => $e
            );
        }
    }

    public function refund_payment($transaction_id, $amount = null) {
        if (!class_exists('Stripe\StripeClient')) {
            return array(
                'success' => false,
                'error' => 'Stripe library not loaded'
            );
        }

        try {
            $stripe = new \Stripe\StripeClient($this->get_setting('secret_key'));

            $refund_data = array('payment_intent' => $transaction_id);

            if ($amount) {
                $refund_data['amount'] = $this->format_amount($amount);
            }

            $refund = $stripe->refunds->create($refund_data);

            $this->log('Refund processed: ' . $refund->id);

            return array(
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $amount,
                'gateway_response' => $refund
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Stripe refund error: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_error' => $e
            );
        }
    }

    public function get_settings_fields() {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'kh-events'),
                'type' => 'checkbox',
                'label' => __('Enable Stripe Payment Gateway', 'kh-events'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'kh-events'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'kh-events'),
                'default' => __('Credit Card (Stripe)', 'kh-events')
            ),
            'description' => array(
                'title' => __('Description', 'kh-events'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'kh-events'),
                'default' => __('Pay with your credit card via Stripe.', 'kh-events')
            ),
            'testmode' => array(
                'title' => __('Test Mode', 'kh-events'),
                'type' => 'checkbox',
                'label' => __('Enable test mode', 'kh-events'),
                'description' => __('Place the payment gateway in test mode.', 'kh-events'),
                'default' => 'yes'
            ),
            'publishable_key' => array(
                'title' => __('Publishable Key', 'kh-events'),
                'type' => 'password',
                'description' => __('Get your API keys from your Stripe account.', 'kh-events'),
                'default' => ''
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'kh-events'),
                'type' => 'password',
                'description' => __('Get your API keys from your Stripe account.', 'kh-events'),
                'default' => ''
            ),
        );
    }
}

class KH_Payment_Handler {

    private static $instance = null;
    private $gateways = array();

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_gateways();
        add_action('init', array($this, 'init'));
    }

    private function load_gateways() {
        // Load Stripe gateway
        if (file_exists(KH_EVENTS_DIR . 'includes/gateways/class-kh-stripe-gateway.php')) {
            require_once KH_EVENTS_DIR . 'includes/gateways/class-kh-stripe-gateway.php';
            $this->gateways['stripe'] = new KH_Stripe_Gateway();
        }

        // Load PayPal gateway
        if (file_exists(KH_EVENTS_DIR . 'includes/gateways/class-kh-paypal-gateway.php')) {
            require_once KH_EVENTS_DIR . 'includes/gateways/class-kh-paypal-gateway.php';
            $this->gateways['paypal'] = new KH_PayPal_Gateway();
        }
    }

    public function init() {
        // Initialize payment processing
    }

    public function get_available_gateways() {
        return array_filter($this->gateways, function($gateway) {
            return $gateway->is_enabled();
        });
    }

    public function get_gateway($gateway_id) {
        return isset($this->gateways[$gateway_id]) ? $this->gateways[$gateway_id] : null;
    }

    public function process_payment($gateway_id, $payment_data) {
        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            return array(
                'success' => false,
                'error' => 'Payment gateway not found'
            );
        }

        if (!$gateway->is_enabled()) {
            return array(
                'success' => false,
                'error' => 'Payment gateway is not enabled'
            );
        }

        return $gateway->process_payment($payment_data);
    }

    public function refund_payment($gateway_id, $transaction_id, $amount = null) {
        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            return array(
                'success' => false,
                'error' => 'Payment gateway not found'
            );
        }

        return $gateway->refund_payment($transaction_id, $amount);
    }

    public function get_gateway_settings_fields($gateway_id) {
        $gateway = $this->get_gateway($gateway_id);
        return $gateway ? $gateway->get_settings_fields() : array();
    }

    public function update_gateway_settings($gateway_id, $settings) {
        $gateway = $this->get_gateway($gateway_id);
        if ($gateway) {
            $gateway->update_settings($settings);
        }
    }
}

class KH_Payment_Logger {

    public static function log($gateway, $message, $level = 'info') {
        $log_file = KH_EVENTS_DIR . 'logs/payment-' . date('Y-m-d') . '.log';

        $log_entry = sprintf(
            "[%s] [%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $gateway,
            $message
        );

        // Ensure log directory exists
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
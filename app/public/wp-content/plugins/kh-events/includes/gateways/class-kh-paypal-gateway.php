<?php
/**
 * PayPal Payment Gateway for KH Events
 *
 * Complete PayPal integration using PayPal PHP SDK
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_PayPal_Gateway extends KH_Payment_Gateway {

    public function __construct() {
        parent::__construct('paypal', 'PayPal');
    }

    public function process_payment($payment_data) {
        // Validate required data
        if (empty($payment_data['amount']) || empty($payment_data['currency'])) {
            return array(
                'success' => false,
                'error' => 'Missing required payment data'
            );
        }

        // Check if PayPal SDK is available
        if (!class_exists('PayPalCheckoutSdk\Orders\OrdersCreateRequest')) {
            return array(
                'success' => false,
                'error' => 'PayPal SDK not loaded'
            );
        }

        try {
            $client = $this->get_paypal_client();

            // Create order
            $request = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
            $request->prefer('return=representation');

            $request->body = array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(
                    array(
                        'amount' => array(
                            'currency_code' => strtoupper($payment_data['currency']),
                            'value' => number_format($payment_data['amount'], 2, '.', '')
                        ),
                        'description' => $payment_data['description'] ?? 'Event Booking',
                        'reference_id' => $payment_data['order_id'] ?? uniqid('kh-booking-')
                    )
                ),
                'application_context' => array(
                    'brand_name' => get_bloginfo('name'),
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $payment_data['return_url'] ?? home_url(),
                    'cancel_url' => $payment_data['cancel_url'] ?? home_url()
                )
            );

            $response = $client->execute($request);

            if ($response->statusCode === 201) {
                $order = $response->result;

                $this->log('PayPal order created: ' . $order->id);

                return array(
                    'success' => true,
                    'transaction_id' => $order->id,
                    'amount' => $payment_data['amount'],
                    'currency' => $payment_data['currency'],
                    'paypal_order_id' => $order->id,
                    'approval_url' => $this->get_approval_url($order),
                    'gateway_response' => $order
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Failed to create PayPal order',
                    'gateway_response' => $response
                );
            }

        } catch (\PayPalHttp\HttpException $e) {
            $this->log('PayPal API error: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_error' => $e
            );
        } catch (Exception $e) {
            $this->log('PayPal general error: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_error' => $e
            );
        }
    }

    public function capture_payment($order_id) {
        try {
            $client = $this->get_paypal_client();

            $request = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($order_id);
            $request->prefer('return=representation');

            $response = $client->execute($request);

            if ($response->statusCode === 201) {
                $order = $response->result;

                $this->log('PayPal payment captured: ' . $order_id);

                return array(
                    'success' => true,
                    'transaction_id' => $order_id,
                    'capture_id' => $order->purchase_units[0]->payments->captures[0]->id,
                    'status' => $order->status,
                    'gateway_response' => $order
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Failed to capture PayPal payment',
                    'gateway_response' => $response
                );
            }

        } catch (\PayPalHttp\HttpException $e) {
            $this->log('PayPal capture error: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_error' => $e
            );
        }
    }

    public function refund_payment($transaction_id, $amount = null) {
        try {
            $client = $this->get_paypal_client();

            // First, get the capture ID from the transaction
            $capture_id = $this->get_capture_id_from_transaction($transaction_id);

            if (!$capture_id) {
                return array(
                    'success' => false,
                    'error' => 'Could not find capture ID for refund'
                );
            }

            $request = new \PayPalCheckoutSdk\Payments\CapturesRefundRequest($capture_id);

            $refund_data = array();
            if ($amount) {
                $refund_data['amount'] = array(
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => 'USD' // Should be dynamic based on original transaction
                );
            }

            $request->body = $refund_data;

            $response = $client->execute($request);

            if ($response->statusCode === 201) {
                $refund = $response->result;

                $this->log('PayPal refund processed: ' . $refund->id);

                return array(
                    'success' => true,
                    'refund_id' => $refund->id,
                    'amount' => $amount,
                    'gateway_response' => $refund
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Failed to process PayPal refund',
                    'gateway_response' => $response
                );
            }

        } catch (\PayPalHttp\HttpException $e) {
            $this->log('PayPal refund error: ' . $e->getMessage(), 'error');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_error' => $e
            );
        }
    }

    private function get_paypal_client() {
        $client_id = $this->get_setting('client_id');
        $client_secret = $this->get_setting('client_secret');
        $testmode = $this->get_setting('testmode') === 'yes';

        if ($testmode) {
            $environment = new \PayPalCheckoutSdk\Core\SandboxEnvironment($client_id, $client_secret);
        } else {
            $environment = new \PayPalCheckoutSdk\Core\ProductionEnvironment($client_id, $client_secret);
        }

        return new \PayPalCheckoutSdk\Core\PayPalHttpClient($environment);
    }

    private function get_approval_url($order) {
        foreach ($order->links as $link) {
            if ($link->rel === 'approve') {
                return $link->href;
            }
        }
        return '';
    }

    private function get_capture_id_from_transaction($transaction_id) {
        // In a real implementation, you'd store and retrieve the capture ID
        // For now, we'll assume the transaction_id is the order ID and we need to get the capture
        try {
            $client = $this->get_paypal_client();
            $request = new \PayPalCheckoutSdk\Orders\OrdersGetRequest($transaction_id);
            $response = $client->execute($request);

            if ($response->statusCode === 200) {
                $order = $response->result;
                if (isset($order->purchase_units[0]->payments->captures[0]->id)) {
                    return $order->purchase_units[0]->payments->captures[0]->id;
                }
            }
        } catch (Exception $e) {
            $this->log('Error getting capture ID: ' . $e->getMessage(), 'error');
        }

        return false;
    }

    public function get_settings_fields() {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'kh-events'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Payment Gateway', 'kh-events'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'kh-events'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'kh-events'),
                'default' => __('PayPal', 'kh-events')
            ),
            'description' => array(
                'title' => __('Description', 'kh-events'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'kh-events'),
                'default' => __('Pay securely with PayPal.', 'kh-events')
            ),
            'testmode' => array(
                'title' => __('Sandbox Mode', 'kh-events'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal sandbox', 'kh-events'),
                'description' => __('Place the payment gateway in sandbox mode.', 'kh-events'),
                'default' => 'yes'
            ),
            'client_id' => array(
                'title' => __('Client ID', 'kh-events'),
                'type' => 'password',
                'description' => __('Get your API credentials from your PayPal developer account.', 'kh-events'),
                'default' => ''
            ),
            'client_secret' => array(
                'title' => __('Client Secret', 'kh-events'),
                'type' => 'password',
                'description' => __('Get your API credentials from your PayPal developer account.', 'kh-events'),
                'default' => ''
            ),
        );
    }
}
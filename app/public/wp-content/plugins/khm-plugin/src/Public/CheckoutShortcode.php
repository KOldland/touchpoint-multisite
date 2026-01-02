<?php
/**
 * Checkout Shortcode Handler
 *
 * Renders secure checkout form with Stripe Elements integration.
 *
 * @package KHM\Public
 */

namespace KHM\Public;

use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Gateways\StripeGateway;
use KHM\Services\EmailService;
use KHM\Services\LevelRepository;

class CheckoutShortcode {

    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private EmailService $email;
    private LevelRepository $levels;

    public function __construct(
        MembershipRepository $memberships,
        OrderRepository $orders,
        EmailService $email,
        ?LevelRepository $levels = null
    ) {
        $this->memberships = $memberships;
        $this->orders = $orders;
        $this->email = $email;
        $this->levels = $levels ?: new LevelRepository();
    }

    /**
     * Register shortcode and hooks.
     */
    public function register(): void {
        add_shortcode('khm_checkout', [ $this, 'render' ]);
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_assets' ]);
        add_action('wp_ajax_khm_process_checkout', [ $this, 'process_checkout' ]);
        add_action('wp_ajax_nopriv_khm_process_checkout', [ $this, 'process_checkout' ]);
    }

    /**
     * Enqueue Stripe.js and checkout scripts.
     */
    public function enqueue_assets(): void {
        if ( ! $this->is_checkout_page() ) {
            return;
        }

        // Stripe.js
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);

        // Checkout script
        wp_enqueue_script(
            'khm-checkout',
            plugins_url('js/checkout.js', dirname(__DIR__)),
            [ 'jquery', 'stripe-js' ],
            '1.0.0',
            true
        );

        wp_localize_script('khm-checkout', 'khmCheckout', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_checkout'),
            'stripeKey' => get_option('khm_stripe_publishable_key', ''),
        ]);

        // Checkout styles
        wp_enqueue_style(
            'khm-checkout',
            plugins_url('css/checkout.css', dirname(__DIR__)),
            [],
            '1.0.0'
        );
    }

    /**
     * Check if current page is checkout.
     */
    private function is_checkout_page(): bool {
        global $post;
        return $post && has_shortcode($post->post_content, 'khm_checkout');
    }

    /**
     * Render checkout form.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render( $atts = [] ): string {
        $atts = shortcode_atts([
            'level_id' => 0,
            'show_levels' => false,
        ], $atts, 'khm_checkout');

        if ( ! is_user_logged_in() ) {
            return $this->render_login_required();
        }

        $user = wp_get_current_user();
        $level_id = (int) ( $atts['level_id'] ?: ( $_GET['level'] ?? 0 ) );

        if ( ! $level_id && ! $atts['show_levels'] ) {
            return '<p class="khm-error">' . esc_html__('Please select a membership level.', 'khm-membership') . '</p>';
        }

        // Get level details
        $level = $this->get_level($level_id);
        if ( ! $level && $level_id ) {
            return '<p class="khm-error">' . esc_html__('Invalid membership level.', 'khm-membership') . '</p>';
        }

        ob_start();

        // Allow theme override
        $template = locate_template('khm/checkout.php');
        if ( $template ) {
            include $template;
        } else {
            $this->render_default_form($user, $level, $atts);
        }

        $html = ob_get_clean();

        /**
         * Filter checkout form HTML.
         *
         * @param string $html Checkout form HTML
         * @param object $user Current user
         * @param object|null $level Selected level
         * @param array $atts Shortcode attributes
         */
        return apply_filters('khm_checkout_form_html', $html, $user, $level, $atts);
    }

    /**
     * Render default checkout form.
     */
    private function render_default_form( $user, $level, $atts ): void {
        ?>
        <div class="khm-checkout-wrapper" id="khm-checkout">
            
            <?php do_action('khm_checkout_before_form', $user, $level); ?>

            <form id="khm-checkout-form" class="khm-checkout-form" method="post">
                
                <?php wp_nonce_field('khm_checkout', 'khm_checkout_nonce'); ?>
                <input type="hidden" name="action" value="khm_process_checkout">
                <input type="hidden" name="level_id" value="<?php echo esc_attr($level->id ?? 0); ?>">
                <input type="hidden" name="payment_method_id" id="khm-payment-method-id">

                <?php if ( $level ) : ?>
                    <div class="khm-checkout-level">
                        <h3><?php echo esc_html($level->name); ?></h3>
                        <?php if ( ! empty($level->description) ) : ?>
                            <p class="khm-level-description"><?php echo wp_kses_post($level->description); ?></p>
                        <?php endif; ?>
                        <p class="khm-level-price">
                            <strong><?php echo esc_html($this->format_price($level)); ?></strong>
                        </p>
                    </div>
                <?php endif; ?>

                <?php do_action('khm_checkout_after_level', $level); ?>

                <div class="khm-checkout-billing">
                    <h4><?php esc_html_e('Billing Information', 'khm-membership'); ?></h4>

                    <div class="khm-form-row">
                        <label for="khm-billing-name"><?php esc_html_e('Full Name', 'khm-membership'); ?> <span class="required">*</span></label>
                        <input type="text" id="khm-billing-name" name="billing_name" required 
                                value="<?php echo esc_attr($user->display_name); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-billing-email"><?php esc_html_e('Email', 'khm-membership'); ?> <span class="required">*</span></label>
                        <input type="email" id="khm-billing-email" name="billing_email" required 
                                value="<?php echo esc_attr($user->user_email); ?>">
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-billing-street"><?php esc_html_e('Address', 'khm-membership'); ?></label>
                        <input type="text" id="khm-billing-street" name="billing_street">
                    </div>

                    <div class="khm-form-row-group">
                        <div class="khm-form-row">
                            <label for="khm-billing-city"><?php esc_html_e('City', 'khm-membership'); ?></label>
                            <input type="text" id="khm-billing-city" name="billing_city">
                        </div>

                        <div class="khm-form-row">
                            <label for="khm-billing-state"><?php esc_html_e('State', 'khm-membership'); ?></label>
                            <input type="text" id="khm-billing-state" name="billing_state">
                        </div>

                        <div class="khm-form-row">
                            <label for="khm-billing-zip"><?php esc_html_e('Zip Code', 'khm-membership'); ?></label>
                            <input type="text" id="khm-billing-zip" name="billing_zip">
                        </div>
                    </div>

                    <div class="khm-form-row">
                        <label for="khm-billing-country"><?php esc_html_e('Country', 'khm-membership'); ?></label>
                        <input type="text" id="khm-billing-country" name="billing_country" value="US">
                    </div>

                    <?php do_action('khm_checkout_after_billing_fields', $user, $level); ?>
                </div>

                <div class="khm-checkout-payment">
                    <h4><?php esc_html_e('Payment Information', 'khm-membership'); ?></h4>
                    
                    <div class="khm-form-row">
                        <label for="khm-card-element"><?php esc_html_e('Credit Card', 'khm-membership'); ?> <span class="required">*</span></label>
                        <div id="khm-card-element" class="khm-card-element">
                            <!-- Stripe Elements will mount here -->
                        </div>
                        <div id="khm-card-errors" class="khm-error" role="alert"></div>
                    </div>

                    <?php do_action('khm_checkout_after_payment_fields', $user, $level); ?>
                </div>

                <div class="khm-checkout-summary">
                    <h4><?php esc_html_e('Order Summary', 'khm-membership'); ?></h4>
                    <div class="khm-summary-row">
                        <span class="khm-summary-label"><?php esc_html_e('Due today', 'khm-membership'); ?>:</span>
                        <strong id="khm_due_today" data-test="khm-due-today">
                            <?php
                            $due_today = $level ? (float) ( $level->initial_payment ?? 0 ) : 0.0;
                            echo esc_html( '$' . number_format( $due_today, 2 ) );
                            ?>
                        </strong>
                    </div>
                    <div class="khm-summary-row">
                        <span class="khm-trial-label" data-test="khm-trial-label" style="display:none"></span>
                    </div>
                    <div class="khm-summary-row">
                        <span class="khm-first-only-label" data-test="khm-first-only-label" style="display:none"></span>
                    </div>
                </div>

                <div class="khm-checkout-submit">
                    <?php do_action('khm_checkout_before_submit', $user, $level); ?>
                    
                    <button type="submit" id="khm-checkout-submit" class="khm-button khm-button-primary">
                        <?php esc_html_e('Complete Purchase', 'khm-membership'); ?>
                    </button>
                    
                    <div id="khm-checkout-messages" class="khm-messages" role="status" aria-live="polite"></div>
                </div>

            </form>

            <?php do_action('khm_checkout_after_form', $user, $level); ?>

        </div>
        <?php
    }

    /**
     * Render login required message.
     */
    private function render_login_required(): string {
        $login_url = wp_login_url(get_permalink());
        $register_url = wp_registration_url();

        ob_start();
        ?>
        <div class="khm-login-required">
            <p><?php esc_html_e('Please log in to complete checkout.', 'khm-membership'); ?></p>
            <p>
                <a href="<?php echo esc_url($login_url); ?>" class="khm-button">
                    <?php esc_html_e('Log In', 'khm-membership'); ?>
                </a>
                <?php if ( get_option('users_can_register') ) : ?>
                    <a href="<?php echo esc_url($register_url); ?>" class="khm-button khm-button-secondary">
                        <?php esc_html_e('Register', 'khm-membership'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Process checkout via AJAX.
     *
     * @throws \Exception When nonce verification fails, user is not logged in, invalid inputs are provided, or processing fails.
     */
    public function process_checkout(): void {
        try {
            // Verify nonce
            if ( ! check_ajax_referer('khm_checkout', 'khm_checkout_nonce', false) ) {
                throw new \Exception(__('Invalid security token. Please refresh and try again.', 'khm-membership'));
            }

            // Verify user
            if ( ! is_user_logged_in() ) {
                throw new \Exception(__('You must be logged in to checkout.', 'khm-membership'));
            }

            $user = wp_get_current_user();
            $level_id = (int) ( $_POST['level_id'] ?? 0 );
            $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

            if ( ! $level_id ) {
                throw new \Exception(__('Invalid membership level.', 'khm-membership'));
            }

            if ( ! $payment_method_id ) {
                throw new \Exception(__('Payment method is required.', 'khm-membership'));
            }

            $level = $this->get_level($level_id);
            if ( ! $level ) {
                throw new \Exception(__('Membership level not found.', 'khm-membership'));
            }

            // Build order data
            $orderData = [
                'user_id' => $user->ID,
                'membership_id' => $level->id,
                'membership_level_name' => $level->name ?? 'Membership',
                'billing_name' => sanitize_text_field($_POST['billing_name'] ?? ''),
                'billing_street' => sanitize_text_field($_POST['billing_street'] ?? ''),
                'billing_city' => sanitize_text_field($_POST['billing_city'] ?? ''),
                'billing_state' => sanitize_text_field($_POST['billing_state'] ?? ''),
                'billing_zip' => sanitize_text_field($_POST['billing_zip'] ?? ''),
                'billing_country' => sanitize_text_field($_POST['billing_country'] ?? ''),
                'subtotal' => $level->initial_payment ?? 0,
                'gateway' => 'stripe',
                'gateway_environment' => get_option('khm_stripe_environment', 'sandbox'),
                'status' => 'pending',
                'currency' => get_option('khm_currency', 'usd'),
            ];

            // Add recurring billing information if this is a subscription
            $is_recurring = ! empty( $level->billing_amount ) && $level->billing_amount > 0;
            if ( $is_recurring ) {
                $orderData['billing_amount'] = $level->billing_amount ?? 0;
                $orderData['billing_period'] = $level->cycle_period ?? 'Month';
                $orderData['billing_frequency'] = $level->cycle_number ?? 1;
            }

            // Calculate tax
            $orderObj = (object) $orderData;
            $orderData['tax'] = $this->orders->calculateTax($orderObj);
            $orderData['total'] = $orderData['subtotal'] + $orderData['tax'];

            /**
             * Filter order data before processing.
             *
             * @param array $orderData Order data
             * @param object $level Membership level
             * @param \WP_User $user Current user
             */
            $orderData = apply_filters('khm_checkout_order_data', $orderData, $level, $user);

            // Create order
            $order = $this->orders->create($orderData);

            // Process payment
            $gateway = $this->get_gateway();
            $order->payment_method_id = $payment_method_id;
            
            // Add all order data properties to order object for gateway
            foreach ( $orderData as $key => $value ) {
                if ( ! isset( $order->$key ) ) {
                    $order->$key = $value;
                }
            }

            do_action('khm_checkout_before_payment', $order, $level, $user);

            // Determine if this is a subscription or one-time payment
            $is_subscription = ! empty( $order->billing_amount ) && $order->billing_amount > 0;

            if ( $is_subscription ) {
                // Create subscription
                $result = $gateway->createSubscription($order);
            } else {
                // One-time charge
                $result = $gateway->charge($order);
            }

            if ( ! $result->isSuccess() ) {
                throw new \Exception($result->getMessage());
            }

            // Update order with transaction/subscription ID
            $update_data = [
                'status' => 'success',
                'payment_transaction_id' => $result->get('transaction_id') ?? $result->get('subscription_id'),
            ];
            
            // Store subscription ID if this was a subscription
            if ( $is_subscription && $result->get('subscription_id') ) {
                $update_data['subscription_transaction_id'] = $result->get('subscription_id');
            }
            
            $this->orders->update( (int) $order->id, $update_data );

            // Assign membership
            $this->memberships->assign($user->ID, $level->id, [
                'status' => 'active',
                'initial_payment' => $level->initial_payment ?? 0,
                'billing_amount' => $level->billing_amount ?? 0,
            ]);

            do_action('khm_checkout_after_payment', $order, $level, $user);

            // Compose email data including discount/trial metadata
            $emailData = [
                'name' => $user->display_name,
                'membership_level' => $level->name,
                'amount' => $orderData['total'],
                'discount_code' => $orderData['discount_code'] ?? '',
                'discount_amount' => $orderData['discount_amount'] ?? 0,
                'trial_days' => $orderData['trial_days'] ?? 0,
                'trial_amount' => $orderData['trial_amount'] ?? 0,
                'first_payment_only' => ! empty($orderData['discount_first_payment_only']) ? 'Yes' : 'No',
                'recurring_discount_type' => $orderData['recurring_discount_type'] ?? '',
                'recurring_discount_amount' => $orderData['recurring_discount_amount'] ?? 0,
            ];

            // Compute due today for clearer messaging
            if ( ! empty($emailData['trial_days']) ) {
                $emailData['due_today'] = (float) $emailData['trial_amount'];
            } else {
                $emailData['due_today'] = (float) $orderData['total'];
            }

            // Human-readable summaries
            if ( ! empty($emailData['trial_days']) ) {
                $emailData['trial_summary'] = ( (float) $emailData['trial_amount'] > 0 )
                    ? sprintf('Paid trial: %d days (%s due today)', (int) $emailData['trial_days'], '$' . number_format((float) $emailData['trial_amount'], 2))
                    : sprintf('Free trial: %d days', (int) $emailData['trial_days']);
            } else {
                $emailData['trial_summary'] = '';
            }

            if ( ! empty($emailData['recurring_discount_type']) && (float) $emailData['recurring_discount_amount'] > 0 ) {
                if ( $emailData['recurring_discount_type'] === 'percent' ) {
                    $emailData['recurring_summary'] = sprintf('Recurring discount: %s%% off each renewal', number_format((float) $emailData['recurring_discount_amount'], 2));
                } else {
                    $emailData['recurring_summary'] = sprintf('Recurring discount: $%s off each renewal', number_format((float) $emailData['recurring_discount_amount'], 2));
                }
            } else {
                $emailData['recurring_summary'] = '';
            }

            if ( ! empty($emailData['discount_code']) && (float) $emailData['discount_amount'] > 0 ) {
                $emailData['discount_summary'] = sprintf('Discount %s applied: -$%s', $emailData['discount_code'], number_format((float) $emailData['discount_amount'], 2));
            } else {
                $emailData['discount_summary'] = '';
            }

            // Send emails
            $this->email->send('checkout_paid', $user->user_email, $emailData);

            wp_send_json_success([
                'message' => __('Payment successful! Redirecting...', 'khm-membership'),
                'redirect' => apply_filters('khm_checkout_success_redirect', home_url('/account/'), $order, $level),
            ]);

        } catch ( \Exception $e ) {
            do_action('khm_checkout_error', $e->getMessage(), $_POST);

            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get membership level details.
     */
    private function get_level( int $level_id ): ?\KHM\Models\MembershipLevel {
        if ( $level_id <= 0 ) {
            return null;
        }

        return $this->levels->get($level_id, true);
    }

    /**
     * Format price display.
     */
    private function format_price( $level ): string {
        $amount = $level->initial_payment ?? 0;
        $currency = get_option('khm_currency', 'USD');

        $formatted = number_format( (float) $amount, 2);

        if ( $level->billing_amount > 0 && isset($level->cycle_number, $level->cycle_period) ) {
            $period = strtolower($level->cycle_period);
            $formatted .= ' then ' . number_format( (float) $level->billing_amount, 2) . '/' . $period;
        }

        return apply_filters('khm_format_price', $formatted, $level, $currency);
    }

    /**
     * Get configured gateway.
     */
    private function get_gateway(): StripeGateway {
        return new StripeGateway([
            'secret_key' => get_option('khm_stripe_secret_key', ''),
            'publishable_key' => get_option('khm_stripe_publishable_key', ''),
            'environment' => get_option('khm_stripe_environment', 'sandbox'),
        ]);
    }
}

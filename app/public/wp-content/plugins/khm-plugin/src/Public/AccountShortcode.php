<?php

namespace KHM\PublicFrontend;

use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\LevelRepository;

/**
 * AccountShortcode
 *
 * Provides [khm_account] shortcode for member account management.
 * Shows user's active memberships, order history, and account details.
 */
class AccountShortcode {

    private MembershipRepository $membership_repo;
    private OrderRepository $order_repo;
    private LevelRepository $level_repo;

    public function __construct(
        MembershipRepository $membership_repo,
        OrderRepository $order_repo,
        ?LevelRepository $level_repo = null
    ) {
        $this->membership_repo = $membership_repo;
        $this->order_repo = $order_repo;
        $this->level_repo = $level_repo ?: new LevelRepository();
    }

    /**
     * Register shortcode and AJAX hooks
     */
    public function register(): void {
        add_shortcode('khm_account', [ $this, 'render' ]);

        // AJAX handlers for account actions
        add_action('wp_ajax_khm_cancel_membership', [ $this, 'handle_cancel_membership' ]);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets(): void {
        if ( ! is_singular() || ! has_shortcode(get_post()->post_content, 'khm_account') ) {
            return;
        }

        wp_enqueue_style(
            'khm-account',
            plugins_url('public/css/account.css', dirname(__DIR__, 2)),
            [],
            '1.0.0'
        );

        // Stripe.js for payment method updates
        $stripe_key = get_option('khm_stripe_publishable_key', '');
        $deps = [ 'jquery' ];
        if ( ! empty($stripe_key) ) {
            wp_register_script(
                'stripe',
                'https://js.stripe.com/v3/',
                [],
                null,
                true
            );
            $deps[] = 'stripe';
        }

        wp_enqueue_script(
            'khm-account',
            plugins_url('public/js/account.js', dirname(__DIR__, 2)),
            $deps,
            '1.1.0',
            true
        );

        wp_localize_script('khm-account', 'khmAccount', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_account'),
            'restUrl' => esc_url_raw( rest_url( 'khm/v1' ) ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'stripeKey' => $stripe_key,
            'confirmCancel' => __('Are you sure you want to cancel this membership?', 'khm-membership'),
            'confirmPause'  => __('Pause this membership?', 'khm-membership'),
        ]);
    }

    /**
     * Render account page
     *
     * Usage: [khm_account]
     *        [khm_account section="memberships"]
     *        [khm_account section="orders"]
     *        [khm_account section="profile"]
     *
     * @param array $atts
     * @return string
     */
    public function render( array $atts = [] ): string {
        $atts = shortcode_atts([
            'section' => 'overview', // overview, memberships, orders, profile
        ], $atts, 'khm_account');

        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return $this->render_login_required();
        }

        // Check for theme override
        $template = locate_template('khm/account.php');
        if ( $template ) {
            ob_start();
            include $template;
            return ob_get_clean();
        }

        // Render default account page
        $user_id = get_current_user_id();

        ob_start();
        ?>
        <div class="khm-account-wrapper">
            <?php $this->render_navigation($atts['section']); ?>
            
            <div class="khm-account-content">
                <?php
                switch ( $atts['section'] ) {
                    case 'memberships':
                        $this->render_memberships($user_id);
                        break;
                    case 'orders':
                        $this->render_orders($user_id);
                        break;
                    case 'profile':
                        $this->render_profile($user_id);
                        break;
                    default:
                        $this->render_overview($user_id);
                }
                ?>
            </div>
            
            <div id="khm-account-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login required message
     */
    private function render_login_required(): string {
        $login_url = wp_login_url(get_permalink());
        $register_url = wp_registration_url();

        ob_start();
        ?>
        <div class="khm-login-required">
            <p><?php esc_html_e('You must be logged in to view your account.', 'khm-membership'); ?></p>
            <a href="<?php echo esc_url($login_url); ?>" class="khm-button khm-button-primary">
                <?php esc_html_e('Log In', 'khm-membership'); ?>
            </a>
            <?php if ( get_option('users_can_register') ) : ?>
                <a href="<?php echo esc_url($register_url); ?>" class="khm-button khm-button-secondary">
                    <?php esc_html_e('Register', 'khm-membership'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render account navigation tabs
     */
    private function render_navigation( string $current_section ): void {
        $sections = [
            'overview' => __('Overview', 'khm-membership'),
            'memberships' => __('Memberships', 'khm-membership'),
            'orders' => __('Order History', 'khm-membership'),
            'profile' => __('Profile', 'khm-membership'),
        ];

        $sections = apply_filters('khm_account_sections', $sections);

        ?>
        <nav class="khm-account-nav">
            <?php foreach ( $sections as $slug => $label ) : ?>
                <a href="<?php echo esc_url(add_query_arg('section', $slug)); ?>" 
                    class="khm-account-nav-item<?php echo $current_section === $slug ? ' active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Render overview section
     */
    private function render_overview( int $user_id ): void {
        $user = get_userdata($user_id);
        $memberships = $this->membership_repo->findActive($user_id);
        $recent_orders = $this->order_repo->findByUser($user_id, 5);

        ?>
        <div class="khm-account-overview">
            <?php // translators: %s is the user's display name. ?>
            <h2><?php printf(esc_html__('Welcome, %s', 'khm-membership'), esc_html($user->display_name)); ?></h2>
            
            <div class="khm-account-stats">
                <div class="khm-stat">
                    <span class="khm-stat-value"><?php echo count($memberships); ?></span>
                    <span class="khm-stat-label"><?php esc_html_e('Active Memberships', 'khm-membership'); ?></span>
                </div>
                <div class="khm-stat">
                    <span class="khm-stat-value"><?php echo count($recent_orders); ?></span>
                    <span class="khm-stat-label"><?php esc_html_e('Recent Orders', 'khm-membership'); ?></span>
                </div>
            </div>

            <?php if ( ! empty($memberships) ) : ?>
                <h3><?php esc_html_e('Active Memberships', 'khm-membership'); ?></h3>
                <?php $this->render_membership_list($memberships); ?>
            <?php else : ?>
                <p><?php esc_html_e('You do not have any active memberships.', 'khm-membership'); ?></p>
                <a href="<?php echo esc_url(home_url('/membership-levels/')); ?>" class="khm-button khm-button-primary">
                    <?php esc_html_e('View Membership Levels', 'khm-membership'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render memberships section
     */
    private function render_memberships( int $user_id ): void {
        $memberships = $this->membership_repo->findActive($user_id);

        ?>
        <div class="khm-account-memberships">
            <h2><?php esc_html_e('My Memberships', 'khm-membership'); ?></h2>
            
            <?php if ( ! empty($memberships) ) : ?>
                <?php $this->render_membership_list($memberships, true); ?>
            <?php else : ?>
                <p><?php esc_html_e('You do not have any active memberships.', 'khm-membership'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render membership list
     */
    private function render_membership_list( array $memberships, bool $show_actions = false ): void {
        ?>
        <div class="khm-membership-list">
            <?php foreach ( $memberships as $membership ) : ?>
                <?php
                $level = $this->get_level_info($membership->membership_id);
                $subscription_info = $show_actions ? $this->get_subscription_info(get_current_user_id(), $membership->membership_id) : null;
                ?>
                <div class="khm-membership-card">
                    <div class="khm-membership-header">
                        <h4><?php echo $level ? esc_html($level->name) : esc_html__('Unknown Level', 'khm-membership'); ?></h4>
                        <span class="khm-badge khm-badge-<?php echo esc_attr($membership->status); ?>">
                            <?php echo esc_html( $this->format_membership_status_label( $membership, $subscription_info ) ); ?>
                        </span>
                    </div>
                    
                    <div class="khm-membership-details">
                        <p>
                            <strong><?php esc_html_e('Start Date:', 'khm-membership'); ?></strong>
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($membership->start_date))); ?>
                        </p>
                        
                        <?php if ( $membership->end_date ) : ?>
                            <p>
                                <strong><?php esc_html_e('End Date:', 'khm-membership'); ?></strong>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($membership->end_date))); ?>
                            </p>
                        <?php else : ?>
                            <p>
                                <strong><?php esc_html_e('End Date:', 'khm-membership'); ?></strong>
                                <?php esc_html_e('Never (recurring)', 'khm-membership'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ( $show_actions ) : ?>
                        <div class="khm-membership-actions">
                            <?php if ( in_array( $membership->status, [ 'active', 'grace' ], true ) ) : ?>
                                <?php if ( $subscription_info && ! empty( $subscription_info['cancel_at_period_end'] ) ) : ?>
                                    <button type="button"
                                            class="khm-button khm-button-reactivate"
                                            data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                        <?php esc_html_e('Reactivate Subscription', 'khm-membership'); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button"
                                            class="khm-button khm-button-cancel-period-end"
                                            data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                        <?php esc_html_e('Cancel at Period End', 'khm-membership'); ?>
                                    </button>
                                    <button type="button"
                                            class="khm-button khm-button-cancel-now"
                                            data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                        <?php esc_html_e('Cancel Now', 'khm-membership'); ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button"
                                        class="khm-button khm-button-pause"
                                        data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                    <?php esc_html_e('Pause Membership', 'khm-membership'); ?>
                                </button>
                            <?php elseif ( 'paused' === $membership->status ) : ?>
                                <button type="button"
                                        class="khm-button khm-button-resume"
                                        data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                    <?php esc_html_e('Resume Membership', 'khm-membership'); ?>
                                </button>
                                <button type="button"
                                        class="khm-button khm-button-cancel-now"
                                        data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                    <?php esc_html_e('Cancel Now', 'khm-membership'); ?>
                                </button>
                            <?php else : ?>
                                <button type="button"
                                        class="khm-button khm-button-reactivate"
                                        data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                    <?php esc_html_e('Reactivate Membership', 'khm-membership'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ( in_array( $membership->status, [ 'active', 'grace', 'paused' ], true ) ) : ?>
                                <button type="button"
                                        class="khm-button khm-button-update-card-toggle"
                                        data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                    <?php esc_html_e('Update Card', 'khm-membership'); ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ( in_array( $membership->status, [ 'active', 'grace', 'paused' ], true ) ) : ?>
                            <div class="khm-update-card" id="khm-update-card-<?php echo esc_attr($membership->membership_id); ?>" style="display:none;">
                                <h5><?php esc_html_e('Update Payment Method', 'khm-membership'); ?></h5>
                                <div class="khm-card-element" id="khm-card-element-<?php echo esc_attr($membership->membership_id); ?>"></div>
                                <div class="khm-card-errors" id="khm-card-errors-<?php echo esc_attr($membership->membership_id); ?>" role="alert"></div>
                                <button type="button"
                                        class="khm-button khm-button-primary khm-button-save-card"
                                        data-level-id="<?php echo esc_attr($membership->membership_id); ?>">
                                    <?php esc_html_e('Save Card', 'khm-membership'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function format_membership_status_label( object $membership, ?array $subscription_info ): string {
        if ( $subscription_info && ! empty( $subscription_info['cancel_at_period_end'] ) && ! empty( $subscription_info['current_period_end'] ) ) {
            $cancel_date = date_i18n( get_option( 'date_format' ), (int) $subscription_info['current_period_end'] );
            return sprintf( __( 'Active (cancels %s)', 'khm-membership' ), $cancel_date );
        }

        $status = strtolower( $membership->status ?? '' );

        if ( 'grace' === $status ) {
            $grace = $membership->grace_enddate ?? $membership->enddate ?? $membership->grace_end_date ?? $membership->end_date ?? null;
            if ( $grace ) {
                return sprintf( __( 'Grace Period (ends %s)', 'khm-membership' ), date_i18n( get_option( 'date_format' ), strtotime( $grace ) ) );
            }
            return __( 'Grace Period', 'khm-membership' );
        }

        if ( 'paused' === $status ) {
            return __( 'Paused', 'khm-membership' );
        }

        if ( 'past_due' === $status ) {
            return __( 'Past Due', 'khm-membership' );
        }

        if ( '' === $status ) {
            return __( 'Unknown', 'khm-membership' );
        }

        return ucfirst( $status );
    }

    /**
     * Render orders section
     */
    private function render_orders( int $user_id ): void {
        $orders = $this->order_repo->findByUser($user_id, 50);

        ?>
        <div class="khm-account-orders">
            <h2><?php esc_html_e('Order History', 'khm-membership'); ?></h2>
            
            <?php if ( ! empty($orders) ) : ?>
                <table class="khm-orders-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Order #', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Date', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Membership', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Amount', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Status', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Actions', 'khm-membership'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $orders as $order ) : ?>
                            <?php $level = $this->get_level_info($order->membership_id); ?>
                            <tr>
                                <td><?php echo esc_html($order->code); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order->timestamp))); ?></td>
                                <td><?php echo $level ? esc_html($level->name) : '—'; ?></td>
                                <td><?php echo esc_html($this->format_price($order->total)); ?></td>
                                <td><span class="khm-badge khm-badge-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(ucfirst($order->status)); ?></span></td>
                                <td>
                                    <?php 
                                        $rest_nonce = wp_create_nonce('wp_rest');
                                        $invoice_url = esc_url( add_query_arg( '_wpnonce', $rest_nonce, rest_url( 'khm/v1/orders/' . rawurlencode($order->code) . '/invoice' ) ) );
                                            $pdf_url = esc_url( add_query_arg( '_wpnonce', $rest_nonce, rest_url( 'khm/v1/orders/' . rawurlencode($order->code) . '/invoice/pdf' ) ) );
                                    ?>
                                    <a href="<?php echo $invoice_url; ?>" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('View Invoice', 'khm-membership'); ?>
                                    </a>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo $pdf_url; ?>" target="_blank" rel="noopener noreferrer">
                                            <?php esc_html_e('Download PDF', 'khm-membership'); ?>
                                        </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('You have no orders yet.', 'khm-membership'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render profile section
     */
    private function render_profile( int $user_id ): void {
        $user = get_userdata($user_id);

        ?>
        <div class="khm-account-profile">
            <h2><?php esc_html_e('Profile', 'khm-membership'); ?></h2>
            
            <div class="khm-profile-info">
                <p>
                    <strong><?php esc_html_e('Name:', 'khm-membership'); ?></strong>
                    <?php echo esc_html($user->display_name); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Email:', 'khm-membership'); ?></strong>
                    <?php echo esc_html($user->user_email); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Username:', 'khm-membership'); ?></strong>
                    <?php echo esc_html($user->user_login); ?>
                </p>
            </div>
            
            <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="khm-button khm-button-primary">
                <?php esc_html_e('Edit Profile', 'khm-membership'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Handle cancel membership AJAX request
     */
    public function handle_cancel_membership(): void {
        check_ajax_referer('khm_account', 'nonce');

        if ( ! is_user_logged_in() ) {
            wp_send_json_error([ 'message' => __('You must be logged in.', 'khm-membership') ]);
        }

        $membership_id = isset($_POST['membership_id']) ? intval($_POST['membership_id']) : 0;

        if ( ! $membership_id ) {
            wp_send_json_error([ 'message' => __('Invalid membership ID.', 'khm-membership') ]);
        }

        $user_id = get_current_user_id();

        // Verify this membership belongs to the current user
        $membership = $this->membership_repo->find($membership_id);

        if ( ! $membership || $membership->user_id !== $user_id ) {
            wp_send_json_error([ 'message' => __('Invalid membership.', 'khm-membership') ]);
        }

        // Cancel the membership
        $success = $this->membership_repo->cancel($membership_id);

        if ( $success ) {
            do_action('khm_membership_cancelled_by_user', $membership_id, $user_id);

            wp_send_json_success([
                'message' => __('Your membership has been cancelled.', 'khm-membership'),
            ]);
        } else {
            wp_send_json_error([ 'message' => __('Failed to cancel membership.', 'khm-membership') ]);
        }
    }

    /**
     * Get membership level info
     */
    private function get_level_info( int $level_id ) {
        return $this->level_repo->get($level_id, true);
    }

    /**
     * Get subscription info from Stripe (cancel_at_period_end, current_period_end)
     */
    private function get_subscription_info( int $user_id, int $level_id ): ?array {
        // Find subscription order
        $orders = $this->order_repo->findByUser($user_id, [ 'membership_id' => $level_id, 'limit' => 50 ]);
        $subOrder = null;
        foreach ($orders as $o) {
            if (!empty($o->subscription_transaction_id)) { $subOrder = $o; break; }
        }

        if (!$subOrder || strtolower($subOrder->gateway ?? '') !== 'stripe') {
            return null;
        }

        $secret = get_option('khm_stripe_secret_key', '');
        if (empty($secret)) {
            return null;
        }

        if ( ! class_exists('\\Stripe\\Stripe') ) {
            require_once dirname(__DIR__, 2) . '/vendor/stripe/stripe-php/init.php';
        }

        \Stripe\Stripe::setApiKey($secret);

        try {
            $subscription = \Stripe\Subscription::retrieve($subOrder->subscription_transaction_id);
            return [
                'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
                'current_period_end' => $subscription->current_period_end ?? null,
            ];
        } catch (\Exception $e) {
            error_log('Stripe subscription fetch error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format price with currency
     */
    private function format_price( float $amount ): string {
        return '$' . number_format($amount, 2);
    }
}

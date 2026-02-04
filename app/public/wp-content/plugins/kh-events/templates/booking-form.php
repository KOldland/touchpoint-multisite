<?php
/**
 * Event Booking Form Template
 *
 * Override this template by copying it to yourtheme/kh-events/booking-form.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $event_id - Event post ID
// $event - Event post object
// $booking_settings - Booking configuration
// $user - Current user data (if logged in)
?>

<div class="kh-booking-form-container">
    <?php if (!is_user_logged_in() && $booking_settings['require_login']): ?>
        <div class="kh-booking-login-required">
            <p><?php _e('Please log in to book this event.', 'kh-events'); ?></p>
            <?php wp_login_form(array('redirect' => get_permalink($event_id))); ?>
            <p><?php _e('Don\'t have an account?', 'kh-events'); ?> <a href="<?php echo wp_registration_url(); ?>"><?php _e('Register here', 'kh-events'); ?></a></p>
        </div>
    <?php else: ?>
        <form id="kh-booking-form" class="kh-booking-form" method="post" action="">
            <?php wp_nonce_field('kh_event_booking', 'kh_booking_nonce'); ?>
            <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">

            <div class="kh-booking-form-section">
                <h4><?php _e('Booking Details', 'kh-events'); ?></h4>

                <?php if ($booking_settings['collect_attendee_info']): ?>
                    <div class="kh-form-row">
                        <label for="attendee_name"><?php _e('Full Name', 'kh-events'); ?> <span class="required">*</span></label>
                        <input type="text" id="attendee_name" name="attendee_name" value="<?php echo esc_attr($user ? $user->display_name : ''); ?>" required>
                    </div>

                    <div class="kh-form-row">
                        <label for="attendee_email"><?php _e('Email Address', 'kh-events'); ?> <span class="required">*</span></label>
                        <input type="email" id="attendee_email" name="attendee_email" value="<?php echo esc_attr($user ? $user->user_email : ''); ?>" required>
                    </div>

                    <div class="kh-form-row">
                        <label for="attendee_phone"><?php _e('Phone Number', 'kh-events'); ?></label>
                        <input type="tel" id="attendee_phone" name="attendee_phone">
                    </div>
                <?php endif; ?>

                <?php if ($booking_settings['enable_quantity']): ?>
                    <div class="kh-form-row">
                        <label for="booking_quantity"><?php _e('Number of Tickets', 'kh-events'); ?> <span class="required">*</span></label>
                        <input type="number" id="booking_quantity" name="booking_quantity" value="1" min="1" max="<?php echo esc_attr($booking_settings['max_quantity'] ?: 10); ?>" required>
                    </div>
                <?php endif; ?>

                <?php if ($booking_settings['collect_additional_info']): ?>
                    <div class="kh-form-row">
                        <label for="additional_info"><?php _e('Additional Information', 'kh-events'); ?></label>
                        <textarea id="additional_info" name="additional_info" rows="4" placeholder="<?php esc_attr_e('Any special requirements or notes...', 'kh-events'); ?>"></textarea>
                    </div>
                <?php endif; ?>

                <?php if ($booking_settings['enable_coupon']): ?>
                    <div class="kh-form-row">
                        <label for="coupon_code"><?php _e('Coupon Code', 'kh-events'); ?></label>
                        <div class="kh-coupon-input-group">
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="<?php esc_attr_e('Enter coupon code', 'kh-events'); ?>">
                            <button type="button" id="apply-coupon" class="kh-btn kh-btn-secondary"><?php _e('Apply', 'kh-events'); ?></button>
                        </div>
                        <div id="coupon-message" class="kh-coupon-message" style="display: none;"></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($booking_settings['payment_enabled'] && class_exists('KH_Payment_Gateways')): ?>
                <div class="kh-booking-form-section">
                    <h4><?php _e('Payment Information', 'kh-events'); ?></h4>

                    <div class="kh-form-row">
                        <label><?php _e('Payment Method', 'kh-events'); ?> <span class="required">*</span></label>
                        <div class="kh-payment-methods">
                            <?php if ($booking_settings['stripe_enabled']): ?>
                                <label class="kh-payment-method">
                                    <input type="radio" name="payment_method" value="stripe" checked>
                                    <span><?php _e('Credit Card (Stripe)', 'kh-events'); ?></span>
                                </label>
                            <?php endif; ?>

                            <?php if ($booking_settings['paypal_enabled']): ?>
                                <label class="kh-payment-method">
                                    <input type="radio" name="payment_method" value="paypal">
                                    <span><?php _e('PayPal', 'kh-events'); ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="stripe-payment-form" class="kh-payment-form" style="display: none;">
                        <div class="kh-form-row">
                            <label for="card-element"><?php _e('Credit Card', 'kh-events'); ?></label>
                            <div id="card-element"></div>
                            <div id="card-errors" role="alert"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="kh-booking-form-section">
                <div class="kh-form-row">
                    <label class="kh-checkbox-label">
                        <input type="checkbox" name="accept_terms" required>
                        <?php printf(__('I accept the %s', 'kh-events'), '<a href="#" target="_blank">' . __('Terms and Conditions', 'kh-events') . '</a>'); ?> <span class="required">*</span>
                    </label>
                </div>

                <?php if ($booking_settings['enable_newsletter']): ?>
                    <div class="kh-form-row">
                        <label class="kh-checkbox-label">
                            <input type="checkbox" name="newsletter_signup">
                            <?php _e('Subscribe to our newsletter for updates', 'kh-events'); ?>
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="kh-booking-form-actions">
                <button type="submit" class="kh-btn kh-btn-primary kh-booking-submit">
                    <?php echo esc_html($booking_settings['payment_enabled'] ? __('Complete Booking & Payment', 'kh-events') : __('Book Now', 'kh-events')); ?>
                </button>
                <div class="kh-booking-spinner" style="display: none;">
                    <span class="spinner"></span> <?php _e('Processing...', 'kh-events'); ?>
                </div>
            </div>

            <div id="kh-booking-message" class="kh-booking-message" style="display: none;"></div>
        </form>
    <?php endif; ?>
</div>
<?php
/**
 * Bookings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Bookings {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_shortcode('kh_event_booking_form', array($this, 'booking_form_shortcode'));
        add_action('wp_ajax_kh_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_kh_submit_booking', array($this, 'submit_booking'));
        add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('save_post', array($this, 'handle_booking_status_change'));
        add_action('wp_ajax_kh_process_refund', array($this, 'process_refund'));
        add_action('manage_kh_booking_posts_columns', array($this, 'add_booking_columns'));
        add_action('manage_kh_booking_posts_custom_column', array($this, 'render_booking_columns'), 10, 2);
    }

    public function register_post_type() {
        register_post_type('kh_booking', array(
            'labels' => array(
                'name' => __('Bookings', 'kh-events'),
                'singular_name' => __('Booking', 'kh-events'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'kh-events',
            'supports' => array('title'),
            'capability_type' => 'post',
        ));
    }

    public function enqueue_scripts() {
        if ( function_exists( 'kh_events_is_builder_preview' ) && kh_events_is_builder_preview() ) {
            return;
        }
        // Enqueue Stripe.js if Stripe is enabled
        $payment_handler = KH_Payment_Handler::instance();
        $gateways = $payment_handler->get_available_gateways();

        if (isset($gateways['stripe'])) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), '3', true);
        }

        wp_enqueue_script('kh-events-booking', KH_EVENTS_URL . 'assets/js/booking.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_localize_script('kh-events-booking', 'kh_events_booking', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_booking_nonce'),
            'stripe_publishable_key' => isset($gateways['stripe']) ? $gateways['stripe']->get_setting('publishable_key') : '',
        ));
    }

    public function add_booking_meta_boxes() {
        // Check permissions before adding meta boxes
        if (class_exists('KH_Event_Permissions')) {
            $permissions = KH_Event_Permissions::instance();
            if (!$permissions->can_view_bookings(true)) {
                return;
            }
        }

        add_meta_box(
            'kh_booking_details',
            __('Booking Details', 'kh-events'),
            array($this, 'render_booking_meta_box'),
            'kh_booking',
            'normal',
            'high'
        );

        add_meta_box(
            'kh_booking_actions',
            __('Booking Actions', 'kh-events'),
            array($this, 'render_booking_actions_meta_box'),
            'kh_booking',
            'side',
            'high'
        );
    }

    public function render_booking_meta_box($post) {
        $event_id = get_post_meta($post->ID, '_kh_booking_event_id', true);
        $attendee_name = get_post_meta($post->ID, '_kh_booking_attendee_name', true);
        $attendee_email = get_post_meta($post->ID, '_kh_booking_attendee_email', true);
        $tickets = get_post_meta($post->ID, '_kh_booking_tickets', true);
        $status = get_post_meta($post->ID, '_kh_booking_status', true);
        $payment_status = get_post_meta($post->ID, '_kh_booking_payment_status', true);
        $payment_gateway = get_post_meta($post->ID, '_kh_booking_payment_gateway', true);
        $transaction_id = get_post_meta($post->ID, '_kh_booking_transaction_id', true);
        $total_amount = get_post_meta($post->ID, '_kh_booking_total_amount', true);
        $refund_id = get_post_meta($post->ID, '_kh_booking_refund_id', true);
        $refund_amount = get_post_meta($post->ID, '_kh_booking_refund_amount', true);
        $refund_date = get_post_meta($post->ID, '_kh_booking_refund_date', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Event', 'kh-events'); ?></label></th>
                <td><?php echo get_the_title($event_id); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Attendee Name', 'kh-events'); ?></label></th>
                <td><?php echo esc_html($attendee_name); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Attendee Email', 'kh-events'); ?></label></th>
                <td><?php echo esc_html($attendee_email); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Tickets', 'kh-events'); ?></label></th>
                <td>
                    <?php if ($tickets): ?>
                        <ul>
                            <?php foreach ($tickets as $ticket): ?>
                                <li><?php echo esc_html($ticket['name']); ?> (<?php echo $ticket['quantity']; ?> x $<?php echo number_format($ticket['price'], 2); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Total Amount', 'kh-events'); ?></label></th>
                <td>$<?php echo number_format($total_amount, 2); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Payment Status', 'kh-events'); ?></label></th>
                <td><?php echo ucfirst($payment_status); ?></td>
            </tr>
            <?php if ($payment_gateway): ?>
            <tr>
                <th><label><?php _e('Payment Gateway', 'kh-events'); ?></label></th>
                <td><?php echo ucfirst($payment_gateway); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($transaction_id): ?>
            <tr>
                <th><label><?php _e('Transaction ID', 'kh-events'); ?></label></th>
                <td><?php echo esc_html($transaction_id); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($refund_id): ?>
            <tr>
                <th><label><?php _e('Refund ID', 'kh-events'); ?></label></th>
                <td><?php echo esc_html($refund_id); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Refund Amount', 'kh-events'); ?></label></th>
                <td>$<?php echo number_format($refund_amount, 2); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Refund Date', 'kh-events'); ?></label></th>
                <td><?php echo esc_html($refund_date); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="kh_booking_status"><?php _e('Booking Status', 'kh-events'); ?></label></th>
                <td>
                    <select name="kh_booking_status" id="kh_booking_status">
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'kh-events'); ?></option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php _e('Confirmed', 'kh-events'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'kh-events'); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <?php if ($payment_status === 'completed' && !$refund_id): ?>
        <div class="kh-refund-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
            <h4><?php _e('Process Refund', 'kh-events'); ?></h4>
            <p><?php _e('Issue a refund for this booking. This will process the refund through the original payment gateway.', 'kh-events'); ?></p>
            <p>
                <label for="refund_amount"><?php _e('Refund Amount', 'kh-events'); ?>:</label>
                <input type="number" id="refund_amount" name="refund_amount" step="0.01" min="0.01" max="<?php echo $total_amount; ?>" value="<?php echo $total_amount; ?>" />
                <button type="button" id="kh-process-refund" class="button button-secondary" data-booking-id="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce('kh_refund_nonce'); ?>">
                    <?php _e('Process Refund', 'kh-events'); ?>
                </button>
            </p>
            <div id="kh-refund-message"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#kh-process-refund').on('click', function() {
                var button = $(this);
                var bookingId = button.data('booking-id');
                var nonce = button.data('nonce');
                var refundAmount = parseFloat($('#refund_amount').val());

                if (!refundAmount || refundAmount <= 0) {
                    $('#kh-refund-message').html('<p class="error"><?php _e('Please enter a valid refund amount.', 'kh-events'); ?></p>');
                    return;
                }

                button.prop('disabled', true).text('<?php _e('Processing...', 'kh-events'); ?>');

                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'kh_process_refund',
                        booking_id: bookingId,
                        refund_amount: refundAmount,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#kh-refund-message').html('<p class="success">' + response.data.message + '</p>');
                            location.reload();
                        } else {
                            $('#kh-refund-message').html('<p class="error">' + response.data.message + '</p>');
                            button.prop('disabled', false).text('<?php _e('Process Refund', 'kh-events'); ?>');
                        }
                    },
                    error: function() {
                        $('#kh-refund-message').html('<p class="error"><?php _e('An error occurred. Please try again.', 'kh-events'); ?></p>');
                        button.prop('disabled', false).text('<?php _e('Process Refund', 'kh-events'); ?>');
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        <?php
    }

    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
        ), $atts);

        $event_id = intval($atts['event_id']);
        $tickets = get_post_meta($event_id, '_kh_event_tickets', true);

        if (!$tickets) {
            return '<p>' . __('No tickets available for this event.', 'kh-events') . '</p>';
        }

        // Get available payment gateways
        $payment_handler = KH_Payment_Handler::instance();
        $available_gateways = $payment_handler->get_available_gateways();

        ob_start();
        ?>
        <div class="kh-booking-form">
            <h3><?php _e('Book Tickets', 'kh-events'); ?></h3>
            <form id="kh-booking-form" method="post">
                <?php wp_nonce_field('kh_booking_nonce', 'kh_booking_nonce'); ?>
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>" />

                <p>
                    <label for="attendee_name"><?php _e('Your Name', 'kh-events'); ?>:</label>
                    <input type="text" id="attendee_name" name="attendee_name" required />
                </p>
                <p>
                    <label for="attendee_email"><?php _e('Your Email', 'kh-events'); ?>:</label>
                    <input type="email" id="attendee_email" name="attendee_email" required />
                </p>

                <h4><?php _e('Select Tickets', 'kh-events'); ?></h4>
                <div id="kh-ticket-selection">
                <?php foreach ($tickets as $index => $ticket): ?>
                    <div class="kh-ticket-selection">
                        <h5><?php echo esc_html($ticket['name']); ?></h5>
                        <p><?php echo esc_html($ticket['description']); ?></p>
                        <p><?php _e('Price', 'kh-events'); ?>: $<?php echo number_format($ticket['price'], 2); ?></p>
                        <p>
                            <label><?php _e('Quantity', 'kh-events'); ?>:</label>
                            <input type="number" name="tickets[<?php echo $index; ?>][quantity]" class="ticket-quantity" min="0" max="<?php echo $ticket['quantity']; ?>" data-price="<?php echo $ticket['price']; ?>" />
                            <input type="hidden" name="tickets[<?php echo $index; ?>][name]" value="<?php echo esc_attr($ticket['name']); ?>" />
                            <input type="hidden" name="tickets[<?php echo $index; ?>][price]" value="<?php echo $ticket['price']; ?>" />
                        </p>
                    </div>
                <?php endforeach; ?>
                </div>

                <div id="kh-payment-section" style="display: none;">
                    <h4><?php _e('Payment Information', 'kh-events'); ?></h4>
                    <div id="kh-total-amount"></div>

                    <?php if (!empty($available_gateways)): ?>
                        <div class="kh-payment-gateways">
                            <h5><?php _e('Select Payment Method', 'kh-events'); ?></h5>
                            <?php foreach ($available_gateways as $gateway_id => $gateway): ?>
                                <label>
                                    <input type="radio" name="payment_gateway" value="<?php echo esc_attr($gateway_id); ?>" />
                                    <?php echo esc_html($gateway->get_gateway_name()); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div id="kh-stripe-payment" class="payment-fields" style="display: none;">
                            <div id="card-element"></div>
                            <div id="card-errors" role="alert"></div>
                        </div>

                        <div id="kh-paypal-payment" class="payment-fields" style="display: none;">
                            <p><?php _e('PayPal payment will be processed after form submission.', 'kh-events'); ?></p>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No payment methods are currently available.', 'kh-events'); ?></p>
                    <?php endif; ?>
                </div>

                <?php do_action('kh_events_booking_form_before_submit'); ?>

                <p>
                    <button type="submit" class="button" id="kh-submit-booking"><?php _e('Submit Booking', 'kh-events'); ?></button>
                </p>
            </form>
            <div id="kh-booking-message"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var stripe, cardElement;

            // Initialize Stripe if available
            <?php if (isset($available_gateways['stripe'])): ?>
            stripe = Stripe('<?php echo esc_js($available_gateways['stripe']->get_setting('publishable_key')); ?>');
            var elements = stripe.elements();
            cardElement = elements.create('card');
            cardElement.mount('#card-element');
            <?php endif; ?>

            // Update total when ticket quantities change
            $('.ticket-quantity').on('change', function() {
                updateTotal();
            });

            function updateTotal() {
                var total = 0;
                $('.ticket-quantity').each(function() {
                    var quantity = parseInt($(this).val()) || 0;
                    var price = parseFloat($(this).data('price'));
                    total += quantity * price;
                });

                if (total > 0) {
                    $('#kh-total-amount').html('<p><strong><?php _e('Total', 'kh-events'); ?>: $' + total.toFixed(2) + '</strong></p>');
                    $('#kh-payment-section').show();
                } else {
                    $('#kh-total-amount').empty();
                    $('#kh-payment-section').hide();
                }
            }

            // Show/hide payment fields based on gateway selection
            $('input[name="payment_gateway"]').on('change', function() {
                $('.payment-fields').hide();
                if ($(this).val() === 'stripe') {
                    $('#kh-stripe-payment').show();
                } else if ($(this).val() === 'paypal') {
                    $('#kh-paypal-payment').show();
                }
            });

            $('#kh-booking-form').submit(function(e) {
                e.preventDefault();

                var total = 0;
                $('.ticket-quantity').each(function() {
                    var quantity = parseInt($(this).val()) || 0;
                    var price = parseFloat($(this).data('price'));
                    total += quantity * price;
                });

                if (total === 0) {
                    $('#kh-booking-message').html('<p class="error"><?php _e('Please select at least one ticket.', 'kh-events'); ?></p>');
                    return;
                }

                var paymentGateway = $('input[name="payment_gateway"]:checked').val();
                if (!paymentGateway) {
                    $('#kh-booking-message').html('<p class="error"><?php _e('Please select a payment method.', 'kh-events'); ?></p>');
                    return;
                }

                $('#kh-submit-booking').prop('disabled', true).text('<?php _e('Processing...', 'kh-events'); ?>');

                if (paymentGateway === 'stripe') {
                    // Process Stripe payment
                    stripe.createToken(cardElement).then(function(result) {
                        if (result.error) {
                            $('#card-errors').text(result.error.message);
                            $('#kh-submit-booking').prop('disabled', false).text('<?php _e('Submit Booking', 'kh-events'); ?>');
                        } else {
                            submitBooking(result.token.id, paymentGateway, total);
                        }
                    });
                } else {
                    // For other gateways, submit directly
                    submitBooking('', paymentGateway, total);
                }
            });

            function submitBooking(paymentToken, paymentGateway, total) {
                var formData = $('#kh-booking-form').serialize();
                formData += '&action=kh_submit_booking&payment_token=' + encodeURIComponent(paymentToken) + '&payment_gateway=' + encodeURIComponent(paymentGateway) + '&total_amount=' + total;

                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#kh-booking-message').html('<p class="success">' + response.data.message + '</p>');
                            $('#kh-booking-form')[0].reset();
                            $('#kh-payment-section').hide();
                            $('#kh-total-amount').empty();
                        } else {
                            $('#kh-booking-message').html('<p class="error">' + response.data.message + '</p>');
                        }
                        $('#kh-submit-booking').prop('disabled', false).text('<?php _e('Submit Booking', 'kh-events'); ?>');
                    },
                    error: function() {
                        $('#kh-booking-message').html('<p class="error"><?php _e('An error occurred. Please try again.', 'kh-events'); ?></p>');
                        $('#kh-submit-booking').prop('disabled', false).text('<?php _e('Submit Booking', 'kh-events'); ?>');
                    }
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function submit_booking() {
        if (!wp_verify_nonce($_POST['kh_booking_nonce'], 'kh_booking_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'kh-events')));
        }

        $event_id = intval($_POST['event_id']);
        $attendee_name = sanitize_text_field($_POST['attendee_name']);
        $attendee_email = sanitize_email($_POST['attendee_email']);
        $tickets = $_POST['tickets'];
        $payment_gateway = sanitize_text_field($_POST['payment_gateway']);
        $payment_token = sanitize_text_field($_POST['payment_token']);
        $total_amount = floatval($_POST['total_amount']);

        // Validate tickets
        $selected_tickets = array();
        $calculated_total = 0;
        $requested_quantity = 0;
        foreach ($tickets as $ticket) {
            if (intval($ticket['quantity']) > 0) {
                $selected_tickets[] = array(
                    'name' => sanitize_text_field($ticket['name']),
                    'price' => floatval($ticket['price']),
                    'quantity' => intval($ticket['quantity']),
                );
                $calculated_total += floatval($ticket['price']) * intval($ticket['quantity']);
                $requested_quantity += intval($ticket['quantity']);
            }
        }

        if (empty($selected_tickets)) {
            wp_send_json_error(array('message' => __('Please select at least one ticket.', 'kh-events')));
        }

        // Verify total amount
        if (abs($calculated_total - $total_amount) > 0.01) {
            wp_send_json_error(array('message' => __('Payment amount mismatch.', 'kh-events')));
        }

        if (!$this->event_has_capacity($event_id, $requested_quantity)) {
            wp_send_json_error(array('message' => __('Sorry, this event is fully booked or does not have enough capacity for your request.', 'kh-events')), 400);
        }

        // Process payment if amount > 0
        $payment_result = null;
        $payment_status = 'pending';
        $transaction_id = '';

        if ($total_amount > 0) {
            if (empty($payment_gateway)) {
                wp_send_json_error(array('message' => __('Please select a payment method.', 'kh-events')));
            }

            $payment_handler = KH_Payment_Handler::instance();
            $payment_data = array(
                'amount' => $total_amount,
                'currency' => 'USD',
                'token' => $payment_token,
                'order_id' => 'booking_' . time() . '_' . $event_id,
                'customer_email' => $attendee_email,
                'description' => sprintf(__('Event booking for %s', 'kh-events'), get_the_title($event_id)),
            );

            $payment_result = $payment_handler->process_payment($payment_gateway, $payment_data);

            if (!$payment_result['success']) {
                wp_send_json_error(array('message' => __('Payment failed: ', 'kh-events') . $payment_result['error']));
            }

            $payment_status = 'completed';
            $transaction_id = $payment_result['transaction_id'];

            // Idempotency: if we already have a booking with this transaction_id, return it.
            $existing = $this->get_booking_by_transaction($transaction_id, $event_id);
            if ($existing) {
                wp_send_json_success(array(
                    'message' => __('Booking already recorded.', 'kh-events'),
                    'booking_id' => $existing,
                ));
            }
        } else {
            $payment_status = 'free';
        }

        // Create booking post
        $booking_id = wp_insert_post(array(
            'post_type' => 'kh_booking',
            'post_title' => sprintf(__('Booking for %s by %s', 'kh-events'), get_the_title($event_id), $attendee_name),
            'post_status' => 'publish',
        ));

        if ($booking_id) {
            update_post_meta($booking_id, '_kh_booking_event_id', $event_id);
            update_post_meta($booking_id, '_kh_booking_attendee_name', $attendee_name);
            update_post_meta($booking_id, '_kh_booking_attendee_email', $attendee_email);
            update_post_meta($booking_id, '_kh_booking_tickets', $selected_tickets);
            update_post_meta($booking_id, '_kh_booking_status', 'confirmed');
            update_post_meta($booking_id, '_kh_booking_payment_status', $payment_status);
            update_post_meta($booking_id, '_kh_booking_payment_gateway', $payment_gateway);
            update_post_meta($booking_id, '_kh_booking_transaction_id', $transaction_id);
            update_post_meta($booking_id, '_kh_booking_total_amount', $total_amount);

            if ($payment_result) {
                update_post_meta($booking_id, '_kh_booking_payment_result', $payment_result);
            }

            // Send confirmation email
            $this->send_booking_confirmation($booking_id);
            
            // Send admin notification
            $this->send_admin_booking_notification($booking_id);

            // Trigger GDPR consent storage
            do_action('kh_events_booking_created', $booking_id, $_POST);

            wp_send_json_success(array('message' => __('Booking submitted successfully. You will receive a confirmation email soon.', 'kh-events')));
        } else {
            // If booking creation failed but payment succeeded, we should refund
            if ($payment_result && $payment_result['success']) {
                $payment_handler->refund_payment($payment_gateway, $transaction_id);
            }

            wp_send_json_error(array('message' => __('Failed to submit booking. Please try again.', 'kh-events')));
        }
    }

    private function send_booking_confirmation($booking_id) {
        $event_id = get_post_meta($booking_id, '_kh_booking_event_id', true);
        $attendee_name = get_post_meta($booking_id, '_kh_booking_attendee_name', true);
        $attendee_email = get_post_meta($booking_id, '_kh_booking_attendee_email', true);
        $attendee_phone = get_post_meta($booking_id, '_kh_booking_attendee_phone', true);
        $tickets = get_post_meta($booking_id, '_kh_booking_tickets', true);
        $payment_status = get_post_meta($booking_id, '_kh_booking_payment_status', true);
        $total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);
        $transaction_id = get_post_meta($booking_id, '_kh_booking_transaction_id', true);

        // Get event details
        $event_title = get_the_title($event_id);
        $event_date = get_post_meta($event_id, '_kh_event_date', true);
        $event_time = get_post_meta($event_id, '_kh_event_time', true);
        $event_location = get_post_meta($event_id, '_kh_event_location', true);

        // Prepare ticket details
        $ticket_details = '';
        if (!empty($tickets)) {
            $ticket_details = '<div class="booking-info"><h3>🎭 Your Tickets</h3>';
            foreach ($tickets as $ticket) {
                $ticket_details .= '<div class="highlight"><strong>' . esc_html($ticket['name']) . '</strong><br>';
                $ticket_details .= 'Quantity: ' . intval($ticket['quantity']) . ' × $' . number_format($ticket['price'], 2) . ' = $' . number_format($ticket['quantity'] * $ticket['price'], 2) . '</div>';
            }
            $ticket_details .= '</div>';
        }

        // Prepare payment details
        $payment_details = '';
        if ($payment_status === 'completed') {
            $payment_details = '<div class="highlight"><strong>✅ Payment Received</strong><br>';
            $payment_details .= 'Your payment of $' . number_format($total_amount, 2) . ' has been processed successfully.';
            if (!empty($transaction_id)) {
                $payment_details .= '<br>Transaction ID: ' . esc_html($transaction_id);
            }
            $payment_details .= '</div>';
        }

        // Prepare email data
        $email_data = [
            'attendee_name' => $attendee_name,
            'attendee_email' => $attendee_email,
            'attendee_phone' => $attendee_phone ?: '',
            'event_title' => $event_title,
            'event_date' => $event_date ? date('F j, Y', strtotime($event_date)) : '',
            'event_time' => $event_time ?: '',
            'event_location' => $event_location ?: '',
            'booking_id' => $booking_id,
            'booking_date' => get_the_date('F j, Y \a\t g:i A', $booking_id),
            'tickets' => $tickets,
            'payment_status' => $payment_status,
            'total_amount' => $total_amount,
            'transaction_id' => $transaction_id,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'contact_email' => get_option('admin_email'),
            'payment_method' => get_post_meta($booking_id, '_kh_booking_payment_method', true) ?: 'stripe',
            'payment_date' => get_post_meta($booking_id, '_kh_booking_payment_date', true) ? 
                date('F j, Y \a\t g:i A', strtotime(get_post_meta($booking_id, '_kh_booking_payment_date', true))) : 
                get_the_date('F j, Y \a\t g:i A', $booking_id),
            'ticket_details' => $ticket_details,
            'payment_details' => $payment_details
        ];

        // Try to use KHM enhanced email service first
        if ($this->send_enhanced_email('booking_confirmation', $attendee_email, $email_data)) {
            return;
        }

        // Fallback to basic wp_mail
        $subject = sprintf(__('Booking Confirmation for %s', 'kh-events'), $event_title);

        $message = sprintf(__('Dear %s,

Thank you for booking tickets for %s.

Booking Details:
', 'kh-events'), $attendee_name, $event_title);

        $message .= __('Tickets:', 'kh-events') . "\n";
        foreach ($tickets as $ticket) {
            $message .= sprintf(__('- %s: %d x $%.2f', 'kh-events'), $ticket['name'], $ticket['quantity'], $ticket['price']) . "\n";
        }

        if ($total_amount > 0) {
            $message .= sprintf(__('Total Amount: $%.2f', 'kh-events'), $total_amount) . "\n";
            $message .= sprintf(__('Payment Status: %s', 'kh-events'), ucfirst($payment_status)) . "\n";
        } else {
            $message .= __('This is a free booking.', 'kh-events') . "\n";
        }

        $message .= "\n" . __('Best regards,', 'kh-events') . "\n" . get_bloginfo('name');

        wp_mail($attendee_email, $subject, $message);
    }

    public function handle_booking_status_change($post_id) {
        if (get_post_type($post_id) !== 'kh_booking') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $old_status = get_post_meta($post_id, '_kh_booking_status', true);
        $new_status = isset($_POST['kh_booking_status']) ? sanitize_text_field($_POST['kh_booking_status']) : $old_status;

        // If status changed to cancelled, send cancellation email
        if ($old_status !== 'cancelled' && $new_status === 'cancelled') {
            $payment_status = get_post_meta($post_id, '_kh_booking_payment_status', true);
            if ($payment_status === 'completed') {
                $this->process_automatic_refund($post_id);
            }
            
            // Send cancellation email to attendee
            $cancellation_reason = isset($_POST['kh_cancellation_reason']) ? sanitize_text_field($_POST['kh_cancellation_reason']) : '';
            $this->send_cancellation_email($post_id, $cancellation_reason);
        }

        // Update the booking status
        update_post_meta($post_id, '_kh_booking_status', $new_status);
    }

    private function process_automatic_refund($booking_id) {
        $transaction_id = get_post_meta($booking_id, '_kh_booking_transaction_id', true);
        $payment_gateway = get_post_meta($booking_id, '_kh_booking_payment_gateway', true);
        $total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);

        if (!$transaction_id || !$payment_gateway || !$total_amount) {
            return;
        }

        $payment_handler = KH_Payment_Handler::instance();
        $refund_result = $payment_handler->refund_payment($payment_gateway, $transaction_id, $total_amount);

        if ($refund_result['success']) {
            update_post_meta($booking_id, '_kh_booking_payment_status', 'refunded');
            update_post_meta($booking_id, '_kh_booking_refund_id', $refund_result['refund_id']);
            update_post_meta($booking_id, '_kh_booking_refund_amount', $total_amount);
            update_post_meta($booking_id, '_kh_booking_refund_date', current_time('mysql'));

            // Log the refund
            KH_Payment_Logger::log($payment_gateway, 'Automatic refund processed for booking ' . $booking_id . ', transaction: ' . $transaction_id);
        } else {
            // Log refund failure
            KH_Payment_Logger::log($payment_gateway, 'Automatic refund failed for booking ' . $booking_id . ', transaction: ' . $transaction_id . ', error: ' . $refund_result['error'], 'error');
        }
    }

    public function process_refund() {
        if (!wp_verify_nonce($_POST['nonce'], 'kh_refund_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'kh-events')));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'kh-events')));
        }

        $booking_id = intval($_POST['booking_id']);
        $refund_amount = floatval($_POST['refund_amount']);

        $transaction_id = get_post_meta($booking_id, '_kh_booking_transaction_id', true);
        $payment_gateway = get_post_meta($booking_id, '_kh_booking_payment_gateway', true);
        $total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);
        $payment_status = get_post_meta($booking_id, '_kh_booking_payment_status', true);

        if (!$transaction_id || $payment_status !== 'completed') {
            wp_send_json_error(array('message' => __('No valid payment found for refund.', 'kh-events')));
        }

        if ($refund_amount <= 0 || $refund_amount > $total_amount) {
            wp_send_json_error(array('message' => __('Invalid refund amount.', 'kh-events')));
        }

        $payment_handler = KH_Payment_Handler::instance();
        $refund_result = $payment_handler->refund_payment($payment_gateway, $transaction_id, $refund_amount);

        if ($refund_result['success']) {
            update_post_meta($booking_id, '_kh_booking_payment_status', 'refunded');
            update_post_meta($booking_id, '_kh_booking_refund_id', $refund_result['refund_id']);
            update_post_meta($booking_id, '_kh_booking_refund_amount', $refund_amount);
            update_post_meta($booking_id, '_kh_booking_refund_date', current_time('mysql'));

            // Log the refund
            KH_Payment_Logger::log($payment_gateway, 'Manual refund processed for booking ' . $booking_id . ', amount: $' . $refund_amount);

            wp_send_json_success(array('message' => __('Refund processed successfully.', 'kh-events')));
        } else {
            KH_Payment_Logger::log($payment_gateway, 'Manual refund failed for booking ' . $booking_id . ', error: ' . $refund_result['error'], 'error');
            wp_send_json_error(array('message' => __('Refund failed: ', 'kh-events') . $refund_result['error']));
        }
    }

    /**
     * Send email using KHM enhanced email service if available
     *
     * @param string $template Template key
     * @param string $recipient Email recipient
     * @param array $data Email data
     * @return bool Success status
     */
    private function send_enhanced_email($template, $recipient, $data) {
        // Try to get KHM enhanced email service
        $email_service = $this->get_khm_email_service();
        
        if (!$email_service) {
            return false;
        }

        try {
            // Set subject based on template
            $subjects = [
                'booking_confirmation' => sprintf(__('Booking Confirmation for %s', 'kh-events'), $data['event_title']),
                'admin_booking_notification' => __('New Event Booking Received', 'kh-events'),
                'payment_success' => __('Payment Successful', 'kh-events'),
                'booking_cancellation' => __('Booking Cancellation Confirmation', 'kh-events')
            ];
            
            $subject = $subjects[$template] ?? __('KH-Events Notification', 'kh-events');
            
            // Send using enhanced service
            return $email_service->setSubject($subject)->send($template, $recipient, $data);
        } catch (Exception $e) {
            error_log('KH-Events: Enhanced email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get KHM enhanced email service instance
     *
     * @return object|null Email service instance or null if not available
     */
    private function get_khm_email_service() {
        // Check global enhanced email service
        if (isset($GLOBALS['khm_enhanced_email']) && $GLOBALS['khm_enhanced_email'] instanceof \KHM\Services\EnhancedEmailService) {
            return $GLOBALS['khm_enhanced_email'];
        }

        // Try to get from marketing suite
        if (function_exists('KHM\Plugin::get_marketing_suite')) {
            $marketing_suite = \KHM\Plugin::get_marketing_suite();
            if ($marketing_suite && method_exists($marketing_suite, 'get_email_service')) {
                return $marketing_suite->get_email_service();
            }
        }

        // Try to create enhanced email service directly
        if (class_exists('\KHM\Services\EnhancedEmailService')) {
            try {
                $plugin_dir = WP_PLUGIN_DIR . '/khm-plugin';
                if (file_exists($plugin_dir)) {
                    return new \KHM\Services\EnhancedEmailService($plugin_dir);
                }
            } catch (Exception $e) {
                error_log('KH-Events: Could not create enhanced email service: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Send admin notification for new booking
     *
     * @param int $booking_id
     */
    private function send_admin_booking_notification($booking_id) {
        $event_id = get_post_meta($booking_id, '_kh_booking_event_id', true);
        $attendee_name = get_post_meta($booking_id, '_kh_booking_attendee_name', true);
        $attendee_email = get_post_meta($booking_id, '_kh_booking_attendee_email', true);
        $attendee_phone = get_post_meta($booking_id, '_kh_booking_attendee_phone', true);
        $tickets = get_post_meta($booking_id, '_kh_booking_tickets', true);
        $payment_status = get_post_meta($booking_id, '_kh_booking_payment_status', true);
        $total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);
        $transaction_id = get_post_meta($booking_id, '_kh_booking_transaction_id', true);
        $booking_status = get_post_meta($booking_id, '_kh_booking_status', true);

        // Get event details
        $event_title = get_the_title($event_id);
        $event_date = get_post_meta($event_id, '_kh_event_date', true);
        $event_time = get_post_meta($event_id, '_kh_event_time', true);
        $event_location = get_post_meta($event_id, '_kh_event_location', true);

        // Prepare email data
        $email_data = [
            'attendee_name' => $attendee_name,
            'attendee_email' => $attendee_email,
            'attendee_phone' => $attendee_phone ?: '',
            'event_title' => $event_title,
            'event_id' => $event_id,
            'event_date' => $event_date ? date('F j, Y', strtotime($event_date)) : '',
            'event_time' => $event_time ?: '',
            'event_location' => $event_location ?: '',
            'booking_id' => $booking_id,
            'booking_date' => get_the_date('F j, Y \a\t g:i A', $booking_id),
            'booking_status' => $booking_status,
            'tickets' => $tickets,
            'payment_status' => $payment_status,
            'total_amount' => $total_amount,
            'transaction_id' => $transaction_id,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'admin_booking_url' => admin_url('post.php?post=' . $booking_id . '&action=edit'),
            'contact_email' => get_option('admin_email')
        ];

        // Get admin email recipients
        $admin_emails = $this->get_admin_notification_emails();
        
        foreach ($admin_emails as $admin_email) {
            $this->send_enhanced_email('admin_booking_notification', $admin_email, $email_data);
        }
    }

    /**
     * Get admin emails for booking notifications
     *
     * @return array List of admin email addresses
     */
    private function get_admin_notification_emails() {
        $emails = [get_option('admin_email')];
        
        // Add additional admin emails from settings if configured
        $additional_emails = get_option('kh_events_admin_notification_emails', '');
        if (!empty($additional_emails)) {
            $additional_emails = array_map('trim', explode(',', $additional_emails));
            $emails = array_merge($emails, $additional_emails);
        }
        
        return array_unique($emails);
    }

    /**
     * Send payment success email to attendee
     *
     * @param int $booking_id
     */
    public function send_payment_success_email($booking_id) {
        $attendee_email = get_post_meta($booking_id, '_kh_booking_attendee_email', true);
        if (empty($attendee_email)) {
            return;
        }

        $event_id = get_post_meta($booking_id, '_kh_booking_event_id', true);
        $attendee_name = get_post_meta($booking_id, '_kh_booking_attendee_name', true);
        $tickets = get_post_meta($booking_id, '_kh_booking_tickets', true);
        $total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);
        $transaction_id = get_post_meta($booking_id, '_kh_booking_transaction_id', true);
        $payment_method = get_post_meta($booking_id, '_kh_booking_payment_method', true) ?: 'stripe';

        // Get event details
        $event_title = get_the_title($event_id);
        $event_date = get_post_meta($event_id, '_kh_event_date', true);
        $event_time = get_post_meta($event_id, '_kh_event_time', true);
        $event_location = get_post_meta($event_id, '_kh_event_location', true);

        // Prepare email data
        $email_data = [
            'attendee_name' => $attendee_name,
            'attendee_email' => $attendee_email,
            'event_title' => $event_title,
            'event_date' => $event_date ? date('F j, Y', strtotime($event_date)) : '',
            'event_time' => $event_time ?: '',
            'event_location' => $event_location ?: '',
            'booking_id' => $booking_id,
            'tickets' => $tickets,
            'total_amount' => $total_amount,
            'transaction_id' => $transaction_id,
            'payment_method' => $payment_method,
            'payment_date' => get_post_meta($booking_id, '_kh_booking_payment_date', true) ? 
                date('F j, Y \a\t g:i A', strtotime(get_post_meta($booking_id, '_kh_booking_payment_date', true))) : 
                current_time('F j, Y \a\t g:i A'),
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'contact_email' => get_option('admin_email')
        ];

        $this->send_enhanced_email('payment_success', $attendee_email, $email_data);
    }

    /**
     * Send booking cancellation email to attendee
     *
     * @param int $booking_id
     * @param string $reason Cancellation reason
     */
    public function send_cancellation_email($booking_id, $reason = '') {
        $attendee_email = get_post_meta($booking_id, '_kh_booking_attendee_email', true);
        if (empty($attendee_email)) {
            return;
        }

        $event_id = get_post_meta($booking_id, '_kh_booking_event_id', true);
        $attendee_name = get_post_meta($booking_id, '_kh_booking_attendee_name', true);
        $tickets = get_post_meta($booking_id, '_kh_booking_tickets', true);
        $total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);
        $refund_amount = get_post_meta($booking_id, '_kh_booking_refund_amount', true);
        $refund_status = get_post_meta($booking_id, '_kh_booking_refund_status', true) ?: 'pending';

        // Get event details
        $event_title = get_the_title($event_id);
        $event_date = get_post_meta($event_id, '_kh_event_date', true);
        $event_time = get_post_meta($event_id, '_kh_event_time', true);
        $event_location = get_post_meta($event_id, '_kh_event_location', true);

        // Prepare email data
        $email_data = [
            'attendee_name' => $attendee_name,
            'attendee_email' => $attendee_email,
            'event_title' => $event_title,
            'event_date' => $event_date ? date('F j, Y', strtotime($event_date)) : '',
            'event_time' => $event_time ?: '',
            'event_location' => $event_location ?: '',
            'booking_id' => $booking_id,
            'cancellation_date' => current_time('F j, Y \a\t g:i A'),
            'cancellation_reason' => $reason ?: __('Requested by customer', 'kh-events'),
            'tickets' => $tickets,
            'total_amount' => $total_amount,
            'refund_amount' => $refund_amount,
            'refund_status' => $refund_status,
            'refund_transaction_id' => get_post_meta($booking_id, '_kh_booking_refund_id', true),
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'contact_email' => get_option('admin_email')
        ];

        $this->send_enhanced_email('booking_cancellation', $attendee_email, $email_data);
    }

    public function add_booking_columns($columns) {
        $columns['attendee'] = __('Attendee', 'kh-events');
        $columns['event'] = __('Event', 'kh-events');
        $columns['payment_status'] = __('Payment', 'kh-events');
        $columns['total'] = __('Total', 'kh-events');
        $columns['booking_status'] = __('Status', 'kh-events');
        return $columns;
    }

    public function render_booking_columns($column, $post_id) {
        switch ($column) {
            case 'attendee':
                $name = get_post_meta($post_id, '_kh_booking_attendee_name', true);
                $email = get_post_meta($post_id, '_kh_booking_attendee_email', true);
                echo esc_html($name) . '<br><small>' . esc_html($email) . '</small>';
                break;

            case 'event':
                $event_id = get_post_meta($post_id, '_kh_booking_event_id', true);
                if ($event_id) {
                    echo '<a href="' . get_edit_post_link($event_id) . '">' . get_the_title($event_id) . '</a>';
                }
                break;

            case 'payment_status':
                $payment_status = get_post_meta($post_id, '_kh_booking_payment_status', true);
                $gateway = get_post_meta($post_id, '_kh_booking_payment_gateway', true);
                $status_class = 'payment-' . $payment_status;

                echo '<span class="payment-status ' . $status_class . '">' . ucfirst($payment_status) . '</span>';
                if ($gateway) {
                    echo '<br><small>' . ucfirst($gateway) . '</small>';
                }
                break;

            case 'total':
                $total = get_post_meta($post_id, '_kh_booking_total_amount', true);
                echo '$' . number_format($total, 2);
                break;

            case 'booking_status':
                $status = get_post_meta($post_id, '_kh_booking_status', true);
                $status_class = 'booking-' . $status;
                echo '<span class="booking-status ' . $status_class . '">' . ucfirst($status) . '</span>';
                break;
        }
    }

    /**
     * Check event capacity against requested quantity.
     */
    private function event_has_capacity($event_id, $requested_quantity) {
        $capacity = intval(get_post_meta($event_id, '_event_capacity', true));
        if ($capacity <= 0) {
            return true; // unlimited
        }

        $current = $this->get_confirmed_ticket_count($event_id);
        return ($current + $requested_quantity) <= $capacity;
    }

    /**
     * Count confirmed tickets for an event.
     */
    private function get_confirmed_ticket_count($event_id) {
        $bookings = get_posts(array(
            'post_type'      => 'kh_booking',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => '_kh_booking_event_id',
                    'value' => $event_id,
                ),
                array(
                    'key'   => '_kh_booking_status',
                    'value' => 'confirmed',
                ),
            ),
        ));

        $count = 0;
        foreach ($bookings as $booking_id) {
            $tickets = get_post_meta($booking_id, '_kh_booking_tickets', true);
            if (is_array($tickets)) {
                foreach ($tickets as $ticket) {
                    $count += intval($ticket['quantity'] ?? 0);
                }
            }
        }

        return $count;
    }

    /**
     * Find an existing booking by transaction id to enforce idempotency.
     */
    private function get_booking_by_transaction($transaction_id, $event_id = null) {
        if (!$transaction_id) {
            return 0;
        }

        $meta_query = array(
            array(
                'key'   => '_kh_booking_transaction_id',
                'value' => $transaction_id,
            ),
        );

        if ($event_id) {
            $meta_query[] = array(
                'key'   => '_kh_booking_event_id',
                'value' => $event_id,
            );
            $meta_query['relation'] = 'AND';
        }

        $existing = get_posts(array(
            'post_type'      => 'kh_booking',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ));

        return !empty($existing) ? intval($existing[0]) : 0;
    }
}

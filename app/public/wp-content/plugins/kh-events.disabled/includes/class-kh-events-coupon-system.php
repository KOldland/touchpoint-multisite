<?php
/**
 * KH Events Coupon System
 *
 * Discount codes and promotional system for event bookings
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Coupon_System {

    private static $instance = null;

    // Coupon types
    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';
    const TYPE_FREE_TICKET = 'free_ticket';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_coupon_meta_boxes'));
        add_action('save_post', array($this, 'save_coupon_meta'));
        add_action('wp_ajax_kh_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_nopriv_kh_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_kh_remove_coupon', array($this, 'ajax_remove_coupon'));
        add_action('wp_ajax_nopriv_kh_remove_coupon', array($this, 'ajax_remove_coupon'));

        // Admin hooks
        add_filter('manage_kh_coupon_posts_columns', array($this, 'add_coupon_columns'));
        add_action('manage_kh_coupon_posts_custom_column', array($this, 'render_coupon_columns'), 10, 2);
        add_action('admin_menu', array($this, 'add_coupon_submenu'));

        // Booking integration
        add_action('kh_events_before_booking_calculation', array($this, 'apply_coupon_to_booking'), 10, 2);
        add_action('kh_events_booking_saved', array($this, 'record_coupon_usage'), 10, 2);
    }

    /**
     * Register coupon post type
     */
    public function register_post_type() {
        register_post_type('kh_coupon', array(
            'labels' => array(
                'name' => __('Coupons', 'kh-events'),
                'singular_name' => __('Coupon', 'kh-events'),
                'add_new' => __('Add New Coupon', 'kh-events'),
                'add_new_item' => __('Add New Coupon', 'kh-events'),
                'edit_item' => __('Edit Coupon', 'kh-events'),
                'new_item' => __('New Coupon', 'kh-events'),
                'view_item' => __('View Coupon', 'kh-events'),
                'search_items' => __('Search Coupons', 'kh-events'),
                'not_found' => __('No coupons found', 'kh-events'),
                'not_found_in_trash' => __('No coupons found in trash', 'kh-events'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'kh-events',
            'supports' => array('title'),
            'capability_type' => 'post',
        ));
    }

    /**
     * Add coupon submenu
     */
    public function add_coupon_submenu() {
        add_submenu_page(
            'kh-events',
            __('Coupons', 'kh-events'),
            __('Coupons', 'kh-events'),
            'manage_options',
            'edit.php?post_type=kh_coupon'
        );
    }

    /**
     * Add coupon meta boxes
     */
    public function add_coupon_meta_boxes() {
        add_meta_box(
            'kh_coupon_details',
            __('Coupon Details', 'kh-events'),
            array($this, 'render_coupon_meta_box'),
            'kh_coupon',
            'normal',
            'high'
        );

        add_meta_box(
            'kh_coupon_usage',
            __('Usage Statistics', 'kh-events'),
            array($this, 'render_coupon_usage_meta_box'),
            'kh_coupon',
            'side',
            'default'
        );
    }

    /**
     * Render coupon meta box
     */
    public function render_coupon_meta_box($post) {
        wp_nonce_field('kh_coupon_meta', 'kh_coupon_meta_nonce');

        $coupon_code = get_post_meta($post->ID, '_coupon_code', true);
        $discount_type = get_post_meta($post->ID, '_discount_type', true);
        $discount_value = get_post_meta($post->ID, '_discount_value', true);
        $usage_limit = get_post_meta($post->ID, '_usage_limit', true);
        $usage_count = get_post_meta($post->ID, '_usage_count', true) ?: 0;
        $expiry_date = get_post_meta($post->ID, '_expiry_date', true);
        $minimum_amount = get_post_meta($post->ID, '_minimum_amount', true);
        $maximum_discount = get_post_meta($post->ID, '_maximum_discount', true);
        $allowed_events = get_post_meta($post->ID, '_allowed_events', true) ?: array();
        $allowed_users = get_post_meta($post->ID, '_allowed_users', true) ?: array();
        $exclude_sale_items = get_post_meta($post->ID, '_exclude_sale_items', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="coupon_code"><?php _e('Coupon Code', 'kh-events'); ?></label></th>
                <td>
                    <input type="text" id="coupon_code" name="coupon_code" value="<?php echo esc_attr($coupon_code); ?>" class="regular-text" required />
                    <p class="description"><?php _e('The code customers enter to apply the coupon.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="discount_type"><?php _e('Discount Type', 'kh-events'); ?></label></th>
                <td>
                    <select id="discount_type" name="discount_type" required>
                        <option value=""><?php _e('Select type...', 'kh-events'); ?></option>
                        <option value="percentage" <?php selected($discount_type, 'percentage'); ?>><?php _e('Percentage Discount', 'kh-events'); ?></option>
                        <option value="fixed" <?php selected($discount_type, 'fixed'); ?>><?php _e('Fixed Amount', 'kh-events'); ?></option>
                        <option value="free_ticket" <?php selected($discount_type, 'free_ticket'); ?>><?php _e('Free Ticket', 'kh-events'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="discount_value"><?php _e('Discount Value', 'kh-events'); ?></label></th>
                <td>
                    <input type="number" id="discount_value" name="discount_value" value="<?php echo esc_attr($discount_value); ?>" step="0.01" min="0" required />
                    <span id="discount-symbol"><?php echo $discount_type === 'percentage' ? '%' : '$'; ?></span>
                    <p class="description"><?php _e('The discount amount or percentage.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="usage_limit"><?php _e('Usage Limit', 'kh-events'); ?></label></th>
                <td>
                    <input type="number" id="usage_limit" name="usage_limit" value="<?php echo esc_attr($usage_limit); ?>" min="0" />
                    <p class="description"><?php _e('How many times this coupon can be used. Leave blank for unlimited.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label><?php _e('Current Usage', 'kh-events'); ?></label></th>
                <td>
                    <span class="usage-count"><?php echo intval($usage_count); ?></span>
                    <?php if ($usage_limit): ?>
                        <span class="usage-limit">/ <?php echo intval($usage_limit); ?></span>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label for="expiry_date"><?php _e('Expiry Date', 'kh-events'); ?></label></th>
                <td>
                    <input type="datetime-local" id="expiry_date" name="expiry_date" value="<?php echo esc_attr($expiry_date); ?>" />
                    <p class="description"><?php _e('The date when this coupon expires. Leave blank for no expiry.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="minimum_amount"><?php _e('Minimum Amount', 'kh-events'); ?></label></th>
                <td>
                    <input type="number" id="minimum_amount" name="minimum_amount" value="<?php echo esc_attr($minimum_amount); ?>" step="0.01" min="0" />
                    <p class="description"><?php _e('The minimum order amount required to use this coupon.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="maximum_discount"><?php _e('Maximum Discount', 'kh-events'); ?></label></th>
                <td>
                    <input type="number" id="maximum_discount" name="maximum_discount" value="<?php echo esc_attr($maximum_discount); ?>" step="0.01" min="0" />
                    <p class="description"><?php _e('The maximum discount amount for percentage coupons.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label><?php _e('Allowed Events', 'kh-events'); ?></label></th>
                <td>
                    <select name="allowed_events[]" multiple="multiple" class="wc-product-search" data-placeholder="<?php esc_attr_e('Search for events...', 'kh-events'); ?>" data-action="woocommerce_json_search_events">
                        <?php
                        if (!empty($allowed_events)) {
                            foreach ($allowed_events as $event_id) {
                                echo '<option value="' . esc_attr($event_id) . '" selected>' . esc_html(get_the_title($event_id)) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Restrict this coupon to specific events. Leave blank to allow all events.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label><?php _e('Allowed Users', 'kh-events'); ?></label></th>
                <td>
                    <select name="allowed_users[]" multiple="multiple" class="wc-customer-search" data-placeholder="<?php esc_attr_e('Search for users...', 'kh-events'); ?>">
                        <?php
                        if (!empty($allowed_users)) {
                            foreach ($allowed_users as $user_id) {
                                $user = get_user_by('id', $user_id);
                                if ($user) {
                                    echo '<option value="' . esc_attr($user_id) . '" selected>' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Restrict this coupon to specific users. Leave blank to allow all users.', 'kh-events'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="exclude_sale_items"><?php _e('Exclude Sale Items', 'kh-events'); ?></label></th>
                <td>
                    <input type="checkbox" id="exclude_sale_items" name="exclude_sale_items" value="yes" <?php checked($exclude_sale_items, 'yes'); ?> />
                    <label for="exclude_sale_items"><?php _e('Check this box if the coupon should not apply to items on sale.', 'kh-events'); ?></label>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('#discount_type').on('change', function() {
                var type = $(this).val();
                var symbol = type === 'percentage' ? '%' : '$';
                $('#discount-symbol').text(symbol);
            });
        });
        </script>
        <?php
    }

    /**
     * Render coupon usage meta box
     */
    public function render_coupon_usage_meta_box($post) {
        $usage_count = get_post_meta($post->ID, '_usage_count', true) ?: 0;
        $usage_limit = get_post_meta($post->ID, '_usage_limit', true);

        ?>
        <div class="coupon-usage-stats">
            <p><strong><?php _e('Times Used:', 'kh-events'); ?></strong> <?php echo intval($usage_count); ?></p>
            <?php if ($usage_limit): ?>
                <p><strong><?php _e('Usage Limit:', 'kh-events'); ?></strong> <?php echo intval($usage_limit); ?></p>
                <p><strong><?php _e('Remaining:', 'kh-events'); ?></strong> <?php echo max(0, intval($usage_limit) - intval($usage_count)); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save coupon meta
     */
    public function save_coupon_meta($post_id) {
        if (!isset($_POST['kh_coupon_meta_nonce']) || !wp_verify_nonce($_POST['kh_coupon_meta_nonce'], 'kh_coupon_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'coupon_code',
            'discount_type',
            'discount_value',
            'usage_limit',
            'expiry_date',
            'minimum_amount',
            'maximum_discount',
            'exclude_sale_items'
        );

        foreach ($fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            update_post_meta($post_id, '_' . $field, $value);
        }

        // Handle arrays
        if (isset($_POST['allowed_events'])) {
            $allowed_events = array_map('intval', $_POST['allowed_events']);
            update_post_meta($post_id, '_allowed_events', $allowed_events);
        }

        if (isset($_POST['allowed_users'])) {
            $allowed_users = array_map('intval', $_POST['allowed_users']);
            update_post_meta($post_id, '_allowed_users', $allowed_users);
        }

        // Generate coupon code if not set
        $coupon_code = get_post_meta($post_id, '_coupon_code', true);
        if (empty($coupon_code)) {
            $coupon_code = $this->generate_coupon_code();
            update_post_meta($post_id, '_coupon_code', $coupon_code);
        }
    }

    /**
     * Add coupon columns
     */
    public function add_coupon_columns($columns) {
        $columns['coupon_code'] = __('Code', 'kh-events');
        $columns['discount'] = __('Discount', 'kh-events');
        $columns['usage'] = __('Usage', 'kh-events');
        $columns['expiry'] = __('Expiry', 'kh-events');
        return $columns;
    }

    /**
     * Render coupon columns
     */
    public function render_coupon_columns($column, $post_id) {
        switch ($column) {
            case 'coupon_code':
                $code = get_post_meta($post_id, '_coupon_code', true);
                echo '<code>' . esc_html($code) . '</code>';
                break;

            case 'discount':
                $type = get_post_meta($post_id, '_discount_type', true);
                $value = get_post_meta($post_id, '_discount_value', true);
                if ($type === 'percentage') {
                    echo esc_html($value) . '%';
                } elseif ($type === 'fixed') {
                    echo '$' . esc_html($value);
                } elseif ($type === 'free_ticket') {
                    echo __('Free Ticket', 'kh-events');
                }
                break;

            case 'usage':
                $usage = get_post_meta($post_id, '_usage_count', true) ?: 0;
                $limit = get_post_meta($post_id, '_usage_limit', true);
                echo intval($usage);
                if ($limit) {
                    echo ' / ' . intval($limit);
                }
                break;

            case 'expiry':
                $expiry = get_post_meta($post_id, '_expiry_date', true);
                if ($expiry) {
                    $timestamp = strtotime($expiry);
                    echo date_i18n(get_option('date_format'), $timestamp);
                    if ($timestamp < time()) {
                        echo ' <span style="color: red;">(' . __('Expired', 'kh-events') . ')</span>';
                    }
                } else {
                    echo __('Never', 'kh-events');
                }
                break;
        }
    }

    /**
     * AJAX apply coupon
     */
    public function ajax_apply_coupon() {
        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        $event_id = intval($_POST['event_id']);
        $ticket_quantity = intval($_POST['ticket_quantity']);
        $base_amount = floatval($_POST['base_amount']);

        $result = $this->validate_and_apply_coupon($coupon_code, $event_id, $ticket_quantity, $base_amount);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX remove coupon
     */
    public function ajax_remove_coupon() {
        wp_send_json_success(array(
            'message' => __('Coupon removed successfully.', 'kh-events')
        ));
    }

    /**
     * Validate and apply coupon
     */
    public function validate_and_apply_coupon($coupon_code, $event_id, $ticket_quantity, $base_amount) {
        // Find coupon by code
        $coupon = $this->get_coupon_by_code($coupon_code);

        if (!$coupon) {
            return array(
                'success' => false,
                'message' => __('Invalid coupon code.', 'kh-events')
            );
        }

        // Check if coupon is active
        if (get_post_status($coupon->ID) !== 'publish') {
            return array(
                'success' => false,
                'message' => __('This coupon is not active.', 'kh-events')
            );
        }

        // Check expiry
        $expiry_date = get_post_meta($coupon->ID, '_expiry_date', true);
        if ($expiry_date && strtotime($expiry_date) < time()) {
            return array(
                'success' => false,
                'message' => __('This coupon has expired.', 'kh-events')
            );
        }

        // Check usage limit
        $usage_limit = get_post_meta($coupon->ID, '_usage_limit', true);
        $usage_count = get_post_meta($coupon->ID, '_usage_count', true) ?: 0;

        if ($usage_limit && $usage_count >= $usage_limit) {
            return array(
                'success' => false,
                'message' => __('This coupon has reached its usage limit.', 'kh-events')
            );
        }

        // Check minimum amount
        $minimum_amount = get_post_meta($coupon->ID, '_minimum_amount', true);
        if ($minimum_amount && $base_amount < $minimum_amount) {
            return array(
                'success' => false,
                'message' => sprintf(__('Minimum order amount of %s required.', 'kh-events'), wc_price($minimum_amount))
            );
        }

        // Check allowed events
        $allowed_events = get_post_meta($coupon->ID, '_allowed_events', true) ?: array();
        if (!empty($allowed_events) && !in_array($event_id, $allowed_events)) {
            return array(
                'success' => false,
                'message' => __('This coupon is not valid for this event.', 'kh-events')
            );
        }

        // Check allowed users
        $allowed_users = get_post_meta($coupon->ID, '_allowed_users', true) ?: array();
        if (!empty($allowed_users) && !in_array(get_current_user_id(), $allowed_users)) {
            return array(
                'success' => false,
                'message' => __('This coupon is not valid for your account.', 'kh-events')
            );
        }

        // Calculate discount
        $discount_type = get_post_meta($coupon->ID, '_discount_type', true);
        $discount_value = floatval(get_post_meta($coupon->ID, '_discount_value', true));
        $maximum_discount = floatval(get_post_meta($coupon->ID, '_maximum_discount', true));

        $discount_amount = 0;

        switch ($discount_type) {
            case 'percentage':
                $discount_amount = $base_amount * ($discount_value / 100);
                if ($maximum_discount > 0 && $discount_amount > $maximum_discount) {
                    $discount_amount = $maximum_discount;
                }
                break;

            case 'fixed':
                $discount_amount = min($discount_value, $base_amount);
                break;

            case 'free_ticket':
                // For free ticket, discount the price of one ticket
                $ticket_price = $base_amount / $ticket_quantity;
                $discount_amount = $ticket_price;
                break;
        }

        return array(
            'success' => true,
            'coupon_id' => $coupon->ID,
            'discount_amount' => $discount_amount,
            'final_amount' => max(0, $base_amount - $discount_amount),
            'message' => sprintf(__('Coupon applied! You saved %s.', 'kh-events'), wc_price($discount_amount))
        );
    }

    /**
     * Apply coupon to booking
     */
    public function apply_coupon_to_booking($booking_data, $event_id) {
        if (!isset($booking_data['coupon_code']) || empty($booking_data['coupon_code'])) {
            return $booking_data;
        }

        $coupon_result = $this->validate_and_apply_coupon(
            $booking_data['coupon_code'],
            $event_id,
            $booking_data['tickets'],
            $booking_data['total_amount']
        );

        if ($coupon_result['success']) {
            $booking_data['discount_amount'] = $coupon_result['discount_amount'];
            $booking_data['final_amount'] = $coupon_result['final_amount'];
            $booking_data['coupon_id'] = $coupon_result['coupon_id'];
        }

        return $booking_data;
    }

    /**
     * Record coupon usage
     */
    public function record_coupon_usage($booking_id, $booking_data) {
        if (isset($booking_data['coupon_id'])) {
            $usage_count = get_post_meta($booking_data['coupon_id'], '_usage_count', true) ?: 0;
            update_post_meta($booking_data['coupon_id'], '_usage_count', $usage_count + 1);

            // Store coupon usage in booking
            update_post_meta($booking_id, '_kh_booking_coupon_id', $booking_data['coupon_id']);
            update_post_meta($booking_id, '_kh_booking_discount_amount', $booking_data['discount_amount']);
        }
    }

    /**
     * Get coupon by code
     */
    private function get_coupon_by_code($code) {
        $coupons = get_posts(array(
            'post_type' => 'kh_coupon',
            'meta_key' => '_coupon_code',
            'meta_value' => $code,
            'posts_per_page' => 1
        ));

        return !empty($coupons) ? $coupons[0] : false;
    }

    /**
     * Generate unique coupon code
     */
    private function generate_coupon_code($length = 8) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
        } while ($this->get_coupon_by_code($code));

        return $code;
    }

    /**
     * Get active coupons for event
     */
    public function get_active_coupons_for_event($event_id) {
        $args = array(
            'post_type' => 'kh_coupon',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_allowed_events',
                    'value' => $event_id,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_allowed_events',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_allowed_events',
                    'value' => '',
                    'compare' => '='
                )
            )
        );

        $coupons = get_posts($args);
        $active_coupons = array();

        foreach ($coupons as $coupon) {
            $expiry_date = get_post_meta($coupon->ID, '_expiry_date', true);
            $usage_limit = get_post_meta($coupon->ID, '_usage_limit', true);
            $usage_count = get_post_meta($coupon->ID, '_usage_count', true) ?: 0;

            // Check if coupon is still valid
            if (($expiry_date && strtotime($expiry_date) < time()) ||
                ($usage_limit && $usage_count >= $usage_limit)) {
                continue;
            }

            $active_coupons[] = $coupon;
        }

        return $active_coupons;
    }
}
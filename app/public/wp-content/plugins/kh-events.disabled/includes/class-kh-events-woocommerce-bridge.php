<?php
/**
 * KH Events WooCommerce Bridge
 *
 * Integrates KH Events with WooCommerce for enhanced e-commerce functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_WooCommerce_Bridge {

    private static $instance = null;

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
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return;
        }

        add_action('init', array($this, 'init_bridge'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_event_product_options'));
        add_action('woocommerce_process_product_meta', array($this, 'save_event_product_options'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_event_details_on_product'));
        add_action('woocommerce_add_to_cart_validation', array($this, 'validate_event_booking'), 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_event_data_to_order_item'), 10, 4);
        add_action('woocommerce_order_status_completed', array($this, 'process_event_booking_on_order_complete'));

        // Add event tickets to My Account
        add_action('woocommerce_account_dashboard', array($this, 'display_event_tickets_in_account'));

        // Admin hooks
        add_action('admin_init', array($this, 'register_event_product_type'));
        add_filter('product_type_selector', array($this, 'add_event_product_type'));
        add_filter('woocommerce_product_data_tabs', array($this, 'add_event_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_event_product_data_panel'));
        add_action('woocommerce_process_product_meta_event', array($this, 'save_event_product_meta'));
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Initialize the bridge
     */
    public function init_bridge() {
        // Register custom product type
        $this->register_event_product_type();
    }

    /**
     * Register event product type
     */
    public function register_event_product_type() {
        if (!class_exists('WC_Product_Event')) {
            require_once KH_EVENTS_DIR . 'includes/woocommerce/class-wc-product-event.php';
        }
    }

    /**
     * Add event product type to selector
     */
    public function add_event_product_type($types) {
        $types['event'] = __('Event', 'kh-events');
        return $types;
    }

    /**
     * Add event product data tab
     */
    public function add_event_product_data_tab($tabs) {
        $tabs['event'] = array(
            'label' => __('Event Details', 'kh-events'),
            'target' => 'event_product_data',
            'class' => array('show_if_event'),
        );
        return $tabs;
    }

    /**
     * Add event product data panel
     */
    public function add_event_product_data_panel() {
        global $post;
        $event_id = get_post_meta($post->ID, '_linked_event_id', true);
        $max_tickets = get_post_meta($post->ID, '_max_tickets_per_order', true);
        $ticket_types = get_post_meta($post->ID, '_ticket_types', true) ?: array();

        ?>
        <div id="event_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="_linked_event_id"><?php _e('Linked Event', 'kh-events'); ?></label>
                    <select name="_linked_event_id" id="_linked_event_id" class="wc-product-search" data-placeholder="<?php esc_attr_e('Search for an event...', 'kh-events'); ?>" data-action="woocommerce_json_search_events">
                        <?php if ($event_id): ?>
                            <option value="<?php echo esc_attr($event_id); ?>" selected><?php echo esc_html(get_the_title($event_id)); ?></option>
                        <?php endif; ?>
                    </select>
                    <span class="description"><?php _e('Link this product to an existing event.', 'kh-events'); ?></span>
                </p>

                <p class="form-field">
                    <label for="_max_tickets_per_order"><?php _e('Max Tickets Per Order', 'kh-events'); ?></label>
                    <input type="number" name="_max_tickets_per_order" id="_max_tickets_per_order" value="<?php echo esc_attr($max_tickets); ?>" min="1" />
                </p>
            </div>

            <div class="options_group">
                <h4><?php _e('Ticket Types', 'kh-events'); ?></h4>
                <div id="ticket-types-container">
                    <?php if (!empty($ticket_types)): ?>
                        <?php foreach ($ticket_types as $index => $ticket): ?>
                            <div class="ticket-type-row">
                                <input type="text" name="ticket_types[<?php echo $index; ?>][name]" placeholder="Ticket Name" value="<?php echo esc_attr($ticket['name']); ?>" />
                                <input type="number" name="ticket_types[<?php echo $index; ?>][price]" placeholder="Price" step="0.01" value="<?php echo esc_attr($ticket['price']); ?>" />
                                <input type="number" name="ticket_types[<?php echo $index; ?>][quantity]" placeholder="Quantity" value="<?php echo esc_attr($ticket['quantity']); ?>" />
                                <button type="button" class="remove-ticket-type button">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="add-ticket-type" class="button"><?php _e('Add Ticket Type', 'kh-events'); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Save event product meta
     */
    public function save_event_product_meta($post_id) {
        $linked_event_id = isset($_POST['_linked_event_id']) ? intval($_POST['_linked_event_id']) : '';
        $max_tickets = isset($_POST['_max_tickets_per_order']) ? intval($_POST['_max_tickets_per_order']) : '';
        $ticket_types = isset($_POST['ticket_types']) ? $_POST['ticket_types'] : array();

        update_post_meta($post_id, '_linked_event_id', $linked_event_id);
        update_post_meta($post_id, '_max_tickets_per_order', $max_tickets);
        update_post_meta($post_id, '_ticket_types', $ticket_types);
    }

    /**
     * Add event product options (legacy support)
     */
    public function add_event_product_options() {
        global $post;

        echo '<div class="options_group">';

        woocommerce_wp_select(array(
            'id' => '_linked_event_id',
            'label' => __('Linked Event', 'kh-events'),
            'options' => $this->get_events_options(),
            'desc_tip' => true,
            'description' => __('Link this product to an existing event.', 'kh-events')
        ));

        woocommerce_wp_text_input(array(
            'id' => '_max_tickets_per_order',
            'label' => __('Max Tickets Per Order', 'kh-events'),
            'type' => 'number',
            'desc_tip' => true,
            'description' => __('Maximum number of tickets that can be purchased in a single order.', 'kh-events')
        ));

        echo '</div>';
    }

    /**
     * Save event product options (legacy support)
     */
    public function save_event_product_options($post_id) {
        $linked_event_id = isset($_POST['_linked_event_id']) ? intval($_POST['_linked_event_id']) : '';
        $max_tickets = isset($_POST['_max_tickets_per_order']) ? intval($_POST['_max_tickets_per_order']) : '';

        update_post_meta($post_id, '_linked_event_id', $linked_event_id);
        update_post_meta($post_id, '_max_tickets_per_order', $max_tickets);
    }

    /**
     * Display event details on product page
     */
    public function display_event_details_on_product() {
        global $product;

        $linked_event_id = get_post_meta($product->get_id(), '_linked_event_id', true);

        if (!$linked_event_id) {
            return;
        }

        $event = get_post($linked_event_id);
        if (!$event || $event->post_type !== 'kh_event') {
            return;
        }

        $event_date = get_post_meta($linked_event_id, '_kh_event_start_date', true);
        $event_time = get_post_meta($linked_event_id, '_kh_event_start_time', true);
        $event_location = get_post_meta($linked_event_id, '_kh_event_location', true);

        ?>
        <div class="kh-event-details-on-product">
            <h3><?php _e('Event Details', 'kh-events'); ?></h3>
            <div class="event-info">
                <?php if ($event_date): ?>
                    <p><strong><?php _e('Date:', 'kh-events'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($event_date))); ?></p>
                <?php endif; ?>

                <?php if ($event_time): ?>
                    <p><strong><?php _e('Time:', 'kh-events'); ?></strong> <?php echo esc_html($event_time); ?></p>
                <?php endif; ?>

                <?php if ($event_location): ?>
                    <p><strong><?php _e('Location:', 'kh-events'); ?></strong> <?php echo esc_html(get_the_title($event_location)); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Validate event booking
     */
    public function validate_event_booking($passed, $product_id, $quantity) {
        $linked_event_id = get_post_meta($product_id, '_linked_event_id', true);

        if (!$linked_event_id) {
            return $passed;
        }

        // Check if event is still available
        $event_status = get_post_meta($linked_event_id, '_kh_event_status', true);
        if ($event_status === 'cancelled' || $event_status === 'postponed') {
            wc_add_notice(__('This event is no longer available for booking.', 'kh-events'), 'error');
            return false;
        }

        // Check ticket availability
        $max_tickets = get_post_meta($product_id, '_max_tickets_per_order', true);
        if ($max_tickets && $quantity > $max_tickets) {
            wc_add_notice(sprintf(__('You can only purchase a maximum of %d tickets per order.', 'kh-events'), $max_tickets), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Add event data to order item
     */
    public function add_event_data_to_order_item($item, $cart_item_key, $values, $order) {
        $product_id = $item->get_product_id();
        $linked_event_id = get_post_meta($product_id, '_linked_event_id', true);

        if ($linked_event_id) {
            $item->add_meta_data('_event_id', $linked_event_id, true);
            $item->add_meta_data('_event_name', get_the_title($linked_event_id), true);
            $item->add_meta_data('_event_date', get_post_meta($linked_event_id, '_kh_event_start_date', true), true);
        }
    }

    /**
     * Process event booking when order is completed
     */
    public function process_event_booking_on_order_complete($order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {
            $event_id = $item->get_meta('_event_id');

            if ($event_id) {
                // Create booking record
                $booking_data = array(
                    'event_id' => $event_id,
                    'order_id' => $order_id,
                    'attendee_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'attendee_email' => $order->get_billing_email(),
                    'tickets' => $item->get_quantity(),
                    'total_amount' => $item->get_total(),
                    'payment_gateway' => 'woocommerce',
                    'payment_status' => 'completed',
                    'status' => 'confirmed'
                );

                $this->create_event_booking($booking_data);
            }
        }
    }

    /**
     * Display event tickets in My Account
     */
    public function display_event_tickets_in_account() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        // Get user's orders with event tickets
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status' => array('completed', 'processing'),
            'limit' => -1
        ));

        $event_tickets = array();

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $event_id = $item->get_meta('_event_id');

                if ($event_id) {
                    $event_tickets[] = array(
                        'order' => $order,
                        'item' => $item,
                        'event_id' => $event_id,
                        'event_name' => $item->get_meta('_event_name'),
                        'event_date' => $item->get_meta('_event_date'),
                        'quantity' => $item->get_quantity()
                    );
                }
            }
        }

        if (!empty($event_tickets)) {
            ?>
            <div class="kh-event-tickets-section">
                <h3><?php _e('My Event Tickets', 'kh-events'); ?></h3>
                <div class="event-tickets-list">
                    <?php foreach ($event_tickets as $ticket): ?>
                        <div class="event-ticket-item">
                            <h4><?php echo esc_html($ticket['event_name']); ?></h4>
                            <p><?php printf(__('Date: %s', 'kh-events'), esc_html(date_i18n(get_option('date_format'), strtotime($ticket['event_date'])))); ?></p>
                            <p><?php printf(__('Tickets: %d', 'kh-events'), $ticket['quantity']); ?></p>
                            <p><?php printf(__('Order: %s', 'kh-events'), $ticket['order']->get_order_number()); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Create event booking
     */
    private function create_event_booking($booking_data) {
        if (!class_exists('KH_Event_Bookings')) {
            return false;
        }

        $bookings = KH_Event_Bookings::instance();

        // Create booking post
        $booking_id = wp_insert_post(array(
            'post_title' => sprintf(__('Booking for %s', 'kh-events'), get_the_title($booking_data['event_id'])),
            'post_type' => 'kh_booking',
            'post_status' => 'publish'
        ));

        if ($booking_id) {
            // Save booking meta
            update_post_meta($booking_id, '_kh_booking_event_id', $booking_data['event_id']);
            update_post_meta($booking_id, '_kh_booking_attendee_name', $booking_data['attendee_name']);
            update_post_meta($booking_id, '_kh_booking_attendee_email', $booking_data['attendee_email']);
            update_post_meta($booking_id, '_kh_booking_tickets', $booking_data['tickets']);
            update_post_meta($booking_id, '_kh_booking_total_amount', $booking_data['total_amount']);
            update_post_meta($booking_id, '_kh_booking_payment_gateway', $booking_data['payment_gateway']);
            update_post_meta($booking_id, '_kh_booking_payment_status', $booking_data['payment_status']);
            update_post_meta($booking_id, '_kh_booking_status', $booking_data['status']);
            update_post_meta($booking_id, '_kh_booking_order_id', $booking_data['order_id']);

            return $booking_id;
        }

        return false;
    }

    /**
     * Get events options for select field
     */
    private function get_events_options() {
        $options = array('' => __('Select an event...', 'kh-events'));

        $events = get_posts(array(
            'post_type' => 'kh_event',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        ));

        foreach ($events as $event) {
            $options[$event->ID] = $event->post_title;
        }

        return $options;
    }

    /**
     * Get WooCommerce product types that are events
     */
    public function get_event_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_linked_event_id',
                    'compare' => 'EXISTS'
                )
            )
        );

        return get_posts($args);
    }
}
<?php
/**
 * WooCommerce Event Product Type
 *
 * Custom product type for event tickets
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Product_Event')) {

    class WC_Product_Event extends WC_Product {

        public function __construct($product = 0) {
            $this->product_type = 'event';
            parent::__construct($product);
        }

        /**
         * Get internal type
         */
        public function get_type() {
            return 'event';
        }

        /**
         * Check if product is purchasable
         */
        public function is_purchasable() {
            $is_purchasable = parent::is_purchasable();

            // Check if linked event is still available
            $linked_event_id = $this->get_linked_event_id();
            if ($linked_event_id) {
                $event_status = get_post_meta($linked_event_id, '_kh_event_status', true);
                if ($event_status === 'cancelled' || $event_status === 'postponed') {
                    $is_purchasable = false;
                }

                // Check if event date has passed
                $event_date = get_post_meta($linked_event_id, '_kh_event_start_date', true);
                if ($event_date && strtotime($event_date) < time()) {
                    $is_purchasable = false;
                }
            }

            return $is_purchasable;
        }

        /**
         * Get linked event ID
         */
        public function get_linked_event_id() {
            return $this->get_meta('_linked_event_id');
        }

        /**
         * Get max tickets per order
         */
        public function get_max_tickets_per_order() {
            return $this->get_meta('_max_tickets_per_order');
        }

        /**
         * Get ticket types
         */
        public function get_ticket_types() {
            return $this->get_meta('_ticket_types') ?: array();
        }

        /**
         * Get available ticket quantity
         */
        public function get_available_ticket_quantity() {
            $ticket_types = $this->get_ticket_types();

            if (empty($ticket_types)) {
                return $this->get_stock_quantity();
            }

            $total_available = 0;
            foreach ($ticket_types as $ticket) {
                $total_available += intval($ticket['quantity']);
            }

            return $total_available;
        }

        /**
         * Check if tickets are still available
         */
        public function has_available_tickets() {
            $available = $this->get_available_ticket_quantity();
            return $available > 0;
        }

        /**
         * Get event details for display
         */
        public function get_event_details() {
            $event_id = $this->get_linked_event_id();

            if (!$event_id) {
                return false;
            }

            $event = get_post($event_id);
            if (!$event || $event->post_type !== 'kh_event') {
                return false;
            }

            return array(
                'id' => $event_id,
                'title' => $event->post_title,
                'date' => get_post_meta($event_id, '_kh_event_start_date', true),
                'time' => get_post_meta($event_id, '_kh_event_start_time', true),
                'location' => get_post_meta($event_id, '_kh_event_location', true),
                'status' => get_post_meta($event_id, '_kh_event_status', true)
            );
        }
    }
}
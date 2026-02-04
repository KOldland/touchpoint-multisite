<?php
/**
 * Tickets Meta Box Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Tickets {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'kh_event_tickets',
            __('Event Tickets', 'kh-events'),
            array($this, 'render_meta_box'),
            'kh_event',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('kh_tickets_meta_nonce', 'kh_tickets_meta_nonce');

        $tickets = get_post_meta($post->ID, '_kh_event_tickets', true);
        if (!$tickets) {
            $tickets = array();
        }

        ?>
        <div id="kh-tickets-container">
            <?php foreach ($tickets as $index => $ticket): ?>
                <div class="kh-ticket-item" data-index="<?php echo $index; ?>">
                    <h4><?php _e('Ticket Type', 'kh-events'); ?> <?php echo $index + 1; ?></h4>
                    <p>
                        <label><?php _e('Name', 'kh-events'); ?>:</label>
                        <input type="text" name="kh_tickets[<?php echo $index; ?>][name]" value="<?php echo esc_attr($ticket['name']); ?>" />
                    </p>
                    <p>
                        <label><?php _e('Price', 'kh-events'); ?>:</label>
                        <input type="number" step="0.01" name="kh_tickets[<?php echo $index; ?>][price]" value="<?php echo esc_attr($ticket['price']); ?>" />
                    </p>
                    <p>
                        <label><?php _e('Quantity Available', 'kh-events'); ?>:</label>
                        <input type="number" name="kh_tickets[<?php echo $index; ?>][quantity]" value="<?php echo esc_attr($ticket['quantity']); ?>" />
                    </p>
                    <p>
                        <label><?php _e('Description', 'kh-events'); ?>:</label>
                        <textarea name="kh_tickets[<?php echo $index; ?>][description]"><?php echo esc_textarea($ticket['description']); ?></textarea>
                    </p>
                    <button type="button" class="button remove-ticket"><?php _e('Remove Ticket', 'kh-events'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-ticket" class="button"><?php _e('Add Ticket Type', 'kh-events'); ?></button>

        <script>
        jQuery(document).ready(function($) {
            var ticketIndex = <?php echo count($tickets); ?>;

            $('#add-ticket').click(function() {
                var html = '<div class="kh-ticket-item" data-index="' + ticketIndex + '">' +
                    '<h4><?php _e('Ticket Type', 'kh-events'); ?> ' + (ticketIndex + 1) + '</h4>' +
                    '<p><label><?php _e('Name', 'kh-events'); ?>:</label> <input type="text" name="kh_tickets[' + ticketIndex + '][name]" /></p>' +
                    '<p><label><?php _e('Price', 'kh-events'); ?>:</label> <input type="number" step="0.01" name="kh_tickets[' + ticketIndex + '][price]" /></p>' +
                    '<p><label><?php _e('Quantity Available', 'kh-events'); ?>:</label> <input type="number" name="kh_tickets[' + ticketIndex + '][quantity]" /></p>' +
                    '<p><label><?php _e('Description', 'kh-events'); ?>:</label> <textarea name="kh_tickets[' + ticketIndex + '][description]"></textarea></p>' +
                    '<button type="button" class="button remove-ticket"><?php _e('Remove Ticket', 'kh-events'); ?></button>' +
                    '</div>';
                $('#kh-tickets-container').append(html);
                ticketIndex++;
            });

            $(document).on('click', '.remove-ticket', function() {
                $(this).closest('.kh-ticket-item').remove();
            });
        });
        </script>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['kh_tickets_meta_nonce']) || !wp_verify_nonce($_POST['kh_tickets_meta_nonce'], 'kh_tickets_meta_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['kh_tickets'])) {
            $tickets = array();
            foreach ($_POST['kh_tickets'] as $ticket) {
                if (!empty($ticket['name'])) {
                    $tickets[] = array(
                        'name' => sanitize_text_field($ticket['name']),
                        'price' => floatval($ticket['price']),
                        'quantity' => intval($ticket['quantity']),
                        'description' => sanitize_textarea_field($ticket['description']),
                    );
                }
            }
            update_post_meta($post_id, '_kh_event_tickets', $tickets);
        }
    }
}
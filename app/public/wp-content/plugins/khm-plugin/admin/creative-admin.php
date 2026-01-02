<?php
/**
 * KHM Creative Admin Interface
 * 
 * Simple admin interface for managing creative materials
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Creative_Admin {
    
    private $creative_service;
    
    public function __construct() {
        // Load CreativeService
        require_once plugin_dir_path(__FILE__) . '../src/Services/CreativeService.php';
        $this->creative_service = new KHM_CreativeService();
        
        // Initialize admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_khm_save_creative', array($this, 'ajax_save_creative'));
        add_action('wp_ajax_khm_delete_creative', array($this, 'ajax_delete_creative'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-dashboard',
            'Creative Materials',
            'Creatives',
            'manage_options',
            'khm-creatives',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'khm-creatives') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_style('khm-creative-admin', plugin_dir_url(__FILE__) . '../assets/css/creative-admin.css');
    }
    
    /**
     * Render the main admin page
     */
    public function render_admin_page() {
        $action = $_GET['action'] ?? 'list';
        $creative_id = $_GET['creative_id'] ?? 0;
        
        echo '<div class="wrap">';
        echo '<h1>Creative Materials Management</h1>';
        
        switch ($action) {
            case 'edit':
                $this->render_edit_form($creative_id);
                break;
            case 'new':
                $this->render_edit_form(0);
                break;
            case 'analytics':
                $this->render_analytics($creative_id);
                break;
            default:
                $this->render_list_page();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render the creatives list
     */
    private function render_list_page() {
        $creatives = $this->creative_service->get_creatives();
        
        echo '<div class="khm-creative-header">';
        echo '<a href="' . admin_url('admin.php?page=khm-creatives&action=new') . '" class="button button-primary">Add New Creative</a>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Name</th>';
        echo '<th>Type</th>';
        echo '<th>Status</th>';
        echo '<th>Created</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if (empty($creatives)) {
            echo '<tr><td colspan="5">No creatives found. <a href="' . admin_url('admin.php?page=khm-creatives&action=new') . '">Create your first creative</a></td></tr>';
        } else {
            foreach ($creatives as $creative) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($creative->name) . '</strong></td>';
                echo '<td>' . ucfirst($creative->type) . '</td>';
                echo '<td>' . ucfirst($creative->status) . '</td>';
                echo '<td>' . date('M j, Y', strtotime($creative->created_at)) . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('admin.php?page=khm-creatives&action=edit&creative_id=' . $creative->id) . '">Edit</a> | ';
                echo '<a href="' . admin_url('admin.php?page=khm-creatives&action=analytics&creative_id=' . $creative->id) . '">Analytics</a> | ';
                echo '<a href="#" onclick="deleteCreative(' . $creative->id . ')" style="color: #d63638;">Delete</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        
        $this->render_admin_scripts();
    }
    
    /**
     * Render edit form
     */
    private function render_edit_form($creative_id) {
        $creative = $creative_id ? $this->creative_service->get_creative($creative_id) : null;
        $is_edit = $creative !== null;
        
        echo '<h2>' . ($is_edit ? 'Edit Creative' : 'Add New Creative') . '</h2>';
        
        echo '<form method="post" action="#" id="khm-creative-form">';
        echo '<table class="form-table">';
        
        // Name
        echo '<tr>';
        echo '<th scope="row"><label for="creative_name">Name</label></th>';
        echo '<td><input type="text" id="creative_name" name="name" value="' . esc_attr($creative->name ?? '') . '" class="regular-text" required></td>';
        echo '</tr>';
        
        // Type
        echo '<tr>';
        echo '<th scope="row"><label for="creative_type">Type</label></th>';
        echo '<td>';
        echo '<select id="creative_type" name="type" required>';
        $types = array('banner' => 'Banner', 'text' => 'Text', 'video' => 'Video', 'social' => 'Social', 'other' => 'Other');
        foreach ($types as $value => $label) {
            $selected = ($creative->type ?? '') === $value ? 'selected' : '';
            echo '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        // Content
        echo '<tr>';
        echo '<th scope="row"><label for="creative_content">Content</label></th>';
        echo '<td><textarea id="creative_content" name="content" rows="5" class="large-text">' . esc_textarea($creative->content ?? '') . '</textarea></td>';
        echo '</tr>';
        
        // Image URL
        echo '<tr>';
        echo '<th scope="row"><label for="creative_image_url">Image URL</label></th>';
        echo '<td><input type="url" id="creative_image_url" name="image_url" value="' . esc_attr($creative->image_url ?? '') . '" class="regular-text"></td>';
        echo '</tr>';
        
        // Alt Text
        echo '<tr>';
        echo '<th scope="row"><label for="creative_alt_text">Alt Text</label></th>';
        echo '<td><input type="text" id="creative_alt_text" name="alt_text" value="' . esc_attr($creative->alt_text ?? '') . '" class="regular-text"></td>';
        echo '</tr>';
        
        // Landing URL
        echo '<tr>';
        echo '<th scope="row"><label for="creative_landing_url">Landing URL</label></th>';
        echo '<td><input type="url" id="creative_landing_url" name="landing_url" value="' . esc_attr($creative->landing_url ?? '') . '" class="regular-text"></td>';
        echo '</tr>';
        
        // Dimensions
        echo '<tr>';
        echo '<th scope="row"><label for="creative_dimensions">Dimensions</label></th>';
        echo '<td><input type="text" id="creative_dimensions" name="dimensions" value="' . esc_attr($creative->dimensions ?? '') . '" class="regular-text" placeholder="e.g., 728x90"></td>';
        echo '</tr>';
        
        // Description
        echo '<tr>';
        echo '<th scope="row"><label for="creative_description">Description</label></th>';
        echo '<td><textarea id="creative_description" name="description" rows="3" class="large-text">' . esc_textarea($creative->description ?? '') . '</textarea></td>';
        echo '</tr>';
        
        // Status
        echo '<tr>';
        echo '<th scope="row"><label for="creative_status">Status</label></th>';
        echo '<td>';
        echo '<select id="creative_status" name="status">';
        $statuses = array('active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived');
        foreach ($statuses as $value => $label) {
            $selected = ($creative->status ?? 'active') === $value ? 'selected' : '';
            echo '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<input type="hidden" name="creative_id" value="' . $creative_id . '">';
        echo '<p class="submit">';
        echo '<input type="submit" class="button button-primary" value="' . ($is_edit ? 'Update Creative' : 'Create Creative') . '">';
        echo ' <a href="' . admin_url('admin.php?page=khm-creatives') . '" class="button">Cancel</a>';
        echo '</p>';
        
        echo '</form>';
        
        // Preview section
        if ($creative) {
            echo '<h3>Preview</h3>';
            echo '<div class="khm-creative-preview">';
            echo $this->creative_service->render_creative($creative->id, 0, array('platform' => 'preview'));
            echo '</div>';
        }
        
        $this->render_form_scripts();
    }
    
    /**
     * Render analytics page
     */
    private function render_analytics($creative_id) {
        $creative = $this->creative_service->get_creative($creative_id);
        
        if (!$creative) {
            echo '<div class="notice notice-error"><p>Creative not found.</p></div>';
            return;
        }
        
        echo '<h2>Analytics: ' . esc_html($creative->name) . '</h2>';
        echo '<p><a href="' . admin_url('admin.php?page=khm-creatives') . '">&larr; Back to Creatives</a></p>';
        
        // Get performance data
        $performance_30 = $this->creative_service->get_creative_performance($creative_id, 30);
        $performance_7 = $this->creative_service->get_creative_performance($creative_id, 7);
        
        echo '<div class="khm-analytics-grid">';
        
        // 30-day stats
        echo '<div class="khm-analytics-card">';
        echo '<h3>Last 30 Days</h3>';
        echo '<div class="khm-stat"><strong>' . number_format($performance_30['views']) . '</strong> Views</div>';
        echo '<div class="khm-stat"><strong>' . number_format($performance_30['clicks']) . '</strong> Clicks</div>';
        echo '<div class="khm-stat"><strong>' . $performance_30['ctr'] . '%</strong> CTR</div>';
        echo '<div class="khm-stat"><strong>' . number_format($performance_30['conversions']) . '</strong> Conversions</div>';
        echo '<div class="khm-stat"><strong>' . $performance_30['conversion_rate'] . '%</strong> Conversion Rate</div>';
        echo '</div>';
        
        // 7-day stats
        echo '<div class="khm-analytics-card">';
        echo '<h3>Last 7 Days</h3>';
        echo '<div class="khm-stat"><strong>' . number_format($performance_7['views']) . '</strong> Views</div>';
        echo '<div class="khm-stat"><strong>' . number_format($performance_7['clicks']) . '</strong> Clicks</div>';
        echo '<div class="khm-stat"><strong>' . $performance_7['ctr'] . '%</strong> CTR</div>';
        echo '<div class="khm-stat"><strong>' . number_format($performance_7['conversions']) . '</strong> Conversions</div>';
        echo '<div class="khm-stat"><strong>' . $performance_7['conversion_rate'] . '%</strong> Conversion Rate</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * AJAX: Save creative
     */
    public function ajax_save_creative() {
        check_ajax_referer('khm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $creative_id = intval($_POST['creative_id'] ?? 0);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type']),
            'content' => wp_kses_post($_POST['content']),
            'image_url' => esc_url_raw($_POST['image_url']),
            'alt_text' => sanitize_text_field($_POST['alt_text']),
            'landing_url' => esc_url_raw($_POST['landing_url']),
            'dimensions' => sanitize_text_field($_POST['dimensions']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status'])
        );
        
        if ($creative_id) {
            $result = $this->creative_service->update_creative($creative_id, $data);
        } else {
            $result = $this->creative_service->create_creative($data);
        }
        
        if ($result) {
            wp_send_json_success(array('message' => 'Creative saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save creative'));
        }
    }
    
    /**
     * AJAX: Delete creative
     */
    public function ajax_delete_creative() {
        check_ajax_referer('khm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $creative_id = intval($_POST['creative_id']);
        $result = $this->creative_service->delete_creative($creative_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Creative deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete creative'));
        }
    }
    
    /**
     * Render admin JavaScript
     */
    private function render_admin_scripts() {
        ?>
        <script>
        function deleteCreative(creativeId) {
            if (!confirm('Are you sure you want to delete this creative?')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'khm_delete_creative',
                creative_id: creativeId,
                nonce: '<?php echo wp_create_nonce('khm_admin_nonce'); ?>'
            }).done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
        </script>
        <style>
        .khm-creative-header { margin-bottom: 20px; }
        .khm-analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .khm-analytics-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
        .khm-analytics-card h3 { margin-top: 0; }
        .khm-stat { padding: 10px 0; border-bottom: 1px solid #f0f0f1; }
        .khm-stat:last-child { border-bottom: none; }
        .khm-creative-preview { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-top: 20px; }
        </style>
        <?php
    }
    
    /**
     * Render form JavaScript
     */
    private function render_form_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#khm-creative-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post(ajaxurl, {
                    action: 'khm_save_creative',
                    nonce: '<?php echo wp_create_nonce('khm_admin_nonce'); ?>',
                    creative_id: $('input[name="creative_id"]').val(),
                    name: $('#creative_name').val(),
                    type: $('#creative_type').val(),
                    content: $('#creative_content').val(),
                    image_url: $('#creative_image_url').val(),
                    alt_text: $('#creative_alt_text').val(),
                    landing_url: $('#creative_landing_url').val(),
                    dimensions: $('#creative_dimensions').val(),
                    description: $('#creative_description').val(),
                    status: $('#creative_status').val()
                }).done(function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo admin_url('admin.php?page=khm-creatives'); ?>';
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize admin interface
new KHM_Creative_Admin();
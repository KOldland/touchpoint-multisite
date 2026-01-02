<?php
/**
 * Subscription Form Template
 * 
 * Available variables:
 * $atts - shortcode attributes
 * - list_id
 * - style
 * - show_interests
 * - success_message
 * - error_message
 */

defined('ABSPATH') or exit;

$settings = TouchPoint_MailChimp_Settings::instance();
$api = TouchPoint_MailChimp_API::instance();

// Get list ID - use provided or default
$list_id = !empty($atts['list_id']) ? $atts['list_id'] : $settings->get_default_list();

if (empty($list_id)) {
    if (current_user_can('manage_options')) {
        echo '<p class="tmc-error">' . __('No MailChimp list configured. Please configure a default list in settings.', 'touchpoint-mailchimp') . '</p>';
    }
    return;
}

// Get form style
$style = isset($atts['style']) ? $atts['style'] : 'default';
$show_interests = isset($atts['show_interests']) && $atts['show_interests'];

// Get interest groups if requested
$interest_categories = array();
if ($show_interests) {
    $categories_result = $api->get_list_interest_categories($list_id);
    if ($categories_result['success']) {
        $interest_categories = $categories_result['categories'];
        
        // Get interests for each category
        foreach ($interest_categories as &$category) {
            $interests_result = $api->get_category_interests($list_id, $category['id']);
            if ($interests_result['success']) {
                $category['interests'] = $interests_result['interests'];
            }
        }
    }
}

// Form classes
$form_classes = array('tmc-subscription-form');
if ($style !== 'default') {
    $form_classes[] = 'tmc-style-' . esc_attr($style);
}

// Generate unique form ID
$form_id = 'tmc-form-' . uniqid();
?>

<form id="<?php echo esc_attr($form_id); ?>" class="<?php echo esc_attr(implode(' ', $form_classes)); ?>">
    <input type="hidden" name="list_id" value="<?php echo esc_attr($list_id); ?>">
    
    <?php if ($style !== 'minimal'): ?>
        <div class="tmc-form-header">
            <h3 class="tmc-form-title"><?php _e('Subscribe to our Newsletter', 'touchpoint-mailchimp'); ?></h3>
            <p class="tmc-form-description"><?php _e('Stay updated with our latest news and offers.', 'touchpoint-mailchimp'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="tmc-form-fields">
        <!-- Email Field -->
        <div class="tmc-form-group">
            <?php if ($style !== 'inline'): ?>
                <label for="<?php echo esc_attr($form_id); ?>_email"><?php _e('Email Address', 'touchpoint-mailchimp'); ?> <span class="required">*</span></label>
            <?php endif; ?>
            <input 
                type="email" 
                id="<?php echo esc_attr($form_id); ?>_email" 
                name="email" 
                placeholder="<?php esc_attr_e('Enter your email address', 'touchpoint-mailchimp'); ?>" 
                required
                aria-describedby="<?php echo esc_attr($form_id); ?>_email_desc"
            >
            <?php if ($style === 'inline'): ?>
                <span id="<?php echo esc_attr($form_id); ?>_email_desc" class="screen-reader-text"><?php _e('Enter your email address to subscribe', 'touchpoint-mailchimp'); ?></span>
            <?php endif; ?>
        </div>
        
        <!-- First Name Field (optional) -->
        <div class="tmc-form-group">
            <?php if ($style !== 'inline'): ?>
                <label for="<?php echo esc_attr($form_id); ?>_fname"><?php _e('First Name', 'touchpoint-mailchimp'); ?></label>
            <?php endif; ?>
            <input 
                type="text" 
                id="<?php echo esc_attr($form_id); ?>_fname" 
                name="merge_fields[FNAME]" 
                placeholder="<?php esc_attr_e('First Name (optional)', 'touchpoint-mailchimp'); ?>"
            >
        </div>
        
        <!-- Last Name Field (optional) -->
        <div class="tmc-form-group">
            <?php if ($style !== 'inline'): ?>
                <label for="<?php echo esc_attr($form_id); ?>_lname"><?php _e('Last Name', 'touchpoint-mailchimp'); ?></label>
            <?php endif; ?>
            <input 
                type="text" 
                id="<?php echo esc_attr($form_id); ?>_lname" 
                name="merge_fields[LNAME]" 
                placeholder="<?php esc_attr_e('Last Name (optional)', 'touchpoint-mailchimp'); ?>"
            >
        </div>
        
        <!-- Interest Groups -->
        <?php if ($show_interests && !empty($interest_categories)): ?>
            <div class="tmc-interests">
                <h4><?php _e('Interests', 'touchpoint-mailchimp'); ?></h4>
                <?php foreach ($interest_categories as $category): ?>
                    <?php if (!empty($category['interests'])): ?>
                        <div class="tmc-interest-group">
                            <h5><?php echo esc_html($category['title']); ?></h5>
                            <?php foreach ($category['interests'] as $interest): ?>
                                <div class="tmc-interest-item">
                                    <input 
                                        type="checkbox" 
                                        id="<?php echo esc_attr($form_id . '_interest_' . $interest['id']); ?>" 
                                        name="interests[]" 
                                        value="<?php echo esc_attr($interest['id']); ?>"
                                    >
                                    <label for="<?php echo esc_attr($form_id . '_interest_' . $interest['id']); ?>">
                                        <?php echo esc_html($interest['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Form Messages -->
    <div class="tmc-form-message" role="alert" aria-live="polite"></div>
    
    <!-- Submit Button -->
    <button type="submit" class="tmc-submit-button" data-original-text="<?php esc_attr_e('Subscribe', 'touchpoint-mailchimp'); ?>">
        <?php _e('Subscribe', 'touchpoint-mailchimp'); ?>
    </button>
    
    <!-- Privacy Notice -->
    <?php if ($style !== 'inline'): ?>
        <p class="tmc-privacy-notice">
            <?php _e('We respect your privacy. Unsubscribe at any time.', 'touchpoint-mailchimp'); ?>
        </p>
    <?php endif; ?>
</form>

<?php
// Add form-specific styles for inline forms
if ($style === 'inline'): ?>
<style>
#<?php echo esc_attr($form_id); ?> .tmc-form-group {
    margin-bottom: 0;
    margin-right: 10px;
    flex: 1;
}
#<?php echo esc_attr($form_id); ?> .tmc-form-group:last-of-type {
    margin-right: 0;
    flex: none;
}
#<?php echo esc_attr($form_id); ?> .tmc-privacy-notice {
    display: none;
}
</style>
<?php endif; ?>

<?php
// KHM Integration - track form views
if (function_exists('khm_call_service')) {
    do_action('tmc_form_displayed', $list_id, $form_id, get_current_user_id());
}
?>
<?php
/**
 * General Settings Template
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options = get_option( 'khm_seo_general', array() );
?>

<div class="wrap khm-seo-settings-page">
    <h1><?php _e( 'KHM SEO - General Settings', 'khm-seo' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'khm_seo_general' ); ?>
        
        <div class="khm-seo-settings-section">
            <h2><?php _e( 'Site Information', 'khm-seo' ); ?></h2>
            
            <div class="khm-seo-form-row">
                <div class="khm-seo-form-label">
                    <label for="site_name"><?php _e( 'Site Name', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <input type="text" id="site_name" name="khm_seo_general[site_name]" 
                           value="<?php echo esc_attr( isset( $options['site_name'] ) ? $options['site_name'] : '' ); ?>" />
                    <div class="khm-seo-form-description">
                        <?php _e( 'The name of your website, used in title templates', 'khm-seo' ); ?>
                    </div>
                </div>
            </div>

            <div class="khm-seo-form-row">
                <div class="khm-seo-form-label">
                    <label for="separator"><?php _e( 'Title Separator', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <select id="separator" name="khm_seo_general[separator]">
                        <option value="|" <?php selected( isset( $options['separator'] ) ? $options['separator'] : '|', '|' ); ?>>| (pipe)</option>
                        <option value="-" <?php selected( isset( $options['separator'] ) ? $options['separator'] : '|', '-' ); ?>>- (dash)</option>
                        <option value="–" <?php selected( isset( $options['separator'] ) ? $options['separator'] : '|', '–' ); ?>>– (ndash)</option>
                        <option value="—" <?php selected( isset( $options['separator'] ) ? $options['separator'] : '|', '—' ); ?>>— (mdash)</option>
                        <option value="·" <?php selected( isset( $options['separator'] ) ? $options['separator'] : '|', '·' ); ?>>· (bullet)</option>
                    </select>
                    <div class="khm-seo-form-description">
                        <?php _e( 'Character used to separate parts of page titles', 'khm-seo' ); ?>
                    </div>
                </div>
            </div>

            <div class="khm-seo-form-row">
                <div class="khm-seo-form-label">
                    <label for="home_title"><?php _e( 'Homepage Title', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <input type="text" id="home_title" name="khm_seo_general[home_title]" 
                           value="<?php echo esc_attr( isset( $options['home_title'] ) ? $options['home_title'] : '' ); ?>" />
                    <div class="khm-seo-form-description">
                        <?php _e( 'Custom title for your homepage. Leave blank to use site name.', 'khm-seo' ); ?>
                    </div>
                </div>
            </div>

            <div class="khm-seo-form-row">
                <div class="khm-seo-form-label">
                    <label for="home_description"><?php _e( 'Homepage Description', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <textarea id="home_description" name="khm_seo_general[home_description]" rows="3"><?php echo esc_textarea( isset( $options['home_description'] ) ? $options['home_description'] : '' ); ?></textarea>
                    <div class="khm-seo-form-description">
                        <?php _e( 'Meta description for your homepage', 'khm-seo' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php _e( 'Knowledge Graph', 'khm-seo' ); ?></h2>
            
            <div class="khm-seo-form-row">
                <div class="khm-seo-form-label">
                    <label for="company_or_person"><?php _e( 'Website Represents', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <select id="company_or_person" name="khm_seo_general[company_or_person]">
                        <option value="company" <?php selected( isset( $options['company_or_person'] ) ? $options['company_or_person'] : 'company', 'company' ); ?>><?php _e( 'Company/Organization', 'khm-seo' ); ?></option>
                        <option value="person" <?php selected( isset( $options['company_or_person'] ) ? $options['company_or_person'] : 'company', 'person' ); ?>><?php _e( 'Person', 'khm-seo' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="khm-seo-form-row" id="company_fields">
                <div class="khm-seo-form-label">
                    <label for="company_name"><?php _e( 'Company Name', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <input type="text" id="company_name" name="khm_seo_general[company_name]" 
                           value="<?php echo esc_attr( isset( $options['company_name'] ) ? $options['company_name'] : '' ); ?>" />
                </div>
            </div>

            <div class="khm-seo-form-row" id="company_logo_field">
                <div class="khm-seo-form-label">
                    <label for="company_logo"><?php _e( 'Company Logo', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <input type="url" id="company_logo" name="khm_seo_general[company_logo]" 
                           value="<?php echo esc_attr( isset( $options['company_logo'] ) ? $options['company_logo'] : '' ); ?>" />
                    <div class="khm-seo-form-description">
                        <?php _e( 'URL to your company logo (recommended size: 600x60px)', 'khm-seo' ); ?>
                    </div>
                </div>
            </div>

            <div class="khm-seo-form-row" id="person_fields" style="display:none;">
                <div class="khm-seo-form-label">
                    <label for="person_name"><?php _e( 'Person Name', 'khm-seo' ); ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <input type="text" id="person_name" name="khm_seo_general[person_name]" 
                           value="<?php echo esc_attr( isset( $options['person_name'] ) ? $options['person_name'] : '' ); ?>" />
                </div>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php _e( 'Social Profiles', 'khm-seo' ); ?></h2>
            <p><?php _e( 'Add your social media profiles to help search engines understand your brand better.', 'khm-seo' ); ?></p>
            
            <?php
            $social_profiles = isset( $options['social_profiles'] ) ? $options['social_profiles'] : array();
            $social_networks = array(
                'facebook' => 'Facebook',
                'twitter' => 'Twitter',
                'instagram' => 'Instagram',
                'linkedin' => 'LinkedIn',
                'youtube' => 'YouTube',
                'pinterest' => 'Pinterest'
            );
            
            foreach ( $social_networks as $network => $label ) :
            ?>
            <div class="khm-seo-form-row">
                <div class="khm-seo-form-label">
                    <label for="social_<?php echo $network; ?>"><?php echo $label; ?></label>
                </div>
                <div class="khm-seo-form-field">
                    <input type="url" id="social_<?php echo $network; ?>" 
                           name="khm_seo_general[social_profiles][<?php echo $network; ?>]" 
                           value="<?php echo esc_attr( isset( $social_profiles[$network] ) ? $social_profiles[$network] : '' ); ?>" />
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle company/person fields
    $('#company_or_person').change(function() {
        if ($(this).val() === 'person') {
            $('#company_fields, #company_logo_field').hide();
            $('#person_fields').show();
        } else {
            $('#company_fields, #company_logo_field').show();
            $('#person_fields').hide();
        }
    }).trigger('change');
});
</script>
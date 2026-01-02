<?php
/**
 * Schema Markup Settings Template
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options = \get_option( 'khm_seo_schema', array() );
?>

<div class="wrap khm-seo-settings-page">
    <h1><?php \_e( 'KHM SEO - Schema Markup', 'khm-seo' ); ?></h1>

    <form method="post" action="options.php">
        <?php \settings_fields( 'khm_seo_schema' ); ?>
        
        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Schema Markup Settings', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Configure structured data markup to help search engines understand your content better.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_schema]" value="1" 
                           <?php \checked( isset( $options['enable_schema'] ) ? $options['enable_schema'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Schema Markup', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add structured data markup to your website', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Organization Schema', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Define your organization information for Google Knowledge Panel.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label for="organization_name"><?php \_e( 'Organization Name', 'khm-seo' ); ?></label>
                <input type="text" id="organization_name" name="khm_seo_schema[organization_name]" 
                       value="<?php echo \esc_attr( isset( $options['organization_name'] ) ? $options['organization_name'] : \get_bloginfo( 'name' ) ); ?>" 
                       class="regular-text" />
                <p class="description"><?php \_e( 'The legal name of your organization', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="organization_type"><?php \_e( 'Organization Type', 'khm-seo' ); ?></label>
                <select id="organization_type" name="khm_seo_schema[organization_type]">
                    <option value="Organization" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'Organization' ); ?>><?php \_e( 'Organization', 'khm-seo' ); ?></option>
                    <option value="Corporation" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'Corporation' ); ?>><?php \_e( 'Corporation', 'khm-seo' ); ?></option>
                    <option value="EducationalOrganization" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'EducationalOrganization' ); ?>><?php \_e( 'Educational Organization', 'khm-seo' ); ?></option>
                    <option value="GovernmentOrganization" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'GovernmentOrganization' ); ?>><?php \_e( 'Government Organization', 'khm-seo' ); ?></option>
                    <option value="LocalBusiness" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'LocalBusiness' ); ?>><?php \_e( 'Local Business', 'khm-seo' ); ?></option>
                    <option value="MedicalOrganization" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'MedicalOrganization' ); ?>><?php \_e( 'Medical Organization', 'khm-seo' ); ?></option>
                    <option value="NGO" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'NGO' ); ?>><?php \_e( 'NGO', 'khm-seo' ); ?></option>
                    <option value="PerformingGroup" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'PerformingGroup' ); ?>><?php \_e( 'Performing Group', 'khm-seo' ); ?></option>
                    <option value="SportsOrganization" <?php \selected( isset( $options['organization_type'] ) ? $options['organization_type'] : '', 'SportsOrganization' ); ?>><?php \_e( 'Sports Organization', 'khm-seo' ); ?></option>
                </select>
                <p class="description"><?php \_e( 'The type of organization', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="organization_logo"><?php \_e( 'Organization Logo', 'khm-seo' ); ?></label>
                <input type="url" id="organization_logo" name="khm_seo_schema[organization_logo]" 
                       value="<?php echo \esc_url( isset( $options['organization_logo'] ) ? $options['organization_logo'] : '' ); ?>" 
                       class="regular-text" />
                <p class="description"><?php \_e( 'Full URL to your organization logo (minimum 112x112px, PNG/JPG)', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="organization_phone"><?php \_e( 'Phone Number', 'khm-seo' ); ?></label>
                <input type="tel" id="organization_phone" name="khm_seo_schema[organization_phone]" 
                       value="<?php echo \esc_attr( isset( $options['organization_phone'] ) ? $options['organization_phone'] : '' ); ?>" 
                       class="regular-text" />
                <p class="description"><?php \_e( 'Organization phone number (e.g., +1-555-123-4567)', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="organization_email"><?php \_e( 'Contact Email', 'khm-seo' ); ?></label>
                <input type="email" id="organization_email" name="khm_seo_schema[organization_email]" 
                       value="<?php echo \esc_attr( isset( $options['organization_email'] ) ? $options['organization_email'] : '' ); ?>" 
                       class="regular-text" />
                <p class="description"><?php \_e( 'General contact email address', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Website Schema', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Configure website-level structured data.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_website_schema]" value="1" 
                           <?php \checked( isset( $options['enable_website_schema'] ) ? $options['enable_website_schema'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Website Schema', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add website structured data markup', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_search_box]" value="1" 
                           <?php \checked( isset( $options['enable_search_box'] ) ? $options['enable_search_box'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Sitelinks Search Box', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add search box markup for Google search results', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Content Schema', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Configure automatic schema markup for different content types.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_article_schema]" value="1" 
                           <?php \checked( isset( $options['enable_article_schema'] ) ? $options['enable_article_schema'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Article Schema', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add Article structured data to blog posts and pages', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_breadcrumb_schema]" value="1" 
                           <?php \checked( isset( $options['enable_breadcrumb_schema'] ) ? $options['enable_breadcrumb_schema'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Breadcrumb Schema', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add breadcrumb structured data markup', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_image_schema]" value="1" 
                           <?php \checked( isset( $options['enable_image_schema'] ) ? $options['enable_image_schema'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Image Schema', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add ImageObject markup to content images', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[enable_video_schema]" value="1" 
                           <?php \checked( isset( $options['enable_video_schema'] ) ? $options['enable_video_schema'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Video Schema', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add VideoObject markup to embedded videos', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Social Profiles', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Add your organization social media profiles to schema markup.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label for="social_facebook"><?php \_e( 'Facebook Page URL', 'khm-seo' ); ?></label>
                <input type="url" id="social_facebook" name="khm_seo_schema[social_facebook]" 
                       value="<?php echo \esc_url( isset( $options['social_facebook'] ) ? $options['social_facebook'] : '' ); ?>" 
                       class="regular-text" />
            </div>

            <div class="khm-seo-settings-row">
                <label for="social_twitter"><?php \_e( 'Twitter Profile URL', 'khm-seo' ); ?></label>
                <input type="url" id="social_twitter" name="khm_seo_schema[social_twitter]" 
                       value="<?php echo \esc_url( isset( $options['social_twitter'] ) ? $options['social_twitter'] : '' ); ?>" 
                       class="regular-text" />
            </div>

            <div class="khm-seo-settings-row">
                <label for="social_instagram"><?php \_e( 'Instagram Profile URL', 'khm-seo' ); ?></label>
                <input type="url" id="social_instagram" name="khm_seo_schema[social_instagram]" 
                       value="<?php echo \esc_url( isset( $options['social_instagram'] ) ? $options['social_instagram'] : '' ); ?>" 
                       class="regular-text" />
            </div>

            <div class="khm-seo-settings-row">
                <label for="social_linkedin"><?php \_e( 'LinkedIn Company URL', 'khm-seo' ); ?></label>
                <input type="url" id="social_linkedin" name="khm_seo_schema[social_linkedin]" 
                       value="<?php echo \esc_url( isset( $options['social_linkedin'] ) ? $options['social_linkedin'] : '' ); ?>" 
                       class="regular-text" />
            </div>

            <div class="khm-seo-settings-row">
                <label for="social_youtube"><?php \_e( 'YouTube Channel URL', 'khm-seo' ); ?></label>
                <input type="url" id="social_youtube" name="khm_seo_schema[social_youtube]" 
                       value="<?php echo \esc_url( isset( $options['social_youtube'] ) ? $options['social_youtube'] : '' ); ?>" 
                       class="regular-text" />
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Advanced Schema Settings', 'khm-seo' ); ?></h2>
            
            <div class="khm-seo-settings-row">
                <label for="default_author"><?php \_e( 'Default Article Author', 'khm-seo' ); ?></label>
                <?php
                $users = \get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
                ?>
                <select id="default_author" name="khm_seo_schema[default_author]">
                    <option value=""><?php \_e( 'Use post author', 'khm-seo' ); ?></option>
                    <?php foreach ( $users as $user ) : ?>
                    <option value="<?php echo \esc_attr( $user->ID ); ?>" 
                            <?php \selected( isset( $options['default_author'] ) ? $options['default_author'] : '', $user->ID ); ?>>
                        <?php echo \esc_html( $user->display_name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php \_e( 'Default author for Article schema when none specified', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="publisher_name"><?php \_e( 'Publisher Name', 'khm-seo' ); ?></label>
                <input type="text" id="publisher_name" name="khm_seo_schema[publisher_name]" 
                       value="<?php echo \esc_attr( isset( $options['publisher_name'] ) ? $options['publisher_name'] : \get_bloginfo( 'name' ) ); ?>" 
                       class="regular-text" />
                <p class="description"><?php \_e( 'Publisher name for Article schema (defaults to site name)', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_schema[validate_schema]" value="1" 
                           <?php \checked( isset( $options['validate_schema'] ) ? $options['validate_schema'] : 0, 1 ); ?> />
                    <?php \_e( 'Enable Schema Validation', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Validate schema markup before output (may slow down page load)', 'khm-seo' ); ?></p>
            </div>
        </div>

        <?php \submit_button(); ?>
    </form>

    <div class="khm-seo-settings-section">
        <h2><?php \_e( 'Schema Testing', 'khm-seo' ); ?></h2>
        <p><?php \_e( 'Use these tools to test your structured data:', 'khm-seo' ); ?></p>
        <ul>
            <li><a href="https://search.google.com/test/rich-results" target="_blank"><?php \_e( 'Google Rich Results Test', 'khm-seo' ); ?></a></li>
            <li><a href="https://validator.schema.org/" target="_blank"><?php \_e( 'Schema.org Validator', 'khm-seo' ); ?></a></li>
            <li><a href="https://search.google.com/search-console" target="_blank"><?php \_e( 'Google Search Console', 'khm-seo' ); ?></a></li>
        </ul>
    </div>
</div>

<style>
.khm-seo-settings-row {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.khm-seo-settings-row:last-child {
    border-bottom: none;
}

.khm-seo-settings-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.khm-seo-settings-row input[type="text"],
.khm-seo-settings-row input[type="url"],
.khm-seo-settings-row input[type="email"],
.khm-seo-settings-row input[type="tel"],
.khm-seo-settings-row select {
    width: 100%;
    max-width: 500px;
}

.description {
    margin-top: 5px;
    font-style: italic;
    color: #666;
}

.khm-seo-settings-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #ddd;
}

.khm-seo-settings-section h2 {
    border-bottom: 1px solid #ccc;
    padding-bottom: 10px;
}
</style>
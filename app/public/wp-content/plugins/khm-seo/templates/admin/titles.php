<?php
/**
 * Titles & Meta Settings Template
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options = \get_option( 'khm_seo_titles', array() );
?>

<div class="wrap khm-seo-settings-page">
    <h1><?php \_e( 'KHM SEO - Titles & Meta', 'khm-seo' ); ?></h1>

    <form method="post" action="options.php">
        <?php \settings_fields( 'khm_seo_titles' ); ?>
        
        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Title Templates', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Configure how titles are generated for different content types. Available variables: %title%, %sitename%, %sep%, %author%, %category%, %tag%', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label for="home_title_format"><?php \_e( 'Homepage Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="home_title_format" name="khm_seo_titles[home_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['home_title_format'] ) ? $options['home_title_format'] : '%sitename% %sep% %tagline%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for homepage title', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="post_title_format"><?php \_e( 'Post Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="post_title_format" name="khm_seo_titles[post_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['post_title_format'] ) ? $options['post_title_format'] : '%title% %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for blog post titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="page_title_format"><?php \_e( 'Page Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="page_title_format" name="khm_seo_titles[page_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['page_title_format'] ) ? $options['page_title_format'] : '%title% %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for page titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="category_title_format"><?php \_e( 'Category Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="category_title_format" name="khm_seo_titles[category_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['category_title_format'] ) ? $options['category_title_format'] : '%term_title% %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for category archive titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="post_tag_title_format"><?php \_e( 'Tag Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="post_tag_title_format" name="khm_seo_titles[post_tag_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['post_tag_title_format'] ) ? $options['post_tag_title_format'] : '%term_title% %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for tag archive titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="author_title_format"><?php \_e( 'Author Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="author_title_format" name="khm_seo_titles[author_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['author_title_format'] ) ? $options['author_title_format'] : '%author% %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for author archive titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="search_title_format"><?php \_e( 'Search Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="search_title_format" name="khm_seo_titles[search_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['search_title_format'] ) ? $options['search_title_format'] : 'Search results for "%search_term%" %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for search results page titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="404_title_format"><?php \_e( '404 Title Format', 'khm-seo' ); ?></label>
                <input type="text" id="404_title_format" name="khm_seo_titles[404_title_format]" 
                       value="<?php echo \esc_attr( isset( $options['404_title_format'] ) ? $options['404_title_format'] : 'Page Not Found %sep% %sitename%' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Format for 404 error page titles', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Meta Settings', 'khm-seo' ); ?></h2>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_titles[enable_title_rewrite]" value="1" 
                           <?php \checked( isset( $options['enable_title_rewrite'] ) ? $options['enable_title_rewrite'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Title Rewriting', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Allow KHM SEO to control page titles', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_titles[enable_keywords]" value="1" 
                           <?php \checked( isset( $options['enable_keywords'] ) ? $options['enable_keywords'] : 0, 1 ); ?> />
                    <?php \_e( 'Enable Meta Keywords', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add meta keywords tag (not recommended for SEO)', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_titles[force_lowercase]" value="1" 
                           <?php \checked( isset( $options['force_lowercase'] ) ? $options['force_lowercase'] : 0, 1 ); ?> />
                    <?php \_e( 'Force Lowercase URLs', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Automatically redirect uppercase URLs to lowercase', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Open Graph & Social Media', 'khm-seo' ); ?></h2>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_titles[enable_og_tags]" value="1" 
                           <?php \checked( isset( $options['enable_og_tags'] ) ? $options['enable_og_tags'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Open Graph Meta Tags', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add Facebook Open Graph meta tags', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_titles[enable_twitter_cards]" value="1" 
                           <?php \checked( isset( $options['enable_twitter_cards'] ) ? $options['enable_twitter_cards'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable Twitter Cards', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add Twitter Card meta tags', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="twitter_site"><?php \_e( 'Twitter Username', 'khm-seo' ); ?></label>
                <input type="text" id="twitter_site" name="khm_seo_titles[twitter_site]" 
                       value="<?php echo \esc_attr( isset( $options['twitter_site'] ) ? $options['twitter_site'] : '' ); ?>" 
                       placeholder="@yourusername" />
                <p class="description"><?php \_e( 'Your Twitter username (with @)', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="default_og_image"><?php \_e( 'Default Open Graph Image', 'khm-seo' ); ?></label>
                <input type="url" id="default_og_image" name="khm_seo_titles[default_og_image]" 
                       value="<?php echo \esc_attr( isset( $options['default_og_image'] ) ? $options['default_og_image'] : '' ); ?>" 
                       class="widefat" />
                <p class="description"><?php \_e( 'Default image for social media sharing when no featured image is set', 'khm-seo' ); ?></p>
            </div>
        </div>

        <?php \submit_button(); ?>
    </form>
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
.khm-seo-settings-row input[type="url"] {
    width: 100%;
    max-width: 600px;
}

.description {
    margin-top: 5px;
    font-style: italic;
    color: #666;
}
</style>
<?php
/**
 * XML Sitemaps Settings Template
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options = \get_option( 'khm_seo_sitemap', array() );
?>

<div class="wrap khm-seo-settings-page">
    <h1><?php \_e( 'KHM SEO - XML Sitemaps', 'khm-seo' ); ?></h1>

    <form method="post" action="options.php">
        <?php \settings_fields( 'khm_seo_sitemap' ); ?>
        
        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'XML Sitemap Settings', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Configure automatic XML sitemap generation for better search engine crawling.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_sitemap[enable_sitemap]" value="1" 
                           <?php \checked( isset( $options['enable_sitemap'] ) ? $options['enable_sitemap'] : 1, 1 ); ?> />
                    <?php \_e( 'Enable XML Sitemap', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Generate XML sitemaps automatically', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="sitemap_posts_per_page"><?php \_e( 'Posts per Sitemap Page', 'khm-seo' ); ?></label>
                <input type="number" id="sitemap_posts_per_page" name="khm_seo_sitemap[posts_per_page]" 
                       value="<?php echo \esc_attr( isset( $options['posts_per_page'] ) ? $options['posts_per_page'] : 1000 ); ?>" 
                       min="1" max="50000" />
                <p class="description"><?php \_e( 'Number of URLs per sitemap file (recommended: 1000)', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Post Types', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Select which post types to include in your XML sitemap.', 'khm-seo' ); ?></p>
            
            <?php
            $post_types = \get_post_types( array( 'public' => true ), 'objects' );
            $included_post_types = isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );
            
            foreach ( $post_types as $post_type ) :
                if ( $post_type->name === 'attachment' ) continue;
            ?>
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_sitemap[post_types][]" value="<?php echo \esc_attr( $post_type->name ); ?>" 
                           <?php \checked( in_array( $post_type->name, $included_post_types ) ); ?> />
                    <?php echo \esc_html( $post_type->labels->name ); ?> (<?php echo \esc_html( $post_type->name ); ?>)
                </label>
                <p class="description"><?php echo \esc_html( $post_type->description ?: 'Include ' . $post_type->labels->name . ' in sitemap' ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Taxonomies', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Select which taxonomies to include in your XML sitemap.', 'khm-seo' ); ?></p>
            
            <?php
            $taxonomies = \get_taxonomies( array( 'public' => true ), 'objects' );
            $included_taxonomies = isset( $options['taxonomies'] ) ? $options['taxonomies'] : array( 'category', 'post_tag' );
            
            foreach ( $taxonomies as $taxonomy ) :
            ?>
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_sitemap[taxonomies][]" value="<?php echo \esc_attr( $taxonomy->name ); ?>" 
                           <?php \checked( in_array( $taxonomy->name, $included_taxonomies ) ); ?> />
                    <?php echo \esc_html( $taxonomy->labels->name ); ?> (<?php echo \esc_html( $taxonomy->name ); ?>)
                </label>
                <p class="description"><?php echo \esc_html( $taxonomy->description ?: 'Include ' . $taxonomy->labels->name . ' in sitemap' ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Additional Settings', 'khm-seo' ); ?></h2>
            
            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_sitemap[include_images]" value="1" 
                           <?php \checked( isset( $options['include_images'] ) ? $options['include_images'] : 1, 1 ); ?> />
                    <?php \_e( 'Include Images in Sitemap', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Add image URLs to the XML sitemap', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label>
                    <input type="checkbox" name="khm_seo_sitemap[ping_search_engines]" value="1" 
                           <?php \checked( isset( $options['ping_search_engines'] ) ? $options['ping_search_engines'] : 1, 1 ); ?> />
                    <?php \_e( 'Ping Search Engines', 'khm-seo' ); ?>
                </label>
                <p class="description"><?php \_e( 'Automatically notify Google and Bing when sitemap is updated', 'khm-seo' ); ?></p>
            </div>

            <div class="khm-seo-settings-row">
                <label for="sitemap_cache_time"><?php \_e( 'Cache Time (hours)', 'khm-seo' ); ?></label>
                <input type="number" id="sitemap_cache_time" name="khm_seo_sitemap[cache_time]" 
                       value="<?php echo \esc_attr( isset( $options['cache_time'] ) ? $options['cache_time'] : 24 ); ?>" 
                       min="1" max="168" />
                <p class="description"><?php \_e( 'How long to cache sitemap files (1-168 hours)', 'khm-seo' ); ?></p>
            </div>
        </div>

        <div class="khm-seo-settings-section">
            <h2><?php \_e( 'Sitemap Status', 'khm-seo' ); ?></h2>
            
            <?php if ( isset( $options['enable_sitemap'] ) && $options['enable_sitemap'] ) : ?>
            <div class="khm-seo-sitemap-status">
                <p><strong><?php \_e( 'Your XML Sitemap URLs:', 'khm-seo' ); ?></strong></p>
                <ul>
                    <li><a href="<?php echo \esc_url( \home_url( '/sitemap.xml' ) ); ?>" target="_blank"><?php echo \esc_url( \home_url( '/sitemap.xml' ) ); ?></a></li>
                    <li><a href="<?php echo \esc_url( \home_url( '/sitemap_index.xml' ) ); ?>" target="_blank"><?php echo \esc_url( \home_url( '/sitemap_index.xml' ) ); ?></a></li>
                </ul>
                <p class="description"><?php \_e( 'Submit these URLs to Google Search Console and Bing Webmaster Tools.', 'khm-seo' ); ?></p>
            </div>
            <?php else : ?>
            <p><?php \_e( 'XML Sitemaps are currently disabled. Enable them above to generate sitemap URLs.', 'khm-seo' ); ?></p>
            <?php endif; ?>
        </div>

        <?php \submit_button(); ?>
    </form>

    <div class="khm-seo-settings-section">
        <h2><?php \_e( 'Sitemap Actions', 'khm-seo' ); ?></h2>
        <p>
            <button type="button" class="button" onclick="khmSeoRegenerateSitemap()">
                <?php \_e( 'Regenerate Sitemap', 'khm-seo' ); ?>
            </button>
            <span class="description"><?php \_e( 'Force regeneration of all sitemap files', 'khm-seo' ); ?></span>
        </p>
    </div>
</div>

<script>
function khmSeoRegenerateSitemap() {
    if (confirm('<?php \_e( "Are you sure you want to regenerate the sitemap?", "khm-seo" ); ?>')) {
        // AJAX call to regenerate sitemap
        jQuery.post(ajaxurl, {
            action: 'khm_seo_regenerate_sitemap',
            nonce: '<?php echo \wp_create_nonce( "khm_seo_regenerate_sitemap" ); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php \_e( "Sitemap regenerated successfully!", "khm-seo" ); ?>');
            } else {
                alert('<?php \_e( "Failed to regenerate sitemap. Please try again.", "khm-seo" ); ?>');
            }
        });
    }
}
</script>

<style>
.khm-seo-sitemap-status {
    background: #f0f8ff;
    border: 1px solid #0073aa;
    border-radius: 4px;
    padding: 15px;
}

.khm-seo-sitemap-status ul {
    margin: 10px 0;
    padding-left: 20px;
}

.khm-seo-sitemap-status li {
    margin-bottom: 5px;
}

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

.description {
    margin-top: 5px;
    font-style: italic;
    color: #666;
}
</style>
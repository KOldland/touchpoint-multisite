<?php
/**
 * Site Search Shortcode
 *
 * Public-facing search widget that queries the unified search endpoint.
 * Provides an "agentic" search experience with a prominent "answer"
 * excerpt and linked content results below.
 *
 * Usage:
 *   [khm_site_search placeholder="Ask a question..." title="Search"]
 *
 * @package KH\Editorial\Search\Frontend
 */

namespace KH\Editorial\Search\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchShortcode
 */
class SearchShortcode {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_shortcode( 'khm_site_search', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Get asset URL and version for a dist file.
     *
     * Falls back to unminified source if dist doesn't exist.
     *
     * @param string $dist_path Relative path under assets/dist/ (e.g. 'site-search.min.js').
     * @param string $src_path  Relative path under assets/ (e.g. 'js/site-search.js').
     * @return array{url: string, version: string}
     */
    private function get_asset_info( string $dist_path, string $src_path ): array {
        $plugin_url = KH_EDITORIAL_PLUGIN_URL . 'assets/';
        $plugin_dir = KH_EDITORIAL_PLUGIN_DIR . 'assets/';

        // Prefer minified dist file.
        $dist_file = $plugin_dir . 'dist/' . $dist_path;
        if ( file_exists( $dist_file ) ) {
            return array(
                'url'     => $plugin_url . 'dist/' . $dist_path,
                'version' => (string) filemtime( $dist_file ),
            );
        }

        // Fall back to unminified source.
        $src_file = $plugin_dir . $src_path;
        return array(
            'url'     => $plugin_url . $src_path,
            'version' => file_exists( $src_file ) ? (string) filemtime( $src_file ) : KH_EDITORIAL_VERSION,
        );
    }

    /**
     * Enqueue frontend assets (only when shortcode is present).
     *
     * @return void
     */
    public function enqueue_assets(): void {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'khm_site_search' ) ) {
            return;
        }

        $css_info = $this->get_asset_info( 'site-search-styles.min.css', 'css/site-search.css' );
        $js_info  = $this->get_asset_info( 'site-search.min.js', 'js/site-search.js' );

        wp_enqueue_style(
            'khm-site-search',
            $css_info['url'],
            array(),
            $css_info['version']
        );

        wp_enqueue_script(
            'khm-site-search',
            $js_info['url'],
            array(),
            $js_info['version'],
            true
        );

        wp_localize_script(
            'khm-site-search',
            'khmSiteSearch',
            array(
                'endpoint' => rest_url( 'kh-editorial/v1/public/search' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'i18n'     => array(
                    'searching'    => __( 'Searching your content…', 'kh-editorial-intelligence' ),
                    'error'        => __( 'Something went wrong. Please try again.', 'kh-editorial-intelligence' ),
                    'no_results'   => __( 'No results found. Try a different search term.', 'kh-editorial-intelligence' ),
                    'result_count' => __( 'results', 'kh-editorial-intelligence' ),
                    'read_more'    => __( 'Read more', 'kh-editorial-intelligence' ),
                    'best_match'   => __( 'Best Match', 'kh-editorial-intelligence' ),
                    'related'      => __( 'Related Content', 'kh-editorial-intelligence' ),
                    'semantic'     => __( 'Semantic Match', 'kh-editorial-intelligence' ),
                    'fulltext'     => __( 'Keyword Match', 'kh-editorial-intelligence' ),
                ),
            )
        );
    }

    /**
     * Render the [khm_site_search] shortcode.
     *
     * @param array  $atts      Shortcode attributes.
     * @param string $content   Enclosed content (unused).
     * @return string HTML output.
     */
    public function render_shortcode( $atts, $content = '' ): string {
        $atts = shortcode_atts(
            array(
                'placeholder' => __( 'Ask a question or search the knowledge base…', 'kh-editorial-intelligence' ),
                'title'       => '',
                'theme'       => 'light', // light | dark
            ),
            $atts,
            'khm_site_search'
        );

        $title       = sanitize_text_field( $atts['title'] );
        $placeholder = sanitize_text_field( $atts['placeholder'] );
        $theme       = in_array( $atts['theme'], array( 'light', 'dark' ), true ) ? $atts['theme'] : 'light';
        $widget_id   = 'khm-site-search-' . wp_rand( 1000, 9999 );

        ob_start();
        ?>
        <div class="khm-site-search-widget khm-site-search-theme-<?php echo esc_attr( $theme ); ?>" id="<?php echo esc_attr( $widget_id ); ?>">
            <?php if ( $title ) : ?>
            <h3 class="khm-site-search-title"><?php echo esc_html( $title ); ?></h3>
            <?php endif; ?>

            <form class="khm-site-search-form" role="search">
                <div class="khm-site-search-input-row">
                    <label for="<?php echo esc_attr( $widget_id ); ?>-input" class="screen-reader-text">
                        <?php esc_html_e( 'Search query', 'kh-editorial-intelligence' ); ?>
                    </label>
                    <div class="khm-site-search-input-wrap">
                        <span class="khm-site-search-icon" aria-hidden="true">🔍</span>
                        <input
                            type="text"
                            id="<?php echo esc_attr( $widget_id ); ?>-input"
                            class="khm-site-search-input"
                            placeholder="<?php echo esc_attr( $placeholder ); ?>"
                            maxlength="500"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="khm-site-search-btn">
                        <?php esc_html_e( 'Search', 'kh-editorial-intelligence' ); ?>
                    </button>
                </div>
            </form>

            <div class="khm-site-search-spinner" hidden aria-label="<?php esc_attr_e( 'Loading', 'kh-editorial-intelligence' ); ?>">
                <div class="khm-site-search-spinner-dot"></div>
                <span class="khm-site-search-spinner-text"><?php esc_html_e( 'Searching your content…', 'kh-editorial-intelligence' ); ?></span>
            </div>

            <div class="khm-site-search-results" aria-live="polite" hidden>
                <div class="khm-site-search-answer-section" hidden>
                    <div class="khm-site-search-answer-label"><?php esc_html_e( 'Best Match', 'kh-editorial-intelligence' ); ?></div>
                    <div class="khm-site-search-answer-content"></div>
                    <a class="khm-site-search-answer-link" href="#" target="_blank">
                        <?php esc_html_e( 'Read the full article →', 'kh-editorial-intelligence' ); ?>
                    </a>
                </div>

                <div class="khm-site-search-list-section" hidden>
                    <div class="khm-site-search-list-label">
                        <?php esc_html_e( 'Related Content', 'kh-editorial-intelligence' ); ?>
                        <span class="khm-site-search-count"></span>
                    </div>
                    <div class="khm-site-search-list"></div>
                </div>
            </div>

            <div class="khm-site-search-error" hidden role="alert"></div>
            <div class="khm-site-search-empty" hidden>
                <p><?php esc_html_e( 'No results found. Try a different search term.', 'kh-editorial-intelligence' ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
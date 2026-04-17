<?php
/**
 * Atomic Search Widget
 *
 * Provides the [khm_atomic_search] shortcode which renders an intelligent
 * search widget backed by the RAG endpoint (POST /khm/v1/atomic/search).
 *
 * Usage:
 *   [khm_atomic_search placeholder="Ask me anything…" title="Search our knowledge base"]
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Search Widget
 */
class AtomicSearchWidget {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_shortcode( 'khm_atomic_search', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue frontend CSS + JS (only when the shortcode is present on the page).
     *
     * @return void
     */
    public function enqueue_assets(): void {
        // Only enqueue if the current post/page uses the shortcode.
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'khm_atomic_search' ) ) {
            return;
        }

        $plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/khm-membership.php' );

        wp_enqueue_style(
            'khm-atomic-search',
            $plugin_url . 'assets/css/atomic-search.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'khm-atomic-search',
            $plugin_url . 'assets/js/atomic-search.js',
            array(),
            '1.0.0',
            true
        );

        wp_localize_script(
            'khm-atomic-search',
            'khmAtomicSearch',
            array(
                'endpoint' => rest_url( 'khm/v1/atomic/search' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'i18n'     => array(
                    'searching'   => __( 'Searching…', 'khm-membership' ),
                    'error'       => __( 'Something went wrong. Please try again.', 'khm-membership' ),
                    'no_results'  => __( 'No results found.', 'khm-membership' ),
                    'sources'     => __( 'Sources', 'khm-membership' ),
                ),
            )
        );
    }

    /**
     * Render the [khm_atomic_search] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $atts ): string {
        $atts = shortcode_atts(
            array(
                'placeholder' => __( 'Ask a question…', 'khm-membership' ),
                'title'       => '',
            ),
            $atts,
            'khm_atomic_search'
        );

        $title       = sanitize_text_field( $atts['title'] );
        $placeholder = sanitize_text_field( $atts['placeholder'] );
        $widget_id   = 'khm-atomic-search-' . wp_rand( 1000, 9999 );

        ob_start();
        ?>
        <div class="khm-atomic-search-widget" id="<?php echo esc_attr( $widget_id ); ?>">
            <?php if ( $title ) : ?>
            <h3 class="khm-atomic-search-title"><?php echo esc_html( $title ); ?></h3>
            <?php endif; ?>

            <form class="khm-atomic-search-form" role="search" aria-label="<?php esc_attr_e( 'Knowledge base search', 'khm-membership' ); ?>">
                <div class="khm-atomic-search-input-row">
                    <label for="<?php echo esc_attr( $widget_id ); ?>-input" class="screen-reader-text">
                        <?php esc_html_e( 'Search query', 'khm-membership' ); ?>
                    </label>
                    <input
                        type="text"
                        id="<?php echo esc_attr( $widget_id ); ?>-input"
                        class="khm-atomic-search-input"
                        placeholder="<?php echo esc_attr( $placeholder ); ?>"
                        maxlength="500"
                        autocomplete="off"
                    >
                    <button type="submit" class="khm-atomic-search-btn">
                        <?php esc_html_e( 'Search', 'khm-membership' ); ?>
                    </button>
                </div>
            </form>

            <div class="khm-atomic-search-results" aria-live="polite" hidden>
                <div class="khm-atomic-search-answer"></div>
                <div class="khm-atomic-search-sources"></div>
            </div>

            <div class="khm-atomic-search-spinner" hidden aria-label="<?php esc_attr_e( 'Loading', 'khm-membership' ); ?>"></div>
            <div class="khm-atomic-search-error" hidden role="alert"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

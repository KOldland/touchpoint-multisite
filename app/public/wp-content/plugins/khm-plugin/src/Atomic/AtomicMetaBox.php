<?php
/**
 * Atomic Article Meta Box
 *
 * Adds an "Atomic Articles" panel to the standard WP post editor.
 * Provides:
 *  - Opt-in checkbox: "Generate atomic articles on publish/update"
 *  - "Regenerate Now" button (calls POST /khm/v1/posts/{id}/atomic/regenerate)
 *  - List of existing atomic articles with edit/view links
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Meta Box
 */
class AtomicMetaBox {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
        add_action( 'admin_footer', array( $this, 'print_js' ) );
    }

    /**
     * Register the meta box on all public post types except atomic_article itself.
     *
     * @return void
     */
    public function add_meta_box(): void {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        unset( $post_types[ AtomicArticlePostType::POST_TYPE ] );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'khm_atomic_articles',
                __( 'Atomic Articles', 'khm-membership' ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render the meta box HTML.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'khm_atomic_meta_nonce', '_khm_atomic_nonce' );

        $enabled     = AtomicArticlePostType::is_generation_enabled( $post->ID );
        $atomic_ids  = AtomicArticlePostType::get_ids_for_parent( $post->ID );
        $count       = count( $atomic_ids );
        ?>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="khm_atomic_generate_enabled"
                    value="1"
                    <?php checked( $enabled ); ?>
                >
                <?php esc_html_e( 'Generate atomic articles on publish/update', 'khm-membership' ); ?>
            </label>
        </p>

        <?php if ( $count > 0 ) : ?>
        <p class="description">
            <?php
            /* translators: %d: number of atomic articles */
            printf( esc_html( _n( '%d atomic article generated.', '%d atomic articles generated.', $count, 'khm-membership' ) ), $count );
            ?>
        </p>
        <ul style="margin:0.5em 0; padding-left:1.25em; font-size:0.85em;">
            <?php foreach ( $atomic_ids as $id ) : ?>
            <li>
                <a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">
                    <?php echo esc_html( get_the_title( $id ) ); ?>
                </a>
                &nbsp;
                <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">↗</a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ( 'publish' === $post->post_status ) : ?>
        <p>
            <button
                type="button"
                id="khm-atomic-regenerate"
                class="button"
                data-post-id="<?php echo absint( $post->ID ); ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
            >
                <?php $count > 0 ? esc_html_e( 'Regenerate Now', 'khm-membership' ) : esc_html_e( 'Generate Now', 'khm-membership' ); ?>
            </button>
            <span id="khm-atomic-status" style="display:none; margin-left:0.5em; font-size:0.85em;"></span>
        </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save the opt-in checkbox value when the post is saved.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function save_meta( int $post_id ): void {
        if ( ! isset( $_POST['_khm_atomic_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_key( $_POST['_khm_atomic_nonce'] ), 'khm_atomic_meta_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $enabled = isset( $_POST['khm_atomic_generate_enabled'] ) ? 1 : 0;
        update_post_meta( $post_id, '_atomic_generate_enabled', $enabled );
    }

    /**
     * Print the small inline JS that powers the "Regenerate Now" button.
     * Only loads on post edit screens.
     *
     * @return void
     */
    public function print_js(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }
        ?>
        <script>
        (function () {
            var btn = document.getElementById('khm-atomic-regenerate');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var postId = btn.dataset.postId;
                var nonce  = btn.dataset.nonce;
                var status = document.getElementById('khm-atomic-status');

                btn.disabled = true;
                status.style.display = 'inline';
                status.textContent   = '<?php echo esc_js( __( 'Generating…', 'khm-membership' ) ); ?>';

                fetch('/wp-json/khm/v1/posts/' + postId + '/atomic/regenerate', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce':   nonce,
                    },
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.count !== undefined) {
                        status.textContent = data.count + ' <?php echo esc_js( __( 'atomic articles created. Reload to see them.', 'khm-membership' ) ); ?>';
                    } else {
                        status.textContent = data.message || '<?php echo esc_js( __( 'Done.', 'khm-membership' ) ); ?>';
                    }
                    btn.disabled = false;
                })
                .catch(function () {
                    status.textContent = '<?php echo esc_js( __( 'Error — check console.', 'khm-membership' ) ); ?>';
                    btn.disabled = false;
                });
            });
        }());
        </script>
        <?php
    }
}

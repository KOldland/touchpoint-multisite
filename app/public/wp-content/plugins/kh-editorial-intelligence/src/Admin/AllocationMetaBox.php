<?php

namespace KH\Editorial\Admin;

use KH\Editorial\Services\AllocationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers and renders the "Site Allocation" meta box in the post editor sidebar.
 */
class AllocationMetaBox {

    private AllocationService $service;

    public function __construct() {
        $this->service = new AllocationService();
    }

    public function init(): void {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register the meta box on the post edit screen.
     */
    public function register_meta_box(): void {
        // Only show on the hub site (blog_id=1)
        if ( get_current_blog_id() !== 1 ) {
            return;
        }

        add_meta_box(
            'kh_allocation_meta_box',
            __( 'Content Allocation', 'kh-editorial-intelligence' ),
            [ $this, 'render_meta_box' ],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box HTML.
     *
     * @param \WP_Post $post
     */
    public function render_meta_box( \WP_Post $post ): void {
        // Don't show on new post creation
        if ( $post->post_status === 'auto-draft' ) {
            echo '<p style="color: #646970;">' . esc_html__( 'Save the post first to enable allocation.', 'kh-editorial-intelligence' ) . '</p>';
            return;
        }

        $sites = $this->service->get_available_sites();
        $allocations = \KH\Editorial\Database\AllocationTable::get_for_post( $post->ID );

        ?>
        <div id="kh-allocation-meta-box" class="kh-allocation-meta-box">
            <p class="kh-allocation-description" style="color: #646970; font-size: 12px; margin-top: 0;">
                <?php esc_html_e( 'Select target sites and clone this post. Choose "Clone & Rewrite" to have AI adapt the content for each site\'s audience.', 'kh-editorial-intelligence' ); ?>
            </p>

            <div class="kh-allocation-sites-list" style="margin-bottom: 12px;">
                <?php foreach ( $sites as $site ) : 
                    $allocated = isset( $allocations[ $site['blog_id'] ] );
                    $alloc = $allocated ? $allocations[ $site['blog_id'] ] : null;
                    $rewrite_badge = $alloc && (bool) $alloc['rewrite_applied'] 
                        ? '<span style="color: #2271b1; font-size: 11px;"> (' . esc_html__( 'rewritten', 'kh-editorial-intelligence' ) . ')</span>' 
                        : '';
                    $status_text = $allocated
                        ? '<span style="color: #00a32a;">✅ ' . esc_html__( 'Cloned', 'kh-editorial-intelligence' ) . $rewrite_badge . '</span>'
                        : '';
                ?>
                    <label class="kh-allocation-site-row" style="display: flex; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f1; cursor: pointer;"
                           data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>"
                           data-slug="<?php echo esc_attr( $site['slug'] ); ?>">
                        <input type="checkbox"
                               class="kh-allocation-checkbox"
                               value="<?php echo esc_attr( $site['slug'] ); ?>"
                               data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>"
                               <?php echo $allocated ? 'disabled' : ''; ?>
                               style="margin-right: 8px;">
                        <span style="flex: 1; font-size: 13px;"><?php echo esc_html( $site['label'] ); ?></span>
                        <span class="kh-allocation-status" style="font-size: 12px; white-space: nowrap;">
                            <?php echo $status_text; ?>
                        </span>
                        <?php if ( $allocated && $alloc ) : ?>
                            <a href="<?php echo esc_url( $this->service->get_cross_site_edit_link( $site['blog_id'], (int) $alloc['target_post_id'] ) ); ?>"
                               class="kh-allocation-edit-link"
                               target="_blank"
                               style="margin-left: 6px; font-size: 12px;"
                               title="<?php esc_attr_e( 'Edit variant', 'kh-editorial-intelligence' ); ?>">
                                📝
                            </a>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="kh-allocation-actions" style="display: flex; gap: 8px;">
                <button type="button"
                        id="kh-allocate-clone-rewrite"
                        class="button button-primary"
                        style="flex: 1;"
                        disabled>
                    <?php esc_html_e( 'Clone & Rewrite', 'kh-editorial-intelligence' ); ?>
                </button>
                <button type="button"
                        id="kh-allocate-clone-only"
                        class="button"
                        style="flex: 1;"
                        disabled>
                    <?php esc_html_e( 'Clone Only', 'kh-editorial-intelligence' ); ?>
                </button>
            </div>

            <div id="kh-allocation-status-message"
                 style="margin-top: 10px; padding: 8px; border-radius: 4px; display: none; font-size: 12px;">
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue JS and CSS for the meta box.
     *
     * @param string $hook_suffix
     */
    public function enqueue_assets( string $hook_suffix ): void {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/kh-editorial-intelligence.php' );

        wp_enqueue_script(
            'kh-allocation-meta-box',
            $plugin_url . 'assets/js/allocation-meta-box.js',
            [ 'jquery', 'wp-api-fetch' ],
            '1.0.0',
            true
        );

        wp_localize_script( 'kh-allocation-meta-box', 'khAllocationData', [
            'postId'       => get_the_ID(),
            'restUrl'      => rest_url( 'kh-editorial/v1/allocation/' ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'labels'       => [
                'cloning'           => __( 'Cloning...', 'kh-editorial-intelligence' ),
                'rewriting'         => __( 'AI rewriting...', 'kh-editorial-intelligence' ),
                'cloneSuccess'      => __( 'Post cloned successfully.', 'kh-editorial-intelligence' ),
                'rewriteSuccess'    => __( 'Post cloned and rewritten.', 'kh-editorial-intelligence' ),
                'error'             => __( 'An error occurred.', 'kh-editorial-intelligence' ),
                'selectSites'       => __( 'Please select at least one site.', 'kh-editorial-intelligence' ),
                'alreadyAllocated'  => __( 'Already allocated.', 'kh-editorial-intelligence' ),
            ],
        ] );

        // Inline styles
        wp_add_inline_style( 'wp-admin', '
            .kh-allocation-site-row:hover { background: #f6f7f7; }
            .kh-allocation-meta-box button:disabled { opacity: 0.6; cursor: not-allowed; }
        ' );
    }
}
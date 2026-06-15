<?php

namespace KH\Editorial\Admin;

use KH\Editorial\Database\AllocationTable;
use KH\Editorial\Services\AllocationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Distribution Overview page — shows all posts that have been distributed
 * (cloned) to network sites from the hub, with a modal popup for new distributions.
 */
class DistributionPage {

    private AllocationService $service;

    public function __construct() {
        $this->service = new AllocationService();
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu(): void {
        add_submenu_page(
            'kh-editorial-studio',
            __( 'Content Distribution', 'kh-editorial-intelligence' ),
            __( 'Distribution', 'kh-editorial-intelligence' ),
            'edit_posts',
            'kh-distribution',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'kh-distribution' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'kh-distribution-page',
            plugins_url( 'assets/js/allocation-meta-box.js', KH_EDITORIAL_PLUGIN_DIR . 'kh-editorial-intelligence.php' ),
            [ 'jquery', 'wp-api-fetch' ],
            '1.0.0',
            true
        );

        // Add Thickbox for modal
        add_thickbox();
    }

    public function render_page(): void {
        $allocations = AllocationTable::get_all_distributed();
        $sites       = $this->service->get_available_sites();

        // Build site label lookup
        $site_labels = [];
        foreach ( $sites as $s ) {
            $site_labels[ (int) $s['blog_id'] ] = $s['label'];
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Content Distribution', 'kh-editorial-intelligence' ); ?></h1>
            <p style="color: #646970;">
                <?php esc_html_e( 'Posts that have been distributed across network sites. Use the "Distribute" button on any post to clone it to additional sites.', 'kh-editorial-intelligence' ); ?>
            </p>

            <?php if ( empty( $allocations ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No posts have been distributed yet. Open a post in the editor and use the Content Allocation panel to distribute it.', 'kh-editorial-intelligence' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Post', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Author', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Distributed To', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Date Distributed', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'kh-editorial-intelligence' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $allocations as $post_id => $sites_for_post ) :
                            $post = get_post( $post_id );
                            if ( ! $post ) continue;

                            // Author
                            $author_name = '';
                            if ( function_exists( 'kh_get_post_authors' ) ) {
                                $authors = \kh_get_post_authors( $post_id );
                                if ( ! empty( $authors ) && is_object( $authors[0] ) ) {
                                    $author_name = function_exists( 'get_field' )
                                        ? get_field( 'author_name', $authors[0]->ID )
                                        : '';
                                    $author_name = $author_name ?: get_the_title( $authors[0]->ID );
                                }
                            }
                            if ( ! $author_name ) {
                                $user = get_user_by( 'ID', $post->post_author );
                                $author_name = $user ? $user->display_name : '—';
                            }

                            // Date
                            $first_alloc = reset( $sites_for_post );
                            $date = $first_alloc['allocated_at'] ?? $post->post_modified;
                            $date_str = is_numeric( $date )
                                ? wp_date( 'Y-m-d H:i', (int) $date )
                                : wp_date( 'Y-m-d H:i', strtotime( $date ) );
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
                                        <?php echo esc_html( get_the_title( $post ) ?: '(no title)' ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html( $author_name ); ?></td>
                            <td>
                                <?php foreach ( $sites_for_post as $alloc ) :
                                    $blog_id   = (int) $alloc['blog_id'];
                                    $label     = $site_labels[ $blog_id ] ?? ( 'Site ' . $blog_id );
                                    $rewritten = (bool) ( $alloc['rewrite_applied'] ?? false );
                                    $color     = $rewritten ? '#dba617' : '#2271b1';
                                    $suffix    = $rewritten ? ' (RW)' : '';
                                    printf(
                                        '<span style="display:inline-block;background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;margin-right:4px;margin-bottom:2px;">%s%s</span>',
                                        esc_attr( $color ),
                                        esc_html( $label ),
                                        esc_html( $suffix )
                                    );
                                endforeach; ?>
                            </td>
                            <td><?php echo esc_html( $date_str ); ?></td>
                            <td>
                                <a href="#TB_inline?width=400&height=400&inlineId=kh-distribute-modal-<?php echo (int) $post_id; ?>"
                                   class="button button-small thickbox">
                                    <?php esc_html_e( 'Distribute More', 'kh-editorial-intelligence' ); ?>
                                </a>
                                <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'kh-editorial-intelligence' ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Inline thickbox modals for each distributed post -->
            <?php foreach ( $allocations as $post_id => $sites_for_post ) :
                $post = get_post( $post_id );
                if ( ! $post ) continue;

                // Determine which sites are already allocated
                $allocated_ids = [];
                foreach ( $sites_for_post as $alloc ) {
                    $allocated_ids[] = (int) $alloc['blog_id'];
                }
            ?>
            <div id="kh-distribute-modal-<?php echo (int) $post_id; ?>" style="display:none;">
                <div class="kh-distribute-modal-content" style="padding: 16px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e( 'Distribute to Sites', 'kh-editorial-intelligence' ); ?></h2>
                    <p style="color: #646970; font-size: 13px;">
                        <?php printf(
                            esc_html__( 'Post: %s', 'kh-editorial-intelligence' ),
                            '<strong>' . esc_html( get_the_title( $post ) ?: '(no title)' ) . '</strong>'
                        ); ?>
                    </p>

                    <div class="kh-allocation-sites-list" style="margin-bottom: 12px;">
                        <?php foreach ( $sites as $site ) :
                            $blog_id  = (int) $site['blog_id'];
                            $disabled = in_array( $blog_id, $allocated_ids, true );
                            $status   = $disabled
                                ? '<span style="color: #00a32a; font-size: 12px;">' . esc_html__( 'Already distributed', 'kh-editorial-intelligence' ) . '</span>'
                                : '';
                        ?>
                            <label class="kh-allocation-site-row"
                                   style="display: flex; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f1; cursor: <?php echo $disabled ? 'default' : 'pointer'; ?>;"
                                   data-slug="<?php echo esc_attr( $site['slug'] ); ?>"
                                   data-blog-id="<?php echo esc_attr( $blog_id ); ?>">
                                <input type="checkbox"
                                       class="kh-allocation-checkbox"
                                       value="<?php echo esc_attr( $site['slug'] ); ?>"
                                       data-blog-id="<?php echo esc_attr( $blog_id ); ?>"
                                       <?php echo $disabled ? 'disabled' : ''; ?>
                                       style="margin-right: 8px;">
                                <span style="flex: 1; font-size: 13px;"><?php echo esc_html( $site['label'] ); ?></span>
                                <span class="kh-allocation-status" style="font-size: 12px;"><?php echo $status; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="kh-allocation-actions" style="display: flex; gap: 8px;">
                        <button type="button"
                                class="kh-allocate-clone-rewrite button button-primary"
                                style="flex: 1;"
                                data-post-id="<?php echo (int) $post_id; ?>"
                                disabled>
                            <?php esc_html_e( 'Clone & Rewrite', 'kh-editorial-intelligence' ); ?>
                        </button>
                        <button type="button"
                                class="kh-allocate-clone-only button"
                                style="flex: 1;"
                                data-post-id="<?php echo (int) $post_id; ?>"
                                disabled>
                            <?php esc_html_e( 'Clone Only', 'kh-editorial-intelligence' ); ?>
                        </button>
                    </div>

                    <div class="kh-allocation-status-message"
                         style="margin-top: 10px; padding: 8px; border-radius: 4px; display: none; font-size: 12px;">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <style>
            .kh-distribute-modal-content .kh-allocation-site-row:hover {
                background: #f6f7f7;
            }
            .kh-distribute-modal-content button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        </style>

        <script>
        (function ($) {
            'use strict';

            var labels = {
                cloned: '<?php echo esc_js( __( 'Distributed', 'kh-editorial-intelligence' ) ); ?>',
                cloning: '<?php echo esc_js( __( 'Distributing...', 'kh-editorial-intelligence' ) ); ?>',
                rewriting: '<?php echo esc_js( __( 'AI rewriting...', 'kh-editorial-intelligence' ) ); ?>',
                error: '<?php echo esc_js( __( 'An error occurred.', 'kh-editorial-intelligence' ) ); ?>',
                selectSites: '<?php echo esc_js( __( 'Please select at least one site.', 'kh-editorial-intelligence' ) ); ?>',
                success: '<?php echo esc_js( __( 'Post distributed successfully.', 'kh-editorial-intelligence' ) ); ?>',
            };

            // Enable/disable buttons based on checkbox selection within each modal
            $(document).on('change', '.kh-allocation-checkbox', function () {
                var $modal = $(this).closest('.kh-distribute-modal-content');
                var checked = $modal.find('.kh-allocation-checkbox:checked:not(:disabled)').length;
                $modal.find('.kh-allocate-clone-rewrite, .kh-allocate-clone-only').prop('disabled', checked === 0);
            });

            // Clone & Rewrite
            $(document).on('click', '.kh-allocate-clone-rewrite', function () {
                doDistribute($(this), true);
            });

            // Clone Only
            $(document).on('click', '.kh-allocate-clone-only', function () {
                doDistribute($(this), false);
            });

            function doDistribute($btn, rewrite) {
                var $modal = $btn.closest('.kh-distribute-modal-content');
                var postId = $btn.data('post-id');
                var $checkboxes = $modal.find('.kh-allocation-checkbox:checked:not(:disabled)');
                var selectedSlugs = $checkboxes.map(function () { return $(this).val(); }).get();

                if (selectedSlugs.length === 0) {
                    showStatus($modal, labels.selectSites, 'error');
                    return;
                }

                setLoading($modal, $btn, true);

                wp.apiFetch({
                    path: 'kh-editorial/v1/allocation/clone',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
                        'Content-Type': 'application/json',
                    },
                    data: {
                        post_id: postId,
                        site_slugs: selectedSlugs,
                        rewrite: rewrite,
                    },
                }).then(function (result) {
                    if (result.success) {
                        showStatus($modal, labels.success, 'success');
                        // Reload after short delay
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showStatus($modal, result.message || labels.error, 'error');
                    }
                }).catch(function (err) {
                    showStatus($modal, (err && err.message) || labels.error, 'error');
                }).finally(function () {
                    setLoading($modal, $btn, false);
                });
            }

            function showStatus($modal, message, type) {
                var $msg = $modal.find('.kh-allocation-status-message');
                $msg.text(message)
                    .removeClass('notice-success notice-error')
                    .addClass(type === 'success' ? 'notice-success' : 'notice-error')
                    .css({
                        display: 'block',
                        background: type === 'success' ? '#ecf7ed' : '#fbeaea',
                        borderLeft: type === 'success' ? '4px solid #00a32a' : '4px solid #d63638',
                        color: type === 'success' ? '#1e561e' : '#8b3a3a',
                    });
            }

            function setLoading($modal, $btn, loading) {
                var isRewrite = $btn.hasClass('kh-allocate-clone-rewrite');
                var $otherBtn = isRewrite ? $modal.find('.kh-allocate-clone-only') : $modal.find('.kh-allocate-clone-rewrite');
                var label = isRewrite
                    ? (loading ? labels.rewriting : 'Clone & Rewrite')
                    : (loading ? labels.cloning : 'Clone Only');

                $btn.prop('disabled', loading).text(label);
                $otherBtn.prop('disabled', loading);
                $modal.find('.kh-allocation-checkbox').prop('disabled', loading);
            }
        })(jQuery);
        </script>
        <?php
    }
}
<?php

namespace KH\Editorial\Admin;

use KH\Editorial\Database\AllocationTable;
use KH\Editorial\Services\AllocationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Distribution Overview page — central control panel for cross-site
 * content distribution. Shows ALL hub posts with their allocation status
 * across network sites, and allows distributing any post to any site.
 */
class DistributionPage {

    const PER_PAGE = 20;

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
        add_thickbox();
    }

    public function render_page(): void {
        $sites       = $this->service->get_available_sites();
        $allocations = AllocationTable::get_all_distributed();

        // Build site lookup tables
        $site_labels = [];
        $site_slugs  = [];
        foreach ( $sites as $s ) {
            $site_labels[ (int) $s['blog_id'] ] = $s['label'];
            $site_slugs[ (int) $s['blog_id'] ]  = $s['slug'];
        }

        // Pagination
        $paged   = max( 1, (int) ( $_GET['kh_paged'] ?? 1 ) );
        $offset  = ( $paged - 1 ) * self::PER_PAGE;

        // Query all hub posts (published, draft, pending, future)
        $query = new \WP_Query( [
            'post_type'      => 'post',
            'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
            'posts_per_page' => self::PER_PAGE,
            'offset'         => $offset,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ] );

        $total_pages = $query->max_num_pages;
        $posts       = $query->posts;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Content Distribution', 'kh-editorial-intelligence' ); ?></h1>
            <p style="color: #646970;">
                <?php esc_html_e( 'All hub posts and their distribution status across network sites. Check sites and click "Distribute Selected" to clone posts.', 'kh-editorial-intelligence' ); ?>
            </p>

            <?php if ( empty( $posts ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No posts found on the hub site.', 'kh-editorial-intelligence' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 35%;"><?php esc_html_e( 'Post', 'kh-editorial-intelligence' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'Status', 'kh-editorial-intelligence' ); ?></th>
                            <th style="width: 8%;"><?php esc_html_e( 'Date', 'kh-editorial-intelligence' ); ?></th>
                            <th style="width: 30%;"><?php esc_html_e( 'Distribution', 'kh-editorial-intelligence' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'Actions', 'kh-editorial-intelligence' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $posts as $post ) :
                            $post_id       = $post->ID;
                            $post_allocs   = $allocations[ $post_id ] ?? [];

                            // Build allocated blog_id -> row lookup
                            $alloc_by_blog = [];
                            foreach ( $post_allocs as $alloc ) {
                                $alloc_by_blog[ (int) $alloc['target_blog_id'] ] = $alloc;
                            }
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
                                        <?php echo esc_html( get_the_title( $post ) ?: '(no title)' ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php
                                $status_labels = [
                                    'publish' => [ 'Publish', '#00a32a', '#fff' ],
                                    'draft'   => [ 'Draft', '#dba617', '#fff' ],
                                    'pending' => [ 'Pending', '#d63638', '#fff' ],
                                    'future'  => [ 'Scheduled', '#2271b1', '#fff' ],
                                ];
                                $sl = $status_labels[ $post->post_status ] ?? [ ucfirst( $post->post_status ), '#999', '#fff' ];
                                printf(
                                    '<span style="display:inline-block;padding:1px 8px;border-radius:3px;font-size:11px;background:%s;color:%s;">%s</span>',
                                    esc_attr( $sl[1] ),
                                    esc_attr( $sl[2] ),
                                    esc_html( $sl[0] )
                                );
                                ?>
                            </td>
                            <td style="font-size: 12px;">
                                <?php echo esc_html( wp_date( 'Y-m-d', strtotime( $post->post_modified ) ) ); ?>
                            </td>
                            <td>
                                <?php foreach ( $sites as $site ) :
                                    $blog_id   = (int) $site['blog_id'];
                                    $alloc     = $alloc_by_blog[ $blog_id ] ?? null;
                                    $cell_key  = 'kh-dist-cell-' . $post_id . '-' . $blog_id;

                                    if ( $alloc ) :
                                        $rewritten = (bool) ( $alloc['rewrite_applied'] ?? false );
                                        $color     = $rewritten ? '#dba617' : '#00a32a';
                                        $icon      = $rewritten ? 'R' : 'C';
                                        $title     = $rewritten
                                            ? __( 'Distributed (rewritten)', 'kh-editorial-intelligence' )
                                            : __( 'Distributed', 'kh-editorial-intelligence' );
                                        ?>
                                        <span id="<?php echo esc_attr( $cell_key ); ?>"
                                              title="<?php echo esc_attr( $title ); ?>"
                                              style="display: inline-block; margin-right: 8px; font-size: 12px;">
                                            <span style="color: <?php echo esc_attr( $color ); ?>; font-weight: bold;"><?php echo esc_html( $icon ); ?></span>
                                            <span style="color: #50575e;"><?php echo esc_html( $site['label'] ); ?></span>
                                        </span>
                                    <?php else : ?>
                                        <span id="<?php echo esc_attr( $cell_key ); ?>"
                                              style="display: inline-block; margin-right: 8px; font-size: 12px; color: #ccc;">
                                            — <?php echo esc_html( $site['label'] ); ?>
                                        </span>
                                    <?php endif;
                                endforeach; ?>
                            </td>
                            <td>
                                <a href="#TB_inline?width=450&height=450&inlineId=kh-distribute-modal-<?php echo (int) $post_id; ?>"
                                   class="button button-small thickbox">
                                    <?php esc_html_e( 'Distribute', 'kh-editorial-intelligence' ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav" style="margin-top: 8px;">
                    <div class="tablenav-pages">
                        <?php
                        $base_args = [ 'page' => 'kh-distribution' ];
                        echo paginate_links( [
                            'base'      => add_query_arg( 'kh_paged', '%#%' ),
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $total_pages,
                            'prev_text' => '←',
                            'next_text' => '→',
                        ] );
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Inline thickbox modals for each post -->
            <?php foreach ( $posts as $post ) :
                $post_id     = $post->ID;
                $post_allocs = $allocations[ $post_id ] ?? [];
                $allocated_ids = [];
                foreach ( $post_allocs as $alloc ) {
                    $allocated_ids[] = (int) $alloc['target_blog_id'];
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
                                       <?php echo $disabled ? ' disabled' : ''; ?>
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
            .wp-list-table th, .wp-list-table td {
                vertical-align: middle;
            }
        </style>

        <script>
        (function ($) {
            'use strict';

            var labels = {
                cloning: '<?php echo esc_js( __( 'Distributing...', 'kh-editorial-intelligence' ) ); ?>',
                rewriting: '<?php echo esc_js( __( 'AI rewriting...', 'kh-editorial-intelligence' ) ); ?>',
                error: '<?php echo esc_js( __( 'An error occurred.', 'kh-editorial-intelligence' ) ); ?>',
                selectSites: '<?php echo esc_js( __( 'Please select at least one site.', 'kh-editorial-intelligence' ) ); ?>',
                success: '<?php echo esc_js( __( 'Post distributed successfully.', 'kh-editorial-intelligence' ) ); ?>',
            };

            var siteLookup = <?php echo json_encode( $site_slugs ); ?>;

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

                        // Update the inline grid icons
                        if (result.results) {
                            result.results.forEach(function (r) {
                                var blogId = r.blog_id;
                                var cellKey = 'kh-dist-cell-' + postId + '-' + blogId;
                                var $cell = $('#' + cellKey);
                                if ($cell.length) {
                                    var icon = r.rewrite_applied ? 'R' : 'C';
                                    var color = r.rewrite_applied ? '#dba617' : '#00a32a';
                                    var title = r.rewrite_applied
                                        ? 'Distributed (rewritten)'
                                        : 'Distributed';
                                    $cell.html('<span style="color:' + color + ';font-weight:bold;">' + icon + '</span>')
                                        .attr('title', title);
                                }
                            });
                        }

                        // Close thickbox
                        if (typeof tb_remove === 'function') {
                            tb_remove();
                        }

                        // Reload after short delay to refresh modals
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
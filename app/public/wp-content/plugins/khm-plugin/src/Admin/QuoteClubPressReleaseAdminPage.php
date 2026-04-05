<?php

namespace KHM\Admin;

class QuoteClubPressReleaseAdminPage {

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu'], 20);
    }

    public function add_menu(): void {
        add_submenu_page(
            'editorial_planner',
            __('Press Releases', 'khm-membership'),
            __('Press Releases', 'khm-membership'),
            'edit_posts',
            'khm-qc-press-releases',
            [$this, 'render']
        );
    }

    public function render(): void {
        $status_filter = isset($_GET['qc_pr_status']) ? sanitize_text_field($_GET['qc_pr_status']) : 'submitted';
        $allowed = ['submitted', 'published', 'rejected', 'all'];
        if (!in_array($status_filter, $allowed, true)) {
            $status_filter = 'submitted';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $where = $status_filter !== 'all'
            ? $wpdb->prepare('WHERE pr.status = %s', $status_filter)
            : '';

        $rows = $wpdb->get_results(
            "SELECT pr.*, u.display_name, u.user_email, s.name AS sponsor_name
             FROM {$table} pr
             LEFT JOIN {$wpdb->users} u ON u.ID = pr.user_id
             LEFT JOIN {$wpdb->prefix}khm_sponsors s ON s.id = pr.sponsor_id
             {$where}
             ORDER BY COALESCE(pr.submission_date, pr.created_at) DESC
             LIMIT 100",
            ARRAY_A
        );

        $nonce = wp_create_nonce('wp_rest');
        $rest_url = esc_url(rest_url('khm/v1/portal/quoteclub/press-releases'));
        $page_url = esc_url(admin_url('admin.php?page=khm-qc-press-releases'));

        $tabs = [
            'submitted' => __('Submitted', 'khm-membership'),
            'published' => __('Published', 'khm-membership'),
            'rejected'  => __('Rejected', 'khm-membership'),
            'all'       => __('All', 'khm-membership'),
        ];
        ?>
        <div class="wrap khm-press-release-review">
            <h1><?php esc_html_e('Press Release Queue', 'khm-membership'); ?></h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:1rem;">
                <?php foreach ($tabs as $slug => $label) : ?>
                    <a href="<?php echo esc_url(add_query_arg('qc_pr_status', $slug, $page_url)); ?>"
                       class="nav-tab<?php echo $status_filter === $slug ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (empty($rows)) : ?>
                <p><?php esc_html_e('No press releases found.', 'khm-membership'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th style="width:130px"><?php esc_html_e('Sponsor', 'khm-membership'); ?></th>
                            <th style="width:150px"><?php esc_html_e('Author', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Title', 'khm-membership'); ?></th>
                            <th><?php esc_html_e('Excerpt', 'khm-membership'); ?></th>
                            <th style="width:100px"><?php esc_html_e('Status', 'khm-membership'); ?></th>
                            <th style="width:110px"><?php esc_html_e('Submitted', 'khm-membership'); ?></th>
                            <th style="width:220px"><?php esc_html_e('Actions', 'khm-membership'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php $excerpt = !empty($row['excerpt']) ? $row['excerpt'] : wp_trim_words((string) $row['content'], 25, '...'); ?>
                        <tr id="pr-row-<?php echo (int) $row['id']; ?>">
                            <td><?php echo (int) $row['id']; ?></td>
                            <td><?php echo esc_html($row['sponsor_name'] ?: '-'); ?></td>
                            <td title="<?php echo esc_attr($row['user_email']); ?>"><?php echo esc_html($row['display_name'] ?: $row['user_email']); ?></td>
                            <td><?php echo esc_html($row['title']); ?></td>
                            <td>
                                <details>
                                    <summary style="cursor:pointer"><?php echo esc_html(wp_trim_words((string) $excerpt, 18, '...')); ?></summary>
                                    <div style="white-space:pre-wrap;margin-top:4px"><?php echo esc_html((string) $row['content']); ?></div>
                                </details>
                                <?php if (!empty($row['rejection_reason'])) : ?>
                                    <p style="color:#8b0000;font-size:.8em;margin:4px 0 0;">
                                        <?php esc_html_e('Rejection:', 'khm-membership'); ?> <?php echo esc_html($row['rejection_reason']); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td><span class="qc-pr-badge qc-pr-badge-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html($row['status']); ?></span></td>
                            <td><?php echo esc_html(substr((string) ($row['submission_date'] ?: $row['created_at']), 0, 10)); ?></td>
                            <td class="qc-pr-actions">
                                <?php if ($row['status'] === 'submitted') : ?>
                                    <button class="button button-primary button-small qc-pr-publish-btn" data-id="<?php echo (int) $row['id']; ?>">
                                        <?php esc_html_e('Approve & Publish', 'khm-membership'); ?>
                                    </button>
                                    <button class="button button-small qc-pr-reject-btn" data-id="<?php echo (int) $row['id']; ?>" style="color:#b32d2e">
                                        <?php esc_html_e('Reject', 'khm-membership'); ?>
                                    </button>
                                <?php else : ?>
                                    <em style="color:#6b7280;font-size:.85em"><?php echo esc_html(ucfirst((string) $row['status'])); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div id="qc-pr-reject-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;padding:2rem;width:min(500px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.2)">
                <h2 style="margin:0 0 1rem"><?php esc_html_e('Reject Press Release', 'khm-membership'); ?></h2>
                <p><?php esc_html_e('Optionally provide a reason that will be emailed to the sponsor.', 'khm-membership'); ?></p>
                <textarea id="qc-pr-rejection-reason" rows="4" style="width:100%;box-sizing:border-box" placeholder="<?php esc_attr_e('Reason for rejection (optional)', 'khm-membership'); ?>"></textarea>
                <div style="display:flex;gap:.75rem;margin-top:1rem">
                    <button id="qc-pr-reject-confirm" class="button button-primary"><?php esc_html_e('Confirm Rejection', 'khm-membership'); ?></button>
                    <button id="qc-pr-reject-cancel" class="button"><?php esc_html_e('Cancel', 'khm-membership'); ?></button>
                </div>
            </div>
        </div>

        <style>
        .qc-pr-badge { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:capitalize }
        .qc-pr-badge-submitted { background:#fef3c7;color:#92400e }
        .qc-pr-badge-published { background:#dbeafe;color:#1e40af }
        .qc-pr-badge-rejected { background:#fee2e2;color:#991b1b }
        .qc-pr-badge-draft { background:#f3f4f6;color:#374151 }
        .qc-pr-actions { display:flex;gap:.5rem;flex-wrap:wrap }
        </style>

        <script>
        (function($) {
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var restBase = <?php echo wp_json_encode($rest_url); ?>;
            var rejectId = null;

            function api(path, method, data) {
                return $.ajax({
                    url: restBase + (path ? '/' + path : ''),
                    method: method,
                    contentType: 'application/json',
                    data: JSON.stringify(data || {}),
                    headers: { 'X-WP-Nonce': nonce },
                    dataType: 'json'
                });
            }

            function updateRow(id, status) {
                var $row = $('#pr-row-' + id);
                $row.find('.qc-pr-badge')
                    .attr('class', 'qc-pr-badge qc-pr-badge-' + status)
                    .text(status);
                $row.find('.qc-pr-actions').html('<em style="color:#6b7280;font-size:.85em">' + status.charAt(0).toUpperCase() + status.slice(1) + '</em>');
                $row.css('opacity', '.6');
            }

            $(document).on('click', '.qc-pr-publish-btn', function() {
                var id = $(this).data('id');
                $(this).prop('disabled', true).text('Publishing...');
                api(id + '/publish', 'POST', {})
                    .done(function() { updateRow(id, 'published'); })
                    .fail(function(xhr) {
                        alert('Publish failed: ' + ((xhr.responseJSON && xhr.responseJSON.error) || 'unknown'));
                    });
            });

            $(document).on('click', '.qc-pr-reject-btn', function() {
                rejectId = $(this).data('id');
                $('#qc-pr-rejection-reason').val('');
                $('#qc-pr-reject-modal').css('display', 'flex');
            });

            $('#qc-pr-reject-cancel').on('click', function() {
                rejectId = null;
                $('#qc-pr-reject-modal').hide();
            });

            $('#qc-pr-reject-confirm').on('click', function() {
                if (!rejectId) {
                    return;
                }

                var reason = $('#qc-pr-rejection-reason').val().trim();
                $(this).prop('disabled', true).text('Rejecting...');
                api(rejectId + '/reject', 'POST', { reason: reason })
                    .done(function() {
                        updateRow(rejectId, 'rejected');
                        $('#qc-pr-reject-modal').hide();
                        rejectId = null;
                    })
                    .fail(function(xhr) {
                        alert('Reject failed: ' + ((xhr.responseJSON && xhr.responseJSON.error) || 'unknown'));
                    })
                    .always(function() {
                        $('#qc-pr-reject-confirm').prop('disabled', false).text('Confirm Rejection');
                    });
            });
        })(jQuery);
        </script>
        <?php
    }
}
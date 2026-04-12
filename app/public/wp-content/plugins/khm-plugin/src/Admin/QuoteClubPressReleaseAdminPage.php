<?php

namespace KHM\Admin;

/**
 * Admin page: Press Release editorial review queue.
 * Registered as a submenu under the editorial_planner menu.
 */
class QuoteClubPressReleaseAdminPage {

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu'], 21);
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
        $status_filter = isset($_GET['qc_status']) ? sanitize_text_field($_GET['qc_status']) : 'submitted';
        $allowed = ['submitted', 'published', 'rejected', 'all'];
        if (!in_array($status_filter, $allowed, true)) {
            $status_filter = 'submitted';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $where = $status_filter !== 'all'
            ? $wpdb->prepare("WHERE pr.status = %s", $status_filter)
            : '';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            "SELECT pr.*, u.display_name, u.user_email,
                    s.name AS sponsor_name
             FROM {$table} pr
             LEFT JOIN {$wpdb->users} u ON u.ID = pr.user_id
             LEFT JOIN {$wpdb->prefix}khm_sponsors s ON s.id = pr.sponsor_id
             {$where}
             ORDER BY pr.submission_date DESC, pr.created_at DESC
             LIMIT 100",
            ARRAY_A
        );

        $nonce = wp_create_nonce('wp_rest');
        $rest_url = esc_url(rest_url('khm/v1/portal/quoteclub/press-releases'));
        $admin_url = esc_url(admin_url('admin.php?page=khm-qc-press-releases'));

        $tabs = [
            'submitted'  => 'Pending Review',
            'published'  => 'Published',
            'rejected'   => 'Rejected',
            'all'        => 'All',
        ];
        ?>
        <div class="wrap khm-press-release-review">
            <h1><?php esc_html_e('Press Release Queue', 'khm-membership'); ?></h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:1rem;">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg('qc_status', $slug, $admin_url)); ?>"
                       class="nav-tab<?php echo $status_filter === $slug ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (empty($rows)): ?>
                <p><?php esc_html_e('No press releases found.', 'khm-membership'); ?></p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:110px">Sponsor</th>
                        <th style="width:120px">Author</th>
                        <th>Title</th>
                        <th style="width:70px">Excerpt</th>
                        <th style="width:90px">Status</th>
                        <th style="width:110px">Submitted</th>
                        <th style="width:200px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr id="pr-row-<?php echo (int) $row['id']; ?>">
                        <td><?php echo (int) $row['id']; ?></td>
                        <td><?php echo esc_html($row['sponsor_name'] ?: '—'); ?></td>
                        <td title="<?php echo esc_attr($row['user_email']); ?>"><?php echo esc_html($row['display_name'] ?: $row['user_email']); ?></td>
                        <td><strong><?php echo esc_html($row['title']); ?></strong></td>
                        <td>
                            <details>
                                <summary style="cursor:pointer"><?php echo esc_html(wp_trim_words((string) $row['excerpt'], 8, '…')); ?></summary>
                                <div style="white-space:pre-wrap;margin-top:4px;background:#f5f5f5;padding:8px;border-radius:4px;font-size:.85em"><?php echo esc_html($row['content']); ?></div>
                            </details>
                        </td>
                        <td><span class="pr-badge pr-badge-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td><?php echo esc_html(substr((string) ($row['submission_date'] ?: $row['created_at']), 0, 10)); ?></td>
                        <td class="pr-actions">
                            <?php if ($row['status'] === 'submitted'): ?>
                                <button class="button button-primary button-small pr-publish-btn"
                                        data-id="<?php echo (int) $row['id']; ?>"
                                        style="margin-bottom:4px">Approve & Publish</button>
                                <button class="button button-small pr-reject-btn"
                                        data-id="<?php echo (int) $row['id']; ?>"
                                        data-user="<?php echo esc_attr($row['display_name']); ?>"
                                        style="color:#b32d2e">Reject</button>
                            <?php else: ?>
                                <em style="color:#6b7280;font-size:.85em"><?php echo esc_html(ucfirst(str_replace('_', ' ', $row['status']))); ?></em>
                                <?php if (!empty($row['rejection_reason'])): ?>
                                    <p style="color:#8b0000;font-size:.8em;margin:4px 0 0">Reason: <?php echo esc_html($row['rejection_reason']); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Reject modal -->
        <div id="pr-reject-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:8px;padding:2rem;width:min(500px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.2)">
                <h2 style="margin:0 0 1rem">Reject Press Release</h2>
                <p>Optionally provide a reason that will be emailed to the sponsor. The credit will be automatically refunded.</p>
                <textarea id="pr-rejection-reason" rows="4" style="width:100%;box-sizing:border-box" placeholder="Reason for rejection (optional)"></textarea>
                <div style="display:flex;gap:.75rem;margin-top:1rem">
                    <button id="pr-reject-confirm" class="button button-primary">Confirm Rejection</button>
                    <button id="pr-reject-cancel" class="button">Cancel</button>
                </div>
            </div>
        </div>

        <style>
        .pr-badge { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:capitalize }
        .pr-badge-submitted { background:#fef3c7;color:#92400e }
        .pr-badge-published { background:#dbeafe;color:#1e40af }
        .pr-badge-rejected { background:#fee2e2;color:#991b1b }
        .pr-badge-draft { background:#f3f4f6;color:#374151 }
        </style>

        <script>
        (function($){
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var restBase = <?php echo wp_json_encode($rest_url); ?>;
            var rejectId = null;

            function prApi(path, method, data) {
                return $.ajax({
                    url: restBase + (path ? '/' + path : ''),
                    method: method || 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data || {}),
                    headers: { 'X-WP-Nonce': nonce },
                    dataType: 'json'
                });
            }

            function flashRow(id, status) {
                var $row = $('#pr-row-' + id);
                $row.find('.pr-badge').attr('class', 'pr-badge pr-badge-' + status).text(status.replace('_', ' '));
                $row.find('.pr-actions').html('<em style="color:#6b7280;font-size:.85em">' + status.replace('_', ' ') + '</em>');
                $row.css('opacity', '.5');
            }

            $(document).on('click', '.pr-publish-btn', function() {
                var id = $(this).data('id');
                $(this).prop('disabled', true).text('Publishing…');
                prApi(id + '/publish', 'POST', {})
                    .done(function(){ flashRow(id, 'published'); })
                    .fail(function(xhr){ alert('Publish failed: ' + (xhr.responseJSON && xhr.responseJSON.error || 'unknown')); });
            });

            $(document).on('click', '.pr-reject-btn', function() {
                rejectId = $(this).data('id');
                $('#pr-rejection-reason').val('');
                $('#pr-reject-modal').css('display', 'flex');
            });

            $('#pr-reject-cancel').on('click', function(){
                $('#pr-reject-modal').hide();
                rejectId = null;
            });

            $('#pr-reject-confirm').on('click', function() {
                if (!rejectId) return;
                var reason = $('#pr-rejection-reason').val().trim();
                $(this).prop('disabled', true).text('Rejecting…');
                prApi(rejectId + '/reject', 'POST', { reason: reason })
                    .done(function(){
                        flashRow(rejectId, 'rejected');
                        $('#pr-reject-modal').hide();
                        rejectId = null;
                    })
                    .fail(function(xhr){
                        alert('Reject failed: ' + (xhr.responseJSON && xhr.responseJSON.error || 'unknown'));
                    })
                    .always(function(){ $('#pr-reject-confirm').prop('disabled', false).text('Confirm Rejection'); });
            });
        })(jQuery);
        </script>
        <?php
    }
}

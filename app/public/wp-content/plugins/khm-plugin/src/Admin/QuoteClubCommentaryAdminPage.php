<?php

namespace KHM\Admin;

/**
 * Admin page: Sponsor Commentary review queue.
 * Registered as a submenu under the editorial_planner menu.
 */
class QuoteClubCommentaryAdminPage {

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu'], 20);
    }

    public function add_menu(): void {
        add_submenu_page(
            'editorial_planner',
            __('Sponsor Commentary', 'khm-membership'),
            __('Sponsor Commentary', 'khm-membership'),
            'edit_posts',
            'khm-qc-commentary',
            [$this, 'render']
        );
    }

    public function render(): void {
        $status_filter = isset($_GET['qc_status']) ? sanitize_text_field($_GET['qc_status']) : 'pending_editorial';
        $allowed = ['pending_editorial', 'approved', 'rejected', 'published', 'all'];
        if (!in_array($status_filter, $allowed, true)) {
            $status_filter = 'pending_editorial';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        $where = $status_filter !== 'all'
            ? $wpdb->prepare("WHERE c.status = %s", $status_filter)
            : '';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            "SELECT c.*, u.display_name, u.user_email,
                    s.name AS sponsor_name
             FROM {$table} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             LEFT JOIN {$wpdb->prefix}khm_sponsors s ON s.id = c.sponsor_id
             {$where}
             ORDER BY c.created_at DESC
             LIMIT 100",
            ARRAY_A
        );

        $nonce = wp_create_nonce('wp_rest');
        $rest_url = esc_url(rest_url('khm/v1/portal/quoteclub/commentary'));
        $portal_url = esc_url(admin_url('admin.php?page=khm-qc-commentary'));

        $tabs = [
            'pending_editorial' => 'Pending',
            'approved'          => 'Approved',
            'rejected'          => 'Rejected',
            'published'         => 'Published',
            'all'               => 'All',
        ];
        ?>
        <div class="wrap khm-commentary-review">
            <h1><?php esc_html_e('Sponsor Commentary Queue', 'khm-membership'); ?></h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:1rem;">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg('qc_status', $slug, $portal_url)); ?>"
                       class="nav-tab<?php echo $status_filter === $slug ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (empty($rows)): ?>
                <p><?php esc_html_e('No commentary found.', 'khm-membership'); ?></p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:110px">Sponsor</th>
                        <th style="width:120px">Author</th>
                        <th>Session ID</th>
                        <th style="width:70px">Words</th>
                        <th style="width:60px">Credits</th>
                        <th style="width:90px">Status</th>
                        <th style="width:110px">Submitted</th>
                        <th>Excerpt</th>
                        <th style="width:200px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr id="qc-row-<?php echo (int) $row['id']; ?>">
                        <td><?php echo (int) $row['id']; ?></td>
                        <td><?php echo esc_html($row['sponsor_name'] ?: '—'); ?></td>
                        <td title="<?php echo esc_attr($row['user_email']); ?>"><?php echo esc_html($row['display_name'] ?: $row['user_email']); ?></td>
                        <td><?php echo esc_html($row['session_id']); ?></td>
                        <td><?php echo (int) $row['word_count']; ?></td>
                        <td><?php echo (int) $row['credits_used']; ?></td>
                        <td><span class="qc-badge qc-badge-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td><?php echo esc_html(substr((string) ($row['submitted_at'] ?: $row['created_at']), 0, 10)); ?></td>
                        <td>
                            <details>
                                <summary style="cursor:pointer"><?php echo esc_html(wp_trim_words((string) $row['commentary_text'], 15, '…')); ?></summary>
                                <div style="white-space:pre-wrap;margin-top:4px"><?php echo esc_html($row['commentary_text']); ?></div>
                            </details>
                            <?php if (!empty($row['rejection_reason'])): ?>
                                <p style="color:#8b0000;font-size:.8em;margin:2px 0 0">Rejection: <?php echo esc_html($row['rejection_reason']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="qc-actions">
                            <?php if ($row['status'] === 'pending_editorial'): ?>
                                <label style="display:block;font-size:.8em;margin-bottom:4px">
                                    <input type="checkbox" class="qc-insert-cb" data-id="<?php echo (int) $row['id']; ?>"> Insert into framework
                                </label>
                                <button class="button button-primary button-small qc-approve-btn"
                                        data-id="<?php echo (int) $row['id']; ?>"
                                        style="margin-bottom:4px">Approve</button>
                                <button class="button button-small qc-reject-btn"
                                        data-id="<?php echo (int) $row['id']; ?>"
                                        data-user="<?php echo esc_attr($row['display_name']); ?>"
                                        style="color:#b32d2e">Reject</button>
                            <?php elseif ($row['status'] === 'approved'): ?>
                                <button class="button button-small qc-publish-btn"
                                        data-id="<?php echo (int) $row['id']; ?>">Mark Published</button>
                            <?php else: ?>
                                <em style="color:#6b7280;font-size:.85em"><?php echo esc_html(ucfirst(str_replace('_', ' ', $row['status']))); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Reject modal -->
        <div id="qc-reject-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:8px;padding:2rem;width:min(500px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.2)">
                <h2 style="margin:0 0 1rem">Reject Commentary</h2>
                <p>Optionally provide a reason that will be emailed to the sponsor.</p>
                <textarea id="qc-rejection-reason" rows="4" style="width:100%;box-sizing:border-box" placeholder="Reason for rejection (optional)"></textarea>
                <div style="display:flex;gap:.75rem;margin-top:1rem">
                    <button id="qc-reject-confirm" class="button button-primary">Confirm Rejection</button>
                    <button id="qc-reject-cancel" class="button">Cancel</button>
                </div>
            </div>
        </div>

        <style>
        .qc-badge { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600;text-transform:capitalize }
        .qc-badge-pending_editorial { background:#fef3c7;color:#92400e }
        .qc-badge-approved { background:#d1fae5;color:#065f46 }
        .qc-badge-rejected { background:#fee2e2;color:#991b1b }
        .qc-badge-published { background:#dbeafe;color:#1e40af }
        .qc-badge-draft { background:#f3f4f6;color:#374151 }
        </style>

        <script>
        (function($){
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var restBase = <?php echo wp_json_encode($rest_url); ?>;
            var rejectId = null;

            function qcApi(path, method, data) {
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
                var $row = $('#qc-row-' + id);
                $row.find('.qc-badge').attr('class', 'qc-badge qc-badge-' + status).text(status.replace('_', ' '));
                $row.find('.qc-actions').html('<em style="color:#6b7280;font-size:.85em">' + status.replace('_', ' ') + '</em>');
                $row.css('opacity', '.5');
            }

            $(document).on('click', '.qc-approve-btn', function() {
                var id = $(this).data('id');
                var insert = $('#qc-row-' + id + ' .qc-insert-cb').prop('checked');
                $(this).prop('disabled', true).text('Approving…');
                qcApi(id + '/approve', 'POST', { insert: insert, insert_target: 'framework' })
                    .done(function(){ flashRow(id, 'approved'); })
                    .fail(function(xhr){ alert('Approve failed: ' + (xhr.responseJSON && xhr.responseJSON.error || 'unknown')); });
            });

            $(document).on('click', '.qc-reject-btn', function() {
                rejectId = $(this).data('id');
                $('#qc-rejection-reason').val('');
                $('#qc-reject-modal').css('display', 'flex');
            });

            $('#qc-reject-cancel').on('click', function(){
                $('#qc-reject-modal').hide();
                rejectId = null;
            });

            $('#qc-reject-confirm').on('click', function() {
                if (!rejectId) return;
                var reason = $('#qc-rejection-reason').val().trim();
                $(this).prop('disabled', true).text('Rejecting…');
                qcApi(rejectId + '/reject', 'POST', { rejection_reason: reason })
                    .done(function(){
                        flashRow(rejectId, 'rejected');
                        $('#qc-reject-modal').hide();
                        rejectId = null;
                    })
                    .fail(function(xhr){
                        alert('Reject failed: ' + (xhr.responseJSON && xhr.responseJSON.error || 'unknown'));
                    })
                    .always(function(){ $('#qc-reject-confirm').prop('disabled', false).text('Confirm Rejection'); });
            });

            $(document).on('click', '.qc-publish-btn', function() {
                var id = $(this).data('id');
                $(this).prop('disabled', true).text('Saving…');
                $.ajax({
                    url: restBase + '/' + id,
                    method: 'PATCH',
                    contentType: 'application/json',
                    data: JSON.stringify({ status: 'published' }),
                    headers: { 'X-WP-Nonce': nonce },
                    dataType: 'json'
                }).done(function(){ flashRow(id, 'published'); })
                  .fail(function(){ alert('Failed to mark as published.'); });
            });
        })(jQuery);
        </script>
        <?php
    }
}

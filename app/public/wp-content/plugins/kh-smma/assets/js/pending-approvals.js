(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(null, root);
        return;
    }

    root.KHSmmaPendingApprovals = factory(root.jQuery, root);
}(typeof window !== 'undefined' ? window : globalThis, function ($, root) {
    'use strict';

    var settings = root.khSmmaSponsorApproval || {};
    var state = {
        page: 1,
        per_page: 25,
        action: null,
        scheduleIds: [],
        canManageApprovals: !!(settings.permissions && settings.permissions.can_manage_approvals),
        deniedMessage: settings.permissions && settings.permissions.denied_message
            ? settings.permissions.denied_message
            : 'You do not have permission to approve schedules for this sponsor.'
    };

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizeRow(row) {
        row = row || {};
        return {
            schedule_id: String(row.schedule_id || ''),
            variant_id: String(row.variant_id || ''),
            post_title: String(row.post_title || '—'),
            variant_preview: String(row.variant_preview || ''),
            sponsor_name: String(row.sponsor_name || '—'),
            submitter: String(row.submitter || '—'),
            requested_schedule_date: String(row.requested_schedule_date || '—'),
            approval_reason: String(row.approval_reason || ''),
            approval_status: String(row.approval_status || 'pending'),
            compliance_status: String(row.compliance_status || 'OK').toUpperCase(),
            compliance_reason: String(row.compliance_reason || ''),
            last_approved_by: String(row.last_approved_by || '—'),
            last_approved_at: String(row.last_approved_at || '—'),
            can_approve: !!row.can_approve,
            permission_message: String(row.permission_message || state.deniedMessage)
        };
    }

    function reasonMarkup(reason) {
        if (reason === 'compliance_changed') {
            return '<span class="kh-smma-rereview-badge">Re-review: Compliance Change</span>';
        }
        if (reason === 'sponsor_claim_change') {
            return '<span class="kh-smma-rereview-badge">Re-review: Claim Permission Change</span>';
        }
        return esc(reason || '—');
    }

    function renderRow(rawRow) {
        var row = normalizeRow(rawRow);
        var isComplianceFail = row.compliance_status === 'FAIL';
        var blockedMessage = settings.messages && settings.messages.complianceFailBlocked
            ? settings.messages.complianceFailBlocked
            : 'Compliance failure detected. Variant must be edited and pass compliance before approval.';
        var permissionMessage = isComplianceFail ? blockedMessage : row.permission_message;
        var checkboxCell = row.can_approve && !isComplianceFail
            ? '<input type="checkbox" class="kh-smma-row-checkbox" value="' + esc(row.schedule_id) + '" />'
            : '<span class="kh-smma-permission-tooltip" title="' + esc(permissionMessage) + '">—</span>';
        var actionsCell = row.can_approve
            ? '<button type="button" class="button button-primary kh-smma-review-action" data-action-type="approve" data-schedule-id="' + esc(row.schedule_id) + '"' + (isComplianceFail ? ' disabled title="' + esc(permissionMessage) + '"' : '') + '>Approve</button>' +
              '<button type="button" class="button kh-smma-review-action" data-action-type="reject" data-schedule-id="' + esc(row.schedule_id) + '">Reject</button>'
            : '<span class="kh-smma-permission-tooltip" title="' + esc(permissionMessage) + '">Read-only</span>';

        return '' +
            '<tr data-schedule-id="' + esc(row.schedule_id) + '">' +
                '<td class="check-column">' + checkboxCell + '</td>' +
                '<td>' + esc(row.schedule_id) + '</td>' +
                '<td class="kh-smma-approval-post-cell">' +
                    '<strong>' + esc(row.post_title) + '</strong>' +
                    '<div class="kh-smma-approval-variant-id">' + esc(row.variant_id || '—') + '</div>' +
                    (row.variant_preview ? '<div class="kh-smma-approval-variant-preview">' + esc(row.variant_preview) + '</div>' : '') +
                '</td>' +
                '<td>' + esc(row.sponsor_name) + '</td>' +
                '<td>' + esc(row.submitter) + '</td>' +
                '<td>' + esc(row.requested_schedule_date) + '</td>' +
                '<td>' + reasonMarkup(row.approval_reason) + '</td>' +
                '<td>' +
                    '<span class="kh-smma-approval-badge kh-smma-approval-' + esc(row.compliance_status.toLowerCase()) + '">' + esc(row.compliance_status) + '</span>' +
                    (row.compliance_reason ? '<div class="kh-smma-approval-compliance-reason">' + esc(row.compliance_reason) + '</div>' : '') +
                '</td>' +
                '<td>' +
                    '<div>' + esc(row.last_approved_by) + '</div>' +
                    '<div class="kh-smma-approval-last-reviewed">' + esc(row.last_approved_at) + '</div>' +
                '</td>' +
                '<td><span class="kh-smma-approval-badge kh-smma-approval-' + esc(row.approval_status) + '">' + esc(row.approval_status.charAt(0).toUpperCase() + row.approval_status.slice(1)) + '</span></td>' +
                '<td>' + actionsCell + '</td>' +
            '</tr>';
    }

    function renderRows(rows) {
        if (!rows || !rows.length) {
            return '<tr><td colspan="10">No schedules found.</td></tr>';
        }
        return rows.map(renderRow).join('');
    }

    function buildDecisionPayload(action, scheduleIds, notes) {
        return {
            action: action,
            endpoint: action === 'approve' ? '/approve' : '/reject',
            body: {
                schedule_ids: scheduleIds.slice(),
                reviewer_user_id: 0,
                review_notes: String(notes || '')
            }
        };
    }

    function formatHistoryTimestamp(value) {
        if (!value) {
            return '';
        }
        if (typeof value === 'number') {
            return new Date(value * 1000).toISOString().slice(0, 16).replace('T', ' ');
        }
        return String(value).replace('T', ' ').replace('Z', '');
    }

    function collectFilters() {
        return {
            sponsor_id: $('#kh-smma-filter-sponsor').val() || '',
            status: $('#kh-smma-filter-status').val() || 'pending',
            date_from: $('input[name="date_from"]').val() || '',
            date_to: $('input[name="date_to"]').val() || '',
            search_term: $('#kh-smma-filter-search').val() || '',
            page: state.page,
            per_page: state.per_page
        };
    }

    function renderPagination(page, totalPages) {
        var $container = $('#kh-smma-pagination');
        totalPages = parseInt(totalPages || 1, 10);
        page = parseInt(page || 1, 10);

        if (totalPages <= 1) {
            $container.empty();
            return;
        }

        var prevDisabled = page <= 1 ? 'disabled' : '';
        var nextDisabled = page >= totalPages ? 'disabled' : '';
        $container.html(
            '<button type="button" class="button kh-smma-page-btn" data-page="' + (page - 1) + '" ' + prevDisabled + '>Previous</button>' +
            '<span class="kh-smma-page-indicator">Page ' + page + ' of ' + totalPages + '</span>' +
            '<button type="button" class="button kh-smma-page-btn" data-page="' + (page + 1) + '" ' + nextDisabled + '>Next</button>'
        );
    }

    function applyPermissionGuardrails() {
        if (state.canManageApprovals) {
            return;
        }

        $('#kh-smma-bulk-approve, #kh-smma-bulk-reject').remove();
        if (!$('.kh-smma-bulk-actions .kh-smma-permission-tooltip').length) {
            $('.kh-smma-bulk-actions').append('<span class="kh-smma-permission-tooltip">' + esc(state.deniedMessage) + '</span>');
        }
    }

    function fetchRows() {
        if (!$) {
            return;
        }

        $.ajax({
            url: settings.apiBase,
            method: 'GET',
            data: collectFilters(),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', settings.nonce);
            }
        }).done(function (response) {
            if (response.permissions) {
                state.canManageApprovals = !!response.permissions.can_manage_approvals;
                state.deniedMessage = response.permissions.denied_message || state.deniedMessage;
            }
            $('#kh-smma-approval-rows').html(renderRows(response.rows || []));
            renderPagination(response.page || 1, response.total_pages || 1);
            applyPermissionGuardrails();
        });
    }

    function selectedScheduleIds() {
        return $('.kh-smma-row-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function openModal(action, ids) {
        if (!ids.length) {
            return;
        }
        state.action = action;
        state.scheduleIds = ids;
        $('#kh-smma-review-title').text(action === 'approve' ? 'Approve Schedule(s)' : 'Reject Schedule(s)');
        $('#kh-smma-review-target').text(ids.length === 1 ? 'Schedule #' + ids[0] : ids.length + ' schedules selected');
        $('#kh-smma-review-notes').val('');
        $('#kh-smma-review-hint').text(settings.messages && settings.messages.bulkReviewHint ? settings.messages.bulkReviewHint : '');
        $('#kh-smma-review-modal').show();

        $.ajax({
            url: settings.apiBase + '/review-started',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                schedule_ids: ids,
                reviewer_user_id: 0,
                timestamp: Math.floor(Date.now() / 1000)
            }),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', settings.nonce);
            }
        });
    }

    function closeModal() {
        $('#kh-smma-review-modal').hide();
        state.action = null;
        state.scheduleIds = [];
    }

    function bindDom() {
        if (!$ || !$('#kh-smma-approval-rows').length) {
            return;
        }

        $(document).on('submit', '#kh-smma-approval-filters', function (e) {
            e.preventDefault();
            state.page = 1;
            fetchRows();
        });

        var searchDebounce;
        $(document).on('input', '#kh-smma-filter-search', function () {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function () {
                state.page = 1;
                fetchRows();
            }, 250);
        });

        $(document).on('change', '#kh-smma-select-all', function () {
            $('.kh-smma-row-checkbox').prop('checked', $(this).is(':checked'));
        });

        $(document).on('click', '.kh-smma-page-btn', function () {
            var page = parseInt($(this).data('page'), 10);
            if (!page || page < 1) {
                return;
            }
            state.page = page;
            fetchRows();
        });

        $(document).on('click', '.kh-smma-review-action', function () {
            var action = $(this).data('action-type');
            var id = String($(this).data('schedule-id') || '');
            if ($(this).is(':disabled')) {
                root.alert(settings.messages && settings.messages.complianceFailBlocked
                    ? settings.messages.complianceFailBlocked
                    : 'Compliance failure detected. Variant must be edited and pass compliance before approval.');
                return;
            }
            openModal(action, id ? [id] : []);
        });

        $(document).on('click', '#kh-smma-bulk-approve', function () {
            if (!state.canManageApprovals) {
                root.alert(state.deniedMessage);
                return;
            }
            openModal('approve', selectedScheduleIds());
        });

        $(document).on('click', '#kh-smma-bulk-reject', function () {
            if (!state.canManageApprovals) {
                root.alert(state.deniedMessage);
                return;
            }
            openModal('reject', selectedScheduleIds());
        });

        $(document).on('click', '#kh-smma-review-cancel', closeModal);

        $(document).on('click', '#kh-smma-review-confirm', function () {
            var payload = buildDecisionPayload(state.action, state.scheduleIds, $('#kh-smma-review-notes').val() || '');
            if (!payload.body.schedule_ids.length) {
                closeModal();
                return;
            }
            $.ajax({
                url: settings.apiBase + payload.endpoint,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload.body),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', settings.nonce);
                }
            }).done(function () {
                closeModal();
                fetchRows();
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Unable to save approval decision.';
                root.alert(message);
            });
        });

        applyPermissionGuardrails();
        fetchRows();
    }

    if ($) {
        $(bindDom);
    }

    return {
        normalizeRow: normalizeRow,
        renderRow: renderRow,
        renderRows: renderRows,
        buildDecisionPayload: buildDecisionPayload,
        formatHistoryTimestamp: formatHistoryTimestamp,
        init: bindDom
    };
}));

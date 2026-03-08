(function ($) {
    'use strict';

    if (typeof khSmmaSponsorApproval === 'undefined') {
        return;
    }

    const state = {
        page: 1,
        per_page: 25,
        action: null,
        scheduleIds: [],
        canManageApprovals: !!(khSmmaSponsorApproval.permissions && khSmmaSponsorApproval.permissions.can_manage_approvals),
        deniedMessage: (khSmmaSponsorApproval.permissions && khSmmaSponsorApproval.permissions.denied_message)
            ? khSmmaSponsorApproval.permissions.denied_message
            : 'You do not have permission to approve schedules for this sponsor.',
    };

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function collectFilters() {
        return {
            sponsor_id: $('#kh-smma-filter-sponsor').val() || '',
            status: $('#kh-smma-filter-status').val() || 'pending',
            date_from: $('input[name="date_from"]').val() || '',
            date_to: $('input[name="date_to"]').val() || '',
            search_term: $('#kh-smma-filter-search').val() || '',
            page: state.page,
            per_page: state.per_page,
        };
    }

    function renderRows(rows) {
        const $tbody = $('#kh-smma-approval-rows');
        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="8">No schedules found.</td></tr>');
            return;
        }

        const html = rows.map(function (row) {
            const status = esc(row.approval_status || 'pending');
            const canApprove = !!row.can_approve;
            const complianceStatus = String(row.compliance_status || 'OK').toUpperCase();
            const isComplianceFail = complianceStatus === 'FAIL';
            const deniedMessage = esc(row.permission_message || state.deniedMessage);
            const complianceBlockedMessage = (khSmmaSponsorApproval.messages && khSmmaSponsorApproval.messages.complianceFailBlocked)
                ? esc(khSmmaSponsorApproval.messages.complianceFailBlocked)
                : 'Compliance failure detected. Variant must be edited and pass compliance before approval.';
            const checkboxCell = (canApprove && !isComplianceFail)
                ? `<input type="checkbox" class="kh-smma-row-checkbox" value="${esc(row.schedule_id)}" />`
                : `<span class="kh-smma-permission-tooltip" title="${isComplianceFail ? complianceBlockedMessage : deniedMessage}">—</span>`;
            const approvalReason = String(row.approval_reason || '');
            const reasonBadge = approvalReason === 'compliance_changed'
                ? '<span class="kh-smma-rereview-badge">Re-review: Compliance Change</span>'
                : (approvalReason === 'sponsor_claim_change'
                    ? '<span class="kh-smma-rereview-badge">Re-review: Claim Permission Change</span>'
                    : esc(approvalReason || '—'));
            const actionsCell = canApprove
                ? `<button type="button" class="button button-primary kh-smma-review-action" data-action-type="approve" data-schedule-id="${esc(row.schedule_id)}" ${isComplianceFail ? 'disabled title="' + complianceBlockedMessage + '"' : ''}>Approve</button>
                   <button type="button" class="button kh-smma-review-action" data-action-type="reject" data-schedule-id="${esc(row.schedule_id)}">Reject</button>`
                : `<span class="kh-smma-permission-tooltip" title="${deniedMessage}">Read-only</span>`;
            return `
                <tr data-schedule-id="${esc(row.schedule_id)}">
                    <td class="check-column">${checkboxCell}</td>
                    <td>${esc(row.schedule_id)}</td>
                    <td>${esc(row.sponsor_name)}</td>
                    <td>${reasonBadge}</td>
                    <td>${esc(row.last_approved_by || '—')}</td>
                    <td>${esc(row.last_approved_at || '—')}</td>
                    <td><span class="kh-smma-approval-badge kh-smma-approval-${status}">${esc(status.charAt(0).toUpperCase() + status.slice(1))}</span></td>
                    <td>${actionsCell}</td>
                </tr>
            `;
        }).join('');

        $tbody.html(html);
    }

    function renderPagination(page, totalPages) {
        const $container = $('#kh-smma-pagination');
        totalPages = parseInt(totalPages || 1, 10);
        page = parseInt(page || 1, 10);

        if (totalPages <= 1) {
            $container.empty();
            return;
        }

        const prevDisabled = page <= 1 ? 'disabled' : '';
        const nextDisabled = page >= totalPages ? 'disabled' : '';
        $container.html(`
            <button type="button" class="button kh-smma-page-btn" data-page="${page - 1}" ${prevDisabled}>Previous</button>
            <span class="kh-smma-page-indicator">Page ${page} of ${totalPages}</span>
            <button type="button" class="button kh-smma-page-btn" data-page="${page + 1}" ${nextDisabled}>Next</button>
        `);
    }

    function fetchRows() {
        const filters = collectFilters();
        $.ajax({
            url: khSmmaSponsorApproval.apiBase,
            method: 'GET',
            data: filters,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khSmmaSponsorApproval.nonce);
            },
        }).done(function (response) {
            if (response.permissions) {
                state.canManageApprovals = !!response.permissions.can_manage_approvals;
                state.deniedMessage = response.permissions.denied_message || state.deniedMessage;
            }
            renderRows(response.rows || []);
            renderPagination(response.page || 1, response.total_pages || 1);
            applyPermissionGuardrails();
        });
    }

    function applyPermissionGuardrails() {
        if (state.canManageApprovals) {
            return;
        }

        $('#kh-smma-bulk-approve, #kh-smma-bulk-reject').remove();
        if (!$('.kh-smma-bulk-actions .kh-smma-permission-tooltip').length) {
            $('.kh-smma-bulk-actions').append(`<span class="kh-smma-permission-tooltip">${esc(state.deniedMessage)}</span>`);
        }
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

    function renderHistoryTimeline(history) {
        const $container = $('#kh-smma-approval-history');
        if (!$container.length) {
            return;
        }

        if (!history || !history.length) {
            $container.html('<p>No approval history recorded</p>');
            return;
        }

        const html = history.map(function (event) {
            const timestamp = esc(formatHistoryTimestamp(event.timestamp));
            const reviewer = esc(event.reviewer_id || '—');
            const notes = esc(event.notes || '');
            const kind = esc((event.event || 'submitted').toLowerCase());

            let title = 'Submitted for approval';
            if (kind === 'approved') {
                title = 'Approved';
            } else if (kind === 'rejected') {
                title = 'Rejected';
            }

            return `
                <div class="kh-smma-history-item">
                    <div class="kh-smma-history-time">[${timestamp}]</div>
                    <div class="kh-smma-history-title">${title} by ${reviewer}</div>
                    ${notes ? `<div class="kh-smma-history-notes">Notes: ${notes}</div>` : ''}
                </div>
            `;
        }).join('');

        $container.html(html);
    }

    function fetchApprovalHistory() {
        const $container = $('#kh-smma-approval-history');
        if (!$container.length) {
            return;
        }

        const scheduleId = String($container.data('schedule-id') || '');
        if (!scheduleId) {
            renderHistoryTimeline([]);
            return;
        }

        $.ajax({
            url: khSmmaSponsorApproval.apiBase + '/history',
            method: 'GET',
            data: { schedule_id: scheduleId },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khSmmaSponsorApproval.nonce);
            },
        }).done(function (response) {
            renderHistoryTimeline(response.history || []);
        }).fail(function () {
            renderHistoryTimeline([]);
        });
    }

    function selectedScheduleIds() {
        return $('.kh-smma-row-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function emitReviewStarted(ids) {
        return $.ajax({
            url: khSmmaSponsorApproval.apiBase + '/review-started',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                schedule_ids: ids,
                reviewer_user_id: 0,
                timestamp: Math.floor(Date.now() / 1000),
            }),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khSmmaSponsorApproval.nonce);
            },
        });
    }

    function submitDecision(action, ids, notes) {
        const endpoint = action === 'approve' ? '/approve' : '/reject';
        return $.ajax({
            url: khSmmaSponsorApproval.apiBase + endpoint,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                schedule_ids: ids,
                reviewer_user_id: 0,
                review_notes: notes || '',
            }),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', khSmmaSponsorApproval.nonce);
            },
        });
    }

    function openModal(action, ids) {
        if (!ids.length) {
            return;
        }

        state.action = action;
        state.scheduleIds = ids;

        $('#kh-smma-review-title').text(action === 'approve' ? 'Approve Schedule(s)' : 'Reject Schedule(s)');
        $('#kh-smma-review-target').text(ids.length === 1 ? `Schedule #${ids[0]}` : `${ids.length} schedules selected`);
        $('#kh-smma-review-notes').val('');
        $('#kh-smma-review-modal').show();

        emitReviewStarted(ids);
    }

    function closeModal() {
        $('#kh-smma-review-modal').hide();
        state.action = null;
        state.scheduleIds = [];
    }

    $(document).on('submit', '#kh-smma-approval-filters', function (e) {
        e.preventDefault();
        state.page = 1;
        fetchRows();
    });

    let searchDebounce;
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
        const page = parseInt($(this).data('page'), 10);
        if (!page || page < 1) {
            return;
        }
        state.page = page;
        fetchRows();
    });

    $(document).on('click', '.kh-smma-review-action', function () {
        if ($(this).is(':disabled')) {
            window.alert((khSmmaSponsorApproval.messages && khSmmaSponsorApproval.messages.complianceFailBlocked)
                ? khSmmaSponsorApproval.messages.complianceFailBlocked
                : 'Compliance failure detected. Variant must be edited and pass compliance before approval.');
            return;
        }
        const action = $(this).data('action-type');
        const id = String($(this).data('schedule-id') || '');
        openModal(action, id ? [id] : []);
    });

    $(document).on('click', '#kh-smma-bulk-approve', function () {
        if (!state.canManageApprovals) {
            window.alert(state.deniedMessage);
            return;
        }
        openModal('approve', selectedScheduleIds());
    });

    $(document).on('click', '#kh-smma-bulk-reject', function () {
        if (!state.canManageApprovals) {
            window.alert(state.deniedMessage);
            return;
        }
        openModal('reject', selectedScheduleIds());
    });

    $(document).on('click', '#kh-smma-review-cancel', function () {
        closeModal();
    });

    $(document).on('click', '#kh-smma-review-confirm', function () {
        const ids = state.scheduleIds.slice();
        const action = state.action;
        const notes = $('#kh-smma-review-notes').val() || '';

        if (!action || !ids.length) {
            closeModal();
            return;
        }

        submitDecision(action, ids, notes)
            .done(function (response) {
                if (response && response.error) {
                    window.alert(response.message || 'Unable to save approval decision.');
                    return;
                }
                closeModal();
                fetchRows();
                fetchApprovalHistory();
            })
            .fail(function (xhr) {
                const message = xhr && xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Unable to save approval decision.';
                window.alert(message);
            });
    });

    fetchApprovalHistory();
    applyPermissionGuardrails();
})(jQuery);

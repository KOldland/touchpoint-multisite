/**
 * KHM SEO Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initSEOAnalysis();
        initFormValidation();
        initTabs();
        initTooltips();
        initSeoAgentMetaBox();
        initSmmaWorkflow();
    });

    function initSeoAgentMetaBox() {
        var $runButton = $('#khm-seo-run-agent-btn');
        if (!$runButton.length) {
            return;
        }

        var state = {
            jobId: '',
            actions: []
        };

        $runButton.on('click', function(event) {
            event.preventDefault();

            if (!window.khmSeo || !khmSeo.seoAgent || !khmSeo.seoAgent.enabled) {
                setSeoAgentStatus((khmSeo && khmSeo.strings && khmSeo.strings.seoAgentError) || 'SEO Agent is not available.', 'error');
                return;
            }

            var postId = parseInt($('#post_ID').val(), 10) || 0;
            if (!postId) {
                setSeoAgentStatus('Missing post ID.', 'error');
                return;
            }

            setSeoAgentBusy($runButton, true);
            setSeoAgentStatus(khmSeo.strings.seoAgentRunning, 'info');
            $('#khm-seo-agent-actions').empty();
            $('#khm-seo-agent-preview').empty();

            seoAgentRequest('audit', {
                post_id: postId
            }).done(function(response) {
                if (response.status === 'queued' && response.job_id) {
                    state.jobId = response.job_id;
                    setSeoAgentStatus(khmSeo.strings.seoAgentQueued, 'info');
                    pollSeoAgentStatus(response.job_id, state, $runButton);
                    return;
                }

                state.jobId = response.job_id || '';
                renderSeoAgentActions(response, state);
                setSeoAgentBusy($runButton, false);
            }).fail(function(message) {
                setSeoAgentStatus(message || khmSeo.strings.seoAgentError, 'error');
                setSeoAgentBusy($runButton, false);
            });
        });
    }

    function pollSeoAgentStatus(jobId, state, $runButton) {
        var attempts = 0;
        var maxAttempts = 18;

        function tick() {
            attempts += 1;

            seoAgentRequest('audit/status?job_id=' + encodeURIComponent(jobId), null, 'GET')
                .done(function(response) {
                    if (response.status === 'completed' && response.llm_output) {
                        renderSeoAgentActions({
                            llm_output: response.llm_output,
                            status: 'completed'
                        }, state);
                        setSeoAgentBusy($runButton, false);
                        return;
                    }

                    if (attempts >= maxAttempts) {
                        setSeoAgentStatus('Audit timed out. Try again.', 'error');
                        setSeoAgentBusy($runButton, false);
                        return;
                    }

                    window.setTimeout(tick, 1500);
                })
                .fail(function(message) {
                    if (attempts >= maxAttempts) {
                        setSeoAgentStatus(message || khmSeo.strings.seoAgentError, 'error');
                        setSeoAgentBusy($runButton, false);
                        return;
                    }

                    window.setTimeout(tick, 1500);
                });
        }

        window.setTimeout(tick, 1500);
    }

    function renderSeoAgentActions(response, state) {
        var output = response && response.llm_output ? response.llm_output : {};
        var actions = Array.isArray(output.apply_actions) ? output.apply_actions : [];
        var $actionsRoot = $('#khm-seo-agent-actions');
        var $previewRoot = $('#khm-seo-agent-preview');
        var postId = parseInt($('#post_ID').val(), 10) || 0;

        state.actions = actions;
        $actionsRoot.empty();
        $previewRoot.empty();

        if (!actions.length) {
            setSeoAgentStatus(khmSeo.strings.seoAgentNoActions, 'warning');
            return;
        }

        var summary = output.summary || {};
        setSeoAgentStatus(
            (summary.issues_total || 0) + ' issues, ' + (summary.suggestions_total || actions.length) + ' suggestions returned.',
            'success'
        );

        var $list = $('<div class="khm-seo-agent-actions-list" style="margin-bottom:8px;"></div>');
        actions.forEach(function(action, index) {
            var label = action.action_type || ('Action ' + (index + 1));
            var id = 'khm-seo-agent-action-' + index;
            var $item = $('<p style="margin:4px 0;"></p>');
            var $checkbox = $('<input type="checkbox" checked="checked" />')
                .attr('id', id)
                .data('action-index', index);
            var $label = $('<label style="margin-left:6px;"></label>')
                .attr('for', id)
                .text(label);
            $item.append($checkbox).append($label);
            $list.append($item);
        });

        var $previewButton = $('<button type="button" class="button"></button>').text(khmSeo.strings.seoAgentPreview);
        var $applyButton = $('<button type="button" class="button button-primary" style="margin-left:8px;"></button>').text(khmSeo.strings.seoAgentApply);

        $previewButton.on('click', function() {
            var selected = collectSelectedSeoAgentActions(actions);
            if (!selected.length) {
                return;
            }

            $previewButton.prop('disabled', true);
            seoAgentRequest('preview', {
                post_id: postId,
                actions: selected
            }).done(function(previewResponse) {
                $previewRoot.html(previewResponse.preview_html || '<p>No preview available.</p>');
            }).fail(function(message) {
                $previewRoot.html('<p style="color:#b32d2e;">' + escapeHtml(message || khmSeo.strings.seoAgentError) + '</p>');
            }).always(function() {
                $previewButton.prop('disabled', false);
            });
        });

        $applyButton.on('click', function() {
            var selected = collectSelectedSeoAgentActions(actions);
            var idempotencyKey = (window.crypto && window.crypto.randomUUID)
                ? window.crypto.randomUUID()
                : ('seo-agent-' + Date.now());
            var includesSchemaChanges = hasSeoAgentActionType(selected, 'set_schema_config');

            if (!selected.length) {
                return;
            }

            if (includesSchemaChanges && !window.confirm(khmSeo.strings.seoAgentConfirmSchema)) {
                return;
            }

            $applyButton.prop('disabled', true).text('Applying...');

            seoAgentRequest('apply', {
                post_id: postId,
                actions: selected,
                job_id: state.jobId || 'manual-' + Date.now(),
                idempotency_key: idempotencyKey,
                confirm_schema_changes: includesSchemaChanges
            }).done(function(applyResponse) {
                syncSeoAgentFieldChanges(applyResponse && applyResponse.changes ? applyResponse.changes : []);
                setSeoAgentStatus(khmSeo.strings.seoAgentApplied, 'success');
            }).fail(function(message) {
                setSeoAgentStatus(message || khmSeo.strings.seoAgentError, 'error');
            }).always(function() {
                $applyButton.prop('disabled', false).text(khmSeo.strings.seoAgentApply);
            });
        });

        $actionsRoot.append($list).append($previewButton).append($applyButton);
    }

    function collectSelectedSeoAgentActions(actions) {
        var selected = [];

        $('#khm-seo-agent-actions input[type="checkbox"]').each(function() {
            if (this.checked) {
                var index = parseInt($(this).data('action-index'), 10);
                if (!isNaN(index) && actions[index]) {
                    selected.push(actions[index]);
                }
            }
        });

        return selected;
    }

    function hasSeoAgentActionType(actions, targetType) {
        return (actions || []).some(function(action) {
            return action && action.action_type === targetType;
        });
    }

    function syncSeoAgentFieldChanges(changes) {
        var selectors = {
            '_khm_seo_title': '#khm_seo_title',
            '_khm_seo_description': '#khm_seo_description',
            '_khm_seo_focus_keyword': '#khm_seo_focus_keyword',
            '_khm_seo_keywords': '#khm_seo_keywords',
            '_khm_seo_robots': '#khm_seo_robots',
            '_khm_seo_canonical': '#khm_seo_canonical'
        };

        (changes || []).forEach(function(change) {
            if (!change || !change.meta_key || !(change.meta_key in selectors)) {
                return;
            }

            var $field = $(selectors[change.meta_key]);
            if (!$field.length) {
                return;
            }

            var nextValue = typeof change.new === 'undefined' || change.new === null ? '' : change.new;
            $field.val(nextValue);
            $field.trigger('input');
            $field.trigger('change');
        });
    }

    function seoAgentRequest(path, payload, method) {
        var deferred = $.Deferred();
        var requestMethod = method || 'POST';
        var endpoint = (khmSeo.seoAgent.rest_url || '').replace(/\/$/, '') + '/' + path;

        $.ajax({
            url: endpoint,
            type: requestMethod,
            data: requestMethod === 'POST' ? JSON.stringify(payload || {}) : null,
            contentType: requestMethod === 'POST' ? 'application/json' : undefined,
            processData: false,
            headers: {
                'X-WP-Nonce': khmSeo.seoAgent.rest_nonce
            },
            success: function(response) {
                deferred.resolve(response);
            },
            error: function(xhr) {
                deferred.reject(extractSmmaError(xhr));
            }
        });

        return deferred.promise();
    }

    function setSeoAgentStatus(message, status) {
        $('#khm-seo-agent-status')
            .text(message || '')
            .css('color', resolveStatusColor(status || 'info'));
    }

    function setSeoAgentBusy($button, busy) {
        if (!$button || !$button.length) {
            return;
        }

        if (busy) {
            $button.data('original-text', $button.text());
            $button.prop('disabled', true).text('Running...');
            return;
        }

        $button.prop('disabled', false).text($button.data('original-text') || 'Run SEO Agent Audit');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initSmmaWorkflow() {
        $(document).on('click', '.khm-smma-promote-btn', handlePromoteClick);
        $(document).on('click', '.khm-smma-boost-btn', handleBoostClick);
        $(document).on('click', '.khm-smma-approve-btn', handleApproveClick);
        $(document).on('click', '.khm-smma-reject-btn', handleRejectClick);
        $(document).on('click', '.khm-smma-edit-variant-btn', handleEditVariantClick);
        $(document).on('click', '.khm-smma-preview-btn', handlePreviewClick);
    }

    function handlePromoteClick(event) {
        event.preventDefault();

        if (!isSmmaConfigured()) {
            return;
        }

        var $button = $(event.currentTarget);
        var $row = $button.closest('tr');

        setButtonBusy($button, true);
        showSmmaStatus($row, khmSeo.strings.smmaGenerating, 'info');

        ensureRowVariant($row)
            .done(function(variant) {
                showVariantPreview($row, variant.text || '');
                showSmmaStatus($row, khmSeo.strings.smmaGenerated, 'success');
            })
            .fail(function(message) {
                showSmmaStatus($row, message, 'error');
            })
            .always(function() {
                setButtonBusy($button, false);
            });
    }

    function handleBoostClick(event) {
        event.preventDefault();

        if (!isSmmaConfigured()) {
            return;
        }

        var $button = $(event.currentTarget);
        var $row = $button.closest('tr');
        var sponsorId = parseInt($button.data('sponsor-id'), 10) || 0;

        if (!sponsorId) {
            showSmmaStatus($row, khmSeo.strings.smmaSponsorRequired, 'error');
            return;
        }

        setButtonBusy($button, true);
        showSmmaStatus($row, khmSeo.strings.smmaScheduling, 'info');

        ensureRowVariant($row)
            .then(function(variant) {
                return createBoostSchedule($row, variant.variant_id, sponsorId);
            })
            .then(function(scheduleResponse) {
                return prepareBoostBundle($row, scheduleResponse.schedule_id).then(function(prepareResponse) {
                    return {
                        schedule: scheduleResponse,
                        prepare: prepareResponse
                    };
                });
            })
            .done(function(result) {
                if (result.prepare && result.prepare.status === 'blocked') {
                    showSmmaStatus($row, khmSeo.strings.smmaApprovalQueued, 'warning');
                    return;
                }

                showSmmaStatus($row, khmSeo.strings.smmaScheduleReady, 'success');
            })
            .fail(function(message) {
                showSmmaStatus($row, message, 'error');
            })
            .always(function() {
                setButtonBusy($button, false);
            });
    }

    function handleApproveClick(event) {
        event.preventDefault();

        if (!isSmmaConfigured()) {
            return;
        }

        var $button = $(event.currentTarget);
        var $row = $button.closest('tr');
        var scheduleId = parseInt($button.data('schedule-id'), 10) || 0;

        if (!scheduleId) {
            showSmmaStatus($row, 'Missing schedule identifier.', 'error');
            return;
        }

        setButtonBusy($button, true);

        smmaRequest('approve', {
            schedule_id: scheduleId
        }).done(function() {
            showSmmaStatus($row, khmSeo.strings.smmaApproved, 'success');
            $row.fadeOut(150, function() {
                $(this).remove();
                updatePendingApprovalsState();
            });
        }).fail(function(message) {
            showSmmaStatus($row, message, 'error');
        }).always(function() {
            setButtonBusy($button, false);
        });
    }

    function handleRejectClick(event) {
        event.preventDefault();

        if (!isSmmaConfigured()) {
            return;
        }

        var $button = $(event.currentTarget);
        var $row = $button.closest('tr');
        var scheduleId = parseInt($button.data('schedule-id'), 10) || 0;
        var reason = window.prompt(khmSeo.strings.smmaPromptReject, '');

        if (!scheduleId) {
            showSmmaStatus($row, 'Missing schedule identifier.', 'error');
            return;
        }

        if (reason === null) {
            return;
        }

        setButtonBusy($button, true);

        smmaRequest('reject', {
            schedule_id: scheduleId,
            reason: reason
        }).done(function() {
            showSmmaStatus($row, khmSeo.strings.smmaRejected, 'warning');
            $row.fadeOut(150, function() {
                $(this).remove();
                updatePendingApprovalsState();
            });
        }).fail(function(message) {
            showSmmaStatus($row, message, 'error');
        }).always(function() {
            setButtonBusy($button, false);
        });
    }

    function handleEditVariantClick(event) {
        event.preventDefault();

        if (!isSmmaConfigured()) {
            return;
        }

        var $button = $(event.currentTarget);
        var $row = $button.closest('tr');
        var scheduleId = parseInt($button.data('schedule-id'), 10) || 0;
        var currentText = String($button.data('variant-text') || '');
        var updatedText = window.prompt(khmSeo.strings.smmaPromptEdit, currentText);

        if (!scheduleId) {
            showSmmaStatus($row, 'Missing schedule identifier.', 'error');
            return;
        }

        if (updatedText === null || updatedText === currentText) {
            return;
        }

        setButtonBusy($button, true);

        smmaRequest('variant-edit', {
            schedule_id: scheduleId,
            updated_text: updatedText
        }).done(function(response) {
            $button.data('variant-text', updatedText);
            $row.find('.khm-smma-preview-btn').data('variant-text', updatedText);
            $row.find('td').eq(2).find('div').first().text(updatedText);
            showSmmaStatus($row, formatComplianceMessage(response.compliance, khmSeo.strings.smmaUpdated), 'success');
        }).fail(function(message) {
            showSmmaStatus($row, message, 'error');
        }).always(function() {
            setButtonBusy($button, false);
        });
    }

    function handlePreviewClick(event) {
        event.preventDefault();

        var $button = $(event.currentTarget);
        var text = String($button.data('variant-text') || $button.closest('td').find('div').first().text() || '');

        if (text) {
            window.alert(khmSeo.strings.smmaPromptPreview + '\n\n' + text);
        }
    }

    function isSmmaConfigured() {
        if (!window.khmSeo || !khmSeo.smma || !khmSeo.smma.rest_url || !khmSeo.smma.rest_nonce) {
            window.alert((khmSeo && khmSeo.strings && khmSeo.strings.smmaMissingDeps) || 'KH Social Manager is unavailable on this environment.');
            return false;
        }

        return true;
    }

    function ensureRowVariant($row) {
        var deferred = $.Deferred();
        var cachedVariantId = String($row.data('variant-id') || '');
        var cachedVariantText = String($row.data('variant-text') || '');

        if (cachedVariantId) {
            deferred.resolve({
                variant_id: cachedVariantId,
                text: cachedVariantText
            });
            return deferred.promise();
        }

        var $promoteButton = $row.find('.khm-smma-promote-btn').first();
        var sponsorId = parseInt($promoteButton.data('sponsor-id'), 10) || 0;
        var sponsorPolicy = String($promoteButton.data('sponsor-policy') || '');

        smmaRequest('generate', {
            post_id: parseInt($promoteButton.data('post-id'), 10) || 0,
            title: String($promoteButton.data('post-title') || ''),
            canonical_url: String($promoteButton.data('post-url') || ''),
            blocks_summary: String($promoteButton.data('post-summary') || $promoteButton.data('post-title') || ''),
            blocks_json: [],
            tone: 'Authority',
            num_variants: 1,
            series: false,
            generate_google_ads: false,
            phase_tag: String($promoteButton.data('phase') || 'Attention'),
            sponsor_context: sponsorId ? {
                sponsor_id: sponsorId,
                policy: sponsorPolicy
            } : {}
        }).done(function(response) {
            var firstVariant = response && response.variants && response.variants[0] ? response.variants[0] : null;
            var linkedIn = firstVariant && firstVariant.linkedIn ? firstVariant.linkedIn : {};
            var variantId = String(firstVariant && firstVariant.variant_id ? firstVariant.variant_id : '');
            var variantText = String(linkedIn && linkedIn.text ? linkedIn.text : '');

            if (!variantId) {
                deferred.reject('Variant generation returned no variant identifier.');
                return;
            }

            $row.data('variant-id', variantId);
            $row.data('variant-text', variantText);
            $row.find('.khm-smma-promote-btn, .khm-smma-boost-btn').data('variant-id', variantId);

            deferred.resolve({
                variant_id: variantId,
                text: variantText,
                raw: response
            });
        }).fail(function(message) {
            deferred.reject(message);
        });

        return deferred.promise();
    }

    function createBoostSchedule($row, variantId, sponsorId) {
        return smmaRequest('schedule', {
            variant_id: variantId,
            sponsor_id: String(sponsorId),
            schedule_time: new Date(Date.now() + 3600000).toISOString(),
            boost_options: {
                channels: [khmSeo.smma.default_channel || 'linkedin'],
                budget_cents: khmSeo.smma.default_budget_cents || 5000,
                currency: khmSeo.smma.default_currency || 'AUD'
            },
            mode: 'sandbox'
        }, {
            idempotencyKey: createIdempotencyKey('schedule', $row.find('.khm-smma-boost-btn').data('post-id'))
        }).done(function(response) {
            if (response && response.schedule_id) {
                $row.data('schedule-id', response.schedule_id);
            }
        });
    }

    function prepareBoostBundle($row, scheduleId) {
        return smmaRequest('boost/prepare', {
            schedule_id: scheduleId
        });
    }

    function smmaRequest(path, payload, options) {
        var deferred = $.Deferred();
        var requestOptions = options || {};

        $.ajax({
            url: khmSeo.smma.rest_url.replace(/\/$/, '') + '/' + path,
            type: 'POST',
            data: JSON.stringify(payload || {}),
            contentType: 'application/json',
            processData: false,
            headers: buildSmmaHeaders(requestOptions),
            success: function(response) {
                deferred.resolve(response);
            },
            error: function(xhr) {
                deferred.reject(extractSmmaError(xhr));
            }
        });

        return deferred.promise();
    }

    function buildSmmaHeaders(options) {
        var headers = {
            'X-WP-Nonce': khmSeo.smma.rest_nonce
        };

        if (options && options.idempotencyKey) {
            headers['Idempotency-Key'] = options.idempotencyKey;
        }

        return headers;
    }

    function createIdempotencyKey(action, seed) {
        return [
            'khm-seo',
            action,
            seed || '0',
            Date.now()
        ].join('-');
    }

    function extractSmmaError(xhr) {
        var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        if (response) {
            if (response.message) {
                return response.message;
            }
            if (response.code && response.data && response.data.message) {
                return response.data.message;
            }
        }

        return 'Request failed.';
    }

    function showVariantPreview($row, text) {
        var $cell = $row.find('td').last();
        var $preview = $cell.find('.khm-smma-inline-preview');

        if (!$preview.length) {
            $preview = $('<div class="khm-smma-inline-preview" style="margin-top:8px;font-size:12px;line-height:1.4;color:#50575e;"></div>');
            $cell.append($preview);
        }

        $preview.text(text);
    }

    function showSmmaStatus($row, message, status) {
        var $cell = $row.find('td').last();
        var $status = $cell.find('.khm-smma-status-message');

        if (!$status.length) {
            $status = $('<div class="khm-smma-status-message" style="margin-top:8px;font-size:12px;"></div>');
            $cell.append($status);
        }

        $status.text(message)
            .css('color', resolveStatusColor(status));
    }

    function resolveStatusColor(status) {
        switch (status) {
            case 'success':
                return '#1d6f42';
            case 'warning':
                return '#996800';
            case 'error':
                return '#b32d2e';
            default:
                return '#50575e';
        }
    }

    function formatComplianceMessage(compliance, fallback) {
        if (!compliance) {
            return fallback;
        }

        if (compliance.notes) {
            return fallback + ' ' + compliance.notes;
        }

        if (compliance.message) {
            return fallback + ' ' + compliance.message;
        }

        return fallback;
    }

    function setButtonBusy($button, busy) {
        if (!$button.length) {
            return;
        }

        if (busy) {
            $button.data('original-text', $button.text());
            $button.prop('disabled', true).text('Working...');
            return;
        }

        $button.prop('disabled', false).text($button.data('original-text') || $button.text());
    }

    function updatePendingApprovalsState() {
        var $heading = $('h2').filter(function() {
            return $(this).text().trim() === 'Pending Sponsor Approvals';
        }).first();

        if (!$heading.length) {
            return;
        }

        var $table = $heading.nextAll('table').first();
        if (!$table.length) {
            return;
        }

        if ($table.find('tbody tr:visible').length === 0) {
            $table.replaceWith('<p>No pending approvals.</p>');
        }
    }

    /**
     * Initialize SEO content analysis
     */
    function initSEOAnalysis() {
        // Real-time analysis on content change
        var analysisTimeout;
        
        $('#content, #title, #khm_seo_focus_keyword').on('input keyup', function() {
            clearTimeout(analysisTimeout);
            analysisTimeout = setTimeout(performAnalysis, 1000);
        });

        // Initial analysis
        if ($('#content').length) {
            performAnalysis();
        }
    }

    /**
     * Perform SEO analysis
     */
    function performAnalysis() {
        var content = getContent();
        var title = $('#title').val() || '';
        var focusKeyword = $('#khm_seo_focus_keyword').val() || '';

        if (!content && !title) {
            return;
        }

        showAnalysisLoading();

        $.ajax({
            url: khmSeo.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_seo_analyze_content',
                nonce: khmSeo.nonce,
                content: content,
                title: title,
                focus_keyword: focusKeyword
            },
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                }
            },
            error: function() {
                hideAnalysisLoading();
            }
        });
    }

    /**
     * Get content from editor
     */
    function getContent() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
            return tinyMCE.activeEditor.getContent();
        }
        return $('#content').val() || '';
    }

    /**
     * Show analysis loading indicator
     */
    function showAnalysisLoading() {
        var $container = getAnalysisContainer();
        $container.html('<div class="khm-seo-loading"></div> ' + khmSeo.strings.analyzing);
    }

    /**
     * Hide analysis loading indicator
     */
    function hideAnalysisLoading() {
        var $container = getAnalysisContainer();
        $container.empty();
    }

    /**
     * Get or create analysis container
     */
    function getAnalysisContainer() {
        var $container = $('#khm-seo-analysis');
        if (!$container.length) {
            $container = $('<div id="khm-seo-analysis" class="khm-seo-analysis"></div>');
            $('#khm-seo-meta-box').append($container);
        }
        return $container;
    }

    /**
     * Display analysis results
     */
    function displayAnalysisResults(data) {
        var $container = getAnalysisContainer();
        var html = '';

        // Score
        html += '<div class="khm-seo-score-container">';
        html += '<h4>SEO Score: <span class="khm-seo-score ' + data.status + '">' + data.score + '/100</span></h4>';
        html += '</div>';

        // Checks
        if (data.checks) {
            html += '<div class="khm-seo-checks">';
            for (var check in data.checks) {
                var checkData = data.checks[check];
                html += '<div class="khm-seo-check">';
                html += '<div class="khm-seo-check-icon ' + checkData.status + '">';
                html += getStatusIcon(checkData.status);
                html += '</div>';
                html += '<div class="khm-seo-check-message">' + checkData.message + '</div>';
                html += '</div>';
            }
            html += '</div>';
        }

        // Recommendations
        if (data.recommendations && data.recommendations.length > 0) {
            html += '<div class="khm-seo-recommendations">';
            html += '<h4>Recommendations</h4>';
            html += '<ul>';
            for (var i = 0; i < data.recommendations.length; i++) {
                html += '<li>' + data.recommendations[i] + '</li>';
            }
            html += '</ul>';
            html += '</div>';
        }

        $container.html(html);
    }

    /**
     * Get status icon
     */
    function getStatusIcon(status) {
        switch (status) {
            case 'good':
                return '✓';
            case 'needs_improvement':
                return '!';
            case 'poor':
                return '✗';
            default:
                return '?';
        }
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // Character count for title and description
        $('#khm_seo_title').on('input', function() {
            updateCharacterCount($(this), 60, 'title');
        });

        $('#khm_seo_description').on('input', function() {
            updateCharacterCount($(this), 160, 'description');
        });

        // Initial character count
        $('#khm_seo_title').trigger('input');
        $('#khm_seo_description').trigger('input');
    }

    /**
     * Update character count display
     */
    function updateCharacterCount($field, recommended, type) {
        var length = $field.val().length;
        var $counter = $field.siblings('.char-counter');
        
        if (!$counter.length) {
            $counter = $('<div class="char-counter"></div>');
            $field.after($counter);
        }

        var status = '';
        if (length > recommended + 10) {
            status = 'over';
        } else if (length < recommended - 10) {
            status = 'under';
        } else {
            status = 'good';
        }

        $counter.html(length + ' / ' + recommended + ' characters')
                .removeClass('under good over')
                .addClass(status);
    }

    /**
     * Initialize tabs functionality
     */
    function initTabs() {
        $('.khm-seo-tabs a').on('click', function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');

            // Update active tab
            $tab.closest('ul').find('a').removeClass('active');
            $tab.addClass('active');

            // Show target content
            $('.khm-seo-tab-content').hide();
            $(target).show();
        });

        // Show first tab by default
        $('.khm-seo-tabs a:first').trigger('click');
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('.khm-seo-help').hover(function() {
            var $help = $(this);
            var text = $help.data('help');
            
            if (text) {
                var $tooltip = $('<div class="khm-seo-tooltip">' + text + '</div>');
                $help.append($tooltip);
                
                // Position tooltip
                var helpPos = $help.position();
                $tooltip.css({
                    top: helpPos.top + $help.outerHeight() + 5,
                    left: helpPos.left
                });
            }
        }, function() {
            $(this).find('.khm-seo-tooltip').remove();
        });
    }

    /**
     * Social media preview functions
     */
    window.khmSeoPreview = {
        updateFacebookPreview: function() {
            var title = $('#khm_seo_og_title').val() || $('#khm_seo_title').val() || $('#title').val();
            var description = $('#khm_seo_og_description').val() || $('#khm_seo_description').val();
            var image = $('#khm_seo_og_image').val();

            $('.facebook-preview .preview-title').text(title);
            $('.facebook-preview .preview-description').text(description);
            
            if (image) {
                $('.facebook-preview .preview-image').attr('src', image).show();
            } else {
                $('.facebook-preview .preview-image').hide();
            }
        },

        updateTwitterPreview: function() {
            var title = $('#khm_seo_twitter_title').val() || $('#khm_seo_title').val() || $('#title').val();
            var description = $('#khm_seo_twitter_description').val() || $('#khm_seo_description').val();
            var image = $('#khm_seo_twitter_image').val();

            $('.twitter-preview .preview-title').text(title);
            $('.twitter-preview .preview-description').text(description);
            
            if (image) {
                $('.twitter-preview .preview-image').attr('src', image).show();
            } else {
                $('.twitter-preview .preview-image').hide();
            }
        }
    };

    // Auto-update social previews
    $('#khm_seo_title, #khm_seo_description, #khm_seo_og_title, #khm_seo_og_description, #khm_seo_og_image').on('input', function() {
        if (typeof window.khmSeoPreview !== 'undefined') {
            window.khmSeoPreview.updateFacebookPreview();
        }
    });

    $('#khm_seo_twitter_title, #khm_seo_twitter_description, #khm_seo_twitter_image').on('input', function() {
        if (typeof window.khmSeoPreview !== 'undefined') {
            window.khmSeoPreview.updateTwitterPreview();
        }
    });

})(jQuery);
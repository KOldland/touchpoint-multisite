(function($, window) {
    'use strict';

    var settings = window.khSMMASettings || {};
    var VariantGrid = window.KHSmmaVariantGrid || {};

    function uuidv4() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(char) {
            var random = Math.random() * 16 | 0;
            var value = char === 'x' ? random : (random & 0x3 | 0x8);
            return value.toString(16);
        });
    }

    function escapeHtml(value) {
        if (VariantGrid.escapeHtml) {
            return VariantGrid.escapeHtml(value);
        }

        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function requestErrorMessage(xhr, fallback) {
        var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        if (response && response.message) {
            return String(response.message);
        }
        if (response && response.error && response.error.message) {
            return String(response.error.message);
        }
        if (response && response.code === 'MBR_ERR_INVALID_PROMO') {
            return 'Invalid promotion code.';
        }
        return fallback || 'Request failed.';
    }

    function parseGeoTargets(rawValue) {
        return String(rawValue || '')
            .split(',')
            .map(function(item) { return item.trim(); })
            .filter(Boolean);
    }

    function normalizeVariant(entry) {
        if (VariantGrid.normalizeVariantEntry) {
            return VariantGrid.normalizeVariantEntry(entry);
        }

        return entry || {};
    }

    var SMMA_API = {
        baseUrl: settings.apiUrl || '/wp-json/kh-smma/v1',
        nonce: settings.nonce || '',

        request: function(path, options) {
            var headers = options.headers || {};
            headers['X-WP-Nonce'] = this.nonce;

            return $.ajax({
                url: this.baseUrl + path,
                method: options.method || 'GET',
                contentType: options.contentType || 'application/json',
                data: options.data ? JSON.stringify(options.data) : undefined,
                beforeSend: function(xhr) {
                    Object.keys(headers).forEach(function(key) {
                        xhr.setRequestHeader(key, headers[key]);
                    });
                }
            });
        },

        generate: function(payload) {
            return this.request('/generate', {
                method: 'POST',
                data: payload
            });
        },

        editVariant: function(variantId, payload) {
            return this.request('/variant/' + encodeURIComponent(variantId) + '/edit', {
                method: 'POST',
                headers: {
                    'Idempotency-Key': uuidv4(),
                    'X-Trace-Id': uuidv4()
                },
                data: payload
            });
        },

        scheduleVariant: function(payload) {
            return this.request('/schedule', {
                method: 'POST',
                headers: {
                    'Idempotency-Key': uuidv4(),
                    'X-Trace-Id': uuidv4()
                },
                data: payload
            });
        }
    };

    var ModalManager = {
        create: function(id, title, content, options) {
            options = options || {};
            var width = options.width || '960px';
            var modal = $(
                '<div id="' + id + '" class="khm-smma-modal" style="display:none;">' +
                    '<div class="khm-smma-modal-content" style="width:' + width + ';">' +
                        '<div class="khm-smma-modal-header">' +
                            '<h2>' + escapeHtml(title) + '</h2>' +
                            '<button type="button" class="khm-smma-modal-close" aria-label="Close">&times;</button>' +
                        '</div>' +
                        '<div class="khm-smma-modal-body">' + content + '</div>' +
                    '</div>' +
                '</div>'
            );

            $('body').append(modal);

            modal.find('.khm-smma-modal-close').on('click', function() {
                ModalManager.close(id);
            });

            modal.on('click', function(event) {
                if (event.target === modal[0]) {
                    ModalManager.close(id);
                }
            });

            return modal;
        },

        open: function(id) {
            $('#' + id).fadeIn(150);
        },

        close: function(id) {
            $('#' + id).fadeOut(150);
        },

        destroy: function(id) {
            $('#' + id).remove();
        }
    };

    var PromoteModal = {
        currentPostId: 0,
        currentPostTitle: '',
        currentPhase: 'Attention',
        currentSponsorId: '',
        generatedVariants: [],

        open: function(postId, postTitle, phase, blocksSummary, sponsorId) {
            this.currentPostId = parseInt(postId, 10) || 0;
            this.currentPostTitle = postTitle || '';
            this.currentPhase = phase || 'Attention';
            this.currentSponsorId = sponsorId || settings.defaultSponsorId || '';
            this.generatedVariants = [];

            var content = '' +
                '<div id="khm-promote-modal-container">' +
                    '<p class="description">Generate deterministic SMMA variants using the current post context.</p>' +
                    '<form id="khm-promote-form">' +
                        '<table class="form-table khm-smma-form-table">' +
                            '<tr>' +
                                '<th><label for="khm-blocks-summary">Blocks Summary</label></th>' +
                                '<td>' +
                                    '<textarea id="khm-blocks-summary" class="large-text" rows="5" required>' + escapeHtml(blocksSummary || postTitle || '') + '</textarea>' +
                                    '<p class="description">This is sent to `/generate` as `blocks_summary`.</p>' +
                                '</td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th><label for="khm-num-variants">Number of variants</label></th>' +
                                '<td><select id="khm-num-variants"><option value="1">1</option><option value="2">2</option><option value="3" selected>3</option><option value="4">4</option></select></td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th><label for="khm-tone">Tone</label></th>' +
                                '<td><select id="khm-tone"><option value="Authority" selected>Authority</option><option value="Friendly">Friendly</option><option value="Professional">Professional</option><option value="Conversational">Conversational</option></select></td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th><label for="khm-geo-targets">Geo targets</label></th>' +
                                '<td><input type="text" id="khm-geo-targets" class="regular-text" placeholder="AU, US" /></td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th><label for="khm-sponsor-id">Sponsor ID</label></th>' +
                                '<td><input type="text" id="khm-sponsor-id" class="regular-text" value="' + escapeHtml(this.currentSponsorId) + '" placeholder="Optional for generate, required for schedule" /></td>' +
                            '</tr>' +
                        '</table>' +
                        '<p><button type="submit" class="button button-primary button-large">Generate variants</button><span class="spinner"></span></p>' +
                        '<div class="khm-smma-feedback khm-smma-feedback-generate" aria-live="polite"></div>' +
                    '</form>' +
                    '<section id="khm-variants-container" hidden>' +
                        '<div class="khm-variants-header">' +
                            '<h3>Variant Grid</h3>' +
                            '<button type="button" id="khm-schedule-selected-btn" class="button button-primary">Schedule selected</button>' +
                        '</div>' +
                        '<div id="khm-variants-grid" class="khm-variants-grid"></div>' +
                    '</section>' +
                '</div>';

            if ($('#khm-promote-modal').length) {
                ModalManager.destroy('khm-promote-modal');
            }

            ModalManager.create('khm-promote-modal', 'Post-publish Generate', content, { width: '1080px' });
            ModalManager.open('khm-promote-modal');
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            $('#khm-promote-form').off('submit').on('submit', function(event) {
                self.handleGenerate(event);
            });

            $(document)
                .off('click.khmPromoteEdit', '.khm-smma-inline-edit-btn')
                .on('click.khmPromoteEdit', '.khm-smma-inline-edit-btn', function() {
                    self.toggleEditor($(this).closest('.khm-variant-card'), true);
                });

            $(document)
                .off('click.khmPromoteCancel', '.khm-smma-cancel-variant-btn')
                .on('click.khmPromoteCancel', '.khm-smma-cancel-variant-btn', function() {
                    self.toggleEditor($(this).closest('.khm-variant-card'), false);
                });

            $(document)
                .off('click.khmPromoteSave', '.khm-smma-save-variant-btn')
                .on('click.khmPromoteSave', '.khm-smma-save-variant-btn', function() {
                    self.saveVariant($(this).closest('.khm-variant-card'));
                });

            $(document)
                .off('click.khmPromoteScheduleSingle', '.khm-smma-inline-schedule-btn')
                .on('click.khmPromoteScheduleSingle', '.khm-smma-inline-schedule-btn', function() {
                    var variantId = $(this).data('variant-id');
                    var normalized = self.findVariant(variantId);
                    if (normalized) {
                        ScheduleModal.open([normalized], $('#khm-sponsor-id').val() || self.currentSponsorId);
                    }
                });

            $('#khm-schedule-selected-btn').off('click').on('click', function() {
                var selected = self.selectedVariants();
                if (!selected.length) {
                    self.showFeedback($('.khm-smma-feedback-generate'), 'Select at least one variant to schedule.', 'error');
                    return;
                }
                ScheduleModal.open(selected, $('#khm-sponsor-id').val() || self.currentSponsorId);
            });
        },

        handleGenerate: function(event) {
            var self = this;
            event.preventDefault();

            var $button = $('#khm-promote-form button[type="submit"]');
            var $spinner = $('#khm-promote-form .spinner');
            var $feedback = $('.khm-smma-feedback-generate');
            var blocksSummary = $('#khm-blocks-summary').val();

            this.showFeedback($feedback, '', '');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            SMMA_API.generate({
                post_id: this.currentPostId,
                blocks_summary: blocksSummary,
                num_variants: parseInt($('#khm-num-variants').val(), 10) || 1,
                tone: $('#khm-tone').val() || 'Authority',
                geo_targets: parseGeoTargets($('#khm-geo-targets').val()),
                sponsor_context: $('#khm-sponsor-id').val() ? { sponsor_id: String($('#khm-sponsor-id').val()) } : {},
                phase_tag: this.currentPhase
            }).done(function(response) {
                self.generatedVariants = response.variants || [];
                self.renderVariants();
                $('#khm-variants-container').prop('hidden', false);
                self.showFeedback($feedback, 'Generated ' + self.generatedVariants.length + ' variant(s).', 'success');
            }).fail(function(xhr) {
                self.showFeedback($feedback, requestErrorMessage(xhr, 'Error generating variants.'), 'error');
            }).always(function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },

        renderVariants: function() {
            var html = '';
            var self = this;

            this.generatedVariants.forEach(function(entry, index) {
                if (VariantGrid.renderVariantCard) {
                    html += VariantGrid.renderVariantCard(entry, index);
                    return;
                }

                var variant = normalizeVariant(entry);
                html += '<article class="khm-variant-card"><p>' + escapeHtml(variant.text) + '</p></article>';
            });

            $('#khm-variants-grid').html(html);

            this.generatedVariants.forEach(function(entry) {
                var variant = normalizeVariant(entry);
                var $card = $('.khm-variant-card[data-variant-id="' + variant.variantId + '"]');
                if ($card.length) {
                    $card.data('variantEntry', entry);
                }
            });
        },

        toggleEditor: function($card, open) {
            var $editor = $card.find('.khm-variant-editor');
            if (open) {
                $editor.prop('hidden', false);
                return;
            }

            $editor.prop('hidden', true);
            $editor.find('.khm-variant-editor__feedback').empty();
        },

        saveVariant: function($card) {
            var self = this;
            var variantId = String($card.data('variant-id') || '');
            var $editor = $card.find('.khm-variant-editor');
            var $spinner = $editor.find('.spinner');
            var $feedback = $editor.find('.khm-variant-editor__feedback');
            var text = $editor.find('.khm-variant-editor__textarea').val();
            var editReason = $editor.find('.khm-variant-editor__reason').val();

            this.showFeedback($feedback, '', '');
            $spinner.addClass('is-active');
            $editor.find('button').prop('disabled', true);

            SMMA_API.editVariant(variantId, {
                editor_user_id: '0',
                text: text,
                asset_hints: [],
                metadata: {},
                edit_reason: editReason
            }).done(function(response) {
                var entry = self.findRawVariant(variantId);
                var nextEntry = $.extend(true, {}, entry || {}, {
                    linkedIn: $.extend(true, {}, (entry && entry.linkedIn) || {}, {
                        variant_id: variantId,
                        text: text,
                        compliance_status: response.compliance && response.compliance.status ? response.compliance.status : 'OK',
                        compliance_reason: response.compliance && response.compliance.reasons ? response.compliance.reasons.join('; ') : '',
                        compliance: response.compliance || { status: 'OK', reasons: [] }
                    }),
                    approval_status: response.approval_status || ''
                });

                self.replaceVariant(variantId, nextEntry);
                self.renderVariants();
                self.showFeedback($('.khm-smma-feedback-generate'), 'Variant updated and compliance re-check completed.', 'success');
            }).fail(function(xhr) {
                self.showFeedback($feedback, requestErrorMessage(xhr, 'Error updating variant.'), 'error');
            }).always(function() {
                $spinner.removeClass('is-active');
                $editor.find('button').prop('disabled', false);
            });
        },

        selectedVariants: function() {
            var self = this;
            return $('.khm-variant-select:checked').map(function() {
                return self.findVariant($(this).data('variant-id'));
            }).get().filter(Boolean);
        },

        findRawVariant: function(variantId) {
            return this.generatedVariants.find(function(entry) {
                return normalizeVariant(entry).variantId === variantId;
            }) || null;
        },

        findVariant: function(variantId) {
            var entry = this.findRawVariant(variantId);
            return entry ? normalizeVariant(entry) : null;
        },

        replaceVariant: function(variantId, replacement) {
            this.generatedVariants = this.generatedVariants.map(function(entry) {
                return normalizeVariant(entry).variantId === variantId ? replacement : entry;
            });
        },

        showFeedback: function($el, message, kind) {
            if (!$el || !$el.length) {
                return;
            }

            $el.removeClass('is-error is-success');
            if (!message) {
                $el.empty();
                return;
            }

            $el
                .addClass(kind === 'error' ? 'is-error' : 'is-success')
                .text(message);
        }
    };

    var ScheduleModal = {
        variants: [],
        sponsorId: '',

        open: function(variants, sponsorId) {
            this.variants = variants || [];
            this.sponsorId = sponsorId || '';

            var rows = this.variants.map(function(variant, index) {
                var recommended = new Date(Date.now() + ((index + 1) * 3600 * 1000)).toISOString().slice(0, 16);
                return '' +
                    '<tr>' +
                        '<td>' + escapeHtml(variant.variantId) + '</td>' +
                        '<td>' + escapeHtml(variant.text.slice(0, 90)) + '</td>' +
                        '<td><input type="datetime-local" class="khm-schedule-time" data-variant-id="' + escapeHtml(variant.variantId) + '" value="' + escapeHtml(recommended) + '"></td>' +
                    '</tr>';
            }).join('');

            var content = '' +
                '<p class="description">Create sandbox schedules from generated variants.</p>' +
                '<table class="form-table khm-smma-form-table">' +
                    '<tr><th><label for="khm-schedule-sponsor-id">Sponsor ID</label></th><td><input type="text" id="khm-schedule-sponsor-id" class="regular-text" value="' + escapeHtml(this.sponsorId) + '" required></td></tr>' +
                    '<tr><th><label for="khm-schedule-budget">Budget (cents)</label></th><td><input type="number" id="khm-schedule-budget" class="regular-text" value="' + escapeHtml(settings.defaultBoostBudgetCents || 10000) + '" min="0"></td></tr>' +
                    '<tr><th><label for="khm-schedule-channel">Channel</label></th><td><select id="khm-schedule-channel"><option value="linkedin" selected>LinkedIn</option></select></td></tr>' +
                '</table>' +
                '<table class="widefat striped khm-schedule-table"><thead><tr><th>Variant</th><th>Preview</th><th>Schedule time</th></tr></thead><tbody>' + rows + '</tbody></table>' +
                '<p><button type="button" id="khm-confirm-schedule-btn" class="button button-primary">Create schedule(s)</button><span class="spinner"></span></p>' +
                '<div class="khm-smma-feedback khm-smma-feedback-schedule" aria-live="polite"></div>';

            if ($('#khm-schedule-modal').length) {
                ModalManager.destroy('khm-schedule-modal');
            }

            ModalManager.create('khm-schedule-modal', 'Schedule Variants', content, { width: '960px' });
            ModalManager.open('khm-schedule-modal');

            $('#khm-confirm-schedule-btn').off('click').on('click', this.submit.bind(this));
        },

        submit: function() {
            var sponsorId = $('#khm-schedule-sponsor-id').val();
            var budget = parseInt($('#khm-schedule-budget').val(), 10) || 0;
            var channel = $('#khm-schedule-channel').val() || 'linkedin';
            var $feedback = $('.khm-smma-feedback-schedule');
            var $spinner = $('#khm-schedule-modal .spinner');
            var requests = [];

            PromoteModal.showFeedback($feedback, '', '');

            if (!String(sponsorId || '').trim()) {
                PromoteModal.showFeedback($feedback, 'Sponsor ID is required for schedule creation.', 'error');
                return;
            }

            $spinner.addClass('is-active');
            $('#khm-confirm-schedule-btn').prop('disabled', true);

            this.variants.forEach(function(variant) {
                var scheduleTime = $('.khm-schedule-time[data-variant-id="' + variant.variantId + '"]').val();
                requests.push(
                    SMMA_API.scheduleVariant({
                        variant_id: variant.variantId,
                        sponsor_id: String(sponsorId),
                        schedule_time: new Date(scheduleTime).toISOString(),
                        boost_options: {
                            budget_cents: budget,
                            currency: settings.defaultCurrency || 'AUD',
                            channels: [channel],
                            prioritize: 'reach'
                        },
                        mode: 'sandbox'
                    })
                );
            });

            $.when.apply($, requests).done(function() {
                PromoteModal.showFeedback($feedback, 'Schedule request(s) created successfully.', 'success');
            }).fail(function(xhr) {
                PromoteModal.showFeedback($feedback, requestErrorMessage(xhr, 'Error creating schedules.'), 'error');
            }).always(function() {
                $spinner.removeClass('is-active');
                $('#khm-confirm-schedule-btn').prop('disabled', false);
            });
        }
    };

    $(document).ready(function() {
        $(document).on('click', '.khm-smma-promote-btn', function(event) {
            event.preventDefault();
            var $button = $(this);
            PromoteModal.open(
                $button.data('post-id'),
                $button.data('post-title'),
                $button.data('phase'),
                $button.data('blocks-summary'),
                $button.data('sponsor-id')
            );
        });
    });

    window.KHSmmaAdmin = {
        api: SMMA_API,
        promoteModal: PromoteModal,
        scheduleModal: ScheduleModal
    };
})(jQuery, window);

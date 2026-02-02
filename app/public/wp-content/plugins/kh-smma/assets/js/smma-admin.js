/**
 * KH-SMMA Admin UI - Frontend JavaScript
 * Handles promotion planning, variant generation, editing, and scheduling
 */

(function($) {
    'use strict';

    // API Client for SMMA REST endpoints
    const SMMA_API = {
        baseUrl: '/wp-json/kh-smma/v1',
        nonce: window.wpApiSettings?.nonce || '',

        /**
         * Generate promotional variants
         */
        generate: function(postId, options = {}) {
            return $.ajax({
                url: `${this.baseUrl}/generate`,
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', this.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    post_id: postId,
                    num_variants: options.numVariants || 1,
                    phase_tag: options.phaseTag || 'Attention',
                    tone: options.tone || 'Authority',
                    geo_targets: options.geoTargets || [],
                    sponsor_context: options.sponsorContext || {},
                    keywords: options.keywords || [],
                    series: options.series || false,
                }),
            });
        },

        /**
         * Schedule variants
         */
        schedule: function(postId, scheduleItems, options = {}) {
            return $.ajax({
                url: `${this.baseUrl}/schedule`,
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', this.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    post_id: postId,
                    schedule: scheduleItems,
                    boost: options.boost || false,
                    boost_settings: options.boostSettings || {},
                    sponsor_context: options.sponsorContext || {},
                }),
            });
        },

        /**
         * Edit variant text
         */
        editVariant: function(scheduleId, updatedText) {
            return $.ajax({
                url: `${this.baseUrl}/variant-edit`,
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', this.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    schedule_id: scheduleId,
                    updated_text: updatedText,
                }),
            });
        },

        /**
         * Approve variant
         */
        approve: function(scheduleId) {
            return $.ajax({
                url: `${this.baseUrl}/approve`,
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', this.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    schedule_id: scheduleId,
                }),
            });
        },

        /**
         * Reject variant
         */
        reject: function(scheduleId, reason = '') {
            return $.ajax({
                url: `${this.baseUrl}/reject`,
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', this.nonce),
                contentType: 'application/json',
                data: JSON.stringify({
                    schedule_id: scheduleId,
                    reason: reason,
                }),
            });
        },

        /**
         * Get sponsor details
         */
        getSponsor: function(sponsorId) {
            return $.ajax({
                url: `${this.baseUrl}/sponsor/${sponsorId}`,
                method: 'GET',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', this.nonce),
            });
        },
    };

    // Modal Manager
    const ModalManager = {
        /**
         * Create a modal element
         */
        create: function(id, title, content, options = {}) {
            const width = options.width || '800px';
            const modal = $(`
                <div id="${id}" class="khm-smma-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7);">
                    <div class="khm-smma-modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 0; border: 1px solid #888; width: ${width}; max-width: 95%; border-radius: 5px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);">
                        <div class="khm-smma-modal-header" style="padding: 15px 20px; background-color: #f1f1f1; border-bottom: 1px solid #ddd; border-radius: 5px 5px 0 0;">
                            <h2 style="margin: 0; font-size: 20px;">${title}</h2>
                            <span class="khm-smma-modal-close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; line-height: 20px; cursor: pointer; margin-top: -5px;">&times;</span>
                        </div>
                        <div class="khm-smma-modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
                            ${content}
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Close on X click
            modal.find('.khm-smma-modal-close').on('click', () => this.close(id));

            // Close on outside click
            modal.on('click', function(e) {
                if (e.target === this) {
                    ModalManager.close(id);
                }
            });

            return modal;
        },

        /**
         * Open modal
         */
        open: function(id) {
            $(`#${id}`).fadeIn(200);
        },

        /**
         * Close modal
         */
        close: function(id) {
            $(`#${id}`).fadeOut(200);
        },

        /**
         * Remove modal from DOM
         */
        destroy: function(id) {
            $(`#${id}`).remove();
        },
    };

    // Promote Modal
    const PromoteModal = {
        currentPostId: null,
        currentPostTitle: '',
        currentPhase: 'Attention',
        generatedVariants: [],

        open: function(postId, postTitle, phase) {
            this.currentPostId = postId;
            this.currentPostTitle = postTitle;
            this.currentPhase = phase;

            const content = `
                <div id="khm-promote-modal-container">
                    <h3>Generate Promotional Variants for: <strong>${postTitle}</strong></h3>
                    <p class="description">Create AI-powered promotional content optimized for the ${phase} phase.</p>

                    <form id="khm-promote-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="khm-num-variants">Number of Variants</label></th>
                                <td>
                                    <select id="khm-num-variants" name="num_variants">
                                        <option value="1">1 Variant</option>
                                        <option value="2">2 Variants</option>
                                        <option value="3" selected>3 Variants</option>
                                        <option value="5">5 Variants</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="khm-tone">Tone</label></th>
                                <td>
                                    <select id="khm-tone" name="tone">
                                        <option value="Authority" selected>Authority</option>
                                        <option value="Friendly">Friendly</option>
                                        <option value="Professional">Professional</option>
                                        <option value="Conversational">Conversational</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="khm-geo-targets">GEO Targets</label></th>
                                <td>
                                    <input type="text" id="khm-geo-targets" name="geo_targets" class="regular-text" placeholder="US-East, UK, etc. (comma separated)" />
                                    <p class="description">Optional: Target specific geographic regions</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="khm-series">Create Series</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="khm-series" name="series" />
                                        Generate a coordinated series of posts
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" class="button button-primary button-large">Generate Variants</button>
                            <span class="spinner" style="float: none; margin: 0 10px;"></span>
                        </p>
                    </form>

                    <div id="khm-variants-container" style="display: none; margin-top: 30px;">
                        <h3>Generated Variants</h3>
                        <div id="khm-variants-grid"></div>
                        <p style="margin-top: 20px;">
                            <button id="khm-schedule-variants-btn" class="button button-primary button-large">Schedule Selected Variants</button>
                        </p>
                    </div>
                </div>
            `;

            if ($('#khm-promote-modal').length) {
                ModalManager.destroy('khm-promote-modal');
            }

            ModalManager.create('khm-promote-modal', 'Promote Content', content, { width: '900px' });
            ModalManager.open('khm-promote-modal');

            this.bindEvents();
        },

        bindEvents: function() {
            $('#khm-promote-form').on('submit', (e) => this.handleGenerate(e));
            $(document).on('click', '#khm-schedule-variants-btn', () => this.handleSchedule());
        },

        handleGenerate: function(e) {
            e.preventDefault();

            const $form = $('#khm-promote-form');
            const $spinner = $form.find('.spinner');
            const $button = $form.find('button[type="submit"]');

            const numVariants = parseInt($('#khm-num-variants').val());
            const tone = $('#khm-tone').val();
            const geoTargets = $('#khm-geo-targets').val().split(',').map(s => s.trim()).filter(Boolean);
            const series = $('#khm-series').is(':checked');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            SMMA_API.generate(this.currentPostId, {
                numVariants: numVariants,
                phaseTag: this.currentPhase,
                tone: tone,
                geoTargets: geoTargets,
                series: series,
            }).done((response) => {
                this.generatedVariants = response.variants || [];
                this.renderVariants();
                $('#khm-variants-container').slideDown();
            }).fail((xhr) => {
                alert('Error generating variants: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }).always(() => {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },

        renderVariants: function() {
            const $grid = $('#khm-variants-grid');
            $grid.empty();

            this.generatedVariants.forEach((variant, index) => {
                const phaseColor = this.getPhaseColor(variant.phase_tag);
                const complianceColor = this.getComplianceColor(variant.compliance_notes);

                const card = $(`
                    <div class="khm-variant-card" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; background: #fff;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div>
                                <span class="khm-phase-badge" style="display: inline-block; padding: 4px 10px; border-radius: 3px; background-color: ${phaseColor}; color: #fff; font-size: 12px; font-weight: 600; margin-right: 8px;">
                                    ${variant.phase_tag}
                                </span>
                                <span class="khm-compliance-badge" style="display: inline-block; padding: 4px 8px; border-radius: 3px; background-color: ${complianceColor}; color: #fff; font-size: 11px;" title="${variant.compliance_notes}">
                                    ${variant.compliance_notes.indexOf('FAIL') !== -1 ? 'FAIL' : (variant.compliance_notes.indexOf('WARN') !== -1 ? 'WARN' : 'OK')}
                                </span>
                            </div>
                            <label style="margin: 0;">
                                <input type="checkbox" class="khm-variant-select" data-index="${index}" checked />
                                Select
                            </label>
                        </div>
                        <div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid ${phaseColor}; font-size: 14px; line-height: 1.6;">
                            ${this.escapeHtml(variant.text)}
                        </div>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; color: #0073aa; font-weight: 600;">View Details</summary>
                            <div style="margin-top: 10px; padding: 10px; background: #f1f1f1; border-radius: 3px; font-size: 13px;">
                                <p><strong>Tone:</strong> ${variant.tone}</p>
                                <p><strong>Explainability:</strong> ${variant.explainability}</p>
                                <p><strong>Recommended Time:</strong> ${variant.time_window}</p>
                                ${variant.geo_recommendations && variant.geo_recommendations.length ? `<p><strong>GEO Recommendations:</strong> ${variant.geo_recommendations.map(g => g.geo).join(', ')}</p>` : ''}
                            </div>
                        </details>
                    </div>
                `);

                $grid.append(card);
            });
        },

        handleSchedule: function() {
            const selectedIndices = [];
            $('.khm-variant-select:checked').each(function() {
                selectedIndices.push($(this).data('index'));
            });

            if (selectedIndices.length === 0) {
                alert('Please select at least one variant to schedule.');
                return;
            }

            // Open calendar modal for scheduling
            CalendarModal.open(this.currentPostId, selectedIndices.map(i => this.generatedVariants[i]));
        },

        getPhaseColor: function(phase) {
            const colors = {
                'Attention': '#0073aa',
                'Antagonistic': '#f0a000',
                'Anxiety': '#dc3232',
                'Acceptance': '#46b450',
            };
            return colors[phase] || '#0073aa';
        },

        getComplianceColor: function(notes) {
            if (notes.indexOf('FAIL') !== -1) return '#dc3232';
            if (notes.indexOf('WARN') !== -1) return '#f0a000';
            return '#46b450';
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };

    // Calendar Modal for Scheduling
    const CalendarModal = {
        currentPostId: null,
        variants: [],

        open: function(postId, variants) {
            this.currentPostId = postId;
            this.variants = variants;

            let variantRows = '';
            variants.forEach((variant, index) => {
                const scheduledTime = new Date(Date.now() + (index + 1) * 24 * 3600 * 1000).toISOString().slice(0, 16);
                variantRows += `
                    <tr>
                        <td style="padding: 10px;">
                            <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${this.escapeHtml(variant.text.substring(0, 60))}...
                            </div>
                        </td>
                        <td style="padding: 10px;">
                            <input type="datetime-local" class="khm-schedule-time" data-index="${index}" value="${scheduledTime}" />
                        </td>
                        <td style="padding: 10px;">
                            <input type="text" class="khm-schedule-geo" data-index="${index}" value="${variant.geo_recommendations?.[0]?.geo || ''}" placeholder="US-East, UK, etc." />
                        </td>
                    </tr>
                `;
            });

            const content = `
                <h3>Schedule Variants</h3>
                <p class="description">Set publish times and geographic targets for each variant.</p>

                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>Variant Text</th>
                            <th>Scheduled Time</th>
                            <th>GEO Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${variantRows}
                    </tbody>
                </table>

                <p style="margin-top: 20px;">
                    <label>
                        <input type="checkbox" id="khm-enable-boost" />
                        <strong>Enable LinkedIn Boost</strong> (requires additional approval)
                    </label>
                </p>

                <p style="margin-top: 20px;">
                    <button id="khm-confirm-schedule-btn" class="button button-primary button-large">Confirm & Schedule</button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            `;

            if ($('#khm-calendar-modal').length) {
                ModalManager.destroy('khm-calendar-modal');
            }

            ModalManager.create('khm-calendar-modal', 'Schedule Variants', content, { width: '900px' });
            ModalManager.open('khm-calendar-modal');

            $('#khm-confirm-schedule-btn').on('click', () => this.handleConfirm());
        },

        handleConfirm: function() {
            const scheduleItems = [];

            $('.khm-schedule-time').each(function() {
                const index = $(this).data('index');
                const scheduledAt = new Date($(this).val()).getTime() / 1000;
                const geo = $(`.khm-schedule-geo[data-index="${index}"]`).val();
                const variant = CalendarModal.variants[index];

                scheduleItems.push({
                    variant_id: variant.variant_id,
                    scheduled_at: scheduledAt,
                    geo: geo,
                    text: variant.text,
                });
            });

            const boost = $('#khm-enable-boost').is(':checked');
            const $button = $('#khm-confirm-schedule-btn');
            const $spinner = $('#khm-calendar-modal').find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            SMMA_API.schedule(this.currentPostId, scheduleItems, { boost: boost }).done((response) => {
                alert('Variants scheduled successfully!');
                ModalManager.close('khm-calendar-modal');
                ModalManager.close('khm-promote-modal');
                location.reload();
            }).fail((xhr) => {
                alert('Error scheduling variants: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }).always(() => {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };

    // Edit Variant Modal
    const EditVariantModal = {
        currentScheduleId: null,

        open: function(scheduleId, currentText) {
            this.currentScheduleId = scheduleId;

            const content = `
                <h3>Edit Variant Text</h3>
                <p class="description">Modify the variant text. Compliance will be re-checked upon saving.</p>

                <textarea id="khm-edit-variant-text" rows="10" style="width: 100%; padding: 10px; font-size: 14px; font-family: monospace;">${this.escapeHtml(currentText)}</textarea>

                <p style="margin-top: 20px;">
                    <button id="khm-save-variant-btn" class="button button-primary">Save Changes</button>
                    <button id="khm-cancel-edit-btn" class="button">Cancel</button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>

                <div id="khm-compliance-result" style="margin-top: 20px; display: none;"></div>
            `;

            if ($('#khm-edit-variant-modal').length) {
                ModalManager.destroy('khm-edit-variant-modal');
            }

            ModalManager.create('khm-edit-variant-modal', 'Edit Variant', content, { width: '700px' });
            ModalManager.open('khm-edit-variant-modal');

            $('#khm-save-variant-btn').on('click', () => this.handleSave());
            $('#khm-cancel-edit-btn').on('click', () => ModalManager.close('khm-edit-variant-modal'));
        },

        handleSave: function() {
            const updatedText = $('#khm-edit-variant-text').val();
            const $button = $('#khm-save-variant-btn');
            const $spinner = $('#khm-edit-variant-modal').find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            SMMA_API.editVariant(this.currentScheduleId, updatedText).done((response) => {
                const compliance = response.compliance || {};
                const $result = $('#khm-compliance-result');

                if (compliance.passed) {
                    $result.html('<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;"><strong>✓ Compliance Passed</strong><br />Variant updated successfully.</div>').show();
                    setTimeout(() => {
                        ModalManager.close('khm-edit-variant-modal');
                        location.reload();
                    }, 1500);
                } else {
                    $result.html(`<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;"><strong>✗ Compliance Failed</strong><br />${compliance.message}</div>`).show();
                    $button.prop('disabled', false);
                }
            }).fail((xhr) => {
                alert('Error updating variant: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $button.prop('disabled', false);
            }).always(() => {
                $spinner.removeClass('is-active');
            });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    };

    // Reject Modal
    const RejectModal = {
        open: function(scheduleId) {
            const content = `
                <h3>Reject Variant</h3>
                <p class="description">Provide a reason for rejecting this variant (optional).</p>

                <textarea id="khm-reject-reason" rows="5" style="width: 100%; padding: 10px;" placeholder="Reason for rejection..."></textarea>

                <p style="margin-top: 20px;">
                    <button id="khm-confirm-reject-btn" class="button button-primary">Confirm Rejection</button>
                    <button id="khm-cancel-reject-btn" class="button">Cancel</button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            `;

            if ($('#khm-reject-modal').length) {
                ModalManager.destroy('khm-reject-modal');
            }

            ModalManager.create('khm-reject-modal', 'Reject Variant', content, { width: '600px' });
            ModalManager.open('khm-reject-modal');

            $('#khm-confirm-reject-btn').on('click', () => this.handleConfirm(scheduleId));
            $('#khm-cancel-reject-btn').on('click', () => ModalManager.close('khm-reject-modal'));
        },

        handleConfirm: function(scheduleId) {
            const reason = $('#khm-reject-reason').val();
            const $button = $('#khm-confirm-reject-btn');
            const $spinner = $('#khm-reject-modal').find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            SMMA_API.reject(scheduleId, reason).done(() => {
                alert('Variant rejected successfully.');
                ModalManager.close('khm-reject-modal');
                location.reload();
            }).fail((xhr) => {
                alert('Error rejecting variant: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }).always(() => {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Promote button click
        $(document).on('click', '.khm-smma-promote-btn', function() {
            const postId = $(this).data('post-id');
            const postTitle = $(this).data('post-title');
            const phase = $(this).data('phase');
            PromoteModal.open(postId, postTitle, phase);
        });

        // Edit variant button click
        $(document).on('click', '.khm-smma-edit-variant-btn', function() {
            const scheduleId = $(this).data('schedule-id');
            const variantText = $(this).data('variant-text');
            EditVariantModal.open(scheduleId, variantText);
        });

        // Approve button click
        $(document).on('click', '.khm-smma-approve-btn', function() {
            const scheduleId = $(this).data('schedule-id');
            if (confirm('Approve this variant for publishing?')) {
                SMMA_API.approve(scheduleId).done(() => {
                    alert('Variant approved successfully.');
                    location.reload();
                }).fail((xhr) => {
                    alert('Error approving variant: ' + (xhr.responseJSON?.message || 'Unknown error'));
                });
            }
        });

        // Reject button click
        $(document).on('click', '.khm-smma-reject-btn', function() {
            const scheduleId = $(this).data('schedule-id');
            RejectModal.open(scheduleId);
        });

        // Boost button click (simplified quick boost)
        $(document).on('click', '.khm-smma-boost-btn', function() {
            const postId = $(this).data('post-id');
            alert('Quick Boost feature coming soon. Use Promote → Enable Boost for now.');
        });
    });

})(jQuery);

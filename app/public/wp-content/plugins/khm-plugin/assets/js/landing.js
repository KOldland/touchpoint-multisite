http://touchpoint-multisite.local/wp-admin/admin-post.php?action=khm_qbo_connect&_wpnonce=537a544834(function (root, helpers) {
    'use strict';

    var MAX_POLL_ATTEMPTS = 5;

    function uuidv4() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
            var random = Math.random() * 16 | 0;
            var value = char === 'x' ? random : (random & 0x3 | 0x8);
            return value.toString(16);
        });
    }

    helpers = helpers || {};

    function text(el) {
        return helpers.text ? helpers.text(el ? el.value : '') : (el ? String(el.value || '').trim() : '');
    }

    function getErrorMessage(payload) {
        if (!payload || typeof payload !== 'object') {
            return 'Unable to start checkout. Please try again.';
        }

        if (payload.error && payload.error.message) {
            return String(payload.error.message);
        }

        if (payload.message) {
            return String(payload.message);
        }

        return 'Unable to start checkout. Please try again.';
    }

    function clearFeedback(statusEl, errorEl) {
        if (statusEl) {
            statusEl.textContent = '';
        }
        if (errorEl) {
            errorEl.textContent = '';
        }
    }

    function postTelemetry(endpoint, payload) {
        if (!endpoint) {
            return;
        }

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).catch(function () {
            return null;
        });
    }

    function createLiveRegion() {
        var live = document.querySelector('.khm-success-live');
        if (!live) {
            return null;
        }
        live.setAttribute('aria-live', 'polite');
        return live;
    }

    function describeAttribution(payload) {
        if (!payload || !payload.consent || !payload.attribution) {
            return '';
        }

        var source = payload.attribution.utm_source || '';
        var campaign = payload.attribution.utm_campaign || '';
        if (!source && !campaign) {
            return '';
        }

        if (source && campaign) {
            return 'Referred by ' + source + ' (' + campaign + ')';
        }

        return 'Referred by ' + (source || campaign);
    }

    function buildSponsorBlock(payload) {
        if (!payload || !payload.sponsor || !payload.consent) {
            return '';
        }

        var sponsor = payload.sponsor;
        var accent = sponsor.accent_color || '#005a9c';
        var html = '<div class="khm-success-sponsor" style="border-left:4px solid ' + accent + ';padding-left:10px;">';
        if (sponsor.logo_url) {
            html += '<img src="' + String(sponsor.logo_url) + '" alt="' + String(sponsor.name || 'Sponsor') + ' logo" style="max-width:120px;height:auto;">';
        }
        if (sponsor.name) {
            html += '<p><strong>' + String(sponsor.name) + '</strong></p>';
        }
        if (sponsor.blurb) {
            html += '<div>' + String(sponsor.blurb) + '</div>';
        }
        html += '</div>';
        return html;
    }

    function statusMessage(payload) {
        if (payload.message) {
            return String(payload.message);
        }
        if (payload.status === 'pending') {
            return 'Your payment is processing.';
        }
        if (payload.status === 'failed') {
            return 'We could not confirm your membership yet.';
        }
        return 'Your membership is ready.';
    }

    function renderCtas(actionsEl, payload, telemetryEndpoint) {
        if (!actionsEl || !payload || !Array.isArray(payload.ctas)) {
            return;
        }

        actionsEl.innerHTML = '';
        payload.ctas.forEach(function (cta, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'khm-cta' + (idx === 0 ? ' khm-cta--primary' : '');
            btn.textContent = String(cta.name || 'Continue');
            btn.setAttribute('data-cta-name', String(cta.name || ''));
            btn.setAttribute('data-cta-action', String(cta.action || 'external'));
            btn.addEventListener('click', function () {
                postTelemetry(telemetryEndpoint, {
                    metric: 'landing.cta.clicked',
                    session_id: payload.session_id || '',
                    cta_name: String(cta.name || ''),
                    cta_action: String(cta.action || 'external')
                });

                if (cta.url) {
                    window.location.assign(String(cta.url));
                }
            });
            actionsEl.appendChild(btn);
        });
    }

    function renderSuccessIntoContainer(container, payload, telemetryEndpoint) {
        var contentEl = container.querySelector('.khm-success-content');
        var actionsEl = container.querySelector('.khm-success-actions');
        var liveEl = createLiveRegion();
        var successContent = helpers.buildSuccessContent ? helpers.buildSuccessContent(payload) : {
            headline: 'Membership confirmation',
            body: '',
            showAttribution: !!(payload && payload.consent)
        };

        var schedule = payload.schedule || {};
        var body = '';
        body += '<p>' + String(successContent.body || '') + '</p>';
        if (payload.consent) {
            body += '<p><strong>Status:</strong> ' + String(payload.membership_status || 'pending') + '</p>';
            body += '<p><strong>Schedule:</strong> ' + String(schedule.title || 'Membership') + '</p>';
            if (schedule.recommended_post_time) {
                body += '<p><strong>Recommended post time:</strong> ' + String(schedule.recommended_post_time) + '</p>';
            }
            if (schedule.boost_copy) {
                body += '<p>' + String(schedule.boost_copy) + '</p>';
            }
            body += buildSponsorBlock(payload);
            var attributionLine = describeAttribution(payload);
            if (attributionLine) {
                body += '<p class="khm-success-referred">' + attributionLine + '</p>';
            }
        } else if (payload.ctas && payload.ctas[0] && payload.ctas[0].url) {
            body += '<p><a href="' + String(payload.ctas[0].url) + '">Open membership details</a></p>';
        }

        if (contentEl) {
            contentEl.innerHTML = body;
        }

        renderCtas(actionsEl, payload, telemetryEndpoint);
        if (liveEl) {
            liveEl.textContent = statusMessage(payload);
        }
    }

    function trapFocus(modal, closeButton, initialFocus, triggerEl) {
        var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) {
            return function () {};
        }

        var first = initialFocus || focusable[0];
        var last = focusable[focusable.length - 1];
        first.focus();

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                closeButton.click();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        modal.addEventListener('keydown', onKeyDown);
        return function cleanup() {
            modal.removeEventListener('keydown', onKeyDown);
            if (triggerEl && typeof triggerEl.focus === 'function') {
                triggerEl.focus();
            }
        };
    }

    function showSuccessModal(payload, telemetryEndpoint, triggerEl) {
        var backdrop = document.createElement('div');
        backdrop.className = 'khm-success-modal-backdrop';

        var modal = document.createElement('div');
        modal.className = 'khm-success-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'khm-success-modal-title');
        modal.setAttribute('aria-describedby', 'khm-success-modal-desc');

        modal.innerHTML =
            '<button type="button" class="khm-success-modal-close" aria-label="Close success dialog">Close</button>' +
            '<h2 id="khm-success-modal-title">Membership confirmation</h2>' +
            '<p id="khm-success-modal-desc"></p>' +
            '<div class="khm-success-content" role="region"></div>' +
            '<div class="khm-success-actions"></div>' +
            '<div class="khm-success-live" aria-live="polite"></div>';

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);

        var closeButton = modal.querySelector('.khm-success-modal-close');
        var descEl = modal.querySelector('#khm-success-modal-desc');
        if (descEl) {
            descEl.textContent = statusMessage(payload);
        }

        renderSuccessIntoContainer(modal, payload, telemetryEndpoint);

        var primary = modal.querySelector('.khm-cta--primary');
        var cleanupFocus = trapFocus(modal, closeButton, primary || closeButton, triggerEl);

        function closeModal() {
            cleanupFocus();
            modal.remove();
            backdrop.remove();
        }

        closeButton.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);
    }

    function fetchSuccessPayload(endpoint, sessionId) {
        var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'session_id=' + encodeURIComponent(sessionId);
        return fetch(url, { credentials: 'same-origin' }).then(function (response) {
            if (!response.ok) {
                throw new Error('failed');
            }
            return response.json();
        });
    }

    function pollLandingSuccess(endpoint, sessionId, attempt) {
        return fetchSuccessPayload(endpoint, sessionId).then(function (payload) {
            if (payload.status !== 'pending' || attempt >= MAX_POLL_ATTEMPTS) {
                return payload;
            }

            var delay = Math.min(1000 * Math.pow(2, attempt), 8000);
            return new Promise(function (resolve) {
                setTimeout(function () {
                    resolve(pollLandingSuccess(endpoint, sessionId, attempt + 1));
                }, delay);
            });
        });
    }

    function renderFallbackSuccess(container, supportText, supportCode) {
        var fallback = container.querySelector('.khm-success-fallback');
        var codeEl = container.querySelector('.khm-support-code');
        if (fallback) {
            fallback.hidden = false;
        }
        if (codeEl) {
            codeEl.textContent = ' Support code: ' + supportCode;
        }
        var content = container.querySelector('.khm-success-content');
        if (content) {
            content.innerHTML = '<p>Membership request received. Please contact support at ' + supportText + '.</p>';
        }
    }

    function bootstrapSuccessView(triggerEl) {
        var successContainer = document.querySelector('.khm-success-page');
        var sessionInput = successContainer ? successContainer.getAttribute('data-session-id') : '';
        var endpoint = successContainer ? successContainer.getAttribute('data-success-endpoint') : '/wp-json/kh-membership/v1/landing-success';
        var telemetryEndpoint = successContainer ? successContainer.getAttribute('data-telemetry-endpoint') : '/wp-json/kh-membership/v1/landing-telemetry';

        var params = new URLSearchParams(window.location.search || '');
        var sessionId = sessionInput || params.get('session_id') || '';
        if (!sessionId) {
            return;
        }

        pollLandingSuccess(endpoint, sessionId, 0)
            .then(function (payload) {
                postTelemetry(telemetryEndpoint, {
                    metric: 'landing.success',
                    session_id: payload.session_id || sessionId,
                    cta_name: '',
                    cta_action: ''
                });

                if (successContainer) {
                    renderSuccessIntoContainer(successContainer, payload, telemetryEndpoint);
                    var printBtn = successContainer.querySelector('.khm-success-print-btn');
                    if (printBtn) {
                        printBtn.addEventListener('click', function () {
                            postTelemetry(telemetryEndpoint, {
                                metric: 'landing.cta.clicked',
                                session_id: payload.session_id || sessionId,
                                cta_name: 'Print / Save as PDF',
                                cta_action: 'external'
                            });
                            window.print();
                        });
                    }
                    return;
                }

                showSuccessModal(payload, telemetryEndpoint, triggerEl || null);
            })
            .catch(function () {
                if (successContainer) {
                    var supportText = successContainer.getAttribute('data-support-contact') || 'support';
                    renderFallbackSuccess(successContainer, supportText, 'LS-' + sessionId.slice(-8));
                }
            });
    }

    function onLandingCtaClick(event) {
        var button = event.currentTarget;
        var form = button.closest('.khm-landing-form');
        if (!form) {
            return;
        }

        var statusEl = form.querySelector('.khm-landing-status');
        var errorEl = form.querySelector('.khm-landing-error');
        clearFeedback(statusEl, errorEl);

        var scheduleInput = form.querySelector('input[name="schedule_id"]');
        var sponsorInput = form.querySelector('input[name="sponsor_id"]');
        var sourceInput = form.querySelector('input[name="utm_source"]');
        var mediumInput = form.querySelector('input[name="utm_medium"]');
        var campaignInput = form.querySelector('input[name="utm_campaign"]');
        var phaseInput = form.querySelector('input[name="phase_at_click"]');
        var endpointInput = form.querySelector('input[name="signup_init_endpoint"]');
        var consentInput = form.querySelector('input[name="consent"]');
        var promoInput = form.querySelector('input[name="promo_code"]');
        var marketingInput = form.querySelector('input[name="profile_marketing_optin"]');

        var scheduleId = text(scheduleInput);
        if (!scheduleId) {
            if (errorEl) {
                errorEl.textContent = 'Missing schedule configuration.';
            }
            return;
        }

        var consent = !!(consentInput && consentInput.checked);
        var payload = helpers.buildLandingPayload
            ? helpers.buildLandingPayload({
                schedule_id: scheduleId,
                sponsor_id: text(sponsorInput),
                utm_source: text(sourceInput),
                utm_medium: text(mediumInput),
                utm_campaign: text(campaignInput),
                phase_at_click: text(phaseInput),
                idempotency_key: uuidv4(),
                consent: consent,
                client_reference: button.getAttribute('data-action') || null,
                plan_id: button.getAttribute('data-plan-id') || null,
                promo_code: text(promoInput),
                profile_marketing_optin: !!(marketingInput && marketingInput.checked)
            })
            : {
                schedule_id: scheduleId,
                sponsor_id: text(sponsorInput) || null,
                utm_source: consent ? (text(sourceInput) || null) : null,
                utm_medium: consent ? (text(mediumInput) || null) : null,
                utm_campaign: consent ? (text(campaignInput) || null) : null,
                phase_at_click: consent ? (text(phaseInput) || null) : null,
                idempotency_key: uuidv4(),
                consent: consent,
                client_reference: button.getAttribute('data-action') || null,
                plan_id: button.getAttribute('data-plan-id') || null,
                promo_code: text(promoInput) || null,
                profile_marketing_optin: !!(marketingInput && marketingInput.checked)
            };

        var endpoint = text(endpointInput) || '/wp-json/kh-membership/v1/signup-init';
        button.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Creating checkout session...';
        }

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    return { status: response.status, body: body };
                });
            })
            .then(function (result) {
                var body = result.body || {};
                if (result.status >= 200 && result.status < 300 && body.checkout_url) {
                    if (statusEl) {
                        statusEl.textContent = 'Redirecting to secure checkout...';
                    }

                    if (button.getAttribute('data-success-mode') === 'modal' && body.session_id) {
                        var successEndpoint = '/wp-json/kh-membership/v1/landing-success';
                        pollLandingSuccess(successEndpoint, String(body.session_id), 0).then(function (payload) {
                            showSuccessModal(payload, '/wp-json/kh-membership/v1/landing-telemetry', button);
                        });
                        return;
                    }

                    window.location.assign(String(body.checkout_url));
                    return;
                }

                if (errorEl) {
                    errorEl.textContent = helpers.getPromoErrorMessage
                        ? helpers.getPromoErrorMessage(body, getErrorMessage(body))
                        : getErrorMessage(body);
                }
            })
            .catch(function () {
                if (errorEl) {
                    errorEl.textContent = 'Network error while creating checkout session.';
                }
            })
            .finally(function () {
                button.disabled = false;
                if (statusEl && !statusEl.textContent) {
                    statusEl.textContent = '';
                }
            });
    }

    function init() {
        var buttons = document.querySelectorAll('.khm-landing-cta');
        buttons.forEach(function (button) {
            button.addEventListener('click', onLandingCtaClick);
        });

        bootstrapSuccessView(null);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, window.KHMCheckoutUiHelpers || {});

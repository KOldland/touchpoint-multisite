(function () {
    'use strict';

    const cfg = window.khmConnectDirectory || {};

    const ICON_SAVE = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
    const ICON_INTRO = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22,4 12,13 2,4"/></svg>';

    // Solution type icons (Feather/Lucide stroke style)
    const ICON_SOFTWARE    = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    const ICON_HARDWARE    = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1"/><path d="M7 2v3M17 2v3M7 19v3M17 19v3M2 7h3M19 7h3M2 17h3M19 17h3"/></svg>';
    const ICON_CONSULTANCY = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

    var SOLUTION_TYPE_META = {
        software:    { icon: ICON_SOFTWARE,    tooltip: 'Software — applications & platforms',              cls: 'software' },
        hardware:    { icon: ICON_HARDWARE,    tooltip: 'Hardware — physical devices & infrastructure',     cls: 'hardware' },
        consultancy: { icon: ICON_CONSULTANCY, tooltip: 'Consultancy — advisory & professional services', cls: 'consultancy' }
    };

    var PROBLEM_COPY_BY_EXPERTISE = {
        pricing: {
            help: 'Start typing to find your pricing challenge, or scroll the list below.',
            placeholder: 'e.g. margin leakage, price waterfall visibility...'
        },
        aftermarket: {
            help: 'Start typing to find your aftermarket challenge, or scroll the list below.',
            placeholder: 'e.g. spare parts profitability, warranty recovery...'
        },
        'field-service': {
            help: 'Start typing to find your field service challenge, or scroll the list below.',
            placeholder: 'e.g. first-time fix rate, scheduling and dispatch...'
        },
        'spare-parts': {
            help: 'Start typing to find your spare parts challenge, or scroll the list below.',
            placeholder: 'e.g. stockouts, parts forecasting, fill rate...'
        },
        ecommerce: {
            help: 'Start typing to find your eCommerce challenge, or scroll the list below.',
            placeholder: 'e.g. conversion rate, cart abandonment, B2B checkout...'
        }
    };

    var DEFAULT_PROBLEM_COPY = {
        help: 'Start typing to find your challenge, or scroll the list below.',
        placeholder: 'e.g. pipeline visibility, lead routing...'
    };

    function selectedValues(root, selector) {
        const el = root.querySelector(selector);
        if (!el || !el.options) return [];
        return Array.from(el.selectedOptions).map(function (o) { return o.value; }).filter(Boolean);
    }

    function selectedOptionLabels(root, selector) {
        const el = root.querySelector(selector);
        if (!el || !el.options) return [];
        return Array.from(el.selectedOptions).map(function (o) {
            return (o.textContent || o.value || '').trim();
        }).filter(Boolean);
    }

    function pickerValue(root, pickerName) {
        const checked = root.querySelector('[data-picker="' + pickerName + '"] input:checked');
        return checked ? checked.value : '';
    }

    function pickerCheckedValues(root, pickerName) {
        return Array.from(
            root.querySelectorAll('[data-picker="' + pickerName + '"] input:checked')
        ).map(function (el) { return el.value; }).filter(Boolean);
    }

    function inputDisplayLabel(inputEl) {
        if (!(inputEl instanceof Element)) return '';
        var label = inputEl.closest('label');
        if (!label) return (inputEl.getAttribute('value') || '').trim();
        var span = label.querySelector('span');
        var text = span ? span.textContent : label.textContent;
        return (text || inputEl.getAttribute('value') || '').trim();
    }

    function pickerLabel(root, pickerName) {
        var input = root.querySelector('[data-picker="' + pickerName + '"] input:checked');
        return inputDisplayLabel(input);
    }

    function pickerCheckedLabels(root, pickerName) {
        return Array.from(root.querySelectorAll('[data-picker="' + pickerName + '"] input:checked')).map(function (input) {
            return inputDisplayLabel(input);
        }).filter(Boolean);
    }

    function boolValue(root, selector) {
        const el = root.querySelector(selector);
        return !!(el && el.checked);
    }

    function numberValue(root, selector) {
        const el = root.querySelector(selector);
        if (!el || el.value === '') return null;
        const n = Number(el.value);
        return Number.isFinite(n) ? n : null;
    }

    function textValue(root, selector) {
        const el = root.querySelector(selector);
        if (!el) return '';
        return (el.value || '').trim();
    }

    function tagsToArray(value) {
        if (Array.isArray(value)) return value;
        if (value && typeof value === 'object') return Object.values(value);
        return [];
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setStatus(root, text, isError) {
        const el = root.querySelector('[data-role="status"]');
        if (!el) return;
        el.textContent = text || '';
        el.classList.toggle('is-error', !!isError);
    }

    function parsePriorityOrder(raw) {
        return raw.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
    }

    function getPriorityOrder(root) {
        var items = Array.from(root.querySelectorAll('[data-role="priority-list"] .khm-priority-item[data-priority]:not([hidden])'));
        if (items.length) {
            return items.map(function (item) {
                return item.getAttribute('data-priority') || '';
            }).filter(Boolean);
        }
        return parsePriorityOrder(textValue(root, '[data-filter="criteria_priority_order"]'));
    }

    function syncPriorityOrderField(root) {
        var input = root.querySelector('[data-filter="criteria_priority_order"]');
        if (!input) return;
        input.value = getPriorityOrder(root).join(',');
    }

    function getPriorityLabel(key) {
        var labels = {
            sector: 'Sector fit',
            region: 'Region',
            integrations: 'Integrations',
            partner_posture: 'Partner type',
            deployment_mode: 'Software deployment',
            onboarding_style: 'Software onboarding support',
            installation_preference: 'Hardware installation model',
            engagement_model: 'Consultancy engagement model',
            team_preference: 'Consultancy team profile',
            proof_of_commitment: 'Pilot preference'
        };
        return labels[key] || key;
    }

    function updatePriorityListVisibility(root, state) {
        var selectedTypes = getSelectedSolutionTypes(root, state);
        root.querySelectorAll('[data-role="priority-list"] .khm-priority-item[data-show-for]').forEach(function (item) {
            var types = (item.getAttribute('data-show-for') || '').split(' ').filter(Boolean);
            var visible = types.some(function (type) { return selectedTypes.indexOf(type) !== -1; });
            item.hidden = !visible;
        });
        syncPriorityOrderField(root);
    }

    function mapPartnerPostureToProviderType(partnerPosture) {
        if (partnerPosture === 'established-platform') return 'platform';
        if (partnerPosture === 'specialist-best-of-breed') return 'specialist';
        return '';
    }

    function normalizePreference(value) {
        return value === 'no-preference' ? '' : value;
    }

    function labelForPreference(value) {
        if (value === 'no-preference') return 'Open to any';
        var labels = {
            'established-platform': 'Established platform with a large customer base',
            'specialist-best-of-breed': 'Specialist or best-of-breed solution'
        };
        if (labels[value]) return labels[value];
        return humanizeSlug(value);
    }

    function humanizeSlug(value) {
        if (!value) return '';
        return String(value)
            .replace(/[-_]+/g, ' ')
            .trim()
            .replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
    }

    function getEffectivePartnerPosture(root) {
        var softwarePosture = pickerValue(root, 'partner_posture_software');
        var hardwarePosture = pickerValue(root, 'partner_posture_hardware');
        var legacyPosture = pickerValue(root, 'partner_posture');

        if (softwarePosture && hardwarePosture) {
            if (softwarePosture === hardwarePosture) {
                return softwarePosture;
            }
            // Mixed preferences across software and hardware should not hard-filter provider type.
            return '';
        }

        return softwarePosture || hardwarePosture || legacyPosture || '';
    }

    function parseJsonAttr(root, attrName) {
        const raw = root.getAttribute(attrName);
        if (!raw) return {};
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (err) {
            return {};
        }
    }

    async function apiFetch(path, options) {
        const response = await fetch(cfg.restBase + path, {
            credentials: 'same-origin',
            headers: Object.assign({
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce || ''
            }, (options && options.headers) || {}),
            method: (options && options.method) || 'GET',
            body: (options && options.body) ? JSON.stringify(options.body) : undefined
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch (err) {
            payload = null;
        }

        if (!response.ok) {
            const message = payload && payload.message ? payload.message : ('HTTP ' + response.status);
            throw new Error(message);
        }

        return payload;
    }

    function setWizardStep(root, state, step) {
        const clamped = Math.max(1, Math.min(state.totalSteps, step));
        state.wizardStep = clamped;
        state.blockedAttemptStep = null;

        root.querySelectorAll('[data-role="wizard-step"]').forEach(function (node) {
            const isActive = Number(node.getAttribute('data-step') || '0') === clamped;
            node.hidden = !isActive;
            node.classList.toggle('is-active', isActive);
        });

        const progressFill = root.querySelector('[data-role="wizard-progress-fill"]');
        if (progressFill) {
            const pct = Math.round((clamped / state.totalSteps) * 100);
            progressFill.style.width = pct + '%';
        }

        const progressText = root.querySelector('[data-role="wizard-progress-text"]');
        if (progressText) {
            progressText.textContent = 'Step ' + clamped + ' of ' + state.totalSteps;
        }

        const backBtn = root.querySelector('[data-action="step-back"]');
        const nextBtn = root.querySelector('[data-action="step-next"]');
        const findBtn = root.querySelector('[data-action="apply-filters"]');
        const saveBtn = root.querySelector('[data-action="save-search"]');
        const finalStep = clamped === state.totalSteps;

        if (backBtn) backBtn.hidden = clamped <= 1;
        if (nextBtn) {
            nextBtn.hidden = finalStep;
            nextBtn.textContent = clamped === 1 ? 'Begin' : 'Continue';
        }
        if (findBtn) findBtn.hidden = !finalStep;
        if (saveBtn) saveBtn.hidden = !finalStep;

        if (finalStep) {
            updateReview(root, state);
        }

        updateNextButtonState(root, state);
    }

    function canProceedFromStep(root, state, step) {
        if (step === 1) return true;
        if (step === 2) {
            return selectedValues(root, '[data-filter="expertise"]').length > 0;
        }
        if (step === 3) {
            return !!state.selectedProblem;
        }
        if (step === 4) {
            return state.selectedSolutions && state.selectedSolutions.size > 0;
        }
        if (step === 5) {
            return pickerCheckedValues(root, 'sector').length > 0
                && !!pickerValue(root, 'company_size_band')
                && !!pickerValue(root, 'region');
        }
        if (step === 6) {
            var visibleGroups = Array.from(root.querySelectorAll('.khm-step6-group')).filter(function (group) {
                return !group.hidden;
            });
            return visibleGroups.every(function (group) {
                return !!group.querySelector('input:checked');
            });
        }
        if (step === 7) {
            return !!pickerValue(root, 'budget_band');
        }
        return true;
    }

    function getStepBlockedMessage(step) {
        if (step === 2) return 'Select one area to continue.';
        if (step === 3) return 'Select a challenge to continue.';
        if (step === 4) return 'Choose at least one solution to continue.';
        if (step === 5) return 'Select sector, team size, and region to continue.';
        if (step === 6) return 'Complete all visible questions to continue.';
        if (step === 7) return 'Select a budget band to continue.';
        return 'Please complete this step to continue.';
    }

    function updateNextButtonState(root, state) {
        var nextBtn = root.querySelector('[data-action="step-next"]');
        var hint = root.querySelector('[data-role="step-blocked-message"]');

        if (!nextBtn || nextBtn.hidden) {
            if (hint) {
                hint.hidden = true;
                hint.textContent = '';
            }
            return;
        }

        var canProceed = canProceedFromStep(root, state, state.wizardStep);
        nextBtn.disabled = false;
        nextBtn.setAttribute('aria-disabled', canProceed ? 'false' : 'true');

        if (hint) {
            var showBlockedMessage = !canProceed && state.blockedAttemptStep === state.wizardStep;
            if (!showBlockedMessage) {
                hint.hidden = true;
                hint.textContent = '';
            } else {
                hint.hidden = false;
                hint.textContent = getStepBlockedMessage(state.wizardStep);
            }
        }
    }

    function getSelectedSolutionTypes(root, state) {
        var directSelections = ['software', 'hardware', 'consultancy'].filter(function (type) {
            return !!root.querySelector('[data-role="solutions-' + type + '-items"] input:checked');
        });

        if (directSelections.length) {
            return directSelections;
        }

        var types = [];
        var selected = state.selectedSolutions ? Array.from(state.selectedSolutions) : [];
        var problemSolutions = state.selectedProblem && state.selectedProblem.solutions ? state.selectedProblem.solutions : {};

        ['software', 'hardware', 'consultancy'].forEach(function (type) {
            var labels = Array.isArray(problemSolutions[type]) ? problemSolutions[type] : [];
            var hasType = labels.some(function (label) {
                return selected.indexOf(label) !== -1;
            });
            if (hasType) {
                types.push(type);
            }
        });

        return types;
    }

    function initStep6Groups(root, state) {
        var selectedTypes = getSelectedSolutionTypes(root, state);
        root.querySelectorAll('.khm-step6-group[data-show-for]').forEach(function (group) {
            var types = (group.getAttribute('data-show-for') || '').split(' ').filter(Boolean);
            var visible = types.some(function (t) { return selectedTypes.indexOf(t) !== -1; });
            group.hidden = !visible;
        });
        updatePriorityListVisibility(root, state);
        updateNextButtonState(root, state);
    }

    function updateReview(root, state) {
        const problemLabel = state.selectedProblem ? state.selectedProblem.label : 'None selected';
        const solutionsLabel = state.selectedSolutions && state.selectedSolutions.size
            ? Array.from(state.selectedSolutions).join(', ')
            : 'None selected';
        var selectedTypes = getSelectedSolutionTypes(root, state);
        var hasSoftware = selectedTypes.indexOf('software') !== -1;
        var hasHardware = selectedTypes.indexOf('hardware') !== -1;
        var hasConsultancy = selectedTypes.indexOf('consultancy') !== -1;

        var budgetBand = pickerValue(root, 'budget_band');
        var budgetLabels = {
            '0-20000': 'Under £20k',
            '20000-50000': '£20k – £50k',
            '50000-150000': '£50k – £150k',
            '150000-500000': '£150k – £500k',
            '500000+': '£500k+'
        };

        var proofLabels = {
            'free-test-expected': 'A free test is expected.',
            'pilot-expected': 'A pilot is expected',
            'pilot-essential': 'A pilot is essential',
            'pilot-preferred': 'A pilot is preferred but not essential',
            'pilot-not-required': 'A pilot is not required',
            'no-preference': 'Open to any',
            'required': 'Required (pilot + free trial)',
            'preferred': 'Preferred (free trial)',
            'not-needed': 'Not needed'
        };

        var deploymentLabels = {
            saas: 'Cloud / SaaS',
            hybrid: 'Hybrid',
            'on-prem': 'On-premise',
            'private-cloud': 'Private cloud',
            'self-serve': "Self-serve - we'll figure it out",
            'guided-onboarding': 'Guided onboarding',
            'fully-managed': 'Fully managed service',
            'no-preference': 'Open to any'
        };

        var engagementLabels = {
            'fixed-project': 'Fixed-scope project',
            retained: 'Ongoing retained advisory',
            'ad-hoc-advisory': 'Ad-hoc advisory',
            'no-preference': 'Open to any'
        };

        var priorityOrder = getPriorityOrder(root);
        var priorityLabel = priorityOrder.length
            ? priorityOrder.map(getPriorityLabel).join(' > ')
            : 'Default';

        const review = {
            expertise: selectedOptionLabels(root, '[data-filter="expertise"]').join(', ') || 'None selected',
            problem: problemLabel,
            solutions: solutionsLabel,
            sector: pickerCheckedLabels(root, 'sector').join(', ') || 'Not set',
            company_size_band: pickerLabel(root, 'company_size_band') || 'Not set',
            region: pickerLabel(root, 'region') || 'Not set',
            integrations: pickerCheckedLabels(root, 'integrations').join(', ') || 'None',
            partner_posture: labelForPreference(getEffectivePartnerPosture(root)) || 'Not set',
            delivery_model: (hasSoftware || hasHardware)
                ? (deploymentLabels[pickerValue(root, 'deployment_mode')] || deploymentLabels[pickerValue(root, 'onboarding_style')] || humanizeSlug(pickerValue(root, 'deployment_mode')) || humanizeSlug(pickerValue(root, 'onboarding_style')) || 'Not set')
                : 'Not applicable',
            engagement_model: hasConsultancy
                ? (engagementLabels[pickerValue(root, 'engagement_model')] || humanizeSlug(pickerValue(root, 'engagement_model')) || 'Not set')
                : 'Not applicable',
            proof_of_commitment: (hasSoftware || hasHardware)
                ? (proofLabels[pickerValue(root, 'proof_of_commitment')] || humanizeSlug(pickerValue(root, 'proof_of_commitment')) || 'Not set')
                : 'Not applicable',
            budget_band: budgetLabels[budgetBand] || (budgetBand ? humanizeSlug(budgetBand) : 'Not set'),
            criteria_priority_order: priorityLabel
        };

        Object.keys(review).forEach(function (key) {
            const target = root.querySelector('[data-role="review-' + key + '"]');
            if (target) target.textContent = review[key];
        });
    }

    function buildDirectoryParams(root) {
        const params = new URLSearchParams();

        selectedValues(root, '[data-filter="expertise"]').forEach(function (v) {
            params.append('expertise[]', v);
        });

        selectedValues(root, '[data-filter="industry"]').forEach(function (v) {
            params.append('industry[]', v);
        });

        const partnerPosture = getEffectivePartnerPosture(root);
        const providerType = mapPartnerPostureToProviderType(partnerPosture);
        const deploymentMode = normalizePreference(pickerValue(root, 'deployment_mode'));
        const budgetMin = numberValue(root, '[data-filter="budget_min"]');
        const budgetMax = numberValue(root, '[data-filter="budget_max"]');
        const companySize = numberValue(root, '[data-filter="company_size"]');
        const proof = pickerValue(root, 'proof_of_commitment');
        const needsPilot = proof === 'pilot-expected' || proof === 'pilot-essential' || proof === 'required';
        const needsTrial = proof === 'free-test-expected' || proof === 'required' || proof === 'preferred';

        if (providerType && providerType !== 'no-preference') params.set('provider_type', providerType);
        if (deploymentMode) params.set('deployment_mode', deploymentMode.toLowerCase());
        if (budgetMin !== null) params.set('budget_min', String(budgetMin));
        if (budgetMax !== null) params.set('budget_max', String(budgetMax));
        if (companySize !== null) params.set('company_size', String(companySize));
        if (needsPilot) params.set('pilot_available', '1');
        if (needsTrial) params.set('free_trial', '1');

        return params;
    }

    function initPriorityList(root) {
        var list = root.querySelector('[data-role="priority-list"]');
        if (!list) return;

        var dragging = null;

        list.addEventListener('dragstart', function (event) {
            var target = event.target instanceof Element ? event.target.closest('.khm-priority-item') : null;
            if (!(target instanceof HTMLElement)) return;
            dragging = target;
            list.classList.add('is-sorting');
            target.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', target.getAttribute('data-priority') || '');
            }
        });

        list.addEventListener('dragend', function () {
            if (dragging) {
                dragging.classList.remove('is-dragging');
            }
            list.classList.remove('is-sorting');
            dragging = null;
            syncPriorityOrderField(root);
            if (root.querySelector('[data-role="wizard-step"][data-step="8"].is-active')) {
                var state = root.__khmState;
                if (state) updateReview(root, state);
            }
        });

        list.addEventListener('dragover', function (event) {
            if (!dragging) return;
            event.preventDefault();
            var target = event.target instanceof Element ? event.target.closest('.khm-priority-item') : null;
            if (!(target instanceof HTMLElement) || target === dragging) return;

            var rect = target.getBoundingClientRect();
            var insertAfter = event.clientY > rect.top + rect.height / 2;
            if (insertAfter) {
                list.insertBefore(dragging, target.nextSibling);
            } else {
                list.insertBefore(dragging, target);
            }
        });

        syncPriorityOrderField(root);
    }

    function renderSolutions(root, state) {
        if (!state.selectedProblem) return;
        const problemSolutions = state.selectedProblem.solutions || {};

        const labelEl = root.querySelector('[data-role="solutions-problem-label"]');
        if (labelEl) labelEl.textContent = state.selectedProblem.label;

        ['software', 'hardware', 'consultancy'].forEach(function (type) {
            const items = problemSolutions[type] || [];
            const section = root.querySelector('[data-role="solutions-' + type + '"]');
            const grid = root.querySelector('[data-role="solutions-' + type + '-items"]');
            if (!section || !grid) return;
            if (!items.length) { section.hidden = true; return; }
            section.hidden = false;
            var meta = SOLUTION_TYPE_META[type] || { icon: '', tooltip: type, cls: type };
            var badge = '<span class="khm-solution-type-badge khm-solution-type-badge--' + meta.cls + '" title="' + escapeHtml(meta.tooltip) + '" aria-label="' + escapeHtml(meta.tooltip) + '">' + meta.icon + '</span>';
            grid.innerHTML = items.map(function (item) {
                var checked = state.selectedSolutions.has(item) ? ' checked' : '';
                return '<label class="khm-solution-item"><input type="checkbox" value="' + escapeHtml(item) + '"' + checked + ' /><span class="khm-solution-item-text">' + escapeHtml(item) + '</span>' + badge + '</label>';
            }).join('');
        });
    }

    function initProblemCombobox(root, state) {
        const input = root.querySelector('[data-role="problem-input"]');
        const dropdown = root.querySelector('[data-role="problem-dropdown"]');
        const helpText = root.querySelector('[data-role="problem-help-text"]');
        const chip = root.querySelector('[data-role="problem-chip"]');
        const chipLabel = root.querySelector('[data-role="problem-chip-label"]');
        const hidden = root.querySelector('[data-filter="problem"]');
        const expertiseSelect = root.querySelector('[data-filter="expertise"]');
        if (!input || !dropdown) return;

        var activeIndex = -1;

        function getAllProblems() {
            const expertiseSlug = selectedValues(root, '[data-filter="expertise"]')[0] || '';
            const channels = state.focusAreaChannels[expertiseSlug] || {};
            var all = [];
            Object.keys(channels).forEach(function (channelSlug) {
                var ch = channels[channelSlug];
                (ch.problems || []).forEach(function (p) {
                    all.push({ slug: p.slug, label: p.label, channelSlug: channelSlug, solutions: p.solutions || {} });
                });
            });
            return all;
        }

        function showDropdown(items) {
            activeIndex = -1;
            if (!items.length) {
                dropdown.innerHTML = '<li class="khm-combobox-empty">No matches found</li>';
            } else {
                dropdown.innerHTML = items.map(function (p, i) {
                    return '<li class="khm-combobox-option" role="option" data-idx="' + i + '" data-slug="' + escapeHtml(p.slug) + '" data-channel="' + escapeHtml(p.channelSlug) + '">' + escapeHtml(p.label) + '</li>';
                }).join('');
            }
            dropdown.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function closeDropdown() {
            dropdown.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        }

        function selectProblem(p) {
            state.selectedProblem = p;
            if (hidden) hidden.value = p.slug;
            if (chip) chip.hidden = false;
            if (chipLabel) chipLabel.textContent = p.label;
            input.value = '';
            input.hidden = true;
            closeDropdown();
            updateNextButtonState(root, state);
        }

        function clearProblem() {
            state.selectedProblem = null;
            state.selectedSolutions = new Set();
            if (hidden) hidden.value = '';
            if (chip) chip.hidden = true;
            input.hidden = false;
            input.value = '';
            var step3 = root.querySelector('[data-role="wizard-step"][data-step="3"]');
            if (step3 && !step3.hidden) {
                input.focus();
            }
            updateNextButtonState(root, state);
        }

        function updateProblemCopy() {
            var expertiseSlug = selectedValues(root, '[data-filter="expertise"]')[0] || '';
            var copy = PROBLEM_COPY_BY_EXPERTISE[expertiseSlug] || DEFAULT_PROBLEM_COPY;
            if (helpText) helpText.textContent = copy.help;
            input.setAttribute('placeholder', copy.placeholder);
        }

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var all = getAllProblems();
            var filtered = q ? all.filter(function (p) { return p.label.toLowerCase().indexOf(q) !== -1; }) : all;
            showDropdown(filtered);
        });

        input.addEventListener('focus', function () {
            var all = getAllProblems();
            showDropdown(all);
        });

        input.addEventListener('keydown', function (e) {
            var items = dropdown.querySelectorAll('.khm-combobox-option');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                var active = items[activeIndex];
                if (active) {
                    var all = getAllProblems();
                    var idx = parseInt(active.getAttribute('data-idx'), 10);
                    if (all[idx]) selectProblem(all[idx]);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        dropdown.addEventListener('mousedown', function (e) {
            var li = e.target instanceof Element ? e.target.closest('.khm-combobox-option') : null;
            if (!li) return;
            e.preventDefault();
            var all = getAllProblems();
            var idx = parseInt(li.getAttribute('data-idx'), 10);
            if (all[idx]) selectProblem(all[idx]);
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) closeDropdown();
        });

        // Wire up chip clear button
        root.addEventListener('click', function (e) {
            var target = e.target instanceof Element ? e.target : null;
            if (!target) return;
            var btn = target.closest('[data-action="clear-problem"]');
            if (btn) clearProblem();
        });

        if (expertiseSelect) {
            expertiseSelect.addEventListener('change', function () {
                updateProblemCopy();
                clearProblem();
            });
        }

        updateProblemCopy();

        // Wire up solutions checkbox changes
        root.addEventListener('change', function (e) {
            var target = e.target instanceof Element ? e.target : null;
            if (!target || !target.matches('.khm-solutions-grid input[type="checkbox"]')) return;
            var value = target.value;
            if (target.checked) {
                state.selectedSolutions.add(value);
            } else {
                state.selectedSolutions.delete(value);
            }
            initStep6Groups(root, state);
            updateNextButtonState(root, state);
        });
    }

    function renderProviderRow(provider, selectedSet) {
        const expertise = tagsToArray(provider.expertise_tags).slice(0, 4);
        const tags = expertise.map(function (t) {
            return '<span class="khm-tag">' + escapeHtml(t) + '</span>';
        }).join('');

        const summarySource = provider.summary || provider.description || provider.sweet_spot_summary || '';
        const summary = summarySource.length > 95 ? summarySource.slice(0, 95) + '...' : summarySource;
        const budgetMin = provider.budget_min || 0;
        const budgetMax = provider.budget_max || 0;
        const budgetText = budgetMin
            ? ('£' + escapeHtml(String(budgetMin)) + (budgetMax ? ('-£' + escapeHtml(String(budgetMax))) : '+'))
            : '-';

        const rawScore = provider.score != null
            ? Number(provider.score)
            : (provider.match_score != null ? Number(provider.match_score) : (provider.affinity_score != null ? Number(provider.affinity_score) : null));
        const clampedScore = rawScore != null && Number.isFinite(rawScore) ? Math.max(0, Math.min(100, Math.round(rawScore))) : null;
        const scoreHtml = clampedScore === null
            ? '<span class="khm-score-na">-</span>'
            : '<span class="khm-score-bar"><span class="khm-score-fill" style="width:' + clampedScore + '%"></span></span><span class="khm-score-pct">' + clampedScore + '%</span>';

        const pills = [];
        if (provider.pilot_scheme_available) pills.push('<span class="khm-meta-pill">Pilot</span>');
        if (provider.free_trial_available) pills.push('<span class="khm-meta-pill">Trial</span>');

        const providerId = Number(provider.id || 0);
        const savedClass = selectedSet.has(providerId) ? ' is-saved' : '';

        return [
            '<tr class="khm-provider-row" data-provider-id="' + providerId + '" data-score="' + (clampedScore || 0) + '" data-price="' + budgetMin + '">',
            '  <td class="khm-col-name">',
            '    <strong>' + escapeHtml(provider.name || 'Unnamed') + '</strong>',
            '    <p class="khm-provider-summary-sm">' + escapeHtml(summary) + '</p>',
            '    <div class="khm-provider-pills">' + pills.join('') + '</div>',
            '  </td>',
            '  <td class="khm-col-type"><span class="khm-type-badge">' + escapeHtml(provider.provider_type || '-') + '</span></td>',
            '  <td class="khm-col-tags">' + (tags || '<span class="khm-score-na">-</span>') + '</td>',
            '  <td class="khm-col-budget">' + budgetText + '</td>',
            '  <td class="khm-col-score">' + scoreHtml + '</td>',
            '  <td class="khm-col-actions">',
            '    <button type="button" class="khm-icon-btn' + savedClass + '" data-action="toggle-shortlist" data-provider-id="' + providerId + '" title="Shortlist" aria-label="Shortlist ' + escapeHtml(provider.name || '') + '">' + ICON_SAVE + '</button>',
            '    <button type="button" class="khm-icon-btn khm-icon-btn-primary" data-action="send-request" data-provider-id="' + providerId + '" title="Request Intro" aria-label="Request intro from ' + escapeHtml(provider.name || '') + '">' + ICON_INTRO + '</button>',
            '  </td>',
            '</tr>'
        ].join('');
    }

    function renderCompare(root, selected, providersById) {
        const drawer = root.querySelector('[data-role="compare-drawer"]');
        const items = root.querySelector('[data-role="compare-items"]');
        if (!drawer || !items) return;

        if (!selected.size) {
            drawer.hidden = true;
            items.innerHTML = '';
            return;
        }

        drawer.hidden = false;
        items.innerHTML = Array.from(selected).map(function (id) {
            const provider = providersById.get(id);
            if (!provider) return '';
            return [
                '<div class="khm-compare-item">',
                '  <strong>' + escapeHtml(provider.name || '') + '</strong><br>',
                '  Type: ' + escapeHtml(provider.provider_type || '-') + '<br>',
                '  Budget: ' + escapeHtml((provider.budget_min ? '£' + provider.budget_min : '-') + ' - ' + (provider.budget_max ? '£' + provider.budget_max : '-')),
                '</div>'
            ].join('');
        }).join('');
    }

    function showResultsPanel(root, providers, state, statusMsg) {
        const panel = root.querySelector('[data-role="results-panel"]');
        const tbody = root.querySelector('[data-role="cards"]');
        if (!panel || !tbody) return;

        panel.hidden = false;
        setStatus(root, statusMsg, false);

        if (!providers.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="khm-empty">No providers matched your setup.</td></tr>';
            return;
        }

        tbody.innerHTML = providers.map(function (provider) {
            return renderProviderRow(provider, state.compareSelected);
        }).join('');

        renderCompare(root, state.compareSelected, state.providersById);
    }

    function sortTable(root, sortKey) {
        const tbody = root.querySelector('[data-role="cards"]');
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('.khm-provider-row'));
        rows.sort(function (left, right) {
            const leftValue = parseFloat(left.dataset[sortKey] || '0');
            const rightValue = parseFloat(right.dataset[sortKey] || '0');
            return sortKey === 'price' ? leftValue - rightValue : rightValue - leftValue;
        });

        rows.forEach(function (row) {
            tbody.appendChild(row);
        });

        root.querySelectorAll('.khm-sort-btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.sort === sortKey);
        });
    }

    async function loadProviders(root, state) {
        setStatus(root, (cfg.strings && cfg.strings.loading) || 'Loading matches...', false);

        try {
            const params = buildDirectoryParams(root);
            const payload = await apiFetch('directory?' + params.toString());
            const providers = Array.isArray(payload.providers) ? payload.providers : [];

            state.providers = providers;
            state.providersById = new Map(providers.map(function (provider) {
                return [Number(provider.id), provider];
            }));

            showResultsPanel(root, providers, state, providers.length + ' provider(s) loaded.');
        } catch (err) {
            setStatus(root, err.message || 'Failed to load providers', true);
        }
    }

    function buildCriteriaFromWizard(root, state) {
        var budgetBand = pickerValue(root, 'budget_band');
        var budgetMinMax = { min: null, max: null };
        var budgetMap = {
            '0-20000': [0, 20000],
            '20000-50000': [20000, 50000],
            '50000-150000': [50000, 150000],
            '150000-500000': [150000, 500000],
            '500000+': [500000, null]
        };
        if (budgetBand && budgetMap[budgetBand]) {
            budgetMinMax.min = budgetMap[budgetBand][0];
            budgetMinMax.max = budgetMap[budgetBand][1];
        }

        var proof = normalizePreference(pickerValue(root, 'proof_of_commitment'));
        var pilotRequired = proof === 'pilot-expected' || proof === 'pilot-essential' || proof === 'required';
        var freeTrial = proof === 'free-test-expected' || proof === 'required' || proof === 'preferred';
        var selectedTypes = getSelectedSolutionTypes(root, state);
        var partnerPosture = getEffectivePartnerPosture(root);
        var providerType = mapPartnerPostureToProviderType(partnerPosture);

        var integrationsOtherEl = root.querySelector('[data-picker="integrations_other"]');
        var integrationsOther = integrationsOtherEl ? (integrationsOtherEl.value || '').trim() : '';

        return {
            expertise: selectedValues(root, '[data-filter="expertise"]'),
            challenge: state.selectedProblem ? state.selectedProblem.label : '',
            solution_types: selectedTypes,
            sector: pickerCheckedValues(root, 'sector').join(','),
            region: pickerValue(root, 'region'),
            company_size_band: pickerValue(root, 'company_size_band'),
            integrations: pickerCheckedValues(root, 'integrations'),
            integrations_other: integrationsOther,
            provider_type: providerType,
            partner_posture: partnerPosture,
            deployment_mode: normalizePreference(pickerValue(root, 'deployment_mode')),
            onboarding_style: normalizePreference(pickerValue(root, 'onboarding_style')),
            installation_preference: normalizePreference(pickerValue(root, 'installation_preference')),
            delivery_model: normalizePreference(pickerValue(root, 'deployment_mode')),
            engagement_model: normalizePreference(pickerValue(root, 'engagement_model')),
            proof_of_commitment: proof,
            pilot_required: pilotRequired,
            free_trial: freeTrial,
            budget_min: budgetMinMax.min,
            budget_max: budgetMinMax.max,
            criteria_priority_order: getPriorityOrder(root)
        };
    }

    async function saveSearch(root, state) {
        if (!cfg.isLoggedIn) {
            window.location.href = cfg.loginUrl || '/wp-login.php';
            return;
        }

        setStatus(root, 'Saving search...', false);

        var criteria = buildCriteriaFromWizard(root, state);
        var defaultLabel = criteria.challenge || (Array.isArray(criteria.expertise) && criteria.expertise.length ? criteria.expertise.join(', ') : 'Saved search');
        var label = (window.prompt('Name this saved search:', defaultLabel) || '').trim();
        if (!label) {
            setStatus(root, 'Save cancelled.', false);
            return;
        }

        try {
            await apiFetch('saved-searches', { method: 'POST', body: { label: label, criteria: criteria } });
            setStatus(root, 'Saved. Find it under Saved Searches.', false);
        } catch (err) {
            setStatus(root, err.message || 'Could not save search.', true);
        }
    }

    async function sendRequest(providerId) {
        if (!cfg.isLoggedIn) {
            window.location.href = cfg.loginUrl || '/wp-login.php';
            return;
        }

        await apiFetch('directory/' + providerId + '/request', {
            method: 'POST',
            body: {}
        });
    }

    function toggleShortlist(root, state, providerId) {
        if (state.compareSelected.has(providerId)) {
            state.compareSelected.delete(providerId);
        } else {
            if (state.compareSelected.size >= 3) {
                setStatus(root, (cfg.strings && cfg.strings.maxCompare) || 'Shortlist limit reached (max 3).', true);
                return;
            }
            state.compareSelected.add(providerId);
        }

        root.querySelectorAll('[data-action="toggle-shortlist"]').forEach(function (btn) {
            const id = Number(btn.getAttribute('data-provider-id') || '0');
            btn.classList.toggle('is-saved', state.compareSelected.has(id));
        });

        renderCompare(root, state.compareSelected, state.providersById);
    }

    function bind(root, state) {
        root.addEventListener('change', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            updateNextButtonState(root, state);
        });

        root.addEventListener('click', async function (event) {
            // Focus area card selection (step 2)
            const card = event.target instanceof Element
                ? event.target.closest('.khm-focus-card')
                : null;
            if (card instanceof HTMLElement) {
                const slug = card.getAttribute('data-expertise-slug');
                if (!slug) return;

                // Deselect all cards (single-select behaviour)
                root.querySelectorAll('.khm-focus-card').forEach(function (c) {
                    c.classList.remove('is-selected');
                });
                card.classList.add('is-selected');

                // Sync hidden select: deselect all, then select the matching option
                const expertiseSelect = root.querySelector('[data-filter="expertise"]');
                if (expertiseSelect && expertiseSelect.options) {
                    Array.from(expertiseSelect.options).forEach(function (opt) {
                        opt.selected = opt.value === slug;
                    });
                    expertiseSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                return;
            }

            const rawTarget = event.target instanceof Element ? event.target : null;
            if (!rawTarget) return;
            const target = rawTarget.closest('button, [data-action], [data-sort-col]');
            if (!target || !(target instanceof HTMLElement)) return;

            if (target.matches('[data-action="step-next"]')) {
                if (!canProceedFromStep(root, state, state.wizardStep)) {
                    state.blockedAttemptStep = state.wizardStep;
                    updateNextButtonState(root, state);
                    return;
                }
                const nextStep = state.wizardStep + 1;
                setWizardStep(root, state, nextStep);
                if (nextStep === 4) {
                    renderSolutions(root, state);
                }
                if (nextStep === 6) {
                    initStep6Groups(root, state);
                }
                return;
            }

            if (target.matches('[data-action="step-back"]')) {
                setWizardStep(root, state, state.wizardStep - 1);
                return;
            }

            if (target.matches('[data-action="apply-filters"]')) {
                await loadProviders(root, state);
                return;
            }

            if (target.matches('[data-action="save-search"]')) {
                await saveSearch(root, state);
                return;
            }

            if (target.matches('[data-action="send-request"]')) {
                const providerId = Number(target.getAttribute('data-provider-id') || '0');
                if (!providerId) return;

                try {
                    target.setAttribute('disabled', 'disabled');
                    await sendRequest(providerId);
                    target.classList.add('is-sent');
                    target.setAttribute('title', 'Intro requested');
                    setStatus(root, (cfg.strings && cfg.strings.requestSent) || 'Request sent', false);
                } catch (err) {
                    target.removeAttribute('disabled');
                    setStatus(root, err.message || ((cfg.strings && cfg.strings.requestFailed) || 'Request failed'), true);
                }
                return;
            }

            if (target.matches('[data-action="toggle-shortlist"]')) {
                const providerId = Number(target.getAttribute('data-provider-id') || '0');
                if (!providerId) return;
                toggleShortlist(root, state, providerId);
                return;
            }

            if (target.matches('[data-action="clear-compare"]')) {
                state.compareSelected.clear();
                root.querySelectorAll('[data-action="toggle-shortlist"]').forEach(function (btn) {
                    btn.classList.remove('is-saved');
                });
                renderCompare(root, state.compareSelected, state.providersById);
                return;
            }

            if (target.matches('[data-sort]')) {
                sortTable(root, target.getAttribute('data-sort'));
                return;
            }

            if (target.matches('[data-sort-col]')) {
                const sortKey = target.getAttribute('data-sort-col') || 'score';
                sortTable(root, sortKey);
                return;
            }
        });
    }

    function initDirectory(root) {
        const industryLabels = {};
        root.querySelectorAll('[data-filter="industry"] option').forEach(function (option) {
            industryLabels[option.value] = option.textContent || option.value;
        });

        const state = {
            providers: [],
            providersById: new Map(),
            compareSelected: new Set(),
            selectedProblem: null,
            selectedSolutions: new Set(),
            wizardStep: 1,
            blockedAttemptStep: null,
            totalSteps: root.querySelectorAll('[data-role="wizard-step"]').length || 1,
            expertiseToIndustries: parseJsonAttr(root, 'data-expertise-industries'),
            focusAreaChannels: parseJsonAttr(root, 'data-focus-channels'),
            industryLabels: industryLabels
        };

        root.__khmState = state;

        bind(root, state);
        initProblemCombobox(root, state);
        initPriorityList(root);
        setWizardStep(root, state, 1);
    }

    function init() {
        const roots = document.querySelectorAll('.khm-connect-directory');
        roots.forEach(function (root) {
            initDirectory(root);
        });

        const savedRoots = document.querySelectorAll('[data-role="saved-searches"]');
        savedRoots.forEach(function (root) {
            initSavedSearches(root);
        });
    }

    async function initSavedSearches(root) {
        if (!cfg.isLoggedIn) {
            root.innerHTML = '<p>Please log in to view your saved searches.</p>';
            return;
        }

        const empty = document.querySelector('[data-role="saved-searches-empty"]');

        async function refresh() {
            root.innerHTML = '<p>Loading saved searches…</p>';
            try {
                const res = await apiFetch('saved-searches/mine');
                const items = Array.isArray(res.saved_searches) ? res.saved_searches : [];

                if (!items.length) {
                    root.innerHTML = '';
                    if (empty) empty.hidden = false;
                    return;
                }

                if (empty) empty.hidden = true;
                root.innerHTML = items.map(renderSavedSearchCard).join('');
            } catch (err) {
                root.innerHTML = '<p class="khm-error">Could not load saved searches: ' + escapeHtml(err.message || 'unknown error') + '</p>';
            }
        }

        function renderSavedSearchCard(item) {
            var summary = describeCriteria(item.criteria || {});
            var lastMatched = item.last_matched_at ? ('Last matched ' + escapeHtml(item.last_matched_at)) : 'Not run yet';
            return [
                '<article class="khm-saved-search-card" data-saved-id="' + item.id + '">',
                '  <header><h4>' + escapeHtml(item.label || 'Untitled') + '</h4>',
                '    <span class="khm-saved-search-meta">' + lastMatched + '</span></header>',
                '  <p class="khm-saved-search-summary">' + escapeHtml(summary) + '</p>',
                '  <div class="khm-saved-search-actions">',
                '    <button type="button" class="khm-buyer-btn" data-saved-action="run">Re-run</button>',
                '    <button type="button" class="khm-buyer-btn khm-buyer-btn-secondary" data-saved-action="delete">Delete</button>',
                '  </div>',
                '  <div class="khm-saved-search-results" data-saved-results hidden></div>',
                '</article>'
            ].join('');
        }

        function describeCriteria(criteria) {
            var parts = [];
            if (criteria.challenge) parts.push(criteria.challenge);
            if (Array.isArray(criteria.expertise) && criteria.expertise.length) parts.push(criteria.expertise.join(', '));
            if (criteria.region) parts.push(criteria.region);
            if (criteria.budget_min || criteria.budget_max) {
                var lo = criteria.budget_min || 0;
                var hi = criteria.budget_max || 'open';
                parts.push('£' + lo + ' – ' + hi);
            }
            return parts.length ? parts.join(' · ') : 'No criteria recorded';
        }

        root.addEventListener('click', async function (e) {
            const target = e.target instanceof Element ? e.target.closest('[data-saved-action]') : null;
            if (!target) return;
            const card = target.closest('[data-saved-id]');
            if (!card) return;
            const id = card.getAttribute('data-saved-id');
            const action = target.getAttribute('data-saved-action');

            if (action === 'delete') {
                if (!window.confirm('Delete this saved search?')) return;
                try {
                    await apiFetch('saved-searches/' + id, { method: 'DELETE' });
                    await refresh();
                } catch (err) {
                    window.alert('Delete failed: ' + (err.message || 'unknown error'));
                }
                return;
            }

            if (action === 'run') {
                const out = card.querySelector('[data-saved-results]');
                if (!out) return;
                out.hidden = false;
                out.innerHTML = '<p>Re-matching…</p>';
                try {
                    const res = await apiFetch('saved-searches/' + id + '/run', { method: 'POST', body: {} });
                    const matches = Array.isArray(res.matches) ? res.matches : [];
                    out.innerHTML = matches.length
                        ? '<p>' + matches.length + ' match(es). <a href="?bh_section=discover">Open in Discover</a> to act on them.</p>'
                        : '<p>No matches yet for this search.</p>';
                } catch (err) {
                    out.innerHTML = '<p class="khm-error">Re-run failed: ' + escapeHtml(err.message || 'unknown error') + '</p>';
                }
            }
        });

        await refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

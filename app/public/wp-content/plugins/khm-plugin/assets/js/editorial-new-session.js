// Editorial New Session React App
const { useState, useEffect } = wp.element;
const {
    Button,
    SelectControl,
    FormTokenField,
    Spinner,
    Notice,
    Card,
    CardBody,
    CardHeader,
    RangeControl,
    ToggleControl,
} = wp.components;
const { dispatch } = wp.data;

const TOPIC_OPTIONS = [
    { label: 'Manufacturing', value: 'Manufacturing' },
    { label: 'Field Service', value: 'Field Service' },
    { label: 'Logistics', value: 'Logistics' },
    { label: 'Energy', value: 'Energy' },
    { label: 'Retail', value: 'Retail' },
    { label: 'Pricing', value: 'Pricing' },
];

const RESEARCH_POLICY_DEFAULTS = {
    recencyMonths: 36,
    maxCitationsPerOrg: 2,
    sourceMix: {
        academic: 1,
        analyst: 1,
        industry: 1,
        caseStudy: 1,
    },
    blockedDomains: ['wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'],
};

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': dualGptData.nonce,
            ...(options.headers || {}),
        },
    });

const EditorialNewSessionApp = () => {
    const [topicOptions, setTopicOptions] = useState(TOPIC_OPTIONS);
    const [topLineCategories, setTopLineCategories] = useState([]);
    const [selectedTopic, setSelectedTopic] = useState(TOPIC_OPTIONS[0].value);
    const [includes, setIncludes] = useState([]);
    const [excludes, setExcludes] = useState([]);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState('');
    const [focusLevel, setFocusLevel] = useState(50);
    const showFocusControls = true;
    
    // New sponsor-related fields
    const [researchProfile, setResearchProfile] = useState('');
    const [isSponsored, setIsSponsored] = useState(false);
    const [selectedSponsor, setSelectedSponsor] = useState('');
    const [sponsors, setSponsors] = useState([]);
    const [sponsorWeighting, setSponsorWeighting] = useState(2);
    const [loadingSponsors, setLoadingSponsors] = useState(false);
    
    // Presets for dropdowns
    const [researchPresets, setResearchPresets] = useState([]);
    const [loadingPresets, setLoadingPresets] = useState(false);
    const [policyRecencyMonths, setPolicyRecencyMonths] = useState(RESEARCH_POLICY_DEFAULTS.recencyMonths);
    const [policySourceMix, setPolicySourceMix] = useState({ ...RESEARCH_POLICY_DEFAULTS.sourceMix });
    const [policyBlockedDomains, setPolicyBlockedDomains] = useState([...RESEARCH_POLICY_DEFAULTS.blockedDomains]);

    // Load presets on mount
    useEffect(() => {
        loadPresets();
        loadTopLineCategories();
    }, []);

    const applyCategoryPolicyToForm = (category) => {
        const policy = category?.research_policy;
        if (!policy) {
            return;
        }

        setPolicyRecencyMonths(Number(policy.recency_months || RESEARCH_POLICY_DEFAULTS.recencyMonths));
        setPolicySourceMix({
            academic: Number(policy?.source_mix_minimums?.academic || 0),
            analyst: Number(policy?.source_mix_minimums?.analyst || 0),
            industry: Number(policy?.source_mix_minimums?.industry || 0),
            caseStudy: Number(policy?.source_mix_minimums?.case_study || 0),
        });
        setPolicyBlockedDomains(
            Array.isArray(policy.blocked_domains) && policy.blocked_domains.length
                ? policy.blocked_domains
                : [...RESEARCH_POLICY_DEFAULTS.blockedDomains]
        );
    };

    const loadTopLineCategories = async () => {
        try {
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/top-line-categories',
                method: 'GET',
            });
            const rows = Array.isArray(response?.top_line_categories) ? response.top_line_categories : [];
            if (!rows.length) {
                return;
            }

            const options = rows.map((row) => ({
                label: row.name,
                value: row.name,
            }));
            setTopLineCategories(rows);
            setTopicOptions(options);

            if (!selectedTopic || !options.some((opt) => opt.value === selectedTopic)) {
                setSelectedTopic(options[0].value);
                applyCategoryPolicyToForm(rows[0]);
            } else {
                const current = rows.find((row) => row.name === selectedTopic);
                if (current) {
                    applyCategoryPolicyToForm(current);
                }
            }
        } catch (err) {
            console.error('Failed to load top-line categories:', err);
        }
    };

    useEffect(() => {
        if (!Array.isArray(topLineCategories) || !topLineCategories.length) {
            return;
        }
        const current = topLineCategories.find((row) => row.name === selectedTopic);
        if (current) {
            applyCategoryPolicyToForm(current);
        }
    }, [selectedTopic, topLineCategories]);

    const loadPresets = async () => {
        try {
            setLoadingPresets(true);
            const response = await apiFetch({
                path: 'dual-gpt/v1/presets',
                method: 'GET',
            });
            
            if (Array.isArray(response)) {
                // Filter presets by role
                const research = response.filter(p => p.role === 'research' || p.role === 'both');
                
                setResearchPresets(research);
            }
        } catch (err) {
            console.error('Failed to load presets:', err);
            // Don't show error - presets are optional
        } finally {
            setLoadingPresets(false);
        }
    };

    // Load sponsors when sponsored content is checked
    useEffect(() => {
        if (isSponsored && sponsors.length === 0) {
            loadSponsors();
        }
    }, [isSponsored]);

    const loadSponsors = async () => {
        try {
            setLoadingSponsors(true);
            const response = await apiFetch({
                path: 'khm-geo/v1/sponsors',
                method: 'GET',
            });
            
            if (Array.isArray(response)) {
                setSponsors(response);
            }
        } catch (err) {
            console.error('Failed to load sponsors:', err);
            setError('Failed to load sponsors. Please try again.');
        } finally {
            setLoadingSponsors(false);
        }
    };

    const handleStartSession = async () => {
        if (!selectedTopic) {
            setError('Please select a top-line topic.');
            return;
        }

        // Validation for sponsored content
        if (isSponsored && !selectedSponsor && sponsors.length > 0) {
            setError('Please select a sponsor for sponsored content, or ensure sponsors are available.');
            return;
        }

        try {
            setStarting(true);
            setError('');

            // Get sponsor name if selected
            const sponsorName = selectedSponsor ? 
                sponsors.find(s => s.id === parseInt(selectedSponsor))?.name : 
                null;

            const sessionPayload = {
                role: 'research',
                preset_id: researchProfile || 'research-default', // Use selected research profile or default
                title: selectedTopic,
                meta: {
                    topic: selectedTopic,
                    includes,
                    excludes,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                    research_policy: {
                        recency_months: policyRecencyMonths,
                        source_mix_minimums: {
                            academic: policySourceMix.academic,
                            analyst: policySourceMix.analyst,
                            industry: policySourceMix.industry,
                            case_study: policySourceMix.caseStudy,
                        },
                        blocked_domains: policyBlockedDomains,
                    },
                    is_sponsored: isSponsored,
                    ...(isSponsored ? {
                        sponsor_id: selectedSponsor || undefined,
                        sponsor_name: sponsorName || undefined,
                        sponsor_weighting: sponsorWeighting,
                        // Instructions for sponsor handling
                        sponsor_config: {
                            ignore_non_sponsor_vendors: true,
                            prioritize_sponsor_queries: !selectedSponsor, // Only when no sponsor library selected
                            weighting_level: sponsorWeighting
                        }
                    } : {})
                },
                idempotency_key: `planner-${Date.now()}`,
            };

            const sessionResponse = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'POST',
                data: sessionPayload,
            });

            if (!sessionResponse || !sessionResponse.session_id) {
                throw new Error('Session creation did not return a session id.');
            }

            await apiFetch({
                path: 'dual-gpt/v1/planner/run',
                method: 'POST',
                data: {
                    session_id: sessionResponse.session_id,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
                },
            });

            dispatch('core/notices').createNotice(
                'success',
                'Planning session created and queued successfully.',
                { type: 'snackbar' }
            );

            // Reset form
            setIncludes([]);
            setExcludes([]);
            setSelectedTopic(topicOptions[0]?.value || TOPIC_OPTIONS[0].value);
            setFocusLevel(50);
            setResearchProfile('');
            setIsSponsored(false);
            setSelectedSponsor('');
            setSponsorWeighting(2);
            setPolicyRecencyMonths(RESEARCH_POLICY_DEFAULTS.recencyMonths);
            setPolicySourceMix({ ...RESEARCH_POLICY_DEFAULTS.sourceMix });
            setPolicyBlockedDomains([...RESEARCH_POLICY_DEFAULTS.blockedDomains]);

            // Navigate to planner to view the session
            window.location.href = admin_url + 'admin.php?page=editorial_planner&session_id=' + encodeURIComponent(sessionResponse.session_id);
        } catch (err) {
            console.error('Failed to create session:', err);
            setError(err.message || 'Failed to create session. Please try again.');
        } finally {
            setStarting(false);
        }
    };

    return wp.element.createElement(
        'div',
        { style: { maxWidth: '700px', margin: '0 auto', padding: '20px' } },
        wp.element.createElement('h1', null, 'Start New Session'),
        wp.element.createElement('p', null, 'Create a new planning session to begin research and content generation.'),
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, error),
        wp.element.createElement(
            Card,
            { style: { marginTop: '20px' } },
            wp.element.createElement(CardHeader, null, 'Session Configuration'),
            wp.element.createElement(
                CardBody,
                { style: { padding: '20px' } },
                wp.element.createElement(SelectControl, {
                    label: 'Top-line Topic',
                    value: selectedTopic,
                    options: topicOptions,
                    onChange: setSelectedTopic,
                    help: 'Select the primary topic or industry for this planning session',
                }),
                wp.element.createElement('div', { style: { marginTop: '20px' } },
                    loadingPresets ? 
                        wp.element.createElement(Spinner, null) :
                        wp.element.createElement(SelectControl, {
                            label: 'Research Profile',
                            value: researchProfile,
                            options: [
                                { label: '-- Select Research Profile --', value: '' },
                                ...researchPresets.map(preset => ({
                                    label: preset.name,
                                    value: preset.id
                                }))
                            ],
                            onChange: setResearchProfile,
                            help: 'Optional: Research perspective from Dual GPT presets',
                        })
                ),
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            marginTop: '10px',
                            padding: '10px',
                            backgroundColor: '#f6f7f7',
                            border: '1px solid #dcdcde',
                            borderRadius: '3px',
                            fontSize: '12px',
                            color: '#50575e',
                        },
                    },
                    wp.element.createElement('strong', null, 'Research policy (server-enforced defaults): '),
                    `Recency ${RESEARCH_POLICY_DEFAULTS.recencyMonths} months, max ${RESEARCH_POLICY_DEFAULTS.maxCitationsPerOrg} citations/org, source mix min (${RESEARCH_POLICY_DEFAULTS.sourceMix.academic} academic, ${RESEARCH_POLICY_DEFAULTS.sourceMix.analyst} analyst, ${RESEARCH_POLICY_DEFAULTS.sourceMix.industry} industry, ${RESEARCH_POLICY_DEFAULTS.sourceMix.caseStudy} case study), blocked domains: ${RESEARCH_POLICY_DEFAULTS.blockedDomains.join(', ')}.`
                ),
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            marginTop: '10px',
                            padding: '12px',
                            border: '1px solid #dcdcde',
                            borderRadius: '3px',
                        },
                    },
                    wp.element.createElement('h4', { style: { margin: '0 0 10px' } }, 'Research Policy Overrides (Optional)'),
                    wp.element.createElement(RangeControl, {
                        label: 'Recency Window (months)',
                        value: policyRecencyMonths,
                        onChange: (value) => setPolicyRecencyMonths(Number(value || RESEARCH_POLICY_DEFAULTS.recencyMonths)),
                        min: 1,
                        max: 60,
                        step: 1,
                    }),
                    wp.element.createElement('p', { style: { margin: '8px 0 4px', fontWeight: '500' } }, 'Source Mix Minimums'),
                    wp.element.createElement(RangeControl, {
                        label: 'Academic',
                        value: policySourceMix.academic,
                        onChange: (value) => setPolicySourceMix((prev) => ({ ...prev, academic: Number(value || 0) })),
                        min: 0,
                        max: 3,
                        step: 1,
                    }),
                    wp.element.createElement(RangeControl, {
                        label: 'Analyst',
                        value: policySourceMix.analyst,
                        onChange: (value) => setPolicySourceMix((prev) => ({ ...prev, analyst: Number(value || 0) })),
                        min: 0,
                        max: 3,
                        step: 1,
                    }),
                    wp.element.createElement(RangeControl, {
                        label: 'Industry',
                        value: policySourceMix.industry,
                        onChange: (value) => setPolicySourceMix((prev) => ({ ...prev, industry: Number(value || 0) })),
                        min: 0,
                        max: 3,
                        step: 1,
                    }),
                    wp.element.createElement(RangeControl, {
                        label: 'Case Study',
                        value: policySourceMix.caseStudy,
                        onChange: (value) => setPolicySourceMix((prev) => ({ ...prev, caseStudy: Number(value || 0) })),
                        min: 0,
                        max: 3,
                        step: 1,
                    }),
                    wp.element.createElement(FormTokenField, {
                        label: 'Blocked Domains',
                        value: policyBlockedDomains,
                        onChange: setPolicyBlockedDomains,
                        placeholder: 'Add domains to block',
                    })
                ),
                wp.element.createElement('div', { style: { marginTop: '20px' } },
                    wp.element.createElement(ToggleControl, {
                        label: 'Sponsored Content',
                        checked: isSponsored,
                        onChange: setIsSponsored,
                        help: 'Enable sponsor-specific research targeting and content filtering',
                    })
                ),
                isSponsored && wp.element.createElement('div', { 
                    style: { 
                        marginTop: '15px', 
                        marginLeft: '20px',
                        padding: '15px',
                        backgroundColor: '#f0f0f1',
                        borderLeft: '3px solid #2271b1',
                        borderRadius: '4px'
                    } 
                },
                    wp.element.createElement('h4', { style: { marginTop: 0, marginBottom: '15px' } }, 'Sponsor Settings'),
                    loadingSponsors ? 
                        wp.element.createElement(Spinner, null) :
                        wp.element.createElement(SelectControl, {
                            label: 'Sponsor',
                            value: selectedSponsor,
                            options: [
                                { label: '-- Select Sponsor --', value: '' },
                                ...sponsors.map(sponsor => ({
                                    label: sponsor.name,
                                    value: sponsor.id
                                }))
                            ],
                            onChange: setSelectedSponsor,
                            help: selectedSponsor ? 
                                'Sponsor library content will be prioritized. Non-sponsor vendors will be filtered out.' :
                                'Without sponsor selection, sponsor name will be added to all research queries.',
                        }),
                    wp.element.createElement('div', { style: { marginTop: '15px' } },
                        wp.element.createElement('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, 'Sponsor Weighting'),
                        wp.element.createElement(RangeControl, {
                            value: sponsorWeighting,
                            onChange: setSponsorWeighting,
                            min: 0,
                            max: 5,
                            step: 1,
                            marks: [
                                { value: 0, label: '0' },
                                { value: 2, label: '2' },
                                { value: 5, label: '5' }
                            ],
                            help: 'Level 0: Impartial (logo only, no vendor references). Level 5: Only sponsor content referenced.',
                        })
                    ),
                    wp.element.createElement('div', { 
                        style: { 
                            marginTop: '10px', 
                            padding: '10px',
                            backgroundColor: '#fff',
                            border: '1px solid #ddd',
                            borderRadius: '3px',
                            fontSize: '13px'
                        }
                    },
                        wp.element.createElement('strong', null, `Current Level: ${sponsorWeighting}`),
                        wp.element.createElement('p', { style: { margin: '5px 0 0 0', color: '#666' } },
                            sponsorWeighting === 0 ? 'Totally impartial - carries sponsor logo but doesn\'t reference other solution providers' :
                            sponsorWeighting === 1 ? 'Minimal sponsor prominence - balanced coverage' :
                            sponsorWeighting === 2 ? 'Balanced - sponsor highlighted with broader context (Default)' :
                            sponsorWeighting === 3 ? 'Sponsor-focused - significant emphasis on sponsor content' :
                            sponsorWeighting === 4 ? 'Heavily sponsor-led - mostly sponsor references' :
                            'Only sponsor content - exclusive sponsor references'
                        )
                    )
                ),
                wp.element.createElement('div', { style: { marginTop: '20px' } },
                    wp.element.createElement(FormTokenField, {
                        label: 'Include Keywords',
                        value: includes,
                        onChange: setIncludes,
                        placeholder: 'Add terms to include (e.g., "AI", "automation")',
                        help: 'Optional: Specify topics or keywords to prioritize',
                    })
                ),
                wp.element.createElement('div', { style: { marginTop: '20px' } },
                    wp.element.createElement(FormTokenField, {
                        label: 'Exclude Keywords',
                        value: excludes,
                        onChange: setExcludes,
                        placeholder: 'Add terms to exclude',
                        help: 'Optional: Specify topics or keywords to avoid',
                    })
                ),
                showFocusControls && wp.element.createElement('div', { style: { marginTop: '20px' } },
                    wp.element.createElement('label', null, 'Research Depth'),
                    wp.element.createElement(RangeControl, {
                        value: focusLevel,
                        onChange: setFocusLevel,
                        min: 0,
                        max: 100,
                        step: 10,
                        marks: [
                            { value: 0, label: 'Broad' },
                            { value: 50, label: 'Balanced' },
                            { value: 100, label: 'Deep' }
                        ],
                        help: 'Balance between breadth of topics and depth of analysis',
                    })
                ),
                wp.element.createElement(
                    'div',
                    { style: { marginTop: '30px', display: 'flex', gap: '10px', justifyContent: 'flex-start' } },
                    wp.element.createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: handleStartSession,
                            disabled: starting || !selectedTopic,
                            isBusy: starting,
                        },
                        starting ? wp.element.createElement(Spinner, null) : 'Create Session'
                    ),
                    wp.element.createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: () => window.history.back()
                        },
                        'Cancel'
                    )
                )
            )
        )
    );
};

// Mount the app
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-new-session-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialNewSessionApp), container);
    }
});

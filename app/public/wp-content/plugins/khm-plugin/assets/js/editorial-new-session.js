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
];

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': dualGptData.nonce,
            ...(options.headers || {}),
        },
    });

const normalizeProfileLabel = (name = '') =>
    String(name)
        .replace(/Editorial Planner/gi, 'Generic')
        .replace(/Research Assistant/gi, 'Specialist');

const hasRole = (preset, roles = []) => {
    const role = String(preset?.role || '').toLowerCase();
    return roles.includes(role);
};

const EditorialNewSessionApp = () => {
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

    // Load presets on mount
    useEffect(() => {
        loadPresets();
    }, []);

    const loadPresets = async () => {
        try {
            setLoadingPresets(true);
            const response = await apiFetch({
                path: 'dual-gpt/v1/presets',
                method: 'GET',
            });
            
            if (Array.isArray(response)) {
                // Support both legacy (research/author) and newer (generic/specialist) role naming.
                const research = response.filter((p) => hasRole(p, ['research', 'generic', 'both']));
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

            const defaultResearchPreset =
                researchPresets.find((preset) => ['generic-default', 'research-default'].includes(preset?.id))?.id ||
                (researchPresets[0]?.id || 'research-default');

            const sessionPayload = {
                role: 'research',
                preset_id: researchProfile || defaultResearchPreset,
                title: selectedTopic,
                meta: {
                    topic: selectedTopic,
                    includes,
                    excludes,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
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
            setSelectedTopic(TOPIC_OPTIONS[0].value);
            setFocusLevel(50);
            setResearchProfile('');
            setIsSponsored(false);
            setSelectedSponsor('');
            setSponsorWeighting(2);

            // Navigate to planner to view the session
            window.location.href = admin_url + 'admin.php?page=editorial_planner';
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
                    options: TOPIC_OPTIONS,
                    onChange: setSelectedTopic,
                    help: 'Select the primary topic or industry for this planning session',
                }),
                wp.element.createElement('div', { style: { marginTop: '20px' } },
                    loadingPresets ? 
                        wp.element.createElement(Spinner, null) :
                        wp.element.createElement(SelectControl, {
                            label: 'Generic Profile',
                            value: researchProfile,
                            options: [
                                { label: '-- Select Generic Profile --', value: '' },
                                ...researchPresets.map(preset => ({
                                    label: normalizeProfileLabel(preset.name),
                                    value: preset.id
                                }))
                            ],
                            onChange: setResearchProfile,
                            help: 'Optional: Generic perspective from presets',
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

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

const TOPIC_OPTIONS = [];

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': editorialData.nonce,
            ...(options.headers || {}),
        },
    });

const EditorialNewSessionApp = () => {
    const [topicOptions, setTopicOptions] = useState(TOPIC_OPTIONS);
    const [topLineCategories, setTopLineCategories] = useState([]);
    const [selectedTopic, setSelectedTopic] = useState('');
    const [includes, setIncludes] = useState([]);
    const [excludes, setExcludes] = useState([]);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState('');
    
    // Synopsis count per session
    const [synopsisCount, setSynopsisCount] = useState(4);
    
    // Sponsor-related fields
    const [isSponsored, setIsSponsored] = useState(false);
    const [selectedSponsor, setSelectedSponsor] = useState('');
    const [sponsors, setSponsors] = useState([]);
    const [sponsorWeighting, setSponsorWeighting] = useState(2);
    const [loadingSponsors, setLoadingSponsors] = useState(false);
    
    // Pillar selection state
    const [pillarOptions, setPillarOptions] = useState([]);
    const [selectedPillar, setSelectedPillar] = useState('');
    const [loadingPillars, setLoadingPillars] = useState(true);

    // Load categories on mount
    useEffect(() => {
        loadTopLineCategories();
    }, []);

    // When topic changes, load pillars
    useEffect(() => {
        const selected = topLineCategories.find((category) => String(category?.name || '') === String(selectedTopic));
        loadPillarsForTopic(selected);
    }, [selectedTopic, topLineCategories]);

    const loadPillarsForTopic = async (selectedCategory) => {
        const slug = selectedCategory?.site_slug || selectedCategory?.slug || '';
        if (!slug) {
            setPillarOptions([]);
            setSelectedPillar('');
            setLoadingPillars(false);
            return;
        }

        try {
            setLoadingPillars(true);
            const response = await apiFetch({
                path: `editorial/v1/planner/top-line-categories/${encodeURIComponent(slug)}/pillars`,
                method: 'GET',
            });

            const pillars = Array.isArray(response?.pillars) ? response.pillars : [];
            const options = pillars.map((p) => ({
                label: p.name,
                value: p.name,
                slug: p.name ? p.name.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-|-$/g, '') : '',
            }));
            setPillarOptions(options);
            if (!options.some((o) => o.value === selectedPillar)) {
                setSelectedPillar('');
            }
        } catch (err) {
            console.error('Failed to load pillars:', err);
            setPillarOptions([]);
        } finally {
            setLoadingPillars(false);
        }
    };

    const loadTopLineCategories = async () => {
        try {
            const response = await apiFetch({
                path: 'editorial/v1/planner/top-line-categories',
                method: 'GET',
            });

            const rows = Array.isArray(response?.top_line_categories) ? response.top_line_categories : [];
            if (!rows.length) {
                return;
            }

            const options = rows
                .map((row) => ({ label: row.name, value: row.name }))
                .filter((row) => row.value)
                .sort((a, b) => String(a.label).localeCompare(String(b.label)));

            setTopLineCategories(rows);
            setTopicOptions(options);
            if (!options.some((option) => option.value === selectedTopic)) {
                setSelectedTopic(options[0]?.value || '');
            }
        } catch (err) {
            console.error('Failed to load top-line categories:', err);
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

        if (isSponsored && !selectedSponsor && sponsors.length > 0) {
            setError('Please select a sponsor for sponsored content, or ensure sponsors are available.');
            return;
        }

        try {
            setStarting(true);
            setError('');

            const sponsorName = selectedSponsor ? 
                sponsors.find(s => s.id === parseInt(selectedSponsor))?.name : 
                null;

            const sessionPayload = {
                role: 'research',
                preset_id: 'research-default',
                title: selectedTopic,
                meta: {
                    topic: selectedTopic,
                    includes,
                    excludes,
                    synopsis_count: synopsisCount,
                    ...(selectedPillar ? {
                        pillar: selectedPillar,
                        pillar_slug: selectedPillar.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-|-$/g, ''),
                    } : {}),
                    is_sponsored: isSponsored,
                    ...(isSponsored ? {
                        sponsor_id: selectedSponsor || undefined,
                        sponsor_name: sponsorName || undefined,
                        sponsor_weighting: sponsorWeighting,
                        sponsor_config: {
                            ignore_non_sponsor_vendors: true,
                            prioritize_sponsor_queries: !selectedSponsor,
                            weighting_level: sponsorWeighting
                        }
                    } : {}),
                },
                idempotency_key: `planner-${Date.now()}`,
            };

            const sessionResponse = await apiFetch({
                path: 'editorial/v1/sessions',
                method: 'POST',
                data: sessionPayload,
            });

            if (!sessionResponse || !sessionResponse.session_id) {
                throw new Error('Session creation did not return a session id.');
            }

            await apiFetch({
                path: `editorial/v1/sessions/${sessionResponse.session_id}/run`,
                method: 'POST',
            });

            dispatch('core/notices').createNotice(
                'success',
                'Planning session created and queued successfully.',
                { type: 'snackbar' }
            );

            setIncludes([]);
            setExcludes([]);
            setSelectedTopic(topicOptions[0]?.value || '');
            setSelectedPillar('');
            setPillarOptions([]);
            setIsSponsored(false);
            setSelectedSponsor('');
            setSponsorWeighting(2);

            window.location.href = `${editorialData.adminUrl}admin.php?page=kh-editorial-planner&session_id=${encodeURIComponent(sessionResponse.session_id)}`;
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
                    label: 'Magazine',
                    value: selectedTopic,
                    options: topicOptions,
                    onChange: setSelectedTopic,
                    help: 'Select the primary topic or industry for this planning session',
                }),
                wp.element.createElement('div', { style: { marginTop: '12px' } },
                    loadingPillars ?
                        wp.element.createElement(Spinner, null) :
                        pillarOptions.length > 0 ?
                            wp.element.createElement(SelectControl, {
                                label: 'Editorial Pillar',
                                value: selectedPillar,
                                options: [
                                    { label: '-- No specific pillar --', value: '' },
                                    ...pillarOptions.map((p) => ({ label: p.label, value: p.value })),
                                ],
                                onChange: setSelectedPillar,
                                help: 'Optional: scope research to a specific editorial pillar within this topic.',
                            }) :
                            null
                ),
                wp.element.createElement('hr', { style: { margin: '20px 0', borderColor: '#ddd' } }),
                wp.element.createElement('div', { style: { marginTop: '20px' } },
                    wp.element.createElement('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, 'Article Summaries'),
                    wp.element.createElement('div', { style: { display: 'flex', gap: '16px', marginBottom: '4px' } },
                        [1, 4, 8].map(function(count) {
                            return wp.element.createElement('label', {
                                key: count,
                                style: { display: 'flex', alignItems: 'center', gap: '4px', cursor: 'pointer' }
                            },
                                wp.element.createElement('input', {
                                    type: 'radio',
                                    name: 'synopsis_count',
                                    value: count,
                                    checked: synopsisCount === count,
                                    onChange: function() { setSynopsisCount(count); }
                                }),
                                wp.element.createElement('span', null, String(count))
                            );
                        })
                    ),
                    wp.element.createElement('p', { style: { margin: '4px 0 0 0', fontSize: '12px', color: '#646970' } },
                        'Number of article summaries to produce. 1 for a focused brief, 4 for standard coverage, 8 for comprehensive exploration.'
                    )
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
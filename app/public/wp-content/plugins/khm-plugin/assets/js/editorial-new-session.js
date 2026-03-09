// Editorial New Session React App
const { useState } = wp.element;
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

const EditorialNewSessionApp = () => {
    const [selectedTopic, setSelectedTopic] = useState(TOPIC_OPTIONS[0].value);
    const [includes, setIncludes] = useState([]);
    const [excludes, setExcludes] = useState([]);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState('');
    const [focusLevel, setFocusLevel] = useState(50);
    const showFocusControls = true;

    const handleStartSession = async () => {
        if (!selectedTopic) {
            setError('Please select a top-line topic.');
            return;
        }

        try {
            setStarting(true);
            setError('');

            const sessionPayload = {
                role: 'research',
                preset_id: 'research-default',
                title: selectedTopic,
                meta: {
                    topic: selectedTopic,
                    includes,
                    excludes,
                    ...(showFocusControls ? { focus_level: focusLevel } : {}),
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

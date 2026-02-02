// Editorial Planner Dashboard React App
console.log('Editorial Planner JS loaded');

const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;

const EditorialPlannerApp = () => {
    const [sessions, setSessions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState('');
    const [broadFocus, setBroadFocus] = useState('');
    const [currentSession, setCurrentSession] = useState(null);
    const [showEditor, setShowEditor] = useState(false);
    const [sessionData, setSessionData] = useState({
        title: '',
        audience: '',
        angle: '',
        key_messages: '',
        framework: '',
        geo: '',
        tone: '',
        word_count: ''
    });

    useEffect(() => {
        // Check if we have a session_id in URL
        const urlParams = new URLSearchParams(window.location.search);
        const sessionId = urlParams.get('session_id');
        const autoOpen = urlParams.get('auto_open_editor');

        if (sessionId) {
            loadSession(sessionId, autoOpen === '1');
        } else {
            loadSessions();
        }
    }, []);

    const loadSession = async (sessionId, autoOpen = false) => {
        try {
            setLoading(true);
            // Load session from post meta
            const response = await apiFetch({ 
                path: `/wp/v2/planner_session/${sessionId}?context=edit`
            });
            
            setCurrentSession(response);
            setSessionData({
                title: response.title?.rendered || '',
                audience: response.meta?.audience || '',
                angle: response.meta?.angle || '',
                key_messages: response.meta?.key_messages || '',
                framework: response.meta?.framework || '',
                geo: response.meta?.geo || '',
                tone: response.meta?.tone || '',
                word_count: response.meta?.word_count || ''
            });
            
            if (autoOpen) {
                setShowEditor(true);
            }
        } catch (err) {
            console.error('Failed to load session:', err);
            setError('Failed to load session: ' + (err.message || 'Unknown error'));
            loadSessions(); // Fallback to dashboard
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadSessions();
    }, []);

    const loadSessions = async () => {
        try {
            setLoading(true);
            // For now, we'll simulate loading sessions - in reality this would be a REST endpoint
            // that returns recent sessions for the current user
            const response = await apiFetch({ path: '/wp/v2/users/me' }); // Just to test API
            // Placeholder data
            setSessions([
                { id: 'sample-1', created_at: '2024-01-01', status: 'completed', citations: 10 },
                { id: 'sample-2', created_at: '2024-01-02', status: 'running', citations: 5 }
            ]);
        } catch (err) {
            setError('Failed to load sessions');
        } finally {
            setLoading(false);
        }
    };

    const startNewSession = async () => {
        if (!broadFocus.trim()) {
            setError('Please enter a broad focus for the session');
            return;
        }

        console.log('Starting new session with broad_focus:', broadFocus.trim());
        try {
            setStarting(true);
            setError('');
            const requestData = {
                broad_focus: broadFocus.trim(),
                idempotency_key: `session-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
            };
            console.log('Sending request data:', requestData);

            const response = await apiFetch({
                path: '/ep/v1/start',
                method: 'POST',
                data: requestData
            });

            console.log('Response received:', response);
            // Redirect to planner page for this session
            if (response && response.link) {
                // Show brief loading message
                setError('✓ Session created! Opening your Planner...');
                setTimeout(() => {
                    window.location.href = response.link + '&auto_open_editor=1';
                }, 500);
            } else {
                // Fallback if no link returned
                setBroadFocus('');
                setError('');
                loadSessions();
                alert('Session created successfully! ID: ' + (response.id || 'unknown'));
            }
        } catch (err) {
            console.error('Start session error:', err);
            setError('Failed to start session: ' + (err.message || 'Unknown error'));
        } finally {
            setStarting(false);
        }
    };

    if (loading) {
        return wp.element.createElement('div', null, 'Loading sessions...');
    }

    return wp.element.createElement('div', { className: 'editorial-planner-dashboard' },
        wp.element.createElement('h2', null, 'Editorial Planner Dashboard'),
        wp.element.createElement('div', { style: { marginBottom: '20px', padding: '15px', border: '1px solid #ccc' } },
            wp.element.createElement('h3', null, 'Start New Session'),
            wp.element.createElement('div', { style: { marginBottom: '10px' } },
                wp.element.createElement('label', { style: { display: 'block', marginBottom: '5px' } }, 'Broad Focus:'),
                wp.element.createElement('input', {
                    type: 'text',
                    value: broadFocus,
                    onChange: (e) => setBroadFocus(e.target.value),
                    placeholder: 'Enter the broad topic or focus for this planning session',
                    style: { width: '100%', padding: '8px' }
                })
            ),
            wp.element.createElement('button', {
                onClick: startNewSession,
                disabled: starting || !broadFocus.trim(),
                style: { padding: '10px 20px', backgroundColor: starting ? '#ccc' : '#007cba', color: 'white', border: 'none', borderRadius: '4px' }
            }, starting ? 'Starting...' : 'Start New Session')
        ),
        error && wp.element.createElement('div', { style: { color: 'red', marginBottom: '20px', padding: '10px', border: '1px solid red', backgroundColor: '#ffeaea' } }, error),
        wp.element.createElement('h3', null, 'Recent Sessions'),
        wp.element.createElement('table', { className: 'widefat' },
            wp.element.createElement('thead', null,
                wp.element.createElement('tr', null,
                    wp.element.createElement('th', null, 'Session ID'),
                    wp.element.createElement('th', null, 'Created'),
                    wp.element.createElement('th', null, 'Status'),
                    wp.element.createElement('th', null, 'Citations')
                )
            ),
            wp.element.createElement('tbody', null,
                sessions.map(session =>
                    wp.element.createElement('tr', { key: session.id },
                        wp.element.createElement('td', null, session.id),
                        wp.element.createElement('td', null, session.created_at),
                        wp.element.createElement('td', null, session.status),
                        wp.element.createElement('td', null, session.citations)
                    )
                )
            )
        )
    );
};

// Mount the app
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, looking for editorial-planner-app');
    const container = document.getElementById('editorial-planner-app');
    if (container) {
        console.log('Found container, mounting React app');
        wp.element.render(wp.element.createElement(EditorialPlannerApp), container);
    } else {
        console.log('Container not found');
    }
});
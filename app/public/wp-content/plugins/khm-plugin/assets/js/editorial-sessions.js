// Editorial Sessions React App
const { useState, useEffect } = wp.element;
const { Spinner, Notice } = wp.components;

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': dualGptData.nonce,
            ...(options.headers || {}),
        },
    });

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const EditorialSessionsApp = () => {
    const [allSessions, setAllSessions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedSession, setSelectedSession] = useState(null);
    const [sessionDetails, setSessionDetails] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('created_at_desc');
    const [error, setError] = useState('');

    useEffect(() => {
        loadSessions();
    }, []);

    const loadSessions = async () => {
        try {
            setLoading(true);
            setError('');
            const response = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'GET',
            });
            
            if (Array.isArray(response)) {
                setAllSessions(response);
            } else {
                console.log('Session response structure:', response);
                // Handle paginated or nested response structure if needed
                setAllSessions(response.data || response.sessions || []);
            }
        } catch (err) {
            console.error('Failed to load sessions', err);
            setError('Failed to load sessions. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const loadSessionDetails = async (sessionId) => {
        try {
            const response = await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}`,
                method: 'GET',
            });
            setSessionDetails(response);
            setSelectedSession(sessionId);
        } catch (err) {
            console.error('Failed to load session details', err);
            setError('Failed to load session details.');
        }
    };

    // Filter sessions by search term
    const filteredSessions = allSessions.filter(session => {
        const searchLower = searchTerm.toLowerCase();
        const title = (session.title || session.session_id || '').toLowerCase();
        const sessionId = (session.session_id || '').toLowerCase();
        return title.includes(searchLower) || sessionId.includes(searchLower);
    });

    // Sort sessions
    const sortedSessions = [...filteredSessions].sort((a, b) => {
        switch (sortBy) {
            case 'created_at_desc':
                return new Date(b.created_at || 0) - new Date(a.created_at || 0);
            case 'created_at_asc':
                return new Date(a.created_at || 0) - new Date(b.created_at || 0);
            case 'title_asc':
                return (a.title || '').localeCompare(b.title || '');
            case 'title_desc':
                return (b.title || '').localeCompare(a.title || '');
            default:
                return 0;
        }
    });

    if (loading) {
        return wp.element.createElement('div', { style: { padding: '20px', textAlign: 'center' } },
            wp.element.createElement(Spinner, null),
            wp.element.createElement('p', null, 'Loading sessions...')
        );
    }

    return wp.element.createElement('div', { className: 'editorial-sessions', style: { padding: '20px' } },
        wp.element.createElement('h1', null, 'Sessions'),
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: true }, error),
        wp.element.createElement('div', { style: { marginBottom: '20px' } },
            wp.element.createElement('input', {
                type: 'text',
                placeholder: 'Search by title or session ID...',
                value: searchTerm,
                onChange: (e) => setSearchTerm(e.target.value),
                style: {
                    padding: '8px 12px',
                    marginRight: '10px',
                    width: '300px',
                    border: '1px solid #ccc',
                    borderRadius: '4px'
                }
            }),
            wp.element.createElement('select', {
                value: sortBy,
                onChange: (e) => setSortBy(e.target.value),
                style: {
                    padding: '8px 12px',
                    border: '1px solid #ccc',
                    borderRadius: '4px'
                }
            },
                wp.element.createElement('option', { value: 'created_at_desc' }, 'Newest First'),
                wp.element.createElement('option', { value: 'created_at_asc' }, 'Oldest First'),
                wp.element.createElement('option', { value: 'title_asc' }, 'Title A-Z'),
                wp.element.createElement('option', { value: 'title_desc' }, 'Title Z-A')
            )
        ),
        wp.element.createElement('div', { style: { display: 'flex', gap: '20px' } },
            // Sessions list
            wp.element.createElement('div', { style: { flex: 1 } },
                wp.element.createElement('h3', null, `Sessions (${sortedSessions.length})`),
                sortedSessions.length === 0 ? 
                    wp.element.createElement('p', null, 'No sessions found.') :
                    wp.element.createElement('table', { className: 'widefat striped' },
                        wp.element.createElement('thead', null,
                            wp.element.createElement('tr', null,
                                wp.element.createElement('th', null, 'Title'),
                                wp.element.createElement('th', null, 'Created'),
                                wp.element.createElement('th', null, 'Status'),
                                wp.element.createElement('th', null, 'Actions')
                            )
                        ),
                        wp.element.createElement('tbody', null,
                            sortedSessions.map(session =>
                                wp.element.createElement('tr', {
                                    key: session.session_id,
                                    style: { backgroundColor: selectedSession === session.session_id ? '#f5f5f5' : 'transparent' }
                                },
                                    wp.element.createElement('td', null, session.title || session.session_id),
                                    wp.element.createElement('td', null, session.created_at ? formatDate(session.created_at) : '-'),
                                    wp.element.createElement('td', null, session.status || '-'),
                                    wp.element.createElement('td', null,
                                        wp.element.createElement('button', {
                                            className: 'button button-small',
                                            onClick: () => loadSessionDetails(session.session_id),
                                            style: { marginRight: '5px' }
                                        }, 'View Details'),
                                        wp.element.createElement('a', {
                                            href: `?page=editorial_planner&session=${session.session_id}`,
                                            className: 'button button-small'
                                        }, 'Edit')
                                    )
                                )
                            )
                        )
                    )
            ),
            // Session details
            selectedSession && sessionDetails && wp.element.createElement('div', { style: { flex: 1, padding: '15px', border: '1px solid #ccc', borderRadius: '4px' } },
                wp.element.createElement('h3', null, sessionDetails.title || selectedSession),
                wp.element.createElement('div', null,
                    wp.element.createElement('p', null, wp.element.createElement('strong', null, 'Session ID: '), sessionDetails.session_id),
                    wp.element.createElement('p', null, wp.element.createElement('strong', null, 'Status: '), sessionDetails.status || 'Unknown'),
                    sessionDetails.created_at && wp.element.createElement('p', null, wp.element.createElement('strong', null, 'Created: '), formatDate(sessionDetails.created_at)),
                    sessionDetails.meta && sessionDetails.meta.topic && wp.element.createElement('p', null, wp.element.createElement('strong', null, 'Topic: '), sessionDetails.meta.topic)
                )
            )
        )
    );
};

// Mount the app
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-sessions-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialSessionsApp), container);
    }
});
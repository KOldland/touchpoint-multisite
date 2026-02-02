// Editorial Sessions React App
const { useState, useEffect } = wp.element;
const { apiFetch } = wp.apiFetch;

const EditorialSessionsApp = () => {
    const [sessions, setSessions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedSession, setSelectedSession] = useState(null);
    const [sessionDetails, setSessionDetails] = useState(null);

    useEffect(() => {
        loadSessions();
    }, []);

    const loadSessions = async () => {
        try {
            setLoading(true);
            // This would ideally be a REST endpoint that returns all sessions
            // For now, placeholder
            setSessions([
                { id: 'session-1', created_at: '2024-01-01 10:00:00', status: 'completed', citations_count: 15, approved: 12 },
                { id: 'session-2', created_at: '2024-01-02 14:30:00', status: 'waiting_for_human', citations_count: 8, approved: 0 }
            ]);
        } catch (err) {
            console.error('Failed to load sessions', err);
        } finally {
            setLoading(false);
        }
    };

    const loadSessionDetails = async (sessionId) => {
        try {
            const response = await apiFetch({ path: `/ep/v1/session/${sessionId}` });
            setSessionDetails(response);
            setSelectedSession(sessionId);
        } catch (err) {
            console.error('Failed to load session details', err);
        }
    };

    if (loading) {
        return wp.element.createElement('div', null, 'Loading sessions...');
    }

    return wp.element.createElement('div', { className: 'editorial-sessions' },
        wp.element.createElement('h2', null, 'Editorial Sessions'),
        wp.element.createElement('div', { style: { display: 'flex', gap: '20px' } },
            // Sessions list
            wp.element.createElement('div', { style: { flex: 1 } },
                wp.element.createElement('h3', null, 'All Sessions'),
                wp.element.createElement('table', { className: 'widefat' },
                    wp.element.createElement('thead', null,
                        wp.element.createElement('tr', null,
                            wp.element.createElement('th', null, 'Session ID'),
                            wp.element.createElement('th', null, 'Created'),
                            wp.element.createElement('th', null, 'Status'),
                            wp.element.createElement('th', null, 'Citations'),
                            wp.element.createElement('th', null, 'Actions')
                        )
                    ),
                    wp.element.createElement('tbody', null,
                        sessions.map(session =>
                            wp.element.createElement('tr', { key: session.id },
                                wp.element.createElement('td', null, session.id),
                                wp.element.createElement('td', null, session.created_at),
                                wp.element.createElement('td', null, session.status),
                                wp.element.createElement('td', null, `${session.approved}/${session.citations_count}`),
                                wp.element.createElement('td', null,
                                    wp.element.createElement('button', {
                                        onClick: () => loadSessionDetails(session.id)
                                    }, 'View Details')
                                )
                            )
                        )
                    )
                )
            ),
            // Session details
            selectedSession && sessionDetails && wp.element.createElement('div', { style: { flex: 1 } },
                wp.element.createElement('h3', null, `Session ${selectedSession}`),
                wp.element.createElement('p', null, `Status: ${sessionDetails.status || 'Unknown'}`),
                wp.element.createElement('p', null, `Citations: ${sessionDetails.citations ? sessionDetails.citations.length : 0}`),
                sessionDetails.citations && wp.element.createElement('div', null,
                    wp.element.createElement('h4', null, 'Citations'),
                    sessionDetails.citations.map(citation =>
                        wp.element.createElement('div', {
                            key: citation.id,
                            style: { border: '1px solid #ccc', padding: '10px', margin: '5px 0' }
                        },
                            wp.element.createElement('h5', null, citation.title),
                            wp.element.createElement('p', null, citation.relevance_note || citation.passage_snippet)
                        )
                    )
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
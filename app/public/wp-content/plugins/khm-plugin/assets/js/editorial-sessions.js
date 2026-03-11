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
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('created_at_desc');
    const [error, setError] = useState('');
    const [deletingSessionId, setDeletingSessionId] = useState('');

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
                setAllSessions(response.data || response.sessions || []);
            }
        } catch (err) {
            console.error('Failed to load sessions', err);
            setError('Failed to load sessions. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const deleteSession = async (sessionId) => {
        if (!sessionId) {
            return;
        }

        const confirmed = window.confirm('Delete this session permanently? This cannot be undone.');
        if (!confirmed) {
            return;
        }

        try {
            setDeletingSessionId(sessionId);
            setError('');
            await apiFetch({
                path: `dual-gpt/v1/sessions/${sessionId}`,
                method: 'DELETE',
            });

            if (selectedSession === sessionId) {
                setSelectedSession(null);
            }

            await loadSessions();
        } catch (err) {
            console.error('Failed to delete session', err);
            setError(err?.message || 'Failed to delete session. Please try again.');
        } finally {
            setDeletingSessionId('');
        }
    };

    const openPlannerSession = (sessionId) => {
        if (!sessionId) {
            return;
        }
        // Navigate to planner detail page with session_id param
        window.location.href = `?page=editorial_planner&session_id=${sessionId}`;
    };

    const filteredSessions = allSessions.filter((session) => {
        const searchLower = searchTerm.toLowerCase();
        const title = (session.title || session.session_id || '').toLowerCase();
        const sessionId = (session.session_id || '').toLowerCase();
        return title.includes(searchLower) || sessionId.includes(searchLower);
    });

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
        return wp.element.createElement(
            'div',
            { style: { padding: '20px', textAlign: 'center' } },
            wp.element.createElement(Spinner, null),
            wp.element.createElement('p', null, 'Loading sessions...')
        );
    }

    return wp.element.createElement(
        'div',
        { className: 'editorial-sessions', style: { padding: '20px' } },
        wp.element.createElement('h1', null, 'Sessions'),
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: true }, error),
        wp.element.createElement(
            'div',
            { style: { marginBottom: '20px' } },
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
                    borderRadius: '4px',
                },
            }),
            wp.element.createElement(
                'select',
                {
                    value: sortBy,
                    onChange: (e) => setSortBy(e.target.value),
                    style: {
                        padding: '8px 12px',
                        border: '1px solid #ccc',
                        borderRadius: '4px',
                    },
                },
                wp.element.createElement('option', { value: 'created_at_desc' }, 'Newest First'),
                wp.element.createElement('option', { value: 'created_at_asc' }, 'Oldest First'),
                wp.element.createElement('option', { value: 'title_asc' }, 'Title A-Z'),
                wp.element.createElement('option', { value: 'title_desc' }, 'Title Z-A')
            )
        ),
        wp.element.createElement(
            'div',
            { style: { display: 'flex', gap: '20px' } },
            wp.element.createElement(
                'div',
                { style: { flex: 1 } },
                wp.element.createElement('h3', null, `Sessions (${sortedSessions.length})`),
                sortedSessions.length === 0
                    ? wp.element.createElement('p', null, 'No sessions found.')
                    : wp.element.createElement(
                          'table',
                          { className: 'widefat striped' },
                          wp.element.createElement(
                              'thead',
                              null,
                              wp.element.createElement(
                                  'tr',
                                  null,
                                  wp.element.createElement('th', null, 'Title'),
                                  wp.element.createElement('th', null, 'Created'),
                                  wp.element.createElement('th', null, 'Status'),
                                  wp.element.createElement('th', null, 'Actions')
                              )
                          ),
                          wp.element.createElement(
                              'tbody',
                              null,
                              sortedSessions.map((session) => {
                                  const sessionId = session.session_id || session.id;
                                  return wp.element.createElement(
                                      'tr',
                                      {
                                          key: sessionId,
                                          style: {
                                              backgroundColor:
                                                  selectedSession === sessionId ? '#f5f5f5' : 'transparent',
                                          },
                                      },
                                      wp.element.createElement('td', null, session.title || sessionId),
                                      wp.element.createElement(
                                          'td',
                                          null,
                                          session.created_at ? formatDate(session.created_at) : '-'
                                      ),
                                      wp.element.createElement('td', null, session.status || '-'),
                                      wp.element.createElement(
                                          'td',
                                          null,
                                          wp.element.createElement(
                                              'button',
                                              {
                                                  className: 'button button-primary button-small',
                                                  onClick: () => openPlannerSession(sessionId),
                                                  style: { marginRight: '5px' },
                                              },
                                              'VIEW'
                                          ),
                                          wp.element.createElement(
                                              'button',
                                              {
                                                  className: 'button button-small',
                                                  onClick: () => deleteSession(sessionId),
                                                  disabled: deletingSessionId === sessionId,
                                                  style: {
                                                      marginLeft: '5px',
                                                      color: '#b32d2e',
                                                      borderColor: '#b32d2e',
                                                  },
                                              },
                                              deletingSessionId === sessionId ? 'Deleting…' : 'Delete'
                                          )
                                      )
                                  );
                              })
                          )
                      )
            )
        )
    );
};

const mountEditorialSessionsApp = () => {
    const container = document.getElementById('editorial-sessions-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialSessionsApp), container);
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountEditorialSessionsApp);
} else {
    mountEditorialSessionsApp();
}
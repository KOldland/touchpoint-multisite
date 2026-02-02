// Editorial Exports React App
const { useState, useEffect } = wp.element;
const { apiFetch } = wp.apiFetch;

const EditorialExportsApp = () => {
    const [sessions, setSessions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [exporting, setExporting] = useState(false);

    useEffect(() => {
        loadSessions();
    }, []);

    const loadSessions = async () => {
        try {
            setLoading(true);
            // Load sessions that have briefs available for export
            setSessions([
                { id: 'session-1', title: 'Tech Trends 2024', has_brief: true, created_at: '2024-01-01' },
                { id: 'session-2', title: 'Healthcare Innovation', has_brief: true, created_at: '2024-01-02' },
                { id: 'session-3', title: 'Finance Report', has_brief: false, created_at: '2024-01-03' }
            ]);
        } catch (err) {
            console.error('Failed to load sessions', err);
        } finally {
            setLoading(false);
        }
    };

    const exportBrief = async (sessionId, format = 'html') => {
        try {
            setExporting(true);
            // Use the existing export endpoint
            const response = await apiFetch({
                path: `/ep/v1/brief/${sessionId}/export?format=${format}`,
                method: 'GET'
            });

            // In a real implementation, this would trigger a download
            // For now, just show the content
            alert(`Exporting session ${sessionId} as ${format.toUpperCase()}`);
        } catch (err) {
            console.error('Failed to export', err);
            alert('Export failed');
        } finally {
            setExporting(false);
        }
    };

    if (loading) {
        return wp.element.createElement('div', null, 'Loading exportable sessions...');
    }

    return wp.element.createElement('div', { className: 'editorial-exports' },
        wp.element.createElement('h2', null, 'Editorial Exports'),
        wp.element.createElement('p', null, 'Export completed editorial briefs in various formats.'),
        wp.element.createElement('table', { className: 'widefat' },
            wp.element.createElement('thead', null,
                wp.element.createElement('tr', null,
                    wp.element.createElement('th', null, 'Session'),
                    wp.element.createElement('th', null, 'Title'),
                    wp.element.createElement('th', null, 'Created'),
                    wp.element.createElement('th', null, 'Actions')
                )
            ),
            wp.element.createElement('tbody', null,
                sessions.filter(session => session.has_brief).map(session =>
                    wp.element.createElement('tr', { key: session.id },
                        wp.element.createElement('td', null, session.id),
                        wp.element.createElement('td', null, session.title),
                        wp.element.createElement('td', null, session.created_at),
                        wp.element.createElement('td', null,
                            wp.element.createElement('button', {
                                onClick: () => exportBrief(session.id, 'html'),
                                disabled: exporting,
                                style: { marginRight: '5px' }
                            }, 'Export HTML'),
                            wp.element.createElement('button', {
                                onClick: () => exportBrief(session.id, 'docx'),
                                disabled: exporting
                            }, 'Export DOCX')
                        )
                    )
                )
            )
        ),
        sessions.filter(session => !session.has_brief).length > 0 && wp.element.createElement('div', { style: { marginTop: '20px' } },
            wp.element.createElement('h3', null, 'Sessions without briefs yet'),
            sessions.filter(session => !session.has_brief).map(session =>
                wp.element.createElement('div', { key: session.id, style: { padding: '10px', border: '1px solid #ddd', margin: '5px 0' } },
                    `${session.title} (${session.id}) - Still processing...`
                )
            )
        )
    );
};

// Mount the app
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-exports-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialExportsApp), container);
    }
});
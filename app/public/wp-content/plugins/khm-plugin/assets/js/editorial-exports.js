https://appcenter.intuit.com/connect/oauth2?client_id=ABYksPaLcXNSMLcwjmIKchevkqWF6mBqwOHzhI3Yd04akPp2JP&scope=com.intuit.quickbooks.accounting&redirect_uri=http%3A%2F%2Ftouchpoint-multisite.local%2Fwp-json%2Fkhm%2Fv1%2Fqbo%2Foauth%2Fcallback&response_type=code&state=4ufi8yS8LijBNQ7vfe68F6IS// Editorial Exports React App
const { useState, useEffect } = wp.element;
const { apiFetch } = wp.apiFetch;
const { dispatch } = wp.data;

const EditorialExportsApp = () => {
    const [frameworks, setFrameworks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [exporting, setExporting] = useState({});

    useEffect(() => {
        loadFrameworks();
    }, []);

    const loadFrameworks = async () => {
        try {
            setLoading(true);
            // Load all sessions with their frameworks
            const sessions = await apiFetch({
                path: 'dual-gpt/v1/sessions?limit=100',
                method: 'GET',
            });

            // Flatten frameworks with session info
            const allFrameworks = [];
            if (Array.isArray(sessions)) {
                sessions.forEach(session => {
                    if (session.meta?.frameworks && Array.isArray(session.meta.frameworks)) {
                        session.meta.frameworks.forEach(fw => {
                            allFrameworks.push({
                                ...fw,
                                session_id: session.id,
                                session_title: session.title || session.meta?.topic || session.id,
                                session_created: session.created_at
                            });
                        });
                    }
                });
            }
            setFrameworks(allFrameworks);
        } catch (err) {
            console.error('Failed to load frameworks', err);
            dispatch('core/notices').createNotice(
                'error',
                'Failed to load frameworks',
                { type: 'snackbar' }
            );
        } finally {
            setLoading(false);
        }
    };

    const triggerDownload = (data, filename) => {
        const blob = new Blob([data], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    };

    const exportBrief = async (briefId, format = 'html') => {
        try {
            setExporting(prev => ({ ...prev, [briefId]: true }));
            
            const response = await apiFetch({
                path: `fg/v1/export/${briefId}?format=${format}`,
                method: 'GET'
            });

            if (response && response.file_url) {
                // Trigger download by navigating to the file URL
                window.location.href = response.file_url;
                
                dispatch('core/notices').createNotice(
                    'success',
                    `Framework exported as ${format.toUpperCase()}`,
                    { type: 'snackbar' }
                );
            } else {
                throw new Error('No download URL returned');
            }
        } catch (err) {
            console.error('Failed to export', err);
            dispatch('core/notices').createNotice(
                'error',
                'Export failed: ' + (err.message || 'Unknown error'),
                { type: 'snackbar' }
            );
        } finally {
            setExporting(prev => ({ ...prev, [briefId]: false }));
        }
    };

    if (loading) {
        return wp.element.createElement('div', null, 'Loading frameworks...');
    }

    if (frameworks.length === 0) {
        return wp.element.createElement('div', { className: 'editorial-exports' },
            wp.element.createElement('h2', null, 'Editorial Frameworks'),
            wp.element.createElement('p', null, 'No frameworks available for export yet. Generate frameworks from the Planner tab.'),
            wp.element.createElement('a', {
                href: '#editorial_planner',
                style: { color: '#0073aa', textDecoration: 'none' }
            }, 'Go to Planner →')
        );
    }

    return wp.element.createElement('div', { className: 'editorial-exports' },
        wp.element.createElement('h2', null, 'Editorial Frameworks'),
        wp.element.createElement('p', null, 'Export generated editorial frameworks in various formats.'),
        wp.element.createElement('table', { className: 'widefat' },
            wp.element.createElement('thead', null,
                wp.element.createElement('tr', null,
                    wp.element.createElement('th', null, 'Framework'),
                    wp.element.createElement('th', null, 'Session'),
                    wp.element.createElement('th', null, 'Created'),
                    wp.element.createElement('th', null, 'Actions')
                )
            ),
            wp.element.createElement('tbody', null,
                frameworks.map(fw =>
                    wp.element.createElement('tr', { key: fw.id },
                        wp.element.createElement('td', null, fw.title || fw.id),
                        wp.element.createElement('td', null, fw.session_title),
                        wp.element.createElement('td', null, fw.session_created || '—'),
                        wp.element.createElement('td', null,
                            wp.element.createElement('button', {
                                onClick: () => exportBrief(fw.id, 'html'),
                                disabled: exporting[fw.id],
                                style: { marginRight: '5px' }
                            }, exporting[fw.id] ? 'Exporting...' : 'Export HTML'),
                            wp.element.createElement('button', {
                                onClick: () => exportBrief(fw.id, 'docx'),
                                disabled: exporting[fw.id],
                                style: { marginRight: '5px' }
                            }, exporting[fw.id] ? 'Exporting...' : 'Export DOCX'),
                            wp.element.createElement('button', {
                                onClick: () => exportBrief(fw.id, 'pdf'),
                                disabled: exporting[fw.id]
                            }, exporting[fw.id] ? 'Exporting...' : 'Export PDF')
                        )
                    )
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
// Editorial Frameworks React App with Export Functionality
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

const EditorialFrameworksApp = () => {
    const [frameworks, setFrameworks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [exporting, setExporting] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        loadFrameworks();
    }, []);

    const loadFrameworks = async () => {
        try {
            setLoading(true);
            setError('');
            const response = await apiFetch({
                path: 'dual-gpt/v1/sessions',
                method: 'GET',
            });
            
            const sessionsList = Array.isArray(response) ? response : response.data || response.sessions || [];
            
            // Flatten frameworks from all sessions
            const allFrameworks = [];
            sessionsList.forEach(session => {
                if (session.briefs && Array.isArray(session.briefs)) {
                    session.briefs.forEach(brief => {
                        allFrameworks.push({
                            id: brief.brief_id,
                            title: brief.title || `Brief ${brief.brief_id}`,
                            session_id: session.session_id,
                            brief_id: brief.brief_id,
                            status: brief.status,
                            created_at: brief.created_at || session.created_at
                        });
                    });
                }
            });
            
            setFrameworks(allFrameworks);
        } catch (err) {
            console.error('Failed to load frameworks', err);
            setError('Failed to load frameworks. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleExport = async (framework, format) => {
        try {
            setExporting(`${framework.brief_id}-${format}`);
            setError('');

            const response = await apiFetch({
                path: `fg/v1/export/${framework.brief_id}?format=${format}`,
                method: 'GET',
            });

            if (response && response.file_url) {
                // Trigger download by navigating to the file URL
                window.location.href = response.file_url;

                // Show success notice
                if (wp.data && wp.data.dispatch) {
                    wp.data.dispatch('core/notices').createNotice(
                        'success',
                        `Successfully exported framework as ${format.toUpperCase()}.`,
                        { type: 'snackbar' }
                    );
                }
            } else {
                throw new Error('No download URL returned from export API');
            }
        } catch (err) {
            console.error(`Failed to export framework as ${format}:`, err);
            setError(`Failed to export as ${format}. Please try again.`);
        } finally {
            setExporting(null);
        }
    };

    if (loading) {
        return wp.element.createElement('div', { style: { padding: '20px', textAlign: 'center' } },
            wp.element.createElement(Spinner, null),
            wp.element.createElement('p', null, 'Loading frameworks...')
        );
    }

    return wp.element.createElement('div', { className: 'editorial-frameworks', style: { padding: '20px' } },
        wp.element.createElement('h1', null, 'Article Frameworks'),
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: true }, error),
        
        wp.element.createElement('div', null,
            frameworks.length === 0 ?
                wp.element.createElement('p', null, 'No frameworks available. Create a planning session to generate frameworks.') :
                wp.element.createElement('div', null,
                    wp.element.createElement('h2', null, `Frameworks (${frameworks.length})`),
                    frameworks.map(framework =>
                        wp.element.createElement('div', {
                            key: framework.brief_id,
                            style: {
                                border: '1px solid #ddd',
                                padding: '15px',
                                margin: '10px 0',
                                borderRadius: '4px',
                                backgroundColor: '#f9f9f9'
                            }
                        },
                            wp.element.createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                                wp.element.createElement('div', null,
                                    wp.element.createElement('h3', { style: { margin: '0 0 5px 0' } }, framework.title),
                                    wp.element.createElement('small', null,
                                        `ID: ${framework.brief_id} | `,
                                        `Status: ${framework.status || 'unknown'} | `,
                                        `Created: ${framework.created_at ? new Date(framework.created_at).toLocaleDateString() : 'N/A'}`
                                    )
                                ),
                                wp.element.createElement('div', { style: { display: 'flex', gap: '10px' } },
                                    wp.element.createElement('button', {
                                        className: 'button button-small',
                                        onClick: () => handleExport(framework, 'html'),
                                        disabled: exporting === `${framework.brief_id}-html`,
                                        style: { whiteSpace: 'nowrap' }
                                    }, exporting === `${framework.brief_id}-html` ? 'Exporting...' : 'Export HTML'),
                                    wp.element.createElement('button', {
                                        className: 'button button-small',
                                        onClick: () => handleExport(framework, 'docx'),
                                        disabled: exporting === `${framework.brief_id}-docx`,
                                        style: { whiteSpace: 'nowrap' }
                                    }, exporting === `${framework.brief_id}-docx` ? 'Exporting...' : 'Export DOCX'),
                                    wp.element.createElement('button', {
                                        className: 'button button-small',
                                        onClick: () => handleExport(framework, 'pdf'),
                                        disabled: exporting === `${framework.brief_id}-pdf`,
                                        style: { whiteSpace: 'nowrap' }
                                    }, exporting === `${framework.brief_id}-pdf` ? 'Exporting...' : 'Export PDF')
                                )
                            )
                        )
                    )
                )
        )
    );
};

// Mount the app
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('editorial-frameworks-app');
    if (container) {
        wp.element.render(wp.element.createElement(EditorialFrameworksApp), container);
    }
});
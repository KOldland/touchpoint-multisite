const { Button, Spinner, Modal, SelectControl, FormTokenField, Notice, Card, CardHeader, CardBody } = wp.components;
const { createElement } = wp.element;

export const SessionsDashboardList = ({
    sessions,
    loadingSessions,
    sessionsError,
    deletingSessionId,
    startModalOpen,
    selectedTopic,
    topicOptions,
    selectedPillar,
    pillarOptions,
    includes,
    excludes,
    starting,
    setStartModalOpen,
    setSelectedTopic,
    setSelectedPillar,
    setPillarOptions,
    setIncludes,
    setExcludes,
    navigateToNewSession,
    navigateToSession,
    deleteSession,
    startNewSession
}) => {
    return createElement('div', null,
        createElement('div', { className: 'editorial-planner-dashboard' },
            createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                createElement('h1', null, 'Article Planner'),
                createElement(Button, { isPrimary: true, onClick: () => setStartModalOpen(true) }, 'Start New Session')
            ),
            sessionsError && createElement(Notice, { status: 'error', isDismissible: false }, sessionsError),
            loadingSessions
                ? createElement(Spinner)
                : createElement(Card, { style: { marginTop: '16px' } },
                    createElement(CardHeader, null, 'Recent Sessions'),
                    createElement(CardBody, null,
                        sessions.length > 0
                            ? createElement('table', { className: 'widefat striped' },
                                createElement('thead', null,
                                    createElement('tr', null,
                                        ['Magazine', 'Editorial Pillar', 'Articles', 'Created', 'Actions'].map(h =>
                                            createElement('th', { key: h }, h)
                                        )
                                    )
                                ),
                                createElement('tbody', null,
                                    sessions.map((session) =>
                                        createElement('tr', { key: session.id },
                                            createElement('td', null, session.title || session.meta?.topic || session.id),
                                            createElement('td', null, session.pillar || session.meta?.pillar || '—'),
                                            createElement('td', null, String(session.article_count ?? 0)),
                                            createElement('td', null, session.created_at || '—'),
                                            createElement('td', null,
                                                createElement(Button, {
                                                    isSecondary: true,
                                                    onClick: () => navigateToSession(session.id),
                                                    style: { marginRight: '5px' },
                                                }, 'View'),
                                                createElement(Button, {
                                                    isSecondary: true,
                                                    onClick: () => deleteSession(session.id),
                                                    disabled: deletingSessionId === session.id,
                                                    style: { color: '#b32d2e', borderColor: '#b32d2e' },
                                                }, deletingSessionId === session.id ? 'Deleting…' : 'Delete')
                                            )
                                        )
                                    )
                                )
                            )
                            : createElement('p', null, 'No planning sessions yet — start your first above now!')
                    )
                )
        ),
        startModalOpen && createElement(Modal, { title: 'Start a New Planning Session', onRequestClose: () => setStartModalOpen(false) },
            createElement(SelectControl, {
                label: 'Top-line Topic',
                value: selectedTopic,
                options: topicOptions,
                onChange: (value) => {
                    setSelectedTopic(value);
                    const category = topicOptions.find(o => o.value === value);
                    if (category && category.pillars && category.pillars.length) {
                        setPillarOptions(category.pillars.map(p => ({ label: p.name, value: p.name })));
                    } else {
                        setPillarOptions([]);
                    }
                    setSelectedPillar(null);
                },
            }),
            pillarOptions.length > 0 && createElement(SelectControl, {
                label: 'Editorial Pillar (optional)',
                value: selectedPillar || '',
                options: [{ label: 'All pillars', value: '' }, ...pillarOptions],
                onChange: setSelectedPillar,
                help: 'Narrow research to a specific editorial pillar within this topic.',
            }),
            createElement(FormTokenField, {
                label: 'Includes',
                value: includes,
                onChange: setIncludes,
                placeholder: 'Add include terms',
            }),
            createElement(FormTokenField, {
                label: 'Excludes',
                value: excludes,
                onChange: setExcludes,
                placeholder: 'Add exclude terms',
            }),
            createElement('div', { style: { marginTop: '16px', display: 'flex', justifyContent: 'flex-end' } },
                createElement(Button, { isSecondary: true, onClick: () => setStartModalOpen(false) }, 'Cancel'),
                createElement(Button, {
                    isPrimary: true,
                    onClick: startNewSession,
                    disabled: starting,
                    style: { marginLeft: '8px' },
                }, starting ? createElement(Spinner) : 'Start New Session')
            )
        )
    );
};

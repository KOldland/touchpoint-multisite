const { Card, CardHeader, CardBody, Button, Spinner, Notice } = wp.components;
import { TrendBlocks } from '../ModalViewerHub.js';

export const PhaseCard = ({ 
    phaseKey, 
    phase, 
    isExpanded, 
    onToggle, 
    handleRerunPhase1, 
    handleRerunPhase2, 
    handleRerunPhase3, 
    handleRerunPhase4, 
    openSynopsisModal, 
    isLoadingPhase1,
    isLoadingPhase2, 
    isLoadingPhase3, 
    isLoadingPhase4, 
    phase3Complete, 
    phase4Complete,
    hasProviderErrors,
    providerAdminInstruction,
    serpapiIssue
}) => {
    const phaseTitleOverrides = {
        phase1: 'Research Phase 1',
        phase2: 'Research Phase 2',
        phase3: 'Research Phase 3',
        phase4: 'Research Phase 4',
    };

    const { createElement } = wp.element;

    return createElement(
        Card,
        { style: { marginBottom: '12px' } },
        createElement(CardHeader, null, phaseTitleOverrides[phaseKey] || phase?.title || phaseKey),
        createElement(CardBody, null,
            createElement(Button, { isSecondary: true, onClick: onToggle, style: { marginTop: '2px' } },
                isExpanded ? 'Hide details' : 'View details'
            ),
            isExpanded && createElement('div', { style: { marginTop: '12px' } },
                phaseKey === 'phase4' && hasProviderErrors && createElement(
                    Notice,
                    { status: 'error', isDismissible: false, style: { marginBottom: '12px' } },
                    createElement('p', { style: { margin: 0, fontWeight: 600 } },
                        serpapiIssue ? 'SerpAPI Failure Detected' : 'Search Provider Error'
                    ),
                    createElement('p', { style: { margin: '4px 0 0', fontSize: '12px', lineHeight: 1.4 } },
                        providerAdminInstruction
                    )
                ),
                // Phase 1: Show trends
                phaseKey === 'phase1' && phase?.payload?.trends && createElement('div', { style: { marginTop: '8px' } },
                    createElement('h2', { style: { margin: '0 0 6px' } }, 'Trends and Highlights'),
                    createElement(TrendBlocks, { trends: phase.payload.trends })
                ),
                // Phase 2: Show ranked keywords
                phaseKey === 'phase2' && phase?.payload?.ranked_keywords && createElement('div', { style: { marginTop: '8px' } },
                    createElement('h2', { style: { margin: '0 0 6px' } }, 'Ranked Keywords'),
                    createElement('table', { style: { width: '100%', borderCollapse: 'collapse' } },
                        createElement('thead', null,
                            createElement('tr', null,
                                createElement('th', { style: { textAlign: 'left', borderBottom: '1px solid #ddd', padding: '8px' } }, 'Keyword'),
                                createElement('th', { style: { textAlign: 'right', borderBottom: '1px solid #ddd', padding: '8px' } }, 'Volume'),
                                createElement('th', { style: { textAlign: 'right', borderBottom: '1px solid #ddd', padding: '8px' } }, 'Difficulty'),
                                createElement('th', { style: { textAlign: 'right', borderBottom: '1px solid #ddd', padding: '8px' } }, 'Priority')
                            )
                        ),
                        createElement('tbody', null,
                            phase.payload.ranked_keywords.map((kw, idx) =>
                                createElement('tr', { key: idx, style: { borderBottom: '1px solid #eee' } },
                                    createElement('td', { style: { padding: '8px' } }, kw.keyword ?? kw.key ?? ''),
                                    createElement('td', { style: { textAlign: 'right', padding: '8px' } }, kw.search_volume ?? '-'),
                                    createElement('td', { style: { textAlign: 'right', padding: '8px' } }, kw.difficulty ?? '-'),
                                    createElement('td', { style: { textAlign: 'right', padding: '8px' } }, kw.priority_score ?? '-')
                                )
                            )
                        )
                    )
                ),
                // Phase 3: Show prioritized topics
                phaseKey === 'phase3' && phase?.payload?.prioritized_topics && createElement('div', { style: { marginTop: '8px' } },
                    createElement('h2', { style: { margin: '0 0 6px' } }, 'Prioritized Topics'),
                    createElement('div', null,
                        phase.payload.prioritized_topics.map((topic, idx) =>
                            createElement('div', { key: idx, style: { marginBottom: '12px', padding: '8px', background: '#f9f9f9', borderRadius: '4px' } },
                                createElement('h3', { style: { margin: '0 0 4px' } }, topic.topic ?? ''),
                                createElement('p', { style: { margin: '4px 0', color: '#666' } }, topic.why_now ?? ''),
                                createElement('p', { style: { margin: '4px 0' } }, 'Key Findings:'),
                                createElement('ul', { style: { margin: '4px 0' } },
                                    (topic.key_findings || []).map((finding, fidx) =>
                                        createElement('li', { key: fidx }, finding)
                                    )
                                ),
                                createElement('p', { style: { margin: '4px 0' } }, 'Keywords: ' + (topic.keywords || []).join(', '))
                            )
                        )
                    )
                ),
                // Phase 4: Show validated topics
                phaseKey === 'phase4' && phase?.payload?.validated_topics && createElement('div', { style: { marginTop: '8px' } },
                    createElement('h2', { style: { margin: '0 0 6px' } }, 'Validated Topics'),
                    createElement('div', null,
                        phase.payload.validated_topics.map((topic, idx) =>
                            createElement('div', { key: idx, style: { marginBottom: '12px', padding: '8px', background: '#f9f9f9', borderRadius: '4px' } },
                                createElement('h3', { style: { margin: '0 0 4px' } }, topic.topic ?? ''),
                                createElement('p', { style: { margin: '4px 0' } }, 'Confidence: ' + (topic.confidence_score ?? 0)),
                                createElement('p', { style: { margin: '4px 0' } }, topic.reason ?? ''),
                                createElement('p', { style: { margin: '4px 0' } }, 'Supporting Citations: ' + (topic.supporting_citations || []).length)
                            )
                        )
                    )
                )
            ),
            isExpanded && phase?.payload?.next_step_question && createElement('div', { style: { marginTop: '8px' } },
                createElement('p', { style: { fontStyle: 'italic', marginBottom: '6px' } },
                    phase.payload.next_step_question
                ),
                phaseKey === 'phase1' && createElement(
                    Button,
                    { isSecondary: true, onClick: handleRerunPhase2, disabled: isLoadingPhase2 },
                    isLoadingPhase2 ? createElement(Spinner) : 'Run Research Phase 2'
                ),
                phaseKey === 'phase2' && createElement(
                    Button,
                    { isSecondary: true, onClick: handleRerunPhase3, disabled: isLoadingPhase3 },
                    isLoadingPhase3 ? createElement(Spinner) : 'Run Research Phase 3'
                ),
                phaseKey === 'phase3' && createElement(
                    Button,
                    { isSecondary: true, onClick: handleRerunPhase4, disabled: isLoadingPhase4 || !phase3Complete },
                    isLoadingPhase4 ? createElement(Spinner) : 'Run Research Phase 4'
                ),
                phaseKey === 'phase4' && createElement(
                    Button,
                    { isPrimary: true, onClick: () => openSynopsisModal(), disabled: !phase4Complete || hasProviderErrors },
                    'Generate Article Synopses'
                )
            )
        )
    );
};

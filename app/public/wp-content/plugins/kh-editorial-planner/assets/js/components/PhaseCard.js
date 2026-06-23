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
                phase?.payload?.trends && createElement('div', { style: { marginTop: '8px' } },
                    createElement('h2', { style: { margin: '0 0 6px' } }, 'Trends and Highlights'),
                    createElement(TrendBlocks, { trends: phase.payload.trends })
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
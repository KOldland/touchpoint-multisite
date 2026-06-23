const { createElement } = wp.element;
const { Button, Spinner, ProgressBar, Modal, Notice } = wp.components;

export const ArticleModalsHub = ({
    // Modals visibility states
    diveDeeperModalOpen,
    previewArticle,
    frameworkPreview,
    authorPreview,
    
    // Data elements
    THINKING_PHRASES,
    thinkingPhraseIndex,
    diveDeeperSuccess,
    isDiveDeeperWorking,
    diveDeeperStageMeta,
    diveDeeperStageKey,
    diveDeeperElapsedSeconds,
    isDiveDeeperStalled,
    isDeepDiveLoading,
    diveDeeperQueueLoading,
    
    // Handlers
    closeDiveDeeperModal,
    handleDiveDeeperRetry,
    handleDiveDeeperQueueSubmit,
    handleDiveDeeperSubmit,
    setPreviewArticle,
    setFrameworkPreview,
    setAuthorPreview,
    handleExportFramework,
    handleRunAuthorAgent,
    handleExportAuthorDraft,
    
    // Utility Profile Mappers
    getAuthorProfileLabel,
    getSelectedAuthorProfile,
    getRecommendedAuthorProfile,
    blocksToHTML,
    diveDeeperJobError
}) => {
    return createElement(
        wp.element.Fragment,
        null,
        
        // ==========================================
        // 1. DIVE DEEPER MODAL
        // ==========================================
        diveDeeperModalOpen && createElement(
            Modal,
            {
                title: 'Dive Deeper - Research Depth',
                onRequestClose: closeDiveDeeperModal,
            },
            diveDeeperSuccess
                ? createElement('div', { style: { textAlign: 'center', padding: '32px 24px' } },
                      createElement('div', { style: { fontSize: '48px', lineHeight: 1, marginBottom: '12px' } }, '✓'),
                      createElement('p', { style: { fontSize: '16px', fontWeight: '600', color: '#1e7e34', margin: '0 0 8px' } }, 'Source-check complete'),
                      createElement('p', { style: { fontSize: '13px', color: '#666', margin: 0 } }, 'Supporting evidence has been processed for this article. You can now review updated citations.')
                  )
                : isDiveDeeperWorking
                ? createElement('div', { style: { textAlign: 'center', padding: '32px 24px' } },
                      createElement(Spinner),
                      createElement('p', { style: { marginTop: '16px', fontSize: '14px', color: '#666' } }, `${diveDeeperStageMeta.label} · ${THINKING_PHRASES[thinkingPhraseIndex]}...`),
                      createElement('div', { style: { marginTop: '14px', marginBottom: '10px' } }, createElement(ProgressBar, { value: diveDeeperStageMeta.progress })),
                      createElement('p', { style: { marginTop: '8px', fontSize: '12px', color: '#666' } }, diveDeeperStageMeta.detail),
                      createElement('p', { style: { marginTop: '6px', fontSize: '12px', color: '#999' } }, `Status: ${diveDeeperStageKey}${diveDeeperElapsedSeconds > 0 ? ` · ${diveDeeperElapsedSeconds}s elapsed` : ''}`),
                      isDiveDeeperStalled && createElement(Notice, { status: 'warning', isDismissible: false },
                          createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px' } },
                              createElement('span', null, 'This job has been queued longer than expected. You can retry now.'),
                              createElement(Button, { isSecondary: true, onClick: handleDiveDeeperRetry, disabled: isDeepDiveLoading }, isDeepDiveLoading ? createElement(Spinner) : 'Retry')
                          )
                      )
                  )
                : createElement('div', { style: { marginBottom: '24px' } },
                      diveDeeperJobError && createElement(Notice, { status: 'error', isDismissible: false }, diveDeeperJobError),
                      createElement('p', { style: { marginBottom: '16px', color: '#50575e' } }, 'The system will automatically find additional citations to bring the total to at least 4 per article.'),
                      createElement('div', { style: { marginTop: '24px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                          createElement(Button, { isSecondary: true, onClick: closeDiveDeeperModal }, 'Cancel'),
                          createElement(Button, { isSecondary: true, onClick: () => handleDiveDeeperQueueSubmit(), disabled: isDiveDeeperWorking || diveDeeperQueueLoading }, diveDeeperQueueLoading ? createElement(Spinner) : 'Add to Queue'),
                          createElement(Button, { isPrimary: true, onClick: () => handleDiveDeeperSubmit(), disabled: isDiveDeeperWorking || diveDeeperQueueLoading }, isDiveDeeperWorking ? createElement(Spinner) : 'Run Now')
                      )
                  )
        ),

        // ==========================================
        // 2. ARTICLE SYNOPSIS PREVIEW MODAL
        // ==========================================
        previewArticle && createElement(
            Modal,
            {
                title: previewArticle.headline || previewArticle.title || 'Article Preview',
                onRequestClose: () => setPreviewArticle(null),
                isDismissible: true,
                shouldCloseOnClickOutside: false,
            },
            createElement('p', null, previewArticle.summary || previewArticle.brief || previewArticle.summary_two_sentences || 'No summary.'),
            previewArticle.key_points && previewArticle.key_points.length && createElement('div', { style: { marginTop: '12px' } },
                createElement('strong', null, 'Key Points'),
                createElement('ul', null, previewArticle.key_points.map((point, idx) => createElement('li', { key: idx }, point)))
            ),
            (previewArticle.keywords || previewArticle.tags) && (previewArticle.keywords || previewArticle.tags).length && createElement('p', { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } }, `Keywords: ${(previewArticle.keywords || previewArticle.tags).join(', ')}`),
            previewArticle.recommended_word_count && createElement('p', { style: { marginTop: '8px', fontSize: '12px', color: '#50575e' } }, `Recommended word count: ${previewArticle.recommended_word_count}`),
            previewArticle.topic_coverage_level && createElement('p', { style: { marginTop: '4px', fontSize: '12px', color: '#50575e' } }, `Topic coverage level: ${previewArticle.topic_coverage_level}`),
            previewArticle.citations && previewArticle.citations.length > 0 && createElement('div', { style: { marginTop: '12px' } },
                createElement('strong', null, 'Supporting Citations'),
                createElement('ul', null, previewArticle.citations.map((citation, idx) => createElement('li', { key: idx }, typeof citation === 'string' ? citation : citation.title || citation.url || 'Citation')))
            )
        ),

        // ==========================================
        // 3. FRAMEWORK PREVIEW MODAL
        // ==========================================
        frameworkPreview && createElement(
            Modal,
            {
                title: 'Framework Preview',
                onRequestClose: () => setFrameworkPreview(null),
                isDismissible: true,
                shouldCloseOnClickOutside: false,
            },
            createElement('div', { style: { marginBottom: '12px', display: 'flex', gap: '8px' } },
                createElement(Button, { isSecondary: true, onClick: () => handleExportFramework(frameworkPreview), disabled: !(frameworkPreview?.framework?.output) }, 'Export Framework'),
                createElement(Button, { isSecondary: true, onClick: () => handleRunAuthorAgent(frameworkPreview), disabled: !(frameworkPreview?.framework?.output) }, 'Run Author Agent')
            ),
            createElement('p', null, frameworkPreview.title || 'Framework'),
            frameworkPreview.framework?.output?.title && createElement('div', { style: { marginTop: '12px' } },
                createElement('h3', null, frameworkPreview.framework.output.title),
                frameworkPreview.framework.output.overview && createElement('div', { style: { marginTop: '8px' } },
                    createElement('strong', null, 'Overview'),
                    createElement('p', null, frameworkPreview.framework.output.overview)
                ),
                frameworkPreview.framework.output.context && createElement('div', { style: { marginTop: '8px' } },
                    createElement('strong', null, 'Context'),
                    createElement('p', null, frameworkPreview.framework.output.context)
                ),
                frameworkPreview.framework.output.application && createElement('div', { style: { marginTop: '8px' } },
                    createElement('strong', null, 'Application'),
                    createElement('p', null, frameworkPreview.framework.output.application.intended_reader ? `Intended Reader: ${frameworkPreview.framework.output.application.intended_reader}` : null),
                    createElement('p', null, frameworkPreview.framework.output.application.use_case ? `Use Case: ${frameworkPreview.framework.output.application.use_case}` : null)
                ),
                frameworkPreview.framework.output.observations && frameworkPreview.framework.output.observations.length > 0 && createElement('div', { style: { marginTop: '8px' } },
                    createElement('strong', null, 'Observations'),
                    createElement('ul', null, frameworkPreview.framework.output.observations.map((item, idx) =>
                        createElement('li', { key: idx },
                            createElement('strong', null, item.headline || 'Observation'),
                            createElement('p', null, item.detail || '')
                        )
                    ))
                ),
                frameworkPreview.framework.output.key_themes && frameworkPreview.framework.output.key_themes.length > 0 && createElement('div', { style: { marginTop: '8px' } },
                    createElement('strong', null, 'Key Themes'),
                    createElement('ul', null, frameworkPreview.framework.output.key_themes.map((theme, idx) => createElement('li', { key: idx }, theme)))
                )
            ),
            (!frameworkPreview.framework?.output?.title && frameworkPreview.framework?.output?.h2_sections) && createElement('div', { style: { marginTop: '12px' } },
                createElement('strong', null, 'Framework'),
                createElement('ul', null, frameworkPreview.framework.output.h2_sections.map((section, idx) =>
                    createElement('li', { key: idx },
                        section.title || 'Section',
                        (section.h3_sections && section.h3_sections.length) ? createElement('ul', null, section.h3_sections.map((h3, h3Idx) => createElement('li', { key: h3Idx }, h3))) : null
                    )
                ))
            ),
            (frameworkPreview.framework?.output?.citations && frameworkPreview.framework.output.citations.length > 0) && createElement('div', { style: { marginTop: '12px' } },
                createElement('strong', null, `Citations (${frameworkPreview.framework.output.citations.length} sources)`),
                createElement('ul', { style: { listStyle: 'none', padding: 0 } }, frameworkPreview.framework.output.citations.map((citation, idx) =>
                    createElement('li', { key: idx, style: { marginBottom: '10px', padding: '8px 10px', border: '1px solid #f0f0f1', borderRadius: '4px', background: '#fafafa' } },
                        citation.apa && createElement('p', { style: { fontStyle: 'italic', margin: '0 0 4px' } }, citation.apa),
                        (!citation.apa && citation.title) && createElement('p', { style: { fontWeight: 'bold', margin: '0 0 2px' } }, citation.title),
                        citation.url && createElement('p', { style: { margin: '0 0 4px' } }, createElement('a', { href: citation.url, target: '_blank', rel: 'noopener' }, citation.url)),
                        (citation.lead_author || citation.organisation || citation.publication_date) && createElement('p', { style: { fontSize: '12px', color: '#50575e', margin: '0 0 4px' } }, [citation.lead_author, citation.organisation, citation.publication_date].filter(Boolean).join(' · ')),
                        citation.passage_snippet && createElement('p', { style: { fontSize: '12px', color: '#787c82', margin: '0 0 2px', fontStyle: 'italic' } }, `"${citation.passage_snippet}"`),
                        citation.relevance && createElement('p', { style: { fontSize: '12px', color: '#50575e', margin: '2px 0 0' } }, 'Relevance: ' + citation.relevance)
                    )
                ))
            )
        ),

        // ==========================================
        // 4. AUTHOR DRAFT PREVIEW MODAL
        // ==========================================
        authorPreview && createElement(
            Modal,
            {
                title: 'Author Draft',
                onRequestClose: () => setAuthorPreview(null),
                isDismissible: true,
                shouldCloseOnClickOutside: false,
            },
            createElement('div', { style: { marginBottom: '12px', display: 'flex', gap: '8px', flexWrap: 'wrap' } },
                createElement(Button, { isSecondary: true, onClick: () => handleExportAuthorDraft(authorPreview), disabled: !(authorPreview?.author?.output) }, 'Export Draft'),
                createElement(Button, { isSecondary: true, onClick: () => authorPreview?.author?.edit_url && window.open(authorPreview.author.edit_url, '_blank'), disabled: !(authorPreview?.author?.edit_url) }, 'Open in Editor')
            ),
            createElement('div', { style: { marginBottom: '12px', padding: '10px 12px', border: '1px solid #dcdcde', borderRadius: '6px', background: '#fff' } },
                createElement('strong', null, authorPreview.title || 'Draft'),
                createElement('p', { style: { margin: '6px 0 0', color: '#50575e' } }, `Profile: ${getAuthorProfileLabel(getSelectedAuthorProfile(authorPreview))} · Recommended: ${getAuthorProfileLabel(getRecommendedAuthorProfile(authorPreview))}`)
            ),
            createElement('div', {
                style: { maxHeight: '60vh', overflowY: 'auto', padding: '12px', border: '1px solid #dcdcde', borderRadius: '6px', background: '#fff', lineHeight: '1.7', color: '#1d2327' },
                dangerouslySetInnerHTML: { __html: blocksToHTML(authorPreview.author?.output?.blocks || []) || 'No draft available.' }
            })
        )
    );
};
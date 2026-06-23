const { useState } = wp.element;
const { Card, CardHeader, CardBody, Button } = wp.components;
const { createElement } = wp.element;

const ResearchPolicyPanel = ({ sessionDetail, onReanalyseGaps }) => {
    const [expandedPillars, setExpandedPillars] = useState({});

    const togglePillar = (slug) => {
        setExpandedPillars((prev) => ({ ...prev, [slug]: !prev[slug] }));
    };

    const handleExportCsv = (pillar) => {
        const pillarSlug = pillar.pillar_slug || pillar.pillar || 'unknown';
        const allStats = Array.isArray(pillar.stats) ? pillar.stats : [];
        
        const csv = [
            ['term', 'hits', 'latest'],
            ...allStats.map(s => [
                `"${String(s.term || '').replace(/"/g, '""')}"`, 
                String(s.hits || 0), 
                `"${String(s.latest || '').replace(/"/g, '""')}"`
            ])
        ].map(row => row.join(',')).join('\n');
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `content-gaps-${pillarSlug}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const internalCoverage =
        sessionDetail?.meta?.phases?.phase1?.payload?.internal_coverage ||
        sessionDetail?.meta?.phase1_result?.internal_coverage ||
        sessionDetail?.meta?.internal_coverage ||
        sessionDetail?.meta?.phases?.phase1?.internal_coverage_calculated ||
        null;
        
    if (!internalCoverage) {
        return null;
    }
    
    const totalPosts = internalCoverage.summary || 'No coverage data.';
    const pillars = Array.isArray(internalCoverage.gaps_by_pillar) ? internalCoverage.gaps_by_pillar : [];
    
    if (pillars.length === 0) {
        return null;
    }
    
    return createElement(Card, { style: { marginBottom: '12px', marginTop: '16px' } },
        createElement(CardHeader, null,
            createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' } },
                createElement('span', null, 'Content Gaps by Pillar'),
                createElement(Button, { isSecondary: true, style: { fontSize: '11px' }, onClick: onReanalyseGaps }, 'Re-analyse Gaps')
            )
        ),
        createElement(CardBody, null,
            createElement('p', { style: { margin: '0 0 8px', fontSize: '12px', color: '#50575e' } }, totalPosts),
            pillars.map((pillar, idx) => {
                const gapTerms = Array.isArray(pillar.gaps) ? pillar.gaps : [];
                const allStats = Array.isArray(pillar.stats) ? pillar.stats : [];
                const pillarKey = pillar.pillar_slug || pillar.pillar || `pillar-${idx}`;
                const isExpanded = !!expandedPillars[pillarKey];
                
                return createElement('div', { key: `gap-pillar-${idx}`, style: { marginBottom: '12px' } },
                    createElement('div', {
                        style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px', cursor: 'pointer' },
                        onClick: () => togglePillar(pillarKey),
                    },
                        createElement('span', { style: { fontSize: '14px', color: '#50575e', width: '16px' } }, isExpanded ? '▼' : '▶'),
                        createElement('strong', null, pillar.pillar || pillar.pillar_slug || 'Pillar'),
                        createElement('span', { style: { fontSize: '11px', color: '#50575e' } },
                            `(${pillar.total_posts || 0} posts, ${pillar.total_terms || 0} terms, ${pillar.gap_count || 0} gaps)`
                        )
                    ),
                    isExpanded && createElement('div', { style: { marginLeft: '24px' } },
                        gapTerms.length > 0
                            ? createElement('div', { style: { marginBottom: '6px' } },
                                createElement('span', { style: { fontSize: '12px', color: '#b32d2e', fontWeight: 600 } }, 'Uncovered terms: '),
                                gapTerms.map((g, gi) =>
                                    createElement('span', { key: `gap-${idx}-${gi}`, style: { fontSize: '12px', color: '#b32d2e', marginRight: '6px' } }, g.term)
                                )
                            )
                            : createElement('p', { style: { margin: '0 0 6px', fontSize: '12px', color: '#1e7e34' } }, 'No gaps — all analysed terms have at least one post.'),
                        allStats.length > 0 && createElement('div', { style: { marginTop: '4px' } },
                            createElement('table', { style: { fontSize: '11px', borderCollapse: 'collapse', width: '100%' } },
                                createElement('thead', null,
                                    createElement('tr', { style: { borderBottom: '1px solid #ddd' } },
                                        createElement('th', { style: { textAlign: 'left', padding: '2px 6px' } }, 'Term'),
                                        createElement('th', { style: { textAlign: 'right', padding: '2px 6px' } }, 'Hits'),
                                        createElement('th', { style: { textAlign: 'right', padding: '2px 6px' } }, 'Latest')
                                    )
                                ),
                                createElement('tbody', null,
                                    allStats.map((s, si) =>
                                        createElement('tr', { key: `stat-${idx}-${si}`, style: { borderBottom: '1px solid #f0f0f0' } },
                                            createElement('td', { style: { padding: '2px 6px' } }, s.term),
                                            createElement('td', { style: { textAlign: 'right', padding: '2px 6px', color: s.hits === 0 ? '#b32d2e' : '#1e7e34' } }, s.hits),
                                            createElement('td', { style: { textAlign: 'right', padding: '2px 6px', fontSize: '10px', color: '#50575e' } }, s.latest || '—')
                                        )
                                    )
                                )
                            ),
                            createElement(Button, { isSecondary: true, style: { marginTop: '6px', fontSize: '11px' }, onClick: () => handleExportCsv(pillar) }, 'Export CSV')
                        )
                    )
                );
            })
        )
    );
};

export default ResearchPolicyPanel;

(function () {
// Content Gap Analysis Dashboard — standalone React app for kh-planner-content-gaps page
const { useState, useEffect, useCallback } = wp.element;
const {
    Button,
    Card,
    CardBody,
    CardHeader,
    SelectControl,
    Spinner,
    Notice,
    Modal,
    TextControl,
} = wp.components;

const apiFetch = (options) =>
    wp.apiFetch(options);

/**
 * Navigate to the planner sessions page to view a newly created session.
 */
function navigateToSessions() {
    const url = new URL(window.location.href);
    url.searchParams.set('page', 'kh-planner-sessions');
    window.location.href = url.href;
}

// ─── GapDashboard Component ──────────────────────────────────────────

const GapDashboard = () => {
    const [dashboardData, setDashboardData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [daysBack, setDaysBack] = useState(730);
    const [selectedAudience, setSelectedAudience] = useState(null);
    const [expandedAudiences, setExpandedAudiences] = useState({});

    const loadDashboard = useCallback(async () => {
        setLoading(true);
        setError('');
        try {
            const response = await apiFetch({
                path: `editorial/v1/content-gaps/dashboard?days_back=${daysBack}`,
                method: 'GET',
            });
            setDashboardData(response);
        } catch (err) {
            setError(err.message || 'Failed to load gap dashboard.');
        } finally {
            setLoading(false);
        }
    }, [daysBack]);

    useEffect(() => {
        loadDashboard();
    }, [loadDashboard]);

    const toggleAudience = (slug) => {
        setExpandedAudiences((prev) => ({
            ...prev,
            [slug]: !prev[slug],
        }));
    };

    if (loading) {
        return wp.element.createElement('div', { style: { textAlign: 'center', padding: '40px' } },
            wp.element.createElement(Spinner, null),
            wp.element.createElement('p', { style: { marginTop: '12px', color: '#666' } }, 'Loading content gap analysis...')
        );
    }

    if (error) {
        return wp.element.createElement(Notice, { status: 'error', isDismissible: false }, error);
    }

    if (!dashboardData || !dashboardData.audiences) {
        return wp.element.createElement(Notice, { status: 'info', isDismissible: false }, 'No audience data available. Ensure SiteAudienceProfile and AllocationService are configured.');
    }

    const audiences = Object.values(dashboardData.audiences);

    return wp.element.createElement(
        'div',
        { className: 'content-gaps-dashboard', style: { maxWidth: '960px', margin: '0 auto' } },
        wp.element.createElement('h1', { style: { marginBottom: '8px' } }, 'Content Gap Analysis'),
        wp.element.createElement(
            'p',
            { style: { color: '#50575e', marginBottom: '20px' } },
            'Overview of published content coverage across all audience sites. Gaps are terms with zero coverage.'
        ),
        wp.element.createElement(
            'div',
            { style: { display: 'flex', gap: '12px', alignItems: 'flex-end', marginBottom: '20px' } },
            wp.element.createElement(TextControl, {
                label: 'Lookback (days)',
                type: 'number',
                min: 30,
                max: 3650,
                value: String(daysBack),
                onChange: (val) => setDaysBack(Math.max(30, parseInt(val || '730', 10))),
                style: { maxWidth: '120px' },
            }),
            wp.element.createElement(
                Button,
                { isSecondary: true, onClick: loadDashboard, disabled: loading },
                loading ? wp.element.createElement(Spinner, null) : 'Refresh'
            )
        ),
        audiences.length === 0
            ? wp.element.createElement('p', { style: { color: '#666' } }, 'No audience data found.')
            : audiences.map((audience) =>
                  wp.element.createElement(
                      Card,
                      {
                          key: audience.slug,
                          style: { marginBottom: '12px' },
                      },
                      wp.element.createElement(
                          CardHeader,
                          {
                              style: {
                                  display: 'flex',
                                  justifyContent: 'space-between',
                                  alignItems: 'center',
                                  cursor: 'pointer',
                              },
                              onClick: () => toggleAudience(audience.slug),
                          },
                          wp.element.createElement(
                              'div',
                              { style: { display: 'flex', alignItems: 'center', gap: '10px' } },
                              wp.element.createElement('strong', null, audience.label || audience.slug),
                              audience.blog_id
                                  ? wp.element.createElement(
                                        'span',
                                        { style: { fontSize: '12px', color: '#666' } },
                                        `(Blog #${audience.blog_id})`
                                    )
                                  : wp.element.createElement(
                                        'span',
                                        { style: { fontSize: '12px', color: '#cc1818' } },
                                        audience.error || 'No mapping'
                                    )
                          ),
                          wp.element.createElement(
                              'div',
                              { style: { display: 'flex', alignItems: 'center', gap: '16px' } },
                              wp.element.createElement(
                                  'span',
                                  { style: { fontSize: '13px', color: '#50575e' } },
                                  `${audience.total_posts} posts total`
                              ),
                              audience.pillars && audience.pillars.length > 0
                                  ? wp.element.createElement(
                                        'span',
                                        { style: { fontSize: '13px', color: '#50575e' } },
                                        `${audience.pillars.filter(p => p.total_posts === 0).length} empty pillars`
                                    )
                                  : null,
                              wp.element.createElement(
                                  Button,
                                  {
                                      isPrimary: true,
                                      onClick: (e) => {
                                          e.stopPropagation();
                                          setSelectedAudience(audience.slug);
                                      },
                                      style: { marginLeft: '8px' },
                                  },
                                  'Audit'
                              ),
                              wp.element.createElement(
                                  'span',
                                  { style: { fontSize: '16px', color: '#666' } },
                                  expandedAudiences[audience.slug] ? '▲' : '▼'
                              )
                          )
                      ),
                      expandedAudiences[audience.slug] && audience.pillars
                          ? wp.element.createElement(
                                CardBody,
                                null,
                                wp.element.createElement('h4', { style: { margin: '0 0 8px' } }, 'Pillar Breakdown'),
                                audience.pillars.length === 0
                                    ? wp.element.createElement('p', { style: { color: '#666', fontSize: '13px' } }, 'No pillars defined for this audience.')
                                    : wp.element.createElement(
                                          'table',
                                          { className: 'widefat striped', style: { fontSize: '13px' } },
                                          wp.element.createElement(
                                              'thead',
                                              null,
                                              wp.element.createElement(
                                                  'tr',
                                                  null,
                                                  wp.element.createElement('th', { style: { width: '40%' } }, 'Pillar'),
                                                  wp.element.createElement('th', { style: { width: '20%' } }, 'Posts'),
                                                  wp.element.createElement('th', { style: { width: '20%' } }, 'Status'),
                                                  wp.element.createElement('th', { style: { width: '20%' } }, 'Actions')
                                              )
                                          ),
                                          wp.element.createElement(
                                              'tbody',
                                              null,
                                              audience.pillars.map((pillar) =>
                                                  wp.element.createElement(
                                                      'tr',
                                                      { key: pillar.slug },
                                                      wp.element.createElement('td', null, pillar.name || pillar.slug),
                                                      wp.element.createElement('td', null, String(pillar.total_posts)),
                                                      wp.element.createElement(
                                                          'td',
                                                          null,
                                                          pillar.total_posts === 0
                                                              ? wp.element.createElement('span', { style: { color: '#cc1818', fontWeight: 600 } }, 'GAP')
                                                              : wp.element.createElement('span', { style: { color: '#46b450' } }, 'Covered')
                                                      ),
                                                      wp.element.createElement(
                                                          'td',
                                                          null,
                                                          wp.element.createElement(
                                                              Button,
                                                              {
                                                                  isSmall: true,
                                                                  isSecondary: true,
                                                                  onClick: () => setSelectedAudience(audience.slug + '|' + pillar.slug),
                                                              },
                                                              'Detail'
                                                          )
                                                      )
                                                  )
                                              )
                                          )
                                      ),
                                wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '12px', fontSize: '12px', color: '#666' } },
                                    audience.date_range || ''
                                )
                            )
                          : null
                  )
          ),
        selectedAudience &&
            wp.element.createElement(GapAuditModal, {
                audienceSlug: selectedAudience.split('|')[0],
                pillarSlug: selectedAudience.includes('|') ? selectedAudience.split('|')[1] : '',
                daysBack: daysBack,
                onClose: () => setSelectedAudience(null),
            })
    );
};

// ─── GapAuditModal Component ──────────────────────────────────────────

const GapAuditModal = ({ audienceSlug, pillarSlug, daysBack, onClose }) => {
    const [auditData, setAuditData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [topic, setTopic] = useState('');
    const [activePillarSlug, setActivePillarSlug] = useState(pillarSlug || '');
    const [creatingSession, setCreatingSession] = useState(null);
    const [sessionCreated, setSessionCreated] = useState(null);

    const loadAudit = useCallback(async (slug, pSlug) => {
        setLoading(true);
        setError('');
        setAuditData(null);
        try {
            let path = `editorial/v1/content-gaps/audit?audience_slug=${slug}&days_back=${daysBack}`;
            if (pSlug) {
                path += `&pillar_slug=${pSlug}`;
            }
            const response = await apiFetch({ path, method: 'GET' });
            setAuditData(response);
        } catch (err) {
            setError(err.message || 'Failed to load audit data.');
        } finally {
            setLoading(false);
        }
    }, [daysBack]);

    useEffect(() => {
        loadAudit(audienceSlug, activePillarSlug);
    }, [audienceSlug, activePillarSlug, loadAudit]);

    // Re-run audit with topic-based search
    const runTopicSearch = async () => {
        if (!topic.trim()) return;
        setLoading(true);
        setError('');
        try {
            let path = `editorial/v1/content-gaps/audit?audience_slug=${audienceSlug}&days_back=${daysBack}&topic=${encodeURIComponent(topic.trim())}`;
            if (activePillarSlug) {
                path += `&pillar_slug=${activePillarSlug}`;
            }
            const response = await apiFetch({ path, method: 'GET' });
            setAuditData(response);
        } catch (err) {
            setError(err.message || 'Failed to run topic search.');
        } finally {
            setLoading(false);
        }
    };

    const handleCreateSession = async (gapTerm) => {
        setCreatingSession(gapTerm);
        setSessionCreated(null);
        try {
            const response = await apiFetch({
                path: 'editorial/v1/content-gaps/create-session',
                method: 'POST',
                data: {
                    topic: gapTerm,
                    audience_slug: audienceSlug,
                    pillar: auditData?.pillar_name || '',
                    pillar_slug: activePillarSlug || '',
                },
            });
            setSessionCreated(response);
        } catch (err) {
            setError(err.message || 'Failed to create session.');
        } finally {
            setCreatingSession(null);
        }
    };

    const getPillarOptions = () => {
        if (!auditData?.internal_coverage?.gaps_by_pillar) return [];
        const options = [{ label: 'All pillars', value: '' }];
        const seen = new Set();
        auditData.internal_coverage.gaps_by_pillar.forEach((g) => {
            if (g.pillar_slug && !seen.has(g.pillar_slug)) {
                seen.add(g.pillar_slug);
                options.push({
                    label: g.pillar === '(all sites)' ? 'Global (all posts)' : (g.pillar || g.pillar_slug),
                    value: g.pillar_slug,
                });
            }
        });
        return options;
    };

    return wp.element.createElement(
        Modal,
        {
            title: `Content Audit: ${auditData?.label || audienceSlug}`,
            onRequestClose: onClose,
            style: { minWidth: '70vw', maxHeight: '80vh' },
            isDismissible: true,
            shouldCloseOnClickOutside: false,
        },
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, error),
        sessionCreated &&
            wp.element.createElement(
                Notice,
                { status: 'success', isDismissible: false },
                wp.element.createElement(
                    'span',
                    null,
                    `Session created for "${sessionCreated.title}". `,
                    wp.element.createElement(
                        Button,
                        {
                            isLink: true,
                            onClick: () => navigateToSessions(),
                        },
                        'View in Sessions'
                    )
                )
            ),
        wp.element.createElement(
            'div',
            { style: { marginBottom: '16px', display: 'flex', gap: '12px', alignItems: 'flex-end', flexWrap: 'wrap' } },
            wp.element.createElement(SelectControl, {
                label: 'Pillar Filter',
                value: activePillarSlug,
                options: getPillarOptions(),
                onChange: (val) => setActivePillarSlug(val),
                style: { minWidth: '200px' },
            }),
            wp.element.createElement(TextControl, {
                label: 'Topic Search (optional)',
                value: topic,
                onChange: setTopic,
                placeholder: 'e.g. field service profitability',
                style: { minWidth: '200px' },
            }),
            wp.element.createElement(
                Button,
                {
                    isSecondary: true,
                    onClick: runTopicSearch,
                    disabled: loading || !topic.trim(),
                },
                'Search'
            )
        ),
        loading
            ? wp.element.createElement('div', { style: { textAlign: 'center', padding: '24px' } },
                  wp.element.createElement(Spinner, null),
                  wp.element.createElement('p', { style: { marginTop: '8px', color: '#666' } }, 'Running coverage analysis...')
              )
            : auditData?.internal_coverage
              ? wp.element.createElement(
                    'div',
                    null,
                    wp.element.createElement(
                        'p',
                        { style: { color: '#50575e', fontSize: '13px', marginBottom: '16px' } },
                        auditData.internal_coverage.summary || 'No summary available.'
                    ),
                    wp.element.createElement(
                        'div',
                        { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '12px' } },
                        auditData.internal_coverage.gaps_by_pillar.map((section, idx) =>
                            wp.element.createElement(
                                Card,
                                { key: idx, style: { fontSize: '13px' } },
                                wp.element.createElement(
                                    CardHeader,
                                    null,
                                    wp.element.createElement(
                                        'div',
                                        { style: { display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' } },
                                        wp.element.createElement('strong', null, section.scope === 'global' ? 'All Sites' : (section.pillar || section.pillar_slug || 'Pillar')),
                                        wp.element.createElement(
                                            'span',
                                            { style: { fontSize: '12px', color: '#50575e' } },
                                            `${section.total_posts} posts · ${section.gap_count} gaps`
                                        )
                                    )
                                ),
                                wp.element.createElement(
                                    CardBody,
                                    null,
                                    section.stats && section.stats.length > 0
                                        ? wp.element.createElement(
                                              'div',
                                              null,
                                              wp.element.createElement(
                                                  'p',
                                                  { style: { margin: '0 0 8px', fontWeight: 500, fontSize: '12px' } },
                                                  'Term Coverage:'
                                              ),
                                              wp.element.createElement(
                                                  'ul',
                                                  { style: { margin: 0, padding: 0, listStyle: 'none' } },
                                                  section.stats.slice(0, 20).map((stat) =>
                                                      wp.element.createElement(
                                                          'li',
                                                          {
                                                              key: stat.term,
                                                              style: {
                                                                  padding: '4px 0',
                                                                  borderBottom: '1px solid #f0f0f1',
                                                                  display: 'flex',
                                                                  justifyContent: 'space-between',
                                                                  alignItems: 'center',
                                                              },
                                                          },
                                                          wp.element.createElement(
                                                              'span',
                                                              {
                                                                  style: {
                                                                      color: stat.hits === 0 ? '#cc1818' : '#46b450',
                                                                      fontWeight: stat.hits === 0 ? 600 : 400,
                                                                  },
                                                              },
                                                              stat.term
                                                          ),
                                                          wp.element.createElement(
                                                              'div',
                                                              { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                                                              wp.element.createElement(
                                                                  'span',
                                                                  { style: { fontSize: '12px', color: '#666', minWidth: '40px', textAlign: 'right' } },
                                                                  `${stat.hits} hit${stat.hits !== 1 ? 's' : ''}`
                                                              ),
                                                              stat.hits === 0 &&
                                                                  wp.element.createElement(
                                                                      Button,
                                                                      {
                                                                          isSmall: true,
                                                                          isSecondary: true,
                                                                          onClick: () => handleCreateSession(stat.term),
                                                                          disabled: creatingSession === stat.term,
                                                                      },
                                                                      creatingSession === stat.term
                                                                          ? wp.element.createElement(Spinner, null)
                                                                          : 'Create Session'
                                                                  )
                                                          )
                                                      )
                                                  )
                                              ),
                                              section.stats.length > 20 &&
                                                  wp.element.createElement(
                                                      'p',
                                                      { style: { marginTop: '8px', fontSize: '11px', color: '#666' } },
                                                      `…and ${section.stats.length - 20} more terms`
                                                  )
                                          )
                                        : wp.element.createElement('p', { style: { color: '#666' } }, 'No stats available for this scope.')
                                )
                            )
                        )
                    )
                )
              : wp.element.createElement('p', { style: { color: '#666' } }, 'No coverage data returned.')
    );
};

// ─── Mount ──────────────────────────────────────────────────────────

wp.element.render(
    wp.element.createElement(GapDashboard),
    document.getElementById('editorial-content-gaps-app')
);

})();
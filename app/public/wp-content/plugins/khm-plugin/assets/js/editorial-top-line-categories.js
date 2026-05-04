(function () {
const { useState, useEffect } = wp.element;
const {
    Button,
    Card,
    CardBody,
    CardHeader,
    Notice,
    Spinner,
    TextControl,
    TextareaControl,
    FormTokenField,
} = wp.components;
const { dispatch } = wp.data;

const DEFAULT_POLICY = {
    priority_domains: [],
    blocked_domains: ['wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com'],
    blocked_keywords: ['chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study'],
    preferred_sources: [],
    source_mix_minimums: {
        academic: 1,
        analyst: 1,
        industry: 1,
        case_study: 1,
    },
    recency_months: 36,
    max_citations_per_org: 2,
    min_priority_domains_hit: 1,
};

const apiFetch = (options) =>
    wp.apiFetch({
        ...options,
        headers: {
            'X-WP-Nonce': dualGptData.nonce,
            ...(options.headers || {}),
        },
    });

const normalizeDomainTokens = (tokens) => {
    if (!Array.isArray(tokens)) {
        return [];
    }
    const map = {};
    tokens.forEach((token) => {
        const value = String(token || '')
            .trim()
            .toLowerCase()
            .replace(/^https?:\/\//, '')
            .replace(/^www\./, '')
            .split('/')[0]
            .replace(/\.+$/, '');
        if (value) {
            map[value] = true;
        }
    });
    return Object.keys(map);
};

const normalizeKeywordTokens = (tokens) => {
    if (!Array.isArray(tokens)) {
        return [];
    }
    const map = {};
    tokens.forEach((token) => {
        const value = String(token || '').trim().toLowerCase();
        if (value) {
            map[value] = true;
        }
    });
    return Object.keys(map);
};

// Publication / journal titles: preserve original case, deduplicate case-insensitively
const normalizePublicationTokens = (tokens) => {
    if (!Array.isArray(tokens)) {
        return [];
    }
    const seen = {};
    const result = [];
    tokens.forEach((token) => {
        const value = String(token || '').trim();
        if (value && !seen[value.toLowerCase()]) {
            seen[value.toLowerCase()] = true;
            result.push(value);
        }
    });
    return result;
};

const normalizeLabelTokens = (tokens) => {
    if (!Array.isArray(tokens)) {
        return [];
    }
    const seen = {};
    const result = [];
    tokens.forEach((token) => {
        const value = String(token || '').trim();
        if (!value) {
            return;
        }
        const key = value.toLowerCase();
        if (seen[key]) {
            return;
        }
        seen[key] = true;
        result.push(value);
    });
    return result;
};

// Typed entity helpers — format is "Name|type" in the UI, {name, type} in storage.
const typedEntityToDisplay = (entity) => {
    if (typeof entity === 'string') return entity; // already display format
    const name = String(entity?.name || '').trim();
    const type = String(entity?.type || '').trim().toLowerCase();
    return type ? `${name}|${type}` : name;
};

const typedEntityFromDisplay = (token) => {
    const parts = String(token || '').split('|');
    return { name: parts[0].trim(), type: (parts[1] || '').trim().toLowerCase() };
};

const normalizeTypedEntityTokens = (entities) => {
    if (!Array.isArray(entities)) return [];
    const seen = {};
    const result = [];
    entities.forEach((entity) => {
        const display = typedEntityToDisplay(entity);
        const key = display.toLowerCase();
        if (!display || seen[key]) return;
        seen[key] = true;
        result.push(display);
    });
    return result;
};

const emptyCategory = () => ({
    slug: '',
    name: '',
    category_type: '',
    pref_domain: '',
    core_content_channel: '',
    target_personas: [],
    target_sponsors: [],
    key_competitors: [],
    trade_associations: [],
    academic_journals: [],
    acronyms: [],
    cultural_lexicon: [],
    key_speakers: [],
    subgroups: [],
    research_policy: { ...DEFAULT_POLICY, source_mix_minimums: { ...DEFAULT_POLICY.source_mix_minimums } },
});

const EditorialTopLineCategoriesApp = () => {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [importing, setImporting] = useState(false);
    const [error, setError] = useState('');
    const [importText, setImportText] = useState('');
    const [editing, setEditing] = useState(emptyCategory());

    const loadCategories = async () => {
        try {
            setLoading(true);
            setError('');
            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/top-line-categories',
                method: 'GET',
            });
            const rows = Array.isArray(response?.top_line_categories) ? response.top_line_categories : [];
            rows.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
            setCategories(rows);
            if (!editing?.name && rows.length) {
                setEditing(rows[0]);
            }
        } catch (err) {
            console.error('Failed to load top-line categories:', err);
            setError(err.message || 'Failed to load top-line categories.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadCategories();
    }, []);

    const selectCategory = (category) => {
        setEditing({
            slug: category.slug || '',
            name: category.name || '',
            category_type: category.category_type || '',
            pref_domain: category.pref_domain || '',
            core_content_channel: category.core_content_channel || '',
            target_personas: normalizeLabelTokens(category.target_personas || []),
            target_sponsors: normalizeTypedEntityTokens(category.target_sponsors || []),
            key_competitors: normalizeTypedEntityTokens(category.key_competitors || []),
            trade_associations: normalizeTypedEntityTokens(category.trade_associations || []),
            academic_journals: normalizeLabelTokens(category.academic_journals || []),
            acronyms: normalizeLabelTokens(category.acronyms || []),
            cultural_lexicon: normalizeLabelTokens(category.cultural_lexicon || []),
            key_speakers: normalizeLabelTokens(category.key_speakers || []),
            subgroups: normalizeLabelTokens(category.subgroups || []),
            research_policy: {
                ...DEFAULT_POLICY,
                ...(category.research_policy || {}),
                source_mix_minimums: {
                    ...DEFAULT_POLICY.source_mix_minimums,
                    ...(category.research_policy?.source_mix_minimums || {}),
                },
            },
        });
    };

    const updatePolicy = (key, value) => {
        setEditing((prev) => ({
            ...prev,
            research_policy: {
                ...prev.research_policy,
                [key]: value,
            },
        }));
    };

    const updateSourceMix = (key, value) => {
        const parsed = Math.max(0, parseInt(value || 0, 10) || 0);
        setEditing((prev) => ({
            ...prev,
            research_policy: {
                ...prev.research_policy,
                source_mix_minimums: {
                    ...prev.research_policy.source_mix_minimums,
                    [key]: parsed,
                },
            },
        }));
    };

    const saveCategory = async () => {
        if (!editing?.name) {
            setError('Category name is required.');
            return;
        }

        const payload = {
            slug: editing.slug || undefined,
            name: editing.name,
            category_type: String(editing.category_type || '').trim(),
            pref_domain: String(editing.pref_domain || '').trim(),
            core_content_channel: String(editing.core_content_channel || '').trim(),
            target_personas: normalizeLabelTokens(editing.target_personas || []),
            target_sponsors: editing.target_sponsors.map(typedEntityFromDisplay),
            key_competitors: editing.key_competitors.map(typedEntityFromDisplay),
            trade_associations: editing.trade_associations.map(typedEntityFromDisplay),
            academic_journals: normalizePublicationTokens(editing.academic_journals || []),
            acronyms: normalizeLabelTokens(editing.acronyms || []),
            cultural_lexicon: normalizeLabelTokens(editing.cultural_lexicon || []),
            key_speakers: normalizeLabelTokens(editing.key_speakers || []),
            subgroups: normalizeLabelTokens(editing.subgroups || []),
            research_policy: {
                priority_domains: normalizeDomainTokens(editing.research_policy?.priority_domains || []),
                blocked_domains: normalizeDomainTokens(editing.research_policy?.blocked_domains || []),
                blocked_keywords: normalizeKeywordTokens(editing.research_policy?.blocked_keywords || []),
                preferred_sources: normalizePublicationTokens(editing.research_policy?.preferred_sources || []),
                source_mix_minimums: {
                    academic: Math.max(0, parseInt(editing.research_policy?.source_mix_minimums?.academic || 0, 10) || 0),
                    analyst: Math.max(0, parseInt(editing.research_policy?.source_mix_minimums?.analyst || 0, 10) || 0),
                    industry: Math.max(0, parseInt(editing.research_policy?.source_mix_minimums?.industry || 0, 10) || 0),
                    case_study: Math.max(0, parseInt(editing.research_policy?.source_mix_minimums?.case_study || 0, 10) || 0),
                },
                recency_months: Math.max(1, parseInt(editing.research_policy?.recency_months || 1, 10) || 1),
                max_citations_per_org: Math.max(1, parseInt(editing.research_policy?.max_citations_per_org || 1, 10) || 1),
                min_priority_domains_hit: Math.max(0, parseInt(editing.research_policy?.min_priority_domains_hit || 0, 10) || 0),
            },
        };

        try {
            setSaving(true);
            setError('');
            await apiFetch({
                path: 'dual-gpt/v1/planner/top-line-categories',
                method: 'POST',
                data: {
                    top_line_category: payload,
                },
            });

            dispatch('core/notices').createNotice('success', 'Top-line category saved.', { type: 'snackbar' });
            await loadCategories();
        } catch (err) {
            console.error('Failed to save top-line category:', err);
            setError(err.message || 'Failed to save top-line category.');
        } finally {
            setSaving(false);
        }
    };

    const importCategories = async () => {
        if (!importText.trim()) {
            setError('Paste JSON array or CSV text into import box first.');
            return;
        }

        try {
            setImporting(true);
            setError('');

            let payload = {};
            const trimmed = importText.trim();
            if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
                const parsed = JSON.parse(trimmed);
                payload.rows = Array.isArray(parsed) ? parsed : (Array.isArray(parsed?.rows) ? parsed.rows : []);
            } else {
                payload.csv = trimmed;
            }

            const response = await apiFetch({
                path: 'dual-gpt/v1/planner/top-line-categories/import',
                method: 'POST',
                data: payload,
            });

            dispatch('core/notices').createNotice(
                'success',
                `Import complete: ${response?.created_or_updated || 0} updated, ${response?.skipped || 0} skipped.`,
                { type: 'snackbar' }
            );

            await loadCategories();
        } catch (err) {
            console.error('Failed to import categories:', err);
            setError(err.message || 'Failed to import categories.');
        } finally {
            setImporting(false);
        }
    };

    return wp.element.createElement(
        'div',
        { className: 'editorial-planner-dashboard' },
        wp.element.createElement('h1', null, 'Top-Line Categories'),
        wp.element.createElement(
            Card,
            { style: { marginBottom: '16px' } },
            wp.element.createElement(CardHeader, null, 'Bulk Import (JSON Array or CSV)'),
            wp.element.createElement(
                CardBody,
                null,
                wp.element.createElement(TextareaControl, {
                    label: 'Paste data',
                    value: importText,
                    rows: 6,
                    onChange: setImportText,
                    help: 'Accepts JSON array of rows or CSV with headers like Brand Title, Pref. Domain, Core Content Channel, Target Sponsors, Academic Journals.',
                }),
                wp.element.createElement(
                    Button,
                    {
                        isSecondary: true,
                        onClick: importCategories,
                        disabled: importing,
                    },
                    importing ? wp.element.createElement(Spinner, null) : 'Import Data'
                )
            )
        ),
        error && wp.element.createElement(Notice, { status: 'error', isDismissible: false }, error),
        loading
            ? wp.element.createElement(Spinner, null)
            : wp.element.createElement(
                  'div',
                  { style: { display: 'grid', gridTemplateColumns: '320px 1fr', gap: '16px' } },
                  wp.element.createElement(
                      Card,
                      null,
                      wp.element.createElement(CardHeader, null, 'Categories'),
                      wp.element.createElement(
                          CardBody,
                          null,
                          wp.element.createElement(
                              Button,
                              {
                                  isSecondary: true,
                                  onClick: () => setEditing(emptyCategory()),
                                  style: { marginBottom: '10px' },
                              },
                              'Create New Category'
                          ),
                          categories.length
                              ? categories.map((category) =>
                                    wp.element.createElement(
                                        'div',
                                        { key: category.slug, style: { marginBottom: '8px' } },
                                        wp.element.createElement(
                                            Button,
                                            {
                                                isPrimary: editing?.slug === category.slug,
                                                isSecondary: editing?.slug !== category.slug,
                                                onClick: () => selectCategory(category),
                                            },
                                            category.name
                                        )
                                    )
                                )
                              : wp.element.createElement('p', null, 'No categories yet.')
                      )
                  ),
                  wp.element.createElement(
                      Card,
                      null,
                      wp.element.createElement(CardHeader, null, editing?.slug ? 'Edit Category' : 'New Category'),
                      wp.element.createElement(
                          CardBody,
                          null,
                          wp.element.createElement(TextControl, {
                              label: 'Category Name',
                              value: editing?.name || '',
                              onChange: (value) => setEditing((prev) => ({ ...prev, name: value })),
                          }),
                              wp.element.createElement(TextControl, {
                                  label: 'Category Type',
                                  value: editing?.category_type || '',
                                  onChange: (value) => setEditing((prev) => ({ ...prev, category_type: value })),
                              }),
                              wp.element.createElement(TextControl, {
                                  label: 'Preferred Domain',
                                  value: editing?.pref_domain || '',
                                  onChange: (value) => setEditing((prev) => ({ ...prev, pref_domain: value })),
                              }),
                              wp.element.createElement(TextControl, {
                                  label: 'Core Content Channel',
                                  value: editing?.core_content_channel || '',
                                  onChange: (value) => setEditing((prev) => ({ ...prev, core_content_channel: value })),
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Subgroups',
                                  value: editing?.subgroups || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, subgroups: normalizeLabelTokens(value) })),
                                  placeholder: 'e.g. Remote Diagnostics',
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Target Personas',
                                  value: editing?.target_personas || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, target_personas: normalizeLabelTokens(value) })),
                                  placeholder: 'e.g. VP Service',
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Target Sponsors / Solution Providers',
                                  value: editing?.target_sponsors || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, target_sponsors: normalizeTypedEntityTokens(value) })),
                                  placeholder: 'e.g. Salesforce|software',
                                  help: 'Format: Name|type  (e.g. SAP|software, Deloitte|consultant, Honeywell|manufacturer)',
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Key Competitors',
                                  value: editing?.key_competitors || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, key_competitors: normalizeTypedEntityTokens(value) })),
                                  placeholder: 'e.g. Siemens|manufacturer',
                                  help: 'Format: Name|type',
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Trade & Professional Associations',
                                  value: editing?.trade_associations || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, trade_associations: normalizeTypedEntityTokens(value) })),
                                  placeholder: 'e.g. CSSA|association',
                                  help: 'Format: Name|type',
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Academic Journals',
                                  value: editing?.academic_journals || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, academic_journals: normalizePublicationTokens(value) })),
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Acronyms',
                                  value: editing?.acronyms || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, acronyms: normalizeLabelTokens(value) })),
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Cultural Lexicon',
                                  value: editing?.cultural_lexicon || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, cultural_lexicon: normalizeLabelTokens(value) })),
                              }),
                              wp.element.createElement(FormTokenField, {
                                  label: 'Key Speakers',
                                  value: editing?.key_speakers || [],
                                  onChange: (value) => setEditing((prev) => ({ ...prev, key_speakers: normalizeLabelTokens(value) })),
                              }),
                          wp.element.createElement(FormTokenField, {
                              label: 'Priority Domains',
                              value: editing?.research_policy?.priority_domains || [],
                              onChange: (value) => updatePolicy('priority_domains', value),
                              placeholder: 'e.g. fieldservice.com',
                          }),
                          wp.element.createElement(FormTokenField, {
                              label: 'Blocked Domains',
                              value: editing?.research_policy?.blocked_domains || [],
                              onChange: (value) => updatePolicy('blocked_domains', value),
                              placeholder: 'e.g. reddit.com',
                          }),
                          wp.element.createElement(FormTokenField, {
                              label: 'Blocked Keywords',
                              value: editing?.research_policy?.blocked_keywords || [],
                              onChange: (value) => updatePolicy('blocked_keywords', value),
                              placeholder: 'e.g. synthetic study',
                          }),
                          wp.element.createElement('hr', { style: { margin: '12px 0', borderColor: '#ddd' } }),
                          wp.element.createElement(
                              'p',
                              { style: { fontSize: '12px', color: '#666', margin: '0 0 6px' } },
                              'Preferred Publications & Reports — specific journal titles, report series, or named publications the research agent should actively seek. Enter the full title as you would search for it.'
                          ),
                          wp.element.createElement(FormTokenField, {
                              label: 'Preferred Publications & Reports',
                              value: editing?.research_policy?.preferred_sources || [],
                              onChange: (value) => updatePolicy('preferred_sources', normalizePublicationTokens(value)),
                              placeholder: 'e.g. Journal of Service Management',
                              tokenizeOnSpace: false,
                          }),
                          wp.element.createElement('hr', { style: { margin: '12px 0', borderColor: '#ddd' } }),
                          wp.element.createElement(TextControl, {
                              label: 'Source Mix: Academic minimum',
                              type: 'number',
                              min: 0,
                              value: editing?.research_policy?.source_mix_minimums?.academic ?? 0,
                              onChange: (value) => updateSourceMix('academic', value),
                          }),
                          wp.element.createElement(TextControl, {
                              label: 'Source Mix: Analyst minimum',
                              type: 'number',
                              min: 0,
                              value: editing?.research_policy?.source_mix_minimums?.analyst ?? 0,
                              onChange: (value) => updateSourceMix('analyst', value),
                          }),
                          wp.element.createElement(TextControl, {
                              label: 'Source Mix: Industry minimum',
                              type: 'number',
                              min: 0,
                              value: editing?.research_policy?.source_mix_minimums?.industry ?? 0,
                              onChange: (value) => updateSourceMix('industry', value),
                          }),
                          wp.element.createElement(TextControl, {
                              label: 'Source Mix: Case Study minimum',
                              type: 'number',
                              min: 0,
                              value: editing?.research_policy?.source_mix_minimums?.case_study ?? 0,
                              onChange: (value) => updateSourceMix('case_study', value),
                          }),
                          wp.element.createElement(TextControl, {
                              label: 'Recency Months',
                              type: 'number',
                              min: 1,
                              value: editing?.research_policy?.recency_months ?? 36,
                              onChange: (value) => updatePolicy('recency_months', Math.max(1, parseInt(value || 1, 10) || 1)),
                          }),
                          wp.element.createElement(TextControl, {
                              label: 'Max Citations Per Org',
                              type: 'number',
                              min: 1,
                              value: editing?.research_policy?.max_citations_per_org ?? 2,
                              onChange: (value) => updatePolicy('max_citations_per_org', Math.max(1, parseInt(value || 1, 10) || 1)),
                          }),
                          wp.element.createElement(TextControl, {
                              label: 'Minimum Priority Domains Hit',
                              type: 'number',
                              min: 0,
                              value: editing?.research_policy?.min_priority_domains_hit ?? 0,
                              onChange: (value) => updatePolicy('min_priority_domains_hit', Math.max(0, parseInt(value || 0, 10) || 0)),
                          }),
                          wp.element.createElement(
                              Button,
                              {
                                  isPrimary: true,
                                  onClick: saveCategory,
                                  disabled: saving,
                              },
                              saving ? wp.element.createElement(Spinner, null) : 'Save Category'
                          )
                      )
                  )
              )
    );
};

wp.element.render(
    wp.element.createElement(EditorialTopLineCategoriesApp),
    document.getElementById('editorial-top-line-categories-app')
);
})();

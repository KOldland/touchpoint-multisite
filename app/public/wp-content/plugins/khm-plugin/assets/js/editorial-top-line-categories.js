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

const emptyCategory = () => ({
    slug: '',
    name: '',
    research_policy: { ...DEFAULT_POLICY, source_mix_minimums: { ...DEFAULT_POLICY.source_mix_minimums } },
});

const EditorialTopLineCategoriesApp = () => {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
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

    return wp.element.createElement(
        'div',
        { className: 'editorial-planner-dashboard' },
        wp.element.createElement('h1', null, 'Top-Line Categories'),
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

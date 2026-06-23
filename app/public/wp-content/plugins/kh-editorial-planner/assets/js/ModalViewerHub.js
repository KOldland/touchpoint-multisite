/**
 * UI View Layer: ModalViewerHub.js
 * Contains shared presentation helpers and modular UI components.
 */

const { createElement } = wp.element;

// --- Shared Internal UI Helpers ---
export const renderList = (items) => {
    if (!items?.length) return null;
    return createElement(
        'ul',
        { style: { marginTop: '6px', marginBottom: 0, paddingLeft: '20px', listStyleType: 'disc' } },
        items.map((item, idx) => createElement('li', { key: idx }, item))
    );
};

export const renderLinkList = (links) => {
    if (!links?.length) return null;
    return createElement(
        'ul',
        { style: { marginTop: '6px', marginBottom: 0 } },
        links.map((link, idx) =>
            createElement('li', { key: idx },
                createElement('a', { href: link.url, target: '_blank', rel: 'noreferrer' }, link.title || link.url)
            )
        )
    );
};

export const canonicalizeUrl = (value) => {
    if (!value) return '';
    try {
        const parsed = new URL(value);
        parsed.hash = '';
        parsed.search = '';
        const normalized = parsed.toString().replace(/\/+$/, '');
        return normalized.toLowerCase();
    } catch (error) {
        return String(value).trim().toLowerCase();
    }
};

export const isPlaceholderSource = (title, url) => {
    const placeholderSourcePattern = /^Relevant Result\s+\d+\s+for:/i;
    const titleText = String(title || '');
    const urlText = String(url || '');
    return placeholderSourcePattern.test(titleText) || urlText.includes('example.com/result');
};

// --- Core HTML / Document Drivers (Restored) ---
export const blocksToHTML = (blocks) => {
    if (!Array.isArray(blocks)) return '';
    return blocks.map(block => block.innerHTML || '').join('');
};

export const handleExportAuthorDraft = (article) => {
    if (!article?.author?.output) return;
    const content = article.author.output;
    const blob = new Blob([content], { type: 'text/html' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${article.slug || 'draft'}-export.html`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};

// --- Modular UI Layout Components ---
export const PhaseSummaries = ({ phases }) => {
    const phaseOrder = ['phase1', 'phase2', 'phase3', 'phase4'];

    return createElement(
        'div',
        null,
        phaseOrder.map((phaseKey) => {
            const phase = phases[phaseKey];
            if (!phase) return null;

            return createElement(
                'div',
                { key: phaseKey, style: { marginBottom: '15px' } },
                createElement('h4', { style: { margin: '0 0 4px', fontSize: '13px' } }, `Results: ${phaseKey}`),
                renderList(phase.summary_points),
                renderLinkList(phase.sources)
            );
        })
    );
};

export const CitationList = ({ citations }) => {
    if (!Array.isArray(citations) || !citations.length) return null;
    
    return createElement(
        'ul',
        { style: { marginTop: '6px', marginBottom: 0 } },
        citations.map((citation, idx) => {
            if (typeof citation === 'string') {
                return createElement('li', { key: idx }, citation);
            }
            const label = (citation.apa && citation.apa !== 'details_unavailable') 
                ? citation.apa 
                : (citation.title || citation.url || 'Citation');
            
            return createElement(
                'li', 
                { key: idx }, 
                citation.url 
                    ? createElement('a', { href: citation.url, target: '_blank', rel: 'noreferrer' }, label)
                    : label
            );
        })
    );
};

export const TrendBlocks = ({ trends }) => {
    if (!Array.isArray(trends) || !trends.length) return null;
    
    const normalizeText = (value) => {
        if (Array.isArray(value)) return value.filter(Boolean).join(' ');
        if (value && typeof value === 'object') return Object.values(value).filter(Boolean).join(' ');
        return value == null ? '' : String(value).replace(/\s0$/, '').trim();
    };
    
    return createElement(
        'div',
        null,
        trends.map((trend, idx) => {
            const insightPoints = Array.isArray(trend.insight_points) && trend.insight_points.length 
                ? trend.insight_points 
                : trend.insight ? [trend.insight] : [];
            const implications = Array.isArray(trend.implications_for_articles) ? trend.implications_for_articles : [];
            const evidencePoints = Array.isArray(trend.evidence) ? trend.evidence : [];
            const whyMatters = normalizeText(trend.why_it_matters);
            
            return createElement(
                'div',
                { key: idx, style: { marginTop: '16px' } },
                createElement('h2', { style: { margin: '0 0 6px' } }, trend.title || `Trend ${idx + 1}`),
                renderList(insightPoints),
                whyMatters && createElement('div', { style: { marginTop: '10px' } },
                    createElement('h3', { style: { margin: '0 0 4px' } }, 'Why this matters'),
                    createElement('p', { style: { margin: 0 } }, whyMatters)
                ),
                trend.strategic_implication && createElement('p', { style: { marginTop: '6px' } }, `Strategic implication: ${trend.strategic_implication}`),
                implications.length > 0 && createElement('div', { style: { marginTop: '6px' } },
                    createElement('h3', { style: { margin: '0 0 4px' } }, 'Implications for articles'),
                    renderList(implications)
                ),
                Array.isArray(trend.citations) && trend.citations.length > 0 && createElement('div', { style: { marginTop: '6px' } },
                    createElement('h3', { style: { margin: '0 0 4px' } }, 'Citations'),
                    createElement(CitationList, { citations: trend.citations })
                ),
                evidencePoints.length > 0 && createElement('div', { style: { marginTop: '6px' } },
                    createElement('h3', { style: { margin: '0 0 4px' } }, 'Supporting evidence'),
                    renderList(evidencePoints.map((item) => {
                        const label = item.stat_or_finding || item.evidence || '';
                        const source = item.source || item.url || '';
                        const year = item.year ? ` (${item.year})` : '';
                        return `${label}${source ? ` — ${source}${year}` : ''}`;
                    }))
                )
            );
        })
    );
};
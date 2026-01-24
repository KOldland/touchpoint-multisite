<?php
/**
 * Framework Generator Citation QA Gutenberg Block
 */

namespace Dual_GPT\Blocks\CitationQA;

defined('ABSPATH') || exit;

/**
 * Register the Citation QA block
 */
function register_citation_qa_block() {
    $block_dir = __DIR__;

    register_block_type($block_dir, array(
        'render_callback' => __NAMESPACE__ . '\\render_citation_qa_block',
    ));
}
add_action('init', __NAMESPACE__ . '\\register_citation_qa_block');

/**
 * Render callback for the Citation QA block
 */
function render_citation_qa_block($attributes) {
    $session_id = $attributes['sessionId'] ?? '';
    $brief_id = $attributes['briefId'] ?? '';

    if (empty($session_id) || empty($brief_id)) {
        return '<p>Citation QA: Please configure session ID and brief ID.</p>';
    }

    // Only show in editor
    if (!is_admin()) {
        return '';
    }

    $post_id = get_the_ID();
    $pullquote_meta = array();
    if (!empty($post_id)) {
        $pullquote_meta = extract_pullquote_metadata_from_post($post_id);
    }
    $pullquote_count = count($pullquote_meta);
    $source_authors = array();
    $publications = array();
    $organisations = array();
    $missing_author = 0;
    $missing_publication = 0;
    $missing_date = 0;
    $missing_ref = 0;
    foreach ($pullquote_meta as $meta) {
        $author = trim((string) ($meta['source_author'] ?? ''));
        $publication = trim((string) ($meta['publication'] ?? ''));
        $organisation = trim((string) ($meta['organisation'] ?? ''));
        if ($author !== '') {
            $source_authors[$author] = true;
        } else {
            $missing_author++;
        }
        if ($publication !== '') {
            $publications[$publication] = true;
        } else {
            $missing_publication++;
        }
        if ($organisation !== '') {
            $organisations[$organisation] = true;
        }
        if (empty($meta['date'])) {
            $missing_date++;
        }
        if (empty($meta['citation_ref_id'])) {
            $missing_ref++;
        }
    }
    $source_authors = array_keys($source_authors);
    $publications = array_keys($publications);
    $organisations = array_keys($organisations);
    sort($source_authors, SORT_NATURAL | SORT_FLAG_CASE);
    sort($publications, SORT_NATURAL | SORT_FLAG_CASE);
    sort($organisations, SORT_NATURAL | SORT_FLAG_CASE);

    ob_start();
    ?>
    <style>
        .fg-citation-qa-block {
            background: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }
        .fg-citation-qa-block h4 {
            margin: 12px 0 8px;
            font-size: 14px;
        }
        .fg-citation-qa-block .fg-qa-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 16px;
            margin: 12px 0 16px;
            padding: 12px;
            background: #f7f7f7;
            border-radius: 10px;
        }
        .fg-citation-qa-block .fg-qa-actions {
            display: flex;
            gap: 8px;
            margin: 12px 0;
        }
        .fg-citation-qa-block .fg-qa-field {
            display: flex;
            flex-direction: column;
            min-width: 180px;
            flex: 1 1 180px;
        }
        .fg-citation-qa-block .fg-qa-field label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1e1e1e;
        }
        .fg-citation-qa-block .fg-qa-field input,
        .fg-citation-qa-block .fg-qa-field select {
            border-radius: 6px;
            border: 1px solid #c7c7c7;
            padding: 6px 8px;
            font-size: 13px;
        }
        .fg-citation-qa-block .fg-citation-qa-pullquotes ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }
        .fg-citation-qa-block .fg-citation-qa-pullquotes li {
            margin-bottom: 6px;
        }
        .fg-citation-qa-block .fg-qa-warnings {
            background: #fff4e5;
            border: 1px solid #ffb74d;
            border-radius: 8px;
            padding: 10px 12px;
            margin: 12px 0;
        }
        .fg-citation-qa-block .fg-qa-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .fg-citation-qa-block .fg-qa-table th,
        .fg-citation-qa-block .fg-qa-table td {
            border: 1px solid #e0e0e0;
            padding: 8px;
            font-size: 12px;
            text-align: left;
            vertical-align: top;
        }
        .fg-citation-qa-block .fg-qa-table th {
            background: #f2f2f2;
        }
    </style>
    <div
        class="fg-citation-qa-block"
        data-session-id="<?php echo esc_attr($session_id); ?>"
        data-brief-id="<?php echo esc_attr($brief_id); ?>"
        data-pullquote-meta="<?php echo esc_attr(wp_json_encode($pullquote_meta)); ?>"
        data-pullquote-count="<?php echo esc_attr($pullquote_count); ?>"
        data-post-id="<?php echo esc_attr($post_id); ?>"
    >
        <p>Citation QA Block - Session: <?php echo esc_html($session_id); ?>, Brief: <?php echo esc_html($brief_id); ?></p>
        <p>Pull Quotes Detected: <span id="fg-qa-count"><?php echo esc_html($pullquote_count); ?></span></p>
        <?php if ($pullquote_count > 0) : ?>
            <div class="fg-qa-meta">
                <div class="fg-qa-field">
                    <label for="fg-qa-search">Search</label>
                    <input id="fg-qa-search" type="search" placeholder="Filter by any text" />
                </div>
                <div class="fg-qa-field">
                    <label for="fg-qa-author">Source Author</label>
                    <select id="fg-qa-author">
                        <option value="">All</option>
                        <?php foreach ($source_authors as $author) : ?>
                            <option value="<?php echo esc_attr($author); ?>"><?php echo esc_html($author); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg-qa-field">
                    <label for="fg-qa-publication">Publication</label>
                    <select id="fg-qa-publication">
                        <option value="">All</option>
                        <?php foreach ($publications as $publication) : ?>
                            <option value="<?php echo esc_attr($publication); ?>"><?php echo esc_html($publication); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg-qa-field">
                    <label for="fg-qa-organisation">Organisation</label>
                    <select id="fg-qa-organisation">
                        <option value="">All</option>
                        <?php foreach ($organisations as $organisation) : ?>
                            <option value="<?php echo esc_attr($organisation); ?>"><?php echo esc_html($organisation); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg-qa-field">
                    <label for="fg-qa-date-start">Date From</label>
                    <input id="fg-qa-date-start" type="date" />
                </div>
                <div class="fg-qa-field">
                    <label for="fg-qa-date-end">Date To</label>
                    <input id="fg-qa-date-end" type="date" />
                </div>
                <div class="fg-qa-field">
                    <label for="fg-qa-include-missing">
                        <input id="fg-qa-include-missing" type="checkbox" checked />
                        Include Missing Dates
                    </label>
                </div>
            </div>
            <div class="fg-qa-actions">
                <button type="button" class="button" id="fg-qa-view-list">List View</button>
                <button type="button" class="button" id="fg-qa-view-table">Table View</button>
                <button type="button" class="button" id="fg-qa-export-csv">Export CSV</button>
                <button type="button" class="button" id="fg-qa-clear-filters">Clear Filters</button>
                <button type="button" class="button" id="fg-qa-reset-view">Reset View Preference</button>
            </div>
            <div class="fg-qa-warnings" id="fg-qa-warnings" style="display:none;"></div>
            <div class="fg-citation-qa-pullquotes">
                <strong>Pull Quote Metadata</strong>
                <p id="fg-qa-empty" style="display:none; margin: 6px 0 0;">No pull quotes match the current filters.</p>
                <ul id="fg-qa-pullquote-list">
                    <?php foreach ($pullquote_meta as $index => $meta) : ?>
                        <li
                            data-author="<?php echo esc_attr($meta['source_author'] ?? ''); ?>"
                            data-publication="<?php echo esc_attr($meta['publication'] ?? ''); ?>"
                            data-organisation="<?php echo esc_attr($meta['organisation'] ?? ''); ?>"
                            data-date="<?php echo esc_attr($meta['date'] ?? ''); ?>"
                            data-citation-ref-id="<?php echo esc_attr($meta['citation_ref_id'] ?? ''); ?>"
                        >
                            <?php echo esc_html('#' . ($index + 1)); ?> -
                            <?php echo esc_html($meta['source_author'] ?? 'Unknown Author'); ?>,
                            <?php echo esc_html($meta['publication'] ?? 'Unknown Publication'); ?>,
                            <?php echo esc_html($meta['organisation'] ?? ''); ?>
                            <?php if (!empty($meta['date'])) : ?>
                                (<?php echo esc_html($meta['date']); ?>)
                            <?php endif; ?>
                            <?php if (!empty($meta['citation_ref_id'])) : ?>
                                [Citation <?php echo esc_html($meta['citation_ref_id']); ?>]
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <table class="fg-qa-table" id="fg-qa-pullquote-table" style="display:none;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Author</th>
                            <th>Publication</th>
                            <th>Organisation</th>
                            <th>Date</th>
                            <th>Citation Ref</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pullquote_meta as $index => $meta) : ?>
                            <tr
                                data-author="<?php echo esc_attr($meta['source_author'] ?? ''); ?>"
                                data-publication="<?php echo esc_attr($meta['publication'] ?? ''); ?>"
                                data-organisation="<?php echo esc_attr($meta['organisation'] ?? ''); ?>"
                                data-date="<?php echo esc_attr($meta['date'] ?? ''); ?>"
                                data-citation-ref-id="<?php echo esc_attr($meta['citation_ref_id'] ?? ''); ?>"
                            >
                                <td><?php echo esc_html($index + 1); ?></td>
                                <td><?php echo esc_html($meta['source_author'] ?? 'Unknown Author'); ?></td>
                                <td><?php echo esc_html($meta['publication'] ?? 'Unknown Publication'); ?></td>
                                <td><?php echo esc_html($meta['organisation'] ?? ''); ?></td>
                                <td><?php echo esc_html($meta['date'] ?? ''); ?></td>
                                <td><?php echo esc_html($meta['citation_ref_id'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <script>
                (function() {
                    const block = document.currentScript.closest('.fg-citation-qa-block');
                    if (!block) {
                        return;
                    }
                    const searchInput = block.querySelector('#fg-qa-search');
                    const authorSelect = block.querySelector('#fg-qa-author');
                    const publicationSelect = block.querySelector('#fg-qa-publication');
                    const organisationSelect = block.querySelector('#fg-qa-organisation');
                    const dateStartInput = block.querySelector('#fg-qa-date-start');
                    const dateEndInput = block.querySelector('#fg-qa-date-end');
                    const includeMissingDates = block.querySelector('#fg-qa-include-missing');
                    const list = block.querySelector('#fg-qa-pullquote-list');
                    const table = block.querySelector('#fg-qa-pullquote-table');
                    const warnings = block.querySelector('#fg-qa-warnings');
                    const countEl = block.querySelector('#fg-qa-count');
                    const viewListBtn = block.querySelector('#fg-qa-view-list');
                    const viewTableBtn = block.querySelector('#fg-qa-view-table');
                    const exportBtn = block.querySelector('#fg-qa-export-csv');
                    const clearBtn = block.querySelector('#fg-qa-clear-filters');
                    const resetViewBtn = block.querySelector('#fg-qa-reset-view');
                    const emptyState = block.querySelector('#fg-qa-empty');
                    const postId = block.dataset.postId;

                    const getMetaFromBlock = () => {
                        try {
                            return JSON.parse(block.dataset.pullquoteMeta || '[]');
                        } catch (e) {
                            return [];
                        }
                    };

                    const buildOptionList = (select, values) => {
                        if (!select) {
                            return;
                        }
                        const existing = Array.from(select.querySelectorAll('option')).slice(1);
                        existing.forEach((opt) => opt.remove());
                        values.forEach((value) => {
                            const option = document.createElement('option');
                            option.value = value;
                            option.textContent = value;
                            select.appendChild(option);
                        });
                    };

                    const updateWarnings = (meta) => {
                        if (!warnings) {
                            return;
                        }
                        let missingAuthor = 0;
                        let missingPublication = 0;
                        let missingDate = 0;
                        let missingRef = 0;
                        meta.forEach((item) => {
                            if (!item.source_author) {
                                missingAuthor++;
                            }
                            if (!item.publication) {
                                missingPublication++;
                            }
                            if (!item.date) {
                                missingDate++;
                            }
                            if (!item.citation_ref_id) {
                                missingRef++;
                            }
                        });
                        const messages = [];
                        if (missingAuthor) {
                            messages.push('Missing author on ' + missingAuthor + ' pull quote(s).');
                        }
                        if (missingPublication) {
                            messages.push('Missing publication on ' + missingPublication + ' pull quote(s).');
                        }
                        if (missingDate) {
                            messages.push('Missing date on ' + missingDate + ' pull quote(s).');
                        }
                        if (missingRef) {
                            messages.push('Missing citation ref ID on ' + missingRef + ' pull quote(s).');
                        }
                        if (messages.length === 0) {
                            warnings.style.display = 'none';
                            warnings.textContent = '';
                            return;
                        }
                        warnings.style.display = '';
                        warnings.innerHTML = '<strong>Metadata Warnings</strong><ul><li>' + messages.join('</li><li>') + '</li></ul>';
                    };

                    const renderList = (meta) => {
                        if (!list) {
                            return;
                        }
                        list.innerHTML = '';
                        meta.forEach((item, index) => {
                            const li = document.createElement('li');
                            li.dataset.author = item.source_author || '';
                            li.dataset.publication = item.publication || '';
                            li.dataset.organisation = item.organisation || '';
                            li.dataset.date = item.date || '';
                            li.dataset.citationRefId = item.citation_ref_id || '';
                            const parts = [
                                '#' + (index + 1),
                                (item.source_author || 'Unknown Author') + ',',
                                (item.publication || 'Unknown Publication') + ',',
                                (item.organisation || '')
                            ];
                            let text = parts.join(' ');
                            if (item.date) {
                                text += ' (' + item.date + ')';
                            }
                            if (item.citation_ref_id) {
                                text += ' [Citation ' + item.citation_ref_id + ']';
                            }
                            li.textContent = text;
                            list.appendChild(li);
                        });
                    };

                    const renderTable = (meta) => {
                        if (!table) {
                            return;
                        }
                        const tbody = table.querySelector('tbody');
                        if (!tbody) {
                            return;
                        }
                        tbody.innerHTML = '';
                        meta.forEach((item, index) => {
                            const row = document.createElement('tr');
                            row.dataset.author = item.source_author || '';
                            row.dataset.publication = item.publication || '';
                            row.dataset.organisation = item.organisation || '';
                            row.dataset.date = item.date || '';
                            row.dataset.citationRefId = item.citation_ref_id || '';
                            const cells = [
                                index + 1,
                                item.source_author || 'Unknown Author',
                                item.publication || 'Unknown Publication',
                                item.organisation || '',
                                item.date || '',
                                item.citation_ref_id || ''
                            ];
                            cells.forEach((value) => {
                                const td = document.createElement('td');
                                td.textContent = value;
                                row.appendChild(td);
                            });
                            tbody.appendChild(row);
                        });
                    };

                    const parseDate = (value) => {
                        if (!value) {
                            return null;
                        }
                        const parsed = new Date(value);
                        if (Number.isNaN(parsed.getTime())) {
                            return null;
                        }
                        return parsed;
                    };

                    const getFilteredItems = (meta) => {
                        const search = (searchInput?.value || '').toLowerCase().trim();
                        const author = authorSelect?.value || '';
                        const publication = publicationSelect?.value || '';
                        const organisation = organisationSelect?.value || '';
                        const startDate = parseDate(dateStartInput?.value || '');
                        const endDate = parseDate(dateEndInput?.value || '');
                        const includeMissing = includeMissingDates ? includeMissingDates.checked : true;
                        return meta.filter((item) => {
                            const text = [
                                item.source_author,
                                item.publication,
                                item.organisation,
                                item.date,
                                item.citation_ref_id
                            ].join(' ').toLowerCase();
                            const matchesSearch = search === '' || text.indexOf(search) !== -1;
                            const matchesAuthor = author === '' || item.source_author === author;
                            const matchesPublication = publication === '' || item.publication === publication;
                            const matchesOrganisation = organisation === '' || item.organisation === organisation;
                            let matchesDate = true;
                            if (startDate || endDate) {
                                const itemDate = parseDate(item.date);
                                if (!itemDate) {
                                    matchesDate = includeMissing;
                                } else {
                                    if (startDate && itemDate < startDate) {
                                        matchesDate = false;
                                    }
                                    if (endDate && itemDate > endDate) {
                                        matchesDate = false;
                                    }
                                }
                            } else if (!includeMissing) {
                                const itemDate = parseDate(item.date);
                                if (!itemDate) {
                                    matchesDate = false;
                                }
                            }
                            return matchesSearch && matchesAuthor && matchesPublication && matchesOrganisation && matchesDate;
                        });
                    };

                    const filterAndRender = (meta) => {
                        const filtered = getFilteredItems(meta);
                        if (countEl) {
                            countEl.textContent = String(filtered.length);
                        }
                        if (emptyState) {
                            emptyState.style.display = filtered.length === 0 ? '' : 'none';
                        }
                        renderList(filtered);
                        renderTable(filtered);
                    };

                    const exportCsv = (meta) => {
                        const rows = [['Index', 'Author', 'Publication', 'Organisation', 'Date', 'Citation Ref']];
                        meta.forEach((item, index) => {
                            rows.push([
                                String(index + 1),
                                item.source_author || '',
                                item.publication || '',
                                item.organisation || '',
                                item.date || '',
                                String(item.citation_ref_id || '')
                            ]);
                        });
                        const csv = rows.map((row) => row.map((cell) => {
                            const value = String(cell);
                            if (value.indexOf('"') !== -1 || value.indexOf(',') !== -1 || value.indexOf('\n') !== -1) {
                                return '"' + value.replace(/"/g, '""') + '"';
                            }
                            return value;
                        }).join(',')).join('\n');
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        const sessionId = block.dataset.sessionId || 'session';
                        const briefId = block.dataset.briefId || 'brief';
                        link.download = 'pullquote-metadata-' + sessionId + '-' + briefId + '.csv';
                        link.click();
                        URL.revokeObjectURL(link.href);
                    };

                    const setView = (view) => {
                        if (!list || !table) {
                            return;
                        }
                        list.style.display = view === 'list' ? '' : 'none';
                        table.style.display = view === 'table' ? '' : 'none';
                    };

                    const initialize = (meta) => {
                        const authors = Array.from(new Set(meta.map((item) => item.source_author).filter(Boolean))).sort();
                        const pubs = Array.from(new Set(meta.map((item) => item.publication).filter(Boolean))).sort();
                        const orgs = Array.from(new Set(meta.map((item) => item.organisation).filter(Boolean))).sort();
                        buildOptionList(authorSelect, authors);
                        buildOptionList(publicationSelect, pubs);
                        buildOptionList(organisationSelect, orgs);
                        updateWarnings(meta);
                        filterAndRender(meta);
                        setView('list');
                    };

                    const bindFilters = (meta) => {
                        [searchInput, authorSelect, publicationSelect, organisationSelect, dateStartInput, dateEndInput, includeMissingDates].forEach((input) => {
                            if (input) {
                                input.addEventListener('input', () => filterAndRender(meta));
                                input.addEventListener('change', () => filterAndRender(meta));
                            }
                        });
                        if (clearBtn) {
                            clearBtn.addEventListener('click', () => {
                                if (searchInput) {
                                    searchInput.value = '';
                                }
                                if (authorSelect) {
                                    authorSelect.value = '';
                                }
                                if (publicationSelect) {
                                    publicationSelect.value = '';
                                }
                                if (organisationSelect) {
                                    organisationSelect.value = '';
                                }
                                if (dateStartInput) {
                                    dateStartInput.value = '';
                                }
                                if (dateEndInput) {
                                    dateEndInput.value = '';
                                }
                                if (includeMissingDates) {
                                    includeMissingDates.checked = true;
                                }
                                filterAndRender(meta);
                            });
                        }
                        if (viewListBtn) {
                            viewListBtn.addEventListener('click', () => {
                                setView('list');
                                saveViewPreference('list');
                            });
                        }
                        if (viewTableBtn) {
                            viewTableBtn.addEventListener('click', () => {
                                setView('table');
                                saveViewPreference('table');
                            });
                        }
                        if (exportBtn) {
                            exportBtn.addEventListener('click', () => exportCsv(getFilteredItems(meta)));
                        }
                        if (resetViewBtn) {
                            resetViewBtn.addEventListener('click', () => {
                                setView('list');
                                saveViewPreference('list');
                            });
                        }
                    };

                    const meta = getMetaFromBlock();
                    initialize(meta);
                    bindFilters(meta);

                    const saveViewPreference = (view) => {
                        if (window.wp && wp.apiFetch) {
                            wp.apiFetch({
                                path: '/dual-gpt/v1/user-preferences',
                                method: 'POST',
                                data: { key: 'pullquote_view', value: view }
                            }).catch(() => {});
                        }
                    };

                    if (window.wp && wp.apiFetch) {
                        wp.apiFetch({ path: '/dual-gpt/v1/user-preferences?key=pullquote_view' })
                            .then((response) => {
                                if (response && (response.value === 'list' || response.value === 'table')) {
                                    setView(response.value);
                                } else {
                                    return wp.apiFetch({ path: '/dual-gpt/v1/user-preferences/pullquote-view' })
                                        .then((legacy) => {
                                            if (legacy && (legacy.view === 'list' || legacy.view === 'table')) {
                                                setView(legacy.view);
                                                saveViewPreference(legacy.view);
                                            }
                                        });
                                }
                            })
                            .catch(() => {});
                    }

                    if (window.wp && wp.apiFetch && postId) {
                        wp.apiFetch({ path: '/dual-gpt/v1/pullquote-meta/' + postId })
                            .then((response) => {
                                if (!response || !response.items) {
                                    return;
                                }
                                block.dataset.pullquoteMeta = JSON.stringify(response.items);
                                if (countEl) {
                                    countEl.textContent = String(response.items.length);
                                }
                                initialize(response.items);
                            })
                            .catch(() => {});
                    }
                })();
            </script>
        <?php endif; ?>
        <button class="button fg-open-qa-modal">Open Citation QA</button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Extract pull quote metadata from post content.
 */
function extract_pullquote_metadata_from_post($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return array();
    }

    $content = $post->post_content ?? '';
    if (empty($content) || !is_string($content)) {
        return array();
    }

    $matches = array();
    preg_match_all('/<span[^>]*class=["\'][^"\']*dual-gpt-pullquote-meta[^"\']*["\'][^>]*>/i', $content, $matches);
    if (empty($matches[0])) {
        return array();
    }

    $results = array();
    foreach ($matches[0] as $tag) {
        $attributes = array();
        preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $tag, $attr_matches, PREG_SET_ORDER);
        foreach ($attr_matches as $attr_match) {
            $name = strtolower($attr_match[1]);
            $value = $attr_match[3] ?? $attr_match[4] ?? $attr_match[5] ?? '';
            $attributes[$name] = $value;
        }

        $results[] = array(
            'source_author' => sanitize_text_field($attributes['data-source-author'] ?? ''),
            'publication' => sanitize_text_field($attributes['data-publication'] ?? ''),
            'organisation' => sanitize_text_field($attributes['data-organisation'] ?? ''),
            'date' => sanitize_text_field($attributes['data-date'] ?? ''),
            'citation_ref_id' => sanitize_text_field($attributes['data-citation-ref-id'] ?? ''),
        );
    }

    return $results;
}

/**
 * Enqueue block assets
 */
function enqueue_citation_qa_assets() {
    $asset_file = __DIR__ . '/build/index.asset.php';
    if (file_exists($asset_file)) {
        $assets = include $asset_file;
        wp_enqueue_script(
            'fg-citation-qa-editor',
            plugins_url('build/index.js', __FILE__),
            $assets['dependencies'],
            $assets['version'],
            true
        );
        wp_enqueue_style(
            'fg-citation-qa-editor',
            plugins_url('build/index.css', __FILE__),
            array(),
            filemtime(__DIR__ . '/build/index.css')
        );
    }
}
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_citation_qa_assets');

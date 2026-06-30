/**
 * Site Search Widget
 *
 * Public-facing search widget that queries the unified search endpoint.
 * Handles form submission, API calls, and result rendering.
 *
 * @package KH\Editorial\Intelligence
 */

(function () {
    'use strict';

    /**
     * Initialize all site search widgets on the page.
     */
    function initAll() {
        var widgets = document.querySelectorAll('.khm-site-search-widget');
        widgets.forEach(function (widget) {
            initWidget(widget);
        });
    }

    /**
     * Initialize a single search widget.
     *
     * @param {HTMLElement} widget The widget container.
     */
    function initWidget(widget) {
        var form = widget.querySelector('.khm-site-search-form');
        var input = widget.querySelector('.khm-site-search-input');
        var spinner = widget.querySelector('.khm-site-search-spinner');
        var resultsEl = widget.querySelector('.khm-site-search-results');
        var errorEl = widget.querySelector('.khm-site-search-error');
        var emptyEl = widget.querySelector('.khm-site-search-empty');
        var answerSection = widget.querySelector('.khm-site-search-answer-section');
        var answerContent = widget.querySelector('.khm-site-search-answer-content');
        var answerLink = widget.querySelector('.khm-site-search-answer-link');
        var listSection = widget.querySelector('.khm-site-search-list-section');
        var listEl = widget.querySelector('.khm-site-search-list');
        var countEl = widget.querySelector('.khm-site-search-count');

        var debounceTimer = null;

        /**
         * Hide all result states.
         */
        function hideAllStates() {
            resultsEl.hidden = true;
            errorEl.hidden = true;
            emptyEl.hidden = true;
            spinner.hidden = true;
            answerSection.hidden = true;
            listSection.hidden = true;
        }

        /**
         * Show an error message.
         *
         * @param {string} message Error message text.
         */
        function showError(message) {
            hideAllStates();
            errorEl.textContent = message;
            errorEl.hidden = false;
        }

        /**
         * Show empty state.
         */
        function showEmpty() {
            hideAllStates();
            emptyEl.hidden = false;
        }

        /**
         * Show loading spinner.
         */
        function showSpinner() {
            hideAllStates();
            spinner.hidden = false;
        }

        /**
         * Render search results.
         *
         * @param {Object} data Response from the search API.
         */
        function renderResults(data) {
            hideAllStates();

            var results = data.results || [];
            var answer = data.answer || '';

            if (results.length === 0) {
                showEmpty();
                return;
            }

            // Show results container.
            resultsEl.hidden = false;

            // Render best match / answer section.
            if (answer) {
                // Convert markdown bold (**text**) to HTML <strong> tags.
                var formattedAnswer = answer
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\n/g, '<br>');
                answerContent.innerHTML = formattedAnswer;
                var bestResult = results[0];
                if (bestResult.url) {
                    answerLink.href = bestResult.url;
                    answerLink.hidden = false;
                } else {
                    answerLink.hidden = true;
                }
                answerSection.hidden = false;
            }

            // Render related content list.
            var listItems = results;
            if (listItems.length > 0) {
                listEl.innerHTML = '';
                countEl.textContent = listItems.length + ' ' + khmSiteSearch.i18n.result_count;

                listItems.forEach(function (item, index) {
                    var card = document.createElement('div');
                    card.className = 'khm-site-search-result-card';

                    // Source badge.
                    var badge = document.createElement('span');
                    badge.className = 'khm-site-search-badge khm-site-search-badge-' + item.source;
                    badge.textContent = item.source === 'rag'
                        ? khmSiteSearch.i18n.semantic
                        : khmSiteSearch.i18n.fulltext;
                    card.appendChild(badge);

                    // Title.
                    var title = document.createElement('h4');
                    title.className = 'khm-site-search-result-title';
                    var titleLink = document.createElement('a');
                    titleLink.href = item.url || '#';
                    titleLink.textContent = item.title || 'Untitled';
                    titleLink.target = '_blank';
                    title.appendChild(titleLink);
                    card.appendChild(title);

                    // Excerpt.
                    var excerpt = document.createElement('p');
                    excerpt.className = 'khm-site-search-result-excerpt';
                    excerpt.textContent = item.excerpt || '';
                    card.appendChild(excerpt);

                    // Read more link.
                    var readMore = document.createElement('a');
                    readMore.className = 'khm-site-search-result-link';
                    readMore.href = item.url || '#';
                    readMore.target = '_blank';
                    readMore.textContent = khmSiteSearch.i18n.read_more + ' →';
                    card.appendChild(readMore);

                    listEl.appendChild(card);
                });

                listSection.hidden = false;
            }
        }

        /**
         * Execute the search.
         *
         * @param {string} query The search query.
         */
        function executeSearch(query) {
            if (!query || query.trim().length === 0) {
                return;
            }

            showSpinner();

            var xhr = new XMLHttpRequest();
            xhr.open('POST', khmSiteSearch.endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
            xhr.setRequestHeader('X-WP-Nonce', khmSiteSearch.nonce);

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        renderResults(data);
                    } catch (e) {
                        showError(khmSiteSearch.i18n.error);
                    }
                } else if (xhr.status === 429) {
                    showError(khmSiteSearch.i18n.error + ' (rate limited)');
                } else {
                    showError(khmSiteSearch.i18n.error);
                }
            };

            xhr.onerror = function () {
                showError(khmSiteSearch.i18n.error);
            };

            xhr.send(JSON.stringify({ query: query }));
        }

        /**
         * Handle form submission.
         *
         * @param {Event} e Submit event.
         */
        function onFormSubmit(e) {
            e.preventDefault();
            var query = (input.value || '').trim();
            if (query.length === 0) {
                return;
            }
            executeSearch(query);
        }

        /**
         * Handle input with debounce for instant search.
         */
        function onInputChange() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            // Debounce at 400ms after the user stops typing.
            debounceTimer = setTimeout(function () {
                var query = (input.value || '').trim();
                if (query.length >= 3) {
                    executeSearch(query);
                }
            }, 400);
        }

        // Attach event listeners.
        form.addEventListener('submit', onFormSubmit);
        input.addEventListener('input', onInputChange);

        // Handle initial focus.
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });
    }

    // Initialize on DOMContentLoaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
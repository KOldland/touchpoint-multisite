/* global khmAtomicSearch */
(function () {
    'use strict';

    document.querySelectorAll('.khm-atomic-search-widget').forEach(function (widget) {
        var form     = widget.querySelector('.khm-atomic-search-form');
        var input    = widget.querySelector('.khm-atomic-search-input');
        var btn      = widget.querySelector('.khm-atomic-search-btn');
        var results  = widget.querySelector('.khm-atomic-search-results');
        var answer   = widget.querySelector('.khm-atomic-search-answer');
        var sources  = widget.querySelector('.khm-atomic-search-sources');
        var spinner  = widget.querySelector('.khm-atomic-search-spinner');
        var errorBox = widget.querySelector('.khm-atomic-search-error');

        if (!form || !input) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var query = input.value.trim();
            if (!query) return;

            // Reset UI.
            results.hidden  = true;
            errorBox.hidden = true;
            spinner.hidden  = false;
            btn.disabled    = true;
            answer.textContent  = '';
            sources.innerHTML   = '';

            var cfg = window.khmAtomicSearch || {};

            fetch(cfg.endpoint || '/wp-json/khm/v1/atomic/search', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':  cfg.nonce || '',
                },
                body: JSON.stringify({ query: query }),
            })
            .then(function (res) {
                if (!res.ok) {
                    return res.json().then(function (data) {
                        throw new Error(data.message || (cfg.i18n && cfg.i18n.error) || 'Error');
                    });
                }
                return res.json();
            })
            .then(function (data) {
                var i18n = cfg.i18n || {};
                spinner.hidden  = false; // keep off
                spinner.hidden  = true;
                btn.disabled    = false;

                if (!data.answer) {
                    errorBox.textContent = i18n.no_results || 'No results found.';
                    errorBox.hidden = false;
                    return;
                }

                answer.textContent = data.answer;

                if (data.sources && data.sources.length) {
                    var heading = document.createElement('p');
                    heading.className   = 'khm-atomic-search-sources-heading';
                    heading.textContent = i18n.sources || 'Sources';

                    var ul = document.createElement('ul');
                    data.sources.forEach(function (src) {
                        var li = document.createElement('li');
                        var a  = document.createElement('a');
                        a.href        = src.url;
                        a.textContent = src.title;
                        a.target      = '_blank';
                        a.rel         = 'noopener';
                        li.appendChild(a);

                        if (src.parent_title) {
                            var small = document.createElement('small');
                            small.textContent = ' — ' + src.parent_title;
                            li.appendChild(small);
                        }

                        ul.appendChild(li);
                    });

                    sources.appendChild(heading);
                    sources.appendChild(ul);
                }

                results.hidden = false;
            })
            .catch(function (err) {
                spinner.hidden  = true;
                btn.disabled    = false;
                errorBox.textContent = err.message || ((cfg.i18n && cfg.i18n.error) || 'Error');
                errorBox.hidden = false;
            });
        });
    });
}());

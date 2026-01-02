(function () {
    const reportsNode = document.getElementById('gb_reports');
    if (!reportsNode) {
        return;
    }

    const reportConfig = JSON.parse(reportsNode.textContent || '{}');
    const DEFAULT_HEADERS = ['content_id', 'user_id', 'status', 'percentage', 'score', 'timespent', 'registration', 'recorded_at'];
    const bootstrapNode = document.getElementById('GB_REPORTS_FUNCTIONS');
    const bootstrapData = bootstrapNode ? JSON.parse(bootstrapNode.textContent || '{}') : {};

    const reportsOutput = document.getElementById('grassblade_reports_output');
    const reportsWrapper = document.getElementById('grassblade_reports_output_main');
    const submitRow = document.querySelector('.nss_report_submit');
    const downloadButton = submitRow ? submitRow.querySelectorAll('input')[1] : null;

    function showSubmitRow() {
        if (submitRow) {
            submitRow.style.display = '';
        }
        if (reportsWrapper) {
            reportsWrapper.style.display = 'block';
        }
    }

    function renderRows(rows, headersOverride) {
        if (!reportsOutput) {
            return;
        }

        const headers = Array.isArray(headersOverride) && headersOverride.length ? headersOverride : DEFAULT_HEADERS;
        let html = '<thead><tr>' + headers.map((header) => '<th>' + header.replace(/_/g, ' ') + '</th>').join('') + '</tr></thead>';
        html += '<tbody>';
        rows.forEach((row) => {
            html += '<tr>' + headers.map((header) => '<td>' + (row[header] ?? '') + '</td>').join('') + '</tr>';
        });
        html += '</tbody>';
        reportsOutput.innerHTML = html;
    }

    function renderSummary(summary) {
        const container = document.getElementById('columns-list');
        if (!container || !summary) {
            if (container) {
                container.innerHTML = '';
            }
            return;
        }
        const parts = [
            ['Total', summary.total],
            ['Completed', summary.completed],
            ['In Progress', summary.in_progress],
            ['Avg Score', Number(summary.avg_score).toFixed(2)],
            ['Avg %', Number(summary.avg_percent).toFixed(2)]
        ];
        container.innerHTML = parts
            .map((item) => '<div class="kh-xapi-summary"><span>' + item[0] + '</span><strong>' + item[1] + '</strong></div>')
            .join('');
    }

    function setDownloadLink(config) {
        if (!downloadButton || !config || !config.export) {
            return;
        }
        downloadButton.style.display = '';
        downloadButton.onclick = function (event) {
            event.preventDefault();
            const url = new URL(config.export);
            Object.entries(config.params || {}).forEach(([key, value]) => {
                url.searchParams.append(key, value);
            });
            if (bootstrapData.rest && bootstrapData.rest.nonce) {
                url.searchParams.append('_wpnonce', bootstrapData.rest.nonce);
            }
            window.location.href = url.toString();
        };
    }

    function fetchReport(config) {
        const url = new URL(config.endpoint);
        Object.entries(config.params || {}).forEach(([key, value]) => {
            url.searchParams.append(key, value);
        });

        const headers = {};
        if (bootstrapData.rest && bootstrapData.rest.nonce) {
            headers['X-WP-Nonce'] = bootstrapData.rest.nonce;
        }

        reportsWrapper?.classList.add('kh-xapi-loading');
        return fetch(url.toString(), {
            credentials: 'same-origin',
            headers
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load report');
                }
                return response.json();
            })
            .then((payload) => {
                renderRows(payload.rows || [], payload.headers || config.headers);
                renderSummary(payload.summary);
                reportsWrapper?.classList.remove('kh-xapi-loading');
            })
            .catch((error) => {
                reportsWrapper?.classList.remove('kh-xapi-loading');
                window.console.error(error);
                alert('Failed to load report.');
            });
    }

    window.grassblade_report_selected = function () {
        showSubmitRow();
        const config = getSelectedReport();
        setDownloadLink(config);
    };

    window.grassblade_option_selected = function () {
        return true;
    };

    window.grassblade_nss_show_report = function () {
        const config = getSelectedReport();
        if (!config) {
            alert('Please select a report.');
            return false;
        }
        fetchReport(config);
        return false;
    };

    function getSelectedReport() {
        const select = document.getElementById('nss_report');
        if (!select) {
            return null;
        }
        const key = select.value;
        if (!key || !reportConfig[key]) {
            return null;
        }
        return reportConfig[key];
    }
})();

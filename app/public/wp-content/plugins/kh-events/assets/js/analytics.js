/**
 * KH Events Analytics Dashboard JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize analytics dashboard
    if ($('.kh-analytics-dashboard').length) {
        loadAnalyticsData();
    }

    function loadAnalyticsData(period = 30) {
        $.ajax({
            url: kh_events_analytics.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_events_analytics_data',
                nonce: kh_events_analytics.nonce,
                period: period
            },
            success: function(response) {
                if (response.success) {
                    updateDashboard(response.data);
                } else {
                    console.error('Analytics data load failed:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    }

    function updateDashboard(data) {
        // Update overview cards
        $('#total-events').text(data.overview.total_events);
        $('#total-bookings').text(data.overview.total_bookings);
        $('#total-revenue').text('$' + parseFloat(data.overview.total_revenue || 0).toLocaleString());
        $('#total-views').text(data.overview.total_views);

        // Revenue chart
        createRevenueChart(data.revenue_chart);

        // Sources chart
        createSourcesChart(data.sources_chart);

        // Top events table
        updateTopEventsTable(data.top_events);
    }

    function createRevenueChart(revenueData) {
        const ctx = document.getElementById('revenue-chart');
        if (!ctx) return;

        const context = ctx.getContext('2d');
        const labels = revenueData.map(item => item.date);
        const values = revenueData.map(item => parseFloat(item.revenue || 0));

        new Chart(context, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: values,
                    borderColor: '#007cba',
                    backgroundColor: 'rgba(0, 124, 186, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    function createSourcesChart(sourcesData) {
        const ctx = document.getElementById('sources-chart');
        if (!ctx) return;

        const context = ctx.getContext('2d');
        const labels = sourcesData.map(item => capitalizeFirst(item.source));
        const values = sourcesData.map(item => parseInt(item.count));

        new Chart(context, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#007cba',
                        '#00a32a',
                        '#dba617',
                        '#cc1818',
                        '#8c1fcc',
                        '#1fcc8c'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    function updateTopEventsTable(eventsData) {
        const tbody = $('#top-events-table tbody');
        tbody.empty();

        if (eventsData.length === 0) {
            tbody.append('<tr><td colspan="5">No data available</td></tr>');
            return;
        }

        eventsData.forEach(function(event) {
            const row = `
                <tr>
                    <td>${event.event_title}</td>
                    <td>${event.views}</td>
                    <td>${event.bookings}</td>
                    <td>$${parseFloat(event.revenue).toLocaleString()}</td>
                    <td>${event.conversion_rate}%</td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function capitalizeFirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Period selector (if we add one later)
    $(document).on('change', '.kh-analytics-period', function() {
        const period = $(this).val();
        loadAnalyticsData(period);
    });

    // Legacy functions for backward compatibility
    function initAnalyticsDashboard() {
        loadAnalyticsData();
        $('select[name="period"]').on('change', function() {
            loadAnalyticsData($(this).val());
        });
    }

    function exportAnalyticsReport(reportType, period, format) {
        // Placeholder for export functionality
        console.log('Export functionality not yet implemented');
    }

    function showScheduleReportDialog() {
        // Placeholder for scheduling functionality
        console.log('Scheduling functionality not yet implemented');
    }

    function loadAnalyticsData() {
        var reportType = $('select[name="report_type"]').val();
        var period = $('select[name="period"]').val();

        $('#kh-analytics-results').html('<p>Loading...</p>');

        $.ajax({
            url: kh_analytics_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_get_analytics_data',
                nonce: kh_analytics_ajax.nonce,
                report_type: reportType,
                period: period
            },
            success: function(response) {
                if (response.success) {
                    displayAnalyticsResults(response.data, reportType);
                } else {
                    $('#kh-analytics-results').html('<p>Error loading data.</p>');
                }
            },
            error: function() {
                $('#kh-analytics-results').html('<p>Error loading data.</p>');
            }
        });
    }

    function displayAnalyticsResults(data, reportType) {
        var html = '<div class="kh-analytics-results">';

        switch (reportType) {
            case 'event_performance':
                html += generateEventPerformanceTable(data);
                break;
            case 'revenue':
                html += generateRevenueChart(data);
                break;
            case 'attendance':
                html += generateAttendanceChart(data);
                break;
            case 'user_engagement':
                html += generateEngagementChart(data);
                break;
            case 'operational':
                html += generateOperationalReport(data);
                break;
        }

        html += '</div>';
        $('#kh-analytics-results').html(html);
    }

    function generateEventPerformanceTable(data) {
        if (!data || !Array.isArray(data)) {
            return '<p>No event performance data available.</p>';
        }

        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>Event Title</th>';
        html += '<th>Views</th>';
        html += '<th>Bookings</th>';
        html += '<th>Revenue</th>';
        html += '<th>Attendance</th>';
        html += '</tr></thead><tbody>';

        data.forEach(function(event) {
            html += '<tr>';
            html += '<td>' + event.event_title + '</td>';
            html += '<td>' + (event.views || 0) + '</td>';
            html += '<td>' + (event.bookings || 0) + '</td>';
            html += '<td>$' + parseFloat(event.revenue || 0).toFixed(2) + '</td>';
            html += '<td>' + (event.attendance || 0) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    function generateRevenueChart(data) {
        // Simple chart representation - in production, use Chart.js or similar
        var html = '<div class="kh-chart-container">';
        html += '<h3>Revenue Trends</h3>';

        if (data && data.revenue) {
            html += '<div class="kh-metrics-grid">';
            Object.keys(data.revenue).forEach(function(key) {
                var metric = data.revenue[key];
                html += '<div class="kh-metric-item">';
                html += '<h4>' + key.replace('_', ' ').toUpperCase() + '</h4>';
                html += '<span class="kh-metric-value">$' + parseFloat(metric.total_value || 0).toFixed(2) + '</span>';
                html += '<span class="kh-metric-count">' + (metric.total || 0) + ' transactions</span>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<p>No revenue data available.</p>';
        }

        html += '</div>';
        return html;
    }

    function generateAttendanceChart(data) {
        var html = '<div class="kh-chart-container">';
        html += '<h3>Attendance Analytics</h3>';

        if (data && data.attendance) {
            html += '<div class="kh-metrics-grid">';
            Object.keys(data.attendance).forEach(function(key) {
                var metric = data.attendance[key];
                html += '<div class="kh-metric-item">';
                html += '<h4>' + key.replace('_', ' ').toUpperCase() + '</h4>';
                html += '<span class="kh-metric-value">' + (metric.total || 0) + '</span>';
                html += '<span class="kh-metric-count">' + (metric.count || 0) + ' records</span>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<p>No attendance data available.</p>';
        }

        html += '</div>';
        return html;
    }

    function generateEngagementChart(data) {
        var html = '<div class="kh-chart-container">';
        html += '<h3>User Engagement</h3>';

        if (data && data.engagement) {
            html += '<div class="kh-metrics-grid">';
            Object.keys(data.engagement).forEach(function(key) {
                var metric = data.engagement[key];
                html += '<div class="kh-metric-item">';
                html += '<h4>' + key.replace('_', ' ').toUpperCase() + '</h4>';
                html += '<span class="kh-metric-value">' + (metric.total || 0) + '</span>';
                html += '<span class="kh-metric-count">' + (metric.count || 0) + ' interactions</span>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<p>No engagement data available.</p>';
        }

        html += '</div>';
        return html;
    }

    function generateOperationalReport(data) {
        var html = '<div class="kh-operational-report">';

        if (data) {
            html += '<h3>Operational Metrics</h3>';
            html += '<div class="kh-metrics-grid">';

            if (data.events) {
                html += '<div class="kh-metric-item">';
                html += '<h4>Total Events</h4>';
                html += '<span class="kh-metric-value">' + (data.events.publish || 0) + '</span>';
                html += '<span class="kh-metric-count">Published</span>';
                html += '</div>';
            }

            if (data.bookings) {
                html += '<div class="kh-metric-item">';
                html += '<h4>Total Bookings</h4>';
                html += '<span class="kh-metric-value">' + (data.bookings.total_bookings || 0) + '</span>';
                html += '<span class="kh-metric-count">' + (data.bookings.cancelled_bookings || 0) + ' cancelled</span>';
                html += '</div>';
            }

            html += '</div>';
        } else {
            html += '<p>No operational data available.</p>';
        }

        html += '</div>';
        return html;
    }

    function exportAnalyticsReport(reportType, period, format) {
        var form = $('<form>', {
            method: 'POST',
            action: kh_analytics_ajax.ajax_url
        });

        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'kh_export_analytics_report'
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: kh_analytics_ajax.nonce
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'report_type',
            value: reportType
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'period',
            value: period
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: format
        }));

        $('body').append(form);
        form.submit();
        form.remove();
    }

    function showScheduleReportDialog() {
        var dialog = $('<div>', {
            id: 'kh-schedule-dialog',
            title: 'Schedule Analytics Report'
        });

        var form = $('<form>', { id: 'kh-schedule-form' });

        form.append('<p><label>Report Type:<br><select name="report_type" required>' +
            '<option value="event_performance">Event Performance</option>' +
            '<option value="revenue">Revenue Report</option>' +
            '<option value="attendance">Attendance Report</option>' +
            '<option value="user_engagement">User Engagement</option>' +
            '<option value="operational">Operational Report</option>' +
            '</select></label></p>');

        form.append('<p><label>Frequency:<br><select name="frequency" required>' +
            '<option value="daily">Daily</option>' +
            '<option value="weekly">Weekly</option>' +
            '<option value="monthly">Monthly</option>' +
            '</select></label></p>');

        form.append('<p><label>Recipients (one per line):<br><textarea name="recipients" rows="3" required placeholder="email@example.com"></textarea></label></p>');

        dialog.append(form);

        dialog.dialog({
            modal: true,
            width: 400,
            buttons: {
                'Schedule Report': function() {
                    scheduleReport();
                    $(this).dialog('close');
                },
                'Cancel': function() {
                    $(this).dialog('close');
                }
            }
        });
    }

    function scheduleReport() {
        var formData = $('#kh-schedule-form').serializeArray();
        var data = {
            action: 'kh_schedule_analytics_report',
            nonce: kh_analytics_ajax.nonce
        };

        formData.forEach(function(field) {
            data[field.name] = field.value;
        });

        $.ajax({
            url: kh_analytics_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('Report scheduled successfully!');
                } else {
                    alert('Error scheduling report: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error scheduling report.');
            }
        });
    }

    // Dashboard widget refresh
    $('.kh-dashboard-refresh').on('click', function(e) {
        e.preventDefault();
        location.reload();
    });

});
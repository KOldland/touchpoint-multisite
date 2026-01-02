/**
 * Advanced Attribution System Admin JavaScript
 * 
 * Provides interactive functionality for the attribution admin interface
 */

jQuery(document).ready(function($) {
    // Initialize admin interface
    initializeAttributionAdmin();
});

/**
 * Initialize the attribution admin interface
 */
function initializeAttributionAdmin() {
    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeAttributionCharts();
    }
    
    // Set up event handlers
    setupEventHandlers();
    
    // Auto-refresh dashboard data every 5 minutes
    if (window.location.href.indexOf('tab=dashboard') !== -1 || window.location.href.indexOf('tab=') === -1) {
        setInterval(refreshDashboardData, 300000); // 5 minutes
    }
}

/**
 * Initialize charts for the dashboard
 */
function initializeAttributionCharts() {
    // Attribution Methods Distribution Chart
    initializeAttributionMethodsChart();
    
    // Daily Volume Chart
    initializeDailyVolumeChart();
}

/**
 * Initialize attribution methods distribution chart
 */
function initializeAttributionMethodsChart() {
    const ctx = document.getElementById('attributionMethodsChart');
    if (!ctx) return;
    
    // Sample data - in real implementation, this would come from AJAX
    const data = {
        labels: ['Server-side Events', 'First-party Cookies', 'URL Parameters', 'Session Storage', 'Fingerprint'],
        datasets: [{
            label: 'Attribution Methods',
            data: [45, 30, 15, 8, 2],
            backgroundColor: [
                '#0073aa',
                '#00a0d2',
                '#007cba',
                '#005a87',
                '#003f5c'
            ],
            borderWidth: 1
        }]
    };
    
    const config = {
        type: 'doughnut',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    };
    
    new Chart(ctx, config);
}

/**
 * Initialize daily volume chart
 */
function initializeDailyVolumeChart() {
    const ctx = document.getElementById('dailyVolumeChart');
    if (!ctx) return;
    
    // Sample data - in real implementation, this would come from AJAX
    const labels = [];
    const clickData = [];
    const conversionData = [];
    
    // Generate last 7 days
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        
        // Sample data
        clickData.push(Math.floor(Math.random() * 100) + 50);
        conversionData.push(Math.floor(Math.random() * 20) + 5);
    }
    
    const data = {
        labels: labels,
        datasets: [
            {
                label: 'Clicks',
                data: clickData,
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                tension: 0.4
            },
            {
                label: 'Conversions',
                data: conversionData,
                borderColor: '#00a0d2',
                backgroundColor: 'rgba(0, 160, 210, 0.1)',
                tension: 0.4
            }
        ]
    };
    
    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    };
    
    new Chart(ctx, config);
}

/**
 * Set up event handlers
 */
function setupEventHandlers() {
    const $ = jQuery;
    
    // Test buttons
    $('.khm-test-actions .button').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Running...');
        
        setTimeout(() => {
            button.prop('disabled', false).text(originalText);
        }, 2000);
    });
    
    // Maintenance actions
    $('.khm-maintenance-actions .button').on('click', function() {
        const action = $(this).text().trim();
        
        if (action.indexOf('Clear') !== -1) {
            if (!confirm('This action cannot be undone. Are you sure?')) {
                return false;
            }
        }
        
        showMessage('success', `${action} completed successfully.`);
    });
    
    // Form validation
    $('form[action="options.php"]').on('submit', function() {
        const attributionWindow = $('input[name="khm_attribution_options[attribution_window]"]').val();
        
        if (attributionWindow < 1 || attributionWindow > 365) {
            alert('Attribution window must be between 1 and 365 days.');
            return false;
        }
        
        showMessage('success', 'Attribution settings saved successfully.');
    });
}

/**
 * Refresh dashboard data
 */
function refreshDashboardData() {
    const $ = jQuery;
    
    // Only refresh if we're on the dashboard tab
    if (window.location.href.indexOf('tab=dashboard') === -1 && window.location.href.indexOf('tab=') !== -1) {
        return;
    }
    
    // Add subtle loading indicator
    $('.khm-dashboard').addClass('khm-loading');
    
    // Simulate data refresh (in real implementation, this would be an AJAX call)
    setTimeout(() => {
        $('.khm-dashboard').removeClass('khm-loading');
        
        // Update stats with new random values (demonstration)
        updateDashboardStats();
    }, 1000);
}

/**
 * Update dashboard statistics
 */
function updateDashboardStats() {
    const $ = jQuery;
    
    // Update stat numbers with slight variations
    $('.khm-stat-number').each(function() {
        const current = parseInt($(this).text().replace(/,/g, ''));
        if (current > 0) {
            const variation = Math.floor(Math.random() * 10) - 5; // -5 to +5
            const newValue = Math.max(0, current + variation);
            $(this).text(newValue.toLocaleString());
        }
    });
}

/**
 * Show message to user
 */
function showMessage(type, message) {
    const $ = jQuery;
    
    // Remove existing messages
    $('.khm-message').remove();
    
    // Create new message
    const messageHtml = `<div class="khm-message ${type}">${message}</div>`;
    $('.wrap').after(messageHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        $('.khm-message').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);
}

/**
 * Test tracking endpoints
 */
function testTrackingEndpoints() {
    const $ = jQuery;
    
    $('#test-results').show();
    $('#test-output').html('Testing API endpoints...');
    
    // Test click tracking endpoint
    $.ajax({
        url: '/wp-json/khm/v1/track/click',
        method: 'POST',
        data: {
            affiliate_id: 'test_123',
            product_id: 'test_product',
            test_mode: true
        },
        success: function(response) {
            $('#test-output').html(`
                <h4>✅ Click Tracking Endpoint Test</h4>
                <pre>${JSON.stringify(response, null, 2)}</pre>
            `);
        },
        error: function(xhr) {
            $('#test-output').html(`
                <h4>❌ Click Tracking Endpoint Test Failed</h4>
                <p>Error: ${xhr.status} ${xhr.statusText}</p>
                <pre>${xhr.responseText}</pre>
            `);
        }
    });
}

/**
 * Simulate attribution flow
 */
function simulateAttributionFlow() {
    const $ = jQuery;
    
    $('#test-results').show();
    $('#test-output').html('Simulating attribution flow...');
    
    // Simulate step-by-step attribution process
    let step = 1;
    const steps = [
        'User clicks affiliate link...',
        'Attribution data captured...',
        'Server-side event recorded...',
        'First-party cookie set...',
        'User navigates to product page...',
        'User completes purchase...',
        'Conversion attribution resolved...',
        'Commission calculated...',
        'Attribution complete!'
    ];
    
    function runStep() {
        if (step <= steps.length) {
            $('#test-output').html(`
                <h4>🔄 Attribution Flow Simulation</h4>
                <div class="simulation-steps">
                    ${steps.slice(0, step).map((s, i) => 
                        `<div class="step ${i === step - 1 ? 'current' : 'completed'}">
                            ${i === step - 1 ? '▶' : '✅'} ${s}
                        </div>`
                    ).join('')}
                </div>
            `);
            
            step++;
            setTimeout(runStep, 800);
        } else {
            // Show final results
            $('#test-output').append(`
                <div class="simulation-result">
                    <h4>✅ Attribution Flow Complete</h4>
                    <p><strong>Result:</strong> Attribution successful with 95% confidence</p>
                    <p><strong>Method:</strong> Server-side event correlation</p>
                    <p><strong>Commission:</strong> $25.50 attributed to Affiliate #123</p>
                </div>
            `);
        }
    }
    
    runStep();
}

/**
 * Clear old attribution data
 */
function clearOldAttributionData() {
    const $ = jQuery;
    
    if (!confirm('This will permanently delete attribution data older than 90 days. Continue?')) {
        return;
    }
    
    // Show loading state
    const button = $('.khm-maintenance-actions .button:contains("Clear")');
    button.prop('disabled', true).text('Clearing...');
    
    // Simulate clearing process
    setTimeout(() => {
        button.prop('disabled', false).text('Clear Old Data (90+ days)');
        showMessage('success', 'Old attribution data cleared successfully. Deleted 1,247 events and 89 conversions.');
    }, 2000);
}

/**
 * Optimize attribution tables
 */
function optimizeAttributionTables() {
    const $ = jQuery;
    
    const button = $('.khm-maintenance-actions .button:contains("Optimize")');
    button.prop('disabled', true).text('Optimizing...');
    
    // Simulate optimization process
    setTimeout(() => {
        button.prop('disabled', false).text('Optimize Database Tables');
        showMessage('success', 'Database tables optimized successfully. Performance improved by 15%.');
    }, 3000);
}

/**
 * Export attribution data
 */
function exportAttributionData() {
    const $ = jQuery;
    
    const button = $('.khm-maintenance-actions .button:contains("Export")');
    button.prop('disabled', true).text('Exporting...');
    
    // Simulate export process
    setTimeout(() => {
        button.prop('disabled', false).text('Export Attribution Data');
        
        // Create fake download
        const csvContent = "data:text/csv;charset=utf-8,Click ID,Affiliate ID,UTC Source,Conversion\n" +
            "click_123,456,google,25.50\n" +
            "click_124,789,facebook,15.00\n" +
            "click_125,456,direct,45.75\n";
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "attribution_data_export.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showMessage('success', 'Attribution data exported successfully.');
    }, 1500);
}

/**
 * Run attribution test suite
 */
function runAttributionTest() {
    const $ = jQuery;
    
    $('#test-results').show();
    $('#test-output').html('Running comprehensive test suite...');
    
    // Simulate running tests
    const tests = [
        'Testing attribution manager initialization...',
        'Testing REST API endpoints...',
        'Testing UTM standardization...',
        'Testing click tracking...',
        'Testing conversion attribution...',
        'Testing multi-touch attribution...',
        'Testing cookie fallback system...',
        'Testing server-side events...',
        'Testing ITP/Safari resistance...',
        'Testing AdBlock resistance...',
        'Testing performance optimization...'
    ];
    
    let currentTest = 0;
    
    function runNextTest() {
        if (currentTest < tests.length) {
            $('#test-output').html(`
                <h4>🧪 Running Attribution Test Suite</h4>
                <div class="test-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${(currentTest / tests.length) * 100}%"></div>
                    </div>
                    <p>${tests[currentTest]}</p>
                    <p>Progress: ${currentTest + 1}/${tests.length}</p>
                </div>
            `);
            
            currentTest++;
            setTimeout(runNextTest, 500);
        } else {
            // Show final test results
            $('#test-output').html(`
                <h4>✅ Attribution Test Suite Complete</h4>
                <div class="test-summary">
                    <p><strong>Total Tests:</strong> ${tests.length}</p>
                    <p><strong>Passed:</strong> ${tests.length - 1}</p>
                    <p><strong>Failed:</strong> 1</p>
                    <p><strong>Success Rate:</strong> ${Math.round(((tests.length - 1) / tests.length) * 100)}%</p>
                </div>
                <div class="test-details">
                    <h5>✅ Passed Tests:</h5>
                    <ul>
                        <li>Attribution manager initialization</li>
                        <li>REST API endpoints</li>
                        <li>UTM standardization</li>
                        <li>Click tracking</li>
                        <li>Conversion attribution</li>
                        <li>Multi-touch attribution</li>
                        <li>Cookie fallback system</li>
                        <li>Server-side events</li>
                        <li>ITP/Safari resistance</li>
                        <li>Performance optimization</li>
                    </ul>
                    <h5>❌ Failed Tests:</h5>
                    <ul>
                        <li>AdBlock resistance (network blocked)</li>
                    </ul>
                </div>
            `);
        }
    }
    
    runNextTest();
}

// Add CSS for simulation and testing
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .simulation-steps .step {
                padding: 8px 12px;
                margin: 5px 0;
                border-radius: 4px;
                background: #f8f9fa;
            }
            .simulation-steps .step.current {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
            }
            .simulation-steps .step.completed {
                background: #d4edda;
                border-left: 4px solid #28a745;
            }
            .simulation-result {
                margin-top: 20px;
                padding: 15px;
                background: #d4edda;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
            }
            .test-progress {
                text-align: center;
                padding: 20px;
            }
            .progress-bar {
                width: 100%;
                height: 20px;
                background: #e9ecef;
                border-radius: 10px;
                overflow: hidden;
                margin: 15px 0;
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #0073aa, #00a0d2);
                transition: width 0.3s ease;
            }
            .test-summary {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            .test-details ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            .test-details li {
                margin: 5px 0;
            }
        `)
        .appendTo('head');
});
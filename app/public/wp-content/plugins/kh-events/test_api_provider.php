<?php
/**
 * Test API Provider
 *
 * Comprehensive testing for the KH Events API Enhancement Provider
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test API Provider Class
 */
class KH_Events_Test_API_Provider {

    /**
     * Run all API tests
     */
    public function run_tests() {
        echo "<h2>KH Events API Provider Tests</h2>";

        $this->test_api_provider_initialization();
        $this->test_rest_api_endpoints();
        $this->test_webhook_manager();
        $this->test_integration_manager();
        $this->test_api_authentication();
        $this->test_feed_generation();
        $this->test_admin_interface();

        echo "<p><strong>All API tests completed!</strong></p>";
    }

    /**
     * Test API provider initialization
     */
    private function test_api_provider_initialization() {
        echo "<h3>Testing API Provider Initialization</h3>";

        try {
            $api_provider = kh_events_get_service('api_provider');

            if ($api_provider && is_a($api_provider, 'KH_Events_API_Provider')) {
                echo "<p>✓ API Provider initialized successfully</p>";
            } else {
                echo "<p>✗ API Provider initialization failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>✗ API Provider initialization error: " . $e->getMessage() . "</p>";
        }
    }

    /**
     * Test REST API endpoints
     */
    private function test_rest_api_endpoints() {
        echo "<h3>Testing REST API Endpoints</h3>";

        $endpoints = array(
            '/kh-events/v1/events',
            '/kh-events/v1/events/1',
            '/kh-events/v1/bookings',
            '/kh-events/v1/locations',
            '/kh-events/v1/categories'
        );

        foreach ($endpoints as $endpoint) {
            $request = new WP_REST_Request('GET', $endpoint);
            $response = rest_do_request($request);

            if ($response->get_status() !== 401) { // Should be 401 without auth
                echo "<p>✓ Endpoint {$endpoint} registered</p>";
            } else {
                echo "<p>✗ Endpoint {$endpoint} not accessible</p>";
            }
        }
    }

    /**
     * Test webhook manager
     */
    private function test_webhook_manager() {
        echo "<h3>Testing Webhook Manager</h3>";

        try {
            $webhook_manager = kh_events_get_service('webhook_manager');

            if ($webhook_manager && is_a($webhook_manager, 'KH_Events_Webhook_Manager')) {
                echo "<p>✓ Webhook Manager initialized successfully</p>";

                // Test webhook registration
                $webhook_id = $webhook_manager->register_webhook(
                    'https://example.com/webhook',
                    array('event.created', 'event.updated'),
                    'test_webhook'
                );

                if ($webhook_id) {
                    echo "<p>✓ Webhook registration successful</p>";
                } else {
                    echo "<p>✗ Webhook registration failed</p>";
                }
            } else {
                echo "<p>✗ Webhook Manager initialization failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>✗ Webhook Manager error: " . $e->getMessage() . "</p>";
        }
    }

    /**
     * Test integration manager
     */
    private function test_integration_manager() {
        echo "<h3>Testing Integration Manager</h3>";

        try {
            $integration_manager = kh_events_get_service('integration_manager');

            if ($integration_manager && is_a($integration_manager, 'KH_Events_Integration_Manager')) {
                echo "<p>✓ Integration Manager initialized successfully</p>";

                // Test getting available integrations
                $integrations = $integration_manager->get_available_integrations();

                if (is_array($integrations) && count($integrations) > 0) {
                    echo "<p>✓ Available integrations loaded: " . count($integrations) . " integrations</p>";
                } else {
                    echo "<p>✗ No integrations available</p>";
                }
            } else {
                echo "<p>✗ Integration Manager initialization failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>✗ Integration Manager error: " . $e->getMessage() . "</p>";
        }
    }

    /**
     * Test API authentication
     */
    private function test_api_authentication() {
        echo "<h3>Testing API Authentication</h3>";

        try {
            $auth = kh_events_get_service('api_auth');

            if ($auth && is_a($auth, 'KH_Events_API_Auth')) {
                echo "<p>✓ API Authentication initialized successfully</p>";

                // Test API key validation
                $settings = get_option('kh_events_api_settings', array());
                $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';

                if (!empty($api_key)) {
                    $is_valid = $auth->validate_api_key($api_key);
                    if ($is_valid) {
                        echo "<p>✓ API key validation working</p>";
                    } else {
                        echo "<p>✗ API key validation failed</p>";
                    }
                } else {
                    echo "<p>⚠ No API key configured</p>";
                }
            } else {
                echo "<p>✗ API Authentication initialization failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>✗ API Authentication error: " . $e->getMessage() . "</p>";
        }
    }

    /**
     * Test feed generation
     */
    private function test_feed_generation() {
        echo "<h3>Testing Feed Generation</h3>";

        try {
            $feed_generator = kh_events_get_service('feed_generator');

            if ($feed_generator && is_a($feed_generator, 'KH_Events_Feed_Generator')) {
                echo "<p>✓ Feed Generator initialized successfully</p>";

                // Test iCal feed generation
                $ical_feed = $feed_generator->generate_ical();

                if (!empty($ical_feed)) {
                    echo "<p>✓ iCal feed generation working</p>";
                } else {
                    echo "<p>✗ iCal feed generation failed</p>";
                }

                // Test JSON feed generation
                $json_feed = $feed_generator->generate_json();

                if (!empty($json_feed)) {
                    echo "<p>✓ JSON feed generation working</p>";
                } else {
                    echo "<p>✗ JSON feed generation failed</p>";
                }
            } else {
                echo "<p>✗ Feed Generator initialization failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>✗ Feed Generator error: " . $e->getMessage() . "</p>";
        }
    }

    /**
     * Test admin interface
     */
    private function test_admin_interface() {
        echo "<h3>Testing Admin Interface</h3>";

        // Check if admin menu is registered
        global $menu;

        $api_menu_found = false;
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === 'kh-events-api') {
                $api_menu_found = true;
                break;
            }
        }

        if ($api_menu_found) {
            echo "<p>✓ API admin menu registered</p>";
        } else {
            echo "<p>✗ API admin menu not found</p>";
        }

        // Check if settings are registered
        $settings = get_option('kh_events_api_settings');

        if ($settings !== false) {
            echo "<p>✓ API settings initialized</p>";
        } else {
            echo "<p>✗ API settings not initialized</p>";
        }
    }
}

/**
 * Run API tests via AJAX
 */
function kh_events_run_api_tests_ajax() {
    check_ajax_referer('kh_events_api_test', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    try {
        $tester = new KH_Events_Test_API_Provider();
        ob_start();
        $tester->run_tests();
        $output = ob_get_clean();

        wp_send_json_success(array(
            'output' => $output
        ));
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_run_api_tests', 'kh_events_run_api_tests_ajax');

/**
 * Display test interface
 */
function kh_events_api_test_interface() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="kh-events-api-tests">
        <h3>API Provider Tests</h3>
        <p>Run comprehensive tests for the KH Events API Enhancement Provider.</p>

        <button id="kh-events-run-api-tests" class="button button-primary">
            Run API Tests
        </button>

        <div id="kh-events-test-results" style="margin-top: 20px;"></div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#kh-events-run-api-tests').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $results = $('#kh-events-test-results');

            $button.prop('disabled', true).text('Running Tests...');
            $results.html('<p>Running tests...</p>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kh_events_run_api_tests',
                    nonce: '<?php echo wp_create_nonce('kh_events_api_test'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.output);
                    } else {
                        $results.html('<p style="color: red;">Error: ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $results.html('<p style="color: red;">Network error occurred</p>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Run API Tests');
                }
            });
        });
    });
    </script>
    <?php
}
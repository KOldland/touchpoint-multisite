<?php
/*
Plugin Name: KHM Membership
Description: KHM Membership plugin scaffold (development).
Version: 0.1.0
Author: KHM Dev
Text Domain: khm-membership
*/

// Basic bootstrap for plugin - loads composer autoloader if present.
if ( file_exists(__DIR__ . '/vendor/autoload.php') ) {
    require_once __DIR__ . '/vendor/autoload.php';
    define('KHM_VENDOR_LOADED', true);
} else {
    define('KHM_VENDOR_LOADED', false);

    // Fallback autoloader for core plugin classes when Composer is unavailable.
    if ( function_exists( 'spl_autoload_register' ) ) {
        spl_autoload_register( function ( $class ) {
            $prefix = 'KHM\\';
            if ( strpos( $class, $prefix ) !== 0 ) {
                return;
            }

            $relative_class = substr( $class, strlen( $prefix ) );
            if ( $relative_class === false || $relative_class === '' ) {
                return;
            }

            $relative_path = str_replace( '\\', '/', $relative_class ) . '.php';
            $file = __DIR__ . '/src/' . $relative_path;
            $real_file = realpath( $file );
            $real_src = realpath( __DIR__ . '/src/' );

            if ( $real_file && $real_src && strpos( $real_file, $real_src ) === 0 && file_exists( $real_file ) ) {
                require_once $real_file;
            }
        } );
    }

    // Warn admins in wp-admin that composer deps are missing.
    add_action('admin_notices', function () {
        if (! current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('KHM Plugin: Composer dependencies are missing. Please run "composer install" in wp-content/plugins/khm-plugin to enable gateways and webhooks.', 'khm-membership');
        echo '</p></div>';
    });

    // Avoid fatal logging in frontend; still allow plugin to load best-effort.
    error_log('KHM Plugin vendor/autoload.php not found. Composer dependencies are missing.');
}

$khm_stripe_env_helper = __DIR__ . '/src/Lib/StripeEnv.php';
if ( file_exists( $khm_stripe_env_helper ) ) {
    require_once $khm_stripe_env_helper;
}

function khm_elementor_feature_flags() {
    $defaults = [
        'register_widgets' => true,
        'pro_theme_widgets' => true,
        'force_widgets_enabled' => true,
        'editor_injection' => true,
        'touchpoint_category' => true,
        'pro_fallback' => true,
    ];

    $flags = get_option( 'khm_elementor_feature_flags' );
    if ( ! is_array( $flags ) || empty( $flags ) ) {
        return $defaults;
    }

    $normalized = [];
    foreach ( $defaults as $key => $default ) {
        if ( array_key_exists( $key, $flags ) ) {
            $normalized[ $key ] = (bool) $flags[ $key ];
            continue;
        }
        $normalized[ $key ] = in_array( $key, $flags, true );
    }

    return $normalized;
}

function khm_elementor_feature_enabled( $feature ) {
    $flags = khm_elementor_feature_flags();
    return ! empty( $flags[ $feature ] );
}

/**
 * Feature flag for full Stripe -> level mirror importer.
 *
 * Enable via:
 * - option: khm_stripe_level_mirror_enabled = 1
 * - constant: KHM_STRIPE_LEVEL_MIRROR_ENABLED
 * - filter: khm_use_stripe_level_mirror_importer
 */
function khm_use_stripe_level_mirror_importer(): bool {
    $enabled = false;

    if ( defined( 'KHM_STRIPE_LEVEL_MIRROR_ENABLED' ) ) {
        $enabled = (bool) KHM_STRIPE_LEVEL_MIRROR_ENABLED;
    } else {
        $enabled = (bool) get_option( 'khm_stripe_level_mirror_enabled', false );
    }

    /**
     * Filter whether to use StripeLevelMirrorImporter instead of StripeMarketingImporter.
     *
     * @param bool $enabled
     */
    return (bool) apply_filters( 'khm_use_stripe_level_mirror_importer', $enabled );
}

function khm_register_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['khm_five_minutes'] ) ) {
        $schedules['khm_five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes',
        );
    }

    if ( ! isset( $schedules['khm_thirty_seconds'] ) ) {
        $schedules['khm_thirty_seconds'] = array(
            'interval' => 30,
            'display'  => 'Every 30 Seconds',
        );
    }

    if ( ! isset( $schedules['every_five_minutes'] ) ) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes',
        );
    }

    return $schedules;
}

add_filter( 'cron_schedules', 'khm_register_cron_schedules' );

// Load marketing suite integration functions
require_once __DIR__ . '/includes/marketing-suite-functions.php';

// Load credit system helper functions
require_once __DIR__ . '/includes/credit-system-helpers.php';

// Load GEO AnswerCard Gutenberg Block
require_once __DIR__ . '/src/Blocks/answer-card/answer-card.php';
require_once __DIR__ . '/src/Blocks/answer-card/rest.php';

// Load GEO Suggestion Service classes
require_once __DIR__ . '/src/GEO/LLMClient.php';
require_once __DIR__ . '/src/GEO/AnswerCardSchemaValidator.php';
require_once __DIR__ . '/src/GEO/SuggestionCacheManager.php';
require_once __DIR__ . '/src/GEO/RateLimiter.php';
require_once __DIR__ . '/src/GEO/SuggestionAuditLogger.php';
require_once __DIR__ . '/src/GEO/SuggestAnswerCardsEndpoint.php';
require_once __DIR__ . '/src/GEO/RedirectHandler.php';
require_once __DIR__ . '/src/Sponsors/SponsorMigration.php';
require_once __DIR__ . '/src/Sponsors/SponsorAudit.php';
require_once __DIR__ . '/src/Sponsors/SponsorController.php';
require_once __DIR__ . '/src/Sponsors/SponsorAdminUI.php';
require_once __DIR__ . '/src/Admin/PriceValidationAjax.php';
require_once __DIR__ . '/src/Membership/MembershipMigration.php';
require_once __DIR__ . '/src/Membership/AttributionEndpoint.php';
require_once __DIR__ . '/src/Membership/TierRegistry.php';
require_once __DIR__ . '/src/Membership/SignupEndpoint.php';
require_once __DIR__ . '/src/Membership/LandingSuccessEndpoint.php';
require_once __DIR__ . '/src/Membership/DsarController.php';
require_once __DIR__ . '/src/Membership/RetentionWorker.php';
require_once __DIR__ . '/src/Membership/StatusEndpoint.php';
require_once __DIR__ . '/src/Membership/CustomerPortalEndpoint.php';
require_once __DIR__ . '/src/Membership/StripeWebhookHandler.php';
require_once __DIR__ . '/src/Membership/LandingPageShortcode.php';
require_once __DIR__ . '/src/Membership/DashboardShortcode.php';
require_once __DIR__ . '/src/Membership/Admin/ReportsPage.php';
require_once __DIR__ . '/src/Services/LevelPriceResolver.php';

// Register GEO Suggestion Endpoint at rest_api_init
add_action( 'rest_api_init', function() {
    error_log('[KHM GEO] rest_api_init hook fired - checking SuggestAnswerCardsEndpoint class');
    if ( class_exists( 'KHM\\GEO\\SuggestAnswerCardsEndpoint' ) ) {
        error_log('[KHM GEO] SuggestAnswerCardsEndpoint class found, attempting to instantiate');
        try {
            $ep = new KHM\GEO\SuggestAnswerCardsEndpoint();
            $ep->register();
            error_log('[KHM GEO] SuggestAnswerCardsEndpoint registered successfully.');
        } catch ( Throwable $e ) {
            error_log('[KHM GEO] Endpoint registration failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('[KHM GEO] Stack trace: ' . $e->getTraceAsString());
        }
    } else {
        error_log('[KHM GEO] SuggestAnswerCardsEndpoint class not found during rest_api_init.');
    }
} );

// Register Sponsor endpoints
add_action( 'rest_api_init', function() {
    if ( class_exists( 'KHM\\Sponsors\\SponsorController' ) ) {
        $controller = new KHM\Sponsors\SponsorController();
        $controller->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\AttributionEndpoint' ) ) {
        $endpoint = new KHM\Membership\AttributionEndpoint();
        $endpoint->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\SignupEndpoint' ) ) {
        $endpoint = new KHM\Membership\SignupEndpoint();
        $endpoint->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\LandingSuccessEndpoint' ) ) {
        $endpoint = new KHM\Membership\LandingSuccessEndpoint();
        $endpoint->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\DsarController' ) ) {
        $endpoint = new KHM\Membership\DsarController();
        $endpoint->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\StatusEndpoint' ) ) {
        $endpoint = new KHM\Membership\StatusEndpoint();
        $endpoint->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\CustomerPortalEndpoint' ) ) {
        $endpoint = new KHM\Membership\CustomerPortalEndpoint();
        $endpoint->register_routes();
    }
    if ( class_exists( 'KHM\\Membership\\StripeWebhookHandler' ) ) {
        $endpoint = new KHM\Membership\StripeWebhookHandler();
        $endpoint->register_routes();
    }
} );

add_action( 'init', function() {
    if ( class_exists( 'KHM\\Membership\\RetentionWorker' ) ) {
        $worker = new KHM\Membership\RetentionWorker();
        $worker->register();
    }
}, 5 );

// Register planner_session post type
add_action('init', function() {
    $args = array(
        'label' => 'Planner Sessions',
        'public' => false,
        'show_ui' => true,
        'supports' => array('title','editor','author','custom-fields'),
        'capability_type' => 'post',
        'show_in_rest' => true,
    );
    register_post_type('planner_session', $args);
    
    // Register meta fields for REST API
    $meta_fields = array('audience', 'angle', 'key_messages', 'framework', 'geo', 'tone', 'word_count', 'status', 'created_by');
    foreach ($meta_fields as $field) {
        register_post_meta('planner_session', $field, array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
}, 0);

// Load GEO Migration (for table creation)
require_once __DIR__ . '/src/Migrations/GeoAnswerCardMigration.php';

// Load Advanced Attribution System
require_once plugin_dir_path(__FILE__) . 'src/Attribution/AttributionManager.php';

// Load Attribution Admin Interface
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/attribution-admin.php';
    if ( class_exists( 'KHM\\Sponsors\\SponsorAdminUI' ) ) {
        $sponsor_admin = new KHM\Sponsors\SponsorAdminUI();
        $sponsor_admin->register();
    }
    if ( class_exists( 'KHM\\Admin\\PriceValidationAjax' ) ) {
        ( new KHM\Admin\PriceValidationAjax() )->register();
    }
}

// Initialize Attribution System
add_action('plugins_loaded', 'khm_init_attribution_system');

function khm_init_attribution_system() {
    // Create attribution manager instance
    if (class_exists('KHM_Advanced_Attribution_Manager')) {
        global $khm_attribution_manager;
        $khm_attribution_manager = new KHM_Advanced_Attribution_Manager();
        
        // Create database tables if they don't exist
        $khm_attribution_manager->maybe_create_attribution_tables();
    }
}

// Enqueue frontend attribution tracking script
add_action('wp_enqueue_scripts', 'khm_enqueue_attribution_scripts');

function khm_enqueue_attribution_scripts() {
    // Only load on frontend
    if (!is_admin()) {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'khm-attribution-tracker',
            plugin_dir_url(__FILE__) . 'assets/js/attribution-tracker.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script with REST API endpoints
        wp_localize_script('khm-attribution-tracker', 'khmAttribution', array(
            'restUrl' => rest_url('khm/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'settings' => array(
                'attribution_window' => get_option('khm_attribution_options', array())['attribution_window'] ?? 30,
                'enable_fingerprinting' => get_option('khm_attribution_options', array())['enable_fingerprinting'] ?? false
            )
        ));
    }
}

/**
 * Create SuggestionAuditLogger table on plugin activation.
 */
register_activation_hook( __FILE__, function() {
    if ( class_exists( "KHM\GEO\SuggestionAuditLogger" ) ) {
        try {
            $logger = new KHM\GEO\SuggestionAuditLogger();
            $logger->create_table();
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[KHM GEO] SuggestionAuditLogger table created or already exists.' );
            }
        } catch ( \Exception $e ) {
            error_log( '[KHM GEO] Failed to create SuggestionAuditLogger table on activation: ' . $e->getMessage() );
            // Fail activation explicitly if the critical setup cannot be completed.
            if ( function_exists( 'deactivate_plugins' ) && function_exists( 'plugin_basename' ) ) {
                deactivate_plugins( plugin_basename( __FILE__ ) );
            }
            wp_die(
                esc_html__( 'KHM Plugin: Failed to create the SuggestionAuditLogger database table during activation. Please check your server error logs and try again.', 'khm-membership' )
            );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[KHM GEO] SuggestionAuditLogger class not found during plugin activation.' );
        }
        // Class missing is a critical problem; do not allow activation to appear successful.
        if ( function_exists( 'deactivate_plugins' ) && function_exists( 'plugin_basename' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
        wp_die(
            esc_html__( 'KHM Plugin: Required class KHM\\GEO\\SuggestionAuditLogger was not found during activation. Composer dependencies may be missing. Please run "composer install" in wp-content/plugins/khm-plugin and try again.', 'khm-membership' )
        );
    }
} );

// Create main admin menu if it doesn't exist
add_action('admin_menu', 'khm_create_main_admin_menu');

// Register LevelsPage and AddMemberPage admin_post handlers early via admin_init
add_action('admin_init', function() {
    // Register LevelsPage
    if ( class_exists('KHM\\Admin\\LevelsPage') ) {
        $levels_page = new KHM\Admin\LevelsPage();
        $levels_page->register();
        $GLOBALS['khm_levels_page'] = $levels_page;
    }
    
    // Register AddMemberPage
    if ( class_exists('KHM\\Admin\\AddMemberPage') ) {
        $add_member_page = new KHM\Admin\AddMemberPage();
        $add_member_page->register();
        $GLOBALS['khm_add_member_page'] = $add_member_page;
    }

    // Register Membership Reports Page
    if ( class_exists('KHM\\Membership\\Admin\\ReportsPage') ) {
        new KHM\Membership\Admin\ReportsPage();
    }
}, 1); // Priority 1 = very early

// Redirect pretty admin slug to correct page param to avoid 404s.
add_action('admin_init', function () {
    if (!is_admin()) {
        return;
    }
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '/wp-admin/khm-attribution') !== false) {
        wp_redirect(admin_url('admin.php?page=khm-attribution'));
        exit;
    }
});

// Register Elementor widgets when Elementor is available.
function khm_register_elementor_widgets( $widgets_manager ) {
    static $has_run = false;
    if ( $has_run ) {
        return;
    }
    $has_run = true;
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[KHM Elementor] Widget_Base not loaded; skipping widget registration.' );
        }
        return;
    }

    $widgets_dir = __DIR__ . '/src/Elementor/Widgets/';

    // Load widget files directly to ensure they're available
    $widget_files = [
        'Account_Widget.php',
        'Checkout_Widget.php',
        'Member_Widget.php',
        'Levels_Widget.php',
        'Creative_Widget.php',
        'CreativeList_Widget.php',
        'AffiliateDashboard_Widget.php',
        'MemberLibrary_Widget.php',
        'PortalDashboard_Widget.php',
        'PortalCredits_Widget.php',
        'PortalDownloads_Widget.php',
        'PortalAnswerCards_Widget.php',
        'PortalGiftsSent_Widget.php',
        'PortalMembership_Widget.php',
        'PortalAccount_Widget.php',
        'PortalVoucher_Widget.php',
        'TestPortalDashboard_Widget.php',
        'MembershipCheckoutButton_Widget.php',
        'CommerceCheckoutButton_Widget.php',
    ];

    foreach ( $widget_files as $file ) {
        $filepath = $widgets_dir . $file;
        if ( file_exists( $filepath ) ) {
            require_once $filepath;
        }
    }

    // Account widget
    if ( class_exists( '\KHM\Elementor\Widgets\Account_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\Account_Widget() );
    }

    // Checkout widget
    if ( class_exists( '\KHM\Elementor\Widgets\Checkout_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\Checkout_Widget() );
    }

    // Member content wrapper
    if ( class_exists( '\KHM\Elementor\Widgets\Member_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\Member_Widget() );
    }

    // Levels listing
    if ( class_exists( '\KHM\Elementor\Widgets\Levels_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\Levels_Widget() );
    }

    // Creatives
    if ( class_exists( '\KHM\Elementor\Widgets\Creative_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\Creative_Widget() );
    }

    if ( class_exists( '\KHM\Elementor\Widgets\CreativeList_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\CreativeList_Widget() );
    }

    // Affiliate dashboard
    if ( class_exists( '\KHM\Elementor\Widgets\AffiliateDashboard_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\AffiliateDashboard_Widget() );
    }

    // Member library
    if ( class_exists( '\KHM\Elementor\Widgets\MemberLibrary_Widget' ) ) {
        $widgets_manager->register( new \KHM\Elementor\Widgets\MemberLibrary_Widget() );
    }

    // Portal widgets - modular member portal sections
    if ( class_exists( '\KHM\Elementor\Widgets\PortalDashboard_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalDashboard_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalDashboard_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalCredits_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalCredits_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalCredits_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalDownloads_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalDownloads_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalDownloads_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalAnswerCards_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalAnswerCards_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalAnswerCards_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalGiftsSent_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalGiftsSent_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalGiftsSent_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalMembership_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalMembership_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalMembership_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalAccount_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalAccount_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalAccount_Widget() );
        }
    }
    if ( class_exists( '\KHM\Elementor\Widgets\PortalVoucher_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\PortalVoucher_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\PortalVoucher_Widget() );
        }
    }
    
    // TEST WIDGET - no namespace, exactly like KH Suggested Reading
    if ( class_exists( 'TestPortalDashboard_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new TestPortalDashboard_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new TestPortalDashboard_Widget() );
        }
    }
    
    // TEST v2 - same widget, different name to force fresh registration
    if ( class_exists( 'TestPortalDashboard_Widget' ) ) {
        $test_widget = new TestPortalDashboard_Widget();
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( $test_widget );
        }
    }

    // Membership Checkout Button Widget
    if ( class_exists( '\KHM\Elementor\Widgets\MembershipCheckoutButton_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\MembershipCheckoutButton_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\MembershipCheckoutButton_Widget() );
        }
    }

    // Commerce Checkout Button Widget
    if ( class_exists( '\KHM\Elementor\Widgets\CommerceCheckoutButton_Widget' ) ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( new \KHM\Elementor\Widgets\CommerceCheckoutButton_Widget() );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( new \KHM\Elementor\Widgets\CommerceCheckoutButton_Widget() );
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $registered = method_exists( $widgets_manager, 'get_widget_types' )
            ? count( $widgets_manager->get_widget_types() )
            : 'unknown';
        error_log( '[KHM Elementor] Widgets registered. Total widget types: ' . $registered );
    }
}

// Register as early as possible in Elementor widget boot.
if ( khm_elementor_feature_enabled( 'register_widgets' ) ) {
    add_action( 'elementor/widgets/register', 'khm_register_elementor_widgets', 50 );
}

add_action( 'elementor/init', function() {
    if ( ! class_exists( '\\Elementor\\Modules\\DynamicTags\\Module' ) || ! class_exists( '\\Elementor\\Modules\\DynamicTags\\Tag' ) ) {
        return;
    }
    if ( class_exists( '\\KHM\\Elementor\\Tags\\KhmDynamicTags' ) ) {
        ( new \KHM\Elementor\Tags\KhmDynamicTags() )->register();
    }
} );

// Ensure core Elementor Pro theme builder widgets are available on early widget init.
if ( khm_elementor_feature_enabled( 'pro_theme_widgets' ) ) {
    add_action( 'elementor/widgets/register', function( $widgets_manager ) {
        if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Widgets\Post_Content' ) ) {
            return;
        }

        $widgets = [
            'Site_Logo',
            'Site_Title',
            'Page_Title',
            'Post_Title',
            'Post_Excerpt',
            'Post_Content',
            'Post_Featured_Image',
            'Archive_Title',
        ];

        foreach ( $widgets as $widget ) {
            $class = '\\ElementorPro\\Modules\\ThemeBuilder\\Widgets\\' . $widget;
            if ( class_exists( $class ) ) {
                $widgets_manager->register( new $class() );
            }
        }
    }, 0 );
}

// Ensure KHM widgets stay enabled even if Elementor Element Manager disables them.
if ( khm_elementor_feature_enabled( 'force_widgets_enabled' ) ) {
    add_filter( 'elementor/widgets/is_widget_enabled', function( $should_register, $widget_instance ) {
        if ( ! $widget_instance || ! method_exists( $widget_instance, 'get_name' ) ) {
            return $should_register;
        }

        $name = $widget_instance->get_name();
        if ( strpos( $name, 'khm_' ) === 0 || $name === 'test_portal_dashboard_v2' ) {
            return true;
        }

        if ( in_array( $name, [ 'nav-menu', 'search-form' ], true ) ) {
            return true;
        }

        return $should_register;
    }, 999, 2 );
}

// Force portal widgets to load in editor config
if ( khm_elementor_feature_enabled( 'editor_injection' ) ) {
add_filter('elementor/editor/localize_settings', function($settings) {
    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        return $settings;
    }

    $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
    if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
        return $settings;
    }

    if ( empty( $settings['widgets'] ) || ! is_array( $settings['widgets'] ) ) {
        $settings['widgets'] = [];
    }

    if ( ! empty( $settings['panel']['elements_categories'] ) && is_array( $settings['panel']['elements_categories'] ) ) {
        foreach ( [ 'pro-elements', 'theme-elements', 'theme-elements-single' ] as $category_key ) {
            if ( isset( $settings['panel']['elements_categories'][ $category_key ] ) ) {
                $settings['panel']['elements_categories'][ $category_key ]['active'] = true;
            }
        }
    }

    if ( did_action( 'elementor/widgets/register' ) === 0 ) {
        return $settings;
    }

    $injected = 0;
    $widget_types = $widgets_manager->get_widget_types();
    $force_widgets = [ 'nav-menu', 'search-form' ];

    // If the editor widget list is suspiciously small, repopulate from the registry.
    if ( count( $settings['widgets'] ) < 20 ) {
        foreach ( $widget_types as $key => $widget ) {
            $settings['widgets'][ $key ] = $widget->get_config();
            $settings['widgets'][ $key ]['show_in_panel'] = true;
        }
        $injected = count( $widget_types );
    } else {
        foreach ( $widget_types as $key => $widget ) {
            if ( strpos( $key, 'khm_' ) === 0 || $key === 'test_portal_dashboard_v2' ) {
                $settings['widgets'][ $key ] = $widget->get_config();
                $settings['widgets'][ $key ]['show_in_panel'] = true;
                $injected++;
            }
        }

        foreach ( $force_widgets as $key ) {
            if ( isset( $settings['widgets'][ $key ] ) ) {
                continue;
            }
            $widget = $widgets_manager->get_widget_types( $key );
            if ( $widget ) {
                $settings['widgets'][ $key ] = $widget->get_config();
                $settings['widgets'][ $key ]['show_in_panel'] = true;
                $injected++;
            }
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[KHM Elementor] Injected into editor localize_settings: ' . $injected );
    }

    return $settings;
}, 20);
}

// Register Touchpoint category for Elementor.
if ( khm_elementor_feature_enabled( 'touchpoint_category' ) ) {
    add_action( 'elementor/init', function() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        $elements_manager = \Elementor\Plugin::$instance->elements_manager;
        if ( method_exists( $elements_manager, 'add_category' ) ) {
            $elements_manager->add_category(
                'touchpoint',
                [
                    'title' => __( 'Touchpoint', 'khm-membership' ),
                    'icon'  => 'fa fa-plug',
                    'active' => true,
                ],
                1
            );
        }
    } );
}

// Ensure Elementor Pro theme builder widgets exist even if widgets register fired early.
if ( khm_elementor_feature_enabled( 'pro_fallback' ) ) {
add_action( 'elementor_pro/init', function() {
    if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
        return;
    }

    $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
    if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
        return;
    }

    if ( $widgets_manager->get_widget_types( 'theme-post-content' ) ) {
        return;
    }

    $theme_builder = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
    if ( ! $theme_builder || ! method_exists( $theme_builder, 'get_widgets' ) ) {
        return;
    }

    foreach ( $theme_builder->get_widgets() as $widget ) {
        $class = '\\ElementorPro\\Modules\\ThemeBuilder\\Widgets\\' . $widget;
        if ( class_exists( $class ) ) {
            $widgets_manager->register( new $class() );
        }
    }
}, 20 );
}

// Ensure core Elementor Pro widgets like Nav Menu/Search Form are registered.
if ( khm_elementor_feature_enabled( 'pro_fallback' ) ) {
add_action( 'elementor_pro/init', function() {
    if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
        return;
    }

    $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
    if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
        return;
    }

    $fallback_widgets = [
        'nav-menu' => [
            'class' => '\\ElementorPro\\Modules\\NavMenu\\Widgets\\Nav_Menu',
            'file' => WP_PLUGIN_DIR . '/elementor-pro/modules/nav-menu/widgets/nav-menu.php',
        ],
        'search-form' => [
            'class' => '\\ElementorPro\\Modules\\ThemeElements\\Widgets\\Search_Form',
            'file' => WP_PLUGIN_DIR . '/elementor-pro/modules/theme-elements/widgets/search-form.php',
        ],
    ];

    foreach ( $fallback_widgets as $widget_key => $widget_meta ) {
        if ( $widgets_manager->get_widget_types( $widget_key ) ) {
            continue;
        }
        if ( ! class_exists( $widget_meta['class'] ) && file_exists( $widget_meta['file'] ) ) {
            require_once $widget_meta['file'];
        }
        if ( class_exists( $widget_meta['class'] ) ) {
            $widgets_manager->register( new $widget_meta['class']() );
        }
    }
}, 25 );
}

// Fallback: register key Elementor Pro widgets if they were skipped in normal init.
if ( khm_elementor_feature_enabled( 'pro_fallback' ) ) {
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
        return;
    }

    if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
        return;
    }

    $fallback_widgets = [
        'nav-menu' => [
            'class' => '\\ElementorPro\\Modules\\NavMenu\\Widgets\\Nav_Menu',
            'file' => WP_PLUGIN_DIR . '/elementor-pro/modules/nav-menu/widgets/nav-menu.php',
        ],
        'search-form' => [
            'class' => '\\ElementorPro\\Modules\\ThemeElements\\Widgets\\Search_Form',
            'file' => WP_PLUGIN_DIR . '/elementor-pro/modules/theme-elements/widgets/search-form.php',
        ],
    ];

    foreach ( $fallback_widgets as $widget_key => $widget_meta ) {
        if ( $widgets_manager->get_widget_types( $widget_key ) ) {
            continue;
        }
        if ( ! class_exists( $widget_meta['class'] ) && file_exists( $widget_meta['file'] ) ) {
            require_once $widget_meta['file'];
        }
        if ( class_exists( $widget_meta['class'] ) ) {
            $widgets_manager->register( new $widget_meta['class']() );
        }
    }
}, 100 );
}

// Force KHM widgets into Elementor editor document config.
if ( khm_elementor_feature_enabled( 'editor_injection' ) ) {
add_filter( 'elementor/document/config', function( $config, $post_id ) {
    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        return $config;
    }

    if ( empty( $config['widgets'] ) || ! is_array( $config['widgets'] ) ) {
        $config['widgets'] = [];
    }

    $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
    if ( ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
        return $config;
    }

    if ( did_action( 'elementor/widgets/register' ) === 0 ) {
        return $config;
    }

    $widget_types = $widgets_manager->get_widget_types();
    $force_widgets = [ 'nav-menu', 'search-form' ];

    if ( count( $config['widgets'] ) < 20 ) {
        foreach ( $widget_types as $key => $widget ) {
            $config['widgets'][ $key ] = $widget->get_config();
            $config['widgets'][ $key ]['show_in_panel'] = true;
        }
    } else {
        foreach ( $widget_types as $key => $widget ) {
            if ( strpos( $key, 'khm_' ) === 0 || $key === 'test_portal_dashboard_v2' || in_array( $key, $force_widgets, true ) ) {
                $config['widgets'][ $key ] = $widget->get_config();
                $config['widgets'][ $key ]['show_in_panel'] = true;
            }
        }
    }

    return $config;
}, 1000, 2 );
}

// Inject KHM widgets into ElementorConfig on the editor screen.
if ( khm_elementor_feature_enabled( 'editor_injection' ) ) {
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
        return;
    }

    $action = $_GET['action'] ?? '';
    if ( $action !== 'elementor' ) {
        return;
    }

    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        return;
    }

    $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
    if ( ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
        return;
    }

    if ( did_action( 'elementor/widgets/register' ) === 0 ) {
        return;
    }

    $widgets_payload = [];
    $force_widgets = [ 'nav-menu', 'search-form' ];
    foreach ( $widgets_manager->get_widget_types() as $key => $widget ) {
        if ( strpos( $key, 'khm_' ) === 0 || $key === 'test_portal_dashboard_v2' || in_array( $key, $force_widgets, true ) ) {
            $widgets_payload[ $key ] = $widget->get_config();
        }
    }

    if ( empty( $widgets_payload ) ) {
        return;
    }

    wp_register_script( 'khm-elementor-inject', '', [], null, true );
    wp_enqueue_script( 'khm-elementor-inject' );

    $script = '(function(){'
        . 'var injected=' . wp_json_encode( $widgets_payload ) . ';'
        . 'function apply(){'
        . 'if(!window.ElementorConfig||!ElementorConfig.initial_document){return false;}'
        . 'ElementorConfig.initial_document.widgets=ElementorConfig.initial_document.widgets||{};'
        . 'Object.keys(injected).forEach(function(key){'
        . 'var cfg=injected[key];'
        . 'cfg.show_in_panel=true;'
        . 'ElementorConfig.initial_document.widgets[key]=cfg;'
        . '});'
        . 'if(ElementorConfig.initial_document.panel&&ElementorConfig.initial_document.panel.elements_categories){'
        . 'var cats=ElementorConfig.initial_document.panel.elements_categories;'
        . '[\"pro-elements\",\"theme-elements\",\"theme-elements-single\"].forEach(function(key){'
        . 'if(cats[key]){cats[key].active=true;}'
        . '});'
        . '}'
        . 'if(window.elementor&&elementor.config&&elementor.config.document){'
        . 'elementor.config.document.widgets=ElementorConfig.initial_document.widgets;'
        . '}'
        . 'if(window.elementorCommon&&elementorCommon.config){'
        . 'elementorCommon.config.widgets=elementorCommon.config.widgets||{};'
        . 'Object.keys(injected).forEach(function(key){'
        . 'var cfg=injected[key];'
        . 'cfg.show_in_panel=true;'
        . 'elementorCommon.config.widgets[key]=cfg;'
        . '});'
        . '}'
        . 'window.khmElementorInjected=true;'
        . 'window.khmElementorInjectedCount=Object.keys(injected).length;'
        . 'return true;'
        . '}'
        . 'if(apply()){return;}'
        . 'var tries=0;'
        . 'var timer=setInterval(function(){tries++;if(apply()||tries>200){clearInterval(timer);}},25);'
        . '})();';

    wp_add_inline_script( 'khm-elementor-inject', $script );
}, 20 );
}

function khm_create_main_admin_menu() {
    // Check if main menu already exists
    global $admin_page_hooks;
    if (!isset($admin_page_hooks['khm-main-menu'])) {
        add_menu_page(
            'KHM Plugin',
            'KHM Plugin',
            'manage_options',
            'khm-main-menu',
            'khm_render_main_admin_page',
            'dashicons-chart-line',
            30
        );
    }
}

function khm_render_main_admin_page() {
    ?>
    <div class="wrap">
        <h1>🎯 KHM Plugin - Advanced Affiliate Management</h1>
        
        <div class="khm-overview">
            <div class="khm-overview-card">
                <h2>Advanced Attribution System</h2>
                <p>Enterprise-grade affiliate tracking with modern web resilience.</p>
                <ul>
                    <li>✅ ITP/Safari/AdBlock resistance</li>
                    <li>✅ Multi-touch attribution</li>
                    <li>✅ Server-side event correlation</li>
                    <li>✅ Hybrid tracking approach</li>
                    <li>✅ Real-time attribution confidence</li>
                </ul>
                <a href="<?php echo admin_url('admin.php?page=khm-attribution'); ?>" class="button button-primary">
                    Configure Attribution
                </a>
            </div>
            
            <div class="khm-overview-card">
                <h2>Creative Materials System</h2>
                <p>Professional affiliate creative management and distribution.</p>
                <ul>
                    <li>✅ Banner/text/video management</li>
                    <li>✅ Performance analytics</li>
                    <li>✅ A/B testing framework</li>
                    <li>✅ Version control</li>
                    <li>✅ Auto-optimization</li>
                </ul>
                <a href="#" class="button">Coming Soon</a>
            </div>
            
            <div class="khm-overview-card">
                <h2>Enhanced Admin Dashboard</h2>
                <p>Comprehensive analytics and business intelligence.</p>
                <ul>
                    <li>✅ Real-time performance metrics</li>
                    <li>✅ P&L calculations</li>
                    <li>✅ Funnel analysis</li>
                    <li>✅ Forecasting algorithms</li>
                    <li>✅ Custom reporting</li>
                </ul>
                <a href="#" class="button">Coming Soon</a>
            </div>
            
            <div class="khm-overview-card">
                <h2>Professional Affiliate Interface</h2>
                <p>Modern affiliate portal with self-service capabilities.</p>
                <ul>
                    <li>✅ Self-serve registration</li>
                    <li>✅ Real-time earnings</li>
                    <li>✅ Creative marketplace</li>
                    <li>✅ Performance insights</li>
                    <li>✅ Mobile-responsive</li>
                </ul>
                <a href="#" class="button">Coming Soon</a>
            </div>
        </div>
        
        <div class="khm-status">
            <h2>🚀 Implementation Status</h2>
            <div class="khm-progress-grid">
                <div class="khm-progress-item completed">
                    <h3>Phase 1: Advanced Attribution System</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 100%"></div>
                    </div>
                    <p><strong>Status:</strong> ✅ Complete</p>
                    <p>Hybrid tracking, server-side events, ITP resistance, multi-touch attribution</p>
                </div>
                
                <div class="khm-progress-item in-progress">
                    <h3>Phase 2: Performance Optimization</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 60%"></div>
                    </div>
                    <p><strong>Status:</strong> 🔄 In Progress</p>
                    <p>Database optimization, caching, async processing, load balancing</p>
                </div>
                
                <div class="khm-progress-item planned">
                    <h3>Phase 3: Enhanced Analytics</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <p><strong>Status:</strong> 📋 Planned</p>
                    <p>Business intelligence, P&L tracking, forecasting, custom reports</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .khm-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .khm-overview-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .khm-overview-card h2 {
        margin: 0 0 10px 0;
        color: #0073aa;
        font-size: 18px;
    }
    
    .khm-overview-card p {
        color: #666;
        margin-bottom: 15px;
    }
    
    .khm-overview-card ul {
        margin: 15px 0;
        padding-left: 0;
        list-style: none;
    }
    
    .khm-overview-card li {
        margin: 5px 0;
        font-size: 14px;
    }
    
    .khm-status {
        margin-top: 40px;
    }
    
    .khm-progress-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .khm-progress-item {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .khm-progress-item.completed {
        border-left: 4px solid #28a745;
    }
    
    .khm-progress-item.in-progress {
        border-left: 4px solid #ffc107;
    }
    
    .khm-progress-item.planned {
        border-left: 4px solid #6c757d;
    }
    
    .progress-bar {
        width: 100%;
        height: 12px;
        background: #e9ecef;
        border-radius: 6px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #0073aa, #00a0d2);
        transition: width 0.3s ease;
    }
    </style>
    <?php
}

// Minimal init: register activation/deactivation hooks and call plugin initializer if available.
register_activation_hook(__FILE__, function () {
    $activation_errors = [];

    // Activation tasks (create tables, etc) — use migrations in /db/migrations
    if ( class_exists('KHM\\Services\\DatabaseIdempotencyStore') ) {
        try {
            KHM\Services\DatabaseIdempotencyStore::createTable();
        } catch (\Exception $e) {
            error_log('Failed to create webhook events table: ' . $e->getMessage());
            $activation_errors[] = 'Webhook events table failed: ' . $e->getMessage();
        }
    }

    if ( class_exists('KHM\\Services\\StripeMarketingImportAuditLogger') ) {
        try {
            KHM\Services\StripeMarketingImportAuditLogger::createTable();
        } catch (\Exception $e) {
            error_log('Failed to create Stripe marketing audit table: ' . $e->getMessage());
            $activation_errors[] = 'Stripe marketing audit table failed: ' . $e->getMessage();
        }
    }

    if ( class_exists('KHM\\Services\\StripeMarketingImportDeadLetterStore') ) {
        try {
            KHM\Services\StripeMarketingImportDeadLetterStore::createTable();
        } catch (\Exception $e) {
            error_log('Failed to create Stripe marketing dead-letter table: ' . $e->getMessage());
            $activation_errors[] = 'Stripe marketing dead-letter table failed: ' . $e->getMessage();
        }
    }

    // Schedule cron tasks
    if ( class_exists('KHM\\Scheduled\\Scheduler') ) {
        KHM\Scheduled\Scheduler::activate();
    }

    // Initialize credit system tables
    if ( class_exists('KHM\\Services\\CreditService') ) {
        try {
            $memberships = new KHM\Services\MembershipRepository();
            $levels = new KHM\Services\LevelRepository();
            $credit_service = new KHM\Services\CreditService($memberships, $levels);
            $credit_service->createTables();
            error_log('KHM Credit System tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create credit system tables: ' . $e->getMessage());
            $activation_errors[] = 'Credit system tables failed: ' . $e->getMessage();
        }
    }

    // Initialize library system tables
    if ( class_exists('KHM\\Services\\LibraryService') ) {
        try {
            $memberships = new KHM\Services\MembershipRepository();
            $library_service = new KHM\Services\LibraryService($memberships);
            $library_service->create_tables();
            error_log('KHM Library System tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create library system tables: ' . $e->getMessage());
            $activation_errors[] = 'Library system tables failed: ' . $e->getMessage();
        }
    }

    // Initialize answer card library tables
    if ( class_exists('KHM\\Services\\AnswerCardLibraryService') ) {
        try {
            $memberships = new KHM\Services\MembershipRepository();
            $answercard_library = new KHM\Services\AnswerCardLibraryService($memberships);
            $answercard_library->create_tables();
            error_log('KHM AnswerCard Library tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create answer card library tables: ' . $e->getMessage());
            $activation_errors[] = 'AnswerCard library tables failed: ' . $e->getMessage();
        }
    }

    // Initialize sponsor tables
    if ( class_exists('KHM\\Sponsors\\SponsorMigration') ) {
        try {
            KHM\Sponsors\SponsorMigration::create_tables();
            error_log('KHM Sponsor tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create sponsor tables: ' . $e->getMessage());
            $activation_errors[] = 'Sponsor tables failed: ' . $e->getMessage();
        }
    }

    // Initialize membership tables
    if ( class_exists('KHM\\Membership\\MembershipMigration') ) {
        try {
            KHM\Membership\MembershipMigration::create_tables();
            error_log('KHM Membership tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create membership tables: ' . $e->getMessage());
            $activation_errors[] = 'Membership tables failed: ' . $e->getMessage();
        }
    }

    // Initialize eCommerce system tables
    if ( class_exists('KHM\\Services\\ECommerceService') ) {
        try {
            $memberships = new KHM\Services\MembershipRepository();
            $orders = new KHM\Services\OrderRepository();
            $ecommerce_service = new KHM\Services\ECommerceService($memberships, $orders);
            $ecommerce_service->create_tables();
            error_log('KHM eCommerce System tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create eCommerce system tables: ' . $e->getMessage());
            $activation_errors[] = 'eCommerce system tables failed: ' . $e->getMessage();
        }
    }

    // Initialize gift system tables
    if ( class_exists('KHM\\Services\\GiftService') ) {
        try {
            $memberships = new KHM\Services\MembershipRepository();
            $orders = new KHM\Services\OrderRepository();
            $email = new KHM\Services\EmailService(__DIR__);
            $gift_service = new KHM\Services\GiftService($memberships, $orders, $email);
            $gift_service->create_tables();
            error_log('KHM Gift System tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create gift system tables: ' . $e->getMessage());
            $activation_errors[] = 'Gift system tables failed: ' . $e->getMessage();
        }
    }

    // Initialize credit download system tables
    if ( class_exists('KHM\\Services\\CreditDownloadService') ) {
        try {
            $memberships = new KHM\Services\MembershipRepository();
            $levels = new KHM\Services\LevelRepository();
            $credits = new KHM\Services\CreditService($memberships, $levels);
            $library = new KHM\Services\LibraryService($memberships);
            $download_service = new KHM\Services\CreditDownloadService($memberships, $credits, $library);
            $download_service->create_tables();
            error_log('KHM Credit Download System tables created successfully');
        } catch (\Exception $e) {
            error_log('Failed to create credit download system tables: ' . $e->getMessage());
            $activation_errors[] = 'Credit download system tables failed: ' . $e->getMessage();
        }
    }

    // Initialize credit system
    do_action('khm_plugin_activated');

    if ( ! empty( $activation_errors ) ) {
        update_option( 'khm_activation_errors', $activation_errors, false );
    } else {
        delete_option( 'khm_activation_errors' );
    }
});

register_deactivation_hook(__FILE__, function () {
    // Deactivation tasks
    if ( class_exists('KHM\\Scheduled\\Scheduler') ) {
        KHM\Scheduled\Scheduler::deactivate();
    }
    $timestamp = wp_next_scheduled('khm_4a_hourly_recompute');
    if ( $timestamp ) {
        wp_unschedule_event($timestamp, 'khm_4a_hourly_recompute');
    }
});

// If a Plugin class exists, call its init method
if ( class_exists('KHM\\Plugin') ) {
    KHM\Plugin::init(__FILE__);
}

// Surface activation issues to admins.
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $errors = get_option( 'khm_activation_errors', [] );
    if ( empty( $errors ) ) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>KHM Plugin:</strong> Some activation tasks reported errors:</p><ul>';
    foreach ( (array) $errors as $error ) {
        echo '<li>' . esc_html( $error ) . '</li>';
    }
    echo '</ul><p>' . esc_html__( 'You can re-run migrations via CLI (bin/migrate.php) or contact support.', 'khm-membership' ) . '</p></div>';
} );

// Register REST routes (webhooks, etc.)
add_action('rest_api_init', function () {
    if ( class_exists('KHM\\Rest\\WebhooksController') &&
        class_exists('KHM\\Gateways\\StripeWebhookVerifier') &&
        class_exists('KHM\\Services\\DatabaseIdempotencyStore') ) {
        $controller = new KHM\Rest\WebhooksController(
            new KHM\Gateways\StripeWebhookVerifier(),
            new KHM\Services\DatabaseIdempotencyStore(),
            new KHM\Services\OrderRepository(),
            new KHM\Services\MembershipRepository()
        );
        $controller->register_routes();
    }
    // Register subscription management routes
    if ( class_exists('KHM\\Rest\\SubscriptionController') ) {
        ( new KHM\Rest\SubscriptionController() )->register();
    }
    // Register payment method routes
    if ( class_exists('KHM\\Rest\\PaymentMethodController') ) {
        ( new KHM\Rest\PaymentMethodController() )->register();
    }
    // Register invoice routes
    if ( class_exists('KHM\\Rest\\InvoiceController') ) {
        ( new KHM\Rest\InvoiceController() )->register();
    }
    // Register download routes (credit-based PDF downloads)
    if ( class_exists('KHM\\Rest\\DownloadController') ) {
        ( new KHM\Rest\DownloadController() )->register();
    }
    // Register member portal routes
    if ( class_exists('KHM\\Rest\\MemberPortalController') ) {
        ( new KHM\Rest\MemberPortalController() )->register();
    }
    // Register checkout routes
    if ( class_exists('KHM\\Rest\\CheckoutController') ) {
        ( new KHM\Rest\CheckoutController() )->register();
    }
    // Register 4A ingestion routes
    if ( class_exists('KHM\\Rest\\FourAIngestionController') ) {
        ( new KHM\Rest\FourAIngestionController() )->register();
    }
});

// Queue worker: Stripe product.updated marketing sync.
add_action( 'khm_import_stripe_marketing_product_updated', function( $product_id, $level_id = 0, $attempt = 0 ) {
    if ( ! class_exists( 'KHM\\Services\\StripeMarketingWebhookImportProcessor' ) ) {
        return;
    }

    $product_id = sanitize_text_field( (string) $product_id );
    $level_id = (int) $level_id;
    $attempt = max( 0, (int) $attempt );
    if ( $product_id === '' ) {
        return;
    }

    $processor = new KHM\Services\StripeMarketingWebhookImportProcessor();
    $processor->process( $product_id, $level_id, $attempt );
}, 10, 3 );

// Daily cleanup for Stripe marketing import audit table.
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'khm_stripe_marketing_audit_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'khm_stripe_marketing_audit_cleanup' );
    }
    if ( ! wp_next_scheduled( 'khm_stripe_marketing_dead_letter_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'khm_stripe_marketing_dead_letter_cleanup' );
    }
} );

add_action( 'khm_stripe_marketing_audit_cleanup', function () {
    if ( ! class_exists( 'KHM\\Services\\StripeMarketingImportAuditLogger' ) ) {
        return;
    }

    $days = (int) get_option( 'khm_stripe_marketing_audit_retention_days', 90 );
    $days = $days > 0 ? $days : 90;

    try {
        ( new KHM\Services\StripeMarketingImportAuditLogger() )->cleanup( $days );
    } catch ( \Throwable $e ) {
        error_log( 'Stripe marketing audit cleanup failed: ' . $e->getMessage() );
    }
} );

add_action( 'khm_stripe_marketing_dead_letter_cleanup', function () {
    if ( ! class_exists( 'KHM\\Services\\StripeMarketingImportDeadLetterStore' ) ) {
        return;
    }

    $days = (int) get_option( 'khm_stripe_marketing_dead_letter_retention_days', 90 );
    $days = $days > 0 ? $days : 90;

    try {
        ( new KHM\Services\StripeMarketingImportDeadLetterStore() )->cleanup( $days );
    } catch ( \Throwable $e ) {
        error_log( 'Stripe marketing dead-letter cleanup failed: ' . $e->getMessage() );
    }
} );

// Schedule hourly 4A scoring cron.
add_action('init', function () {
    if ( ! wp_next_scheduled('khm_4a_hourly_recompute') ) {
        wp_schedule_event(time(), 'hourly', 'khm_4a_hourly_recompute');
    }
});

add_action('khm_4a_hourly_recompute', function () {
    if ( class_exists('KHM\\Services\\FourAScoringService') ) {
        ( new KHM\Services\FourAScoringService() )->run();
    }
});

// CLI command.
if ( defined('WP_CLI') && WP_CLI && class_exists('KHM\\Cli\\FourAScoreCommand') ) {
    WP_CLI::add_command('khm-4a', 'KHM\\Cli\\FourAScoreCommand');
}

// Register WP-CLI command for migrating Stripe prices
if ( defined('WP_CLI') && WP_CLI ) {
    $cli_dir = is_dir( __DIR__ . '/src/CLI' ) ? '/src/CLI/' : '/src/Cli/';
    require_once __DIR__ . $cli_dir . 'MigratePricesCommand.php';
    require_once __DIR__ . $cli_dir . 'ImportStripeMarketingCommand.php';
    require_once __DIR__ . $cli_dir . 'ImportStripeLevelMirrorCommand.php';
    require_once __DIR__ . $cli_dir . 'StripeMarketingAuditCommand.php';
    require_once __DIR__ . $cli_dir . 'StripeMarketingDeadLettersCommand.php';
    require_once __DIR__ . $cli_dir . 'StripeMarketingDeadLettersReplayCommand.php';
    require_once __DIR__ . $cli_dir . 'StripeMarketingHealthCommand.php';
    require_once __DIR__ . $cli_dir . 'MembershipWebhookDeadLettersCommand.php';
    require_once __DIR__ . $cli_dir . 'MembershipWebhookDeadLettersReplayCommand.php';
    require_once __DIR__ . $cli_dir . 'AnonymizeAttributionCommand.php';
    require_once __DIR__ . $cli_dir . 'RetentionRunCommand.php';
    require_once __DIR__ . $cli_dir . 'MembershipEmailControlCommand.php';

    // Register CLI commands
    WP_CLI::add_command( 'khm membership:dlq', 'KHM\\CLI\\MembershipWebhookDeadLettersCommand' );
    WP_CLI::add_command( 'khm membership:dlq:replay', 'KHM\\CLI\\MembershipWebhookDeadLettersReplayCommand' );
    WP_CLI::add_command( 'khm anonymize_attribution', 'KHM\\CLI\\AnonymizeAttributionCommand' );
    WP_CLI::add_command( 'khm retention:run', 'KHM\\CLI\\RetentionRunCommand' );
    WP_CLI::add_command( 'khm membership:email-control', 'KHM\\CLI\\MembershipEmailControlCommand' );
}

// Register webhook email notifications
add_action('init', function () {
    if ( class_exists('KHM\\Services\\WebhookEmailNotifications') ) {
        $webhook_emails = new KHM\Services\WebhookEmailNotifications(
            new KHM\Services\EmailService(__DIR__),
            new KHM\Services\OrderRepository()
        );
        $webhook_emails->register();
    }
});

// Load helper functions
require_once __DIR__ . '/includes/functions.php';

// Register checkout shortcode
add_action('init', function () {
    if ( class_exists('KHM\\Public\\CheckoutShortcode') ) {
        $checkout = new KHM\Public\CheckoutShortcode(
            new KHM\Services\MembershipRepository(),
            new KHM\Services\OrderRepository(),
            new KHM\Services\EmailService(__DIR__),
            new KHM\Services\LevelRepository()
        );
        $checkout->register();
    }
    if ( class_exists('KHM\\Public\\MembershipCheckoutButtonShortcode') ) {
        ( new KHM\Public\MembershipCheckoutButtonShortcode() )->register();
    }
    if ( class_exists('KHM\\Public\\CommerceCheckoutButtonShortcode') ) {
        ( new KHM\Public\CommerceCheckoutButtonShortcode() )->register();
    }
    if ( class_exists('KHM\\Membership\\LandingPageShortcode') ) {
        new KHM\Membership\LandingPageShortcode();
    }
    if ( class_exists('KHM\\Membership\\DashboardShortcode') ) {
        new KHM\Membership\DashboardShortcode();
    }
    if ( class_exists('KHM\\Blocks\\CommerceCheckoutButtonBlock') ) {
        ( new KHM\Blocks\CommerceCheckoutButtonBlock() )->register();
    }
});

// Register content protection
add_action('init', function () {
    if ( class_exists('KHM\\Services\\AccessControlService') &&
        class_exists('KHM\\PublicFrontend\\ContentFilter') ) {
        $membership_repo = new KHM\Services\MembershipRepository();
        $level_repo      = new KHM\Services\LevelRepository();

        $access_control = new KHM\Services\AccessControlService(
            $membership_repo,
            $level_repo
        );

        $content_filter = new KHM\PublicFrontend\ContentFilter($access_control);
        $content_filter->register();

        // Register member shortcode
        if ( class_exists('KHM\\PublicFrontend\\MemberShortcode') ) {
            $member_shortcode = new KHM\PublicFrontend\MemberShortcode($access_control);
            $member_shortcode->register();
        }

        // Register account shortcode
        if ( class_exists('KHM\\PublicFrontend\\AccountShortcode') ) {
            $account_shortcode = new KHM\PublicFrontend\AccountShortcode(
                $membership_repo,
                new KHM\Services\OrderRepository(),
                $level_repo
            );
            $account_shortcode->register();
            add_action('wp_enqueue_scripts', [ $account_shortcode, 'enqueue_assets' ]);
        }

        // Register library frontend
        if ( class_exists('KHM\\PublicFrontend\\LibraryFrontend') ) {
            $library_service = new KHM\Services\LibraryService($membership_repo);
            $library_frontend = new KHM\PublicFrontend\LibraryFrontend($library_service, $membership_repo);
        }

        // Register commerce frontend (unified modal for cart/checkout)
        if ( class_exists('KHM\\Frontend\\CommerceFrontend') ) {
            $commerce_frontend = new KHM\Frontend\CommerceFrontend();
        }

        // Register membership checkout handler (modal for membership signup)
        if ( class_exists('KHM\\Frontend\\MembershipCheckoutHandler') ) {
            $membership_checkout_handler = new KHM\Frontend\MembershipCheckoutHandler();
        }

        // Register member portal shortcode
        if ( class_exists('KHM\\PublicFrontend\\MemberPortalShortcode') ) {
            $member_portal = new KHM\PublicFrontend\MemberPortalShortcode();
            $member_portal->register();
        }
        
        // Register portal shortcodes (modular sections)
        if ( class_exists('KHM\\PublicFrontend\\PortalShortcodes') ) {
            $portal_shortcodes = new KHM\PublicFrontend\PortalShortcodes();
        }
    }
});

// Register portal shortcodes early on init hook
add_action('init', function() {
    if ( class_exists('KHM\\PublicFrontend\\PortalShortcodes') ) {
        new KHM\PublicFrontend\PortalShortcodes();
    }
}, 5);

// Ensure answer card library table exists for legacy installs.
add_action('init', function() {
    if ( class_exists('KHM\\Services\\AnswerCardLibraryService') ) {
        $memberships = new KHM\Services\MembershipRepository();
        $answercard_library = new KHM\Services\AnswerCardLibraryService($memberships);
        if ( ! $answercard_library->table_exists() ) {
            $answercard_library->create_tables();
        }
    }
}, 6);

// Register admin menu and pages
add_action('init', function () {
    if ( is_admin() && class_exists('KHM\\Admin\\AdminMenu') ) {
        $admin_menu = new KHM\Admin\AdminMenu();
        $admin_menu->register();
    }

    // Register reports page
    if ( is_admin() && class_exists('KHM\\Admin\\ReportsPage') ) {
        $reports_page = new KHM\Admin\ReportsPage(
            new KHM\Services\ReportsService()
        );
        $reports_page->register();
    }

    // Register membership webhook operations page.
    if ( is_admin() && class_exists( 'KHM\\Membership\\Admin\\WebhookEventsPage' ) ) {
        ( new KHM\Membership\Admin\WebhookEventsPage() )->register();
    }

    // Register members page
    if ( is_admin() && class_exists('KHM\\Admin\\MembersPage') ) {
        $members_page = new KHM\Admin\MembersPage();
        $members_page->register();
        $GLOBALS['khm_members_page'] = $members_page;
    }

    // Register orders page
    if ( is_admin() && class_exists('KHM\\Admin\\OrdersPage') ) {
        $orders_page = new KHM\Admin\OrdersPage();
        $orders_page->register();
        $GLOBALS['khm_orders_page'] = $orders_page;
    }

    // Register admin order action handlers (resend receipts, manual refunds).
    if ( is_admin() && class_exists('KHM\\Services\\AdminOrderActions') ) {
        ( new KHM\Services\AdminOrderActions(
            new KHM\Services\OrderRepository(),
            new KHM\Services\EmailService(__DIR__)
        ) )->register();
    }

    // Register discount codes page
    if ( is_admin() && class_exists('KHM\\Admin\\DiscountCodesPage') ) {
        $discount_codes_page = new KHM\Admin\DiscountCodesPage();
        $discount_codes_page->register();
    }

    // Warn if legacy shortcode checkout is still published.
    if ( is_admin() && class_exists('KHM\\Admin\\LegacyCheckoutNotice') ) {
        ( new KHM\Admin\LegacyCheckoutNotice() )->register();
    }

    // Register discount code hooks for checkout integration
    if ( class_exists('KHM\\Hooks\\DiscountCodeHooks') && class_exists('KHM\\Services\\DiscountCodeService') ) {
        $discount_service = new KHM\Services\DiscountCodeService();
        $discount_levels  = new KHM\Services\LevelRepository();
        
        $discount_hooks = new KHM\Hooks\DiscountCodeHooks( $discount_service, $discount_levels );
        $discount_hooks->register();

        // Register discount code widget for checkout page
        if ( class_exists('KHM\\Public\\DiscountCodeWidget') ) {
            $discount_widget = new KHM\Public\DiscountCodeWidget( $discount_service );
            $discount_widget->register();
        }
    }
});

// Register scheduler and daily tasks
add_action('init', function () {
    if ( class_exists('KHM\\Scheduled\\Scheduler') ) {
        ( new KHM\Scheduled\Scheduler() )->register();
    }

    if ( class_exists('KHM\\Scheduled\\Scheduler') && class_exists('KHM\\Scheduled\\Tasks') ) {
        add_action(KHM\Scheduled\Scheduler::HOOK_DAILY, [ new KHM\Scheduled\Tasks(), 'run_daily' ]);
    }
});

// Add custom capabilities
add_action('admin_init', function () {
    $admin_role = get_role('administrator');
    if ( $admin_role ) {
        $admin_role->add_cap('manage_khm');
        $admin_role->add_cap('edit_khm_members');
        $admin_role->add_cap('edit_khm_orders');
        $admin_role->add_cap('read_khm_orders');
        $admin_role->add_cap('export_khm_reports');
    }
});

// Clear reports cache when orders or memberships change
add_action('khm_order_created', function () {
    if ( class_exists('KHM\\Services\\ReportsService') ) {
        ( new KHM\Services\ReportsService() )->clear_cache();
    }
});

add_action('khm_order_updated', function () {
    if ( class_exists('KHM\\Services\\ReportsService') ) {
        ( new KHM\Services\ReportsService() )->clear_cache();
    }
});

add_action('khm_membership_assigned', function ( $userId, $levelId, $membershipId, $options ) {
    // Clear reports cache
    if ( class_exists('KHM\\Services\\ReportsService') ) {
        ( new KHM\Services\ReportsService() )->clear_cache();
    }
    
    // Allocate enrollment credits for the new membership
    if ( class_exists('KHM\\Services\\CreditService') && 
         class_exists('KHM\\Services\\MembershipRepository') && 
         class_exists('KHM\\Services\\LevelRepository') ) {
        try {
            $creditService = new KHM\Services\CreditService(
                new KHM\Services\MembershipRepository(),
                new KHM\Services\LevelRepository()
            );
            $creditService->allocateEnrollmentCredits( $userId, $levelId );
        } catch ( \Throwable $e ) {
            error_log( 'KHM: Failed to allocate credits on membership assignment: ' . $e->getMessage() );
        }
    }
}, 10, 4);

add_action('khm_membership_cancelled', function () {
    if ( class_exists('KHM\\Services\\ReportsService') ) {
        ( new KHM\Services\ReportsService() )->clear_cache();
    }
});

// Reset credit period when membership level changes
add_action('khm_membership_level_changed', function ( $userId, $oldLevelId, $newLevelId, $membershipId ) {
    if ( class_exists('KHM\\Services\\CreditService') && 
         class_exists('KHM\\Services\\MembershipRepository') && 
         class_exists('KHM\\Services\\LevelRepository') ) {
        try {
            $creditService = new KHM\Services\CreditService(
                new KHM\Services\MembershipRepository(),
                new KHM\Services\LevelRepository()
            );
            // Reset the 30-day period when level changes
            $creditService->resetCreditPeriod( $userId );
        } catch ( \Throwable $e ) {
            error_log( 'KHM: Failed to reset credit period on level change: ' . $e->getMessage() );
        }
    }
}, 10, 4);

// Schedule daily cron job for credit expiration
add_action('init', function () {
    if ( ! wp_next_scheduled('khm_daily_credit_expiration') ) {
        wp_schedule_event( time(), 'daily', 'khm_daily_credit_expiration' );
    }
});

// Process credit expiration daily
add_action('khm_daily_credit_expiration', function () {
    if ( class_exists('KHM\\Services\\CreditService') && 
         class_exists('KHM\\Services\\MembershipRepository') && 
         class_exists('KHM\\Services\\LevelRepository') ) {
        try {
            $creditService = new KHM\Services\CreditService(
                new KHM\Services\MembershipRepository(),
                new KHM\Services\LevelRepository()
            );
            $stats = $creditService->processExpiredCredits();
            
            if ( $stats['expired'] > 0 ) {
                error_log( sprintf( 
                    'KHM Credit Expiration: Processed %d records, expired %d, skipped %d paid accounts', 
                    $stats['processed'], 
                    $stats['expired'], 
                    $stats['skipped_paid'] 
                ) );
            }
        } catch ( \Throwable $e ) {
            error_log( 'KHM: Credit expiration cron failed: ' . $e->getMessage() );
        }
    }
});

// Initialize Marketing Suite Integration
add_action('plugins_loaded', function () {
    if ( class_exists('KHM\\Services\\PluginRegistry') && 
         class_exists('KHM\\Services\\MarketingSuiteServices') ) {
        
        // Initialize services
        $marketing_services = new KHM\Services\MarketingSuiteServices(
            new KHM\Services\MembershipRepository(),
            new KHM\Services\OrderRepository(),
            new KHM\Services\LevelRepository()
        );
        
        // Register all services
        $marketing_services->register_services();
        
        // Fire hook to let other plugins know KHM is ready
        do_action('khm_marketing_suite_ready');
    }
});

// Social Strip Widget Data Function
if (!function_exists('kss_get_enhanced_widget_data')) {
    /**
     * Get enhanced data for social strip widget
     * Provides data for all 5 buttons: Download, Save, Buy, Gift, Share
     *
     * @param int $post_id
     * @return array
     */
    function kss_get_enhanced_widget_data(int $post_id): array {
        if ( defined( 'KSS_DISABLE_KHM' ) && KSS_DISABLE_KHM ) {
            return [];
        }

        $user_id = get_current_user_id();
        $post = get_post($post_id);
        
        if (!$post) {
            return [];
        }
        
        // Base data
        $data = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post_id),
            'user_id' => $user_id,
            'is_logged_in' => $user_id > 0,
        ];

        $credit_cost = get_post_meta( $post_id, 'kss_credit_cost', true );
        $credit_cost = $credit_cost !== '' ? (int) $credit_cost : 0;
        
        // Only proceed with KHM integration if user is logged in and KHM is available
        if ($user_id > 0 && function_exists('khm_get_user_membership')) {
            
            // Get user membership and credits
            $membership = khm_get_user_membership($user_id);
            $credits = khm_get_user_credits($user_id);
            
            // Download functionality (credits)
            $data['credits'] = [
                'available' => $credits,
                'required' => $credit_cost,
                'can_download' => $credit_cost === 0 ? true : $credits >= $credit_cost,
            ];
            
            // Save to Library functionality
            $is_saved = false;
            if (function_exists('khm_call_service')) {
                try {
                    $is_saved = khm_call_service('is_saved_to_library', $user_id, $post_id) ?: false;
                } catch (Exception $e) {
                    $is_saved = false;
                }
            }
            $data['library'] = [
                'is_saved' => $is_saved,
                'can_save' => true
            ];
            
            // Buy functionality (pricing)
            $base_price = get_post_meta( $post_id, 'kss_article_price', true );
            $base_price = $base_price !== '' ? (float) $base_price : 0;
            $discount_info = khm_get_member_discount($user_id, $base_price, 'article');
            
            $data['pricing'] = [
                'base_price' => $base_price,
                'member_price' => $discount_info['discounted_price'],
                'discount_percent' => $discount_info['discount_percent'],
                'currency' => '£'
            ];
            
            // Gift functionality
            $data['gift'] = [
                'can_gift' => true,
                'price' => $data['pricing']['member_price']
            ];
            
            // Member status
            $data['membership'] = [
                'is_member' => !empty($membership),
                'level' => $membership ? $membership->level_name : null
            ];
        } else {
            // Guest user defaults
            $base_price = get_post_meta( $post_id, 'kss_article_price', true );
            $base_price = $base_price !== '' ? (float) $base_price : 0;
            
            $data['credits'] = [
                'available' => 0,
                'required' => $credit_cost,
                'can_download' => false
            ];
            
            $data['library'] = [
                'is_saved' => false,
                'can_save' => false
            ];
            
            $data['pricing'] = [
                'base_price' => $base_price,
                'member_price' => $base_price,
                'discount_percent' => 0,
                'currency' => '£'
            ];
            
            $data['gift'] = [
                'can_gift' => true,
                'price' => $base_price
            ];
            
            $data['membership'] = [
                'is_member' => false,
                'level' => null
            ];
        }
        
        // Share functionality (always available)
        $data['share'] = [
            'title' => $post->post_title,
            'url' => get_permalink($post_id),
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30)
        ];
        
        return apply_filters('kss_enhanced_widget_data', $data, $post_id, $user_id);
    }
}

// Initialize Enhanced Email System
add_action('plugins_loaded', function () {
    if ( class_exists('KHM\\Services\\EnhancedEmailService') && 
         class_exists('KHM\\Admin\\EnhancedEmailAdmin') &&
         class_exists('KHM\\Migrations\\EnhancedEmailMigration') ) {
        
        // Initialize enhanced email service
        $enhanced_email_service = new KHM\Services\EnhancedEmailService(
            plugin_dir_path(__FILE__)
        );
        
        // Initialize admin interface
        if (is_admin()) {
            new KHM\Admin\EnhancedEmailAdmin($enhanced_email_service);
        }
        
        // Register enhanced email service globally
        $GLOBALS['khm_enhanced_email'] = $enhanced_email_service;
        
        // Fire hook to let other plugins know enhanced email is ready
        do_action('khm_enhanced_email_ready', $enhanced_email_service);
    }
});

// Enhanced Email Cron Schedule
add_filter('cron_schedules', function($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display'  => __('Every 5 Minutes', 'khm-plugin')
    );
    return $schedules;
});

// Editorial Admin Menu and Subpages
add_action('admin_menu', function() {
    add_menu_page(
        __('Editorial','khm-membership'),
        __('Editorial','khm-membership'),
        'edit_posts',
        'editorial_planner',
        'render_editorial_planner_page',
        'dashicons-welcome-write-blog',
        6
    );

    add_submenu_page('editorial_planner', __('Planner','khm-membership'), __('Planner','khm-membership'), 'edit_posts', 'editorial_planner', 'render_editorial_planner_page');
    add_submenu_page('editorial_planner', __('Frameworks','khm-membership'), __('Frameworks','khm-membership'), 'edit_posts', 'editorial_frameworks', 'render_frameworks_page');
    add_submenu_page('editorial_planner', __('Sessions','khm-membership'), __('Sessions','khm-membership'), 'edit_posts', 'editorial_sessions', 'render_sessions_page');
    add_submenu_page('editorial_planner', __('Exports','khm-membership'), __('Exports','khm-membership'), 'manage_options', 'editorial_exports', 'render_exports_page');
});

function render_editorial_planner_page() {
    // Bootstrap existing Planner UI
    echo '<div id="editorial-planner-app"></div>';
    $planner_path = plugin_dir_path(__FILE__) . 'assets/js/editorial-planner.js';
    $planner_version = file_exists($planner_path) ? filemtime($planner_path) : '1.0';
    wp_enqueue_script(
        'editorial-planner',
        plugins_url('assets/js/editorial-planner.js', __FILE__),
        array('wp-element', 'wp-api-fetch', 'wp-components', 'wp-data'),
        $planner_version,
        true
    );
    wp_localize_script(
        'editorial-planner',
        'dualGptData',
        array(
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('dual-gpt/v1/'),
        )
    );
}

function render_frameworks_page() {
    echo '<div id="editorial-frameworks-app"></div>';
    wp_enqueue_script('editorial-frameworks', plugins_url('assets/js/editorial-frameworks.js', __FILE__), ['wp-element', 'wp-api-fetch'], '1.0', true);
}

function render_sessions_page() {
    echo '<div id="editorial-sessions-app"></div>';
    wp_enqueue_script('editorial-sessions', plugins_url('assets/js/editorial-sessions.js', __FILE__), ['wp-element', 'wp-api-fetch'], '1.0', true);
}

function render_exports_page() {
    echo '<div id="editorial-exports-app"></div>';
    wp_enqueue_script('editorial-exports', plugins_url('assets/js/editorial-exports.js', __FILE__), ['wp-element', 'wp-api-fetch'], '1.0', true);
}

// Dashboard Widgets
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget('plnr_quick_create','Quick Create Planner','render_planner_quick_create_widget');
    wp_add_dashboard_widget('plnr_recent_sessions','Recent Planner Sessions','render_planner_recent_sessions_widget');
});

function render_planner_quick_create_widget() {
    echo '<div id="plnr-quick-create" data-nonce="' . wp_create_nonce('wp_rest') . '"></div>';
}

function render_planner_recent_sessions_widget() {
    echo '<div id="plnr-recent-sessions" data-nonce="' . wp_create_nonce('wp_rest') . '"></div>';
}

// Enqueue dashboard JS
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'index.php') {
        return;
    }

    wp_enqueue_script(
        'editorial-dashboard',
        plugins_url('assets/js/editorial-dashboard.js', __FILE__),
        array( 'wp-api-fetch' ),
        '1.0.0',
        true
    );

    wp_localize_script(
        'editorial-dashboard',
        'editorialData',
        array(
            'restBase' => rest_url( 'editorial/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' )
        )
    );
});

// REST Endpoints
add_action('rest_api_init', function() {
    register_rest_route('editorial/v1','/sessions',[
        'methods' => 'GET',
        'callback' => 'ed_get_sessions',
        'permission_callback' => function(){ return current_user_can('edit_posts'); }
    ]);
    register_rest_route('editorial/v1','/sessions',[
        'methods' => 'POST',
        'callback' => 'ed_create_session',
        'permission_callback' => function(){ return current_user_can('edit_posts'); },
        'args' => [ 'title' => ['required' => true] ]
    ]);
    register_rest_route('editorial/v1','/frameworks',[
        'methods' => 'GET',
        'callback' => 'ed_get_frameworks',
        'permission_callback' => function(){ return current_user_can('edit_posts'); }
    ]);
    register_rest_route('editorial/v1','/pipeline',[
        'methods' => 'GET',
        'callback' => 'ed_get_pipeline',
        'permission_callback' => function(){ return current_user_can('edit_posts'); }
    ]);
});

function ed_get_sessions($request){
    $limit = intval($request->get_param('limit') ?: 6);
    $args = ['post_type'=>'planner_session','posts_per_page'=>$limit,'post_status'=>'any'];
    $q = get_posts($args);
    $out = array_map(function($p){ return ['id'=>$p->ID,'title'=>$p->post_title,'status'=>get_post_meta($p->ID,'status',true),'link'=>admin_url("admin.php?page=editorial_planner&session_id={$p->ID}")]; }, $q);
    return rest_ensure_response($out);
}

function ed_create_session( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $title  = isset($params['title']) ? sanitize_text_field($params['title']) : '';

    if ( empty( $title ) ) {
        return new WP_Error( 'missing_title', 'Title is required', array( 'status' => 400 ) );
    }

    // prepare post array
    $postarr = array(
        'post_type'    => 'planner_session',
        'post_title'   => $title,
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
    );

    // attempt insert with WP_Error return allowed
    $post_id = wp_insert_post( $postarr, true );

    if ( is_wp_error( $post_id ) ) {
        // explicit log for debugging — include user id and request data (careful with secrets)
        error_log( '[PLANNER] wp_insert_post failed: ' . $post_id->get_error_message() . ' | user:' . get_current_user_id() . ' | title:' . $title );
        return new WP_Error( 'insert_failed', 'Failed to insert session: ' . $post_id->get_error_message(), array( 'status' => 500 ) );
    }

    if ( empty( $post_id ) || intval( $post_id ) === 0 ) {
        global $wpdb;
        error_log( '[PLANNER] wp_insert_post returned 0. DB error: ' . $wpdb->last_error . ' | user:' . get_current_user_id() );
        return new WP_Error( 'insert_failed', 'Failed to insert session (DB error).', array( 'status' => 500, 'db_error' => $wpdb->last_error ) );
    }

    // set default meta and return
    update_post_meta( $post_id, 'status', 'draft' );
    update_post_meta( $post_id, 'created_by', get_current_user_id() );
    return rest_ensure_response( array(
        'id'   => (int) $post_id,
        'link' => admin_url( 'admin.php?page=editorial_planner&session_id=' . $post_id ),
    ));
}

function ed_get_frameworks($request){
    // Placeholder - implement based on your frameworks
    return rest_ensure_response([]);
}

function ed_get_pipeline($request){
    // Placeholder - implement based on your pipeline
    return rest_ensure_response([]);
}

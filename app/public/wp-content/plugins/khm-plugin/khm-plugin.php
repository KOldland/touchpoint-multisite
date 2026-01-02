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

// Load marketing suite integration functions
require_once __DIR__ . '/includes/marketing-suite-functions.php';

// Load credit system helper functions
require_once __DIR__ . '/includes/credit-system-helpers.php';

// Load Advanced Attribution System
require_once plugin_dir_path(__FILE__) . 'src/Attribution/AttributionManager.php';

// Load Attribution Admin Interface
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/attribution-admin.php';
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

// Create main admin menu if it doesn't exist
add_action('admin_menu', 'khm_create_main_admin_menu');

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
// Register Elementor widgets when Elementor is available.
add_action('elementor/widgets/register', function( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
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
});

// Register custom Touchpoint category (separate hook Elementor expects).
add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
    if ( method_exists( $elements_manager, 'add_category' ) ) {
        $elements_manager->add_category(
            'touchpoint',
            [
                'title' => __( 'Touchpoint', 'khm-membership' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }
} );

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
    // Register 4A ingestion routes
    if ( class_exists('KHM\\Rest\\FourAIngestionController') ) {
        ( new KHM\Rest\FourAIngestionController() )->register();
    }
});

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
    }
});

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

    // Register levels page
    if ( is_admin() && class_exists('KHM\\Admin\\LevelsPage') ) {
        $levels_page = new KHM\Admin\LevelsPage();
        $levels_page->register();
        $GLOBALS['khm_levels_page'] = $levels_page;
    }

    // Register discount codes page
    if ( is_admin() && class_exists('KHM\\Admin\\DiscountCodesPage') ) {
        $discount_codes_page = new KHM\Admin\DiscountCodesPage();
        $discount_codes_page->register();
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

add_action('khm_membership_assigned', function () {
    if ( class_exists('KHM\\Services\\ReportsService') ) {
        ( new KHM\Services\ReportsService() )->clear_cache();
    }
});

add_action('khm_membership_cancelled', function () {
    if ( class_exists('KHM\\Services\\ReportsService') ) {
        ( new KHM\Services\ReportsService() )->clear_cache();
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
        
        // Only proceed with KHM integration if user is logged in and KHM is available
        if ($user_id > 0 && function_exists('khm_get_user_membership')) {
            
            // Get user membership and credits
            $membership = khm_get_user_membership($user_id);
            $credits = khm_get_user_credits($user_id);
            
            // Download functionality (credits)
            $data['credits'] = [
                'available' => $credits,
                'required' => 1,
                'can_download' => $credits >= 1
            ];
            
            // Save to Library functionality
            $data['library'] = [
                'is_saved' => false, // TODO: Check if article is already saved
                'can_save' => true
            ];
            
            // Buy functionality (pricing)
            $base_price = get_post_meta($post_id, '_article_price', true) ?: 5.99;
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
            $base_price = get_post_meta($post_id, '_article_price', true) ?: 5.99;
            
            $data['credits'] = [
                'available' => 0,
                'required' => 1,
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

<?php

namespace KHM\Admin;

/**
 * AdminMenu
 *
 * Registers admin menu pages for KHM Membership plugin.
 */
class AdminMenu {

    /**
     * Register admin menu and hooks
     */
    public function register(): void {
        add_action('admin_menu', [ $this, 'add_menu_pages' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages(): void {
        // Main menu
        add_menu_page(
            __('KHM Membership', 'khm-membership'),
            __('Memberships', 'khm-membership'),
            'manage_khm',
            'khm-membership',
            [ $this, 'render_dashboard' ],
            'dashicons-groups',
            7
        );

        // Dashboard (default)
        add_submenu_page(
            'khm-membership',
            __('Dashboard', 'khm-membership'),
            __('Dashboard', 'khm-membership'),
            'manage_khm',
            'khm-membership',
            [ $this, 'render_dashboard' ]
        );

        // Members list
        add_submenu_page(
            'khm-membership',
            __('Members', 'khm-membership'),
            __('Members', 'khm-membership'),
            'edit_khm_members',
            'khm-members',
            [ $this, 'render_members' ]
        );

        // Orders list
        add_submenu_page(
            'khm-membership',
            __('Orders', 'khm-membership'),
            __('Orders', 'khm-membership'),
            'edit_khm_orders',
            'khm-orders',
            [ $this, 'render_orders' ]
        );

        // Membership Levels
        add_submenu_page(
            'khm-membership',
            __('Membership Levels', 'khm-membership'),
            __('Levels', 'khm-membership'),
            'manage_khm',
            'khm-levels',
            [ $this, 'render_levels' ]
        );

        // Discount Codes
        add_submenu_page(
            'khm-membership',
            __('Discount Codes', 'khm-membership'),
            __('Discount Codes', 'khm-membership'),
            'manage_khm',
            'khm-discount-codes',
            [ $this, 'render_discount_codes' ]
        );

        // Settings
        add_submenu_page(
            'khm-membership',
            __('Settings', 'khm-membership'),
            __('Settings', 'khm-membership'),
            'manage_options',
            'khm-settings',
            [ $this, 'render_settings' ]
        );

        // Extensions
        add_submenu_page(
            'khm-membership',
            __('Extensions', 'khm-membership'),
            __('Extensions', 'khm-membership'),
            'manage_khm',
            'khm-extensions',
            [ $this, 'render_extensions' ]
        );

        // Email Preview
        add_submenu_page(
            'khm-membership',
            __('Email Preview', 'khm-membership'),
            __('Emails', 'khm-membership'),
            'manage_options',
            'khm-email-preview',
            [ $this, 'render_email_preview' ]
        );

        add_submenu_page(
            'khm-membership',
            __('Price Review', 'khm-membership'),
            __('Price Review', 'khm-membership'),
            'manage_options',
            'khm-price-review',
            [ $this, 'render_price_review' ]
        );

        // Elementor cache reset
        add_submenu_page(
            'khm-membership',
            __('Reset Elementor Cache', 'khm-membership'),
            __('Elementor Cache', 'khm-membership'),
            'manage_khm',
            'khm-elementor-cache',
            [ $this, 'render_elementor_cache' ]
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ): void {
        // Only load on KHM pages
        if ( strpos($hook, 'khm-') === false ) {
            return;
        }

        wp_enqueue_style(
            'khm-admin',
            plugins_url('admin/css/admin.css', dirname(__DIR__, 2) . '/khm-plugin.php'),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'khm-admin',
            plugins_url('admin/js/admin.js', dirname(__DIR__, 2) . '/khm-plugin.php'),
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script('khm-admin', 'khmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_admin'),
        ]);

        if ( false !== strpos( $hook, 'khm-price-review' ) ) {
            $plugin_file = dirname(__DIR__, 2) . '/khm-plugin.php';
            $price_js = dirname(__DIR__, 2) . '/assets/js/price-review.js';
            $helpers_js = dirname(__DIR__, 2) . '/assets/js/checkout-ui-helpers.js';

            wp_enqueue_script(
                'khm-checkout-ui-helpers',
                plugins_url('assets/js/checkout-ui-helpers.js', $plugin_file),
                [],
                file_exists($helpers_js) ? (string) filemtime($helpers_js) : '1.0.0',
                true
            );

            wp_enqueue_script(
                'khm-price-review',
                plugins_url('assets/js/price-review.js', $plugin_file),
                [ 'khm-checkout-ui-helpers' ],
                file_exists($price_js) ? (string) filemtime($price_js) : '1.0.0',
                true
            );

            wp_localize_script( 'khm-price-review', 'khmPriceReview', [
                'endpoint' => rest_url( 'kh-membership/v1/price-override' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard(): void {
        require_once __DIR__ . '/pages/dashboard.php';
    }

    /**
     * Render members page
     */
    public function render_members(): void {
        if ( isset( $GLOBALS['khm_members_page'] ) && $GLOBALS['khm_members_page'] instanceof MembersPage ) {
            $GLOBALS['khm_members_page']->render_page();
            return;
        }

        if ( class_exists( MembersPage::class ) ) {
            $members_page = new MembersPage();
            $members_page->register();
            $GLOBALS['khm_members_page'] = $members_page;
            $members_page->render_page();
            return;
        }

        esc_html_e( 'Members admin is unavailable.', 'khm-membership' );
    }

    /**
     * Render orders page
     */
    public function render_orders(): void {
        if ( isset( $GLOBALS['khm_orders_page'] ) && $GLOBALS['khm_orders_page'] instanceof OrdersPage ) {
            $GLOBALS['khm_orders_page']->render_page();
            return;
        }

        if ( class_exists( OrdersPage::class ) ) {
            $orders_page = new OrdersPage();
            $orders_page->register();
            $GLOBALS['khm_orders_page'] = $orders_page;
            $orders_page->render_page();
            return;
        }

        esc_html_e( 'Orders admin is unavailable.', 'khm-membership' );
    }

    /**
     * Render levels page
     */
    public function render_levels(): void {
        if ( isset( $GLOBALS['khm_levels_page'] ) && $GLOBALS['khm_levels_page'] instanceof LevelsPage ) {
            $GLOBALS['khm_levels_page']->render_page();
            return;
        }

        if ( class_exists( LevelsPage::class ) ) {
            $levels_page = new LevelsPage();
            $levels_page->register();
            $GLOBALS['khm_levels_page'] = $levels_page;
            $levels_page->render_page();
            return;
        }

        esc_html_e( 'Membership level admin is unavailable.', 'khm-membership' );
    }

    public function render_price_review(): void {
        $repo = class_exists( '\KHM\Services\MembershipRepository' ) ? new \KHM\Services\MembershipRepository() : null;
        $payload = is_object( $repo ) ? $repo->getPriceReviewOverride( 'demo-price-review' ) : null;
        if ( ! is_array( $payload ) ) {
            $payload = [
                'reference_id' => 'demo-price-review',
                'currency' => 'AUD',
                'items' => [
                    [ 'key' => 'creative_setup', 'label' => 'Creative setup', 'amount_cents' => 4500 ],
                    [ 'key' => 'campaign_management', 'label' => 'Campaign management', 'amount_cents' => 8500 ],
                    [ 'key' => 'reporting', 'label' => 'Reporting', 'amount_cents' => 2500 ],
                ],
            ];
        }

        $GLOBALS['khm_price_review_payload'] = $payload;
        require_once __DIR__ . '/pages/price-review.php';
    }

    /**
     * Render discount codes page
     */
    public function render_discount_codes(): void {
        require_once __DIR__ . '/pages/discount-codes.php';
    }

    /**
     * Render settings page
     */
    public function render_settings(): void {
        require_once __DIR__ . '/pages/settings.php';
    }

    /**
     * Render extensions page
     */
    public function render_extensions(): void {
        require_once __DIR__ . '/pages/extensions.php';
    }

    /**
     * Render email preview page
     */
    public function render_email_preview(): void {
        require_once __DIR__ . '/pages/email-preview.php';
    }

    public function render_elementor_cache(): void {
        require_once __DIR__ . '/pages/elementor-cache.php';
    }
}

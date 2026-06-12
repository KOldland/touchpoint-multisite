<?php

namespace KH\Dashboards\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MenuManager {

    public function init() {
        // Register our 3 top-level menus early
        add_action( 'admin_menu', [ $this, 'register_top_level_menus' ], 5 );
        // Hide conflicting menu items late, after everything is registered
        add_action( 'admin_menu', [ $this, 'hide_conflicting_menus' ], 999 );
        // Enqueue dashboard CSS on our pages
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
    }

    public function register_top_level_menus() {
        global $menu, $submenu;

        // ── 1. Sponsorship & Promotion ──
        add_menu_page(
            __( 'Sponsorship & Promotion', 'kh-dashboards' ),
            __( 'Sponsorship & Promotion', 'kh-dashboards' ),
            'edit_posts',
            'kh-dashboards-sponsorship',
            [ $this, 'render_sponsorship_dashboard' ],
            'dashicons-megaphone',
            4
        );

        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'Dashboard', 'kh-dashboards' ),
            __( 'Dashboard', 'kh-dashboards' ),
            'edit_posts',
            'kh-dashboards-sponsorship',
            [ $this, 'render_sponsorship_dashboard' ]
        );

        // Link: Sponsors
        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'Sponsors', 'kh-dashboards' ),
            __( 'Sponsors', 'kh-dashboards' ),
            'edit_posts',
            'kh-dashboards-link-sponsors',
            [ $this, 'redirect_to' ],
            // Store target in a global hack — WordPress doesn't support external submenu links natively
        );
        // We'll use a JS redirect for external-link submenus

        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'Ads', 'kh-dashboards' ),
            __( 'Ads', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-ads',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'Connect', 'kh-dashboards' ),
            __( 'Connect', 'kh-dashboards' ),
            'edit_posts',
            'kh-dashboards-link-connect',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'Events', 'kh-dashboards' ),
            __( 'Events', 'kh-dashboards' ),
            'edit_posts',
            'kh-dashboards-link-events',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'MailChimp', 'kh-dashboards' ),
            __( 'MailChimp', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-mailchimp',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-sponsorship',
            __( 'Settings', 'kh-dashboards' ),
            __( 'Settings', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-sponsor-settings',
            [ $this, 'redirect_to' ]
        );

        // ── 3. Commerce / Membership ──
        add_menu_page(
            __( 'Commerce', 'kh-dashboards' ),
            __( 'Commerce', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-commerce',
            [ $this, 'render_commerce_dashboard' ],
            'dashicons-cart',
            6
        );

        add_submenu_page(
            'kh-dashboards-commerce',
            __( 'Dashboard', 'kh-dashboards' ),
            __( 'Dashboard', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-commerce',
            [ $this, 'render_commerce_dashboard' ]
        );

        add_submenu_page(
            'kh-dashboards-commerce',
            __( 'Members', 'kh-dashboards' ),
            __( 'Members', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-members',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-commerce',
            __( 'Orders', 'kh-dashboards' ),
            __( 'Orders', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-orders',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-commerce',
            __( 'Levels', 'kh-dashboards' ),
            __( 'Levels', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-levels',
            [ $this, 'redirect_to' ]
        );

        add_submenu_page(
            'kh-dashboards-commerce',
            __( 'Settings', 'kh-dashboards' ),
            __( 'Settings', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-commerce-settings',
            [ $this, 'redirect_to' ]
        );

        // ── 4. SEO Suite ──
        add_menu_page(
            __( 'SEO Suite', 'kh-dashboards' ),
            __( 'SEO Suite', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-seo-suite',
            [ $this, 'render_seo_dashboard' ],
            'dashicons-chart-area',
            5
        );

        add_submenu_page(
            'kh-dashboards-seo-suite',
            __( 'Dashboard', 'kh-dashboards' ),
            __( 'Dashboard', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-seo-suite',
            [ $this, 'render_seo_dashboard' ]
        );

        // Link: Audit (khm-seo-agent)
        add_submenu_page(
            'kh-dashboards-seo-suite',
            __( 'Audit', 'kh-dashboards' ),
            __( 'Audit', 'kh-dashboards' ),
            'edit_posts',
            'kh-dashboards-link-seo-audit',
            [ $this, 'redirect_to' ]
        );

        // Link: General Settings (khm-seo config)
        add_submenu_page(
            'kh-dashboards-seo-suite',
            __( 'SEO Settings', 'kh-dashboards' ),
            __( 'SEO Settings', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-seo-settings',
            [ $this, 'redirect_to' ]
        );

        // Link: Schema Markup (khm-seo schema)
        add_submenu_page(
            'kh-dashboards-seo-suite',
            __( 'Schema', 'kh-dashboards' ),
            __( 'Schema', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-seo-schema',
            [ $this, 'redirect_to' ]
        );

        // Link: SEO Tools (khm-seo tools)
        add_submenu_page(
            'kh-dashboards-seo-suite',
            __( 'SEO Tools', 'kh-dashboards' ),
            __( 'SEO Tools', 'kh-dashboards' ),
            'manage_options',
            'kh-dashboards-link-seo-tools',
            [ $this, 'redirect_to' ]
        );
    }

    /**
     * Render functions for each dashboard.
     */
    public function render_sponsorship_dashboard() {
        $data = $this->get_sponsorship_stats();
        include KH_DASHBOARDS_PLUGIN_DIR . 'templates/sponsorship.php';
    }

    public function render_editorial_dashboard() {
        $data = $this->get_editorial_stats();
        include KH_DASHBOARDS_PLUGIN_DIR . 'templates/editorial.php';
    }

    public function render_commerce_dashboard() {
        $data = $this->get_commerce_stats();
        include KH_DASHBOARDS_PLUGIN_DIR . 'templates/commerce.php';
    }

    /**
     * Render SEO Suite dashboard page.
     */
    public function render_seo_dashboard() {
        $data = $this->get_seo_stats();
        include KH_DASHBOARDS_PLUGIN_DIR . 'templates/seo.php';
    }

    /**
     * Redirect handler for link-type submenu pages.
     * Uses JS redirect because headers are already sent at this point.
     */
    public function redirect_to() {
        $page = $_GET['page'] ?? '';
        $urls = [
            'kh-dashboards-link-sponsors'          => admin_url( 'edit.php?post_type=sponsor' ),
            'kh-dashboards-link-ads'               => admin_url( 'admin.php?page=kh-ad-manager' ),
            'kh-dashboards-link-connect'            => admin_url( 'admin.php?page=kh-connect' ),
            'kh-dashboards-link-events'             => admin_url( 'edit.php?post_type=event' ),
            'kh-dashboards-link-mailchimp'          => admin_url( 'admin.php?page=mailchimp' ),
            'kh-dashboards-link-sponsor-settings'   => admin_url( 'options-general.php?page=kh-ad-settings' ),
            'kh-dashboards-link-api-settings'       => admin_url( 'admin.php?page=kh-editorial-settings' ),
            'kh-dashboards-link-members'            => admin_url( 'admin.php?page=members' ),
            'kh-dashboards-link-orders'             => admin_url( 'admin.php?page=orders' ),
            'kh-dashboards-link-levels'             => admin_url( 'admin.php?page=levels' ),
            'kh-dashboards-link-commerce-settings'  => admin_url( 'admin.php?page=membership-settings' ),
            'kh-dashboards-link-seo-audit'          => admin_url( 'admin.php?page=khm-seo-agent-audit' ),
            'kh-dashboards-link-seo-settings'       => admin_url( 'admin.php?page=khm-seo' ),
            'kh-dashboards-link-seo-schema'         => admin_url( 'admin.php?page=khm-seo-schema' ),
            'kh-dashboards-link-seo-tools'          => admin_url( 'admin.php?page=khm-seo-tools' ),
        ];
        $url = $urls[ $page ] ?? admin_url();
        echo '<script>window.location.href = "' . esc_url( $url ) . '";</script>';
        exit;
    }

    /**
     * Hide conflicting menu items from other plugins.
     */
    public function hide_conflicting_menus() {
        global $menu;

        // Top-level menu slugs we've replaced or consolidated
        $remove_menus = [
            'kh-smma-dashboard',     // KH Social (consolidated into Sponsorship)
            'khm-seo-tracker',       // GEO Tracker (accessible via SEO submenu)
            'khm-seo',               // KHM SEO (moved under SEO Suite)
            'khm-seo-agent-dashboard', // khm-seo-agent (moved under SEO Suite)
        ];

        foreach ( $menu as $key => $item ) {
            $slug = $item[2] ?? '';
            if ( in_array( $slug, $remove_menus, true ) ) {
                unset( $menu[ $key ] );
            }
        }
    }

    /**
     * Gather summary stats for editorial dashboard.
     */
    private function get_editorial_stats() {
        $count_posts = wp_count_posts( 'post' );
        $authors = wp_count_posts( 'multi_author' );

        return [
            'total_posts'        => (int) ( $count_posts->publish ?? 0 ),
            'draft_posts'        => (int) ( $count_posts->draft ?? 0 ),
            'scheduled_posts'    => (int) ( $count_posts->future ?? 0 ),
            'total_authors'      => (int) ( $authors->publish ?? 0 ),
            'posts_this_month'   => $this->count_posts_this_month(),
        ];
    }

    /**
     * Gather summary stats for sponsorship dashboard.
     */
    private function get_sponsorship_stats() {
        $sponsors = wp_count_posts( 'sponsor' );

        return [
            'active_sponsors'   => (int) ( $sponsors->publish ?? 0 ),
            'total_ads'         => $this->count_post_type( 'kh_ad' ),
            'active_events'     => $this->count_post_type( 'event' ),
        ];
    }

    /**
     * Gather summary stats for commerce dashboard.
     */
    private function get_commerce_stats() {
        $members = wp_count_posts( 'khm_member' );

        return [
            'active_members' => (int) ( $members->publish ?? 0 ),
            'total_orders'   => $this->count_post_type( 'khm_order' ),
            'total_levels'   => $this->count_post_type( 'khm_level' ),
        ];
    }

    private function get_seo_stats() {
        $overall_score = (int) get_option( 'khm_seo_overall_score', 0 );
        return [
            'overall_score'       => $overall_score,
            'total_keywords'      => (int) get_option( 'khm_seo_total_keywords', 0 ),
            'top_10_rankings'     => (int) get_option( 'khm_seo_top_10_rankings', 0 ),
            'recent_audits'       => (int) get_option( 'khm_seo_recent_audits', 0 ),
        ];
    }

    private function count_posts_this_month() {
        global $wpdb;
        $first_day = date( 'Y-m-01 00:00:00' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_date >= %s",
            $first_day
        ) );
    }

    private function count_post_type( $post_type ) {
        $counted = wp_count_posts( $post_type );
        if ( ! $counted ) {
            return 0;
        }
        return (int) ( ( $counted->publish ?? 0 ) + ( $counted->draft ?? 0 ) );
    }

    /**
     * Enqueue dashboard CSS on our dashboard pages.
     */
    public function enqueue_dashboard_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        $our_pages = [
            'toplevel_page_kh-dashboards-sponsorship',
            'toplevel_page_kh-dashboards-commerce',
            'editorial_page_kh-dashboards-editorial',
            'sponsorship_page_kh-dashboards-link-sponsors',
            'sponsorship_page_kh-dashboards-link-ads',
            'sponsorship_page_kh-dashboards-link-connect',
            'sponsorship_page_kh-dashboards-link-events',
            'sponsorship_page_kh-dashboards-link-mailchimp',
            'sponsorship_page_kh-dashboards-link-sponsor-settings',
            'editorial_page_kh-dashboards-link-posts',
            'editorial_page_kh-dashboards-link-authors',
            'editorial_page_kh-dashboards-link-planner',
            'editorial_page_kh-dashboards-link-api-settings',
            'toplevel_page_kh-dashboards-seo-suite',
            'seo-suite_page_kh-dashboards-link-seo-audit',
            'seo-suite_page_kh-dashboards-link-seo-settings',
            'seo-suite_page_kh-dashboards-link-seo-schema',
            'seo-suite_page_kh-dashboards-link-seo-tools',
            'commerce_page_kh-dashboards-link-members',
            'commerce_page_kh-dashboards-link-orders',
            'commerce_page_kh-dashboards-link-levels',
            'commerce_page_kh-dashboards-link-commerce-settings',
        ];
        if ( in_array( $screen->id, $our_pages, true ) ) {
            wp_enqueue_style(
                'kh-dashboards',
                KH_DASHBOARDS_PLUGIN_URL . 'assets/css/dashboards.css',
                [],
                KH_DASHBOARDS_VERSION
            );
        }
    }
}
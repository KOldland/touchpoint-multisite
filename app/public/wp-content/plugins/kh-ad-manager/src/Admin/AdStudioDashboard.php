<?php

/**
 * AdStudioDashboard — unified "Ad Studio" top-level admin menu.
 *
 * Mirrors the Editorial Studio pattern: a top-level menu page with
 * a card-based dashboard, plus sub-pages for all ad management tools.
 */

class KH_AdManager_AdStudioDashboard {

    const PARENT_SLUG = 'ad-studio';

    /**
     * Bootstrap: register menu and enqueue assets.
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
    }

    /**
     * Register the Ad Studio top-level menu and all sub-pages.
     */
    public function register_menu() {
        // Parent: Ad Studio dashboard
        add_menu_page(
            __( 'Ad Studio', 'kh-ad-manager' ),
            __( 'Ad Studio', 'kh-ad-manager' ),
            'edit_posts',
            self::PARENT_SLUG,
            [ $this, 'render_dashboard_page' ],
            'dashicons-megaphone',
            26
        );

        // Sub: Dashboard
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Ad Studio Dashboard', 'kh-ad-manager' ),
            __( 'Dashboard', 'kh-ad-manager' ),
            'edit_posts',
            self::PARENT_SLUG,
            [ $this, 'render_dashboard_page' ]
        );

        // Sub: All Ads — the ad_unit CPT list (moved under Ad Studio)
        // We handle the menu nesting via cpt-ad-unit.php's show_in_menu param.
        // Ensure ad_unit is nested correctly — we re-add it here in case
        // the CPT registration runs before our menu.
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'All Ads', 'kh-ad-manager' ),
            __( 'All Ads', 'kh-ad-manager' ),
            'edit_posts',
            'edit.php?post_type=ad_unit'
        );

        // Sub: Add New Ad
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Add New Ad', 'kh-ad-manager' ),
            __( 'Add New Ad', 'kh-ad-manager' ),
            'edit_posts',
            'post-new.php?post_type=ad_unit'
        );

        // Sub: Campaigns — ad-campaign taxonomy page
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Campaigns', 'kh-ad-manager' ),
            __( 'Campaigns', 'kh-ad-manager' ),
            'edit_posts',
            'edit-tags.php?taxonomy=ad-campaign&post_type=ad_unit'
        );

        // Sub: Sponsors — kh_sponsor CPT list
        if ( post_type_exists( 'kh_sponsor' ) ) {
            add_submenu_page(
                self::PARENT_SLUG,
                __( 'Sponsors', 'kh-ad-manager' ),
                __( 'Sponsors', 'kh-ad-manager' ),
                'edit_posts',
                'edit.php?post_type=kh_sponsor'
            );
        }

        // Sub: Ad Slots — ad-slot taxonomy page
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Ad Slots', 'kh-ad-manager' ),
            __( 'Ad Slots', 'kh-ad-manager' ),
            'edit_posts',
            'edit-tags.php?taxonomy=ad-slot&post_type=ad_unit'
        );

        // Sub: Overlay Settings — moved from Settings menu
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Overlay Settings', 'kh-ad-manager' ),
            __( 'Overlay Settings', 'kh-ad-manager' ),
            'manage_options',
            'ad-studio-overlays',
            [ $this, 'render_overlay_settings_page' ]
        );

        // Sub: Global Ad Codes — moved from Settings menu
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Global Ad Codes', 'kh-ad-manager' ),
            __( 'Global Ad Codes', 'kh-ad-manager' ),
            'manage_options',
            'ad-studio-global-codes',
            [ $this, 'render_global_codes_page' ]
        );

        // Sub: Reconciliation — moved from top-level plug
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Reconciliation', 'kh-ad-manager' ),
            __( 'Reconciliation', 'kh-ad-manager' ),
            'manage_options',
            'ad-studio-reconciliation',
            [ $this, 'render_reconciliation_page' ]
        );

        // Sub: Finance — moved from top-level plug
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Finance', 'kh-ad-manager' ),
            __( 'Finance', 'kh-ad-manager' ),
            'manage_options',
            'ad-studio-finance',
            [ $this, 'render_finance_page' ]
        );

        // Remove the old standalone Settings pages from the Settings menu.
        remove_submenu_page( 'options-general.php', 'kh-ad-overlays' );
        remove_submenu_page( 'options-general.php', 'kh-ad-settings-native' );
    }

    /**
     * Render the Ad Studio Dashboard page.
     */
    public function render_dashboard_page() {
        require_once AM_PATH . 'src/Admin/DashboardCards.php';

        $stats      = KH_AdManager_DashboardCards::quick_stats();
        $campaigns  = KH_AdManager_DashboardCards::active_campaigns();
        $ads_by_slot= KH_AdManager_DashboardCards::ads_by_slot();
        $clicks     = KH_AdManager_DashboardCards::daily_spend();
        $slot_refs  = KH_AdManager_DashboardCards::slot_reference();

        // Status colour map.
        $status_colors = [
            'live'      => '#1e7e34',
            'draft'     => '#6c757d',
            'scheduled' => '#dba617',
            'paused'    => '#d63638',
        ];

        ?>
        <div class="wrap kh-ad-studio-dashboard">
            <h1 style="margin-bottom: 8px;"><?php esc_html_e( 'Ad Studio', 'kh-ad-manager' ); ?></h1>
            <p style="color: #646970; margin-bottom: 24px;"><?php esc_html_e( 'Manage ads, campaigns, sponsors, and slot assignments from one dashboard.', 'kh-ad-manager' ); ?></p>

            <!-- Stat cards row -->
            <div class="kh-ad-stats-row">
                <div class="kh-ad-stat-card">
                    <div class="kh-ad-stat-value"><?php echo esc_html( $stats['total_ads'] ); ?></div>
                    <div class="kh-ad-stat-label"><?php esc_html_e( 'Total Ads', 'kh-ad-manager' ); ?></div>
                </div>
                <div class="kh-ad-stat-card">
                    <div class="kh-ad-stat-value" style="color: #1e7e34;"><?php echo esc_html( $stats['live_campaigns'] ); ?></div>
                    <div class="kh-ad-stat-label"><?php esc_html_e( 'Live Campaigns', 'kh-ad-manager' ); ?></div>
                </div>
                <div class="kh-ad-stat-card">
                    <div class="kh-ad-stat-value"><?php echo esc_html( $stats['total_campaigns'] ); ?></div>
                    <div class="kh-ad-stat-label"><?php esc_html_e( 'Total Campaigns', 'kh-ad-manager' ); ?></div>
                </div>
                <div class="kh-ad-stat-card">
                    <div class="kh-ad-stat-value" style="color: #2271b1;"><?php echo esc_html( $stats['total_sponsors'] ); ?></div>
                    <div class="kh-ad-stat-label"><?php esc_html_e( 'Sponsors', 'kh-ad-manager' ); ?></div>
                </div>
            </div>

            <!-- Quick Create row -->
            <h2 style="margin-bottom: 12px;"><?php esc_html_e( 'Quick Create', 'kh-ad-manager' ); ?></h2>
            <div class="kh-ad-quick-create">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ad_unit' ) ); ?>" class="kh-ad-create-card kh-ad-create-card--primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <div>
                        <strong><?php esc_html_e( 'New Ad', 'kh-ad-manager' ); ?></strong>
                        <span><?php esc_html_e( 'Create a new ad creative.', 'kh-ad-manager' ); ?></span>
                    </div>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=ad-campaign&post_type=ad_unit' ) ); ?>" class="kh-ad-create-card">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <div>
                        <strong><?php esc_html_e( 'New Campaign', 'kh-ad-manager' ); ?></strong>
                        <span><?php esc_html_e( 'Set budget, dates, and target slots.', 'kh-ad-manager' ); ?></span>
                    </div>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=kh_sponsor' ) ); ?>" class="kh-ad-create-card">
                    <span class="dashicons dashicons-admin-users"></span>
                    <div>
                        <strong><?php esc_html_e( 'New Sponsor', 'kh-ad-manager' ); ?></strong>
                        <span><?php esc_html_e( 'Add a sponsor record with assets and contacts.', 'kh-ad-manager' ); ?></span>
                    </div>
                </a>
            </div>

            <!-- Two-column grid: Active Campaigns + Daily Clicks -->
            <div class="kh-ad-dashboard-grid">
                <!-- Active Campaigns -->
                <div class="kh-ad-dashboard-card">
                    <div class="kh-ad-card-header">
                        <span class="dashicons dashicons-chart-area"></span>
                        <h3><?php esc_html_e( 'Active Campaigns', 'kh-ad-manager' ); ?></h3>
                        <span class="kh-ad-card-badge"><?php echo count( $campaigns ); ?></span>
                    </div>
                    <div class="kh-ad-card-body">
                        <?php if ( empty( $campaigns ) ) : ?>
                            <p style="color: #646970;"><?php esc_html_e( 'No campaigns yet. Create your first campaign.', 'kh-ad-manager' ); ?></p>
                        <?php else : ?>
                            <table class="kh-ad-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Campaign', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Sponsor', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Budget', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Spend', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'CPC', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'End', 'kh-ad-manager' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $campaigns as $c ) :
                                        $status_color = isset( $status_colors[ $c['status'] ] ) ? $status_colors[ $c['status'] ] : '#6c757d';
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url( $c['edit_url'] ); ?>" style="font-weight: 600;">
                                                <?php echo esc_html( $c['name'] ); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html( $c['sponsor'] ); ?></td>
                                        <td><span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #fff; background: <?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( $c['status'] ); ?></span></td>
                                        <td>£<?php echo esc_html( number_format( $c['budget'], 2 ) ); ?></td>
                                        <td>£<?php echo esc_html( number_format( $c['spend'], 2 ) ); ?></td>
                                        <td>£<?php echo esc_html( number_format( $c['cpc'], 2 ) ); ?></td>
                                        <td><?php echo esc_html( $c['end_date'] ); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Daily Clicks (Spend Proxy) -->
                <div class="kh-ad-dashboard-card">
                    <div class="kh-ad-card-header">
                        <span class="dashicons dashicons-money-alt"></span>
                        <h3><?php esc_html_e( 'Clicks', 'kh-ad-manager' ); ?></h3>
                    </div>
                    <div class="kh-ad-card-body">
                        <div class="kh-ad-click-stats">
                            <div class="kh-ad-click-item">
                                <div class="kh-ad-click-number"><?php echo esc_html( number_format( $clicks['today'] ) ); ?></div>
                                <div class="kh-ad-click-label"><?php esc_html_e( 'Today', 'kh-ad-manager' ); ?></div>
                            </div>
                            <div class="kh-ad-click-item">
                                <div class="kh-ad-click-number"><?php echo esc_html( number_format( $clicks['month'] ) ); ?></div>
                                <div class="kh-ad-click-label"><?php esc_html_e( 'This Month', 'kh-ad-manager' ); ?></div>
                            </div>
                            <div class="kh-ad-click-item">
                                <div class="kh-ad-click-number"><?php echo esc_html( number_format( $clicks['quarter'] ) ); ?></div>
                                <div class="kh-ad-click-label"><?php esc_html_e( 'This Quarter', 'kh-ad-manager' ); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ads by Slot -->
                <div class="kh-ad-dashboard-card kh-ad-dashboard-card--wide">
                    <div class="kh-ad-card-header">
                        <span class="dashicons dashicons-grid-view"></span>
                        <h3><?php esc_html_e( 'Active Ads by Slot', 'kh-ad-manager' ); ?></h3>
                    </div>
                    <div class="kh-ad-card-body">
                        <?php if ( empty( $ads_by_slot ) ) : ?>
                            <p style="color: #646970;"><?php esc_html_e( 'No ads assigned to slots.', 'kh-ad-manager' ); ?></p>
                        <?php else : ?>
                            <table class="kh-ad-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Slot', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Ad', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Campaign', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Impressions', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Clicks', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'CTR', 'kh-ad-manager' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $ads_by_slot as $row ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $row['slot'] ); ?></code></td>
                                        <td>
                                            <?php if ( ! empty( $row['ad_edit_url'] ) ) : ?>
                                                <a href="<?php echo esc_url( $row['ad_edit_url'] ); ?>"><?php echo esc_html( $row['ad_title'] ); ?></a>
                                            <?php else : ?>
                                                <?php echo esc_html( $row['ad_title'] ); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $row['campaign'] ); ?></td>
                                        <td><?php echo esc_html( number_format( $row['impressions'] ) ); ?></td>
                                        <td><?php echo esc_html( number_format( $row['clicks'] ) ); ?></td>
                                        <td><?php echo esc_html( $row['ctr'] ); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Slot Reference -->
                <div class="kh-ad-dashboard-card kh-ad-dashboard-card--wide">
                    <div class="kh-ad-card-header">
                        <span class="dashicons dashicons-info-outline"></span>
                        <h3><?php esc_html_e( 'Slot Reference', 'kh-ad-manager' ); ?></h3>
                    </div>
                    <div class="kh-ad-card-body">
                        <?php if ( empty( $slot_refs ) ) : ?>
                            <p style="color: #646970;"><?php esc_html_e( 'No ad slots defined.', 'kh-ad-manager' ); ?></p>
                        <?php else : ?>
                            <table class="kh-ad-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Slot', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Dimensions', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Description', 'kh-ad-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Shortcode', 'kh-ad-manager' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $slot_refs as $ref ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $ref['slug'] ); ?></code></td>
                                        <td><?php echo esc_html( $ref['dimensions'] ); ?></td>
                                        <td style="font-size: 12px;"><?php echo esc_html( $ref['description'] ); ?></td>
                                        <td><code style="font-size: 11px;"><?php echo esc_html( $ref['shortcode'] ); ?></code></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Overlay Settings sub-page.
     * (Migrated from kh-ad-manager.php admin_menu callback.)
     */
    public function render_overlay_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save handler.
        if ( isset( $_POST['kh_ad_save_overlays'] ) && check_admin_referer( 'kh_ad_overlay_settings', 'kh_ad_overlay_nonce' ) ) {
            foreach ( kh_ad_manager_overlay_slots() as $slot => $label ) {
                $base = 'kh_' . str_replace( '-', '_', $slot );
                if ( isset( $_POST[ $base . '_enabled' ] ) ) {
                    update_option( $base . '_enabled', (bool) $_POST[ $base . '_enabled' ] );
                }
                if ( isset( $_POST[ $base . '_delay' ] ) ) {
                    update_option( $base . '_delay', max( 0, (int) $_POST[ $base . '_delay' ] ) );
                }
            }
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Overlay settings saved.', 'kh-ad-manager' ) . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ad Overlay Settings', 'kh-ad-manager' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'kh_ad_overlay_settings', 'kh_ad_overlay_nonce' ); ?>
                <table class="widefat fixed" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Overlay', 'kh-ad-manager' ); ?></th>
                            <th><?php esc_html_e( 'Enabled', 'kh-ad-manager' ); ?></th>
                            <th><?php esc_html_e( 'Delay (milliseconds)', 'kh-ad-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( kh_ad_manager_overlay_slots() as $slot => $label ) :
                            $base = 'kh_' . str_replace( '-', '_', $slot );
                            $enabled = get_option( $base . '_enabled', true );
                            $delay   = get_option( $base . '_delay', kh_ad_manager_get_overlay_default_delay( $slot ) );
                        ?>
                        <tr>
                            <td><label for="<?php echo esc_attr( $base . '_delay' ); ?>"><?php echo esc_html( $label ); ?></label></td>
                            <td>
                                <input type="hidden" name="<?php echo esc_attr( $base . '_enabled' ); ?>" value="0" />
                                <input type="checkbox" name="<?php echo esc_attr( $base . '_enabled' ); ?>" value="1" <?php checked( $enabled, true ); ?> />
                            </td>
                            <td><input type="number" min="0" step="100" name="<?php echo esc_attr( $base . '_delay' ); ?>" id="<?php echo esc_attr( $base . '_delay' ); ?>" value="<?php echo esc_attr( $delay ); ?>" /></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="kh_ad_save_overlays" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'kh-ad-manager' ); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render the Global Ad Codes sub-page.
     * (Migrated from admin-ui.php admin_menu callback.)
     */
    public function render_global_codes_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save handler.
        if ( isset( $_POST['kh_ad_save_global_codes'] ) && check_admin_referer( 'kh_ad_global_codes', 'kh_ad_global_nonce' ) ) {
            $slots = [ 'exit_overlay', 'footer', 'header', 'popup', 'sidebar1', 'sidebar2', 'ticker', 'slide_in' ];
            foreach ( $slots as $slot ) {
                if ( isset( $_POST[ 'ad_code_' . $slot ] ) ) {
                    update_option( 'ad_code_' . $slot, wp_kses_post( wp_unslash( $_POST[ 'ad_code_' . $slot ] ) ) );
                }
            }
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Global ad codes saved.', 'kh-ad-manager' ) . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Global Ad Codes', 'kh-ad-manager' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Fallback ad code for each slot when no ad unit is assigned or the assigned ad is not active.', 'kh-ad-manager' ); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field( 'kh_ad_global_codes', 'kh_ad_global_nonce' ); ?>
                <table class="form-table">
                    <?php foreach ( [ 'exit_overlay', 'footer', 'header', 'popup', 'sidebar1', 'sidebar2', 'ticker', 'slide_in' ] as $slot ) : ?>
                        <tr>
                            <th><label for="ad_code_<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $slot ) ) ); ?></label></th>
                            <td><textarea class="large-text" rows="3" id="ad_code_<?php echo esc_attr( $slot ); ?>" name="ad_code_<?php echo esc_attr( $slot ); ?>"><?php echo esc_textarea( get_option( "ad_code_{$slot}", '' ) ); ?></textarea></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="submit">
                    <input type="submit" name="kh_ad_save_global_codes" class="button button-primary" value="<?php esc_attr_e( 'Save Codes', 'kh-ad-manager' ); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render the Reconciliation sub-page.
     *
     * If the existing ReconciliationPage class is available, delegates to it.
     * Otherwise shows a placeholder.
     */
    public function render_reconciliation_page() {
        // If the legacy ReconciliationPage class exists and we want to reuse its render,
        // we can check for its method and call it directly here.
        // For now, provide a clean routing message.
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Reconciliation', 'kh-ad-manager' ); ?></h1>
            <p><?php esc_html_e( 'Reconciliation tools moved here. If this page is empty, the Reconciliation module may need updating.', 'kh-ad-manager' ); ?></p>
            <?php
            // If the Reconciliation class is loaded and has a render method, call it.
            if ( class_exists( 'KH_AdManager_ReconciliationPage' ) ) {
                $page = new KH_AdManager_ReconciliationPage();
                if ( method_exists( $page, 'render_page' ) ) {
                    $page->render_page();
                } elseif ( method_exists( $page, 'render' ) ) {
                    $page->render();
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render the Finance sub-page.
     *
     * If the existing FinanceReconciliationPage class is available, delegates to it.
     */
    public function render_finance_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Finance', 'kh-ad-manager' ); ?></h1>
            <p><?php esc_html_e( 'Finance reconciliation tools moved here. If this page is empty, the Finance module may need updating.', 'kh-ad-manager' ); ?></p>
            <?php
            if ( class_exists( 'KH_AdManager_FinanceReconciliationPage' ) ) {
                $page = new KH_AdManager_FinanceReconciliationPage();
                if ( method_exists( $page, 'render_page' ) ) {
                    $page->render_page();
                } elseif ( method_exists( $page, 'render' ) ) {
                    $page->render();
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Enqueue dashboard CSS on Ad Studio pages.
     */
    public function enqueue_dashboard_assets( $hook ) {
        $screen = get_current_screen();

        // Determine if we're on an Ad Studio page.
        $is_ad_studio = $screen && (
            $screen->id === 'toplevel_page_' . self::PARENT_SLUG
            || strpos( $screen->id, self::PARENT_SLUG ) !== false
            || strpos( $hook, self::PARENT_SLUG ) !== false
            // Also enqueue on the ad_unit CPT screens nested under Ad Studio.
            || ( $screen->post_type === 'ad_unit' && $screen->base === 'edit' )
            || ( $screen->post_type === 'ad_unit' && $screen->base === 'post' )
            || ( $screen->post_type === 'kh_sponsor' && $screen->base === 'edit' )
            || ( $screen->taxonomy === 'ad-campaign' )
            || ( $screen->taxonomy === 'ad-slot' )
        );

        if ( ! $is_ad_studio ) {
            return;
        }

        wp_enqueue_style(
            'ad-studio-dashboard',
            AM_URL . 'assets/ad-manager-admin.css',
            [],
            '0.2'
        );
    }
}
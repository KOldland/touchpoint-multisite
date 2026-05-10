<?php

namespace KHM\PublicFrontend;

use KHM\Connect\ConnectTaxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Buyer-facing Connect directory + unified buyer hub shortcodes.
 */
class ConnectDirectoryShortcode {

    private const TEAM_META_KEY = 'khm_buyer_team_user_ids';

    public function register(): void {
        add_shortcode( 'khm_connect_directory', [ $this, 'render_connect_directory' ] );
        add_shortcode( 'khm_buyer_hub', [ $this, 'render_buyer_hub' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'template_include', [ $this, 'portal_template_include' ] );
        add_filter( 'body_class', function ( array $classes ): array {
            global $post;
            if ( $post && has_shortcode( $post->post_content, 'khm_buyer_hub' ) ) {
                $classes[] = 'khm-buyer-portal-page';
            }
            return $classes;
        } );
    }

    public function portal_template_include( string $template ): string {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'khm_buyer_hub' ) ) {
            return $template;
        }
        $portal_template = dirname( __DIR__, 2 ) . '/templates/portal-full-width.php';
        if ( file_exists( $portal_template ) ) {
            return $portal_template;
        }
        return $template;
    }

    public function enqueue_assets(): void {
        global $post;

        if ( ! $post ) {
            return;
        }

        $content = (string) $post->post_content;
        $has_directory = has_shortcode( $content, 'khm_connect_directory' );
        $has_hub       = has_shortcode( $content, 'khm_buyer_hub' );

        if ( ! $has_directory && ! $has_hub ) {
            return;
        }

        $plugin_url  = plugin_dir_url( dirname( __DIR__ ) );
        $plugin_path = plugin_dir_path( dirname( __DIR__ ) );

        $css_rel = 'assets/css/connect-directory.css';
        $js_rel  = 'assets/js/connect-directory.js';

        wp_enqueue_style(
            'khm-connect-directory',
            $plugin_url . $css_rel,
            [],
            file_exists( $plugin_path . $css_rel ) ? (string) filemtime( $plugin_path . $css_rel ) : '1'
        );

        wp_enqueue_script(
            'khm-connect-directory',
            $plugin_url . $js_rel,
            [],
            file_exists( $plugin_path . $js_rel ) ? (string) filemtime( $plugin_path . $js_rel ) : '1',
            true
        );

        $user_id = get_current_user_id();

        wp_localize_script( 'khm-connect-directory', 'khmConnectDirectory', [
            'restBase'      => esc_url_raw( rest_url( 'khm/v1/connect/' ) ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn'    => is_user_logged_in(),
            'userId'        => $user_id,
            'loginUrl'      => wp_login_url( get_permalink( get_queried_object_id() ) ?: home_url( '/' ) ),
            'isSuperUser'   => $this->is_buyer_superuser( $user_id ),
            'strings'       => [
                'loading'            => __( 'Loading providers...', 'khm-membership' ),
                'noProviders'        => __( 'No providers match the selected filters.', 'khm-membership' ),
                'maxCompare'         => __( 'You can compare up to 3 providers.', 'khm-membership' ),
                'requestSent'        => __( 'Request sent successfully.', 'khm-membership' ),
                'requestFailed'      => __( 'Could not send request. Please try again.', 'khm-membership' ),
                'rfqCreated'         => __( 'RFQ created. Fetching matches...', 'khm-membership' ),
                'rfqCreateFailed'    => __( 'Could not create RFQ.', 'khm-membership' ),
                'matchesFailed'      => __( 'Could not load matches for this RFQ.', 'khm-membership' ),
                'loginRequired'      => __( 'Please log in with an active membership to continue.', 'khm-membership' ),
            ],
        ] );
    }

    public function render_connect_directory( array $atts = [] ): string {
        $atts = shortcode_atts( [
            'show_rfp'     => 'yes',
            'show_compare' => 'yes',
        ], $atts, 'khm_connect_directory' );

        return $this->render_directory_ui( [
            'show_rfp'     => 'yes' === (string) $atts['show_rfp'],
            'show_compare' => 'yes' === (string) $atts['show_compare'],
            'hub_mode'     => false,
        ] );
    }

    public function render_buyer_hub( array $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );
            return '<div class="khm-buyer-hub-login"><p>' . esc_html__( 'Please log in to access your buyer hub.', 'khm-membership' ) . '</p><a class="khm-buyer-btn" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log In', 'khm-membership' ) . '</a></div>';
        }

        $this->handle_team_settings_post();

        $section = isset( $_GET['bh_section'] ) ? sanitize_key( (string) $_GET['bh_section'] ) : 'overview';
        if ( ! in_array( $section, [ 'overview', 'discover', 'searches', 'requests', 'membership', 'credits', 'team' ], true ) ) {
            $section = 'overview';
        }

        ob_start();
        ?>
        <div class="khm-buyer-hub">
            <header class="khm-buyer-hub-header">
                <h2><?php esc_html_e( 'Buyer Hub', 'khm-membership' ); ?></h2>
                <p><?php esc_html_e( 'Find providers, run RFQs, manage membership, and coordinate your team from one place.', 'khm-membership' ); ?></p>
            </header>

            <div class="khm-buyer-hub-body">
                <aside class="khm-buyer-hub-sidebar">
                    <?php $this->render_hub_nav( $section ); ?>
                </aside>

                <section class="khm-buyer-hub-section">
                    <?php
                    switch ( $section ) {
                        case 'discover':
                            echo $this->render_directory_ui( [
                                'show_rfp'     => true,
                                'show_compare' => true,
                                'hub_mode'     => true,
                            ] );
                            break;
                        case 'searches':
                            $this->render_searches_panel();
                            break;
                        case 'requests':
                            $this->render_requests_panel();
                            break;
                        case 'membership':
                            $this->render_membership_panel();
                            break;
                        case 'credits':
                            $this->render_credits_panel();
                            break;
                        case 'team':
                            $this->render_team_panel();
                            break;
                        default:
                            $this->render_overview_panel();
                            break;
                    }
                    ?>
                </section>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_hub_nav( string $section ): void {
        $tabs = [
            'overview'   => __( 'Overview', 'khm-membership' ),
            'discover'   => __( 'Discover', 'khm-membership' ),
            'searches'   => __( 'Saved Searches', 'khm-membership' ),
            'requests'   => __( 'My Requests', 'khm-membership' ),
            'membership' => __( 'Membership', 'khm-membership' ),
            'credits'    => __( 'Credits', 'khm-membership' ),
            'team'       => __( 'Team Controls', 'khm-membership' ),
        ];

        echo '<nav class="khm-buyer-hub-nav">';
        foreach ( $tabs as $slug => $label ) {
            $url   = add_query_arg( 'bh_section', $slug );
            $class = 'khm-buyer-hub-nav-link' . ( $slug === $section ? ' is-active' : '' );
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }

    private function render_overview_panel(): void {
        $user_id          = get_current_user_id();
        $active_requests  = $this->count_active_requests_for_user( $user_id );
        $team_count       = count( $this->get_team_user_ids_for_owner( $user_id ) );
        $is_super         = $this->is_buyer_superuser( $user_id );

        ?>
        <div class="khm-buyer-cards">
            <article class="khm-buyer-card">
                <h3><?php esc_html_e( 'Active Requests', 'khm-membership' ); ?></h3>
                <p class="khm-buyer-metric"><?php echo esc_html( (string) $active_requests ); ?></p>
            </article>
            <article class="khm-buyer-card">
                <h3><?php esc_html_e( 'Team Seats Managed', 'khm-membership' ); ?></h3>
                <p class="khm-buyer-metric"><?php echo esc_html( (string) $team_count ); ?></p>
            </article>
            <article class="khm-buyer-card">
                <h3><?php esc_html_e( 'SuperUser Status', 'khm-membership' ); ?></h3>
                <p class="khm-buyer-metric"><?php echo esc_html( $is_super ? __( 'Enabled', 'khm-membership' ) : __( 'Disabled', 'khm-membership' ) ); ?></p>
            </article>
        </div>
        <div class="khm-buyer-overview-modules">
            <?php echo do_shortcode( '[khm_portal_dashboard show_activity="no" show_quick_actions="yes"]' ); ?>
        </div>
        <?php
    }

    private function render_searches_panel(): void {
        $discover_url = esc_url( add_query_arg( 'bh_section', 'discover' ) );
        ?>
        <h3><?php esc_html_e( 'Saved Searches', 'khm-membership' ); ?></h3>
        <p><?php esc_html_e( 'Bookmarked sets of search criteria. Re-running a search shows the current matches without contacting providers — quotes are only sent when you click Send RFQ.', 'khm-membership' ); ?></p>
        <div class="khm-saved-searches" data-role="saved-searches"></div>
        <p class="khm-saved-searches-empty" data-role="saved-searches-empty" hidden>
            <?php
            printf(
                /* translators: %s: link to the Discover wizard */
                esc_html__( 'No saved searches yet. Run the wizard from %s and save the criteria.', 'khm-membership' ),
                '<a href="' . esc_attr( $discover_url ) . '">' . esc_html__( 'Discover', 'khm-membership' ) . '</a>'
            );
            ?>
        </p>
        <?php
    }

    private function render_requests_panel(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'connect_opportunities';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, request_type, opportunity_status, created_at
                 FROM {$table}
                 WHERE buyer_account_id = %d
                 ORDER BY created_at DESC
                 LIMIT 25",
                get_current_user_id()
            ),
            ARRAY_A
        );

        echo '<h3>' . esc_html__( 'Recent Buyer Requests', 'khm-membership' ) . '</h3>';

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No requests yet. Visit Discover to contact providers.', 'khm-membership' ) . '</p>';
            return;
        }

        echo '<table class="khm-buyer-table"><thead><tr><th>' . esc_html__( 'Request ID', 'khm-membership' ) . '</th><th>' . esc_html__( 'Type', 'khm-membership' ) . '</th><th>' . esc_html__( 'Status', 'khm-membership' ) . '</th><th>' . esc_html__( 'Created', 'khm-membership' ) . '</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr>';
            echo '<td>#' . esc_html( (string) ( $row['id'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['request_type'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['opportunity_status'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function render_membership_panel(): void {
        echo '<h3>' . esc_html__( 'Membership Management', 'khm-membership' ) . '</h3>';
        echo '<p>' . esc_html__( 'Pause, resume, and cancel your subscription from this panel.', 'khm-membership' ) . '</p>';
        echo do_shortcode( '[khm_portal_membership]' );
    }

    private function render_credits_panel(): void {
        echo '<h3>' . esc_html__( 'Credits and Gifting', 'khm-membership' ) . '</h3>';
        echo '<p>' . esc_html__( 'Track credit usage, top up, and redeem gift vouchers.', 'khm-membership' ) . '</p>';
        echo do_shortcode( '[khm_portal_credits show_history="yes" show_topup="yes"]' );
    }

    private function render_team_panel(): void {
        $user_id    = get_current_user_id();
        $is_super   = $this->is_buyer_superuser( $user_id );

        if ( ! $is_super ) {
            echo '<div class="khm-buyer-team-locked">' . esc_html__( 'Team controls are available to buyer SuperUsers only.', 'khm-membership' ) . '</div>';
            return;
        }

        $team_ids = $this->get_team_user_ids_for_owner( $user_id );

        echo '<h3>' . esc_html__( 'Team Subscription Controls', 'khm-membership' ) . '</h3>';
        echo '<p>' . esc_html__( 'Manage the team user IDs under your buying account. SuperUsers can review subscription status and request load for each team member.', 'khm-membership' ) . '</p>';

        echo '<form method="post" class="khm-buyer-team-form">';
        wp_nonce_field( 'khm_buyer_hub_team_settings', 'khm_buyer_hub_team_nonce' );
        echo '<input type="hidden" name="khm_buyer_hub_action" value="save_team_settings" />';
        echo '<label for="khm_buyer_team_ids"><strong>' . esc_html__( 'Team User IDs', 'khm-membership' ) . '</strong></label>';
        echo '<input type="text" id="khm_buyer_team_ids" name="khm_buyer_team_ids" value="' . esc_attr( implode( ',', $team_ids ) ) . '" placeholder="12,45,98" />';
        echo '<p class="description">' . esc_html__( 'Comma-separated WordPress user IDs.', 'khm-membership' ) . '</p>';
        echo '<button type="submit" class="khm-buyer-btn">' . esc_html__( 'Save Team Settings', 'khm-membership' ) . '</button>';
        echo '</form>';

        $this->render_team_table( $team_ids );
    }

    private function render_team_table( array $team_ids ): void {
        if ( empty( $team_ids ) ) {
            echo '<p>' . esc_html__( 'No team members configured yet.', 'khm-membership' ) . '</p>';
            return;
        }

        global $wpdb;
        $membership_table = $wpdb->prefix . 'user_membership';

        echo '<table class="khm-buyer-table"><thead><tr><th>' . esc_html__( 'User', 'khm-membership' ) . '</th><th>' . esc_html__( 'Email', 'khm-membership' ) . '</th><th>' . esc_html__( 'Membership Status', 'khm-membership' ) . '</th><th>' . esc_html__( 'Active Requests', 'khm-membership' ) . '</th></tr></thead><tbody>';

        foreach ( $team_ids as $team_user_id ) {
            $user = get_userdata( $team_user_id );
            if ( ! $user ) {
                continue;
            }

            $status = (string) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT status FROM {$membership_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                    $team_user_id
                )
            );

            $active_requests = $this->count_active_requests_for_user( $team_user_id );

            echo '<tr>';
            echo '<td>' . esc_html( $user->display_name ) . ' (#' . esc_html( (string) $team_user_id ) . ')</td>';
            echo '<td>' . esc_html( (string) $user->user_email ) . '</td>';
            echo '<td>' . esc_html( $status !== '' ? $status : __( 'none', 'khm-membership' ) ) . '</td>';
            echo '<td>' . esc_html( (string) $active_requests ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_directory_ui( array $opts ): string {
        $opts = wp_parse_args( $opts, [
            'show_rfp'     => true,
            'show_compare' => true,
            'hub_mode'     => false,
        ] );

        $expertise_options       = ConnectTaxonomy::EXPERTISE_AREAS;
        $industry_options        = ConnectTaxonomy::INDUSTRY_LABELS;
        $expertise_to_industries = ConnectTaxonomy::SITE_INDUSTRIES;

        // Focus-area cards: only the 5 primary product areas (no vertical/sector sites).
        $focus_area_slugs = [ 'pricing', 'aftermarket', 'field-service', 'spare-parts', 'ecommerce' ];
        $focus_area_logos = [
            'pricing'       => 'revenue_operations.png',
            'aftermarket'   => 'aftermarket-operations.png',
            'field-service' => 'field-service-management-logo.png',
            'spare-parts'   => 'spare-parts-and-logistics2.png',
            'ecommerce'     => 'Industrial-eCommerce.png',
        ];
        $plugin_images_url = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/images/sites/';

        ob_start();
        ?>
        <div class="khm-connect-directory" data-show-rfp="<?php echo esc_attr( $opts['show_rfp'] ? '1' : '0' ); ?>" data-show-compare="<?php echo esc_attr( $opts['show_compare'] ? '1' : '0' ); ?>" data-expertise-industries="<?php echo esc_attr( (string) wp_json_encode( $expertise_to_industries ) ); ?>" data-focus-channels="<?php echo esc_attr( (string) wp_json_encode( ConnectTaxonomy::FOCUS_AREA_CHANNELS ) ); ?>">
            <div class="khm-connect-filter-panel khm-onboard-shell" data-role="wizard">
                <header class="khm-onboard-header">
                    <div>
                        <h3><?php esc_html_e( 'Discover', 'khm-membership' ); ?></h3>
                        <p><?php esc_html_e( 'Use discovery wizard to find the companies that can help take your business forward.', 'khm-membership' ); ?></p>
                    </div>
                    <span class="khm-onboard-step-count" data-role="wizard-progress-text"></span>
                </header>

                <div class="khm-onboard-progress" aria-hidden="true">
                    <div class="khm-onboard-progress-fill" data-role="wizard-progress-fill"></div>
                </div>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="1">
                    <h4><?php esc_html_e( 'Welcome', 'khm-membership' ); ?></h4>
                    <p><?php esc_html_e( 'Over the next few pages we will find out a little bit about your needs and then our recommendation engine will find companies that are best placed to partner with you. Click Begin to continue.', 'khm-membership' ); ?></p>
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="2" hidden>
                    <h4><?php esc_html_e( 'To start — which area of your business are you looking to improve performance in?', 'khm-membership' ); ?></h4>
                    <p><?php esc_html_e( 'Select one to continue.', 'khm-membership' ); ?></p>
                    <div class="khm-focus-cards" data-role="focus-cards">
                        <?php foreach ( $focus_area_slugs as $slug ) :
                            $label    = ConnectTaxonomy::EXPERTISE_AREAS[ $slug ] ?? $slug;
                            $logo_url = isset( $focus_area_logos[ $slug ] ) ? esc_url( $plugin_images_url . $focus_area_logos[ $slug ] ) : '';
                        ?>
                            <button type="button" class="khm-focus-card" data-expertise-slug="<?php echo esc_attr( $slug ); ?>">
                                <?php if ( $logo_url ) : ?>
                                    <img class="khm-focus-card-logo" src="<?php echo $logo_url; ?>" alt="" aria-hidden="true">
                                <?php endif; ?>
                                <span class="khm-focus-card-label"><?php echo esc_html( $label ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php /* Hidden select — only focus-area slugs — so buildDirectoryParams/saveAsRfq continue to work */ ?>
                    <select data-filter="expertise" multiple aria-hidden="true" style="display:none">
                        <?php foreach ( $focus_area_slugs as $slug ) :
                            $label = ConnectTaxonomy::EXPERTISE_AREAS[ $slug ] ?? $slug;
                        ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="3" hidden>
                    <h4><?php esc_html_e( 'What is it you are looking to improve today?', 'khm-membership' ); ?></h4>
                    <p data-role="problem-help-text"><?php esc_html_e( 'Start typing to find your challenge, or scroll the list below.', 'khm-membership' ); ?></p>
                    <div class="khm-combobox" data-role="problem-combobox">
                        <div class="khm-combobox-chip" data-role="problem-chip" hidden>
                            <span class="khm-combobox-chip-label" data-role="problem-chip-label"></span>
                            <button type="button" class="khm-combobox-chip-clear" data-action="clear-problem" aria-label="<?php esc_attr_e( 'Remove selection', 'khm-membership' ); ?>">&#x2715;</button>
                        </div>
                        <input type="text" class="khm-combobox-input" data-role="problem-input" autocomplete="off"
                               placeholder="<?php esc_attr_e( 'e.g. pipeline visibility, lead routing...', 'khm-membership' ); ?>"
                               aria-label="<?php esc_attr_e( 'Search for a business challenge', 'khm-membership' ); ?>"
                               aria-autocomplete="list" aria-expanded="false" role="combobox" />
                        <ul class="khm-combobox-dropdown" data-role="problem-dropdown" role="listbox" hidden></ul>
                    </div>
                    <input type="hidden" data-filter="problem" value="" />
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="4" hidden>
                    <h4><?php esc_html_e( 'These are some of the solutions that can help', 'khm-membership' ); ?> <em data-role="solutions-problem-label"><?php esc_html_e( 'with your challenge', 'khm-membership' ); ?></em></h4>
                    <p><?php esc_html_e( 'Please check any solutions you would like to explore further.', 'khm-membership' ); ?></p>
                    <div data-role="solutions-software" hidden>
                        <h5 class="khm-solutions-type-heading"><?php esc_html_e( 'Software', 'khm-membership' ); ?></h5>
                        <div class="khm-solutions-grid" data-role="solutions-software-items"></div>
                    </div>
                    <div data-role="solutions-hardware" hidden>
                        <h5 class="khm-solutions-type-heading"><?php esc_html_e( 'Hardware', 'khm-membership' ); ?></h5>
                        <div class="khm-solutions-grid" data-role="solutions-hardware-items"></div>
                    </div>
                    <div data-role="solutions-consultancy" hidden>
                        <h5 class="khm-solutions-type-heading"><?php esc_html_e( 'Consultancy', 'khm-membership' ); ?></h5>
                        <div class="khm-solutions-grid" data-role="solutions-consultancy-items"></div>
                    </div>
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="5" hidden>
                    <h4><?php esc_html_e( 'About your business', 'khm-membership' ); ?></h4>

                    <fieldset class="khm-card-picker-group">
                        <legend><?php esc_html_e( 'What sector do you operate in?', 'khm-membership' ); ?> <em class="khm-optional"><?php esc_html_e( '(select all that apply)', 'khm-membership' ); ?></em></legend>
                        <div class="khm-card-picker" data-picker="sector">
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="manufacturing" /><span><?php esc_html_e( 'Manufacturing', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="discrete-manufacturing" /><span><?php esc_html_e( 'Discrete Manufacturing', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="process-manufacturing" /><span><?php esc_html_e( 'Process Manufacturing', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="electronics-electrical-goods" /><span><?php esc_html_e( 'Electronics &amp; Electrical Goods', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="chemical-manufacturing" /><span><?php esc_html_e( 'Chemical Manufacturing', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="food-beverage" /><span><?php esc_html_e( 'Food &amp; Beverage', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="aerospace-defense" /><span><?php esc_html_e( 'Aerospace &amp; Defense', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="pharma" /><span><?php esc_html_e( 'Pharma', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="med-tech" /><span><?php esc_html_e( 'Med Tech', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="automotive" /><span><?php esc_html_e( 'Automotive', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="metal-fabrication" /><span><?php esc_html_e( 'Metal Fabrication', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="infrastructure" /><span><?php esc_html_e( 'Infrastructure', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="facilities-management" /><span><?php esc_html_e( 'Facilities Management', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="construction" /><span><?php esc_html_e( 'Construction', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="aviation" /><span><?php esc_html_e( 'Aviation', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="aerospace" /><span><?php esc_html_e( 'Aerospace', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="heavy-manufacturing" /><span><?php esc_html_e( 'Heavy Manufacturing', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="industrial-engineering" /><span><?php esc_html_e( 'Industrial Engineering', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="checkbox" name="sector[]" value="other" /><span><?php esc_html_e( 'Other', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group">
                        <legend>
                            <?php esc_html_e( 'How large is your team?', 'khm-membership' ); ?>
                            <span class="khm-help-tooltip">
                                <button type="button" class="khm-help-tooltip-btn" aria-label="<?php esc_attr_e( 'Team size help', 'khm-membership' ); ?>">?</button>
                                <span class="khm-help-tooltip-bubble"><?php esc_html_e( 'We specifically want to know the size of the team you are looking to implement the solution for.', 'khm-membership' ); ?></span>
                            </span>
                        </legend>
                        <div class="khm-card-picker khm-card-picker--wide" data-picker="company_size_band">
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="1-50" /><span>1 – 50</span></label>
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="51-250" /><span>51 – 250</span></label>
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="251-500" /><span>251 – 500</span></label>
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="501-1000" /><span>501 – 1,000</span></label>
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="1001-2500" /><span>1,001 – 2,500</span></label>
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="2501-5000" /><span>2,501 – 5,000</span></label>
                            <label class="khm-card-option"><input type="radio" name="company_size_band" value="5000+" /><span>5,000+</span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group">
                        <legend>
                            <?php esc_html_e( 'Where are you based?', 'khm-membership' ); ?>
                            <span class="khm-help-tooltip">
                                <button type="button" class="khm-help-tooltip-btn" aria-label="<?php esc_attr_e( 'Location help', 'khm-membership' ); ?>">?</button>
                                <span class="khm-help-tooltip-bubble"><?php esc_html_e( 'Where is the team based that you are looking to implement a solution for?', 'khm-membership' ); ?></span>
                            </span>
                        </legend>
                        <div class="khm-card-picker" data-picker="region">
                            <label class="khm-card-option"><input type="radio" name="region" value="uk" /><span><?php esc_html_e( 'UK', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="region" value="europe" /><span><?php esc_html_e( 'Europe', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="region" value="north-america" /><span><?php esc_html_e( 'North America', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="region" value="apac" /><span><?php esc_html_e( 'APAC', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="region" value="global" /><span><?php esc_html_e( 'Global / Remote', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group">
                        <legend><?php esc_html_e( 'Which tools do you already use?', 'khm-membership' ); ?> <em class="khm-optional"><?php esc_html_e( '(optional)', 'khm-membership' ); ?></em></legend>
                        <div class="khm-integration-grid" data-picker="integrations">
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="microsoft-dynamics" /><span>Microsoft Dynamics</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="sap" /><span>SAP</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="ibm" /><span>IBM</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="ptc" /><span>PTC</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="ifs" /><span>IFS</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="servicenow" /><span>ServiceNow</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="salesforce" /><span>Salesforce</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="oracle" /><span>Oracle</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="infor" /><span>Infor</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="epicor" /><span>Epicor</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="siemens" /><span>Siemens</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="schneider-electric" /><span>Schneider Electric</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="honeywell" /><span>Honeywell</span></label>
                            <label class="khm-integration-option"><input type="checkbox" name="integrations" value="netsuite" /><span>NetSuite</span></label>
                        </div>
                        <div class="khm-integrations-other">
                            <label><?php esc_html_e( 'Any others worth mentioning?', 'khm-membership' ); ?>
                                <input type="text" data-picker="integrations_other" placeholder="<?php esc_attr_e( 'e.g. legacy ERP, custom data warehouse…', 'khm-membership' ); ?>" maxlength="300" />
                            </label>
                        </div>
                    </fieldset>
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="6" hidden>
                    <h4><?php esc_html_e( 'How you want to work', 'khm-membership' ); ?></h4>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="software" hidden>
                        <legend><?php esc_html_e( 'What kind of software partner are you looking for?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="partner_posture_software">
                            <label class="khm-card-option"><input type="radio" name="partner_posture_software" value="established-platform" /><span><?php esc_html_e( 'Established platform with a large customer base', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="partner_posture_software" value="specialist-best-of-breed" /><span><?php esc_html_e( 'Specialist or best-of-breed solution', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="partner_posture_software" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="hardware" hidden>
                        <legend><?php esc_html_e( 'What kind of hardware partner are you looking for?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="partner_posture_hardware">
                            <label class="khm-card-option"><input type="radio" name="partner_posture_hardware" value="established-platform" /><span><?php esc_html_e( 'Established platform with a large customer base', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="partner_posture_hardware" value="specialist-best-of-breed" /><span><?php esc_html_e( 'Specialist or best-of-breed solution', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="partner_posture_hardware" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="software" hidden>
                        <legend><?php esc_html_e( 'Where should the software run?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="deployment_mode">
                            <label class="khm-card-option"><input type="radio" name="deployment_mode" value="saas" /><span><?php esc_html_e( 'Cloud / SaaS', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="deployment_mode" value="hybrid" /><span><?php esc_html_e( 'Hybrid', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="deployment_mode" value="on-prem" /><span><?php esc_html_e( 'On-premise', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="deployment_mode" value="private-cloud" /><span><?php esc_html_e( 'Private cloud', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="deployment_mode" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="software" hidden>
                        <legend><?php esc_html_e( 'How much support do you need getting started with software?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="onboarding_style">
                            <label class="khm-card-option"><input type="radio" name="onboarding_style" value="self-serve" /><span><?php esc_html_e( "Self-serve — we'll figure it out", 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="onboarding_style" value="guided-onboarding" /><span><?php esc_html_e( 'Guided onboarding', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="onboarding_style" value="fully-managed" /><span><?php esc_html_e( 'Fully managed service', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="onboarding_style" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="hardware" hidden>
                        <legend><?php esc_html_e( 'Who should handle hardware installation?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="installation_preference">
                            <label class="khm-card-option"><input type="radio" name="installation_preference" value="managed-installation" /><span><?php esc_html_e( 'Managed by the partner', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="installation_preference" value="do-it-together" /><span><?php esc_html_e( 'Do it together', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="installation_preference" value="self-install" /><span><?php esc_html_e( 'We will self-install', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="installation_preference" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="consultancy" hidden>
                        <legend><?php esc_html_e( 'How do you want to engage with consultants?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="engagement_model">
                            <label class="khm-card-option"><input type="radio" name="engagement_model" value="fixed-project" /><span><?php esc_html_e( 'Fixed-scope project', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="engagement_model" value="retained" /><span><?php esc_html_e( 'Ongoing retained advisory', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="engagement_model" value="ad-hoc-advisory" /><span><?php esc_html_e( 'Ad-hoc advisory', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="engagement_model" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="consultancy" hidden>
                        <legend><?php esc_html_e( 'What kind of consultancy team?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="team_preference">
                            <label class="khm-card-option"><input type="radio" name="team_preference" value="boutique-specialist" /><span><?php esc_html_e( 'Boutique specialist', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="team_preference" value="large-practice" /><span><?php esc_html_e( 'Large established practice', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="team_preference" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>

                    <fieldset class="khm-card-picker-group khm-step6-group" data-show-for="software hardware" hidden>
                        <legend><?php esc_html_e( 'How important is a pilot?', 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker" data-picker="proof_of_commitment">
                            <label class="khm-card-option"><input type="radio" name="proof_of_commitment" value="free-test-expected" /><span><?php esc_html_e( 'A free test is expected.', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="proof_of_commitment" value="pilot-expected" /><span><?php esc_html_e( 'A pilot is expected', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="proof_of_commitment" value="pilot-essential" /><span><?php esc_html_e( 'A pilot is essential', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="proof_of_commitment" value="pilot-preferred" /><span><?php esc_html_e( 'A pilot is preferred but not essential', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="proof_of_commitment" value="pilot-not-required" /><span><?php esc_html_e( 'A pilot is not required', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="proof_of_commitment" value="no-preference" /><span><?php esc_html_e( 'Open to any', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="7" hidden>
                    <h4><?php esc_html_e( 'Budget', 'khm-membership' ); ?></h4>
                    <fieldset class="khm-card-picker-group">
                        <legend><?php esc_html_e( "What's your approximate annual budget?", 'khm-membership' ); ?></legend>
                        <div class="khm-card-picker khm-card-picker--wide" data-picker="budget_band">
                            <label class="khm-card-option"><input type="radio" name="budget_band" value="0-20000" /><span><?php esc_html_e( 'Under £20k', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="budget_band" value="20000-50000" /><span><?php esc_html_e( '£20k – £50k', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="budget_band" value="50000-150000" /><span><?php esc_html_e( '£50k – £150k', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="budget_band" value="150000-500000" /><span><?php esc_html_e( '£150k – £500k', 'khm-membership' ); ?></span></label>
                            <label class="khm-card-option"><input type="radio" name="budget_band" value="500000+" /><span><?php esc_html_e( '£500k+', 'khm-membership' ); ?></span></label>
                        </div>
                    </fieldset>
                </section>

                <section class="khm-onboard-step" data-role="wizard-step" data-step="8" hidden>
                    <h4><?php esc_html_e( 'Review And Launch', 'khm-membership' ); ?></h4>
                    <?php if ( $opts['show_rfp'] ) : ?>
                        <div class="khm-onboard-priority-field">
                            <span class="khm-onboard-priority-label"><?php esc_html_e( 'Priority Order', 'khm-membership' ); ?></span>
                            <p class="khm-onboard-priority-help"><?php esc_html_e( 'Drag to rank what matters most.', 'khm-membership' ); ?></p>
                            <ul class="khm-priority-list" data-role="priority-list">
                                <li class="khm-priority-item" data-priority="sector" draggable="true"><?php esc_html_e( 'Sector fit', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="region" draggable="true"><?php esc_html_e( 'Region', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="integrations" draggable="true"><?php esc_html_e( 'Integrations', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="partner_posture" data-show-for="software hardware" draggable="true"><?php esc_html_e( 'Partner type', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="deployment_mode" data-show-for="software" draggable="true"><?php esc_html_e( 'Software deployment', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="onboarding_style" data-show-for="software" draggable="true"><?php esc_html_e( 'Software onboarding support', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="installation_preference" data-show-for="hardware" draggable="true"><?php esc_html_e( 'Hardware installation model', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="engagement_model" data-show-for="consultancy" draggable="true"><?php esc_html_e( 'Consultancy engagement model', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="team_preference" data-show-for="consultancy" draggable="true"><?php esc_html_e( 'Consultancy team profile', 'khm-membership' ); ?></li>
                                <li class="khm-priority-item" data-priority="proof_of_commitment" data-show-for="software hardware" draggable="true"><?php esc_html_e( 'Pilot preference', 'khm-membership' ); ?></li>
                            </ul>
                            <input type="hidden" data-filter="criteria_priority_order" value="sector,region,integrations,partner_posture,deployment_mode,onboarding_style,installation_preference,engagement_model,team_preference,proof_of_commitment" />
                        </div>
                    <?php endif; ?>
                    <div class="khm-onboard-review-grid">
                        <div><span><?php esc_html_e( 'Focus Area', 'khm-membership' ); ?></span><strong data-role="review-expertise"></strong></div>
                        <div><span><?php esc_html_e( 'Challenge', 'khm-membership' ); ?></span><strong data-role="review-problem"></strong></div>
                        <div><span><?php esc_html_e( 'Solutions to Explore', 'khm-membership' ); ?></span><strong data-role="review-solutions"></strong></div>
                        <div><span><?php esc_html_e( 'Sector', 'khm-membership' ); ?></span><strong data-role="review-sector"></strong></div>
                        <div><span><?php esc_html_e( 'Team Size', 'khm-membership' ); ?></span><strong data-role="review-company_size_band"></strong></div>
                        <div><span><?php esc_html_e( 'Region', 'khm-membership' ); ?></span><strong data-role="review-region"></strong></div>
                        <div><span><?php esc_html_e( 'Integrations', 'khm-membership' ); ?></span><strong data-role="review-integrations"></strong></div>
                        <div><span><?php esc_html_e( 'Deployment / Delivery', 'khm-membership' ); ?></span><strong data-role="review-delivery_model"></strong></div>
                        <div><span><?php esc_html_e( 'Engagement', 'khm-membership' ); ?></span><strong data-role="review-engagement_model"></strong></div>
                        <div><span><?php esc_html_e( 'Proof Before Commitment', 'khm-membership' ); ?></span><strong data-role="review-proof_of_commitment"></strong></div>
                        <div><span><?php esc_html_e( 'Budget', 'khm-membership' ); ?></span><strong data-role="review-budget_band"></strong></div>
                        <?php if ( $opts['show_rfp'] ) : ?>
                            <div><span><?php esc_html_e( 'Priority', 'khm-membership' ); ?></span><strong data-role="review-criteria_priority_order"></strong></div>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="khm-connect-filter-actions khm-onboard-actions">
                    <button type="button" class="khm-buyer-btn khm-buyer-btn-secondary" data-action="step-back"><?php esc_html_e( 'Back', 'khm-membership' ); ?></button>
                    <button type="button" class="khm-buyer-btn" data-action="step-next"><?php esc_html_e( 'Continue', 'khm-membership' ); ?></button>
                    <button type="button" class="khm-buyer-btn khm-buyer-btn-secondary" data-action="apply-filters" hidden><?php esc_html_e( 'Find Matches', 'khm-membership' ); ?></button>
                    <button type="button" class="khm-buyer-btn" data-action="save-search" hidden><?php esc_html_e( 'Save Search', 'khm-membership' ); ?></button>
                    <p class="khm-step-blocked-message" data-role="step-blocked-message" hidden></p>
                </div>
            </div>

            <div class="khm-connect-results" data-role="results-panel" hidden>
                <div class="khm-connect-toolbar">
                    <p data-role="status"></p>
                    <div class="khm-connect-sort-controls">
                        <span><?php esc_html_e( 'Sort by:', 'khm-membership' ); ?></span>
                        <button type="button" class="khm-sort-btn is-active" data-sort="score"><?php esc_html_e( 'Score', 'khm-membership' ); ?></button>
                        <button type="button" class="khm-sort-btn" data-sort="price"><?php esc_html_e( 'Price', 'khm-membership' ); ?></button>
                    </div>
                </div>
                <table class="khm-provider-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Provider', 'khm-membership' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'khm-membership' ); ?></th>
                            <th><?php esc_html_e( 'Expertise', 'khm-membership' ); ?></th>
                            <th class="khm-col-sortable" data-sort-col="price"><?php esc_html_e( 'Budget', 'khm-membership' ); ?></th>
                            <th class="khm-col-sortable" data-sort-col="score"><?php esc_html_e( 'Score', 'khm-membership' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
                        </tr>
                    </thead>
                    <tbody data-role="cards"></tbody>
                </table>
            </div>

            <?php if ( $opts['show_compare'] ) : ?>
                <section class="khm-connect-compare" data-role="compare-drawer" hidden>
                    <div class="khm-connect-compare-head">
                        <h4><?php esc_html_e( 'Shortlist', 'khm-membership' ); ?></h4>
                        <button type="button" data-action="clear-compare"><?php esc_html_e( 'Clear', 'khm-membership' ); ?></button>
                    </div>
                    <div data-role="compare-items"></div>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function handle_team_settings_post(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( empty( $_POST['khm_buyer_hub_action'] ) || 'save_team_settings' !== sanitize_key( (string) $_POST['khm_buyer_hub_action'] ) ) {
            return;
        }

        if ( ! isset( $_POST['khm_buyer_hub_team_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['khm_buyer_hub_team_nonce'] ) ), 'khm_buyer_hub_team_settings' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $this->is_buyer_superuser( $user_id ) ) {
            return;
        }

        $raw      = isset( $_POST['khm_buyer_team_ids'] ) ? (string) wp_unslash( $_POST['khm_buyer_team_ids'] ) : '';
        $team_ids = $this->parse_user_ids_csv( $raw );

        update_user_meta( $user_id, self::TEAM_META_KEY, $team_ids );
    }

    private function parse_user_ids_csv( string $raw ): array {
        $parts = array_filter( array_map( 'trim', explode( ',', $raw ) ), static fn( $v ) => $v !== '' );
        $ids   = [];
        foreach ( $parts as $part ) {
            $id = (int) $part;
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }

        return array_values( array_unique( $ids ) );
    }

    private function get_team_user_ids_for_owner( int $user_id ): array {
        $raw = get_user_meta( $user_id, self::TEAM_META_KEY, true );

        if ( is_array( $raw ) ) {
            $ids = array_map( 'intval', $raw );
            return array_values( array_filter( $ids, static fn( $id ) => $id > 0 ) );
        }

        if ( is_string( $raw ) && $raw !== '' ) {
            return $this->parse_user_ids_csv( $raw );
        }

        return [];
    }

    private function is_buyer_superuser( int $user_id ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        $meta_flag = get_user_meta( $user_id, 'khm_buyer_superuser', true );
        $by_meta   = in_array( strtolower( (string) $meta_flag ), [ '1', 'true', 'yes', 'on' ], true );

        return current_user_can( 'manage_options' )
            || $by_meta
            || (bool) apply_filters( 'khm_buyer_hub_is_superuser', false, $user_id );
    }

    private function count_active_requests_for_user( int $user_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'connect_opportunities';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE buyer_account_id = %d
                   AND opportunity_status NOT IN ('closed', 'rejected', 'cancelled')",
                $user_id
            )
        );
    }
}

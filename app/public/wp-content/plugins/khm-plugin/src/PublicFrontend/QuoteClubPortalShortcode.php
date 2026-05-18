<?php
/**
 * Quote Club Portal Shortcode
 *
 * Registers [khm_quote_club_portal] which renders the dedicated sponsor-facing
 * Quote Club experience. Completely separate from [khm_member_portal]; uses
 * SponsorService for access gating and has its own navigation and layout.
 *
 * Navigation sections:
 *   overview       — sponsor content dashboard (articles, prospects, requests, analytics)
 *   commentary     — rolling-calendar search & commentary submission
 *   press-releases — press release composer (Phase 5)
 *   tracking       — article & PR performance dashboard (Phase 6)
 *   social         — LinkedIn & scheduling (Phase 7)
 *
 * @package KHM\PublicFrontend
 */
namespace KHM\PublicFrontend;
use KHM\Connect\ConnectEngagedSettingsPage;
use KHM\Services\CreditService;
use KHM\Services\MembershipRepository;
use KHM\Services\LevelRepository;
use KHM\Services\SponsorService;
use KHM\Services\QuoteClubCreditBundleService;
defined( 'ABSPATH' ) || exit;
class QuoteClubPortalShortcode {
	private CreditService $credits;
	private QuoteClubCreditBundleService $bundles;
	private MembershipRepository $memberships;
	private LevelRepository $levels;
	public function __construct() {
		$this->memberships = new MembershipRepository();
		$this->levels      = new LevelRepository();
		$this->credits     = new CreditService( $this->memberships, $this->levels );
		$this->bundles     = new QuoteClubCreditBundleService( $this->credits );
	}
	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------
	public function register(): void {
		add_shortcode( 'khm_quote_club_portal', [ $this, 'render' ] );
		// S17 — serve a sponsor advert in any page/template.
		add_shortcode( 'khm_sponsor_advert', [ $this, 'render_sponsor_advert_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		// Swap to dedicated full-width portal template so the page bypasses all theme chrome.
		add_filter( 'template_include', [ $this, 'portal_template_include' ] );
		// Keep body class for backward-compatible CSS targeting.
		add_filter( 'body_class', function( $classes ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'khm_quote_club_portal' ) ) {
				$classes[] = 'khm-partner-portal-page';
			}
			return $classes;
		} );
	}
	/**
	 * Swap in the plugin's standalone portal template whenever the current page
	 * contains [khm_quote_club_portal], replacing all theme chrome with a
	 * minimal full-width wrapper.
	 *
	 * @param string $template Current resolved template path.
	 * @return string
	 */
	public function portal_template_include( string $template ): string {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'khm_quote_club_portal' ) ) {
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
		if ( ! $post || ! has_shortcode( $post->post_content, 'khm_quote_club_portal' ) ) {
			return;
		}
		$plugin_url  = plugin_dir_url( dirname( __DIR__ ) );
		$plugin_path = plugin_dir_path( dirname( __DIR__ ) );
		wp_enqueue_style(
			'khm-quote-club-portal',
			$plugin_url . 'assets/css/quote-club-portal.css',
			[],
			file_exists( $plugin_path . 'assets/css/quote-club-portal.css' )
				? filemtime( $plugin_path . 'assets/css/quote-club-portal.css' )
				: '1'
		);
		// Reuse existing quote-club.css as base
		wp_enqueue_style(
			'khm-quote-club',
			$plugin_url . 'assets/css/quote-club.css',
			[ 'khm-quote-club-portal' ],
			file_exists( $plugin_path . 'assets/css/quote-club.css' )
				? filemtime( $plugin_path . 'assets/css/quote-club.css' )
				: '1'
		);
		// Partner portal modular CSS files (split from quote-club-portal.css)
		$partner_css_files = [
			'partner-portal-components'    => 'assets/css/partner-portal-components.css',
			'partner-portal-overview'      => 'assets/css/partner-portal-overview.css',
			'partner-portal-commentary'    => 'assets/css/partner-portal-commentary.css',
			'partner-portal-press-releases'=> 'assets/css/partner-portal-press-releases.css',
			'partner-portal-tracking'      => 'assets/css/partner-portal-tracking.css',
			'partner-portal-social'        => 'assets/css/partner-portal-social.css',
			'partner-portal-adverts'       => 'assets/css/partner-portal-adverts.css',
			'tabs/sponsor-account'         => 'assets/css/tabs/sponsor-account.css',
			'tabs/sponsor-connect'         => 'assets/css/tabs/sponsor-connect.css',
		];
		foreach ( $partner_css_files as $handle => $rel_path ) {
			$file = $plugin_path . $rel_path;
			if ( file_exists( $file ) ) {
				wp_enqueue_style(
					'khm-' . $handle,
					$plugin_url . $rel_path,
					[ 'khm-quote-club-portal' ],
					filemtime( $file )
				);
			}
		}
		wp_enqueue_script(
			'khm-quote-club',
			$plugin_url . 'assets/js/quote-club.js',
			[ 'jquery' ],
			file_exists( $plugin_path . 'assets/js/quote-club.js' )
				? filemtime( $plugin_path . 'assets/js/quote-club.js' )
				: '1',
			true
		);
		wp_enqueue_script(
			'khm-quote-club-connect',
			$plugin_url . 'assets/js/quote-club-connect.js',
			[ 'jquery', 'khm-quote-club' ],
			file_exists( $plugin_path . 'assets/js/quote-club-connect.js' )
				? filemtime( $plugin_path . 'assets/js/quote-club-connect.js' )
				: '1',
			true
		);
		wp_enqueue_script(
			'khm-connect-subscription-modal',
			$plugin_url . 'assets/js/connect-subscription-modal.js',
			[],
			file_exists( $plugin_path . 'assets/js/connect-subscription-modal.js' )
				? filemtime( $plugin_path . 'assets/js/connect-subscription-modal.js' )
				: '1',
			true
		);
		$user_id = get_current_user_id();
		$sponsor = SponsorService::get_user_sponsor( $user_id );
		wp_localize_script( 'khm-quote-club', 'khmQuoteClub', [
			'restUrl'       => esc_url_raw( rest_url( 'khm/v1/portal/quoteclub/' ) ),
			'connectRestUrl'=> esc_url_raw( rest_url( 'khm/v1/connect/' ) ),
			'sponsorRestUrl'=> esc_url_raw( rest_url( 'khm/v1/sponsor/' ) ),
			'bundleRestUrl' => esc_url_raw( rest_url( 'khm/v1/portal/quoteclub/bundles' ) ),
			'portalUrl'     => esc_url_raw( get_permalink( $post ) ?: home_url( '/quote-club/' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'userId'        => $user_id,
			'currentUserName' => sanitize_text_field( (string) wp_get_current_user()->display_name ),
			'currentUserEmail' => sanitize_email( (string) wp_get_current_user()->user_email ),
			'sponsorId'     => isset( $sponsor['id'] ) ? (int) $sponsor['id'] : 0,
			'editorialCredits' => $this->credits->getEditorialCredits( $user_id ),
			'pressReleaseCredits' => $this->credits->getPressReleaseCredits( $user_id ),
			'inviteToken'   => sanitize_text_field( (string) ( $_GET['khm_sponsor_invite'] ?? '' ) ),
			'inviteEmail'   => sanitize_email( (string) ( $_GET['khm_sponsor_invite_email'] ?? '' ) ),
			'wordsPerCredit'=> 120,
			'availableCategories' => $this->get_top_line_categories(),
			'shareLinkedInBase' => esc_url_raw( add_query_arg( [ 'qc_section' => 'social', 'li_suggest_title' => '' ], get_permalink( get_queried_object_id() ) ?: home_url( '/quote-club/' ) ) ),
		] );
		// Inject engaged pricing config from DB for Connect portal JS.
		// Fallback keeps the portal working if engaged settings class is unavailable.
		$connect_config = [
			'engagedOptionTwoEnabled' => true,
			'optionOneLabel'          => 'Option 1: Fixed Fee',
			'optionTwoLabel'          => 'Option 2: Success Fee',
			'optionOnePriceDesc'      => '£1,500 one-off introduction fee',
			'optionTwoPriceDesc'      => '£375 listing fee + 15% commission on first-year contract value',
		];
		if ( class_exists( '\\KHM\\Connect\\ConnectEngagedSettingsPage' ) ) {
			$connect_config = ConnectEngagedSettingsPage::get_js_config();
		}
		wp_localize_script( 'khm-quote-club-connect', 'khmConnectConfig', $connect_config );
	}
	// -------------------------------------------------------------------------
	// Shortcode render
	// -------------------------------------------------------------------------
	public function render( array $atts = [] ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}
		$user_id = get_current_user_id();
		$sponsor = SponsorService::get_user_sponsor( $user_id );
		if ( ! $sponsor && ! current_user_can( 'manage_options' ) ) {
			return $this->render_access_denied();
		}
		$section = isset( $_GET['qc_section'] )
			? sanitize_key( $_GET['qc_section'] )
			: 'overview';
		ob_start();
		?>
		<div class="khm-partner-portal" data-user-id="<?php echo esc_attr( $user_id ); ?>">
			<?php $this->render_header( $user_id, $sponsor ); ?>
			<div class="khm-partner-portal-body">
				<?php $this->render_nav( $section ); ?>
				<div class="khm-partner-portal-content">
					<?php
					switch ( $section ) {
						case 'connect':
							$this->render_connect_section( $user_id, $sponsor );
							break;
						case 'commentary':
							$this->render_commentary_section( $user_id, $sponsor );
							break;
						case 'press-releases':
							$this->render_press_releases_section( $user_id, $sponsor );
							break;
						case 'tracking':
							$this->render_tracking_section( $user_id, $sponsor );
							break;
						case 'social':
							$this->render_social_section( $user_id, $sponsor );
							break;
						case 'account':
							$this->render_account_section( $user_id, $sponsor );
							break;
						case 'adverts':
							$this->render_adverts_section( $user_id, $sponsor );
							break;
						default:
							$this->render_overview_section( $user_id, $sponsor );
					}
					?>
				</div>
			</div>
			<div id="khm-partner-toast" class="khm-toast" role="status" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}
	// -------------------------------------------------------------------------
	// Header
	// -------------------------------------------------------------------------
	private function render_header( int $user_id, ?array $sponsor ): void {
		$user         = get_userdata( $user_id );
		$sponsor_name = isset( $sponsor['name'] ) ? sanitize_text_field( $sponsor['name'] ) : '';
		$credit_breakdown = $this->get_credit_breakdown( $user_id );
		$connected_sites  = $this->get_connected_sites_count( (int) ( $sponsor['id'] ?? 0 ) );
		$_use_demo_header = defined( 'KHM_PORTAL_DEMO' ) && KHM_PORTAL_DEMO;
		if ( $_use_demo_header || 0 === array_sum( $credit_breakdown ) ) {
			$credit_breakdown = [
				'editorial_monthly_remaining'   => 0,
				'editorial_purchased_remaining' => 3,
				'press_monthly_remaining'       => 2,
				'press_purchased_remaining'     => 1,
			];
		}
		?>
		<header class="khm-partner-header">
			<div class="khm-partner-header-identity">
				<div class="khm-partner-brand">
					<span class="khm-partner-brand-name">Quote Club</span>
					<?php if ( $sponsor_name ) : ?>
						<span class="khm-partner-sponsor-name"><?php echo esc_html( $sponsor_name ); ?></span>
					<?php endif; ?>
				</div>
				<div class="khm-partner-user-name">
					<?php echo esc_html( $user ? $user->display_name : '' ); ?>
				</div>
			</div>
			<div class="khm-partner-header-metrics" aria-label="<?php esc_attr_e( 'Portal summary metrics', 'khm-membership' ); ?>">
				<div class="khm-partner-header-metric">
					<span class="khm-partner-header-metric-value"><?php echo esc_html( number_format_i18n( (int) $credit_breakdown['editorial_monthly_remaining'] ) ); ?></span>
					<span class="khm-partner-header-metric-label"><?php esc_html_e( 'Monthly Editorial', 'khm-membership' ); ?></span>
				</div>
				<div class="khm-partner-header-metric">
					<span class="khm-partner-header-metric-value"><?php echo esc_html( number_format_i18n( (int) $credit_breakdown['editorial_purchased_remaining'] ) ); ?></span>
					<span class="khm-partner-header-metric-label"><?php esc_html_e( 'Purchased Editorial', 'khm-membership' ); ?></span>
				</div>
				<div class="khm-partner-header-metric">
					<span class="khm-partner-header-metric-value"><?php echo esc_html( number_format_i18n( (int) $credit_breakdown['press_monthly_remaining'] ) ); ?></span>
					<span class="khm-partner-header-metric-label"><?php esc_html_e( 'Monthly Press Releases', 'khm-membership' ); ?></span>
				</div>
				<div class="khm-partner-header-metric">
					<span class="khm-partner-header-metric-value"><?php echo esc_html( number_format_i18n( (int) $credit_breakdown['press_purchased_remaining'] ) ); ?></span>
					<span class="khm-partner-header-metric-label"><?php esc_html_e( 'Purchased Press Releases', 'khm-membership' ); ?></span>
				</div>
				<div class="khm-partner-header-metric">
					<span class="khm-partner-header-metric-value"><?php echo esc_html( number_format_i18n( $connected_sites ) ); ?></span>
					<span class="khm-partner-header-metric-label"><?php esc_html_e( 'Connected Sites', 'khm-membership' ); ?></span>
				</div>
			</div>
		</header>
		<?php
	}
	// -------------------------------------------------------------------------
	// Navigation
	// -------------------------------------------------------------------------
	private function render_nav( string $current_section ): void {
		$sections = [
			'overview'       => [ 'label' => __( 'Overview', 'khm-membership' ),       'icon' => 'dashicons-chart-bar' ],
			'connect'        => [ 'label' => __( 'Connect', 'khm-membership' ),        'icon' => 'dashicons-networking' ],
			'account'        => [ 'label' => __( 'Account', 'khm-membership' ),        'icon' => 'dashicons-admin-settings' ],
			'commentary'     => [ 'label' => __( 'Commentary', 'khm-membership' ),      'icon' => 'dashicons-format-quote' ],
			'press-releases' => [ 'label' => __( 'Press Releases', 'khm-membership' ),  'icon' => 'dashicons-media-document' ],
			'tracking'       => [ 'label' => __( 'Tracking', 'khm-membership' ),        'icon' => 'dashicons-chart-line' ],
			'social'         => [ 'label' => __( 'Social', 'khm-membership' ),          'icon' => 'dashicons-share' ],
			'adverts'        => [ 'label' => __( 'Adverts', 'khm-membership' ),         'icon' => 'dashicons-megaphone' ],
		];
		$sections = apply_filters( 'khm_qc_portal_sections', $sections );
		?>
		<nav class="khm-partner-nav" role="navigation" aria-label="<?php esc_attr_e( 'Quote Club sections', 'khm-membership' ); ?>">
			<?php foreach ( $sections as $slug => $section ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'qc_section', $slug ) ); ?>"
				   class="khm-partner-nav-item<?php echo $current_section === $slug ? ' is-active' : ''; ?>"
				   aria-current="<?php echo $current_section === $slug ? 'page' : 'false'; ?>">
					<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>"></span>
					<span><?php echo esc_html( $section['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
	// -------------------------------------------------------------------------
	// Sections
	// -------------------------------------------------------------------------
	private function render_overview_section( int $user_id, ?array $sponsor ): void {
		$sponsor_id        = (int) ( $sponsor['id'] ?? 0 );
		$team_user_ids     = $this->get_sponsor_team_user_ids( $user_id, $sponsor );
		$published_articles = $this->get_recent_published_articles( $team_user_ids, 5 );
		$scheduled_articles = $this->get_scheduled_articles( $team_user_ids, 5 );
		$suggested_summaries = $this->get_suggested_summaries( $sponsor_id, 5 );
		$potential_prospects = $this->get_potential_prospects( $sponsor_id, 5 );
		$meeting_requests    = $this->get_prospect_meeting_requests( $sponsor_id, 5 );
		$page_views_30       = $this->get_page_views_last_30_days( $team_user_ids );
		$smart_clicks_30     = $this->get_smart_link_clicks_last_30_days( $team_user_ids );
		$performance_series  = $this->get_performance_series_last_30_days( $team_user_ids );
		$smart_clicks_by_site = $this->get_smart_clicks_by_site_last_30_days( $team_user_ids, 5 );
		$views_by_maturity    = $this->get_estimated_views_by_user_maturity_last_30_days( $sponsor_id, $page_views_30 );
		$has_performance_signal = false;
		foreach ( (array) ( $performance_series['rows'] ?? [] ) as $point ) {
			if ( (int) ( $point['views'] ?? 0 ) > 0 || (int) ( $point['clicks'] ?? 0 ) > 0 ) {
				$has_performance_signal = true;
				break;
			}
		}
		// ── Demo mock data (remove once live data is flowing) ──────────────────
		$use_demo = defined( 'KHM_PORTAL_DEMO' ) && KHM_PORTAL_DEMO;
		if ( $use_demo || ! $has_performance_signal ) {
			$base = $use_demo ? 0 : 0; // always fill when no real data
			$mock_views  = [ 312, 278, 401, 360, 290, 415, 380, 452, 310, 375,
			                 420, 395, 480, 355, 302, 440, 390, 410, 365, 335,
			                 455, 428, 392, 378, 415, 460, 402, 385, 420, 445 ];
			$mock_clicks = [  28,  22,  35,  30,  19,  38,  31,  42,  25,  33,
			                   37,  29,  45,  27,  21,  40,  34,  36,  28,  24,
			                   41,  38,  32,  30,  35,  44,  36,  29,  38,  43 ];
			$max_v = max( $mock_views );
			$max_c = max( $mock_clicks );
			$start_ts = strtotime( '-29 days' );
			$rows = [];
			for ( $i = 0; $i < 30; $i++ ) {
				$ts = $start_ts + $i * DAY_IN_SECONDS;
				$rows[] = [
					'label'      => gmdate( 'M j', $ts ),
					'views'      => $mock_views[ $i ],
					'clicks'     => $mock_clicks[ $i ],
					'views_pct'  => (int) round( ( $mock_views[ $i ] / $max_v ) * 100 ),
					'clicks_pct' => (int) round( ( $mock_clicks[ $i ] / $max_c ) * 100 ),
				];
			}
			$performance_series = [
				'rows'        => $rows,
				'start_label' => $rows[0]['label'],
				'end_label'   => $rows[29]['label'],
			];
			if ( $use_demo || 0 === $page_views_30 ) {
				$page_views_30  = array_sum( $mock_views );
				$smart_clicks_30 = array_sum( $mock_clicks );
			}
		}
		if ( $use_demo || empty( $smart_clicks_by_site ) ) {
			$mock_sites = [
				[ 'label' => 'Flagship',      'value' => 214 ],
				[ 'label' => 'Aerospace',     'value' => 187 ],
				[ 'label' => 'Energy',        'value' => 143 ],
				[ 'label' => 'Industrial',    'value' =>  98 ],
				[ 'label' => 'Field Service', 'value' =>  62 ],
			];
			$max = $mock_sites[0]['value'];
			foreach ( $mock_sites as &$s ) {
				$s['pct'] = (int) round( ( $s['value'] / $max ) * 100 );
			}
			unset( $s );
			$smart_clicks_by_site = $mock_sites;
		}
		if ( $use_demo || empty( $views_by_maturity ) ) {
			$views_by_maturity = [
				[ 'label' => __( 'Accelerating', 'khm-membership' ), 'value' => 5820, 'pct' => 100 ],
				[ 'label' => __( 'Assessing', 'khm-membership' ),    'value' => 3410, 'pct' =>  59 ],
				[ 'label' => __( 'Exploring', 'khm-membership' ),    'value' => 1980, 'pct' =>  34 ],
			];
		}
		if ( $use_demo || count( $published_articles ) < 3 ) {
			$published_articles = [
				[ 'title' => 'AI-Driven Predictive Maintenance in Aerospace MRO', 'url' => '#', 'date' => 'Apr 28, 2026' ],
				[ 'title' => 'The Future of Smart Grid Management',               'url' => '#', 'date' => 'Apr 24, 2026' ],
				[ 'title' => 'Industrial IoT: Connecting the Factory Floor',      'url' => '#', 'date' => 'Apr 20, 2026' ],
				[ 'title' => 'Field Service Excellence with Mobile-First Tools',  'url' => '#', 'date' => 'Apr 15, 2026' ],
				[ 'title' => 'Spare Parts Optimisation in Aftermarket Services',  'url' => '#', 'date' => 'Apr 10, 2026' ],
			];
		}
		if ( $use_demo || empty( $scheduled_articles ) ) {
			$scheduled_articles = [
				[ 'title' => 'Unlocking Revenue with Dynamic Pricing Models',   'url' => '#', 'date' => 'May 5, 2026' ],
				[ 'title' => 'Digital Twins in Built Environment Projects',      'url' => '#', 'date' => 'May 12, 2026' ],
				[ 'title' => 'eCommerce Integration for B2B Manufacturers',      'url' => '#', 'date' => 'May 19, 2026' ],
			];
		}
		if ( $use_demo || count( $suggested_summaries ) < 2 ) {
			$suggested_summaries = [
				[ 'title' => 'Benchmark: After-Sales Revenue in Capital Equipment', 'url' => '#', 'summary' => 'Industry leaders generate 40–60% of revenue post-sale. See where you sit.', 'date' => 'Apr 30, 2026' ],
				[ 'title' => 'State of Field Service 2026',                         'url' => '#', 'summary' => 'New data on first-time fix rates, mobile adoption, and customer satisfaction benchmarks.', 'date' => 'Apr 22, 2026' ],
				[ 'title' => 'Pricing Transformation in Industrial Markets',         'url' => '#', 'summary' => 'How top performers are moving from cost-plus to value-based pricing.', 'date' => 'Apr 17, 2026' ],
			];
		}
		if ( $use_demo || empty( $potential_prospects ) ) {
			$potential_prospects = [
				[ 'domain' => 'Siemens Energy',        'meta' => 'Energy · 10,000+ employees' ],
				[ 'domain' => 'BAE Systems',           'meta' => 'Aerospace & Defence · 90,000+ employees' ],
				[ 'domain' => 'Schneider Electric',    'meta' => 'Industrial Automation · 150,000+ employees' ],
				[ 'domain' => 'Rentokil Initial',      'meta' => 'Field Services · 57,000+ employees' ],
				[ 'domain' => 'SKF Group',             'meta' => 'Aftermarket · 40,000+ employees' ],
			];
		}
		if ( $use_demo || empty( $meeting_requests ) ) {
			$meeting_requests = [
				[ 'provider' => 'Emerson Electric',            'meta' => 'Requested via Connect · Apr 29, 2026' ],
				[ 'provider' => 'Mitsubishi Heavy Industries', 'meta' => 'Requested via Connect · Apr 27, 2026' ],
			];
		}
		// KPI totals — independent fallback so they never show 0 on demo
		if ( $use_demo || 0 === $page_views_30 ) {
			$page_views_30   = 11210;
		}
		if ( $use_demo || 0 === $smart_clicks_30 ) {
			$smart_clicks_30 = 984;
		}
		// ── End demo mock data ─────────────────────────────────────────────────
		?>
		<div class="khm-partner-section khm-partner-overview">
			<h2><?php esc_html_e( 'Overview', 'khm-membership' ); ?></h2>
			<div class="khm-partner-dashboard-kpis">
				<div class="khm-partner-dashboard-kpi">
					<span class="khm-partner-dashboard-kpi-value"><?php echo esc_html( number_format_i18n( $page_views_30 ) ); ?></span>
					<span class="khm-partner-dashboard-kpi-label"><?php esc_html_e( 'Page views (last 30 days)', 'khm-membership' ); ?></span>
				</div>
				<div class="khm-partner-dashboard-kpi">
					<span class="khm-partner-dashboard-kpi-value"><?php echo esc_html( number_format_i18n( $smart_clicks_30 ) ); ?></span>
					<span class="khm-partner-dashboard-kpi-label"><?php esc_html_e( 'Smart Link clicks (last 30 days)', 'khm-membership' ); ?></span>
				</div>
			</div>
			<section class="khm-partner-dashboard-card khm-partner-dashboard-chart-card">
				<h3><?php esc_html_e( 'Performance (Last 30 Days)', 'khm-membership' ); ?></h3>
				<div class="khm-partner-dashboard-chart-legend">
					<span><i class="khm-partner-chart-dot khm-partner-chart-dot-views"></i><?php esc_html_e( 'Page Views', 'khm-membership' ); ?></span>
					<span><i class="khm-partner-chart-dot khm-partner-chart-dot-clicks"></i><?php esc_html_e( 'Smart Link Clicks', 'khm-membership' ); ?></span>
				</div>
				<?php if ( empty( $performance_series['rows'] ) ) : ?>
					<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No performance data yet for the last 30 days.', 'khm-membership' ); ?></p>
				<?php else : ?>
				<div class="khm-partner-dashboard-chart-bars">
					<?php foreach ( $performance_series['rows'] as $point ) : ?>
					<div class="khm-partner-dashboard-chart-day" title="<?php echo esc_attr( sprintf( '%1$s: %2$s views / %3$s clicks', $point['label'], number_format_i18n( $point['views'] ), number_format_i18n( $point['clicks'] ) ) ); ?>">
						<div class="khm-partner-dashboard-chart-col">
							<span class="khm-partner-chart-bar khm-partner-chart-bar-views" style="height:<?php echo esc_attr( (int) round( $point['views_pct'] * 1.2 ) ); ?>px"></span>
							<span class="khm-partner-chart-bar khm-partner-chart-bar-clicks" style="height:<?php echo esc_attr( (int) round( $point['clicks_pct'] * 1.2 ) ); ?>px"></span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="khm-partner-dashboard-chart-axis">
					<span><?php echo esc_html( $performance_series['start_label'] ); ?></span>
					<span><?php echo esc_html( $performance_series['end_label'] ); ?></span>
				</div>
				<?php endif; ?>
			</section>
			<div class="khm-partner-dashboard-grid">
				<section class="khm-partner-dashboard-card">
					<h3><?php esc_html_e( 'Smart Clicks by Site (30d)', 'khm-membership' ); ?></h3>
					<?php if ( empty( $smart_clicks_by_site ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No smart-click site data yet.', 'khm-membership' ); ?></p>
					<?php else : ?>
					<ul class="khm-partner-breakdown-list">
						<?php foreach ( $smart_clicks_by_site as $row ) : ?>
						<li>
							<div class="khm-partner-breakdown-row">
								<span class="khm-partner-breakdown-label"><?php echo esc_html( $row['label'] ); ?></span>
								<span class="khm-partner-breakdown-value"><?php echo esc_html( number_format_i18n( $row['value'] ) ); ?></span>
							</div>
							<div class="khm-partner-breakdown-bar"><span style="width:<?php echo esc_attr( $row['pct'] ); ?>%"></span></div>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</section>
				<section class="khm-partner-dashboard-card">
					<h3><?php esc_html_e( 'Views by User Maturity (30d)', 'khm-membership' ); ?></h3>
					<?php if ( empty( $views_by_maturity ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No maturity segmentation data yet.', 'khm-membership' ); ?></p>
					<?php else :
						$_pie_colors = [ '#1a56db', '#16a34a', '#d97706' ];
						$_pie_total  = array_sum( array_column( $views_by_maturity, 'value' ) );
						$_pie_stops  = [];
						$_cumul      = 0;
						foreach ( $views_by_maturity as $_i => $_row ) {
							$_share         = $_pie_total > 0 ? (int) round( ( (int) $_row['value'] / $_pie_total ) * 100 ) : 0;
							$_color         = $_pie_colors[ $_i % count( $_pie_colors ) ];
							$_pie_stops[]   = [ 'label' => $_row['label'], 'color' => $_color, 'from' => $_cumul, 'to' => $_cumul + $_share, 'share' => $_share ];
							$_cumul        += $_share;
						}
						$_gradient_parts = [];
						foreach ( $_pie_stops as $_stop ) {
							$_gradient_parts[] = $_stop['color'] . ' ' . $_stop['from'] . '% ' . $_stop['to'] . '%';
						}
						$_gradient = 'conic-gradient(' . implode( ', ', $_gradient_parts ) . ')';
					?>
					<div class="khm-partner-pie-wrap">
						<div class="khm-partner-pie" style="background:<?php echo esc_attr( $_gradient ); ?>"></div>
						<ul class="khm-partner-pie-legend">
							<?php foreach ( $_pie_stops as $_stop ) : ?>
							<li>
								<span class="khm-partner-pie-swatch" style="background:<?php echo esc_attr( $_stop['color'] ); ?>"></span>
								<span class="khm-partner-pie-legend-label"><?php echo esc_html( $_stop['label'] ); ?></span>
								<span class="khm-partner-pie-legend-pct"><?php echo esc_html( $_stop['share'] ); ?>%</span>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<p class="khm-partner-dashboard-footnote"><?php esc_html_e( 'Estimated from 30-day maturity signal mix.', 'khm-membership' ); ?></p>
					<?php endif; ?>
				</section>
				<section class="khm-partner-dashboard-card">
					<h3><?php esc_html_e( 'Last Five Published Articles', 'khm-membership' ); ?></h3>
					<?php if ( empty( $published_articles ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No published articles yet.', 'khm-membership' ); ?></p>
					<?php else : ?>
					<ul class="khm-partner-dashboard-list">
						<?php foreach ( $published_articles as $article ) : ?>
						<li>
							<a href="<?php echo esc_url( $article['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $article['title'] ); ?></a>
							<span><?php echo esc_html( $article['date'] ); ?></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</section>
				<section class="khm-partner-dashboard-card">
					<h3><?php esc_html_e( 'Your Scheduled Articles', 'khm-membership' ); ?></h3>
					<?php if ( empty( $scheduled_articles ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No scheduled articles right now.', 'khm-membership' ); ?></p>
					<?php else : ?>
					<ul class="khm-partner-dashboard-list">
						<?php foreach ( $scheduled_articles as $article ) : ?>
						<li>
							<a href="<?php echo esc_url( $article['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $article['title'] ); ?></a>
							<span><?php echo esc_html( $article['date'] ); ?></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</section>
				<section class="khm-partner-dashboard-card khm-partner-dashboard-card-span-2">
					<h3><?php esc_html_e( 'Suggested Upcoming Summaries', 'khm-membership' ); ?></h3>
					<?php if ( empty( $suggested_summaries ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No summary recommendations found yet.', 'khm-membership' ); ?></p>
					<?php else : ?>
					<ul class="khm-partner-dashboard-list khm-partner-dashboard-summary-list">
						<?php foreach ( $suggested_summaries as $summary ) : ?>
						<li>
							<div>
								<a href="<?php echo esc_url( $summary['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $summary['title'] ); ?></a>
								<p><?php echo esc_html( $summary['summary'] ); ?></p>
							</div>
							<span><?php echo esc_html( $summary['date'] ); ?></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</section>
				<section class="khm-partner-dashboard-card">
					<h3><?php esc_html_e( 'Potential Prospects Available', 'khm-membership' ); ?></h3>
					<?php if ( empty( $potential_prospects ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No prospects currently available.', 'khm-membership' ); ?></p>
					<?php else : ?>
					<ul class="khm-partner-dashboard-list">
						<?php foreach ( $potential_prospects as $prospect ) : ?>
						<li>
							<strong><?php echo esc_html( $prospect['domain'] ); ?></strong>
							<span><?php echo esc_html( $prospect['meta'] ); ?></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</section>
				<section class="khm-partner-dashboard-card">
					<h3><?php esc_html_e( 'Prospect Meeting Requests', 'khm-membership' ); ?></h3>
					<?php if ( empty( $meeting_requests ) ) : ?>
						<p class="khm-partner-dashboard-empty"><?php esc_html_e( 'No meeting requests pending.', 'khm-membership' ); ?></p>
					<?php else : ?>
					<ul class="khm-partner-dashboard-list">
						<?php foreach ( $meeting_requests as $request ) : ?>
						<li>
							<strong><?php echo esc_html( $request['provider'] ); ?></strong>
							<span><?php echo esc_html( $request['meta'] ); ?></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}
	private function get_sponsor_team_user_ids( int $user_id, ?array $sponsor ): array {
		$user_ids = [ $user_id ];
		if ( is_array( $sponsor ) ) {
			$team_members = json_decode( (string) ( $sponsor['team_members'] ?? '' ), true );
			if ( is_array( $team_members ) ) {
				foreach ( $team_members as $member ) {
					$member_user_id = (int) ( $member['user_id'] ?? 0 );
					if ( $member_user_id > 0 ) {
						$user_ids[] = $member_user_id;
					}
				}
			}
		}
		return array_values( array_unique( array_map( 'intval', $user_ids ) ) );
	}
	private function get_credit_breakdown( int $user_id ): array {
		global $wpdb;
		$current_month = date( 'Y-m' );
		$table = $wpdb->prefix . 'khm_user_credits';
		$defaults = [
			'editorial_monthly_remaining'   => 0,
			'editorial_purchased_remaining' => 0,
			'press_monthly_remaining'       => 0,
			'press_purchased_remaining'     => 0,
		];
		if ( ! $this->table_exists( $table ) ) {
			return $defaults;
		}
		$this->credits->allocateMonthlyEditorialCredits( $user_id );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT editorial_allocated_credits, editorial_bonus_credits, press_release_credits, press_release_credits_used
				 FROM {$table}
				 WHERE user_id = %d AND allocation_month = %s LIMIT 1",
				$user_id,
				$current_month
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return $defaults;
		}
		$editorial_monthly_remaining   = max( 0, (int) ( $row['editorial_allocated_credits'] ?? 0 ) );
		$editorial_purchased_remaining = max( 0, (int) ( $row['editorial_bonus_credits'] ?? 0 ) );
		$press_total_remaining = max(
			0,
			(int) ( $row['press_release_credits'] ?? 0 ) - (int) ( $row['press_release_credits_used'] ?? 0 )
		);
		$monthly_press_quota = $this->get_press_release_monthly_quota( $user_id );
		$monthly_press_pool  = min( (int) ( $row['press_release_credits'] ?? 0 ), $monthly_press_quota );
		$monthly_press_used  = min( (int) ( $row['press_release_credits_used'] ?? 0 ), $monthly_press_pool );
		$press_monthly_remaining = max( 0, $monthly_press_pool - $monthly_press_used );
		$press_purchased_remaining = max( 0, $press_total_remaining - $press_monthly_remaining );
		return [
			'editorial_monthly_remaining'   => $editorial_monthly_remaining,
			'editorial_purchased_remaining' => $editorial_purchased_remaining,
			'press_monthly_remaining'       => $press_monthly_remaining,
			'press_purchased_remaining'     => $press_purchased_remaining,
		];
	}
	private function get_press_release_monthly_quota( int $user_id ): int {
		$memberships = $this->memberships->findActive( $user_id );
		if ( empty( $memberships ) ) {
			return 0;
		}
		$level_id = (int) $memberships[0]->membership_id;
		$quota = (int) $this->levels->getMeta( $level_id, 'qc_press_release_credits_monthly', 1 );
		return max( 0, (int) apply_filters( 'khm_press_release_credits_monthly_quota', $quota, $user_id ) );
	}
	private function get_connected_sites_count( int $sponsor_id ): int {
		if ( $sponsor_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'connect_providers';
		if ( ! $this->table_exists( $table ) ) {
			return 0;
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT blog_id)
				 FROM {$table}
				 WHERE sponsor_id = %d
				   AND status = %s
				   AND blog_id > 1",
				$sponsor_id,
				'active'
			)
		);
	}
	private function get_performance_series_last_30_days( array $author_ids ): array {
		$rows = [];
		$today = current_time( 'timestamp' );
		for ( $i = 29; $i >= 0; $i-- ) {
			$ts = strtotime( '-' . $i . ' days', $today );
			$key = gmdate( 'Y-m-d', $ts );
			$rows[ $key ] = [
				'label'      => wp_date( 'M j', $ts ),
				'views'      => 0,
				'clicks'     => 0,
				'views_pct'  => 0,
				'clicks_pct' => 0,
			];
		}
		$views_by_day  = $this->get_page_views_daily_last_30_days( $author_ids );
		$clicks_by_day = $this->get_smart_link_clicks_daily_last_30_days( $author_ids );
		$max = 0;
		foreach ( $rows as $day => &$point ) {
			$point['views']  = (int) ( $views_by_day[ $day ] ?? 0 );
			$point['clicks'] = (int) ( $clicks_by_day[ $day ] ?? 0 );
			$max = max( $max, $point['views'], $point['clicks'] );
		}
		unset( $point );
		$max = max( 1, $max );
		foreach ( $rows as &$point ) {
			$point['views_pct']  = (int) round( ( $point['views'] / $max ) * 100 );
			$point['clicks_pct'] = (int) round( ( $point['clicks'] / $max ) * 100 );
		}
		unset( $point );
		$indexed = array_values( $rows );
		return [
			'rows'        => $indexed,
			'start_label' => $indexed[0]['label'] ?? '',
			'end_label'   => $indexed[ count( $indexed ) - 1 ]['label'] ?? '',
		];
	}
	private function get_page_views_daily_last_30_days( array $author_ids ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_seo_metrics';
		if ( empty( $author_ids ) || ! $this->table_exists( $table ) ) {
			return [];
		}
		$author_placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$sql =
			"SELECT DATE(measurement_date) AS metric_day, COALESCE(SUM(CAST(metric_value AS UNSIGNED)), 0) AS total
			 FROM {$table}
			 WHERE metric_name IN ('page_views', 'screenPageViews')
			   AND measurement_date >= %s
			   AND post_id IN (
				   SELECT ID FROM {$wpdb->posts}
				   WHERE post_author IN ({$author_placeholders})
				     AND post_status = 'publish'
				     AND post_type IN ('post', 'atomic_article')
			   )
			 GROUP BY DATE(measurement_date)";
		$params = array_merge( [ $since ], $author_ids );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$out = [];
		foreach ( $rows as $row ) {
			$day = (string) ( $row['metric_day'] ?? '' );
			if ( '' !== $day ) {
				$out[ $day ] = (int) ( $row['total'] ?? 0 );
			}
		}
		return $out;
	}
	private function get_smart_link_clicks_daily_last_30_days( array $author_ids ): array {
		global $wpdb;
		$redirects_table = $wpdb->prefix . 'geo_redirects';
		$clicks_table    = $wpdb->prefix . 'geo_redirect_clicks';
		if ( empty( $author_ids ) || ! $this->table_exists( $redirects_table ) || ! $this->table_exists( $clicks_table ) ) {
			return [];
		}
		$author_placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$sql =
			"SELECT DATE(c.clicked_at) AS metric_day, COUNT(1) AS total
			 FROM {$clicks_table} c
			 INNER JOIN {$redirects_table} r ON r.id = c.redirect_id
			 WHERE r.created_by IN ({$author_placeholders})
			   AND c.clicked_at >= %s
			 GROUP BY DATE(c.clicked_at)";
		$params = array_merge( $author_ids, [ $since ] );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$out = [];
		foreach ( $rows as $row ) {
			$day = (string) ( $row['metric_day'] ?? '' );
			if ( '' !== $day ) {
				$out[ $day ] = (int) ( $row['total'] ?? 0 );
			}
		}
		return $out;
	}
	private function get_smart_clicks_by_site_last_30_days( array $author_ids, int $limit = 5 ): array {
		global $wpdb;
		$redirects_table = $wpdb->prefix . 'geo_redirects';
		$clicks_table    = $wpdb->prefix . 'geo_redirect_clicks';
		if ( empty( $author_ids ) || ! $this->table_exists( $redirects_table ) || ! $this->table_exists( $clicks_table ) ) {
			return [];
		}
		$author_placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$sql =
			"SELECT r.target_url, COUNT(1) AS total
			 FROM {$clicks_table} c
			 INNER JOIN {$redirects_table} r ON r.id = c.redirect_id
			 WHERE r.created_by IN ({$author_placeholders})
			   AND c.clicked_at >= %s
			 GROUP BY r.target_url
			 ORDER BY total DESC
			 LIMIT 250";
		$params = array_merge( $author_ids, [ $since ] );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [];
		}
		$by_host = [];
		foreach ( $rows as $row ) {
			$url = (string) ( $row['target_url'] ?? '' );
			$host = wp_parse_url( $url, PHP_URL_HOST );
			$label = is_string( $host ) && '' !== $host ? preg_replace( '/^www\./i', '', $host ) : __( 'Unknown site', 'khm-membership' );
			if ( ! isset( $by_host[ $label ] ) ) {
				$by_host[ $label ] = 0;
			}
			$by_host[ $label ] += (int) ( $row['total'] ?? 0 );
		}
		arsort( $by_host );
		$top = array_slice( $by_host, 0, max( 1, $limit ), true );
		$max = max( 1, (int) ( reset( $top ) ?: 1 ) );
		$out = [];
		foreach ( $top as $label => $value ) {
			$out[] = [
				'label' => (string) $label,
				'value' => (int) $value,
				'pct'   => (int) round( ( (int) $value / $max ) * 100 ),
			];
		}
		return $out;
	}
	private function get_estimated_views_by_user_maturity_last_30_days( int $sponsor_id, int $total_views ): array {
		if ( $sponsor_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$table = \KHM\Migrations\ConnectWorkflowMigration::opportunities_table_name();
		if ( ! $this->table_exists( $table ) ) {
			return [];
		}
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT commercial_tier, COUNT(1) AS total
				 FROM {$table}
				 WHERE (sponsor_id = %d OR sponsor_id IS NULL)
				   AND created_at >= %s
				 GROUP BY commercial_tier",
				$sponsor_id,
				$since
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [];
		}
		$tier_counts = [
			'premium'     => 0,
			'standard'    => 0,
			'exploratory' => 0,
		];
		foreach ( $rows as $row ) {
			$tier = sanitize_key( (string) ( $row['commercial_tier'] ?? '' ) );
			if ( isset( $tier_counts[ $tier ] ) ) {
				$tier_counts[ $tier ] += (int) ( $row['total'] ?? 0 );
			}
		}
		$total_signals = array_sum( $tier_counts );
		if ( $total_signals <= 0 ) {
			return [];
		}
		$max = 1;
		$out = [];
		$labels = [
			'premium'     => __( 'Accelerating', 'khm-membership' ),
			'standard'    => __( 'Assessing', 'khm-membership' ),
			'exploratory' => __( 'Exploring', 'khm-membership' ),
		];
		foreach ( [ 'premium', 'standard', 'exploratory' ] as $tier ) {
			$estimated = (int) round( ( $tier_counts[ $tier ] / $total_signals ) * max( 0, $total_views ) );
			$max = max( $max, $estimated );
			$out[] = [
				'label' => $labels[ $tier ],
				'value' => $estimated,
				'pct'   => 0,
			];
		}
		foreach ( $out as &$row ) {
			$row['pct'] = (int) round( ( (int) $row['value'] / $max ) * 100 );
		}
		unset( $row );
		return $out;
	}
	private function get_recent_published_articles( array $author_ids, int $limit = 5 ): array {
		$query = new \WP_Query([
			'post_type'           => [ 'post', 'atomic_article' ],
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $limit ),
			'author__in'          => $author_ids,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		]);
		if ( ! $query->have_posts() ) {
			return [];
		}
		$articles = [];
		foreach ( $query->posts as $post ) {
			$articles[] = [
				'title' => get_the_title( $post ) ?: __( '(Untitled)', 'khm-membership' ),
				'url'   => get_permalink( $post ) ?: '#',
				'date'  => wp_date( get_option( 'date_format' ), strtotime( (string) $post->post_date ) ),
			];
		}
		return $articles;
	}
	private function get_scheduled_articles( array $author_ids, int $limit = 5 ): array {
		$query = new \WP_Query([
			'post_type'      => [ 'post', 'atomic_article' ],
			'post_status'    => 'future',
			'posts_per_page' => max( 1, $limit ),
			'author__in'     => $author_ids,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		]);
		if ( ! $query->have_posts() ) {
			return [];
		}
		$articles = [];
		foreach ( $query->posts as $post ) {
			$articles[] = [
				'title' => get_the_title( $post ) ?: __( '(Untitled)', 'khm-membership' ),
				'url'   => get_preview_post_link( $post ) ?: '#',
				'date'  => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $post->post_date ) ),
			];
		}
		return $articles;
	}
	private function get_suggested_summaries( int $sponsor_id, int $limit = 5 ): array {
		global $wpdb;
		$category_slugs = [];
		$providers_table = $wpdb->prefix . 'connect_providers';
		if ( $sponsor_id > 0 && $this->table_exists( $providers_table ) ) {
			$titles_rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT titles FROM {$providers_table} WHERE sponsor_id = %d AND status = %s",
					$sponsor_id,
					'active'
				)
			);
			if ( is_array( $titles_rows ) ) {
				foreach ( $titles_rows as $row ) {
					$titles = json_decode( (string) $row, true );
					if ( is_array( $titles ) ) {
						foreach ( $titles as $title_slug ) {
							$normalized = sanitize_title( (string) $title_slug );
							if ( '' !== $normalized ) {
								$category_slugs[] = $normalized;
							}
						}
					}
				}
			}
		}
		$category_slugs = array_values( array_unique( $category_slugs ) );
		$args = [
			'post_type'      => [ 'post', 'atomic_article' ],
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, $limit ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];
		if ( ! empty( $category_slugs ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => $category_slugs,
				],
			];
		}
		$query = new \WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return [];
		}
		$summaries = [];
		foreach ( $query->posts as $post ) {
			$summary_text = get_the_excerpt( $post );
			if ( '' === trim( (string) $summary_text ) ) {
				$summary_text = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 24, '…' );
			}
			$summaries[] = [
				'title'   => get_the_title( $post ) ?: __( '(Untitled)', 'khm-membership' ),
				'url'     => get_permalink( $post ) ?: '#',
				'date'    => wp_date( get_option( 'date_format' ), strtotime( (string) $post->post_date ) ),
				'summary' => wp_trim_words( wp_strip_all_tags( (string) $summary_text ), 28, '…' ),
			];
		}
		return $summaries;
	}
	private function get_potential_prospects( int $sponsor_id, int $limit = 5 ): array {
		if ( $sponsor_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$table = \KHM\Migrations\ConnectWorkflowMigration::opportunities_table_name();
		if ( ! $this->table_exists( $table ) ) {
			return [];
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT actor_email_domain, commercial_tier, internal_stage, person_score
				 FROM {$table}
				 WHERE ( sponsor_id = %d OR sponsor_id IS NULL )
				   AND opportunity_status IN ('detected', 'offered')
				 ORDER BY person_score DESC, updated_at DESC, id DESC
				 LIMIT %d",
				$sponsor_id,
				max( 1, $limit )
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [];
		}
		$items = [];
		foreach ( $rows as $row ) {
			$items[] = [
				'domain' => (string) ( $row['actor_email_domain'] ?: __( 'Anonymous domain', 'khm-membership' ) ),
				'meta'   => sprintf(
					/* translators: 1: stage, 2: tier, 3: score percentage */
					__( 'Stage: %1$s • Tier: %2$s • Score: %3$s%%', 'khm-membership' ),
					(string) ( $row['internal_stage'] ?: '—' ),
					(string) ( $row['commercial_tier'] ?: '—' ),
					number_format_i18n( (float) ( $row['person_score'] ?? 0 ), 0 )
				),
			];
		}
		return $items;
	}
	private function get_prospect_meeting_requests( int $sponsor_id, int $limit = 5 ): array {
		if ( $sponsor_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$handovers_table = \KHM\Migrations\ConnectWorkflowMigration::handovers_table_name();
		$threads_table   = \KHM\Migrations\ConnectWorkflowMigration::threads_table_name();
		$providers_table = $wpdb->prefix . 'connect_providers';
		if ( ! $this->table_exists( $handovers_table ) || ! $this->table_exists( $threads_table ) ) {
			return [];
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.buyer_requested_at, t.buyer_company, t.latest_message_at, p.name AS provider_name
				 FROM {$handovers_table} h
				 LEFT JOIN {$threads_table} t ON t.id = h.thread_id
				 LEFT JOIN {$providers_table} p ON p.id = t.provider_id
				 WHERE h.sponsor_id = %d AND h.status = %s
				 ORDER BY h.buyer_requested_at DESC, h.id DESC
				 LIMIT %d",
				$sponsor_id,
				'buyer_requested',
				max( 1, $limit )
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [];
		}
		$items = [];
		foreach ( $rows as $row ) {
			$company = (string) ( $row['buyer_company'] ?: __( 'Anonymous company', 'khm-membership' ) );
			$requested_at = strtotime( (string) ( $row['buyer_requested_at'] ?: '' ) );
			$items[] = [
				'provider' => (string) ( $row['provider_name'] ?: __( 'Unknown offering', 'khm-membership' ) ),
				'meta'     => sprintf(
					/* translators: 1: buyer company, 2: requested date */
					__( '%1$s • Requested %2$s', 'khm-membership' ),
					$company,
					$requested_at ? wp_date( get_option( 'date_format' ), $requested_at ) : __( 'recently', 'khm-membership' )
				),
			];
		}
		return $items;
	}
	private function get_page_views_last_30_days( array $author_ids ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_seo_metrics';
		if ( empty( $author_ids ) || ! $this->table_exists( $table ) ) {
			return 0;
		}
		$author_placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$sql =
			"SELECT COALESCE(SUM(CAST(metric_value AS UNSIGNED)), 0)
			 FROM {$table}
			 WHERE metric_name IN ('page_views', 'screenPageViews')
			   AND measurement_date >= %s
			   AND post_id IN (
				   SELECT ID FROM {$wpdb->posts}
				   WHERE post_author IN ({$author_placeholders})
				     AND post_status = 'publish'
				     AND post_type IN ('post', 'atomic_article')
			   )";
		$params = array_merge( [ $since ], $author_ids );
		$value  = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return (int) $value;
	}
	private function get_smart_link_clicks_last_30_days( array $author_ids ): int {
		global $wpdb;
		$redirects_table = $wpdb->prefix . 'geo_redirects';
		$clicks_table    = $wpdb->prefix . 'geo_redirect_clicks';
		if ( empty( $author_ids ) || ! $this->table_exists( $redirects_table ) || ! $this->table_exists( $clicks_table ) ) {
			return 0;
		}
		$author_placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$sql =
			"SELECT COUNT(1)
			 FROM {$clicks_table} c
			 INNER JOIN {$redirects_table} r ON r.id = c.redirect_id
			 WHERE r.created_by IN ({$author_placeholders})
			   AND c.clicked_at >= %s";
		$params = array_merge( $author_ids, [ $since ] );
		$value  = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return (int) $value;
	}
	private function table_exists( string $table ): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return is_string( $found ) && $found === $table;
	}
	private function render_connect_section( int $user_id, ?array $sponsor ): void {
		$categories = $this->get_top_line_categories();
		?>
		<div class="khm-partner-section khm-partner-connect">
			<div class="khm-partner-connect-shell" data-sponsor-id="<?php echo esc_attr( (int) ( $sponsor['id'] ?? 0 ) ); ?>">
				<div class="khm-partner-connect-hero">
					<div>
						<h2><?php esc_html_e( 'Connect Offerings', 'khm-membership' ); ?></h2>
						<p><?php esc_html_e( 'Manage the provider offerings that power comparison, guided matching, commentary eligibility, and future intro workflows.', 'khm-membership' ); ?></p>
					</div>
					<div class="khm-partner-connect-status" role="status" aria-live="polite"></div>
				</div>
				<div class="khm-partner-connect-grid">
					<section class="khm-partner-connect-panel khm-partner-connect-subscription-panel khm-partner-connect-span-full" id="khm-partner-sub-panel-<?php echo esc_attr( (int) ( $sponsor['id'] ?? 0 ) ); ?>">
						<style>
							.khm-partner-sub-carousel-shell{margin:14px 0 10px;}
							.khm-partner-sub-carousel-window{overflow:hidden;border:1px solid #dcdcde;border-radius:12px;background:#f8fafc;padding:12px;}
							.khm-partner-sub-carousel-track{display:flex;gap:12px;transition:transform .3s ease;will-change:transform;}
							.khm-partner-sub-site-card{flex:0 0 170px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;min-height:158px;}
							.khm-partner-sub-site-card.is-connected{border-color:#2563eb;box-shadow:0 1px 6px rgba(37,99,235,.14);}
							.khm-partner-sub-site-card.is-not-connected{filter:grayscale(1);opacity:.58;}
							.khm-partner-sub-logo-link{display:flex;align-items:center;justify-content:center;width:100%;height:100%;}
							.khm-partner-sub-logo-link:hover img{opacity:.75;}
							.khm-partner-sub-logo-link img{transition:opacity .15s;}
							.khm-partner-sub-logo-wrap{width:140px;height:72px;display:flex;align-items:center;justify-content:center;margin-bottom:8px;}
							.khm-partner-sub-logo-wrap img{max-width:100%;max-height:100%;object-fit:contain;display:block;}
							.khm-partner-sub-logo-fallback{width:58px;height:58px;border-radius:8px;background:#e5e7eb;color:#6b7280;font-size:20px;font-weight:700;display:flex;align-items:center;justify-content:center;}
							.khm-partner-sub-site-name{font-size:12px;line-height:1.25;font-weight:600;color:#111827;text-align:center;margin-bottom:6px;}
							.khm-partner-sub-site-status{font-size:11px;line-height:1.2;font-weight:600;text-transform:uppercase;letter-spacing:.03em;}
							.khm-partner-sub-site-card.is-connected .khm-partner-sub-site-status{color:#1d4ed8;}
							.khm-partner-sub-site-card.is-not-connected .khm-partner-sub-site-status{color:#6b7280;}
							.khm-partner-sub-carousel-nav{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px;}
							.khm-partner-sub-carousel-btn{width:36px;height:36px;border:1px solid #d1d5db;border-radius:999px;background:#fff;display:inline-flex;align-items:center;justify-content:center;color:#111827;font-weight:700;cursor:pointer;}
							.khm-partner-sub-carousel-btn[disabled]{opacity:.38;cursor:default;}
							.khm-partner-sub-carousel-pages{font-size:12px;color:#4b5563;font-weight:600;}
							.khm-partner-sub-empty{font-size:13px;color:#646970;padding:10px 12px;border:1px dashed #ccd0d4;border-radius:8px;background:#fbfcfd;}
							.khm-partner-sub-notice{display:none;font-size:12px;padding:8px 12px;border-radius:4px;margin-bottom:8px;}
							.khm-partner-sub-actions{display:flex;gap:8px;flex-wrap:wrap;}
							@media (max-width: 1024px){
								.khm-partner-sub-site-card{flex-basis:158px;}
							}
							@media (max-width: 768px){
								.khm-partner-sub-site-card{flex-basis:146px;min-height:146px;}
								.khm-partner-sub-logo-wrap{width:120px;height:60px;}
							}
							/* ── Subscription Modal ───────────────────────────────── */
							#khm-sub-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:flex-start;justify-content:center;padding:24px 16px;}
							#khm-sub-modal[hidden]{display:none;}
							.khm-sub-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:0;}
							.khm-sub-modal-dialog{position:relative;z-index:1;background:#fff;border-radius:16px;padding:28px 28px 20px;width:100%;max-width:720px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.22);}
							.khm-sub-modal-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;line-height:1;color:#6b7280;cursor:pointer;padding:4px 6px;border-radius:4px;}
							.khm-sub-modal-close:hover{color:#111827;background:#f3f4f6;}
							.khm-sub-modal-title{font-size:18px;font-weight:700;color:#111827;margin:0 0 18px;}
							/* Grid of site cards inside the modal */
							.khm-sub-sites-grid{display:flex;flex-wrap:wrap;gap:12px;list-style:none;margin:0;padding:0;}
							.khm-sub-site-card{flex:0 0 calc(20% - 10px);min-width:120px;border:2px solid #e5e7eb;border-radius:10px;padding:12px 8px 10px;display:flex;flex-direction:column;align-items:center;gap:6px;background:#fff;transition:border-color .15s,box-shadow .15s;}
							.khm-sub-site-card.is-connected{border-color:#2563eb;background:#f0f6ff;}
							.khm-sub-site-card.is-selected{border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,.15);}
							.khm-sub-site-card.is-pending{border-color:#d97706;background:#fffbf0;}
							.khm-sub-site-card.is-not-connected{opacity:.65;filter:grayscale(1);transition:filter .2s,opacity .2s;}
							.khm-sub-site-card.is-not-connected.is-selected{filter:none;opacity:1;}
							.khm-sub-card-logo{width:110px;height:56px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
							.khm-sub-card-logo img{max-width:100%;max-height:100%;object-fit:contain;display:block;}
							.khm-sub-card-logo-fallback{width:48px;height:48px;border-radius:8px;background:#e5e7eb;color:#6b7280;font-size:16px;font-weight:700;display:flex;align-items:center;justify-content:center;}
							.khm-sub-card-label{font-size:11px;font-weight:600;color:#111827;text-align:center;line-height:1.3;}
							.khm-sub-card-status{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;}
							.khm-sub-site-card.is-connected .khm-sub-card-status{color:#1d4ed8;}
							.khm-sub-site-card.is-pending .khm-sub-card-status{color:#b45309;}
							.khm-sub-site-card{position:relative;}
							.khm-sub-card-cancel{position:absolute;bottom:6px;right:6px;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:13px;line-height:1;padding:0;border:1px solid #d1d5db;border-radius:50%;background:#fff;color:#9ca3af;cursor:pointer;}
							.khm-sub-card-cancel:hover{background:#fee2e2;border-color:#fca5a5;color:#b91c1c;}
							.khm-sub-card-check{width:16px;height:16px;cursor:pointer;accent-color:#059669;margin-top:2px;}
							/* Cart bar */
							.khm-sub-cart-bar{margin-top:18px;padding:14px 16px;background:#f0f6ff;border:1px solid #bfdbfe;border-radius:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
							.khm-sub-cart-bar[hidden]{display:none;}
							.khm-sub-cart-summary{flex:1;min-width:180px;}
							.khm-sub-cart-label{display:block;font-size:12px;color:#374151;font-weight:600;margin-bottom:2px;}
							.khm-sub-cart-price{display:block;font-size:20px;font-weight:700;color:#1d4ed8;}
							.khm-sub-cart-controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
							/* Upgrade banner */
							.khm-sub-upgrade-banner{margin-bottom:16px;padding:12px 16px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
							.khm-sub-upgrade-banner[hidden]{display:none;}
							.khm-sub-upgrade-banner-inner{flex:1;min-width:160px;}
							.khm-sub-upgrade-banner-inner strong{display:block;font-size:13px;color:#166534;}
							.khm-sub-upgrade-desc{display:block;font-size:12px;color:#15803d;margin-top:1px;}
							.khm-sub-upgrade-credit-note{display:block;font-size:11px;color:#4d7c0f;margin-top:2px;}
							.khm-sub-upgrade-net-price{display:block;font-size:15px;font-weight:700;color:#166534;margin-top:4px;}
							/* Notice */
							.khm-sub-modal-notice{display:block;padding:8px 12px;border-radius:6px;font-size:12px;margin-top:12px;}
							.khm-sub-modal-notice[hidden]{display:none;}
							.khm-sub-modal-notice--ok{background:#f0fdf4;color:#166534;border:1px solid #86efac;}
							.khm-sub-modal-notice--err{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;}
							@media (max-width: 600px){
								.khm-sub-site-card{flex:0 0 calc(33.33% - 8px);}
								.khm-sub-modal-dialog{padding:20px 14px 16px;}
							}
							@media (max-width: 400px){
								.khm-sub-site-card{flex:0 0 calc(50% - 6px);}
							}
						</style>
						<div class="khm-partner-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Connect Subscription', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Network channels and their live connection status.', 'khm-membership' ); ?></p>
							</div>
						</div>
						<div class="khm-partner-sub-notice" role="status" aria-live="polite"></div>
						<div class="khm-partner-sub-carousel-shell">
							<div class="khm-partner-sub-carousel-window">
								<div class="khm-partner-sub-carousel-track">
									<?php
										$logo_map = array(
										// Horizontal
										'pricing'                => 'revenue_operations.png',
										'aftermarket'            => 'aftermarket-operations.png',
										'field-service'          => 'field-service-management-logo.png',
										'spare-parts'            => 'spare-parts-and-logistics2.png',
										'ecommerce'              => 'Industrial-eCommerce.png',
										// Vertical
										'industrial'             => 'industrial-operations.png',
										'aerospace'              => 'aerospace-engineering-logo.png',
										'utilities-ops'          => 'utilities-operations.png',
										'built-env'              => 'infrastructure_operations.png',
										'manufacturing-flagship' => 'modern-manufacturing-logo.png',
									);
									// Build a URL map from the live network blog list.
									$_url_map = array();
									foreach ( get_sites( array( 'number' => 100 ) ) as $_s ) {
										$_ps = trim( $_s->path, '/' );
										if ( $_ps ) {
											$_url_map[ $_ps ] = get_home_url( (int) $_s->blog_id );
										}
									}
									// 10-channel network catalog (Portal excluded — user is already there).
									// blog_slugs: WP site path slugs that map to this channel (first = primary URL).
									$all_sites = array(
										// --- Horizontal ---
										array( 'slug' => 'pricing',               'label' => 'Revenue Operations',        'blog_slugs' => array( 'pricing' ) ),
										array( 'slug' => 'aftermarket',           'label' => 'Aftermarket Operations',    'blog_slugs' => array( 'aftermarket' ) ),
										array( 'slug' => 'field-service',         'label' => 'Field Service Management',  'blog_slugs' => array( 'field-service' ) ),
										array( 'slug' => 'spare-parts',           'label' => 'Spare Parts & Logistics',   'blog_slugs' => array( 'spare-parts' ) ),
										array( 'slug' => 'ecommerce',             'label' => 'Industrial eCommerce',      'blog_slugs' => array( 'ecommerce' ) ),
										// --- Vertical ---
										array( 'slug' => 'industrial',            'label' => 'Industrial Operations',     'blog_slugs' => array( 'industrial' ) ),
										array( 'slug' => 'aerospace',             'label' => 'Aerospace Engineering',     'blog_slugs' => array( 'aerospace' ) ),
										array( 'slug' => 'utilities-ops',         'label' => 'Utilities Operations',      'blog_slugs' => array( 'energy', 'utilities' ) ),
										array( 'slug' => 'built-env',             'label' => 'Infrastructure Operations', 'blog_slugs' => array( 'built-env' ) ),
										array( 'slug' => 'manufacturing-flagship','label' => 'Modern Manufacturing',      'blog_slugs' => array( 'flagship', 'manufacturing' ) ),
									);
									$connected = array();
									$sponsor_id = (int) ( $sponsor['id'] ?? 0 );
									if ( $sponsor_id > 0 ) {
										global $wpdb;
										$table = $wpdb->prefix . 'connect_providers';
										if ( $this->table_exists( $table ) ) {
											$rows = $wpdb->get_results(
												$wpdb->prepare(
													"SELECT DISTINCT b.path
													 FROM {$table} p
													 INNER JOIN {$wpdb->blogs} b ON b.blog_id = p.blog_id
													 WHERE p.sponsor_id = %d AND p.status = %s AND p.blog_id > 1",
													$sponsor_id,
													'active'
												),
												ARRAY_A
											);
											foreach ( (array) $rows as $row ) {
												$path_slug = trim( (string) ( $row['path'] ?? '' ), '/' );
												if ( $path_slug ) {
													$connected[ $path_slug ] = true;
												}
											}
										}
									}
									foreach ( $all_sites as $site_item ) :
										$slug         = (string) $site_item['slug'];
										$label        = (string) $site_item['label'];
										$logo_file    = $logo_map[ $slug ] ?? '';
										$initials     = strtoupper( substr( preg_replace( '/[^a-z]/i', '', $slug ), 0, 2 ) );
										$primary_slug = $site_item['blog_slugs'][0] ?? '';
										$site_url     = $primary_slug ? ( $_url_map[ $primary_slug ] ?? '' ) : '';
										// Connected if ANY of the mapped blog slugs are active for this sponsor.
										$is_connected = false;
										foreach ( $site_item['blog_slugs'] as $bs ) {
											if ( ! empty( $connected[ $bs ] ) ) {
												$is_connected = true;
												break;
											}
										}
										$card_class  = $is_connected ? 'is-connected' : 'is-not-connected';
										$status_text = $is_connected ? 'Connected' : 'Not connected';
									?>
										<div class="khm-partner-sub-site-card <?php echo esc_attr( $card_class ); ?>">
											<div class="khm-partner-sub-logo-wrap">
												<?php if ( $logo_file ) : ?>
													<?php if ( $site_url ) : ?>
														<a href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener" class="khm-partner-sub-logo-link" title="<?php echo esc_attr( sprintf( 'Visit %s', $label ) ); ?>">
															<img src="<?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) ) . 'assets/images/sites/' . rawurlencode( $logo_file ) ); ?>" alt="<?php echo esc_attr( $label . ' logo' ); ?>" />
														</a>
													<?php else : ?>
														<img src="<?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) ) . 'assets/images/sites/' . rawurlencode( $logo_file ) ); ?>" alt="<?php echo esc_attr( $label . ' logo' ); ?>" />
													<?php endif; ?>
												<?php else : ?>
													<div class="khm-partner-sub-logo-fallback"><?php echo esc_html( $initials ?: 'NA' ); ?></div>
												<?php endif; ?>
											</div>
											<div class="khm-partner-sub-site-name"><?php echo esc_html( $label ); ?></div>
											<div class="khm-partner-sub-site-status"><?php echo esc_html( $status_text ); ?></div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="khm-partner-sub-carousel-nav">
								<button type="button" class="khm-partner-sub-carousel-btn khm-partner-sub-prev" aria-label="<?php esc_attr_e( 'Previous slide', 'khm-membership' ); ?>">&#8592;</button>
								<div class="khm-partner-sub-carousel-pages">1 / 1</div>
								<button type="button" class="khm-partner-sub-carousel-btn khm-partner-sub-next" aria-label="<?php esc_attr_e( 'Next slide', 'khm-membership' ); ?>">&#8594;</button>
							</div>
						</div>
						<div class="khm-partner-sub-actions">
							<button type="button" class="khm-partner-btn khm-partner-btn-primary" id="khm-sub-modal-trigger" data-khm-sub-modal-trigger>
								<?php esc_html_e( 'Manage Connections', 'khm-membership' ); ?>
							</button>
						</div>
					</section>
					<?php
						// ── Inject window.khmSubData for the modal ──────────────────
						$_user_id_for_sub = get_current_user_id();
						$_site_subs_raw   = get_user_meta( $_user_id_for_sub, 'khm_connect_site_subscriptions', true );
						$_site_subs_raw   = is_array( $_site_subs_raw ) ? $_site_subs_raw : [];
						$_portfolio_raw   = get_user_meta( $_user_id_for_sub, 'khm_connect_subscription', true );
						$_portfolio_raw   = is_array( $_portfolio_raw ) ? $_portfolio_raw : [];
						$_prices_opt      = get_option( 'khm_connect_subscription_annual_prices', [] );
						$_prices          = [
							'site'      => isset( $_prices_opt['site'] ) ? (int) $_prices_opt['site'] : 35000,
							'portfolio' => isset( $_prices_opt['portfolio'] ) ? (int) $_prices_opt['portfolio'] : 250000,
						];
						// Build site sub data with provider connected state.
						$_sub_sites_data  = [];
						foreach ( $all_sites as $_sub_site_item ) {
							$_ss      = $_sub_site_item['slug'];
							$_sub     = $_site_subs_raw[ $_ss ] ?? [];
							$_status  = (string) ( $_sub['status'] ?? 'inactive' );
							$_expires = (string) ( $_sub['expires_at'] ?? '' );
							$_days    = $_expires ? max( 0, (int) ceil( ( strtotime( $_expires ) - time() ) / 86400 ) ) : 0;
							$_is_prov = false;
							foreach ( $_sub_site_item['blog_slugs'] as $_bs ) {
								if ( ! empty( $connected[ $_bs ] ) ) { $_is_prov = true; break; }
							}
							$_sub_sites_data[] = [
								'slug'             => $_ss,
								'label'            => $_sub_site_item['label'],
								'logo'             => $logo_map[ $_ss ] ?? '',
								'logo_url'         => $logo_map[ $_ss ] ? ( plugin_dir_url( dirname( __DIR__ ) ) . 'assets/images/sites/' . $logo_map[ $_ss ] ) : '',
								'is_connected'     => $_is_prov || in_array( $_status, [ 'active', 'pending_invoice' ], true ),
								'is_provider'      => $_is_prov,
								'status'           => $_is_prov && $_status === 'inactive' ? 'provider_active' : $_status,
								'expires_at'       => $_expires,
								'cancelled_at'     => (string) ( $_sub['cancelled_at'] ?? '' ),
								'days_remaining'   => $_days,
								'renews_on'        => $_expires ? date_i18n( 'j M Y', strtotime( $_expires ) ) : '',
							];
						}
						// Pro-rata upgrade credit.
						$_credit = 0;
						foreach ( $_site_subs_raw as $_sub_entry ) {
							if ( ( $_sub_entry['status'] ?? '' ) !== 'active' ) continue;
							$_exp = $_sub_entry['expires_at'] ?? '';
							if ( ! $_exp ) continue;
							$_dr = max( 0, (int) ceil( ( strtotime( $_exp ) - time() ) / 86400 ) );
							$_credit += (int) round( ( $_prices['site'] / 365 ) * $_dr );
						}
						$_upgrade_cost = max( 0, $_prices['portfolio'] - $_credit );
						$_has_portfolio = ( $_portfolio_raw['scope'] ?? '' ) === 'portfolio' && ( $_portfolio_raw['status'] ?? '' ) === 'active';
					?>
					<script>
					window.khmSubData = <?php echo wp_json_encode( [
						'nonce'               => wp_create_nonce( 'wp_rest' ),
						'apiBase'             => esc_url_raw( rest_url( 'khm/v1/connect/subscription' ) ),
						'sites'               => $_sub_sites_data,
						'portfolio'           => $_portfolio_raw,
						'hasPortfolio'        => $_has_portfolio,
						'prices'              => $_prices,
						'upgradeCreditPence'  => $_credit,
						'upgradeCostPence'    => $_upgrade_cost,
						'logoBase'            => plugin_dir_url( dirname( __DIR__ ) ) . 'assets/images/sites/',
					] ); ?>;
					</script>
					<!-- ── Connect Subscription Modal ─────────────────────────────── -->
					<div id="khm-sub-modal" class="khm-sub-modal" role="dialog" aria-modal="true" aria-labelledby="khm-sub-modal-title" hidden>
						<div class="khm-sub-modal-backdrop"></div>
						<div class="khm-sub-modal-dialog">
							<button type="button" class="khm-sub-modal-close" aria-label="<?php esc_attr_e( 'Close', 'khm-membership' ); ?>">&times;</button>
							<h2 id="khm-sub-modal-title" class="khm-sub-modal-title"><?php esc_html_e( 'Manage Connections', 'khm-membership' ); ?></h2>
							<!-- Upgrade banner (shown when upgrade is available and portfolio not active) -->
							<div class="khm-sub-upgrade-banner" hidden>
								<div class="khm-sub-upgrade-banner-inner">
									<strong><?php esc_html_e( 'Upgrade to Portfolio', 'khm-membership' ); ?></strong>
									<span class="khm-sub-upgrade-desc"></span>
									<span class="khm-sub-upgrade-credit-note"></span>
									<span class="khm-sub-upgrade-net-price"></span>
								</div>
								<div class="khm-sub-upgrade-actions">
									<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-sub-upgrade-btn">
										<?php esc_html_e( 'Upgrade to Portfolio', 'khm-membership' ); ?>
									</button>
								</div>
							</div>
							<!-- Sites grid -->
							<div class="khm-sub-sites-grid" role="list"></div>
							<!-- Cart bar (shown when unconnected sites are selected) -->
							<div class="khm-sub-cart-bar" hidden>
								<div class="khm-sub-cart-summary">
									<span class="khm-sub-cart-label"></span>
									<span class="khm-sub-cart-price"></span>
								</div>
								<div class="khm-sub-cart-controls">
									<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-sub-cart-confirm">
										<?php esc_html_e( 'Checkout', 'khm-membership' ); ?>
									</button>
								</div>
							</div>
							<p class="khm-sub-modal-notice" role="status" aria-live="polite"></p>
						</div>
					</div>
					<!-- /Connect Subscription Modal -->
					<script>
					(function(){
						var sponsorId = <?php echo (int) ( $sponsor['id'] ?? 0 ); ?>;
						var panel = document.getElementById('khm-partner-sub-panel-' + sponsorId);
						if (!panel || !sponsorId) return;
						var carouselWindow = panel.querySelector('.khm-partner-sub-carousel-window');
						var track = panel.querySelector('.khm-partner-sub-carousel-track');
						var pageLabel = panel.querySelector('.khm-partner-sub-carousel-pages');
						var prevBtn = panel.querySelector('.khm-partner-sub-prev');
						var nextBtn = panel.querySelector('.khm-partner-sub-next');
						var notice = panel.querySelector('.khm-partner-sub-notice');
						var cards = Array.prototype.slice.call(track.querySelectorAll('.khm-partner-sub-site-card'));
						var currentPage = 0;
						var visibleCount = 6;
						function getVisibleCount(){
							if (window.innerWidth <= 560) return 2;
							if (window.innerWidth <= 860) return 3;
							if (window.innerWidth <= 1180) return 4;
							return 6;
						}
						function showNotice(msg, ok){
							notice.textContent = msg;
							notice.style.background = ok ? '#e6f4ea' : '#fce8e6';
							notice.style.color = ok ? '#1e8c45' : '#c5221f';
							notice.style.display = 'block';
							setTimeout(function(){ notice.style.display = 'none'; }, 5000);
						}
						function updateNav(){
							visibleCount = getVisibleCount();
							var totalPages = Math.max(1, Math.ceil(cards.length / visibleCount));
							if (currentPage > totalPages - 1) currentPage = totalPages - 1;
							var card = track.querySelector('.khm-partner-sub-site-card');
							var cardWidth = card ? (card.offsetWidth + 12) : 182;
							var shift = currentPage * visibleCount * cardWidth;
							track.style.transform = 'translateX(-' + shift + 'px)';
							pageLabel.textContent = (currentPage + 1) + ' / ' + totalPages;
							prevBtn.disabled = currentPage <= 0;
							nextBtn.disabled = currentPage >= totalPages - 1;
						}
						function goPrev(){
							currentPage = Math.max(0, currentPage - 1);
							updateNav();
						}
						function goNext(){
							var maxPage = Math.max(0, Math.ceil(cards.length / visibleCount) - 1);
							currentPage = Math.min(maxPage, currentPage + 1);
							updateNav();
						}
						prevBtn.addEventListener('click', function(){
							goPrev();
						});
						nextBtn.addEventListener('click', function(){
							goNext();
						});
						if (carouselWindow) {
							var wheelLocked = false;
							var pointerStartX = 0;
							var pointerStartY = 0;
							var pointerActive = false;
							carouselWindow.setAttribute('tabindex', '0');
							carouselWindow.addEventListener('wheel', function(event){
								var delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
								if (Math.abs(delta) < 16) return;
								event.preventDefault();
								if (wheelLocked) return;
								wheelLocked = true;
								if (delta > 0) goNext(); else goPrev();
								setTimeout(function(){ wheelLocked = false; }, 220);
							}, { passive: false });
							carouselWindow.addEventListener('pointerdown', function(event){
								pointerActive = true;
								pointerStartX = event.clientX;
								pointerStartY = event.clientY;
							});
							carouselWindow.addEventListener('pointerup', function(event){
								if (!pointerActive) return;
								pointerActive = false;
								var dx = event.clientX - pointerStartX;
								var dy = event.clientY - pointerStartY;
								if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;
								if (dx < 0) goNext(); else goPrev();
							});
							carouselWindow.addEventListener('pointercancel', function(){
								pointerActive = false;
							});
							carouselWindow.addEventListener('keydown', function(event){
								if (event.key === 'ArrowLeft') {
									event.preventDefault();
									goPrev();
								}
								if (event.key === 'ArrowRight') {
									event.preventDefault();
									goNext();
								}
							});
						}
						window.addEventListener('resize', updateNav);
						updateNav();
					})();
					</script>
					<section class="khm-partner-connect-panel khm-partner-connect-list-panel">
						<div class="khm-partner-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Your Live Offerings', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'These records are owned by your sponsor account and scoped to this site.', 'khm-membership' ); ?></p>
							</div>
							<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-connect-new"><?php esc_html_e( 'New Offering', 'khm-membership' ); ?></button>
						</div>
						<div class="khm-partner-connect-list"></div>
					</section>
					<section class="khm-partner-connect-panel khm-partner-connect-form-panel">
						<div class="khm-partner-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Offering Details', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Use typed fields for fit and delivery, then keep advanced comparison and matching metadata in JSON until the guided workflow expands.', 'khm-membership' ); ?></p>
							</div>
						</div>
						<form class="khm-partner-connect-form" id="khm-partner-connect-form">
							<input type="hidden" name="id" value="" />
							<div class="khm-partner-connect-form-grid">
								<label>
									<span><?php esc_html_e( 'Name', 'khm-membership' ); ?></span>
									<input type="text" name="name" required />
								</label>
								<label>
									<span><?php esc_html_e( 'Slug', 'khm-membership' ); ?></span>
									<input type="text" name="slug" placeholder="auto-from-name" />
								</label>
								<label>
									<span><?php esc_html_e( 'Website URL', 'khm-membership' ); ?></span>
									<input type="url" name="website_url" placeholder="https://example.com" />
								</label>
								<label>
									<span><?php esc_html_e( 'Provider Type', 'khm-membership' ); ?></span>
									<select name="provider_type">
										<option value=""><?php esc_html_e( 'Select type', 'khm-membership' ); ?></option>
										<option value="agency"><?php esc_html_e( 'Agency', 'khm-membership' ); ?></option>
										<option value="platform"><?php esc_html_e( 'Platform', 'khm-membership' ); ?></option>
										<option value="consultancy"><?php esc_html_e( 'Consultancy', 'khm-membership' ); ?></option>
										<option value="data-provider"><?php esc_html_e( 'Data Provider', 'khm-membership' ); ?></option>
										<option value="other"><?php esc_html_e( 'Other', 'khm-membership' ); ?></option>
									</select>
								</label>
								<label class="khm-partner-connect-span-2">
									<span><?php esc_html_e( 'Description', 'khm-membership' ); ?></span>
									<textarea name="description" rows="3"></textarea>
								</label>
								<label class="khm-partner-connect-span-2">
									<span><?php esc_html_e( 'Sweet Spot Summary', 'khm-membership' ); ?></span>
									<textarea name="sweet_spot_summary" rows="3" placeholder="Who you are best for, typical use cases, and what makes the fit strong."></textarea>
								</label>
								
								<fieldset style="grid-column: 1 / -1; border: 1px solid #dcdcde; border-radius: 6px; padding: 14px; background: #fafbfc; margin: 8px 0;">
									<legend style="padding: 0 8px; font-weight: 600; font-size: 13px; color: #3c434a; text-transform: uppercase; letter-spacing: 0.04em;"><?php esc_html_e( 'Ideal Customer Profile (ICP)', 'khm-membership' ); ?></legend>
									<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-top: 8px;">
										<label>
											<span><?php esc_html_e( 'Company Size - Min (employees)', 'khm-membership' ); ?></span>
											<input type="number" min="0" name="company_size_min" placeholder="e.g., 10" />
											<p style="margin: 4px 0 0; font-size: 11px; color: #666;"><?php esc_html_e( 'Minimum company headcount in your ideal segment', 'khm-membership' ); ?></p>
										</label>
										<label>
											<span><?php esc_html_e( 'Company Size - Max (employees)', 'khm-membership' ); ?></span>
											<input type="number" min="0" name="company_size_max" placeholder="e.g., 500" />
											<p style="margin: 4px 0 0; font-size: 11px; color: #666;"><?php esc_html_e( 'Maximum company headcount in your ideal segment', 'khm-membership' ); ?></p>
										</label>
										<label>
											<span><?php esc_html_e( 'Budget - Min (annual, in £)', 'khm-membership' ); ?></span>
											<input type="number" min="0" name="budget_min" placeholder="e.g., 50000" />
											<p style="margin: 4px 0 0; font-size: 11px; color: #666;"><?php esc_html_e( 'Minimum annual budget for typical deal', 'khm-membership' ); ?></p>
										</label>
										<label>
											<span><?php esc_html_e( 'Budget - Max (annual, in £)', 'khm-membership' ); ?></span>
											<input type="number" min="0" name="budget_max" placeholder="e.g., 500000" />
											<p style="margin: 4px 0 0; font-size: 11px; color: #666;"><?php esc_html_e( 'Maximum annual budget for typical deal', 'khm-membership' ); ?></p>
										</label>
										<label style="grid-column: 1 / -1;">
											<span><?php esc_html_e( 'Typical Onboarding Timeline (days)', 'khm-membership' ); ?></span>
											<input type="number" min="0" name="onboarding_days" placeholder="e.g., 30" />
											<p style="margin: 4px 0 0; font-size: 11px; color: #666;"><?php esc_html_e( 'Average time to activate a new customer', 'khm-membership' ); ?></p>
										</label>
									</div>
								</fieldset>
								<fieldset style="grid-column: 1 / -1; border: 1px solid #dcdcde; border-radius: 6px; padding: 14px; background: #fafbfc; margin: 8px 0;">
									<legend style="padding: 0 8px; font-weight: 600; font-size: 13px; color: #3c434a; text-transform: uppercase; letter-spacing: 0.04em;"><?php esc_html_e( 'RFQ Response Defaults', 'khm-membership' ); ?></legend>
									<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-top: 8px;">
										<label>
											<span><?php esc_html_e( 'Default Scope', 'khm-membership' ); ?></span>
											<select name="rfq_default_scope">
												<option value="pilot_scheme"><?php esc_html_e( 'Structured pilot scheme (time-boxed, defined success criteria)', 'khm-membership' ); ?></option>
												<option value="fsm_evaluation_poc"><?php esc_html_e( 'Complete FSM platform evaluation and POC', 'khm-membership' ); ?></option>
												<option value="mobile_iot_optimisation"><?php esc_html_e( 'Mobile-first FSM with IoT optimisation', 'khm-membership' ); ?></option>
												<option value="workforce_scheduling_upgrade"><?php esc_html_e( 'Workforce scheduling and dispatch modernisation', 'khm-membership' ); ?></option>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'Default Seat Band', 'khm-membership' ); ?></span>
											<select name="rfq_default_seats">
												<option value="20_30"><?php esc_html_e( '20-30 seats', 'khm-membership' ); ?></option>
												<option value="50_100"><?php esc_html_e( '50-100 seats', 'khm-membership' ); ?></option>
												<option value="100_250"><?php esc_html_e( '100-250 seats', 'khm-membership' ); ?></option>
												<option value="500_plus"><?php esc_html_e( '500+ seats', 'khm-membership' ); ?></option>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'Default Timeframe', 'khm-membership' ); ?></span>
											<select name="rfq_default_timeframe">
												<option value="3_months"><?php esc_html_e( '3 months', 'khm-membership' ); ?></option>
												<option value="6_months"><?php esc_html_e( '6 months', 'khm-membership' ); ?></option>
												<option value="12_months"><?php esc_html_e( '12 months', 'khm-membership' ); ?></option>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'Default Cost Per Licence / Month (£)', 'khm-membership' ); ?></span>
											<input type="number" min="0" step="0.01" name="rfq_default_cpl_gbp" placeholder="e.g., 325" />
										</label>
										<label>
											<span><?php esc_html_e( 'Default Buyer Estimate (£)', 'khm-membership' ); ?></span>
											<input type="number" min="0" step="500" name="rfq_default_estimate_gbp" placeholder="e.g., 120000" />
										</label>
										<label>
											<span><?php esc_html_e( 'Max Discount You Will Offer (%)', 'khm-membership' ); ?></span>
											<input type="number" min="0" max="30" step="1" name="rfq_max_discount_pct" placeholder="e.g., 10" />
										</label>
										<label style="grid-column: 1 / -1;">
											<span><?php esc_html_e( 'Supported Features (comma-separated keys)', 'khm-membership' ); ?></span>
											<input type="text" name="rfq_supported_features" placeholder="mobile_app,offline_capabilities,real_time_reporting,erp_integration" />
										</label>
									</div>
								</fieldset>
								
								<label>
									<span><?php esc_html_e( 'Title Contexts', 'khm-membership' ); ?></span>
									<input type="text" name="titles" placeholder="finance, saas, cybersecurity" list="khm-partner-connect-title-contexts" />
								</label>
								<label>
									<span><?php esc_html_e( 'Regions', 'khm-membership' ); ?></span>
									<input type="text" name="regions" placeholder="uk, europe, north-america" />
								</label>
								<label>
									<span><?php esc_html_e( 'Deployment Modes', 'khm-membership' ); ?></span>
									<input type="text" name="deployment_modes" placeholder="self-serve, managed-service" />
								</label>
								<label>
									<span><?php esc_html_e( 'Support Tiers', 'khm-membership' ); ?></span>
									<input type="text" name="support_tiers" placeholder="email, dedicated-csm" />
								</label>
								<label>
									<span><?php esc_html_e( 'Status', 'khm-membership' ); ?></span>
									<select name="status">
										<option value="active"><?php esc_html_e( 'Active', 'khm-membership' ); ?></option>
										<option value="inactive"><?php esc_html_e( 'Inactive', 'khm-membership' ); ?></option>
									</select>
								</label>
								<label class="khm-partner-connect-check">
									<input type="checkbox" name="commentary_enabled" value="1" />
									<span><?php esc_html_e( 'Eligible for commentary contexts', 'khm-membership' ); ?></span>
								</label>
								<label class="khm-partner-connect-check">
									<input type="checkbox" name="ad_targeting_enabled" value="1" />
									<span><?php esc_html_e( 'Eligible for ad targeting', 'khm-membership' ); ?></span>
								</label>
								<label class="khm-partner-connect-span-2">
									<span><?php esc_html_e( 'Comparison Fields JSON', 'khm-membership' ); ?></span>
									<textarea name="comparison_fields" rows="6" spellcheck="false">{}</textarea>
								</label>
								<label class="khm-partner-connect-span-2">
									<span><?php esc_html_e( 'Match Rules JSON', 'khm-membership' ); ?></span>
									<textarea name="match_rules" rows="6" spellcheck="false">{}</textarea>
								</label>
							</div>
							<div class="khm-partner-connect-actions">
								<button type="submit" class="khm-partner-btn khm-partner-btn-primary khm-partner-connect-save"><?php esc_html_e( 'Save Offering', 'khm-membership' ); ?></button>
								<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-connect-reset"><?php esc_html_e( 'Reset', 'khm-membership' ); ?></button>
								<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-connect-delete" style="display:none"><?php esc_html_e( 'Delete', 'khm-membership' ); ?></button>
							</div>
						</form>
						<datalist id="khm-partner-connect-title-contexts">
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( sanitize_title( $category ) ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</section>
					<section class="khm-partner-connect-panel khm-partner-connect-leads-panel khm-partner-connect-span-full" id="khm-partner-leads-panel-<?php echo esc_attr( (string) (int) ( $sponsor['id'] ?? 0 ) ); ?>">
						<style>
							.khm-partner-leads-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}
							.khm-partner-leads-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:12px;}
							.khm-partner-lead-card{border:1px solid #dcdcde;border-radius:6px;padding:14px;background:#fff;display:flex;flex-direction:column;gap:8px;}
							.khm-partner-lead-card[data-status="sponsor_accepted"]{border-color:#1a73e8;}
							.khm-partner-lead-card[data-status="intro_requested"],.khm-partner-lead-card[data-status="introduced"]{border-color:#1e8c45;}
							.khm-partner-lead-tier{display:inline-block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:2px 7px;border-radius:3px;background:#f0f0f1;color:#3c434a;}
							.khm-partner-lead-tier-premium{background:#e8f0fe;color:#1a73e8;}
							.khm-partner-lead-tier-standard{background:#e6f4ea;color:#1e8c45;}
							.khm-partner-lead-tier-exploratory{background:#fce8e6;color:#c5221f;}
							.khm-partner-lead-tier-engaged{background:#fff4e5;color:#9a3412;}
							.khm-partner-lead-score-bar{height:4px;border-radius:2px;background:#e9e9e9;overflow:hidden;}
							.khm-partner-lead-score-fill{height:100%;border-radius:2px;background:#1a73e8;transition:width .3s;}
							.khm-partner-lead-meta{font-size:12px;color:#555;display:flex;flex-wrap:wrap;gap:6px 14px;}
							.khm-partner-lead-anon{display:grid;gap:4px;font-size:12px;color:#3c434a;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:8px 10px;min-height:112px;align-content:start;}
							.khm-partner-lead-anon span strong{font-weight:600;}
							.khm-partner-lead-status{font-size:12px;font-weight:600;}
							.khm-partner-lead-actions{margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;}
							.khm-partner-lead-demo-pill{display:inline-block;margin-left:6px;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 6px;border-radius:3px;background:#fff3cd;color:#8a6116;}
							.khm-partner-lead-accept-form{display:flex;flex-direction:column;gap:8px;}
							.khm-partner-lead-accept-form select{font-size:12px;padding:4px 6px;border:1px solid #ccd0d4;border-radius:4px;width:100%;}
							.khm-partner-leads-empty{padding:16px;color:#777;font-size:13px;}
							.khm-partner-leads-notice{font-size:12px;padding:8px 12px;border-radius:4px;margin-bottom:8px;display:none;}
							.khm-partner-leads-notice-ok{background:#e6f4ea;color:#1e8c45;}
							.khm-partner-leads-notice-err{background:#fce8e6;color:#c5221f;}
								.khm-partner-leads-nav{display:flex;align-items:center;gap:10px;margin-top:12px;margin-bottom:32px;}
								.khm-partner-leads-page-label{flex:1;text-align:center;font-size:12px;color:#555;}
							.khm-partner-lead-price-base{color:#888;font-size:11px;font-weight:400;}
							.khm-partner-lead-price{font-size:12px;color:#555;}
							.khm-partner-lead-engaged-options{font-size:11px;color:#3c434a;background:#fff8f1;border:1px solid #f2d3b0;border-radius:6px;padding:7px 9px;display:grid;gap:3px;min-height:58px;}
							.khm-partner-lead-engaged-options-placeholder{background:transparent;border-color:transparent;visibility:hidden;}
							.khm-partner-lead-engaged-ctas{display:flex;flex-wrap:wrap;gap:8px;}
							.khm-partner-lead-engaged-ctas .khm-partner-btn{font-size:12px;padding:4px 10px;}
							/* RFQ Cards */
							.khm-partner-rfq-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-bottom:16px;align-items:start;}
							.khm-partner-rfq-card{background:#fafafa;border:1px solid #ddd;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px;}
							.khm-partner-rfq-card[data-status="detected"]{background:#fef9f3;}
							.khm-partner-rfq-card > div:first-child{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
							.khm-partner-rfq-metadata{font-size:11px;color:#3c434a;background:#f5f5f5;border:1px solid #e8e8e8;border-radius:6px;padding:8px;display:grid;gap:4px;align-content:start;height:200px;overflow-y:auto;}
							.khm-partner-rfq-metadata span{display:block;}
							.khm-partner-rfq-metadata strong{color:#1e8c45;font-weight:600;}
							.khm-partner-rfq-notice{font-size:12px;padding:8px 12px;border-radius:4px;margin-bottom:8px;display:none;}
							.khm-partner-rfq-notice-ok{background:#e6f4ea;color:#1e8c45;}
							.khm-partner-rfq-notice-err{background:#fce8e6;color:#c5221f;}
							.khm-partner-rfq-nav{display:flex;align-items:center;gap:10px;margin-top:12px;margin-bottom:32px;}
							.khm-partner-rfq-page-label{flex:1;text-align:center;font-size:12px;color:#555;}
							.khm-partner-rfq-accept-form{display:flex;flex-direction:column;gap:8px;}
							.khm-partner-rfq-response-workflow[hidden]{display:none;}
							.khm-partner-rfq-response-grid{display:grid;gap:12px;}
							.khm-partner-rfq-response-field{display:grid;gap:4px;}
							.khm-partner-rfq-response-field label{font-size:11px;font-weight:600;color:#3c434a;}
							.khm-partner-rfq-response-field input[type="text"],.khm-partner-rfq-response-field input[type="number"],.khm-partner-rfq-response-field textarea,.khm-partner-rfq-response-field select{width:100%;padding:8px 10px;font-size:12px;border:1px solid #ccd0d4;border-radius:6px;background:#fff;}
							.khm-partner-rfq-response-field textarea{min-height:72px;resize:vertical;}
							.khm-partner-rfq-response-notes{margin-bottom:8px;}
							.khm-partner-rfq-response-hint{font-size:11px;color:#555;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:7px 9px;margin-bottom:10px;}
							.khm-partner-rfq-summary{background:#fff;border:1px solid #e1e4e8;border-radius:8px;padding:12px;}
							.khm-partner-rfq-summary input[type="hidden"]{display:none !important;}
							.khm-partner-rfq-summary h4{margin:0 0 8px;font-size:15px;line-height:1.3;color:#1f2937;}
							.khm-partner-rfq-summary-list{margin:0;padding-left:18px;display:grid;gap:8px;font-size:12px;color:#3c434a;}
							.khm-partner-rfq-summary-list > li{margin:0;}
							.khm-partner-rfq-summary-list strong{color:#111827;}
							.khm-partner-rfq-summary-sublist{margin:6px 0 0 0;padding-left:18px;display:grid;gap:4px;}
							.khm-partner-rfq-feature-grid{display:block;}
							.khm-partner-rfq-calc-card{background:#f8fafc;border:1px solid #dbe4ea;border-radius:8px;padding:10px 12px;display:grid;gap:8px;}
							.khm-partner-rfq-estimate-value{font-size:16px;font-weight:700;color:#111827;}
							.khm-partner-rfq-estimate-caption{font-size:11px;color:#6b7280;}
							.khm-partner-rfq-toggle{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:#1f2937;cursor:pointer;}
							.khm-partner-rfq-toggle input{width:auto;margin:0;}
							.khm-partner-rfq-discount-controls[hidden],.khm-partner-rfq-commission-breakdown[hidden]{display:none;}
							.khm-partner-rfq-discount-readout{font-size:11px;color:#3c434a;display:grid;grid-template-columns:minmax(96px,auto) 1fr;gap:10px 14px;align-items:start;}
							.khm-partner-rfq-breakdown-row{display:flex;justify-content:space-between;gap:12px;font-size:12px;color:#3c434a;align-items:flex-start;}
							.khm-partner-rfq-breakdown-row strong{color:#111827;}
							.khm-partner-rfq-tooltip{position:relative;display:inline-flex;align-items:center;gap:6px;}
							.khm-partner-rfq-tooltip-btn{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border:1px solid #93a1b0;border-radius:999px;background:#fff;color:#3c434a;font-size:10px;line-height:1;cursor:help;padding:0;}
							.khm-partner-rfq-tooltip-bubble{display:none;position:absolute;left:0;top:calc(100% + 6px);z-index:6;min-width:260px;max-width:320px;background:#111827;color:#f9fafb;border-radius:6px;padding:8px 10px;font-size:11px;line-height:1.35;box-shadow:0 8px 20px rgba(15,23,42,.25);}
							.khm-partner-rfq-tooltip.is-open .khm-partner-rfq-tooltip-bubble{display:block;}
							.khm-partner-rfq-tooltip:hover .khm-partner-rfq-tooltip-bubble,.khm-partner-rfq-tooltip:focus-within .khm-partner-rfq-tooltip-bubble{display:block;}
							.khm-partner-rfq-review-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:99999;padding:16px;}
							.khm-partner-rfq-review-modal.is-open{display:flex;}
							.khm-partner-rfq-review-dialog{background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(15,23,42,.18);width:min(680px,100%);max-height:90vh;overflow:auto;padding:20px;display:grid;gap:14px;}
							.khm-partner-rfq-review-head{display:flex;justify-content:space-between;align-items:center;gap:12px;}
							.khm-partner-rfq-review-head h3{margin:0;font-size:18px;color:#111827;}
							.khm-partner-rfq-review-close{border:0;background:none;font-size:24px;line-height:1;color:#6b7280;cursor:pointer;padding:0;}
							.khm-partner-rfq-review-preview{background:#f8fafc;border:1px solid #dbe4ea;border-radius:8px;padding:12px;display:grid;gap:10px;}
							.khm-partner-rfq-review-preview h4{margin:0;font-size:14px;color:#111827;}
							.khm-partner-rfq-review-terms{font-size:12px;color:#3c434a;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px;display:grid;gap:8px;}
							.khm-partner-rfq-review-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;}
							.khm-partner-rfq-response-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
							/* Affinity block */
							.khm-partner-lead-affinity{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;font-size:11px;padding:6px 10px;border-radius:5px;border:1px solid transparent;}
							.khm-partner-lead-affinity-signals{width:100%;color:#555;font-size:11px;margin-top:2px;}
							.khm-partner-affinity-label{font-weight:700;text-transform:uppercase;letter-spacing:.04em;font-size:10px;}
							.khm-partner-affinity-uplift{font-size:10px;font-weight:600;padding:1px 6px;border-radius:3px;}
							/* Affinity tier colours */
							.khm-partner-affinity-base_affinity{background:#f0f0f0;border-color:#ddd;color:#555;}
							.khm-partner-affinity-base_affinity .khm-partner-affinity-label{color:#555;}
							.khm-partner-affinity-brand_recognition{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32;}
							.khm-partner-affinity-brand_recognition .khm-partner-affinity-label{color:#2e7d32;}
							.khm-partner-affinity-brand_recognition .khm-partner-affinity-uplift{background:#c8e6c9;color:#1b5e20;}
							.khm-partner-affinity-brand_engagement{background:#e3f2fd;border-color:#bbdefb;color:#1565c0;}
							.khm-partner-affinity-brand_engagement .khm-partner-affinity-label{color:#1565c0;}
							.khm-partner-affinity-brand_engagement .khm-partner-affinity-uplift{background:#bbdefb;color:#0d47a1;}
							.khm-partner-affinity-brand_interest{background:#fce4ec;border-color:#f8bbd0;color:#ad1457;}
							.khm-partner-affinity-brand_interest .khm-partner-affinity-label{color:#ad1457;}
							.khm-partner-affinity-brand_interest .khm-partner-affinity-uplift{background:#f8bbd0;color:#880e4f;}
						</style>
						<div class="khm-partner-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Active Matches', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'The buyers below have engaged with your content. Their identity stays anonymised until they explicitly request an introduction, you are buying qualified intent, not cold contact details.', 'khm-membership' ); ?></p>
							</div>
							<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-leads-refresh"><?php esc_html_e( 'Refresh', 'khm-membership' ); ?></button>
						</div>
						<div class="khm-partner-leads-notice" role="status" aria-live="polite"></div>
						<div class="khm-partner-leads-grid"></div>
							<div class="khm-partner-leads-nav"></div>
					<script>
					(function() {
						var sponsorId   = <?php echo (int) ( $sponsor['id'] ?? 0 ); ?>;
						var restBase    = <?php echo wp_json_encode( trailingslashit( rest_url( 'khm/v1/connect' ) ) ); ?>;
						var nonce       = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
						var allowDemoFallback = /(localhost|\.local)$/i.test(window.location.hostname || '');
						var panel       = document.getElementById('khm-partner-leads-panel-' + sponsorId);
						if (!panel || !sponsorId) return;
						var grid   = panel.querySelector('.khm-partner-leads-grid');
						var notice = panel.querySelector('.khm-partner-leads-notice');
						var btn    = panel.querySelector('.khm-partner-leads-refresh');
						function showNotice(msg, ok) {
							notice.textContent = msg;
							notice.className = 'khm-partner-leads-notice ' + (ok ? 'khm-partner-leads-notice-ok' : 'khm-partner-leads-notice-err');
							notice.style.display = 'block';
							setTimeout(function(){ notice.style.display = 'none'; }, 5000);
						}
						function tierClass(tier) {
							if (tier === 'premium') return 'khm-partner-lead-tier-premium';
							if (tier === 'standard') return 'khm-partner-lead-tier-standard';
							if (tier === 'exploratory') return 'khm-partner-lead-tier-exploratory';
							if (tier === 'engaged') return 'khm-partner-lead-tier-engaged';
							return '';
						}
						function tierLabel(tier) {
							var map = {
								'premium': 'Accelerating',
								'standard': 'Assessing',
								'exploratory': 'Exploring',
								'engaged': 'Engaged'
							};
							return map[tier] || tier;
						}
						function rfqStatusLabel(status) {
							if (status === 'sponsor_accepted') return 'Response Submitted';
							if (status === 'intro_requested') return 'Intro Requested';
							if (status === 'introduced') return 'Introduced';
							if (status === 'closed') return 'Closed';
							return 'In Progress';
						}
						function toPercent(value) {
							var n = parseFloat(value);
							if (!isFinite(n) || n <= 0) return 0;
							if (n <= 1) return Math.round(n * 100);
							return Math.round(Math.min(100, n));
						}
						function normalizeCommercialTier(tier, scorePct) {
							var raw = String(tier || '').toLowerCase();
							if (raw === 'engaged') return 'engaged';
							if (raw === 'premium' || raw === 'accelerating' || raw === 'high') return 'premium';
							if (raw === 'standard' || raw === 'assessing' || raw === 'mid' || raw === 'medium') return 'standard';
							if (raw === 'exploratory' || raw === 'exploring' || raw === 'low' || raw === 'base') return 'exploratory';
							if (scorePct >= 75) return 'premium';
							if (scorePct >= 45) return 'standard';
							return 'exploratory';
						}
						function statusLabel(s) {
							var map = {
								detected:'New', offered:'Offered', sponsor_accepted:'Accepted',
								intro_requested:'Intro Requested', introduced:'Introduced',
								rejected:'Declined', expired:'Expired'
							};
							return map[s] || s;
						}
						function formatPrice(cents) {
							return '$' + (cents/100).toFixed(2);
						}
						function safe(value) {
							return String(value || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
						}
						function demoProviders() {
							return [
								{
									id: 9101,
									name: 'Field Service Management Platform',
									comparison_fields: {
										rfq_profile: {
											default_scope: 'fsm_evaluation_poc',
											default_seats: '20_30',
											default_timeframe: '3_months',
												default_cpl_gbp: 325,
											supported_features: ['mobile_app', 'offline_capabilities', 'real_time_reporting'],
											default_estimate_gbp: 120000,
											max_discount_pct: 12
										}
									}
								},
								{
									id: 9102,
									name: 'Spare Parts & Inventory Optimisation Suite',
									comparison_fields: {
										rfq_profile: {
											default_scope: 'mobile_iot_optimisation',
											default_seats: '100_250',
											default_timeframe: '6_months',
												default_cpl_gbp: 145,
											supported_features: ['mobile_app', 'real_time_reporting', 'erp_integration'],
											default_estimate_gbp: 175000,
											max_discount_pct: 8
										}
									}
								}
							];
						}
						// Affinity scoring constants (mirrors SponsorAffinityService.php)
						var AFFINITY_THRESHOLDS = { base: 5, recognition: 20, engagement: 45, interest: 75 };
						var AFFINITY_UPLIFTS    = { base_affinity: 1.00, brand_recognition: 1.15, brand_engagement: 1.30, brand_interest: 1.50 };
						var AFFINITY_LABELS     = { base_affinity: 'Base Affinity', brand_recognition: 'Brand Recognition', brand_engagement: 'Brand Engagement', brand_interest: 'Brand Interest' };
						function resolveAffinityTier(score) {
							if (score >= AFFINITY_THRESHOLDS.interest)    return 'brand_interest';
							if (score >= AFFINITY_THRESHOLDS.engagement)  return 'brand_engagement';
							if (score >= AFFINITY_THRESHOLDS.recognition) return 'brand_recognition';
							if (score >= AFFINITY_THRESHOLDS.base)        return 'base_affinity';
							return null;
						}
						function applyAffinityUplift(baseCents, tier) {
							return Math.round(baseCents * (AFFINITY_UPLIFTS[tier] || 1.00));
						}
						function demoLeads() {
							// Pricing: £75 baseline × tier multiplier (pence)
							// Exploring=7500p  Assessing=15000p  Accelerating=37500p
							//
							// Affinity signals per lead (field service demo):
							//
							// Lead 1 – Brand Interest (score 80):
							//   12 articles (+5+8×3+15=44) + 3 profile views (+10+15=25) + bookmark (+25) = 94 → Brand Interest
							//   Uplift: +50% → £375 × 1.5 = £563
							//
							// Lead 2 – Brand Recognition (score 28):
							//   3 articles (+5+2×3=11) + web click (+15) + 1 profile view (+10) = 36 → Brand Recognition
							//   Uplift: +15% → £150 × 1.15 = £173
							//
							// Lead 3 – Base Affinity (score 5):
							//   1 article (+5) = 5 → Base Affinity, no uplift
							//   Uplift: none → £75
							var leads = [
								{
									id: 7001,
									opportunity_status: 'detected',
									commercial_tier: 'premium',
									person_score: 0.91,
									internal_stage: 'solution',
									unit_price_cents: 37500,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'direct_connection',
									affinity: { score: 94, signals: { articles_read: 12, profile_views: 3, bookmarks: 1 } },
									anonymised_profile: {
										segment: 'Mid-market field service operator (50–500 engineers)',
										region: 'UK & Ireland',
										intent: 'Explicitly opted in to vendor outreach for FSM platform evaluation'
									}
								},
								{
									id: 7002,
									opportunity_status: 'offered',
									commercial_tier: 'standard',
									person_score: 0.76,
									internal_stage: 'diagnosis',
									unit_price_cents: 15000,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'direct_connection',
									affinity: { score: 36, signals: { articles_read: 3, profile_views: 1, website_clicks: 1 } },
									anonymised_profile: {
										segment: 'Industrial distributor reviewing spare parts procurement',
										region: 'DACH',
										intent: 'Actively researching inventory optimisation & parts availability solutions'
									}
								},
								{
									id: 7003,
									opportunity_status: 'intro_requested',
									commercial_tier: 'exploratory',
									person_score: 0.63,
									internal_stage: 'attention',
									unit_price_cents: 7500,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'direct_connection',
									provider_id: 9102,
									affinity: { score: 5, signals: { articles_read: 1 } },
									anonymised_profile: {
										segment: 'Enterprise facilities management group',
										region: 'North America',
										intent: 'Early-stage awareness of connected field service capabilities'
									}
								},
								{
									id: 7005,
									opportunity_status: 'detected',
									commercial_tier: 'engaged',
									person_score: 0.98,
									internal_stage: 'decision',
									unit_price_cents: 150000,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'direct_connection',
									affinity: { score: 86, signals: { articles_read: 7, profile_views: 4, website_clicks: 3, explicit_optins: 1 } },
									anonymised_profile: {
										segment: 'National field services group in final vendor shortlist',
										region: 'UK',
										intent: 'Explicitly opted in for direct sales consultation'
									}
								},
								{
									id: 7004,
									opportunity_status: 'detected',
									commercial_tier: 'standard',
									person_score: 0.54,
									internal_stage: 'diagnosis',
									unit_price_cents: 15000,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'direct_connection',
									affinity: { score: 22, signals: { articles_read: 2, profile_views: 1 } },
									anonymised_profile: {
										segment: 'Regional utilities provider evaluating workforce scheduling',
										region: 'Benelux',
										intent: 'Researching mobile workforce management and scheduling optimisation'
									}
								}
							];
							// Resolve affinity tier and adjusted price for each lead
							leads.forEach(function(lead) {
								if (lead.commercial_tier === 'engaged') {
									// Keep the demo score as-is; just tag the affinity tier
									lead.affinity.tier  = 'brand_interest';
									lead.affinity.label = AFFINITY_LABELS.brand_interest;
									lead.affinity.uplift = 1.00;
									lead.affinity.adjusted_price_cents = lead.unit_price_cents;
									return;
								}
								var tier = resolveAffinityTier(lead.affinity.score);
								lead.affinity.tier   = tier;
								lead.affinity.label  = tier ? AFFINITY_LABELS[tier] : null;
								lead.affinity.uplift = tier ? AFFINITY_UPLIFTS[tier] : 1.00;
								lead.affinity.adjusted_price_cents = tier ? applyAffinityUplift(lead.unit_price_cents, tier) : lead.unit_price_cents;
							});
							return leads;
						}
						function buildAnonymisedSignals(lead) {
							var profile = lead.anonymised_profile || {};
							return [
								{ label: 'Segment', value: profile.segment || 'Undisclosed segment' },
								{ label: 'Region', value: profile.region || 'Undisclosed region' },
								{ label: 'Intent', value: profile.intent || 'Active evaluation signal' }
							];
						}
						var PAGE_SIZE = 3;
						var ENGAGED_OPTION_TWO_ENABLED =
							(window.khmConnectConfig && typeof window.khmConnectConfig.engagedOptionTwoEnabled !== 'undefined')
								? !!window.khmConnectConfig.engagedOptionTwoEnabled
								: true;
						var currentPage = 0;
						var allOpps = [];
						var allProviders = [];
						function bindLeadsInputControls() {
							if (!grid || grid.dataset.navBound === '1') return;
							grid.dataset.navBound = '1';
							grid.setAttribute('tabindex', '0');
							var wheelLocked = false;
							var pointerStartX = 0;
							var pointerStartY = 0;
							var pointerActive = false;
							function goPrev() {
								if (currentPage <= 0) return;
								renderPage(currentPage - 1);
							}
							function goNext() {
								var maxPage = Math.max(0, Math.ceil(allOpps.length / PAGE_SIZE) - 1);
								if (currentPage >= maxPage) return;
								renderPage(currentPage + 1);
							}
							grid.addEventListener('wheel', function(event) {
								if (!allOpps.length || allOpps.length <= PAGE_SIZE) return;
								if (event.target && event.target.closest('input,select,textarea,button')) return;
								var delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
								if (Math.abs(delta) < 16) return;
								event.preventDefault();
								if (wheelLocked) return;
								wheelLocked = true;
								if (delta > 0) goNext(); else goPrev();
								setTimeout(function(){ wheelLocked = false; }, 220);
							}, { passive: false });
							grid.addEventListener('pointerdown', function(event) {
								pointerActive = true;
								pointerStartX = event.clientX;
								pointerStartY = event.clientY;
							});
							grid.addEventListener('pointerup', function(event) {
								if (!pointerActive || !allOpps.length || allOpps.length <= PAGE_SIZE) return;
								pointerActive = false;
								var dx = event.clientX - pointerStartX;
								var dy = event.clientY - pointerStartY;
								if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;
								if (dx < 0) goNext(); else goPrev();
							});
							grid.addEventListener('pointercancel', function() {
								pointerActive = false;
							});
							grid.addEventListener('keydown', function(event) {
								if (!allOpps.length || allOpps.length <= PAGE_SIZE) return;
								if (event.key === 'ArrowLeft') {
									event.preventDefault();
									goPrev();
								}
								if (event.key === 'ArrowRight') {
									event.preventDefault();
									goNext();
								}
							});
						}
						function renderPage(page) {
							currentPage = page;
							var start = page * PAGE_SIZE;
							var pageOpps = allOpps.slice(start, start + PAGE_SIZE);
							var totalPages = Math.ceil(allOpps.length / PAGE_SIZE);
							grid.innerHTML = '';
							pageOpps.forEach(function(o) {
								var scorePct = toPercent(o.person_score);
								var aff = o.affinity || {};
								var rawAffinityPct = toPercent(aff.score);
								var normalizedTier = normalizeCommercialTier(o.commercial_tier, rawAffinityPct || scorePct);
								var affinityPct = normalizedTier === 'engaged'
									? 100
									: (rawAffinityPct || scorePct);
								var inferredAffinityTier = resolveAffinityTier(affinityPct);
								var appliedAffinityTier = aff.tier || inferredAffinityTier;
								var canAccept = (o.opportunity_status === 'detected' || o.opportunity_status === 'offered');
								var acceptedAlready = o.opportunity_status === 'sponsor_accepted' || o.opportunity_status === 'intro_requested' || o.opportunity_status === 'introduced';
								var acceptedAlready = o.opportunity_status === 'sponsor_accepted' || o.opportunity_status === 'intro_requested' || o.opportunity_status === 'introduced';
								var providerOpts = allProviders.map(function(p) {
									return '<option value="' + parseInt(p.id,10) + '"' + (o.provider_id == p.id ? ' selected' : '') + '>' + p.name.replace(/</g,'&lt;') + '</option>';
								}).join('');
								var acceptHtml = '';
								if (canAccept && allProviders.length) {
									if (normalizedTier === 'engaged') {
										var optionTwoBtn = ENGAGED_OPTION_TWO_ENABLED
											? '<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-lead-accept-btn" data-id="' + o.id + '" data-engaged-option="option_2"><?php echo esc_js( __( 'Request Option 2', 'khm-membership' ) ); ?></button>'
											: '';
										acceptHtml = '<div class="khm-partner-lead-accept-form">' +
											'<select class="khm-partner-lead-provider-sel">' + providerOpts + '</select>' +
											'<div class="khm-partner-lead-engaged-ctas">' +
												'<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-partner-lead-accept-btn" data-id="' + o.id + '" data-engaged-option="option_1"><?php echo esc_js( __( 'Request Option 1', 'khm-membership' ) ); ?></button>' +
												optionTwoBtn +
											'</div>' +
										'</div>';
									} else {
										acceptHtml = '<div class="khm-partner-lead-accept-form">' +
											'<select class="khm-partner-lead-provider-sel">' + providerOpts + '</select>' +
											'<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-partner-lead-accept-btn" data-id="' + o.id + '"><?php echo esc_js( __( 'Request', 'khm-membership' ) ); ?></button>' +
										'</div>';
									}
								} else if (acceptedAlready) {
									var matchedProvider = allProviders.find(function(p){ return p.id == o.provider_id; });
									var providerLine = matchedProvider ? '<span style="font-size:12px;font-weight:600;color:#3c434a;">' + safe(matchedProvider.name) + '</span>' : '';
									acceptHtml = providerLine + (providerLine ? '<br>' : '') + '<span class="khm-partner-lead-status" style="color:#1e8c45;">&#10003; ' + statusLabel(o.opportunity_status) + '</span>';
								}
								var card = document.createElement('div');
								card.className = 'khm-partner-lead-card';
								card.dataset.status = o.opportunity_status;
								card.dataset.id = o.id;
								card.dataset.demo = o.is_demo ? '1' : '0';
								var signalHtml = buildAnonymisedSignals(o).map(function(signal){
									return '<span><strong>' + safe(signal.label) + ':</strong> ' + safe(signal.value) + '</span>';
								}).join('');
								var maturityTooltip = 'Confidence score calculated from the buyer\'s tracked engagement events. Factors include: touchpoint type and weight, engagement depth (scroll, dwell time, content completion), activity frequency in the past 30 days, and recency decay (score halves roughly every 6 weeks of inactivity).';
								var tierTooltips = {
									'premium':     'Accelerating — buyer is actively evaluating solutions. High intent, solution-stage engagement. Highest CPL tier (5× baseline).',
									'standard':    'Assessing — buyer is investigating the problem space and researching vendors. Mid-tier CPL (2× baseline).',
									'exploratory': 'Exploring — buyer has shown early category interest. Awareness-stage signals. Entry-level CPL (1× baseline).',
									'engaged':     'Engaged — definitive vendor selection phase with explicit opt-in for direct sales consultation. Commercial options: £1.5K fixed CPL, or £375 + 15% commission.'
								};
								var tierTooltip = tierTooltips[normalizedTier] || '';
								var basePrice = o.unit_price_cents || 0;
								var adjPrice  = aff.adjusted_price_cents || (appliedAffinityTier ? applyAffinityUplift(basePrice, appliedAffinityTier) : basePrice);
								var affinityTooltip = 'Sponsor affinity score — calculated from content interactions: articles read, Connect profile views, website click-throughs, saves, and explicit opt-ins. Higher affinity reflects stronger buyer awareness of your brand and applies a CPL uplift.';
								var priceHtml = (adjPrice !== basePrice)
									? formatPrice(adjPrice) + ' <span class="khm-partner-lead-price-base" title="Base CPL before affinity uplift">(base ' + formatPrice(basePrice) + ')</span> ' + safe(o.pricing_model || '')
									: formatPrice(basePrice) + ' ' + safe(o.pricing_model || '');
								var engagedOptionsHtml = '';
								if (normalizedTier === 'engaged') {
									engagedOptionsHtml =
										'<div class="khm-partner-lead-engaged-options">' +
											'<span><strong>Option 1:</strong> £1.5K (20 x baseline)</span>' +
											'<span><strong>Option 2:</strong> £375 (5 x baseline) + 15% commission*</span>' +
										'</div>';
								} else {
									engagedOptionsHtml = '<div class="khm-partner-lead-engaged-options khm-partner-lead-engaged-options-placeholder" aria-hidden="true"></div>';
								}
								card.innerHTML =
									'<div><span class="khm-partner-lead-tier ' + tierClass(normalizedTier) + '" title="' + tierTooltip + '" style="cursor:help;">' + safe(tierLabel(normalizedTier) || 'unknown') + '</span></div>' +
									'<div class="khm-partner-lead-score-bar"><div class="khm-partner-lead-score-fill" style="width:' + scorePct + '%"></div></div>' +
									'<div class="khm-partner-lead-meta">' +
										'<span title="' + maturityTooltip + '" style="cursor:help;">Maturity: <strong>' + scorePct + '%</strong></span>' +
										'<span title="' + affinityTooltip + '" style="cursor:help;">Affinity: <strong>' + affinityPct + '%</strong></span>' +
									'</div>' +
									'<div class="khm-partner-lead-price"><?php echo esc_js( __( 'Price', 'khm-membership' ) ); ?>: ' + priceHtml + '</div>' +
									engagedOptionsHtml +
									'<div class="khm-partner-lead-anon">' + signalHtml + '</div>' +
									(acceptHtml ? '<div class="khm-partner-lead-actions">' + acceptHtml + '</div>' : '');
								grid.appendChild(card);
							});
							// Pagination nav
							var nav = panel.querySelector('.khm-partner-leads-nav');
							if (totalPages > 1) {
								nav.innerHTML =
									'<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-leads-prev"' + (page === 0 ? ' disabled' : '') + '>&#8592;</button>' +
									'<span class="khm-partner-leads-page-label">Page ' + (page + 1) + ' of ' + totalPages + '</span>' +
									'<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-leads-next"' + (page >= totalPages - 1 ? ' disabled' : '') + '>&#8594;</button>';
								nav.querySelector('.khm-partner-leads-prev').addEventListener('click', function(){ renderPage(currentPage - 1); });
								nav.querySelector('.khm-partner-leads-next').addEventListener('click', function(){ renderPage(currentPage + 1); });
							} else {
								nav.innerHTML = '';
							}
							// ── Active match payment modal ──────────────────────────────────────
							var khmMatchModalEl = document.getElementById('khm-match-payment-modal');
							if (!khmMatchModalEl) {
								khmMatchModalEl = document.createElement('div');
								khmMatchModalEl.id = 'khm-match-payment-modal';
								khmMatchModalEl.style.cssText = 'display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;';
								khmMatchModalEl.innerHTML =
									'<div style="background:#fff;border-radius:8px;padding:32px 28px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18);">' +
										'<h3 style="margin:0 0 8px;font-size:1.2rem;" id="khm-match-modal-title"><?php echo esc_js( __( 'Complete payment to request introduction', 'khm-membership' ) ); ?></h3>' +
										'<p style="margin:0 0 20px;color:#555;font-size:.9rem;" id="khm-match-modal-amount"></p>' +
										'<div id="khm-match-payment-element" style="margin-bottom:20px;"></div>' +
										'<div id="khm-match-payment-error" style="color:#c0392b;margin-bottom:12px;font-size:.85rem;display:none;"></div>' +
										'<div style="display:flex;gap:12px;justify-content:flex-end;">' +
											'<button type="button" id="khm-match-modal-cancel" style="background:none;border:1px solid #ccc;padding:8px 20px;border-radius:4px;cursor:pointer;"><?php echo esc_js( __( 'Cancel', 'khm-membership' ) ); ?></button>' +
											'<button type="button" id="khm-match-modal-pay" style="background:#2271b1;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-weight:600;"><?php echo esc_js( __( 'Pay & Request Introduction', 'khm-membership' ) ); ?></button>' +
										'</div>' +
									'</div>';
								document.body.appendChild(khmMatchModalEl);
							}
							var khmMatchStripe = null, khmMatchElements = null, khmMatchPaymentEl = null;
							var khmMatchCurrentState = {};
							function khmMatchShowModal(oppId, providerId, engagedOption, amount, currency, clientSecret, paymentIntentId, pubKey) {
								khmMatchCurrentState = { oppId: oppId, providerId: providerId, engagedOption: engagedOption, paymentIntentId: paymentIntentId };
								var amountFmt = (amount / 100).toFixed(2);
								var currSym   = (currency || 'gbp').toUpperCase() === 'GBP' ? '£' : (currency || '').toUpperCase() === 'USD' ? '$' : currency.toUpperCase();
								document.getElementById('khm-match-modal-amount').textContent = currSym + amountFmt + ' — ' + <?php echo wp_json_encode( __( 'one-off match fee', 'khm-membership' ) ); ?>;
								document.getElementById('khm-match-payment-error').style.display = 'none';
								document.getElementById('khm-match-modal-pay').disabled = false;
								document.getElementById('khm-match-modal-pay').textContent = <?php echo wp_json_encode( __( 'Pay & Request Introduction', 'khm-membership' ) ); ?>;
								khmMatchModalEl.style.display = 'flex';
								if (typeof Stripe === 'undefined') {
									document.getElementById('khm-match-payment-error').textContent = <?php echo wp_json_encode( __( 'Payment system not available. Please refresh the page.', 'khm-membership' ) ); ?>;
									document.getElementById('khm-match-payment-error').style.display = 'block';
									return;
								}
								khmMatchStripe   = Stripe(pubKey);
								khmMatchElements = khmMatchStripe.elements({ clientSecret: clientSecret, appearance: { theme: 'stripe' } });
								khmMatchPaymentEl = khmMatchElements.create('payment');
								document.getElementById('khm-match-payment-element').innerHTML = '';
								khmMatchPaymentEl.mount('#khm-match-payment-element');
							}
							document.getElementById('khm-match-modal-cancel').addEventListener('click', function() {
								khmMatchModalEl.style.display = 'none';
								khmMatchElements = null;
								khmMatchPaymentEl = null;
							});
							document.getElementById('khm-match-modal-pay').addEventListener('click', function() {
								if (!khmMatchStripe || !khmMatchElements) return;
								var payBtn = this;
								payBtn.disabled = true;
								payBtn.textContent = <?php echo wp_json_encode( __( 'Processing…', 'khm-membership' ) ); ?>;
								document.getElementById('khm-match-payment-error').style.display = 'none';
								khmMatchStripe.confirmPayment({ elements: khmMatchElements, redirect: 'if_required' }).then(function(result) {
									if (result.error) {
										document.getElementById('khm-match-payment-error').textContent = result.error.message;
										document.getElementById('khm-match-payment-error').style.display = 'block';
										payBtn.disabled = false;
										payBtn.textContent = <?php echo wp_json_encode( __( 'Pay & Request Introduction', 'khm-membership' ) ); ?>;
										return;
									}
									// Payment confirmed — call accept endpoint
									var s = khmMatchCurrentState;
									fetch('<?php echo esc_js( rest_url( 'khm/v1/connect/match/' ) ); ?>' + s.oppId + '/accept', {
										method: 'POST',
										headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
										body: JSON.stringify({
											provider_id: s.providerId,
											engaged_option: s.engagedOption || null,
											payment_intent_id: s.paymentIntentId
										})
									}).then(function(r){ return r.json(); }).then(function(d) {
										khmMatchModalEl.style.display = 'none';
										if (d.success) {
											showNotice(<?php echo wp_json_encode( __( 'Payment confirmed — introduction requested. Your inbox will update shortly.', 'khm-membership' ) ); ?>, true);
											loadLeads();
										} else {
											showNotice((d.message || <?php echo wp_json_encode( __( 'Request failed after payment. Please contact support.', 'khm-membership' ) ); ?>), false);
										}
									}).catch(function() {
										khmMatchModalEl.style.display = 'none';
										showNotice(<?php echo wp_json_encode( __( 'Network error after payment. Please contact support.', 'khm-membership' ) ); ?>, false);
									});
								});
							});
							// ── End match payment modal ──────────────────────────────────────────
							// Bind request buttons
							grid.querySelectorAll('.khm-partner-lead-accept-btn').forEach(function(btn) {
								btn.addEventListener('click', function() {
									var engagedOption = btn.dataset.engagedOption || '';
									var leadCard = btn.closest('.khm-partner-lead-card');
									if (leadCard && leadCard.dataset.demo === '1') {
										var allBtns = btn.closest('.khm-partner-lead-actions').querySelectorAll('.khm-partner-lead-accept-btn');
										allBtns.forEach(function(b){ b.disabled = true; });
										btn.textContent = engagedOption
											? (engagedOption === 'option_1' ? <?php echo wp_json_encode( __( 'Option 1 Requested', 'khm-membership' ) ); ?> : <?php echo wp_json_encode( __( 'Option 2 Requested', 'khm-membership' ) ); ?>)
											: <?php echo wp_json_encode( __( 'Requested', 'khm-membership' ) ); ?>;
										return;
									}
									var oppId     = parseInt(btn.dataset.id, 10);
									var sel       = btn.closest('.khm-partner-lead-accept-form').querySelector('.khm-partner-lead-provider-sel');
									var providerId = sel ? parseInt(sel.value, 10) : 0;
									if (!providerId) { showNotice(<?php echo wp_json_encode( __( 'No provider mapping found for this RFQ.', 'khm-membership' ) ); ?>, false); return; }
									btn.disabled = true;
									// Step 1: Create PaymentIntent for this match
									fetch('<?php echo esc_js( rest_url( 'khm/v1/connect/match/' ) ); ?>' + oppId + '/payment-intent', {
										method: 'POST',
										headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
										body: JSON.stringify({})
									}).then(function(r){ return r.json(); }).then(function(d) {
										btn.disabled = false;
										if (d.success && d.client_secret) {
											khmMatchShowModal(oppId, providerId, engagedOption, d.amount, d.currency, d.client_secret, d.payment_intent_id, d.publishable_key);
										} else {
											showNotice((d.message || <?php echo wp_json_encode( __( 'Unable to initiate payment. Please try again.', 'khm-membership' ) ); ?>), false);
										}
									}).catch(function(){ btn.disabled = false; showNotice(<?php echo wp_json_encode( __( 'Network error — please try again.', 'khm-membership' ) ); ?>, false); });
								});
							});
						}
						function renderCards(opps, providers) {
							bindLeadsInputControls();
							allOpps      = opps;
							allProviders = providers;
							currentPage  = 0;
							if (!opps.length) {
								grid.innerHTML = '<p class="khm-partner-leads-empty"><?php echo esc_js( __( 'No active matches at this time.', 'khm-membership' ) ); ?></p>';
								var nav = panel.querySelector('.khm-partner-leads-nav');
								if (nav) nav.innerHTML = '';
								return;
							}
							renderPage(0);
						}
						function loadLeads() {
							grid.innerHTML = '<p class="khm-partner-leads-empty"><?php echo esc_js( __( 'Loading…', 'khm-membership' ) ); ?></p>';
							renderCards(demoLeads(), demoProviders());
						}
						btn.addEventListener('click', loadLeads);
						loadLeads();
					})();
					</script>
					<section class="khm-partner-connect-panel khm-partner-rfq-requests-panel khm-partner-connect-span-full" id="khm-partner-rfq-requests-panel-<?php echo (int) ( $sponsor['id'] ?? 0 ); ?>">
						<div class="khm-partner-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'RFQ Requests', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Buyers with formal procurement processes and defined requirements. RFQ scope details below.', 'khm-membership' ); ?></p>
							</div>
							<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-rfq-refresh"><?php esc_html_e( 'Refresh', 'khm-membership' ); ?></button>
						</div>
						<div class="khm-partner-rfq-notice" role="status" aria-live="polite"></div>
						<div class="khm-partner-rfq-grid"></div>
							<div class="khm-partner-rfq-nav"></div>
					<script>
					(function() {
						var sponsorId   = <?php echo (int) ( $sponsor['id'] ?? 0 ); ?>;
						var restBase    = <?php echo wp_json_encode( trailingslashit( rest_url( 'khm/v1/connect' ) ) ); ?>;
						var nonce       = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
						var allowDemoFallback = /(localhost|\.local)$/i.test(window.location.hostname || '');
						var panel       = document.getElementById('khm-partner-rfq-requests-panel-' + sponsorId);
						if (!panel || !sponsorId) return;
						var grid   = panel.querySelector('.khm-partner-rfq-grid');
						var notice = panel.querySelector('.khm-partner-rfq-notice');
						var btn    = panel.querySelector('.khm-partner-rfq-refresh');
						function showNotice(msg, ok) {
							notice.textContent = msg;
							notice.className = 'khm-partner-rfq-notice ' + (ok ? 'khm-partner-rfq-notice-ok' : 'khm-partner-rfq-notice-err');
							notice.style.display = 'block';
							setTimeout(function(){ notice.style.display = 'none'; }, 5000);
						}
						function tierClass(tier) {
							if (tier === 'premium') return 'khm-partner-lead-tier-premium';
							if (tier === 'standard') return 'khm-partner-lead-tier-standard';
							if (tier === 'exploratory') return 'khm-partner-lead-tier-exploratory';
							if (tier === 'engaged') return 'khm-partner-lead-tier-engaged';
							return '';
						}
						function tierLabel(tier) {
							var map = {
								'premium': 'Accelerating',
								'standard': 'Assessing',
								'exploratory': 'Exploring',
								'engaged': 'Engaged'
							};
							return map[tier] || tier;
						}
						function safe(value) {
							return String(value || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
						}
						function demoProviders() {
							return [
								{
									id: 9101,
									name: 'Field Service Management Platform',
									comparison_fields: {
										rfq_profile: {
											default_scope: 'fsm_evaluation_poc',
											default_seats: '20_30',
											default_timeframe: '3_months',
												default_cpl_gbp: 325,
											supported_features: ['mobile_app', 'offline_capabilities', 'real_time_reporting'],
											default_estimate_gbp: 120000,
											max_discount_pct: 12
										}
									}
								},
								{
									id: 9102,
									name: 'Spare Parts & Inventory Optimisation Suite',
									comparison_fields: {
										rfq_profile: {
											default_scope: 'mobile_iot_optimisation',
											default_seats: '100_250',
											default_timeframe: '6_months',
												default_cpl_gbp: 145,
											supported_features: ['mobile_app', 'real_time_reporting', 'erp_integration'],
											default_estimate_gbp: 175000,
											max_discount_pct: 8
										}
									}
								}
							];
						}
						function buildAnonymisedSignals(lead) {
							var profile = lead.anonymised_profile || {};
							return [
								{ label: 'Segment', value: profile.segment || 'Undisclosed segment' },
								{ label: 'Region', value: profile.region || 'Undisclosed region' },
								{ label: 'Intent', value: profile.intent || 'Active evaluation signal' }
							];
						}
						function demoRfqLeads() {
							// Demo RFQ Requests: Buyers with defined procurement scope and timeline
							// RFQ metadata includes: scope, timeline, budget_range, deadline, competing_vendors
							var rfqLeads = [
								{
									id: 8001,
									opportunity_status: 'detected',
									commercial_tier: 'engaged',
									person_score: 0.85,
									internal_stage: 'decision',
									unit_price_cents: 150000,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'rfq_request',
									provider_id: 9101,
									affinity: { score: 100, signals: {} },
									rfq_metadata: {
										scope: 'Complete FSM platform evaluation and POC (proof of concept) with 20-30 user seats for 3 months. Include mobile app, offline capabilities, and real-time reporting dashboard.',
										timeline: 'RFQ issued Q2 2026; vendor demos Q3; contract signature Q3/Q4',
										budget_range: '£80K–£150K annual license + £20K implementation',
										deadline: '2026-06-30',
										competing_vendors: ['Competitor A (FSM Leader)', 'Competitor B (Emerging)', 'Competitor C (Regional)']
									},
									anonymised_profile: {
										segment: 'Global logistics and supply chain operator (2,000+ field engineers)',
										region: 'EMEA HQ',
										intent: 'Formal procurement process; evaluation of 3–4 shortlisted vendors'
									}
								},
								{
									id: 8002,
									opportunity_status: 'detected',
									commercial_tier: 'engaged',
									person_score: 0.78,
									internal_stage: 'decision',
									unit_price_cents: 150000,
									pricing_model: 'CPL',
									is_demo: true,
									request_type: 'rfq_request',
									provider_id: 9102,
									affinity: { score: 100, signals: {} },
									rfq_metadata: {
										scope: 'Mobile-first field operations platform with advanced analytics, IoT sensor integration, and AI-powered route optimization. Integration with existing ERP (SAP).',
										timeline: 'RFQ responses due 2026-07-15; shortlist narrowed by Aug; final decision Sept 2026',
										budget_range: '£150K–£300K annual + implementation',
										deadline: '2026-07-15',
										competing_vendors: ['Competitor D', 'Competitor E', 'Incumbent Provider']
									},
									anonymised_profile: {
										segment: 'Manufacturing group with 500+ field technicians across Europe',
										region: 'Nordics',
										intent: 'Multi-vendor evaluation; 2-year commitment with growth optionality'
									}
								}
							];
							// Set affinity for RFQ (always 100%, no uplift)
							rfqLeads.forEach(function(lead) {
								lead.affinity.tier  = 'brand_interest';
								lead.affinity.label = 'Brand Interest';
								lead.affinity.uplift = 1.00;
								lead.affinity.adjusted_price_cents = lead.unit_price_cents;
							});
							return rfqLeads;
						}
						function buildRfqMetadata(rfqMeta) {
							if (!rfqMeta) return [];
							return [
								{ label: 'Scope', value: rfqMeta.scope || '' },
								{ label: 'Timeline', value: rfqMeta.timeline || '' },
								{ label: 'Budget', value: rfqMeta.budget_range || '' },
								{ label: 'Deadline', value: rfqMeta.deadline || '' },
								{ label: 'Competitors', value: (rfqMeta.competing_vendors || []).join(', ') || '' }
							].filter(function(m){ return m.value; });
						}
						function optionHtml(options, selectedValue) {
							return options.map(function(opt) {
								var selected = String(opt.value) === String(selectedValue) ? ' selected' : '';
								return '<option value="' + safe(opt.value) + '"' + selected + '>' + safe(opt.label) + '</option>';
							}).join('');
						}
						function featureCheckboxHtml(features, selected) {
							selected = selected || [];
							var matched = features.filter(function(feature) {
								return selected.indexOf(feature.value) !== -1;
							});
							var hiddenInputs = matched.map(function(feature) {
								return '<input type="hidden" class="khm-partner-rfq-feature" value="' + safe(feature.value) + '" />';
							}).join('');
							var items = matched.map(function(feature) {
								return '<li>' + safe(feature.label) + '</li>';
							}).join('');
							return hiddenInputs + '<ul class="khm-partner-rfq-summary-sublist">' + items + '</ul>';
						}
						function seatRangeForValue(value) {
							switch (String(value || '')) {
								case '50_100': return { min: 50, max: 100, label: '50-100 licences' };
								case '100_250': return { min: 100, max: 250, label: '100-250 licences' };
								case '500_plus': return { min: 500, max: 500, label: '500+ licences' };
								case '20_30':
								default: return { min: 20, max: 30, label: '20-30 licences' };
							}
						}
						function timeframeLabelForValue(value) {
							switch (String(value || '')) {
								case '6_months': return '6 months';
								case '12_months': return '12 months';
								case '3_months':
								default: return '3 months';
							}
						}
						function deriveServiceItems(meta) {
							var combined = [meta.scope, meta.timeline, meta.budget_range, meta.intent].join(' ').toLowerCase();
							var items = [];
							if (combined.indexOf('implementation') !== -1) items.push('Implementation');
							if (combined.indexOf('integration') !== -1 || combined.indexOf('erp') !== -1 || combined.indexOf('sap') !== -1) items.push('Integration services');
							if (combined.indexOf('migration') !== -1) items.push('Migration');
							if (combined.indexOf('training') !== -1) items.push('Training');
							if (combined.indexOf('support') !== -1 || combined.indexOf('managed') !== -1) items.push('Support');
							if (!items.length && combined.indexOf('poc') !== -1) items.push('Implementation');
							return items.filter(function(item, index, arr) {
								return arr.indexOf(item) === index;
							});
						}
						function parseBudgetMidpointGbp(rangeText) {
							var text = String(rangeText || '');
							var m = text.match(/£\s*(\d+)\s*K\s*[\u2013\-]\s*£\s*(\d+)\s*K/i);
							if (m) {
								return Math.round(((parseInt(m[1], 10) + parseInt(m[2], 10)) / 2) * 1000);
							}
							var single = text.match(/£\s*(\d+)\s*K/i);
							if (single) {
								return parseInt(single[1], 10) * 1000;
							}
							return 0;
						}
						function deriveDefaultCplGbp(profile, seatsValue, annualEstimateGbp) {
							var profileCpl = Number(profile && profile.default_cpl_gbp ? profile.default_cpl_gbp : 0);
							if (profileCpl > 0) {
								return profileCpl;
							}
							var seatRange = seatRangeForValue(seatsValue);
							var averageSeats = seatRange.max ? (seatRange.min + seatRange.max) / 2 : seatRange.min;
							var annualEstimate = Number(annualEstimateGbp || 0);
							if (annualEstimate > 0 && averageSeats > 0) {
								return annualEstimate / averageSeats / 12;
							}
							return 0;
						}
						function deriveMiniRfqDraft(o, provider) {
							var meta = o.rfq_metadata || {};
							var providerProfile = provider && provider.comparison_fields && provider.comparison_fields.rfq_profile
								? provider.comparison_fields.rfq_profile
								: {};
							var scopeText = String(meta.scope || '').toLowerCase();
							var timelineText = String(meta.timeline || '').toLowerCase();
							var pilotText = String(meta.pilot_scheme || '').toLowerCase();
							var scopeValue = 'fsm_evaluation_poc';
							if (scopeText.indexOf('mobile-first') !== -1 || scopeText.indexOf('iot') !== -1) {
								scopeValue = 'mobile_iot_optimisation';
							}
							var seatsValue = '20_30';
							if (scopeText.indexOf('500+') !== -1 || scopeText.indexOf('500 +') !== -1) seatsValue = '500_plus';
							else if (scopeText.indexOf('100-250') !== -1 || scopeText.indexOf('100 to 250') !== -1) seatsValue = '100_250';
							else if (scopeText.indexOf('50-100') !== -1 || scopeText.indexOf('50 to 100') !== -1) seatsValue = '50_100';
							var timeframeValue = '3_months';
							if (scopeText.indexOf('6 months') !== -1 || timelineText.indexOf('q4') !== -1) timeframeValue = '6_months';
							if (scopeText.indexOf('12 months') !== -1 || scopeText.indexOf('1 year') !== -1) timeframeValue = '12_months';
							var pilotRequested = false;
							if (scopeText.indexOf('pilot') !== -1 || timelineText.indexOf('pilot') !== -1) {
								pilotRequested = true;
							}
							if (pilotText === 'yes' || pilotText === 'true' || pilotText === '1' || pilotText === 'required') {
								pilotRequested = true;
							}
							var pilotSchemeValue = 'no';
							var profilePilotScheme = String(providerProfile.pilot_scheme_available || '').toLowerCase();
							if (profilePilotScheme === 'yes' || profilePilotScheme === 'no') {
								pilotSchemeValue = profilePilotScheme;
							} else if (String(providerProfile.default_scope || '') === 'pilot_scheme') {
								pilotSchemeValue = 'yes';
							}
							var features = [];
							if (scopeText.indexOf('mobile') !== -1) features.push('mobile_app');
							if (scopeText.indexOf('offline') !== -1) features.push('offline_capabilities');
							if (scopeText.indexOf('reporting') !== -1 || scopeText.indexOf('dashboard') !== -1) features.push('real_time_reporting');
							if (scopeText.indexOf('erp') !== -1 || scopeText.indexOf('sap') !== -1) features.push('erp_integration');
							if (!features.length) features = ['mobile_app', 'real_time_reporting'];
							var annualEstimateGbp = providerProfile.default_estimate_gbp || parseBudgetMidpointGbp(meta.budget_range) || 120000;
							return {
								scope_value: providerProfile.default_scope || scopeValue,
								seats_value: providerProfile.default_seats || seatsValue,
								timeframe_value: providerProfile.default_timeframe || timeframeValue,
								pilot_requested: pilotRequested,
								pilot_scheme_available: pilotSchemeValue,
								service_items: deriveServiceItems(meta),
								default_cpl_gbp: deriveDefaultCplGbp(providerProfile, providerProfile.default_seats || seatsValue, annualEstimateGbp),
								features: (Array.isArray(providerProfile.supported_features) && providerProfile.supported_features.length) ? providerProfile.supported_features : features,
								provisional_estimate_gbp: annualEstimateGbp,
								seller_discount_pct: 0,
								max_discount_pct: Math.max(0, Math.min(30, parseInt(providerProfile.max_discount_pct, 10) || 30))
							};
						}
						function formatGbp(value) {
							var amount = Number(value || 0);
							return '£' + amount.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
						}
						function deriveOpportunityBaselineGbp(unitPriceCents, commercialTier) {
							var unitPriceGbp = Number(unitPriceCents || 0) / 100;
							if (unitPriceGbp <= 0) return 0;
							var tier = String(commercialTier || '').toLowerCase();
							if (tier === 'engaged') {
								return unitPriceGbp / 20;
							}
							return unitPriceGbp;
						}
						function calculateRfqPricing(cplValue, seatRange, commissionRate, offerPlatformDiscount, opportunityBaselineGbp) {
							var cpl = Number(cplValue || 0);
							var rate = Math.max(5, Math.min(25, parseInt(commissionRate, 10) || 5));
							var baseline = Math.max(0, Number(opportunityBaselineGbp || 0));
							var annualBaseMin = seatRange.min * cpl * 12;
							var annualBaseMax = seatRange.max * cpl * 12;
							var annualBaseMid = (annualBaseMin + annualBaseMax) / 2;
							var discountedMin = offerPlatformDiscount ? annualBaseMin * (1 - (rate / 100)) : annualBaseMin;
							var discountedMax = offerPlatformDiscount ? annualBaseMax * (1 - (rate / 100)) : annualBaseMax;
							var totalCommercialMid = offerPlatformDiscount ? annualBaseMid * (rate / 100) : 0;
							var estimatedBuyerDiscount = totalCommercialMid * 0.5;
							var estimatedPlatformCommission = totalCommercialMid * 0.5;
							var flatFee = baseline * (offerPlatformDiscount ? 5 : 20);
							return {
								rate: rate,
								flatFeeBasis: baseline,
								annualBaseMin: annualBaseMin,
								annualBaseMax: annualBaseMax,
								annualBaseMid: annualBaseMid,
								discountedMin: discountedMin,
								discountedMax: discountedMax,
								estimatedBuyerDiscount: estimatedBuyerDiscount,
								estimatedPlatformCommission: estimatedPlatformCommission,
								flatFee: flatFee
							};
						}
						function ensureRfqReviewModal() {
							var modal = document.getElementById('khm-partner-rfq-review-modal');
							if (modal) return modal;
							modal = document.createElement('div');
							modal.id = 'khm-partner-rfq-review-modal';
							modal.className = 'khm-partner-rfq-review-modal';
							modal.innerHTML =
								'<div class="khm-partner-rfq-review-dialog" role="dialog" aria-modal="true" aria-labelledby="khm-partner-rfq-review-title">' +
									'<div class="khm-partner-rfq-review-head">' +
										'<h3 id="khm-partner-rfq-review-title"><?php echo esc_js( __( 'Review Proposal Terms', 'khm-membership' ) ); ?></h3>' +
										'<button type="button" class="khm-partner-rfq-review-close" aria-label="<?php echo esc_js( __( 'Close', 'khm-membership' ) ); ?>">&times;</button>' +
									'</div>' +
									'<div class="khm-partner-rfq-review-preview"></div>' +
									'<div class="khm-partner-rfq-review-terms"></div>' +
									'<div class="khm-partner-rfq-review-actions">' +
										'<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-rfq-review-cancel"><?php echo esc_js( __( 'Back', 'khm-membership' ) ); ?></button>' +
										'<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-partner-rfq-review-confirm"><?php echo esc_js( __( 'Send Proposal', 'khm-membership' ) ); ?></button>' +
									'</div>' +
								'</div>';
							document.body.appendChild(modal);
							var closeModal = function() {
								modal.classList.remove('is-open');
								modal.dataset.confirmHandler = '';
							};
							modal.querySelector('.khm-partner-rfq-review-close').addEventListener('click', closeModal);
							modal.querySelector('.khm-partner-rfq-review-cancel').addEventListener('click', closeModal);
							modal.addEventListener('click', function(event) {
								if (event.target === modal) closeModal();
							});
							return modal;
						}
						function closeAllRfqTooltips(exceptNode) {
							document.querySelectorAll('.khm-partner-rfq-tooltip.is-open').forEach(function(node) {
								if (exceptNode && node === exceptNode) return;
								node.classList.remove('is-open');
								var btn = node.querySelector('.khm-partner-rfq-tooltip-btn');
								if (btn) btn.setAttribute('aria-expanded', 'false');
							});
						}
						document.addEventListener('click', function(event) {
							var btn = event.target.closest('.khm-partner-rfq-tooltip-btn');
							if (btn) {
								event.preventDefault();
								event.stopPropagation();
								var wrap = btn.closest('.khm-partner-rfq-tooltip');
								if (!wrap) return;
								var isOpen = wrap.classList.contains('is-open');
								closeAllRfqTooltips();
								if (!isOpen) {
									wrap.classList.add('is-open');
									btn.setAttribute('aria-expanded', 'true');
								}
								return;
							}
							if (!event.target.closest('.khm-partner-rfq-tooltip')) {
								closeAllRfqTooltips();
							}
						});
						document.addEventListener('keydown', function(event) {
							if (event.key === 'Escape') {
								closeAllRfqTooltips();
							}
						});
						var PAGE_SIZE = 3;
						var currentPage = 0;
						var allRfqs = [];
						var allProviders = [];
						function bindRfqInputControls() {
							if (!grid || grid.dataset.navBound === '1') return;
							grid.dataset.navBound = '1';
							grid.setAttribute('tabindex', '0');
							var wheelLocked = false;
							var pointerStartX = 0;
							var pointerStartY = 0;
							var pointerActive = false;
							function goPrev() {
								if (currentPage <= 0) return;
								renderPage(currentPage - 1);
							}
							function goNext() {
								var maxPage = Math.max(0, Math.ceil(allRfqs.length / PAGE_SIZE) - 1);
								if (currentPage >= maxPage) return;
								renderPage(currentPage + 1);
							}
							grid.addEventListener('wheel', function(event) {
								if (!allRfqs.length || allRfqs.length <= PAGE_SIZE) return;
								if (event.target && event.target.closest('input,select,textarea,button')) return;
								var delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
								if (Math.abs(delta) < 16) return;
								event.preventDefault();
								if (wheelLocked) return;
								wheelLocked = true;
								if (delta > 0) goNext(); else goPrev();
								setTimeout(function(){ wheelLocked = false; }, 220);
							}, { passive: false });
							grid.addEventListener('pointerdown', function(event) {
								pointerActive = true;
								pointerStartX = event.clientX;
								pointerStartY = event.clientY;
							});
							grid.addEventListener('pointerup', function(event) {
								if (!pointerActive || !allRfqs.length || allRfqs.length <= PAGE_SIZE) return;
								pointerActive = false;
								var dx = event.clientX - pointerStartX;
								var dy = event.clientY - pointerStartY;
								if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;
								if (dx < 0) goNext(); else goPrev();
							});
							grid.addEventListener('pointercancel', function() {
								pointerActive = false;
							});
							grid.addEventListener('keydown', function(event) {
								if (!allRfqs.length || allRfqs.length <= PAGE_SIZE) return;
								if (event.key === 'ArrowLeft') {
									event.preventDefault();
									goPrev();
								}
								if (event.key === 'ArrowRight') {
									event.preventDefault();
									goNext();
								}
							});
						}
						function renderPage(page) {
							currentPage = page;
							var start = page * PAGE_SIZE;
							var pageRfqs = allRfqs.slice(start, start + PAGE_SIZE);
							var totalPages = Math.ceil(allRfqs.length / PAGE_SIZE);
							grid.innerHTML = '';
							pageRfqs.forEach(function(o) {
								var scorePct = Math.round((o.person_score || 0) * 100);
								var canAccept = (o.opportunity_status === 'detected' || o.opportunity_status === 'offered');
								var acceptedAlready = o.opportunity_status === 'sponsor_accepted' || o.opportunity_status === 'intro_requested' || o.opportunity_status === 'introduced';
								var acceptHtml = '';
								if (canAccept && allProviders.length) {
									var selectedProviderId = o.provider_id || (allProviders[0] && allProviders[0].id) || 0;
									var matchedProvider = allProviders.find(function(p){ return parseInt(p.id, 10) === parseInt(selectedProviderId, 10); }) || null;
									var miniRfq = deriveMiniRfqDraft(o, matchedProvider);
									var scopeOptions = [
										{ value: 'pilot_scheme', label: 'Structured pilot scheme (time-boxed, defined success criteria)' },
										{ value: 'fsm_evaluation_poc', label: 'Complete FSM platform evaluation and POC' },
										{ value: 'mobile_iot_optimisation', label: 'Mobile-first FSM with IoT and route optimisation' },
										{ value: 'workforce_scheduling_upgrade', label: 'Workforce scheduling and dispatch modernisation' }
									];
									var seatOptions = [
										{ value: '20_30', label: '20-30 user seats' },
										{ value: '50_100', label: '50-100 user seats' },
										{ value: '100_250', label: '100-250 user seats' },
										{ value: '500_plus', label: '500+ user seats' }
									];
									var featureOptions = [
										{ value: 'mobile_app', label: 'Mobile app' },
										{ value: 'offline_capabilities', label: 'Offline capabilities' },
										{ value: 'real_time_reporting', label: 'Real-time reporting dashboard' },
										{ value: 'erp_integration', label: 'ERP integration' }
									];
									var baseCpl = Number(miniRfq.default_cpl_gbp || 0);
										var opportunityBaselineGbp = deriveOpportunityBaselineGbp(o.unit_price_cents, o.commercial_tier);
									var scopeLabel = (scopeOptions.find(function(s){ return s.value === miniRfq.scope_value; }) || {}).label || miniRfq.scope_value || '';
									var seatRange = seatRangeForValue(miniRfq.seats_value);
									var commissionRateDefault = Math.max(5, Math.min(25, parseInt(miniRfq.max_discount_pct, 10) || 10));
									var pilotRequestedHtml = miniRfq.pilot_requested ? '<li><strong><?php echo esc_js( __( 'Pilot requested:', 'khm-membership' ) ); ?></strong> <?php echo esc_js( __( 'Yes', 'khm-membership' ) ); ?></li>' : '';
									var servicesHtml = (miniRfq.service_items || []).length
										? '<li><strong><?php echo esc_js( __( 'Services:', 'khm-membership' ) ); ?></strong><ul class="khm-partner-rfq-summary-sublist">' + miniRfq.service_items.map(function(item){ return '<li>' + safe(item) + '</li>'; }).join('') + '</ul></li>'
										: '';
									acceptHtml = '<div class="khm-partner-rfq-accept-form">' +
										'<input type="hidden" class="khm-partner-rfq-provider-id" value="' + parseInt(selectedProviderId, 10) + '" />' +
										'<input type="hidden" class="khm-partner-rfq-opportunity-baseline" value="' + opportunityBaselineGbp.toFixed(2) + '" />' +
										'<div class="khm-partner-lead-engaged-ctas">' +
											'<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-partner-rfq-open-form-btn" data-id="' + o.id + '"><?php echo esc_js( __( 'Open Proposal Builder', 'khm-membership' ) ); ?></button>' +
										'</div>' +
										'<div class="khm-partner-rfq-response-workflow" hidden>' +
											'<div class="khm-partner-rfq-response-hint"><?php echo esc_js( __( 'Pricing is based on the matched opportunity and your seller profile. Review the commercial terms before sending.', 'khm-membership' ) ); ?></div>' +
											'<div class="khm-partner-rfq-response-grid">' +
												'<div class="khm-partner-rfq-response-field khm-partner-rfq-summary">' +
													'<input type="hidden" class="khm-partner-rfq-response-scope" value="' + miniRfq.scope_value + '" />' +
													'<input type="hidden" class="khm-partner-rfq-response-seats" value="' + miniRfq.seats_value + '" />' +
													'<input type="hidden" class="khm-partner-rfq-seat-min" value="' + seatRange.min + '" />' +
													'<input type="hidden" class="khm-partner-rfq-seat-max" value="' + seatRange.max + '" />' +
													'<input type="hidden" class="khm-partner-rfq-pilot-requested" value="' + (miniRfq.pilot_requested ? 'yes' : 'no') + '" />' +
													'<h4><?php echo esc_js( __( 'Engagement Summary', 'khm-membership' ) ); ?></h4>' +
													'<ul class="khm-partner-rfq-summary-list">' +
														'<li><strong><?php echo esc_js( __( 'Solution:', 'khm-membership' ) ); ?></strong> ' + scopeLabel + '</li>' +
														'<li><strong><?php echo esc_js( __( 'Licences:', 'khm-membership' ) ); ?></strong> ' + seatRange.label + '</li>' +
														pilotRequestedHtml +
														'<li><strong><?php echo esc_js( __( 'Features:', 'khm-membership' ) ); ?></strong><div class="khm-partner-rfq-feature-grid">' + featureCheckboxHtml(featureOptions, miniRfq.features) + '</div></li>' +
														servicesHtml +
													'</ul>' +
												'</div>' +
												'<div class="khm-partner-rfq-response-field">' +
													'<label><?php echo esc_js( __( 'Cost per licence / month (£)', 'khm-membership' ) ); ?></label>' +
													'<input type="number" min="1" step="0.01" class="khm-partner-rfq-response-cpl" value="' + baseCpl.toFixed(2) + '" />' +
													'<p class="khm-partner-rfq-estimate-caption"><?php echo esc_js( __( 'Editable commercial price seeded from the matched seller rate.', 'khm-membership' ) ); ?></p>' +
												'</div>' +
												'<div class="khm-partner-rfq-response-field">' +
													'<label><?php echo esc_js( __( '12-month estimate', 'khm-membership' ) ); ?></label>' +
													'<div class="khm-partner-rfq-calc-card">' +
														'<div class="khm-partner-rfq-estimate-value khm-partner-rfq-estimate-range"></div>' +
														'<div class="khm-partner-rfq-estimate-caption"><?php echo esc_js( __( 'Calculated from the licence range in the RFQ over 12 months.', 'khm-membership' ) ); ?></div>' +
													'</div>' +
												'</div>' +
												'<div class="khm-partner-rfq-response-field">' +
													'<label class="khm-partner-rfq-toggle">' +
														'<input type="checkbox" class="khm-partner-rfq-discount-toggle" />' +
														'<span><?php echo esc_js( __( 'Offer platform discount', 'khm-membership' ) ); ?></span>' +
													'</label>' +
												'</div>' +
												'<div class="khm-partner-rfq-response-field">' +
													'<div class="khm-partner-rfq-discount-controls" hidden>' +
														'<label><?php echo esc_js( __( 'Discount / commission rate (%)', 'khm-membership' ) ); ?></label>' +
														'<input type="range" min="5" max="25" step="1" value="' + commissionRateDefault + '" class="khm-partner-rfq-commission-rate" />' +
														'<div class="khm-partner-rfq-discount-readout"><span><?php echo esc_js( __( 'Selected rate:', 'khm-membership' ) ); ?> <strong class="khm-partner-rfq-rate-value">' + commissionRateDefault + '%</strong></span><span><?php echo esc_js( __( 'This total rate is split 50/50 between estimated buyer discount and estimated platform commission.', 'khm-membership' ) ); ?></span></div>' +
													'</div>' +
												'</div>' +
												'<div class="khm-partner-rfq-response-field">' +
													'<label><?php echo esc_js( __( 'Commercial breakdown', 'khm-membership' ) ); ?></label>' +
													'<div class="khm-partner-rfq-calc-card">' +
														'<div class="khm-partner-rfq-breakdown-row"><span><?php echo esc_js( __( 'Payable today', 'khm-membership' ) ); ?></span><strong class="khm-partner-rfq-flat-fee"></strong></div>' +
														'<div class="khm-partner-rfq-commission-breakdown" hidden>' +
															'<div class="khm-partner-rfq-breakdown-row"><span class="khm-partner-rfq-tooltip"><?php echo esc_js( __( 'Estimated buyer discount', 'khm-membership' ) ); ?><button type="button" class="khm-partner-rfq-tooltip-btn" aria-label="<?php echo esc_js( __( 'Buyer discount help', 'khm-membership' ) ); ?>">i</button><span class="khm-partner-rfq-tooltip-bubble"><?php echo esc_js( __( 'Deductible from the buyer\'s first invoice with your company.', 'khm-membership' ) ); ?></span></span><strong class="khm-partner-rfq-client-saving"></strong></div>' +
															'<div class="khm-partner-rfq-breakdown-row"><span class="khm-partner-rfq-tooltip"><?php echo esc_js( __( 'Estimated platform commission', 'khm-membership' ) ); ?><button type="button" class="khm-partner-rfq-tooltip-btn" aria-label="<?php echo esc_js( __( 'Platform commission help', 'khm-membership' ) ); ?>">i</button><span class="khm-partner-rfq-tooltip-bubble"><?php echo esc_js( __( 'Debited from your account when the buyer confirms agreement so they can claim discount.', 'khm-membership' ) ); ?></span></span><strong class="khm-partner-rfq-platform-commission"></strong></div>' +
														'</div>' +
													'</div>' +
												'</div>' +
												'<div class="khm-partner-rfq-response-field">' +
													'<label><?php echo esc_js( __( 'Note to send with this proposal (optional)', 'khm-membership' ) ); ?></label>' +
													'<textarea class="khm-partner-rfq-response-notes" placeholder="<?php echo esc_js( __( 'Any caveats, assumptions, or implementation notes…', 'khm-membership' ) ); ?>"></textarea>' +
												'</div>' +
											'</div>' +
											'<div class="khm-partner-rfq-response-actions">' +
												'<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-partner-rfq-submit-btn" data-id="' + o.id + '"><?php echo esc_js( __( 'Review Proposal Terms', 'khm-membership' ) ); ?></button>' +
												'<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-rfq-cancel-btn"><?php echo esc_js( __( 'Cancel', 'khm-membership' ) ); ?></button>' +
											'</div>' +
										'</div>' +
									'</div>';
								} else if (acceptedAlready) {
									var matchedProvider = allProviders.find(function(p){ return p.id == o.provider_id; });
									var providerLine = matchedProvider ? '<span style="font-size:12px;font-weight:600;color:#3c434a;">' + safe(matchedProvider.name) + '</span>' : '';
									acceptHtml = providerLine + (providerLine ? '<br>' : '') + '<span class="khm-partner-lead-status" style="color:#1e8c45;">&#10003; ' + rfqStatusLabel(o.opportunity_status) + '</span>';
								}
								var card = document.createElement('div');
								card.className = 'khm-partner-rfq-card';
								card.dataset.status = o.opportunity_status;
								card.dataset.id = o.id;
								card.dataset.demo = o.is_demo ? '1' : '0';
								var signalHtml = buildAnonymisedSignals(o).map(function(signal){
									return '<span><strong>' + safe(signal.label) + ':</strong> ' + safe(signal.value) + '</span>';
								}).join('');
								var rfqMetadata = buildRfqMetadata(o.rfq_metadata);
								var rfqMetadataHtml = rfqMetadata.map(function(m){
									return '<span><strong>' + safe(m.label) + ':</strong> ' + safe(m.value) + '</span>';
								}).join('');
								var maturityTooltip = 'Confidence score calculated from RFQ issuance signals and procurement stage signals.';
								var tierTooltip = 'Engaged — RFQ Procurement: Formal vendor selection process with multiple buyers and defined requirements.';
								card.innerHTML =
									'<div><span class="khm-partner-lead-tier ' + tierClass(o.commercial_tier) + '" title="' + tierTooltip + '" style="cursor:help;">RFQ Request</span></div>' +
									'<div class="khm-partner-lead-score-bar"><div class="khm-partner-lead-score-fill" style="width:' + scorePct + '%"></div></div>' +
									'<div class="khm-partner-lead-meta">' +
										'<span title="' + maturityTooltip + '" style="cursor:help;">Maturity: <strong>' + scorePct + '%</strong></span>' +
									'</div>' +
									'<div class="khm-partner-rfq-metadata">' + rfqMetadataHtml + '</div>' +
									'<div class="khm-partner-lead-anon">' + signalHtml + '</div>' +
									(acceptHtml ? '<div class="khm-partner-lead-actions">' + acceptHtml + '</div>' : '');
								grid.appendChild(card);
							});
							// Pagination nav
							var nav = panel.querySelector('.khm-partner-rfq-nav');
							if (totalPages > 1) {
								nav.innerHTML =
									'<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-rfq-prev"' + (page === 0 ? ' disabled' : '') + '>&#8592;</button>' +
									'<span class="khm-partner-rfq-page-label">Page ' + (page + 1) + ' of ' + totalPages + '</span>' +
									'<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-rfq-next"' + (page >= totalPages - 1 ? ' disabled' : '') + '>&#8594;</button>';
								nav.querySelector('.khm-partner-rfq-prev').addEventListener('click', function(){ renderPage(currentPage - 1); });
								nav.querySelector('.khm-partner-rfq-next').addEventListener('click', function(){ renderPage(currentPage + 1); });
							} else {
								nav.innerHTML = '';
							}
							function updateRfqPricing(form) {
								if (!form) return null;
								var cplInput = form.querySelector('.khm-partner-rfq-response-cpl');
								var seatMinInput = form.querySelector('.khm-partner-rfq-seat-min');
								var seatMaxInput = form.querySelector('.khm-partner-rfq-seat-max');
								var discountToggle = form.querySelector('.khm-partner-rfq-discount-toggle');
								var rateInput = form.querySelector('.khm-partner-rfq-commission-rate');
								var discountControls = form.querySelector('.khm-partner-rfq-discount-controls');
								var commissionBreakdown = form.querySelector('.khm-partner-rfq-commission-breakdown');
								var rateValueNode = form.querySelector('.khm-partner-rfq-rate-value');
								var estimateNode = form.querySelector('.khm-partner-rfq-estimate-range');
								var flatFeeNode = form.querySelector('.khm-partner-rfq-flat-fee');
								var clientSavingNode = form.querySelector('.khm-partner-rfq-client-saving');
								var platformCommissionNode = form.querySelector('.khm-partner-rfq-platform-commission');
								var baselineInput = form.querySelector('.khm-partner-rfq-opportunity-baseline');
								var pricing = calculateRfqPricing(
									parseFloat(cplInput && cplInput.value ? cplInput.value : '0') || 0,
									{
										min: parseInt(seatMinInput && seatMinInput.value ? seatMinInput.value : '0', 10) || 0,
										max: parseInt(seatMaxInput && seatMaxInput.value ? seatMaxInput.value : '0', 10) || 0
									},
									parseInt(rateInput && rateInput.value ? rateInput.value : '5', 10) || 5,
									!!(discountToggle && discountToggle.checked),
									parseFloat(baselineInput && baselineInput.value ? baselineInput.value : '0') || 0
								);
								if (discountControls) discountControls.hidden = !(discountToggle && discountToggle.checked);
								if (commissionBreakdown) commissionBreakdown.hidden = !(discountToggle && discountToggle.checked);
								if (rateValueNode) rateValueNode.textContent = pricing.rate + '%';
								if (estimateNode) estimateNode.textContent = formatGbp(pricing.discountedMin) + ' - ' + formatGbp(pricing.discountedMax);
								if (flatFeeNode) flatFeeNode.textContent = formatGbp(pricing.flatFee);
								if (clientSavingNode) clientSavingNode.textContent = formatGbp(pricing.estimatedBuyerDiscount);
								if (platformCommissionNode) platformCommissionNode.textContent = formatGbp(pricing.estimatedPlatformCommission);
								return pricing;
							}
							function openRfqReviewModal(previewHtml, termsHtml, onConfirm) {
								var modal = ensureRfqReviewModal();
								modal.querySelector('.khm-partner-rfq-review-preview').innerHTML = previewHtml;
								modal.querySelector('.khm-partner-rfq-review-terms').innerHTML = termsHtml;
								var oldConfirmBtn = modal.querySelector('.khm-partner-rfq-review-confirm');
								var newConfirmBtn = oldConfirmBtn.cloneNode(true);
								oldConfirmBtn.parentNode.replaceChild(newConfirmBtn, oldConfirmBtn);
								newConfirmBtn.addEventListener('click', function() {
									modal.classList.remove('is-open');
									onConfirm();
								});
								modal.classList.add('is-open');
							}
							// Step 1: open/close the RFQ response workflow form.
							grid.querySelectorAll('.khm-partner-rfq-open-form-btn').forEach(function(btn) {
								btn.addEventListener('click', function() {
									var form = btn.closest('.khm-partner-rfq-accept-form');
									var workflow = form ? form.querySelector('.khm-partner-rfq-response-workflow') : null;
									if (!workflow) return;
									workflow.hidden = false;
									btn.style.display = 'none';
									if (!workflow.dataset.pricingBound) {
										['.khm-partner-rfq-response-cpl', '.khm-partner-rfq-discount-toggle', '.khm-partner-rfq-commission-rate'].forEach(function(selector) {
											var input = workflow.querySelector(selector);
											if (!input) return;
											input.addEventListener('input', function() { updateRfqPricing(form); });
											input.addEventListener('change', function() { updateRfqPricing(form); });
										});
										workflow.dataset.pricingBound = '1';
									}
									updateRfqPricing(form);
									var cplInput = workflow.querySelector('.khm-partner-rfq-response-cpl');
									if (cplInput) cplInput.focus();
								});
							});
							grid.querySelectorAll('.khm-partner-rfq-cancel-btn').forEach(function(btn) {
								btn.addEventListener('click', function() {
									var form = btn.closest('.khm-partner-rfq-accept-form');
									var workflow = form ? form.querySelector('.khm-partner-rfq-response-workflow') : null;
									var openBtn = form ? form.querySelector('.khm-partner-rfq-open-form-btn') : null;
									if (workflow) workflow.hidden = true;
									if (openBtn) openBtn.style.display = '';
								});
							});
							// Step 2: submit the response after form completion.
							grid.querySelectorAll('.khm-partner-rfq-submit-btn').forEach(function(btn) {
								btn.addEventListener('click', function() {
									var form = btn.closest('.khm-partner-rfq-accept-form');
									var scopeInput = form ? form.querySelector('.khm-partner-rfq-response-scope') : null;
									var seatsInput = form ? form.querySelector('.khm-partner-rfq-response-seats') : null;
									var pilotRequestedInput = form ? form.querySelector('.khm-partner-rfq-pilot-requested') : null;
									var cplInput = form ? form.querySelector('.khm-partner-rfq-response-cpl') : null;
									var discountToggleInput = form ? form.querySelector('.khm-partner-rfq-discount-toggle') : null;
									var commissionRateInput = form ? form.querySelector('.khm-partner-rfq-commission-rate') : null;
									var notesInput = form ? form.querySelector('.khm-partner-rfq-response-notes') : null;
									var featureInputs = form ? form.querySelectorAll('.khm-partner-rfq-feature') : [];
									var scopeValue = scopeInput ? String(scopeInput.value || '') : '';
									var seatsValue = seatsInput ? String(seatsInput.value || '') : '';
									var pilotRequestedValue = pilotRequestedInput ? String(pilotRequestedInput.value || 'no') : 'no';
									var cplValue = cplInput ? parseFloat(cplInput.value || '0') : 0;
									var offerPlatformDiscount = !!(discountToggleInput && discountToggleInput.checked);
									var commissionRate = offerPlatformDiscount ? (parseInt(commissionRateInput ? commissionRateInput.value : '5', 10) || 5) : 0;
									var notesValue = notesInput ? String(notesInput.value || '').trim() : '';
									var features = Array.prototype.map.call(featureInputs, function(input){ return String(input.value || ''); });
									var pricing = updateRfqPricing(form);
									if (!scopeValue || cplValue <= 0 || !features.length || !pricing) {
										showNotice(<?php echo wp_json_encode( __( 'Please complete the pricing details before continuing.', 'khm-membership' ) ); ?>, false);
										return;
									}
									var oppId = parseInt(btn.dataset.id, 10);
									var providerInput = form ? form.querySelector('.khm-partner-rfq-provider-id') : null;
									var providerId = providerInput ? parseInt(providerInput.value, 10) : 0;
									if (!providerId) {
										showNotice(<?php echo wp_json_encode( __( 'No provider mapping found for this RFQ.', 'khm-membership' ) ); ?>, false);
										return;
									}
									var responseSummary = 'Scope=' + scopeValue + '; Seats=' + seatsValue + '; Features=' + features.join(',') + '; CPLGBP=' + cplValue.toFixed(2) + '; AnnualEstimateMin=' + pricing.discountedMin.toFixed(2) + '; AnnualEstimateMax=' + pricing.discountedMax.toFixed(2) + '; PlatformDiscount=' + (offerPlatformDiscount ? 'yes' : 'no') + '; CommissionRate=' + commissionRate + '; BaselineGBP=' + pricing.flatFeeBasis.toFixed(2) + '; FlatFeeGBP=' + pricing.flatFee.toFixed(2) + '; BuyerDiscountGBP=' + pricing.estimatedBuyerDiscount.toFixed(2) + '; PlatformCommissionGBP=' + pricing.estimatedPlatformCommission.toFixed(2);
									var responseForm = {
										scope: scopeValue,
										seats: seatsValue,
										pilot_requested: pilotRequestedValue,
										required_features: features,
										cost_per_licence_gbp: Number(cplValue.toFixed(2)),
										annual_estimate_min_gbp: Number(pricing.discountedMin.toFixed(2)),
										annual_estimate_max_gbp: Number(pricing.discountedMax.toFixed(2)),
										offer_platform_discount: offerPlatformDiscount ? 1 : 0,
										seller_commission_rate: commissionRate,
										platform_fee_baseline_gbp: Number(pricing.flatFeeBasis.toFixed(2)),
										platform_flat_fee_gbp: Number(pricing.flatFee.toFixed(2)),
										estimated_buyer_discount_gbp: Number(pricing.estimatedBuyerDiscount.toFixed(2)),
										estimated_platform_commission_gbp: Number(pricing.estimatedPlatformCommission.toFixed(2)),
										estimated_client_discount_min_gbp: Number(pricing.estimatedBuyerDiscount.toFixed(2)),
										estimated_client_discount_max_gbp: Number(pricing.estimatedBuyerDiscount.toFixed(2)),
										estimated_platform_commission_min_gbp: Number(pricing.estimatedPlatformCommission.toFixed(2)),
										estimated_platform_commission_max_gbp: Number(pricing.estimatedPlatformCommission.toFixed(2)),
										response_notes: notesValue
									};
									var summarySection = form.querySelector('.khm-partner-rfq-summary');
									var previewHtml =
										(summarySection ? summarySection.outerHTML : '') +
										'<div class="khm-partner-rfq-calc-card">' +
											'<h4><?php echo esc_js( __( 'Commercial Terms', 'khm-membership' ) ); ?></h4>' +
											'<div class="khm-partner-rfq-breakdown-row"><span><?php echo esc_js( __( 'Cost per licence / month', 'khm-membership' ) ); ?></span><strong>' + formatGbp(cplValue) + '</strong></div>' +
											'<div class="khm-partner-rfq-breakdown-row"><span><?php echo esc_js( __( '12-month estimate', 'khm-membership' ) ); ?></span><strong>' + formatGbp(pricing.discountedMin) + ' - ' + formatGbp(pricing.discountedMax) + '</strong></div>' +
											'<div class="khm-partner-rfq-breakdown-row"><span><?php echo esc_js( __( 'Platform discount offered', 'khm-membership' ) ); ?></span><strong>' + (offerPlatformDiscount ? 'Yes (' + commissionRate + '%)' : 'No') + '</strong></div>' +
											'<div class="khm-partner-rfq-breakdown-row"><span><?php echo esc_js( __( 'Payable today', 'khm-membership' ) ); ?></span><strong>' + formatGbp(pricing.flatFee) + '</strong></div>' +
											(offerPlatformDiscount ? '<div class="khm-partner-rfq-breakdown-row"><span class="khm-partner-rfq-tooltip"><?php echo esc_js( __( 'Estimated buyer discount', 'khm-membership' ) ); ?><button type="button" class="khm-partner-rfq-tooltip-btn" aria-label="<?php echo esc_js( __( 'Buyer discount help', 'khm-membership' ) ); ?>">i</button><span class="khm-partner-rfq-tooltip-bubble"><?php echo esc_js( __( 'Deductible from the buyer\'s first invoice with your company.', 'khm-membership' ) ); ?></span></span><strong>' + formatGbp(pricing.estimatedBuyerDiscount) + '</strong></div>' : '') +
											(offerPlatformDiscount ? '<div class="khm-partner-rfq-breakdown-row"><span class="khm-partner-rfq-tooltip"><?php echo esc_js( __( 'Estimated platform commission', 'khm-membership' ) ); ?><button type="button" class="khm-partner-rfq-tooltip-btn" aria-label="<?php echo esc_js( __( 'Platform commission help', 'khm-membership' ) ); ?>">i</button><span class="khm-partner-rfq-tooltip-bubble"><?php echo esc_js( __( 'Debited from your account when the buyer confirms agreement so they can claim discount.', 'khm-membership' ) ); ?></span></span><strong>' + formatGbp(pricing.estimatedPlatformCommission) + '</strong></div>' : '') +
											(notesValue ? '<div><strong><?php echo esc_js( __( 'Note to buyer', 'khm-membership' ) ); ?>:</strong><p style="margin:6px 0 0;">' + safe(notesValue).replace(/\n/g, '<br>') + '</p></div>' : '') +
										'</div>';
									var termsHtml = '<p><?php echo esc_js( __( 'By sending this proposal you agree that your account will be debited for the flat fee if the buyer accepts and wishes to proceed off-platform.', 'khm-membership' ) ); ?></p>';
									if (offerPlatformDiscount) {
										termsHtml += '<p><?php echo esc_js( __( 'If a deal is agreed with the buyer, accessing the platform discount requires proof of contract. Submitting proof will automatically trigger the platform commission payment.', 'khm-membership' ) ); ?></p>';
									}
									openRfqReviewModal(previewHtml, termsHtml, function() {
										var rfqCard = btn.closest('.khm-partner-rfq-card');
										if (rfqCard && rfqCard.dataset.demo === '1') {
											var actions = btn.closest('.khm-partner-lead-actions');
											if (actions) {
												actions.innerHTML = '<span class="khm-partner-lead-status" style="color:#1e8c45;">&#10003; <?php echo esc_js( __( 'Proposal sent', 'khm-membership' ) ); ?></span>';
											}
											return;
										}
										btn.disabled = true;
										fetch(restBase + 'opportunities/mine/' + oppId + '/accept', {
											method: 'POST',
											headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
											body: JSON.stringify({
												provider_id: providerId,
												engaged_option: null,
												response_summary: responseSummary,
												response_form: responseForm
											})
										}).then(function(r){ return r.json(); }).then(function(d) {
											if (d.success) {
												showNotice(<?php echo wp_json_encode( __( 'Proposal sent — intro thread will open shortly.', 'khm-membership' ) ); ?>, true);
												loadRfqs();
											} else {
												showNotice((d.message || <?php echo wp_json_encode( __( 'Request failed.', 'khm-membership' ) ); ?>), false);
												btn.disabled = false;
											}
										}).catch(function(){ showNotice(<?php echo wp_json_encode( __( 'Network error — please try again.', 'khm-membership' ) ); ?>, false); btn.disabled = false; });
									});
								});
							});
						}
						function renderRfqCards(rfps, providers) {
							bindRfqInputControls();
							allRfqs      = rfps;
							allProviders = providers;
							currentPage  = 0;
							if (!rfps.length) {
								grid.innerHTML = '<p class="khm-partner-leads-empty"><?php echo esc_js( __( 'No RFQ requests at this time.', 'khm-membership' ) ); ?></p>';
								var nav = panel.querySelector('.khm-partner-rfq-nav');
								if (nav) nav.innerHTML = '';
								return;
							}
							renderPage(0);
						}
						function seedDemoClientSetup() {
							// When on localhost/demo and no buyer rfq_setup exists yet, pre-populate
							// with a realistic demo client profile matching the first RFQ lead (id:8001 FSM evaluation).
							if (!allowDemoFallback) return;
							if (localStorage.getItem('khm_connect_rfq_setup')) return;
							localStorage.setItem('khm_connect_rfq_setup', JSON.stringify({
								scope:     'fsm_evaluation_poc',
								seats:     '20_30',
								timeframe: '3_months',
								features:  ['mobile_app', 'offline_capabilities', 'real_time_reporting'],
								estimate:  120000
							}));
						}
						function loadRfqs() {
							seedDemoClientSetup();
							grid.innerHTML = '<p class="khm-partner-leads-empty"><?php echo esc_js( __( 'Loading…', 'khm-membership' ) ); ?></p>';
							renderRfqCards(demoRfqLeads(), demoProviders());
						}
						btn.addEventListener('click', loadRfqs);
						loadRfqs();
					})();
					</script>
					<section class="khm-partner-connect-panel khm-partner-connect-inbox-panel khm-partner-connect-span-full">
						<style>
							.khm-partner-connect-pill-engaged{background:#fff4e5;color:#9a3412;font-weight:600;}
							.khm-partner-connect-pill-price{background:#d1fae5;color:#065f46;font-weight:600;}
							.khm-partner-connect-pill{font-size:11px;padding:4px 8px;border-radius:12px;background:#e5e7eb;color:#374151;display:inline-block;margin-right:4px;margin-bottom:4px;}
							.khm-partner-connect-inbox-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;height:700px;}
							.khm-partner-connect-thread-list{overflow-y:auto;border-right:1px solid #e5e7eb;padding-right:8px;}
							.khm-partner-connect-thread-detail{overflow-y:auto;}
						</style>
						<div class="khm-partner-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Intro Inbox', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Replies stay platform-mediated until a buyer explicitly requests handover and your team confirms it.', 'khm-membership' ); ?></p>
							</div>
						</div>
						<div class="khm-partner-connect-inbox-grid">
							<div class="khm-partner-connect-thread-list"></div>
							<div class="khm-partner-connect-thread-detail">
								<div class="khm-partner-connect-empty"><?php esc_html_e( 'Select an intro thread to review messages, reply, and manage handover.', 'khm-membership' ); ?></div>
							</div>
						</div>
					</section>
				</div>
			</div>
		</div>
		<?php
	}
	private function render_commentary_section( int $user_id, ?array $sponsor ): void {
		$categories = $this->get_top_line_categories();
		?>
		<div class="khm-partner-section khm-partner-commentary">
			<!-- Invite / accept status banner -->
			<div class="khm-quoteclub-invite-status" role="status" aria-live="polite"></div>
			<h2><?php esc_html_e( 'Search Articles &amp; Submit Commentary', 'khm-membership' ); ?></h2>
			<div class="khm-quoteclub-toolbar">
				<select class="khm-filter-date-range" aria-label="<?php esc_attr_e( 'Date range', 'khm-membership' ); ?>">
					<option value="all"><?php esc_html_e( 'All', 'khm-membership' ); ?></option>
					<option value="week"><?php esc_html_e( 'Within the next week', 'khm-membership' ); ?></option>
					<option value="month"><?php esc_html_e( 'Within the next month', 'khm-membership' ); ?></option>
				</select>
				<?php if ( ! empty( $categories ) ) : ?>
				<select multiple class="khm-filter-categories" aria-label="<?php esc_attr_e( 'Categories', 'khm-membership' ); ?>">
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>
				<div class="khm-topic-autocomplete">
					<input type="text" class="khm-filter-topics" autocomplete="off" placeholder="<?php esc_attr_e( 'Topics', 'khm-membership' ); ?>">
					<div class="khm-topic-suggest-menu" role="listbox" aria-label="<?php esc_attr_e( 'Topic suggestions', 'khm-membership' ); ?>"></div>
				</div>
				<input type="text" class="khm-filter-keywords" placeholder="<?php esc_attr_e( 'Keywords', 'khm-membership' ); ?>">
				<select class="khm-filter-operator" aria-label="<?php esc_attr_e( 'Keyword operator', 'khm-membership' ); ?>">
					<option value="AND"><?php esc_html_e( 'AND', 'khm-membership' ); ?></option>
					<option value="OR"><?php esc_html_e( 'OR', 'khm-membership' ); ?></option>
				</select>
				<p class="khm-filter-operator-help"><?php esc_html_e( 'Keyword match: AND requires all words, OR matches any word. Use AND to narrow and OR to broaden.', 'khm-membership' ); ?></p>
				<select class="khm-saved-searches" aria-label="<?php esc_attr_e( 'Saved searches', 'khm-membership' ); ?>">
					<option value=""><?php esc_html_e( '— Saved Searches —', 'khm-membership' ); ?></option>
				</select>
				<button type="button" class="button khm-quoteclub-search-btn"><?php esc_html_e( 'Search', 'khm-membership' ); ?></button>
				<button type="button" class="button khm-save-search-btn"><?php esc_html_e( 'Save Search', 'khm-membership' ); ?></button>
			</div>
			<div class="khm-quoteclub-layout">
				<div class="khm-quoteclub-results" role="list" aria-label="<?php esc_attr_e( 'Search results', 'khm-membership' ); ?>"></div>
				<div class="khm-quoteclub-detail">
					<p class="khm-quoteclub-detail-placeholder">
						<?php esc_html_e( 'Select a result to view the brief and submit commentary.', 'khm-membership' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
	private function render_press_releases_section( int $user_id, ?array $sponsor ): void {
		$sponsor_id = isset( $sponsor['id'] ) ? (int) $sponsor['id'] : 0;
		// S7: Build portfolio site list for distribution checkboxes.
		$portfolio_sites = array();
		if ( is_multisite() ) {
			$blogs = get_sites( array( 'public' => 1, 'archived' => 0, 'deleted' => 0, 'number' => 50 ) );
			foreach ( $blogs as $blog ) {
				$bid  = (int) $blog->blog_id;
				$name = get_blog_option( $bid, 'blogname' );
				$portfolio_sites[] = array( 'id' => $bid, 'name' => $name );
			}
		}
		$portfolio_sites_json = wp_json_encode( $portfolio_sites );
		$current_blog_id      = is_multisite() ? (int) get_current_blog_id() : 0;
		?>
		<div class="khm-partner-section khm-partner-press-releases">
			<h2><?php esc_html_e( 'Press Releases', 'khm-membership' ); ?></h2>
			
			<div class="khm-partner-pr-toolbar">
				<button class="khm-partner-btn khm-partner-btn-primary" id="pr-create-btn">
					<?php esc_html_e( '+ Create New Press Release', 'khm-membership' ); ?>
				</button>
			</div>
			<!-- Press release list -->
			<div id="pr-list" class="khm-partner-pr-list"></div>
			<!-- Create/Edit form modal -->
			<div id="pr-form-modal" class="khm-partner-modal" style="display:none">
				<div class="khm-partner-modal-content">
					<div class="khm-partner-modal-header">
						<h3 id="pr-form-title"><?php esc_html_e( 'Create Press Release', 'khm-membership' ); ?></h3>
						<button class="khm-partner-modal-close" id="pr-form-close">×</button>
					</div>
					<div class="khm-partner-modal-body">
						<form id="pr-form">
							<div class="khm-partner-form-group">
								<label for="pr-title"><?php esc_html_e( 'Title', 'khm-membership' ); ?> *</label>
								<input type="text" id="pr-title" name="title" required maxlength="255"
									   placeholder="<?php esc_attr_e( 'Press release title', 'khm-membership' ); ?>"
									   class="khm-partner-input">
							</div>
							<div class="khm-partner-form-group">
								<label for="pr-content"><?php esc_html_e( 'Content', 'khm-membership' ); ?> *</label>
								<textarea id="pr-content" name="content" required rows="8"
										  placeholder="<?php esc_attr_e( 'Write your press release content here...', 'khm-membership' ); ?>"
										  class="khm-partner-textarea"></textarea>
								<small><?php esc_html_e( 'Press releases can be any length.', 'khm-membership' ); ?></small>
							</div>
							<?php if ( ! empty( $portfolio_sites ) ) : ?>
							<div class="khm-partner-form-group" id="pr-dist-group">
								<label><?php esc_html_e( 'Also distribute to portfolio sites (S7):', 'khm-membership' ); ?></label>
								<div id="pr-dist-sites" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
								<?php foreach ( $portfolio_sites as $site ) : ?>
									<label style="font-size:13px;display:flex;align-items:center;gap:4px;">
										<input type="checkbox" class="pr-dist-site-cb"
											   value="<?php echo (int) $site['id']; ?>"
											   <?php checked( (int) $site['id'], $current_blog_id ); ?>
											   <?php disabled( (int) $site['id'], $current_blog_id ); ?> />
										<?php echo esc_html( $site['name'] ); ?>
										<?php if ( (int) $site['id'] === $current_blog_id ) : ?>
											<em style="color:#777;font-size:11px;">(<?php esc_html_e( 'this site', 'khm-membership' ); ?>)</em>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
								</div>
								<small><?php esc_html_e( 'The current site is always included. Extra sites require editorial approval on each.', 'khm-membership' ); ?></small>
							</div>
							<?php endif; ?>
						</form>
					</div>
					<div class="khm-partner-modal-footer">
						<button class="khm-partner-btn" id="pr-form-cancel"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
						<button class="khm-partner-btn khm-partner-btn-primary" id="pr-form-save-draft"><?php esc_html_e( 'Save Draft', 'khm-membership' ); ?></button>
						<button class="khm-partner-btn khm-partner-btn-success" id="pr-form-submit" style="display:none">
							<?php esc_html_e( 'Save & Submit (1 Credit)', 'khm-membership' ); ?>
						</button>
					</div>
				</div>
			</div>
			<!-- View/Confirm modal -->
			<div id="pr-confirm-modal" class="khm-partner-modal" style="display:none">
				<div class="khm-partner-modal-content" style="max-width:700px">
					<div class="khm-partner-modal-header">
						<h3><?php esc_html_e( 'Confirm Submission', 'khm-membership' ); ?></h3>
						<button class="khm-partner-modal-close" id="pr-confirm-close">×</button>
					</div>
					<div class="khm-partner-modal-body">
						<div id="pr-confirm-preview" style="background:#f9f9f9;padding:1rem;border-radius:4px;margin-bottom:1rem"></div>
						<div class="khm-partner-alert khm-partner-alert-info">
							<strong><?php esc_html_e( 'Cost:', 'khm-membership' ); ?></strong> 
							<?php esc_html_e( '1 Press Release Credit', 'khm-membership' ); ?>
						</div>
					</div>
					<div class="khm-partner-modal-footer">
						<button class="khm-partner-btn" id="pr-confirm-cancel"><?php esc_html_e( 'Back to Edit', 'khm-membership' ); ?></button>
						<button class="khm-partner-btn khm-partner-btn-success" id="pr-confirm-submit"><?php esc_html_e( 'Confirm & Submit', 'khm-membership' ); ?></button>
					</div>
				</div>
			</div>
			<script>
			(function($) {
				var restUrl = khmQuoteClub.restUrl + 'press-releases';
				var nonce = khmQuoteClub.nonce;
				var currentPrId = null;
				var prList = [];
				// Load and display PR list
				function loadPrList() {
					$.ajax({
						url: restUrl,
						method: 'GET',
						headers: { 'X-WP-Nonce': nonce },
						dataType: 'json'
					}).done(function(res) {
						if (res.success && res.items) {
							prList = res.items;
							renderPrList();
						}
					});
				}
				// Render PR list UI
				function renderPrList() {
					var html = '';
					if (prList.length === 0) {
						html = '<p class="khm-partner-empty"><?php esc_html_e( 'No press releases yet. Create one to get started.', 'khm-membership' ); ?></p>';
					} else {
						html = '<div class="khm-partner-pr-items">';
						$.each(prList, function(i, pr) {
							var statusClass = 'khm-partner-badge-' + pr.status;
							var actions = '';
							if (pr.status === 'draft') {
								actions = '<button class="khm-partner-btn khm-partner-btn-sm pr-edit-btn" data-id="' + pr.id + '">Edit</button> ';
								actions += '<button class="khm-partner-btn khm-partner-btn-sm pr-delete-btn" data-id="' + pr.id + '">Delete</button>';
							}
							html += '<div class="khm-partner-pr-item">' +
								'<div class="khm-partner-pr-item-header">' +
								'<h4>' + (pr.title || '(Untitled)') + '</h4>' +
								'<span class="khm-partner-badge ' + statusClass + '">' + pr.status + '</span>' +
								'</div>' +
								'<p class="khm-partner-pr-item-excerpt">' + (pr.excerpt || '') + '</p>' +
								'<div class="khm-partner-pr-item-meta">' +
								'<small>Created: ' + pr.created_at.substring(0, 10) + '</small>' +
								(pr.status === 'published' ? '<small>Published: ' + pr.published_date.substring(0, 10) + '</small>' : '') +
								(pr.status === 'rejected' ? '<small style="color:#991b1b">Rejected</small>' : '') +
								'</div>' +
								(actions ? '<div class="khm-partner-pr-item-actions">' + actions + '</div>' : '') +
								(pr.status === 'published'
									? '<div class="khm-partner-pr-item-actions"><a href="' + khmQuoteClub.shareLinkedInBase + encodeURIComponent(pr.title || '') + '" class="khm-partner-btn khm-partner-btn-sm" style="background:#0a66c2;color:#fff;border-color:#0a66c2">&#128279; Share on LinkedIn</a></div>'
									: '') +
								'</div>';
						});
						html += '</div>';
					}
					$('#pr-list').html(html);
					// Bind edit/delete buttons
					$('.pr-edit-btn').on('click', function() {
						var id = $(this).data('id');
						var pr = prList.find(p => p.id == id);
						if (pr && pr.status === 'draft') {
							editPr(id, pr.title, pr.content);
						}
					});
					$('.pr-delete-btn').on('click', function() {
						if (confirm('<?php esc_attr_e( 'Delete this draft?', 'khm-membership' ); ?>')) {
							var id = $(this).data('id');
							$.ajax({
								url: restUrl + '/' + id,
								method: 'DELETE',
								headers: { 'X-WP-Nonce': nonce }
							}).done(function() {
								loadPrList();
							});
						}
					});
				}
				// Create new PR
				$('#pr-create-btn').on('click', function() {
					currentPrId = null;
					$('#pr-form').trigger('reset');
					$('#pr-form-title').text('<?php esc_html_e( 'Create Press Release', 'khm-membership' ); ?>');
					$('#pr-form-submit').hide();
					$('#pr-form-save-draft').show();
					$('#pr-form-modal').fadeIn();
				});
				// Edit existing PR
				function editPr(id, title, content) {
					currentPrId = id;
					$('#pr-title').val(title || '');
					$('#pr-content').val(content || '');
					$('#pr-form-title').text('<?php esc_html_e( 'Edit Press Release', 'khm-membership' ); ?>');
					$('#pr-form-submit').show();
					$('#pr-form-save-draft').text('<?php esc_html_e( 'Save Changes', 'khm-membership' ); ?>');
					$('#pr-form-modal').fadeIn();
				}
				// Save draft
				$('#pr-form-save-draft').on('click', function() {
					var title = $('#pr-title').val().trim();
					var content = $('#pr-content').val().trim();
					if (!title || !content) {
						alert('<?php esc_attr_e( 'Please fill in title and content.', 'khm-membership' ); ?>');
						return;
					}
					$(this).prop('disabled', true).text('Saving…');
					// S7: collect checked distribution site IDs.
					var distIds = [];
					$('.pr-dist-site-cb:checked').each(function(){ distIds.push(parseInt($(this).val(), 10)); });
					var method, url, data;
					if (currentPrId) {
						method = 'PUT';
						url = restUrl + '/' + currentPrId;
						data = { title: title, content: content, distribution_site_ids: distIds };
					} else {
						method = 'POST';
						url = restUrl;
						data = { title: title, content: content, distribution_site_ids: distIds };
					}
					$.ajax({
						url: url,
						method: method,
						contentType: 'application/json',
						data: JSON.stringify(data),
						headers: { 'X-WP-Nonce': nonce },
						dataType: 'json'
					}).done(function(res) {
						$('#pr-form-modal').fadeOut();
						loadPrList();
						if (!currentPrId) {
							$('#pr-form-save-draft').prop('disabled', false).text('<?php esc_html_e( 'Save Draft', 'khm-membership' ); ?>');
						}
					}).fail(function() {
						alert('<?php esc_attr_e( 'Failed to save draft.', 'khm-membership' ); ?>');
						$('#pr-form-save-draft').prop('disabled', false);
					});
				});
				// Submit for review
				$('#pr-form-submit').on('click', function() {
					var pr = prList.find(p => p.id == currentPrId);
					if (!pr) return;
					// Show confirmation modal
					var preview = '<h4>' + pr.title + '</h4><p>' + pr.excerpt + '</p>';
					$('#pr-confirm-preview').html(preview);
					$('#pr-form-modal').fadeOut();
					$('#pr-confirm-modal').fadeIn();
				});
				// Confirm submission
				$('#pr-confirm-submit').on('click', function() {
					$(this).prop('disabled', true).text('Submitting…');
					$.ajax({
						url: restUrl + '/' + currentPrId + '/submit',
						method: 'POST',
						headers: { 'X-WP-Nonce': nonce },
						dataType: 'json'
					}).done(function(res) {
						if (res.success) {
							$('#pr-confirm-modal').fadeOut();
							loadPrList();
							// Update credit balance
							if (res.credits_remaining !== undefined) {
								$('#qc-pr-balance').text(res.credits_remaining);
							}
						} else if (res.error === 'insufficient_press_release_credits') {
							alert('<?php esc_attr_e( 'Insufficient credits. Please purchase more.', 'khm-membership' ); ?>');
						}
					}).always(function() {
						$('#pr-confirm-submit').prop('disabled', false).text('<?php esc_html_e( 'Confirm & Submit', 'khm-membership' ); ?>');
					});
				});
				// Close modals
				['#pr-form-close', '#pr-form-cancel'].forEach(function(sel) {
					$(sel).on('click', function() { $('#pr-form-modal').fadeOut(); });
				});
				['#pr-confirm-close', '#pr-confirm-cancel'].forEach(function(sel) {
					$(sel).on('click', function() { $('#pr-confirm-modal').fadeOut(); });
				});
				// Initial load
				loadPrList();
			})(jQuery);
			</script>
		</div>
		<?php
	}
	private function render_tracking_section( int $user_id, ?array $sponsor ): void {
		?>
		<div class="khm-partner-section khm-partner-tracking">
			<h2><?php esc_html_e( 'Tracking', 'khm-membership' ); ?></h2>
			<p class="khm-partner-coming-soon">
				<?php esc_html_e( 'Submission tracking and GEO / SEO performance metrics for your articles and press releases will appear here.', 'khm-membership' ); ?>
			</p>
		</div>
		<?php
	}
	// -------------------------------------------------------------------------
	// Adverts section — S16 (creative portal) + S17 (ad serving preview)
	// -------------------------------------------------------------------------
	private function render_adverts_section( int $user_id, ?array $sponsor ): void {
		$sponsor_id = $sponsor['id'] ?? 0;
		$nonce      = wp_create_nonce( 'wp_rest' );
		$rest_root  = esc_url( rest_url( 'khm/v1' ) );
		$upload_url = esc_url( admin_url( 'media-new.php' ) );
		?>
		<div class="khm-partner-section khm-partner-adverts">
			<h2><?php esc_html_e( 'Advert Creatives', 'khm-membership' ); ?></h2>
			<p class="khm-partner-lead">
				<?php esc_html_e( 'Upload banner or image ad creatives for review. Once approved, they will appear alongside relevant content on the site.', 'khm-membership' ); ?>
			</p>
			<style>
				.khm-partner-adverts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin:20px 0}
				.khm-partner-advert-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;position:relative}
				.khm-partner-advert-card img{max-width:100%;height:120px;object-fit:cover;border-radius:4px;margin-bottom:10px}
				.khm-partner-advert-card .khm-partner-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;text-transform:uppercase}
				.khm-partner-badge-draft{background:#f3f4f6;color:#374151}
				.khm-partner-badge-pending{background:#fef3c7;color:#92400e}
				.khm-partner-badge-approved{background:#d1fae5;color:#065f46}
				.khm-partner-badge-rejected{background:#fee2e2;color:#991b1b}
				.khm-partner-badge-paused{background:#e5e7eb;color:#6b7280}
				.khm-partner-advert-card h4{margin:6px 0 4px;font-size:14px}
				.khm-partner-advert-card .khm-partner-placement{font-size:12px;color:#6b7280;margin-bottom:8px}
				.khm-partner-advert-card .khm-partner-stats{font-size:11px;color:#9ca3af;margin-bottom:10px}
				.khm-partner-advert-card button{margin-right:6px;font-size:12px;padding:4px 10px}
				#khm-partner-advert-form{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:24px;margin:24px 0;display:none}
				#khm-partner-advert-form h3{margin-top:0}
				#khm-partner-advert-form label{display:block;font-weight:600;margin:10px 0 4px;font-size:13px}
				#khm-partner-advert-form input[type=text],#khm-partner-advert-form select,#khm-partner-advert-form input[type=url],#khm-partner-advert-form input[type=date]{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
				#khm-partner-advert-form .khm-partner-media-preview{max-width:100%;height:120px;object-fit:cover;border-radius:4px;margin:8px 0;display:none}
				#khm-partner-advert-form .khm-partner-form-actions{margin-top:16px;display:flex;gap:8px;flex-wrap:wrap}
				.khm-partner-advert-rejection{font-size:12px;color:#991b1b;background:#fee2e2;padding:6px 10px;border-radius:4px;margin:6px 0}
				.khm-partner-advert-metrics{display:flex;gap:12px;font-size:12px;color:#374151;margin-top:6px}
				.khm-partner-advert-metrics span{font-weight:600}
				/* preview modal */
				#khm-partner-advert-preview-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center}
				#khm-partner-advert-preview-modal.active{display:flex}
				#khm-partner-advert-preview-box{background:#fff;border-radius:12px;padding:28px;max-width:540px;width:92%;max-height:82vh;overflow-y:auto;position:relative}
				#khm-partner-advert-preview-box h4{margin-top:0;font-size:16px}
				#khm-partner-advert-preview-box img{max-width:100%;border-radius:6px;margin-bottom:12px;display:block}
				#khm-partner-advert-preview-box .khm-preview-link{font-size:12px;color:#6b7280;word-break:break-all;margin-top:6px}
				#khm-partner-advert-preview-close{position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#6b7280;line-height:1;padding:0}
				/* analytics panel */
				#khm-partner-advert-analytics{display:none;margin:20px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px}
				#khm-partner-advert-analytics h3{margin-top:0;font-size:15px}
				.khm-analytics-kpis{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
				.khm-analytics-kpi{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 18px;min-width:100px}
				.khm-analytics-kpi .khm-kpi-val{font-size:22px;font-weight:700;color:#111827}
				.khm-analytics-kpi .khm-kpi-lbl{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px}
				#khm-partner-advert-analytics table{width:100%;border-collapse:collapse;font-size:13px;margin-top:12px}
				#khm-partner-advert-analytics th{text-align:left;padding:6px 10px;border-bottom:2px solid #e5e7eb;font-weight:600;color:#374151}
				#khm-partner-advert-analytics td{padding:6px 10px;border-bottom:1px solid #f3f4f6}
			</style>
			<!-- Create button -->
			<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
			<button class="button button-primary" id="khm-partner-advert-new-btn">
				+ <?php esc_html_e( 'New Creative', 'khm-membership' ); ?>
			</button>
			<button class="button" id="khm-partner-advert-analytics-btn">
				<?php esc_html_e( 'Analytics', 'khm-membership' ); ?>
			</button>
			</div>
			<p style="font-size:12px;color:#6b7280;margin-top:6px">
				<?php
				printf(
					/* translators: %s: WP Media Library URL */
					wp_kses( __( 'Need to upload an image first? <a href="%s" target="_blank">Open the Media Library</a>, then paste the attachment ID below.', 'khm-membership' ), [ 'a' => [ 'href' => [], 'target' => [] ] ] ),
					esc_url( $upload_url )
				);
				?>
			</p>
			<!-- Analytics panel -->
			<div id="khm-partner-advert-analytics">
				<h3><?php esc_html_e( 'Your Advert Analytics', 'khm-membership' ); ?></h3>
				<div class="khm-analytics-kpis">
					<div class="khm-analytics-kpi"><div class="khm-kpi-val" id="khm-kpi-total">—</div><div class="khm-kpi-lbl"><?php esc_html_e( 'Creatives', 'khm-membership' ); ?></div></div>
					<div class="khm-analytics-kpi"><div class="khm-kpi-val" id="khm-kpi-impressions">—</div><div class="khm-kpi-lbl"><?php esc_html_e( 'Total Impressions', 'khm-membership' ); ?></div></div>
					<div class="khm-analytics-kpi"><div class="khm-kpi-val" id="khm-kpi-clicks">—</div><div class="khm-kpi-lbl"><?php esc_html_e( 'Total Clicks', 'khm-membership' ); ?></div></div>
					<div class="khm-analytics-kpi"><div class="khm-kpi-val" id="khm-kpi-ctr">—</div><div class="khm-kpi-lbl"><?php esc_html_e( 'Overall CTR', 'khm-membership' ); ?></div></div>
				</div>
				<table>
					<thead><tr>
						<th><?php esc_html_e( 'Creative', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Placement', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Impr.', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Clicks', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'CTR', 'khm-membership' ); ?></th>
						<th><?php esc_html_e( 'Weight', 'khm-membership' ); ?></th>
					</tr></thead>
					<tbody id="khm-analytics-rows"></tbody>
				</table>
			</div>
			<!-- Create / Edit form -->
			<div id="khm-partner-advert-form">
				<h3 id="khm-partner-advert-form-title"><?php esc_html_e( 'New Ad Creative', 'khm-membership' ); ?></h3>
				<input type="hidden" id="khm-partner-advert-edit-id" value="">
				<label for="khm-partner-advert-title"><?php esc_html_e( 'Internal title', 'khm-membership' ); ?></label>
				<input type="text" id="khm-partner-advert-title" placeholder="<?php esc_attr_e( 'e.g. Summer banner – commentary', 'khm-membership' ); ?>">
				<label for="khm-partner-advert-placement"><?php esc_html_e( 'Placement', 'khm-membership' ); ?></label>
				<select id="khm-partner-advert-placement">
					<option value="commentary"><?php esc_html_e( 'Commentary', 'khm-membership' ); ?></option>
					<option value="press-release"><?php esc_html_e( 'Press Releases', 'khm-membership' ); ?></option>
					<option value="overview"><?php esc_html_e( 'Overview dashboard', 'khm-membership' ); ?></option>
					<option value="sidebar"><?php esc_html_e( 'Sidebar', 'khm-membership' ); ?></option>
				</select>
				<label for="khm-partner-advert-media-id"><?php esc_html_e( 'Attachment ID (from Media Library)', 'khm-membership' ); ?></label>
				<input type="text" id="khm-partner-advert-media-id" placeholder="e.g. 42" inputmode="numeric">
				<img id="khm-partner-advert-media-preview" class="khm-partner-media-preview" src="" alt="">
				<label for="khm-partner-advert-click-url"><?php esc_html_e( 'Click-through URL', 'khm-membership' ); ?></label>
				<input type="url" id="khm-partner-advert-click-url" placeholder="https://example.com/landing-page">
				<label for="khm-partner-advert-alt"><?php esc_html_e( 'Alt text', 'khm-membership' ); ?></label>
				<input type="text" id="khm-partner-advert-alt" placeholder="<?php esc_attr_e( 'Short description of the image', 'khm-membership' ); ?>">
				<label for="khm-partner-advert-start"><?php esc_html_e( 'Start date (optional)', 'khm-membership' ); ?></label>
				<input type="date" id="khm-partner-advert-start" title="<?php esc_attr_e( 'Leave blank to serve immediately when approved', 'khm-membership' ); ?>">
				<label for="khm-partner-advert-end"><?php esc_html_e( 'End date (optional)', 'khm-membership' ); ?></label>
				<input type="date" id="khm-partner-advert-end" title="<?php esc_attr_e( 'Leave blank to run indefinitely', 'khm-membership' ); ?>">
				<div class="khm-partner-form-actions">
					<button class="button button-primary" id="khm-partner-advert-save-btn"><?php esc_html_e( 'Save draft', 'khm-membership' ); ?></button>
					<button class="button button-secondary" id="khm-partner-advert-submit-btn" style="display:none"><?php esc_html_e( 'Submit for review', 'khm-membership' ); ?></button>
					<button class="button" id="khm-partner-advert-cancel-btn"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
				</div>
				<p id="khm-partner-advert-form-msg" style="margin-top:10px;font-size:13px"></p>
			</div>
			<!-- Adverts list -->
			<div class="khm-partner-adverts-grid" id="khm-partner-adverts-grid">
				<p style="color:#6b7280;font-style:italic"><?php esc_html_e( 'Loading your creatives…', 'khm-membership' ); ?></p>
			</div>
			<!-- Preview modal -->
			<div id="khm-partner-advert-preview-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Ad preview', 'khm-membership' ); ?>">
				<div id="khm-partner-advert-preview-box">
					<button id="khm-partner-advert-preview-close" aria-label="Close">&times;</button>
					<div id="khm-partner-advert-preview-content"></div>
				</div>
			</div>
		</div>
		<script>
		(function () {
			'use strict';
			var REST = '<?php echo esc_js( $rest_root ); ?>';
			var NONCE = '<?php echo esc_js( $nonce ); ?>';
			var grid     = document.getElementById('khm-partner-adverts-grid');
			var form     = document.getElementById('khm-partner-advert-form');
			var formTitle = document.getElementById('khm-partner-advert-form-title');
			var editId   = document.getElementById('khm-partner-advert-edit-id');
			var fTitle   = document.getElementById('khm-partner-advert-title');
			var fPlace   = document.getElementById('khm-partner-advert-placement');
			var fMedia   = document.getElementById('khm-partner-advert-media-id');
			var fPreview = document.getElementById('khm-partner-advert-media-preview');
			var fClick   = document.getElementById('khm-partner-advert-click-url');
			var fAlt     = document.getElementById('khm-partner-advert-alt');
			var fStart   = document.getElementById('khm-partner-advert-start');
			var fEnd     = document.getElementById('khm-partner-advert-end');
			var fSave    = document.getElementById('khm-partner-advert-save-btn');
			var fSubmit  = document.getElementById('khm-partner-advert-submit-btn');
			var fCancel  = document.getElementById('khm-partner-advert-cancel-btn');
			var fMsg     = document.getElementById('khm-partner-advert-form-msg');
			var newBtn        = document.getElementById('khm-partner-advert-new-btn');
			var analyticsBtn  = document.getElementById('khm-partner-advert-analytics-btn');
			var analyticsPanel = document.getElementById('khm-partner-advert-analytics');
			var previewModal  = document.getElementById('khm-partner-advert-preview-modal');
			var previewContent = document.getElementById('khm-partner-advert-preview-content');
			var previewClose  = document.getElementById('khm-partner-advert-preview-close');
			var analyticsRows = document.getElementById('khm-analytics-rows');
			function api(path, method, body) {
				var opts = { method: method || 'GET', headers: { 'X-WP-Nonce': NONCE } };
				if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
				return fetch(REST + path, opts).then(function (r) { return r.json(); });
			}
			var PLACEMENT_LABELS = {
				'commentary':    'Commentary',
				'press-release': 'Press Releases',
				'overview':      'Overview',
				'sidebar':       'Sidebar'
			};
			var STATUS_LABELS = {
				'draft':    'Draft',
				'pending':  'Pending review',
				'approved': 'Approved',
				'rejected': 'Rejected',
				'paused':   'Paused'
			};
			function renderCard(ad) {
				var img   = ad.media_url ? '<img src="' + ad.media_url + '" alt="' + ad.alt_text + '">' : '<div style="height:120px;background:#f3f4f6;border-radius:4px;margin-bottom:10px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px">No image</div>';
				var badge = '<span class="khm-partner-badge khm-partner-badge-' + ad.status + '">' + (STATUS_LABELS[ad.status] || ad.status) + '</span>';
				var rej   = ad.rejection_reason ? '<div class="khm-partner-advert-rejection">Rejected: ' + ad.rejection_reason + '</div>' : '';
				var metrics = '<div class="khm-partner-advert-metrics"><span>' + ad.impressions + '</span> impressions &nbsp;·&nbsp; <span>' + ad.clicks + '</span> clicks</div>';
				var editBtn   = (ad.status === 'draft' || ad.status === 'rejected') ? '<button class="button khm-partner-advert-edit" data-id="' + ad.id + '">Edit</button>' : '';
				var submitBtn = (ad.status === 'draft' || ad.status === 'rejected') ? '<button class="button button-primary khm-partner-advert-submit" data-id="' + ad.id + '">Submit</button>' : '';
				var previewBtn = '<button class="button khm-partner-advert-preview" data-id="' + ad.id + '">Preview</button>';
				var dupeBtn   = '<button class="button khm-partner-advert-dupe" data-id="' + ad.id + '" title="Duplicate as new draft">Duplicate</button>';
				return '<div class="khm-partner-advert-card" id="khm-advert-' + ad.id + '">' +
					img + badge + rej +
					'<h4>' + ad.title + '</h4>' +
					'<div class="khm-partner-placement">' + (PLACEMENT_LABELS[ad.placement] || ad.placement) + '</div>' +
					metrics +
					'<div style="margin-top:8px">' + editBtn + submitBtn + previewBtn + dupeBtn + '</div>' +
					'</div>';
			}
			function loadAdverts() {
				api('/adverts').then(function (data) {
					if (!data.success || !data.adverts.length) {
						grid.innerHTML = '<p style="color:#6b7280;font-style:italic">No ad creatives yet. Click "New Creative" to get started.</p>';
						return;
					}
					grid.innerHTML = data.adverts.map(renderCard).join('');
					// Bind card-level buttons
					grid.querySelectorAll('.khm-partner-advert-edit').forEach(function (btn) {
						btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
					});
					grid.querySelectorAll('.khm-partner-advert-submit').forEach(function (btn) {
						btn.addEventListener('click', function () { submitAdvert(btn.dataset.id); });
					});
					grid.querySelectorAll('.khm-partner-advert-preview').forEach(function (btn) {
						btn.addEventListener('click', function () { openPreview(btn.dataset.id); });
					});
					grid.querySelectorAll('.khm-partner-advert-dupe').forEach(function (btn) {
						btn.addEventListener('click', function () { duplicateAdvert(btn.dataset.id); });
					});
					// Populate analytics panel
					var ads = data.adverts;
					var totImp = ads.reduce(function(s,a){return s+a.impressions;},0);
					var totClk = ads.reduce(function(s,a){return s+a.clicks;},0);
					var ctr = totImp ? (totClk/totImp*100).toFixed(2)+'%' : '—';
					document.getElementById('khm-kpi-total').textContent = ads.length;
					document.getElementById('khm-kpi-impressions').textContent = totImp.toLocaleString();
					document.getElementById('khm-kpi-clicks').textContent = totClk.toLocaleString();
					document.getElementById('khm-kpi-ctr').textContent = ctr;
					analyticsRows.innerHTML = ads.map(function(a){
						var aCtr = a.impressions ? (a.clicks/a.impressions*100).toFixed(2)+'%' : '—';
						return '<tr>'+
							'<td>'+a.title+'</td>'+
							'<td>'+(PLACEMENT_LABELS[a.placement]||a.placement)+'</td>'+
							'<td><span class="khm-partner-badge khm-partner-badge-'+a.status+'">'+(STATUS_LABELS[a.status]||a.status)+'</span></td>'+
							'<td>'+a.impressions.toLocaleString()+'</td>'+
							'<td>'+a.clicks.toLocaleString()+'</td>'+
							'<td>'+aCtr+'</td>'+
							'<td>'+(a.weight||'—')+'</td>'+
						'</tr>';
					}).join('');
				});
			}
			function openNew() {
				editId.value = '';
				fTitle.value = '';
				fPlace.value = 'commentary';
				fMedia.value = '';
				fPreview.style.display = 'none';
				fClick.value = '';
				fAlt.value = '';
				fStart.value = '';
				fEnd.value = '';
				fMsg.textContent = '';
				fSubmit.style.display = 'none';
				formTitle.textContent = 'New Ad Creative';
				form.style.display = 'block';
			}
			function openEdit(id) {
				api('/adverts/' + id).then(function (data) {
					if (!data.success) { return; }
					var ad = data.advert;
					editId.value = ad.id;
					fTitle.value = ad.title;
					fPlace.value = ad.placement;
					fMedia.value = ad.media_id || '';
					if (ad.media_url) { fPreview.src = ad.media_url; fPreview.style.display = 'block'; }
					fClick.value = ad.click_url || '';
					fAlt.value   = ad.alt_text || '';
					fStart.value = ad.start_date ? ad.start_date.substring(0, 10) : '';
					fEnd.value   = ad.end_date ? ad.end_date.substring(0, 10) : '';
					fMsg.textContent = '';
					fSubmit.style.display = 'inline-block';
					formTitle.textContent = 'Edit: ' + ad.title;
					form.style.display = 'block';
				});
			}
			function saveAdvert() {
				fMsg.textContent = 'Saving…';
				var id      = editId.value;
				var payload = {
					title:      fTitle.value.trim(),
					placement:  fPlace.value,
					media_id:   parseInt(fMedia.value, 10) || 0,
					click_url:  fClick.value.trim(),
					alt_text:   fAlt.value.trim(),
					start_date: fStart.value || null,
					end_date:   fEnd.value || null
				};
				var req = id
					? api('/adverts/' + id, 'POST', payload)
					: api('/adverts', 'POST', payload);
				req.then(function (data) {
					if (data.success) {
						fMsg.style.color = '#065f46';
						fMsg.textContent = 'Saved. Submit when ready for review.';
						if (!id) { editId.value = data.advert.id; fSubmit.style.display = 'inline-block'; }
						loadAdverts();
					} else {
						fMsg.style.color = '#991b1b';
						fMsg.textContent = data.message || 'Save failed.';
					}
				}).catch(function () {
					fMsg.style.color = '#991b1b';
					fMsg.textContent = 'Network error.';
				});
			}
			function submitAdvert(id) {
				var targetId = id || editId.value;
				if (!targetId) { return; }
				api('/adverts/' + targetId, 'POST', { action: 'submit' }).then(function (data) {
					if (data.success) {
						fMsg.style.color = '#065f46';
						fMsg.textContent = 'Submitted for review.';
						form.style.display = 'none';
						loadAdverts();
					} else {
						fMsg.style.color = '#991b1b';
						fMsg.textContent = data.message || 'Submission failed.';
					}
				});
			}
			function openPreview(id) {
				api('/adverts/' + id).then(function (data) {
					if (!data.success) { return; }
					var ad = data.advert;
					var imgHtml = ad.media_url
						? '<img src="' + ad.media_url + '" alt="' + (ad.alt_text||'Ad image') + '" style="max-width:100%;border-radius:6px;margin-bottom:12px">'
						: '<div style="height:140px;background:#f3f4f6;border-radius:6px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13px">No image</div>';
					var linkHtml = ad.click_url
						? '<p class="khm-preview-link">Click-through: <a href="' + ad.click_url + '" target="_blank" rel="noopener noreferrer">' + ad.click_url + '</a></p>'
						: '';
					var placement = PLACEMENT_LABELS[ad.placement] || ad.placement;
					var schedule = '';
					if (ad.start_date || ad.end_date) {
						schedule = '<p style="font-size:12px;color:#6b7280;margin-top:6px">Schedule: '+(ad.start_date?ad.start_date.substring(0,10):'now')+' → '+(ad.end_date?ad.end_date.substring(0,10):'ongoing')+'</p>';
					}
					previewContent.innerHTML =
						'<p style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Placement: ' + placement + '</p>' +
						imgHtml +
						'<h4>' + ad.title + '</h4>' +
						linkHtml + schedule +
						'<p style="font-size:12px;color:#6b7280;margin-top:10px">'+(ad.impressions||0)+' impressions · '+(ad.clicks||0)+' clicks</p>';
					previewModal.classList.add('active');
				});
			}
			function duplicateAdvert(id) {
				if (!confirm('Duplicate this ad creative as a new draft?')) { return; }
				api('/adverts/' + id).then(function (data) {
					if (!data.success) { return; }
					var ad = data.advert;
					var payload = {
						title:      'Copy of ' + ad.title,
						placement:  ad.placement,
						media_id:   ad.media_id || 0,
						click_url:  ad.click_url || '',
						alt_text:   ad.alt_text || '',
						start_date: null,
						end_date:   null
					};
					api('/adverts', 'POST', payload).then(function (res) {
						if (res.success) { loadAdverts(); }
					});
				});
			}
			// Media ID preview
			fMedia.addEventListener('input', function () {
				var mid = parseInt(fMedia.value, 10);
				if (!mid) { fPreview.style.display = 'none'; return; }
				// Resolve media URL via WP REST.
				fetch('/wp-json/wp/v2/media/' + mid, { headers: { 'X-WP-Nonce': NONCE } })
					.then(function (r) { return r.json(); })
					.then(function (d) {
						var url = (d.media_details && d.media_details.sizes && d.media_details.sizes.medium && d.media_details.sizes.medium.source_url) || d.source_url;
						if (url) { fPreview.src = url; fPreview.style.display = 'block'; }
						else     { fPreview.style.display = 'none'; }
					}).catch(function () { fPreview.style.display = 'none'; });
			});
			newBtn.addEventListener('click', openNew);
			fSave.addEventListener('click', saveAdvert);
			fSubmit.addEventListener('click', function () { submitAdvert(editId.value); });
			fCancel.addEventListener('click', function () { form.style.display = 'none'; });
			analyticsBtn.addEventListener('click', function () {
				var open = analyticsPanel.style.display === 'block';
				analyticsPanel.style.display = open ? 'none' : 'block';
				analyticsBtn.textContent = open ? 'Analytics' : 'Hide Analytics';
			});
			previewClose.addEventListener('click', function () { previewModal.classList.remove('active'); });
			previewModal.addEventListener('click', function (e) { if (e.target === previewModal) { previewModal.classList.remove('active'); } });
			loadAdverts();
		}());
		</script>
		<?php
	}
	private function render_social_section( int $user_id, ?array $sponsor ): void {
		$nonce     = wp_create_nonce( 'wp_rest' );
		$rest_root = esc_url( rest_url( 'khm/v1' ) );
		?>
		<div class="khm-partner-section khm-partner-social">
			<h2><?php esc_html_e( 'LinkedIn Scheduling', 'khm-membership' ); ?></h2>
			<p class="khm-partner-lead">
				<?php esc_html_e( 'Connect your LinkedIn account to schedule posts that go out alongside your press releases and commentary.', 'khm-membership' ); ?>
			</p>
			<style>
				#khm-partner-li-connect-panel,#khm-partner-li-queue-panel{transition:all .2s}
				#khm-partner-li-connected-banner{display:none;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
				#khm-partner-li-disconnected-banner{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;text-align:center}
				#khm-partner-li-schedule-form{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:20px}
				#khm-partner-li-schedule-form label{display:block;font-weight:600;font-size:13px;margin-bottom:4px;margin-top:12px}
				#khm-partner-li-schedule-form label:first-child{margin-top:0}
				#khm-partner-li-text{width:100%;min-height:100px;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;resize:vertical}
				#khm-partner-li-url{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
				#khm-partner-li-when{padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
				#khm-partner-li-char-count{font-size:11px;color:#6b7280;margin-top:3px;text-align:right}
				.khm-partner-li-post-row{background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:12px 14px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
				.khm-partner-li-post-row .khm-partner-li-post-body{flex:1;min-width:0}
				.khm-partner-li-post-row .khm-partner-li-post-text{font-size:13px;white-space:pre-wrap;word-break:break-word;margin:0 0 4px}
				.khm-partner-li-post-row .khm-partner-li-post-meta{font-size:11px;color:#9ca3af}
				.khm-partner-li-post-row .khm-partner-badge{margin-left:6px}
				.khm-partner-li-post-row button{flex-shrink:0;font-size:12px;padding:3px 10px}
				#khm-partner-li-queue-empty{font-size:13px;color:#6b7280;font-style:italic}
			</style>
			<!-- Connected banner (hidden until JS loads status) -->
			<div id="khm-partner-li-connected-banner" style="display:none">
				<span id="khm-partner-li-profile-label" style="font-size:13px;color:#065f46;font-weight:600">
					<?php esc_html_e( 'LinkedIn connected', 'khm-membership' ); ?>
				</span>
				<button class="button" id="khm-partner-li-disconnect-btn" style="font-size:12px;padding:3px 10px">
					<?php esc_html_e( 'Disconnect', 'khm-membership' ); ?>
				</button>
			</div>
			<!-- Disconnected banner -->
			<div id="khm-partner-li-disconnected-banner">
				<p style="margin:0 0 10px;font-size:14px;font-weight:600"><?php esc_html_e( 'Not connected', 'khm-membership' ); ?></p>
				<p style="margin:0 0 12px;font-size:13px;color:#6b7280">
					<?php esc_html_e( 'Authorise QuoteClub to post on your behalf. You can disconnect at any time.', 'khm-membership' ); ?>
				</p>
				<button class="button button-primary" id="khm-partner-li-connect-btn">
					<?php esc_html_e( 'Connect LinkedIn', 'khm-membership' ); ?>
				</button>
				<p id="khm-partner-li-not-configured" style="display:none;font-size:12px;color:#991b1b;margin-top:8px">
					<?php esc_html_e( 'LinkedIn integration is not yet configured. Please contact support.', 'khm-membership' ); ?>
				</p>
			</div>
			<!-- Schedule form (shown when connected) -->
			<div id="khm-partner-li-schedule-form" style="display:none">
				<h3 style="margin-top:0"><?php esc_html_e( 'Schedule a post', 'khm-membership' ); ?></h3>
				<label for="khm-partner-li-text"><?php esc_html_e( 'Post text', 'khm-membership' ); ?></label>
				<textarea id="khm-partner-li-text" maxlength="3000" placeholder="<?php esc_attr_e( 'Write your LinkedIn post here (max 3000 characters)…', 'khm-membership' ); ?>"></textarea>
				<div id="khm-partner-li-char-count">0 / 3000</div>
				<label for="khm-partner-li-url"><?php esc_html_e( 'Link URL (optional)', 'khm-membership' ); ?></label>
				<input type="url" id="khm-partner-li-url" placeholder="https://example.com/article">
				<label for="khm-partner-li-when"><?php esc_html_e( 'Schedule time', 'khm-membership' ); ?></label>
				<input type="datetime-local" id="khm-partner-li-when">
				<div style="margin-top:14px;display:flex;gap:8px;align-items:center">
					<button class="button button-primary" id="khm-partner-li-schedule-btn"><?php esc_html_e( 'Schedule post', 'khm-membership' ); ?></button>
					<span id="khm-partner-li-schedule-msg" style="font-size:13px"></span>
				</div>
			</div>
			<!-- Queue -->
			<div id="khm-partner-li-queue-panel" style="display:none">
				<h3><?php esc_html_e( 'Scheduled posts', 'khm-membership' ); ?></h3>
				<div id="khm-partner-li-queue-list">
					<p id="khm-partner-li-queue-empty"><?php esc_html_e( 'No scheduled posts yet.', 'khm-membership' ); ?></p>
				</div>
			</div>
		</div>
		<script>
		(function () {
			'use strict';
			var REST  = '<?php echo esc_js( $rest_root ); ?>';
			var NONCE = '<?php echo esc_js( $nonce ); ?>';
			var connBanner   = document.getElementById('khm-partner-li-connected-banner');
			var discBanner   = document.getElementById('khm-partner-li-disconnected-banner');
			var profileLabel = document.getElementById('khm-partner-li-profile-label');
			var schedForm    = document.getElementById('khm-partner-li-schedule-form');
			var queuePanel   = document.getElementById('khm-partner-li-queue-panel');
			var queueList    = document.getElementById('khm-partner-li-queue-list');
			var connectBtn   = document.getElementById('khm-partner-li-connect-btn');
			var disconnBtn   = document.getElementById('khm-partner-li-disconnect-btn');
			var schedBtn     = document.getElementById('khm-partner-li-schedule-btn');
			var schedMsg     = document.getElementById('khm-partner-li-schedule-msg');
			var textArea     = document.getElementById('khm-partner-li-text');
			var charCount    = document.getElementById('khm-partner-li-char-count');
			var notConf      = document.getElementById('khm-partner-li-not-configured');
			var STATUS_LABELS = { queued: 'Scheduled', published: 'Published', failed: 'Failed', cancelled: 'Cancelled' };
			function api(path, method, body) {
				var opts = { method: method || 'GET', headers: { 'X-WP-Nonce': NONCE } };
				if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
				return fetch(REST + path, opts).then(function (r) { return r.json(); });
			}
			function fmtDate(iso) {
				var d = new Date(iso);
				return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
			}
			function renderPostRow(p) {
				var status  = STATUS_LABELS[p.status] || p.status;
				var cls     = 'khm-partner-badge khm-partner-badge-' + (p.status === 'published' ? 'approved' : p.status === 'failed' ? 'rejected' : p.status === 'cancelled' ? 'paused' : 'pending');
				var cancel  = p.status === 'queued' ? '<button class="button khm-partner-li-cancel" data-id="' + p.id + '">Cancel</button>' : '';
				var err     = p.error ? '<div style="font-size:11px;color:#991b1b;margin-top:4px">Error: ' + p.error + '</div>' : '';
				var link    = p.url ? '<a href="' + p.url + '" target="_blank" rel="noopener" style="font-size:11px">' + p.url + '</a>' : '';
				return '<div class="khm-partner-li-post-row" id="li-post-' + p.id + '">' +
					'<div class="khm-partner-li-post-body">' +
						'<p class="khm-partner-li-post-text">' + p.text + '</p>' +
						(link ? link + '<br>' : '') +
						'<span class="khm-partner-li-post-meta">Scheduled: ' + fmtDate(p.scheduled_at) + '</span>' +
						'<span class="' + cls + '">' + status + '</span>' +
						err +
					'</div>' +
					cancel +
					'</div>';
			}
			function loadQueue() {
				api('/social/linkedin/queue').then(function (data) {
					if (!data.success || !data.posts.length) {
						queueList.innerHTML = '<p id="khm-partner-li-queue-empty" style="font-size:13px;color:#6b7280;font-style:italic">No scheduled posts yet.</p>';
						return;
					}
					queueList.innerHTML = data.posts.map(renderPostRow).join('');
					queueList.querySelectorAll('.khm-partner-li-cancel').forEach(function (btn) {
						btn.addEventListener('click', function () {
							api('/social/linkedin/cancel', 'POST', { post_id: btn.dataset.id }).then(function (r) {
								if (r.success) { loadQueue(); }
							});
						});
					});
				});
			}
			function showConnected(profileId) {
				connBanner.style.display = 'flex';
				discBanner.style.display = 'none';
				schedForm.style.display  = 'block';
				queuePanel.style.display = 'block';
				if (profileId) {
					profileLabel.textContent = 'LinkedIn connected (URN: ' + profileId + ')';
				}
				loadQueue();
			}
			function showDisconnected(configured) {
				connBanner.style.display = 'none';
				discBanner.style.display = 'block';
				schedForm.style.display  = 'none';
				queuePanel.style.display = 'none';
				if (!configured) { notConf.style.display = 'block'; connectBtn.disabled = true; }
			}
			// Load status on init
			api('/social/linkedin/status').then(function (data) {
				if (!data.success) { return; }
				if (data.connected) {
					showConnected(data.profile_id);
				} else {
					showDisconnected(data.configured);
				}
				// Handle URL params after OAuth redirect
				var params = new URLSearchParams(window.location.search);
				if (params.get('li_connected') === '1' && data.connected) {
					schedMsg.style.color = '#065f46';
					schedMsg.textContent = 'LinkedIn connected successfully!';
				}
				if (params.get('li_error')) {
					schedMsg.style.color = '#991b1b';
					schedMsg.textContent = 'LinkedIn error: ' + decodeURIComponent(params.get('li_error'));
				}
				// PR → LI auto-suggest: pre-fill schedule form with PR title
				var suggestTitle = params.get('li_suggest_title');
				if (suggestTitle && data.connected) {
					var suggestText = decodeURIComponent(suggestTitle);
					textArea.value = suggestText;
					charCount.textContent = suggestText.length + ' / 3000';
					schedMsg.style.color = '#0a66c2';
					schedMsg.textContent = 'Pre-filled from your press release. Add a link and schedule time below.';
					schedForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
				} else if (suggestTitle && !data.connected) {
					schedMsg.style.color = '#92400e';
					schedMsg.textContent = 'Connect your LinkedIn account first, then share this press release.';
				}
			});
			// Connect button → fetch auth URL → redirect
			connectBtn.addEventListener('click', function () {
				connectBtn.disabled = true;
				connectBtn.textContent = 'Connecting…';
				api('/social/linkedin/auth-url').then(function (data) {
					if (data.success && data.auth_url) {
						window.location.href = data.auth_url;
					} else {
						connectBtn.disabled = false;
						connectBtn.textContent = 'Connect LinkedIn';
						notConf.style.display = 'block';
					}
				}).catch(function () {
					connectBtn.disabled = false;
					connectBtn.textContent = 'Connect LinkedIn';
				});
			});
			// Disconnect
			disconnBtn.addEventListener('click', function () {
				api('/social/linkedin/disconnect', 'POST').then(function (r) {
					if (r.success) { showDisconnected(true); }
				});
			});
			// Char counter
			textArea.addEventListener('input', function () {
				charCount.textContent = textArea.value.length + ' / 3000';
			});
			// Schedule post
			schedBtn.addEventListener('click', function () {
				var text = textArea.value.trim();
				if (!text) { schedMsg.style.color = '#991b1b'; schedMsg.textContent = 'Post text is required.'; return; }
				schedMsg.style.color = '#374151'; schedMsg.textContent = 'Scheduling…';
				schedBtn.disabled = true;
				var payload = {
					text: text,
					url:  document.getElementById('khm-partner-li-url').value.trim(),
					scheduled_at: document.getElementById('khm-partner-li-when').value
				};
				api('/social/linkedin/schedule', 'POST', payload).then(function (data) {
					schedBtn.disabled = false;
					if (data.success) {
						schedMsg.style.color = '#065f46';
						schedMsg.textContent = 'Post scheduled for ' + fmtDate(data.post.scheduled_at);
						textArea.value = '';
						document.getElementById('khm-partner-li-url').value = '';
						document.getElementById('khm-partner-li-when').value = '';
						charCount.textContent = '0 / 3000';
						loadQueue();
					} else {
						schedMsg.style.color = '#991b1b';
						schedMsg.textContent = data.message || 'Schedule failed.';
					}
				}).catch(function () {
					schedBtn.disabled = false;
					schedMsg.style.color = '#991b1b';
					schedMsg.textContent = 'Network error.';
				});
			});
		}());
		</script>
		<?php
	}
	// -------------------------------------------------------------------------
	// Account section — Company Profile, Solutions Mapping, Offering Details
	// -------------------------------------------------------------------------
	/**
	 * Render the Account tab for sponsor company profile management, solution
	 * selection (mapped to wp_tc_solutions and wp_tc_sponsor_solutions), and
	 * deployment & support preferences.
	 *
	 * CSS is loaded from tabs/sponsor-account.css (enqueued in enqueue_assets()).
	 */
	private function render_account_section( int $user_id, ?array $sponsor ): void {
		$sponsor_id      = (int) ( $sponsor['id'] ?? 0 );
		$sponsor_name    = isset( $sponsor['name'] ) ? sanitize_text_field( $sponsor['name'] ) : '';
		$sponsor_company_url = isset( $sponsor['url'] ) ? esc_url( $sponsor['url'] ) : '';
		$sponsor_hq      = isset( $sponsor['hq_location'] ) ? sanitize_text_field( $sponsor['hq_location'] ) : '';
		$regions         = isset( $sponsor['regions'] ) ? (array) json_decode( $sponsor['regions'], true ) : [];
		$deployment_mode = isset( $sponsor['deployment_modes'] ) ? sanitize_text_field( $sponsor['deployment_modes'] ) : 'cloud';
		$support_hours   = isset( $sponsor['support_hours'] ) ? sanitize_text_field( $sponsor['support_hours'] ) : 'business';
		$impl_support    = isset( $sponsor['implementation_support'] ) ? (bool) $sponsor['implementation_support'] : false;
		$pilot_terms     = isset( $sponsor['pilot_terms'] ) ? sanitize_textarea_field( $sponsor['pilot_terms'] ) : '';

		// Fetch solutions from Tech.Connect catalog
		global $wpdb;
		$solutions_table = $wpdb->prefix . 'tc_solutions';
		$all_solutions   = [];
		if ( $this->table_exists( $solutions_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$all_solutions = $wpdb->get_results(
				"SELECT id, solution_name, solution_slug FROM {$solutions_table} ORDER BY solution_name ASC",
				ARRAY_A
			);
		}

		// Group solutions by simple keyword matching for accordion display
		$grouped_solutions = [ 'software' => [], 'hardware' => [], 'consultancy' => [] ];
		foreach ( $all_solutions as $sol ) {
			$name = strtolower( $sol['solution_name'] );
			if ( preg_match( '/\b(consulting|consultancy|advisory|strategy)\b/i', $name ) ) {
				$grouped_solutions['consultancy'][] = $sol;
			} elseif ( preg_match( '/\b(hardware|device|equipment|sensor|iot|appliance)\b/i', $name ) ) {
				$grouped_solutions['hardware'][] = $sol;
			} else {
				$grouped_solutions['software'][] = $sol;
			}
		}

		// Accordion group config
		$accordion_groups = [
			'software'    => [ 'label' => __( 'Software', 'khm-membership' ),    'icon' => 'dashicons-desktop' ],
			'hardware'    => [ 'label' => __( 'Hardware', 'khm-membership' ),    'icon' => 'dashicons-admin-generic' ],
			'consultancy' => [ 'label' => __( 'Consultancy', 'khm-membership' ), 'icon' => 'dashicons-groups' ],
		];

		// Fetch the current sponsor's mapped solution IDs
		$mapped_solutions = [];
		if ( $sponsor_id > 0 ) {
			$mapping_table = $wpdb->prefix . 'tc_sponsor_solutions';
			if ( $this->table_exists( $mapping_table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$mapped_rows = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT solution_id FROM {$mapping_table} WHERE sponsor_id = %d",
						$sponsor_id
					)
				);
				$mapped_solutions = array_map( 'intval', $mapped_rows );
			}
		}

		?>
		<div class="khm-partner-section khm-partner-account-form" data-sponsor-id="<?php echo esc_attr( $sponsor_id ); ?>">
			<h2><?php esc_html_e( 'Account & Offering Details', 'khm-membership' ); ?></h2>
			<p><?php esc_html_e( 'Manage your company profile, map your solutions to the Tech.Connect catalog, and configure deployment and support preferences. These fields feed your public seller listing on the buyer portal.', 'khm-membership' ); ?></p>

			<form id="khm-partner-account-form" class="khm-partner-account-form">
				<input type="hidden" name="sponsor_id" value="<?php echo esc_attr( $sponsor_id ); ?>" />

				<!-- ── Company Profile Block ─────────────────────────────── -->
				<div class="khm-partner-account-block">
					<div class="khm-partner-block-header">
						<h3><span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Company Profile', 'khm-membership' ); ?></h3>
					</div>
					<div class="khm-partner-block-grid-2col">
						<label>
							<span><?php esc_html_e( 'Company Name', 'khm-membership' ); ?></span>
							<input type="text" name="company_name" value="<?php echo esc_attr( $sponsor_name ); ?>" placeholder="<?php esc_attr_e( 'Your company name', 'khm-membership' ); ?>" />
						</label>
						<label>
							<span><?php esc_html_e( 'Company URL', 'khm-membership' ); ?></span>
							<input type="url" name="company_url" value="<?php echo esc_attr( $sponsor_company_url ); ?>" placeholder="https://example.com" />
						</label>
						<label>
							<span><?php esc_html_e( 'HQ Location', 'khm-membership' ); ?></span>
							<input type="text" name="hq_location" value="<?php echo esc_attr( $sponsor_hq ); ?>" placeholder="<?php esc_attr_e( 'City, Country', 'khm-membership' ); ?>" />
						</label>
						<div class="khm-partner-regions-container">
							<span style="display:block;font-size:0.875rem;font-weight:600;margin-bottom:0.35rem;"><?php esc_html_e( 'Regions Served', 'khm-membership' ); ?></span>
							<div class="khm-partner-regions-tags" id="khm-region-tags">
								<?php foreach ( $regions as $region ) : ?>
									<span class="khm-partner-region-tag">
										<span class="khm-partner-region-tag-text"><?php echo esc_html( $region ); ?></span>
										<button type="button" class="khm-partner-region-tag-remove" data-region="<?php echo esc_attr( $region ); ?>">&times;</button>
									</span>
								<?php endforeach; ?>
							</div>
							<select multiple class="khm-partner-regions-select" id="khm-regions-select">
								<option value=""><?php esc_html_e( 'Select regions…', 'khm-membership' ); ?></option>
								<?php
								$region_options = [ 'UK & Ireland', 'Western Europe', 'DACH', 'Nordics', 'Southern Europe', 'Eastern Europe', 'North America', 'Central America', 'South America', 'Middle East & Africa', 'Asia Pacific', 'Oceania', 'Global' ];
								foreach ( $region_options as $r ) :
									$selected = in_array( $r, $regions, true ) ? 'selected' : '';
								?>
									<option value="<?php echo esc_attr( $r ); ?>" <?php echo $selected; ?>><?php echo esc_html( $r ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<!-- ── Solutions Offered Block ──────────────────────────── -->
				<div class="khm-partner-account-block">
					<div class="khm-partner-block-header">
						<h3><span class="dashicons dashicons-grid-view"></span> <?php esc_html_e( 'Solutions Offered', 'khm-membership' ); ?></h3>
					</div>
					<p class="khm-partner-field-helper"><?php esc_html_e( 'Select the Tech.Connect solution categories your company offers, grouped by type (Software, Hardware, Consultancy). These map your business to buyer discovery queries on the portal.', 'khm-membership' ); ?></p>
					<div class="khm-partner-solutions-accordion" id="khm-solutions-accordion">
						<?php if ( empty( $all_solutions ) ) : ?>
							<p class="khm-partner-field-helper"><?php esc_html_e( 'No solutions catalog available yet. Please run the Tech.Connect migration.', 'khm-membership' ); ?></p>
						<?php else : ?>
							<?php foreach ( $accordion_groups as $group_key => $group_config ) : ?>
								<?php
								$group_solutions = $grouped_solutions[ $group_key ] ?? [];
								if ( empty( $group_solutions ) ) {
									continue;
								}
								?>
								<?php
								// Count selected solutions in this group
								$selected_in_group = count( array_filter( $group_solutions, function( $sol ) use ( $mapped_solutions ) {
									return in_array( (int) $sol['id'], $mapped_solutions, true );
								} ) );
								?>
								<div class="khm-partner-accordion">
									<button type="button" class="khm-partner-accordion-trigger" aria-expanded="false" aria-controls="sol-panel-<?php echo esc_attr( $group_key ); ?>">
										<span class="dashicons <?php echo esc_attr( $group_config['icon'] ); ?>"></span>
										<?php echo esc_html( $group_config['label'] ); ?>
										<span class="khm-partner-version-tag khm-solutions-badge" data-group="<?php echo esc_attr( $group_key ); ?>"><?php echo esc_html( $selected_in_group . ' solutions selected' ); ?></span>
									</button>
									<div class="khm-partner-accordion-panel" id="sol-panel-<?php echo esc_attr( $group_key ); ?>" hidden>
										<?php foreach ( $group_solutions as $sol ) : ?>
											<?php
											$sol_id  = (int) $sol['id'];
											$checked = in_array( $sol_id, $mapped_solutions, true );
											?>
											<label class="khm-partner-solution-row" for="sol-<?php echo esc_attr( $sol_id ); ?>">
												<input type="checkbox" id="sol-<?php echo esc_attr( $sol_id ); ?>" name="solutions[]" value="<?php echo esc_attr( $sol_id ); ?>" <?php checked( $checked ); ?> />
												<span><?php echo esc_html( $sol['solution_name'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- ── Deployment & Support Block ───────────────────────── -->
				<div class="khm-partner-account-block">
					<div class="khm-partner-block-header">
						<h3><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Deployment & Support', 'khm-membership' ); ?></h3>
					</div>
					<div class="khm-partner-sub-sections-container">
						<div class="khm-partner-sub-section">
							<h4><?php esc_html_e( 'Deployment Mode', 'khm-membership' ); ?></h4>
							<div class="khm-partner-sub-section-content">
								<label><input type="radio" name="deployment_mode" value="on-premise" <?php checked( $deployment_mode, 'on-premise' ); ?> /> <?php esc_html_e( 'On-Premise', 'khm-membership' ); ?></label>
								<label><input type="radio" name="deployment_mode" value="cloud" <?php checked( $deployment_mode, 'cloud' ); ?> /> <?php esc_html_e( 'Cloud / SaaS', 'khm-membership' ); ?></label>
								<label><input type="radio" name="deployment_mode" value="hybrid" <?php checked( $deployment_mode, 'hybrid' ); ?> /> <?php esc_html_e( 'Hybrid', 'khm-membership' ); ?></label>
							</div>
						</div>
						<div class="khm-partner-sub-section">
							<h4><?php esc_html_e( 'Implementation Support', 'khm-membership' ); ?></h4>
							<div class="khm-partner-sub-section-content">
								<label>
									<input type="checkbox" name="implementation_support" value="1" <?php checked( $impl_support ); ?> />
									<?php esc_html_e( 'Offer implementation support', 'khm-membership' ); ?>
								</label>
							</div>
						</div>
						<div class="khm-partner-sub-section">
							<h4><?php esc_html_e( 'Support Desk Hours', 'khm-membership' ); ?></h4>
							<div class="khm-partner-sub-section-content">
								<label><input type="radio" name="support_hours" value="business" <?php checked( $support_hours, 'business' ); ?> /> <?php esc_html_e( 'Business Hours', 'khm-membership' ); ?></label>
								<label><input type="radio" name="support_hours" value="24x7" <?php checked( $support_hours, '24x7' ); ?> /> <?php esc_html_e( '24/7 Support', 'khm-membership' ); ?></label>
							</div>
						</div>
					</div>
				</div>

				<!-- ── Pilot Terms Block ────────────────────────────────── -->
				<div class="khm-partner-account-block">
					<div class="khm-partner-block-header">
						<h3><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Pilot Terms', 'khm-membership' ); ?></h3>
					</div>
					<label>
						<span class="khm-partner-field-helper"><?php esc_html_e( 'Describe your standard pilot or proof-of-concept terms (duration, scope, pricing, success criteria).', 'khm-membership' ); ?></span>
						<textarea name="pilot_terms" rows="4" class="khm-partner-block-grid-2col" style="grid-column:1/-1;width:100%;padding:0.65rem 0.75rem;border:1px solid var(--partner-border);border-radius:6px;font:inherit;"><?php echo esc_textarea( $pilot_terms ); ?></textarea>
					</label>
				</div>

				<!-- ── Save Button ──────────────────────────────────────── -->
				<div class="khm-partner-account-block" style="border:none;box-shadow:none;background:transparent;padding:1rem 0;">
					<button type="submit" class="khm-partner-btn khm-partner-btn-primary"><?php esc_html_e( 'Save Account Settings', 'khm-membership' ); ?></button>
					<span class="khm-partner-form-message" style="margin-left:1rem;font-size:0.875rem;"></span>
				</div>
			</form>
		</div>

		<script>
		(function() {
			'use strict';

			// Accordion toggle for solutions groups
			document.addEventListener('click', function(e) {
				var trigger = e.target.closest('.khm-partner-accordion-trigger');
				if (!trigger) return;
				var panel = document.getElementById(trigger.getAttribute('aria-controls'));
				if (!panel) return;
				var expanded = trigger.getAttribute('aria-expanded') === 'true';
				trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				panel.hidden = expanded;
			});

			// Live update selected count badges on checkbox change
			document.getElementById('khm-solutions-accordion').addEventListener('change', function(e) {
				if (!e.target.matches('input[type="checkbox"]')) return;
				var accordion = e.target.closest('.khm-partner-accordion');
				if (!accordion) return;
				var badge = accordion.querySelector('.khm-solutions-badge');
				if (!badge) return;
				var checked = accordion.querySelectorAll('input[type="checkbox"]:checked').length;
				badge.textContent = checked + ' solutions selected';
			});

			var form     = document.getElementById('khm-partner-account-form');
			var msgEl    = form ? form.querySelector('.khm-partner-form-message') : null;
			var regionsSelect = document.getElementById('khm-regions-select');
			var regionsTags   = document.getElementById('khm-region-tags');
			if (!form) return;

			// Regions multi-select
			if (regionsSelect) {
				regionsSelect.addEventListener('change', function() {
					var selected = Array.prototype.map.call(this.selectedOptions, function(opt) { return opt.value; }).filter(Boolean);
					// Remove any current tags not in selection
					var currentTags = Array.prototype.map.call(regionsTags.querySelectorAll('.khm-partner-region-tag'), function(tag) {
						return tag.dataset.region;
					});
					selected.forEach(function(region) {
						if (currentTags.indexOf(region) === -1) {
							var tag = document.createElement('span');
							tag.className = 'khm-partner-region-tag';
							tag.dataset.region = region;
							tag.innerHTML = '<span class="khm-partner-region-tag-text">' + region + '</span> <button type="button" class="khm-partner-region-tag-remove" data-region="' + region + '">&times;</button>';
							regionsTags.appendChild(tag);
							tag.querySelector('.khm-partner-region-tag-remove').addEventListener('click', function() {
								tag.remove();
								var opt = regionsSelect.querySelector('option[value="' + region + '"]');
								if (opt) opt.selected = false;
							});
						}
					});
				});
				// Bind initial remove buttons
				regionsTags.querySelectorAll('.khm-partner-region-tag-remove').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var region = btn.dataset.region;
						btn.closest('.khm-partner-region-tag').remove();
						var opt = regionsSelect.querySelector('option[value="' + region + '"]');
						if (opt) opt.selected = false;
					});
				});
			}

			// Form submit
			form.addEventListener('submit', function(e) {
				e.stopImmediatePropagation();
				e.preventDefault();
				if (msgEl) { msgEl.textContent = '<?php echo esc_js( __( 'Saving…', 'khm-membership' ) ); ?>'; msgEl.style.color = '#374151'; }
				var submitBtn = form.querySelector('button[type="submit"]');
				if (submitBtn) submitBtn.disabled = true;

				// Gather regions from tags
				// The jQuery initializeRegionsMultiSelect creates tags with data-value;
				// the PHP inline handler creates them with data-region. Handle both.
				var regions = [];
				regionsTags.querySelectorAll('.khm-partner-region-tag').forEach(function(tag) {
					regions.push(tag.dataset.region || tag.dataset.value);
				});

				// Gather selected solutions
				var solutions = [];
				form.querySelectorAll('input[name="solutions[]"]:checked').forEach(function(cb) {
					solutions.push(parseInt(cb.value, 10));
				});

				var companyUrl = form.querySelector('input[name="company_url"]').value.trim();
				if (companyUrl && !companyUrl.match(/^https?:\/\//)) {
					companyUrl = 'https://' + companyUrl;
				}

				var data = {
					sponsor_id:             parseInt(form.querySelector('input[name="sponsor_id"]').value, 10) || 0,
					company_name:           form.querySelector('input[name="company_name"]').value.trim(),
					company_url:            companyUrl,
					hq_location:            form.querySelector('input[name="hq_location"]').value.trim(),
					regions:                regions,
					solutions:              solutions,
					deployment_mode:        (form.querySelector('input[name="deployment_mode"]:checked') || {}).value || 'cloud',
					implementation_support: form.querySelector('input[name="implementation_support"]').checked ? 1 : 0,
					support_hours:          (form.querySelector('input[name="support_hours"]:checked') || {}).value || 'business',
					pilot_terms:            form.querySelector('textarea[name="pilot_terms"]').value.trim()
				};

				fetch( khmQuoteClub.sponsorRestUrl + 'profile', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': khmQuoteClub.nonce },
					body: JSON.stringify(data)
				}).then(function(r) { return r.json(); }).then(function(res) {
					if (submitBtn) submitBtn.disabled = false;
					if (res.success) {
						if (msgEl) { msgEl.textContent = '<?php echo esc_js( __( 'Settings saved successfully.', 'khm-membership' ) ); ?>'; msgEl.style.color = '#065f46'; }
					} else {
						if (msgEl) { msgEl.textContent = (res.message || '<?php echo esc_js( __( 'Save failed.', 'khm-membership' ) ); ?>'); msgEl.style.color = '#991b1b'; }
					}
				}).catch(function() {
					if (submitBtn) submitBtn.disabled = false;
					if (msgEl) { msgEl.textContent = '<?php echo esc_js( __( 'Network error.', 'khm-membership' ) ); ?>'; msgEl.style.color = '#991b1b'; }
				});
			});
		})();
		</script>
		<?php
	}
	// -------------------------------------------------------------------------
	// Access gate messages
	// -------------------------------------------------------------------------
	private function render_login_required(): string {
		$login_url = wp_login_url( get_permalink() );
		ob_start();
		?>
		<div class="khm-partner-access-gate">
			<h2><?php esc_html_e( 'Quote Club', 'khm-membership' ); ?></h2>
			<p><?php esc_html_e( 'Please log in to access the Quote Club partner portal.', 'khm-membership' ); ?></p>
			<a href="<?php echo esc_url( $login_url ); ?>" class="khm-partner-btn khm-partner-btn-primary"><?php esc_html_e( 'Log In', 'khm-membership' ); ?></a>
		</div>
		<?php
		return ob_get_clean();
	}
	/**
	 * Fetch top-line categories from the shared Dual GPT option.
	 *
	 * @return array<int, string>
	 */
	private function get_top_line_categories(): array {
		$stored = get_option( 'dual_gpt_top_line_categories', null );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return [];
		}
		$categories = [];
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( $name !== '' ) {
				$categories[] = $name;
			}
		}
		return array_values( array_unique( $categories ) );
	}
	private function render_access_denied(): string {
		ob_start();
		?>
		<div class="khm-partner-access-gate">
			<h2><?php esc_html_e( 'Quote Club', 'khm-membership' ); ?></h2>
			<p><?php esc_html_e( 'This portal is available to Quote Club partner accounts. If you believe you should have access, please contact support.', 'khm-membership' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	// -------------------------------------------------------------------------
	// S17 — [khm_sponsor_advert placement="commentary"] shortcode
	// -------------------------------------------------------------------------
	/**
	 * Render a sponsor advert in any page context.
	 *
	 * Usage: [khm_sponsor_advert placement="commentary"]
	 *
	 * Pulls the highest-weight approved creative for the given placement,
	 * increments its impression counter, and renders a linked image with a
	 * JS click-tracker call so clicks are recorded without a page reload.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_sponsor_advert_shortcode( array $atts ): string {
		$allowed_placements = [ 'commentary', 'press-release', 'overview', 'sidebar' ];
		$atts               = shortcode_atts( [ 'placement' => 'commentary' ], $atts, 'khm_sponsor_advert' );
		$placement          = in_array( $atts['placement'], $allowed_placements, true ) ? $atts['placement'] : 'commentary';
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		// Check the table exists before querying (graceful no-op if migration hasn't run).
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return '';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE status = 'approved' AND placement = %s ORDER BY weight DESC, impressions ASC LIMIT 1",
				$placement
			)
		);
		if ( ! $row || empty( $row->media_url ) ) {
			return '';
		}
		// Increment impressions server-side.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET impressions = impressions + 1 WHERE id = %d", $row->id ) );
		$rest_url  = esc_url( rest_url( 'khm/v1/adverts/' . (int) $row->id . '/click' ) );
		$nonce     = wp_create_nonce( 'wp_rest' );
		$click_url = esc_url( $row->click_url ?: '' );
		$media_url = esc_url( $row->media_url );
		$alt_text  = esc_attr( $row->alt_text ?: $row->title );
		$ad_id     = (int) $row->id;
		ob_start();
		?>
		<div class="khm-sponsor-advert khm-sponsor-advert-<?php echo esc_attr( $placement ); ?>" style="margin:16px 0;text-align:center">
			<?php if ( $click_url ) : ?>
			<a href="<?php echo $click_url; ?>" target="_blank" rel="noopener sponsored"
			   onclick="(function(){fetch('<?php echo $rest_url; ?>',{method:'POST',headers:{'X-WP-Nonce':'<?php echo esc_js( $nonce ); ?>'}});})()">
				<img src="<?php echo $media_url; ?>" alt="<?php echo $alt_text; ?>" style="max-width:100%;height:auto">
			</a>
			<?php else : ?>
			<img src="<?php echo $media_url; ?>" alt="<?php echo $alt_text; ?>" style="max-width:100%;height:auto">
			<?php endif; ?>
			<p style="font-size:10px;color:#9ca3af;margin-top:4px"><?php esc_html_e( 'Sponsored', 'khm-membership' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}

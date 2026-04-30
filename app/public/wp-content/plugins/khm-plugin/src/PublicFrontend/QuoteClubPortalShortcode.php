<?php
/**
 * Quote Club Portal Shortcode
 *
 * Registers [khm_quote_club_portal] which renders the dedicated sponsor-facing
 * Quote Club experience. Completely separate from [khm_member_portal]; uses
 * SponsorService for access gating and has its own navigation and layout.
 *
 * Navigation sections:
 *   overview       — credit balances, quick stats, purchase bundles
 *   commentary     — rolling-calendar search & commentary submission
 *   press-releases — press release composer (Phase 5)
 *   tracking       — article & PR performance dashboard (Phase 6)
 *   social         — LinkedIn & scheduling (Phase 7)
 *
 * @package KHM\PublicFrontend
 */

namespace KHM\PublicFrontend;

use KHM\Services\CreditService;
use KHM\Services\MembershipRepository;
use KHM\Services\LevelRepository;
use KHM\Services\SponsorService;
use KHM\Services\QuoteClubCreditBundleService;

defined( 'ABSPATH' ) || exit;

class QuoteClubPortalShortcode {

	private CreditService $credits;
	private QuoteClubCreditBundleService $bundles;

	public function __construct() {
		$memberships    = new MembershipRepository();
		$levels         = new LevelRepository();
		$this->credits  = new CreditService( $memberships, $levels );
		$this->bundles  = new QuoteClubCreditBundleService( $this->credits );
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_shortcode( 'khm_quote_club_portal', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
		] );

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
		<div class="khm-qc-portal" data-user-id="<?php echo esc_attr( $user_id ); ?>">

			<?php $this->render_header( $user_id, $sponsor ); ?>

			<div class="khm-qc-portal-body">
				<?php $this->render_nav( $section ); ?>

				<div class="khm-qc-portal-content">
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
						default:
							$this->render_overview_section( $user_id, $sponsor );
					}
					?>
				</div>
			</div>

			<div id="khm-qc-toast" class="khm-toast" role="status" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Header
	// -------------------------------------------------------------------------

	private function render_header( int $user_id, ?array $sponsor ): void {
		$user              = get_userdata( $user_id );
		$editorial_credits = $this->credits->getEditorialCredits( $user_id );
		$pr_credits        = $this->credits->getPressReleaseCredits( $user_id );
		$sponsor_name      = isset( $sponsor['name'] ) ? sanitize_text_field( $sponsor['name'] ) : '';
		?>
		<header class="khm-qc-header">
			<div class="khm-qc-header-identity">
				<div class="khm-qc-brand">
					<span class="khm-qc-brand-name">Quote Club</span>
					<?php if ( $sponsor_name ) : ?>
						<span class="khm-qc-sponsor-name"><?php echo esc_html( $sponsor_name ); ?></span>
					<?php endif; ?>
				</div>
				<div class="khm-qc-user-name">
					<?php echo esc_html( $user ? $user->display_name : '' ); ?>
				</div>
			</div>
			<div class="khm-qc-header-credits">
				<div class="khm-qc-credit-pill">
					<span class="khm-qc-credit-value" id="qc-editorial-balance"><?php echo (int) $editorial_credits; ?></span>
					<span class="khm-qc-credit-label"><?php esc_html_e( 'Editorial Credits', 'khm-membership' ); ?></span>
				</div>
				<div class="khm-qc-credit-pill">
					<span class="khm-qc-credit-value" id="qc-pr-balance"><?php echo (int) $pr_credits; ?></span>
					<span class="khm-qc-credit-label"><?php esc_html_e( 'Press Release Credits', 'khm-membership' ); ?></span>
				</div>
				<a href="<?php echo esc_url( add_query_arg( 'qc_section', 'overview' ) ); ?>#qc-bundles"
				   class="khm-qc-btn khm-qc-btn-sm"><?php esc_html_e( 'Buy Credits', 'khm-membership' ); ?></a>
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
			'commentary'     => [ 'label' => __( 'Commentary', 'khm-membership' ),      'icon' => 'dashicons-format-quote' ],
			'press-releases' => [ 'label' => __( 'Press Releases', 'khm-membership' ),  'icon' => 'dashicons-media-document' ],
			'tracking'       => [ 'label' => __( 'Tracking', 'khm-membership' ),        'icon' => 'dashicons-chart-line' ],
			'social'         => [ 'label' => __( 'Social', 'khm-membership' ),          'icon' => 'dashicons-share' ],
		];

		$sections = apply_filters( 'khm_qc_portal_sections', $sections );
		?>
		<nav class="khm-qc-nav" role="navigation" aria-label="<?php esc_attr_e( 'Quote Club sections', 'khm-membership' ); ?>">
			<?php foreach ( $sections as $slug => $section ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'qc_section', $slug ) ); ?>"
				   class="khm-qc-nav-item<?php echo $current_section === $slug ? ' is-active' : ''; ?>"
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
		$editorial_credits = $this->credits->getEditorialCredits( $user_id );
		$pr_credits        = $this->credits->getPressReleaseCredits( $user_id );
		$active_bundles    = $this->bundles->list_bundles( true );
		?>
		<div class="khm-qc-section khm-qc-overview">
			<h2><?php esc_html_e( 'Overview', 'khm-membership' ); ?></h2>

			<div class="khm-qc-stats-grid">
				<div class="khm-qc-stat-card">
					<span class="khm-qc-stat-number"><?php echo (int) $editorial_credits; ?></span>
					<span class="khm-qc-stat-label"><?php esc_html_e( 'Editorial Credits Available', 'khm-membership' ); ?></span>
					<p class="khm-qc-stat-hint"><?php esc_html_e( '1 credit = 120 words of commentary', 'khm-membership' ); ?></p>
				</div>
				<div class="khm-qc-stat-card">
					<span class="khm-qc-stat-number"><?php echo (int) $pr_credits; ?></span>
					<span class="khm-qc-stat-label"><?php esc_html_e( 'Press Release Credits', 'khm-membership' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $active_bundles ) ) : ?>
			<div class="khm-qc-bundles" id="qc-bundles">
				<h3><?php esc_html_e( 'Purchase Credits', 'khm-membership' ); ?></h3>
				<div class="khm-qc-bundle-grid">
					<?php foreach ( $active_bundles as $bundle ) : ?>
					<div class="khm-qc-bundle-card">
						<h4><?php echo esc_html( $bundle->name ); ?></h4>
						<?php if ( $bundle->description ) : ?>
							<p><?php echo esc_html( $bundle->description ); ?></p>
						<?php endif; ?>
						<ul class="khm-qc-bundle-details">
							<?php if ( (int) $bundle->editorial_credits > 0 ) : ?>
								<li><?php echo (int) $bundle->editorial_credits; ?> <?php esc_html_e( 'Editorial Credits', 'khm-membership' ); ?></li>
							<?php endif; ?>
							<?php if ( (int) $bundle->press_release_credits > 0 ) : ?>
								<li><?php echo (int) $bundle->press_release_credits; ?> <?php esc_html_e( 'Press Release Credits', 'khm-membership' ); ?></li>
							<?php endif; ?>
						</ul>
						<div class="khm-qc-bundle-price">$<?php echo number_format( (int) $bundle->price_cents / 100, 2 ); ?></div>
						<button type="button"
						        class="khm-qc-btn khm-qc-btn-primary khm-qc-purchase-bundle"
						        data-bundle-id="<?php echo (int) $bundle->id; ?>"
						        data-bundle-name="<?php echo esc_attr( $bundle->name ); ?>">
							<?php esc_html_e( 'Buy Now', 'khm-membership' ); ?>
						</button>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="khm-qc-quick-links">
				<a href="<?php echo esc_url( add_query_arg( 'qc_section', 'connect' ) ); ?>" class="khm-qc-btn khm-qc-btn-secondary">
					<?php esc_html_e( 'Manage Connect Offerings →', 'khm-membership' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'qc_section', 'commentary' ) ); ?>" class="khm-qc-btn khm-qc-btn-secondary">
					<?php esc_html_e( 'Search Articles & Submit Commentary →', 'khm-membership' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private function render_connect_section( int $user_id, ?array $sponsor ): void {
		$categories = $this->get_top_line_categories();
		?>
		<div class="khm-qc-section khm-qc-connect">
			<div class="khm-qc-connect-shell" data-sponsor-id="<?php echo esc_attr( (int) ( $sponsor['id'] ?? 0 ) ); ?>">
				<div class="khm-qc-connect-hero">
					<div>
						<h2><?php esc_html_e( 'Connect Offerings', 'khm-membership' ); ?></h2>
						<p><?php esc_html_e( 'Manage the provider offerings that power comparison, guided matching, commentary eligibility, and future intro workflows.', 'khm-membership' ); ?></p>
					</div>
					<div class="khm-qc-connect-status" role="status" aria-live="polite"></div>
				</div>

				<div class="khm-qc-connect-grid">
					<section class="khm-qc-connect-panel khm-qc-connect-list-panel">
						<div class="khm-qc-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Your Live Offerings', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'These records are owned by your sponsor account and scoped to this site.', 'khm-membership' ); ?></p>
							</div>
							<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-connect-new"><?php esc_html_e( 'New Offering', 'khm-membership' ); ?></button>
						</div>
						<div class="khm-qc-connect-list"></div>
					</section>

					<section class="khm-qc-connect-panel khm-qc-connect-form-panel">
						<div class="khm-qc-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Offering Details', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Use typed fields for fit and delivery, then keep advanced comparison and matching metadata in JSON until the guided workflow expands.', 'khm-membership' ); ?></p>
							</div>
						</div>

						<form class="khm-qc-connect-form" id="khm-qc-connect-form">
							<input type="hidden" name="id" value="" />

							<div class="khm-qc-connect-form-grid">
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
								<label class="khm-qc-connect-span-2">
									<span><?php esc_html_e( 'Description', 'khm-membership' ); ?></span>
									<textarea name="description" rows="3"></textarea>
								</label>
								<label class="khm-qc-connect-span-2">
									<span><?php esc_html_e( 'Sweet Spot Summary', 'khm-membership' ); ?></span>
									<textarea name="sweet_spot_summary" rows="3" placeholder="Who you are best for, typical use cases, and what makes the fit strong."></textarea>
								</label>
								<label>
									<span><?php esc_html_e( 'Title Contexts', 'khm-membership' ); ?></span>
									<input type="text" name="titles" placeholder="finance, saas, cybersecurity" list="khm-qc-connect-title-contexts" />
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
									<span><?php esc_html_e( 'Company Size Min', 'khm-membership' ); ?></span>
									<input type="number" min="0" name="company_size_min" />
								</label>
								<label>
									<span><?php esc_html_e( 'Company Size Max', 'khm-membership' ); ?></span>
									<input type="number" min="0" name="company_size_max" />
								</label>
								<label>
									<span><?php esc_html_e( 'Budget Min', 'khm-membership' ); ?></span>
									<input type="number" min="0" name="budget_min" />
								</label>
								<label>
									<span><?php esc_html_e( 'Budget Max', 'khm-membership' ); ?></span>
									<input type="number" min="0" name="budget_max" />
								</label>
								<label>
									<span><?php esc_html_e( 'Typical Onboarding Days', 'khm-membership' ); ?></span>
									<input type="number" min="0" name="onboarding_days" />
								</label>
								<label>
									<span><?php esc_html_e( 'Status', 'khm-membership' ); ?></span>
									<select name="status">
										<option value="active"><?php esc_html_e( 'Active', 'khm-membership' ); ?></option>
										<option value="inactive"><?php esc_html_e( 'Inactive', 'khm-membership' ); ?></option>
									</select>
								</label>
								<label class="khm-qc-connect-check">
									<input type="checkbox" name="commentary_enabled" value="1" />
									<span><?php esc_html_e( 'Eligible for commentary contexts', 'khm-membership' ); ?></span>
								</label>
								<label class="khm-qc-connect-check">
									<input type="checkbox" name="ad_targeting_enabled" value="1" />
									<span><?php esc_html_e( 'Eligible for ad targeting', 'khm-membership' ); ?></span>
								</label>
								<label class="khm-qc-connect-span-2">
									<span><?php esc_html_e( 'Comparison Fields JSON', 'khm-membership' ); ?></span>
									<textarea name="comparison_fields" rows="6" spellcheck="false">{}</textarea>
								</label>
								<label class="khm-qc-connect-span-2">
									<span><?php esc_html_e( 'Match Rules JSON', 'khm-membership' ); ?></span>
									<textarea name="match_rules" rows="6" spellcheck="false">{}</textarea>
								</label>
							</div>

							<div class="khm-qc-connect-actions">
								<button type="submit" class="khm-qc-btn khm-qc-btn-primary khm-qc-connect-save"><?php esc_html_e( 'Save Offering', 'khm-membership' ); ?></button>
								<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-connect-reset"><?php esc_html_e( 'Reset', 'khm-membership' ); ?></button>
								<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-connect-delete" style="display:none"><?php esc_html_e( 'Delete', 'khm-membership' ); ?></button>
							</div>
						</form>

						<datalist id="khm-qc-connect-title-contexts">
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( sanitize_title( $category ) ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</section>

					<section class="khm-qc-connect-panel khm-qc-connect-leads-panel khm-qc-connect-span-full" id="khm-qc-leads-panel-<?php echo esc_attr( (string) (int) ( $sponsor['id'] ?? 0 ) ); ?>">
						<style>
							.khm-qc-leads-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}
							.khm-qc-leads-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;}
							.khm-qc-lead-card{border:1px solid #dcdcde;border-radius:6px;padding:14px;background:#fff;display:flex;flex-direction:column;gap:8px;}
							.khm-qc-lead-card[data-status="sponsor_accepted"]{border-color:#1a73e8;}
							.khm-qc-lead-card[data-status="intro_requested"],.khm-qc-lead-card[data-status="introduced"]{border-color:#1e8c45;}
							.khm-qc-lead-tier{display:inline-block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:2px 7px;border-radius:3px;background:#f0f0f1;color:#3c434a;}
							.khm-qc-lead-tier-premium{background:#e8f0fe;color:#1a73e8;}
							.khm-qc-lead-tier-standard{background:#e6f4ea;color:#1e8c45;}
							.khm-qc-lead-tier-exploratory{background:#fce8e6;color:#c5221f;}
							.khm-qc-lead-score-bar{height:4px;border-radius:2px;background:#e9e9e9;overflow:hidden;}
							.khm-qc-lead-score-fill{height:100%;border-radius:2px;background:#1a73e8;transition:width .3s;}
							.khm-qc-lead-meta{font-size:12px;color:#555;display:flex;flex-wrap:wrap;gap:6px 14px;}
							.khm-qc-lead-status{font-size:12px;font-weight:600;}
							.khm-qc-lead-actions{margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;}
							.khm-qc-lead-accept-form select{font-size:12px;padding:4px 6px;border:1px solid #ccd0d4;border-radius:4px;max-width:180px;}
							.khm-qc-leads-empty{padding:16px;color:#777;font-size:13px;}
							.khm-qc-leads-notice{font-size:12px;padding:8px 12px;border-radius:4px;margin-bottom:8px;display:none;}
							.khm-qc-leads-notice-ok{background:#e6f4ea;color:#1e8c45;}
							.khm-qc-leads-notice-err{background:#fce8e6;color:#c5221f;}
						</style>
						<div class="khm-qc-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Matched Leads', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Anonymised intent signals matched to your offerings. Accept a lead to trigger a platform-mediated introduction — buyer identity is revealed only after they explicitly request handover.', 'khm-membership' ); ?></p>
							</div>
							<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-leads-refresh"><?php esc_html_e( 'Refresh', 'khm-membership' ); ?></button>
						</div>
						<div class="khm-qc-leads-notice" role="status" aria-live="polite"></div>
						<div class="khm-qc-leads-grid"></div>
					</section>

					<script>
					(function() {
						var sponsorId   = <?php echo (int) ( $sponsor['id'] ?? 0 ); ?>;
						var restBase    = <?php echo wp_json_encode( trailingslashit( rest_url( 'khm/v1/connect' ) ) ); ?>;
						var nonce       = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
						var panel       = document.getElementById('khm-qc-leads-panel-' + sponsorId);
						if (!panel || !sponsorId) return;

						var grid   = panel.querySelector('.khm-qc-leads-grid');
						var notice = panel.querySelector('.khm-qc-leads-notice');
						var btn    = panel.querySelector('.khm-qc-leads-refresh');

						function showNotice(msg, ok) {
							notice.textContent = msg;
							notice.className = 'khm-qc-leads-notice ' + (ok ? 'khm-qc-leads-notice-ok' : 'khm-qc-leads-notice-err');
							notice.style.display = 'block';
							setTimeout(function(){ notice.style.display = 'none'; }, 5000);
						}

						function tierClass(tier) {
							if (tier === 'premium') return 'khm-qc-lead-tier-premium';
							if (tier === 'standard') return 'khm-qc-lead-tier-standard';
							if (tier === 'exploratory') return 'khm-qc-lead-tier-exploratory';
							return '';
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

						function renderCards(opps, providers) {
							if (!opps.length) {
								grid.innerHTML = '<p class="khm-qc-leads-empty"><?php echo esc_js( __( 'No matched leads in your current inbox.', 'khm-membership' ) ); ?></p>';
								return;
							}
							grid.innerHTML = '';
							opps.forEach(function(o) {
								var scorePct = Math.round((o.person_score || 0) * 100);
								var canAccept = (o.opportunity_status === 'detected' || o.opportunity_status === 'offered');
								var acceptedAlready = o.opportunity_status === 'sponsor_accepted' || o.opportunity_status === 'intro_requested' || o.opportunity_status === 'introduced';

								var providerOpts = providers.map(function(p) {
									return '<option value="' + parseInt(p.id,10) + '"' + (o.provider_id == p.id ? ' selected' : '') + '>' + p.name.replace(/</g,'&lt;') + '</option>';
								}).join('');

								var acceptHtml = '';
								if (canAccept && providers.length) {
									acceptHtml = '<div class="khm-qc-lead-accept-form">' +
										'<select class="khm-qc-lead-provider-sel"><option value=""><?php echo esc_js( __( 'Select provider…', 'khm-membership' ) ); ?></option>' + providerOpts + '</select> ' +
										'<button type="button" class="khm-qc-btn khm-qc-btn-primary khm-qc-lead-accept-btn" data-id="' + o.id + '" style="font-size:12px;padding:4px 10px;"><?php echo esc_js( __( 'Accept', 'khm-membership' ) ); ?></button>' +
									'</div>';
								} else if (acceptedAlready) {
									acceptHtml = '<span class="khm-qc-lead-status" style="color:#1e8c45;">&#10003; ' + statusLabel(o.opportunity_status) + '</span>';
								}

								var card = document.createElement('div');
								card.className = 'khm-qc-lead-card';
								card.dataset.status = o.opportunity_status;
								card.dataset.id = o.id;
								card.innerHTML =
									'<div><span class="khm-qc-lead-tier ' + tierClass(o.commercial_tier) + '">' + (o.commercial_tier || 'unknown') + '</span></div>' +
									'<div class="khm-qc-lead-score-bar"><div class="khm-qc-lead-score-fill" style="width:' + scorePct + '%"></div></div>' +
									'<div class="khm-qc-lead-meta">' +
										'<span><?php echo esc_js( __( 'Score', 'khm-membership' ) ); ?>: <strong>' + scorePct + '%</strong></span>' +
										'<span><?php echo esc_js( __( 'Stage', 'khm-membership' ) ); ?>: ' + (o.internal_stage || '—') + '</span>' +
										'<span><?php echo esc_js( __( 'Price', 'khm-membership' ) ); ?>: ' + formatPrice(o.unit_price_cents) + ' ' + (o.pricing_model || '') + '</span>' +
									'</div>' +
									(acceptHtml ? '<div class="khm-qc-lead-actions">' + acceptHtml + '</div>' : '');
								grid.appendChild(card);
							});

							// Bind accept buttons
							grid.querySelectorAll('.khm-qc-lead-accept-btn').forEach(function(btn) {
								btn.addEventListener('click', function() {
									var oppId     = parseInt(btn.dataset.id, 10);
									var sel       = btn.closest('.khm-qc-lead-accept-form').querySelector('.khm-qc-lead-provider-sel');
									var providerId = sel ? parseInt(sel.value, 10) : 0;
									if (!providerId) { showNotice(<?php echo wp_json_encode( __( 'Please select a provider first.', 'khm-membership' ) ); ?>, false); return; }
									btn.disabled = true;
									fetch(restBase + 'opportunities/mine/' + oppId + '/accept', {
										method: 'POST',
										headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
										body: JSON.stringify({ provider_id: providerId })
									}).then(function(r){ return r.json(); }).then(function(d) {
										if (d.success) {
											showNotice(<?php echo wp_json_encode( __( 'Lead accepted — intro thread will open shortly.', 'khm-membership' ) ); ?>, true);
											loadLeads();
										} else {
											showNotice((d.message || <?php echo wp_json_encode( __( 'Accept failed.', 'khm-membership' ) ); ?>), false);
											btn.disabled = false;
										}
									}).catch(function(){ showNotice(<?php echo wp_json_encode( __( 'Network error — please try again.', 'khm-membership' ) ); ?>, false); btn.disabled = false; });
								});
							});
						}

						function loadLeads() {
							grid.innerHTML = '<p class="khm-qc-leads-empty"><?php echo esc_js( __( 'Loading…', 'khm-membership' ) ); ?></p>';
							// Load opportunities + providers in parallel
							Promise.all([
								fetch(restBase + 'opportunities/mine', { headers: { 'X-WP-Nonce': nonce } }).then(function(r){ return r.json(); }),
								fetch(<?php echo wp_json_encode( trailingslashit( rest_url( 'khm/v1/connect' ) ) . 'providers/mine' ); ?>, { headers: { 'X-WP-Nonce': nonce } }).then(function(r){ return r.json(); }).catch(function(){ return { providers: [] }; })
							]).then(function(results) {
								var opps      = (results[0].opportunities || []);
								var providers = (results[1].providers     || []);
								renderCards(opps, providers);
							}).catch(function() {
								grid.innerHTML = '<p class="khm-qc-leads-empty" style="color:#c5221f;"><?php echo esc_js( __( 'Failed to load leads.', 'khm-membership' ) ); ?></p>';
							});
						}

						btn.addEventListener('click', loadLeads);
						loadLeads();
					})();
					</script>

					<section class="khm-qc-connect-panel khm-qc-connect-inbox-panel khm-qc-connect-span-full">
						<div class="khm-qc-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Intro Inbox', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Replies stay platform-mediated until a buyer explicitly requests handover and your team confirms it.', 'khm-membership' ); ?></p>
							</div>
						</div>
						<div class="khm-qc-connect-inbox-grid">
							<div class="khm-qc-connect-thread-list"></div>
							<div class="khm-qc-connect-thread-detail">
								<div class="khm-qc-connect-empty"><?php esc_html_e( 'Select an intro thread to review messages, reply, and manage handover.', 'khm-membership' ); ?></div>
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
		<div class="khm-qc-section khm-qc-commentary">
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
		?>
		<div class="khm-qc-section khm-qc-press-releases">
			<h2><?php esc_html_e( 'Press Releases', 'khm-membership' ); ?></h2>
			
			<div class="khm-qc-pr-toolbar">
				<button class="khm-qc-btn khm-qc-btn-primary" id="pr-create-btn">
					<?php esc_html_e( '+ Create New Press Release', 'khm-membership' ); ?>
				</button>
			</div>

			<!-- Press release list -->
			<div id="pr-list" class="khm-qc-pr-list"></div>

			<!-- Create/Edit form modal -->
			<div id="pr-form-modal" class="khm-qc-modal" style="display:none">
				<div class="khm-qc-modal-content">
					<div class="khm-qc-modal-header">
						<h3 id="pr-form-title"><?php esc_html_e( 'Create Press Release', 'khm-membership' ); ?></h3>
						<button class="khm-qc-modal-close" id="pr-form-close">×</button>
					</div>
					<div class="khm-qc-modal-body">
						<form id="pr-form">
							<div class="khm-qc-form-group">
								<label for="pr-title"><?php esc_html_e( 'Title', 'khm-membership' ); ?> *</label>
								<input type="text" id="pr-title" name="title" required maxlength="255"
									   placeholder="<?php esc_attr_e( 'Press release title', 'khm-membership' ); ?>"
									   class="khm-qc-input">
							</div>
							<div class="khm-qc-form-group">
								<label for="pr-content"><?php esc_html_e( 'Content', 'khm-membership' ); ?> *</label>
								<textarea id="pr-content" name="content" required rows="8"
										  placeholder="<?php esc_attr_e( 'Write your press release content here...', 'khm-membership' ); ?>"
										  class="khm-qc-textarea"></textarea>
								<small><?php esc_html_e( 'Press releases can be any length.', 'khm-membership' ); ?></small>
							</div>
						</form>
					</div>
					<div class="khm-qc-modal-footer">
						<button class="khm-qc-btn" id="pr-form-cancel"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
						<button class="khm-qc-btn khm-qc-btn-primary" id="pr-form-save-draft"><?php esc_html_e( 'Save Draft', 'khm-membership' ); ?></button>
						<button class="khm-qc-btn khm-qc-btn-success" id="pr-form-submit" style="display:none">
							<?php esc_html_e( 'Save & Submit (1 Credit)', 'khm-membership' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- View/Confirm modal -->
			<div id="pr-confirm-modal" class="khm-qc-modal" style="display:none">
				<div class="khm-qc-modal-content" style="max-width:700px">
					<div class="khm-qc-modal-header">
						<h3><?php esc_html_e( 'Confirm Submission', 'khm-membership' ); ?></h3>
						<button class="khm-qc-modal-close" id="pr-confirm-close">×</button>
					</div>
					<div class="khm-qc-modal-body">
						<div id="pr-confirm-preview" style="background:#f9f9f9;padding:1rem;border-radius:4px;margin-bottom:1rem"></div>
						<div class="khm-qc-alert khm-qc-alert-info">
							<strong><?php esc_html_e( 'Cost:', 'khm-membership' ); ?></strong> 
							<?php esc_html_e( '1 Press Release Credit', 'khm-membership' ); ?>
						</div>
					</div>
					<div class="khm-qc-modal-footer">
						<button class="khm-qc-btn" id="pr-confirm-cancel"><?php esc_html_e( 'Back to Edit', 'khm-membership' ); ?></button>
						<button class="khm-qc-btn khm-qc-btn-success" id="pr-confirm-submit"><?php esc_html_e( 'Confirm & Submit', 'khm-membership' ); ?></button>
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
						html = '<p class="khm-qc-empty"><?php esc_html_e( 'No press releases yet. Create one to get started.', 'khm-membership' ); ?></p>';
					} else {
						html = '<div class="khm-qc-pr-items">';
						$.each(prList, function(i, pr) {
							var statusClass = 'khm-qc-badge-' + pr.status;
							var actions = '';
							if (pr.status === 'draft') {
								actions = '<button class="khm-qc-btn khm-qc-btn-sm pr-edit-btn" data-id="' + pr.id + '">Edit</button> ';
								actions += '<button class="khm-qc-btn khm-qc-btn-sm pr-delete-btn" data-id="' + pr.id + '">Delete</button>';
							}
							html += '<div class="khm-qc-pr-item">' +
								'<div class="khm-qc-pr-item-header">' +
								'<h4>' + (pr.title || '(Untitled)') + '</h4>' +
								'<span class="khm-qc-badge ' + statusClass + '">' + pr.status + '</span>' +
								'</div>' +
								'<p class="khm-qc-pr-item-excerpt">' + (pr.excerpt || '') + '</p>' +
								'<div class="khm-qc-pr-item-meta">' +
								'<small>Created: ' + pr.created_at.substring(0, 10) + '</small>' +
								(pr.status === 'published' ? '<small>Published: ' + pr.published_date.substring(0, 10) + '</small>' : '') +
								(pr.status === 'rejected' ? '<small style="color:#991b1b">Rejected</small>' : '') +
								'</div>' +
								(actions ? '<div class="khm-qc-pr-item-actions">' + actions + '</div>' : '') +
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

					var method, url, data;
					if (currentPrId) {
						method = 'PUT';
						url = restUrl + '/' + currentPrId;
						data = { title: title, content: content };
					} else {
						method = 'POST';
						url = restUrl;
						data = { title: title, content: content };
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
		<div class="khm-qc-section khm-qc-tracking">
			<h2><?php esc_html_e( 'Tracking', 'khm-membership' ); ?></h2>
			<p class="khm-qc-coming-soon">
				<?php esc_html_e( 'Submission tracking and GEO / SEO performance metrics for your articles and press releases will appear here.', 'khm-membership' ); ?>
			</p>
		</div>
		<?php
	}

	private function render_social_section( int $user_id, ?array $sponsor ): void {
		?>
		<div class="khm-qc-section khm-qc-social">
			<h2><?php esc_html_e( 'Social Media', 'khm-membership' ); ?></h2>
			<p class="khm-qc-coming-soon">
				<?php esc_html_e( 'LinkedIn connection and scheduled post management will be available here.', 'khm-membership' ); ?>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Access gate messages
	// -------------------------------------------------------------------------

	private function render_login_required(): string {
		$login_url = wp_login_url( get_permalink() );
		ob_start();
		?>
		<div class="khm-qc-access-gate">
			<h2><?php esc_html_e( 'Quote Club', 'khm-membership' ); ?></h2>
			<p><?php esc_html_e( 'Please log in to access the Quote Club sponsor portal.', 'khm-membership' ); ?></p>
			<a href="<?php echo esc_url( $login_url ); ?>" class="khm-qc-btn khm-qc-btn-primary"><?php esc_html_e( 'Log In', 'khm-membership' ); ?></a>
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
		<div class="khm-qc-access-gate">
			<h2><?php esc_html_e( 'Quote Club', 'khm-membership' ); ?></h2>
			<p><?php esc_html_e( 'This portal is available to Quote Club sponsor accounts. If you believe you should have access, please contact support.', 'khm-membership' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}

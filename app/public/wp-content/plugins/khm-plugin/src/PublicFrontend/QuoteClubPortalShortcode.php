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
		// S17 — serve a sponsor advert in any page/template.
		add_shortcode( 'khm_sponsor_advert', [ $this, 'render_sponsor_advert_shortcode' ] );
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
			'shareLinkedInBase' => esc_url_raw( add_query_arg( [ 'qc_section' => 'social', 'li_suggest_title' => '' ], get_permalink( get_queried_object_id() ) ?: home_url( '/quote-club/' ) ) ),
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
						case 'adverts':
							$this->render_adverts_section( $user_id, $sponsor );
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
			'adverts'        => [ 'label' => __( 'Adverts', 'khm-membership' ),         'icon' => 'dashicons-megaphone' ],
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
		$boost_credits     = (int) get_user_meta( $user_id, 'khm_boost_credits', true );
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
				<div class="khm-qc-stat-card">
					<span class="khm-qc-stat-number" id="qc-boost-balance"><?php echo (int) $boost_credits; ?></span>
					<span class="khm-qc-stat-label"><?php esc_html_e( 'Boost Credits', 'khm-membership' ); ?></span>
					<p class="khm-qc-stat-hint"><?php esc_html_e( '1 credit = +0.1 score weight on one article card', 'khm-membership' ); ?></p>
				</div>
			</div>

			<!-- S8: Boost purchase panel -->
			<div class="khm-qc-boost-panel" id="khm-qc-boost-panel">
				<h3><?php esc_html_e( 'Boost Credits', 'khm-membership' ); ?></h3>
				<p><?php esc_html_e( 'Each Boost Credit applies a +0.1 scoring weight to one article answer-card session, improving its ranking in guided matching results.', 'khm-membership' ); ?></p>
				<style>
					.khm-qc-boost-qty{display:flex;align-items:center;gap:10px;margin:10px 0;}
					.khm-qc-boost-qty input[type=number]{width:80px;padding:6px 8px;border:1px solid #ccd0d4;border-radius:4px;font-size:14px;}
					.khm-qc-boost-price{font-size:13px;color:#555;}
					.khm-qc-boost-notice{font-size:12px;padding:8px 12px;border-radius:4px;margin-top:8px;display:none;}
				</style>
				<div class="khm-qc-boost-qty">
					<label for="khm-boost-qty"><?php esc_html_e( 'Quantity:', 'khm-membership' ); ?></label>
					<input type="number" id="khm-boost-qty" min="1" max="100" value="5" />
					<span class="khm-qc-boost-price"><?php
						/* translators: price per boost credit */
						printf( esc_html__( '$%s per credit', 'khm-membership' ), '5.00' );
					?></span>
				</div>
				<button type="button" class="khm-qc-btn khm-qc-btn-primary" id="khm-boost-buy-btn"><?php esc_html_e( 'Request Boost Credits', 'khm-membership' ); ?></button>
				<div class="khm-qc-boost-notice" role="status" aria-live="polite"></div>
			</div>

			<script>
			(function(){
				var restBase = <?php echo wp_json_encode( trailingslashit( rest_url( 'khm/v1/connect' ) ) ); ?>;
				var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
				var panel    = document.getElementById('khm-qc-boost-panel');
				if (!panel) return;

				var btn     = document.getElementById('khm-boost-buy-btn');
				var qtyIn   = document.getElementById('khm-boost-qty');
				var notice  = panel.querySelector('.khm-qc-boost-notice');
				var balance = document.getElementById('qc-boost-balance');

				function showNotice(msg, ok){
					notice.textContent = msg;
					notice.style.background = ok ? '#e6f4ea' : '#fce8e6';
					notice.style.color       = ok ? '#1e8c45' : '#c5221f';
					notice.style.display     = 'block';
					setTimeout(function(){ notice.style.display='none'; }, 6000);
				}

				btn.addEventListener('click', function(){
					var qty = Math.max(1, Math.min(100, parseInt(qtyIn.value, 10) || 1));
					btn.disabled = true;
					fetch(restBase + 'boost', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
						body: JSON.stringify({ quantity: qty })
					}).then(function(r){ return r.json(); })
					.then(function(d){
						if (d.success){
							showNotice(d.message || <?php echo wp_json_encode( __( 'Request received.', 'khm-membership' ) ); ?>, true);
							if (balance && d.balance !== undefined) balance.textContent = d.balance;
						} else {
							showNotice(d.message || <?php echo wp_json_encode( __( 'Request failed.', 'khm-membership' ) ); ?>, false);
						}
						btn.disabled = false;
					}).catch(function(){
						showNotice(<?php echo wp_json_encode( __( 'Network error — try again.', 'khm-membership' ) ); ?>, false);
						btn.disabled = false;
					});
				});
			})();
			</script>

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
					<section class="khm-qc-connect-panel khm-qc-connect-subscription-panel khm-qc-connect-span-full" id="khm-qc-sub-panel-<?php echo esc_attr( (int) ( $sponsor['id'] ?? 0 ) ); ?>">
						<style>
							.khm-qc-sub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin:12px 0;}
							.khm-qc-sub-card{border:2px solid #dcdcde;border-radius:8px;padding:16px;cursor:pointer;transition:border-color .2s,box-shadow .2s;background:#fff;}
							.khm-qc-sub-card:hover{border-color:#1a73e8;box-shadow:0 2px 8px rgba(26,115,232,.15);}
							.khm-qc-sub-card.selected{border-color:#1a73e8;background:#e8f0fe;}
							.khm-qc-sub-card h4{margin:0 0 4px;font-size:15px;text-transform:capitalize;}
							.khm-qc-sub-card .price{font-size:20px;font-weight:700;color:#1a73e8;margin:6px 0 2px;}
							.khm-qc-sub-card .model{font-size:11px;color:#777;text-transform:uppercase;letter-spacing:.04em;}
							.khm-qc-sub-card .commission{font-size:12px;color:#555;margin-top:4px;}
							.khm-qc-sub-current{font-size:13px;padding:10px 14px;border-radius:6px;background:#e6f4ea;color:#1e8c45;display:none;margin-bottom:10px;}
							.khm-qc-sub-pending{font-size:13px;padding:10px 14px;border-radius:6px;background:#fef9e7;color:#b5770d;display:none;margin-bottom:10px;}
							.khm-qc-sub-scope{display:flex;gap:10px;margin:8px 0 12px;flex-wrap:wrap;}
							.khm-qc-sub-scope label{font-size:13px;display:flex;align-items:center;gap:4px;}
						</style>
						<div class="khm-qc-connect-panel-head">
							<div>
								<h3><?php esc_html_e( 'Connect Subscription', 'khm-membership' ); ?></h3>
								<p><?php esc_html_e( 'Activate a Connect subscription to appear in matching, receive anonymised leads, and unlock introduction workflows with qualified buyers.', 'khm-membership' ); ?></p>
							</div>
						</div>
						<div class="khm-qc-sub-current" role="status"></div>
						<div class="khm-qc-sub-pending" role="status"></div>
						<div class="khm-qc-sub-grid" aria-label="<?php esc_attr_e( 'Select a tier', 'khm-membership' ); ?>"></div>
						<div class="khm-qc-sub-scope">
							<strong style="font-size:13px;align-self:center;"><?php esc_html_e( 'Scope:', 'khm-membership' ); ?></strong>
							<label><input type="radio" name="khm-qc-sub-scope" value="site" checked /> <?php esc_html_e( 'This site only', 'khm-membership' ); ?></label>
							<label><input type="radio" name="khm-qc-sub-scope" value="portfolio" /> <?php esc_html_e( 'Portfolio-wide (all sites)', 'khm-membership' ); ?></label>
						</div>
						<div class="khm-qc-sub-notice" style="display:none;font-size:12px;padding:8px 12px;border-radius:4px;margin-bottom:8px;"></div>
						<button type="button" class="khm-qc-btn khm-qc-btn-primary khm-qc-sub-request-btn" disabled><?php esc_html_e( 'Request Subscription', 'khm-membership' ); ?></button>
					</section>

					<script>
					(function(){
						var sponsorId = <?php echo (int) ( $sponsor['id'] ?? 0 ); ?>;
						var restBase  = <?php echo wp_json_encode( trailingslashit( rest_url( 'khm/v1/connect' ) ) ); ?>;
						var nonce     = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
						var panel     = document.getElementById('khm-qc-sub-panel-' + sponsorId);
						if (!panel || !sponsorId) return;

						var grid      = panel.querySelector('.khm-qc-sub-grid');
						var curBanner = panel.querySelector('.khm-qc-sub-current');
						var penBanner = panel.querySelector('.khm-qc-sub-pending');
						var notice    = panel.querySelector('.khm-qc-sub-notice');
						var btn       = panel.querySelector('.khm-qc-sub-request-btn');
						var selected  = null;

						function fmt(cents){ return '$' + (cents/100).toFixed(2); }

						function showNotice(msg, ok){
							notice.textContent = msg;
							notice.style.background = ok ? '#e6f4ea' : '#fce8e6';
							notice.style.color = ok ? '#1e8c45' : '#c5221f';
							notice.style.display = 'block';
							setTimeout(function(){ notice.style.display='none'; }, 6000);
						}

						function renderTiers(pricing, sub){
							grid.innerHTML = '';
							['premium','standard','exploratory'].forEach(function(tier){
								var p    = pricing[tier] || {};
								var card = document.createElement('div');
								card.className = 'khm-qc-sub-card' + (sub && sub.tier === tier && sub.status === 'active' ? ' selected' : '');
								card.dataset.tier = tier;
								card.innerHTML =
									'<h4>' + tier + '</h4>' +
									'<div class="price">' + fmt(p.unit_price_cents||0) + '</div>' +
									'<div class="model">' + (p.pricing_model||'') + '</div>' +
									'<div class="commission"><?php echo esc_js( __( 'Commission eligible:', 'khm-membership' ) ); ?> ' + (p.commission_eligible ? '<?php echo esc_js( __( 'Yes', 'khm-membership' ) ); ?>' : '<?php echo esc_js( __( 'No', 'khm-membership' ) ); ?>') + '</div>';
								card.addEventListener('click', function(){
									grid.querySelectorAll('.khm-qc-sub-card').forEach(function(c){ c.classList.remove('selected'); });
									card.classList.add('selected');
									selected = tier;
									btn.disabled = false;
								});
								grid.appendChild(card);
							});
						}

						function loadSubscription(){
							fetch(restBase + 'subscription', { headers:{'X-WP-Nonce':nonce} })
								.then(function(r){ return r.json(); })
								.then(function(d){
									var sub     = d.subscription || {};
									var pricing = d.pricing     || {};
									curBanner.style.display = 'none';
									penBanner.style.display = 'none';
									if (sub.status === 'active'){
										curBanner.textContent = <?php echo wp_json_encode( __( 'Active subscription: ', 'khm-membership' ) ); ?> + sub.tier + ' (' + sub.scope + ')';
										curBanner.style.display = 'block';
									} else if (sub.status === 'pending'){
										penBanner.textContent = <?php echo wp_json_encode( __( 'Subscription pending activation — ', 'khm-membership' ) ); ?> + sub.tier + ' (' + sub.scope + '). <?php echo esc_js( __( 'We will notify you when it goes live.', 'khm-membership' ) ); ?>';
										penBanner.style.display = 'block';
									}
									renderTiers(pricing, sub);
								}).catch(function(){
									grid.innerHTML = '<p style="color:#c5221f;font-size:13px;"><?php echo esc_js( __( 'Unable to load subscription info.', 'khm-membership' ) ); ?></p>';
								});
						}

						btn.addEventListener('click', function(){
							if (!selected) return;
							var scope = (panel.querySelector('input[name="khm-qc-sub-scope"]:checked') || {}).value || 'site';
							btn.disabled = true;
							fetch(restBase + 'subscription', {
								method:'POST',
								headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
								body: JSON.stringify({tier:selected, scope:scope})
							}).then(function(r){ return r.json(); })
							.then(function(d){
								if (d.success){
									showNotice(d.message || <?php echo wp_json_encode( __( 'Request submitted.', 'khm-membership' ) ); ?>, true);
									loadSubscription();
								} else {
									showNotice(d.message || <?php echo wp_json_encode( __( 'Request failed.', 'khm-membership' ) ); ?>, false);
								}
								btn.disabled = false;
							}).catch(function(){
								showNotice(<?php echo wp_json_encode( __( 'Network error — please try again.', 'khm-membership' ) ); ?>, false);
								btn.disabled = false;
							});
						});

						loadSubscription();
					})();
					</script>

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
							<?php if ( ! empty( $portfolio_sites ) ) : ?>
							<div class="khm-qc-form-group" id="pr-dist-group">
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
								(pr.status === 'published'
									? '<div class="khm-qc-pr-item-actions"><a href="' + khmQuoteClub.shareLinkedInBase + encodeURIComponent(pr.title || '') + '" class="khm-qc-btn khm-qc-btn-sm" style="background:#0a66c2;color:#fff;border-color:#0a66c2">&#128279; Share on LinkedIn</a></div>'
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
		<div class="khm-qc-section khm-qc-tracking">
			<h2><?php esc_html_e( 'Tracking', 'khm-membership' ); ?></h2>
			<p class="khm-qc-coming-soon">
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
		<div class="khm-qc-section khm-qc-adverts">
			<h2><?php esc_html_e( 'Advert Creatives', 'khm-membership' ); ?></h2>
			<p class="khm-qc-lead">
				<?php esc_html_e( 'Upload banner or image ad creatives for review. Once approved, they will appear alongside relevant content on the site.', 'khm-membership' ); ?>
			</p>

			<style>
				.khm-qc-adverts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin:20px 0}
				.khm-qc-advert-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;position:relative}
				.khm-qc-advert-card img{max-width:100%;height:120px;object-fit:cover;border-radius:4px;margin-bottom:10px}
				.khm-qc-advert-card .khm-qc-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;text-transform:uppercase}
				.khm-qc-badge-draft{background:#f3f4f6;color:#374151}
				.khm-qc-badge-pending{background:#fef3c7;color:#92400e}
				.khm-qc-badge-approved{background:#d1fae5;color:#065f46}
				.khm-qc-badge-rejected{background:#fee2e2;color:#991b1b}
				.khm-qc-badge-paused{background:#e5e7eb;color:#6b7280}
				.khm-qc-advert-card h4{margin:6px 0 4px;font-size:14px}
				.khm-qc-advert-card .khm-qc-placement{font-size:12px;color:#6b7280;margin-bottom:8px}
				.khm-qc-advert-card .khm-qc-stats{font-size:11px;color:#9ca3af;margin-bottom:10px}
				.khm-qc-advert-card button{margin-right:6px;font-size:12px;padding:4px 10px}
				#khm-qc-advert-form{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:24px;margin:24px 0;display:none}
				#khm-qc-advert-form h3{margin-top:0}
				#khm-qc-advert-form label{display:block;font-weight:600;margin:10px 0 4px;font-size:13px}
				#khm-qc-advert-form input[type=text],#khm-qc-advert-form select,#khm-qc-advert-form input[type=url],#khm-qc-advert-form input[type=date]{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
				#khm-qc-advert-form .khm-qc-media-preview{max-width:100%;height:120px;object-fit:cover;border-radius:4px;margin:8px 0;display:none}
				#khm-qc-advert-form .khm-qc-form-actions{margin-top:16px;display:flex;gap:8px;flex-wrap:wrap}
				.khm-qc-advert-rejection{font-size:12px;color:#991b1b;background:#fee2e2;padding:6px 10px;border-radius:4px;margin:6px 0}
				.khm-qc-advert-metrics{display:flex;gap:12px;font-size:12px;color:#374151;margin-top:6px}
				.khm-qc-advert-metrics span{font-weight:600}
				/* preview modal */
				#khm-qc-advert-preview-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center}
				#khm-qc-advert-preview-modal.active{display:flex}
				#khm-qc-advert-preview-box{background:#fff;border-radius:12px;padding:28px;max-width:540px;width:92%;max-height:82vh;overflow-y:auto;position:relative}
				#khm-qc-advert-preview-box h4{margin-top:0;font-size:16px}
				#khm-qc-advert-preview-box img{max-width:100%;border-radius:6px;margin-bottom:12px;display:block}
				#khm-qc-advert-preview-box .khm-preview-link{font-size:12px;color:#6b7280;word-break:break-all;margin-top:6px}
				#khm-qc-advert-preview-close{position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#6b7280;line-height:1;padding:0}
				/* analytics panel */
				#khm-qc-advert-analytics{display:none;margin:20px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px}
				#khm-qc-advert-analytics h3{margin-top:0;font-size:15px}
				.khm-analytics-kpis{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
				.khm-analytics-kpi{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 18px;min-width:100px}
				.khm-analytics-kpi .khm-kpi-val{font-size:22px;font-weight:700;color:#111827}
				.khm-analytics-kpi .khm-kpi-lbl{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px}
				#khm-qc-advert-analytics table{width:100%;border-collapse:collapse;font-size:13px;margin-top:12px}
				#khm-qc-advert-analytics th{text-align:left;padding:6px 10px;border-bottom:2px solid #e5e7eb;font-weight:600;color:#374151}
				#khm-qc-advert-analytics td{padding:6px 10px;border-bottom:1px solid #f3f4f6}
			</style>

			<!-- Create button -->
			<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
			<button class="button button-primary" id="khm-qc-advert-new-btn">
				+ <?php esc_html_e( 'New Creative', 'khm-membership' ); ?>
			</button>
			<button class="button" id="khm-qc-advert-analytics-btn">
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
			<div id="khm-qc-advert-analytics">
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
			<div id="khm-qc-advert-form">
				<h3 id="khm-qc-advert-form-title"><?php esc_html_e( 'New Ad Creative', 'khm-membership' ); ?></h3>
				<input type="hidden" id="khm-qc-advert-edit-id" value="">

				<label for="khm-qc-advert-title"><?php esc_html_e( 'Internal title', 'khm-membership' ); ?></label>
				<input type="text" id="khm-qc-advert-title" placeholder="<?php esc_attr_e( 'e.g. Summer banner – commentary', 'khm-membership' ); ?>">

				<label for="khm-qc-advert-placement"><?php esc_html_e( 'Placement', 'khm-membership' ); ?></label>
				<select id="khm-qc-advert-placement">
					<option value="commentary"><?php esc_html_e( 'Commentary', 'khm-membership' ); ?></option>
					<option value="press-release"><?php esc_html_e( 'Press Releases', 'khm-membership' ); ?></option>
					<option value="overview"><?php esc_html_e( 'Overview dashboard', 'khm-membership' ); ?></option>
					<option value="sidebar"><?php esc_html_e( 'Sidebar', 'khm-membership' ); ?></option>
				</select>

				<label for="khm-qc-advert-media-id"><?php esc_html_e( 'Attachment ID (from Media Library)', 'khm-membership' ); ?></label>
				<input type="text" id="khm-qc-advert-media-id" placeholder="e.g. 42" inputmode="numeric">
				<img id="khm-qc-advert-media-preview" class="khm-qc-media-preview" src="" alt="">

				<label for="khm-qc-advert-click-url"><?php esc_html_e( 'Click-through URL', 'khm-membership' ); ?></label>
				<input type="url" id="khm-qc-advert-click-url" placeholder="https://example.com/landing-page">

				<label for="khm-qc-advert-alt"><?php esc_html_e( 'Alt text', 'khm-membership' ); ?></label>
				<input type="text" id="khm-qc-advert-alt" placeholder="<?php esc_attr_e( 'Short description of the image', 'khm-membership' ); ?>">

				<label for="khm-qc-advert-start"><?php esc_html_e( 'Start date (optional)', 'khm-membership' ); ?></label>
				<input type="date" id="khm-qc-advert-start" title="<?php esc_attr_e( 'Leave blank to serve immediately when approved', 'khm-membership' ); ?>">

				<label for="khm-qc-advert-end"><?php esc_html_e( 'End date (optional)', 'khm-membership' ); ?></label>
				<input type="date" id="khm-qc-advert-end" title="<?php esc_attr_e( 'Leave blank to run indefinitely', 'khm-membership' ); ?>">

				<div class="khm-qc-form-actions">
					<button class="button button-primary" id="khm-qc-advert-save-btn"><?php esc_html_e( 'Save draft', 'khm-membership' ); ?></button>
					<button class="button button-secondary" id="khm-qc-advert-submit-btn" style="display:none"><?php esc_html_e( 'Submit for review', 'khm-membership' ); ?></button>
					<button class="button" id="khm-qc-advert-cancel-btn"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
				</div>
				<p id="khm-qc-advert-form-msg" style="margin-top:10px;font-size:13px"></p>
			</div>

			<!-- Adverts list -->
			<div class="khm-qc-adverts-grid" id="khm-qc-adverts-grid">
				<p style="color:#6b7280;font-style:italic"><?php esc_html_e( 'Loading your creatives…', 'khm-membership' ); ?></p>
			</div>

			<!-- Preview modal -->
			<div id="khm-qc-advert-preview-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Ad preview', 'khm-membership' ); ?>">
				<div id="khm-qc-advert-preview-box">
					<button id="khm-qc-advert-preview-close" aria-label="Close">&times;</button>
					<div id="khm-qc-advert-preview-content"></div>
				</div>
			</div>
		</div>

		<script>
		(function () {
			'use strict';
			var REST = '<?php echo esc_js( $rest_root ); ?>';
			var NONCE = '<?php echo esc_js( $nonce ); ?>';

			var grid     = document.getElementById('khm-qc-adverts-grid');
			var form     = document.getElementById('khm-qc-advert-form');
			var formTitle = document.getElementById('khm-qc-advert-form-title');
			var editId   = document.getElementById('khm-qc-advert-edit-id');
			var fTitle   = document.getElementById('khm-qc-advert-title');
			var fPlace   = document.getElementById('khm-qc-advert-placement');
			var fMedia   = document.getElementById('khm-qc-advert-media-id');
			var fPreview = document.getElementById('khm-qc-advert-media-preview');
			var fClick   = document.getElementById('khm-qc-advert-click-url');
			var fAlt     = document.getElementById('khm-qc-advert-alt');
			var fStart   = document.getElementById('khm-qc-advert-start');
			var fEnd     = document.getElementById('khm-qc-advert-end');
			var fSave    = document.getElementById('khm-qc-advert-save-btn');
			var fSubmit  = document.getElementById('khm-qc-advert-submit-btn');
			var fCancel  = document.getElementById('khm-qc-advert-cancel-btn');
			var fMsg     = document.getElementById('khm-qc-advert-form-msg');
			var newBtn        = document.getElementById('khm-qc-advert-new-btn');
			var analyticsBtn  = document.getElementById('khm-qc-advert-analytics-btn');
			var analyticsPanel = document.getElementById('khm-qc-advert-analytics');
			var previewModal  = document.getElementById('khm-qc-advert-preview-modal');
			var previewContent = document.getElementById('khm-qc-advert-preview-content');
			var previewClose  = document.getElementById('khm-qc-advert-preview-close');
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
				var badge = '<span class="khm-qc-badge khm-qc-badge-' + ad.status + '">' + (STATUS_LABELS[ad.status] || ad.status) + '</span>';
				var rej   = ad.rejection_reason ? '<div class="khm-qc-advert-rejection">Rejected: ' + ad.rejection_reason + '</div>' : '';
				var metrics = '<div class="khm-qc-advert-metrics"><span>' + ad.impressions + '</span> impressions &nbsp;·&nbsp; <span>' + ad.clicks + '</span> clicks</div>';
				var editBtn   = (ad.status === 'draft' || ad.status === 'rejected') ? '<button class="button khm-qc-advert-edit" data-id="' + ad.id + '">Edit</button>' : '';
				var submitBtn = (ad.status === 'draft' || ad.status === 'rejected') ? '<button class="button button-primary khm-qc-advert-submit" data-id="' + ad.id + '">Submit</button>' : '';
				var previewBtn = '<button class="button khm-qc-advert-preview" data-id="' + ad.id + '">Preview</button>';
				var dupeBtn   = '<button class="button khm-qc-advert-dupe" data-id="' + ad.id + '" title="Duplicate as new draft">Duplicate</button>';

				return '<div class="khm-qc-advert-card" id="khm-advert-' + ad.id + '">' +
					img + badge + rej +
					'<h4>' + ad.title + '</h4>' +
					'<div class="khm-qc-placement">' + (PLACEMENT_LABELS[ad.placement] || ad.placement) + '</div>' +
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
					grid.querySelectorAll('.khm-qc-advert-edit').forEach(function (btn) {
						btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
					});
					grid.querySelectorAll('.khm-qc-advert-submit').forEach(function (btn) {
						btn.addEventListener('click', function () { submitAdvert(btn.dataset.id); });
					});
					grid.querySelectorAll('.khm-qc-advert-preview').forEach(function (btn) {
						btn.addEventListener('click', function () { openPreview(btn.dataset.id); });
					});
					grid.querySelectorAll('.khm-qc-advert-dupe').forEach(function (btn) {
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
							'<td><span class="khm-qc-badge khm-qc-badge-'+a.status+'">'+(STATUS_LABELS[a.status]||a.status)+'</span></td>'+
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
		<div class="khm-qc-section khm-qc-social">
			<h2><?php esc_html_e( 'LinkedIn Scheduling', 'khm-membership' ); ?></h2>
			<p class="khm-qc-lead">
				<?php esc_html_e( 'Connect your LinkedIn account to schedule posts that go out alongside your press releases and commentary.', 'khm-membership' ); ?>
			</p>

			<style>
				#khm-qc-li-connect-panel,#khm-qc-li-queue-panel{transition:all .2s}
				#khm-qc-li-connected-banner{display:none;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
				#khm-qc-li-disconnected-banner{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;text-align:center}
				#khm-qc-li-schedule-form{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:20px}
				#khm-qc-li-schedule-form label{display:block;font-weight:600;font-size:13px;margin-bottom:4px;margin-top:12px}
				#khm-qc-li-schedule-form label:first-child{margin-top:0}
				#khm-qc-li-text{width:100%;min-height:100px;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;resize:vertical}
				#khm-qc-li-url{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
				#khm-qc-li-when{padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
				#khm-qc-li-char-count{font-size:11px;color:#6b7280;margin-top:3px;text-align:right}
				.khm-qc-li-post-row{background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:12px 14px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
				.khm-qc-li-post-row .khm-qc-li-post-body{flex:1;min-width:0}
				.khm-qc-li-post-row .khm-qc-li-post-text{font-size:13px;white-space:pre-wrap;word-break:break-word;margin:0 0 4px}
				.khm-qc-li-post-row .khm-qc-li-post-meta{font-size:11px;color:#9ca3af}
				.khm-qc-li-post-row .khm-qc-badge{margin-left:6px}
				.khm-qc-li-post-row button{flex-shrink:0;font-size:12px;padding:3px 10px}
				#khm-qc-li-queue-empty{font-size:13px;color:#6b7280;font-style:italic}
			</style>

			<!-- Connected banner (hidden until JS loads status) -->
			<div id="khm-qc-li-connected-banner" style="display:none">
				<span id="khm-qc-li-profile-label" style="font-size:13px;color:#065f46;font-weight:600">
					<?php esc_html_e( 'LinkedIn connected', 'khm-membership' ); ?>
				</span>
				<button class="button" id="khm-qc-li-disconnect-btn" style="font-size:12px;padding:3px 10px">
					<?php esc_html_e( 'Disconnect', 'khm-membership' ); ?>
				</button>
			</div>

			<!-- Disconnected banner -->
			<div id="khm-qc-li-disconnected-banner">
				<p style="margin:0 0 10px;font-size:14px;font-weight:600"><?php esc_html_e( 'Not connected', 'khm-membership' ); ?></p>
				<p style="margin:0 0 12px;font-size:13px;color:#6b7280">
					<?php esc_html_e( 'Authorise QuoteClub to post on your behalf. You can disconnect at any time.', 'khm-membership' ); ?>
				</p>
				<button class="button button-primary" id="khm-qc-li-connect-btn">
					<?php esc_html_e( 'Connect LinkedIn', 'khm-membership' ); ?>
				</button>
				<p id="khm-qc-li-not-configured" style="display:none;font-size:12px;color:#991b1b;margin-top:8px">
					<?php esc_html_e( 'LinkedIn integration is not yet configured. Please contact support.', 'khm-membership' ); ?>
				</p>
			</div>

			<!-- Schedule form (shown when connected) -->
			<div id="khm-qc-li-schedule-form" style="display:none">
				<h3 style="margin-top:0"><?php esc_html_e( 'Schedule a post', 'khm-membership' ); ?></h3>
				<label for="khm-qc-li-text"><?php esc_html_e( 'Post text', 'khm-membership' ); ?></label>
				<textarea id="khm-qc-li-text" maxlength="3000" placeholder="<?php esc_attr_e( 'Write your LinkedIn post here (max 3000 characters)…', 'khm-membership' ); ?>"></textarea>
				<div id="khm-qc-li-char-count">0 / 3000</div>

				<label for="khm-qc-li-url"><?php esc_html_e( 'Link URL (optional)', 'khm-membership' ); ?></label>
				<input type="url" id="khm-qc-li-url" placeholder="https://example.com/article">

				<label for="khm-qc-li-when"><?php esc_html_e( 'Schedule time', 'khm-membership' ); ?></label>
				<input type="datetime-local" id="khm-qc-li-when">

				<div style="margin-top:14px;display:flex;gap:8px;align-items:center">
					<button class="button button-primary" id="khm-qc-li-schedule-btn"><?php esc_html_e( 'Schedule post', 'khm-membership' ); ?></button>
					<span id="khm-qc-li-schedule-msg" style="font-size:13px"></span>
				</div>
			</div>

			<!-- Queue -->
			<div id="khm-qc-li-queue-panel" style="display:none">
				<h3><?php esc_html_e( 'Scheduled posts', 'khm-membership' ); ?></h3>
				<div id="khm-qc-li-queue-list">
					<p id="khm-qc-li-queue-empty"><?php esc_html_e( 'No scheduled posts yet.', 'khm-membership' ); ?></p>
				</div>
			</div>
		</div>

		<script>
		(function () {
			'use strict';
			var REST  = '<?php echo esc_js( $rest_root ); ?>';
			var NONCE = '<?php echo esc_js( $nonce ); ?>';

			var connBanner   = document.getElementById('khm-qc-li-connected-banner');
			var discBanner   = document.getElementById('khm-qc-li-disconnected-banner');
			var profileLabel = document.getElementById('khm-qc-li-profile-label');
			var schedForm    = document.getElementById('khm-qc-li-schedule-form');
			var queuePanel   = document.getElementById('khm-qc-li-queue-panel');
			var queueList    = document.getElementById('khm-qc-li-queue-list');
			var connectBtn   = document.getElementById('khm-qc-li-connect-btn');
			var disconnBtn   = document.getElementById('khm-qc-li-disconnect-btn');
			var schedBtn     = document.getElementById('khm-qc-li-schedule-btn');
			var schedMsg     = document.getElementById('khm-qc-li-schedule-msg');
			var textArea     = document.getElementById('khm-qc-li-text');
			var charCount    = document.getElementById('khm-qc-li-char-count');
			var notConf      = document.getElementById('khm-qc-li-not-configured');

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
				var cls     = 'khm-qc-badge khm-qc-badge-' + (p.status === 'published' ? 'approved' : p.status === 'failed' ? 'rejected' : p.status === 'cancelled' ? 'paused' : 'pending');
				var cancel  = p.status === 'queued' ? '<button class="button khm-qc-li-cancel" data-id="' + p.id + '">Cancel</button>' : '';
				var err     = p.error ? '<div style="font-size:11px;color:#991b1b;margin-top:4px">Error: ' + p.error + '</div>' : '';
				var link    = p.url ? '<a href="' + p.url + '" target="_blank" rel="noopener" style="font-size:11px">' + p.url + '</a>' : '';
				return '<div class="khm-qc-li-post-row" id="li-post-' + p.id + '">' +
					'<div class="khm-qc-li-post-body">' +
						'<p class="khm-qc-li-post-text">' + p.text + '</p>' +
						(link ? link + '<br>' : '') +
						'<span class="khm-qc-li-post-meta">Scheduled: ' + fmtDate(p.scheduled_at) + '</span>' +
						'<span class="' + cls + '">' + status + '</span>' +
						err +
					'</div>' +
					cancel +
					'</div>';
			}

			function loadQueue() {
				api('/social/linkedin/queue').then(function (data) {
					if (!data.success || !data.posts.length) {
						queueList.innerHTML = '<p id="khm-qc-li-queue-empty" style="font-size:13px;color:#6b7280;font-style:italic">No scheduled posts yet.</p>';
						return;
					}
					queueList.innerHTML = data.posts.map(renderPostRow).join('');
					queueList.querySelectorAll('.khm-qc-li-cancel').forEach(function (btn) {
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
					url:  document.getElementById('khm-qc-li-url').value.trim(),
					scheduled_at: document.getElementById('khm-qc-li-when').value
				};
				api('/social/linkedin/schedule', 'POST', payload).then(function (data) {
					schedBtn.disabled = false;
					if (data.success) {
						schedMsg.style.color = '#065f46';
						schedMsg.textContent = 'Post scheduled for ' + fmtDate(data.post.scheduled_at);
						textArea.value = '';
						document.getElementById('khm-qc-li-url').value = '';
						document.getElementById('khm-qc-li-when').value = '';
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
	// Access gate messages
	// -------------------------------------------------------------------------

	private function render_login_required(): string {
		$login_url = wp_login_url( get_permalink() );
		ob_start();
		?>
		<div class="khm-qc-access-gate">
			<h2><?php esc_html_e( 'Quote Club', 'khm-membership' ); ?></h2>
			<p><?php esc_html_e( 'Please log in to access the Quote Club partner portal.', 'khm-membership' ); ?></p>
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

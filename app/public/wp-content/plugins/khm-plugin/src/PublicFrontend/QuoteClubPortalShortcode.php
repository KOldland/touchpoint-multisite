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

		$user_id = get_current_user_id();
		$sponsor = SponsorService::get_user_sponsor( $user_id );

		wp_localize_script( 'khm-quote-club', 'khmQuoteClub', [
			'restUrl'       => esc_url_raw( rest_url( 'khm/v1/portal/quoteclub/' ) ),
			'sponsorRestUrl'=> esc_url_raw( rest_url( 'khm/v1/sponsor/' ) ),
			'bundleRestUrl' => esc_url_raw( rest_url( 'khm/v1/portal/quoteclub/bundles' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'userId'        => $user_id,
			'sponsorId'     => isset( $sponsor['id'] ) ? (int) $sponsor['id'] : 0,
			'editorialCredits' => $this->credits->getEditorialCredits( $user_id ),
			'pressReleaseCredits' => $this->credits->getPressReleaseCredits( $user_id ),
			'inviteToken'   => sanitize_text_field( (string) ( $_GET['khm_sponsor_invite'] ?? '' ) ),
			'inviteEmail'   => sanitize_email( (string) ( $_GET['khm_sponsor_invite_email'] ?? '' ) ),
			'wordsPerCredit'=> 120,
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
				<a href="<?php echo esc_url( add_query_arg( 'qc_section', 'commentary' ) ); ?>" class="khm-qc-btn khm-qc-btn-secondary">
					<?php esc_html_e( 'Search Articles & Submit Commentary →', 'khm-membership' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private function render_commentary_section( int $user_id, ?array $sponsor ): void {
		?>
		<div class="khm-qc-section khm-qc-commentary">
			<!-- Invite / accept status banner -->
			<div class="khm-quoteclub-invite-status" role="status" aria-live="polite"></div>

			<h2><?php esc_html_e( 'Search Articles &amp; Submit Commentary', 'khm-membership' ); ?></h2>

			<div class="khm-quoteclub-toolbar">
				<input type="date" class="khm-filter-date-from" aria-label="<?php esc_attr_e( 'From date', 'khm-membership' ); ?>">
				<input type="date" class="khm-filter-date-to" aria-label="<?php esc_attr_e( 'To date', 'khm-membership' ); ?>">
				<input type="text" class="khm-filter-topics" placeholder="<?php esc_attr_e( 'Topics (comma-separated)', 'khm-membership' ); ?>">
				<input type="text" class="khm-filter-portfolio" placeholder="<?php esc_attr_e( 'Portfolio (comma-separated)', 'khm-membership' ); ?>">
				<input type="text" class="khm-filter-keywords" placeholder="<?php esc_attr_e( 'Keywords', 'khm-membership' ); ?>">
				<select class="khm-filter-operator" aria-label="<?php esc_attr_e( 'Keyword operator', 'khm-membership' ); ?>">
					<option value="AND"><?php esc_html_e( 'AND', 'khm-membership' ); ?></option>
					<option value="OR"><?php esc_html_e( 'OR', 'khm-membership' ); ?></option>
				</select>
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
		?>
		<div class="khm-qc-section khm-qc-press-releases">
			<h2><?php esc_html_e( 'Press Releases', 'khm-membership' ); ?></h2>
			<p class="khm-qc-section-intro"><?php esc_html_e( 'Draft, revise, and submit press releases for editorial approval.', 'khm-membership' ); ?></p>

			<div class="khm-qc-pr-toolbar">
				<div class="khm-qc-pr-filters" role="tablist" aria-label="<?php esc_attr_e( 'Press release filters', 'khm-membership' ); ?>">
					<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-filter is-active" data-status="all"><?php esc_html_e( 'All', 'khm-membership' ); ?></button>
					<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-filter" data-status="draft"><?php esc_html_e( 'Drafts', 'khm-membership' ); ?></button>
					<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-filter" data-status="submitted"><?php esc_html_e( 'Submitted', 'khm-membership' ); ?></button>
					<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-filter" data-status="published"><?php esc_html_e( 'Published', 'khm-membership' ); ?></button>
					<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-filter" data-status="rejected"><?php esc_html_e( 'Rejected', 'khm-membership' ); ?></button>
				</div>
				<button type="button" class="khm-qc-btn khm-qc-btn-primary" id="khm-qc-pr-create-btn"><?php esc_html_e( 'Create Press Release', 'khm-membership' ); ?></button>
			</div>

			<div class="khm-qc-alert khm-qc-alert-error" id="khm-qc-pr-error" hidden></div>
			<div id="khm-qc-pr-list" class="khm-qc-pr-list" aria-live="polite"></div>

			<div id="khm-qc-pr-form-modal" class="khm-qc-modal" hidden>
				<div class="khm-qc-modal-content">
					<div class="khm-qc-modal-header">
						<h3 id="khm-qc-pr-form-title"><?php esc_html_e( 'Create Press Release', 'khm-membership' ); ?></h3>
						<button type="button" class="khm-qc-modal-close" id="khm-qc-pr-form-close" aria-label="<?php esc_attr_e( 'Close', 'khm-membership' ); ?>">&times;</button>
					</div>
					<div class="khm-qc-modal-body">
						<form id="khm-qc-pr-form">
							<div class="khm-qc-form-group">
								<label for="khm-qc-pr-title"><?php esc_html_e( 'Title', 'khm-membership' ); ?></label>
								<input type="text" id="khm-qc-pr-title" name="title" class="khm-qc-input" maxlength="255" required>
							</div>
							<div class="khm-qc-form-group">
								<label for="khm-qc-pr-content"><?php esc_html_e( 'Content', 'khm-membership' ); ?></label>
								<textarea id="khm-qc-pr-content" name="content" class="khm-qc-textarea" rows="10" required></textarea>
								<small><?php esc_html_e( 'Your draft stays editable until you submit it for review.', 'khm-membership' ); ?></small>
							</div>
						</form>
					</div>
					<div class="khm-qc-modal-footer">
						<button type="button" class="khm-qc-btn khm-qc-btn-secondary" id="khm-qc-pr-form-cancel"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
						<button type="button" class="khm-qc-btn khm-qc-btn-primary" id="khm-qc-pr-save-btn"><?php esc_html_e( 'Save Draft', 'khm-membership' ); ?></button>
						<button type="button" class="khm-qc-btn khm-qc-btn-success" id="khm-qc-pr-submit-btn" hidden><?php esc_html_e( 'Save and Submit', 'khm-membership' ); ?></button>
					</div>
				</div>
			</div>

			<div id="khm-qc-pr-confirm-modal" class="khm-qc-modal" hidden>
				<div class="khm-qc-modal-content khm-qc-modal-content-narrow">
					<div class="khm-qc-modal-header">
						<h3><?php esc_html_e( 'Confirm Submission', 'khm-membership' ); ?></h3>
						<button type="button" class="khm-qc-modal-close" id="khm-qc-pr-confirm-close" aria-label="<?php esc_attr_e( 'Close', 'khm-membership' ); ?>">&times;</button>
					</div>
					<div class="khm-qc-modal-body">
						<div id="khm-qc-pr-confirm-preview" class="khm-qc-pr-confirm-preview"></div>
						<div class="khm-qc-alert khm-qc-alert-info">
							<strong><?php esc_html_e( 'Cost:', 'khm-membership' ); ?></strong>
							<?php esc_html_e( '1 press release credit', 'khm-membership' ); ?>
						</div>
					</div>
					<div class="khm-qc-modal-footer">
						<button type="button" class="khm-qc-btn khm-qc-btn-secondary" id="khm-qc-pr-confirm-cancel"><?php esc_html_e( 'Back', 'khm-membership' ); ?></button>
						<button type="button" class="khm-qc-btn khm-qc-btn-success" id="khm-qc-pr-confirm-submit"><?php esc_html_e( 'Confirm and Submit', 'khm-membership' ); ?></button>
					</div>
				</div>
			</div>

			<script>
			(function($) {
				var baseUrl = khmQuoteClub.restUrl + 'press-releases';
				var nonce = khmQuoteClub.nonce;
				var list = [];
				var currentStatus = 'all';
				var currentId = null;

				function request(path, method, data) {
					return $.ajax({
						url: baseUrl + (path ? '/' + path : ''),
						method: method,
						contentType: method === 'GET' ? undefined : 'application/json',
						data: method === 'GET' ? data : JSON.stringify(data || {}),
						headers: { 'X-WP-Nonce': nonce },
						dataType: 'json'
					});
				}

				function showToast(message) {
					var $toast = $('#khm-qc-toast');
					if (!$toast.length) {
						return;
					}
					$toast.text(message).addClass('is-visible');
					window.clearTimeout($toast.data('timeoutId'));
					$toast.data('timeoutId', window.setTimeout(function() {
						$toast.removeClass('is-visible');
					}, 2400));
				}

				function showError(message) {
					$('#khm-qc-pr-error').text(message).prop('hidden', false);
				}

				function clearError() {
					$('#khm-qc-pr-error').prop('hidden', true).text('');
				}

				function statusLabel(status) {
					return (status || '').charAt(0).toUpperCase() + (status || '').slice(1);
				}

				function renderList() {
					var items = list.filter(function(item) {
						return currentStatus === 'all' ? true : item.status === currentStatus;
					});

					if (!items.length) {
						$('#khm-qc-pr-list').html('<p class="khm-qc-empty"><?php echo esc_js( __( 'No press releases in this view yet.', 'khm-membership' ) ); ?></p>');
						return;
					}

					var html = '<div class="khm-qc-pr-items">';
					items.forEach(function(item) {
						var excerpt = item.excerpt || '';
						var submissionDate = item.submission_date ? item.submission_date.substring(0, 10) : '';
						var publishedDate = item.published_date ? item.published_date.substring(0, 10) : '';
						html += '<article class="khm-qc-pr-item">';
						html += '<div class="khm-qc-pr-item-header"><div><h3>' + $('<div/>').text(item.title || '<?php echo esc_js( __( '(Untitled)', 'khm-membership' ) ); ?>').html() + '</h3>';
						html += '<p class="khm-qc-pr-item-meta">';
						html += '<?php echo esc_js( __( 'Created', 'khm-membership' ) ); ?>: ' + (item.created_at ? item.created_at.substring(0, 10) : '-');
						if (submissionDate) {
							html += ' | <?php echo esc_js( __( 'Submitted', 'khm-membership' ) ); ?>: ' + submissionDate;
						}
						if (publishedDate) {
							html += ' | <?php echo esc_js( __( 'Published', 'khm-membership' ) ); ?>: ' + publishedDate;
						}
						html += '</p></div>';
						html += '<span class="khm-qc-badge khm-qc-badge-' + item.status + '">' + statusLabel(item.status) + '</span></div>';
						html += '<p class="khm-qc-pr-item-excerpt">' + $('<div/>').text(excerpt).html() + '</p>';
						if (item.rejection_reason) {
							html += '<p class="khm-qc-pr-rejection"><?php echo esc_js( __( 'Rejection reason:', 'khm-membership' ) ); ?> ' + $('<div/>').text(item.rejection_reason).html() + '</p>';
						}
						html += '<div class="khm-qc-pr-item-actions">';
						if (item.status === 'draft') {
							html += '<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-edit" data-id="' + item.id + '"><?php echo esc_js( __( 'Edit', 'khm-membership' ) ); ?></button>';
							html += '<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-pr-delete" data-id="' + item.id + '"><?php echo esc_js( __( 'Delete', 'khm-membership' ) ); ?></button>';
							html += '<button type="button" class="khm-qc-btn khm-qc-btn-success khm-qc-pr-open-submit" data-id="' + item.id + '"><?php echo esc_js( __( 'Submit', 'khm-membership' ) ); ?></button>';
						}
						html += '</div></article>';
					});
					html += '</div>';
					$('#khm-qc-pr-list').html(html);
				}

				function loadList() {
					clearError();
					request('', 'GET', currentStatus === 'all' ? {} : { status: currentStatus })
						.done(function(response) {
							list = response.items || [];
							renderList();
						})
						.fail(function(xhr) {
							showError(((xhr.responseJSON && xhr.responseJSON.error) || '<?php echo esc_js( __( 'Unable to load press releases.', 'khm-membership' ) ); ?>'));
						});
				}

				function openModal(selector) {
					$(selector).prop('hidden', false);
				}

				function closeModal(selector) {
					$(selector).prop('hidden', true);
				}

				function resetForm() {
					currentId = null;
					$('#khm-qc-pr-form')[0].reset();
					$('#khm-qc-pr-form-title').text('<?php echo esc_js( __( 'Create Press Release', 'khm-membership' ) ); ?>');
					$('#khm-qc-pr-save-btn').text('<?php echo esc_js( __( 'Save Draft', 'khm-membership' ) ); ?>').prop('disabled', false);
					$('#khm-qc-pr-submit-btn').prop('hidden', true).prop('disabled', false).text('<?php echo esc_js( __( 'Save and Submit', 'khm-membership' ) ); ?>');
				}

				function openDraft(id) {
					clearError();
					request(String(id), 'GET', {})
						.done(function(response) {
							var item = response.press_release || {};
							currentId = item.id;
							$('#khm-qc-pr-title').val(item.title || '');
							$('#khm-qc-pr-content').val(item.content || '');
							$('#khm-qc-pr-form-title').text('<?php echo esc_js( __( 'Edit Press Release', 'khm-membership' ) ); ?>');
							$('#khm-qc-pr-save-btn').text('<?php echo esc_js( __( 'Save Changes', 'khm-membership' ) ); ?>').prop('disabled', false);
							$('#khm-qc-pr-submit-btn').prop('hidden', false);
							openModal('#khm-qc-pr-form-modal');
						})
						.fail(function(xhr) {
							showError(((xhr.responseJSON && xhr.responseJSON.error) || '<?php echo esc_js( __( 'Unable to load the selected draft.', 'khm-membership' ) ); ?>'));
						});
				}

				function saveDraft(afterSave) {
					var payload = {
						title: $.trim($('#khm-qc-pr-title').val()),
						content: $.trim($('#khm-qc-pr-content').val())
					};

					if (!payload.title || !payload.content) {
						showError('<?php echo esc_js( __( 'Please provide both a title and content.', 'khm-membership' ) ); ?>');
						return;
					}

					clearError();
					$('#khm-qc-pr-save-btn').prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'khm-membership' ) ); ?>');
					$('#khm-qc-pr-submit-btn').prop('disabled', true);

					var path = currentId ? String(currentId) + '/draft' : 'draft';
					var method = currentId ? 'PUT' : 'POST';

					request(path, method, payload)
						.done(function(response) {
							if (!currentId && response.draft_id) {
								currentId = response.draft_id;
								$('#khm-qc-pr-submit-btn').prop('hidden', false);
							}
							$('#khm-qc-pr-save-btn').prop('disabled', false).text(currentId ? '<?php echo esc_js( __( 'Save Changes', 'khm-membership' ) ); ?>' : '<?php echo esc_js( __( 'Save Draft', 'khm-membership' ) ); ?>');
							$('#khm-qc-pr-submit-btn').prop('disabled', false);
							if (typeof afterSave === 'function') {
								afterSave();
								return;
							}
							closeModal('#khm-qc-pr-form-modal');
							showToast('<?php echo esc_js( __( 'Draft saved.', 'khm-membership' ) ); ?>');
							loadList();
						})
						.fail(function(xhr) {
							showError(((xhr.responseJSON && xhr.responseJSON.error) || '<?php echo esc_js( __( 'Unable to save your draft.', 'khm-membership' ) ); ?>'));
							$('#khm-qc-pr-save-btn').prop('disabled', false).text(currentId ? '<?php echo esc_js( __( 'Save Changes', 'khm-membership' ) ); ?>' : '<?php echo esc_js( __( 'Save Draft', 'khm-membership' ) ); ?>');
							$('#khm-qc-pr-submit-btn').prop('disabled', false);
						});
				}

				function openConfirm() {
					$('#khm-qc-pr-confirm-preview').html(
						'<h4>' + $('<div/>').text($('#khm-qc-pr-title').val()).html() + '</h4>' +
						'<p>' + $('<div/>').text($('#khm-qc-pr-content').val().slice(0, 280)).html() + '</p>'
					);
					closeModal('#khm-qc-pr-form-modal');
					openModal('#khm-qc-pr-confirm-modal');
				}

				function submitCurrentDraft() {
					if (!currentId) {
						return;
					}

					$('#khm-qc-pr-confirm-submit').prop('disabled', true).text('<?php echo esc_js( __( 'Submitting...', 'khm-membership' ) ); ?>');
					request(String(currentId) + '/submit', 'POST', {})
						.done(function(response) {
							if (typeof response.credits_remaining !== 'undefined') {
								$('#qc-pr-balance').text(response.credits_remaining);
							}
							closeModal('#khm-qc-pr-confirm-modal');
							resetForm();
							showToast('<?php echo esc_js( __( 'Press release submitted for review.', 'khm-membership' ) ); ?>');
							loadList();
						})
						.fail(function(xhr) {
							closeModal('#khm-qc-pr-confirm-modal');
							openModal('#khm-qc-pr-form-modal');
							showError(((xhr.responseJSON && xhr.responseJSON.error) || '<?php echo esc_js( __( 'Submission failed.', 'khm-membership' ) ); ?>'));
						})
						.always(function() {
							$('#khm-qc-pr-confirm-submit').prop('disabled', false).text('<?php echo esc_js( __( 'Confirm and Submit', 'khm-membership' ) ); ?>');
						});
				}

				$(document).on('click', '.khm-qc-pr-filter', function() {
					$('.khm-qc-pr-filter').removeClass('is-active');
					$(this).addClass('is-active');
					currentStatus = $(this).data('status');
					loadList();
				});

				$('#khm-qc-pr-create-btn').on('click', function() {
					resetForm();
					clearError();
					openModal('#khm-qc-pr-form-modal');
				});

				$('#khm-qc-pr-save-btn').on('click', function() {
					saveDraft();
				});

				$('#khm-qc-pr-submit-btn').on('click', function() {
					saveDraft(openConfirm);
				});

				$('#khm-qc-pr-confirm-submit').on('click', function() {
					submitCurrentDraft();
				});

				$(document).on('click', '.khm-qc-pr-edit', function() {
					openDraft($(this).data('id'));
				});

				$(document).on('click', '.khm-qc-pr-open-submit', function() {
					openDraft($(this).data('id'));
				});

				$(document).on('click', '.khm-qc-pr-delete', function() {
					var id = $(this).data('id');
					if (!window.confirm('<?php echo esc_js( __( 'Delete this draft?', 'khm-membership' ) ); ?>')) {
						return;
					}
					request(String(id) + '/draft', 'DELETE', {})
						.done(function() {
							showToast('<?php echo esc_js( __( 'Draft deleted.', 'khm-membership' ) ); ?>');
							loadList();
						})
						.fail(function(xhr) {
							showError(((xhr.responseJSON && xhr.responseJSON.error) || '<?php echo esc_js( __( 'Unable to delete the draft.', 'khm-membership' ) ); ?>'));
						});
				});

				$('#khm-qc-pr-form-close, #khm-qc-pr-form-cancel').on('click', function() {
					closeModal('#khm-qc-pr-form-modal');
				});

				$('#khm-qc-pr-confirm-close, #khm-qc-pr-confirm-cancel').on('click', function() {
					closeModal('#khm-qc-pr-confirm-modal');
					openModal('#khm-qc-pr-form-modal');
				});

				loadList();
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

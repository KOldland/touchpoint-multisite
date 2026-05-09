<?php
/**
 * Connect Subscription Endpoint
 *
 * Handles per-site and portfolio subscription management:
 *   GET  /khm/v1/connect/subscription/sites    — list all sites with status + upgrade credit
 *   POST /khm/v1/connect/subscription/cart     — purchase one or more sites (Stripe or QB invoice)
 *   POST /khm/v1/connect/subscription/upgrade  — upgrade per-site subs to portfolio
 *   POST /khm/v1/connect/subscription/cancel   — cancel a per-site subscription
 *
 * Per-site subscription user meta key: `khm_connect_site_subscriptions`
 * Shape: { [site_slug]: { status, activated_at, expires_at, stripe_subscription_id, qbo_invoice_id, cancelled_at } }
 *
 * Portfolio subscription stays in existing `khm_connect_subscription` meta (scope: portfolio).
 *
 * Annual prices (pence) are stored in option `khm_connect_subscription_annual_prices`:
 *   { "site": 35000, "portfolio": 250000 }
 *
 * @package KHM\Connect
 */

namespace KHM\Connect;

use KHM\Services\SponsorService;
use KHM\QuickBooks\QBOService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class ConnectSubscriptionEndpoint {

	/** All 10 network sites in canonical order. */
	const ALL_SITES = [
		'pricing'                => 'Revenue Operations',
		'aftermarket'            => 'Aftermarket Operations',
		'field-service'          => 'Field Service Management',
		'spare-parts'            => 'Spare Parts & Logistics',
		'ecommerce'              => 'Industrial eCommerce',
		'industrial'             => 'Industrial Operations',
		'aerospace'              => 'Aerospace Engineering',
		'utilities-ops'          => 'Utilities Operations',
		'built-env'              => 'Infrastructure Operations',
		'manufacturing-flagship' => 'Modern Manufacturing',
	];

	/** Blog path slugs for each site slug (for connect_providers lookup). */
	const SITE_BLOG_SLUGS = [
		'pricing'                => [ 'pricing' ],
		'aftermarket'            => [ 'aftermarket' ],
		'field-service'          => [ 'field-service' ],
		'spare-parts'            => [ 'spare-parts' ],
		'ecommerce'              => [ 'ecommerce' ],
		'industrial'             => [ 'industrial' ],
		'aerospace'              => [ 'aerospace' ],
		'utilities-ops'          => [ 'energy', 'utilities' ],
		'built-env'              => [ 'built-env' ],
		'manufacturing-flagship' => [ 'flagship', 'manufacturing' ],
	];

	/** Logo image filenames for each site (in assets/images/sites/). */
	const SITE_LOGOS = [
		'pricing'                => 'revenue_operations.png',
		'aftermarket'            => 'aftermarket-operations.png',
		'field-service'          => 'field-service-management-logo.png',
		'spare-parts'            => 'spare-parts-and-logistics2.png',
		'ecommerce'              => 'Industrial-eCommerce.png',
		'industrial'             => 'industrial-operations.png',
		'aerospace'              => 'aerospace-engineering-logo.png',
		'utilities-ops'          => 'utilities-operations.png',
		'built-env'              => 'infrastructure_operations.png',
		'manufacturing-flagship' => 'modern-manufacturing-logo.png',
	];

	const META_KEY = 'khm_connect_site_subscriptions';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'khm/v1', '/connect/subscription/sites', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_sites' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( 'khm/v1', '/connect/subscription/cart', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'process_cart' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( 'khm/v1', '/connect/subscription/upgrade', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'process_upgrade' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( 'khm/v1', '/connect/subscription/cancel', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'cancel_site' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );
	}

	public function check_permission(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return SponsorService::get_user_sponsor( get_current_user_id() ) !== null;
	}

	// ─── GET /sites ───────────────────────────────────────────────────────────

	public function get_sites( WP_REST_Request $request ): WP_REST_Response {
		$user_id    = get_current_user_id();
		$sponsor    = SponsorService::get_user_sponsor( $user_id );
		$sponsor_id = (int) ( $sponsor['id'] ?? 0 );

		$site_subs  = $this->get_site_subs( $user_id );
		$portfolio  = get_user_meta( $user_id, 'khm_connect_subscription', true );
		$portfolio  = is_array( $portfolio ) ? $portfolio : [];
		$prices     = $this->get_prices();

		// Connected slugs via connect_providers (activated by admin / Stripe).
		$provider_connected = $this->get_provider_connected_slugs( $sponsor_id );

		$sites_out = [];
		foreach ( self::ALL_SITES as $slug => $label ) {
			$sub           = $site_subs[ $slug ] ?? [];
			$status        = (string) ( $sub['status'] ?? 'inactive' );
			$expires_at    = (string) ( $sub['expires_at'] ?? '' );
			$activated_at  = (string) ( $sub['activated_at'] ?? '' );
			$cancelled_at  = (string) ( $sub['cancelled_at'] ?? '' );
			$days_remaining = $expires_at ? max( 0, (int) ceil( ( strtotime( $expires_at ) - time() ) / 86400 ) ) : 0;

			// Also treat provider-connected (admin-activated) as connected.
			$is_provider_connected = $provider_connected[ $slug ] ?? false;
			$is_active = $is_provider_connected || in_array( $status, [ 'active', 'pending_invoice' ], true );

			$sites_out[] = [
				'slug'           => $slug,
				'label'          => $label,
				'is_connected'   => $is_active,
				'status'         => $is_provider_connected && $status === 'inactive' ? 'provider_active' : $status,
				'activated_at'   => $activated_at,
				'expires_at'     => $expires_at,
				'cancelled_at'   => $cancelled_at,
				'days_remaining' => $days_remaining,
				'renews_on'      => $expires_at ? date_i18n( 'j M Y', strtotime( $expires_at ) ) : '',
			];
		}

		// Pro-rata upgrade credit: sum of remaining value of active per-site subs.
		$credit_pence  = $this->calculate_upgrade_credit( $site_subs, $prices['site'] );
		$upgrade_cost  = max( 0, $prices['portfolio'] - $credit_pence );
		$has_portfolio = ( $portfolio['scope'] ?? '' ) === 'portfolio' && ( $portfolio['status'] ?? '' ) === 'active';

		return new WP_REST_Response( [
			'success'              => true,
			'sites'                => $sites_out,
			'portfolio'            => $portfolio,
			'has_portfolio'        => $has_portfolio,
			'prices'               => $prices,
			'upgrade_credit_pence' => $credit_pence,
			'upgrade_cost_pence'   => $upgrade_cost,
			'active_site_count'    => count( array_filter( $sites_out, fn( $s ) => $s['is_connected'] && ( $s['status'] ?? '' ) !== 'pending_invoice' ) ),
		] );
	}

	// ─── POST /cart ───────────────────────────────────────────────────────────

	public function process_cart( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$sponsor = SponsorService::get_user_sponsor( $user_id );
		if ( ! $sponsor ) {
			return new WP_Error( 'no_sponsor', 'Sponsor account required.', [ 'status' => 403 ] );
		}

		$params  = $request->get_json_params() ?: [];
		$sites   = array_filter( (array) ( $params['sites'] ?? [] ), fn( $s ) => isset( self::ALL_SITES[ $s ] ) );
		$sites   = array_values( array_map( 'sanitize_key', $sites ) );
		$payment = sanitize_key( (string) ( $params['payment'] ?? 'invoice' ) );

		if ( empty( $sites ) ) {
			return new WP_Error( 'no_sites', 'No valid sites selected.', [ 'status' => 400 ] );
		}
		if ( ! in_array( $payment, [ 'stripe', 'invoice' ], true ) ) {
			$payment = 'invoice';
		}

		$prices      = $this->get_prices();
		$is_portfolio = count( $sites ) === count( self::ALL_SITES );
		$total_pence  = $is_portfolio ? $prices['portfolio'] : count( $sites ) * $prices['site'];
		$scope        = $is_portfolio ? 'portfolio' : 'site';

		if ( 'stripe' === $payment ) {
			return $this->handle_cart_stripe( $user_id, $sponsor, $sites, $scope, $total_pence, $is_portfolio );
		}

		return $this->handle_cart_invoice( $user_id, $sponsor, $sites, $scope, $total_pence, $is_portfolio );
	}

	// ─── POST /upgrade ────────────────────────────────────────────────────────

	public function process_upgrade( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$sponsor = SponsorService::get_user_sponsor( $user_id );
		if ( ! $sponsor ) {
			return new WP_Error( 'no_sponsor', 'Sponsor account required.', [ 'status' => 403 ] );
		}

		$params  = $request->get_json_params() ?: [];
		$payment = sanitize_key( (string) ( $params['payment'] ?? 'invoice' ) );
		if ( ! in_array( $payment, [ 'stripe', 'invoice' ], true ) ) {
			$payment = 'invoice';
		}

		$prices       = $this->get_prices();
		$site_subs    = $this->get_site_subs( $user_id );
		$credit_pence = $this->calculate_upgrade_credit( $site_subs, $prices['site'] );
		$upgrade_cost = max( 0, $prices['portfolio'] - $credit_pence );

		if ( $upgrade_cost <= 0 ) {
			// Credit covers full portfolio price — activate directly.
			$this->activate_portfolio_subscription( $user_id, array_keys( self::ALL_SITES ), '', '' );
			return new WP_REST_Response( [
				'success'  => true,
				'status'   => 'active',
				'message'  => __( 'Portfolio subscription activated — existing credits covered the full cost.', 'khm-membership' ),
			] );
		}

		if ( 'stripe' === $payment ) {
			return $this->handle_upgrade_stripe( $user_id, $sponsor, $upgrade_cost, $credit_pence );
		}

		return $this->handle_upgrade_invoice( $user_id, $sponsor, $upgrade_cost, $credit_pence );
	}

	// ─── POST /cancel ─────────────────────────────────────────────────────────

	public function cancel_site( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id   = get_current_user_id();
		$params    = $request->get_json_params() ?: [];
		$site_slug = sanitize_key( (string) ( $params['site_slug'] ?? '' ) );

		if ( ! isset( self::ALL_SITES[ $site_slug ] ) ) {
			return new WP_Error( 'invalid_site', 'Invalid site slug.', [ 'status' => 400 ] );
		}

		$site_subs = $this->get_site_subs( $user_id );
		$sub       = $site_subs[ $site_slug ] ?? [];

		// Handle provider_active (admin-provisioned) cancellation.
		if ( ( $sub['status'] ?? 'inactive' ) === 'inactive' || ( $sub['status'] ?? '' ) === 'provider_active' ) {
			// Check if actually provider-connected.
			$provider_connected = $this->get_provider_connected_slugs( $user_id );
			if ( ! isset( $provider_connected[ $site_slug ] ) ) {
				return new WP_Error( 'not_active', 'No active subscription for this site.', [ 'status' => 400 ] );
			}

			// Soft-cancel provider rows in connect_providers.
			$this->cancel_provider_access( $user_id, self::SITE_BLOG_SLUGS[ $site_slug ] ?? [] );

			// Record in subscription meta so the site card updates.
			$site_subs[ $site_slug ] = array_merge( $sub, [
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql', true ),
				'expires_at'   => '',
			] );
			update_user_meta( $user_id, self::META_KEY, $site_subs );

			return new WP_REST_Response( [
				'success' => true,
				'message' => sprintf(
					__( '%s connection removed.', 'khm-membership' ),
					esc_html( self::ALL_SITES[ $site_slug ] )
				),
			] );
		}

		if ( empty( $sub ) || ! in_array( $sub['status'] ?? '', [ 'active', 'pending_invoice' ], true ) ) {
			return new WP_Error( 'not_active', 'No active subscription for this site.', [ 'status' => 400 ] );
		}

		// Cancel in Stripe so the customer is not billed again.
		$stripe_sub_id = (string) ( $sub['stripe_subscription_id'] ?? '' );
		if ( '' !== $stripe_sub_id ) {
			$stripe_secret = $this->get_stripe_secret();
			if ( $stripe_secret ) {
				try {
					\Stripe\Stripe::setApiKey( $stripe_secret );
					\Stripe\Subscription::cancel( $stripe_sub_id );
				} catch ( \Throwable $e ) {
					error_log( '[KHM Sub] Stripe subscription cancel error for ' . $site_slug . ': ' . $e->getMessage() );
				}
			}
		}

		$sub['cancelled_at'] = current_time( 'mysql', true );
		$sub['status']       = 'cancelled';
		// Access stays until expires_at — cron removes it.

		$site_subs[ $site_slug ] = $sub;
		update_user_meta( $user_id, self::META_KEY, $site_subs );

		return new WP_REST_Response( [
			'success'    => true,
			'expires_at' => $sub['expires_at'] ?? '',
			'expires_on' => $sub['expires_at'] ? date_i18n( 'j M Y', strtotime( $sub['expires_at'] ) ) : '',
			'message'    => sprintf(
				__( 'Subscription cancelled. You will retain access to %s until %s.', 'khm-membership' ),
				esc_html( self::ALL_SITES[ $site_slug ] ),
				$sub['expires_at'] ? date_i18n( 'j M Y', strtotime( $sub['expires_at'] ) ) : 'expiry'
			),
		] );
	}

	private function cancel_provider_access( int $user_id, array $blog_paths ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'connect_providers';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return;
		}
		// Resolve blog paths to blog IDs.
		foreach ( $blog_paths as $path ) {
			$blog_id = get_blog_id_from_url( home_url(), '/' . trim( $path, '/' ) . '/' );
			if ( ! $blog_id ) {
				continue;
			}
			$wpdb->update(
				$table,
				[ 'status' => 'cancelled' ],
				[ 'sponsor_id' => $user_id, 'blog_id' => (int) $blog_id, 'status' => 'active' ],
				[ '%s' ],
				[ '%d', '%d', '%s' ]
			);
		}
	}

	// ─── Stripe cart path ─────────────────────────────────────────────────────

	private function handle_cart_stripe( int $user_id, array $sponsor, array $sites, string $scope, int $total_pence, bool $is_portfolio ): WP_REST_Response|WP_Error {
		$stripe_secret = $this->get_stripe_secret();
		if ( ! $stripe_secret ) {
			return new WP_Error( 'no_stripe', 'Payment system not configured.', [ 'status' => 500 ] );
		}

		$annual_prices = get_option( 'khm_connect_subscription_annual_prices', [ 'site' => 35000, 'portfolio' => 250000 ] );
		$user_email    = get_userdata( $user_id )->user_email ?? '';
		$site_count    = count( self::ALL_SITES );

		try {
			\Stripe\Stripe::setApiKey( $stripe_secret );

			// Build one line item per site. For portfolio, split the portfolio price
			// across all sites so Stripe shows each site as an individual entry.
			if ( $is_portfolio ) {
				$line_items  = [];
				$base_amount = intdiv( (int) $annual_prices['portfolio'], $site_count );
				$remainder   = (int) $annual_prices['portfolio'] % $site_count;
				$context_note = 'Annual access across all ' . $site_count . ' sites. Each connection is valid for 12 months from activation.';

				foreach ( array_values( $sites ) as $index => $slug ) {
					$site_label   = self::ALL_SITES[ $slug ] ?? $slug;
					$unit_amount  = $base_amount + ( $index < $remainder ? 1 : 0 );
					$description  = ( 0 === $index )
						? $context_note . ' Line items below show each included site.'
						: 'Portfolio annual plan site allocation. Valid for 12 months from activation.';
					$item         = [
						'price_data' => [
							'currency'     => 'gbp',
							'unit_amount'  => $unit_amount,
							'recurring'    => [ 'interval' => 'year' ],
							'product_data' => [
								'name'        => 'Connect — ' . $site_label,
								'description' => $description,
							],
						],
						'quantity' => 1,
					];
					$logo_url = self::get_site_logo_url( $slug );
					if ( $logo_url ) {
						$item['price_data']['product_data']['images'] = [ $logo_url ];
					}
					$line_items[] = $item;
				}
				$sub_label = 'Portfolio (all ' . $site_count . ' sites)';
			} else {
				$line_items = [];
				foreach ( $sites as $slug ) {
					$site_label = self::ALL_SITES[ $slug ] ?? $slug;
					$item       = [
						'price_data' => [
							'currency'     => 'gbp',
							'unit_amount'  => (int) $annual_prices['site'],
							'recurring'    => [ 'interval' => 'year' ],
							'product_data' => [
								'name'        => 'Connect — ' . $site_label,
								'description' => 'Annual sector intelligence subscription. Valid for 12 months from activation.',
							],
						],
						'quantity' => 1,
					];
					$logo_url = self::get_site_logo_url( $slug );
					if ( $logo_url ) {
						$item['price_data']['product_data']['images'] = [ $logo_url ];
					}
					$line_items[] = $item;
				}
				$sub_label = implode( ', ', array_map( fn( $s ) => self::ALL_SITES[ $s ] ?? $s, $sites ) );
			}

			$session_metadata = [
				'khm_type'     => 'connect_subscription',
				'purchase_type'=> 'connect_subscription',
				'scope'        => $scope,
				'sites'        => implode( ',', $sites ),
				'user_id'      => $user_id,
				'total_pence'  => $total_pence,
			];

			$session = \Stripe\Checkout\Session::create( [
				'mode'                       => 'subscription',
				'customer_email'             => $user_email,
				'billing_address_collection' => 'required',
				'allow_promotion_codes'      => true,
				'line_items'                 => $line_items,
				'subscription_data'          => [
					'description' => 'Connect Subscription — ' . $sub_label,
					'metadata'    => $session_metadata,
				],
				'custom_text'  => [
					'submit' => [ 'message' => 'Each connection is valid for 12 months and renews annually. You can cancel any time from your Partner Portal.' ],
				],
				'success_url'  => add_query_arg( 'connect_cart', 'success', home_url( '/partner-portal/' ) ),
				'cancel_url'   => add_query_arg( 'connect_cart', 'cancelled', home_url( '/partner-portal/' ) ),
				'metadata'     => $session_metadata,
			] );

			// Write pending entries immediately.
			$this->write_pending_site_subs( $user_id, $sites, $scope, '', $session->id );

			return new WP_REST_Response( [
				'success'      => true,
				'payment'      => 'stripe',
				'checkout_url' => $session->url,
			] );

		} catch ( \Throwable $e ) {
			error_log( '[KHM Sub] Stripe cart error: ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', 'Payment session creation failed.', [ 'status' => 500 ] );
		}
	}

	/** Returns the public URL for a site's logo image, or empty string if not available. */
	private static function get_site_logo_url( string $slug ): string {
		if ( ! isset( self::SITE_LOGOS[ $slug ] ) ) {
			return '';
		}
		// Logos require a publicly accessible HTTPS URL — local/dev hosts cannot be fetched by Stripe.
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $home_host || 'localhost' === $home_host || str_ends_with( $home_host, '.local' ) || preg_match( '/^127\./', $home_host ) ) {
			return '';
		}

		$plugin_root = dirname( __DIR__, 2 );
		$url         = (string) plugins_url( 'assets/images/sites/' . self::SITE_LOGOS[ $slug ], $plugin_root . '/khm-plugin.php' );
		$scheme      = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( 'https' !== $scheme ) {
			return '';
		}

		return $url;
	}

	// ─── QB invoice cart path ─────────────────────────────────────────────────

	private function handle_cart_invoice( int $user_id, array $sponsor, array $sites, string $scope, int $total_pence, bool $is_portfolio ): WP_REST_Response|WP_Error {
		$user     = get_userdata( $user_id );
		$email    = $user ? $user->user_email : '';
		$name     = is_array( $sponsor ) ? ( (string) ( $sponsor['name'] ?? '' ) ) : $email;
		$label    = $is_portfolio ? 'Portfolio (all 10 sites)' : implode( ', ', array_map( fn( $s ) => self::ALL_SITES[ $s ] ?? $s, $sites ) );
		$amount   = round( $total_pence / 100, 2 );
		$desc     = 'Connect Subscription — ' . $label;

		$qbo_invoice_id  = null;
		$qbo_invoice_url = null;

		try {
			$qbo = new QBOService();
			if ( $qbo->is_connected() ) {
				$qb_customer_id = $qbo->find_or_create_customer( $email, $name );
				$invoice        = $qbo->create_invoice( $qb_customer_id, $desc, $amount, 'GBP', [
					'user_id'     => $user_id,
					'scope'       => $scope,
					'sites'       => implode( ',', $sites ),
					'source'      => 'connect_subscription_cart',
					'total_pence' => $total_pence,
				] );
				$qbo_invoice_id  = $invoice['id'];
				$qbo_invoice_url = $invoice['deep_link'];
			}
		} catch ( \Throwable $e ) {
			error_log( '[KHM Sub] QB invoice error: ' . $e->getMessage() );
		}

		$this->write_pending_site_subs( $user_id, $sites, $scope, $qbo_invoice_id ?? '', '' );

		// Notify admin.
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[Connect] Invoice request: %s', $name ),
			sprintf( "Sponsor \"%s\" has requested a Connect subscription.\n\nSites: %s\nAmount: £%.2f\n%s",
				$name, $label, $amount,
				$qbo_invoice_url ? "QB Invoice: $qbo_invoice_url" : '(QB not connected — activate manually.)'
			)
		);

		return new WP_REST_Response( [
			'success'     => true,
			'payment'     => 'invoice',
			'invoice_url' => $qbo_invoice_url,
			'message'     => $qbo_invoice_id
				? __( 'Invoice sent to your email. Your connections will activate once payment is received.', 'khm-membership' )
				: __( 'Your request has been received. We will send you an invoice within 1 business day.', 'khm-membership' ),
		] );
	}

	// ─── Stripe upgrade path ──────────────────────────────────────────────────

	private function handle_upgrade_stripe( int $user_id, array $sponsor, int $upgrade_cost, int $credit_pence ): WP_REST_Response|WP_Error {
		$stripe_secret = $this->get_stripe_secret();
		if ( ! $stripe_secret ) {
			return new WP_Error( 'no_stripe', 'Payment system not configured.', [ 'status' => 500 ] );
		}

		$user_email  = get_userdata( $user_id )->user_email ?? '';
		$credit_desc = $credit_pence > 0 ? sprintf( ' (after £%.2f credit from existing sites)', $credit_pence / 100 ) : '';

		try {
			\Stripe\Stripe::setApiKey( $stripe_secret );

			$site_slugs   = array_keys( self::ALL_SITES );
			$site_count   = count( $site_slugs );
			$base_amount  = intdiv( $upgrade_cost, $site_count );
			$remainder    = $upgrade_cost % $site_count;
			$line_items   = [];
			$context_note = 'One-time payment to upgrade from per-site to full Portfolio access across all ' . $site_count . ' sites. Each connection is valid for 12 months from activation.';
			if ( $credit_pence > 0 ) {
				$context_note .= sprintf( ' Includes £%.2f credit from existing sites.', $credit_pence / 100 );
			}

			foreach ( $site_slugs as $index => $slug ) {
				$site_label  = self::ALL_SITES[ $slug ] ?? $slug;
				$unit_amount = $base_amount + ( $index < $remainder ? 1 : 0 );
				$description = ( 0 === $index )
					? $context_note . ' Line items below show each included site.'
					: 'One-time portfolio upgrade charge allocation for this site. Valid for 12 months from activation.';
				$item        = [
					'price_data' => [
						'currency'     => 'gbp',
						'unit_amount'  => $unit_amount,
						'product_data' => [
							'name'        => 'Connect — ' . $site_label,
							'description' => $description,
						],
					],
					'quantity' => 1,
				];
				$logo_url = self::get_site_logo_url( $slug );
				if ( $logo_url ) {
					$item['price_data']['product_data']['images'] = [ $logo_url ];
				}
				$line_items[] = $item;
			}

			$session = \Stripe\Checkout\Session::create( [
				'mode'                       => 'payment',
				'customer_email'             => $user_email,
				'billing_address_collection' => 'required',
				'allow_promotion_codes'      => true,
				'invoice_creation'           => [ 'enabled' => true ],
				'line_items'                 => $line_items,
				'payment_intent_data' => [
					'statement_descriptor' => 'KHM CONNECT UPGRADE',
				],
				'custom_text'  => [
					'submit' => [ 'message' => 'Upgrades your access to all ' . count( self::ALL_SITES ) . ' sites' . $credit_desc . '. Connections are valid for 12 months from activation and renew annually.' ],
				],
				'success_url'  => add_query_arg( 'connect_upgrade', 'success', home_url( '/partner-portal/' ) ),
				'cancel_url'   => add_query_arg( 'connect_upgrade', 'cancelled', home_url( '/partner-portal/' ) ),
				'metadata'     => [
					'khm_type'     => 'connect_subscription',
					'purchase_type'=> 'connect_subscription',
					'scope'        => 'portfolio',
					'sites'        => implode( ',', array_keys( self::ALL_SITES ) ),
					'user_id'      => $user_id,
					'is_upgrade'   => '1',
					'credit_pence' => $credit_pence,
					'total_pence'  => $upgrade_cost,
				],
			] );

			return new WP_REST_Response( [
				'success'      => true,
				'payment'      => 'stripe',
				'checkout_url' => $session->url,
			] );

		} catch ( \Throwable $e ) {
			error_log( '[KHM Sub] Stripe upgrade error: ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', 'Payment session creation failed.', [ 'status' => 500 ] );
		}
	}

	// ─── QB upgrade path ──────────────────────────────────────────────────────

	private function handle_upgrade_invoice( int $user_id, array $sponsor, int $upgrade_cost, int $credit_pence ): WP_REST_Response|WP_Error {
		$user         = get_userdata( $user_id );
		$email        = $user ? $user->user_email : '';
		$name         = is_array( $sponsor ) ? ( (string) ( $sponsor['name'] ?? '' ) ) : $email;
		$amount       = round( $upgrade_cost / 100, 2 );
		$credit_human = round( $credit_pence / 100, 2 );
		$desc         = sprintf( 'Connect Portfolio Upgrade (includes £%.2f credit from existing sites)', $credit_human );

		$qbo_invoice_id  = null;
		$qbo_invoice_url = null;

		try {
			$qbo = new QBOService();
			if ( $qbo->is_connected() ) {
				$qb_customer_id = $qbo->find_or_create_customer( $email, $name );
				$invoice        = $qbo->create_invoice( $qb_customer_id, $desc, $amount, 'GBP', [
					'user_id'      => $user_id,
					'scope'        => 'portfolio',
					'sites'        => implode( ',', array_keys( self::ALL_SITES ) ),
					'source'       => 'connect_upgrade',
					'credit_pence' => $credit_pence,
					'total_pence'  => $upgrade_cost,
				] );
				$qbo_invoice_id  = $invoice['id'];
				$qbo_invoice_url = $invoice['deep_link'];
			}
		} catch ( \Throwable $e ) {
			error_log( '[KHM Sub] QB upgrade invoice error: ' . $e->getMessage() );
		}

		// Write portfolio pending entry.
		$existing_portfolio = get_user_meta( $user_id, 'khm_connect_subscription', true );
		$existing_portfolio = is_array( $existing_portfolio ) ? $existing_portfolio : [];
		$existing_portfolio = array_merge( $existing_portfolio, [
			'scope'          => 'portfolio',
			'status'         => 'pending_invoice',
			'requested_at'   => current_time( 'mysql', true ),
			'qbo_invoice_id' => $qbo_invoice_id,
		] );
		update_user_meta( $user_id, 'khm_connect_subscription', $existing_portfolio );

		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[Connect] Portfolio upgrade request: %s', $name ),
			sprintf( "Sponsor \"%s\" has requested a portfolio upgrade.\n\nUpgrade cost: £%.2f (after £%.2f credit)\n%s",
				$name, $amount, $credit_human,
				$qbo_invoice_url ? "QB Invoice: $qbo_invoice_url" : '(QB not connected — activate manually.)'
			)
		);

		return new WP_REST_Response( [
			'success'     => true,
			'payment'     => 'invoice',
			'invoice_url' => $qbo_invoice_url,
			'message'     => $qbo_invoice_id
				? __( 'Invoice sent to your email. Your portfolio will activate once payment is received.', 'khm-membership' )
				: __( 'Your upgrade request has been received. We will send an invoice within 1 business day.', 'khm-membership' ),
		] );
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/** Read per-site subscription meta (always returns array). */
	public static function get_site_subs( int $user_id ): array {
		$meta = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $meta ) ? $meta : [];
	}

	/** Write `pending` or `pending_invoice` entries for each site. */
	private function write_pending_site_subs( int $user_id, array $sites, string $scope, string $qbo_invoice_id, string $stripe_session_id ): void {
		$site_subs = self::get_site_subs( $user_id );
		$now       = current_time( 'mysql', true );
		$status    = $qbo_invoice_id ? 'pending_invoice' : 'pending';

		foreach ( $sites as $slug ) {
			if ( ! isset( self::ALL_SITES[ $slug ] ) ) {
				continue;
			}
			$existing         = $site_subs[ $slug ] ?? [];
			$site_subs[ $slug ] = array_merge( $existing, [
				'status'             => $status,
				'requested_at'       => $now,
				'qbo_invoice_id'     => $qbo_invoice_id,
				'stripe_session_id'  => $stripe_session_id,
			] );
		}

		update_user_meta( $user_id, self::META_KEY, $site_subs );
	}

	/**
	 * Activate per-site subscriptions after payment confirmed.
	 * Called from StripeWebhookHandler / QBOWebhookEndpoint.
	 */
	public static function activate_site_subscriptions( int $user_id, array $sites, string $scope, string $stripe_sub_id, string $stripe_session_id ): void {
		$site_subs = self::get_site_subs( $user_id );
		$now       = current_time( 'mysql', true );
		$expires   = gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );

		foreach ( $sites as $slug ) {
			if ( ! isset( self::ALL_SITES[ $slug ] ) ) {
				continue;
			}
			$existing          = $site_subs[ $slug ] ?? [];
			$site_subs[ $slug ] = array_merge( $existing, [
				'status'                 => 'active',
				'billing_interval'       => 'year',
				'activated_at'           => $now,
				'expires_at'             => $expires,
				'stripe_subscription_id' => $stripe_sub_id,
				'stripe_session_id'      => $stripe_session_id,
			] );
		}

		update_user_meta( $user_id, self::META_KEY, $site_subs );
		do_action( 'khm_connect_site_subscriptions_activated', $user_id, $sites, $scope );
	}

	/** Portfolio subscription activation (also writes per-site entries). */
	public static function activate_portfolio_subscription( int $user_id, array $sites, string $stripe_sub_id, string $stripe_session_id ): void {
		// Activate all per-site entries.
		self::activate_site_subscriptions( $user_id, $sites, 'portfolio', $stripe_sub_id, $stripe_session_id );

		// Update portfolio meta.
		$existing = get_user_meta( $user_id, 'khm_connect_subscription', true );
		$existing = is_array( $existing ) ? $existing : [];
		update_user_meta( $user_id, 'khm_connect_subscription', array_merge( $existing, [
			'scope'                  => 'portfolio',
			'status'                 => 'active',
			'activated_at'           => current_time( 'mysql', true ),
			'expires_at'             => gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			'stripe_subscription_id' => $stripe_sub_id,
			'stripe_session_id'      => $stripe_session_id,
		] ) );
	}

	/** Calculate pro-rata credit from active per-site subs (pence). */
	public static function calculate_upgrade_credit( array $site_subs, int $site_price_pence ): int {
		$credit = 0;
		foreach ( $site_subs as $sub ) {
			if ( ( $sub['status'] ?? '' ) !== 'active' ) {
				continue;
			}
			$expires_at = $sub['expires_at'] ?? '';
			if ( ! $expires_at ) {
				continue;
			}
			$days_remaining = max( 0, (int) ceil( ( strtotime( $expires_at ) - time() ) / 86400 ) );
			$credit        += (int) round( ( $site_price_pence / 365 ) * $days_remaining );
		}
		return $credit;
	}

	/** Get slugs that are connected via connect_providers (admin-activated). */
	private function get_provider_connected_slugs( int $sponsor_id ): array {
		if ( $sponsor_id <= 0 ) {
			return [];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'connect_providers';

		// Check table exists.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return [];
		}

		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT b.path FROM {$table} p
			 INNER JOIN {$wpdb->blogs} b ON b.blog_id = p.blog_id
			 WHERE p.sponsor_id = %d AND p.status = %s AND p.blog_id > 1",
			$sponsor_id, 'active'
		) );

		// Map blog paths back to site slugs.
		$path_to_slug = [];
		foreach ( self::SITE_BLOG_SLUGS as $slug => $blog_paths ) {
			foreach ( $blog_paths as $bp ) {
				$path_to_slug[ $bp ] = $slug;
			}
		}

		$connected = [];
		foreach ( $rows as $path ) {
			$ps = trim( (string) $path, '/' );
			if ( isset( $path_to_slug[ $ps ] ) ) {
				$connected[ $path_to_slug[ $ps ] ] = true;
			}
		}

		return $connected;
	}

	private function get_prices(): array {
		$opt = get_option( 'khm_connect_subscription_annual_prices', [] );
		return [
			'site'      => isset( $opt['site'] ) ? (int) $opt['site'] : 35000,
			'portfolio' => isset( $opt['portfolio'] ) ? (int) $opt['portfolio'] : 250000,
		];
	}

	private function get_stripe_secret(): string {
		if ( function_exists( 'khm_get_stripe_secret' ) ) {
			return (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' );
		}
		return '';
	}
}

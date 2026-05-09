<?php
/**
 * QuickBooks Online Service
 *
 * Wraps the QuickBooks v3-php-sdk to provide:
 *   - OAuth 2.0 token storage and auto-refresh
 *   - Customer find-or-create by email
 *   - Invoice creation with line items
 *   - Invoice read (for payment confirmation polling)
 *
 * Credentials are stored in WP options (never in source):
 *   khm_qbo_client_id       — Intuit app Client ID
 *   khm_qbo_client_secret   — Intuit app Client Secret
 *   khm_qbo_realm_id        — Company/Realm ID
 *   khm_qbo_environment     — 'sandbox' | 'production'
 *   khm_qbo_redirect_uri    — OAuth callback URI
 *   khm_qbo_access_token    — Stored after OAuth exchange
 *   khm_qbo_refresh_token   — Stored after OAuth exchange
 *   khm_qbo_token_expiry    — Unix timestamp
 *   khm_qbo_refresh_expiry  — Unix timestamp
 *
 * @package KHM\QuickBooks
 */

namespace KHM\QuickBooks;

use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\Facades\Customer;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\Facades\Line;
use QuickBooksOnline\API\Facades\SalesItemLineDetail;

defined( 'ABSPATH' ) || exit;

class QBOService {

	private DataService $data_service;

	public function __construct() {
		$this->data_service = $this->build_data_service();
	}

	// ─── Factory ──────────────────────────────────────────────────────────────

	private function build_data_service(): DataService {
		$config = [
			'auth_mode'       => 'oauth2',
			'ClientID'        => (string) get_option( 'khm_qbo_client_id', '' ),
			'ClientSecret'    => (string) get_option( 'khm_qbo_client_secret', '' ),
			'RedirectURI'     => (string) get_option( 'khm_qbo_redirect_uri', '' ),
			'scope'           => 'com.intuit.quickbooks.accounting',
			'baseUrl'         => 'sandbox' === get_option( 'khm_qbo_environment', 'sandbox' ) ? 'Development' : 'Production',
		];

		$access_token  = (string) get_option( 'khm_qbo_access_token', '' );
		$refresh_token = (string) get_option( 'khm_qbo_refresh_token', '' );
		$realm_id      = (string) get_option( 'khm_qbo_realm_id', '' );

		if ( $access_token && $refresh_token ) {
			$config['accessTokenKey']  = $access_token;
			$config['refreshTokenKey'] = $refresh_token;
			$config['QBORealmID']      = $realm_id;
		}

		$ds = DataService::Configure( $config );
		$ds->setLogLocation( WP_CONTENT_DIR . '/uploads/khm-qbo-logs' );

		return $ds;
	}

	// ─── Token management ─────────────────────────────────────────────────────

	/**
	 * Build the Intuit OAuth2 authorization URL to redirect the user to.
	 */
	public function get_authorization_url(): string {
		$login_helper = $this->data_service->getOAuth2LoginHelper();
		return $login_helper->getAuthorizationCodeURL();
	}

	/**
	 * Exchange the authorization code for access + refresh tokens.
	 * Stores tokens in WP options.
	 *
	 * @param string $code  The `code` param from Intuit's callback redirect.
	 * @param string $realm_id The `realmId` param from the callback.
	 * @throws \Exception
	 */
	public function exchange_code_for_tokens( string $code, string $realm_id ): void {
		$login_helper    = $this->data_service->getOAuth2LoginHelper();
		$access_token_obj = $login_helper->exchangeAuthorizationCodeForToken( $code, $realm_id );
		$this->store_tokens( $access_token_obj, $realm_id );
	}

	/**
	 * Refresh the access token using the stored refresh token.
	 * Called automatically before API requests when token is near expiry.
	 *
	 * @throws \Exception
	 */
	public function refresh_access_token(): void {
		$login_helper    = $this->data_service->getOAuth2LoginHelper();
		$access_token_obj = $login_helper->refreshToken();
		$realm_id        = (string) get_option( 'khm_qbo_realm_id', '' );
		$this->store_tokens( $access_token_obj, $realm_id );
		// Rebuild data service with new token.
		$this->data_service = $this->build_data_service();
	}

	private function store_tokens( object $token_obj, string $realm_id ): void {
		update_option( 'khm_qbo_access_token',   $token_obj->getAccessToken(), false );
		update_option( 'khm_qbo_refresh_token',  $token_obj->getRefreshToken(), false );
		update_option( 'khm_qbo_token_expiry',   time() + (int) $token_obj->getAccessTokenValidationPeriodInSeconds(), false );
		update_option( 'khm_qbo_refresh_expiry', time() + (int) $token_obj->getRefreshTokenValidationPeriodInSeconds(), false );
		update_option( 'khm_qbo_realm_id',       $realm_id, false );
	}

	/**
	 * Returns true if an access token is stored and not yet expired.
	 */
	public function is_connected(): bool {
		$token  = (string) get_option( 'khm_qbo_access_token', '' );
		$expiry = (int) get_option( 'khm_qbo_token_expiry', 0 );
		return $token !== '' && $expiry > time();
	}

	/**
	 * Ensure the token is fresh, refreshing if within 5 minutes of expiry.
	 */
	public function maybe_refresh(): void {
		$expiry = (int) get_option( 'khm_qbo_token_expiry', 0 );
		if ( $expiry > 0 && ( $expiry - time() ) < 300 ) {
			$this->refresh_access_token();
		}
	}

	// ─── Customer ─────────────────────────────────────────────────────────────

	/**
	 * Find an existing QB customer by email, or create one.
	 *
	 * @param string $email
	 * @param string $display_name  Full name / company name.
	 * @return string QB Customer entity ID.
	 * @throws \Exception
	 */
	public function find_or_create_customer( string $email, string $display_name ): string {
		$this->maybe_refresh();
		$this->data_service->updateServiceContext( $this->build_service_context() );

		// Search for existing customer by email.
		$customers = $this->data_service->Query(
			sprintf( "SELECT * FROM Customer WHERE PrimaryEmailAddr = '%s' MAXRESULTS 1", esc_sql( $email ) )
		);

		if ( ! empty( $customers ) && isset( $customers[0] ) ) {
			return (string) $customers[0]->Id;
		}

		// Create new customer.
		$customer_obj = Customer::create( [
			'DisplayName'      => sanitize_text_field( $display_name ),
			'PrimaryEmailAddr' => [ 'Address' => sanitize_email( $email ) ],
		] );

		$result = $this->data_service->Add( $customer_obj );
		$error  = $this->data_service->getLastError();
		if ( $error ) {
			throw new \RuntimeException( 'QB Customer create failed: ' . $error->getResponseBody() );
		}

		return (string) $result->Id;
	}

	// ─── Invoice ──────────────────────────────────────────────────────────────

	/**
	 * Create a QB invoice and send it to the customer.
	 *
	 * @param string $customer_id   QB Customer entity ID.
	 * @param string $description   Line item description.
	 * @param float  $amount_gbp    Amount in GBP decimal.
	 * @param string $currency_code ISO 4217 currency (default 'GBP').
	 * @param array  $metadata      Extra fields stored in PrivateNote.
	 * @return array{ id: string, doc_number: string, deep_link: string }
	 * @throws \Exception
	 */
	public function create_invoice(
		string $customer_id,
		string $description,
		float $amount_gbp,
		string $currency_code = 'GBP',
		array $metadata = []
	): array {
		$this->maybe_refresh();
		$this->data_service->updateServiceContext( $this->build_service_context() );

		$private_note = $metadata ? wp_json_encode( $metadata ) : '';

		$line = [
			'Amount'              => $amount_gbp,
			'DetailType'          => 'SalesItemLineDetail',
			'Description'         => sanitize_text_field( $description ),
			'SalesItemLineDetail' => [
				'ItemRef' => [ 'value' => '1', 'name' => 'Services' ], // Item 1 = Services in QB default chart
				'UnitPrice' => $amount_gbp,
				'Qty'       => 1,
			],
		];

		$invoice_data = [
			'CustomerRef'       => [ 'value' => $customer_id ],
			'Line'              => [ $line ],
			'CurrencyRef'       => [ 'value' => strtoupper( $currency_code ) ],
			'BillEmail'         => [ 'Address' => '' ], // QB will use customer email
			'EmailStatus'       => 'NeedToSend', // QB sends the invoice automatically
		];

		if ( $private_note ) {
			$invoice_data['PrivateNote'] = substr( $private_note, 0, 4000 );
		}

		$invoice_obj = Invoice::create( $invoice_data );
		$result      = $this->data_service->Add( $invoice_obj );
		$error       = $this->data_service->getLastError();
		if ( $error ) {
			throw new \RuntimeException( 'QB Invoice create failed: ' . $error->getResponseBody() );
		}

		$env       = get_option( 'khm_qbo_environment', 'sandbox' );
		$realm_id  = (string) get_option( 'khm_qbo_realm_id', '' );
		$base      = 'sandbox' === $env
			? "https://app.sandbox.qbo.intuit.com/app/invoice?txnId={$result->Id}"
			: "https://app.qbo.intuit.com/app/invoice?txnId={$result->Id}";

		return [
			'id'         => (string) $result->Id,
			'doc_number' => (string) ( $result->DocNumber ?? '' ),
			'deep_link'  => $base,
		];
	}

	/**
	 * Read a QB invoice by ID. Returns the raw SDK object.
	 *
	 * @param string $invoice_id
	 * @return object|null
	 */
	public function get_invoice( string $invoice_id ): ?object {
		$this->maybe_refresh();
		$this->data_service->updateServiceContext( $this->build_service_context() );

		$result = $this->data_service->FindById( 'invoice', $invoice_id );
		$error  = $this->data_service->getLastError();
		if ( $error ) {
			error_log( '[KHM QBO] get_invoice error: ' . $error->getResponseBody() );
			return null;
		}

		return $result ?: null;
	}

	// ─── Internal ─────────────────────────────────────────────────────────────

	/**
	 * Build a ServiceContext with stored tokens.
	 */
	private function build_service_context(): object {
		$access_token  = (string) get_option( 'khm_qbo_access_token', '' );
		$refresh_token = (string) get_option( 'khm_qbo_refresh_token', '' );
		$realm_id      = (string) get_option( 'khm_qbo_realm_id', '' );
		$env           = get_option( 'khm_qbo_environment', 'sandbox' );

		$oauth2_helper = new \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken(
			(string) get_option( 'khm_qbo_client_id', '' ),
			(string) get_option( 'khm_qbo_client_secret', '' ),
			$access_token,
			(string) get_option( 'khm_qbo_token_expiry', '' ),
			$refresh_token,
			(string) get_option( 'khm_qbo_refresh_expiry', '' ),
			$realm_id
		);

		$service_type = 'sandbox' === $env
			? \QuickBooksOnline\API\Core\ServiceContext::IntuitServicesType_QBO
			: \QuickBooksOnline\API\Core\ServiceContext::IntuitServicesType_QBO;

		return new \QuickBooksOnline\API\Core\ServiceContext( $realm_id, $service_type, $oauth2_helper );
	}
}

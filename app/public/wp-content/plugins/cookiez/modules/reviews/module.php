<?php

namespace Cookiez\Modules\Reviews;

use Cookiez\Classes\Module_Base;
use Cookiez\Classes\Utils;
use Cookiez\Modules\Connect\Module as Connect_Module;
use Cookiez\Modules\Scanner\Database\Scan_Entry;
use Cookiez\Modules\Settings\Classes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Module
 */
class Module extends Module_Base {
	private const MAX_POPUP_DISMISSALS = 3;
	private const HIGH_RATING_THRESHOLD = 4;
	private const PLUGIN_ROW_STYLE_HANDLE = 'cookiez-reviews-plugin-row';

	/**
	 * @var bool
	 */
	private bool $should_print_reviews_mount = false;

	/**
	 * Get module name.
	 * Retrieve the module name.
	 *
	 * @access public
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'Reviews';
	}

	public static function routes_list(): array {
		return [
			'Feedback',
		];
	}

	public function enqueue_plugin_row_styles( string $hook_suffix ): void {
		if ( 'plugins.php' !== $hook_suffix ) {
			return;
		}

		wp_register_style( self::PLUGIN_ROW_STYLE_HANDLE, false );
		wp_enqueue_style( self::PLUGIN_ROW_STYLE_HANDLE );

		$css = '
			.wp-cookiez-review__stars {
				display: inline-flex;
				flex-direction: row-reverse;
			}
			.wp-cookiez-review__stars > span {
				color: #888;
			}
			.wp-cookiez-review__stars > span:hover {
				color: #ffa400;
			}
			.wp-cookiez-review__stars > span:hover ~ span {
				color: #ffa400;
			}
		';

		wp_add_inline_style( self::PLUGIN_ROW_STYLE_HANDLE, $css );
	}

	/**
	 * Enqueue Scripts and Styles
	 */
	public function enqueue_scripts(): void {
		$this->should_print_reviews_mount = false;

		if ( ! Utils::is_plugin_page() ) {
			return;
		}

		if ( ! Connect_Module::is_connected() ) {
			return;
		}

		if ( ! $this->maybe_show_review_popup() ) {
			return;
		}

		$this->should_print_reviews_mount = true;

		Utils\Assets::enqueue_app_assets( 'reviews', false );

		wp_localize_script(
			'reviews',
			'cookiezReviewData',
			[
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
				'reviewData' => $this->get_review_data(),
				'isRTL' => is_rtl(),
				'isDevelopment' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			]
		);
	}

	/**
	 * Print reviews app mount node in admin footer (after enqueued scripts).
	 */
	public function print_reviews_mount(): void {
		if ( ! $this->should_print_reviews_mount ) {
			return;
		}

		echo '<div id="reviews-app"></div>';
	}

	public function register_base_data(): void {

		if ( get_option( Settings::REVIEW_DATA ) ) {
			return;
		}

		$data = [
			'dismissals' => 0,
			'hide_for_days' => 0,
			'last_dismiss' => null,
			'rating' => null,
			'feedback' => null,
			'added_on' => gmdate( 'Y-m-d H:i:s' ),
			'submitted' => false,
			'repo_review_clicked' => false,
		];

		update_option( Settings::REVIEW_DATA, $data, false );
	}

	/**
	 * Register settings.
	 *
	 * Register settings for the plugin.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'options',
			Settings::REVIEW_DATA,
			[
				'type' => 'object',
				'show_in_rest' => [
					'schema' => [
						'type' => 'object',
						'additionalProperties' => true,
					],
				],
			]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_review_data(): array {
		$data = get_option( Settings::REVIEW_DATA, [] );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Whether the review prompt may appear based on scan activity: at least one completed scan,
	 * and at least one full day has passed since that scan finished (updated_at).
	 *
	 * @return bool
	 */
	public function show_review_popup(): bool {
		$first_completed = Scan_Entry::get_first_completed_scan();

		if ( ! $first_completed || empty( $first_completed->updated_at ) ) {
			return false;
		}

		$completed_at = strtotime( $first_completed->updated_at );

		if ( false === $completed_at ) {
			return false;
		}

		return ( time() - $completed_at ) >= DAY_IN_SECONDS;
	}

	/**
	 * Maybe show the review popup.
	 * Check if the review popup should be shown based on various conditions.
	 * @return bool
	 */
	public function maybe_show_review_popup() {
		if ( ! $this->show_review_popup() ) {
			return false;
		}

		$review_data = $this->get_review_data();

		// Don't show if user has already submitted feedback when rating is less than 4.
		if ( isset( $review_data['rating'] ) && (int) $review_data['rating'] < self::HIGH_RATING_THRESHOLD ) {
			return false;
		}

		// Hide if rating is submitted but repo review is not clicked.
		if (
			isset( $review_data['rating'] ) &&
			(int) $review_data['rating'] >= self::HIGH_RATING_THRESHOLD &&
			! empty( $review_data['repo_review_clicked'] )
		) {
			return false;
		}

		// Don't show if user has dismissed the popup 3+ times.
		if ( (int) ( $review_data['dismissals'] ?? 0 ) >= self::MAX_POPUP_DISMISSALS ) {
			return false;
		}

		if ( ! empty( $review_data['hide_for_days'] ) && ! empty( $review_data['last_dismiss'] ) ) {
			$hide_for_days = (int) $review_data['hide_for_days'];
			$last_dismiss  = strtotime( $review_data['last_dismiss'] );

			if ( false === $last_dismiss ) {
				return true;
			}

			$days_since_dismiss = (int) floor( ( time() - $last_dismiss ) / DAY_IN_SECONDS );

			if ( $days_since_dismiss < $hide_for_days ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add review link to plugin row meta
	 *
	 * @param array  $links Plugin row meta links.
	 * @param string $file  Plugin file path.
	 * @return array
	 */
	public function add_plugin_row_meta( $links, $file ) {

		if ( ! defined( 'COOKIEZ_PLUGIN_BASE' ) || COOKIEZ_PLUGIN_BASE !== $file ) {
			return $links;
		}

		$review_url = 'https://wordpress.org/support/plugin/cookiez/reviews/#new-post';

		$links[] = '<a class="wp-cookiez-review"
						href="' . esc_url( $review_url ) . '"
						target="_blank" rel="noopener noreferrer"
						title="' . esc_attr__( 'Review our plugin', 'cookiez' ) . '"
						aria-label="' . esc_attr__(
							'Rate Cookiez on WordPress.org (opens in a new tab)',
							'cookiez'
						) . '">
							<span class="wp-cookiez-review__stars" aria-hidden="true">
								<span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
							</span>
					</a>';

		return $links;
	}

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_plugin_row_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_footer', [ $this, 'print_reviews_mount' ] );
		add_action( 'admin_init', [ $this, 'register_base_data' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_row_meta', [ $this, 'add_plugin_row_meta' ], 10, 2 );

		$this->register_routes();
	}
}

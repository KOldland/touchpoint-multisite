<?php

namespace Cookiez\Modules\Settings\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Cookiez\Modules\Core\Components\Pointers;
use Cookiez\Modules\Settings\Module as Settings_Module;
use Cookiez\Classes\Utils;

/**
 * Settings Pointer
 */
class Settings_Pointer {
	const CURRENT_POINTER_SLUG = 'cookiez-settings';

	/**
	 * Print the script for the settings pointer
	 *
	 * @return void
	 */
	public function admin_print_script() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( Pointers::is_dismissed( self::CURRENT_POINTER_SLUG ) ) {
			return;
		}

		if ( Settings_Module::is_elementor_one() ) {
			return;
		}

		if ( Utils::is_plugin_page() ) {
			return;
		}

		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-util' );

		$pointer_content = sprintf(
			'<h3>%s</h3><p>%s</p><p><a class="button button-primary cookiez-pointer-settings-link" href="%s">%s</a></p>',
			esc_html__( 'Cookie Consent', 'cookiez' ),
			esc_html__( 'Start configuring your cookie banner and manage user consent to improve your site compliance.', 'cookiez' ),
			admin_url( 'admin.php?page=' . Settings_Module::SETTING_BASE_SLUG ),
			esc_html__( 'Get started', 'cookiez' )
		);

		$allowed_tags = [
			'h3' => [],
			'p' => [],
			'a' => [
				'class' => [],
				'href' => [],
			],
		];

		$pointer_position = [
			'edge'  => 'top',
			'align' => 'left',
			'at'    => 'left+20 bottom',
			'my'    => is_rtl() ? 'left-200 top' : 'left top',
		];

		?>
		<script>
			const onClose = () => {
				return wp.ajax.post( 'cookiez_pointer_dismissed', {
					data: {
						pointer: '<?php echo esc_js( static::CURRENT_POINTER_SLUG ); ?>',
					},
					nonce: '<?php echo esc_js( wp_create_nonce( 'cookiez-pointer-dismissed' ) ); ?>',
				} );
			}

			jQuery( document ).ready( function( $ ) {
				$( '#toplevel_page_elementor-home' ).pointer( {
					content: '<?php echo wp_kses( $pointer_content, $allowed_tags ); ?>',
					pointerClass: 'cookiez-settings-pointer',
					position: <?php echo wp_json_encode( $pointer_position ); ?>,
					show: function( event, t ) {
						t.pointer.show().css( 'z-index', 100050 );
						t.opened();
					},
					close: onClose
				} ).pointer( 'open' );

				$( '.cookiez-pointer-settings-link' ).first().on( 'click', function( e ) {
					e.preventDefault();

					$(this).attr( 'disabled', true );

					onClose().promise().done(() => {
						location = $(this).attr( 'href' );
					});
				})
			} );
		</script>

		<style>
			.cookiez-settings-pointer .wp-pointer-content h3::before {
				background: transparent;
				border-radius: 0;
				background-repeat: no-repeat;	
				content: '';
				background-image: url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3QgeD0iMSIgeT0iMiIgd2lkdGg9IjMwIiBoZWlnaHQ9IjMwIiByeD0iNSIgZmlsbD0iI0ZBRTRGQSIvPgo8Y2lyY2xlIGN4PSIxNC41OTY4IiBjeT0iMTYuMDQ4NSIgcj0iMS4yNDk5MSIgc3Ryb2tlPSIjRUQwMUVFIiBzdHJva2Utd2lkdGg9IjAuODY4MDMxIi8+CjxjaXJjbGUgY3g9IjEyLjk5OTYiIGN5PSIyMC4wMDY3IiByPSIxLjA0MTYiIHN0cm9rZT0iI0VEMDFFRSIgc3Ryb2tlLXdpZHRoPSIwLjg2ODAzMSIvPgo8Y2lyY2xlIGN4PSIxNy43MjE2IiBjeT0iMjAuNDcxNiIgcj0iMC45MDI3MTYiIHN0cm9rZT0iI0VEMDFFRSIgc3Ryb2tlLXdpZHRoPSIwLjg2ODAzMSIvPgo8cGF0aCBkPSJNMTUuNDMwMSAxMS4yMzYxQzE1LjUzOTggMTEuMjM2MSAxNS42NDkgMTEuMjM4OCAxNS43NTc1IDExLjI0NDFDMTYuMDQwNSAxMS4yNTc4IDE2LjI2NjggMTEuNDY1MSAxNi4zODczIDExLjcyMTZDMTYuODMwOSAxMi42NjU2IDE3Ljc4OTkgMTMuMzE5MyAxOC45MDIgMTMuMzE5M0MxOS4yNTQ0IDEzLjMxOTMgMTkuNTk2NCAxMy42NjEzIDE5LjU5NjQgMTQuMDEzN0MxOS41OTY0IDE1LjMxNTcgMjAuNDkyMiAxNi40MDg1IDIxLjcwMTIgMTYuNzA5M0MyMi4wMDkgMTYuNzg1OSAyMi4yODE2IDE3LjAwNDkgMjIuMzIwNyAxNy4zMTk3QzIyLjM1NTggMTcuNjAxNiAyMi4zNzQgMTcuODg4NyAyMi4zNzQgMTguMTgwMUMyMi4zNzQgMjIuMDE1NCAxOS4yNjUyIDI1LjEyNDYgMTUuNDMwMSAyNS4xMjQ3QzExLjU5NDkgMjUuMTI0NyA4LjQ4NjA4IDIyLjAxNTQgOC40ODYwOCAxOC4xODAxQzguNDg2NDIgMTQuMzQ1IDExLjU5NTEgMTEuMjM2MSAxNS40MzAxIDExLjIzNjFaIiBzdHJva2U9IiNFRDAxRUUiIHN0cm9rZS13aWR0aD0iMS4wNDE1OSIvPgo8cmVjdCB4PSIxOS41IiB3aWR0aD0iMTIuNSIgaGVpZ2h0PSIxMi41IiByeD0iNi4yNSIgZmlsbD0iI0VEMDFFRSIvPgo8Y2lyY2xlIGN4PSIyNS43NSIgY3k9IjYuMjUiIHI9IjUuNjI1IiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMjUuNzUgMC42MjVDMjIuNjQzOSAwLjYyNSAyMC4xMjUgMy4xNDM4NyAyMC4xMjUgNi4yNUMyMC4xMjUgOS4zNTYxMiAyMi42NDM5IDExLjg3NSAyNS43NSAxMS44NzVDMjguODU2MSAxMS44NzUgMzEuMzc1IDkuMzU2MTIgMzEuMzc1IDYuMjVDMzEuMzc1IDMuMTQzODcgMjguODU2MSAwLjYyNSAyNS43NSAwLjYyNVpNMjQuMDYyNSA5LjA2MjVIMjIuOTM3NVYzLjQzNzVIMjQuMDYyNVY5LjA2MjVaTTI4LjU2MjUgOS4wNjI1SDI1LjE4NzVWNy45Mzc1SDI4LjU2MjVWOS4wNjI1Wk0yOC41NjI1IDYuODEyNUgyNS4xODc1VjUuNjg3NUgyOC41NjI1VjYuODEyNVpNMjguNTYyNSA0LjU2MjVIMjUuMTg3NVYzLjQzNzVIMjguNTYyNVY0LjU2MjVaIiBmaWxsPSIjRUQwMUVFIi8+Cjwvc3ZnPgo=");
			}
		</style>
		<?php
	}

	/**
	 * @return void
	 */
	public function __construct() {
		add_action( 'in_admin_header', [ $this, 'admin_print_script' ] );
	}
}
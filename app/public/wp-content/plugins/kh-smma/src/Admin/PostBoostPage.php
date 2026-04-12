<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use function add_action;
use function add_meta_box;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html_e;
use function get_current_screen;
use function get_current_user_id;
use function get_post;
use function plugin_dir_url;
use function rest_url;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostBoostPage {
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_meta_box(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		add_meta_box(
			'kh-smma-post-boost',
			__( 'SMMA Boost Workflow', 'kh-smma' ),
			array( $this, 'render_meta_box' ),
			array( 'post', 'page' ),
			'side',
			'high'
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		$version = defined( 'KH_SMMA_VERSION' ) ? KH_SMMA_VERSION : '1.0.0';
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$post = $post_id ? get_post( $post_id ) : null;
		$status = $post ? (string) $post->post_status : 'draft';

		wp_enqueue_style(
			'kh-smma-editor-workflow',
			$plugin_url . 'assets/css/editor-workflow.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'kh-smma-variant-grid',
			$plugin_url . 'assets/js/variant-grid.js',
			array(),
			$version,
			true
		);
		wp_enqueue_script(
			'kh-smma-variant-editor',
			$plugin_url . 'assets/js/variant-editor.js',
			array( 'kh-smma-variant-grid' ),
			$version,
			true
		);
		wp_enqueue_script(
			'kh-smma-schedule-modal',
			$plugin_url . 'assets/js/schedule-modal.js',
			array( 'kh-smma-variant-grid' ),
			$version,
			true
		);
		wp_enqueue_script(
			'kh-smma-editor-generate',
			$plugin_url . 'assets/js/editor-generate.js',
			array( 'kh-smma-variant-grid', 'kh-smma-variant-editor', 'kh-smma-schedule-modal' ),
			$version,
			true
		);

		wp_localize_script( 'kh-smma-editor-generate', 'khSmmaEditor', array(
			'apiBase' => rest_url( 'kh-smma/v1' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'postId' => $post_id,
			'postStatus' => $status,
			'userId' => get_current_user_id(),
			'urls' => array(
				'dashboard' => admin_url( 'admin.php?page=kh-smma-dashboard' ),
			),
		) );
	}

	public function render_meta_box(): void {
		?>
		<div class="kh-smma-editor-workflow-entry">
			<p><?php esc_html_e( 'Generate, edit, and schedule campaign variants from this post.', 'kh-smma' ); ?></p>
			<button type="button" class="button button-primary" id="kh-smma-open-workflow">
				<?php esc_html_e( 'Boost Post', 'kh-smma' ); ?>
			</button>
			<div id="kh-smma-editor-root" data-post-id="<?php echo esc_attr( (string) ( isset( $_GET['post'] ) ? (int) $_GET['post'] : 0 ) ); ?>"></div>
		</div>
		<?php
	}
}

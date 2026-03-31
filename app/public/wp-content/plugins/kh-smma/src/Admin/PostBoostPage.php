<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use function add_action;
use function add_meta_box;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html_e;
use function esc_textarea;
use function get_current_screen;
use function get_current_user_id;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_the_post_thumbnail_url;
use function get_post_type;
use function plugin_dir_url;
use function rest_url;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function update_post_meta;
use function delete_post_meta;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_unslash;
use function wp_verify_nonce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostBoostPage {
	private const META_PLATFORM_KEYS = array( 'facebook', 'twitter', 'linkedin', 'pinterest' );

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'save_post', array( $this, 'save_social_workspace_meta' ) );
	}

	public function register_meta_box(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			// Gutenberg entry moves into the GEO AnswerCards sidebar to reduce duplicate UI.
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
		$plugin_path = dirname( dirname( __FILE__ ) );
		$version = defined( 'KH_SMMA_VERSION' ) ? KH_SMMA_VERSION : '1.0.0';
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$post = $post_id ? get_post( $post_id ) : null;
		$status = $post ? (string) $post->post_status : 'draft';
		$asset_version = static function( string $relative_path ) use ( $plugin_path, $version ) {
			$full_path = $plugin_path . '/' . ltrim( $relative_path, '/' );
			return file_exists( $full_path ) ? (string) filemtime( $full_path ) : $version;
		};

		wp_enqueue_style(
			'kh-smma-editor-workflow',
			$plugin_url . 'assets/css/editor-workflow.css',
			array(),
			$asset_version( 'assets/css/editor-workflow.css' )
		);

		wp_enqueue_script(
			'kh-smma-variant-grid',
			$plugin_url . 'assets/js/variant-grid.js',
			array(),
			$asset_version( 'assets/js/variant-grid.js' ),
			true
		);
		wp_enqueue_script(
			'kh-smma-variant-editor',
			$plugin_url . 'assets/js/variant-editor.js',
			array( 'kh-smma-variant-grid' ),
			$asset_version( 'assets/js/variant-editor.js' ),
			true
		);
		wp_enqueue_script(
			'kh-smma-schedule-modal',
			$plugin_url . 'assets/js/schedule-modal.js',
			array( 'kh-smma-variant-grid' ),
			$asset_version( 'assets/js/schedule-modal.js' ),
			true
		);
		wp_enqueue_script(
			'kh-smma-editor-generate',
			$plugin_url . 'assets/js/editor-generate.js',
			array( 'kh-smma-variant-grid', 'kh-smma-variant-editor', 'kh-smma-schedule-modal' ),
			$asset_version( 'assets/js/editor-generate.js' ),
			true
		);
		if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			wp_enqueue_script(
				'kh-smma-editor-sidebar',
				$plugin_url . 'assets/js/editor-sidebar.js',
				array( 'kh-smma-editor-generate', 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-i18n' ),
				$asset_version( 'assets/js/editor-sidebar.js' ),
				true
			);
		}

		wp_localize_script( 'kh-smma-editor-generate', 'khSmmaEditor', array(
			'apiBase' => rest_url( 'kh-smma/v1' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'postId' => $post_id,
			'postStatus' => $status,
			'featuredImageUrl' => $post_id ? (string) ( get_the_post_thumbnail_url( $post_id, 'large' ) ?: '' ) : '',
			'userId' => get_current_user_id(),
			'allowedPlatforms' => $this->get_enabled_platforms(),
			'urls' => array(
				'dashboard' => admin_url( 'admin.php?page=kh-smma-dashboard' ),
			),
		) );
	}

	public function render_meta_box(): void {
		$post_id = $this->get_current_post_id();
		$generic_title = $post_id ? (string) get_post_meta( $post_id, '_khm_seo_social_title', true ) : '';
		$generic_description = $post_id ? (string) get_post_meta( $post_id, '_khm_seo_social_description', true ) : '';
		$twitter_card_type = $post_id ? (string) get_post_meta( $post_id, '_khm_seo_twitter_card_type', true ) : '';
		if ( '' === $twitter_card_type ) {
			$twitter_card_type = 'summary_large_image';
		}

		?>
		<div class="kh-smma-editor-workflow-entry" id="kh-smma-post-boost-workspace">
			<div class="kh-social-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Social workspace sections', 'kh-smma' ); ?>">
				<button type="button" class="kh-social-tab is-active" data-kh-social-tab="campaigns" role="tab" aria-selected="true">
					<?php esc_html_e( 'Campaigns', 'kh-smma' ); ?>
				</button>
				<button type="button" class="kh-social-tab" data-kh-social-tab="metadata" role="tab" aria-selected="false">
					<?php esc_html_e( 'Sharing Metadata', 'kh-smma' ); ?>
				</button>
			</div>

			<div class="kh-social-panel is-active" data-kh-social-panel="campaigns">
				<p><?php esc_html_e( 'Generate, edit, and schedule campaign variants from this post.', 'kh-smma' ); ?></p>
				<button type="button" class="button button-primary" id="kh-smma-open-workflow">
					<?php esc_html_e( 'Boost Post', 'kh-smma' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Use this for social campaign copy, compliance, and scheduling.', 'kh-smma' ); ?>
				</p>
				<div id="kh-smma-editor-root" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"></div>
			</div>

			<div class="kh-social-panel" data-kh-social-panel="metadata" hidden>
				<?php wp_nonce_field( 'kh_social_workspace_meta', 'kh_social_workspace_nonce' ); ?>
				<p class="description">
					<?php esc_html_e( 'Set metadata used for Open Graph / Twitter sharing previews.', 'kh-smma' ); ?>
				</p>

				<p>
					<label for="kh-social-generic-title"><strong><?php esc_html_e( 'Default Social Title', 'kh-smma' ); ?></strong></label>
					<input type="text" id="kh-social-generic-title" name="kh_social_generic_title" value="<?php echo esc_attr( $generic_title ); ?>" />
				</p>

				<p>
					<label for="kh-social-generic-description"><strong><?php esc_html_e( 'Default Social Description', 'kh-smma' ); ?></strong></label>
					<textarea id="kh-social-generic-description" name="kh_social_generic_description" rows="3"><?php echo esc_textarea( $generic_description ); ?></textarea>
				</p>

				<p>
					<label for="kh-social-twitter-card-type"><strong><?php esc_html_e( 'Twitter Card Type', 'kh-smma' ); ?></strong></label>
					<select id="kh-social-twitter-card-type" name="kh_social_twitter_card_type">
						<option value="summary" <?php selected( $twitter_card_type, 'summary' ); ?>><?php esc_html_e( 'Summary', 'kh-smma' ); ?></option>
						<option value="summary_large_image" <?php selected( $twitter_card_type, 'summary_large_image' ); ?>><?php esc_html_e( 'Summary Large Image', 'kh-smma' ); ?></option>
					</select>
				</p>

				<div class="kh-social-platform-grid">
					<?php foreach ( self::META_PLATFORM_KEYS as $platform ) : ?>
						<?php
						$platform_title = $post_id ? (string) get_post_meta( $post_id, "_khm_seo_social_{$platform}_title", true ) : '';
						$platform_description = $post_id ? (string) get_post_meta( $post_id, "_khm_seo_social_{$platform}_description", true ) : '';
						?>
						<div class="kh-social-platform-card">
							<h4><?php echo esc_html( ucfirst( $platform ) ); ?></h4>
							<p>
								<label for="<?php echo esc_attr( "kh-social-{$platform}-title" ); ?>"><?php esc_html_e( 'Title', 'kh-smma' ); ?></label>
								<input type="text" id="<?php echo esc_attr( "kh-social-{$platform}-title" ); ?>" name="<?php echo esc_attr( "kh_social_{$platform}_title" ); ?>" value="<?php echo esc_attr( $platform_title ); ?>" />
							</p>
							<p>
								<label for="<?php echo esc_attr( "kh-social-{$platform}-description" ); ?>"><?php esc_html_e( 'Description', 'kh-smma' ); ?></label>
								<textarea id="<?php echo esc_attr( "kh-social-{$platform}-description" ); ?>" name="<?php echo esc_attr( "kh_social_{$platform}_description" ); ?>" rows="2"><?php echo esc_textarea( $platform_description ); ?></textarea>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_social_workspace_meta( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		$nonce = isset( $_POST['kh_social_workspace_nonce'] ) ? (string) wp_unslash( $_POST['kh_social_workspace_nonce'] ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'kh_social_workspace_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$generic_title = isset( $_POST['kh_social_generic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_social_generic_title'] ) ) : '';
		$generic_description = isset( $_POST['kh_social_generic_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['kh_social_generic_description'] ) ) : '';
		$twitter_card_type = isset( $_POST['kh_social_twitter_card_type'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_social_twitter_card_type'] ) ) : '';

		$this->persist_meta_value( $post_id, '_khm_seo_social_title', $generic_title );
		$this->persist_meta_value( $post_id, '_khm_seo_social_description', $generic_description );
		$this->persist_meta_value( $post_id, '_khm_seo_twitter_card_type', $twitter_card_type );

		foreach ( self::META_PLATFORM_KEYS as $platform ) {
			$title_key = "kh_social_{$platform}_title";
			$description_key = "kh_social_{$platform}_description";
			$title = isset( $_POST[ $title_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $title_key ] ) ) : '';
			$description = isset( $_POST[ $description_key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $description_key ] ) ) : '';

			$this->persist_meta_value( $post_id, "_khm_seo_social_{$platform}_title", $title );
			$this->persist_meta_value( $post_id, "_khm_seo_social_{$platform}_description", $description );
		}
	}

	private function persist_meta_value( int $post_id, string $meta_key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	private function get_current_post_id(): int {
		if ( isset( $_GET['post'] ) ) {
			return (int) $_GET['post'];
		}

		if ( isset( $_POST['post_ID'] ) ) {
			return (int) $_POST['post_ID'];
		}

		return 0;
	}

	private function get_enabled_platforms(): array {
		$stored = get_option( 'kh_smma_enabled_platforms', array( 'linkedin', 'google' ) );
		if ( ! is_array( $stored ) ) {
			return array( 'linkedin', 'google' );
		}

		$normalized = array();
		foreach ( $stored as $value ) {
			$key = sanitize_text_field( (string) $value );
			if ( in_array( $key, array( 'linkedin', 'google' ), true ) ) {
				$normalized[] = $key;
			}
		}

		if ( empty( $normalized ) ) {
			return array( 'linkedin' );
		}

		return array_values( array_unique( $normalized ) );
	}
}

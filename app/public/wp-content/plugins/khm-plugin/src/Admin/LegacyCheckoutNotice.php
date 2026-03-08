<?php

namespace KHM\Admin;

class LegacyCheckoutNotice {
	private const CACHE_KEY = 'khm_legacy_checkout_pages_v1';
	private const RESULT_QUERY_ARG = 'khm_legacy_migration_result';

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_khm_create_legacy_checkout_drafts', array( $this, 'handle_create_drafts' ) );
		add_action( 'save_post', array( $this, 'clear_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_cache' ) );
		add_action( 'trashed_post', array( $this, 'clear_cache' ) );
	}

	public function render_notice(): void {
		if ( ! current_user_can( 'manage_khm' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_relevant_admin_screen() ) {
			return;
		}

		$pages = $this->find_legacy_checkout_pages();
		if ( empty( $pages ) ) {
			$this->render_result_notice();
			return;
		}

		$this->render_result_notice();
		$count = count( $pages );
		$action_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=khm_create_legacy_checkout_drafts' ),
			'khm_create_legacy_checkout_drafts'
		);

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'KHM Checkout hardening:', 'khm-membership' ) . '</strong> ';
		echo esc_html__( 'Legacy [khm_checkout] pages are still published. Membership sales should use the Stripe modal checkout path.', 'khm-membership' );
		echo '</p><p>' . esc_html__( 'Detected pages:', 'khm-membership' ) . '</p><ul style="margin-left:1.2em;list-style:disc;">';
		foreach ( $pages as $page ) {
			$page_id = isset( $page['ID'] ) ? (int) $page['ID'] : (int) ( $page['id'] ?? 0 );
			$title_text = isset( $page['post_title'] ) ? (string) $page['post_title'] : '';
			$title = $title_text !== '' ? $title_text : sprintf( __( 'Post #%d', 'khm-membership' ), $page_id );
			echo '<li><a href="' . esc_url( get_edit_post_link( $page_id ) ) . '">' . esc_html( $title ) . '</a></li>';
		}
		echo '</ul>';
		if ( $count >= 10 ) {
			echo '<p>' . esc_html__( 'Showing first 10 matches.', 'khm-membership' ) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( $action_url ) . '">' . esc_html__( 'Create Draft Replacements', 'khm-membership' ) . '</a></p>';
		echo '</div>';
	}

	public function handle_create_drafts(): void {
		if ( ! current_user_can( 'manage_khm' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'khm-membership' ) );
		}
		check_admin_referer( 'khm_create_legacy_checkout_drafts' );

		$pages = $this->find_legacy_checkout_pages();
		$created = 0;
		$skipped = 0;

		foreach ( $pages as $page ) {
			$source_id = isset( $page['ID'] ) ? (int) $page['ID'] : (int) ( $page['id'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			$source = get_post( $source_id );
			if ( ! $source || ! isset( $source->post_content ) ) {
				$skipped++;
				continue;
			}

			$new_content = $this->convert_legacy_checkout_content( (string) $source->post_content );
			if ( $new_content === (string) $source->post_content ) {
				$skipped++;
				continue;
			}

			$draft_id = wp_insert_post(
				array(
					'post_type'    => $source->post_type,
					'post_status'  => 'draft',
					'post_title'   => sprintf( __( '%s (Modal Checkout Draft)', 'khm-membership' ), (string) $source->post_title ),
					'post_content' => $new_content,
					'post_author'  => get_current_user_id(),
				),
				true
			);

			if ( is_wp_error( $draft_id ) ) {
				$skipped++;
				continue;
			}

			$created++;
		}

		$this->clear_cache();
		$redirect = add_query_arg(
			array(
				'page'                    => 'khm-levels',
				self::RESULT_QUERY_ARG    => $created . ':' . $skipped,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	private function is_relevant_admin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return true;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return true;
		}

		$allowed = array(
			'toplevel_page_khm-dashboard',
			'khm-membership_page_khm-levels',
			'edit-page',
			'page',
			'edit-post',
			'post',
		);

		return in_array( $screen->id, $allowed, true );
	}

	private function find_legacy_checkout_pages(): array {
		global $wpdb;

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts_table = $wpdb->posts;
		$sql = "
			SELECT ID, post_title, post_content
			FROM {$posts_table}
			WHERE post_status = 'publish'
				AND post_type IN ('page', 'post')
				AND post_content LIKE %s
			ORDER BY ID DESC
			LIMIT 10
		";
		$like = '%[khm_checkout%';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $like ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();

		set_transient( self::CACHE_KEY, $rows, 5 * MINUTE_IN_SECONDS );
		return $rows;
	}

	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	private function convert_legacy_checkout_content( string $content ): string {
		$pattern = '/\[khm_checkout([^\]]*)\]/i';
		$updated = preg_replace_callback(
			$pattern,
			function ( array $matches ): string {
				$attrs_raw = trim( (string) ( $matches[1] ?? '' ) );
				$attrs = shortcode_parse_atts( $attrs_raw );
				$attrs = is_array( $attrs ) ? $attrs : array();
				$level_id = isset( $attrs['level_id'] ) ? absint( $attrs['level_id'] ) : 0;

				if ( $level_id > 0 ) {
					return '[khm_membership_checkout_button level_id="' . $level_id . '"]';
				}

				return '<!-- Legacy khm_checkout shortcode detected with no level_id. Add one or replace manually with [khm_membership_checkout_button level_id="..."] -->';
			},
			$content
		);

		return is_string( $updated ) ? $updated : $content;
	}

	private function render_result_notice(): void {
		if ( empty( $_GET[ self::RESULT_QUERY_ARG ] ) ) {
			return;
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_GET[ self::RESULT_QUERY_ARG ] ) );
		$parts = explode( ':', $raw );
		$created = isset( $parts[0] ) ? max( 0, (int) $parts[0] ) : 0;
		$skipped = isset( $parts[1] ) ? max( 0, (int) $parts[1] ) : 0;

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html( sprintf( __( 'Legacy checkout migration complete. Drafts created: %1$d. Skipped: %2$d.', 'khm-membership' ), $created, $skipped ) );
		echo '</p></div>';
	}
}

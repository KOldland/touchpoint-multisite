<?php
/**
 * Social Media Manager — Unified social tools for KH-SMMA
 *
 * Active LinkedIn publishing pipeline:
 * - Save social data to post meta
 * - Post to LinkedIn immediately via API
 * - Queue for later review in SMMA admin
 *
 * Removed: passive OG meta tag output (legacy — replaced by direct API publishing).
 *
 * @package KH_SMMA\Social
 * @since 0.3.0
 */

namespace KH_SMMA\Social;

use KH\ContentRegistry\Services\ContentRegistryService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Social Media Manager Class
 */
class SocialManager {

	/**
	 * Meta key prefix for per-post social data.
	 *
	 * @var string
	 */
	const META_PREFIX = '_kh_smma_social_linkedin';

	/**
	 * Queue status meta key.
	 *
	 * @var string
	 */
	const QUEUE_STATUS_KEY = '_kh_smma_social_queue_status';

	/**
	 * Constructor — registers all hooks.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Admin meta box
		add_action( 'add_meta_boxes', array( $this, 'add_social_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_social_meta' ) );

		// AJAX for save
		add_action( 'wp_ajax_kh_smma_save_social', array( $this, 'ajax_save_social' ) );

		// AJAX for post now (immediate publish to LinkedIn)
		add_action( 'wp_ajax_kh_smma_post_now', array( $this, 'ajax_post_now' ) );

		// AJAX for queue for later
		add_action( 'wp_ajax_kh_smma_queue_later', array( $this, 'ajax_queue_later' ) );

		// AJAX for live preview
		add_action( 'wp_ajax_kh_smma_social_preview', array( $this, 'ajax_social_preview' ) );

		// AJAX for AI suggestion
		add_action( 'wp_ajax_kh_smma_social_suggest', array( $this, 'ajax_suggest_social' ) );

		// REST endpoint for block editor / variant pipeline
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	// ─── Meta Box ─────────────────────────────────────────────────

	/**
	 * Add social media meta box.
	 */
	public function add_social_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'kh-smma-social',
				__( 'LinkedIn Post', 'kh-smma' ),
				array( $this, 'render_social_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render the social media meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_social_meta_box( $post ) {
		wp_nonce_field( 'kh_smma_social_meta', 'kh_smma_social_nonce' );

		$title        = get_post_meta( $post->ID, self::META_PREFIX . '_title', true );
		$description  = get_post_meta( $post->ID, self::META_PREFIX . '_description', true );
		$image_id     = get_post_meta( $post->ID, self::META_PREFIX . '_image', true );
		$queue_status = get_post_meta( $post->ID, self::QUEUE_STATUS_KEY, true );
		$last_posted  = get_post_meta( $post->ID, self::META_PREFIX . '_last_posted', true );

		include KH_SMMA_PATH . 'admin/templates/meta-box-social.php';
	}

	/**
	 * Save social media meta data (via WordPress save_post hook).
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_social_meta( $post_id ) {
		if ( ! isset( $_POST['kh_smma_social_nonce'] )
			|| ! wp_verify_nonce( $_POST['kh_smma_social_nonce'], 'kh_smma_social_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->persist_social_fields( $post_id, $_POST );
	}

	/**
	 * Persist social fields from a data array.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    POST data array.
	 */
	private function persist_social_fields( $post_id, $data ) {
		$fields = array( 'title', 'description', 'image' );
		foreach ( $fields as $field ) {
			$key   = self::META_PREFIX . "_{$field}";
			$input = $data[ 'kh_smma_social_linkedin_' . $field ] ?? '';

			if ( ! empty( $input ) ) {
				if ( 'description' === $field ) {
					update_post_meta( $post_id, $key, sanitize_textarea_field( $input ) );
				} else {
					update_post_meta( $post_id, $key, sanitize_text_field( $input ) );
				}
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}

	// ─── Social Data Getters ──────────────────────────────────────

	/**
	 * Get social title for a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function get_social_title( $post ) {
		$custom = get_post_meta( $post->ID, self::META_PREFIX . '_title', true );
		if ( ! empty( $custom ) ) {
			return $custom;
		}

		$seo_title = get_post_meta( $post->ID, '_khm_seo_title', true );
		if ( ! empty( $seo_title ) ) {
			return $seo_title;
		}

		return get_the_title( $post );
	}

	/**
	 * Get social description for a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function get_social_description( $post ) {
		$custom = get_post_meta( $post->ID, self::META_PREFIX . '_description', true );
		if ( ! empty( $custom ) ) {
			return $custom;
		}

		$seo_desc = get_post_meta( $post->ID, '_khm_seo_description', true );
		if ( ! empty( $seo_desc ) ) {
			return $seo_desc;
		}

		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 30, '...' );
	}

	/**
	 * Get social image for a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string|null
	 */
	public function get_social_image( $post ) {
		$custom = get_post_meta( $post->ID, self::META_PREFIX . '_image', true );
		if ( ! empty( $custom ) ) {
			$image_data = wp_get_attachment_image_src( (int) $custom, 'full' );
			if ( $image_data ) {
				return $image_data[0];
			}
		}

		if ( has_post_thumbnail( $post ) ) {
			$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
			if ( $image_data ) {
				return $image_data[0];
			}
		}

		return null;
	}

	/**
	 * Get LinkedIn credentials from editorial settings.
	 *
	 * @return array|null
	 */
	public static function get_linkedin_credentials() {
		$settings = get_option( 'kh_editorial_settings', array() );
		$token    = $settings['linkedin_access_token'] ?? '';
		$author   = $settings['linkedin_author_urn'] ?? '';

		if ( empty( $token ) || empty( $author ) ) {
			return null;
		}

		return array(
			'access_token' => $token,
			'author'       => $author,
		);
	}

	// ─── AJAX: Save Social Data ───────────────────────────────────

	/**
	 * AJAX handler — explicitly save social fields without a full post save.
	 */
	public function ajax_save_social() {
		check_ajax_referer( 'kh_smma_social_preview', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Invalid post or insufficient permissions', 'kh-smma' ) );
		}

		$this->persist_social_fields( $post_id, $_POST );

		$now = current_time( 'timestamp' );
		update_post_meta( $post_id, self::META_PREFIX . '_last_saved', $now );

		wp_send_json_success( array(
			'message'   => __( 'Social data saved.', 'kh-smma' ),
			'timestamp' => $now,
			'human'     => human_time_diff( $now, current_time( 'timestamp' ) ) . ' ago',
		) );
	}

	// ─── AJAX: Post Now ──────────────────────────────────────────

	/**
	 * AJAX handler — immediately post to LinkedIn.
	 */
	public function ajax_post_now() {
		check_ajax_referer( 'kh_smma_social_preview', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Invalid post or insufficient permissions', 'kh-smma' ) );
		}

		// Save fields first
		$this->persist_social_fields( $post_id, $_POST );

		$result = $this->publish_to_linkedin( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		$now = current_time( 'timestamp' );
		update_post_meta( $post_id, self::META_PREFIX . '_last_posted', $now );
		update_post_meta( $post_id, self::QUEUE_STATUS_KEY, 'published' );
		update_post_meta( $post_id, self::META_PREFIX . '_last_response', $result );

		// Transition registry status to Live
		$this->sync_publish_to_registry( $post_id, $result );

		wp_send_json_success( array(
			'message'   => __( 'Posted to LinkedIn successfully.', 'kh-smma' ),
			'response'  => $result,
			'timestamp' => $now,
			'human'     => human_time_diff( $now, current_time( 'timestamp' ) ) . ' ago',
		) );
	}

	// ─── AJAX: Queue for Later ────────────────────────────────────

	/**
	 * AJAX handler — save and queue for later review.
	 */
	public function ajax_queue_later() {
		check_ajax_referer( 'kh_smma_social_preview', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Invalid post or insufficient permissions', 'kh-smma' ) );
		}

		// Save fields first
		$this->persist_social_fields( $post_id, $_POST );

		$now = current_time( 'timestamp' );
		update_post_meta( $post_id, self::QUEUE_STATUS_KEY, 'pending' );
		update_post_meta( $post_id, self::META_PREFIX . '_queued_at', $now );

		wp_send_json_success( array(
			'message'   => __( 'Queued for later. Review in SMMA → Social Queue.', 'kh-smma' ),
			'timestamp' => $now,
			'human'     => human_time_diff( $now, current_time( 'timestamp' ) ) . ' ago',
		) );
	}

	// ─── LinkedIn API Publishing ──────────────────────────────────

	/**
	 * Publish a post to LinkedIn via the UGC Posts API.
	 *
	 * Uses credentials from kh_editorial_settings (same admin as AI APIs).
	 *
	 * @param int $post_id Post ID.
	 * @return array|\WP_Error Response data or error.
	 */
	public function publish_to_linkedin( $post_id ) {
		$creds = self::get_linkedin_credentials();

		if ( ! $creds ) {
			return new \WP_Error(
				'kh_smma_linkedin_no_creds',
				__( 'LinkedIn credentials not configured. Go to Editorial Studio → API Settings.', 'kh-smma' )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'kh_smma_post_not_found', __( 'Post not found.', 'kh-smma' ) );
		}

		$title       = $this->get_social_title( $post );
		$description = $this->get_social_description( $post );
		$image       = $this->get_social_image( $post );
		$url         = get_permalink( $post );

		if ( ! $url ) {
			return new \WP_Error( 'kh_smma_no_permalink', __( 'Post must be published before sharing to LinkedIn.', 'kh-smma' ) );
		}

		$endpoint = 'https://api.linkedin.com/v2/ugcPosts';

		$share_text = $title;
		if ( ! empty( $description ) ) {
			$share_text .= "\n\n" . $description;
		}
		$share_text .= "\n" . $url;

		$share_content = array(
			'shareCommentary'    => array( 'text' => $share_text ),
			'shareMediaCategory' => 'ARTICLE',
			'media'              => array(
				array(
					'status'      => 'READY',
					'originalUrl' => $url,
					'title'       => array( 'text' => self::truncate_text_ln( $title, 200 ) ),
					'description' => array( 'text' => self::truncate_text_ln( $description ?: $title, 256 ) ),
				),
			),
		);

		// If there's an image, include it as a thumbnail
		if ( $image ) {
			$share_content['media'][0]['thumbnails'] = array(
				array(
					'url' => $image,
				),
			);
		}

		$post_body = array(
			'author'          => $creds['author'],
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => array(
				'com.linkedin.ugc.ShareContent' => $share_content,
			),
			'visibility' => array(
				'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
			),
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization'             => 'Bearer ' . $creds['access_token'],
				'Content-Type'              => 'application/json',
				'X-Restli-Protocol-Version' => '2.0.0',
			),
			'body'    => wp_json_encode( $post_body ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[KH SMMA] LinkedIn publish failed: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			error_log( '[KH SMMA] LinkedIn API error (' . $code . '): ' . $body );
			return new \WP_Error(
				'kh_smma_linkedin_http_error',
				sprintf( __( 'LinkedIn API returned %d. Check your credentials in API Settings.', 'kh-smma' ), $code ),
				$body
			);
		}

		$data = json_decode( $body, true );

		return array(
			'id'        => $data['id'] ?? null,
			'activity'  => $data['activity'] ?? null,
			'timestamp' => current_time( 'timestamp' ),
		);
	}

	/**
	 * Truncate text for LinkedIn field limits.
	 *
	 * @param string $text   Input text.
	 * @param int    $length Max length.
	 * @return string
	 */
	public static function truncate_text_ln( $text, $length ) {
		if ( strlen( $text ) <= $length ) {
			return $text;
		}
		return substr( $text, 0, $length - 3 ) . '...';
	}

	// ─── AJAX / REST Preview ──────────────────────────────────────

	/**
	 * AJAX handler for social preview.
	 */
	public function ajax_social_preview() {
		check_ajax_referer( 'kh_smma_social_preview', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Invalid post or insufficient permissions', 'kh-smma' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Post not found', 'kh-smma' ) );
		}

		$override_title       = sanitize_text_field( $_POST['title'] ?? '' );
		$override_description = sanitize_textarea_field( $_POST['description'] ?? '' );

		$title       = $override_title ?: $this->get_social_title( $post );
		$description = $override_description ?: $this->get_social_description( $post );
		$image       = $this->get_social_image( $post );
		$url         = get_permalink( $post ) ?: home_url( '?p=' . $post_id );

		$preview_html = $this->generate_card_html( $title, $description, $image, $url );

		wp_send_json_success( array(
			'title'        => $title,
			'description'  => $description,
			'image'        => $image,
			'url'          => $url,
			'preview_html' => $preview_html,
			'warnings'     => $this->validate_social_data( $title, $description, $image ),
		) );
	}

	/**
	 * AJAX handler — AI suggestion for social title & description.
	 */
	public function ajax_suggest_social() {
		check_ajax_referer( 'kh_smma_social_preview', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Invalid post or insufficient permissions', 'kh-smma' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Post not found', 'kh-smma' ) );
		}

		$post_title   = get_the_title( $post );
		$post_excerpt = $post->post_excerpt ? wp_strip_all_tags( $post->post_excerpt ) : '';
		$post_body    = wp_strip_all_tags( $post->post_content );
		$max_body     = 1500;
		if ( strlen( $post_body ) > $max_body ) {
			$post_body = substr( $post_body, 0, $max_body ) . '...';
		}

		$llm_available = class_exists( '\\KH\\Editorial\\Core\\LLMService' )
			&& \KH\Editorial\Core\LLMService::is_configured();

		if ( ! $llm_available ) {
			wp_send_json_error( __( 'LLM service is not configured. Please configure an API key in Editorial Settings.', 'kh-smma' ) );
		}

		$system = "You are a social media copywriter. Generate a LinkedIn share for a blog post. "
			. "Return valid JSON with exactly two keys: \"title\" (a compelling headline, max 150 characters) "
			. "and \"description\" (a 2-3 sentence summary, max 300 characters). "
			. "Do NOT include markdown fences or commentary. Output raw JSON only.";

		$user = "Post Title: " . $post_title . "\n\n";
		if ( $post_excerpt ) {
			$user .= "Post Excerpt: " . $post_excerpt . "\n\n";
		}
		$user .= "Post Content: " . $post_body;

		try {
			$route = \KH\Editorial\Core\LLMService::resolve_agent_model( 'social_posts' );

			$result = \KH\Editorial\Core\LLMService::post_completion(
				array(
					array( 'role' => 'system', 'content' => $system ),
					array( 'role' => 'user', 'content' => $user ),
				),
				array(
					'provider'    => $route['provider'],
					'model'       => $route['model'],
					'temperature' => 0.4,
					'max_tokens'  => 500,
				)
			);

			if ( is_wp_error( $result ) ) {
				error_log( '[KH SMMA] AI suggest failed: ' . $result->get_error_message() );
				wp_send_json_error( $result->get_error_message() );
			}

			$content = trim( $result['content'] );
			$content = preg_replace( '/^```(?:json)?[\r\n]+|```[\r\n]*$/', '', $content );
			$content = trim( $content );

			$decoded = json_decode( $content, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $decoded['title'], $decoded['description'] ) ) {
				error_log( '[KH SMMA] AI suggest failed to parse: ' . substr( $content, 0, 200 ) );
				wp_send_json_error( __( 'AI returned invalid response. Please try again.', 'kh-smma' ) );
			}

			$title       = $this->truncate_text( $decoded['title'], 150 );
			$description = $this->truncate_text( $decoded['description'], 300 );

			wp_send_json_success( array(
				'title'       => $title,
				'description' => $description,
				'model'       => $route['model'],
			) );
		} catch ( \Exception $e ) {
			error_log( '[KH SMMA] AI suggest exception: ' . $e->getMessage() );
			wp_send_json_error( __( 'An unexpected error occurred. Check the error log.', 'kh-smma' ) );
		}
	}

	/**
	 * Register REST routes for social preview.
	 */
	public function register_rest_routes() {
		register_rest_route( 'kh-smma/v1', '/social/preview', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_social_preview' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args' => array(
				'post_id'     => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'title'       => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'description' => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
			),
		) );

		register_rest_route( 'kh-smma/v1', '/social/variant-populate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_variant_populate' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args' => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'text'    => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
			),
		) );
	}

	/**
	 * REST handler for social preview.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_social_preview( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found', 'kh-smma' ), array( 'status' => 404 ) );
		}

		$override_title       = $request->get_param( 'title' ) ?: '';
		$override_description = $request->get_param( 'description' ) ?: '';

		$title       = $override_title ?: $this->get_social_title( $post );
		$description = $override_description ?: $this->get_social_description( $post );
		$image       = $this->get_social_image( $post );
		$url         = get_permalink( $post ) ?: home_url( '?p=' . $post_id );

		return rest_ensure_response( array(
			'title'        => $title,
			'description'  => $description,
			'image'        => $image,
			'url'          => $url,
			'preview_html' => $this->generate_card_html( $title, $description, $image, $url ),
			'warnings'     => $this->validate_social_data( $title, $description, $image ),
		) );
	}

	/**
	 * REST handler — auto-populate social fields from a generated variant.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_variant_populate( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$text    = $request->get_param( 'text' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Insufficient permissions', 'kh-smma' ), array( 'status' => 403 ) );
		}

		$words       = explode( ' ', $text );
		$title_words = array_slice( $words, 0, 15 );
		$desc_words  = array_slice( $words, 15 );

		$title       = implode( ' ', $title_words );
		$description = implode( ' ', $desc_words );

		if ( strlen( $title ) > 150 ) {
			$title = substr( $title, 0, 147 ) . '...';
		}

		update_post_meta( $post_id, self::META_PREFIX . '_title', $title );
		update_post_meta( $post_id, self::META_PREFIX . '_description', $description );

		return rest_ensure_response( array(
			'title'       => $title,
			'description' => $description,
			'message'     => __( 'Social fields populated from variant', 'kh-smma' ),
		) );
	}

	// ─── Card HTML Generation ─────────────────────────────────────

	/**
	 * Generate LinkedIn-style card HTML for preview.
	 *
	 * @param string      $title       Card title.
	 * @param string      $description Card description.
	 * @param string|null $image       Image URL.
	 * @param string      $url         Post URL.
	 * @return string HTML.
	 */
	public function generate_card_html( $title, $description, $image, $url ) {
		$domain = wp_parse_url( $url, PHP_URL_HOST );

		$html  = '<div class="kh-smma-social-card" style="max-width:520px;border:1px solid #e1e1e1;border-radius:8px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#fff;">';

		if ( $image ) {
			$html .= '<div class="kh-smma-card-image" style="width:100%;height:272px;background-image:url(\'' . esc_url( $image ) . '\');background-size:cover;background-position:center;background-repeat:no-repeat;"></div>';
		}

		$html .= '<div class="kh-smma-card-content" style="padding:12px 16px;">';
		$html .= '<div class="kh-smma-card-title" style="font-size:16px;font-weight:600;line-height:1.3;color:#000;margin-bottom:4px;">' . esc_html( $this->truncate_text( $title, 150 ) ) . '</div>';
		$html .= '<div class="kh-smma-card-description" style="font-size:14px;line-height:1.4;color:#666;margin-bottom:8px;">' . esc_html( $this->truncate_text( $description, 300 ) ) . '</div>';
		$html .= '<div class="kh-smma-card-domain" style="font-size:13px;color:#999;text-transform:lowercase;">' . esc_html( $domain ) . '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	// ─── Validation ───────────────────────────────────────────────

	/**
	 * Validate social media data and return warnings.
	 *
	 * @param string      $title       Title text.
	 * @param string      $description Description text.
	 * @param string|null $image       Image URL.
	 * @return array Warnings.
	 */
	public function validate_social_data( $title, $description, $image ) {
		$warnings = array();

		if ( empty( $title ) ) {
			$warnings[] = array(
				'type'     => 'missing_title',
				'message'  => __( 'Title is missing — LinkedIn will use the page title instead', 'kh-smma' ),
				'severity' => 'high',
			);
		} elseif ( strlen( $title ) > 150 ) {
			$warnings[] = array(
				'type'     => 'title_too_long',
				'message'  => sprintf( __( 'Title is %d characters — LinkedIn truncates at ~150', 'kh-smma' ), strlen( $title ) ),
				'severity' => 'medium',
			);
		}

		if ( empty( $description ) ) {
			$warnings[] = array(
				'type'     => 'missing_description',
				'message'  => __( 'Description is missing — LinkedIn will use the page excerpt', 'kh-smma' ),
				'severity' => 'medium',
			);
		} elseif ( strlen( $description ) > 300 ) {
			$warnings[] = array(
				'type'     => 'description_too_long',
				'message'  => sprintf( __( 'Description is %d characters — LinkedIn truncates at ~300', 'kh-smma' ), strlen( $description ) ),
				'severity' => 'low',
			);
		}

		if ( empty( $image ) ) {
			$warnings[] = array(
				'type'     => 'missing_image',
				'message'  => __( 'No image set — posts without images get less engagement on LinkedIn', 'kh-smma' ),
				'severity' => 'low',
			);
		}

		return $warnings;
	}

	// ─── Helpers ──────────────────────────────────────────────────

	/**
	 * Truncate text to a character limit.
	 *
	 * @param string $text   Input text.
	 * @param int    $length Max length.
	 * @return string
	 */
	private function truncate_text( $text, $length ) {
		if ( strlen( $text ) <= $length ) {
			return $text;
		}
		return substr( $text, 0, $length - 3 ) . '...';
	}

	// ─── Content Registry Integration ─────────────────────────────

	/**
	 * Transition the content registry entry to Live after a successful social publish.
	 *
	 * @param int   $post_id The WordPress post ID.
	 * @param array $result  The LinkedIn API response data.
	 */
	protected function sync_publish_to_registry( int $post_id, array $result ): void {
		if ( ! class_exists( '\KH\ContentRegistry\Services\ContentRegistryService' ) ) {
			return;
		}

		try {
			$registry  = ContentRegistryService::instance();
			$blog_id   = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
			$title     = get_the_title( $post_id ) ?: ( 'post-' . $post_id );
			$slug      = sanitize_title( $title );

			// Look up the registry article
			$article = $registry->get_article_for_route( $blog_id, $slug );
			if ( ! $article ) {
				$articles = $registry->search_articles( $slug, $blog_id, true );
				$article  = ! empty( $articles ) ? $articles[0] : null;
			}

			if ( ! $article ) {
				return; // No registry entry — nothing to transition
			}

			// Update smma_flags with publish metadata
			$existing_flags = is_array( $article->smma_flags ) ? $article->smma_flags : array();
			$existing_flags['published_at']     = current_time( 'mysql' );
			$existing_flags['platform']         = 'linkedin';
			$existing_flags['platform_post_id'] = $result['id'] ?? null;
			$existing_flags['linkedin_activity'] = $result['activity'] ?? null;

			$flag_result = $registry->update_smma_flags( $article->id, $existing_flags );

			if ( is_wp_error( $flag_result ) ) {
				error_log( '[KHM SMMA] Failed to update publish flags in registry: ' . $flag_result->get_error_message() );
			}

			// Transition to Live
			$transition = $registry->transition_status( $article->id, 'Live' );

			if ( is_wp_error( $transition ) ) {
				error_log( '[KHM SMMA] Failed to transition registry to Live after LinkedIn publish: ' . $transition->get_error_message() );
			}
		} catch ( \Exception $e ) {
			error_log( '[KHM SMMA] Sync publish to registry failed: ' . $e->getMessage() );
		}
	}

	// ─── Asset Enqueue ───────────────────────────────────────────

	/**
	 * Enqueue admin assets for the social meta box.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;
		if ( ! $post || ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ), true ) ) {
			return;
		}

		$js_path  = KH_SMMA_PATH . 'assets/js/social-editor.js';
		$css_path = KH_SMMA_PATH . 'assets/css/social-editor.css';
		$version  = defined( 'KH_SMMA_VERSION' ) ? KH_SMMA_VERSION : '0.3.0';

		wp_enqueue_script(
			'kh-smma-social-editor',
			KH_SMMA_URL . 'assets/js/social-editor.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : $version,
			true
		);

		wp_localize_script( 'kh-smma-social-editor', 'khSmmaSocial', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'kh_smma_social_preview' ),
			'restUrl'    => rest_url( 'kh-smma/v1/social' ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'postId'     => $post->ID,
			'queueStatus'=> get_post_meta( $post->ID, self::QUEUE_STATUS_KEY, true ) ?: '',
			'lastPosted' => get_post_meta( $post->ID, self::META_PREFIX . '_last_posted', true ) ?: '',
			'strings'    => array(
				'saving'            => __( 'Saving...', 'kh-smma' ),
				'saved'             => __( 'Social data saved', 'kh-smma' ),
				'saveError'         => __( 'Save failed', 'kh-smma' ),
				'posting'           => __( 'Posting to LinkedIn...', 'kh-smma' ),
				'posted'            => __( 'Posted to LinkedIn', 'kh-smma' ),
				'postError'         => __( 'LinkedIn publish failed', 'kh-smma' ),
				'queuing'           => __( 'Queuing...', 'kh-smma' ),
				'queued'            => __( 'Queued for later', 'kh-smma' ),
				'queueError'        => __( 'Queue failed', 'kh-smma' ),
				'generating'        => __( 'Generating preview...', 'kh-smma' ),
				'error'             => __( 'Error generating preview', 'kh-smma' ),
				'titleTooLong'      => __( 'Title exceeds recommended 150 characters', 'kh-smma' ),
				'descriptionTooLong'=> __( 'Description exceeds recommended 300 characters', 'kh-smma' ),
				'noImage'           => __( 'No image set', 'kh-smma' ),
				'selectImage'       => __( 'Select Image', 'kh-smma' ),
				'changeImage'       => __( 'Change Image', 'kh-smma' ),
				'removeImage'       => __( 'Remove Image', 'kh-smma' ),
				'populateFromVariant' => __( 'Use as social text', 'kh-smma' ),
				'populated'         => __( 'Social fields populated', 'kh-smma' ),
				'suggesting'        => __( 'AI is generating suggestions...', 'kh-smma' ),
				'suggested'         => __( 'AI suggestions applied', 'kh-smma' ),
				'suggestError'      => __( 'AI suggestion failed. Is the LLM configured?', 'kh-smma' ),
				'lastSaved'         => __( 'Last saved', 'kh-smma' ),
				'notSaved'          => __( 'Not saved', 'kh-smma' ),
			),
		) );

		wp_enqueue_style(
			'kh-smma-social-editor',
			KH_SMMA_URL . 'assets/css/social-editor.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : $version
		);
	}
}
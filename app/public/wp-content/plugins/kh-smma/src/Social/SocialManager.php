<?php
/**
 * Social Media Manager — Unified social tools for KH-SMMA
 *
 * Merges what was previously SocialMediaManager + SocialMediaPreviewManager
 * from khm-seo into a single coherent system under kh-smma.
 *
 * Features:
 * - LinkedIn OG meta tags on wp_head
 * - Single "Social Media" meta box (title, description, live card preview)
 * - REST endpoint for preview generation
 * - Variant auto-populate hook
 * - Works for drafts AND published posts
 *
 * @package KH_SMMA\Social
 * @since 0.2.0
 */

namespace KH_SMMA\Social;

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
	 * Supported platforms.
	 *
	 * @var array
	 */
	private $platforms = array(
		'linkedin' => 'LinkedIn',
	);

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
		// Frontend meta tags
		add_action( 'wp_head', array( $this, 'output_social_meta_tags' ), 10 );

		// Admin meta box
		add_action( 'add_meta_boxes', array( $this, 'add_social_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_social_meta' ) );

		// AJAX for live preview
		add_action( 'wp_ajax_kh_smma_social_preview', array( $this, 'ajax_social_preview' ) );

		// AJAX for AI suggestion
		add_action( 'wp_ajax_kh_smma_social_suggest', array( $this, 'ajax_suggest_social' ) );

		// REST endpoint for block editor / variant pipeline
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	// ─── Frontend Meta Tag Output ────────────────────────────────

	/**
	 * Output social media meta tags in wp_head.
	 */
	public function output_social_meta_tags() {
		global $post;

		if ( ! is_singular() || ! $post ) {
			return;
		}

		$title       = $this->get_social_title( $post );
		$description = $this->get_social_description( $post );
		$image       = $this->get_social_image( $post );
		$url         = get_permalink( $post );

		echo '<!-- KH SMMA Social Meta Tags -->' . "\n";

		// OG tags (used by LinkedIn)
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:type" content="article">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";

		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";

			// Try to get image dimensions
			$image_id = attachment_url_to_postid( $image );
			if ( $image_id ) {
				$image_meta = wp_get_attachment_metadata( $image_id );
				if ( $image_meta && ! empty( $image_meta['width'] ) ) {
					echo '<meta property="og:image:width" content="' . esc_attr( $image_meta['width'] ) . '">' . "\n";
					echo '<meta property="og:image:height" content="' . esc_attr( $image_meta['height'] ) . '">' . "\n";
				}
				$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
				if ( $image_alt ) {
					echo '<meta property="og:image:alt" content="' . esc_attr( $image_alt ) . '">' . "\n";
				}
			}
		}

		// Twitter Card (also used by LinkedIn as fallback)
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
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

		// Fallback to SEO title if khm-seo is active
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

		// Fallback to SEO description
		$seo_desc = get_post_meta( $post->ID, '_khm_seo_description', true );
		if ( ! empty( $seo_desc ) ) {
			return $seo_desc;
		}

		// Fallback to excerpt
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		// Fallback to content excerpt
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

		// Fallback to featured image
		if ( has_post_thumbnail( $post ) ) {
			$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
			if ( $image_data ) {
				return $image_data[0];
			}
		}

		return null;
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
				__( 'Social Media', 'kh-smma' ),
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

		$title       = get_post_meta( $post->ID, self::META_PREFIX . '_title', true );
		$description = get_post_meta( $post->ID, self::META_PREFIX . '_description', true );
		$image_id    = get_post_meta( $post->ID, self::META_PREFIX . '_image', true );

		include KH_SMMA_PATH . 'admin/templates/meta-box-social.php';
	}

	/**
	 * Save social media meta data.
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

		// LinkedIn fields
		$fields = array( 'title', 'description', 'image' );
		foreach ( $fields as $field ) {
			$key   = self::META_PREFIX . "_{$field}";
			$input = $_POST[ 'kh_smma_social_linkedin_' . $field ] ?? '';

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

		// Accept override values from the editor (for live preview before save)
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
	 *
	 * Uses the social_posts agent configured in kh-editorial-intelligence.
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

		// Build context from post
		$post_title   = get_the_title( $post );
		$post_excerpt = $post->post_excerpt ? wp_strip_all_tags( $post->post_excerpt ) : '';
		$post_body    = wp_strip_all_tags( $post->post_content );
		$max_body     = 1500;
		if ( strlen( $post_body ) > $max_body ) {
			$post_body = substr( $post_body, 0, $max_body ) . '...';
		}

		// Check LLM availability
		$llm_available = class_exists( '\\KH\\Editorial\\Core\\LLMService' )
			&& \KH\Editorial\Core\LLMService::is_configured();

		if ( ! $llm_available ) {
			wp_send_json_error( __( 'LLM service is not configured. Please configure an API key in Editorial Settings.', 'kh-smma' ) );
		}

		// Build prompt
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
			// Strip markdown fences if present
			$content = preg_replace( '/^```(?:json)?[\r\n]+|```[\r\n]*$/', '', $content );
			$content = trim( $content );

			$decoded = json_decode( $content, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $decoded['title'], $decoded['description'] ) ) {
				error_log( '[KH SMMA] AI suggest failed to parse: ' . substr( $content, 0, 200 ) );
				wp_send_json_error( __( 'AI returned invalid response. Please try again.', 'kh-smma' ) );
			}

			// Enforce length limits
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
					'required'    => true,
					'type'        => 'integer',
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

		// Use first ~100 chars as title, rest as description
		$words = explode( ' ', $text );
		$title_words = array_slice( $words, 0, 15 );
		$desc_words  = array_slice( $words, 15 );

		$title = implode( ' ', $title_words );
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
		$version  = defined( 'KH_SMMA_VERSION' ) ? KH_SMMA_VERSION : '0.2.0';

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
			'strings'    => array(
				'generating'        => __( 'Generating preview...', 'kh-smma' ),
				'error'             => __( 'Error generating preview', 'kh-smma' ),
				'titleTooLong'      => __( 'Title exceeds recommended 150 characters', 'kh-smma' ),
				'descriptionTooLong' => __( 'Description exceeds recommended 300 characters', 'kh-smma' ),
				'noImage'           => __( 'No image set', 'kh-smma' ),
				'populateFromVariant' => __( 'Use as social text', 'kh-smma' ),
				'populated'         => __( 'Social fields populated', 'kh-smma' ),
				'suggesting'        => __( 'AI is generating suggestions...', 'kh-smma' ),
				'suggested'         => __( 'AI suggestions applied', 'kh-smma' ),
				'suggestError'      => __( 'AI suggestion failed. Is the LLM configured?', 'kh-smma' ),
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
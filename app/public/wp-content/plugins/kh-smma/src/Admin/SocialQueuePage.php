<?php
/**
 * Social Queue Admin Page
 *
 * Surfaces queued LinkedIn posts for review and publishing.
 * Lists all posts with _kh_smma_social_queue_status = 'pending'.
 *
 * @package KH_SMMA\Admin
 * @since 0.3.0
 */

namespace KH_SMMA\Admin;

use KH_SMMA\Social\SocialManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SocialQueuePage {

	/**
	 * Register the admin page.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_kh_smma_publish_queued', array( $this, 'handle_publish_action' ) );
		add_action( 'admin_post_kh_smma_remove_queued', array( $this, 'handle_remove_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add submenu page under SMMA.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=kh_social_schedule',
			__( 'Social Queue', 'kh-smma' ),
			__( 'Social Queue', 'kh-smma' ),
			'edit_posts',
			'kh-smma-social-queue',
			array( $this, 'render_page' ),
			30
		);
	}

	/**
	 * Handle publish action from queue.
	 */
	public function handle_publish_action() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions.', 'kh-smma' ) );
		}

		$post_id = (int) ( $_GET['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_die( __( 'Invalid post ID.', 'kh-smma' ) );
		}

		check_admin_referer( 'kh_smma_publish_' . $post_id, '_wpnonce' );

		$social = new SocialManager();
		$result = $social->publish_to_linkedin( $post_id );

		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', function () use ( $result ) {
				printf(
					'<div class="notice notice-error"><p>%s: %s</p></div>',
					esc_html__( 'LinkedIn publish failed', 'kh-smma' ),
					esc_html( $result->get_error_message() )
				);
			} );
		} else {
			$now = current_time( 'timestamp' );
			update_post_meta( $post_id, SocialManager::META_PREFIX . '_last_posted', $now );
			update_post_meta( $post_id, SocialManager::QUEUE_STATUS_KEY, 'published' );
			update_post_meta( $post_id, SocialManager::META_PREFIX . '_last_response', $result );

			add_action( 'admin_notices', function () use ( $post_id ) {
				$title = get_the_title( $post_id );
				printf(
					'<div class="notice notice-success"><p>%s: "%s"</p></div>',
					esc_html__( 'Posted to LinkedIn', 'kh-smma' ),
					esc_html( $title ?: 'Post #' . $post_id )
				);
			} );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=kh_social_schedule&page=kh-smma-social-queue' ) );
		exit;
	}

	/**
	 * Handle remove action (un-queue).
	 */
	public function handle_remove_action() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions.', 'kh-smma' ) );
		}

		$post_id = (int) ( $_GET['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_die( __( 'Invalid post ID.', 'kh-smma' ) );
		}

		check_admin_referer( 'kh_smma_remove_' . $post_id, '_wpnonce' );

		delete_post_meta( $post_id, SocialManager::QUEUE_STATUS_KEY );
		delete_post_meta( $post_id, SocialManager::META_PREFIX . '_queued_at' );

		wp_safe_redirect( admin_url( 'edit.php?post_type=kh_social_schedule&page=kh-smma-social-queue' ) );
		exit;
	}

	/**
	 * Enqueue admin assets for the queue page.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'kh-smma-social-queue' ) === false ) {
			return;
		}

		$version = defined( 'KH_SMMA_VERSION' ) ? KH_SMMA_VERSION : '0.3.0';

		wp_enqueue_style(
			'kh-smma-social-queue',
			KH_SMMA_URL . 'assets/css/social-editor.css',
			array(),
			$version
		);
	}

	/**
	 * Render the Social Queue admin page.
	 */
	public function render_page() {
		global $wpdb;

		$meta_key_title       = SocialManager::META_PREFIX . '_title';
		$meta_key_description = SocialManager::META_PREFIX . '_description';
		$meta_key_image       = SocialManager::META_PREFIX . '_image';
		$meta_key_queued      = SocialManager::META_PREFIX . '_queued_at';
		$queue_status_key     = SocialManager::QUEUE_STATUS_KEY;

		// Query posts with pending queue status
		$queued_posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_status, p.post_type, p.post_date,
			        pm_q.meta_value AS queued_at,
			        pm_t.meta_value AS linkedin_title,
			        pm_d.meta_value AS linkedin_description,
			        pm_i.meta_value AS linkedin_image
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_q ON p.ID = pm_q.post_id AND pm_q.meta_key = %s AND pm_q.meta_value = 'pending'
			 LEFT JOIN {$wpdb->postmeta} pm_t ON p.ID = pm_t.post_id AND pm_t.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} pm_d ON p.ID = pm_d.post_id AND pm_d.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} pm_i ON p.ID = pm_i.post_id AND pm_i.meta_key = %s
			 ORDER BY pm_q.meta_value DESC",
			$queue_status_key,
			$meta_key_title,
			$meta_key_description,
			$meta_key_image
		) );

		// Also show recently published posts
		$published_posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_status, p.post_type, p.post_date,
			        pm_lp.meta_value AS last_posted,
			        pm_t.meta_value AS linkedin_title,
			        pm_d.meta_value AS linkedin_description,
			        pm_i.meta_value AS linkedin_image
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_q ON p.ID = pm_q.post_id AND pm_q.meta_key = %s AND pm_q.meta_value = 'published'
			 LEFT JOIN {$wpdb->postmeta} pm_lp ON p.ID = pm_lp.post_id AND pm_lp.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} pm_t ON p.ID = pm_t.post_id AND pm_t.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} pm_d ON p.ID = pm_d.post_id AND pm_d.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} pm_i ON p.ID = pm_i.post_id AND pm_i.meta_key = %s
			 ORDER BY pm_lp.meta_value DESC
			 LIMIT 20",
			$queue_status_key,
			SocialManager::META_PREFIX . '_last_posted',
			$meta_key_title,
			$meta_key_description,
			$meta_key_image
		) );

		?>
		<div class="wrap" id="kh-smma-social-queue-page">
			<h1><?php esc_html_e( 'Social Queue', 'kh-smma' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Review and publish LinkedIn posts queued from the post editor. Posts are saved with "Save for Later" in the Social Media meta box.', 'kh-smma' ); ?>
			</p>

			<?php if ( ! empty( $queued_posts ) ) : ?>
				<h2><?php esc_html_e( 'Pending Queue', 'kh-smma' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:25%;"><?php esc_html_e( 'Post', 'kh-smma' ); ?></th>
							<th style="width:25%;"><?php esc_html_e( 'LinkedIn Headline', 'kh-smma' ); ?></th>
							<th style="width:20%;"><?php esc_html_e( 'Description', 'kh-smma' ); ?></th>
							<th style="width:12%;"><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
							<th style="width:8%;"><?php esc_html_e( 'Image', 'kh-smma' ); ?></th>
							<th style="width:10%;"><?php esc_html_e( 'Actions', 'kh-smma' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $queued_posts as $qp ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( get_the_title( $qp->ID ) ?: __( '(no title)', 'kh-smma' ) ); ?></strong>
									<br><small>
										<?php
										printf(
											'%s | %s',
											esc_html( $qp->post_type ),
											esc_html( $qp->post_status )
										);
										?>
									</small>
									<?php if ( $qp->queued_at ) : ?>
										<br><small><?php echo esc_html( human_time_diff( (int) $qp->queued_at, current_time( 'timestamp' ) ) . ' ago' ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $qp->linkedin_title ?: get_the_title( $qp->ID ) ); ?></td>
								<td>
									<?php
									$desc = $qp->linkedin_description;
									if ( ! empty( $desc ) ) {
										echo esc_html( strlen( $desc ) > 120 ? substr( $desc, 0, 120 ) . '...' : $desc );
									} else {
										echo '<em>' . esc_html__( 'No description', 'kh-smma' ) . '</em>';
									}
									?>
								</td>
								<td>
									<span class="dashicons dashicons-clock" style="color:#dba617;vertical-align:middle;"></span>
									<?php esc_html_e( 'Pending', 'kh-smma' ); ?>
								</td>
								<td>
									<?php if ( $qp->linkedin_image ) : ?>
										<?php echo wp_get_attachment_image( (int) $qp->linkedin_image, 'thumbnail', false, array( 'style' => 'max-width:50px;max-height:50px;' ) ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-format-image" style="color:#ccc;"></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$publish_url = wp_nonce_url(
										admin_url( 'admin-post.php?action=kh_smma_publish_queued&post_id=' . $qp->ID ),
										'kh_smma_publish_' . $qp->ID
									);
									$remove_url = wp_nonce_url(
										admin_url( 'admin-post.php?action=kh_smma_remove_queued&post_id=' . $qp->ID ),
										'kh_smma_remove_' . $qp->ID
									);
									$edit_url = get_edit_post_link( $qp->ID );
									?>
									<a href="<?php echo esc_url( $publish_url ); ?>" class="button button-primary button-small" onclick="return confirm('<?php esc_attr_e( 'Publish this post to LinkedIn now?', 'kh-smma' ); ?>');">
										<?php esc_html_e( 'Publish Now', 'kh-smma' ); ?>
									</a>
									<br><br>
									<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'kh-smma' ); ?>
									</a>
									<a href="<?php echo esc_url( $remove_url ); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('<?php esc_attr_e( 'Remove from queue?', 'kh-smma' ); ?>');">
										<?php esc_html_e( 'Remove', 'kh-smma' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No queued social posts. Use "Save for Later" in the post editor Social Media meta box to queue posts for review.', 'kh-smma' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $published_posts ) ) : ?>
				<h2 style="margin-top:30px;"><?php esc_html_e( 'Recently Published', 'kh-smma' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:30%;"><?php esc_html_e( 'Post', 'kh-smma' ); ?></th>
							<th style="width:30%;"><?php esc_html_e( 'LinkedIn Headline', 'kh-smma' ); ?></th>
							<th style="width:15%;"><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
							<th style="width:15%;"><?php esc_html_e( 'Posted', 'kh-smma' ); ?></th>
							<th style="width:10%;"><?php esc_html_e( 'Actions', 'kh-smma' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $published_posts as $pp ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( get_the_title( $pp->ID ) ?: __( '(no title)', 'kh-smma' ) ); ?></strong>
									<br><small><?php echo esc_html( $pp->post_type ); ?></small>
								</td>
								<td><?php echo esc_html( $pp->linkedin_title ?: get_the_title( $pp->ID ) ); ?></td>
								<td>
									<span class="dashicons dashicons-yes-alt" style="color:#008a20;vertical-align:middle;"></span>
									<?php esc_html_e( 'Published', 'kh-smma' ); ?>
								</td>
								<td>
									<?php
									if ( $pp->last_posted ) {
										echo esc_html( human_time_diff( (int) $pp->last_posted, current_time( 'timestamp' ) ) . ' ago' );
									} else {
										esc_html_e( 'Unknown', 'kh-smma' );
									}
									?>
								</td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $pp->ID ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'kh-smma' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<div style="margin-top:20px;padding:15px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:4px;">
				<h3><?php esc_html_e( 'Workflow', 'kh-smma' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Edit a post and fill in LinkedIn title, description, and image in the Social Media meta box.', 'kh-smma' ); ?></li>
					<li><?php esc_html_e( 'Click "Save for Later" to queue it here.', 'kh-smma' ); ?></li>
					<li><?php esc_html_e( 'Review the queue and click "Publish Now" when ready.', 'kh-smma' ); ?></li>
					<li><?php esc_html_e( 'LinkedIn credentials must be configured in Editorial Studio → API Settings.', 'kh-smma' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}
}
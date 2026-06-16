<?php
/**
 * Social Media Meta Box Template
 *
 * Active LinkedIn publishing composer with:
 * - Title / description / image fields
 * - Live card preview
 * - AI suggest
 * - Save Social Data button
 * - Post to LinkedIn Now button
 * - Save for Later (Queue) button
 * - Status indicator (saved / queued / published)
 *
 * Available variables:
 * @var \WP_Post $post          Current post object.
 * @var string   $title         LinkedIn custom title.
 * @var string   $description   LinkedIn custom description.
 * @var string   $image_id      LinkedIn custom image attachment ID.
 * @var string   $queue_status  Queue status (pending / published / '').
 * @var string   $last_posted   Timestamp of last LinkedIn publish.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="kh-smma-social-meta-box" class="kh-smma-social-meta-box" style="padding:12px;">

	<!-- Status Bar -->
	<div id="kh-smma-status-bar" class="kh-smma-status-bar" style="margin-bottom:15px;padding:8px 12px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:4px;display:none;">
		<span id="kh-smma-status-icon" class="dashicons" style="vertical-align:middle;"></span>
		<span id="kh-smma-status-text"></span>
	</div>

	<!-- LinkedIn Fields -->
	<div class="kh-smma-social-section">
		<h4><?php esc_html_e( 'LinkedIn Sharing', 'kh-smma' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Customize how this post appears on LinkedIn. Save to persist, then Post Now or Save for Later.', 'kh-smma' ); ?>
		</p>

		<div class="kh-smma-field">
			<label for="kh_smma_social_linkedin_title">
				<?php esc_html_e( 'Title', 'kh-smma' ); ?>
				<span class="kh-smma-char-count" id="kh-smma-title-count"></span>
			</label>
			<input
				type="text"
				id="kh_smma_social_linkedin_title"
				name="kh_smma_social_linkedin_title"
				class="widefat"
				value="<?php echo esc_attr( $title ); ?>"
				maxlength="200"
				placeholder="<?php esc_attr_e( 'Custom LinkedIn title (recommended: 150 characters)', 'kh-smma' ); ?>"
			/>
		</div>

		<div class="kh-smma-field">
			<label for="kh_smma_social_linkedin_description">
				<?php esc_html_e( 'Description', 'kh-smma' ); ?>
				<span class="kh-smma-char-count" id="kh-smma-desc-count"></span>
			</label>
			<textarea
				id="kh_smma_social_linkedin_description"
				name="kh_smma_social_linkedin_description"
				class="widefat"
				rows="3"
				maxlength="400"
				placeholder="<?php esc_attr_e( 'Custom LinkedIn description (recommended: 300 characters)', 'kh-smma' ); ?>"
			><?php echo esc_textarea( $description ); ?></textarea>
		</div>

		<div class="kh-smma-field">
			<label for="kh_smma_social_linkedin_image">
				<?php esc_html_e( 'Image', 'kh-smma' ); ?>
			</label>
			<input type="hidden"
				id="kh_smma_social_linkedin_image"
				name="kh_smma_social_linkedin_image"
				value="<?php echo esc_attr( $image_id ); ?>"
			/>
			<div id="kh-smma-social-image-preview" class="kh-smma-image-preview">
				<?php if ( $image_id ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:300px;' ) ); ?>
				<?php endif; ?>
			</div>
			<button type="button" class="button" id="kh-smma-select-social-image">
				<?php echo $image_id ? esc_html__( 'Change Image', 'kh-smma' ) : esc_html__( 'Select Image', 'kh-smma' ); ?>
			</button>
			<?php if ( $image_id ) : ?>
				<button type="button" class="button" id="kh-smma-remove-social-image">
					<?php esc_html_e( 'Remove Image', 'kh-smma' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Live Preview -->
	<div class="kh-smma-social-section">
		<h4><?php esc_html_e( 'Live Preview', 'kh-smma' ); ?></h4>

		<div class="kh-smma-social-actions">
			<button type="button" class="button button-secondary" id="kh-smma-suggest-ai">
				<?php esc_html_e( 'Suggest with AI', 'kh-smma' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="kh-smma-refresh-preview">
				<?php esc_html_e( 'Refresh Preview', 'kh-smma' ); ?>
			</button>
		</div>

		<!-- Preview Card -->
		<div id="kh-smma-preview-container" class="kh-smma-preview-container">
			<div class="kh-smma-preview-placeholder">
				<?php esc_html_e( 'Click "Refresh Preview" to see how this post will look on LinkedIn.', 'kh-smma' ); ?>
			</div>
		</div>

		<!-- Warnings -->
		<div id="kh-smma-preview-warnings" class="kh-smma-preview-warnings" style="display:none;"></div>
	</div>

	<!-- Action Buttons -->
	<div class="kh-smma-social-section" style="border-bottom:none;padding-top:5px;">
		<div class="kh-smma-action-buttons" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
			<button type="button" class="button button-primary" id="kh-smma-save-social">
				<?php esc_html_e( 'Save Social Data', 'kh-smma' ); ?>
			</button>
			<button type="button" class="button" id="kh-smma-post-now" style="background:#0073b0;color:#fff;border-color:#005a87;">
				<?php esc_html_e( 'Post to LinkedIn Now', 'kh-smma' ); ?>
			</button>
			<button type="button" class="button" id="kh-smma-queue-later">
				<?php esc_html_e( 'Save for Later', 'kh-smma' ); ?>
			</button>
		</div>
		<?php if ( ! empty( $queue_status ) ) : ?>
			<div id="kh-smma-current-status" style="margin-top:10px;font-size:13px;color:#555;">
				<?php if ( 'published' === $queue_status ) : ?>
					<span class="dashicons dashicons-yes-alt" style="color:#008a20;vertical-align:middle;"></span>
					<?php printf( __( 'Posted to LinkedIn %s', 'kh-smma' ), human_time_diff( (int) $last_posted, current_time( 'timestamp' ) ) . ' ago' ); ?>
				<?php elseif ( 'pending' === $queue_status ) : ?>
					<span class="dashicons dashicons-clock" style="color:#dba617;vertical-align:middle;"></span>
					<?php esc_html_e( 'Queued for later — review in SMMA → Social Queue.', 'kh-smma' ); ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

</div>

<style>
.kh-smma-social-meta-box {
	background: #fff;
	padding: 12px;
}



.kh-smma-social-section {
	margin-bottom: 20px;
	padding-bottom: 15px;
	border-bottom: 1px solid #f0f0f0;
}

.kh-smma-social-section:last-child {
	border-bottom: none;
	margin-bottom: 0;
}

.kh-smma-social-section h4 {
	margin: 0 0 8px;
	font-size: 14px;
}

.kh-smma-field {
	margin-bottom: 12px;
}

.kh-smma-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 4px;
}

.kh-smma-field .description {
	margin: 2px 0 0;
}

.kh-smma-char-count {
	font-weight: normal;
	color: #888;
	font-size: 12px;
	margin-left: 8px;
}

.kh-smma-char-count.warning {
	color: #dba617;
}

.kh-smma-char-count.over {
	color: #d63638;
}

.kh-smma-image-preview {
	margin: 8px 0;
}

.kh-smma-social-actions {
	display: flex;
	gap: 10px;
	margin-bottom: 12px;
}

.kh-smma-preview-container {
	margin-top: 10px;
}

.kh-smma-preview-placeholder {
	padding: 20px;
	background: #f8f9fa;
	border: 1px dashed #ccc;
	text-align: center;
	color: #888;
	border-radius: 4px;
}

.kh-smma-preview-warnings {
	margin-top: 10px;
}

.kh-smma-warning {
	background: #fff3cd;
	border-left: 4px solid #dba617;
	padding: 8px 12px;
	margin-bottom: 6px;
	border-radius: 2px;
	font-size: 13px;
}

.kh-smma-warning.high {
	background: #f8d7da;
	border-left-color: #d63638;
}

.kh-smma-warning.low {
	background: #e8f5e9;
	border-left-color: #4caf50;
}

/* Status bar variants */
.kh-smma-status-bar.saved {
	background: #ecf7ed;
	border-color: #a7d6a9;
}
.kh-smma-status-bar.posted {
	background: #e8f5e9;
	border-color: #81c784;
}
.kh-smma-status-bar.queued {
	background: #fff8e1;
	border-color: #ffe082;
}
.kh-smma-status-bar.error {
	background: #ffebee;
	border-color: #ef9a9a;
}
</style>
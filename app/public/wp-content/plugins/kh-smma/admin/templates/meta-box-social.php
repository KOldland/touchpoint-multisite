<?php
/**
 * Social Media Meta Box Template
 *
 * Single unified meta box with:
 * - LinkedIn title/description fields
 * - Live card preview
 * - Character counters + validation warnings
 *
 * Available variables:
 * @var \WP_Post $post          Current post object.
 * @var string   $title         LinkedIn custom title.
 * @var string   $description   LinkedIn custom description.
 * @var string   $image_id      LinkedIn custom image attachment ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="kh-smma-social-meta-box" class="kh-smma-social-meta-box" style="padding:12px;">

	<!-- LinkedIn Fields -->
	<div class="kh-smma-social-section">
		<h4><?php esc_html_e( 'LinkedIn Sharing', 'kh-smma' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Customize how this post appears when shared on LinkedIn. Leave blank to use the default title and excerpt.', 'kh-smma' ); ?>
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
				<span class="dashicons dashicons-superhero"></span>
				<?php esc_html_e( 'Suggest with AI', 'kh-smma' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="kh-smma-refresh-preview">
				<span class="dashicons dashicons-visibility"></span>
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

</div>

<style>
.kh-smma-social-meta-box {
	background: #fff;
	padding: 12px;
}

#kh-smma-suggest-ai {
	margin-right: 6px;
}

#kh-smma-suggest-ai .dashicons {
	margin-right: 2px;
	vertical-align: middle;
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
</style>
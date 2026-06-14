/**
 * KH SMMA Social Editor JS
 *
 * Handles the "Social Media" meta box:
 * - Live LinkedIn card preview
 * - Character counters
 * - Variant auto-populate listener (from editor-generate.js)
 *
 * @package KH_SMMA
 * @since 0.2.0
 */

(function ($) {
	'use strict';

	var SocialEditor = {

		/**
		 * Initialize.
		 */
		init: function () {
			if (typeof khSmmaSocial === 'undefined') {
				console.error('KH SMMA: khSmmaSocial not localized — social-editor.js will not function');
				return;
			}

			this.bindEvents();
			this.initCharCounters();
			console.log('KH SMMA: Social editor initialized for post ID', khSmmaSocial.postId);
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			var self = this;

			// Suggest with AI button
			$('#kh-smma-suggest-ai').on('click', function () {
				self.suggestWithAI();
			});

			// Refresh preview button
			$('#kh-smma-refresh-preview').on('click', function () {
				self.fetchPreview();
			});

			// Live preview on input change (debounced)
			var debounceTimer;
			$('#kh_smma_social_linkedin_title, #kh_smma_social_linkedin_description').on('input', function () {
				self.updateCharCounters();
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function () {
					self.fetchPreview();
				}, 800);
			});

			// Image selection via WordPress media library
			$('#kh-smma-select-social-image').on('click', function (e) {
				e.preventDefault();
				self.openMediaLibrary();
			});

			// Remove image
			$(document).on('click', '#kh-smma-remove-social-image', function (e) {
				e.preventDefault();
				$('#kh_smma_social_linkedin_image').val('');
				$('#kh-smma-social-image-preview').html('');
				$('#kh-smma-select-social-image').text(khSmmaSocial.strings.selectImage || 'Select Image');
				$(this).remove();
				self.fetchPreview();
			});

			// Listen for variant auto-populate event (dispatched by editor-generate.js)
			$(document).on('smma:social.populate', function (e, data) {
				self.populateFromVariant(data.text, data.button);
			});
		},

		/**
		 * Initialize character counters.
		 */
		initCharCounters: function () {
			this.updateCharCounters();
		},

		/**
		 * Update character count display.
		 */
		updateCharCounters: function () {
			var titleLen = ($('#kh_smma_social_linkedin_title').val() || '').length;
			var descLen = ($('#kh_smma_social_linkedin_description').val() || '').length;

			var $titleCount = $('#kh-smma-title-count');
			var $descCount = $('#kh-smma-desc-count');

			$titleCount.text('(' + titleLen + '/150)');
			$descCount.text('(' + descLen + '/300)');

			$titleCount.removeClass('warning over');
			$descCount.removeClass('warning over');

			if (titleLen > 150) $titleCount.addClass('over');
			else if (titleLen > 120) $titleCount.addClass('warning');

			if (descLen > 300) $descCount.addClass('over');
			else if (descLen > 250) $descCount.addClass('warning');
		},

		/**
		 * Fetch live preview from server.
		 */
		fetchPreview: function () {
			var $container = $('#kh-smma-preview-container');
			var $warnings = $('#kh-smma-preview-warnings');
			var $btn = $('#kh-smma-refresh-preview');

			$btn.prop('disabled', true);
			$container.html('<div class="kh-smma-preview-placeholder">' + (khSmmaSocial.strings.generating || 'Generating preview...') + '</div>');

			$.post(khSmmaSocial.ajaxUrl, {
				action: 'kh_smma_social_preview',
				nonce: khSmmaSocial.nonce,
				post_id: khSmmaSocial.postId,
				title: $('#kh_smma_social_linkedin_title').val() || '',
				description: $('#kh_smma_social_linkedin_description').val() || ''
			})
			.done(function (response) {
				if (response.success && response.data) {
					$container.html(response.data.preview_html);
					SocialEditor.renderWarnings(response.data.warnings);
				} else {
					$container.html('<div class="kh-smma-preview-placeholder" style="color:#d63638;">' + (response.data || 'Error generating preview') + '</div>');
					$warnings.hide();
				}
			})
			.fail(function () {
				$container.html('<div class="kh-smma-preview-placeholder" style="color:#d63638;">Network error</div>');
				$warnings.hide();
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
		},

		/**
		 * Render validation warnings.
		 *
		 * @param {Array} warnings List of warning objects.
		 */
		renderWarnings: function (warnings) {
			var $warnings = $('#kh-smma-preview-warnings');
			$warnings.empty();

			if (!warnings || !warnings.length) {
				$warnings.hide();
				return;
			}

			$.each(warnings, function (i, w) {
				$warnings.append(
					'<div class="kh-smma-warning ' + (w.severity || 'medium') + '">' +
					'<strong>' + (w.severity === 'high' ? '[!] ' : w.severity === 'low' ? '[i] ' : '') + '</strong>' +
					(w.message || '') +
					'</div>'
				);
			});

			$warnings.show();
		},

		/**
		 * Open WordPress media library to select/upload an image.
		 */
		openMediaLibrary: function () {
			var self = this;

			// If the media frame already exists, reopen it.
			if (this._mediaFrame) {
				this._mediaFrame.open();
				return;
			}

			// Create a new media frame
			this._mediaFrame = wp.media({
				title: 'Select Social Sharing Image',
				button: { text: 'Use this image' },
				library: { type: 'image' },
				multiple: false
			});

			this._mediaFrame.on('select', function () {
				var attachment = self._mediaFrame.state().get('selection').first().toJSON();
				$('#kh_smma_social_linkedin_image').val(attachment.id);
				$('#kh-smma-social-image-preview').html(
					'<img src="' + (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) +
					'" style="max-width:300px;" alt="" />'
				);
				$('#kh-smma-select-social-image').text('Change Image');

				// Ensure remove button exists
				if (!$('#kh-smma-remove-social-image').length) {
					$('#kh-smma-select-social-image').after(
						' <button type="button" class="button" id="kh-smma-remove-social-image">Remove Image</button>'
					);
				}

				self.fetchPreview();
			});

			this._mediaFrame.open();
		},

		/**
		 * Trigger AI suggestion for title and description.
		 */
		suggestWithAI: function () {
			var self = this;
			var $btn = $('#kh-smma-suggest-ai');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(khSmmaSocial.strings.suggesting || 'AI is generating...');

			$.post(khSmmaSocial.ajaxUrl, {
				action: 'kh_smma_social_suggest',
				nonce: khSmmaSocial.nonce,
				post_id: khSmmaSocial.postId
			})
			.done(function (response) {
				if (response.success && response.data) {
					// Populate fields
					$('#kh_smma_social_linkedin_title').val(response.data.title || '');
					$('#kh_smma_social_linkedin_description').val(response.data.description || '');
					self.updateCharCounters();
					self.fetchPreview();

					$btn.text(khSmmaSocial.strings.suggested || 'AI suggestions applied');
					setTimeout(function () {
						$btn.text(originalText).prop('disabled', false);
					}, 3000);
				} else {
					var msg = (response.data && response.data.message) || response.data || khSmmaSocial.strings.suggestError;
					$btn.text(msg).prop('disabled', false);
					setTimeout(function () {
						$btn.text(originalText);
					}, 5000);
				}
			})
			.fail(function (xhr) {
				console.error('KH SMMA: AI suggest failed', xhr.responseText);
				$btn.text(khSmmaSocial.strings.suggestError || 'AI suggestion failed').prop('disabled', false);
				setTimeout(function () {
					$btn.text(originalText);
				}, 5000);
			});
		},

		/**
		 * Populate social fields from a generated variant.
		 *
		 * Called via CustomEvent 'smma:social.populate' from editor-generate.js.
		 *
		 * @param {string} text   Variant text.
		 * @param {Element} button The button element that was clicked (to update its text).
		 */
		populateFromVariant: function (text, button) {
			var self = this;

			$.ajax({
				url: khSmmaSocial.restUrl + '/variant-populate',
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', khSmmaSocial.restNonce);
				},
				data: {
					post_id: khSmmaSocial.postId,
					text: text
				}
			})
			.done(function (response) {
				if (response.title) {
					$('#kh_smma_social_linkedin_title').val(response.title);
				}
				if (response.description) {
					$('#kh_smma_social_linkedin_description').val(response.description);
				}
				self.updateCharCounters();
				self.fetchPreview();

				if (button) {
					var $btn = $(button);
					$btn.text(khSmmaSocial.strings.populated || 'Populated');
					$btn.prop('disabled', true);
					setTimeout(function () {
						$btn.text(khSmmaSocial.strings.populateFromVariant || 'Use as social text')
							.prop('disabled', false);
					}, 3000);
				}
			})
			.fail(function (xhr) {
				console.error('KH SMMA: Variant populate failed', xhr.responseText);
				if (button) {
					$(button).text('Error — try again');
				}
			});
		}
	};

	// Initialize on DOM ready
	$(document).ready(function () {
		SocialEditor.init();
	});

	// Expose for external use
	window.KHSMMASocialEditor = SocialEditor;

})(jQuery);
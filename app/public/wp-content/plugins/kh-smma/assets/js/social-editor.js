/**
 * KH SMMA Social Editor JS
 *
 * Handles the "Social Media" meta box:
 * - Live LinkedIn card preview
 * - Character counters
 * - Save Social Data button
 * - Post to LinkedIn Now button
 * - Save for Later (Queue) button
 * - Status indicator bar
 * - AI suggest
 * - Variant auto-populate listener (from editor-generate.js)
 *
 * @package KH_SMMA
 * @since 0.3.0
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
			this.restoreStatus();
			console.log('KH SMMA: Social editor initialized for post ID', khSmmaSocial.postId);
		},

		/**
		 * Restore existing status on page load.
		 */
		restoreStatus: function () {
			if (khSmmaSocial.queueStatus === 'published') {
				this.showStatus('posted', 'Posted to LinkedIn' + (khSmmaSocial.lastPosted ? ' ' + khSmmaSocial.lastPosted : ''));
			} else if (khSmmaSocial.queueStatus === 'pending') {
				this.showStatus('queued', 'Queued for later — review in SMMA → Social Queue.');
			}
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			var self = this;

			// Save button
			$('#kh-smma-save-social').on('click', function () {
				self.saveSocialData();
			});

			// Post Now button
			$('#kh-smma-post-now').on('click', function () {
				self.postNow();
			});

			// Queue for later button
			$('#kh-smma-queue-later').on('click', function () {
				self.queueLater();
			});

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
		 * Show the status bar with a message.
		 *
		 * @param {string} type    Status type: saved / posted / queued / error
		 * @param {string} message Status message text
		 * @param {number} timeout Auto-hide timeout in ms (0 = persist)
		 */
		showStatus: function (type, message, timeout) {
			var $bar = $('#kh-smma-status-bar');
			var $icon = $('#kh-smma-status-icon');
			var $text = $('#kh-smma-status-text');

			$bar.removeClass('saved posted queued error');

			switch (type) {
				case 'saved':
					$bar.addClass('saved');
					$icon.attr('class', 'dashicons dashicons-yes');
					break;
				case 'posted':
					$bar.addClass('posted');
					$icon.attr('class', 'dashicons dashicons-yes-alt');
					break;
				case 'queued':
					$bar.addClass('queued');
					$icon.attr('class', 'dashicons dashicons-clock');
					break;
				case 'error':
					$bar.addClass('error');
					$icon.attr('class', 'dashicons dashicons-warning');
					break;
				default:
					$icon.attr('class', 'dashicons dashicons-info');
			}

			$text.text(message);
			$bar.show();

			if (timeout && timeout > 0) {
				setTimeout(function () {
					$bar.fadeOut(500);
				}, timeout);
			}
		},

		/**
		 * Hide the status bar.
		 */
		hideStatus: function () {
			$('#kh-smma-status-bar').hide();
		},

		/**
		 * Collect current field values.
		 *
		 * @returns {{ post_id: number, kh_smma_social_linkedin_title: string, kh_smma_social_linkedin_description: string, kh_smma_social_linkedin_image: string }}
		 */
		getFieldData: function () {
			return {
				post_id: khSmmaSocial.postId,
				nonce: khSmmaSocial.nonce,
				kh_smma_social_linkedin_title: $('#kh_smma_social_linkedin_title').val() || '',
				kh_smma_social_linkedin_description: $('#kh_smma_social_linkedin_description').val() || '',
				kh_smma_social_linkedin_image: $('#kh_smma_social_linkedin_image').val() || ''
			};
		},

		/**
		 * Save social data to post meta.
		 */
		saveSocialData: function () {
			var self = this;
			var $btn = $('#kh-smma-save-social');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(khSmmaSocial.strings.saving || 'Saving...');
			self.hideStatus();

			$.post(khSmmaSocial.ajaxUrl, $.extend(self.getFieldData(), {
				action: 'kh_smma_save_social'
			}))
			.done(function (response) {
				if (response.success) {
					self.showStatus('saved', khSmmaSocial.strings.saved || 'Social data saved', 5000);
					// Update the "Last saved" indicator in the UI if present
					if (response.data && response.data.human) {
						self.showStatus('saved', (khSmmaSocial.strings.lastSaved || 'Last saved') + ': ' + response.data.human, 5000);
					}
				} else {
					self.showStatus('error', (response.data && response.data.message) || khSmmaSocial.strings.saveError || 'Save failed', 8000);
				}
			})
			.fail(function (xhr) {
				console.error('KH SMMA: Save failed', xhr.responseText);
				self.showStatus('error', khSmmaSocial.strings.saveError || 'Save failed', 8000);
			})
			.always(function () {
				$btn.text(originalText).prop('disabled', false);
			});
		},

		/**
		 * Post to LinkedIn immediately.
		 */
		postNow: function () {
			var self = this;
			var $btn = $('#kh-smma-post-now');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(khSmmaSocial.strings.posting || 'Posting to LinkedIn...');
			self.hideStatus();

			$.post(khSmmaSocial.ajaxUrl, $.extend(self.getFieldData(), {
				action: 'kh_smma_post_now'
			}))
			.done(function (response) {
				if (response.success) {
					self.showStatus('posted', khSmmaSocial.strings.posted || 'Posted to LinkedIn', 0);
					// Update current-status section
					$('#kh-smma-current-status').remove();
					var $statusDiv = $('<div id="kh-smma-current-status" style="margin-top:10px;font-size:13px;color:#555;">' +
						'<span class="dashicons dashicons-yes-alt" style="color:#008a20;vertical-align:middle;"></span> ' +
						(khSmmaSocial.strings.posted || 'Posted to LinkedIn') +
						(response.data && response.data.human ? ' ' + response.data.human : '') +
						'</div>');
					$('#kh-smma-queue-later').parent().after($statusDiv);
				} else {
					var errMsg = (response.data && response.data.message) || khSmmaSocial.strings.postError || 'LinkedIn publish failed';
					self.showStatus('error', errMsg, 0);
				}
			})
			.fail(function (xhr) {
				console.error('KH SMMA: Post now failed', xhr.responseText);
				try {
					var resp = JSON.parse(xhr.responseText);
					self.showStatus('error', (resp.data && resp.data.message) || khSmmaSocial.strings.postError || 'LinkedIn publish failed', 0);
				} catch (e) {
					self.showStatus('error', khSmmaSocial.strings.postError || 'LinkedIn publish failed', 0);
				}
			})
			.always(function () {
				$btn.text(originalText).prop('disabled', false);
			});
		},

		/**
		 * Save and queue for later SMMA admin review.
		 */
		queueLater: function () {
			var self = this;
			var $btn = $('#kh-smma-queue-later');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(khSmmaSocial.strings.queuing || 'Queuing...');
			self.hideStatus();

			$.post(khSmmaSocial.ajaxUrl, $.extend(self.getFieldData(), {
				action: 'kh_smma_queue_later'
			}))
			.done(function (response) {
				if (response.success) {
					self.showStatus('queued', khSmmaSocial.strings.queued || 'Queued for later', 0);
					// Update current-status section
					$('#kh-smma-current-status').remove();
					var $statusDiv = $('<div id="kh-smma-current-status" style="margin-top:10px;font-size:13px;color:#555;">' +
						'<span class="dashicons dashicons-clock" style="color:#dba617;vertical-align:middle;"></span> ' +
						(khSmmaSocial.strings.queued || 'Queued for later') +
						(response.data && response.data.message ? ' — ' + response.data.message : '') +
						'</div>');
					$('#kh-smma-queue-later').parent().after($statusDiv);
				} else {
					self.showStatus('error', (response.data && response.data.message) || khSmmaSocial.strings.queueError || 'Queue failed', 8000);
				}
			})
			.fail(function (xhr) {
				console.error('KH SMMA: Queue later failed', xhr.responseText);
				self.showStatus('error', khSmmaSocial.strings.queueError || 'Queue failed', 8000);
			})
			.always(function () {
				$btn.text(originalText).prop('disabled', false);
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

			if ($btn.length) $btn.prop('disabled', true);
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
					$container.html('<div class="kh-smma-preview-placeholder" style="color:#d63638;">' + ((response.data && response.data.message) || 'Error generating preview') + '</div>');
					$warnings.hide();
				}
			})
			.fail(function () {
				$container.html('<div class="kh-smma-preview-placeholder" style="color:#d63638;">Network error</div>');
				$warnings.hide();
			})
			.always(function () {
				if ($btn.length) $btn.prop('disabled', false);
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
				$('#kh-smma-select-social-image').text(khSmmaSocial.strings.changeImage || 'Change Image');

				// Ensure remove button exists
				if (!$('#kh-smma-remove-social-image').length) {
					$('#kh-smma-select-social-image').after(
						' <button type="button" class="button" id="kh-smma-remove-social-image">' + (khSmmaSocial.strings.removeImage || 'Remove Image') + '</button>'
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
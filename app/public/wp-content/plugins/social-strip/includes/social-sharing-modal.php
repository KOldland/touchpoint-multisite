<?php
/**
 * Social Sharing Modal functionality
 *
 * This file provides the unified social sharing modal for the Social Strip plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add unified modal to footer
 */
function kss_add_unified_modal_to_footer() {
    add_action('wp_footer', 'kss_render_unified_modal');
}

/**
 * Render the unified social sharing modal
 */
function kss_render_unified_modal() {
    if (is_admin()) {
        return;
    }
    ?>
    <div id="kss-unified-modal" class="kss-modal-overlay" style="display: none;">
        <div class="kss-modal-container">
            <div class="kss-modal-header">
                <h3 class="kss-modal-title">Share This Article</h3>
                <button class="kss-modal-close" type="button">&times;</button>
            </div>
            
            <div class="kss-modal-content">
                <div class="kss-share-tabs">
                    <button class="kss-tab-btn is-active" type="button" data-tab="email">Email</button>
                    <button class="kss-tab-btn" type="button" data-tab="linkedin">LinkedIn</button>
                </div>

                <div class="kss-tab-panel is-active" data-tab="email">
                    <form class="kss-share-email-form">
                        <div class="kss-form-group">
                            <label for="kss-share-recipient-email">Recipient Email</label>
                            <div class="kss-input-with-button">
                                <input type="email" id="kss-share-recipient-email" name="recipient_email" required>
                                <button type="button" class="kss-contact-btn" aria-label="Open contacts">
                                    <span class="kss-contact-icon">👤</span>
                                </button>
                            </div>
                        </div>
                        <div class="kss-form-group">
                            <label for="kss-share-message">Message</label>
                            <textarea id="kss-share-message" name="message" rows="4"></textarea>
                        </div>
                        <button type="submit" class="kss-btn kss-btn-primary">Send Email</button>
                    </form>

                    <div class="kss-share-confirm" style="display: none;">
                        <p class="kss-share-confirm-message"></p>
                        <button type="button" class="kss-btn kss-btn-secondary kss-copy-share">Copy Message</button>
                        <div class="kss-share-confirm-note"></div>
                    </div>
                </div>

                <div class="kss-tab-panel" data-tab="linkedin">
                    <div class="kss-linkedin-preview">
                        <div class="kss-linkedin-thumb"></div>
                        <div class="kss-linkedin-content">
                            <div class="kss-linkedin-title"></div>
                            <div class="kss-linkedin-excerpt"></div>
                        </div>
                    </div>
                    <div class="kss-form-group">
                        <label for="kss-linkedin-message">Intro Text</label>
                        <textarea id="kss-linkedin-message" rows="3">This is a really great read from Service Business Review - well worth a read!</textarea>
                    </div>
                    <button class="kss-share-btn kss-linkedin" data-platform="linkedin" type="button">
                        Share on LinkedIn
                    </button>
                </div>
            </div>

            <div class="kss-modal-footer">
                <div class="kss-article-info">
                    <h5 class="kss-article-title"></h5>
                    <p class="kss-article-excerpt"></p>
                </div>
            </div>
        </div>
    </div>

    <style>
    .kss-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .kss-modal-container {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    .kss-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #eee;
    }

    .kss-modal-title {
        margin: 0;
        font-size: 24px;
        color: #333;
    }

    .kss-modal-close {
        background: none;
        border: none;
        font-size: 28px;
        color: #999;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .kss-modal-close:hover {
        color: #333;
    }

    .kss-modal-content {
        padding: 24px;
    }

    .kss-modal-section {
        margin-bottom: 24px;
    }

    .kss-modal-section h4 {
        margin: 0 0 12px 0;
        font-size: 18px;
        color: #333;
    }

    .kss-share-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
        border-bottom: 1px solid #eee;
    }

    .kss-tab-btn {
        background: transparent;
        border: none;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        color: #777;
        cursor: pointer;
        border-bottom: 2px solid transparent;
    }

    .kss-tab-btn.is-active {
        color: #222;
        border-color: #6d0b0b;
    }

    .kss-tab-panel {
        display: none;
    }

    .kss-tab-panel.is-active {
        display: block;
    }

    .kss-form-group {
        margin-bottom: 16px;
    }

    .kss-form-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #333;
    }

    .kss-form-group input,
    .kss-form-group textarea {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px 12px;
        font-size: 14px;
        font-family: inherit;
    }

    .kss-input-with-button {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .kss-input-with-button input {
        flex: 1;
    }

    .kss-contact-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #ddd;
        background: #f8f6f3;
        cursor: pointer;
    }

    .kss-contact-btn:hover {
        background: #efedea;
    }

    .kss-contact-icon {
        font-size: 18px;
        line-height: 1;
    }

    .kss-contact-note {
        font-size: 13px;
        color: #888;
        margin-bottom: 16px;
    }

    .kss-share-confirm {
        margin-top: 16px;
        padding: 14px;
        border-radius: 8px;
        background: #f8f6f3;
        border: 1px solid #e5e5e5;
    }

    .kss-share-confirm-message {
        margin: 0 0 10px;
        font-weight: 600;
    }

    .kss-share-confirm-note {
        margin-top: 10px;
        font-size: 13px;
        color: #2e7d32;
        display: none;
    }

    .kss-share-confirm-note.is-visible {
        display: block;
    }

    .kss-linkedin-preview {
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 16px;
        background: #fafafa;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .kss-linkedin-thumb {
        width: 72px;
        height: 72px;
        border-radius: 8px;
        background: #e5e5e5 center/cover no-repeat;
        flex-shrink: 0;
    }

    .kss-linkedin-content {
        flex: 1;
    }

    .kss-linkedin-title {
        font-weight: 600;
        margin-bottom: 6px;
    }

    .kss-linkedin-excerpt {
        color: #666;
        font-size: 14px;
    }

    .kss-social-buttons,
    .kss-direct-share {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
    }

    .kss-share-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        border: 1px solid #2e7d32;
        border-radius: 8px;
        background: #2e7d32;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
    }

    .kss-share-btn:hover {
        background: #1b5e20;
        border-color: #1b5e20;
        color: #fff;
    }

    #kss-unified-modal .kss-share-btn:hover {
        transform: none !important;
        box-shadow: none !important;
    }

    .kss-form-group {
        margin-bottom: 16px;
    }

    .kss-form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
        color: #333;
    }

    .kss-form-group input,
    .kss-form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .kss-gift-options {
        display: flex;
        gap: 16px;
    }

    .kss-gift-options label {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 0;
    }

    .kss-btn {
        padding: 10px 20px;
        border: 1px solid #2e7d32;
        border-radius: 4px;
        background: #2e7d32;
        color: white;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s ease;
    }

    .kss-btn:hover {
        background: #1b5e20;
    }

    .kss-btn-secondary {
        background: #f3f3f3;
        border-color: #ddd;
        color: #333;
    }

    .kss-btn-secondary:hover {
        background: #e8e8e8;
    }

    .kss-url-container {
        display: flex;
        gap: 8px;
    }

    .kss-article-url {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f8f9fa;
        font-size: 14px;
    }

    .kss-copy-url-btn {
        padding: 8px 16px;
        border: 1px solid #007cba;
        border-radius: 4px;
        background: #007cba;
        color: white;
        cursor: pointer;
        font-size: 14px;
    }

    .kss-modal-footer {
        padding: 20px 24px;
        border-top: 1px solid #eee;
        background: #f8f9fa;
    }

    .kss-article-title {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #333;
    }

    .kss-article-excerpt {
        margin: 0;
        color: #666;
        font-size: 14px;
        line-height: 1.4;
    }

    /* Preview Section Styles */
    .kss-preview-container {
        background: #f8f9fa;
        border: 1px solid #e1e1e1;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
    }

    .kss-preview-platform {
        margin-bottom: 12px;
    }

    .kss-preview-selector {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        background: white;
    }

    .kss-preview-content {
        background: white;
        border: 1px solid #e1e1e1;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 8px;
    }

    .kss-preview-text {
        font-size: 14px;
        line-height: 1.4;
        color: #333;
        margin-bottom: 8px;
        white-space: pre-wrap;
    }

    .kss-preview-hashtags {
        font-size: 13px;
        color: #1da1f2;
        margin-bottom: 8px;
    }

    .kss-preview-limits {
        font-size: 12px;
        color: #666;
        text-align: right;
    }

    .kss-preview-limits .kss-char-count.over-limit {
        color: #dc3545;
        font-weight: bold;
    }

    .kss-preview-toggle {
        background: #28a745;
        border-color: #28a745;
    }

    .kss-preview-toggle:hover {
        background: #218838;
    }

    .kss-preview-toggle.active {
        background: #dc3545;
        border-color: #dc3545;
    }

    .kss-preview-toggle.active:hover {
        background: #c82333;
    }

    @media (max-width: 768px) {
        .kss-modal-container {
            width: 95%;
            margin: 20px;
        }
        
        .kss-social-buttons,
        .kss-direct-share {
            grid-template-columns: 1fr;
        }
        
        .kss-gift-options {
            flex-direction: column;
            gap: 8px;
        }
    }
    </style>

    <script>
    (function($) {
        'use strict';

        $(document).ready(function() {
            initUnifiedModal();
        });

        function initUnifiedModal() {
            const $modal = $('#kss-unified-modal');

            $(document).on('click', '.ssm-share-trigger', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const $btn = $(this);
                const data = {
                    title: $btn.data('title') || '',
                    url: $btn.data('url') || '',
                    excerpt: $btn.data('excerpt') || '',
                    featured_image: $btn.data('image') || '',
                    post_id: $btn.data('post-id') || 0
                };
                openModal(data);
            });

            $modal.find('.kss-modal-close').on('click', closeModal);
            $modal.on('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            $modal.find('.kss-tab-btn').on('click', function() {
                const tab = $(this).data('tab');
                $modal.find('.kss-tab-btn').removeClass('is-active');
                $(this).addClass('is-active');
                $modal.find('.kss-tab-panel').removeClass('is-active');
                $modal.find(`.kss-tab-panel[data-tab="${tab}"]`).addClass('is-active');
            });

            $modal.find('.kss-share-btn').on('click', function(e) {
                e.preventDefault();
                const platform = $(this).data('platform');
                handleShare(platform);
            });

            $modal.find('.kss-share-email-form').on('submit', function(e) {
                e.preventDefault();
                handleShareEmail($(this));
            });

            $modal.on('click', '.kss-copy-share', function() {
                const $confirm = $modal.find('.kss-share-confirm');
                const copyText = $confirm.data('copyText') || '';
                if (!copyText) {
                    return;
                }
                navigator.clipboard.writeText(copyText).then(() => {
                    $modal.find('.kss-share-confirm-note').text('Message copied to clipboard.').addClass('is-visible');
                });
            });
        }

        function openModal(data) {
            const $modal = $('#kss-unified-modal');

            $modal.find('.kss-article-title').text(data.title || '');
            $modal.find('.kss-article-excerpt').text(data.excerpt || '');
            $modal.find('.kss-linkedin-title').text(data.title || '');
            $modal.find('.kss-linkedin-excerpt').text(data.excerpt || '');
            const thumbUrl = data.featured_image || '';
            $modal.find('.kss-linkedin-thumb').css('background-image', thumbUrl ? `url('${thumbUrl}')` : 'none');
            $modal.find('#kss-linkedin-message').val('This is a really great read from Service Business Review - well worth a read!');
            $modal.find('#kss-share-message').val(data.default_message || 'This is a really good piece of insight that I think you will find valuable.');
            $modal.find('#kss-share-recipient-email').val('');
            $modal.find('.kss-share-confirm').hide();
            $modal.find('.kss-share-confirm-note').removeClass('is-visible').text('');
            $modal.find('.kss-tab-btn').removeClass('is-active').first().addClass('is-active');
            $modal.find('.kss-tab-panel').removeClass('is-active').first().addClass('is-active');

            $modal.data('current-article', data);
            $modal.show();
        }

        function closeModal() {
            $('#kss-unified-modal').hide();
        }

        function handleShare(platform) {
            const $modal = $('#kss-unified-modal');
            const data = $modal.data('current-article') || {};

            if (window.kssEnhanced && typeof window.kssEnhanced.handleShare === 'function') {
                if (platform === 'linkedin') {
                    const intro = ($('#kss-linkedin-message').val() || '').trim();
                    const originalExcerpt = data.excerpt || '';
                    data.excerpt = intro ? `${intro}\n\n${originalExcerpt}` : originalExcerpt;
                    $modal.data('current-article', data);
                    window.kssEnhanced.handleShare(platform);
                    data.excerpt = originalExcerpt;
                    $modal.data('current-article', data);
                    return;
                }
                window.kssEnhanced.handleShare(platform);
            }
        }

        function handleShareEmail($form) {
            const $modal = $('#kss-unified-modal');
            const data = $modal.data('current-article') || {};
            const recipient = ($form.find('#kss-share-recipient-email').val() || '').trim();
            const message = ($form.find('#kss-share-message').val() || '').trim();

            if (!recipient || !message) {
                return;
            }

            $form.find('button[type="submit"]').prop('disabled', true).text('Sending...');

            $.ajax({
                url: (window.kssKhm && kssKhm.ajaxUrl) ? kssKhm.ajaxUrl : '',
                method: 'POST',
                data: {
                    action: 'kss_send_share_email',
                    nonce: (window.kssKhm && kssKhm.nonce) ? kssKhm.nonce : '',
                    post_id: data.post_id || data.id || 0,
                    recipient_email: recipient,
                    message: message
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    alert(response?.data || 'Unable to send email.');
                    return;
                }

                const url = data.url || '';
                const copyText = `${message}

${url}`;
                $modal.find('.kss-share-confirm-message').text(`Email sent to ${recipient}.`);
                $modal.find('.kss-share-confirm').data('copyText', copyText).show();
            }).fail(function() {
                alert('Unable to send email.');
            }).always(function() {
                $form.find('button[type="submit"]').prop('disabled', false).text('Send Email');
            });
        }

    })(jQuery);
    </script>
    <?php
}

/**
 * Handle modal AJAX requests
 */
add_action('wp_ajax_kss_get_modal_data', 'kss_handle_get_modal_data');
add_action('wp_ajax_nopriv_kss_get_modal_data', 'kss_handle_get_modal_data');

function kss_handle_get_modal_data() {
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }
    
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
    }
    
    // Get categories and tags for hashtag generation
    $categories = get_the_category($post_id);
    $tags = get_the_tags($post_id);
    
    $category_names = $categories ? array_map(function($cat) { return $cat->name; }, $categories) : [];
    $tag_names = $tags ? array_map(function($tag) { return $tag->name; }, $tags) : [];
    
    $data = [
        'post_id' => $post_id,
        'title' => $post->post_title,
        'excerpt' => wp_trim_words($post->post_content, 30),
        'url' => get_permalink($post_id),
        'featured_image' => get_the_post_thumbnail_url($post_id, 'large'),
        'author' => get_the_author_meta('display_name', $post->post_author),
        'categories' => $category_names,
        'tags' => $tag_names,
        'khm_available' => function_exists('khm_is_marketing_suite_ready') && khm_is_marketing_suite_ready(),
        'is_logged_in' => is_user_logged_in()
    ];
    
    // Add enhanced data if available
    if (function_exists('kss_get_enhanced_widget_data')) {
        $enhanced_data = kss_get_enhanced_widget_data($post_id);
        $data = array_merge($data, $enhanced_data);
    }
    
    wp_send_json_success($data);
}

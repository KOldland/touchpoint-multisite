/**
 * Enhanced Social Sharing Modal with Hashtag Generation and Character Limits
 * 
 * Features:
 * - Platform-specific character limits
 * - Automatic hashtag generation from categories/tags
 * - Real-time preview functionality
 * - Optimized content for each social platform
 */

(function($) {
    'use strict';
    
    // Platform configurations
    const PLATFORM_CONFIG = {
        twitter: {
            charLimit: 280,
            urlLength: 23, // Twitter auto-shortens URLs
            hashtagLimit: 5,
            tone: 'casual',
            prefix: '',
            suffix: ''
        },
        facebook: {
            charLimit: 63206,
            urlLength: 0, // Facebook shows full URL
            hashtagLimit: 10,
            tone: 'engaging',
            prefix: '',
            suffix: ''
        },
        linkedin: {
            charLimit: 1300, // LinkedIn posts
            titleLimit: 200,
            summaryLimit: 256,
            hashtagLimit: 3,
            tone: 'professional',
            prefix: '',
            suffix: ''
        },
        pinterest: {
            charLimit: 500,
            urlLength: 0,
            hashtagLimit: 20,
            tone: 'descriptive',
            prefix: '',
            suffix: ''
        },
        whatsapp: {
            charLimit: 1600,
            urlLength: 0,
            hashtagLimit: 5,
            tone: 'casual',
            prefix: '📰 ',
            suffix: ''
        },
        email: {
            subjectLimit: 78,
            bodyLimit: 2000,
            hashtagLimit: 0,
            tone: 'formal',
            prefix: 'I thought you might be interested in: ',
            suffix: ''
        }
    };
    
    /**
     * Generate hashtags from article categories and tags
     */
    function generateHashtags(categories, tags, platform) {
        const config = PLATFORM_CONFIG[platform] || PLATFORM_CONFIG.twitter;
        const hashtags = [];
        
        // Function to clean and format hashtag
        function formatHashtag(text) {
            return '#' + text
                .replace(/[^a-zA-Z0-9\s]/g, '') // Remove special characters
                .replace(/\s+/g, '') // Remove spaces
                .toLowerCase()
                .substring(0, 30); // Limit hashtag length
        }
        
        // Process categories first (higher priority)
        if (categories && Array.isArray(categories)) {
            categories.forEach(category => {
                if (hashtags.length >= config.hashtagLimit) return;
                const hashtag = formatHashtag(category);
                if (hashtag.length > 2 && !hashtags.includes(hashtag)) {
                    hashtags.push(hashtag);
                }
            });
        }
        
        // Add tags if we have room
        if (tags && Array.isArray(tags)) {
            tags.forEach(tag => {
                if (hashtags.length >= config.hashtagLimit) return;
                const hashtag = formatHashtag(tag);
                if (hashtag.length > 2 && !hashtags.includes(hashtag)) {
                    hashtags.push(hashtag);
                }
            });
        }
        
        // Add generic hashtags if we still have room
        if (hashtags.length < config.hashtagLimit) {
            const genericTags = ['#article', '#read', '#share'];
            genericTags.forEach(tag => {
                if (hashtags.length >= config.hashtagLimit) return;
                if (!hashtags.includes(tag)) {
                    hashtags.push(tag);
                }
            });
        }
        
        return hashtags;
    }
    
    /**
     * Create platform-optimized content
     */
    function createOptimizedContent(data, platform, hashtags) {
        const config = PLATFORM_CONFIG[platform] || PLATFORM_CONFIG.twitter;
        const hashtagText = hashtags.length > 0 ? ' ' + hashtags.join(' ') : '';
        
        let title = data.title || '';
        let text = data.excerpt || '';
        let optimizedText = '';
        
        switch (platform) {
            case 'twitter':
                optimizedText = createTwitterContent(data, config, hashtagText);
                break;
                
            case 'facebook':
                optimizedText = createFacebookContent(data, config, hashtagText);
                break;
                
            case 'linkedin':
                optimizedText = createLinkedInContent(data, config, hashtagText);
                break;
                
            case 'pinterest':
                optimizedText = createPinterestContent(data, config, hashtagText);
                break;
                
            case 'whatsapp':
                optimizedText = createWhatsAppContent(data, config, hashtagText);
                break;
                
            case 'email':
                optimizedText = createEmailContent(data, config, hashtagText);
                break;
                
            default:
                optimizedText = `${title}\n\n${text}${hashtagText}`;
        }
        
        return {
            title: title,
            text: optimizedText,
            hashtags: hashtags,
            charCount: optimizedText.length,
            isOverLimit: optimizedText.length > config.charLimit
        };
    }
    
    /**
     * Twitter-specific content optimization
     */
    function createTwitterContent(data, config, hashtagText) {
        const urlSpace = config.urlLength + 1; // +1 for space before URL
        const hashtagSpace = hashtagText.length;
        const availableChars = config.charLimit - urlSpace - hashtagSpace;
        
        let text = data.title;
        
        if (text.length > availableChars) {
            text = text.substring(0, availableChars - 3) + '...';
        }
        
        return text + hashtagText;
    }
    
    /**
     * Facebook-specific content optimization
     */
    function createFacebookContent(data, config, hashtagText) {
        const text = `${data.title}\n\n${data.excerpt}${hashtagText}`;
        
        if (text.length > config.charLimit) {
            const availableChars = config.charLimit - hashtagText.length - data.title.length - 5;
            const shortExcerpt = data.excerpt.substring(0, availableChars - 3) + '...';
            return `${data.title}\n\n${shortExcerpt}${hashtagText}`;
        }
        
        return text;
    }
    
    /**
     * LinkedIn-specific content optimization
     */
    function createLinkedInContent(data, config, hashtagText) {
        let title = data.title;
        let text = data.excerpt;
        
        if (title.length > config.titleLimit) {
            title = title.substring(0, config.titleLimit - 3) + '...';
        }
        
        if (text.length + hashtagText.length > config.summaryLimit) {
            const availableChars = config.summaryLimit - hashtagText.length;
            text = text.substring(0, availableChars - 3) + '...';
        }
        
        return `${title}\n\n${text}${hashtagText}`;
    }
    
    /**
     * Pinterest-specific content optimization
     */
    function createPinterestContent(data, config, hashtagText) {
        const combinedText = `${data.title}\n\n${data.excerpt}${hashtagText}`;
        
        if (combinedText.length > config.charLimit) {
            const availableChars = config.charLimit - hashtagText.length - data.title.length - 5;
            const shortExcerpt = data.excerpt.substring(0, availableChars - 3) + '...';
            return `${data.title}\n\n${shortExcerpt}${hashtagText}`;
        }
        
        return combinedText;
    }
    
    /**
     * WhatsApp-specific content optimization
     */
    function createWhatsAppContent(data, config, hashtagText) {
        let text = `${config.prefix}${data.title}\n\n${data.excerpt}${hashtagText}`;
        
        if (text.length > config.charLimit) {
            const prefixAndTitle = `${config.prefix}${data.title}\n\n`;
            const availableChars = config.charLimit - prefixAndTitle.length - hashtagText.length;
            const shortExcerpt = data.excerpt.substring(0, availableChars - 3) + '...';
            text = prefixAndTitle + shortExcerpt + hashtagText;
        }
        
        return text;
    }
    
    /**
     * Email-specific content optimization
     */
    function createEmailContent(data, config, hashtagText) {
        const subject = data.title.length > config.subjectLimit 
            ? data.title.substring(0, config.subjectLimit - 3) + '...'
            : data.title;
        
        let body = `${config.prefix}\n\n${data.excerpt}`;
        
        if (body.length > config.bodyLimit) {
            body = body.substring(0, config.bodyLimit - 3) + '...';
        }
        
        return `Subject: ${subject}\n\n${body}`;
    }
    
    /**
     * Update preview display
     */
    function updatePreview() {
        const $modal = $('#kss-unified-modal');
        const data = $modal.data('current-article');
        const platform = $modal.find('.kss-preview-selector').val();
        
        if (!data || !platform) return;
        
        // Generate hashtags and optimized content
        const hashtags = generateHashtags(data.categories || [], data.tags || [], platform);
        const optimizedContent = createOptimizedContent(data, platform, hashtags);
        const config = PLATFORM_CONFIG[platform] || PLATFORM_CONFIG.twitter;
        
        // Update preview display
        $modal.find('.kss-preview-text').text(optimizedContent.text);
        $modal.find('.kss-preview-hashtags').text(optimizedContent.hashtags.join(' '));
        $modal.find('.kss-preview-content').attr('data-platform', platform);
        
        // Update character count
        const $charCount = $modal.find('.kss-char-count');
        const $charLimit = $modal.find('.kss-char-limit');
        
        $charCount.text(optimizedContent.charCount);
        $charLimit.text(config.charLimit);
        
        // Add over-limit styling if needed
        if (optimizedContent.isOverLimit) {
            $charCount.addClass('over-limit');
        } else {
            $charCount.removeClass('over-limit');
        }
        
        // Add helpful tips for each platform
        updatePlatformTips(platform, $modal);
    }
    
    /**
     * Add platform-specific tips
     */
    function updatePlatformTips(platform, $modal) {
        const tips = {
            twitter: 'Keep it short and engaging. Use relevant hashtags for discovery.',
            facebook: 'Longer posts perform well. Ask questions to encourage engagement.',
            linkedin: 'Professional tone works best. Share insights and value.',
            pinterest: 'Use descriptive text and relevant hashtags for discovery.',
            whatsapp: 'Casual and personal tone. Emojis work well.',
            email: 'Clear subject line and compelling preview text are important.'
        };
        
        const tip = tips[platform] || '';
        
        // Remove existing tip
        $modal.find('.kss-platform-tip').remove();
        
        if (tip) {
            $modal.find('.kss-preview-content').after(
                `<div class="kss-platform-tip" style="font-size: 12px; color: #666; font-style: italic; margin-top: 8px;">💡 ${tip}</div>`
            );
        }
    }
    
    /**
     * Load affiliate URL for current user
     */
    function loadAffiliateUrl(baseUrl, postId) {
        return new Promise((resolve, reject) => {
            // If no AJAX data available, return base URL
            if (typeof khm_ajax === 'undefined') {
                resolve({
                    affiliate_url: baseUrl,
                    has_affiliate: false,
                    message: 'AJAX not available'
                });
                return;
            }
            
            $.ajax({
                url: khm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kss_get_affiliate_url',
                    nonce: khm_ajax.nonce,
                    post_id: postId,
                    base_url: baseUrl
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        // Fallback to base URL on error
                        resolve({
                            affiliate_url: baseUrl,
                            has_affiliate: false,
                            error: response.data
                        });
                    }
                },
                error: function() {
                    // Fallback to base URL on AJAX error
                    resolve({
                        affiliate_url: baseUrl,
                        has_affiliate: false,
                        error: 'AJAX request failed'
                    });
                }
            });
        });
    }

    /**
     * Enhanced share handling with affiliate URLs
     */
    function enhancedHandleShare(platform) {
        const $modal = $('#kss-unified-modal');
        const data = $modal.data('current-article');
        
        if (!data) return;
        
        // First load affiliate URL, then proceed with sharing
        loadAffiliateUrl(data.url, data.post_id || data.id).then(urlData => {
            // Use affiliate URL if available
            const shareData = {...data, url: urlData.affiliate_url};
            
            // Generate optimized content with affiliate URL
            const hashtags = generateHashtags(shareData.categories || [], shareData.tags || [], platform);
            const optimizedContent = createOptimizedContent(shareData, platform, hashtags);
            
            // Show affiliate status in UI
            if (urlData.has_affiliate) {
                showMessage('🎯 Sharing with your affiliate tracking!', 'success');
            }
            
            trackShare(platform, shareData, optimizedContent);
            performPlatformShare(platform, shareData, optimizedContent);
        });
    }

    /**
     * Perform the actual platform sharing
     */
    function performPlatformShare(platform, data, optimizedContent) {
        
        let shareUrl = '';
        
        switch (platform) {
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(data.url)}`;
                break;
                
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(data.url)}&text=${encodeURIComponent(optimizedContent.text)}`;
                break;
                
            case 'linkedin':
                const linkedinParams = new URLSearchParams({
                    url: data.url,
                    title: data.title,
                    summary: optimizedContent.text.replace(/\n/g, ' ')
                });
                shareUrl = `https://www.linkedin.com/sharing/share-offsite/?${linkedinParams.toString()}`;
                break;
                
            case 'pinterest':
                const pinterestParams = new URLSearchParams({
                    url: data.url,
                    description: optimizedContent.text,
                    media: data.featured_image || ''
                });
                shareUrl = `https://pinterest.com/pin/create/button/?${pinterestParams.toString()}`;
                break;
                
            case 'whatsapp':
                shareUrl = `https://wa.me/?text=${encodeURIComponent(optimizedContent.text + ' ' + data.url)}`;
                break;
                
            case 'email':
                const emailParams = new URLSearchParams({
                    subject: data.title,
                    body: optimizedContent.text + '\n\n' + data.url
                });
                shareUrl = `mailto:?${emailParams.toString()}`;
                break;
                
            case 'copy':
                const copyText = optimizedContent.text + '\n' + data.url;
                navigator.clipboard.writeText(copyText).then(() => {
                    showMessage('✅ Content copied to clipboard with affiliate tracking!');
                });
                return;
        }
        
        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    }

    /**
     * Show status message to user
     */
    function showMessage(message, type = 'info') {
        // Create a temporary notification
        const $notification = $('<div>')
            .addClass('kss-notification')
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '12px 16px',
                background: type === 'success' ? '#4CAF50' : '#2196F3',
                color: 'white',
                borderRadius: '4px',
                fontSize: '14px',
                zIndex: 10001,
                opacity: 0
            })
            .text(message);
            
        $('body').append($notification);
        
        $notification.animate({opacity: 1}, 300);
        
        setTimeout(() => {
            $notification.animate({opacity: 0}, 300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Send share telemetry to the backend so SMMA/PPC systems can react.
     */
    function trackShare(platform, data, optimizedContent) {
        if (typeof kssKhm === 'undefined' || !kssKhm.ajaxUrl) {
            return Promise.resolve();
        }

        const payload = new URLSearchParams();
        payload.append('action', 'kss_track_share');
        if (kssKhm.nonce) {
            payload.append('nonce', kssKhm.nonce);
        }
        payload.append('platform', platform);
        payload.append('post_id', data.post_id || data.id || 0);
        payload.append('url', data.url || '');
        payload.append('content', optimizedContent.text || '');
        payload.append('char_count', optimizedContent.charCount || (optimizedContent.text || '').length || 0);
        payload.append('source', data.share_source || 'social_strip_modal');

        if (optimizedContent.hashtags && optimizedContent.hashtags.length) {
            optimizedContent.hashtags.forEach(tag => payload.append('hashtags[]', tag));
        }

        if (data.affiliate_id) {
            payload.append('meta[affiliate_id]', data.affiliate_id);
        }
        if (data.campaign) {
            payload.append('meta[campaign]', data.campaign);
        }
        if (data.account) {
            payload.append('meta[account]', data.account);
        }

        return fetch(kssKhm.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: payload.toString()
        }).catch(() => {
            // Silently ignore telemetry errors to avoid blocking sharing.
        });
    }
    
    // Expose enhanced functions globally
    window.kssEnhanced = {
        generateHashtags: generateHashtags,
        createOptimizedContent: createOptimizedContent,
        updatePreview: updatePreview,
        handleShare: enhancedHandleShare,
        loadAffiliateUrl: loadAffiliateUrl,
        showMessage: showMessage,
        trackShare: trackShare,
        PLATFORM_CONFIG: PLATFORM_CONFIG
    };
    
})(jQuery);

/**
 * Social Media Preview Frontend JavaScript
 * Handles frontend preview interactions and live updates
 * 
 * @package KHM_SEO
 * @subpackage Preview
 */

(function($) {
    'use strict';
    
    // Frontend Preview Handler
    window.KHMSocialPreviewFrontend = {
        
        // Configuration
        config: {
            apiUrl: '/wp-json/khm-seo/v1/preview',
            refreshInterval: 60000, // 1 minute
            platforms: ['facebook', 'twitter', 'linkedin', 'whatsapp', 'discord', 'slack']
        },
        
        // State
        state: {
            previewsEnabled: false,
            currentPost: null,
            observers: new Map()
        },
        
        // Initialize frontend preview system
        init: function() {
            if (typeof window.khmSocialPreview === 'undefined' || !window.khmSocialPreview.active) {
                return;
            }
            
            this.state.previewsEnabled = true;
            this.state.currentPost = window.khmSocialPreview.postId;
            
            console.log('KHM Social Preview Frontend initialized for post:', this.state.currentPost);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if (window.khmSocialPreview && window.khmSocialPreview.active) {
            KHMSocialPreviewFrontend.init();
        }
    });
    
})(jQuery);
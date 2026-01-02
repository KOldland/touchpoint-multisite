/**
 * Advanced Attribution Tracker (JavaScript)
 * 
 * Handles client-side attribution tracking with ITP/Safari/AdBlock resistance
 * Supports hybrid tracking: 1P cookies + server-side events + fingerprinting fallback
 */

(function($) {
    'use strict';

    window.KHMAttributionTracker = {
        
        // Configuration
        config: {
            apiUrl: '',
            nonce: '',
            attributionWindow: 30,
            cookielessFallback: true,
            serverSideEvents: true,
            fingerprintingEnabled: false,
            debug: false
        },

        // State management
        state: {
            initialized: false,
            trackingId: null,
            sessionData: {},
            attributionData: null,
            fallbackMethods: []
        },

        /**
         * Initialize attribution tracker
         */
        init: function() {
            if (this.state.initialized) {
                return;
            }

            // Load configuration from WordPress localization
            if (typeof khmAttribution !== 'undefined') {
                this.config = Object.assign(this.config, khmAttribution);
            }

            this.log('Initializing KHM Attribution Tracker', this.config);

            // Initialize tracking components
            this.initializeSessionTracking();
            this.initializeAttributionDetection();
            this.initializeLinkTracking();
            this.initializeConversionTracking();
            this.initializeFallbackMethods();

            this.state.initialized = true;
            this.log('Attribution tracker initialized successfully');
        },

        /**
         * Initialize session tracking
         */
        initializeSessionTracking: function() {
            // Generate or retrieve session ID
            this.state.trackingId = this.getOrCreateTrackingId();
            
            // Collect session data
            this.state.sessionData = {
                trackingId: this.state.trackingId,
                timestamp: Date.now(),
                url: window.location.href,
                referrer: document.referrer,
                userAgent: navigator.userAgent,
                language: navigator.language,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                screen: {
                    width: screen.width,
                    height: screen.height
                },
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                platform: navigator.platform
            };

            // Store session data
            this.storeSessionData();
        },

        /**
         * Initialize attribution detection
         */
        initializeAttributionDetection: function() {
            // Check for affiliate parameters in URL
            const urlParams = new URLSearchParams(window.location.search);
            const affiliateParams = ['aff', 'affiliate_id', 'ref', 'affiliate'];
            
            let affiliateId = null;
            for (const param of affiliateParams) {
                if (urlParams.has(param)) {
                    affiliateId = urlParams.get(param);
                    break;
                }
            }

            if (affiliateId) {
                this.trackAffiliateClick(affiliateId, urlParams);
            } else {
                // Check for existing attribution
                this.loadExistingAttribution();
            }
        },

        /**
         * Initialize link tracking for affiliate links
         */
        initializeLinkTracking: function() {
            const self = this;
            
            // Track clicks on affiliate links
            $(document).on('click', 'a[data-affiliate-id], a[href*="aff="], a[href*="affiliate_id="], a[href*="ref="]', function(e) {
                const $link = $(this);
                const href = $link.attr('href');
                const affiliateId = $link.data('affiliate-id') || self.extractAffiliateIdFromUrl(href);
                
                if (affiliateId) {
                    self.trackLinkClick(affiliateId, href, $link);
                }
            });

            // Track clicks on creative elements
            $(document).on('click', '[data-khm-creative]', function(e) {
                const $element = $(this);
                const creativeId = $element.data('khm-creative');
                const affiliateId = $element.data('affiliate-id');
                
                if (affiliateId && creativeId) {
                    self.trackCreativeClick(affiliateId, creativeId, $element);
                }
            });
        },

        /**
         * Initialize conversion tracking
         */
        initializeConversionTracking: function() {
            // Listen for conversion events
            $(document).on('khmConversion', this.handleConversionEvent.bind(this));
            
            // Auto-detect conversions on key pages
            this.detectAutoConversions();
        },

        /**
         * Initialize fallback attribution methods
         */
        initializeFallbackMethods: function() {
            if (this.config.cookielessFallback) {
                this.state.fallbackMethods.push('localStorage');
                this.state.fallbackMethods.push('sessionStorage');
                
                if (this.config.fingerprintingEnabled) {
                    this.state.fallbackMethods.push('fingerprint');
                }
            }

            this.log('Fallback methods enabled:', this.state.fallbackMethods);
        },

        /**
         * Track affiliate click
         */
        trackAffiliateClick: function(affiliateId, urlParams) {
            const utmParams = this.extractUtmParams(urlParams);
            const trackingData = {
                affiliate_id: affiliateId,
                url: window.location.href,
                referrer: document.referrer,
                utm_source: utmParams.utm_source || '',
                utm_medium: utmParams.utm_medium || '',
                utm_campaign: utmParams.utm_campaign || '',
                utm_term: utmParams.utm_term || '',
                utm_content: utmParams.utm_content || '',
                client_data: this.state.sessionData
            };

            this.log('Tracking affiliate click:', trackingData);

            // Send to server-side tracking endpoint
            this.sendTrackingEvent('click', trackingData);
        },

        /**
         * Track link click
         */
        trackLinkClick: function(affiliateId, href, $element) {
            const trackingData = {
                affiliate_id: affiliateId,
                url: href,
                referrer: window.location.href,
                client_data: {
                    ...this.state.sessionData,
                    linkText: $element.text().trim(),
                    linkPosition: this.getElementPosition($element)
                }
            };

            this.log('Tracking link click:', trackingData);
            this.sendTrackingEvent('click', trackingData);
        },

        /**
         * Track creative click
         */
        trackCreativeClick: function(affiliateId, creativeId, $element) {
            const trackingData = {
                affiliate_id: affiliateId,
                creative_id: creativeId,
                url: window.location.href,
                referrer: document.referrer,
                client_data: {
                    ...this.state.sessionData,
                    creativeType: $element.data('creative-type') || 'unknown',
                    creativePosition: this.getElementPosition($element)
                }
            };

            this.log('Tracking creative click:', trackingData);
            this.sendTrackingEvent('creative_click', trackingData);
        },

        /**
         * Handle conversion event
         */
        handleConversionEvent: function(event, conversionData) {
            this.trackConversion(conversionData);
        },

        /**
         * Track conversion
         */
        trackConversion: function(conversionData) {
            // Get attribution data from various sources
            const attributionData = this.getAttributionData();
            
            const trackingData = {
                conversion_id: conversionData.id || this.generateConversionId(),
                value: conversionData.value || 0,
                currency: conversionData.currency || 'USD',
                attribution_data: {
                    ...attributionData,
                    conversionMethod: conversionData.method || 'auto',
                    sessionData: this.state.sessionData
                }
            };

            this.log('Tracking conversion:', trackingData);
            this.sendTrackingEvent('conversion', trackingData);
        },

        /**
         * Auto-detect conversions on key pages
         */
        detectAutoConversions: function() {
            // Check for conversion indicators
            const conversionIndicators = [
                '.woocommerce-order-received',
                '.edd-success',
                '#checkout-success',
                '.conversion-complete',
                '[data-conversion]'
            ];

            for (const indicator of conversionIndicators) {
                const $element = $(indicator);
                if ($element.length) {
                    const conversionValue = this.extractConversionValue($element);
                    this.trackConversion({
                        id: this.generateConversionId(),
                        value: conversionValue,
                        method: 'auto_detect',
                        source: indicator
                    });
                    break;
                }
            }
        },

        /**
         * Send tracking event to server
         */
        sendTrackingEvent: function(eventType, data) {
            if (!this.config.serverSideEvents) {
                this.log('Server-side events disabled, storing locally');
                this.storeEventLocally(eventType, data);
                return;
            }

            const endpoint = this.config.apiUrl + 'track/' + eventType;
            
            $.ajax({
                url: endpoint,
                method: 'POST',
                data: data,
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                success: (response) => {
                    this.log('Tracking event sent successfully:', response);
                    this.handleTrackingResponse(eventType, response);
                },
                error: (xhr, status, error) => {
                    this.log('Tracking event failed:', error);
                    this.handleTrackingError(eventType, data, error);
                }
            });
        },

        /**
         * Handle successful tracking response
         */
        handleTrackingResponse: function(eventType, response) {
            if (eventType === 'click' && response.click_id) {
                // Store click ID for attribution
                this.storeAttribution({
                    click_id: response.click_id,
                    attribution_window: response.attribution_window,
                    tracking_method: response.tracking_method,
                    timestamp: Date.now()
                });
            }
        },

        /**
         * Handle tracking error with fallback
         */
        handleTrackingError: function(eventType, data, error) {
            this.log('Tracking error, using fallback methods:', error);
            
            // Store event locally for retry
            this.storeEventLocally(eventType, data);
            
            // Try alternative attribution methods
            if (eventType === 'click') {
                this.setFallbackAttribution(data);
            }
        },

        /**
         * Store attribution data using multiple methods
         */
        storeAttribution: function(attributionData) {
            // Primary: First-party cookie
            this.setAttributionCookie(attributionData);
            
            // Fallback: Local storage
            if (this.isStorageAvailable('localStorage')) {
                localStorage.setItem('khm_attribution', JSON.stringify(attributionData));
            }
            
            // Fallback: Session storage
            if (this.isStorageAvailable('sessionStorage')) {
                sessionStorage.setItem('khm_attribution', JSON.stringify(attributionData));
            }
            
            // Store in memory
            this.state.attributionData = attributionData;
        },

        /**
         * Set attribution cookie
         */
        setAttributionCookie: function(attributionData) {
            const expires = new Date();
            expires.setDate(expires.getDate() + this.config.attributionWindow);
            
            const cookieValue = JSON.stringify(attributionData);
            const cookieOptions = [
                'khm_attribution=' + encodeURIComponent(cookieValue),
                'expires=' + expires.toUTCString(),
                'path=/',
                'SameSite=Lax'
            ];
            
            if (location.protocol === 'https:') {
                cookieOptions.push('Secure');
            }
            
            document.cookie = cookieOptions.join('; ');
        },

        /**
         * Get attribution data from various sources
         */
        getAttributionData: function() {
            // Try multiple sources in order of preference
            let attributionData = null;
            
            // 1. Memory state
            if (this.state.attributionData) {
                attributionData = this.state.attributionData;
            }
            
            // 2. First-party cookie
            if (!attributionData) {
                attributionData = this.getAttributionFromCookie();
            }
            
            // 3. Local storage
            if (!attributionData && this.isStorageAvailable('localStorage')) {
                const stored = localStorage.getItem('khm_attribution');
                if (stored) {
                    try {
                        attributionData = JSON.parse(stored);
                    } catch (e) {
                        this.log('Error parsing attribution from localStorage:', e);
                    }
                }
            }
            
            // 4. Session storage
            if (!attributionData && this.isStorageAvailable('sessionStorage')) {
                const stored = sessionStorage.getItem('khm_attribution');
                if (stored) {
                    try {
                        attributionData = JSON.parse(stored);
                    } catch (e) {
                        this.log('Error parsing attribution from sessionStorage:', e);
                    }
                }
            }
            
            // Check if attribution data is still valid
            if (attributionData && this.isAttributionExpired(attributionData)) {
                this.log('Attribution data expired, clearing');
                this.clearAttribution();
                return null;
            }
            
            return attributionData;
        },

        /**
         * Get attribution from cookie
         */
        getAttributionFromCookie: function() {
            const cookies = document.cookie.split(';');
            
            for (let cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'khm_attribution') {
                    try {
                        return JSON.parse(decodeURIComponent(value));
                    } catch (e) {
                        this.log('Error parsing attribution cookie:', e);
                        return null;
                    }
                }
            }
            
            return null;
        },

        /**
         * Check if attribution data is expired
         */
        isAttributionExpired: function(attributionData) {
            if (!attributionData.timestamp) {
                return true;
            }
            
            const expiryTime = attributionData.timestamp + (this.config.attributionWindow * 24 * 60 * 60 * 1000);
            return Date.now() > expiryTime;
        },

        /**
         * Clear attribution data
         */
        clearAttribution: function() {
            // Clear memory
            this.state.attributionData = null;
            
            // Clear cookie
            document.cookie = 'khm_attribution=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            
            // Clear storage
            if (this.isStorageAvailable('localStorage')) {
                localStorage.removeItem('khm_attribution');
            }
            
            if (this.isStorageAvailable('sessionStorage')) {
                sessionStorage.removeItem('khm_attribution');
            }
        },

        /**
         * Load existing attribution
         */
        loadExistingAttribution: function() {
            const attribution = this.getAttributionData();
            if (attribution) {
                this.log('Existing attribution found:', attribution);
                this.state.attributionData = attribution;
            }
        },

        /**
         * Set fallback attribution
         */
        setFallbackAttribution: function(clickData) {
            const fallbackAttribution = {
                affiliate_id: clickData.affiliate_id,
                method: 'fallback',
                timestamp: Date.now(),
                data: clickData
            };
            
            this.storeAttribution(fallbackAttribution);
        },

        /**
         * Extract UTM parameters from URL params
         */
        extractUtmParams: function(urlParams) {
            const utmParams = {};
            const utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
            
            for (const key of utmKeys) {
                if (urlParams.has(key)) {
                    utmParams[key] = urlParams.get(key);
                }
            }
            
            return utmParams;
        },

        /**
         * Extract affiliate ID from URL
         */
        extractAffiliateIdFromUrl: function(url) {
            const urlObj = new URL(url, window.location.origin);
            const params = new URLSearchParams(urlObj.search);
            
            const affiliateParams = ['aff', 'affiliate_id', 'ref', 'affiliate'];
            for (const param of affiliateParams) {
                if (params.has(param)) {
                    return params.get(param);
                }
            }
            
            return null;
        },

        /**
         * Extract conversion value from element
         */
        extractConversionValue: function($element) {
            // Try various methods to extract conversion value
            let value = 0;
            
            // Look for data attribute
            if ($element.data('conversion-value')) {
                value = parseFloat($element.data('conversion-value'));
            }
            
            // Look for common price selectors
            if (!value) {
                const priceSelectors = [
                    '.order-total .amount',
                    '.total .amount',
                    '.price',
                    '.woocommerce-Price-amount'
                ];
                
                for (const selector of priceSelectors) {
                    const $price = $element.find(selector).first();
                    if ($price.length) {
                        const priceText = $price.text().replace(/[^\d.,]/g, '');
                        value = parseFloat(priceText.replace(',', '.'));
                        if (value) break;
                    }
                }
            }
            
            return value || 0;
        },

        /**
         * Get element position for tracking
         */
        getElementPosition: function($element) {
            const offset = $element.offset();
            return {
                top: Math.round(offset.top),
                left: Math.round(offset.left),
                width: $element.outerWidth(),
                height: $element.outerHeight()
            };
        },

        /**
         * Generate unique tracking ID
         */
        getOrCreateTrackingId: function() {
            // Try to get existing ID from storage
            if (this.isStorageAvailable('localStorage')) {
                const existingId = localStorage.getItem('khm_tracking_id');
                if (existingId) {
                    return existingId;
                }
            }
            
            // Generate new ID
            const trackingId = 'track_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Store for future use
            if (this.isStorageAvailable('localStorage')) {
                localStorage.setItem('khm_tracking_id', trackingId);
            }
            
            return trackingId;
        },

        /**
         * Generate conversion ID
         */
        generateConversionId: function() {
            return 'conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Store session data
         */
        storeSessionData: function() {
            if (this.isStorageAvailable('sessionStorage')) {
                sessionStorage.setItem('khm_session', JSON.stringify(this.state.sessionData));
            }
        },

        /**
         * Store event locally for retry
         */
        storeEventLocally: function(eventType, data) {
            if (!this.isStorageAvailable('localStorage')) {
                return;
            }
            
            const events = JSON.parse(localStorage.getItem('khm_pending_events') || '[]');
            events.push({
                type: eventType,
                data: data,
                timestamp: Date.now()
            });
            
            // Keep only last 50 events
            if (events.length > 50) {
                events.splice(0, events.length - 50);
            }
            
            localStorage.setItem('khm_pending_events', JSON.stringify(events));
        },

        /**
         * Check if storage is available
         */
        isStorageAvailable: function(type) {
            try {
                const storage = window[type];
                const x = '__storage_test__';
                storage.setItem(x, x);
                storage.removeItem(x);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Log debug messages
         */
        log: function(message, data) {
            if (this.config.debug && console && console.log) {
                console.log('[KHM Attribution]', message, data || '');
            }
        }
    };

    // Auto-initialize when DOM is ready
    $(document).ready(function() {
        if (window.khmAttributionReady) {
            KHMAttributionTracker.init();
        }
    });

    // Global conversion tracking function
    window.khmTrackConversion = function(conversionData) {
        KHMAttributionTracker.trackConversion(conversionData);
    };

})(jQuery);
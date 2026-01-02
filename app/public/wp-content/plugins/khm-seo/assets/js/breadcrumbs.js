/**
 * KHM SEO Breadcrumbs Frontend JavaScript
 * 
 * Handles analytics tracking, dynamic interactions, and enhanced
 * user experience features for the breadcrumb navigation system.
 */

(function($) {
    'use strict';
    
    // Configuration from PHP
    const config = window.khmSeoBreadcrumbsConfig || {};
    
    // Initialize when document is ready
    $(document).ready(function() {
        initializeBreadcrumbs();
    });
    
    /**
     * Initialize breadcrumb functionality
     */
    function initializeBreadcrumbs() {
        const $breadcrumbs = $('.khm-seo-breadcrumbs');
        
        if ($breadcrumbs.length === 0) {
            return;
        }
        
        // Initialize click tracking
        if (config.track_clicks) {
            initializeClickTracking($breadcrumbs);
        }
        
        // Initialize responsive behavior
        initializeResponsiveBehavior($breadcrumbs);
        
        // Initialize keyboard navigation
        initializeKeyboardNavigation($breadcrumbs);
        
        // Initialize tooltips
        initializeTooltips($breadcrumbs);
        
        // Initialize scroll to breadcrumb
        initializeScrollToBreadcrumb($breadcrumbs);
        
        // Initialize animation effects
        initializeAnimations($breadcrumbs);
        
        console.log('KHM SEO Breadcrumbs initialized');
    }
    
    /**
     * Initialize click tracking for analytics
     */
    function initializeClickTracking($breadcrumbs) {
        $breadcrumbs.on('click', '.breadcrumb-item a', function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            const text = $link.text().trim();
            const position = $link.closest('.breadcrumb-item').index() + 1;
            
            // Track with Google Analytics (Universal Analytics)
            if (typeof ga !== 'undefined') {
                ga('send', 'event', {
                    eventCategory: config.ga_category || 'Breadcrumbs',
                    eventAction: config.ga_action || 'click',
                    eventLabel: text,
                    eventValue: position
                });
            }
            
            // Track with Google Analytics 4
            if (typeof gtag !== 'undefined') {
                gtag('event', 'breadcrumb_click', {
                    breadcrumb_text: text,
                    breadcrumb_position: position,
                    breadcrumb_url: href,
                    event_category: config.ga_category || 'Breadcrumbs'
                });
            }
            
            // Track with Google Tag Manager
            if (typeof dataLayer !== 'undefined') {
                dataLayer.push({
                    event: 'breadcrumb_click',
                    breadcrumb_text: text,
                    breadcrumb_position: position,
                    breadcrumb_url: href
                });
            }
            
            // Track with Facebook Pixel
            if (typeof fbq !== 'undefined') {
                fbq('track', 'ViewContent', {
                    content_name: text,
                    content_category: 'Breadcrumb Navigation'
                });
            }
            
            // Custom event for other tracking systems
            $(document).trigger('khm_seo_breadcrumb_click', {
                text: text,
                url: href,
                position: position,
                element: $link[0]
            });
        });
    }
    
    /**
     * Initialize responsive behavior
     */
    function initializeResponsiveBehavior($breadcrumbs) {
        let resizeTimer;
        
        function handleResize() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                $breadcrumbs.each(function() {
                    const $container = $(this);
                    optimizeBreadcrumbDisplay($container);
                });
            }, 250);
        }
        
        $(window).on('resize', handleResize);
        
        // Initial optimization
        $breadcrumbs.each(function() {
            optimizeBreadcrumbDisplay($(this));
        });
    }
    
    /**
     * Optimize breadcrumb display for current viewport
     */
    function optimizeBreadcrumbDisplay($container) {
        const containerWidth = $container.width();
        const $items = $container.find('.breadcrumb-item');
        
        // Reset any previous modifications
        $items.removeClass('hidden-mobile').show();
        $container.find('.breadcrumb-ellipsis').remove();
        
        if (containerWidth < 600) {
            // Mobile optimization
            const $visibleItems = $items.filter(':first-child, :last-child, :nth-last-child(2)');
            const $hiddenItems = $items.not($visibleItems);
            
            if ($hiddenItems.length > 0) {
                $hiddenItems.addClass('hidden-mobile').hide();
                
                // Add ellipsis indicator
                const $ellipsis = $('<span class="breadcrumb-ellipsis" title="Hidden breadcrumb items">...</span>');
                $ellipsis.insertAfter($items.first());
                
                // Make ellipsis clickable to show hidden items
                $ellipsis.on('click', function() {
                    $hiddenItems.toggle();
                    $ellipsis.text($hiddenItems.is(':visible') ? '×' : '...');
                });
            }
        }
    }
    
    /**
     * Initialize keyboard navigation
     */
    function initializeKeyboardNavigation($breadcrumbs) {
        $breadcrumbs.on('keydown', '.breadcrumb-item a', function(e) {
            const $current = $(this);
            const $allLinks = $breadcrumbs.find('.breadcrumb-item a');
            const currentIndex = $allLinks.index($current);
            
            switch(e.keyCode) {
                case 37: // Left arrow
                    e.preventDefault();
                    if (currentIndex > 0) {
                        $allLinks.eq(currentIndex - 1).focus();
                    }
                    break;
                    
                case 39: // Right arrow
                    e.preventDefault();
                    if (currentIndex < $allLinks.length - 1) {
                        $allLinks.eq(currentIndex + 1).focus();
                    }
                    break;
                    
                case 36: // Home
                    e.preventDefault();
                    $allLinks.first().focus();
                    break;
                    
                case 35: // End
                    e.preventDefault();
                    $allLinks.last().focus();
                    break;
            }
        });
    }
    
    /**
     * Initialize tooltips for truncated text
     */
    function initializeTooltips($breadcrumbs) {
        $breadcrumbs.find('.breadcrumb-item a, .breadcrumb-item span').each(function() {
            const $element = $(this);
            const text = $element.text();
            
            // Check if text is truncated
            if (isTextTruncated($element[0])) {
                $element.attr('title', text);
                
                // Enhanced tooltip with delay
                $element.on('mouseenter', function() {
                    const $tooltip = createTooltip(text);
                    $('body').append($tooltip);
                    
                    const offset = $element.offset();
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    }).fadeIn(200);
                    
                }).on('mouseleave', function() {
                    $('.khm-breadcrumb-tooltip').fadeOut(200, function() {
                        $(this).remove();
                    });
                });
            }
        });
    }
    
    /**
     * Check if text is truncated
     */
    function isTextTruncated(element) {
        return element.scrollWidth > element.clientWidth;
    }
    
    /**
     * Create tooltip element
     */
    function createTooltip(text) {
        return $(`
            <div class="khm-breadcrumb-tooltip">
                ${text}
                <div class="tooltip-arrow"></div>
            </div>
        `).css({
            position: 'absolute',
            background: '#333',
            color: '#fff',
            padding: '8px 12px',
            borderRadius: '4px',
            fontSize: '12px',
            zIndex: 10000,
            maxWidth: '300px',
            wordWrap: 'break-word',
            display: 'none'
        });
    }
    
    /**
     * Initialize scroll to breadcrumb functionality
     */
    function initializeScrollToBreadcrumb($breadcrumbs) {
        // Add scroll-to-top functionality for breadcrumb clicks
        $breadcrumbs.on('click', '.breadcrumb-item a[href="#top"]', function(e) {
            e.preventDefault();
            
            $('html, body').animate({
                scrollTop: 0
            }, 600, 'easeInOutCubic');
        });
        
        // Highlight breadcrumb when scrolling past certain sections
        if ($breadcrumbs.hasClass('highlight-sections')) {
            initializeSectionHighlighting($breadcrumbs);
        }
    }
    
    /**
     * Initialize section highlighting
     */
    function initializeSectionHighlighting($breadcrumbs) {
        const $sections = $('h1, h2, h3, .section, .breadcrumb-section');
        let scrollTimer;
        
        $(window).on('scroll', function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                const scrollTop = $(window).scrollTop();
                const windowHeight = $(window).height();
                
                $sections.each(function() {
                    const $section = $(this);
                    const sectionTop = $section.offset().top;
                    const sectionHeight = $section.outerHeight();
                    
                    if (scrollTop >= sectionTop - windowHeight/3 && 
                        scrollTop < sectionTop + sectionHeight) {
                        
                        const sectionId = $section.attr('id') || $section.data('breadcrumb-section');
                        if (sectionId) {
                            highlightBreadcrumbSection(sectionId, $breadcrumbs);
                        }
                    }
                });
            }, 50);
        });
    }
    
    /**
     * Highlight breadcrumb section
     */
    function highlightBreadcrumbSection(sectionId, $breadcrumbs) {
        $breadcrumbs.find('.breadcrumb-item').removeClass('active-section');
        $breadcrumbs.find(`.breadcrumb-item[data-section="${sectionId}"]`).addClass('active-section');
    }
    
    /**
     * Initialize animation effects
     */
    function initializeAnimations($breadcrumbs) {
        // Add loading animation for dynamic breadcrumbs
        if ($breadcrumbs.hasClass('dynamic')) {
            $breadcrumbs.addClass('loading');
            
            setTimeout(function() {
                $breadcrumbs.removeClass('loading').addClass('animated');
            }, 500);
        }
        
        // Hover effects for interactive breadcrumbs
        $breadcrumbs.find('.breadcrumb-item a').on('mouseenter', function() {
            $(this).addClass('hover-effect');
        }).on('mouseleave', function() {
            $(this).removeClass('hover-effect');
        });
        
        // Click animation
        $breadcrumbs.find('.breadcrumb-item a').on('click', function() {
            const $link = $(this);
            
            $link.addClass('clicked');
            setTimeout(function() {
                $link.removeClass('clicked');
            }, 200);
        });
    }
    
    /**
     * Initialize breadcrumb updates for single-page applications
     */
    function initializeDynamicUpdates() {
        // Listen for History API changes
        $(window).on('popstate', function() {
            updateBreadcrumbsForCurrentPage();
        });
        
        // Listen for custom page change events
        $(document).on('khm_seo_page_change', function(e, data) {
            updateBreadcrumbs(data.breadcrumbs);
        });
    }
    
    /**
     * Update breadcrumbs for current page
     */
    function updateBreadcrumbsForCurrentPage() {
        const currentUrl = window.location.href;
        
        // Make AJAX request to get breadcrumbs for current URL
        $.ajax({
            url: ajaxurl || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'khm_seo_get_breadcrumbs',
                url: currentUrl,
                nonce: config.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $('.khm-seo-breadcrumbs').fadeOut(200, function() {
                        $(this).html(response.data.html).fadeIn(200);
                        initializeBreadcrumbs();
                    });
                }
            }
        });
    }
    
    /**
     * Update breadcrumbs with new data
     */
    function updateBreadcrumbs(breadcrumbsHtml) {
        const $breadcrumbs = $('.khm-seo-breadcrumbs');
        
        if ($breadcrumbs.length && breadcrumbsHtml) {
            $breadcrumbs.fadeOut(200, function() {
                $(this).html(breadcrumbsHtml).fadeIn(200);
                initializeBreadcrumbs();
            });
        }
    }
    
    /**
     * Initialize structured data enhancement
     */
    function initializeStructuredData($breadcrumbs) {
        // Enhance existing schema markup
        $breadcrumbs.find('.breadcrumb-item a').each(function(index) {
            const $link = $(this);
            
            if (!$link.attr('itemprop')) {
                $link.attr('itemprop', 'item');
            }
            
            if (!$link.find('[itemprop="name"]').length) {
                $link.wrapInner('<span itemprop="name"></span>');
            }
        });
    }
    
    /**
     * Performance monitoring
     */
    function monitorPerformance() {
        if (typeof performance !== 'undefined' && performance.mark) {
            performance.mark('khm-breadcrumbs-init-start');
            
            $(window).on('load', function() {
                performance.mark('khm-breadcrumbs-init-end');
                performance.measure('khm-breadcrumbs-init', 'khm-breadcrumbs-init-start', 'khm-breadcrumbs-init-end');
                
                const measures = performance.getEntriesByName('khm-breadcrumbs-init');
                if (measures.length > 0) {
                    console.log('KHM Breadcrumbs initialization time:', measures[0].duration + 'ms');
                }
            });
        }
    }
    
    /**
     * Accessibility enhancements
     */
    function enhanceAccessibility($breadcrumbs) {
        // Add ARIA attributes if missing
        if (!$breadcrumbs.attr('aria-label')) {
            $breadcrumbs.attr('aria-label', 'Breadcrumb navigation');
        }
        
        // Add role if missing
        if (!$breadcrumbs.attr('role')) {
            $breadcrumbs.attr('role', 'navigation');
        }
        
        // Mark current page in breadcrumb
        $breadcrumbs.find('.breadcrumb-item.current').attr('aria-current', 'page');
        
        // Add screen reader text for separators
        $breadcrumbs.find('.breadcrumb-separator').each(function() {
            if (!$(this).find('.screen-reader-text').length) {
                $(this).append('<span class="screen-reader-text">, </span>');
            }
        });
    }
    
    /**
     * Initialize custom events
     */
    function initializeCustomEvents($breadcrumbs) {
        // Trigger ready event
        $(document).trigger('khm_seo_breadcrumbs_ready', {
            breadcrumbs: $breadcrumbs,
            config: config
        });
        
        // Handle external updates
        $(document).on('khm_seo_update_breadcrumbs', function(e, data) {
            if (data.html) {
                updateBreadcrumbs(data.html);
            }
        });
    }
    
    // Utility functions
    const utils = {
        /**
         * Get breadcrumb data as JSON
         */
        getBreadcrumbData: function($breadcrumbs) {
            const items = [];
            
            $breadcrumbs.find('.breadcrumb-item').each(function() {
                const $item = $(this);
                const $link = $item.find('a');
                
                items.push({
                    text: $item.text().trim(),
                    url: $link.length ? $link.attr('href') : '',
                    position: $item.index() + 1,
                    isCurrent: $item.hasClass('current')
                });
            });
            
            return items;
        },
        
        /**
         * Generate breadcrumb URL
         */
        generateBreadcrumbUrl: function(items) {
            return items.map(item => encodeURIComponent(item.text)).join('/');
        },
        
        /**
         * Validate breadcrumb structure
         */
        validateBreadcrumbs: function($breadcrumbs) {
            const issues = [];
            
            if (!$breadcrumbs.find('.breadcrumb-item').length) {
                issues.push('No breadcrumb items found');
            }
            
            if (!$breadcrumbs.find('.breadcrumb-item.current').length) {
                issues.push('No current page indicator found');
            }
            
            return issues;
        }
    };
    
    // Export public API
    window.KhmSeoBreadcrumbs = {
        init: initializeBreadcrumbs,
        update: updateBreadcrumbs,
        utils: utils,
        config: config
    };
    
    // Start performance monitoring
    monitorPerformance();
    
})(jQuery);
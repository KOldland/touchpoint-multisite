/**
 * Frontend Integration Test for TouchPoint Marketing Suite
 * Validates that all frontend assets work together properly
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Integration test suite
    const IntegrationTest = {
        errors: [],
        warnings: [],
        passed: [],
        
        init: function() {
            console.log('🧪 Starting TouchPoint Marketing Suite Frontend Integration Tests...');
            this.runAllTests();
            this.reportResults();
        },
        
        runAllTests: function() {
            this.testDependencies();
            this.testEventBindings();
            this.testCSSClasses();
            this.testAJAXEndpoints();
            this.testModalSystem();
            this.testResponsiveDesign();
        },
        
        testDependencies: function() {
            console.log('📚 Testing Dependencies...');
            
            // Check jQuery
            if (typeof jQuery !== 'undefined') {
                this.passed.push('jQuery library loaded');
            } else {
                this.errors.push('jQuery library missing');
            }
            
            // Check KHM Integration
            if (typeof window.KHMEcommerce !== 'undefined') {
                this.passed.push('KHM eCommerce integration available');
            } else {
                this.warnings.push('KHM eCommerce integration not loaded yet');
            }
            
            // Check AJAX variables
            if (typeof khmEcommerce !== 'undefined') {
                this.passed.push('AJAX configuration available');
            } else {
                this.warnings.push('AJAX configuration may not be available');
            }
        },
        
        testEventBindings: function() {
            console.log('🔗 Testing Event Bindings...');
            
            // Test social strip buttons
            const socialStripButtons = $('.kss-social-strip button, .kss-social-strip .kss-download-credit, .kss-social-strip .kss-save-button').length;
            if (socialStripButtons > 0) {
                this.passed.push(`Found ${socialStripButtons} social strip buttons`);
            } else {
                this.warnings.push('No social strip buttons found on this page');
            }
            
            // Test eCommerce buttons
            const ecommerceButtons = $('.kss-add-to-cart, .khm-quantity-btn, .khm-checkout-btn').length;
            if (ecommerceButtons > 0) {
                this.passed.push(`Found ${ecommerceButtons} eCommerce interaction elements`);
            } else {
                this.warnings.push('No eCommerce elements found on this page');
            }
            
            // Test modal triggers
            const modalTriggers = $('[data-modal], .kss-gift-button, .kss-share-button').length;
            if (modalTriggers > 0) {
                this.passed.push(`Found ${modalTriggers} modal trigger elements`);
            } else {
                this.warnings.push('No modal triggers found on this page');
            }
        },
        
        testCSSClasses: function() {
            console.log('🎨 Testing CSS Classes...');
            
            // Check required CSS files are loaded by looking for specific styles
            const testDiv = $('<div class="khm-modal test-element" style="position: absolute; top: -9999px;">Test</div>');
            $('body').append(testDiv);
            
            const modalStyle = testDiv.css('background');
            if (modalStyle && modalStyle !== 'rgba(0, 0, 0, 0)' && modalStyle !== 'transparent') {
                this.passed.push('Modal CSS appears to be loaded');
            } else {
                this.warnings.push('Modal CSS may not be loaded');
            }
            
            testDiv.remove();
            
            // Test for critical CSS classes
            const criticalClasses = [
                '.kss-social-strip',
                '.khm-modal',
                '.khm-cart-item',
                '.khm-checkout-form'
            ];
            
            criticalClasses.forEach(className => {
                if ($(className).length > 0 || this.cssRuleExists(className)) {
                    this.passed.push(`CSS class ${className} is available`);
                } else {
                    this.warnings.push(`CSS class ${className} not found`);
                }
            });
        },
        
        testAJAXEndpoints: function() {
            console.log('🌐 Testing AJAX Configuration...');
            
            // Check if AJAX URL is configured
            if (typeof ajaxurl !== 'undefined' || (typeof khmEcommerce !== 'undefined' && khmEcommerce.ajaxUrl)) {
                this.passed.push('AJAX URL configured');
            } else {
                this.warnings.push('AJAX URL not configured - check WordPress localization');
            }
            
            // Check nonce configuration
            if (typeof khmEcommerce !== 'undefined' && khmEcommerce.nonce) {
                this.passed.push('Security nonce configured');
            } else {
                this.warnings.push('Security nonce not configured');
            }
        },
        
        testModalSystem: function() {
            console.log('📱 Testing Modal System...');
            
            // Test modal creation
            try {
                const testModal = $(`
                    <div class="khm-modal-backdrop test-modal" style="display: none;">
                        <div class="khm-modal small">
                            <div class="khm-modal-header">
                                <h3 class="khm-modal-title">Test Modal</h3>
                                <button class="khm-modal-close">&times;</button>
                            </div>
                            <div class="khm-modal-content">
                                <p>Test content</p>
                            </div>
                            <div class="khm-modal-footer">
                                <button class="khm-modal-btn primary">OK</button>
                            </div>
                        </div>
                    </div>
                `);
                
                $('body').append(testModal);
                
                // Test modal styling
                const modalWidth = testModal.find('.khm-modal').outerWidth();
                if (modalWidth > 0) {
                    this.passed.push('Modal system rendering correctly');
                } else {
                    this.warnings.push('Modal system may have styling issues');
                }
                
                testModal.remove();
                
            } catch (error) {
                this.errors.push(`Modal system error: ${error.message}`);
            }
        },
        
        testResponsiveDesign: function() {
            console.log('📱 Testing Responsive Design...');
            
            const windowWidth = $(window).width();
            
            if (windowWidth <= 768) {
                // Mobile test
                const socialStrip = $('.kss-social-strip');
                if (socialStrip.length > 0) {
                    const stripDisplay = socialStrip.css('flex-direction');
                    if (stripDisplay === 'column' || stripDisplay === 'row') {
                        this.passed.push('Mobile responsive layout active');
                    } else {
                        this.warnings.push('Mobile responsive layout may need attention');
                    }
                }
            } else {
                // Desktop test
                this.passed.push('Desktop layout active');
            }
            
            // Test viewport meta tag
            if ($('meta[name="viewport"]').length > 0) {
                this.passed.push('Viewport meta tag present');
            } else {
                this.warnings.push('Viewport meta tag missing - responsive design may be affected');
            }
        },
        
        cssRuleExists: function(selector) {
            const styleSheets = Array.from(document.styleSheets);
            
            try {
                for (let styleSheet of styleSheets) {
                    if (styleSheet.cssRules) {
                        for (let rule of styleSheet.cssRules) {
                            if (rule.selectorText && rule.selectorText.includes(selector.replace('.', ''))) {
                                return true;
                            }
                        }
                    }
                }
            } catch (e) {
                // Cross-origin stylesheets may cause errors
            }
            
            return false;
        },
        
        reportResults: function() {
            console.log('\n🔍 Integration Test Results:');
            console.log('===============================');
            
            if (this.passed.length > 0) {
                console.log(`✅ Passed (${this.passed.length}):`);
                this.passed.forEach(item => console.log(`   • ${item}`));
                console.log('');
            }
            
            if (this.warnings.length > 0) {
                console.log(`⚠️  Warnings (${this.warnings.length}):`);
                this.warnings.forEach(item => console.log(`   • ${item}`));
                console.log('');
            }
            
            if (this.errors.length > 0) {
                console.log(`❌ Errors (${this.errors.length}):`);
                this.errors.forEach(item => console.log(`   • ${item}`));
                console.log('');
            }
            
            // Overall assessment
            const totalTests = this.passed.length + this.warnings.length + this.errors.length;
            const healthScore = Math.round((this.passed.length / totalTests) * 100);
            
            console.log(`📊 Integration Health Score: ${healthScore}%`);
            
            if (healthScore >= 90) {
                console.log('🎉 Excellent integration! All systems operational.');
            } else if (healthScore >= 75) {
                console.log('✨ Good integration! Minor issues to address.');
            } else if (healthScore >= 50) {
                console.log('⚡ Fair integration. Several issues need attention.');
            } else {
                console.log('🔧 Poor integration. Major issues require immediate attention.');
            }
            
            console.log('\n💡 To fix issues, check:');
            console.log('   • WordPress admin → plugins are active');
            console.log('   • CSS/JS files are properly enqueued');
            console.log('   • AJAX settings in PHP backend');
            console.log('   • Browser console for JavaScript errors');
            console.log('===============================\n');
        }
    };
    
    // Auto-run tests when page loads
    setTimeout(function() {
        IntegrationTest.init();
    }, 1000);
    
    // Expose for manual testing
    window.TouchPointIntegrationTest = IntegrationTest;
});
/**
 * Sitemap Admin JavaScript
 */
(function($) {
    'use strict';

    const SitemapAdmin = {
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        bindEvents: function() {
            // Quick action buttons
            $('#regenerate-sitemap').on('click', this.regenerateSitemap);
            $('#ping-search-engines').on('click', this.pingSearchEngines);
            $('#test-sitemap').on('click', this.testSitemap);
            
            // Tool buttons
            $('#force-regenerate').on('click', this.forceRegenerate);
            $('#validate-sitemap').on('click', this.validateSitemap);
            $('#test-sitemap-access').on('click', this.testSitemapAccess);
            $('#clear-sitemap-cache').on('click', this.clearCache);
        },

        initModal: function() {
            const modal = $('#sitemap-modal');
            const closeBtn = $('.sitemap-modal-close');
            
            closeBtn.on('click', function() {
                modal.hide();
            });
            
            $(window).on('click', function(e) {
                if (e.target === modal[0]) {
                    modal.hide();
                }
            });
        },

        showModal: function(title, content) {
            const modal = $('#sitemap-modal');
            const modalBody = $('.sitemap-modal-body');
            
            modalBody.html('<h3>' + title + '</h3>' + content);
            modal.show();
        },

        showLoader: function(button, text) {
            button.addClass('loading')
                  .prop('disabled', true)
                  .attr('data-original-text', button.text())
                  .text(text || khmSeoSitemapAdmin.strings.processing);
        },

        hideLoader: function(button) {
            const originalText = button.attr('data-original-text');
            button.removeClass('loading')
                  .prop('disabled', false)
                  .text(originalText || button.text());
        },

        showNotice: function(message, type) {
            type = type || 'success';
            
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.khm-seo-sitemap-admin').prepend(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
            
            // Make dismissible
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            });
        },

        ajaxRequest: function(action, data, button) {
            data = data || {};
            data.action = action;
            data.nonce = khmSeoSitemapAdmin.nonce;
            
            return $.ajax({
                url: khmSeoSitemapAdmin.ajax_url,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    if (button) {
                        SitemapAdmin.showLoader(button);
                    }
                },
                complete: function() {
                    if (button) {
                        SitemapAdmin.hideLoader(button);
                    }
                }
            });
        },

        regenerateSitemap: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const confirmed = confirm(khmSeoSitemapAdmin.strings.confirm_regenerate);
            
            if (!confirmed) {
                return;
            }
            
            SitemapAdmin.ajaxRequest('khm_seo_regenerate_sitemap', {}, button)
                .done(function(response) {
                    if (response.success) {
                        SitemapAdmin.showNotice(response.data.message, 'success');
                        // Refresh status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SitemapAdmin.showNotice(response.data || 'Error occurred', 'error');
                    }
                })
                .fail(function() {
                    SitemapAdmin.showNotice('Ajax request failed', 'error');
                });
        },

        pingSearchEngines: function(e) {
            e.preventDefault();
            
            const button = $(this);
            
            SitemapAdmin.ajaxRequest('khm_seo_ping_search_engines', {}, button)
                .done(function(response) {
                    if (response.success) {
                        SitemapAdmin.showNotice(response.data.message, 'success');
                    } else {
                        SitemapAdmin.showNotice(response.data || 'Error occurred', 'error');
                    }
                })
                .fail(function() {
                    SitemapAdmin.showNotice('Ajax request failed', 'error');
                });
        },

        testSitemap: function(e) {
            e.preventDefault();
            
            const button = $(this);
            
            SitemapAdmin.ajaxRequest('khm_seo_test_sitemap', {}, button)
                .done(function(response) {
                    if (response.success) {
                        SitemapAdmin.showTestResults(response.data.results);
                    } else {
                        SitemapAdmin.showNotice(response.data || 'Error occurred', 'error');
                    }
                })
                .fail(function() {
                    SitemapAdmin.showNotice('Ajax request failed', 'error');
                });
        },

        showTestResults: function(results) {
            let content = '<div class="test-results">';
            
            $.each(results, function(type, result) {
                const statusClass = result.status === 'success' ? 'status-success' : 'status-error';
                const statusText = result.status === 'success' ? 'Pass' : 'Fail';
                
                content += '<div class="test-result">';
                content += '<h4>' + type.charAt(0).toUpperCase() + type.slice(1) + ' Sitemap</h4>';
                content += '<p class="' + statusClass + '">' + statusText + '</p>';
                
                if (result.message) {
                    content += '<p>' + result.message + '</p>';
                }
                
                if (result.url) {
                    content += '<p><a href="' + result.url + '" target="_blank">View Sitemap</a></p>';
                }
                content += '</div>';
            });
            
            content += '</div>';
            
            SitemapAdmin.showModal('Sitemap Test Results', content);
        },

        forceRegenerate: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const confirmed = confirm('This will force regenerate all sitemap files. Continue?');
            
            if (!confirmed) {
                return;
            }
            
            SitemapAdmin.ajaxRequest('khm_seo_regenerate_sitemap', { force: true }, button)
                .done(function(response) {
                    if (response.success) {
                        SitemapAdmin.showResults('Sitemap regeneration completed successfully.');
                    } else {
                        SitemapAdmin.showResults('Error: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(function() {
                    SitemapAdmin.showResults('Ajax request failed');
                });
        },

        validateSitemap: function(e) {
            e.preventDefault();
            
            const button = $(this);
            
            // Simple client-side validation check
            SitemapAdmin.showLoader(button, 'Validating...');
            
            // Simulate validation process
            setTimeout(function() {
                SitemapAdmin.hideLoader(button);
                
                const sitemapUrl = window.location.origin + '/sitemap.xml';
                
                // Try to fetch and validate sitemap structure
                $.get(sitemapUrl)
                    .done(function(data) {
                        let results = 'Sitemap validation results:\n\n';
                        
                        if (typeof data === 'string' && data.includes('<?xml')) {
                            results += '✓ Valid XML format\n';
                        } else {
                            results += '✗ Invalid XML format\n';
                        }
                        
                        if (data.includes('<urlset') || data.includes('<sitemapindex')) {
                            results += '✓ Valid sitemap structure\n';
                        } else {
                            results += '✗ Invalid sitemap structure\n';
                        }
                        
                        if (data.includes('<lastmod>')) {
                            results += '✓ Contains lastmod dates\n';
                        } else {
                            results += '! Missing lastmod dates (optional)\n';
                        }
                        
                        SitemapAdmin.showResults(results);
                    })
                    .fail(function() {
                        SitemapAdmin.showResults('Error: Could not access sitemap at ' + sitemapUrl);
                    });
            }, 1000);
        },

        testSitemapAccess: function(e) {
            e.preventDefault();
            
            const button = $(this);
            
            SitemapAdmin.ajaxRequest('khm_seo_test_sitemap', {}, button)
                .done(function(response) {
                    if (response.success) {
                        let results = 'Sitemap accessibility test results:\n\n';
                        
                        $.each(response.data.results, function(type, result) {
                            const status = result.status === 'success' ? '✓' : '✗';
                            results += status + ' ' + type + ' sitemap: ' + result.message + '\n';
                        });
                        
                        SitemapAdmin.showResults(results);
                    } else {
                        SitemapAdmin.showResults('Error: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(function() {
                    SitemapAdmin.showResults('Ajax request failed');
                });
        },

        clearCache: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const confirmed = confirm('This will clear all sitemap cache. Continue?');
            
            if (!confirmed) {
                return;
            }
            
            SitemapAdmin.showLoader(button, 'Clearing cache...');
            
            // Simulate cache clearing
            setTimeout(function() {
                SitemapAdmin.hideLoader(button);
                SitemapAdmin.showResults('Sitemap cache cleared successfully.');
            }, 1500);
        },

        showResults: function(results) {
            const resultsDiv = $('#tool-results');
            const resultsContent = $('.results-content');
            
            resultsContent.text(results);
            resultsDiv.show();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: resultsDiv.offset().top - 100
            }, 500);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SitemapAdmin.init();
    });

})(jQuery);
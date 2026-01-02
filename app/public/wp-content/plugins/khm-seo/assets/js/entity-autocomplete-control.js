/**
 * Entity Autocomplete Control for Elementor
 *
 * Custom control for entity selection with autocomplete in Elementor editor.
 *
 * @package KHM_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Register the control view
    elementor.addControlView('khm_entity_autocomplete', {

        onReady: function() {
            this.initializeAutocomplete();
        },

        initializeAutocomplete: function() {
            const self = this;
            const $input = this.$el.find('.khm-entity-autocomplete-input');
            const $value = this.$el.find('.khm-entity-autocomplete-value');
            const $results = this.$el.find('.khm-entity-autocomplete-results');

            let searchTimeout;

            $input.on('input', function() {
                const query = $(this).val().trim();

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $results.hide();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    self.searchEntities(query, $results, $value, $input);
                }, 300);
            });

            // Handle result selection
            $results.on('click', '.khm-entity-result', function() {
                const $result = $(this);
                const entityId = $result.data('entity-id');
                const entityText = $result.text();

                $input.val(entityText);
                $value.val(entityId);
                $results.hide();

                // Update Elementor control value
                self.setValue(entityId);
                self.applySavedValue();
            });

            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.khm-entity-autocomplete-wrapper').length) {
                    $results.hide();
                }
            });
        },

        searchEntities: function(query, $results, $value, $input) {
            const self = this;

            const ajaxUrl = (window.elementor && elementor.config && elementor.config.ajaxurl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : (window.khmGeoElementor && khmGeoElementor.ajax_url));
            const nonce = (window.elementor && elementor.config && elementor.config.nonce) ||
                          (window.elementorCommonConfig && elementorCommonConfig.nonce) ||
                          (window.khmGeoElementor && khmGeoElementor.nonce) ||
                          (window._wpnonce || '');

            if ( ! ajaxUrl || ! nonce ) {
                $results.html('<div class="khm-search-error">Search unavailable</div>').show();
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_geo_entity_search',
                    search: query,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        self.displaySearchResults(response.data, $results);
                    } else {
                        $results.html('<div class="khm-no-results">No entities found</div>').show();
                    }
                },
                error: function() {
                    $results.html('<div class="khm-search-error">Search error</div>').show();
                }
            });
        },

        displaySearchResults: function(entities, $results) {
            let html = '';

            entities.forEach(function(entity) {
                html += '<div class="khm-entity-result" data-entity-id="' + entity.id + '">' +
                    '<span class="khm-entity-text">' + entity.text + '</span>' +
                    '<span class="khm-entity-type">' + entity.type + '</span>' +
                    '</div>';
            });

            $results.html(html).show();
        },

        onBeforeDestroy: function() {
            this.$el.find('.khm-entity-autocomplete-input').off();
            this.$el.find('.khm-entity-autocomplete-results').off();
        }
    });

})(jQuery);

/**
 * Elementor Editor Integration for KHM GEO
 *
 * Handles entity autocomplete and widget interactions in Elementor editor.
 *
 * @package KHM_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize entity autocomplete when Elementor is ready
        if (typeof elementor !== 'undefined') {
            elementor.on('panel:init', function() {
                initializeEntityAutocomplete();
            });
        }
    });

    /**
     * Initialize entity autocomplete functionality
     */
    function initializeEntityAutocomplete() {
        $('.khm-entity-autocomplete-input').each(function() {
            const $input = $(this);
            const $wrapper = $input.closest('.khm-entity-autocomplete-wrapper');
            const $results = $wrapper.find('.khm-entity-autocomplete-results');
            const $value = $wrapper.find('.khm-entity-autocomplete-value');

            let searchTimeout;

            $input.on('input', function() {
                const query = $(this).val().trim();

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $results.hide();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    searchEntities(query, $results, $value, $input);
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

                // Trigger Elementor control change
                $value.trigger('input');
            });

            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.khm-entity-autocomplete-wrapper').length) {
                    $results.hide();
                }
            });
        });
    }

    /**
     * Search entities via AJAX
     */
    function searchEntities(query, $results, $value, $input) {
        $.ajax({
            url: khmGeoElementor.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_geo_entity_search',
                search: query,
                nonce: khmGeoElementor.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displaySearchResults(response.data, $results);
                } else {
                    $results.html('<div class="khm-no-results">' + khmGeoElementor.strings.no_entities_found + '</div>').show();
                }
            },
            error: function() {
                $results.html('<div class="khm-search-error">' + khmGeoElementor.strings.search_error + '</div>').show();
            }
        });
    }

    /**
     * Display search results
     */
    function displaySearchResults(entities, $results) {
        let html = '';

        entities.forEach(function(entity) {
            html += '<div class="khm-entity-result" data-entity-id="' + entity.id + '">' +
                '<span class="khm-entity-text">' + entity.text + '</span>' +
                '<span class="khm-entity-type">' + entity.type + '</span>' +
                '</div>';
        });

        $results.html(html).show();
    }

})(jQuery);
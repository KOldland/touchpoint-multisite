// GEO Admin JS: Handles quick alias add/remove in entity list
jQuery(document).ready(function($) {
    // Remove alias
    $(document).on('click', '.khm-remove-alias', function() {
        var entityId = $(this).data('entity-id');
        var alias = $(this).data('alias');
        var row = $(this).closest('li');
        if (confirm('Remove alias "' + alias + '"?')) {
            $.post(ajaxurl, {
                action: 'khm_geo_remove_alias',
                entity_id: entityId,
                alias: alias,
                nonce: khmGeoAdmin.nonce
            }, function(resp) {
                if (resp.success) {
                    row.fadeOut(200, function() { $(this).remove(); });
                } else {
                    alert('Error: ' + resp.data);
                }
            });
        }
    });
    // Add alias
    $(document).on('click', '.khm-add-alias', function() {
        var entityId = $(this).data('entity-id');
        var alias = prompt('Enter new alias:');
        if (alias && alias.trim().length > 0) {
            $.post(ajaxurl, {
                action: 'khm_geo_add_alias',
                entity_id: entityId,
                alias: alias,
                nonce: khmGeoAdmin.nonce
            }, function(resp) {
                if (resp.success) {
                    location.reload();
                } else {
                    alert('Error: ' + resp.data);
                }
            });
        }
    });
    // Select All checkbox
    $(document).on('change', '#cb-select-all-1', function() {
        var checked = $(this).is(':checked');
        $('input[name="entity_ids[]"]').prop('checked', checked);
    });

    // Intercept bulk action form submit and perform AJAX
    $(document).on('submit', '.khm-geo-bulk-actions form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var action = $form.find('select[name="bulk_action"]').val();
        var $notice = $('#khm-geo-notification');
        if (!action) {
            $notice.removeClass('notice-success').addClass('notice-error').text('Please choose a bulk action').show();
            return;
        }
        var ids = [];
        $form.find('input[name="entity_ids[]"]:checked').each(function() {
            ids.push($(this).val());
        });
        if (ids.length === 0) {
            $notice.removeClass('notice-success').addClass('notice-error').text('Please select at least one entity.').show();
            return;
        }
        if (action === 'delete' && !confirm('Are you sure you want to delete the selected entities?')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'khm_geo_bulk_action_ajax',
            bulk_action: action,
            entity_ids: ids,
            nonce: khmGeoAdmin.nonce
        }, function(resp) {
            if (resp.success) {
                $notice.removeClass('notice-error').addClass('notice-success').text(resp.data.message || 'Bulk action completed.').show();
                // Optionally update rows in-place
                if (resp.data.updated_ids && resp.data.updated_ids.length) {
                    resp.data.updated_ids.forEach(function(id) {
                        // For delete, remove row
                        if (action === 'delete') {
                            $('#entity-' + id).fadeOut(200, function(){ $(this).remove(); });
                        } else if (action === 'deprecate') {
                            $('#entity-' + id).addClass('khm-deprecated');
                            $('#entity-' + id + ' .column-status').html('<span class="khm-status khm-status-deprecated"><span class="dashicons dashicons-warning" aria-hidden="true"></span>Deprecated</span>');
                        } else if (action === 'activate') {
                            $('#entity-' + id).removeClass('khm-deprecated');
                            $('#entity-' + id + ' .column-status').html('<span class="khm-status khm-status-active"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>Active</span>');
                        }
                    });
                }
            } else {
                $notice.removeClass('notice-success').addClass('notice-error').text('Bulk action failed: ' + (resp.data || 'Unknown error')).show();
            }
        });
    });
});

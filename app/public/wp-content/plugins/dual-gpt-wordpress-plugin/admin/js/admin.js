/**
 * Dual-GPT Admin JavaScript
 */

jQuery(document).ready(function($) {

    // Test API connection with enhanced error handling
    $('#test-api-connection').on('click', function() {
        const button = $(this);
        const resultDiv = $('#api-test-result');

        button.prop('disabled', true).text('Testing...');
        resultDiv.html('<p>Testing API connection...</p>');

        $.ajax({
            url: dualGptAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dual_gpt_test_api',
                nonce: dualGptAdmin.nonce
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>✓ API connection successful!</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>✗ API connection failed: ' + (response.data.message || 'Unknown error') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';

                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please check your internet connection.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }

                resultDiv.html('<div class="notice notice-error"><p>✗ ' + errorMessage + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('Test OpenAI API Connection');
            }
        });
    });

    // Preset management
    let currentPresetId = null;

    $('#add-preset').on('click', function() {
        currentPresetId = null;
        $('#modal-title').text('Add Preset');
        $('#preset-form')[0].reset();
        $('#preset-modal').show();
    });

    $('.edit-preset').on('click', function() {
        currentPresetId = $(this).data('id');
        $('#modal-title').text('Edit Preset');

        // Show loading state
        $('#preset-modal .modal-body').append('<p class="loading">Loading preset data...</p>');

        // Load preset data with error handling
        $.ajax({
            url: dualGptAdmin.restUrl + 'presets/' + currentPresetId,
            method: 'GET',
            timeout: 10000,
            success: function(preset) {
                $('#preset-id').val(preset.id);
                $('#preset-name').val(preset.name);
                $('#preset-role').val(preset.role);
                $('#preset-model').val(preset.default_model);
                $('#preset-prompt').val(preset.system_prompt);
                $('#preset-modal .loading').remove();
                $('#preset-modal').show();
            },
            error: function(xhr, status, error) {
                $('#preset-modal .loading').remove();
                let errorMessage = 'Failed to load preset data';

                if (xhr.status === 404) {
                    errorMessage = 'Preset not found';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out';
                }

                alert('Error: ' + errorMessage);
            }
        });
    });

    $('.delete-preset').on('click', function() {
        if (!confirm('Are you sure you want to delete this preset?')) {
            return;
        }

        const presetId = $(this).data('id');
        const button = $(this);

        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: dualGptAdmin.restUrl + 'presets/' + presetId,
            method: 'DELETE',
            timeout: 10000,
            success: function() {
                location.reload();
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text('Delete');
                let errorMessage = 'Failed to delete preset';

                if (xhr.status === 403) {
                    errorMessage = 'Cannot delete this preset (may be locked)';
                } else if (xhr.status === 404) {
                    errorMessage = 'Preset not found';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out';
                }

                alert('Error: ' + errorMessage);
            }
        });
    });

    $('#preset-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            name: $('#preset-name').val().trim(),
            role: $('#preset-role').val(),
            default_model: $('#preset-model').val(),
            system_prompt: $('#preset-prompt').val().trim()
        };

        // Client-side validation
        if (!formData.name) {
            alert('Preset name is required');
            $('#preset-name').focus();
            return;
        }

        if (!formData.system_prompt) {
            alert('System prompt is required');
            $('#preset-prompt').focus();
            return;
        }

        if (formData.system_prompt.length > 10000) {
            alert('System prompt is too long (maximum 10,000 characters)');
            $('#preset-prompt').focus();
            return;
        }

        const submitButton = $(this).find('button[type="submit"]');
        const originalText = submitButton.text();

        submitButton.prop('disabled', true).text('Saving...');

        const method = currentPresetId ? 'PUT' : 'POST';
        const url = currentPresetId ?
            dualGptAdmin.restUrl + 'presets/' + currentPresetId :
            dualGptAdmin.restUrl + 'presets';

        $.ajax({
            url: url,
            method: method,
            data: JSON.stringify(formData),
            contentType: 'application/json',
            timeout: 15000,
            success: function(response) {
                $('#preset-modal').hide();
                location.reload();
            },
            error: function(xhr, status, error) {
                submitButton.prop('disabled', false).text(originalText);

                let errorMessage = 'Failed to save preset';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Invalid data provided';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied';
                } else if (xhr.status === 409) {
                    errorMessage = 'Preset name already exists';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                }

                alert('Error: ' + errorMessage);
            }
        });
    });

    // Budget management
    $('#add-budget').on('click', function() {
        // Simple budget creation - in a real implementation you'd have a modal
        const scope = prompt('Scope (site/user/role):');
        const scopeId = prompt('Scope ID:');
        const tokenLimit = prompt('Token limit:');

        if (scope && scopeId && tokenLimit) {
            $.ajax({
                url: dualGptAdmin.restUrl + 'budgets',
                method: 'POST',
                data: JSON.stringify({
                    scope: scope,
                    scope_id: scopeId,
                    token_limit: parseInt(tokenLimit)
                }),
                contentType: 'application/json',
                success: function() {
                    location.reload();
                }
            });
        }
    });

    $('.edit-budget').on('click', function() {
        const budgetId = $(this).data('id');
        // Implement budget editing
        alert('Budget editing not implemented in this demo');
    });

    $('.delete-budget').on('click', function() {
        if (!confirm('Are you sure you want to delete this budget?')) {
            return;
        }

        const budgetId = $(this).data('id');
        // Implement budget deletion
        alert('Budget deletion not implemented in this demo');
    });

});
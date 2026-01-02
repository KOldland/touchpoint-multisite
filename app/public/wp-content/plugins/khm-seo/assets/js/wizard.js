/**
 * KHM SEO Setup Wizard JavaScript
 * 
 * Handles wizard navigation, form validation, AJAX submissions,
 * and user interface interactions for the setup wizard.
 */

(function($) {
    'use strict';
    
    // Wizard state management
    let wizardState = {
        currentStep: 'welcome',
        stepData: {},
        isProcessing: false,
        setupType: 'full'
    };
    
    // Initialize wizard when document is ready
    $(document).ready(function() {
        initializeWizard();
        bindEvents();
        updateProgress();
    });
    
    /**
     * Initialize wizard interface
     */
    function initializeWizard() {
        // Load initial state from localized data
        if (typeof khmSeoWizard !== 'undefined') {
            wizardState.currentStep = khmSeoWizard.current_step || 'welcome';
            wizardState.stepData = khmSeoWizard.wizard_data || {};
        }
        
        // Setup initial UI state
        updateStepNavigation();
        updateNavigationButtons();
        
        // Initialize form elements
        initializeFormElements();
        
        console.log('KHM SEO Wizard initialized');
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Navigation buttons
        $('#wizard-next-btn').on('click', handleNextStep);
        $('#wizard-prev-btn').on('click', handlePrevStep);
        $('#wizard-skip-btn').on('click', handleSkipStep);
        
        // Setup type selection
        $('.setup-option-btn').on('click', handleSetupTypeSelection);
        
        // Form field changes
        $(document).on('change', '.wizard-step-content input, .wizard-step-content select', handleFormFieldChange);
        $(document).on('input', '.wizard-step-content input[type="text"], .wizard-step-content textarea', handleFormFieldChange);
        
        // Site type selection
        $(document).on('change', 'input[name="site_type"]', handleSiteTypeChange);
        
        // Step navigation clicks
        $('.step-nav-item').on('click', handleStepNavClick);
        
        // Plugin detection and import
        $(document).on('click', '#detect-plugins-btn', handlePluginDetection);
        $(document).on('click', '.import-plugin-btn', handlePluginImport);
        
        // Form validation on blur
        $(document).on('blur', '.wizard-step-content input[required], .wizard-step-content select[required]', validateField);
        
        // Keyboard navigation
        $(document).on('keydown', handleKeyboardNavigation);
        
        // Prevent accidental page leave during setup
        $(window).on('beforeunload', handleBeforeUnload);
    }
    
    /**
     * Initialize form elements
     */
    function initializeFormElements() {
        // Initialize radio button states
        $('.site-type-option input:checked').closest('.site-type-option').addClass('checked');
        
        // Initialize business info visibility
        toggleBusinessInfo();
        
        // Populate form fields with existing data
        populateFormFields();
        
        // Initialize tooltips
        initializeTooltips();
    }
    
    /**
     * Handle next step navigation
     */
    function handleNextStep() {
        if (wizardState.isProcessing) return;
        
        const currentStepData = collectCurrentStepData();
        
        // Validate current step
        if (!validateCurrentStep(currentStepData)) {
            showValidationErrors();
            return;
        }
        
        // Save step data and move to next step
        saveStepData(wizardState.currentStep, currentStepData, 'next');
    }
    
    /**
     * Handle previous step navigation
     */
    function handlePrevStep() {
        if (wizardState.isProcessing) return;
        
        const currentStepData = collectCurrentStepData();
        
        // Save step data and move to previous step (no validation required)
        saveStepData(wizardState.currentStep, currentStepData, 'prev');
    }
    
    /**
     * Handle skip step
     */
    function handleSkipStep() {
        if (wizardState.isProcessing) return;
        
        // Move to next step without saving data
        saveStepData(wizardState.currentStep, {}, 'next');
    }
    
    /**
     * Handle setup type selection
     */
    function handleSetupTypeSelection(e) {
        e.preventDefault();
        
        const setupType = $(this).data('setup-type');
        wizardState.setupType = setupType;
        
        // Highlight selected option
        $('.setup-option').removeClass('selected');
        $(this).closest('.setup-option').addClass('selected');
        
        // Enable next button
        $('#wizard-next-btn').prop('disabled', false).text('Start Setup').find('.dashicons').removeClass('dashicons-arrow-right-alt').addClass('dashicons-yes-alt');
        
        console.log('Setup type selected:', setupType);
    }
    
    /**
     * Handle form field changes
     */
    function handleFormFieldChange() {
        const $field = $(this);
        
        // Clear validation errors
        $field.removeClass('error');
        $field.closest('.form-group').find('.error-message').remove();
        
        // Update navigation buttons based on form completeness
        updateNavigationButtons();
        
        // Handle specific field types
        if ($field.attr('name') === 'site_type') {
            handleSiteTypeChange();
        }
    }
    
    /**
     * Handle site type selection change
     */
    function handleSiteTypeChange() {
        const selectedType = $('input[name="site_type"]:checked').val();
        
        // Update visual selection
        $('.site-type-option').removeClass('checked');
        $('input[name="site_type"]:checked').closest('.site-type-option').addClass('checked');
        
        // Toggle business info section
        toggleBusinessInfo();
        
        // Update recommendations based on site type
        updateSiteTypeRecommendations(selectedType);
    }
    
    /**
     * Toggle business information section visibility
     */
    function toggleBusinessInfo() {
        const selectedType = $('input[name="site_type"]:checked').val();
        const businessTypes = ['business', 'ecommerce', 'nonprofit'];
        
        if (businessTypes.includes(selectedType)) {
            $('.business-info').slideDown(300);
        } else {
            $('.business-info').slideUp(300);
        }
    }
    
    /**
     * Update site type recommendations
     */
    function updateSiteTypeRecommendations(siteType) {
        // This will show different recommendations based on site type
        const recommendations = {
            'blog': ['Focus on content optimization', 'Enable social media sharing', 'Set up XML sitemaps'],
            'business': ['Set up local SEO', 'Configure business schema', 'Enable Google My Business'],
            'ecommerce': ['Product schema markup', 'Breadcrumb navigation', 'Category optimization'],
            'portfolio': ['Image optimization', 'Social media integration', 'Project showcasing'],
            'news': ['News sitemap', 'Article schema', 'Breaking news optimization'],
            'nonprofit': ['Local presence', 'Donation optimization', 'Event promotion']
        };
        
        // Update UI with recommendations (if recommendation element exists)
        const $recommendations = $('.site-type-recommendations');
        if ($recommendations.length && recommendations[siteType]) {
            const items = recommendations[siteType].map(item => `<li>${item}</li>`).join('');
            $recommendations.html(`<ul>${items}</ul>`).show();
        }
    }
    
    /**
     * Handle step navigation clicks
     */
    function handleStepNavClick() {
        const targetStep = $(this).data('step');
        
        // Only allow navigation to completed or current step
        if ($(this).hasClass('completed') || $(this).hasClass('active')) {
            navigateToStep(targetStep);
        }
    }
    
    /**
     * Navigate directly to a step
     */
    function navigateToStep(targetStep) {
        if (wizardState.isProcessing) return;
        
        const currentStepData = collectCurrentStepData();
        wizardState.stepData[wizardState.currentStep] = currentStepData;
        
        // Request step content via AJAX
        loadStepContent(targetStep);
    }
    
    /**
     * Handle plugin detection
     */
    function handlePluginDetection() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text('Detecting...').prop('disabled', true);
        
        $.ajax({
            url: khmSeoWizard.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_seo_detect_plugins',
                nonce: khmSeoWizard.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displayDetectedPlugins(response.data);
                } else {
                    showMessage('No SEO plugins detected on your site.', 'info');
                }
            },
            error: function() {
                showMessage('Error detecting plugins. Please try again.', 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    /**
     * Display detected plugins
     */
    function displayDetectedPlugins(plugins) {
        let html = '<div class="detected-plugins">';
        html += '<h4>Detected SEO Plugins:</h4>';
        
        plugins.forEach(plugin => {
            html += `
                <div class="plugin-item">
                    <div class="plugin-info">
                        <strong>${plugin.name}</strong>
                        <span class="plugin-status ${plugin.active ? 'active' : 'inactive'}">
                            ${plugin.active ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                    ${plugin.has_data ? 
                        `<button type="button" class="button import-plugin-btn" data-plugin="${plugin.key}">
                            Import Settings
                        </button>` : 
                        '<span class="no-data">No settings found</span>'
                    }
                </div>
            `;
        });
        
        html += '</div>';
        
        $('#plugin-detection-results').html(html);
    }
    
    /**
     * Handle plugin import
     */
    function handlePluginImport() {
        const $button = $(this);
        const plugin = $button.data('plugin');
        const originalText = $button.text();
        
        $button.text('Importing...').prop('disabled', true);
        
        $.ajax({
            url: khmSeoWizard.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_seo_import_plugin_data',
                plugin: plugin,
                nonce: khmSeoWizard.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    if (response.data.imported_items) {
                        displayImportedItems(response.data.imported_items);
                    }
                    $button.text('Imported').addClass('imported').prop('disabled', true);
                } else {
                    showMessage('Error importing plugin data. Please try again.', 'error');
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showMessage('Error importing plugin data. Please try again.', 'error');
                $button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    /**
     * Display imported items
     */
    function displayImportedItems(items) {
        let html = '<div class="imported-items"><h5>Successfully Imported:</h5><ul>';
        items.forEach(item => {
            html += `<li><span class="dashicons dashicons-yes"></span> ${item}</li>`;
        });
        html += '</ul></div>';
        
        $('#import-results').html(html).slideDown();
    }
    
    /**
     * Collect current step form data
     */
    function collectCurrentStepData() {
        const data = {};
        
        $('.wizard-step-content input, .wizard-step-content select, .wizard-step-content textarea').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            
            if (!name) return;
            
            if ($field.attr('type') === 'radio' || $field.attr('type') === 'checkbox') {
                if ($field.is(':checked')) {
                    if (name.includes('[]')) {
                        const cleanName = name.replace('[]', '');
                        if (!data[cleanName]) data[cleanName] = [];
                        data[cleanName].push($field.val());
                    } else {
                        data[name] = $field.val();
                    }
                }
            } else {
                data[name] = $field.val();
            }
        });
        
        return data;
    }
    
    /**
     * Validate current step data
     */
    function validateCurrentStep(stepData) {
        let isValid = true;
        
        // Clear previous errors
        $('.wizard-step-content .error').removeClass('error');
        $('.error-message').remove();
        
        // Check required fields
        $('.wizard-step-content input[required], .wizard-step-content select[required]').each(function() {
            if (!validateField.call(this)) {
                isValid = false;
            }
        });
        
        // Step-specific validations
        if (wizardState.currentStep === 'site_info') {
            if (!stepData.site_type) {
                showFieldError($('input[name="site_type"]').first(), 'Please select a site type');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    /**
     * Validate individual field
     */
    function validateField() {
        const $field = $(this);
        const value = $field.val();
        const isRequired = $field.prop('required');
        
        // Clear previous error
        $field.removeClass('error');
        $field.closest('.form-group').find('.error-message').remove();
        
        if (isRequired && (!value || value.trim() === '')) {
            showFieldError($field, 'This field is required');
            return false;
        }
        
        // Field-specific validation
        if ($field.attr('type') === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showFieldError($field, 'Please enter a valid email address');
                return false;
            }
        }
        
        if ($field.attr('type') === 'url' && value) {
            const urlRegex = /^https?:\/\/.+/;
            if (!urlRegex.test(value)) {
                showFieldError($field, 'Please enter a valid URL (including http:// or https://)');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Show field validation error
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        
        if (!$field.closest('.form-group').find('.error-message').length) {
            $field.closest('.form-group').append(`<span class="error-message">${message}</span>`);
        }
    }
    
    /**
     * Save step data via AJAX
     */
    function saveStepData(step, data, action) {
        wizardState.isProcessing = true;
        showLoading();
        
        $.ajax({
            url: khmSeoWizard.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_seo_wizard_step',
                step: step,
                data: data,
                wizard_action: action,
                nonce: khmSeoWizard.nonce
            },
            success: function(response) {
                if (response.success) {
                    wizardState.currentStep = response.data.next_step;
                    wizardState.stepData[step] = data;
                    
                    // Update UI
                    updateStepContent(response.data.step_content);
                    updateProgress(response.data.progress);
                    updateStepNavigation();
                    updateNavigationButtons();
                    
                    // Scroll to top
                    $('.wizard-content').animate({scrollTop: 0}, 300);
                    
                } else {
                    showMessage('Error proceeding to next step. Please try again.', 'error');
                }
            },
            error: function() {
                showMessage('Connection error. Please check your internet connection and try again.', 'error');
            },
            complete: function() {
                wizardState.isProcessing = false;
                hideLoading();
            }
        });
    }
    
    /**
     * Load step content via AJAX
     */
    function loadStepContent(step) {
        wizardState.isProcessing = true;
        showLoading();
        
        $.ajax({
            url: khmSeoWizard.ajax_url,
            type: 'POST',
            data: {
                action: 'khm_seo_wizard_step',
                step: wizardState.currentStep,
                data: wizardState.stepData[wizardState.currentStep] || {},
                wizard_action: 'goto',
                target_step: step,
                nonce: khmSeoWizard.nonce
            },
            success: function(response) {
                if (response.success) {
                    wizardState.currentStep = step;
                    updateStepContent(response.data.step_content);
                    updateStepNavigation();
                    updateNavigationButtons();
                } else {
                    showMessage('Error loading step. Please try again.', 'error');
                }
            },
            error: function() {
                showMessage('Connection error. Please try again.', 'error');
            },
            complete: function() {
                wizardState.isProcessing = false;
                hideLoading();
            }
        });
    }
    
    /**
     * Update step content
     */
    function updateStepContent(content) {
        $('#wizard-content').fadeOut(200, function() {
            $(this).html(content).fadeIn(200);
            initializeFormElements();
        });
    }
    
    /**
     * Update progress bar
     */
    function updateProgress(percentage) {
        if (typeof percentage === 'undefined') {
            // Calculate percentage based on current step
            const steps = Object.keys(khmSeoWizard.steps || {});
            const currentIndex = steps.indexOf(wizardState.currentStep);
            percentage = Math.round((currentIndex + 1) / steps.length * 100);
        }
        
        $('#wizard-progress-fill').css('width', percentage + '%');
        $('#wizard-progress-text').text(`Step ${getCurrentStepNumber()} of ${getTotalSteps()}`);
    }
    
    /**
     * Get current step number
     */
    function getCurrentStepNumber() {
        const steps = Object.keys(khmSeoWizard.steps || {});
        return steps.indexOf(wizardState.currentStep) + 1;
    }
    
    /**
     * Get total number of steps
     */
    function getTotalSteps() {
        return Object.keys(khmSeoWizard.steps || {}).length;
    }
    
    /**
     * Update step navigation
     */
    function updateStepNavigation() {
        $('.step-nav-item').removeClass('active completed');
        
        const steps = Object.keys(khmSeoWizard.steps || {});
        const currentIndex = steps.indexOf(wizardState.currentStep);
        
        steps.forEach((step, index) => {
            const $navItem = $(`.step-nav-item[data-step="${step}"]`);
            
            if (index < currentIndex) {
                $navItem.addClass('completed');
            } else if (index === currentIndex) {
                $navItem.addClass('active');
            }
        });
    }
    
    /**
     * Update navigation buttons
     */
    function updateNavigationButtons() {
        const steps = Object.keys(khmSeoWizard.steps || {});
        const currentIndex = steps.indexOf(wizardState.currentStep);
        const isFirstStep = currentIndex === 0;
        const isLastStep = currentIndex === steps.length - 1;
        
        // Previous button
        if (isFirstStep) {
            $('#wizard-prev-btn').hide();
        } else {
            $('#wizard-prev-btn').show();
        }
        
        // Next button
        if (isLastStep) {
            $('#wizard-next-btn').text('Complete Setup').find('.dashicons').removeClass('dashicons-arrow-right-alt').addClass('dashicons-yes-alt');
        } else {
            $('#wizard-next-btn').text('Continue').find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-arrow-right-alt');
        }
        
        // Skip button (hide on welcome and review steps)
        if (wizardState.currentStep === 'welcome' || wizardState.currentStep === 'review') {
            $('#wizard-skip-btn').hide();
        } else {
            $('#wizard-skip-btn').show();
        }
    }
    
    /**
     * Populate form fields with existing data
     */
    function populateFormFields() {
        const stepData = wizardState.stepData[wizardState.currentStep] || {};
        
        Object.keys(stepData).forEach(key => {
            const value = stepData[key];
            const $field = $(`[name="${key}"], [name="${key}[]"]`);
            
            if ($field.length) {
                if ($field.attr('type') === 'radio') {
                    $field.filter(`[value="${value}"]`).prop('checked', true);
                } else if ($field.attr('type') === 'checkbox') {
                    if (Array.isArray(value)) {
                        value.forEach(val => {
                            $field.filter(`[value="${val}"]`).prop('checked', true);
                        });
                    }
                } else {
                    $field.val(value);
                }
            }
        });
        
        // Trigger change events to update UI
        $('.wizard-step-content input:checked, .wizard-step-content select').trigger('change');
    }
    
    /**
     * Initialize tooltips
     */
    function initializeTooltips() {
        // Add tooltips to help icons
        $('.help-icon').each(function() {
            $(this).attr('title', $(this).data('tooltip'));
        });
    }
    
    /**
     * Handle keyboard navigation
     */
    function handleKeyboardNavigation(e) {
        if (wizardState.isProcessing) return;
        
        if (e.ctrlKey || e.metaKey) {
            switch (e.keyCode) {
                case 37: // Left arrow - Previous step
                    e.preventDefault();
                    $('#wizard-prev-btn').click();
                    break;
                case 39: // Right arrow - Next step
                    e.preventDefault();
                    $('#wizard-next-btn').click();
                    break;
                case 13: // Enter - Next step
                    if (!$(e.target).is('textarea')) {
                        e.preventDefault();
                        $('#wizard-next-btn').click();
                    }
                    break;
            }
        }
    }
    
    /**
     * Handle page unload warning
     */
    function handleBeforeUnload(e) {
        if (wizardState.currentStep !== 'review' && Object.keys(wizardState.stepData).length > 0) {
            const message = 'You have unsaved changes in the setup wizard. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    }
    
    /**
     * Show loading overlay
     */
    function showLoading() {
        $('#wizard-loading').fadeIn(200);
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoading() {
        $('#wizard-loading').fadeOut(200);
    }
    
    /**
     * Show validation errors summary
     */
    function showValidationErrors() {
        const $errors = $('.wizard-step-content .error');
        
        if ($errors.length > 0) {
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $errors.first().offset().top - 100
            }, 300);
            
            // Show summary message
            showMessage(`Please correct the ${$errors.length} error(s) below before continuing.`, 'error');
        }
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        type = type || 'info';
        
        // Remove existing messages
        $('.wizard-message').remove();
        
        // Create message element
        const $message = $(`
            <div class="wizard-message wizard-message-${type}">
                <span class="dashicons dashicons-${getMessageIcon(type)}"></span>
                <span class="message-text">${message}</span>
                <button type="button" class="message-close">×</button>
            </div>
        `);
        
        // Add to top of wizard content
        $('.wizard-content').prepend($message);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Handle close button
        $message.find('.message-close').on('click', function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 300);
    }
    
    /**
     * Get icon for message type
     */
    function getMessageIcon(type) {
        const icons = {
            'success': 'yes-alt',
            'error': 'warning',
            'warning': 'info',
            'info': 'info'
        };
        
        return icons[type] || 'info';
    }
    
    // Export wizard object for external access
    window.KhmSeoWizard = {
        state: wizardState,
        navigateToStep: navigateToStep,
        showMessage: showMessage,
        collectCurrentStepData: collectCurrentStepData,
        validateCurrentStep: validateCurrentStep
    };
    
})(jQuery);
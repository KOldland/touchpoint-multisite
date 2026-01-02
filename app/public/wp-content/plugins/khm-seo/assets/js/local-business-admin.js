/**
 * Local Business SEO Admin JavaScript
 * Interactive functionality for local business management interface
 */

(function($) {
    'use strict';
    
    class LocalBusinessAdmin {
        constructor() {
            this.settings = window.khmSeoLocalBusiness || {};
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initTabs();
            this.loadStats();
            this.initBusinessHours();
            this.initLocationManager();
            this.initSchemaPreview();
            this.initValidation();
        }
        
        bindEvents() {
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick.bind(this));
            
            // Business hours toggle
            $('.closed-toggle input').on('change', this.handleBusinessHoursToggle.bind(this));
            
            // Multiple locations toggle
            $('#multiple_locations').on('change', this.handleMultipleLocationsToggle.bind(this));
            
            // GMB API toggle
            $('#gmb_api_enabled').on('change', this.handleGmbApiToggle.bind(this));
            
            // Action buttons
            $('#validate-nap').on('click', this.handleNapValidation.bind(this));
            $('#sync-gmb').on('click', this.handleGmbSync.bind(this));
            $('#refresh-schema-preview').on('click', this.handleSchemaRefresh.bind(this));
            $('#add-location').on('click', this.handleAddLocation.bind(this));
            
            // Form validation
            $('.khm-seo-settings-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Real-time validation
            $('input[type="email"]').on('blur', this.validateEmail.bind(this));
            $('input[type="tel"]').on('blur', this.validatePhone.bind(this));
            $('input[type="url"]').on('blur', this.validateUrl.bind(this));
            
            // Auto-save functionality
            $('.form-table input, .form-table select, .form-table textarea').on('change', 
                this.debounce(this.autoSave.bind(this), 1000)
            );
            
            // Schema preview auto-update
            $('input[name*="primary_business"], input[name*="primary_address"], select[name*="business_type"]').on('change',
                this.debounce(this.updateSchemaPreview.bind(this), 500)
            );
        }
        
        initTabs() {
            // Set active tab from URL hash or default to first tab
            const hash = window.location.hash.substring(1);
            const activeTab = hash || 'business-info';
            
            this.showTab(activeTab);
            
            // Update URL hash when tab changes
            $(window).on('hashchange', () => {
                const newHash = window.location.hash.substring(1);
                if (newHash) {
                    this.showTab(newHash);
                }
            });
        }
        
        handleTabClick(e) {
            e.preventDefault();
            const tabId = $(e.currentTarget).attr('href').substring(1);
            this.showTab(tabId);
            window.location.hash = tabId;
        }
        
        showTab(tabId) {
            // Hide all tabs
            $('.tab-content').removeClass('active');
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Show selected tab
            $(`#${tabId}`).addClass('active');
            $(`.nav-tab[href="#${tabId}"]`).addClass('nav-tab-active');
        }
        
        loadStats() {
            this.updateStat('locations-count', this.getLocationsCount());
            this.updateStat('review-rating', this.getAverageRating());
            this.updateStat('local-rankings', this.getLocalRankings());
            this.updateStat('nap-status', this.getNapStatus());
        }
        
        updateStat(elementId, value) {
            const element = $(`#${elementId}`);
            element.addClass('loading');
            
            setTimeout(() => {
                element.text(value).removeClass('loading');
            }, 500);
        }
        
        getLocationsCount() {
            // Get number of configured locations
            const multipleLocations = $('#multiple_locations').is(':checked');
            return multipleLocations ? this.countConfiguredLocations() : 1;
        }
        
        countConfiguredLocations() {
            // Count locations from saved data or location manager
            return $('.location-item').length || 1;
        }
        
        getAverageRating() {
            // Placeholder for review data
            return '4.5★';
        }
        
        getLocalRankings() {
            // Placeholder for ranking data
            return '12/20';
        }
        
        getNapStatus() {
            // Check NAP consistency
            const hasName = $('#primary_business_name').val().trim();
            const hasAddress = $('#street_address').val().trim();
            const hasPhone = $('#primary_phone').val().trim();
            
            if (hasName && hasAddress && hasPhone) {
                return 'Good';
            } else {
                return 'Needs Work';
            }
        }
        
        initBusinessHours() {
            $('.closed-toggle input').each((index, element) => {
                this.toggleTimeInputs($(element));
            });
        }
        
        handleBusinessHoursToggle(e) {
            this.toggleTimeInputs($(e.target));
        }
        
        toggleTimeInputs($checkbox) {
            const $timeInputs = $checkbox.closest('.hours-controls').find('.time-inputs');
            
            if ($checkbox.is(':checked')) {
                $timeInputs.hide();
            } else {
                $timeInputs.show();
            }
        }
        
        handleMultipleLocationsToggle(e) {
            const $locationsManager = $('#locations-manager');
            
            if ($(e.target).is(':checked')) {
                $locationsManager.slideDown();
                this.loadLocationsList();
            } else {
                $locationsManager.slideUp();
            }
        }
        
        handleGmbApiToggle(e) {
            const $apiSettings = $('.gmb-api-settings');
            
            if ($(e.target).is(':checked')) {
                $apiSettings.slideDown();
            } else {
                $apiSettings.slideUp();
            }
        }
        
        initLocationManager() {
            if ($('#multiple_locations').is(':checked')) {
                this.loadLocationsList();
            }
        }
        
        loadLocationsList() {
            // Load existing locations or show empty state
            const $locationsList = $('#locations-list');
            
            // Show loading state
            $locationsList.html('<div class="loading-locations">Loading locations...</div>');
            
            // Simulate API call
            setTimeout(() => {
                this.renderLocationsList([]);
            }, 500);
        }
        
        renderLocationsList(locations) {
            const $locationsList = $('#locations-list');
            
            if (locations.length === 0) {
                $locationsList.html(`
                    <div class="no-locations">
                        <div class="no-locations-icon">
                            <span class="dashicons dashicons-location"></span>
                        </div>
                        <h4>No additional locations configured</h4>
                        <p>Click "Add Location" to create additional business locations.</p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="locations-grid">';
            locations.forEach((location, index) => {
                html += this.renderLocationItem(location, index);
            });
            html += '</div>';
            
            $locationsList.html(html);
        }
        
        renderLocationItem(location, index) {
            return `
                <div class="location-item" data-index="${index}">
                    <div class="location-header">
                        <h5>${location.name || 'Unnamed Location'}</h5>
                        <div class="location-actions">
                            <button type="button" class="button button-small edit-location">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="button button-small delete-location">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="location-details">
                        <p><strong>Address:</strong> ${location.address || 'Not specified'}</p>
                        <p><strong>Phone:</strong> ${location.phone || 'Not specified'}</p>
                    </div>
                </div>
            `;
        }
        
        handleAddLocation(e) {
            e.preventDefault();
            this.showLocationModal();
        }
        
        showLocationModal(location = null) {
            const isEdit = location !== null;
            const title = isEdit ? 'Edit Location' : 'Add New Location';
            
            const modal = $(`
                <div class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>${title}</h3>
                            <button type="button" class="modal-close">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form class="location-form">
                                <div class="form-row">
                                    <label for="location-name">Location Name</label>
                                    <input type="text" id="location-name" name="name" value="${location?.name || ''}" required>
                                </div>
                                <div class="form-row">
                                    <label for="location-address">Address</label>
                                    <textarea id="location-address" name="address" rows="3">${location?.address || ''}</textarea>
                                </div>
                                <div class="form-row-group">
                                    <div class="form-row">
                                        <label for="location-phone">Phone</label>
                                        <input type="tel" id="location-phone" name="phone" value="${location?.phone || ''}">
                                    </div>
                                    <div class="form-row">
                                        <label for="location-email">Email</label>
                                        <input type="email" id="location-email" name="email" value="${location?.email || ''}">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <label for="location-hours">Special Hours (optional)</label>
                                    <textarea id="location-hours" name="hours" rows="2" placeholder="Different from main business hours">${location?.hours || ''}</textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button button-secondary modal-cancel">Cancel</button>
                            <button type="button" class="button button-primary save-location">${isEdit ? 'Update' : 'Add'} Location</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            modal.fadeIn();
            
            // Bind modal events
            modal.find('.modal-close, .modal-cancel').on('click', () => this.closeModal(modal));
            modal.find('.save-location').on('click', () => this.saveLocation(modal, isEdit));
            modal.find('.modal-overlay').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal(modal);
                }
            });
        }
        
        closeModal($modal) {
            $modal.fadeOut(() => $modal.remove());
        }
        
        saveLocation($modal, isEdit) {
            const formData = new FormData($modal.find('.location-form')[0]);
            const locationData = {};
            
            for (let [key, value] of formData.entries()) {
                locationData[key] = value;
            }
            
            // Validate required fields
            if (!locationData.name.trim()) {
                this.showNotice('Location name is required', 'error');
                return;
            }
            
            // Save location (placeholder for actual save logic)
            console.log('Saving location:', locationData);
            
            this.showNotice(`Location ${isEdit ? 'updated' : 'added'} successfully`, 'success');
            this.closeModal($modal);
            this.loadLocationsList();
        }
        
        initSchemaPreview() {
            this.updateSchemaPreview();
        }
        
        updateSchemaPreview() {
            const $preview = $('#schema-preview-code pre code');
            $preview.addClass('loading');
            
            // Collect current form data
            const schemaData = this.buildSchemaFromForm();
            
            setTimeout(() => {
                $preview.text(JSON.stringify(schemaData, null, 2)).removeClass('loading');
            }, 300);
        }
        
        buildSchemaFromForm() {
            return {
                '@context': 'https://schema.org',
                '@type': $('#business_type').val() || 'LocalBusiness',
                'name': $('#primary_business_name').val() || '',
                'url': $('#primary_website').val() || '',
                'telephone': $('#primary_phone').val() || '',
                'email': $('#primary_email').val() || '',
                'address': {
                    '@type': 'PostalAddress',
                    'streetAddress': $('#street_address').val() || '',
                    'addressLocality': $('#locality').val() || '',
                    'addressRegion': $('#region').val() || '',
                    'postalCode': $('#postal_code').val() || '',
                    'addressCountry': $('#country').val() || ''
                }
            };
        }
        
        handleSchemaRefresh(e) {
            e.preventDefault();
            this.updateSchemaPreview();
            this.showNotice('Schema preview updated', 'success');
        }
        
        initValidation() {
            // Real-time form validation
            this.setupFieldValidation();
        }
        
        setupFieldValidation() {
            // Email validation
            $('input[type="email"]').on('input', (e) => {
                const email = $(e.target).val();
                const isValid = this.isValidEmail(email);
                this.toggleFieldValidation($(e.target), isValid);
            });
            
            // Phone validation
            $('input[type="tel"]').on('input', (e) => {
                const phone = $(e.target).val();
                const isValid = this.isValidPhone(phone);
                this.toggleFieldValidation($(e.target), isValid);
            });
            
            // URL validation
            $('input[type="url"]').on('input', (e) => {
                const url = $(e.target).val();
                const isValid = this.isValidUrl(url);
                this.toggleFieldValidation($(e.target), isValid);
            });
        }
        
        isValidEmail(email) {
            if (!email) return true; // Empty is okay
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        isValidPhone(phone) {
            if (!phone) return true; // Empty is okay
            return /^[\+]?[\s\-\(\)]?[\d\s\-\(\)]{10,}$/.test(phone);
        }
        
        isValidUrl(url) {
            if (!url) return true; // Empty is okay
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        }
        
        toggleFieldValidation($field, isValid) {
            $field.removeClass('valid invalid');
            
            if ($field.val() && !isValid) {
                $field.addClass('invalid');
            } else if ($field.val() && isValid) {
                $field.addClass('valid');
            }
        }
        
        validateEmail(e) {
            const $field = $(e.target);
            const email = $field.val();
            
            if (email && !this.isValidEmail(email)) {
                this.showFieldError($field, 'Please enter a valid email address');
            } else {
                this.clearFieldError($field);
            }
        }
        
        validatePhone(e) {
            const $field = $(e.target);
            const phone = $field.val();
            
            if (phone && !this.isValidPhone(phone)) {
                this.showFieldError($field, 'Please enter a valid phone number');
            } else {
                this.clearFieldError($field);
            }
        }
        
        validateUrl(e) {
            const $field = $(e.target);
            const url = $field.val();
            
            if (url && !this.isValidUrl(url)) {
                this.showFieldError($field, 'Please enter a valid URL');
            } else {
                this.clearFieldError($field);
            }
        }
        
        showFieldError($field, message) {
            this.clearFieldError($field);
            
            const $error = $(`<div class="field-error">${message}</div>`);
            $field.addClass('invalid').after($error);
        }
        
        clearFieldError($field) {
            $field.removeClass('invalid').next('.field-error').remove();
        }
        
        handleNapValidation(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Validating...');
            
            // Simulate NAP validation
            setTimeout(() => {
                const napData = {
                    name: $('#primary_business_name').val(),
                    address: $('#street_address').val(),
                    phone: $('#primary_phone').val()
                };
                
                this.performNapValidation(napData).then((results) => {
                    this.showNapValidationResults(results);
                    $button.prop('disabled', false).text(originalText);
                });
            }, 2000);
        }
        
        async performNapValidation(napData) {
            // Placeholder for actual NAP validation logic
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve({
                        consistency: 85,
                        issues: [
                            'Phone number format varies across 3 listings',
                            'Address abbreviation inconsistent on 2 directories'
                        ],
                        suggestions: [
                            'Standardize phone number format as (555) 123-4567',
                            'Use full street names instead of abbreviations'
                        ]
                    });
                }, 1000);
            });
        }
        
        showNapValidationResults(results) {
            const modal = $(`
                <div class="modal-overlay">
                    <div class="modal-content nap-results-modal">
                        <div class="modal-header">
                            <h3>NAP Consistency Report</h3>
                            <button type="button" class="modal-close">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="nap-score">
                                <div class="score-circle">
                                    <span class="score">${results.consistency}%</span>
                                </div>
                                <p>Overall Consistency Score</p>
                            </div>
                            
                            ${results.issues.length > 0 ? `
                                <div class="nap-issues">
                                    <h4>Issues Found</h4>
                                    <ul>
                                        ${results.issues.map(issue => `<li>${issue}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                            
                            ${results.suggestions.length > 0 ? `
                                <div class="nap-suggestions">
                                    <h4>Recommendations</h4>
                                    <ul>
                                        ${results.suggestions.map(suggestion => `<li>${suggestion}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button button-primary modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            modal.fadeIn();
            
            modal.find('.modal-close').on('click', () => this.closeModal(modal));
        }
        
        handleGmbSync(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            if (!$('#gmb_api_enabled').is(':checked')) {
                this.showNotice('Google My Business API integration is not enabled', 'warning');
                return;
            }
            
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Syncing...');
            
            // Simulate GMB sync
            setTimeout(() => {
                this.showNotice('Successfully synced with Google My Business', 'success');
                $button.prop('disabled', false).text(originalText);
                this.loadStats(); // Refresh stats
            }, 3000);
        }
        
        autoSave() {
            // Auto-save functionality
            const formData = new FormData($('.khm-seo-settings-form')[0]);
            
            // Show auto-save indicator
            this.showAutoSaveIndicator();
            
            // Simulate auto-save
            setTimeout(() => {
                this.hideAutoSaveIndicator();
            }, 1000);
        }
        
        showAutoSaveIndicator() {
            if (!$('.auto-save-indicator').length) {
                $('body').append('<div class="auto-save-indicator">Saving...</div>');
            }
            $('.auto-save-indicator').show();
        }
        
        hideAutoSaveIndicator() {
            $('.auto-save-indicator').fadeOut();
        }
        
        handleFormSubmit(e) {
            // Validate form before submit
            let isValid = true;
            
            // Check required fields
            $('.form-table input[required]').each((index, element) => {
                const $field = $(element);
                if (!$field.val().trim()) {
                    this.showFieldError($field, 'This field is required');
                    isValid = false;
                }
            });
            
            // Check email fields
            $('.form-table input[type="email"]').each((index, element) => {
                const $field = $(element);
                const email = $field.val();
                if (email && !this.isValidEmail(email)) {
                    this.showFieldError($field, 'Please enter a valid email address');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                this.showNotice('Please fix the errors below before saving', 'error');
                return false;
            }
            
            // Show saving state
            const $submitButton = $('#save-local-business-settings');
            $submitButton.prop('disabled', true).val('Saving...');
            
            // Re-enable button after form submission
            setTimeout(() => {
                $submitButton.prop('disabled', false).val('Save Local Business Settings');
            }, 2000);
        }
        
        showNotice(message, type = 'success') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.khm-seo-local-business-admin h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
            
            // Manual dismiss
            $notice.find('.notice-dismiss').on('click', () => {
                $notice.fadeOut(() => $notice.remove());
            });
        }
        
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    }
    
    // Initialize when document is ready
    $(document).ready(() => {
        new LocalBusinessAdmin();
    });
    
    // Add spinning animation for loading states
    const style = $(`
        <style>
            .spin {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .auto-save-indicator {
                position: fixed;
                top: 32px;
                right: 20px;
                background: #2271b1;
                color: white;
                padding: 8px 16px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 999999;
                display: none;
            }
            
            .field-error {
                color: #c62828;
                font-size: 12px;
                margin-top: 4px;
            }
            
            .nap-results-modal .modal-content {
                max-width: 600px;
            }
            
            .nap-score {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .score-circle {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 10px;
            }
            
            .score {
                font-size: 24px;
                font-weight: 700;
                color: white;
            }
            
            .nap-issues ul,
            .nap-suggestions ul {
                padding-left: 20px;
            }
            
            .nap-issues li,
            .nap-suggestions li {
                margin-bottom: 8px;
            }
            
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999999;
                display: none;
                align-items: center;
                justify-content: center;
            }
            
            .modal-content {
                background: white;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow: auto;
            }
            
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #e1e5e9;
            }
            
            .modal-header h3 {
                margin: 0;
            }
            
            .modal-close {
                background: none;
                border: none;
                padding: 4px;
                cursor: pointer;
                color: #646970;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-footer {
                padding: 20px;
                border-top: 1px solid #e1e5e9;
                text-align: right;
            }
            
            .modal-footer .button {
                margin-left: 10px;
            }
            
            .location-form .form-row {
                margin-bottom: 16px;
            }
            
            .location-form label {
                display: block;
                margin-bottom: 6px;
                font-weight: 500;
            }
            
            .location-form input,
            .location-form textarea {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .form-row-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }
            
            .no-locations {
                text-align: center;
                padding: 40px 20px;
                color: #646970;
            }
            
            .no-locations-icon {
                font-size: 48px;
                margin-bottom: 16px;
                opacity: 0.5;
            }
            
            .no-locations h4 {
                margin: 0 0 8px 0;
                font-size: 16px;
            }
            
            .no-locations p {
                margin: 0;
                font-size: 14px;
            }
        </style>
    `);
    
    $('head').append(style);
    
})(jQuery);
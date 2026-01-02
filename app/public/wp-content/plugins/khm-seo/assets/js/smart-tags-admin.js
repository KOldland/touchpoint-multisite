/**
 * Smart Tags & Templates Admin JavaScript
 * Interactive functionality for template builder and smart tags management
 */

(function($) {
    'use strict';
    
    class SmartTagsAdmin {
        constructor() {
            this.settings = window.khmSeoSmartTags || {};
            this.currentTemplateType = 'title';
            this.templates = {};
            this.smartTags = {};
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initTabs();
            this.initTemplateBuilder();
            this.initSmartTagsBrowser();
            this.initCustomTagsManager();
            this.loadStats();
            this.loadTemplates();
            this.loadSmartTags();
        }
        
        bindEvents() {
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick.bind(this));
            
            // Template builder events
            $('.template-type-tab').on('click', this.handleTemplateTypeChange.bind(this));
            $('#template-preset').on('change', this.handlePresetChange.bind(this));
            $('#load-preset').on('click', this.handleLoadPreset.bind(this));
            $('#save-template').on('click', this.handleSaveTemplate.bind(this));
            $('#preview-template').on('click', this.handlePreviewTemplate.bind(this));
            $('#template-input').on('input', this.handleTemplateInput.bind(this));
            
            // Header action buttons
            $('#preview-templates').on('click', this.handlePreviewTemplates.bind(this));
            $('#bulk-optimize').on('click', this.handleBulkOptimize.bind(this));
            $('#generate-templates').on('click', this.handleGenerateTemplates.bind(this));
            
            // Smart tags browser events
            $('#tags-search').on('click', this.handleTagsSearch.bind(this));
            $('#tags-search input').on('keypress', this.handleTagsSearchKeypress.bind(this));
            $('#tags-category-filter').on('change', this.handleTagsCategoryFilter.bind(this));
            
            // Custom tags manager
            $('#add-custom-tag').on('click', this.handleAddCustomTag.bind(this));
            
            // Template actions (delegated events)
            $(document).on('click', '.template-edit', this.handleEditTemplate.bind(this));
            $(document).on('click', '.template-delete', this.handleDeleteTemplate.bind(this));
            $(document).on('click', '.template-duplicate', this.handleDuplicateTemplate.bind(this));
            $(document).on('click', '.tag-insert', this.handleInsertTag.bind(this));
            $(document).on('click', '.tag-item', this.handleSelectTag.bind(this));
            
            // Modal events
            $(document).on('click', '.template-modal-close, .modal-cancel', this.handleCloseModal.bind(this));
            $(document).on('click', '.template-modal-overlay', this.handleModalOverlayClick.bind(this));
            
            // Form submission
            $('.khm-seo-settings-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Real-time validation
            $('#template-input').on('input', this.debounce(this.validateTemplate.bind(this), 300));
            
            // Auto-save functionality
            $('#template-input').on('input', this.debounce(this.autoSaveTemplate.bind(this), 1000));
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        }
        
        initTabs() {
            // Set active tab from URL hash or default to first tab
            const hash = window.location.hash.substring(1);
            const activeTab = hash || 'template-builder';
            
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
            
            // Initialize tab-specific content
            this.initTabContent(tabId);
        }
        
        initTabContent(tabId) {
            switch (tabId) {
                case 'smart-tags':
                    this.refreshSmartTagsBrowser();
                    break;
                case 'conditional-logic':
                    this.initConditionalLogicBuilder();
                    break;
                case 'bulk-optimization':
                    this.initBulkOptimization();
                    break;
                case 'content-analysis':
                    this.initContentAnalysis();
                    break;
            }
        }
        
        initTemplateBuilder() {
            this.updateTemplateEditor();
            this.loadTemplatePresets();
        }
        
        loadTemplatePresets() {
            const presets = {
                'title': {
                    'basic': '%%post_title%% | %%site_title%%',
                    'keyword-focused': '%%focus_keyword%% - %%post_title%% | %%site_title%%',
                    'category-based': '%%post_title%% in %%category%% | %%site_title%%',
                    'author-focused': '%%post_title%% by %%author_name%% | %%site_title%%',
                    'date-focused': '%%post_title%% (%%current_year%%) | %%site_title%%',
                    'question-format': 'How to %%post_title%%? | %%site_title%%',
                    'listicle-format': '%%word_count%% Tips: %%post_title%% | %%site_title%%',
                    'location-based': '%%post_title%% in [Location] | %%site_title%%'
                },
                'description': {
                    'basic': '%%post_excerpt%%',
                    'keyword-rich': 'Learn about %%focus_keyword%%. %%post_excerpt%% Read more on %%site_title%%.',
                    'call-to-action': '%%post_excerpt%% Click to learn more!',
                    'question-answer': 'Looking for %%focus_keyword%%? %%post_excerpt%%',
                    'benefit-focused': 'Discover how %%post_title%% can help you. %%post_excerpt%%',
                    'urgency-driven': 'Don\'t miss out! %%post_excerpt%% Learn more now.',
                    'social-proof': 'Join thousands who learned about %%focus_keyword%%. %%post_excerpt%%',
                    'problem-solution': 'Struggling with %%focus_keyword%%? %%post_excerpt%% Find solutions here.'
                },
                'keywords': {
                    'basic': '%%focus_keyword%%, %%related_keywords%%',
                    'expanded': '%%focus_keyword%%, %%category%%, %%post_tags%%, %%related_keywords%%',
                    'location-based': '%%focus_keyword%%, %%category%% in [location], %%related_keywords%%'
                }
            };
            
            this.templatePresets = presets;
        }
        
        handleTemplateTypeChange(e) {
            e.preventDefault();
            
            // Update active tab
            $('.template-type-tab').removeClass('active');
            $(e.currentTarget).addClass('active');
            
            // Update current template type
            this.currentTemplateType = $(e.currentTarget).data('type');
            
            // Update template editor
            this.updateTemplateEditor();
            
            // Update preset options
            this.updatePresetOptions();
        }
        
        updateTemplateEditor() {
            const placeholder = this.getPlaceholderText();
            $('#template-input').attr('placeholder', placeholder);
            
            // Load saved template for this type
            this.loadCurrentTemplate();
        }
        
        getPlaceholderText() {
            const placeholders = {
                'title': 'Enter your title template here... Use %%tag_name%% for smart tags\nExample: %%post_title%% | %%site_title%%',
                'description': 'Enter your description template here... Use %%tag_name%% for smart tags\nExample: Learn about %%focus_keyword%%. %%post_excerpt%%',
                'keywords': 'Enter your keywords template here... Use %%tag_name%% for smart tags\nExample: %%focus_keyword%%, %%related_keywords%%'
            };
            
            return placeholders[this.currentTemplateType] || 'Enter your template here...';
        }
        
        updatePresetOptions() {
            const $presetSelect = $('#template-preset');
            $presetSelect.empty().append('<option value="">Choose a preset...</option>');
            
            if (this.templatePresets && this.templatePresets[this.currentTemplateType]) {
                const presets = this.templatePresets[this.currentTemplateType];
                
                Object.keys(presets).forEach(key => {
                    const label = key.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    $presetSelect.append(`<option value="${key}">${label}</option>`);
                });
            }
        }
        
        handlePresetChange(e) {
            const presetKey = $(e.target).val();
            if (presetKey && this.templatePresets && this.templatePresets[this.currentTemplateType]) {
                const template = this.templatePresets[this.currentTemplateType][presetKey];
                if (template) {
                    $('#template-input').val(template);
                    this.handleTemplateInput();
                }
            }
        }
        
        handleLoadPreset(e) {
            e.preventDefault();
            const presetKey = $('#template-preset').val();
            
            if (!presetKey) {
                this.showNotice('Please select a preset first', 'warning');
                return;
            }
            
            this.handlePresetChange({ target: $('#template-preset')[0] });
            this.showNotice('Preset loaded successfully', 'success');
        }
        
        handleTemplateInput(e) {
            const template = $('#template-input').val();
            
            // Update character count
            this.updateCharacterCount(template);
            
            // Validate template
            this.validateTemplate(template);
            
            // Update preview if visible
            if ($('#template-preview-container').is(':visible')) {
                this.updateTemplatePreview(template);
            }
        }
        
        updateCharacterCount(template) {
            const charCount = template.length;
            $('#char-count').text(charCount);
            
            // Add color coding based on optimal lengths
            const $charCount = $('#char-count').parent();
            $charCount.removeClass('optimal warning error');
            
            if (this.currentTemplateType === 'title') {
                if (charCount <= 60) {
                    $charCount.addClass('optimal');
                } else if (charCount <= 70) {
                    $charCount.addClass('warning');
                } else {
                    $charCount.addClass('error');
                }
            } else if (this.currentTemplateType === 'description') {
                if (charCount <= 160) {
                    $charCount.addClass('optimal');
                } else if (charCount <= 180) {
                    $charCount.addClass('warning');
                } else {
                    $charCount.addClass('error');
                }
            }
        }
        
        validateTemplate(template) {
            const $validation = $('#template-validation');
            $validation.removeClass('valid invalid warning');
            
            if (!template.trim()) {
                $validation.addClass('warning').html('<span class="dashicons dashicons-warning"></span> Template is empty');
                return;
            }
            
            // Check for valid smart tags
            const invalidTags = this.findInvalidTags(template);
            
            if (invalidTags.length > 0) {
                $validation.addClass('invalid').html(`<span class="dashicons dashicons-no"></span> Invalid tags: ${invalidTags.join(', ')}`);
                return;
            }
            
            // Check for balanced conditional logic
            if (!this.validateConditionalLogic(template)) {
                $validation.addClass('invalid').html('<span class="dashicons dashicons-no"></span> Unbalanced conditional logic');
                return;
            }
            
            $validation.addClass('valid').html('<span class="dashicons dashicons-yes"></span> Template is valid');
        }
        
        findInvalidTags(template) {
            const tagPattern = /%%([^%]+)%%/g;
            const invalidTags = [];
            let match;
            
            while ((match = tagPattern.exec(template)) !== null) {
                const tag = match[1];
                if (!this.isValidTag(tag)) {
                    invalidTags.push(tag);
                }
            }
            
            return invalidTags;
        }
        
        isValidTag(tag) {
            // Check against available smart tags
            return this.smartTags.hasOwnProperty(tag) || this.isCustomFunction(tag);
        }
        
        isCustomFunction(tag) {
            // Check if it's a custom function like focus_keyword, related_keywords, etc.
            const customFunctions = ['focus_keyword', 'related_keywords', 'competitor_analysis', 'content_score', 'seo_recommendations'];
            return customFunctions.includes(tag);
        }
        
        validateConditionalLogic(template) {
            // Simple validation for balanced {if} and {/if} tags
            const openTags = (template.match(/\{if\s+[^}]+\}/g) || []).length;
            const closeTags = (template.match(/\{\/if\}/g) || []).length;
            
            return openTags === closeTags;
        }
        
        handlePreviewTemplate(e) {
            e.preventDefault();
            
            const template = $('#template-input').val().trim();
            if (!template) {
                this.showNotice('Please enter a template to preview', 'warning');
                return;
            }
            
            const $container = $('#template-preview-container');
            const $output = $('#template-preview-output');
            
            // Show preview container
            $container.show();
            
            // Add loading state
            $output.html('<div class="loading-placeholder" style="height: 40px;"></div>');
            
            // Simulate template processing
            setTimeout(() => {
                const processedTemplate = this.processTemplatePreview(template);
                $output.html(processedTemplate);
            }, 500);
        }
        
        processTemplatePreview(template) {
            // Sample data for preview
            const sampleData = {
                'post_title': 'How to Create Amazing WordPress Websites',
                'site_title': 'WebDev Pro',
                'focus_keyword': 'WordPress website creation',
                'category': 'Web Development',
                'author_name': 'John Smith',
                'current_year': new Date().getFullYear().toString(),
                'word_count': '1,250',
                'post_excerpt': 'Learn the essential steps to create professional WordPress websites that engage users and drive conversions.',
                'related_keywords': 'WordPress development, website design, CMS',
                'site_description': 'Professional web development tutorials and resources'
            };
            
            // Replace smart tags with sample data
            let processed = template;
            Object.keys(sampleData).forEach(tag => {
                const regex = new RegExp(`%%${tag}%%`, 'g');
                processed = processed.replace(regex, sampleData[tag]);
            });
            
            // Process simple conditional logic
            processed = processed.replace(/\{if\s+([^}]+)\}(.*?)\{\/if\}/g, (match, condition, content) => {
                // Simple condition evaluation for preview
                if (condition.includes('post_title')) {
                    return content;
                }
                return '';
            });
            
            return processed;
        }
        
        handleSaveTemplate(e) {
            e.preventDefault();
            
            const template = $('#template-input').val().trim();
            if (!template) {
                this.showNotice('Please enter a template to save', 'warning');
                return;
            }
            
            // Validate template before saving
            const invalidTags = this.findInvalidTags(template);
            if (invalidTags.length > 0) {
                this.showNotice(`Cannot save template with invalid tags: ${invalidTags.join(', ')}`, 'error');
                return;
            }
            
            this.showSaveTemplateModal(template);
        }
        
        showSaveTemplateModal(template) {
            const modal = $(`
                <div class="template-modal-overlay">
                    <div class="template-modal">
                        <div class="template-modal-header">
                            <h3>Save Template</h3>
                            <button type="button" class="template-modal-close">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                        <div class="template-modal-body">
                            <form class="template-form">
                                <div class="form-row">
                                    <label for="template-name">Template Name</label>
                                    <input type="text" id="template-name" name="name" required placeholder="Enter template name">
                                </div>
                                <div class="form-row">
                                    <label for="template-description">Description (optional)</label>
                                    <textarea id="template-description" name="description" rows="3" placeholder="Describe when to use this template"></textarea>
                                </div>
                                <div class="form-row">
                                    <label for="template-type-save">Template Type</label>
                                    <select id="template-type-save" name="type">
                                        <option value="title" ${this.currentTemplateType === 'title' ? 'selected' : ''}>Title Template</option>
                                        <option value="description" ${this.currentTemplateType === 'description' ? 'selected' : ''}>Description Template</option>
                                        <option value="keywords" ${this.currentTemplateType === 'keywords' ? 'selected' : ''}>Keywords Template</option>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <label>Template Content</label>
                                    <textarea readonly rows="4">${template}</textarea>
                                </div>
                            </form>
                        </div>
                        <div class="template-modal-footer">
                            <button type="button" class="button button-secondary modal-cancel">Cancel</button>
                            <button type="button" class="button button-primary save-template-confirm">Save Template</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            modal.fadeIn();
            
            // Focus on name input
            modal.find('#template-name').focus();
            
            // Bind save event
            modal.find('.save-template-confirm').on('click', () => {
                this.saveTemplateConfirm(modal, template);
            });
        }
        
        saveTemplateConfirm(modal, template) {
            const formData = new FormData(modal.find('.template-form')[0]);
            const templateData = {
                name: formData.get('name'),
                description: formData.get('description'),
                type: formData.get('type'),
                content: template,
                created: new Date().toISOString()
            };
            
            // Validate name
            if (!templateData.name.trim()) {
                this.showNotice('Template name is required', 'error');
                return;
            }
            
            // Save template
            this.saveTemplate(templateData);
            
            this.closeModal(modal);
            this.showNotice(`Template "${templateData.name}" saved successfully`, 'success');
            this.loadTemplates();
        }
        
        saveTemplate(templateData) {
            // In a real implementation, this would save to database via AJAX
            let savedTemplates = JSON.parse(localStorage.getItem('khm_seo_templates') || '[]');
            
            // Generate ID
            templateData.id = Date.now().toString();
            
            savedTemplates.push(templateData);
            localStorage.setItem('khm_seo_templates', JSON.stringify(savedTemplates));
            
            // Update templates object
            this.templates[templateData.id] = templateData;
        }
        
        loadTemplates() {
            // Load templates from storage
            const savedTemplates = JSON.parse(localStorage.getItem('khm_seo_templates') || '[]');
            
            this.templates = {};
            savedTemplates.forEach(template => {
                this.templates[template.id] = template;
            });
            
            this.renderTemplatesList();
        }
        
        renderTemplatesList() {
            const $templatesList = $('#templates-list');
            
            if (Object.keys(this.templates).length === 0) {
                $templatesList.html(`
                    <div class="no-templates">
                        <p>No saved templates. Create your first template using the editor above.</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            Object.values(this.templates).forEach(template => {
                html += this.renderTemplateItem(template);
            });
            
            $templatesList.html(html);
        }
        
        renderTemplateItem(template) {
            const truncatedContent = template.content.length > 50 
                ? template.content.substring(0, 50) + '...' 
                : template.content;
            
            return `
                <div class="template-item" data-template-id="${template.id}">
                    <div class="template-info-left">
                        <div class="template-name">${template.name}</div>
                        <div class="template-preview-text">${truncatedContent}</div>
                    </div>
                    <div class="template-actions">
                        <button type="button" class="button button-small template-edit" data-template-id="${template.id}">
                            <span class="dashicons dashicons-edit"></span>
                            Edit
                        </button>
                        <button type="button" class="button button-small template-duplicate" data-template-id="${template.id}">
                            <span class="dashicons dashicons-admin-page"></span>
                            Duplicate
                        </button>
                        <button type="button" class="button button-small template-delete" data-template-id="${template.id}">
                            <span class="dashicons dashicons-trash"></span>
                            Delete
                        </button>
                    </div>
                </div>
            `;
        }
        
        handleEditTemplate(e) {
            e.preventDefault();
            const templateId = $(e.currentTarget).data('template-id');
            const template = this.templates[templateId];
            
            if (template) {
                // Load template into editor
                $('#template-input').val(template.content);
                this.currentTemplateType = template.type;
                
                // Update template type tab
                $('.template-type-tab').removeClass('active');
                $(`.template-type-tab[data-type="${template.type}"]`).addClass('active');
                
                this.updateTemplateEditor();
                this.handleTemplateInput();
                
                this.showNotice(`Template "${template.name}" loaded into editor`, 'success');
            }
        }
        
        handleDeleteTemplate(e) {
            e.preventDefault();
            const templateId = $(e.currentTarget).data('template-id');
            const template = this.templates[templateId];
            
            if (template && confirm(`Are you sure you want to delete template "${template.name}"?`)) {
                this.deleteTemplate(templateId);
                this.showNotice(`Template "${template.name}" deleted successfully`, 'success');
            }
        }
        
        deleteTemplate(templateId) {
            // Remove from memory
            delete this.templates[templateId];
            
            // Remove from storage
            let savedTemplates = JSON.parse(localStorage.getItem('khm_seo_templates') || '[]');
            savedTemplates = savedTemplates.filter(t => t.id !== templateId);
            localStorage.setItem('khm_seo_templates', JSON.stringify(savedTemplates));
            
            // Refresh display
            this.renderTemplatesList();
        }
        
        handleDuplicateTemplate(e) {
            e.preventDefault();
            const templateId = $(e.currentTarget).data('template-id');
            const template = this.templates[templateId];
            
            if (template) {
                const duplicatedTemplate = {
                    ...template,
                    name: template.name + ' (Copy)',
                    created: new Date().toISOString()
                };
                
                delete duplicatedTemplate.id;
                this.saveTemplate(duplicatedTemplate);
                this.loadTemplates();
                this.showNotice(`Template "${template.name}" duplicated successfully`, 'success');
            }
        }
        
        // Smart Tags Browser functionality
        initSmartTagsBrowser() {
            this.loadSmartTags();
        }
        
        loadSmartTags() {
            // Built-in smart tags
            this.smartTags = {
                // Site tags
                'site_title': { description: 'Site Title', category: 'site' },
                'site_description': { description: 'Site Description', category: 'site' },
                'site_url': { description: 'Site URL', category: 'site' },
                'site_name': { description: 'Site Name', category: 'site' },
                'admin_email': { description: 'Admin Email', category: 'site' },
                
                // Post tags
                'post_title': { description: 'Post Title', category: 'post' },
                'post_content': { description: 'Post Content', category: 'post' },
                'post_excerpt': { description: 'Post Excerpt', category: 'post' },
                'post_date': { description: 'Post Date', category: 'post' },
                'post_modified': { description: 'Post Modified Date', category: 'post' },
                'post_author': { description: 'Post Author', category: 'post' },
                'post_category': { description: 'Post Category', category: 'post' },
                'post_tags': { description: 'Post Tags', category: 'post' },
                'post_id': { description: 'Post ID', category: 'post' },
                'post_slug': { description: 'Post Slug', category: 'post' },
                'post_type': { description: 'Post Type', category: 'post' },
                'word_count': { description: 'Word Count', category: 'post' },
                'reading_time': { description: 'Reading Time', category: 'post' },
                
                // Taxonomy tags
                'category': { description: 'Category Name', category: 'taxonomy' },
                'category_description': { description: 'Category Description', category: 'taxonomy' },
                'tag': { description: 'Tag Name', category: 'taxonomy' },
                'tag_description': { description: 'Tag Description', category: 'taxonomy' },
                'term_name': { description: 'Term Name', category: 'taxonomy' },
                'term_description': { description: 'Term Description', category: 'taxonomy' },
                'term_count': { description: 'Term Post Count', category: 'taxonomy' },
                
                // Author tags
                'author_name': { description: 'Author Name', category: 'author' },
                'author_bio': { description: 'Author Bio', category: 'author' },
                'author_email': { description: 'Author Email', category: 'author' },
                'author_url': { description: 'Author URL', category: 'author' },
                'author_posts_count': { description: 'Author Posts Count', category: 'author' },
                
                // Date tags
                'current_date': { description: 'Current Date', category: 'date' },
                'current_year': { description: 'Current Year', category: 'date' },
                'current_month': { description: 'Current Month', category: 'date' },
                'current_day': { description: 'Current Day', category: 'date' },
                
                // Search tags
                'search_term': { description: 'Search Term', category: 'search' },
                'search_count': { description: 'Search Results Count', category: 'search' },
                
                // Custom tags
                'focus_keyword': { description: 'Focus Keyword', category: 'custom' },
                'related_keywords': { description: 'Related Keywords', category: 'custom' },
                'competitor_analysis': { description: 'Competitor Analysis', category: 'custom' },
                'content_score': { description: 'Content Score', category: 'custom' },
                'seo_recommendations': { description: 'SEO Recommendations', category: 'custom' }
            };
            
            this.renderSmartTagsBrowser();
        }
        
        renderSmartTagsBrowser() {
            const $tagsList = $('#smart-tags-list');
            
            const categories = this.groupTagsByCategory();
            
            let html = '<div class="tags-grid">';
            
            Object.keys(categories).forEach(category => {
                html += `<div class="tag-category">`;
                html += `<h5>${this.getCategoryLabel(category)}</h5>`;
                
                categories[category].forEach(tag => {
                    html += this.renderSmartTagItem(tag);
                });
                
                html += `</div>`;
            });
            
            html += '</div>';
            
            $tagsList.html(html);
        }
        
        groupTagsByCategory() {
            const categories = {};
            
            Object.keys(this.smartTags).forEach(tag => {
                const tagData = this.smartTags[tag];
                const category = tagData.category || 'custom';
                
                if (!categories[category]) {
                    categories[category] = [];
                }
                
                categories[category].push({ tag, ...tagData });
            });
            
            return categories;
        }
        
        getCategoryLabel(category) {
            const labels = {
                'site': 'Site Information',
                'post': 'Post Content',
                'taxonomy': 'Categories & Tags',
                'author': 'Author Information',
                'date': 'Date & Time',
                'search': 'Search',
                'custom': 'Custom Tags'
            };
            
            return labels[category] || category.toUpperCase();
        }
        
        renderSmartTagItem(tagData) {
            return `
                <div class="tag-item" data-tag="${tagData.tag}">
                    <div class="tag-info">
                        <div class="tag-name">%%${tagData.tag}%%</div>
                        <div class="tag-description">${tagData.description}</div>
                    </div>
                    <button type="button" class="tag-insert" data-tag="${tagData.tag}">
                        Insert
                    </button>
                </div>
            `;
        }
        
        handleInsertTag(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const tag = $(e.currentTarget).data('tag');
            this.insertTagIntoEditor(`%%${tag}%%`);
        }
        
        insertTagIntoEditor(tagText) {
            const $templateInput = $('#template-input');
            const textarea = $templateInput[0];
            
            // Get current cursor position
            const startPos = textarea.selectionStart;
            const endPos = textarea.selectionEnd;
            const currentValue = $templateInput.val();
            
            // Insert tag at cursor position
            const newValue = currentValue.substring(0, startPos) + tagText + currentValue.substring(endPos);
            $templateInput.val(newValue);
            
            // Update cursor position
            const newCursorPos = startPos + tagText.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
            
            // Focus back on textarea
            $templateInput.focus();
            
            // Trigger input event to update validation and preview
            this.handleTemplateInput();
            
            this.showNotice(`Tag ${tagText} inserted into template`, 'success');
        }
        
        handleTagsSearch(e) {
            e.preventDefault();
            this.filterSmartTags();
        }
        
        handleTagsSearchKeypress(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                this.filterSmartTags();
            }
        }
        
        handleTagsCategoryFilter(e) {
            this.filterSmartTags();
        }
        
        filterSmartTags() {
            const searchTerm = $('#tags-search input').val().toLowerCase();
            const selectedCategory = $('#tags-category-filter').val();
            
            $('.tag-item').each((index, element) => {
                const $item = $(element);
                const tagName = $item.find('.tag-name').text().toLowerCase();
                const tagDescription = $item.find('.tag-description').text().toLowerCase();
                const tagCategory = this.smartTags[$item.data('tag')]?.category || '';
                
                // Check search term match
                const matchesSearch = !searchTerm || 
                    tagName.includes(searchTerm) || 
                    tagDescription.includes(searchTerm);
                
                // Check category filter
                const matchesCategory = !selectedCategory || tagCategory === selectedCategory;
                
                // Show/hide item
                if (matchesSearch && matchesCategory) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
            
            // Hide empty categories
            $('.tag-category').each((index, element) => {
                const $category = $(element);
                const visibleTags = $category.find('.tag-item:visible').length;
                
                if (visibleTags === 0) {
                    $category.hide();
                } else {
                    $category.show();
                }
            });
        }
        
        refreshSmartTagsBrowser() {
            this.renderSmartTagsBrowser();
            this.filterSmartTags(); // Apply any existing filters
        }
        
        // Stats loading
        loadStats() {
            this.updateStat('active-templates', this.getActiveTemplatesCount());
            this.updateStat('optimization-score', this.getOptimizationScore());
            this.updateStat('processed-posts', this.getProcessedPostsCount());
            this.updateStat('time-saved', this.getTimeSaved());
        }
        
        updateStat(elementId, value) {
            const element = $(`#${elementId}`);
            element.addClass('loading');
            
            setTimeout(() => {
                element.text(value).removeClass('loading');
            }, 300 + Math.random() * 500); // Stagger the loading
        }
        
        getActiveTemplatesCount() {
            return Object.keys(this.templates).length;
        }
        
        getOptimizationScore() {
            return '87%';
        }
        
        getProcessedPostsCount() {
            return '1,247';
        }
        
        getTimeSaved() {
            return '23 hrs';
        }
        
        // Additional functionality methods...
        
        autoSaveTemplate() {
            const template = $('#template-input').val();
            if (template.trim()) {
                // Save to temporary storage
                localStorage.setItem('khm_seo_temp_template', JSON.stringify({
                    content: template,
                    type: this.currentTemplateType,
                    timestamp: Date.now()
                }));
            }
        }
        
        loadCurrentTemplate() {
            // Load from temporary storage if exists
            const tempTemplate = localStorage.getItem('khm_seo_temp_template');
            if (tempTemplate) {
                const templateData = JSON.parse(tempTemplate);
                if (templateData.type === this.currentTemplateType) {
                    $('#template-input').val(templateData.content);
                    this.handleTemplateInput();
                }
            }
        }
        
        handleKeyboardShortcuts(e) {
            // Ctrl/Cmd + S to save template
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if ($('#template-builder').hasClass('active')) {
                    $('#save-template').click();
                }
            }
            
            // Ctrl/Cmd + P to preview template
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                if ($('#template-builder').hasClass('active')) {
                    $('#preview-template').click();
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                $('.template-modal-overlay:visible').fadeOut(() => {
                    $('.template-modal-overlay').remove();
                });
            }
        }
        
        handleCloseModal(e) {
            e.preventDefault();
            const $modal = $(e.target).closest('.template-modal-overlay');
            this.closeModal($modal);
        }
        
        handleModalOverlayClick(e) {
            if (e.target === e.currentTarget) {
                this.closeModal($(e.currentTarget));
            }
        }
        
        closeModal($modal) {
            $modal.fadeOut(() => $modal.remove());
        }
        
        showNotice(message, type = 'success') {
            // Remove existing notices
            $('.admin-notice').remove();
            
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible admin-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.khm-seo-smart-tags-admin h1').after($notice);
            
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
        
        // Placeholder methods for additional functionality
        initConditionalLogicBuilder() {
            console.log('Initializing conditional logic builder...');
        }
        
        initBulkOptimization() {
            console.log('Initializing bulk optimization...');
        }
        
        initContentAnalysis() {
            console.log('Initializing content analysis...');
        }
        
        handlePreviewTemplates(e) {
            e.preventDefault();
            console.log('Preview templates functionality...');
        }
        
        handleBulkOptimize(e) {
            e.preventDefault();
            console.log('Bulk optimize functionality...');
        }
        
        handleGenerateTemplates(e) {
            e.preventDefault();
            console.log('Generate templates functionality...');
        }
        
        handleAddCustomTag(e) {
            e.preventDefault();
            console.log('Add custom tag functionality...');
        }
        
        handleFormSubmit(e) {
            // Allow form submission to proceed
            console.log('Form submitted');
        }
    }
    
    // Initialize when document is ready
    $(document).ready(() => {
        new SmartTagsAdmin();
    });
    
    // Add additional styles for character count colors
    const style = $(`
        <style>
            .character-count.optimal {
                color: #4caf50;
            }
            
            .character-count.warning {
                color: #ff9800;
            }
            
            .character-count.error {
                color: #f44336;
            }
            
            .no-templates {
                text-align: center;
                padding: 40px 20px;
                color: #646970;
                font-style: italic;
            }
            
            .admin-notice {
                margin: 16px 32px 0;
            }
        </style>
    `);
    
    $('head').append(style);
    
})(jQuery);
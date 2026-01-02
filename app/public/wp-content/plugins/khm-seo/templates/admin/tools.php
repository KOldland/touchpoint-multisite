<?php
/**
 * SEO Tools Template
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options = \get_option( 'khm_seo_tools', array() );
?>

<div class="wrap khm-seo-settings-page">
    <h1><?php \_e( 'KHM SEO - Tools & Analysis', 'khm-seo' ); ?></h1>

    <div class="khm-seo-tools-grid">
        
        <!-- Content Analysis Tool -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'Content Analysis', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Analyze any URL or post content for SEO optimization opportunities.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <label for="analyze-url"><?php \_e( 'URL or Post ID to Analyze:', 'khm-seo' ); ?></label>
                <input type="text" id="analyze-url" placeholder="<?php \_e( 'Enter URL or Post ID', 'khm-seo' ); ?>" />
                <button type="button" class="button-primary" onclick="khmSeoAnalyzeContent()">
                    <?php \_e( 'Analyze Content', 'khm-seo' ); ?>
                </button>
            </div>
            
            <div id="analysis-results" class="khm-seo-analysis-results" style="display: none;">
                <h3><?php \_e( 'Analysis Results', 'khm-seo' ); ?></h3>
                <div id="analysis-content"></div>
            </div>
        </div>

        <!-- Bulk SEO Editor -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'Bulk SEO Editor', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Edit SEO titles and descriptions for multiple posts at once.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <label for="bulk-post-type"><?php \_e( 'Post Type:', 'khm-seo' ); ?></label>
                <select id="bulk-post-type">
                    <?php
                    $post_types = \get_post_types( array( 'public' => true ), 'objects' );
                    foreach ( $post_types as $post_type ) :
                        if ( $post_type->name === 'attachment' ) continue;
                    ?>
                    <option value="<?php echo \esc_attr( $post_type->name ); ?>">
                        <?php echo \esc_html( $post_type->labels->name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button-primary" onclick="khmSeoLoadBulkEditor()">
                    <?php \_e( 'Load Bulk Editor', 'khm-seo' ); ?>
                </button>
            </div>
            
            <div id="bulk-editor-results" class="khm-seo-bulk-editor" style="display: none;">
                <div id="bulk-editor-content"></div>
            </div>
        </div>

        <!-- SEO Report -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'SEO Site Report', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Generate a comprehensive SEO report for your entire website.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <button type="button" class="button-primary" onclick="khmSeoGenerateReport()">
                    <?php \_e( 'Generate SEO Report', 'khm-seo' ); ?>
                </button>
                <label>
                    <input type="checkbox" id="include-content-analysis" checked />
                    <?php \_e( 'Include Content Analysis', 'khm-seo' ); ?>
                </label>
                <label>
                    <input type="checkbox" id="include-technical-seo" checked />
                    <?php \_e( 'Include Technical SEO', 'khm-seo' ); ?>
                </label>
            </div>
            
            <div id="seo-report-results" class="khm-seo-report" style="display: none;">
                <div id="seo-report-content"></div>
            </div>
        </div>

        <!-- Import/Export -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'Import / Export', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Import SEO data from other plugins or export your current settings.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <h3><?php \_e( 'Export Settings', 'khm-seo' ); ?></h3>
                <button type="button" class="button" onclick="khmSeoExportSettings()">
                    <?php \_e( 'Export KHM SEO Settings', 'khm-seo' ); ?>
                </button>
                
                <h3><?php \_e( 'Import from Other Plugins', 'khm-seo' ); ?></h3>
                <select id="import-source">
                    <option value=""><?php \_e( 'Select Plugin', 'khm-seo' ); ?></option>
                    <option value="yoast"><?php \_e( 'Yoast SEO', 'khm-seo' ); ?></option>
                    <option value="rankmath"><?php \_e( 'Rank Math', 'khm-seo' ); ?></option>
                    <option value="aioseo"><?php \_e( 'All in One SEO', 'khm-seo' ); ?></option>
                    <option value="seopress"><?php \_e( 'SEOPress', 'khm-seo' ); ?></option>
                </select>
                <button type="button" class="button" onclick="khmSeoImportData()">
                    <?php \_e( 'Import Data', 'khm-seo' ); ?>
                </button>
                
                <h3><?php \_e( 'Import Settings File', 'khm-seo' ); ?></h3>
                <input type="file" id="import-file" accept=".json" />
                <button type="button" class="button" onclick="khmSeoImportSettings()">
                    <?php \_e( 'Import Settings', 'khm-seo' ); ?>
                </button>
            </div>
            
            <div id="import-export-results" class="khm-seo-import-export" style="display: none;">
                <div id="import-export-content"></div>
            </div>
        </div>

        <!-- Robots.txt Editor -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'Robots.txt Editor', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Edit your robots.txt file directly from the admin panel.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <textarea id="robots-txt-content" rows="15" style="width: 100%; font-family: monospace;">
<?php echo \esc_textarea( \get_option( 'khm_seo_robots_txt', "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . \home_url('/sitemap.xml') ) ); ?>
                </textarea>
                <div class="khm-seo-robots-actions">
                    <button type="button" class="button-primary" onclick="khmSeoSaveRobotsTxt()">
                        <?php \_e( 'Save Robots.txt', 'khm-seo' ); ?>
                    </button>
                    <button type="button" class="button" onclick="khmSeoResetRobotsTxt()">
                        <?php \_e( 'Reset to Default', 'khm-seo' ); ?>
                    </button>
                    <a href="<?php echo \esc_url( \home_url( '/robots.txt' ) ); ?>" target="_blank" class="button">
                        <?php \_e( 'View Current Robots.txt', 'khm-seo' ); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- .htaccess Editor -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( '.htaccess Editor', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Edit your .htaccess file for SEO redirects and optimizations.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <?php if ( \is_writable( \ABSPATH . '.htaccess' ) ) : ?>
                <textarea id="htaccess-content" rows="15" style="width: 100%; font-family: monospace;">
<?php echo \esc_textarea( \file_get_contents( \ABSPATH . '.htaccess' ) ); ?>
                </textarea>
                <div class="khm-seo-htaccess-actions">
                    <button type="button" class="button-primary" onclick="khmSeoSaveHtaccess()">
                        <?php \_e( 'Save .htaccess', 'khm-seo' ); ?>
                    </button>
                    <button type="button" class="button" onclick="khmSeoBackupHtaccess()">
                        <?php \_e( 'Create Backup', 'khm-seo' ); ?>
                    </button>
                </div>
                <p class="description">
                    <strong><?php \_e( 'Warning:', 'khm-seo' ); ?></strong>
                    <?php \_e( 'Incorrect .htaccess rules can break your website. Always create a backup first.', 'khm-seo' ); ?>
                </p>
                <?php else : ?>
                <p class="khm-seo-error">
                    <?php \_e( '.htaccess file is not writable. Please check file permissions.', 'khm-seo' ); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- URL Inspector -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'URL Inspector', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Inspect how search engines see your URLs.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <label for="inspect-url"><?php \_e( 'URL to Inspect:', 'khm-seo' ); ?></label>
                <input type="url" id="inspect-url" placeholder="<?php \_e( 'https://example.com/page', 'khm-seo' ); ?>" />
                <button type="button" class="button-primary" onclick="khmSeoInspectUrl()">
                    <?php \_e( 'Inspect URL', 'khm-seo' ); ?>
                </button>
            </div>
            
            <div id="url-inspector-results" class="khm-seo-inspector" style="display: none;">
                <div id="url-inspector-content"></div>
            </div>
        </div>

        <!-- Keyword Density Analyzer -->
        <div class="khm-seo-tool-card">
            <h2><?php \_e( 'Keyword Density Analyzer', 'khm-seo' ); ?></h2>
            <p><?php \_e( 'Analyze keyword density and frequency in your content.', 'khm-seo' ); ?></p>
            
            <div class="khm-seo-tool-form">
                <label for="keyword-content"><?php \_e( 'Content to Analyze:', 'khm-seo' ); ?></label>
                <textarea id="keyword-content" rows="8" placeholder="<?php \_e( 'Paste your content here...', 'khm-seo' ); ?>"></textarea>
                <label for="focus-keyword"><?php \_e( 'Focus Keyword (optional):', 'khm-seo' ); ?></label>
                <input type="text" id="focus-keyword" placeholder="<?php \_e( 'Enter focus keyword', 'khm-seo' ); ?>" />
                <button type="button" class="button-primary" onclick="khmSeoAnalyzeKeywords()">
                    <?php \_e( 'Analyze Keywords', 'khm-seo' ); ?>
                </button>
            </div>
            
            <div id="keyword-analyzer-results" class="khm-seo-keyword-results" style="display: none;">
                <div id="keyword-analyzer-content"></div>
            </div>
        </div>

    </div>
</div>

<script>
// Content Analysis
function khmSeoAnalyzeContent() {
    const url = document.getElementById('analyze-url').value;
    if (!url) {
        alert('<?php \_e( "Please enter a URL or Post ID", "khm-seo" ); ?>');
        return;
    }
    
    document.getElementById('analysis-results').style.display = 'block';
    document.getElementById('analysis-content').innerHTML = '<p><?php \_e( "Analyzing content...", "khm-seo" ); ?></p>';
    
    jQuery.post(ajaxurl, {
        action: 'khm_seo_analyze_content',
        url: url,
        nonce: '<?php echo \wp_create_nonce( "khm_seo_analyze_content" ); ?>'
    }, function(response) {
        document.getElementById('analysis-content').innerHTML = response.data;
    });
}

// Bulk Editor
function khmSeoLoadBulkEditor() {
    const postType = document.getElementById('bulk-post-type').value;
    
    document.getElementById('bulk-editor-results').style.display = 'block';
    document.getElementById('bulk-editor-content').innerHTML = '<p><?php \_e( "Loading posts...", "khm-seo" ); ?></p>';
    
    jQuery.post(ajaxurl, {
        action: 'khm_seo_load_bulk_editor',
        post_type: postType,
        nonce: '<?php echo \wp_create_nonce( "khm_seo_bulk_editor" ); ?>'
    }, function(response) {
        document.getElementById('bulk-editor-content').innerHTML = response.data;
    });
}

// SEO Report
function khmSeoGenerateReport() {
    const includeContent = document.getElementById('include-content-analysis').checked;
    const includeTechnical = document.getElementById('include-technical-seo').checked;
    
    document.getElementById('seo-report-results').style.display = 'block';
    document.getElementById('seo-report-content').innerHTML = '<p><?php \_e( "Generating SEO report...", "khm-seo" ); ?></p>';
    
    jQuery.post(ajaxurl, {
        action: 'khm_seo_generate_report',
        include_content: includeContent,
        include_technical: includeTechnical,
        nonce: '<?php echo \wp_create_nonce( "khm_seo_generate_report" ); ?>'
    }, function(response) {
        document.getElementById('seo-report-content').innerHTML = response.data;
    });
}

// Export Settings
function khmSeoExportSettings() {
    window.location.href = ajaxurl + '?action=khm_seo_export_settings&nonce=<?php echo \wp_create_nonce( "khm_seo_export_settings" ); ?>';
}

// Import Data
function khmSeoImportData() {
    const source = document.getElementById('import-source').value;
    if (!source) {
        alert('<?php \_e( "Please select a source plugin", "khm-seo" ); ?>');
        return;
    }
    
    if (confirm('<?php \_e( "This will overwrite existing SEO data. Continue?", "khm-seo" ); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'khm_seo_import_data',
            source: source,
            nonce: '<?php echo \wp_create_nonce( "khm_seo_import_data" ); ?>'
        }, function(response) {
            alert(response.data);
            if (response.success) {
                location.reload();
            }
        });
    }
}

// Import Settings
function khmSeoImportSettings() {
    const file = document.getElementById('import-file').files[0];
    if (!file) {
        alert('<?php \_e( "Please select a settings file", "khm-seo" ); ?>');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        jQuery.post(ajaxurl, {
            action: 'khm_seo_import_settings',
            settings: e.target.result,
            nonce: '<?php echo \wp_create_nonce( "khm_seo_import_settings" ); ?>'
        }, function(response) {
            alert(response.data);
            if (response.success) {
                location.reload();
            }
        });
    };
    reader.readAsText(file);
}

// Save Robots.txt
function khmSeoSaveRobotsTxt() {
    const content = document.getElementById('robots-txt-content').value;
    
    jQuery.post(ajaxurl, {
        action: 'khm_seo_save_robots_txt',
        content: content,
        nonce: '<?php echo \wp_create_nonce( "khm_seo_save_robots_txt" ); ?>'
    }, function(response) {
        alert(response.data);
    });
}

// Reset Robots.txt
function khmSeoResetRobotsTxt() {
    if (confirm('<?php \_e( "Reset robots.txt to default content?", "khm-seo" ); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'khm_seo_reset_robots_txt',
            nonce: '<?php echo \wp_create_nonce( "khm_seo_reset_robots_txt" ); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('robots-txt-content').value = response.data;
                alert('<?php \_e( "Robots.txt reset to default", "khm-seo" ); ?>');
            }
        });
    }
}

// Save .htaccess
function khmSeoSaveHtaccess() {
    const content = document.getElementById('htaccess-content').value;
    
    if (confirm('<?php \_e( "Save changes to .htaccess? Incorrect rules can break your site.", "khm-seo" ); ?>')) {
        jQuery.post(ajaxurl, {
            action: 'khm_seo_save_htaccess',
            content: content,
            nonce: '<?php echo \wp_create_nonce( "khm_seo_save_htaccess" ); ?>'
        }, function(response) {
            alert(response.data);
        });
    }
}

// Backup .htaccess
function khmSeoBackupHtaccess() {
    jQuery.post(ajaxurl, {
        action: 'khm_seo_backup_htaccess',
        nonce: '<?php echo \wp_create_nonce( "khm_seo_backup_htaccess" ); ?>'
    }, function(response) {
        alert(response.data);
    });
}

// URL Inspector
function khmSeoInspectUrl() {
    const url = document.getElementById('inspect-url').value;
    if (!url) {
        alert('<?php \_e( "Please enter a URL to inspect", "khm-seo" ); ?>');
        return;
    }
    
    document.getElementById('url-inspector-results').style.display = 'block';
    document.getElementById('url-inspector-content').innerHTML = '<p><?php \_e( "Inspecting URL...", "khm-seo" ); ?></p>';
    
    jQuery.post(ajaxurl, {
        action: 'khm_seo_inspect_url',
        url: url,
        nonce: '<?php echo \wp_create_nonce( "khm_seo_inspect_url" ); ?>'
    }, function(response) {
        document.getElementById('url-inspector-content').innerHTML = response.data;
    });
}

// Keyword Analyzer
function khmSeoAnalyzeKeywords() {
    const content = document.getElementById('keyword-content').value;
    const keyword = document.getElementById('focus-keyword').value;
    
    if (!content) {
        alert('<?php \_e( "Please enter content to analyze", "khm-seo" ); ?>');
        return;
    }
    
    document.getElementById('keyword-analyzer-results').style.display = 'block';
    document.getElementById('keyword-analyzer-content').innerHTML = '<p><?php \_e( "Analyzing keywords...", "khm-seo" ); ?></p>';
    
    jQuery.post(ajaxurl, {
        action: 'khm_seo_analyze_keywords',
        content: content,
        keyword: keyword,
        nonce: '<?php echo \wp_create_nonce( "khm_seo_analyze_keywords" ); ?>'
    }, function(response) {
        document.getElementById('keyword-analyzer-content').innerHTML = response.data;
    });
}
</script>

<style>
.khm-seo-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.khm-seo-tool-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.khm-seo-tool-card h2 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.khm-seo-tool-form {
    margin-top: 15px;
}

.khm-seo-tool-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    margin-top: 15px;
}

.khm-seo-tool-form input,
.khm-seo-tool-form select,
.khm-seo-tool-form textarea {
    width: 100%;
    margin-bottom: 10px;
}

.khm-seo-tool-form button {
    margin-top: 10px;
}

.khm-seo-analysis-results,
.khm-seo-bulk-editor,
.khm-seo-report,
.khm-seo-import-export,
.khm-seo-inspector,
.khm-seo-keyword-results {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.khm-seo-robots-actions,
.khm-seo-htaccess-actions {
    margin-top: 15px;
}

.khm-seo-robots-actions button,
.khm-seo-htaccess-actions button,
.khm-seo-robots-actions a {
    margin-right: 10px;
}

.khm-seo-error {
    background: #ffeaa7;
    border: 1px solid #fdcb6e;
    border-radius: 4px;
    padding: 10px;
    color: #2d3436;
}

@media (max-width: 768px) {
    .khm-seo-tools-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<?php

namespace KHM\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF Service for Article Downloads
 * 
 * Generates PDFs from WordPress posts/articles for member downloads
 * Extends the existing DomPDF infrastructure from InvoiceService
 */
class PDFService {

    /**
     * Generate PDF from WordPress post
     *
     * @param int $post_id WordPress post ID
     * @param int $user_id User requesting the PDF (for tracking)
     * @return array ['success' => bool, 'pdf_data' => string, 'filename' => string, 'error' => string]
     */
    public function generateArticlePDF(int $post_id, int $user_id): array {
        $post = get_post($post_id);
        
        if (!$post || $post->post_status !== 'publish') {
            return [
                'success' => false,
                'error' => 'Article not found or not published'
            ];
        }

        try {
            // Generate HTML content
            $html = $this->buildArticleHTML($post);
            
            // Configure PDF options
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', true);
            
            // Create PDF
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $filename = $this->generateFilename($post);
            
            // Log the download
            do_action('khm_article_pdf_generated', $post_id, $user_id, $filename);
            
            return [
                'success' => true,
                'pdf_data' => $dompdf->output(),
                'filename' => $filename,
                'size' => strlen($dompdf->output())
            ];
            
        } catch (\Exception $e) {
            error_log('PDF Generation Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to generate PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build HTML content for PDF generation
     *
     * @param \WP_Post $post
     * @return string
     */
    private function buildArticleHTML(\WP_Post $post): string {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $post_url = get_permalink($post->ID);
        $date = get_the_date('F j, Y', $post->ID);
        $featured_image = $this->getFeaturedImageHTML($post->ID);
        $logo_html = $this->getSiteLogoHTML();
        $authors_html = $this->getAuthorsHTML($post);
        $summary = $this->getSummary($post);
        $footnotes_html = $this->getFootnotesHTML($post);
        
        // Extract abstract from raw content (ACF block) before applying filters
        $abstract_html = '';
        $raw_content = $this->extractAbstractFromContent($post->post_content, $abstract_html);
        
        // Get post content and apply filters
        $content = apply_filters('the_content', $raw_content);
        $content = $this->cleanContentForPDF($content);
        
        // Extract kss-footnotes from content (to place after author section)
        $inline_footnotes_html = '';
        $content = $this->extractFootnotesFromContent($content, $inline_footnotes_html);
        
        // Format content as two columns using table (DOMPDF doesn't support CSS columns)
        $two_column_content = $this->formatTwoColumnContent($content);
        
        // Get author names for cover page
        $cover_authors = $this->getCoverAuthorsHTML($post);
        
        // Escape article title for use in PHP script
        $escaped_title = addslashes($post->post_title);
        if (strlen($escaped_title) > 70) {
            $escaped_title = substr($escaped_title, 0, 67) . '...';
        }
        $escaped_site_name = addslashes($site_name);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$post->post_title} - {$site_name}</title>
            <style>
                {$this->getPDFStyles()}
            </style>
        </head>
        <body>
            <!-- Running header/footer - appears on all pages -->
            <div class='running-header'>{$post->post_title}</div>
            <div class='running-footer'>{$site_name}</div>

            
            <!-- COVER PAGE -->
            <div class='cover-page'>
                <div class='cover-header'>
                    {$logo_html}
                    <p class='site-url'>{$site_url}</p>
                    <p class='publish-date'>Published: {$date}</p>
                </div>
                
                <div class='cover-divider'></div>
                
                <div class='cover-content'>
                    {$featured_image}
                    <h1 class='article-title'>{$post->post_title}</h1>
                    <p class='cover-authors'>By: {$cover_authors}</p>
                </div>
            </div>
            
            <div style='page-break-after: always;'></div>
            
            <!-- ABSTRACT PAGE -->
            <div class='abstract-page'>
                {$abstract_html}
            </div>
            
            <div style='page-break-after: always;'></div>
            
            <!-- ARTICLE CONTENT (Two Columns via Table) -->
            <div class='article-page'>
                {$two_column_content}
            </div>
            
            <div style='page-break-after: always;'></div>
            
            <!-- AUTHOR PAGE -->
            <div class='author-page'>
                <div class='author-section'>
                    <h2 class='section-title'>About the Author</h2>
                    <div class='author-details'>
                        {$authors_html}
                    </div>
                </div>
            </div>
            
            <!-- FOOTNOTES PAGE -->
            <div class='footnotes-page' style='page-break-before: always;'>
                {$inline_footnotes_html}
                {$footnotes_html}
                
                <div class='pdf-footer'>
                    <p class='original-link'>Original article: <a href='{$post_url}'>{$post_url}</a></p>
                    <p class='copyright'>© " . date('Y') . " {$site_name}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get site logo HTML
     *
     * @return string
     */
    private function getSiteLogoHTML(): string {
        $logo_id = get_theme_mod('custom_logo');
        if (!$logo_id) {
            // Fallback to site name
            return '<h1 class="site-name">' . esc_html(get_bloginfo('name')) . '</h1>';
        }
        
        $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
        return "<img src='{$logo_url}' alt='" . esc_attr(get_bloginfo('name')) . "' class='site-logo'>";
    }
    
    /**
     * Get author data from Multiple Authors plugin (multi_author post type)
     *
     * @param \WP_Post $post
     * @return array Array of author data
     */
    private function getMultipleAuthorsData(\WP_Post $post): array {
        $author_ids = get_post_meta($post->ID, 'authors', true);
        $authors_data = [];
        
        if (!empty($author_ids) && is_array($author_ids)) {
            foreach ($author_ids as $author_id) {
                // multi_author is a custom post type
                $author_post = get_post($author_id);
                if ($author_post && $author_post->post_type === 'multi_author') {
                    $photo_id = get_post_meta($author_id, 'author_photo', true);
                    $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
                    
                    $authors_data[] = [
                        'name' => get_post_meta($author_id, 'author_name', true) ?: $author_post->post_title,
                        'title' => get_post_meta($author_id, 'author_title', true),
                        'company' => get_post_meta($author_id, 'author_company', true),
                        'bio' => get_post_meta($author_id, 'author_bio', true),
                        'photo_url' => $photo_url,
                    ];
                }
            }
        }
        
        // Fallback to post author if no multi_author found
        if (empty($authors_data)) {
            $user = get_user_by('id', $post->post_author);
            if ($user) {
                $authors_data[] = [
                    'name' => $user->display_name,
                    'title' => get_user_meta($user->ID, 'khm_job_title', true),
                    'company' => get_user_meta($user->ID, 'khm_company', true),
                    'bio' => get_the_author_meta('description', $user->ID),
                    'photo_url' => get_avatar_url($user->ID),
                ];
            }
        }
        
        return $authors_data;
    }
    
    /**
     * Get author names for cover page (small font)
     *
     * @param \WP_Post $post
     * @return string
     */
    private function getCoverAuthorsHTML(\WP_Post $post): string {
        $authors_data = $this->getMultipleAuthorsData($post);
        
        if (empty($authors_data)) {
            return '';
        }
        
        $names = array_map(function($a) { return esc_html($a['name']); }, $authors_data);
        return implode(', ', $names);
    }
    
    /**
     * Get full authors HTML for author section (with photo, bio, etc.)
     *
     * @param \WP_Post $post
     * @return string
     */
    private function getAuthorsHTML(\WP_Post $post): string {
        $authors_data = $this->getMultipleAuthorsData($post);
        
        if (empty($authors_data)) {
            return '<p>No author information available.</p>';
        }
        
        $html = '';
        foreach ($authors_data as $author) {
            $html .= '<div class="author-card">';
            
            // Author photo
            if (!empty($author['photo_url'])) {
                $html .= '<img src="' . esc_url($author['photo_url']) . '" alt="' . esc_attr($author['name']) . '" class="author-photo">';
            }
            
            $html .= '<div class="author-info">';
            $html .= '<p class="author-name"><strong>' . esc_html($author['name']) . '</strong></p>';
            
            if (!empty($author['title']) || !empty($author['company'])) {
                $role_parts = array_filter([$author['title'], $author['company']]);
                $html .= '<p class="author-role">' . esc_html(implode(', ', $role_parts)) . '</p>';
            }
            
            if (!empty($author['bio'])) {
                $html .= '<p class="author-bio">' . esc_html($author['bio']) . '</p>';
            }
            
            $html .= '</div></div>';
        }
        
        return $html;
    }
    
    /**
     * Get summary/excerpt
     *
     * @param \WP_Post $post
     * @return string
     */
    private function getSummary(\WP_Post $post): string {
        // Use excerpt if available
        if (!empty($post->post_excerpt)) {
            return '<p>' . esc_html($post->post_excerpt) . '</p>';
        }
        
        // Generate excerpt from content
        $content = strip_tags($post->post_content);
        $excerpt = wp_trim_words($content, 100, '...');
        return '<p>' . esc_html($excerpt) . '</p>';
    }
    
    /**
     * Extract abstract block from raw content if present
     * Parses ACF abstract Gutenberg block from raw post content
     *
     * @param string $content The raw post content
     * @param string &$abstract_html Reference to store extracted abstract HTML
     * @return string Content with abstract block removed
     */
    private function extractAbstractFromContent(string $content, string &$abstract_html): string {
        // Pattern to match ACF abstract block: <!-- wp:acf/abstract {"name":"acf/abstract","data":{...}} /-->
        $pattern = '/<!-- wp:acf\/abstract \{"name":"acf\/abstract","data":(\{.+?\}),"mode":"[^"]*"\} \/-->/s';
        
        if (preg_match($pattern, $content, $matches)) {
            // Parse the JSON data
            $data = json_decode($matches[1], true);
            
            if ($data) {
                $html = '<div class="abstract-block">';
                $html .= '<h2 class="abstract-title">Abstract</h2>';
                
                // Overview section
                if (!empty($data['overview'])) {
                    $html .= '<div class="abstract-section">';
                    $html .= '<h3>Overview</h3>';
                    $html .= '<p>' . esc_html($data['overview']) . '</p>';
                    $html .= '</div>';
                }
                
                // Context section
                if (!empty($data['context'])) {
                    $html .= '<div class="abstract-section">';
                    $html .= '<h3>Context</h3>';
                    $html .= '<p>' . esc_html($data['context']) . '</p>';
                    $html .= '</div>';
                }
                
                // Application section
                if (!empty($data['application'])) {
                    $html .= '<div class="abstract-section">';
                    $html .= '<h3>Application</h3>';
                    $html .= '<p>' . esc_html($data['application']) . '</p>';
                    $html .= '</div>';
                }
                
                // Key points
                $key_points_count = intval($data['key_points'] ?? 0);
                if ($key_points_count > 0) {
                    $html .= '<div class="abstract-section">';
                    $html .= '<h3>Key Points</h3>';
                    $html .= '<ul class="key-points-list">';
                    for ($i = 0; $i < $key_points_count; $i++) {
                        $bullet_key = "key_points_{$i}_bullet";
                        if (!empty($data[$bullet_key])) {
                            $html .= '<li>' . esc_html($data[$bullet_key]) . '</li>';
                        }
                    }
                    $html .= '</ul>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
                $abstract_html = $html;
            }
            
            // Remove the ACF block from content
            $content = preg_replace($pattern, '', $content, 1);
        }
        
        return $content;
    }
    
    /**
     * Extract kss-footnotes div from content and return separately
     *
     * @param string $content The post content
     * @param string &$footnotes_html Reference to store extracted footnotes
     * @return string Content with footnotes removed
     */
    private function extractFootnotesFromContent(string $content, string &$footnotes_html): string {
        // Pattern to match the kss-footnotes div and everything inside it
        $pattern = '/<div[^>]*class="[^"]*\bkss-footnotes\b[^"]*"[^>]*>.*?<\/div>/si';
        
        if (preg_match($pattern, $content, $matches)) {
            // Clean up the footnotes HTML - remove the hr divider at the end
            $footnotes_html = preg_replace('/<hr[^>]*class="[^"]*footnotes-divider[^"]*"[^>]*\/?>/i', '', $matches[0]);
            // Remove the original from content
            $content = preg_replace($pattern, '', $content, 1);
        }
        
        return $content;
    }
    
    /**
     * Format content for PDF layout
     * Note: DOMPDF doesn't support CSS columns and has issues with multi-page table cells
     * For now, we use a single-column layout for reliable rendering
     *
     * @param string $content The HTML content
     * @return string Formatted HTML
     */
    private function formatTwoColumnContent(string $content): string {
        // DOMPDF limitation: table cells cannot span multiple pages
        // Using single column layout for reliable rendering
        return "<div class='article-body'>{$content}</div>";
    }

    /**
     * Get footnotes HTML
     *
     * @param \WP_Post $post
     * @return string
     */
    private function getFootnotesHTML(\WP_Post $post): string {
        // Check for footnotes custom field
        $footnotes = get_post_meta($post->ID, 'footnotes', true);
        
        if (empty($footnotes)) {
            return '';
        }
        
        return "
            <div class='footnotes-section'>
                <h2 class='section-title'>Footnotes</h2>
                <div class='footnotes-content'>
                    {$footnotes}
                </div>
            </div>";
    }

    /**
     * Get CSS styles for PDF
     *
     * @return string
     */
    private function getPDFStyles(): string {
        // Brand accent color - used only for borders/accents
        $accent = '#6d0b0b';
        
        return "
            /* Google Fonts - matching site fonts */
            @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Barlow:wght@400;600;700&family=Lora:wght@400;500;600&display=swap');
            
            /* Base styles */
            body {
                font-family: 'DM Sans', 'DejaVu Sans', Arial, sans-serif;
                font-size: 10pt;
                line-height: 1.6;
                margin: 0;
                padding: 0;
                color: #333;
            }
            
            @page {
                margin: 2.5cm 2cm 2.5cm 2cm; /* top right bottom left - extra space for header/footer */
            }
            
            /* Running header - fixed to top of every page */
            .running-header {
                position: fixed;
                top: -55px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 9pt;
                color: #333;
                padding: 10px 40px;
                border-bottom: 0.5pt solid {$accent};
            }
            
            /* Running footer - fixed to bottom of every page */
            .running-footer {
                position: fixed;
                bottom: -50px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 9pt;
                color: #333;
                padding: 10px 40px;
                border-top: 0.5pt solid {$accent};
            }
            
            /* ========== COVER PAGE ========== */
            .cover-page {
            }
            
            .cover-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .site-logo {
                max-width: 200px;
                max-height: 80px;
                margin-bottom: 10px;
            }
            
            .site-name {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                font-size: 24pt;
                font-weight: 700;
                color: #000;
                margin: 0 0 10px 0;
            }
            
            .site-url {
                font-size: 10pt;
                color: #666;
                margin: 5px 0;
            }
            
            .publish-date {
                font-size: 9pt;
                color: #666;
                margin: 5px 0;
            }
            
            .cover-divider {
                height: 3px;
                background: {$accent};
                margin: 25px 0;
            }
            
            .cover-content {
                text-align: center;
            }
            
            .cover-content .featured-image {
                max-width: 100%;
                max-height: 350px;
                margin: 20px auto;
                border-radius: 5px;
            }
            
            .article-title {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                font-size: 22pt;
                font-weight: 700;
                color: #000;
                margin: 20px 0 15px 0;
                line-height: 1.3;
                text-align: center;
            }
            
            .cover-authors {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 10pt;
                color: #555;
                margin: 10px 0 0 0;
                font-style: italic;
            }
            
            /* ========== SUMMARY PAGE ========== */
            .summary-page {
                page-break-before: always;
                page-break-after: always;
            }
            
            .section-title {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                font-size: 18pt;
                font-weight: 600;
                color: #000;
                border-bottom: 2px solid {$accent};
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            
            .summary-content {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 11pt;
                line-height: 1.8;
                text-align: justify;
                padding: 20px;
                background: #f8f9fa;
                border-left: 4px solid {$accent};
            }
            
            .summary-content p {
                margin: 0;
            }
            
            /* ========== ABSTRACT PAGE ========== */
            .abstract-page {
                padding: 20px 0;
            }
            
            .abstract-block {
                margin: 0;
                padding: 0;
            }
            
            .abstract-title {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                font-size: 24pt;
                color: {$accent};
                margin: 0 0 20px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid {$accent};
            }
            
            .abstract-section {
                margin-bottom: 20px;
            }
            
            .abstract-section h3 {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                font-size: 14pt;
                color: #333;
                margin: 0 0 8px 0;
                font-weight: 600;
            }
            
            .abstract-section p {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                margin: 0;
                color: #333;
            }
            
            .key-points-list {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                margin: 0;
                padding-left: 20px;
                color: #333;
            }
            
            .key-points-list li {
                margin-bottom: 8px;
            }
            
            /* ========== ARTICLE PAGE (Two Column via Table) ========== */
            .article-page {
                font-family: 'Lora', 'DejaVu Serif', Georgia, serif;
            }
            
            .two-column-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }
            
            .two-column-table td {
                width: 48%;
                vertical-align: top;
                padding: 0 15px;
                font-family: 'Lora', 'DejaVu Serif', Georgia, serif;
                font-size: 10pt;
                line-height: 1.6;
                text-align: justify;
            }
            
            .two-column-table .column-left {
                padding-left: 0;
                padding-right: 15px;
                border-right: 1px solid #ddd;
            }
            
            .two-column-table .column-right {
                padding-left: 15px;
                padding-right: 0;
            }
            
            .article-body.single-column {
                font-family: 'Lora', 'DejaVu Serif', Georgia, serif;
                font-size: 10pt;
                line-height: 1.6;
                text-align: justify;
            }
            
            .two-column-table h1, .two-column-table h2, .two-column-table h3,
            .article-body h1, .article-body h2, .article-body h3 {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                color: #000;
                margin-top: 20px;
                margin-bottom: 10px;
            }
            
            .two-column-table h1, .article-body h1 { font-size: 16pt; font-weight: 700; }
            .two-column-table h2, .article-body h2 { font-size: 14pt; font-weight: 600; }
            .two-column-table h3, .article-body h3 { font-size: 12pt; font-weight: 600; }
            
            .two-column-table p, .article-body p {
                margin-bottom: 10px;
                orphans: 3;
                widows: 3;
            }
            
            .two-column-table blockquote, .article-body blockquote {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                border-left: 3px solid {$accent};
                padding-left: 15px;
                margin: 15px 0;
                font-style: italic;
                color: #555;
            }
            
            .two-column-table ul, .two-column-table ol,
            .article-body ul, .article-body ol {
                margin: 12px 0;
                padding-left: 20px;
            }
            
            .two-column-table li, .article-body li {
                margin-bottom: 4px;
            }
            
            .article-body img {
                max-width: 100%;
                height: auto;
                margin: 10px 0;
                border-radius: 3px;
            }
            
            .article-body code {
                background: #f4f4f4;
                padding: 1px 3px;
                border-radius: 2px;
                font-family: 'Courier New', monospace;
                font-size: 8pt;
            }
            
            .article-body pre {
                background: #f4f4f4;
                padding: 10px;
                border-radius: 3px;
                overflow-x: auto;
                font-family: 'Courier New', monospace;
                font-size: 8pt;
                line-height: 1.3;
                column-span: all;
            }
            
            /* ========== FOOTNOTES PAGE ========== */
            .footnotes-page {
                page-break-before: always;
            }
            
            .footnotes-section {
                margin-bottom: 40px;
            }
            
            .footnotes-content {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 9pt;
                line-height: 1.5;
                color: #555;
            }
            
            .footnotes-content p {
                margin-bottom: 8px;
                padding-left: 20px;
                text-indent: -20px;
            }
            
            /* ========== AUTHOR SECTION ========== */
            .author-section {
                margin-top: 40px;
                padding-top: 20px;
            }
            
            .author-details {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 5px;
            }
            
            .author-card {
                margin-bottom: 20px;
                overflow: hidden;
            }
            
            .author-card:last-child {
                margin-bottom: 0;
            }
            
            .author-photo {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                float: left;
                margin-right: 15px;
                border: 2px solid {$accent};
            }
            
            .author-info {
                overflow: hidden;
            }
            
            .author-name {
                font-family: 'Barlow', 'DejaVu Sans', sans-serif;
                font-size: 12pt;
                font-weight: 600;
                color: #000;
                margin: 0 0 5px 0;
            }
            
            .author-role {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 10pt;
                color: #666;
                margin: 0 0 8px 0;
            }
            
            .author-bio {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 9pt;
                color: #555;
                line-height: 1.5;
                margin: 0;
            }
            
            /* ========== FOOTER ========== */
            .pdf-footer {
                margin-top: 40px;
            }
            
            .footer-divider {
                height: 2px;
                background: {$accent};
                margin-bottom: 20px;
            }
            
            .original-link {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 9pt;
                color: #666;
                text-align: center;
                margin: 10px 0;
            }
            
            .original-link a {
                color: {$accent};
                text-decoration: none;
            }
            
            .copyright {
                font-family: 'DM Sans', 'DejaVu Sans', sans-serif;
                font-size: 9pt;
                color: #999;
                text-align: center;
                margin: 5px 0;
            }
            
            /* ========== UTILITY CLASSES ========== */
            .featured-image {
                max-width: 100%;
                height: auto;
            }
        ";
    }

    /**
     * Get featured image HTML for PDF
     *
     * @param int $post_id
     * @return string
     */
    private function getFeaturedImageHTML(int $post_id): string {
        if (!has_post_thumbnail($post_id)) {
            return '';
        }
        
        $image_url = get_the_post_thumbnail_url($post_id, 'large');
        $image_alt = get_post_meta(get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true);
        
        return "<img src='{$image_url}' alt='{$image_alt}' class='featured-image'>";
    }

    /**
     * Get post terms (categories/tags) HTML
     *
     * @param int $post_id
     * @param string $taxonomy
     * @return string
     */
    private function getPostTermsHTML(int $post_id, string $taxonomy): string {
        $terms = get_the_terms($post_id, $taxonomy);
        
        if (!$terms || is_wp_error($terms)) {
            return '';
        }
        
        $term_names = array_map(function($term) {
            return $term->name;
        }, $terms);
        
        $label = $taxonomy === 'category' ? 'Categories' : 'Tags';
        $term_list = implode(', ', $term_names);
        
        return "<p class='{$taxonomy}'><strong>{$label}:</strong> {$term_list}</p>";
    }

    /**
     * Clean content for PDF generation
     *
     * @param string $content
     * @return string
     */
    private function cleanContentForPDF(string $content): string {
        // Remove shortcodes that might not render properly in PDF
        $content = strip_shortcodes($content);
        
        // Remove or replace problematic HTML elements
        $content = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $content);
        $content = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '[Video/Interactive Content]', $content);
        
        // Convert relative URLs to absolute
        $site_url = rtrim(get_bloginfo('url'), '/');
        $content = preg_replace('/src="\/([^"]*)"/', 'src="' . $site_url . '/$1"', $content);
        $content = preg_replace('/href="\/([^"]*)"/', 'href="' . $site_url . '/$1"', $content);
        
        return $content;
    }

    /**
     * Generate PDF filename
     *
     * @param \WP_Post $post
     * @return string
     */
    private function generateFilename(\WP_Post $post): string {
        $title = sanitize_title($post->post_title);
        $date = get_the_date('Y-m-d', $post->ID);
        
        return "{$date}_{$title}.pdf";
    }

    /**
     * Create secure download URL for PDF
     *
     * @param int $post_id
     * @param int $user_id
     * @param int $expires_hours
     * @return string
     */
    public function createDownloadURL(int $post_id, int $user_id, int $expires_hours = 2): string {
        $token = wp_create_nonce("pdf_download_{$post_id}_{$user_id}");
        $expires = time() + ($expires_hours * 3600);
        
        return add_query_arg([
            'action' => 'khm_download_pdf',
            'post_id' => $post_id,
            'user_id' => $user_id,
            'token' => $token,
            'expires' => $expires
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Verify download token and handle PDF download
     *
     * @param array $params Request parameters
     * @return array|void
     */
    public function handleDownloadRequest(array $params) {
        $post_id = intval($params['post_id'] ?? 0);
        $user_id = intval($params['user_id'] ?? 0);
        $token = sanitize_text_field($params['token'] ?? '');
        $expires = intval($params['expires'] ?? 0);
        
        // Verify token
        if (!wp_verify_nonce($token, "pdf_download_{$post_id}_{$user_id}")) {
            wp_die('Invalid download token', 'Download Error', ['response' => 403]);
        }
        
        // Check expiration
        if (time() > $expires) {
            wp_die('Download link has expired', 'Download Error', ['response' => 410]);
        }
        
        // Verify user access
        if (get_current_user_id() !== $user_id) {
            wp_die('Unauthorized access', 'Download Error', ['response' => 403]);
        }
        
        // Generate and serve PDF
        $result = $this->generateArticlePDF($post_id, $user_id);
        
        if (!$result['success']) {
            wp_die($result['error'], 'PDF Generation Error', ['response' => 500]);
        }
        
        // Set headers and output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        header('Content-Length: ' . $result['size']);
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $result['pdf_data'];
        exit;
    }
}
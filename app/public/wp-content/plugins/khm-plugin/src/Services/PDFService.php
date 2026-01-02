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
        $author = get_the_author_meta('display_name', $post->post_author);
        $date = get_the_date('F j, Y', $post->ID);
        $featured_image = $this->getFeaturedImageHTML($post->ID);
        
        // Get post content and apply filters
        $content = apply_filters('the_content', $post->post_content);
        $content = $this->cleanContentForPDF($content);
        
        // Get categories and tags
        $categories = $this->getPostTermsHTML($post->ID, 'category');
        $tags = $this->getPostTermsHTML($post->ID, 'post_tag');
        
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
            <div class='pdf-header'>
                <div class='site-info'>
                    <h1>{$site_name}</h1>
                    <p>{$site_url}</p>
                </div>
                <div class='download-info'>
                    <p>Downloaded: " . current_time('F j, Y g:i A') . "</p>
                </div>
            </div>
            
            <div class='article-content'>
                <div class='article-header'>
                    {$featured_image}
                    <h1 class='article-title'>{$post->post_title}</h1>
                    <div class='article-meta'>
                        <p><strong>By:</strong> {$author}</p>
                        <p><strong>Published:</strong> {$date}</p>
                        {$categories}
                        {$tags}
                    </div>
                </div>
                
                <div class='article-body'>
                    {$content}
                </div>
            </div>
            
            <div class='pdf-footer'>
                <p>Original article: <a href='{$post_url}'>{$post_url}</a></p>
                <p>© " . date('Y') . " {$site_name}. All rights reserved.</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Get CSS styles for PDF
     *
     * @return string
     */
    private function getPDFStyles(): string {
        return "
            body {
                font-family: 'DejaVu Sans', Arial, sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            
            .pdf-header {
                display: flex;
                justify-content: space-between;
                border-bottom: 2px solid #2c3e50;
                padding-bottom: 15px;
                margin-bottom: 30px;
            }
            
            .site-info h1 {
                font-size: 18pt;
                margin: 0;
                color: #2c3e50;
            }
            
            .site-info p {
                margin: 5px 0 0 0;
                font-size: 9pt;
                color: #666;
            }
            
            .download-info p {
                margin: 0;
                font-size: 9pt;
                color: #666;
            }
            
            .article-header {
                margin-bottom: 30px;
            }
            
            .featured-image {
                max-width: 100%;
                height: auto;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            
            .article-title {
                font-size: 24pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 0 0 15px 0;
                line-height: 1.3;
            }
            
            .article-meta {
                background: #f8f9fa;
                padding: 15px;
                border-left: 4px solid #3498db;
                margin-bottom: 25px;
            }
            
            .article-meta p {
                margin: 5px 0;
                font-size: 10pt;
            }
            
            .categories, .tags {
                font-size: 9pt;
                color: #666;
            }
            
            .article-body {
                margin-bottom: 40px;
            }
            
            .article-body h1, .article-body h2, .article-body h3 {
                color: #2c3e50;
                margin-top: 25px;
                margin-bottom: 15px;
            }
            
            .article-body h1 { font-size: 18pt; }
            .article-body h2 { font-size: 16pt; }
            .article-body h3 { font-size: 14pt; }
            
            .article-body p {
                margin-bottom: 12px;
                text-align: justify;
            }
            
            .article-body blockquote {
                border-left: 4px solid #3498db;
                padding-left: 20px;
                margin: 20px 0;
                font-style: italic;
                color: #555;
            }
            
            .article-body ul, .article-body ol {
                margin: 15px 0;
                padding-left: 25px;
            }
            
            .article-body li {
                margin-bottom: 5px;
            }
            
            .article-body img {
                max-width: 100%;
                height: auto;
                margin: 15px 0;
                border-radius: 3px;
            }
            
            .article-body code {
                background: #f4f4f4;
                padding: 2px 4px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                font-size: 9pt;
            }
            
            .article-body pre {
                background: #f4f4f4;
                padding: 15px;
                border-radius: 5px;
                overflow-x: auto;
                font-family: 'Courier New', monospace;
                font-size: 9pt;
                line-height: 1.4;
            }
            
            .pdf-footer {
                border-top: 1px solid #ddd;
                padding-top: 15px;
                margin-top: 40px;
                font-size: 9pt;
                color: #666;
                text-align: center;
            }
            
            .pdf-footer a {
                color: #3498db;
                text-decoration: none;
            }
            
            @page {
                margin: 2cm;
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
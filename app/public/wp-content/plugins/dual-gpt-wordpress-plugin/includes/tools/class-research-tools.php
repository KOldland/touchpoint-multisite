<?php
/**
 * Research Tools for Dual-GPT Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Research_Tools {

    /**
     * Web search tool - Enhanced implementation
     */
    public function web_search($query, $top_k = 10, $site_filter = array()) {
        // In production, this would use a real search API like:
        // - Google Custom Search API
        // - Bing Web Search API
        // - DuckDuckGo Instant Answer API
        // - Serply (privacy-focused search API)

        // For now, we'll simulate realistic search results
        // In production, replace this with actual API calls

        $results = array();

        // Simulate different types of results based on query
        $query_lower = strtolower($query);

        if (strpos($query_lower, 'wordpress') !== false) {
            $results = $this->get_wordpress_search_results($query, $top_k);
        } elseif (strpos($query_lower, 'ai') !== false || strpos($query_lower, 'gpt') !== false) {
            $results = $this->get_ai_search_results($query, $top_k);
        } elseif (strpos($query_lower, 'research') !== false) {
            $results = $this->get_research_search_results($query, $top_k);
        } else {
            $results = $this->get_general_search_results($query, $top_k);
        }

        // Apply site filter if specified
        if (!empty($site_filter)) {
            $results = array_filter($results, function($result) use ($site_filter) {
                $url = parse_url($result['url'], PHP_URL_HOST);
                foreach ($site_filter as $site) {
                    if (strpos($url, $site) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        return array(
            'results' => array_slice($results, 0, $top_k),
            'query' => $query,
            'total_results' => count($results),
        );
    }

    /**
     * Get WordPress-specific search results
     */
    private function get_wordpress_search_results($query, $top_k) {
        return array(
            array(
                'title' => 'WordPress Developer Resources - Official Documentation',
                'url' => 'https://developer.wordpress.org/',
                'snippet' => 'Complete guide to WordPress development including themes, plugins, and core functionality. Learn about hooks, filters, and best practices.',
                'published_at' => date('Y-m-d', strtotime('-30 days')),
            ),
            array(
                'title' => 'WordPress Plugin Development Handbook',
                'url' => 'https://developer.wordpress.org/plugins/',
                'snippet' => 'Comprehensive guide to creating WordPress plugins. Covers plugin architecture, hooks, database interactions, and security.',
                'published_at' => date('Y-m-d', strtotime('-15 days')),
            ),
            array(
                'title' => 'Gutenberg Block Editor Development',
                'url' => 'https://developer.wordpress.org/block-editor/',
                'snippet' => 'Learn how to create custom blocks for the WordPress block editor. Includes JavaScript, React, and PHP development.',
                'published_at' => date('Y-m-d', strtotime('-7 days')),
            ),
            array(
                'title' => 'WordPress REST API Reference',
                'url' => 'https://developer.wordpress.org/rest-api/',
                'snippet' => 'Complete reference for the WordPress REST API. Learn about endpoints, authentication, and extending the API.',
                'published_at' => date('Y-m-d', strtotime('-20 days')),
            ),
            array(
                'title' => 'WordPress Security Best Practices',
                'url' => 'https://wordpress.org/support/article/hardening-wordpress/',
                'snippet' => 'Essential security measures for WordPress sites including file permissions, updates, and secure coding practices.',
                'published_at' => date('Y-m-d', strtotime('-45 days')),
            ),
        );
    }

    /**
     * Get AI-specific search results
     */
    private function get_ai_search_results($query, $top_k) {
        return array(
            array(
                'title' => 'OpenAI API Documentation - Chat Completions',
                'url' => 'https://platform.openai.com/docs/api-reference/chat',
                'snippet' => 'Complete reference for OpenAI Chat API including GPT models, function calling, and tool usage. Learn about messages, roles, and responses.',
                'published_at' => date('Y-m-d', strtotime('-10 days')),
            ),
            array(
                'title' => 'Function Calling with GPT Models',
                'url' => 'https://platform.openai.com/docs/guides/function-calling',
                'snippet' => 'Guide to using function calling with GPT models. Learn how to define tools, handle tool calls, and integrate with external APIs.',
                'published_at' => date('Y-m-d', strtotime('-5 days')),
            ),
            array(
                'title' => 'Best Practices for AI Tool Integration',
                'url' => 'https://platform.openai.com/docs/guides/tools-best-practices',
                'snippet' => 'Learn best practices for integrating AI tools including error handling, rate limiting, and user experience considerations.',
                'published_at' => date('Y-m-d', strtotime('-12 days')),
            ),
            array(
                'title' => 'Token Usage and Cost Optimization',
                'url' => 'https://platform.openai.com/docs/guides/token-usage',
                'snippet' => 'Understanding token usage, pricing, and optimization strategies for OpenAI API calls.',
                'published_at' => date('Y-m-d', strtotime('-8 days')),
            ),
        );
    }

    /**
     * Get research-specific search results
     */
    private function get_research_search_results($query, $top_k) {
        return array(
            array(
                'title' => 'Academic Research Methodology Guide',
                'url' => 'https://libguides.library.edu/research-methods',
                'snippet' => 'Comprehensive guide to research methodology including qualitative and quantitative approaches, data collection, and analysis.',
                'published_at' => date('Y-m-d', strtotime('-60 days')),
            ),
            array(
                'title' => 'Citation and Reference Management',
                'url' => 'https://www.citethisforme.com/guides',
                'snippet' => 'Learn proper citation formats including APA, MLA, Chicago, and Harvard styles with examples and tools.',
                'published_at' => date('Y-m-d', strtotime('-25 days')),
            ),
            array(
                'title' => 'Peer Review Process Explained',
                'url' => 'https://www.elsevier.com/reviewers/what-is-peer-review',
                'snippet' => 'Understanding the peer review process in academic publishing including reviewer responsibilities and ethical considerations.',
                'published_at' => date('Y-m-d', strtotime('-40 days')),
            ),
        );
    }

    /**
     * Get general search results
     */
    private function get_general_search_results($query, $top_k) {
        $results = array();
        for ($i = 0; $i < min($top_k, 8); $i++) {
            $results[] = array(
                'title' => "Relevant Result " . ($i + 1) . " for: " . $query,
                'url' => "https://example.com/result" . ($i + 1),
                'snippet' => "This is a comprehensive snippet for the search result about " . $query . ". It provides detailed information and context relevant to the query.",
                'published_at' => date('Y-m-d', strtotime('-' . rand(1, 90) . ' days')),
            );
        }
        return $results;
    }

    /**
     * Fetch URL content - Enhanced implementation
     */
    public function fetch_url($url, $format = 'text', $max_chars = 10000) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array('error' => 'Invalid URL format');
        }

        // Check for common file extensions that might not be web pages
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $binary_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi');

        if (in_array($extension, $binary_extensions)) {
            return array(
                'error' => 'Binary file detected. Use summarize_pdf tool for PDF files.',
                'file_type' => $extension,
                'url' => $url
            );
        }

        // Use WordPress HTTP API to fetch content
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Dual-GPT-Research/1.0 (WordPress Plugin)',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ),
            'redirection' => 5,
        ));

        if (is_wp_error($response)) {
            return array(
                'error' => 'Failed to fetch URL: ' . $response->get_error_message(),
                'url' => $url
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'error' => 'HTTP ' . $response_code . ' error',
                'url' => $url,
                'response_code' => $response_code
            );
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Extract metadata
        $meta = $this->extract_page_metadata($body, $url);

        // Process content based on format
        if ($format === 'html') {
            $content = substr($body, 0, $max_chars);
        } else {
            // Extract readable text content
            $content = $this->extract_readable_text($body, $max_chars);
        }

        return array(
            'content_text' => $content,
            'meta' => $meta,
            'url' => $url,
            'content_type' => $content_type,
            'content_length' => strlen($body),
            'extracted_length' => strlen($content),
        );
    }

    /**
     * Extract page metadata
     */
    private function extract_page_metadata($html, $url) {
        $meta = array(
            'title' => '',
            'description' => '',
            'author' => '',
            'published_date' => '',
            'url' => $url,
        );

        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $matches)) {
            $meta['title'] = trim(wp_strip_all_tags($matches[1]));
        }

        // Extract meta description
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $meta['description'] = trim($matches[1]);
        }

        // Extract author
        if (preg_match('/<meta[^>]*name=["\']author["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $meta['author'] = trim($matches[1]);
        }

        // Try to extract publication date from various meta tags
        $date_patterns = array(
            '/<meta[^>]*property=["\']article:published_time["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i',
            '/<meta[^>]*name=["\']publishdate["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i',
            '/<meta[^>]*name=["\']date["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i',
        );

        foreach ($date_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $meta['published_date'] = trim($matches[1]);
                break;
            }
        }

        return $meta;
    }

    /**
     * Extract readable text content from HTML
     */
    private function extract_readable_text($html, $max_chars) {
        // Remove script and style elements
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Remove comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Convert common block elements to text with spacing
        $html = preg_replace('/<\/(p|div|h[1-6]|li|br)>/i', "$0\n", $html);

        // Strip remaining HTML tags
        $text = wp_strip_all_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text); // Multiple newlines to double
        $text = preg_replace('/\n\s+/', "\n", $text); // Leading spaces after newlines
        $text = preg_replace('/\s+\n/', "\n", $text); // Trailing spaces before newlines
        $text = preg_replace('/\s{2,}/', ' ', $text); // Multiple spaces to single

        // Trim and limit length
        $text = trim($text);
        if (strlen($text) > $max_chars) {
            $text = substr($text, 0, $max_chars);
            // Try to cut at a sentence or word boundary
            $text = preg_replace('/\s+[^\s]*$/', '', $text);
            $text .= '...';
        }

        return $text;
    }

    /**
     * Summarize PDF - Enhanced implementation
     */
    public function summarize_pdf($url, $pages = null, $granularity = 'section') {
        // In production, this would use a PDF parsing library like:
        // - TCPDF for basic parsing
        // - PDFlib for advanced features
        // - External service like PDF.co or Adobe PDF Services

        // For now, simulate PDF analysis
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array('error' => 'Invalid PDF URL');
        }

        // Check if URL ends with .pdf
        if (!preg_match('/\.pdf$/i', $url)) {
            return array('error' => 'URL does not appear to be a PDF file');
        }

        // Simulate PDF structure analysis
        $sections = array();

        if ($granularity === 'section') {
            $sections = $this->simulate_pdf_section_analysis($url, $pages);
        } elseif ($granularity === 'page') {
            $sections = $this->simulate_pdf_page_analysis($url, $pages);
        } else {
            $sections = $this->simulate_pdf_paragraph_analysis($url, $pages);
        }

        return array(
            'sections' => $sections,
            'url' => $url,
            'pages_analyzed' => $pages ?: 'all',
            'granularity' => $granularity,
            'note' => 'This is a simulated PDF analysis. In production, this would use actual PDF parsing.',
        );
    }

    /**
     * Simulate PDF section analysis
     */
    private function simulate_pdf_section_analysis($url, $pages) {
        $sections = array();

        // Simulate typical academic paper structure
        $typical_sections = array(
            'Abstract' => 'This paper presents a comprehensive analysis of the topic, demonstrating key findings and implications for the field.',
            'Introduction' => 'The introduction provides background context and outlines the research objectives and methodology used in this study.',
            'Literature Review' => 'Previous research in this area shows consistent patterns and identifies gaps that this study aims to address.',
            'Methodology' => 'The research methodology includes data collection procedures, analytical techniques, and validation approaches.',
            'Results' => 'The results section presents findings from the analysis, including statistical data and key observations.',
            'Discussion' => 'The discussion interprets the results in the context of existing literature and addresses implications for practice.',
            'Conclusion' => 'The conclusion summarizes key findings and suggests directions for future research in this area.',
        );

        foreach ($typical_sections as $heading => $summary) {
            $sections[] = array(
                'heading' => $heading,
                'summary' => $summary,
                'quotes' => array(
                    'Key finding: ' . substr($summary, 0, 50) . '...',
                    'Important insight: ' . substr($summary, 10, 60) . '...',
                ),
                'page_range' => rand(1, 20) . '-' . rand(21, 40),
            );
        }

        return $sections;
    }

    /**
     * Simulate PDF page analysis
     */
    private function simulate_pdf_page_analysis($url, $pages) {
        $sections = array();
        $page_count = $pages ?: 10;

        for ($i = 1; $i <= $page_count; $i++) {
            $sections[] = array(
                'heading' => 'Page ' . $i,
                'summary' => 'Content analysis of page ' . $i . ' covering key concepts and findings relevant to the research topic.',
                'quotes' => array(
                    'Page ' . $i . ' excerpt: This section discusses important aspects of the research methodology.',
                ),
                'page_range' => $i,
            );
        }

        return $sections;
    }

    /**
     * Simulate PDF paragraph analysis
     */
    private function simulate_pdf_paragraph_analysis($url, $pages) {
        $sections = array();
        $paragraph_count = ($pages ?: 5) * 3; // Assume 3 paragraphs per page

        for ($i = 1; $i <= $paragraph_count; $i++) {
            $sections[] = array(
                'heading' => 'Paragraph ' . $i,
                'summary' => 'Detailed analysis of paragraph ' . $i . ' containing specific information about the research findings.',
                'quotes' => array(
                    'Key statement from paragraph ' . $i . ': This highlights an important aspect of the research.',
                ),
                'page_range' => ceil($i / 3),
            );
        }

        return $sections;
    }

    /**
     * Citation check - Enhanced implementation
     */
    public function citation_check($claims, $sources) {
        $verdicts = array();

        foreach ($claims as $index => $claim) {
            $claim_lower = strtolower($claim);

            // Analyze claim against sources
            $supporting_sources = array();
            $conflicting_sources = array();
            $neutral_sources = array();

            foreach ($sources as $source_index => $source) {
                $source_lower = strtolower($source);

                // Simple text matching - in production, use NLP/semantic analysis
                if (strpos($source_lower, $claim_lower) !== false ||
                    $this->calculate_similarity($claim_lower, $source_lower) > 0.7) {
                    $supporting_sources[] = $source_index;
                } elseif (strpos($source_lower, str_replace(array('is', 'are', 'was', 'were'), array('is not', 'are not', 'was not', 'were not'), $claim_lower)) !== false) {
                    $conflicting_sources[] = $source_index;
                } else {
                    $neutral_sources[] = $source_index;
                }
            }

            // Determine verdict
            if (!empty($supporting_sources)) {
                $status = 'verified';
                $notes = 'Claim supported by ' . count($supporting_sources) . ' source(s)';
            } elseif (!empty($conflicting_sources)) {
                $status = 'contradicted';
                $notes = 'Claim contradicted by ' . count($conflicting_sources) . ' source(s)';
            } else {
                $status = 'unverified';
                $notes = 'No direct supporting or contradicting evidence found in provided sources';
            }

            $verdicts[] = array(
                'id' => $index,
                'claim' => $claim,
                'status' => $status,
                'notes' => $notes,
                'supporting_sources' => $supporting_sources,
                'conflicting_sources' => $conflicting_sources,
                'confidence' => $this->calculate_confidence($supporting_sources, $conflicting_sources, count($sources)),
            );
        }

        return array(
            'verdicts' => $verdicts,
            'summary' => array(
                'total_claims' => count($claims),
                'verified' => count(array_filter($verdicts, fn($v) => $v['status'] === 'verified')),
                'contradicted' => count(array_filter($verdicts, fn($v) => $v['status'] === 'contradicted')),
                'unverified' => count(array_filter($verdicts, fn($v) => $v['status'] === 'unverified')),
            ),
        );
    }

    /**
     * Calculate text similarity (simple implementation)
     */
    private function calculate_similarity($text1, $text2) {
        $words1 = array_unique(str_word_count($text1, 1));
        $words2 = array_unique(str_word_count($text2, 1));

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    /**
     * Calculate confidence score
     */
    private function calculate_confidence($supporting, $conflicting, $total_sources) {
        if (empty($supporting) && empty($conflicting)) {
            return 0.5; // Neutral confidence
        }

        $support_score = count($supporting) / $total_sources;
        $conflict_score = count($conflicting) / $total_sources;

        return max(0, min(1, $support_score - $conflict_score + 0.5));
    }

    /**
     * Get tool definitions for OpenAI
     */
    public function get_tool_definitions() {
        return array(
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'web_search',
                    'description' => 'Search the web for information',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'query' => array(
                                'type' => 'string',
                                'description' => 'The search query',
                            ),
                            'top_k' => array(
                                'type' => 'integer',
                                'description' => 'Number of results to return',
                                'default' => 10,
                            ),
                            'site_filter' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'Limit search to specific sites',
                            ),
                        ),
                        'required' => array('query'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'fetch_url',
                    'description' => 'Fetch and extract content from a URL',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'url' => array(
                                'type' => 'string',
                                'description' => 'The URL to fetch',
                            ),
                            'format' => array(
                                'type' => 'string',
                                'enum' => array('text', 'html'),
                                'description' => 'Format of returned content',
                                'default' => 'text',
                            ),
                            'max_chars' => array(
                                'type' => 'integer',
                                'description' => 'Maximum characters to return',
                                'default' => 10000,
                            ),
                        ),
                        'required' => array('url'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'summarize_pdf',
                    'description' => 'Summarize content from a PDF URL',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'url' => array(
                                'type' => 'string',
                                'description' => 'The PDF URL',
                            ),
                            'pages' => array(
                                'type' => 'string',
                                'description' => 'Page range to summarize (e.g., "1-5")',
                            ),
                            'granularity' => array(
                                'type' => 'string',
                                'enum' => array('section', 'page', 'paragraph'),
                                'description' => 'Level of detail for summarization',
                                'default' => 'section',
                            ),
                        ),
                        'required' => array('url'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'citation_check',
                    'description' => 'Check citations against provided sources',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'claims' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'List of claims to verify',
                            ),
                            'sources' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'List of source URLs or texts',
                            ),
                        ),
                        'required' => array('claims', 'sources'),
                    ),
                ),
            ),
        );
    }

    /**
     * Execute a tool call
     */
    public function execute_tool($tool_name, $arguments) {
        if (!method_exists($this, $tool_name)) {
            return array('error' => 'Tool not found');
        }

        return call_user_func_array(array($this, $tool_name), $arguments);
    }
}
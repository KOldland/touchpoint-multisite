<?php
/**
 * Citation Verifier
 *
 * Handles citation verification via CrossRef, OpenAlex, and URL metadata extraction.
 * This file contains the consolidated logic for the citation verifier, including
 * client-like functionality for external APIs and URL fetching utilities.
 *
 * @package    Dual_GPT_WordPress_Plugin
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Framework_Generator_Citation_Verifier {

    /**
     * CrossRef API base URL
     */
    const CROSSREF_API_BASE = 'https://api.crossref.org/works/';

    /**
     * OpenAlex API base URL
     */
    const OPENALEX_API_BASE = 'https://api.openalex.org/works/';

    /**
     * Main verification method.
     *
     * @param array $candidate An array containing 'url', 'doi', and 'title'.
     * @param int   $job_id    The ID of the job triggering the verification.
     *
     * @return array The validated citation data.
     */
    public function verify_citation($candidate, $job_id = 0) {
        $verified_data = [
            'apa_string' => 'details_unavailable',
            'apa_details_available' => false,
            'passage_snippet' => '',
            'confidence' => 0.0,
            'url' => $candidate['url'] ?? '',
            'title' => $candidate['title'] ?? '',
            'doi' => $candidate['doi'] ?? null,
        ];

        $doi = $candidate['doi'] ?? $this->extract_doi_from_url($candidate['url']);

        if ($doi) {
            $academic_meta = $this->fetch_by_doi($doi);
            if ($academic_meta) {
                $this->log_verification_attempt($candidate['url'], 'doi_lookup', true, $job_id, 'DOI lookup successful.');
                $verified_data = array_merge($verified_data, $academic_meta);
                $verified_data['confidence'] = 0.9;
                $verified_data['apa_details_available'] = true;
            } else {
                 $this->log_verification_attempt($candidate['url'], 'doi_lookup', false, $job_id, 'DOI lookup failed.');
            }
        }
        
        // If no DOI or academic lookup failed, fetch from URL
        if (!$verified_data['apa_details_available']) {
            $url_meta = $this->fetch_url_metadata($candidate['url']);
            if (!empty($url_meta)) {
                $this->log_verification_attempt($candidate['url'], 'url_fetch', true, $job_id, 'URL fetch successful.');
                $verified_data = array_merge($verified_data, $url_meta);
                $verified_data['confidence'] = max($verified_data['confidence'], 0.6);

                if(!empty($verified_data['apa_string'])) {
                    $verified_data['apa_details_available'] = true;
                }
            } else {
                 $this->log_verification_attempt($candidate['url'], 'url_fetch', false, $job_id, 'URL fetch failed.');
            }
        }

        // Final confidence boost if we have a title
        if(!empty($verified_data['title'])) {
             $verified_data['confidence'] = max($verified_data['confidence'], 0.3);
        }

        return $verified_data;
    }

    /**
     * Fetch URL metadata
     */
    public function fetch_url_metadata($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'FrameworkGenerator/1.0 (WordPress Plugin)',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        // Parse HTML for metadata
        return $this->parse_html_metadata($body, $url);
    }

    /**
     * Parse HTML for citation metadata
     */
    private function parse_html_metadata($html, $url) {
        $metadata = array();

        // Parse meta tags
        if (preg_match_all('/<meta[^>]+name=["\']citation[_-]([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $index => $key) {
                $value = $matches[2][$index];
                $metadata[strtolower($key)] = $value;
            }
        }

        // Parse JSON-LD
        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $json_ld_match)) {
            $json_data = json_decode($json_ld_match[1], true);
            if ($json_data && isset($json_data['@type'])) {
                $metadata = array_merge($metadata, $this->extract_json_ld_citation($json_data));
            }
        }

        // Extract title from HTML
        if (empty($metadata['title']) && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title_match)) {
            $metadata['title'] = trim(strip_tags($title_match[1]));
        }

        // Extract passage snippet
        if (empty($metadata['passage_snippet'])) {
            $metadata['passage_snippet'] = $this->extract_passage_snippet($html);
        }

        return $metadata;
    }

    /**
     * Extract a relevant passage snippet from HTML content.
     */
    private function extract_passage_snippet($html) {
        $text = strip_tags($html, '<p>');
        $paragraphs = explode('</p>', $text);
        foreach ($paragraphs as $p) {
            $trimmed_p = trim(strip_tags($p));
            if (strlen($trimmed_p) > 100) { // Find a reasonably long paragraph
                return substr($trimmed_p, 0, 250) . '...';
            }
        }
        return '';
    }
    
    /**
     * Extract citation data from JSON-LD
     */
    private function extract_json_ld_citation($json_data) {
        $citation = array();

        if (isset($json_data['headline'])) {
            $citation['title'] = $json_data['headline'];
        }

        if (isset($json_data['author'])) {
            if (is_array($json_data['author'])) {
                $authors = array();
                foreach ($json_data['author'] as $author) {
                    if (is_array($author) && isset($author['name'])) {
                        $authors[] = $author['name'];
                    } elseif (is_string($author)) {
                        $authors[] = $author;
                    }
                }
                if (!empty($authors)) {
                    $citation['lead_author'] = $authors[0];
                    $citation['authors'] = implode(', ', $authors);
                }
            }
        }

        if (isset($json_data['publisher']) && is_array($json_data['publisher']) && isset($json_data['publisher']['name'])) {
            $citation['publication'] = $json_data['publisher']['name'];
        }

        if (isset($json_data['datePublished'])) {
            $citation['year'] = date('Y', strtotime($json_data['datePublished']));
        }

        return $citation;
    }

    /**
     * Fetch academic metadata from CrossRef or OpenAlex
     */
    public function fetch_academic_metadata($title, $url) {
        // Try to extract DOI from URL first
        $doi = $this->extract_doi_from_url($url);
        if ($doi) {
            return $this->fetch_by_doi($doi);
        }

        // Search by title
        return $this->search_by_title($title);
    }

    /**
     * Extract DOI from URL
     */
    private function extract_doi_from_url($url) {
        // Common DOI patterns in URLs
        $patterns = array(
            '/doi\.org\/(10\.\d{4,9}\/[-._;()\/:A-Z0-9]+)/i',
            '/doi:?\s*(10\.\d{4,9}\/[-._;()\/:A-Z0-9]+)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Fetch metadata by DOI
     */
    private function fetch_by_doi($doi) {
        // Try CrossRef first
        $crossref_data = $this->query_crossref($doi);
        if ($crossref_data) {
            return $crossref_data;
        }

        // Fallback to OpenAlex
        return $this->query_openalex($doi);
    }

    /**
     * Search by title
     */
    private function search_by_title($title) {
        // Try CrossRef search
        $crossref_data = $this->search_crossref($title);
        if ($crossref_data) {
            return $crossref_data;
        }

        // Fallback to OpenAlex
        return $this->search_openalex($title);
    }

    /**
     * Query CrossRef API
     */
    private function query_crossref($doi) {
        $url = self::CROSSREF_API_BASE . urlencode($doi);

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                // It is polite to identify your client and provide a contact.
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:dev@example.com)',
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data || !isset($data['message'])) {
            return null;
        }

        return $this->format_crossref_data($data['message']);
    }

    /**
     * Search CrossRef by title
     */
    private function search_crossref($title) {
        $url = add_query_arg(array(
            'query.bibliographic' => urlencode($title),
            'rows' => 1,
        ), 'https://api.crossref.org/works');

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:dev@example.com)',
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data || !isset($data['message']['items']) || empty($data['message']['items'])) {
            return null;
        }

        return $this->format_crossref_data($data['message']['items'][0]);
    }

    /**
     * Format CrossRef data
     */
    private function format_crossref_data($item) {
        $citation = array();

        if (isset($item['title']) && is_array($item['title'])) {
            $citation['title'] = $item['title'][0];
        }

        if (isset($item['author']) && is_array($item['author'])) {
            $authors = array();
            foreach ($item['author'] as $author) {
                if (isset($author['family']) && isset($author['given'])) {
                    $authors[] = $author['family'] . ', ' . $author['given'];
                } elseif (isset($author['name'])) {
                    $authors[] = $author['name'];
                }
            }
            if (!empty($authors)) {
                $citation['lead_author'] = $authors[0];
                $citation['authors'] = implode(', ', $authors);
            }
        }

        if (isset($item['container-title']) && is_array($item['container-title'])) {
            $citation['publication'] = $item['container-title'][0];
        }

        if (isset($item['publisher'])) {
            $citation['organisation'] = $item['publisher'];
        }

        if (isset($item['published-print']) && isset($item['published-print']['date-parts'])) {
            $citation['year'] = $item['published-print']['date-parts'][0][0];
        } elseif (isset($item['published-online']) && isset($item['published-online']['date-parts'])) {
            $citation['year'] = $item['published-online']['date-parts'][0][0];
        }

        if (isset($item['DOI'])) {
            $citation['doi'] = $item['DOI'];
            $citation['apa_string'] = $this->generate_apa_from_crossref($item);
        }

        return $citation;
    }

    /**
     * Generate APA string from CrossRef data
     */
    private function generate_apa_from_crossref($item) {
        $apa_parts = array();

        // Authors
        if (isset($item['author']) && is_array($item['author'])) {
            $authors_list = array_slice($item['author'], 0, 20); // APA style limit
            $author_strings = [];
            foreach ($authors_list as $author) {
                if (!empty($author['family']) && !empty($author['given'])) {
                    $author_strings[] = $author['family'] . ', ' . mb_substr($author['given'], 0, 1) . '.';
                } elseif (!empty($author['name'])) {
                     $author_strings[] = $author['name'];
                }
            }
            if(count($author_strings) > 1) {
                $last_author = array_pop($author_strings);
                $apa_parts[] = implode(', ', $author_strings) . ' & ' . $last_author;
            } elseif(!empty($author_strings)) {
                $apa_parts[] = $author_strings[0];
            }
        }

        // Year
        if (isset($item['published-print']['date-parts'][0][0])) {
            $apa_parts[] = '(' . $item['published-print']['date-parts'][0][0] . ').';
        } elseif (isset($item['published-online']['date-parts'][0][0])) {
            $apa_parts[] = '(' . $item['published-online']['date-parts'][0][0] . ').';
        }

        // Title
        if (isset($item['title'][0])) {
            $apa_parts[] = '<em>' . rtrim($item['title'][0], '.') . '.</em>';
        }

        // Journal/Publication
        if (isset($item['container-title'][0])) {
            $apa_parts[] = $item['container-title'][0];
        }

        // DOI
        if (isset($item['DOI'])) {
            $apa_parts[] = 'https://doi.org/' . $item['DOI'];
        }

        return implode(' ', $apa_parts);
    }

    /**
     * Query OpenAlex API
     */
    private function query_openalex($doi) {
        $url = self::OPENALEX_API_BASE . urlencode($doi);

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:dev@example.com)',
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data) {
            return null;
        }

        return $this->format_openalex_data($data);
    }

    /**
     * Search OpenAlex by title
     */
    private function search_openalex($title) {
        $url = add_query_arg(array(
            'search' => urlencode($title),
            'per-page' => 1,
        ), self::OPENALEX_API_BASE);

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:dev@example.com)',
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!$data || !isset($data['results']) || empty($data['results'])) {
            return null;
        }

        return $this->format_openalex_data($data['results'][0]);
    }

    /**
     * Format OpenAlex data
     */
    private function format_openalex_data($item) {
        $citation = array();

        if (isset($item['title'])) {
            $citation['title'] = $item['title'];
        }

        if (isset($item['authorships']) && is_array($item['authorships'])) {
            $authors = array();
            foreach ($item['authorships'] as $authorship) {
                if (isset($authorship['author']['display_name'])) {
                    $authors[] = $authorship['author']['display_name'];
                }
            }
            if (!empty($authors)) {
                $citation['lead_author'] = $authors[0];
                $citation['authors'] = implode(', ', $authors);
            }
        }

        if (isset($item['host_venue']['display_name'])) {
            $citation['publication'] = $item['host_venue']['display_name'];
        }

        if (isset($item['publication_year'])) {
            $citation['year'] = $item['publication_year'];
        }

        if (isset($item['doi'])) {
            $citation['doi'] = str_replace('https://doi.org/', '', $item['doi']);
            $citation['apa_string'] = $this->generate_apa_from_openalex($item);
        }

        return $citation;
    }

    /**
     * Generate APA string from OpenAlex data
     */
    private function generate_apa_from_openalex($item) {
        $apa_parts = array();

        // Authors
        if (isset($item['authorships']) && is_array($item['authorships'])) {
            $authors_list = array_slice($item['authorships'], 0, 20);
            $author_strings = [];
            foreach ($authors_list as $authorship) {
                if (isset($authorship['author']['display_name'])) {
                    $name_parts = explode(' ', $authorship['author']['display_name']);
                    if(count($name_parts) > 1) {
                        $last_name = array_pop($name_parts);
                        $first_initial = mb_substr($name_parts[0], 0, 1);
                        $author_strings[] = $last_name . ', ' . $first_initial . '.';
                    } else {
                        $author_strings[] = $authorship['author']['display_name'];
                    }
                }
            }
             if(count($author_strings) > 1) {
                $last_author = array_pop($author_strings);
                $apa_parts[] = implode(', ', $author_strings) . ' & ' . $last_author;
            } elseif(!empty($author_strings)) {
                $apa_parts[] = $author_strings[0];
            }
        }

        // Year
        if (isset($item['publication_year'])) {
            $apa_parts[] = '(' . $item['publication_year'] . ').';
        }

        // Title
        if (isset($item['title'])) {
            $apa_parts[] = '<em>' . rtrim($item['title'],'.') . '.</em>';
        }

        // Journal/Publication
        if (isset($item['host_venue']['display_name'])) {
            $apa_parts[] = $item['host_venue']['display_name'];
        }

        // DOI
        if (isset($item['doi'])) {
            $apa_parts[] = $item['doi'];
        }

        return implode(' ', $apa_parts);
    }

    /**
     * Log verification attempt
     */
    public function log_verification_attempt($url, $method, $success, $job_id = 0, $details = '') {
        if (class_exists('Dual_GPT_DB_Handler')) {
            $db = new Dual_GPT_DB_Handler();
            $db->insert_audit_log($job_id, 'citation_verification_attempt', array(
                'url' => $url,
                'method' => $method,
                'success' => $success ? 1 : 0,
                'details' => $details,
            ));
        }
    }
}
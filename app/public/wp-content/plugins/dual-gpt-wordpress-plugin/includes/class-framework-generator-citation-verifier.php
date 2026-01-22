<?php
/**
 * Citation Verifier
 *
 * Handles citation verification via CrossRef, OpenAlex, and URL metadata extraction.
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
                $metadata[$key] = $value;
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

        return $metadata;
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
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:contact@example.com)',
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
            'query' => urlencode($title),
            'rows' => 1,
        ), 'https://api.crossref.org/works');

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:contact@example.com)',
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
            $authors = array();
            foreach (array_slice($item['author'], 0, 20) as $author) { // Limit to 20 authors
                if (isset($author['family']) && isset($author['given'])) {
                    $authors[] = $author['family'] . ', ' . substr($author['given'], 0, 1) . '.';
                }
            }
            if (!empty($authors)) {
                if (count($authors) > 1) {
                    $last_author = array_pop($authors);
                    $apa_parts[] = implode(', ', $authors) . ', & ' . $last_author;
                } else {
                    $apa_parts[] = $authors[0];
                }
            }
        }

        // Year
        if (isset($item['published-print']) && isset($item['published-print']['date-parts'])) {
            $apa_parts[] = '(' . $item['published-print']['date-parts'][0][0] . ')';
        } elseif (isset($item['published-online']) && isset($item['published-online']['date-parts'])) {
            $apa_parts[] = '(' . $item['published-online']['date-parts'][0][0] . ')';
        }

        // Title
        if (isset($item['title']) && is_array($item['title'])) {
            $apa_parts[] = '<em>' . $item['title'][0] . '</em>';
        }

        // Journal/Publication
        if (isset($item['container-title']) && is_array($item['container-title'])) {
            $apa_parts[] = $item['container-title'][0];
        }

        // DOI
        if (isset($item['DOI'])) {
            $apa_parts[] = 'https://doi.org/' . $item['DOI'];
        }

        return implode('. ', $apa_parts) . '.';
    }

    /**
     * Query OpenAlex API
     */
    private function query_openalex($doi) {
        $url = self::OPENALEX_API_BASE . urlencode($doi);

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:contact@example.com)',
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
                'User-Agent' => 'FrameworkGenerator/1.0 (mailto:contact@example.com)',
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
            $authors = array();
            foreach (array_slice($item['authorships'], 0, 20) as $authorship) {
                if (isset($authorship['author']['display_name'])) {
                    $name_parts = explode(' ', $authorship['author']['display_name']);
                    if (count($name_parts) > 1) {
                        $last_name = array_pop($name_parts);
                        $first_initial = substr($name_parts[0], 0, 1);
                        $authors[] = $last_name . ', ' . $first_initial . '.';
                    } else {
                        $authors[] = $authorship['author']['display_name'];
                    }
                }
            }
            if (!empty($authors)) {
                if (count($authors) > 1) {
                    $last_author = array_pop($authors);
                    $apa_parts[] = implode(', ', $authors) . ', & ' . $last_author;
                } else {
                    $apa_parts[] = $authors[0];
                }
            }
        }

        // Year
        if (isset($item['publication_year'])) {
            $apa_parts[] = '(' . $item['publication_year'] . ')';
        }

        // Title
        if (isset($item['title'])) {
            $apa_parts[] = '<em>' . $item['title'] . '</em>';
        }

        // Journal/Publication
        if (isset($item['host_venue']['display_name'])) {
            $apa_parts[] = $item['host_venue']['display_name'];
        }

        // DOI
        if (isset($item['doi'])) {
            $apa_parts[] = $item['doi'];
        }

        return implode('. ', $apa_parts) . '.';
    }

    /**
     * Log verification attempt
     */
    public function log_verification_attempt($url, $method, $success, $details = '') {
        $db = new Dual_GPT_DB_Handler();
        $db->insert_audit_log(0, 'citation_verification_attempt', array(
            'url' => $url,
            'method' => $method,
            'success' => $success ? 1 : 0,
            'details' => $details,
        ));
    }
}
<?php

namespace KH\Editorial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CitationVerifier
 * 
 * Handles citation verification via CrossRef, OpenAlex, and URL metadata extraction.
 */
class CitationVerifier {

    const CROSSREF_API_BASE = 'https://api.crossref.org/works/';
    const OPENALEX_API_BASE = 'https://api.openalex.org/works/';

    public function verify_citation( $candidate, $job_id = 0 ) {
        $verified_data = [
            'apa_string' => 'details_unavailable',
            'apa_details_available' => false,
            'passage_snippet' => '',
            'confidence' => 0.0,
            'url' => $candidate['url'] ?? '',
            'title' => $candidate['title'] ?? '',
            'doi' => $candidate['doi'] ?? null,
            'source_type' => $candidate['source_type'] ?? 'industry',
            'tier' => $this->determine_tier( $candidate['source_type'] ?? 'industry' ),
            'authority_score' => 0.5,
        ];

        $doi = $candidate['doi'] ?? $this->extract_doi_from_url( $candidate['url'] );

        if ( $doi ) {
            $academic_meta = $this->fetch_by_doi( $doi );
            if ( $academic_meta ) {
                $verified_data = array_merge( $verified_data, $academic_meta );
                $verified_data['confidence'] = 0.9;
                $verified_data['apa_details_available'] = true;
            }
        }
        
        if ( ! $verified_data['apa_details_available'] ) {
            $url_meta = $this->fetch_url_metadata( $candidate['url'] );
            if ( ! empty( $url_meta ) ) {
                $verified_data = array_merge( $verified_data, $url_meta );
                $verified_data['confidence'] = max( $verified_data['confidence'], 0.6 );

                if ( ! empty( $verified_data['apa_string'] ) ) {
                    $verified_data['apa_details_available'] = true;
                }
            }
        }

        if ( ! empty( $verified_data['title'] ) ) {
             $verified_data['confidence'] = max( $verified_data['confidence'], 0.3 );
        }

        // Calculate authority score based on source type and metadata
        $verified_data['authority_score'] = $this->calculate_authority_score( $verified_data );

        return $verified_data;
    }

    public function fetch_url_metadata( $url ) {
        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'user-agent' => 'KH-Editorial-Suite/1.0',
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);

        if ( is_wp_error( $response ) ) {
            return [];
        }

        return $this->parse_html_metadata( wp_remote_retrieve_body( $response ), $url );
    }

    private function parse_html_metadata( $html, $url ) {
        $metadata = [];

        if ( preg_match_all( '/<meta[^>]+name=["\']citation[_-]([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
            foreach ( $matches[1] as $index => $key ) {
                $metadata[strtolower( $key )] = $matches[2][$index];
            }
        }

        if ( preg_match( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $json_ld_match ) ) {
            $json_data = json_decode( $json_ld_match[1], true );
            if ( $json_data ) {
                $metadata = array_merge( $metadata, $this->extract_json_ld_citation( $json_data ) );
            }
        }

        if ( empty( $metadata['title'] ) && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $title_match ) ) {
            $metadata['title'] = trim( strip_tags( $title_match[1] ) );
        }

        return $metadata;
    }

    private function extract_json_ld_citation( $json_data ) {
        $citation = [];

        if ( isset( $json_data['headline'] ) ) {
            $citation['title'] = $json_data['headline'];
        }

        if ( isset( $json_data['author'] ) ) {
            if ( is_array( $json_data['author'] ) ) {
                $authors = [];
                foreach ( $json_data['author'] as $author ) {
                    if ( is_array( $author ) && isset( $author['name'] ) ) {
                        $authors[] = $author['name'];
                    } elseif ( is_string( $author ) ) {
                        $authors[] = $author;
                    }
                }
                if ( ! empty( $authors ) ) {
                    $citation['lead_author'] = $authors[0];
                    $citation['authors'] = implode( ', ', $authors );
                }
            }
        }

        if ( isset( $json_data['publisher'] ) && is_array( $json_data['publisher'] ) && isset( $json_data['publisher']['name'] ) ) {
            $citation['publication'] = $json_data['publisher']['name'];
        }

        if ( isset( $json_data['datePublished'] ) ) {
            $citation['year'] = date( 'Y', strtotime( $json_data['datePublished'] ) );
            $citation['publication_date'] = $json_data['datePublished'];
        }

        return $citation;
    }

    private function extract_doi_from_url( $url ) {
        $patterns = [
            '/doi\.org\/(10\.\d{4,9}\/[-._;()\/:A-Z0-9]+)/i',
            '/doi:?\s*(10\.\d{4,9}\/[-._;()\/:A-Z0-9]+)/i',
        ];
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $matches ) ) { return $matches[1]; }
        }
        return null;
    }

    private function fetch_by_doi( $doi ) {
        $url = self::CROSSREF_API_BASE . urlencode( $doi );
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) { return null; }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return ( $data && isset( $data['message'] ) ) ? $this->format_crossref_data( $data['message'] ) : null;
    }

    private function format_crossref_data( $item ) {
        $citation = [];
        if ( isset( $item['title'][0] ) ) { $citation['title'] = $item['title'][0]; }
        if ( isset( $item['DOI'] ) ) {
            $citation['doi'] = $item['DOI'];
            $citation['apa_string'] = $this->generate_apa_from_crossref( $item );
        }
        return $citation;
    }

    private function generate_apa_from_crossref( $item ) {
        $apa_parts = [];

        // Authors
        if ( isset( $item['author'] ) && is_array( $item['author'] ) ) {
            $authors_list = array_slice( $item['author'], 0, 20 ); // APA style limit
            $author_strings = [];
            foreach ( $authors_list as $author ) {
                if ( ! empty( $author['family'] ) && ! empty( $author['given'] ) ) {
                    $author_strings[] = $author['family'] . ', ' . mb_substr( $author['given'], 0, 1 ) . '.';
                } elseif ( ! empty( $author['name'] ) ) {
                     $author_strings[] = $author['name'];
                }
            }
            if ( count( $author_strings ) > 1 ) {
                $last_author = array_pop( $author_strings );
                $apa_parts[] = implode( ', ', $author_strings ) . ' & ' . $last_author;
            } elseif ( ! empty( $author_strings ) ) {
                $apa_parts[] = $author_strings[0];
            }
        }

        // Year
        if ( isset( $item['published-print']['date-parts'][0][0] ) ) {
            $apa_parts[] = '(' . $item['published-print']['date-parts'][0][0] . ').';
        } elseif ( isset( $item['published-online']['date-parts'][0][0] ) ) {
            $apa_parts[] = '(' . $item['published-online']['date-parts'][0][0] . ').';
        }

        // Title
        if ( isset( $item['title'][0] ) ) {
            $apa_parts[] = '<em>' . rtrim( $item['title'][0], '.' ) . '.</em>';
        }

        // Journal/Publication
        if ( isset( $item['container-title'][0] ) ) {
            $apa_parts[] = $item['container-title'][0];
        }

        // DOI
        if ( isset( $item['DOI'] ) ) {
            $apa_parts[] = 'https://doi.org/' . $item['DOI'];
        }

        return implode( ' ', $apa_parts );
    }

    // -------------------------------------------------------------------------
    //  Source Tier Classification (ported from Dual GPT)
    // -------------------------------------------------------------------------

    /**
     * Determine the authority tier for a given source type.
     *
     * @param string $source_type e.g. 'academic', 'analyst', 'industry', 'case_study', 'trade'
     * @return string 'tier1', 'tier2', or 'tier3'
     */
    public function determine_tier( $source_type ) {
        $tiers = [
            'academic'   => 'tier1',
            'analyst'    => 'tier1',
            'industry'   => 'tier2',
            'case_study' => 'tier2',
            'trade'      => 'tier3',
        ];
        return $tiers[ $source_type ] ?? 'tier3';
    }

    /**
     * Check whether a source type is academic-level.
     */
    public function is_academic_source( $source_type ) {
        return in_array( $source_type, [ 'academic', 'journal', 'conference' ], true );
    }

    /**
     * Calculate an authority score (0.0–1.0) based on source type and metadata.
     *
     * @param array $verified_data The citation data after verification.
     * @return float Authority score.
     */
    public function calculate_authority_score( $verified_data ) {
        $score = 0.5; // Base score

        // Boost for academic-level sources
        if ( $this->is_academic_source( $verified_data['source_type'] ?? '' ) ) {
            $score += 0.3;
        }

        // Boost for analyst reports
        if ( ( $verified_data['source_type'] ?? '' ) === 'analyst' ) {
            $score += 0.2;
        }

        // Boost for recent content (within 2 years)
        if ( ! empty( $verified_data['year'] ) ) {
            $year = is_numeric( $verified_data['year'] ) ? (int) $verified_data['year'] : 0;
            if ( $year >= (int) date( 'Y' ) - 2 ) {
                $score += 0.1;
            }
        }

        // Boost for APA string availability (CrossRef verified)
        if ( ! empty( $verified_data['apa_string'] ) && $verified_data['apa_string'] !== 'details_unavailable' ) {
            $score += 0.1;
        }

        // Penalise for no title (weak signal)
        if ( empty( $verified_data['title'] ) ) {
            $score -= 0.2;
        }

        return min( max( $score, 0.0 ), 1.0 );
    }
}

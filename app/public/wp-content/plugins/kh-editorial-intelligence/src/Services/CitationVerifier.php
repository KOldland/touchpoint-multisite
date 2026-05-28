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
}

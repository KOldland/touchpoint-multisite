<?php
/**
 * Wikidata Resolver
 *
 * @package KHM_SEO\GEO\Entity
 */

namespace KHM_SEO\GEO\Entity;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WikidataResolver {
    const API_ENDPOINT = 'https://www.wikidata.org/w/api.php';
    const USER_AGENT   = 'KHM-GEO/1.0 (contact@yourdomain.com)';

    private $cache_ttl;
    private $negative_ttl;
    private $backoff_ttl;

    public function __construct() {
        $hour = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
        $this->cache_ttl = 24 * $hour;
        $this->negative_ttl = 30 * 60;
        $this->backoff_ttl = 10 * 60;
    }

    /**
     * Search Wikidata entities by term.
     *
     * @param string $term Search term.
     * @param string $lang Language code.
     * @return array
     */
    public function search( $term, $lang = 'en' ) {
        $term = trim( $term );
        if ( $term === '' ) {
            return array();
        }

        $cache_key = 'khm_wikidata_suggest_' . md5( $term . '|' . $lang );
        $cached = $this->get_cache( $cache_key );
        if ( $cached !== null ) {
            return $cached;
        }

        if ( $this->get_cache( 'khm_wikidata_backoff' ) !== null ) {
            return array();
        }

        $url = add_query_arg( array(
            'action' => 'wbsearchentities',
            'format' => 'json',
            'language' => $lang,
            'search' => $term,
            'limit' => 8,
        ), self::API_ENDPOINT );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'User-Agent' => self::USER_AGENT,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status === 429 ) {
            $this->set_cache( 'khm_wikidata_backoff', true, $this->backoff_ttl );
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        if ( $status < 200 || $status >= 300 ) {
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || empty( $data['search'] ) ) {
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        $candidates = array();
        foreach ( $data['search'] as $item ) {
            $candidates[] = array(
                'qid' => $item['id'] ?? '',
                'label' => $item['label'] ?? '',
                'description' => $item['description'] ?? '',
                'aliases' => $item['aliases'] ?? array(),
                'score_base' => 0.5,
                'term' => $term,
            );
        }

        $this->set_cache( $cache_key, $candidates, $this->cache_ttl );
        return $candidates;
    }

    /**
     * Fetch Wikidata entity details for ranking.
     *
     * @param string $qid Wikidata QID.
     * @return array
     */
    public function getEntityDetails( $qid ) {
        $qid = trim( $qid );
        if ( $qid === '' ) {
            return array();
        }

        $cache_key = 'khm_wikidata_entity_' . md5( $qid );
        $cached = $this->get_cache( $cache_key );
        if ( $cached !== null ) {
            return $cached;
        }

        $url = add_query_arg( array(
            'action' => 'wbgetentities',
            'format' => 'json',
            'ids' => $qid,
            'props' => 'claims|sitelinks',
        ), self::API_ENDPOINT );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'User-Agent' => self::USER_AGENT,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status === 429 ) {
            $this->set_cache( 'khm_wikidata_backoff', true, $this->backoff_ttl );
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        if ( $status < 200 || $status >= 300 ) {
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || empty( $data['entities'][ $qid ] ) ) {
            $this->set_cache( $cache_key, array(), $this->negative_ttl );
            return array();
        }

        $entity = $data['entities'][ $qid ];
        $instance_of = array();

        if ( ! empty( $entity['claims']['P31'] ) ) {
            foreach ( $entity['claims']['P31'] as $claim ) {
                $snak = $claim['mainsnak'] ?? array();
                $datavalue = $snak['datavalue']['value']['id'] ?? '';
                if ( $datavalue ) {
                    $instance_of[] = $datavalue;
                }
            }
        }

        $details = array(
            'instance_of' => $instance_of,
            'sitelink_count' => ! empty( $entity['sitelinks'] ) ? count( $entity['sitelinks'] ) : 0,
        );

        $this->set_cache( $cache_key, $details, 3 * $this->cache_ttl );
        return $details;
    }

    /**
     * Rank candidates based on heuristics.
     *
     * @param array $candidates Candidate list.
     * @param string $context Optional context.
     * @return array
     */
    public function rankCandidates( $candidates, $context ) {
        $context = trim( (string) $context );
        $ranked = array();

        foreach ( $candidates as $candidate ) {
            $score = floatval( $candidate['score_base'] ?? 0 );
            $label = strtolower( $candidate['label'] ?? '' );
            $term = strtolower( $candidate['term'] ?? '' );
            $description = strtolower( $candidate['description'] ?? '' );
            $aliases = array_map( 'strtolower', $candidate['aliases'] ?? array() );

            if ( $term && $label === $term ) {
                $score += 0.4;
            } elseif ( $term && in_array( $term, $aliases, true ) ) {
                $score += 0.2;
            }

            if ( $context && $description && strpos( $description, strtolower( $context ) ) !== false ) {
                $score += 0.15;
            }

            if ( ! empty( $candidate['instance_of'] ) ) {
                $score += 0.15;
            }

            if ( ! empty( $candidate['sitelink_count'] ) && $candidate['sitelink_count'] > 20 ) {
                $score += 0.1;
            }

            $candidate['score'] = min( 1.0, round( $score, 3 ) );
            $ranked[] = $candidate;
        }

        usort( $ranked, function( $a, $b ) {
            return ( $b['score'] ?? 0 ) <=> ( $a['score'] ?? 0 );
        } );

        return $ranked;
    }

    /**
     * Suggest ranked candidates for a term.
     *
     * @param string $term Search term.
     * @param string $context Optional context for ranking.
     * @param int $limit Max candidates to return.
     * @return array
     */
    public function suggest( $term, $context = '', $limit = 5 ) {
        $candidates = $this->search( $term );
        if ( empty( $candidates ) ) {
            return array();
        }

        foreach ( $candidates as &$candidate ) {
            $details = $this->getEntityDetails( $candidate['qid'] ?? '' );
            $candidate['instance_of'] = $details['instance_of'] ?? array();
            $candidate['sitelink_count'] = $details['sitelink_count'] ?? 0;
        }
        unset( $candidate );

        $ranked = $this->rankCandidates( $candidates, $context );
        $ranked = array_slice( $ranked, 0, $limit );

        $output = array();
        foreach ( $ranked as $candidate ) {
            $output[] = array(
                'qid' => $candidate['qid'] ?? '',
                'label' => $candidate['label'] ?? '',
                'description' => $candidate['description'] ?? '',
                'score' => $candidate['score'] ?? 0,
            );
        }

        return $output;
    }

    private function get_cache( $key ) {
        if ( function_exists( 'get_transient' ) ) {
            $value = get_transient( $key );
            if ( $value !== false ) {
                return $value;
            }
        }

        return null;
    }

    private function set_cache( $key, $value, $ttl ) {
        if ( function_exists( 'set_transient' ) ) {
            set_transient( $key, $value, $ttl );
        }
    }
}

<?php
namespace KH_SMMA\Compliance;

use function array_filter;
use function array_map;
use function current_time;
use function get_option;
use function sanitize_key;
use function sanitize_text_field;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComplianceRulesStore {
    const CORPUS_OPTION  = 'kh_smma_compliance_corpus';
    const META_OPTION    = 'kh_smma_compliance_corpus_meta';
    const CLAIMS_OPTION  = 'kh_smma_sponsor_allowed_claims';

    public function get_corpus(): array {
        $corpus = get_option( self::CORPUS_OPTION, array() );
        if ( ! is_array( $corpus ) ) {
            return array();
        }

        return $corpus;
    }

    public function get_corpus_meta(): array {
        $meta = get_option( self::META_OPTION, array() );
        if ( ! is_array( $meta ) ) {
            $meta = array();
        }

        return array(
            'corpus_version' => (int) ( $meta['corpus_version'] ?? 1 ),
            'updated_at'     => $meta['updated_at'] ?? '',
            'updated_by'     => (int) ( $meta['updated_by'] ?? 0 ),
        );
    }

    public function add_or_update_phrase( string $phrase, string $severity, string $category, int $user_id, string $phrase_id = '' ): array {
        $normalized_phrase = $this->normalize_phrase( $phrase );
        $normalized_severity = $this->normalize_severity( $severity );
        $normalized_category = sanitize_key( $category ?: 'uncategorized' );

        if ( '' === $normalized_phrase ) {
            return array( 'ok' => false, 'error' => 'Phrase is required.' );
        }

        if ( '' === $normalized_severity ) {
            return array( 'ok' => false, 'error' => 'Severity must be WARN or FAIL.' );
        }

        $corpus = $this->get_corpus();
        $id     = $phrase_id ? sanitize_key( $phrase_id ) : $this->build_phrase_id( $normalized_phrase );

        foreach ( $corpus as $existing_id => $entry ) {
            if ( $existing_id === $id ) {
                continue;
            }
            if ( $this->normalize_phrase( (string) ( $entry['phrase'] ?? '' ) ) === $normalized_phrase ) {
                return array( 'ok' => false, 'error' => 'Duplicate phrase detected.' );
            }
        }

        $existing = $corpus[ $id ] ?? null;
        $record   = array(
            'phrase'     => $normalized_phrase,
            'severity'   => $normalized_severity,
            'category'   => $normalized_category,
            'created_by' => (int) ( $existing['created_by'] ?? $user_id ),
            'created_at' => $existing['created_at'] ?? current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
            'updated_by' => $user_id,
        );

        $corpus[ $id ] = $record;
        update_option( self::CORPUS_OPTION, $corpus );

        return array(
            'ok'        => true,
            'phrase_id' => $id,
            'record'    => $record,
            'previous'  => is_array( $existing ) ? $existing : null,
        );
    }

    public function remove_phrase( string $phrase_id ): array {
        $id = sanitize_key( $phrase_id );
        $corpus = $this->get_corpus();

        if ( ! isset( $corpus[ $id ] ) ) {
            return array( 'ok' => false, 'error' => 'Phrase not found.' );
        }

        $previous = $corpus[ $id ];
        unset( $corpus[ $id ] );
        update_option( self::CORPUS_OPTION, $corpus );

        return array(
            'ok'       => true,
            'previous' => $previous,
            'phrase_id'=> $id,
        );
    }

    public function increment_corpus_version( int $user_id ): array {
        $meta = $this->get_corpus_meta();
        $meta['corpus_version'] = max( 1, (int) $meta['corpus_version'] ) + 1;
        $meta['updated_at'] = current_time( 'mysql' );
        $meta['updated_by'] = $user_id;

        update_option( self::META_OPTION, $meta );

        return $meta;
    }

    public function list_sponsor_claims(): array {
        $claims = get_option( self::CLAIMS_OPTION, array() );
        return is_array( $claims ) ? $claims : array();
    }

    public function get_sponsor_claims( int $sponsor_id ): array {
        $all = $this->list_sponsor_claims();
        $row = $all[ (string) $sponsor_id ] ?? array();

        return array(
            'sponsor_id'    => $sponsor_id,
            'allowed_claims'=> isset( $row['allowed_claims'] ) && is_array( $row['allowed_claims'] ) ? $row['allowed_claims'] : array(),
            'updated_by'    => (int) ( $row['updated_by'] ?? 0 ),
            'updated_at'    => $row['updated_at'] ?? '',
        );
    }

    public function update_sponsor_claims( int $sponsor_id, array $claims, int $user_id ): array {
        $all = $this->list_sponsor_claims();

        $clean = array_values( array_filter( array_map( function ( $claim ) {
            return sanitize_text_field( (string) $claim );
        }, $claims ) ) );

        $previous = $all[ (string) $sponsor_id ] ?? null;

        $all[ (string) $sponsor_id ] = array(
            'sponsor_id'     => $sponsor_id,
            'allowed_claims' => $clean,
            'updated_by'     => $user_id,
            'updated_at'     => current_time( 'mysql' ),
        );

        update_option( self::CLAIMS_OPTION, $all );

        return array(
            'ok'       => true,
            'current'  => $all[ (string) $sponsor_id ],
            'previous' => $previous,
        );
    }

    public function get_fail_phrases(): array {
        $phrases = array();
        foreach ( $this->get_corpus() as $entry ) {
            if ( 'FAIL' !== strtoupper( (string) ( $entry['severity'] ?? '' ) ) ) {
                continue;
            }
            $phrase = $this->normalize_phrase( (string) ( $entry['phrase'] ?? '' ) );
            if ( '' !== $phrase ) {
                $phrases[] = $phrase;
            }
        }

        return array_values( array_unique( $phrases ) );
    }

    private function build_phrase_id( string $phrase ): string {
        return sanitize_key( substr( sha1( $phrase ), 0, 16 ) );
    }

    private function normalize_phrase( string $phrase ): string {
        return sanitize_text_field( trim( strtolower( $phrase ) ) );
    }

    private function normalize_severity( string $severity ): string {
        $value = strtoupper( sanitize_text_field( $severity ) );
        if ( in_array( $value, array( 'WARN', 'FAIL' ), true ) ) {
            return $value;
        }

        return '';
    }
}

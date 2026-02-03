<?php
/**
 * Sponsor audit helper.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorAudit {
    public static function log_action( array $data ): bool {
        global $wpdb;

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $table = SponsorMigration::audit_table_name();

        $defaults = array(
            'post_id'          => null,
            'job_id'           => 'n/a',
            'sponsor_doc_ids'  => null,
            'public_doc_ids'   => null,
            'action'           => '',
            'actor'            => 'system',
            'model_version'    => 'n/a',
            'prompt_hash'      => 'n/a',
            'justification'    => '',
            'payload'          => null,
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $table,
            array(
                'post_id'         => $data['post_id'] ? absint( $data['post_id'] ) : null,
                'job_id'          => $data['job_id'] ? sanitize_text_field( $data['job_id'] ) : 'n/a',
                'sponsor_doc_ids' => $data['sponsor_doc_ids'] ? wp_json_encode( $data['sponsor_doc_ids'] ) : null,
                'public_doc_ids'  => $data['public_doc_ids'] ? wp_json_encode( $data['public_doc_ids'] ) : null,
                'action'          => sanitize_key( $data['action'] ),
                'actor'           => $data['actor'] ? sanitize_text_field( $data['actor'] ) : 'system',
                'model_version'   => $data['model_version'] ? sanitize_text_field( $data['model_version'] ) : 'n/a',
                'prompt_hash'     => $data['prompt_hash'] ? sanitize_text_field( $data['prompt_hash'] ) : 'n/a',
                'justification'   => $data['justification'] ? sanitize_text_field( $data['justification'] ) : '',
                'payload'         => $data['payload'] ? wp_json_encode( $data['payload'] ) : null,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $result !== false;
    }
}

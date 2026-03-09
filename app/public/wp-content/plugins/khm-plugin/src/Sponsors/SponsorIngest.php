<?php
/**
 * Sponsor bulk ingest helper.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorIngest {
    public static function create_job( int $sponsor_id, array $items, int $allowed_for_export = 1, string $source_type = 'urls', int $created_by = 0, int $library_id = 0 ): int {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $items = self::normalize_items( $items );
        if ( ! $sponsor_id || empty( $items ) ) {
            return 0;
        }

        return self::insert_job(
            $sponsor_id,
            array(
                'allowed_for_export' => $allowed_for_export ? 1 : 0,
                'library_id'         => $library_id > 0 ? $library_id : 0,
                'items'              => $items,
            ),
            sanitize_key( $source_type ?: 'urls' ),
            count( $items ),
            $created_by
        );
    }

    public static function create_source( array $data ): int {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $sponsor_id = absint( $data['sponsor_id'] ?? 0 );
        $root_url = esc_url_raw( $data['root_url'] ?? '' );
        if ( ! $sponsor_id || '' === $root_url ) {
            return 0;
        }

        $allowlist = self::normalize_allowlist( $data['domain_allowlist'] ?? '' );
        $max_pages = max( 1, min( 500, absint( $data['max_pages'] ?? 25 ) ) );
        $max_depth = max( 0, min( 6, absint( $data['max_depth'] ?? 2 ) ) );
        $max_response_kb = max( 64, min( 4096, absint( $data['max_response_kb'] ?? 512 ) ) );
        $status = sanitize_key( $data['status'] ?? 'active' );
        if ( ! in_array( $status, array( 'active', 'paused' ), true ) ) {
            $status = 'active';
        }

        global $wpdb;
        $table = SponsorMigration::sources_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'sponsor_id'       => $sponsor_id,
                'library_id'       => absint( $data['library_id'] ?? 0 ) ?: null,
                'root_url'         => $root_url,
                'domain_allowlist' => implode( ',', $allowlist ),
                'max_pages'        => $max_pages,
                'max_depth'        => $max_depth,
                'max_response_kb'  => $max_response_kb,
                'status'           => $status,
                'created_by'       => absint( $data['created_by'] ?? 0 ) ?: get_current_user_id(),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
        );

        return false === $inserted ? 0 : (int) $wpdb->insert_id;
    }

    public static function list_sources( int $limit = 50 ): array {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::sources_table_name();
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $limit = max( 1, min( 200, $limit ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT src.*, s.name AS sponsor_name FROM {$table} src LEFT JOIN {$sponsors_table} s ON src.sponsor_id = s.id ORDER BY src.created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        return array_map(
            function( array $row ): array {
                return array(
                    'id'              => absint( $row['id'] ?? 0 ),
                    'sponsor_id'      => absint( $row['sponsor_id'] ?? 0 ),
                    'library_id'      => absint( $row['library_id'] ?? 0 ),
                    'sponsor_name'    => sanitize_text_field( $row['sponsor_name'] ?? '' ),
                    'root_url'        => esc_url_raw( $row['root_url'] ?? '' ),
                    'domain_allowlist'=> sanitize_text_field( $row['domain_allowlist'] ?? '' ),
                    'max_pages'       => absint( $row['max_pages'] ?? 25 ),
                    'max_depth'       => absint( $row['max_depth'] ?? 2 ),
                    'max_response_kb' => absint( $row['max_response_kb'] ?? 512 ),
                    'status'          => sanitize_key( $row['status'] ?? 'active' ),
                    'last_run_at'     => sanitize_text_field( $row['last_run_at'] ?? '' ),
                    'last_job_id'     => absint( $row['last_job_id'] ?? 0 ),
                    'updated_at'      => sanitize_text_field( $row['updated_at'] ?? '' ),
                );
            },
            $rows
        );
    }

    public static function queue_source_crawl_job( int $source_id, int $allowed_for_export = 1, int $created_by = 0 ): int {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::sources_table_name();
        $source = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $source_id ),
            ARRAY_A
        );
        if ( empty( $source ) ) {
            return 0;
        }

        if ( sanitize_key( $source['status'] ?? 'active' ) === 'paused' ) {
            return 0;
        }

        $payload = array(
            'source_id'         => absint( $source['id'] ?? 0 ),
            'library_id'        => absint( $source['library_id'] ?? 0 ),
            'root_url'          => esc_url_raw( $source['root_url'] ?? '' ),
            'domain_allowlist'  => self::normalize_allowlist( $source['domain_allowlist'] ?? '' ),
            'max_pages'         => max( 1, absint( $source['max_pages'] ?? 25 ) ),
            'max_depth'         => max( 0, absint( $source['max_depth'] ?? 2 ) ),
            'max_response_kb'   => max( 64, absint( $source['max_response_kb'] ?? 512 ) ),
            'allowed_for_export'=> $allowed_for_export ? 1 : 0,
        );

        $job_id = self::insert_job(
            absint( $source['sponsor_id'] ?? 0 ),
            $payload,
            'crawl',
            max( 1, absint( $payload['max_pages'] ?? 1 ) ),
            $created_by
        );

        if ( $job_id > 0 ) {
            $wpdb->update(
                $table,
                array(
                    'last_job_id' => $job_id,
                    'last_run_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $source_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        }

        return $job_id;
    }

    public static function create_library( array $data ): int {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $sponsor_id = absint( $data['sponsor_id'] ?? 0 );
        $name = sanitize_text_field( $data['name'] ?? '' );
        $topic = sanitize_text_field( $data['topic'] ?? '' );
        if ( ! $sponsor_id || '' === $name ) {
            return 0;
        }

        global $wpdb;
        $table = SponsorMigration::libraries_table_name();
        $inserted = $wpdb->insert(
            $table,
            array(
                'sponsor_id' => $sponsor_id,
                'name'       => $name,
                'topic'      => $topic ?: null,
                'status'     => 'active',
                'created_by' => absint( $data['created_by'] ?? 0 ) ?: get_current_user_id(),
            ),
            array( '%d', '%s', '%s', '%s', '%d' )
        );

        return false === $inserted ? 0 : (int) $wpdb->insert_id;
    }

    public static function list_libraries( int $limit = 200, int $sponsor_id = 0 ): array {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::libraries_table_name();
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $docs_table = SponsorMigration::docs_table_name();
        $limit = max( 1, min( 500, $limit ) );

        if ( $sponsor_id > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.*, s.name AS sponsor_name,
                        COUNT(d.id) AS file_count,
                        SUM(CASE WHEN d.approved = 0 THEN 1 ELSE 0 END) AS pending_count,
                        MAX(d.created_at) AS last_updated
                    FROM {$table} l
                    LEFT JOIN {$sponsors_table} s ON s.id = l.sponsor_id
                    LEFT JOIN {$docs_table} d ON d.library_id = l.id
                    WHERE l.sponsor_id = %d
                    GROUP BY l.id
                    ORDER BY l.created_at DESC
                    LIMIT %d",
                    $sponsor_id,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.*, s.name AS sponsor_name,
                        COUNT(d.id) AS file_count,
                        SUM(CASE WHEN d.approved = 0 THEN 1 ELSE 0 END) AS pending_count,
                        MAX(d.created_at) AS last_updated
                    FROM {$table} l
                    LEFT JOIN {$sponsors_table} s ON s.id = l.sponsor_id
                    LEFT JOIN {$docs_table} d ON d.library_id = l.id
                    GROUP BY l.id
                    ORDER BY l.created_at DESC
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }

        if ( ! is_array( $rows ) ) {
            return array();
        }

        return array_map(
            function( array $row ): array {
                return array(
                    'id'           => absint( $row['id'] ?? 0 ),
                    'sponsor_id'   => absint( $row['sponsor_id'] ?? 0 ),
                    'sponsor_name' => sanitize_text_field( $row['sponsor_name'] ?? '' ),
                    'name'         => sanitize_text_field( $row['name'] ?? '' ),
                    'topic'        => sanitize_text_field( $row['topic'] ?? '' ),
                    'status'       => sanitize_key( $row['status'] ?? 'active' ),
                    'file_count'   => absint( $row['file_count'] ?? 0 ),
                    'pending_count'=> absint( $row['pending_count'] ?? 0 ),
                    'last_updated' => sanitize_text_field( $row['last_updated'] ?? '' ),
                );
            },
            $rows
        );
    }

    public static function get_library( int $library_id ): array {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        if ( $library_id <= 0 ) {
            return array();
        }

        global $wpdb;
        $table = SponsorMigration::libraries_table_name();
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*, s.name AS sponsor_name FROM {$table} l LEFT JOIN {$sponsors_table} s ON s.id = l.sponsor_id WHERE l.id = %d LIMIT 1",
                $library_id
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : array();
    }

    public static function list_docs_by_library( int $library_id, int $limit = 500 ): array {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        if ( $library_id <= 0 ) {
            return array();
        }

        global $wpdb;
        $docs_table = SponsorMigration::docs_table_name();
        $limit = max( 1, min( 1000, $limit ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$docs_table} WHERE library_id = %d ORDER BY created_at DESC LIMIT %d",
                $library_id,
                $limit
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    public static function approve_docs_by_library( int $library_id ): int {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        if ( $library_id <= 0 ) {
            return 0;
        }

        global $wpdb;
        $docs_table = SponsorMigration::docs_table_name();
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$docs_table} SET approved = 1 WHERE library_id = %d AND approved = 0",
                $library_id
            )
        );

        return max( 0, absint( $updated ) );
    }

    public static function approve_imported_docs_by_sponsor( int $sponsor_id ): int {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        if ( ! $sponsor_id ) {
            return 0;
        }

        global $wpdb;
        $docs_table = SponsorMigration::docs_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, meta FROM {$docs_table} WHERE sponsor_id = %d AND approved = 0",
                $sponsor_id
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return 0;
        }

        $approved = 0;
        foreach ( $rows as $row ) {
            $meta = json_decode( (string) ( $row['meta'] ?? '' ), true );
            if ( ! is_array( $meta ) ) {
                continue;
            }
            $source = sanitize_key( $meta['source'] ?? '' );
            if ( ! in_array( $source, array( 'bulk_ingest', 'crawl' ), true ) ) {
                continue;
            }

            $updated = $wpdb->update(
                $docs_table,
                array( 'approved' => 1 ),
                array( 'id' => absint( $row['id'] ) ),
                array( '%d' ),
                array( '%d' )
            );
            if ( false !== $updated ) {
                $approved++;
            }
        }

        if ( $approved > 0 ) {
            SponsorAudit::log_action(
                array(
                    'action'  => 'bulk_approve_imported',
                    'actor'   => (string) ( get_current_user_id() ?: 'system' ),
                    'payload' => array(
                        'sponsor_id' => $sponsor_id,
                        'approved'   => $approved,
                    ),
                )
            );
        }

        return $approved;
    }

    private static function insert_job( int $sponsor_id, array $payload, string $source_type, int $total_items, int $created_by = 0 ): int {
        global $wpdb;
        $table = SponsorMigration::ingest_jobs_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'sponsor_id'       => $sponsor_id,
                'status'           => 'queued',
                'source_type'      => sanitize_key( $source_type ),
                'payload'          => wp_json_encode( $payload ),
                'total_items'      => max( 0, $total_items ),
                'processed_items'  => 0,
                'succeeded_items'  => 0,
                'failed_items'     => 0,
                'created_by'       => $created_by ?: get_current_user_id(),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
        );

        if ( false === $inserted ) {
            return 0;
        }

        $job_id = (int) $wpdb->insert_id;
        if ( $job_id > 0 ) {
            wp_schedule_single_event( time() + 3, 'khm_sponsor_process_ingest_job', array( $job_id ) );
        }

        return $job_id;
    }

    public static function process_job( int $job_id ): bool {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $jobs_table = SponsorMigration::ingest_jobs_table_name();
        $docs_table = SponsorMigration::docs_table_name();

        $job = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$jobs_table} WHERE id = %d", $job_id ),
            ARRAY_A
        );

        if ( empty( $job ) ) {
            return false;
        }

        $status = sanitize_key( $job['status'] ?? '' );
        if ( ! in_array( $status, array( 'queued', 'processing' ), true ) ) {
            return true;
        }

        $payload = json_decode( (string) ( $job['payload'] ?? '' ), true );
        $source_type = sanitize_key( $job['source_type'] ?? 'urls' );

        $wpdb->update(
            $jobs_table,
            array(
                'status'          => 'processing',
                'processed_items' => 0,
                'succeeded_items' => 0,
                'failed_items'    => 0,
                'error_message'   => null,
            ),
            array( 'id' => $job_id ),
            array( '%s', '%d', '%d', '%d', '%s' ),
            array( '%d' )
        );

        $sponsor_id = absint( $job['sponsor_id'] ?? 0 );
        $outcome = array(
            'processed' => 0,
            'succeeded' => 0,
            'failed'    => 0,
            'error'     => '',
        );

        if ( 'crawl' === $source_type ) {
            $outcome = self::process_crawl_job( $sponsor_id, $job_id, is_array( $payload ) ? $payload : array() );
        } else {
            $items = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
            $allowed_for_export = ! empty( $payload['allowed_for_export'] ) ? 1 : 0;
            if ( empty( $items ) ) {
                $outcome = array(
                    'processed' => 0,
                    'succeeded' => 0,
                    'failed'    => 0,
                    'error'     => 'No ingest items were supplied.',
                );
            } else {
                $outcome = self::process_url_items( $sponsor_id, $job_id, $items, $allowed_for_export, 'bulk_ingest', absint( $payload['library_id'] ?? 0 ) );
            }
        }

        $processed = absint( $outcome['processed'] ?? 0 );
        $succeeded = absint( $outcome['succeeded'] ?? 0 );
        $failed = absint( $outcome['failed'] ?? 0 );
        $error_message = sanitize_text_field( $outcome['error'] ?? '' );

        if ( $error_message && 0 === $processed ) {
            $wpdb->update(
                $jobs_table,
                array(
                    'status'          => 'failed',
                    'processed_items' => 0,
                    'succeeded_items' => 0,
                    'failed_items'    => 0,
                    'error_message'   => $error_message,
                ),
                array( 'id' => $job_id ),
                array( '%s', '%d', '%d', '%d', '%s' ),
                array( '%d' )
            );
            return false;
        }

        $final_status = 'completed';
        if ( $failed > 0 && 0 === $succeeded ) {
            $final_status = 'failed';
        } elseif ( $failed > 0 ) {
            $final_status = 'partial';
        }

        $wpdb->update(
            $jobs_table,
            array(
                'status'          => $final_status,
                'processed_items' => $processed,
                'succeeded_items' => $succeeded,
                'failed_items'    => $failed,
                'error_message'   => $error_message ?: null,
            ),
            array( 'id' => $job_id ),
            array( '%s', '%d', '%d', '%d', '%s' ),
            array( '%d' )
        );

        SponsorAudit::log_action(
            array(
                'action'  => 'crawl' === $source_type ? 'crawl_ingest' : 'bulk_ingest',
                'actor'   => (string) ( absint( $job['created_by'] ?? 0 ) ?: get_current_user_id() ?: 'system' ),
                'job_id'  => (string) $job_id,
                'payload' => array(
                    'sponsor_id' => $sponsor_id,
                    'processed'  => $processed,
                    'succeeded'  => $succeeded,
                    'failed'     => $failed,
                    'status'     => $final_status,
                    'source_type'=> $source_type,
                ),
            )
        );

        return true;
    }

    public static function list_jobs( int $limit = 25 ): array {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $jobs_table = SponsorMigration::ingest_jobs_table_name();
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $limit = max( 1, min( 200, $limit ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.*, s.name AS sponsor_name FROM {$jobs_table} j LEFT JOIN {$sponsors_table} s ON j.sponsor_id = s.id ORDER BY j.created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        return array_map(
            function( array $row ): array {
                return array(
                    'id'              => absint( $row['id'] ?? 0 ),
                    'sponsor_id'      => absint( $row['sponsor_id'] ?? 0 ),
                    'sponsor_name'    => sanitize_text_field( $row['sponsor_name'] ?? '' ),
                    'status'          => sanitize_key( $row['status'] ?? 'queued' ),
                    'source_type'     => sanitize_key( $row['source_type'] ?? 'urls' ),
                    'total_items'     => absint( $row['total_items'] ?? 0 ),
                    'processed_items' => absint( $row['processed_items'] ?? 0 ),
                    'succeeded_items' => absint( $row['succeeded_items'] ?? 0 ),
                    'failed_items'    => absint( $row['failed_items'] ?? 0 ),
                    'error_message'   => sanitize_text_field( $row['error_message'] ?? '' ),
                    'created_at'      => sanitize_text_field( $row['created_at'] ?? '' ),
                    'updated_at'      => sanitize_text_field( $row['updated_at'] ?? '' ),
                );
            },
            $rows
        );
    }

    public static function collect_items_from_url_lines( string $raw_urls ): array {
        $items = array();
        $lines = preg_split( '/\r\n|\r|\n/', $raw_urls );
        if ( ! is_array( $lines ) ) {
            return $items;
        }

        foreach ( $lines as $line ) {
            $url = esc_url_raw( trim( (string) $line ) );
            if ( '' === $url ) {
                continue;
            }
            $items[] = array( 'url' => $url );
        }

        return self::normalize_items( $items );
    }

    public static function collect_items_from_csv_file( array $file ): array {
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return array();
        }

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            return array();
        }

        $items = array();
        $header = null;

        while ( false !== ( $row = fgetcsv( $handle ) ) ) {
            if ( empty( $row ) ) {
                continue;
            }

            if ( null === $header ) {
                $normalized_header = array_map(
                    function( $cell ) {
                        return strtolower( trim( (string) $cell ) );
                    },
                    $row
                );
                if ( in_array( 'url', $normalized_header, true ) ) {
                    $header = $normalized_header;
                    continue;
                }
            }

            if ( is_array( $header ) ) {
                $mapped = array();
                foreach ( $header as $idx => $key ) {
                    $mapped[ $key ] = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
                }
                $items[] = array(
                    'url'       => $mapped['url'] ?? '',
                    'title'     => $mapped['title'] ?? '',
                    'authors'   => $mapped['authors'] ?? '',
                    'publisher' => $mapped['publisher'] ?? '',
                    'pub_date'  => $mapped['pub_date'] ?? '',
                );
                continue;
            }

            $items[] = array(
                'url'      => trim( (string) ( $row[0] ?? '' ) ),
                'title'    => trim( (string) ( $row[1] ?? '' ) ),
                'authors'  => trim( (string) ( $row[2] ?? '' ) ),
                'publisher'=> trim( (string) ( $row[3] ?? '' ) ),
                'pub_date' => trim( (string) ( $row[4] ?? '' ) ),
            );
        }

        fclose( $handle );
        return self::normalize_items( $items );
    }

    public static function collect_items_from_uploaded_files( array $files ): array {
        if ( empty( $files ) || ! isset( $files['name'] ) || ! is_array( $files['name'] ) ) {
            return array();
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $items = array();
        $count = count( $files['name'] );
        for ( $index = 0; $index < $count; $index++ ) {
            $single = array(
                'name'     => $files['name'][ $index ] ?? '',
                'type'     => $files['type'][ $index ] ?? '',
                'tmp_name' => $files['tmp_name'][ $index ] ?? '',
                'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][ $index ] ?? 0,
            );

            if ( (int) $single['error'] !== UPLOAD_ERR_OK || empty( $single['tmp_name'] ) ) {
                continue;
            }

            $uploaded = wp_handle_upload( $single, array( 'test_form' => false ) );
            if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) || empty( $uploaded['url'] ) || empty( $uploaded['file'] ) ) {
                continue;
            }

            $attachment_id = wp_insert_attachment(
                array(
                    'post_title'     => sanitize_file_name( pathinfo( (string) $single['name'], PATHINFO_FILENAME ) ),
                    'post_mime_type' => sanitize_mime_type( $uploaded['type'] ?? '' ),
                    'post_status'    => 'inherit',
                ),
                $uploaded['file']
            );

            if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
                $metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
                if ( is_array( $metadata ) ) {
                    wp_update_attachment_metadata( $attachment_id, $metadata );
                }
            }

            $title = sanitize_text_field( pathinfo( (string) $single['name'], PATHINFO_FILENAME ) );
            $thumbnail_url = '';
            $mime_type = (string) ( $uploaded['type'] ?? '' );
            if ( strpos( $mime_type, 'image/' ) === 0 ) {
                $thumbnail_url = esc_url_raw( $uploaded['url'] );
            }

            $pdf_meta = array();
            if ( 'application/pdf' === $mime_type ) {
                $pdf_meta = self::extract_pdf_metadata( (string) $uploaded['file'] );
            }

            $items[] = array(
                'url'      => esc_url_raw( $uploaded['url'] ),
                'title'    => sanitize_text_field( $pdf_meta['title'] ?? $title ),
                'authors'  => sanitize_text_field( $pdf_meta['authors'] ?? '' ),
                'publisher'=> '',
                'pub_date' => sanitize_text_field( $pdf_meta['pub_date'] ?? '' ),
                'cover_thumbnail_url' => $thumbnail_url,
            );
        }

        return self::normalize_items( $items );
    }

    public static function normalize_items( array $items ): array {
        $normalized = array();
        $seen = array();

        foreach ( $items as $item ) {
            if ( is_string( $item ) ) {
                $item = array( 'url' => $item );
            }
            if ( ! is_array( $item ) ) {
                continue;
            }

            $url = esc_url_raw( trim( (string) ( $item['url'] ?? '' ) ) );
            if ( '' === $url || isset( $seen[ $url ] ) ) {
                continue;
            }
            $seen[ $url ] = true;

            $normalized[] = array(
                'url'       => $url,
                'title'     => sanitize_text_field( $item['title'] ?? '' ),
                'authors'   => sanitize_text_field( $item['authors'] ?? '' ),
                'publisher' => sanitize_text_field( $item['publisher'] ?? '' ),
                'pub_date'  => sanitize_text_field( $item['pub_date'] ?? '' ),
                'cover_thumbnail_url' => esc_url_raw( $item['cover_thumbnail_url'] ?? '' ),
            );
        }

        return $normalized;
    }

    private static function process_url_items( int $sponsor_id, int $job_id, array $items, int $allowed_for_export, string $source, int $library_id = 0 ): array {
        global $wpdb;
        $docs_table = SponsorMigration::docs_table_name();
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ( $items as $item ) {
            $processed++;
            $url = esc_url_raw( $item['url'] ?? '' );
            if ( empty( $url ) ) {
                $failed++;
                continue;
            }

            $title = sanitize_text_field( $item['title'] ?? '' );
            if ( '' === $title ) {
                $title = self::title_from_url( $url );
            }

            $web_meta = self::extract_page_metadata( $url );
            if ( '' === $title && ! empty( $web_meta['title'] ) ) {
                $title = sanitize_text_field( $web_meta['title'] );
            }

            if ( '' === $title ) {
                $failed++;
                continue;
            }

            $meta_patch = array(
                'source'   => $source,
                'job_id'   => $job_id,
                'imported' => current_time( 'mysql' ),
            );

            $result = self::upsert_doc_by_url(
                $sponsor_id,
                $url,
                array(
                    'title'              => $title,
                    'authors'            => sanitize_text_field( $item['authors'] ?? ( $web_meta['authors'] ?? '' ) ),
                    'publisher'          => sanitize_text_field( $item['publisher'] ?? '' ),
                    'pub_date'           => self::normalize_pub_date( $item['pub_date'] ?? ( $web_meta['pub_date'] ?? '' ) ),
                    'cover_thumbnail_url'=> esc_url_raw( $item['cover_thumbnail_url'] ?? ( $web_meta['cover_thumbnail_url'] ?? '' ) ),
                    'allowed_for_export' => $allowed_for_export,
                    'created_by'         => get_current_user_id(),
                    'library_id'         => $library_id,
                ),
                $meta_patch
            );

            if ( $result ) {
                $succeeded++;
            }
        }

        return array(
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed'    => $failed,
            'error'     => '',
        );
    }

    private static function process_crawl_job( int $sponsor_id, int $job_id, array $payload ): array {
        $root_url = esc_url_raw( $payload['root_url'] ?? '' );
        if ( '' === $root_url ) {
            return array( 'processed' => 0, 'succeeded' => 0, 'failed' => 0, 'error' => 'Missing root_url for crawl job.' );
        }

        $allowlist = self::normalize_allowlist( $payload['domain_allowlist'] ?? '' );
        $max_pages = max( 1, min( 500, absint( $payload['max_pages'] ?? 25 ) ) );
        $max_depth = max( 0, min( 6, absint( $payload['max_depth'] ?? 2 ) ) );
        $max_response_kb = max( 64, min( 4096, absint( $payload['max_response_kb'] ?? 512 ) ) );
        $allowed_for_export = ! empty( $payload['allowed_for_export'] ) ? 1 : 0;
        $library_id = absint( $payload['library_id'] ?? 0 );

        $base_host = strtolower( (string) wp_parse_url( $root_url, PHP_URL_HOST ) );
        if ( '' === $base_host ) {
            return array( 'processed' => 0, 'succeeded' => 0, 'failed' => 0, 'error' => 'Invalid root_url host for crawl job.' );
        }

        $host_allowlist = array_filter( array_unique( array_merge( array( $base_host ), $allowlist ) ) );
        $robots_rules = self::fetch_robots_rules_for_hosts( $host_allowlist );

        $queue = array( array( 'url' => $root_url, 'depth' => 0 ) );
        $seen = array();
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        while ( ! empty( $queue ) && $processed < $max_pages ) {
            $current = array_shift( $queue );
            $url = esc_url_raw( $current['url'] ?? '' );
            $depth = absint( $current['depth'] ?? 0 );
            if ( '' === $url || isset( $seen[ $url ] ) ) {
                continue;
            }
            $seen[ $url ] = true;

            if ( ! self::is_allowed_url( $url, $host_allowlist ) ) {
                continue;
            }
            if ( ! self::is_allowed_by_robots( $url, $robots_rules ) ) {
                continue;
            }

            $processed++;
            $page = self::fetch_page( $url, $max_response_kb );
            if ( ! empty( $page['error'] ) ) {
                $failed++;
                continue;
            }

            $headers = is_array( $page['headers'] ?? null ) ? $page['headers'] : array();
            $content_type = strtolower( (string) ( $headers['content-type'] ?? '' ) );
            if ( strpos( $content_type, 'text/html' ) === false ) {
                continue;
            }

            $html = (string) ( $page['body'] ?? '' );
            if ( self::is_noindex_page( $html, $headers ) ) {
                continue;
            }

            $title = self::extract_html_title( $html, $url );
            $meta_details = self::extract_html_metadata( $html );
            $etag = sanitize_text_field( (string) ( $headers['etag'] ?? '' ) );
            $last_modified = sanitize_text_field( (string) ( $headers['last-modified'] ?? '' ) );
            $content_hash = hash( 'sha256', $html );

            $upserted = self::upsert_doc_by_url(
                $sponsor_id,
                $url,
                array(
                    'title'              => $title,
                    'authors'            => sanitize_text_field( $meta_details['authors'] ?? '' ),
                    'publisher'          => '',
                    'pub_date'           => self::normalize_pub_date( (string) ( $meta_details['pub_date'] ?? '' ) ),
                    'cover_thumbnail_url'=> esc_url_raw( $meta_details['cover_thumbnail_url'] ?? '' ),
                    'allowed_for_export' => $allowed_for_export,
                    'created_by'         => get_current_user_id(),
                    'library_id'         => $library_id,
                ),
                array(
                    'source'        => 'crawl',
                    'job_id'        => $job_id,
                    'source_id'     => absint( $payload['source_id'] ?? 0 ),
                    'root_url'      => $root_url,
                    'etag'          => $etag,
                    'last_modified' => $last_modified,
                    'content_hash'  => $content_hash,
                    'imported'      => current_time( 'mysql' ),
                )
            );

            if ( $upserted ) {
                $succeeded++;
            }

            if ( $depth < $max_depth ) {
                $links = self::extract_links( $html, $url );
                foreach ( $links as $link ) {
                    if ( count( $queue ) + count( $seen ) >= ( $max_pages * 4 ) ) {
                        break;
                    }
                    if ( ! isset( $seen[ $link ] ) ) {
                        $queue[] = array(
                            'url' => $link,
                            'depth' => $depth + 1,
                        );
                    }
                }
            }
        }

        return array(
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed'    => $failed,
            'error'     => '',
        );
    }

    private static function upsert_doc_by_url( int $sponsor_id, string $url, array $doc_data, array $meta_patch ): bool {
        global $wpdb;
        $docs_table = SponsorMigration::docs_table_name();
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, meta FROM {$docs_table} WHERE sponsor_id = %d AND url = %s LIMIT 1",
                $sponsor_id,
                $url
            ),
            ARRAY_A
        );

        $current_meta = array();
        if ( ! empty( $existing['meta'] ) ) {
            $decoded = json_decode( (string) $existing['meta'], true );
            if ( is_array( $decoded ) ) {
                $current_meta = $decoded;
            }
        }

        $next_meta = array_merge( $current_meta, $meta_patch );

        if ( ! empty( $existing ) ) {
            $same_hash = ! empty( $meta_patch['content_hash'] ) && ! empty( $current_meta['content_hash'] ) && $meta_patch['content_hash'] === $current_meta['content_hash'];
            $same_etag = ! empty( $meta_patch['etag'] ) && ! empty( $current_meta['etag'] ) && $meta_patch['etag'] === $current_meta['etag'];
            $same_modified = ! empty( $meta_patch['last_modified'] ) && ! empty( $current_meta['last_modified'] ) && $meta_patch['last_modified'] === $current_meta['last_modified'];

            if ( $same_hash || ( $same_etag && $same_modified ) ) {
                return true;
            }

            $updated = $wpdb->update(
                $docs_table,
                array(
                    'library_id'         => absint( $doc_data['library_id'] ?? 0 ) ?: null,
                    'title'              => sanitize_text_field( $doc_data['title'] ?? '' ),
                    'authors'            => sanitize_text_field( $doc_data['authors'] ?? '' ),
                    'publisher'          => sanitize_text_field( $doc_data['publisher'] ?? '' ),
                    'pub_date'           => self::normalize_pub_date( (string) ( $doc_data['pub_date'] ?? '' ) ),
                    'cover_thumbnail_url'=> esc_url_raw( $doc_data['cover_thumbnail_url'] ?? '' ),
                    'meta'               => wp_json_encode( $next_meta ),
                    'allowed_for_export' => ! empty( $doc_data['allowed_for_export'] ) ? 1 : 0,
                    'approved'           => 0,
                ),
                array( 'id' => absint( $existing['id'] ?? 0 ) ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ),
                array( '%d' )
            );

            return false !== $updated;
        }

        $inserted = $wpdb->insert(
            $docs_table,
            array(
                'sponsor_id'         => $sponsor_id,
                'library_id'         => absint( $doc_data['library_id'] ?? 0 ) ?: null,
                'title'              => sanitize_text_field( $doc_data['title'] ?? '' ),
                'url'                => $url,
                'authors'            => sanitize_text_field( $doc_data['authors'] ?? '' ),
                'publisher'          => sanitize_text_field( $doc_data['publisher'] ?? '' ),
                'pub_date'           => self::normalize_pub_date( (string) ( $doc_data['pub_date'] ?? '' ) ),
                'cover_thumbnail_url'=> esc_url_raw( $doc_data['cover_thumbnail_url'] ?? '' ),
                'meta'               => wp_json_encode( $next_meta ),
                'allowed_for_export' => ! empty( $doc_data['allowed_for_export'] ) ? 1 : 0,
                'approved'           => 0,
                'created_by'         => absint( $doc_data['created_by'] ?? 0 ) ?: get_current_user_id(),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
        );

        return false !== $inserted;
    }

    private static function fetch_page( string $url, int $max_response_kb ): array {
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 12,
                'redirection' => 3,
                'user-agent' => 'KHM-SponsorCrawler/1.0',
                'headers' => array( 'Accept' => 'text/html,application/xhtml+xml' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return array( 'error' => 'HTTP ' . $code );
        }

        $body = (string) wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > ( $max_response_kb * 1024 ) ) {
            return array( 'error' => 'Response too large' );
        }

        $headers_obj = wp_remote_retrieve_headers( $response );
        $headers = array();
        if ( $headers_obj && method_exists( $headers_obj, 'getAll' ) ) {
            $headers = array_change_key_case( (array) $headers_obj->getAll(), CASE_LOWER );
        } elseif ( is_array( $headers_obj ) ) {
            $headers = array_change_key_case( $headers_obj, CASE_LOWER );
        }

        return array(
            'body' => $body,
            'headers' => $headers,
        );
    }

    private static function extract_html_title( string $html, string $fallback_url ): string {
        if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
            $title = wp_strip_all_tags( html_entity_decode( (string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            $title = trim( preg_replace( '/\s+/', ' ', $title ) );
            if ( '' !== $title ) {
                return sanitize_text_field( $title );
            }
        }

        return self::title_from_url( $fallback_url );
    }

    private static function extract_html_metadata( string $html ): array {
        $meta = array(
            'authors' => '',
            'pub_date' => '',
            'cover_thumbnail_url' => '',
        );

        if ( preg_match( '/<meta[^>]+name=["\']author["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            $meta['authors'] = sanitize_text_field( html_entity_decode( (string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        }

        if ( preg_match( '/<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            $meta['pub_date'] = sanitize_text_field( (string) $matches[1] );
        } elseif ( preg_match( '/<meta[^>]+name=["\']pubdate["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            $meta['pub_date'] = sanitize_text_field( (string) $matches[1] );
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            $meta['cover_thumbnail_url'] = esc_url_raw( (string) $matches[1] );
        }

        return $meta;
    }

    private static function extract_page_metadata( string $url ): array {
        $meta = array(
            'title' => '',
            'authors' => '',
            'pub_date' => '',
            'cover_thumbnail_url' => '',
        );

        $response = wp_remote_get( $url, array( 'timeout' => 8, 'redirection' => 2, 'user-agent' => 'KHM-SponsorCrawler/1.0' ) );
        if ( is_wp_error( $response ) ) {
            return $meta;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return $meta;
        }

        $body = (string) wp_remote_retrieve_body( $response );
        if ( '' === $body ) {
            return $meta;
        }

        $meta['title'] = self::extract_html_title( $body, $url );
        $html_meta = self::extract_html_metadata( $body );
        $meta['authors'] = sanitize_text_field( $html_meta['authors'] ?? '' );
        $meta['pub_date'] = sanitize_text_field( $html_meta['pub_date'] ?? '' );
        $meta['cover_thumbnail_url'] = esc_url_raw( $html_meta['cover_thumbnail_url'] ?? '' );
        return $meta;
    }

    private static function extract_pdf_metadata( string $file_path ): array {
        $meta = array(
            'title' => '',
            'authors' => '',
            'pub_date' => '',
        );

        if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return $meta;
        }

        $chunk = @file_get_contents( $file_path, false, null, 0, 65536 );
        if ( ! is_string( $chunk ) || '' === $chunk ) {
            return $meta;
        }

        if ( preg_match( '/\/Title\s*\(([^\)]+)\)/', $chunk, $matches ) ) {
            $meta['title'] = sanitize_text_field( (string) $matches[1] );
        }
        if ( preg_match( '/\/Author\s*\(([^\)]+)\)/', $chunk, $matches ) ) {
            $meta['authors'] = sanitize_text_field( (string) $matches[1] );
        }
        if ( preg_match( '/\/CreationDate\s*\(D:(\d{4})(\d{2})(\d{2})/', $chunk, $matches ) ) {
            $meta['pub_date'] = sanitize_text_field( $matches[1] . '-' . $matches[2] . '-' . $matches[3] );
        }

        return $meta;
    }

    private static function extract_links( string $html, string $base_url ): array {
        $links = array();
        if ( ! preg_match_all( '/<a[^>]+href=["\']?([^"\' >#]+)["\']?/i', $html, $matches ) ) {
            return $links;
        }

        foreach ( (array) ( $matches[1] ?? array() ) as $href ) {
            $href = trim( (string) $href );
            if ( '' === $href ) {
                continue;
            }

            if ( strpos( $href, 'http://' ) === 0 || strpos( $href, 'https://' ) === 0 ) {
                $resolved = $href;
            } elseif ( strpos( $href, '//' ) === 0 ) {
                $scheme = wp_parse_url( $base_url, PHP_URL_SCHEME ) ?: 'https';
                $resolved = $scheme . ':' . $href;
            } elseif ( strpos( $href, '/' ) === 0 ) {
                $scheme = wp_parse_url( $base_url, PHP_URL_SCHEME ) ?: 'https';
                $host = wp_parse_url( $base_url, PHP_URL_HOST ) ?: '';
                $resolved = $scheme . '://' . $host . $href;
            } else {
                $base_path = (string) wp_parse_url( $base_url, PHP_URL_PATH );
                $base_dir = rtrim( dirname( $base_path ), '/' );
                $scheme = wp_parse_url( $base_url, PHP_URL_SCHEME ) ?: 'https';
                $host = wp_parse_url( $base_url, PHP_URL_HOST ) ?: '';
                $resolved = $scheme . '://' . $host . ( $base_dir ? $base_dir . '/' : '/' ) . ltrim( $href, '/' );
            }

            $resolved = esc_url_raw( $resolved );
            if ( '' !== $resolved ) {
                $links[] = $resolved;
            }
        }

        return array_values( array_unique( $links ) );
    }

    private static function is_noindex_page( string $html, array $headers ): bool {
        $x_robots = strtolower( (string) ( $headers['x-robots-tag'] ?? '' ) );
        if ( strpos( $x_robots, 'noindex' ) !== false ) {
            return true;
        }

        if ( preg_match( '/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            $content = strtolower( (string) ( $matches[1] ?? '' ) );
            if ( strpos( $content, 'noindex' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_allowlist( $raw ): array {
        if ( is_array( $raw ) ) {
            $parts = $raw;
        } else {
            $parts = preg_split( '/\s*,\s*|\r\n|\r|\n/', (string) $raw );
        }

        $hosts = array();
        foreach ( (array) $parts as $part ) {
            $part = strtolower( trim( (string) $part ) );
            if ( '' === $part ) {
                continue;
            }
            $part = preg_replace( '#^https?://#', '', $part );
            $part = preg_replace( '#/.*$#', '', $part );
            if ( '' !== $part ) {
                $hosts[] = $part;
            }
        }

        return array_values( array_unique( $hosts ) );
    }

    private static function is_allowed_url( string $url, array $allowlist_hosts ): bool {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' === $host || empty( $allowlist_hosts ) ) {
            return false;
        }

        foreach ( $allowlist_hosts as $allowed ) {
            $allowed = strtolower( trim( (string) $allowed ) );
            if ( '' === $allowed ) {
                continue;
            }
            if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) {
                return true;
            }
        }

        return false;
    }

    private static function fetch_robots_rules_for_hosts( array $hosts ): array {
        $rules = array();
        foreach ( $hosts as $host ) {
            $host = strtolower( trim( (string) $host ) );
            if ( '' === $host ) {
                continue;
            }

            $robots_url = 'https://' . $host . '/robots.txt';
            $response = wp_remote_get(
                $robots_url,
                array(
                    'timeout' => 8,
                    'redirection' => 2,
                    'user-agent' => 'KHM-SponsorCrawler/1.0',
                )
            );

            $disallow_paths = array();
            if ( ! is_wp_error( $response ) ) {
                $code = (int) wp_remote_retrieve_response_code( $response );
                if ( $code >= 200 && $code < 300 ) {
                    $body = (string) wp_remote_retrieve_body( $response );
                    $lines = preg_split( '/\r\n|\r|\n/', $body );
                    $in_global_agent = false;
                    foreach ( (array) $lines as $line ) {
                        $line = trim( preg_replace( '/#.*/', '', (string) $line ) );
                        if ( '' === $line ) {
                            continue;
                        }
                        if ( stripos( $line, 'user-agent:' ) === 0 ) {
                            $agent = trim( substr( $line, strlen( 'user-agent:' ) ) );
                            $in_global_agent = ( '*' === $agent );
                            continue;
                        }
                        if ( $in_global_agent && stripos( $line, 'disallow:' ) === 0 ) {
                            $path = trim( substr( $line, strlen( 'disallow:' ) ) );
                            if ( '' !== $path ) {
                                $disallow_paths[] = $path;
                            }
                        }
                    }
                }
            }

            $rules[ $host ] = array_values( array_unique( $disallow_paths ) );
        }

        return $rules;
    }

    private static function is_allowed_by_robots( string $url, array $robots_rules ): bool {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        if ( '' === $path ) {
            $path = '/';
        }

        $rules = (array) ( $robots_rules[ $host ] ?? array() );
        if ( empty( $rules ) ) {
            return true;
        }

        foreach ( $rules as $rule ) {
            $rule = trim( (string) $rule );
            if ( '/' === $rule ) {
                return false;
            }
            if ( '' !== $rule && strpos( $path, $rule ) === 0 ) {
                return false;
            }
        }

        return true;
    }

    private static function title_from_url( string $url ): string {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        if ( ! is_string( $path ) || '' === $path || '/' === $path ) {
            $host = wp_parse_url( $url, PHP_URL_HOST );
            return sanitize_text_field( (string) $host );
        }

        $slug = basename( $path );
        $slug = preg_replace( '/\.[a-zA-Z0-9]+$/', '', (string) $slug );
        $slug = str_replace( array( '-', '_' ), ' ', (string) $slug );
        $slug = trim( preg_replace( '/\s+/', ' ', (string) $slug ) );
        if ( '' === $slug ) {
            $host = wp_parse_url( $url, PHP_URL_HOST );
            return sanitize_text_field( (string) $host );
        }

        return sanitize_text_field( ucwords( $slug ) );
    }

    private static function normalize_pub_date( string $pub_date ): ?string {
        $pub_date = trim( $pub_date );
        if ( '' === $pub_date ) {
            return null;
        }

        $timestamp = strtotime( $pub_date );
        if ( false === $timestamp ) {
            return null;
        }

        return gmdate( 'Y-m-d', $timestamp );
    }
}

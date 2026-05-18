<?php
/**
 * Sponsor REST + selection controller.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorController {
    public function register_routes(): void {
        // Sponsor profile endpoint for the Quote Club portal account form
        register_rest_route( 'khm/v1/sponsor', '/profile', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_profile' ),
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsors', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'list_sponsors' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-docs', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_doc' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
                },
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'list_docs' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            ),
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-docs/bulk-import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_bulk_import_job' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-docs/ingest-jobs', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'list_ingest_jobs' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-sources', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'list_sources' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_source' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
                },
            ),
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-sources/(?P<id>\d+)/run', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'run_source' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-docs/bulk-approve', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'bulk_approve_imported_docs' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/sponsor-docs/(?P<id>\\d+)/approve', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'approve_doc' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/research/select', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'select_research_docs' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/cards/(?P<post_id>\\d+)/sponsor-toggle', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'toggle_card_sponsor' ),
            'permission_callback' => function( $request ) {
                $post_id = absint( $request->get_param( 'post_id' ) );
                return current_user_can( 'edit_post', $post_id );
            },
        ) );

        register_rest_route( 'khm-geo/v1', '/cards/(?P<post_id>\\d+)/sponsor-approve', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'approve_card_sponsor' ),
            'permission_callback' => function( $request ) {
                $post_id = absint( $request->get_param( 'post_id' ) );
                return current_user_can( 'publish_posts' ) && current_user_can( 'edit_post', $post_id );
            },
        ) );
    }

    public function list_sponsors(): \WP_REST_Response {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
        $sponsors = array();

        foreach ( $rows as $row ) {
            $sponsors[] = array(
                'id' => absint( $row['id'] ),
                'name' => $row['name'],
                'url' => $row['url'] ?? '',
                'contact_email' => $row['contact_email'],
                'publish_allowed' => ! empty( $row['publish_allowed'] ),
            );
        }

        return new \WP_REST_Response( $sponsors );
    }

    public function create_doc( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $body = $request->get_json_params();
        $sponsor_id = absint( $body['sponsor_id'] ?? 0 );
        $title = sanitize_text_field( $body['title'] ?? '' );
        $url = esc_url_raw( $body['url'] ?? '' );
        $allowed_for_export = ! empty( $body['allowed_for_export'] ) ? 1 : 0;

        if ( ! $sponsor_id || empty( $title ) || empty( $url ) ) {
            return new \WP_REST_Response( array( 'error' => 'Missing sponsor_id, title, or url.' ), 400 );
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();

        $wpdb->insert(
            $table,
            array(
                'sponsor_id' => $sponsor_id,
                'title'      => $title,
                'url'        => $url,
                'authors'    => sanitize_text_field( $body['authors'] ?? '' ),
                'publisher'  => sanitize_text_field( $body['publisher'] ?? '' ),
                'pub_date'   => ! empty( $body['pub_date'] ) ? sanitize_text_field( $body['pub_date'] ) : null,
                'meta'       => ! empty( $body['meta'] ) ? wp_json_encode( $body['meta'] ) : null,
                'allowed_for_export' => $allowed_for_export,
                'approved'   => 0,
                'created_by' => get_current_user_id(),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
        );

        return new \WP_REST_Response( array( 'id' => $wpdb->insert_id ), 201 );
    }

    public function list_docs( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $sponsor_id = absint( $request->get_param( 'sponsor_id' ) );

        if ( $sponsor_id ) {
            $docs = $wpdb->get_results(
                $wpdb->prepare( "SELECT d.*, s.name as sponsor_name FROM {$table} d LEFT JOIN {$sponsors_table} s ON d.sponsor_id = s.id WHERE d.sponsor_id = %d ORDER BY d.created_at DESC", $sponsor_id ),
                ARRAY_A
            );
        } else {
            $docs = $wpdb->get_results( "SELECT d.*, s.name as sponsor_name FROM {$table} d LEFT JOIN {$sponsors_table} s ON d.sponsor_id = s.id ORDER BY d.created_at DESC", ARRAY_A );
        }

        return new \WP_REST_Response( $this->normalize_docs( $docs ) );
    }

    public function approve_doc( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $id = absint( $request->get_param( 'id' ) );
        if ( ! $id ) {
            return new \WP_REST_Response( array( 'error' => 'Invalid sponsor doc id.' ), 400 );
        }

        $wpdb->update(
            $table,
            array( 'approved' => 1 ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );

        return new \WP_REST_Response( array( 'success' => true ) );
    }

    public function create_bulk_import_job( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $sponsor_id = absint( $body['sponsor_id'] ?? 0 );
        $allowed_for_export = ! empty( $body['allowed_for_export'] ) ? 1 : 0;
        $source_type = sanitize_key( $body['source_type'] ?? 'urls' );

        $items = array();
        if ( ! empty( $body['urls'] ) && is_string( $body['urls'] ) ) {
            $items = SponsorIngest::collect_items_from_url_lines( $body['urls'] );
        } elseif ( ! empty( $body['urls'] ) && is_array( $body['urls'] ) ) {
            $items = SponsorIngest::normalize_items( $body['urls'] );
        }

        if ( ! $sponsor_id || empty( $items ) ) {
            return new \WP_REST_Response( array( 'error' => 'Missing sponsor_id or bulk URLs payload.' ), 400 );
        }

        $job_id = SponsorIngest::create_job( $sponsor_id, $items, $allowed_for_export, $source_type, get_current_user_id() );
        if ( ! $job_id ) {
            return new \WP_REST_Response( array( 'error' => 'Failed to queue ingest job.' ), 500 );
        }

        return new \WP_REST_Response(
            array(
                'job_id'      => $job_id,
                'status'      => 'queued',
                'item_count'  => count( $items ),
                'sponsor_id'  => $sponsor_id,
            ),
            201
        );
    }

    public function list_ingest_jobs( \WP_REST_Request $request ): \WP_REST_Response {
        $limit = absint( $request->get_param( 'limit' ) ?: 25 );
        return new \WP_REST_Response( SponsorIngest::list_jobs( $limit ) );
    }

    public function list_sources( \WP_REST_Request $request ): \WP_REST_Response {
        $limit = absint( $request->get_param( 'limit' ) ?: 50 );
        return new \WP_REST_Response( SponsorIngest::list_sources( $limit ) );
    }

    public function create_source( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $source_id = SponsorIngest::create_source(
            array(
                'sponsor_id'       => absint( $body['sponsor_id'] ?? 0 ),
                'root_url'         => esc_url_raw( $body['root_url'] ?? '' ),
                'domain_allowlist' => sanitize_text_field( $body['domain_allowlist'] ?? '' ),
                'max_pages'        => absint( $body['max_pages'] ?? 25 ),
                'max_depth'        => absint( $body['max_depth'] ?? 2 ),
                'max_response_kb'  => absint( $body['max_response_kb'] ?? 512 ),
                'status'           => sanitize_key( $body['status'] ?? 'active' ),
                'created_by'       => get_current_user_id(),
            )
        );

        if ( ! $source_id ) {
            return new \WP_REST_Response( array( 'error' => 'Failed to create source.' ), 400 );
        }

        return new \WP_REST_Response( array( 'source_id' => $source_id ), 201 );
    }

    public function run_source( \WP_REST_Request $request ): \WP_REST_Response {
        $source_id = absint( $request->get_param( 'id' ) );
        if ( ! $source_id ) {
            return new \WP_REST_Response( array( 'error' => 'Invalid source id.' ), 400 );
        }

        $job_id = SponsorIngest::queue_source_crawl_job( $source_id, 1, get_current_user_id() );
        if ( ! $job_id ) {
            return new \WP_REST_Response( array( 'error' => 'Failed to queue source crawl job.' ), 400 );
        }

        return new \WP_REST_Response( array( 'job_id' => $job_id, 'status' => 'queued' ), 201 );
    }

    public function bulk_approve_imported_docs( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $sponsor_id = absint( $body['sponsor_id'] ?? 0 );
        if ( ! $sponsor_id ) {
            return new \WP_REST_Response( array( 'error' => 'Missing sponsor_id.' ), 400 );
        }

        $approved_count = SponsorIngest::approve_imported_docs_by_sponsor( $sponsor_id );
        return new \WP_REST_Response( array( 'approved_count' => $approved_count ) );
    }

    public function select_research_docs( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $body = $request->get_json_params();
        $post_id = absint( $body['post_id'] ?? 0 );
        $topic = sanitize_text_field( $body['topic'] ?? '' );
        $job_id = sanitize_text_field( $body['job_id'] ?? wp_generate_uuid4() );
        $min_score = floatval( get_option( 'khm_geo_sponsor_min_score', 0.35 ) );

        $sponsor_docs = $this->get_approved_sponsor_docs();
        $ranked_sponsors = $this->rank_docs_by_topic( $sponsor_docs, $topic );
        $filtered_sponsors = array_filter( $ranked_sponsors, function( $doc ) use ( $min_score ) {
            return ( $doc['score'] ?? 0 ) >= $min_score;
        } );
        $sponsor_selected = array_slice( array_values( $filtered_sponsors ), 0, 3 );

        $public_selected = $this->select_public_docs_from_post( $post_id );
        $warnings = array();
        if ( count( $sponsor_selected ) < 2 ) {
            $warnings[] = 'sponsor_insufficient';
        }

        SponsorAudit::log_action( array(
            'post_id'         => $post_id,
            'job_id'          => $job_id,
            'sponsor_doc_ids' => array_column( $sponsor_selected, 'id' ),
            'public_doc_ids'  => array_column( $public_selected, 'id' ),
            'action'          => 'selection',
            'actor'           => get_current_user_id() ? (string) get_current_user_id() : 'editorial-assistant',
            'model_version'   => sanitize_text_field( $body['model_version'] ?? 'n/a' ),
            'prompt_hash'     => sanitize_text_field( $body['prompt_hash'] ?? 'n/a' ),
            'payload'         => array(
                'topic' => $topic,
                'sponsor_scores' => $sponsor_selected,
                'public_scores' => $public_selected,
            ),
        ) );

        return new \WP_REST_Response( array(
            'job_id'            => $job_id,
            'sponsor_selected'  => array_map( function( $doc ) {
                return array(
                    'doc_id' => $doc['id'],
                    'score'  => $doc['score'] ?? 0,
                    'title'  => $doc['title'] ?? '',
                );
            }, $sponsor_selected ),
            'public_selected'   => array_map( function( $doc ) {
                return array(
                    'doc_id' => $doc['id'],
                    'score'  => $doc['score'] ?? 0,
                    'title'  => $doc['title'] ?? '',
                );
            }, $public_selected ),
            'warnings'          => $warnings,
        ) );
    }

    public function toggle_card_sponsor( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = absint( $request->get_param( 'post_id' ) );
        $body = $request->get_json_params();
        $enable = ! empty( $body['enable'] );
        $sponsor_id = absint( $body['sponsor_id'] ?? 0 );
        $answer_card_id = sanitize_text_field( $body['answer_card_id'] ?? '' );
        $sponsor_doc_ids = array_map( 'absint', $body['sponsor_doc_ids'] ?? array() );
        $boost = isset( $body['sponsor_boost'] ) ? floatval( $body['sponsor_boost'] ) : 0.0;
        $approval_required = isset( $body['require_approval'] )
            ? (bool) $body['require_approval']
            : ( isset( $body['approval_required'] ) ? (bool) $body['approval_required'] : true );
        $approved = isset( $body['approved'] ) ? (bool) $body['approved'] : false;
        $justification = sanitize_text_field( $body['justification'] ?? '' );
        $sponsor_name = sanitize_text_field( $body['sponsor_name'] ?? '' );
        $sponsor_url = esc_url_raw( $body['sponsor_url'] ?? '' );

        $cards = get_post_meta( $post_id, '_geo_answercards', true );
        if ( ! is_array( $cards ) ) {
            return new \WP_REST_Response( array( 'error' => 'No AnswerCards found.' ), 404 );
        }

        $updated = false;
        $approved_doc_ids = array();
        foreach ( $cards as $idx => $card ) {
            if ( $answer_card_id && ( $card['answer_card_id'] ?? '' ) !== $answer_card_id ) {
                continue;
            }

            if ( $enable ) {
                if ( $sponsor_id && empty( $sponsor_name ) ) {
                    $sponsor_row = $this->get_sponsor_by_id( $sponsor_id );
                    if ( $sponsor_row ) {
                        $sponsor_name = sanitize_text_field( $sponsor_row['name'] ?? '' );
                        $sponsor_url = esc_url_raw( $sponsor_row['url'] ?? '' );
                    }
                }

                $cards[ $idx ]['sponsor_toggle'] = true;
                $cards[ $idx ]['sponsor_id'] = $sponsor_id;
                $cards[ $idx ]['sponsor_name'] = $sponsor_name;
                $cards[ $idx ]['sponsor_url'] = $sponsor_url;
                $cards[ $idx ]['sponsor_boost'] = max( 0, min( 0.1, $boost ) );
                $cards[ $idx ]['sponsor_requires_approval'] = $approval_required;
                $cards[ $idx ]['sponsor_approved'] = $approved;
                $cards[ $idx ]['sponsor_justification'] = $justification;
                $cards[ $idx ]['citation_ordering'] = 'sponsor_first';
                $cards[ $idx ]['sponsor_doc_ids'] = $sponsor_doc_ids;
                $cards[ $idx ]['sponsor'] = array(
                    'id' => $sponsor_id,
                    'name' => $sponsor_name,
                    'url' => $sponsor_url,
                );

                if ( empty( $cards[ $idx ]['citations_original'] ) && ! empty( $cards[ $idx ]['citations'] ) ) {
                    $cards[ $idx ]['citations_original'] = $cards[ $idx ]['citations'];
                }

                if ( ! empty( $sponsor_doc_ids ) ) {
                    $sponsor_docs = $this->get_sponsor_docs_by_ids( $sponsor_doc_ids );
                    $cards[ $idx ]['citations'] = $this->merge_sponsor_citations(
                        $cards[ $idx ]['citations'],
                        $sponsor_docs,
                        $sponsor_id,
                        $approved
                    );
                }

                $cards[ $idx ]['citations'] = $this->reorder_citations_sponsor_first(
                    $cards[ $idx ]['citations'],
                    $sponsor_id,
                    $sponsor_doc_ids
                );
            } else {
                $cards[ $idx ]['sponsor_toggle'] = false;
                $cards[ $idx ]['sponsor_id'] = null;
                $cards[ $idx ]['sponsor_name'] = '';
                $cards[ $idx ]['sponsor_url'] = '';
                $cards[ $idx ]['sponsor_boost'] = 0.0;
                $cards[ $idx ]['sponsor_requires_approval'] = false;
                $cards[ $idx ]['sponsor_approved'] = false;
                $cards[ $idx ]['sponsor_justification'] = '';
                $cards[ $idx ]['citation_ordering'] = '';
                $cards[ $idx ]['sponsor'] = array();
                $cards[ $idx ]['sponsor_doc_ids'] = array();

                if ( ! empty( $cards[ $idx ]['citations_original'] ) ) {
                    $cards[ $idx ]['citations'] = $cards[ $idx ]['citations_original'];
                }
            }

            $cards[ $idx ]['audit'] = $this->append_card_audit(
                $cards[ $idx ]['audit'] ?? array(),
                array(
                    'event' => $enable ? 'sponsor_toggle_on' : 'sponsor_toggle_off',
                    'actor' => get_current_user_id(),
                    'sponsor_id' => $sponsor_id,
                    'sponsor_boost' => $boost,
                    'approved' => $approved,
                    'justification' => $justification,
                    'timestamp' => current_time( 'mysql' ),
                )
            );

            $approved_doc_ids = $cards[ $idx ]['sponsor_doc_ids'] ?? array();
            $updated = true;
        }

        if ( ! $updated ) {
            return new \WP_REST_Response( array( 'error' => 'AnswerCard not found.' ), 404 );
        }

        update_post_meta( $post_id, '_geo_answercards', $cards );

        SponsorAudit::log_action( array(
            'post_id'        => $post_id,
            'job_id'         => sanitize_text_field( $body['job_id'] ?? 'manual' ),
            'sponsor_doc_ids'=> $sponsor_doc_ids,
            'public_doc_ids' => array(),
            'action'         => 'toggle',
            'actor'          => get_current_user_id() ? (string) get_current_user_id() : 'editorial-assistant',
            'justification'  => $justification,
            'payload'        => array(
                'enable' => $enable,
                'answer_card_id' => $answer_card_id,
                'sponsor_boost' => $boost,
                'approved' => $approved,
            ),
        ) );

        return new \WP_REST_Response( array( 'success' => true, 'cards' => $cards ) );
    }

    public function approve_card_sponsor( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = absint( $request->get_param( 'post_id' ) );
        $body = $request->get_json_params();
        $answer_card_id = sanitize_text_field( $body['answer_card_id'] ?? '' );
        $justification = sanitize_text_field( $body['justification'] ?? '' );

        $cards = get_post_meta( $post_id, '_geo_answercards', true );
        if ( ! is_array( $cards ) ) {
            return new \WP_REST_Response( array( 'error' => 'No AnswerCards found.' ), 404 );
        }

        $updated = false;
        foreach ( $cards as $idx => $card ) {
            if ( $answer_card_id && ( $card['answer_card_id'] ?? '' ) !== $answer_card_id ) {
                continue;
            }

            $cards[ $idx ]['sponsor_approved'] = true;
            $cards[ $idx ]['sponsor_justification'] = $justification;
            $approved_doc_ids = $cards[ $idx ]['sponsor_doc_ids'] ?? array();
            if ( ! empty( $cards[ $idx ]['citations'] ) && ! empty( $approved_doc_ids ) ) {
                foreach ( $cards[ $idx ]['citations'] as $c_idx => $citation ) {
                    if ( empty( $citation['sponsor_doc_id'] ) ) {
                        continue;
                    }
                    $doc_id = absint( $citation['sponsor_doc_id'] );
                    if ( in_array( $doc_id, $approved_doc_ids, true ) ) {
                        $cards[ $idx ]['citations'][ $c_idx ]['sponsor_approved'] = true;
                        $cards[ $idx ]['citations'][ $c_idx ]['sponsor_id'] = absint( $card['sponsor_id'] ?? 0 );
                    }
                }
            }
            $cards[ $idx ]['audit'] = $this->append_card_audit(
                $cards[ $idx ]['audit'] ?? array(),
                array(
                    'event' => 'sponsor_approve',
                    'actor' => get_current_user_id(),
                    'sponsor_id' => $card['sponsor_id'] ?? 0,
                    'justification' => $justification,
                    'timestamp' => current_time( 'mysql' ),
                )
            );

            $updated = true;
        }

        if ( ! $updated ) {
            return new \WP_REST_Response( array( 'error' => 'AnswerCard not found.' ), 404 );
        }

        update_post_meta( $post_id, '_geo_answercards', $cards );

        SponsorAudit::log_action( array(
            'post_id'        => $post_id,
            'job_id'         => sanitize_text_field( $body['job_id'] ?? 'manual' ),
            'sponsor_doc_ids'=> $approved_doc_ids,
            'public_doc_ids' => array(),
            'action'         => 'approve',
            'actor'          => (string) get_current_user_id(),
            'justification'  => $justification,
        ) );

        return new \WP_REST_Response( array( 'success' => true ) );
    }

    private function normalize_docs( array $docs ): array {
        $normalized = array();
        foreach ( $docs as $doc ) {
            $meta = array();
            if ( ! empty( $doc['meta'] ) ) {
                $decoded = json_decode( $doc['meta'], true );
                if ( is_array( $decoded ) ) {
                    $meta = $decoded;
                }
            }
            $normalized[] = array(
                'id'         => absint( $doc['id'] ?? 0 ),
                'sponsor_id' => absint( $doc['sponsor_id'] ?? 0 ),
                'title'      => $doc['title'] ?? '',
                'url'        => $doc['url'] ?? '',
                'authors'    => $doc['authors'] ?? '',
                'publisher'  => $doc['publisher'] ?? '',
                'pub_date'   => $doc['pub_date'] ?? '',
                'sponsor_name' => $doc['sponsor_name'] ?? '',
                'allowed_for_export' => isset( $doc['allowed_for_export'] ) ? (int) $doc['allowed_for_export'] : 1,
                'meta'       => $meta,
                'approved'   => ! empty( $doc['approved'] ),
            );
        }
        return $normalized;
    }

    private function get_sponsor_by_id( int $sponsor_id ): ?array {
        if ( ! $sponsor_id ) {
            return null;
        }
        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $sponsor_id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    private function get_sponsor_docs_by_ids( array $doc_ids ): array {
        // Validate that all doc_ids are integers
        // Ensure all doc_ids are integers to prevent SQL injection
        $doc_ids = array_values( array_filter( array_map( 'absint', $doc_ids ) ) );
        if ( empty( $doc_ids ) ) {
            return array();
        }
        
        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        // Safe to use placeholders with prepare() since all IDs are validated integers
        $placeholders = implode( ',', array_fill( 0, count( $doc_ids ), '%d' ) );
        $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders})";
        $prepared = $wpdb->prepare( $sql, $doc_ids );
        $rows = $wpdb->get_results( $prepared, ARRAY_A );
        return $rows ?: array();
    }

    private function merge_sponsor_citations( array $citations, array $sponsor_docs, int $sponsor_id, bool $approved ): array {
        if ( empty( $sponsor_docs ) ) {
            return $citations;
        }

        $existing_urls = array();
        foreach ( $citations as $citation ) {
            if ( ! empty( $citation['url'] ) ) {
                $existing_urls[] = $citation['url'];
            }
        }

        $sponsor_citations = array();
        foreach ( $sponsor_docs as $doc ) {
            $doc_url = $doc['url'] ?? '';
            if ( $doc_url && in_array( $doc_url, $existing_urls, true ) ) {
                continue;
            }

            $meta = array();
            if ( ! empty( $doc['meta'] ) && is_string( $doc['meta'] ) ) {
                $decoded = json_decode( $doc['meta'], true );
                if ( is_array( $decoded ) ) {
                    $meta = $decoded;
                }
            }

            $tier = $meta['tier'] ?? '';
            $year = '';
            if ( ! empty( $doc['pub_date'] ) ) {
                $year = substr( $doc['pub_date'], 0, 4 );
            }

            $sponsor_citations[] = array(
                'title'            => $doc['title'] ?? '',
                'url'              => $doc_url,
                'author'           => $doc['authors'] ?? '',
                'publisher'        => $doc['publisher'] ?? '',
                'year'             => $year,
                'tier'             => $tier,
                'doi'              => '',
                'keywords'         => array(),
                'enable_tracking'  => false,
                'tracked_url'      => '',
                'sponsor_id'       => $sponsor_id,
                'sponsor_doc_id'   => absint( $doc['id'] ?? 0 ),
                'sponsor_approved' => $approved,
                'allowed_for_export' => isset( $doc['allowed_for_export'] ) ? (int) $doc['allowed_for_export'] : 1,
            );
        }

        if ( empty( $sponsor_citations ) ) {
            return $citations;
        }

        return array_merge( $sponsor_citations, $citations );
    }

    private function get_approved_sponsor_docs(): array {
        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $docs = $wpdb->get_results( "SELECT * FROM {$table} WHERE approved = 1", ARRAY_A );
        return $this->normalize_docs( $docs );
    }

    private function rank_docs_by_topic( array $docs, string $topic ): array {
        if ( empty( $topic ) ) {
            return $docs;
        }

        $topic_tokens = $this->tokenize( $topic );
        foreach ( $docs as &$doc ) {
            $haystack = implode( ' ', array_filter( array(
                $doc['title'] ?? '',
                $doc['authors'] ?? '',
                $doc['publisher'] ?? '',
            ) ) );
            $score = $this->overlap_score( $topic_tokens, $this->tokenize( $haystack ) );
            $quality = 0.0;
            if ( ! empty( $doc['authors'] ) ) {
                $quality += 0.15;
            }
            if ( ! empty( $doc['publisher'] ) ) {
                $quality += 0.15;
            }
            if ( ! empty( $doc['pub_date'] ) ) {
                $quality += 0.1;
            }
            $doc['score'] = round( min( 1.0, $score + $quality ), 3 );
        }
        unset( $doc );

        usort( $docs, function( $a, $b ) {
            return ( $b['score'] ?? 0 ) <=> ( $a['score'] ?? 0 );
        } );

        return $docs;
    }

    private function select_public_docs_from_post( int $post_id ): array {
        $cards = get_post_meta( $post_id, '_geo_answercards', true );
        if ( empty( $cards ) || ! is_array( $cards ) ) {
            return array();
        }

        $seen = array();
        $public = array();
        foreach ( $cards as $card ) {
            foreach ( (array) ( $card['citations'] ?? array() ) as $citation ) {
                if ( ! is_array( $citation ) ) {
                    continue;
                }
                if ( ! empty( $citation['sponsor_id'] ) ) {
                    continue;
                }
                $url = $citation['url'] ?? '';
                if ( ! $url || isset( $seen[ $url ] ) ) {
                    continue;
                }
                $seen[ $url ] = true;
                $public[] = array(
                    'id'    => $url,
                    'title' => $citation['title'] ?? $url,
                    'url'   => $url,
                    'score' => 0.5,
                );
                if ( count( $public ) >= 3 ) {
                    break 2;
                }
            }
        }

        return $public;
    }

    private function reorder_citations_sponsor_first( array $citations, int $sponsor_id, array $sponsor_doc_ids = array() ): array {
        if ( empty( $citations ) ) {
            return $citations;
        }

        $sponsor = array();
        $public = array();
        foreach ( $citations as $citation ) {
            $is_sponsor = false;
            if ( is_array( $citation ) ) {
                if ( ! empty( $sponsor_doc_ids ) && ! empty( $citation['sponsor_doc_id'] ) ) {
                    $is_sponsor = in_array( absint( $citation['sponsor_doc_id'] ), $sponsor_doc_ids, true );
                } elseif ( ! empty( $citation['sponsor_id'] ) ) {
                    $is_sponsor = absint( $citation['sponsor_id'] ) === $sponsor_id;
                }
            }

            if ( $is_sponsor ) {
                $sponsor[] = $citation;
            } else {
                $public[] = $citation;
            }
        }

        return array_merge( $sponsor, $public );
    }

    private function tokenize( string $text ): array {
        $text = strtolower( preg_replace( '/[^a-z0-9\s]/', ' ', $text ) );
        $parts = array_filter( explode( ' ', $text ) );
        return array_values( array_unique( $parts ) );
    }

    private function overlap_score( array $a, array $b ): float {
        if ( empty( $a ) || empty( $b ) ) {
            return 0.0;
        }
        $intersection = array_intersect( $a, $b );
        return count( $intersection ) / max( count( $a ), 1 );
    }

    private function append_card_audit( array $audit, array $entry ): array {
        if ( empty( $audit ) || ! is_array( $audit ) ) {
            $audit = array();
        }
        $audit[] = $entry;
        return $audit;
    }

    /**
     * Save/update the sponsor account profile from the Quote Club portal form.
     *
     * POST /wp-json/khm/v1/sponsor/profile
     */
    public function update_profile( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Not logged in.' ), 401 );
        }

        $body = $request->get_json_params();
        $sponsor_id = absint( $body['sponsor_id'] ?? 0 );

        if ( ! $sponsor_id ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Missing sponsor_id.' ), 400 );
        }

        // Verify the current user belongs to this sponsor
        $sponsor = \KHM\Services\SponsorService::get_user_sponsor( $user_id );
        if ( ! $sponsor || (int) ( $sponsor['id'] ?? 0 ) !== $sponsor_id ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Access denied.' ), 403 );
        }

        global $wpdb;
        $sponsors_table = SponsorMigration::sponsors_table_name();

        // Company profile fields
        $company_name = sanitize_text_field( (string) ( $body['company_name'] ?? '' ) );
        $raw_url      = trim( (string) ( $body['company_url'] ?? '' ) );
        if ( $raw_url && ! preg_match( '/^https?:\/\//', $raw_url ) ) {
            $raw_url = 'https://' . $raw_url;
        }
        $company_url  = esc_url_raw( $raw_url );
        $hq_location  = sanitize_text_field( (string) ( $body['hq_location'] ?? '' ) );
        $regions      = isset( $body['regions'] ) && is_array( $body['regions'] )
            ? array_map( 'sanitize_text_field', $body['regions'] )
            : array();
        $deployment_mode = sanitize_text_field( (string) ( $body['deployment_mode'] ?? 'cloud' ) );
        $impl_support    = ! empty( $body['implementation_support'] ) ? 1 : 0;
        $support_hours   = sanitize_text_field( (string) ( $body['support_hours'] ?? 'business' ) );
        $pilot_terms     = sanitize_textarea_field( (string) ( $body['pilot_terms'] ?? '' ) );

        $updated = $wpdb->update(
            $sponsors_table,
            array(
                'name'                  => $company_name,
                'url'                   => $company_url,
                'hq_location'           => $hq_location,
                'regions'               => wp_json_encode( $regions ),
                'deployment_modes'      => $deployment_mode,
                'implementation_support' => $impl_support,
                'support_hours'         => $support_hours,
                'updated_at'            => current_time( 'mysql' ),
            ),
            array( 'id' => $sponsor_id ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => 'Database update failed.' ), 500 );
        }

        // Sync solution mappings to tc_sponsor_solutions
        $solutions = isset( $body['solutions'] ) && is_array( $body['solutions'] )
            ? array_map( 'absint', $body['solutions'] )
            : array();

        $mapping_table = $wpdb->prefix . 'tc_sponsor_solutions';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$mapping_table}'" ) === $mapping_table ) {
            // Remove existing mappings
            $wpdb->delete( $mapping_table, array( 'sponsor_id' => $sponsor_id ), array( '%d' ) );

            // Insert new mappings
            foreach ( $solutions as $solution_id ) {
                if ( $solution_id > 0 ) {
                    $wpdb->insert(
                        $mapping_table,
                        array(
                            'sponsor_id'  => $sponsor_id,
                            'solution_id' => $solution_id,
                        ),
                        array( '%d', '%d' )
                    );
                }
            }
        }

        return new \WP_REST_Response( array( 'success' => true, 'message' => 'Settings saved successfully.' ) );
    }
}

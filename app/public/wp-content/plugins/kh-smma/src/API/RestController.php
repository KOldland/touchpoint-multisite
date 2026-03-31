<?php
namespace KH_SMMA\API;

use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\PhaseEngine;
use KH_SMMA\Services\ScheduleQueueProcessor;
use KH_SMMA\Services\ComplianceValidator;
use KH_SMMA\Services\Card1StateStore;
use KH_SMMA\Generator\VariantGenerator;
use KH_SMMA\Compliance\ComplianceService;
use KH_SMMA\Variants\VariantRepository;
use KH_SMMA\Variants\VariantRevisionRepository;
use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestController {
    private FeatureFlags $flags;
    private SmmaGenerator $generator;
    private AuditLogger $logger;
    private ?PhaseEngine $phase_engine;
    private ?ComplianceValidator $compliance_validator;
    private Card1StateStore $card1_store;
    private VariantGenerator $variant_generator;
    private VariantEditController $variant_edit_controller;
    private ComplianceService $compliance_service;
    private ScheduleController $schedule_controller;
    private ?EventEmitter $emitter;

    public function __construct( FeatureFlags $flags, SmmaGenerator $generator, AuditLogger $logger, PhaseEngine $phase_engine = null, ComplianceValidator $compliance_validator = null, Card1StateStore $card1_store = null, EventEmitter $emitter = null ) {
        global $wpdb;
        $db = ( $wpdb instanceof \wpdb ) ? $wpdb : ( class_exists( 'wpdb' ) ? new \wpdb() : null );
        $this->flags = $flags;
        $this->generator = $generator;
        $this->logger = $logger;
        $this->phase_engine = $phase_engine;
        $this->compliance_validator = $compliance_validator ?? new ComplianceValidator();
        $this->card1_store = $card1_store ?? new Card1StateStore();
        $this->variant_generator = new VariantGenerator();
        $variant_repo = new VariantRepository( $db );
        $revision_repo = new VariantRevisionRepository( $db );
        $this->variant_edit_controller = new VariantEditController( $variant_repo, $revision_repo, $logger );
        $this->compliance_service = new ComplianceService();
        $this->schedule_controller = new ScheduleController( $this->card1_store );
        $this->emitter = $emitter;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( 'kh-smma/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_generate' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/export', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_export' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/record-event', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_record_event' ),
            'permission_callback' => array( $this, 'check_record_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/schedule', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_schedule' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/boost/prepare', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_boost_prepare' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/approve', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_approve' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/sponsor/(?P<id>\\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_get_sponsor' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/seo-table', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_seo_table' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/variant-edit', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_variant_edit' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/variant/(?P<variant_id>[A-Za-z0-9_\\-]+)/edit', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_variant_edit_v2' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/reject', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_reject' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/compliance/check', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_compliance_check' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/demo/compose', array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, 'handle_demo_compose_get' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'handle_demo_compose_post' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
        ) );
    }

    public function check_permissions( WP_REST_Request $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'kh_smma_forbidden', __( 'Insufficient permissions.', 'kh-smma' ), array( 'status' => 403 ) );
        }

        if ( ! $this->flags->is_enabled( 'smma' ) ) {
            return new WP_Error(
                'kh_smma_disabled',
                __( 'Social Campaigns is currently unavailable. Please ask an administrator to enable it in KH Social settings.', 'kh-smma' ),
                array( 'status' => 403 )
            );
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'kh_smma_bad_nonce', __( 'Invalid REST nonce.', 'kh-smma' ), array( 'status' => 401 ) );
        }

        return true;
    }

    public function check_record_permissions( WP_REST_Request $request ) {
        if ( is_user_logged_in() ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );
            if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return true;
            }
        }

        $service_token = get_option( 'kh_smma_service_token' );
        if ( $service_token ) {
            $header_token = $request->get_header( 'x-kh-service-token' );
            if ( ! $header_token ) {
                $header_token = $request->get_header( 'x-service-token' );
            }
            if ( $header_token && hash_equals( $service_token, $header_token ) ) {
                return true;
            }
        }

        return new WP_Error( 'kh_smma_forbidden', __( 'Invalid permissions.', 'kh-smma' ), array( 'status' => 403 ) );
    }

    public function handle_generate( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        if ( empty( $payload['post_id'] ) ) {
            return new WP_Error( 'kh_smma_missing_post', __( 'post_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }
        if ( empty( $payload['blocks_summary'] ) ) {
            return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'blocks_summary is required.', array( 'status' => 400 ) );
        }

        $phase_context = $payload['phase_context'] ?? array();
        if ( empty( $phase_context ) ) {
            $phase_context = $this->resolve_phase_context( $payload );
        }

        $phase_tag = $payload['phase_tag'] ?? '';
        if ( empty( $phase_tag ) && ! empty( $phase_context['assigned_phase'] ) ) {
            $phase_tag = $phase_context['assigned_phase'];
        }
        if ( empty( $phase_tag ) ) {
            $phase_tag = 'Attention';
        }

        // Log generation request
        $prompt_hash = hash( 'sha256', wp_json_encode( array(
            'post_id'   => (int) $payload['post_id'],
            'phase_tag' => $phase_tag,
            'tone'      => $payload['tone'] ?? 'Authority',
            'blocks_summary' => (string) ( $payload['blocks_summary'] ?? '' ),
            'geo_targets' => $payload['geo_targets'] ?? array(),
        ) ) );
        $request_id = sanitize_text_field( $request->get_header( 'X-Request-Id' ) ?: ( 'req_' . wp_generate_uuid4() ) );

        // Initialise trace context — use caller-supplied X-Trace-Id when present,
        // otherwise use the request_id as the correlation anchor.
        $caller_trace = sanitize_text_field( (string) $request->get_header( 'X-Trace-Id' ) );
        TraceContext::init( '' !== $caller_trace ? $caller_trace : $request_id );

        $start = microtime( true );

        $this->logger->log_generate_request(
            (int) $payload['post_id'],
            get_current_user_id(),
            $prompt_hash,
            array(
                'request_id'          => $request_id,
                'num_variants'        => $payload['num_variants'] ?? 1,
                'tone'                => $payload['tone'] ?? 'Authority',
                'geo_targets'         => $payload['geo_targets'] ?? array(),
                'generate_google_ads' => $payload['generate_google_ads'] ?? true,
            )
        );

        $result = $this->generator->generate( array(
            'post_id'             => (int) $payload['post_id'],
            'blocks_json'         => $payload['blocks_json'] ?? array(),
            'phase_tag'           => $phase_tag,
            'num_variants'        => $payload['num_variants'] ?? 1,
            'series'              => (bool) ( $payload['series'] ?? false ),
            'tone'                => $payload['tone'] ?? 'Authority',
            'geo_targets'         => $payload['geo_targets'] ?? array(),
            'sponsor_context'     => $payload['sponsor_context'] ?? array(),
            'user_controls'       => $payload['user_controls'] ?? array(),
            'keywords'            => $payload['keywords'] ?? array(),
            'intent_scores'       => $payload['intent_scores'] ?? array(),
            'audience_presets'    => $payload['audience_presets'] ?? array(),
            'phase_context'       => $phase_context,
            'user_id'             => (int) ( $payload['user_id'] ?? get_current_user_id() ),
            'generate_google_ads' => $payload['generate_google_ads'] ?? true,
            'num_ad_groups'       => $payload['num_ad_groups'] ?? 2,
            'title'               => $payload['title'] ?? '',
            'canonical_url'       => $payload['canonical_url'] ?? '',
            'blocks_summary'      => $payload['blocks_summary'] ?? '',
            'strict_llm_json'     => true,
        ) );
        if ( is_wp_error( $result ) ) {
            $error_code = method_exists( $result, 'get_error_code' ) ? $result->get_error_code() : 'SMMA_ERR_INVALID_LLM';
            $error_message = method_exists( $result, 'get_error_message' ) ? $result->get_error_message() : 'Invalid LLM output.';
            $this->logger->log( 'smma_generate_failed', array(
                'object_type' => 'post',
                'object_id' => (int) $payload['post_id'],
                'details' => array(
                    'request_id' => $request_id,
                    'error_code' => $error_code,
                    'error_message' => $error_message,
                ),
                'user_id' => get_current_user_id(),
            ) );

            return new WP_Error( 'SMMA_ERR_INVALID_LLM', 'LLM returned invalid JSON.', array( 'status' => 400 ) );
        }

        $llm_content_variants = array();
        foreach ( $result['linkedin_variants'] ?? array() as $raw_variant ) {
            $hints = $this->normalize_asset_hints( $raw_variant['asset_hints'] ?? array() );
            foreach ( $hints as $hint_index => $hint ) {
                if ( empty( $hint['type'] ) ) {
                    $hints[ $hint_index ]['type'] = 'image';
                }
                if ( empty( $hint['description'] ) ) {
                    $hints[ $hint_index ]['description'] = (string) ( $hint['alt_text'] ?? 'Suggested creative asset' );
                }
            }
            $llm_content_variants[] = array(
                'variant_id' => $raw_variant['variant_id'] ?? '',
                'text' => $raw_variant['text'] ?? '',
                'rationale' => $raw_variant['rationale'] ?? $raw_variant['explainability'] ?? '',
                'asset_hints' => $hints,
                'platform' => $raw_variant['platform'] ?? 'linkedin',
                'compliance_notes' => $raw_variant['compliance_notes'] ?? 'OK',
            );
        }

        $parsed_generated = $this->variant_generator->generate_from_response(
            array(
                'choices' => array(
                    array(
                        'message' => array(
                            'content' => wp_json_encode(
                                array(
                                    'variants' => $llm_content_variants,
                                    'google_ad_draft' => $result['google_ad_draft'] ?? array(),
                                )
                            ),
                        ),
                    ),
                ),
            ),
            $request_id,
            'linkedin'
        );
        if ( is_wp_error( $parsed_generated ) ) {
            return new WP_Error(
                'SMMA_ERR_INVALID_LLM',
                'Invalid generator response',
                array_merge(
                    array( 'status' => 400 ),
                    (array) ( method_exists( $parsed_generated, 'get_error_data' ) ? $parsed_generated->get_error_data() : array() )
                )
            );
        }

        $this->logger->log( 'smma_generate', array(
            'object_type' => 'post',
            'object_id' => (int) $payload['post_id'],
            'details' => array(
                'variants' => count( $parsed_generated['variants'] ),
                'phase_tag' => $phase_tag,
                'tone' => $payload['tone'] ?? 'Authority',
            ),
            'user_id' => get_current_user_id(),
        ) );

        ScheduleQueueProcessor::log_telemetry( (int) $payload['post_id'], array(
            'mode' => 'generate',
            'provider' => 'smma',
            'request' => array(
                'post_id' => (int) $payload['post_id'],
                'variants' => $payload['num_variants'] ?? 1,
                'phase_tag' => $phase_tag,
            ),
        ) );

        // Validate Google Ads draft if present
        $google_ad_compliance = array();
        if ( ! empty( $result['google_ad_draft'] ) && ! empty( $result['google_ad_draft']['ad_groups'] ) ) {
            $google_ad_compliance = $this->compliance_validator->validate_google_ad_draft(
                $result['google_ad_draft'],
                array(
                    'sponsor_id'      => $payload['sponsor_context']['sponsor_id'] ?? null,
                    'allowed_claims'  => $payload['sponsor_context']['allowed_claims'] ?? array(),
                )
            );
        }

        // Log generation response
        $variant_ids = array();
        if ( ! empty( $result['linkedin_variants'] ) ) {
            foreach ( $result['linkedin_variants'] as $variant ) {
                if ( ! empty( $variant['variant_id'] ) ) {
                    $variant_ids[] = $variant['variant_id'];
                }
            }
        }

        $response_hash = hash( 'sha256', wp_json_encode( $result ) );
        $this->logger->log_generate_response(
            $response_hash,
            $variant_ids,
            array(
                'request_id'        => $request_id,
                'model'            => $result['model'] ?? 'unknown',
                'google_ad_draft'  => ! empty( $result['google_ad_draft'] ),
            )
        );
        $variants = array();
        foreach ( $parsed_generated['variants'] as $variant ) {
            $variant_id = (string) ( $variant['variant_id'] ?? ( 'var_' . wp_generate_uuid4() ) );
            $compliance_meta = $this->compliance_service->evaluate_variant( $variant_id, (string) ( $variant['text'] ?? '' ) );
            $linked_in = array(
                'variant_id' => $variant_id,
                'text' => (string) ( $variant['text'] ?? '' ),
                'rationale' => (string) ( $variant['rationale'] ?? 'Generated from source post.' ),
                'asset_hints' => $this->normalize_asset_hints( $variant['asset_hints'] ?? array() ),
                'platform' => (string) ( $variant['platform'] ?? 'linkedin' ),
                'compliance_status' => (string) ( $compliance_meta['compliance_status'] ?? 'OK' ),
                'compliance_reason' => (string) ( $compliance_meta['compliance_reason'] ?? '' ),
                'matched_rules' => $compliance_meta['matched_rules'] ?? array(),
                'ai_review_summary' => (string) ( $compliance_meta['ai_review_summary'] ?? '' ),
                'checked_at' => (string) ( $compliance_meta['checked_at'] ?? gmdate( 'c' ) ),
                'compliance' => array(
                    'status' => (string) ( $compliance_meta['compliance_status'] ?? 'OK' ),
                    'reasons' => '' === (string) ( $compliance_meta['compliance_reason'] ?? '' ) ? array() : array( (string) ( $compliance_meta['compliance_reason'] ?? '' ) ),
                ),
            );
            $variant_id = $this->card1_store->upsert_variant( $request_id, $linked_in, $parsed_generated['google_ad_draft'] ?? array() );
            $linked_in['variant_id'] = $variant_id;
            $this->emit_telemetry( 'compliance.check', array(
                'variant_id'        => $variant_id,
                'outcome'           => $linked_in['compliance_status'],
                'rules_matched'     => $linked_in['matched_rules'],
                'ai_review_summary' => $linked_in['ai_review_summary'] ?? '',
                'service'           => 'smma',
            ) );
            $variants[] = array(
                'variant_id' => $variant_id,
                'linkedIn' => $linked_in,
                'google' => $parsed_generated['google_ad_draft'] ?? array(),
            );
        }

        $this->card1_store->create_generate_request( array(
            'request_id' => $request_id,
            'post_id' => (string) $payload['post_id'],
            'user_id' => get_current_user_id(),
            'prompt_hash' => $prompt_hash,
            'model' => $result['model'] ?? 'unknown',
            'status' => 'success',
            'created_at' => gmdate( 'c' ),
        ) );

        $latency_ms = (int) round( ( microtime( true ) - $start ) * 1000 );
        $this->emit_telemetry( 'generate.request', array(
            'session_id'              => $request_id,
            'prompt_hash'             => $prompt_hash,
            'variant_count_requested' => (int) ( $payload['num_variants'] ?? 1 ),
            'service'                 => 'smma',
        ) );
        $this->emit_telemetry( 'generate.response', array(
            'session_id'             => $request_id,
            'variant_count_generated' => count( $variants ),
            'latency_ms'             => $latency_ms,
            'service'                => 'smma',
        ) );

        $response = rest_ensure_response( array(
            'request_id' => $request_id,
            'variants' => $variants,
            'provenance' => array(
                'prompt_hash' => $prompt_hash,
                'fixture' => sanitize_text_field( (string) getenv( 'KH_SMMA_GOLDEN_FIXTURE' ) ),
                'model' => $result['model'] ?? 'unknown',
            ),
            'google_ad_compliance' => $google_ad_compliance,
        ) );
        if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
            $response->header( 'X-Request-Id', $request_id );
        }
        return $response;
    }

    public function handle_record_event( WP_REST_Request $request ) {
        if ( ! $this->phase_engine ) {
            return new WP_Error( 'kh_smma_phase_engine_missing', __( 'Phase Engine unavailable.', 'kh-smma' ), array( 'status' => 500 ) );
        }

        $payload  = $request->get_json_params();
        $event_id = sanitize_text_field( $payload['event_id'] ?? '' );
        $metadata = is_array( $payload['metadata'] ?? null ) ? $payload['metadata'] : array();

        if ( empty( $event_id ) ) {
            return new WP_Error( 'kh_smma_missing_event', __( 'event_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $user_id = absint( $payload['user_id'] ?? 0 );
        }

        if ( ! $user_id ) {
            return new WP_Error( 'kh_smma_missing_user', __( 'user_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $result = $this->phase_engine->record_event( (int) $user_id, $event_id, 'smma_rest', (array) $metadata );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'success' => true,
            'event_id' => $event_id,
            'user_id' => (int) $user_id,
        ) );
    }

    private function resolve_phase_context( array $payload ): array {
        if ( ! $this->phase_engine ) {
            return array();
        }

        $user_id = (int) ( $payload['user_id'] ?? get_current_user_id() );
        if ( ! $user_id ) {
            return array();
        }

        $context = $this->phase_engine->get_user_phase( $user_id );
        return is_array( $context ) ? $context : array();
    }

    public function handle_schedule( WP_REST_Request $request ) {
        $caller_trace = sanitize_text_field( (string) $request->get_header( 'X-Trace-Id' ) );
        TraceContext::init( '' !== $caller_trace ? $caller_trace : null );

        $payload = $request->get_json_params();
        $idempotency_key = sanitize_text_field( $request->get_header( 'Idempotency-Key' ) );
        if ( '' === $idempotency_key ) {
            return new WP_Error( 'SMMA_ERR_IDEMPOTENCY_CONFLICT', 'Idempotency-Key header is required.', array( 'status' => 409 ) );
        }

        $variant_id = sanitize_text_field( (string) ( $payload['variant_id'] ?? '' ) );
        if ( '' === $variant_id ) {
            return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'variant_id is required.', array( 'status' => 400 ) );
        }
        if ( ! current_user_can( 'kh_smma_schedule_posts' ) && ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'SMMA_ERR_FORBIDDEN', 'Insufficient permissions for schedule creation.', array( 'status' => 403 ) );
        }

        $variant = $this->card1_store->get_variant( $variant_id );
        if ( empty( $variant ) ) {
            return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'variant_id does not exist.', array( 'status' => 400 ) );
        }
        $sponsor_id = (string) ( $payload['sponsor_id'] ?? '' );
        if ( '' === $sponsor_id ) {
            return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'sponsor_id is required.', array( 'status' => 400 ) );
        }
        if ( function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $sponsor_meta = kh_ad_manager_get_sponsor_meta( (int) $sponsor_id );
            if ( empty( $sponsor_meta ) ) {
                return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'Unknown sponsor_id.', array( 'status' => 400 ) );
            }
        }

        $gate = $this->schedule_controller->enforce_compliance_gate( $request );
        if ( is_wp_error( $gate ) ) {
            $this->emit_telemetry( 'compliance.check', array(
                'trace_id' => TraceContext::require_current(),
                'variant_id' => $variant_id,
                'schedule_attempt' => true,
                'compliance_status' => 'FAIL',
                'matched_rules' => (array) ( $variant['linkedIn']['matched_rules'] ?? array() ),
            ) );
            $this->emit_telemetry( 'schedule.blocked', array(
                'trace_id' => TraceContext::require_current(),
                'variant_id' => $variant_id,
                'reason' => 'compliance_fail',
            ) );
            return $gate;
        }

        $approval_required = ! empty( $gate['approval_required'] );
        $approval_status = (string) ( $gate['approval_status'] ?? 'approved' );
        $compliance_status = (string) ( $gate['compliance_status'] ?? 'OK' );
        $compliance_reason = (string) ( $gate['compliance_reason'] ?? '' );
        $matched_rules = (array) ( $gate['matched_rules'] ?? array() );
        $status = $approval_required ? 'pending_approval' : 'queued';

        $this->emit_telemetry( 'compliance.check', array(
            'trace_id' => TraceContext::require_current(),
            'variant_id' => $variant_id,
            'schedule_attempt' => true,
            'compliance_status' => $compliance_status,
            'matched_rules' => $matched_rules,
        ) );

        $manifest = array(
            'manifest_id' => 'man_' . gmdate( 'Ymd' ) . '_' . wp_generate_uuid4(),
            'campaign' => array(
                'campaign_id' => 'camp_' . $variant_id,
                'title' => 'SMMA Schedule ' . $variant_id,
            ),
            'operations' => array(
                array(
                    'operation_id' => 'op_' . wp_generate_uuid4(),
                    'type' => 'CREATE_AD',
                    'channel' => sanitize_text_field( $payload['boost_options']['channels'][0] ?? 'linkedin' ),
                    'creative' => array(
                        'headline' => mb_substr( (string) ( $variant['linkedIn']['text'] ?? 'Boost campaign' ), 0, 30 ),
                        'body' => (string) ( $variant['linkedIn']['text'] ?? '' ),
                    ),
                    'bid' => array(
                        'type' => 'CPM',
                        'amount' => (float) ( (int) ( $payload['boost_options']['budget_cents'] ?? 0 ) / 100 ),
                        'currency' => strtoupper( (string) ( $payload['boost_options']['currency'] ?? 'AUD' ) ),
                    ),
                    'start_time' => (string) ( $payload['schedule_time'] ?? gmdate( 'c' ) ),
                    'end_time' => (string) ( $payload['schedule_time'] ?? gmdate( 'c' ) ),
                ),
            ),
            'meta' => array(
                'sponsor_id' => (string) ( $payload['sponsor_id'] ?? '' ),
                'schedule_id' => '',
                'idempotency_key' => $idempotency_key,
            ),
        );

        $row = $this->card1_store->create_schedule(
            $idempotency_key,
            get_current_user_id(),
            array(
                'variant_id' => $variant_id,
                'sponsor_id' => $sponsor_id,
                'schedule_time' => (string) ( $payload['schedule_time'] ?? '' ),
                'boost_options' => $payload['boost_options'] ?? array(),
                'status' => $status,
                'approval_required' => $approval_required,
                'approval_status' => $approval_status,
                'compliance_status' => $compliance_status,
                'compliance_reason' => $compliance_reason,
                'mode' => sanitize_text_field( (string) ( $payload['mode'] ?? 'sandbox' ) ),
                'manifest' => $manifest,
            )
        );
        $schedule = $row['schedule'] ?? array();
        if ( empty( $schedule ) ) {
            return new WP_Error( 'SMMA_ERR_INTERNAL', 'Failed to create schedule.', array( 'status' => 500 ) );
        }

        $manifest['meta']['schedule_id'] = $schedule['schedule_id'];
        $this->logger->log( 'smma_schedule_create', array(
            'object_type' => 'schedule',
            'object_id' => 0,
            'details' => array(
                'schedule_id' => $schedule['schedule_id'],
                'variant_id' => $variant_id,
                'sponsor_id' => (string) ( $payload['sponsor_id'] ?? '' ),
                'status' => $schedule['status'],
                'idempotent' => (bool) ( $row['idempotent'] ?? false ),
            ),
            'user_id' => get_current_user_id(),
        ) );
        $this->emit_telemetry( 'schedule.create', array(
            'schedule_id'      => $schedule['schedule_id'],
            'sponsor_id'       => (string) ( $payload['sponsor_id'] ?? '' ),
            'variant_id'       => $variant_id,
            'approval_required' => $approval_required,
            'approval_status'  => $approval_status,
            'compliance_status' => $compliance_status,
            'service'          => 'smma',
        ) );

        return rest_ensure_response( array(
            'schedule_id' => $schedule['schedule_id'],
            'status' => $schedule['status'],
            'approval_required' => (bool) ( $schedule['approval_required'] ?? $approval_required ),
            'approval_status' => (string) ( $schedule['approval_status'] ?? $approval_status ),
            'compliance_status' => (string) ( $schedule['compliance_status'] ?? $compliance_status ),
            'compliance_reason' => (string) ( $schedule['compliance_reason'] ?? $compliance_reason ),
            'enqueued' => true,
            'manifest' => $manifest,
            'idempotent' => (bool) ( $row['idempotent'] ?? false ),
        ) );
    }

    public function handle_boost_prepare( WP_REST_Request $request ) {
        $caller_trace = sanitize_text_field( (string) $request->get_header( 'X-Trace-Id' ) );
        TraceContext::init( '' !== $caller_trace ? $caller_trace : null );

        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );

        if ( ! $schedule_id ) {
            return new WP_Error( 'kh_smma_missing_schedule', __( 'schedule_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $approval_required = (bool) get_post_meta( $schedule_id, '_kh_smma_approval_required', true );
        $approval_status = strtolower( (string) get_post_meta( $schedule_id, '_kh_smma_approval_status', true ) );
        if ( '' === $approval_status ) {
            $approval_status = 'pending';
        }
        if ( 'auto_approved' === $approval_status ) {
            $approval_status = 'approved';
        }
        if ( 'denied' === $approval_status ) {
            $approval_status = 'rejected';
        }

        if ( 'rejected' === $approval_status || ( $approval_required && 'approved' !== $approval_status ) ) {
            $this->emit_telemetry( 'schedule.blocked', array(
                'trace_id' => TraceContext::require_current(),
                'schedule_id' => (string) $schedule_id,
                'reason' => 'approval_required',
                'approval_status' => $approval_status,
                'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            ) );
            return rest_ensure_response( array(
                'status' => 'blocked',
                'reason' => 'approval_required',
                'approval_status' => $approval_status,
            ) );
        }

        $schedule_payload = get_post_meta( $schedule_id, '_kh_smma_payload', true );
        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'account_id' => 0,
            'payload' => $schedule_payload,
            'generated' => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'awaiting_manual_export' );

        return rest_ensure_response( array(
            'status' => 'awaiting_manual_export',
            'bundle' => $export_bundle,
        ) );
    }

    public function handle_approve( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );
        $approver_id = (int) ( $payload['approver_id'] ?? get_current_user_id() );
        $notes = sanitize_textarea_field( $payload['notes'] ?? '' );

        if ( ! $schedule_id ) {
            return new WP_Error( 'kh_smma_missing_schedule', __( 'schedule_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        // Authorization: Check capability for sponsor approvals
        if ( ! current_user_can( 'approve_sponsor_posts' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'kh_smma_insufficient_permissions',
                __( 'You do not have permission to approve sponsored content.', 'kh-smma' ),
                array( 'status' => 403 )
            );
        }

        // Idempotency: Check if already approved
        $current_status = get_post_meta( $schedule_id, '_kh_smma_approval_status', true );
        if ( 'approved' === $current_status ) {
            $approved_by = get_post_meta( $schedule_id, '_kh_smma_approved_by', true );
            $approved_at = get_post_meta( $schedule_id, '_kh_smma_approved_at', true );

            return rest_ensure_response( array(
                'status' => 'approved',
                'message' => __( 'This schedule was already approved.', 'kh-smma' ),
                'approved_by' => $approved_by,
                'approved_at' => $approved_at,
                'idempotent' => true,
            ) );
        }

        // Update approval status
        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'approved' );
        update_post_meta( $schedule_id, '_kh_smma_approved_by', $approver_id );
        update_post_meta( $schedule_id, '_kh_smma_approved_at', time() );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'pending' );
        if ( $notes ) {
            update_post_meta( $schedule_id, '_kh_smma_approval_notes', $notes );
        }

        // Audit logging
        $this->logger->log( 'smma_schedule_approve', array(
            'object_type' => 'schedule',
            'object_id' => $schedule_id,
            'details' => array(
                'approver_id' => $approver_id,
                'notes' => $notes,
            ),
            'user_id' => get_current_user_id(),
        ) );

        // Telemetry
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'approve',
            'provider' => 'smma',
            'approver_id' => $approver_id,
            'notes' => $notes,
        ) );

        return rest_ensure_response( array(
            'status' => 'approved',
            'schedule_id' => $schedule_id,
            'approved_by' => $approver_id,
            'approved_at' => time(),
        ) );
    }

    public function handle_get_sponsor( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        if ( ! $id ) {
            return new WP_Error( 'kh_smma_missing_sponsor', __( 'Sponsor ID is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        if ( ! function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            return new WP_Error( 'kh_smma_sponsor_missing', __( 'Sponsorship Manager not available.', 'kh-smma' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( kh_ad_manager_get_sponsor_meta( $id ) );
    }

    public function handle_seo_table( WP_REST_Request $request ) {
        $posts = get_posts( array(
            'post_type' => array( 'post', 'page' ),
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        $rows = array();
        foreach ( $posts as $post ) {
            $rows[] = array(
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'seo_score' => (int) get_post_meta( $post->ID, '_khm_seo_score', true ),
                'geo_score' => (int) get_post_meta( $post->ID, '_khm_geo_score', true ),
                'sponsor' => '',
                'promote' => true,
                'boost' => true,
            );
        }

        return rest_ensure_response( array( 'rows' => $rows ) );
    }

    public function handle_variant_edit( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );
        $updated_text = $payload['updated_text'] ?? '';

        if ( ! $schedule_id || '' === $updated_text ) {
            return new WP_Error( 'kh_smma_invalid_edit', __( 'schedule_id and updated_text are required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $schedule_payload = get_post_meta( $schedule_id, '_kh_smma_payload', true );
        if ( empty( $schedule_payload ) ) {
            return new WP_Error( 'kh_smma_schedule_not_found', __( 'Schedule not found.', 'kh-smma' ), array( 'status' => 404 ) );
        }

        // Store original text for diff calculation
        $original_text = $schedule_payload['text'] ?? '';

        $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
        $sponsor_context = array(
            'sponsor_id' => $sponsor_id,
            'sponsor_policy' => get_post_meta( $schedule_id, '_kh_smma_sponsor_mode', true ),
            'sponsor_assets' => get_post_meta( $schedule_id, '_kh_smma_sponsor_assets', true ),
            'channel' => $schedule_payload['channel'] ?? 'linkedin',
            'phase_tag' => $schedule_payload['phase_tag'] ?? 'Attention',
        );

        // Get allowed claims for sponsor if applicable
        if ( $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $sponsor_meta = kh_ad_manager_get_sponsor_meta( $sponsor_id );
            $sponsor_context['allowed_claims'] = $sponsor_meta['allowed_claims'] ?? array();
        }

        $compliance_check = $this->compliance_validator->validate( $updated_text, $sponsor_context );
        if ( ! $compliance_check['passed'] ) {
            return new WP_Error( 'kh_smma_compliance_failed', $compliance_check['message'], array( 'status' => 422 ) );
        }

        // Calculate unified diff
        $unified_diff = $this->calculate_unified_diff( $original_text, $updated_text );

        $schedule_payload['text'] = sanitize_textarea_field( $updated_text );
        $schedule_payload['edited_at'] = time();
        $schedule_payload['edited_by'] = get_current_user_id();

        update_post_meta( $schedule_id, '_kh_smma_payload', $schedule_payload );
        update_post_meta( $schedule_id, '_kh_smma_compliance_notes', $compliance_check['notes'] );

        // Store preview changes with full metadata
        $preview_changes = array(
            'variant_id' => $schedule_payload['variant_id'] ?? '',
            'editor_id' => get_current_user_id(),
            'full_text' => $updated_text,
            'unified_diff' => $unified_diff,
            'timestamp' => time(),
            'compliance_result' => $compliance_check,
        );
        update_post_meta( $schedule_id, '_kh_smma_preview_changes', $preview_changes );

        $this->logger->log( 'smma_variant_edit', array(
            'object_type' => 'schedule',
            'object_id' => $schedule_id,
            'details' => array(
                'compliance_passed' => $compliance_check['passed'],
                'edited_by' => get_current_user_id(),
                'diff_size' => strlen( $unified_diff ),
            ),
            'user_id' => get_current_user_id(),
        ) );

        // Enhanced telemetry with diff
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'variant_edit',
            'provider' => 'smma',
            'editor_id' => get_current_user_id(),
            'diff' => $unified_diff,
            'compliance_result' => $compliance_check,
        ) );

        return rest_ensure_response( array(
            'status' => 'updated',
            'compliance' => $compliance_check,
        ) );
    }

    public function handle_variant_edit_v2( WP_REST_Request $request ) {
        $caller_trace = sanitize_text_field( (string) $request->get_header( 'X-Trace-Id' ) );
        TraceContext::init( '' !== $caller_trace ? $caller_trace : null );

        $variant_id = sanitize_text_field( (string) $request->get_param( 'variant_id' ) );
        $payload = $request->get_json_params();
        $idempotency_key = sanitize_text_field( $request->get_header( 'Idempotency-Key' ) );

        if ( '' === $idempotency_key ) {
            return new WP_Error( 'SMMA_ERR_IDEMPOTENCY_CONFLICT', 'Idempotency-Key header is required.', array( 'status' => 409 ) );
        }
        if ( '' === $variant_id ) {
            return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'variant_id is required.', array( 'status' => 400 ) );
        }

        $variant = $this->card1_store->get_variant( $variant_id );
        if ( empty( $variant ) ) {
            return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'Variant not found.', array( 'status' => 400 ) );
        }

        $text = (string) ( $payload['text'] ?? ( $variant['linkedIn']['text'] ?? '' ) );
        $compliance_meta = $this->compliance_service->evaluate_variant( $variant_id, $text );
        $status = (string) ( $compliance_meta['compliance_status'] ?? 'WARN' );
        $reasons = (array) ( $compliance_meta['matched_rules'] ?? array() );
        if ( '' !== (string) ( $compliance_meta['compliance_reason'] ?? '' ) ) {
            $reasons[] = (string) $compliance_meta['compliance_reason'];
        }

        $compliance = array(
            'status' => $status,
            'reasons' => array_values( array_filter( $reasons ) ),
        );

        $edit_result = $this->card1_store->apply_variant_edit(
            $variant_id,
            $idempotency_key,
            array(
                'editor_user_id' => (string) ( $payload['editor_user_id'] ?? get_current_user_id() ),
                'text' => $text,
                'asset_hints' => $payload['asset_hints'] ?? array(),
                'metadata' => $payload['metadata'] ?? array(),
                'edit_reason' => (string) ( $payload['edit_reason'] ?? '' ),
            ),
            $compliance
        );
        if ( empty( $edit_result ) ) {
            return new WP_Error( 'SMMA_ERR_INTERNAL', 'Failed to persist variant revision.', array( 'status' => 500 ) );
        }

        $revision = $edit_result['revision'] ?? array();
        $this->logger->log( 'smma_variant_edit', array(
            'object_type' => 'variant',
            'object_id' => 0,
            'details' => array(
                'variant_id' => $variant_id,
                'revision_id' => $revision['revision_id'] ?? '',
                'status' => $status,
                'idempotent' => (bool) ( $edit_result['idempotent'] ?? false ),
            ),
            'user_id' => get_current_user_id(),
        ) );
        $this->emit_telemetry( 'variant.edit', array(
            'variant_id'  => $variant_id,
            'editor_id'   => (string) ( $payload['editor_user_id'] ?? get_current_user_id() ),
            'revision_id' => $revision['revision_id'] ?? '',
            'deltas'      => array( 'edit_reason' => (string) ( $payload['edit_reason'] ?? '' ) ),
            'service'     => 'smma',
        ) );
        $this->emit_telemetry( 'compliance.check', array(
            'variant_id'        => $variant_id,
            'outcome'           => $status,
            'rules_matched'     => $compliance['reasons'],
            'ai_review_summary' => (string) ( $compliance_meta['ai_review_summary'] ?? '' ),
            'service'           => 'smma',
        ) );
        $this->logger->log( 'smma_compliance_check', array(
            'object_type' => 'variant',
            'object_id' => 0,
            'details' => array(
                'variant_id' => $variant_id,
                'revision_id' => $revision['revision_id'] ?? '',
                'status' => $status,
                'reasons' => $compliance['reasons'],
            ),
            'user_id' => get_current_user_id(),
        ) );

        if ( 'FAIL' === $status ) {
            return new WP_Error( 'SMMA_ERR_COMPLIANCE_FAIL', 'Compliance FAIL blocks this variant.', array(
                'status' => 409,
                'reasons' => $compliance['reasons'],
                'revision_id' => $revision['revision_id'] ?? '',
            ) );
        }

        $approval_status = 'OK' === $status ? 'approved' : 'pending_approval';
        $revision_payload = array(
            'variant_id' => $variant_id,
            'revision_id' => $revision['revision_id'] ?? '',
            'editor_user_id' => (string) ( $payload['editor_user_id'] ?? get_current_user_id() ),
            'edited_at' => $revision['created_at'] ?? gmdate( 'c' ),
            'previous_text' => (string) ( $revision['diff']['previous_text'] ?? '' ),
            'updated_text' => (string) ( $revision['diff']['updated_text'] ?? $text ),
            'edit_reason' => (string) ( $revision['diff']['edit_reason'] ?? ( $payload['edit_reason'] ?? '' ) ),
        );
        return rest_ensure_response( array(
            'variant_id' => $variant_id,
            'revision_id' => $revision['revision_id'] ?? '',
            'revision' => $revision_payload,
            'approval_status' => $approval_status,
            'compliance' => $compliance,
            'idempotent' => (bool) ( $edit_result['idempotent'] ?? false ),
        ) );
    }

    public function handle_reject( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );
        $reason = sanitize_textarea_field( $payload['reason'] ?? '' );

        if ( ! $schedule_id ) {
            return new WP_Error( 'kh_smma_missing_schedule', __( 'schedule_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        // Authorization: Check capability for sponsor approvals/rejections
        if ( ! current_user_can( 'approve_sponsor_posts' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'kh_smma_insufficient_permissions',
                __( 'You do not have permission to reject sponsored content.', 'kh-smma' ),
                array( 'status' => 403 )
            );
        }

        // Idempotency: Check if already rejected
        $current_status = get_post_meta( $schedule_id, '_kh_smma_approval_status', true );
        if ( 'rejected' === $current_status ) {
            $rejected_by = get_post_meta( $schedule_id, '_kh_smma_rejected_by', true );
            $rejected_at = get_post_meta( $schedule_id, '_kh_smma_rejected_at', true );
            $existing_reason = get_post_meta( $schedule_id, '_kh_smma_rejection_reason', true );

            return rest_ensure_response( array(
                'status' => 'rejected',
                'message' => __( 'This schedule was already rejected.', 'kh-smma' ),
                'rejected_by' => $rejected_by,
                'rejected_at' => $rejected_at,
                'reason' => $existing_reason,
                'idempotent' => true,
            ) );
        }

        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'rejected' );
        update_post_meta( $schedule_id, '_kh_smma_rejected_by', get_current_user_id() );
        update_post_meta( $schedule_id, '_kh_smma_rejected_at', time() );
        update_post_meta( $schedule_id, '_kh_smma_rejection_reason', $reason );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'rejected' );

        $this->logger->log( 'smma_schedule_reject', array(
            'object_type' => 'schedule',
            'object_id' => $schedule_id,
            'details' => array(
                'reason' => $reason,
                'rejected_by' => get_current_user_id(),
            ),
            'user_id' => get_current_user_id(),
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'reject',
            'provider' => 'smma',
            'rejected_by' => get_current_user_id(),
            'rejection_reason' => $reason,
        ) );

        return rest_ensure_response( array(
            'status' => 'rejected',
            'schedule_id' => $schedule_id,
            'rejected_by' => get_current_user_id(),
            'rejected_at' => time(),
        ) );
    }

    /**
     * Handle compliance check request.
     *
     * POST /wp-json/kh-smma/v1/compliance/check
     *
     * Request body:
     * {
     *   "variant_id": "v-123",
     *   "text": "variant text to check",
     *   "channel": "linkedin",
     *   "sponsor_context": {
     *     "sponsor_id": 123,
     *     "allowed_claims": ["claim1", "claim2"]
     *   },
     *   "metadata": {...}
     * }
     *
     * Response:
     * {
     *   "pass": true|false,
     *   "level": "OK"|"WARN"|"FAIL",
     *   "flags": [...],
     *   "suggested_edits": [...],
     *   "confidence": 0.95
     * }
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function handle_compliance_check( WP_REST_Request $request ) {
        $payload = $request->get_json_params();

        // Required fields
        if ( empty( $payload['text'] ) ) {
            return new WP_Error( 'kh_smma_missing_text', __( 'text is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $text = sanitize_textarea_field( $payload['text'] );
        $variant_id = sanitize_text_field( $payload['variant_id'] ?? '' );
        $channel = sanitize_text_field( $payload['channel'] ?? 'linkedin' );
        $sponsor_context = $payload['sponsor_context'] ?? array();
        $metadata = $payload['metadata'] ?? array();

        // Build validation context
        $context = array(
            'channel' => $channel,
            'phase_tag' => sanitize_text_field( $metadata['phase_tag'] ?? 'Attention' ),
        );

        // Add sponsor context if present
        if ( ! empty( $sponsor_context['sponsor_id'] ) ) {
            $context['sponsor_id'] = (int) $sponsor_context['sponsor_id'];
            $context['sponsor_policy'] = sanitize_text_field( $sponsor_context['sponsor_policy'] ?? '' );
            $context['allowed_claims'] = (array) ( $sponsor_context['allowed_claims'] ?? array() );
        }

        // Perform compliance check
        $result = $this->compliance_validator->validate( $text, $context );

        // Map to API response format (OK|WARN|FAIL)
        $level = 'OK';
        if ( ! $result['passed'] ) {
            // Determine severity from violation type or notes
            $violation_type = $result['violation_type'] ?? '';
            $notes = $result['notes'] ?? '';

            if ( in_array( $violation_type, array( 'blacklist', 'length' ), true ) ||
                 stripos( $notes, 'FAIL' ) !== false ) {
                $level = 'FAIL';
            } else {
                $level = 'WARN';
            }
        }

        // Build suggested edits
        $suggested_edits = array();
        if ( ! empty( $result['message'] ) ) {
            $suggested_edits[] = $result['message'];
        }

        // Extract flags from result
        $flags = $result['flags'] ?? array();
        if ( ! empty( $result['violation_type'] ) && ! in_array( $result['violation_type'], $flags, true ) ) {
            $flags[] = $result['violation_type'];
        }

        // Log compliance check
        $this->logger->log( 'compliance.check', array(
            'object_type' => 'variant',
            'object_id' => $variant_id,
            'details' => array(
                'level' => $level,
                'passed' => $result['passed'],
                'channel' => $channel,
                'sponsor_id' => $context['sponsor_id'] ?? null,
            ),
            'user_id' => get_current_user_id(),
        ) );

        // Telemetry
        ScheduleQueueProcessor::log_telemetry( 0, array(
            'mode' => 'compliance_check',
            'provider' => 'smma',
            'variant_id' => $variant_id,
            'level' => $level,
            'passed' => $result['passed'],
            'latency_ms' => 0, // Could track actual latency if needed
        ) );

        // Return API response
        return rest_ensure_response( array(
            'pass' => $result['passed'],
            'level' => $level,
            'flags' => $flags,
            'suggested_edits' => $suggested_edits,
            'confidence' => (float) ( $result['confidence_score'] ?? 0.9 ),
            'details' => array(
                'message' => $result['message'] ?? '',
                'notes' => $result['notes'] ?? '',
            ),
        ) );
    }

    /**
     * Parse datetime input (ISO 8601 or unix timestamp) to UTC timestamp.
     *
     * @param mixed $input Datetime input (ISO 8601 string or unix timestamp)
     * @return int UTC unix timestamp
     */
    private function parse_datetime_to_utc( $input ): int {
        // If already a unix timestamp, return as-is
        if ( is_int( $input ) || ( is_string( $input ) && ctype_digit( $input ) ) ) {
            return (int) $input;
        }

        // Parse ISO 8601 datetime string
        if ( is_string( $input ) ) {
            try {
                $datetime = new \DateTime( $input );
                // Convert to UTC
                $datetime->setTimezone( new \DateTimeZone( 'UTC' ) );
                return $datetime->getTimestamp();
            } catch ( \Exception $e ) {
                // Fallback to current time on parse error
                return time();
            }
        }

        return time();
    }

    /**
     * Extract timezone from ISO 8601 datetime string.
     *
     * @param mixed $input Datetime input
     * @return string Timezone identifier (e.g., "America/New_York", "-05:00", "UTC")
     */
    private function extract_timezone( $input ): string {
        if ( ! is_string( $input ) ) {
            return 'UTC';
        }

        try {
            $datetime = new \DateTime( $input );
            $timezone = $datetime->getTimezone();
            return $timezone ? $timezone->getName() : 'UTC';
        } catch ( \Exception $e ) {
            return 'UTC';
        }
    }

    /**
     * Calculate unified diff between original and updated text.
     *
     * @param string $original Original text
     * @param string $updated Updated text
     * @return string Unified diff string
     */
    private function calculate_unified_diff( string $original, string $updated ): string {
        if ( $original === $updated ) {
            return '';
        }

        $original_lines = explode( "\n", $original );
        $updated_lines = explode( "\n", $updated );

        $diff = array();
        $diff[] = '--- Original';
        $diff[] = '+++ Updated';
        $diff[] = '@@ -1,' . count( $original_lines ) . ' +1,' . count( $updated_lines ) . ' @@';

        // Simple line-by-line diff
        $max_lines = max( count( $original_lines ), count( $updated_lines ) );
        for ( $i = 0; $i < $max_lines; $i++ ) {
            $orig_line = $original_lines[ $i ] ?? null;
            $upd_line = $updated_lines[ $i ] ?? null;

            if ( $orig_line === null && $upd_line !== null ) {
                $diff[] = '+' . $upd_line;
            } elseif ( $orig_line !== null && $upd_line === null ) {
                $diff[] = '-' . $orig_line;
            } elseif ( $orig_line !== $upd_line ) {
                $diff[] = '-' . $orig_line;
                $diff[] = '+' . $upd_line;
            } else {
                $diff[] = ' ' . $orig_line;
            }
        }

        return implode( "\n", $diff );
    }

    private function normalize_asset_hints( $asset_hints ): array {
        if ( ! is_array( $asset_hints ) ) {
            return array();
        }
        if ( array_keys( $asset_hints ) !== range( 0, count( $asset_hints ) - 1 ) ) {
            return array( $asset_hints );
        }
        return $asset_hints;
    }

    private function emit_telemetry( string $event_name, array $payload ): void {
        if ( null !== $this->emitter ) {
            $this->emitter->emit( $event_name, $payload );
            return;
        }

        // Fallback: legacy behaviour when EventEmitter is not injected.
        do_action( 'kh_smma_telemetry_event', $event_name, $payload );
        ScheduleQueueProcessor::log_telemetry( 0, array(
            'mode'     => $event_name,
            'provider' => 'smma',
            'payload'  => $payload,
        ) );
    }

    /**
     * Handle export request for manual paid campaign export
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_export( WP_REST_Request $request ) {
        $payload = $request->get_json_params();

        if ( empty( $payload['post_id'] ) ) {
            return new WP_Error( 'kh_smma_missing_post', __( 'post_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $post_id              = (int) $payload['post_id'];
        $variant_ids          = $payload['variant_ids'] ?? array();
        $include_google_ads   = $payload['include_google_ads'] ?? true;
        $include_assets       = $payload['include_assets'] ?? true;

        // Create export directory
        $upload_dir = wp_upload_dir();
        $export_dir = trailingslashit( $upload_dir['basedir'] ) . 'smma-exports';
        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }

        $export_id = 'exp_' . $post_id . '_' . time();
        $export_path = trailingslashit( $export_dir ) . $export_id;
        wp_mkdir_p( $export_path );

        // Gather variant data
        $variants = array();
        foreach ( $variant_ids as $variant_id ) {
            $variant = get_transient( 'kh_smma_variant_' . $variant_id );
            if ( $variant ) {
                $variants[] = $variant;
            }
        }

        // Gather Google Ads draft if requested
        $google_ad_draft = array();
        if ( $include_google_ads ) {
            $google_ad_draft = get_transient( 'kh_smma_google_ads_' . $post_id );
        }

        // Build manifest
        $manifest = array(
            'export_id'         => $export_id,
            'post_id'           => $post_id,
            'created_at'        => current_time( 'mysql' ),
            'expires_at'        => gmdate( 'Y-m-d\\TH:i:s\\Z', strtotime( '+7 days' ) ),
            'linkedin_variants' => $variants,
            'google_ad_draft'   => $google_ad_draft,
            'assets'            => array(),
        );

        // Save manifest JSON
        $manifest_file = $export_path . '/manifest.json';
        file_put_contents( $manifest_file, wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

        // Create ZIP archive
        $zip_file = $export_dir . '/' . $export_id . '.zip';
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new \ZipArchive();
            if ( $zip->open( $zip_file, \ZipArchive::CREATE ) === true ) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator( $export_path ),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ( $files as $file ) {
                    if ( ! $file->isDir() ) {
                        $file_path = $file->getRealPath();
                        $relative_path = substr( $file_path, strlen( $export_path ) + 1 );
                        $zip->addFile( $file_path, $relative_path );
                    }
                }
                $zip->close();
            }
        }

        // Generate download URL
        $download_url = trailingslashit( $upload_dir['baseurl'] ) . 'smma-exports/' . $export_id . '.zip';

        // Log export
        $this->logger->log( 'export.create', array(
            'object_type' => 'export',
            'object_id'   => $post_id,
            'details'     => array(
                'export_id'           => $export_id,
                'variant_count'       => count( $variants ),
                'include_google_ads'  => $include_google_ads,
                'include_assets'      => $include_assets,
            ),
        ) );

        return rest_ensure_response( array(
            'export_id'    => $export_id,
            'download_url' => $download_url,
            'manifest'     => $manifest,
            'expires_at'   => gmdate( 'Y-m-d\\TH:i:s\\Z', strtotime( '+7 days' ) ),
        ) );
    }

    public function handle_demo_compose_get( WP_REST_Request $request ) {
        $reference_id = sanitize_text_field( (string) $request->get_param( 'reference_id' ) );
        $post_id      = absint( $request->get_param( 'post_id' ) );
        $saved        = $this->load_demo_compose_mapping( $reference_id, $post_id );

        return rest_ensure_response( array(
            'status'       => 'ok',
            'reference_id' => $reference_id,
            'post_id'      => $post_id,
            'mapping'      => $saved['mapping'],
            'preview_url'  => $saved['preview_url'],
            'saved_at'     => $saved['saved_at'],
        ) );
    }

    public function handle_demo_compose_post( WP_REST_Request $request ) {
        $payload      = $request->get_json_params();
        $reference_id = sanitize_text_field( (string) ( $payload['reference_id'] ?? '' ) );
        $post_id      = absint( $payload['post_id'] ?? 0 );
        $mapping      = is_array( $payload['mapping'] ?? null ) ? $payload['mapping'] : array();
        $layout_id    = sanitize_text_field( (string) ( $payload['layout_id'] ?? '' ) );
        $preview_url  = esc_url_raw( (string) ( $payload['preview_url'] ?? '' ) );

        if ( '' === $reference_id && 0 === $post_id ) {
            return new WP_Error( 'kh_smma_missing_reference', __( 'reference_id or post_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        if ( '' === $layout_id ) {
            return new WP_Error( 'kh_smma_missing_layout', __( 'layout_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $record = array(
            'reference_id' => $reference_id,
            'post_id'      => $post_id,
            'layout_id'    => $layout_id,
            'mapping'      => $mapping,
            'preview_url'  => $preview_url,
            'saved_at'     => current_time( 'mysql' ),
        );

        if ( $post_id > 0 ) {
            update_post_meta( $post_id, '_khm_image_compose', wp_json_encode( $record ) );
        } else {
            update_option( 'kh_smma_image_compose_' . md5( $reference_id ), $record );
        }

        return rest_ensure_response( array(
            'status'            => 'ok',
            'reference_id'      => $reference_id,
            'post_id'           => $post_id,
            'layout_id'         => $layout_id,
            'mapping'           => $mapping,
            'preview_url'       => $preview_url,
            'composed_image_id' => sanitize_text_field( (string) ( $payload['composed_image_id'] ?? '' ) ),
            'saved_at'          => $record['saved_at'],
        ) );
    }

    private function load_demo_compose_mapping( string $reference_id, int $post_id ): array {
        $stored = array();

        if ( $post_id > 0 ) {
            $raw = get_post_meta( $post_id, '_khm_image_compose', true );
            if ( is_string( $raw ) && '' !== $raw ) {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    $stored = $decoded;
                }
            } elseif ( is_array( $raw ) ) {
                $stored = $raw;
            }
        }

        if ( empty( $stored ) && '' !== $reference_id ) {
            $stored = get_option( 'kh_smma_image_compose_' . md5( $reference_id ), array() );
        }

        return array(
            'mapping'     => is_array( $stored['mapping'] ?? null ) ? $stored['mapping'] : array(),
            'preview_url' => sanitize_text_field( (string) ( $stored['preview_url'] ?? '' ) ),
            'saved_at'    => sanitize_text_field( (string) ( $stored['saved_at'] ?? '' ) ),
        );
    }

}

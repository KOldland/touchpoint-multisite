<?php

namespace KH\Editorial\Providers\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SEOToolkit Provider
 * 
 * Handles the persistence of SEO metadata and schema configurations.
 * 
 * @package KH\Editorial\Providers\Tools
 */
class SEOToolkit {

    /**
     * Supported action types.
     * @var array
     */
    private const SUPPORTED_ACTIONS = [
        'set_meta_title',
        'set_meta_description',
        'set_focus_keyword',
        'set_keywords',
        'set_robots',
        'set_canonical',
        'set_schema_config'
    ];

    /**
     * Fixed budget fee for a successful toolkit application.
     * @var int
     */
    private const TOOL_FEE = 100;

    /**
     * AI Storage service.
     * @var \KH\Editorial\Services\AI\AIStorage|null
     */
    private $storage = null;

    /**
     * SEO Agent service (for post-apply re-scoring).
     * @var \KH\Editorial\Services\AI\SEOAgent|null
     */
    private $agent = null;

    /**
     * Setter for storage (Dependency Injection)
     * 
     * @param \KH\Editorial\Services\AI\AIStorage $storage
     * @return self
     */
    public function set_storage( $storage ): self {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Setter for agent (Dependency Injection)
     * 
     * @param \KH\Editorial\Services\AI\SEOAgent $agent
     * @return self
     */
    public function set_agent( $agent ): self {
        $this->agent = $agent;
        return $this;
    }

    /**
     * Apply a set of SEO actions to a post.
     * 
     * @param int    $post_id            The Post ID to modify.
     * @param array  $actions            Array of action objects.
     * @param string $idempotency_key    Key to prevent duplicate execution.
     * @param bool   $allow_schema_write Whether schema changes are authorized.
     * @param int    $user_id            User ID performing the action (for budget/perms).
     * @param string $job_id             Intelligence job ID for audit trail.
     * @return array|\WP_Error Results of the operation or error.
     */
    public function apply_actions( int $post_id, array $actions, string $idempotency_key, bool $allow_schema_write, int $user_id, string $job_id = '' ) {
        // 1. Safety Gates
        if ( ! user_can( $user_id, 'edit_post', $post_id ) ) {
            return new \WP_Error( 'permission_denied', 'User does not have permission to edit this post.', [ 'status' => 403 ] );
        }

        // Budget Guard
        if ( $this->storage ) {
            $budget = $this->storage->check_budget( $user_id );
            if ( ! $budget['has_budget'] ) {
                return new \WP_Error( 'budget_exceeded', 'User AI token budget has been exceeded.', [ 'status' => 402 ] );
            }
        }

        if ( $this->is_duplicate_request( $post_id, $idempotency_key ) ) {
            return new \WP_Error( 'duplicate_request', 'This action has already been applied.', [ 'status' => 409 ] );
        }

        // 2. Execution
        $changes = [];
        $errors = [];
        $rollback_data = [];
        $new_focus_keyword = '';

        foreach ( $actions as $action ) {
            $type = $action['action_type'] ?? '';
            $payload = $action['payload'] ?? [];

            if ( ! in_array( $type, self::SUPPORTED_ACTIONS, true ) ) {
                continue;
            }

            // Specific gate for schema
            if ( 'set_schema_config' === $type && ! $allow_schema_write ) {
                $errors[] = [ 'type' => $type, 'message' => 'Schema changes require explicit authorization.' ];
                continue;
            }

            $result = $this->execute_action( $post_id, $type, $payload );
            
            if ( is_wp_error( $result ) ) {
                $errors[] = [ 'type' => $type, 'message' => $result->get_error_message() ];
                continue;
            }

            if ( ! empty( $result ) ) {
                $changes[] = $result;
                $rollback_data[] = [
                    'action_type' => $type,
                    'payload' => [
                        'value' => $result['old']
                    ]
                ];

                if ( 'set_focus_keyword' === $type ) {
                    $new_focus_keyword = $result['new'];
                }
            }
        }

        // 3. Post-Update Triggers (Re-scoring for parity)
        $audit_result = null;
        if ( ! empty( $changes ) && $this->agent ) {
            $audit_result = $this->agent->audit( $post_id, $new_focus_keyword );
        }

        // 4. Budget & Audit Finalization
        if ( ! empty( $changes ) || ! empty( $errors ) ) {
            update_post_meta( $post_id, '_khm_seo_agent_last_idempotency', $idempotency_key );
            
            if ( $this->storage ) {
                if ( ! empty( $changes ) ) {
                    $this->storage->update_budget_usage( $user_id, self::TOOL_FEE );
                }

                if ( ! empty( $job_id ) ) {
                    $this->storage->log_event( $job_id, 'tool_call', [
                        'tool' => 'SEOToolkit::apply_actions',
                        'post_id' => $post_id,
                        'user_id' => $user_id,
                        'idempotency_key' => $idempotency_key,
                        'changes' => $changes,
                        'errors' => $errors,
                        'rollback' => $rollback_data
                    ]);
                }
            }
        }

        return [
            'success' => ! empty( $changes ),
            'post_id' => $post_id,
            'changes' => $changes,
            'errors'  => $errors,
            'analysis'=> $audit_result['analysis'] ?? null,
            'idempotency_key' => $idempotency_key,
            'rollback_data' => $rollback_data
        ];
    }

    /**
     * Execute a single metadata update action.
     */
    private function execute_action( int $post_id, string $type, array $payload ) {
        $value = $payload['value'] ?? null;
        if ( null === $value ) {
            return new \WP_Error( 'invalid_payload', "Missing value for action: {$type}" );
        }

        $meta_key = $this->map_action_to_meta_key( $type );
        if ( ! $meta_key ) {
            return [];
        }

        $old_value = get_post_meta( $post_id, $meta_key, true );
        $new_value = $this->sanitize_value( $type, $value );

        if ( $old_value === $new_value ) {
            return [];
        }

        update_post_meta( $post_id, $meta_key, $new_value );

        if ( 'set_schema_config' === $type ) {
            $this->refresh_schema_cache( $post_id, $new_value );
        }

        return [
            'action' => $type,
            'meta_key' => $meta_key,
            'old' => $old_value,
            'new' => $new_value
        ];
    }

    /**
     * Check if the request is a duplicate based on idempotency key.
     */
    private function is_duplicate_request( int $post_id, string $key ): bool {
        $last_key = get_post_meta( $post_id, '_khm_seo_agent_last_idempotency', true );
        return ! empty( $key ) && $last_key === $key;
    }

    /**
     * Map action types to legacy meta keys for parity.
     */
    private function map_action_to_meta_key( string $type ): string {
        $map = [
            'set_meta_title'       => '_khm_seo_title',
            'set_meta_description' => '_khm_seo_description',
            'set_focus_keyword'    => '_khm_seo_focus_keyword',
            'set_keywords'         => '_khm_seo_keywords',
            'set_robots'           => '_khm_seo_robots',
            'set_canonical'        => '_khm_seo_canonical',
            'set_schema_config'    => '_khm_seo_schema_config',
        ];

        return $map[$type] ?? '';
    }

    /**
     * Sanitize values based on action type.
     */
    private function sanitize_value( string $type, $value ) {
        switch ( $type ) {
            case 'set_meta_title':
            case 'set_focus_keyword':
            case 'set_keywords':
            case 'set_robots':
                return sanitize_text_field( (string) $value );
            case 'set_canonical':
                return esc_url_raw( (string) $value );
            case 'set_meta_description':
                return sanitize_textarea_field( (string) $value );
            case 'set_schema_config':
                return $this->sanitize_schema_config( $value );
            default:
                return $value;
        }
    }

    /**
     * Deep sanitization for schema configuration objects.
     */
    private function sanitize_schema_config( $config ): array {
        if ( ! is_array( $config ) ) {
            $config = [];
        }

        $sanitized = [
            'enabled'       => ! empty( $config['enabled'] ),
            'type'          => sanitize_key( $config['type'] ?? 'article' ),
            'custom_fields' => [],
            'options'       => [],
        ];

        if ( ! empty( $config['custom_fields'] ) && is_array( $config['custom_fields'] ) ) {
            foreach ( $config['custom_fields'] as $key => $val ) {
                $sanitized['custom_fields'][ sanitize_key( $key ) ] = sanitize_textarea_field( $val );
            }
        }

        if ( ! empty( $config['options'] ) && is_array( $config['options'] ) ) {
            foreach ( $config['options'] as $key => $val ) {
                $sanitized['options'][ sanitize_key( $key ) ] = sanitize_text_field( (string) $val );
            }
        }

        return $sanitized;
    }

    /**
     * Bridge to the legacy KHM SEO SchemaManager for cache refreshing.
     */
    private function refresh_schema_cache( int $post_id, array $config ): void {
        if ( empty( $config['enabled'] ) ) {
            delete_post_meta( $post_id, '_khm_seo_schema_cache' );
            return;
        }

        if ( ! class_exists( '\KHM_SEO\Schema\SchemaManager' ) ) {
            return;
        }

        $manager = new \KHM_SEO\Schema\SchemaManager();
        if ( ! method_exists( $manager, 'generate_post_schema' ) ) {
            return;
        }

        $schema_json = $manager->generate_post_schema( $post_id, $config );

        update_post_meta( $post_id, '_khm_seo_schema_cache', [
            'json_ld'     => $schema_json,
            'generated'   => current_time( 'mysql' ),
            'config_hash' => md5( serialize( $config ) ),
        ] );
    }
}

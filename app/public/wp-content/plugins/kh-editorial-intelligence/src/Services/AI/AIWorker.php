<?php

namespace KH\Editorial\Services\AI;

use KH\Editorial\Core\Container;
use KH\Editorial\Core\LLMService;
use KH\Editorial\Services\AI\SEOAgent;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIWorker
 * 
 * Background processor for AI jobs. Listens for job creation,
 * manages state transitions, and dispatches to specific handlers.
 */
class AIWorker {

    /**
     * @var AIStorage
     */
    private $storage;

    public function __construct() {
        $this->storage = new AIStorage();
    }

    /**
     * Initialize the worker by hooking into job creation.
     */
    public function init() {
        add_action( 'kh_editorial_job_created', [ $this, 'process_job' ], 10, 2 );
        add_filter( 'kh_editorial_execute_job_seo_audit', [ $this, 'handle_seo_audit_job' ], 10, 2 );
        add_filter( 'kh_editorial_execute_job_planner', [ $this, 'handle_planner_job' ], 10, 2 );
    }

    /**
     * Entry point for job processing.
     */
    public function process_job( $job_id, $type ) {
        $job = $this->storage->get_job( $job_id );
        if ( ! $job ) {
            error_log( "[Editorial AI] Worker error: Job {$job_id} not found." );
            return;
        }

        $this->storage->update_job( $job_id, 'processing' );
        $this->storage->log_event( $job_id, 'job_started', [ 'type' => $type ] );

        $result = apply_filters( "kh_editorial_execute_job_{$type}", null, $job );

        if ( is_wp_error( $result ) ) {
            $this->handle_failure( $job_id, $result );
        } elseif ( $result !== null ) {
            // Pass the job creator ID for budgeting attribution
            $this->handle_success( $job_id, $result, (int) ($job['created_by'] ?? 0) );
        } else {
            $this->handle_failure( $job_id, new \WP_Error( 
                'unhandled_job', 
                sprintf( 'No handler found for job type: %s', $type ) 
            ) );
        }
    }

    /**
     * Specialized handler for SEO Audit jobs.
     */
    public function handle_seo_audit_job( $dummy, $job ) {
        $payload = json_decode( $job['payload'], true );
        $post_id = (int) ($payload['post_id'] ?? 0);
        $keyword = $payload['keyword'] ?? '';

        if ( ! $post_id ) {
            return new \WP_Error( 'invalid_post', 'Missing Post ID in job payload.' );
        }

        try {
            /** @var SEOAgent $agent */
            $agent = Container::get('SEOAgent');
            
            // 1. Get Agent context (Analysis + Prompt)
            $audit_context = $agent->audit( $post_id, $keyword );

            if ( $audit_context['status'] === 'fallback' ) {
                return $audit_context['llm_output'];
            }

            // 2. Execute LLM Request via unified LLMService
            // Use the Agent's detailed prompt as the SYSTEM instruction for persona integrity.
            $messages = [
                [ 'role' => 'system', 'content' => $audit_context['prompt'] ]
            ];

            $llm_result = LLMService::post_completion_with_retry( $messages );

            if ( is_wp_error( $llm_result ) ) {
                $this->storage->log_event( $job['id'], 'llm_fallback_triggered', [ 'error' => $llm_result->get_error_message() ] );
                return $audit_context['deterministic_payload'];
            }
            
            // 3. Process & Augment
            $final_payload = $agent->process_response( $llm_result['content'], [
                'post_id'               => $post_id,
                'deterministic_payload' => $audit_context['deterministic_payload']
            ]);

            // Include usage data for the success handler
            $final_payload['usage'] = $llm_result['usage'];

            return $final_payload;

        } catch (\Exception $e) {
            return new \WP_Error( 'agent_error', $e->getMessage() );
        }
    }

    /**
     * Specialized handler for Planner jobs (Phases 1-4 and Final Synopsis).
     */
    public function handle_planner_job( $dummy, $job ) {
        $payload = ( $job['payload'] ?? null ) ? json_decode( $job['payload'], true ) : [];
        $payload = is_array( $payload ) ? $payload : [];
        $prompt = $payload['prompt'] ?? $job['prompt'] ?? '';
        $session_id = (int) ($payload['session_id'] ?? $job['session_id'] ?? 0);

        if ( empty( $prompt ) ) {
            return new \WP_Error( 'invalid_prompt', 'Missing prompt in planner job payload.' );
        }

        try {
            $messages = [
                [ 'role' => 'system', 'content' => 'You are a B2B editorial research assistant. Respond only with valid JSON.' ],
                [ 'role' => 'user', 'content' => $prompt ]
            ];

            // Pass the job's resolved model AND provider so LLMService routes correctly.
            // post_completion() defaults provider to 'openai' if not set — models with '/'
            // (e.g. deepseek/deepseek-v4-flash) must explicitly request 'openrouter'.
            // Framework generation jobs (fw- prefix) need higher max_tokens
            // to accommodate the expanded schema (article_idea, writer_guidance,
            // scoring, observations.evidence, 6-8 APA citations).
            $is_framework_job = strpos( $job['idempotency_key'] ?? '', 'fw-' ) === 0;
            $llm_args = [
                'max_tokens' => $is_framework_job ? 24000 : 16000,
            ];
            if ( ! empty( $job['model'] ) ) {
                $llm_args['model']    = $job['model'];
                $llm_args['provider'] = ( strpos( $job['model'], '/' ) !== false ) ? 'openrouter' : 'openai';

                // For framework jobs routed through OpenAI, enable json_object mode
                // to ensure valid structured JSON output. This requires OpenAI models
                // (gpt-4o-mini, gpt-4o) which support response_format.
                // OpenRouter does not support response_format, so skip for those.
                if ( $is_framework_job && $llm_args['provider'] === 'openai' ) {
                    $llm_args['response_format'] = [ 'type' => 'json_object' ];
                }
            }

            $llm_result = LLMService::post_completion_with_retry( $messages, $llm_args );

            if ( is_wp_error( $llm_result ) ) {
                $this->reset_planner_status_on_failure( $job );
                return $llm_result;
            }

        $content = json_decode( $llm_result['content'], true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            preg_match( '/\`\`\`(?:json)?\s*([\s\S]*?)\`\`\`/', $llm_result['content'], $matches );
            if ( ! empty( $matches[1] ) ) {
                $content = json_decode( $matches[1], true );
            }
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $this->reset_planner_status_on_failure( $job );
                return new \WP_Error( 'invalid_json', 'LLM response was not valid JSON: ' . $llm_result['content'] );
            }
        }

        // Validate framework output schema — warn if required sections missing.
        // The LLM now outputs a flat schema (no "framework" wrapper).
        // Validate top-level keys directly on $content.
        $idempotency_key = $job['idempotency_key'] ?? '';
        if ( strpos( $idempotency_key, 'fw-' ) === 0 ) {
            $missing_sections = [];
            if ( ! isset( $content['article_idea'] ) ) $missing_sections[] = 'article_idea';
            if ( ! isset( $content['title'] ) ) $missing_sections[] = 'title';
            if ( ! isset( $content['overview'] ) ) $missing_sections[] = 'overview';
            if ( ! isset( $content['context'] ) ) $missing_sections[] = 'context';
            if ( ! isset( $content['application'] ) ) $missing_sections[] = 'application';
            if ( ! isset( $content['writer_guidance'] ) ) $missing_sections[] = 'writer_guidance';
            if ( ! isset( $content['scoring'] ) ) $missing_sections[] = 'scoring';
            if ( ! isset( $content['observations'] ) ) $missing_sections[] = 'observations';
            if ( ! empty( $missing_sections ) ) {
                error_log( '[PLANNER] Framework output missing sections: ' . implode( ', ', $missing_sections ) . ' — Job ID: ' . $job['id'] );
                // Don't fail the job — the LLM may still produce useful partial output.
                // But log the issue so operators can monitor quality.
            }
        }

        // Validate dive_deeper citation count — fail if fewer than expected.
        // The prompt already says "EXACTLY N" via the slider's target_min_citations (default 3).
        // Accept any response with at least 1 citation; the prompt enforces the upper bound.
        if ( strpos( $idempotency_key, 'dive-' ) === 0 ) {
            $citations = $content['citations'] ?? [];
            if ( empty( $citations ) ) {
                error_log( '[PLANNER] Dive deeper returned 0 citations. Job ID: ' . $job['id'] );
                $this->reset_planner_status_on_failure( $job );
                return new \WP_Error( 'insufficient_citations', 
                    'Dive deeper returned 0 citations — rerun with a higher depth setting.'
                );
            }
        }

            if ( $session_id && ! empty( $idempotency_key ) ) {
                if ( strpos( $idempotency_key, 'planner-p1-' ) === 0 ) {
                    update_post_meta( $session_id, 'kh_planner_phase1_result', $content );
                } elseif ( strpos( $idempotency_key, 'planner-p2-' ) === 0 ) {
                    update_post_meta( $session_id, 'kh_planner_phase2_result', $content );
                } elseif ( strpos( $idempotency_key, 'planner-p3-' ) === 0 ) {
                    update_post_meta( $session_id, 'kh_planner_phase3_result', $content );
                } elseif ( strpos( $idempotency_key, 'planner-p4-' ) === 0 ) {
                    update_post_meta( $session_id, 'kh_planner_phase4_result', $content );
                } elseif ( strpos( $idempotency_key, 'planner-final-' ) === 0 ) {
                    update_post_meta( $session_id, 'kh_planner_final_synopses', $content );
                } elseif ( strpos( $idempotency_key, 'dive-' ) === 0 ) {
                    // Store dive_deeper results: kh_planner_dives
                    $existing = get_post_meta( $session_id, 'kh_planner_dives', true ) ?: [];
                    $dive_id = substr( $idempotency_key, strlen( 'dive-' ) ); // {session_id}-{hash}
                    $existing[ $dive_id ] = [
                        'citations'  => $content['citations'] ?? $content,
                        'article_headline' => $content['article_headline'] ?? '',
                        'updated_at' => current_time( 'mysql' ),
                    ];
                    update_post_meta( $session_id, 'kh_planner_dives', $existing );
                } elseif ( strpos( $idempotency_key, 'fw-' ) === 0 ) {
                    // Store framework generation results into session meta articles
                    $meta_json = get_post_meta( $session_id, 'kh_planner_meta', true );
                    $meta = ( $meta_json ? json_decode( $meta_json, true ) : null ) ?: [];
                    $articles = $meta['articles'] ?? [];
                    
                    // Extract the article hash from the idempotency key.
                    // Format: fw-{session_id}-{article_hash}[-r{timestamp}]
                    // Strip the session_id prefix and any -r suffix to get just the hash.
                    $after_prefix = substr( $idempotency_key, strlen( 'fw-' . $session_id . '-' ) );
                    // Remove any -r{timestamp} suffix from force re-run mode
                    $article_hash = preg_replace( '/-r\d+$/', '', $after_prefix );
                    
                    $found = false;
                    foreach ( $articles as &$article ) {
                        $article_hash_check = substr( md5( $article['id'] ?? '' ), 0, 12 );
                        if ( $article_hash_check === $article_hash ) {
                            $article['framework'] = [
                                'status'  => 'completed',
                                'output'  => $content,
                                'completed_at' => current_time( 'mysql' ),
                            ];
                            $found = true;
                            error_log( '[PLANNER] Framework result matched article ' . $article['id'] . ' via hash ' . $article_hash );
                            break;
                        }
                    }
                    unset( $article );
                    
                    if ( $found ) {
                        $meta['articles'] = $articles;
                        update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta ) );
                        error_log( '[PLANNER] Framework result stored for article in session ' . $session_id );
                        
                        // Update the queue item status to 'completed' so the queue UI
                        // reflects that the job is done, rather than staying stuck at 'dispatched'.
                        $queue = get_post_meta( $session_id, 'kh_planner_queue', true );
                        if ( is_array( $queue ) ) {
                            $queue_updated = false;
                            foreach ( $queue as $q_idx => $q_item ) {
                                if (
                                    ( $q_item['task_type'] ?? '' ) === 'framework_generation' &&
                                    ( $q_item['article_id'] ?? '' ) === $article['id'] &&
                                    in_array( $q_item['status'] ?? '', [ 'running', 'dispatched' ], true )
                                ) {
                                    $queue[ $q_idx ]['status'] = 'completed';
                                    $queue[ $q_idx ]['completed_at'] = current_time( 'mysql' );
                                    $queue_updated = true;
                                    error_log( '[PLANNER] Queue item ' . $q_item['id'] . ' marked completed for article ' . $article['id'] . ' in session ' . $session_id );
                                    break;
                                }
                            }
                            if ( $queue_updated ) {
                                update_post_meta( $session_id, 'kh_planner_queue', $queue );
                            }
                        }
                    } else {
                        error_log( '[PLANNER] Framework result: article not found in session meta for key ' . $idempotency_key . ' (hash: ' . $article_hash . ')' );
                        // Fallback: try matching by matching any article where framework.status === 'running'
                        // (the status was set before the job was created)
                        foreach ( $articles as &$article ) {
                            if ( ( $article['framework']['status'] ?? '' ) === 'running' ) {
                                $article['framework'] = [
                                    'status'  => 'completed',
                                    'output'  => $content,
                                    'completed_at' => current_time( 'mysql' ),
                                ];
                                $found = true;
                                error_log( '[PLANNER] Framework result matched article ' . $article['id'] . ' via running-status fallback' );
                                break;
                            }
                        }
                        unset( $article );
                        if ( $found ) {
                            $meta['articles'] = $articles;
                            update_post_meta( $session_id, 'kh_planner_meta', wp_json_encode( $meta ) );
                        }
                    }
                }
                update_post_meta( $session_id, 'kh_planner_status', 'phase_complete' );
            }

            return [
                'content' => $content,
                'usage'   => $llm_result['usage'],
                'session_id' => $session_id
            ];

        } catch ( \Exception $e ) {
            $this->reset_planner_status_on_failure( $job );
            return new \WP_Error( 'planner_error', $e->getMessage() );
        }
    }

    /**
     * Reset planner session status on failure so the UI doesn't show it stuck at "running".
     */
    private function reset_planner_status_on_failure( $job ) {
        $session_id = (int) ( $job['session_id'] ?? 0 );
        if ( $session_id && ! empty( $job['idempotency_key'] ) && strpos( $job['idempotency_key'], 'planner-' ) === 0 ) {
            $current_status = get_post_meta( $session_id, 'kh_planner_status', true );
            if ( in_array( $current_status, [ 'phase1_running', 'phase2_running', 'phase3_running', 'phase4_running' ], true ) ) {
                update_post_meta( $session_id, 'kh_planner_status', 'phase_complete' );
            }
        }
    }

    /**
     * Handle successful job completion.
     */
    private function handle_success( $job_id, $result, int $user_id ) {
        $this->storage->update_job( $job_id, 'completed', [
            'response' => wp_json_encode( $result )
        ] );

        $this->storage->log_event( $job_id, 'job_completed' );

        // Financial Integrity: Record usage in the budget table
        if ( ! empty( $result['usage'] ) && $user_id > 0 ) {
            $tokens = intval( $result['usage']['total_tokens'] ?? 0 );
            $this->storage->update_budget_usage( $user_id, $tokens );
        }

        do_action( 'kh_editorial_job_completed', $job_id, $result );
    }

    /**
     * Handle job failure.
     */
    private function handle_failure( $job_id, $error ) {
        $this->storage->update_job( $job_id, 'failed', [
            'error_message' => $error->get_error_message()
        ] );

        $this->storage->log_event( $job_id, 'job_failed', [
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message()
        ] );

        do_action( 'kh_editorial_job_failed', $job_id, $error );
    }
}

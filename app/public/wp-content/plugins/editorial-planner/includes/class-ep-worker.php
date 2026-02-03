<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class EP_Worker {

    public function __construct() {
        add_action( 'ep_run_phase_1_job', array( $this, 'process_phase_1_job' ) );
        add_action( 'ep_run_phase_2_job', array( $this, 'process_phase_2_job' ) );
        add_action( 'ep_run_phase_3_job', array( $this, 'process_phase_3_job' ) );
    }

    public function process_phase_1_job( $job_id ) {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                // Cannot proceed without the DB handler.
                return;
            }
        }
        $db_handler = new Dual_GPT_DB_Handler();

        $job = $db_handler->get_job( $job_id );
        if ( ! $job || $job['status'] !== 'queued' ) {
            return;
        }

        $db_handler->insert_audit_log( $job_id, 'phase_1_started' );

        $budget = $db_handler->check_user_budget( $job['created_by'] );
        if ( $budget['token_used'] >= $budget['token_limit'] ) {
            $db_handler->update_job_status( $job_id, 'failed', array( 'error_message' => 'Budget exceeded.' ) );
            $db_handler->insert_audit_log( $job_id, 'phase_1_failed', array( 'error' => 'Budget exceeded.' ) );
            return;
        }

        $db_handler->update_job_status( $job_id, 'running' );

        $session = $db_handler->get_session( $job['session_id'] );
        $meta = json_decode( $session['meta'] ?? '', true );

        $broad_focus = $meta['broad_focus'] ?? [];
        $granular_focus = $meta['granular_focus'] ?? [];
        $exclusions = $meta['exclusions'] ?? [];
        $preferred_sources = $meta['preferred_sources'] ?? [];
        $force_regenerate = ! empty( $meta['force_regenerate'] );
        $articles = [];

        if ( ! empty( $meta['sponsor_mode'] ) ) {
            $articles = array_merge( $articles, $this->search_sponsor_library( $broad_focus ) );
        }

        $queries = $this->build_queries( $broad_focus, $granular_focus, $preferred_sources, $exclusions );

        foreach ( $queries as $query ) {
            $results = $this->call_serp_api( $query, $force_regenerate );
            $cache_hit = ! is_wp_error( $results ) && ! empty( $results['_cache_hit'] );
            $db_handler->insert_audit_log( $job_id, 'serp_api_call', array( 'query' => $query, 'cache_hit' => $cache_hit ) );
            if ( is_wp_error( $results ) ) {
                continue;
            }
            if ( isset( $results['_cache_hit'] ) ) {
                unset( $results['_cache_hit'] );
            }
            $articles = array_merge( $articles, $this->extract_articles( $results ) );
        }

        $articles = $this->deduplicate_articles( $articles );
        $articles = $this->score_articles( $articles );

        $this->save_articles( $job['session_id'], $job_id, $articles );

        // Placeholder for token usage calculation
        $tokens_used = 1000;
        $db_handler->update_token_usage( $job['created_by'], $tokens_used );
        $db_handler->insert_audit_log( $job_id, 'token_usage_updated', array( 'tokens' => $tokens_used ) );

        $db_handler->update_job_status( $job_id, 'completed' );
        $db_handler->insert_audit_log( $job_id, 'phase_1_completed' );
    }

    private function call_serp_api( $query, $force_regenerate = false ) {
        $cache_key = $this->build_cache_key( $query, 'phase_1', 'serpapi' );
        $cached_results = $force_regenerate ? false : get_transient( $cache_key );

        if ( false !== $cached_results ) {
            $cached_results['_cache_hit'] = true;
            return $cached_results;
        }

        $api_key = get_option( 'ep_serp_api_key' ); // Placeholder for API key
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', 'SERP API key not configured.' );
        }

        $url = add_query_arg(
            array(
                'q'       => $query,
                'api_key' => $api_key,
                'num'     => 20, // Fetch 2 pages of 10 results each
            ),
            'https://api.serpapi.com/search'
        );

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! empty( $data['error'] ) ) {
            return new \WP_Error( 'serp_api_error', $data['error'] );
        }

        $results = $data['organic_results'] ?? [];
        set_transient( $cache_key, $results, DAY_IN_SECONDS );

        $results['_cache_hit'] = false;
        return $results;
    }

    private function extract_articles( $results ) {
        $articles = [];
        if ( empty( $results ) ) {
            return $articles;
        }

        foreach ( $results as $result ) {
            if ( ! empty( $result['title'] ) && ! empty( $result['link'] ) ) {
                $url = $result['link'];
                $domain = $this->canonicalize_domain( $url );
                $articles[] = array(
                    'title'       => $result['title'],
                    'url'         => $url,
                    'snippet'     => $result['snippet'] ?? '',
                    'publication' => $result['source'] ?? '',
                    'domain'      => $domain,
                    'source_type' => $this->classify_source_type(
                        $domain,
                        $result['title'],
                        $result['snippet'] ?? '',
                        $result['source'] ?? ''
                    ),
                    'keywords'    => $this->extract_keywords( $result['title'], $result['snippet'] ?? '' ),
                );
            }
        }
        return $articles;
    }

    private function deduplicate_articles( $articles ) {
        $unique_domains = [];
        $deduplicated_articles = [];

        foreach ( $articles as $article ) {
            if ( empty( $article['url'] ) ) {
                continue;
            }

            $host = $this->canonicalize_domain( $article['url'] );
            if ( ! $host ) {
                continue;
            }

            if ( ! in_array( $host, $unique_domains, true ) ) {
                $unique_domains[] = $host;
                $article['domain'] = $host;
                $deduplicated_articles[] = $article;
            }
        }

        return array_slice( $deduplicated_articles, 0, 16 );
    }

    private function score_articles( $articles ) {
        // Placeholder for scoring logic
        return $articles;
    }

    private function search_sponsor_library( $queries ) {
        // Placeholder for searching sponsor library
        return [];
    }

    private function save_articles( $session_id, $job_id, $articles ) {
        global $wpdb;
        $citations_table = $wpdb->prefix . 'ep_citations';

        foreach ( $articles as $article ) {
            $wpdb->insert(
                $citations_table,
                array(
                    'id'           => wp_generate_uuid4(),
                    'session_id'   => $session_id,
                    'job_id'       => $job_id,
                    'title'        => $article['title'],
                    'url'          => $article['url'],
                    'publication'  => $article['publication'],
                    'domain'       => $article['domain'],
                    'confidence'   => $article['confidence'] ?? 0.5,
                    'sponsored'    => $article['sponsored'] ?? 0,
                    'approved'     => 0,
                    'type'         => $article['source_type'] ?? '',
                    'organisation' => $article['publication'] ?? $article['domain'],
                    'relevance_note' => $article['snippet'] ?? '',
                    'vendor_meta'  => wp_json_encode(
                        array(
                            'snippet' => $article['snippet'] ?? '',
                            'keywords' => $article['keywords'] ?? array(),
                        )
                    ),
                    'created_at'   => current_time( 'mysql' ),
                )
            );
        }
    }

    public function process_phase_2_job( $job_id ) {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                return;
            }
        }
        $db_handler = new Dual_GPT_DB_Handler();

        $job = $db_handler->get_job( $job_id );
        if ( ! $job || $job['status'] !== 'queued' ) {
            return;
        }

        $db_handler->insert_audit_log( $job_id, 'phase_2_started' );

        $budget = $db_handler->check_user_budget( $job['created_by'] );
        if ( $budget['token_used'] >= $budget['token_limit'] ) {
            $db_handler->update_job_status( $job_id, 'failed', array( 'error_message' => 'Budget exceeded.' ) );
            $db_handler->insert_audit_log( $job_id, 'phase_2_failed', array( 'error' => 'Budget exceeded.' ) );
            return;
        }

        $db_handler->update_job_status( $job_id, 'running' );

        $session = $db_handler->get_session( $job['session_id'] );
        $meta = json_decode( $session['meta'] ?? '', true );
        $rejected_domains = $meta['rejected_domains'] ?? [];

        $citations = $this->get_phase_1_citations( $job['session_id'], $rejected_domains );
        $keywords = $this->extract_keywords_from_citations( $citations );
        
        $keyword_data = $this->get_keyword_data( $keywords );
        $db_handler->insert_audit_log( $job_id, 'keyword_api_call', array( 'keywords' => $keywords ) );

        if ( is_wp_error( $keyword_data ) ) {
            $keyword_data = array();
        }

        $validated_citations = $this->validate_and_rank_citations( $citations, $keyword_data );
        $this->update_citations( $validated_citations );

        // Placeholder for token usage calculation
        $tokens_used = 2000;
        $db_handler->update_token_usage( $job['created_by'], $tokens_used );
        $db_handler->insert_audit_log( $job_id, 'token_usage_updated', array( 'tokens' => $tokens_used ) );

        $db_handler->update_job_status( $job_id, 'waiting_for_human' );
        $db_handler->insert_audit_log( $job_id, 'phase_2_completed' );
    }

    private function get_phase_1_citations( $session_id, $rejected_domains = array() ) {
        global $wpdb;
        $citations_table = $wpdb->prefix . 'ep_citations';
        $citations = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $citations_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );
        if ( empty( $rejected_domains ) ) {
            return $citations;
        }

        return array_values(
            array_filter(
                $citations,
                function( $citation ) use ( $rejected_domains ) {
                    if ( empty( $citation['url'] ) ) {
                        return true;
                    }
                    $domain = $this->canonicalize_domain( $citation['url'] );
                    return ! in_array( $domain, $rejected_domains, true );
                }
            )
        );
    }

    private function extract_keywords_from_citations( $citations ) {
        $keywords = [];
        $stop_words = [
            'a', 'an', 'and', 'the', 'in', 'on', 'for', 'is', 'of', 'with', 'to', 'from',
            'that', 'it', 'as', 'by', 'are', 'was', 'were', 'be', 'been', 'has', 'have',
            'had', 'but', 'not', 'or', 'if', 'at', 'this', 'that', 'these', 'those', 'we',
            'you', 'he', 'she', 'it', 'they', 'i', 'me', 'my', 'myself', 'we', 'our',
            'ours', 'ourselves', 'you', 'your', 'yours', 'yourself', 'yourselves', 'he',
            'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'it', 'its',
            'itself', 'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which',
            'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are', 'was',
            'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does',
            'did', 'doing', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as',
            'until', 'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against',
            'between', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
            'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under',
      ];

        foreach ( $citations as $citation ) {
            $snippet = $citation['relevance_note'] ?? '';
            if ( empty( $snippet ) && ! empty( $citation['vendor_meta'] ) ) {
                $meta = json_decode( $citation['vendor_meta'], true );
                $snippet = $meta['snippet'] ?? '';
            }
            $text = strtolower( $citation['title'] . ' ' . $snippet );
            $words = str_word_count( $text, 1 );
            $keywords = array_merge( $keywords, array_diff( $words, $stop_words ) );
        }

        return array_unique( array_slice( $keywords, 0, 50 ) );
    }

    private function get_keyword_data( $keywords ) {
        $api_key = get_option( 'ep_google_ads_api_key' ); // Placeholder for API key
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', 'Google Ads API key not configured.' );
        }

        $keyword_data = [];

        foreach ( $keywords as $keyword ) {
            $url = add_query_arg(
                array(
                    'query'   => $keyword,
                    'api_key' => $api_key,
                ),
                'https://googleads.googleapis.com/v10/keywords:generateHistoricalMetrics'
            );

            $response = wp_remote_get( $url );

            if ( is_wp_error( $response ) ) {
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( ! empty( $data ) ) {
                $keyword_data[ $keyword ] = $data;
            }
        }

        return $keyword_data;
    }

    private function validate_and_rank_citations( $citations, $keyword_data ) {
        $validated_citations = [];

        foreach ( $citations as $citation ) {
            $crossref_data = $this->call_crossref_api( $citation );
            $openalex_data = $this->call_openalex_api( $citation );

            if ( ! empty( $crossref_data ) ) {
                $citation['apa_string'] = $crossref_data['apa'] ?? '';
                $citation['type'] = 'academic';
            } elseif ( ! empty( $openalex_data ) ) {
                $citation['type'] = 'academic';
            }

            $citation['confidence'] = $this->score_citation( $citation, $keyword_data, $crossref_data, $openalex_data );
            $citation['tier'] = $this->get_citation_tier( $citation['confidence'] );
            $citation['organisation'] = $citation['publication'] ?? $citation['domain'] ?? '';
            $citation['vendor_meta'] = wp_json_encode(
                array(
                    'crossref' => $crossref_data,
                    'openalex' => $openalex_data,
                )
            );

            $validated_citations[] = $citation;
        }

        return $this->select_high_authority_citations( $validated_citations );
    }

    private function call_crossref_api( $citation ) {
        // Fictional endpoint
        $url = add_query_arg(
            array(
                'query.bibliographic' => $citation['title'],
            ),
            'https://api.crossref.org/works'
        );
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            return [];
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['message']['items'][0] ?? [];
    }

    private function call_openalex_api( $citation ) {
        // Fictional endpoint
        $url = add_query_arg(
            array(
                'search' => $citation['title'],
            ),
            'https://api.openalex.org/works'
        );
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            return [];
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['results'][0] ?? [];
    }

    private function score_citation( $citation, $keyword_data, $crossref_data, $openalex_data ) {
        // Placeholder for scoring logic
        $score = 0.5;
        if ( ! empty( $crossref_data ) || ! empty( $openalex_data ) ) {
            $score += 0.2;
        }
        if ( $citation['type'] === 'academic' ) {
            $score += 0.1;
        }
        if ( ! empty( $citation['sponsored'] ) ) {
            $score += 0.1; // Boost score for sponsored content
        }
        return min( $score, 1.0 );
    }

    private function get_citation_tier( $confidence ) {
        if ( $confidence > 0.8 ) {
            return 'tier1';
        }
        if ( $confidence > 0.6 ) {
            return 'tier2';
        }
        return 'tier3';
    }

    private function update_citations( $citations ) {
        global $wpdb;
        $citations_table = $wpdb->prefix . 'ep_citations';

        foreach ( $citations as $citation ) {
            $wpdb->update(
                $citations_table,
                array(
                    'confidence' => $citation['confidence'],
                    'tier'       => $citation['tier'],
                    'type'       => $citation['type'] ?? '',
                    'organisation' => $citation['organisation'] ?? '',
                    'apa_string' => $citation['apa_string'] ?? 'details_unavailable',
                    'vendor_meta' => $citation['vendor_meta'] ?? null,
                ),
                array( 'id' => $citation['id'] )
            );
        }
    }

    private function select_high_authority_citations( $citations ) {
        usort( $citations, function( $a, $b ) {
            return $b['confidence'] <=> $a['confidence'];
        } );

        $selected = array();
        $types_needed = array( 'academic' => 1, 'analyst' => 1, 'industry' => 1, 'case_study' => 1 );
        $org_counts = array();

        foreach ( $citations as $citation ) {
            $type = $citation['type'] ?? 'industry';
            $org = $citation['organisation'] ?? $citation['publication'] ?? $citation['domain'] ?? '';

            if ( $org && ( $org_counts[ $org ] ?? 0 ) >= 2 ) {
                continue;
            }

            if ( isset( $types_needed[ $type ] ) && $types_needed[ $type ] > 0 ) {
                $types_needed[ $type ]--;
                $selected[] = $citation;
                if ( $org ) {
                    $org_counts[ $org ] = ( $org_counts[ $org ] ?? 0 ) + 1;
                }
            } elseif ( count( $selected ) < 8 ) {
                $selected[] = $citation;
                if ( $org ) {
                    $org_counts[ $org ] = ( $org_counts[ $org ] ?? 0 ) + 1;
                }
            }

            if ( count( $selected ) >= 8 ) {
                break;
            }
        }

        return $selected;
    }

    public function process_phase_3_job( $job_id ) {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            $db_handler_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-db-handler.php';
            if ( file_exists( $db_handler_file ) ) {
                require_once $db_handler_file;
            } else {
                return;
            }
        }
        $db_handler = new Dual_GPT_DB_Handler();

        $job = $db_handler->get_job( $job_id );
        if ( ! $job || $job['status'] !== 'queued' ) {
            return;
        }

        $db_handler->insert_audit_log( $job_id, 'phase_3_started' );

        $budget = $db_handler->check_user_budget( $job['created_by'] );
        if ( $budget['token_used'] >= $budget['token_limit'] ) {
            $db_handler->update_job_status( $job_id, 'failed', array( 'error_message' => 'Budget exceeded.' ) );
            $db_handler->insert_audit_log( $job_id, 'phase_3_failed', array( 'error' => 'Budget exceeded.' ) );
            return;
        }

        $db_handler->update_job_status( $job_id, 'running' );

        $approved_citations = $this->get_approved_citations( $job['session_id'] );

        $llm_result = $this->generate_trends_and_ideas( $approved_citations );
        if ( is_wp_error( $llm_result ) ) {
            $db_handler->update_job_status( $job_id, 'failed', array( 'error_message' => $llm_result->get_error_message() ) );
            $db_handler->insert_audit_log( $job_id, 'phase_3_failed', array( 'error' => $llm_result->get_error_message() ) );
            return;
        }

        $llm_data = $llm_result['data'] ?? array();
        $usage = $llm_result['usage'] ?? array( 'prompt_tokens' => 0, 'completion_tokens' => 0 );
        $cost = $llm_result['cost'] ?? 0.0;

        $db_handler->insert_audit_log( $job_id, 'llm_api_call', array(
            'citations' => $approved_citations,
            'usage'     => $usage,
            'cost'      => $cost,
            'model'     => $llm_result['model'] ?? null,
        ) );

        $tokens_used = (int) ( ( $usage['prompt_tokens'] ?? 0 ) + ( $usage['completion_tokens'] ?? 0 ) );
        $cost_micro = (int) round( $cost * 1000000 );
        $db_handler->update_job_status( $job_id, 'running', array(
            'usage_prompt_tokens'     => (int) ( $usage['prompt_tokens'] ?? 0 ),
            'usage_completion_tokens' => (int) ( $usage['completion_tokens'] ?? 0 ),
            'cost_micro'              => $cost_micro,
        ) );

        $trends = $this->parse_llm_trends( $llm_data );
        $article_ideas = $this->parse_llm_article_ideas( $llm_data );

        $trend_map = $this->save_trends( $job['session_id'], $trends );
        $this->save_article_ideas( $job['session_id'], $article_ideas, $trend_map );

        $this->create_final_brief( $job['session_id'] );

        $db_handler->update_token_usage( $job['created_by'], $tokens_used );
        $db_handler->insert_audit_log( $job_id, 'token_usage_updated', array( 'tokens' => $tokens_used ) );

        $db_handler->update_job_status( $job_id, 'completed', array(
            'response_json' => wp_json_encode( $llm_data ),
        ) );
        $db_handler->insert_audit_log( $job_id, 'phase_3_completed' );
    }

    private function get_approved_citations( $session_id ) {
        global $wpdb;
        $citations_table = $wpdb->prefix . 'ep_citations';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $citations_table WHERE session_id = %s AND approved = 1", $session_id ),
            ARRAY_A
        );
    }

    private function generate_trends_and_ideas( $citations ) {
        if ( ! class_exists( 'Dual_GPT\Dual_GPT_LLM_Client' ) ) {
            $llm_client_file = WP_PLUGIN_DIR . '/dual-gpt-wordpress-plugin/includes/class-llm-client.php';
            if ( file_exists( $llm_client_file ) ) {
                require_once $llm_client_file;
            } else {
                return new \WP_Error( 'class_not_found', 'Dual_GPT_LLM_Client class not found.' );
            }
        }
        $llm_client = new \Dual_GPT\Dual_GPT_LLM_Client();

        $system_prompt = "You are an expert research analyst. Based on the citations, identify exactly 5 trends and generate exactly 3 article ideas per trend. Respond with a JSON object that matches the provided schema and uses trend_title to connect ideas to trends.";
        $user_prompt = "Citations: \n" . wp_json_encode( $citations );

        $json_schema = $this->get_llm_json_schema();
        $user_prompt .= "\n\nJSON Schema:\n" . wp_json_encode( $json_schema );

        for ( $i = 0; $i < 3; $i++ ) {
            $response = $llm_client->call( $system_prompt, $user_prompt, array( 'json_mode' => true ) );

            if ( is_wp_error( $response ) ) {
                continue;
            }
            
            $content = $llm_client->extract_content( $response );
            $data = json_decode( $content, true );

            if ( $this->validate_json_schema( $data, $json_schema ) ) {
                $usage = $llm_client->get_usage( $response );
                return array(
                    'data'  => $data,
                    'usage' => $usage,
                    'cost'  => $llm_client->estimate_cost( $usage, $llm_client->get_model_name() ),
                    'model' => $llm_client->get_model_name(),
                );
            }
        }

        return new \WP_Error( 'llm_validation_failed', 'Failed to get a valid response from the LLM after 3 attempts.' );
    }

    private function get_llm_json_schema() {
        return array(
            'type' => 'object',
            'properties' => array(
                'trends' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'title' => array( 'type' => 'string' ),
                            'summary' => array( 'type' => 'string' ),
                            'prevalence_score' => array( 'type' => 'number' ),
                            'keywords' => array(
                                'type' => 'array',
                                'items' => array( 'type' => 'string' ),
                            ),
                            'evidence' => array(
                                'type' => 'array',
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'citation_id' => array( 'type' => 'string' ),
                                        'passage_snippet' => array( 'type' => 'string' ),
                                        'confidence' => array( 'type' => 'number' ),
                                    ),
                                    'required' => array( 'citation_id', 'passage_snippet' ),
                                ),
                            ),
                        ),
                        'required' => array( 'title', 'summary' ),
                    ),
                ),
                'article_ideas' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'trend_title' => array( 'type' => 'string' ),
                            'title' => array( 'type' => 'string' ),
                            'three_sentence_summary' => array( 'type' => 'string' ),
                            'key_points' => array(
                                'type' => 'array',
                                'items' => array( 'type' => 'string' ),
                            ),
                            'recommended_length' => array( 'type' => 'string' ),
                            'rating' => array( 'type' => 'string' ),
                            'relevance_score' => array( 'type' => 'number' ),
                        ),
                        'required' => array( 'trend_title', 'title', 'three_sentence_summary', 'key_points', 'recommended_length', 'rating' ),
                    ),
                ),
            ),
            'required' => array( 'trends', 'article_ideas' ),
        );
    }

    private function validate_json_schema( $data, $schema ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        if ( empty( $data['trends'] ) || ! is_array( $data['trends'] ) || count( $data['trends'] ) !== 5 ) {
            return false;
        }

        $trend_titles = array();
        foreach ( $data['trends'] as $trend ) {
            if ( empty( $trend['title'] ) || empty( $trend['summary'] ) ) {
                return false;
            }
            if ( ! is_string( $trend['title'] ) || ! is_string( $trend['summary'] ) ) {
                return false;
            }
            if ( isset( $trend['prevalence_score'] ) && ( ! is_numeric( $trend['prevalence_score'] ) || $trend['prevalence_score'] < 0 || $trend['prevalence_score'] > 1 ) ) {
                return false;
            }
            $trend_titles[] = $trend['title'];
        }

        if ( empty( $data['article_ideas'] ) || ! is_array( $data['article_ideas'] ) || count( $data['article_ideas'] ) !== 15 ) {
            return false;
        }

        $allowed_lengths = array( 'short', 'medium', 'long' );
        $allowed_ratings = array( 'highly_requested', 'emerging', 'undercovered' );
        $idea_counts = array_fill_keys( $trend_titles, 0 );

        foreach ( $data['article_ideas'] as $idea ) {
            if ( empty( $idea['trend_title'] ) || ! in_array( $idea['trend_title'], $trend_titles, true ) ) {
                return false;
            }
            if ( empty( $idea['title'] ) || empty( $idea['three_sentence_summary'] ) ) {
                return false;
            }
            if ( ! is_string( $idea['title'] ) || ! is_string( $idea['three_sentence_summary'] ) ) {
                return false;
            }
            if ( empty( $idea['key_points'] ) || ! is_array( $idea['key_points'] ) || count( $idea['key_points'] ) < 5 ) {
                return false;
            }
            if ( empty( $idea['recommended_length'] ) || ! in_array( $idea['recommended_length'], $allowed_lengths, true ) ) {
                return false;
            }
            if ( empty( $idea['rating'] ) || ! in_array( $idea['rating'], $allowed_ratings, true ) ) {
                return false;
            }

            $idea_counts[ $idea['trend_title'] ]++;
        }

        foreach ( $idea_counts as $count ) {
            if ( $count !== 3 ) {
                return false;
            }
        }

        return true;
    }

    private function parse_llm_trends( $response ) {
        if ( empty( $response['trends'] ) || ! is_array( $response['trends'] ) ) {
            return [];
        }

        return $response['trends'];
    }

    private function parse_llm_article_ideas( $response ) {
        if ( empty( $response['article_ideas'] ) || ! is_array( $response['article_ideas'] ) ) {
            return [];
        }

        return $response['article_ideas'];
    }

    private function save_trends( $session_id, $trends ) {
        global $wpdb;
        $trends_table = $wpdb->prefix . 'ep_trends';
        $trend_map = array();
        foreach ( $trends as $trend ) {
            $trend_id = wp_generate_uuid4();
            $title = $trend['title'] ?? '';
            $wpdb->insert(
                $trends_table,
                array(
                    'id'                => $trend_id,
                    'session_id'        => $session_id,
                    'title'             => $title,
                    'summary'           => $trend['summary'] ?? '',
                    'prevalence_score'  => $trend['prevalence_score'] ?? 0,
                    'created_at'        => current_time( 'mysql' ),
                )
            );
            if ( $title ) {
                $trend_map[ $title ] = $trend_id;
            }
        }
        return $trend_map;
    }

    private function save_article_ideas( $session_id, $article_ideas, $trend_map = array() ) {
        global $wpdb;
        $article_ideas_table = $wpdb->prefix . 'ep_article_ideas';
        foreach ( $article_ideas as $idea ) {
            $trend_id = $idea['trend_id'] ?? '';
            if ( ! $trend_id && ! empty( $idea['trend_title'] ) ) {
                $trend_id = $trend_map[ $idea['trend_title'] ] ?? '';
            }
            if ( ! $trend_id ) {
                continue;
            }
            $wpdb->insert(
                $article_ideas_table,
                array(
                    'id'                     => wp_generate_uuid4(),
                    'trend_id'               => $trend_id,
                    'session_id'             => $session_id,
                    'title'                  => $idea['title'],
                    'three_sentence_summary' => $idea['three_sentence_summary'],
                    'key_points'             => wp_json_encode( $idea['key_points'] ?? array() ),
                    'recommended_length'     => $idea['recommended_length'] ?? '',
                    'rating'                 => $idea['rating'] ?? '',
                    'created_at'             => current_time( 'mysql' ),
                )
            );
        }
    }

    private function create_final_brief( $session_id ) {
        global $wpdb;
        $briefs_table = $wpdb->prefix . 'ep_briefs';

        $citations_table = $wpdb->prefix . 'ep_citations';
        $citations = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $citations_table WHERE session_id = %s AND approved = 1", $session_id ),
            ARRAY_A
        );

        $trends_table = $wpdb->prefix . 'ep_trends';
        $trends = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $trends_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );

        $article_ideas_table = $wpdb->prefix . 'ep_article_ideas';
        $article_ideas = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $article_ideas_table WHERE session_id = %s", $session_id ),
            ARRAY_A
        );

        $ideas_by_trend = array();
        foreach ( $article_ideas as $idea ) {
            $ideas_by_trend[ $idea['trend_id'] ][] = $idea;
        }

        foreach ( $trends as &$trend ) {
            $trend['article_ideas'] = $ideas_by_trend[ $trend['id'] ] ?? array();
        }
        unset( $trend );

        $observations = array();
        $needs_review = false;

        $job_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT response_json FROM {$wpdb->prefix}ai_jobs WHERE session_id = %s AND response_json IS NOT NULL ORDER BY created_at DESC LIMIT 1",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! empty( $job_row['response_json'] ) ) {
            $response = json_decode( $job_row['response_json'], true );
            $trend_data = $response['trends'] ?? array();
            foreach ( $trend_data as $trend_item ) {
                $evidence = $trend_item['evidence'] ?? array();
                $valid_evidence = array_filter( $evidence, function( $item ) {
                    return ! empty( $item['passage_snippet'] ) && strlen( $item['passage_snippet'] ) > 20;
                } );
                if ( empty( $valid_evidence ) ) {
                    $needs_review = true;
                }
                $observations[] = array(
                    'summary'  => $trend_item['summary'] ?? '',
                    'evidence' => array_values( $valid_evidence ),
                );
            }
        } else {
            $needs_review = true;
        }

        if ( $needs_review ) {
            $observations[] = array(
                'summary'  => 'Requires editor review: evidence snippets are missing or incomplete.',
                'evidence' => array(),
            );
        }

        $brief_data = array(
            'session_id'        => $session_id,
            'executive_summary' => 'Draft executive summary generated from approved citations.',
            'context'           => 'Derived from validated citations and trend analysis.',
            'application'       => wp_json_encode( array( 'notes' => 'Populate application guidance in Phase 3 output.' ) ),
            'observations'      => wp_json_encode( array( 'requires_editor_review' => $needs_review, 'items' => $observations ) ),
            'key_themes'        => wp_json_encode( $trends ),
            'citations'         => wp_json_encode( $citations ),
            'writer_guidance'   => $needs_review ? 'requires_editor_review: true' : 'requires_editor_review: false',
            'produced_at'       => current_time( 'mysql' ),
        );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM $briefs_table WHERE session_id = %s", $session_id )
        );

        if ( $existing_id ) {
            $wpdb->update(
                $briefs_table,
                $brief_data,
                array( 'id' => $existing_id )
            );
            return;
        }

        $brief_data['id'] = wp_generate_uuid4();
        $wpdb->insert( $briefs_table, $brief_data );
    }

    private function build_queries( $broad_focus, $granular_focus, $preferred_sources, $exclusions ) {
        $queries = array();

        foreach ( $broad_focus as $broad ) {
            $queries[] = $broad;
            foreach ( $granular_focus as $granular ) {
                $queries[] = trim( $broad . ' ' . $granular );
            }
        }

        foreach ( $preferred_sources as $source ) {
            foreach ( $broad_focus as $broad ) {
                $queries[] = trim( $broad . ' ' . $source );
            }
        }

        $queries = array_unique( array_filter( array_map( 'trim', $queries ) ) );

        if ( ! empty( $exclusions ) ) {
            $exclude = implode( ' ', array_map( function( $term ) {
                return '-' . $term;
            }, $exclusions ) );
            $queries = array_map( function( $query ) use ( $exclude ) {
                return trim( $query . ' ' . $exclude );
            }, $queries );
        }

        return array_slice( $queries, 0, 12 );
    }

    private function build_cache_key( $query, $phase, $model_version ) {
        $normalized = strtolower( trim( preg_replace( '/\s+/', ' ', $query ) ) );
        $hash = hash( 'sha256', $normalized );
        return "ep_research:{$hash}:{$phase}:{$model_version}";
    }

    private function canonicalize_domain( $url ) {
        $host = parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return '';
        }

        $host = strtolower( $host );
        $host = preg_replace( '/^www\./', '', $host );

        $parts = explode( '.', $host );
        if ( count( $parts ) <= 2 ) {
            return $host;
        }

        $tld = array_pop( $parts );
        $sld = array_pop( $parts );
        $second_level = array( 'co', 'com', 'org', 'net', 'gov', 'ac' );

        if ( strlen( $tld ) === 2 && in_array( $sld, $second_level, true ) && ! empty( $parts ) ) {
            $sld = array_pop( $parts ) . '.' . $sld;
        }

        return $sld . '.' . $tld;
    }

    private function classify_source_type( $domain, $title, $snippet, $publication ) {
        $haystack = strtolower( $domain . ' ' . $title . ' ' . $snippet . ' ' . $publication );

        if ( preg_match( '/\b(edu|ac\.uk|journal|research|doi|ieee|acm)\b/', $haystack ) ) {
            return 'academic';
        }
        if ( preg_match( '/\b(gartner|forrester|idc|cbinsights|mckinsey)\b/', $haystack ) ) {
            return 'analyst';
        }
        if ( preg_match( '/\b(case study|case-study|whitepaper)\b/', $haystack ) ) {
            return 'case_study';
        }
        if ( preg_match( '/\b(news|press|times|post|journal)\b/', $haystack ) ) {
            return 'news';
        }

        return 'industry';
    }

    private function extract_keywords( $title, $snippet ) {
        $text = strtolower( $title . ' ' . $snippet );
        $words = str_word_count( $text, 1 );
        $stop_words = array( 'a', 'an', 'and', 'the', 'in', 'on', 'for', 'is', 'of', 'with', 'to', 'from' );
        $keywords = array_diff( $words, $stop_words );
        $keywords = array_slice( array_values( array_unique( $keywords ) ), 0, 6 );

        return $keywords;
    }
}

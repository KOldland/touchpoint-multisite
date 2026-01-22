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
        $meta = json_decode( $session['meta'], true );

        $broad_focus = $meta['broad_focus'] ?? [];
        $articles = [];

        if ( ! empty( $meta['sponsor_mode'] ) ) {
            $articles = array_merge( $articles, $this->search_sponsor_library( $broad_focus ) );
        }

        foreach ( $broad_focus as $query ) {
            $results = $this->call_serp_api( $query );
            $db_handler->insert_audit_log( $job_id, 'serp_api_call', array( 'query' => $query ) );
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

    private function call_serp_api( $query ) {
        $cache_key = 'ep_serp_' . md5( $query );
        $cached_results = get_transient( $cache_key );

        if ( false !== $cached_results ) {
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

        return $results;
    }

    private function extract_articles( $results ) {
        $articles = [];
        if ( empty( $results ) ) {
            return $articles;
        }

        foreach ( $results as $result ) {
            if ( ! empty( $result['title'] ) && ! empty( $result['link'] ) ) {
                $articles[] = array(
                    'title'       => $result['title'],
                    'link'        => $result['link'],
                    'snippet'     => $result['snippet'] ?? '',
                    'publication' => $result['source'] ?? '',
                );
            }
        }
        return $articles;
    }

    private function deduplicate_articles( $articles ) {
        $unique_domains = [];
        $deduplicated_articles = [];

        foreach ( $articles as $article ) {
            if ( empty( $article['link'] ) ) {
                continue;
            }

            $host = parse_url( $article['link'], PHP_URL_HOST );
            if ( ! $host ) {
                continue;
            }

            // Remove www. prefix
            if ( strpos( $host, 'www.' ) === 0 ) {
                $host = substr( $host, 4 );
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

        $citations = $this->get_phase_1_citations( $job['session_id'] );
        $keywords = $this->extract_keywords_from_citations( $citations );
        
        $keyword_data = $this->get_keyword_data( $keywords );
        $db_handler->insert_audit_log( $job_id, 'keyword_api_call', array( 'keywords' => $keywords ) );

        $validated_citations = $this->validate_and_rank_citations( $citations, $keyword_data );
        $this->update_citations( $validated_citations );

        // Placeholder for token usage calculation
        $tokens_used = 2000;
        $db_handler->update_token_usage( $job['created_by'], $tokens_used );
        $db_handler->insert_audit_log( $job_id, 'token_usage_updated', array( 'tokens' => $tokens_used ) );

        $db_handler->update_job_status( $job_id, 'waiting_for_human' );
        $db_handler->insert_audit_log( $job_id, 'phase_2_completed' );
    }

    private function get_phase_1_citations( $session_id ) {
        global $wpdb;
        $citations_table = $wpdb->prefix . 'ep_citations';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $citations_table WHERE session_id = %s", $session_id ),
            ARRAY_A
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
            $text = strtolower( $citation['title'] . ' ' . ( $citation['snippet'] ?? '' ) );
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
                $citation['apa_string'] = $crossref_data['apa'];
                $citation['type'] = 'academic';
            } elseif ( ! empty( $openalex_data ) ) {
                $citation['type'] = 'academic';
            }

            $citation['confidence'] = $this->score_citation( $citation, $keyword_data, $crossref_data, $openalex_data );
            $citation['tier'] = $this->get_citation_tier( $citation['confidence'] );

            $validated_citations[] = $citation;
        }

        usort( $validated_citations, function( $a, $b ) {
            return $b['confidence'] <=> $a['confidence'];
        } );

        return array_slice( $validated_citations, 0, 8 );
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
                ),
                array( 'id' => $citation['id'] )
            );
        }
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
        
        $llm_response = $this->generate_trends_and_ideas( $approved_citations );
        $db_handler->insert_audit_log( $job_id, 'llm_api_call', array( 'citations' => $approved_citations ) );
        
        $trends = $this->parse_llm_trends( $llm_response );
        $article_ideas = $this->parse_llm_article_ideas( $llm_response );

        $this->save_trends( $job['session_id'], $trends );
        $this->save_article_ideas( $job['session_id'], $article_ideas );

        $this->create_final_brief( $job['session_id'] );

        // Placeholder for token usage calculation
        $tokens_used = 5000;
        $db_handler->update_token_usage( $job['created_by'], $tokens_used );
        $db_handler->insert_audit_log( $job_id, 'token_usage_updated', array( 'tokens' => $tokens_used ) );

        $db_handler->update_job_status( $job_id, 'completed' );
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

        $system_prompt = "You are an expert research analyst. Based on the following citations, identify 5 key trends and generate 3 article ideas for each trend. Respond with a JSON object matching the provided schema.";
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
                return $data;
            }
        }

        return new \WP_Error( 'llm_validation_failed', 'Failed to get a valid response from the LLM after 3 attempts.' );
    }

    private function get_llm_json_schema() {
        // Placeholder for the JSON schema
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
                        ),
                        'required' => array( 'trend_title', 'title', 'three_sentence_summary' ),
                    ),
                ),
            ),
            'required' => array( 'trends', 'article_ideas' ),
        );
    }

    private function validate_json_schema( $data, $schema ) {
        // Placeholder for JSON schema validation logic
        return true;
    }

    private function parse_llm_trends( $response ) {
        // Placeholder for parsing trends from LLM response
        return [];
    }

    private function parse_llm_article_ideas( $response ) {
        // Placeholder for parsing article ideas from LLM response
        return [];
    }

    private function save_trends( $session_id, $trends ) {
        global $wpdb;
        $trends_table = $wpdb->prefix . 'ep_trends';
        foreach ( $trends as $trend ) {
            $wpdb->insert(
                $trends_table,
                array(
                    'id'                => wp_generate_uuid4(),
                    'session_id'        => $session_id,
                    'title'             => $trend['title'],
                    'summary'           => $trend['summary'],
                    'prevalence_score'  => $trend['prevalence_score'],
                    'created_at'        => current_time( 'mysql' ),
                )
            );
        }
    }

    private function save_article_ideas( $session_id, $article_ideas ) {
        global $wpdb;
        $article_ideas_table = $wpdb->prefix . 'ep_article_ideas';
        foreach ( $article_ideas as $idea ) {
            $wpdb->insert(
                $article_ideas_table,
                array(
                    'id'                     => wp_generate_uuid4(),
                    'trend_id'               => $idea['trend_id'],
                    'session_id'             => $session_id,
                    'title'                  => $idea['title'],
                    'three_sentence_summary' => $idea['three_sentence_summary'],
                    'key_points'             => wp_json_encode( $idea['key_points'] ),
                    'recommended_length'     => $idea['recommended_length'],
                    'rating'                 => $idea['rating'],
                    'created_at'             => current_time( 'mysql' ),
                )
            );
        }
    }

    private function create_final_brief( $session_id ) {
        global $wpdb;
        $briefs_table = $wpdb->prefix . 'ep_briefs';

        // In a real implementation, we would generate a more comprehensive brief.
        // For now, we'll just create a placeholder.
        $wpdb->insert(
            $briefs_table,
            array(
                'id'                => wp_generate_uuid4(),
                'session_id'        => $session_id,
                'executive_summary' => 'This is a placeholder executive summary.',
                'produced_at'       => current_time( 'mysql' ),
            )
        );
    }
}

<?php

namespace KH\Editorial\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LLMService
 * 
 * Provides a standardized way for suite plugins to access LLM APIs.
 * Supports OpenAI and OpenRouter providers, with per-agent model routing.
 */
class LLMService {

    /**
     * Agent model configuration keys.
     */
    const AGENTS = [
        'research_phase1', 'research_phase2', 'research_phase3', 'research_phase4',
        'framework',
        'draft', 'abstract', 'excerpt',
        'seo_schema', 'geo_cards', 'social_posts', 'gutenberg_push',
    ];

    /**
     * Persona slugs.
     */
    const PERSONAS = [ 'journalist', 'analyst', 'veteran', 'editor' ];

    /**
     * Unified model registry — every model used across all presets, personas,
     * defaults, and fallback chains. Used to populate all UI dropdowns so no
     * model in any preset is missing from the picker.
     *
     * key = OpenRouter model ID (or plain name for OpenAI-direct models)
     * value = Human-readable label
     */
    const ALL_MODELS = [
        // OpenAI (direct)
        'gpt-4o-mini' => 'GPT-4o Mini',
        'gpt-4o'      => 'GPT-4o',

        // Anthropic
        'anthropic/claude-sonnet-4.5'   => 'Claude Sonnet 4.5',
        'anthropic/claude-fable-latest' => 'Claude Fable',

        // DeepSeek
        'deepseek/deepseek-v4-pro'                => 'DeepSeek V4 Pro',
        'deepseek/deepseek-v4-flash'              => 'DeepSeek V4 Flash',
        'deepseek/deepseek-r1'                    => 'DeepSeek R1',
        'deepseek/deepseek-r1-distill-llama-70b'  => 'DeepSeek R1 Distill (Llama 70B)',
        'deepseek/deepseek-r1-distill-qwen-32b'   => 'DeepSeek R1 Distill (Qwen 32B)',
        'deepseek/deepseek-v3'                    => 'DeepSeek V3',
        'deepseek/deepseek-chat'                  => 'DeepSeek V3 Chat',

        // Google
        'google/gemini-2.5-flash' => 'Gemini 2.5 Flash',
        'google/gemini-2.5-pro'   => 'Gemini 2.5 Pro',

        // Meta
        'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
        'meta-llama/llama-3.2-3b-instruct'  => 'Llama 3.2 3B',
        'meta-llama/llama-4-scout'          => 'Llama 4 Scout',
        'meta-llama/llama-4-maverick'       => 'Llama 4 Maverick',

        // Mistral
        'mistralai/mistral-large'              => 'Mistral Large',
        'mistralai/mistral-small-3.1-24b-instruct' => 'Mistral Small 3.1 24B',

        // xAI
        'x-ai/grok-4.3' => 'Grok 4.3',

        // Qwen
        'qwen/qwen3-32b'       => 'Qwen 3 32B',
        'qwen/qwen3-235b-a22b' => 'Qwen 3 235B',
        'qwen/qwen3-coder:free' => 'Qwen 3 Coder (Free)',

        // NVIDIA
        'nvidia/nemotron-nano-9b-v2' => 'Nemotron Nano 9B v2',

        // Nous Research
        'nousresearch/hermes-3-llama-3.1-405b' => 'Hermes 3 Llama 3.1 405B',

        // OpenAI (via OpenRouter)
        'openai/gpt-4o-mini' => 'GPT-4o Mini (OpenRouter)',
        'openai/gpt-4o'      => 'GPT-4o (OpenRouter)',
    ];

    /**
     * Default models per agent (mirrors Balanced profile).
     */
    const DEFAULT_AGENT_MODELS = [
        'research_phase1' => 'deepseek/deepseek-v4-pro',
        'research_phase2' => 'meta-llama/llama-3.3-70b-instruct',
        'research_phase3' => 'deepseek/deepseek-v4-pro',
        'research_phase4' => 'deepseek/deepseek-v4-pro',
        'framework'       => 'anthropic/claude-sonnet-4.5',
        'draft'           => 'anthropic/claude-sonnet-4.5',
        'abstract'        => 'anthropic/claude-sonnet-4.5',
        'excerpt'         => 'meta-llama/llama-3.3-70b-instruct',
        'seo_schema'      => 'deepseek/deepseek-v4-flash',
        'geo_cards'       => 'deepseek/deepseek-v4-pro',
        'social_posts'    => 'meta-llama/llama-3.3-70b-instruct',
        'gutenberg_push'  => 'deepseek/deepseek-v4-flash',
    ];

    /**
     * Default models and temperature per persona.
     */
    const DEFAULT_PERSONA_MODELS = [
        'journalist' => [ 'model' => 'deepseek/deepseek-r1',          'temperature' => 0.4 ],
        'analyst'    => [ 'model' => 'mistralai/mistral-large',       'temperature' => 0.3 ],
        'veteran'    => [ 'model' => 'meta-llama/llama-3.3-70b-instruct', 'temperature' => 0.7 ],
        'editor'     => [ 'model' => 'anthropic/claude-sonnet-4.5',   'temperature' => 0.5 ],
    ];

    /**
     * Preset profiles — 4 profiles × 12 agents × 3-tier fallback chains.
     * Each profile has `models` (primary per agent) and `fallback_chains`
     * (array of [secondary, tertiary] per agent).
     */
    const PRESET_PROFILES = [
        'cost' => [
            'label' => 'Cost',
            'models' => [
                'research_phase1' => 'deepseek/deepseek-v4-flash',
                'research_phase2' => 'qwen/qwen3-32b',
                'research_phase3' => 'deepseek/deepseek-v4-flash',
                'research_phase4' => 'meta-llama/llama-3.3-70b-instruct',
                'framework'       => 'meta-llama/llama-3.3-70b-instruct',
                'draft'           => 'meta-llama/llama-3.3-70b-instruct',
                'abstract'        => 'meta-llama/llama-3.3-70b-instruct',
                'excerpt'         => 'google/gemini-2.5-flash',
                'seo_schema'      => 'deepseek/deepseek-v4-flash',
                'geo_cards'       => 'deepseek/deepseek-v4-flash',
                'social_posts'    => 'meta-llama/llama-3.3-70b-instruct',
                'gutenberg_push'  => 'deepseek/deepseek-v4-flash',
            ],
            'fallback_chains' => [
                'research_phase1' => ['meta-llama/llama-3.3-70b-instruct', 'qwen/qwen3-32b'],
                'research_phase2' => ['deepseek/deepseek-v4-flash', 'nvidia/nemotron-nano-9b-v2'],
                'research_phase3' => ['deepseek/deepseek-r1-distill-llama-70b', 'meta-llama/llama-3.3-70b-instruct'],
                'research_phase4' => ['deepseek/deepseek-r1-distill-qwen-32b', 'google/gemini-2.5-flash'],
                'framework'       => ['mistralai/mistral-small-3.1-24b-instruct', 'deepseek/deepseek-v4-flash'],
                'draft'           => ['qwen/qwen3-32b', 'deepseek/deepseek-v4-flash'],
                'abstract'        => ['deepseek/deepseek-v4-flash', 'nvidia/nemotron-nano-9b-v2'],
                'excerpt'         => ['meta-llama/llama-3.2-3b-instruct', 'deepseek/deepseek-v4-flash'],
                'seo_schema'      => ['qwen/qwen3-coder:free', 'google/gemini-2.5-flash'],
                'geo_cards'       => ['meta-llama/llama-3.3-70b-instruct', 'qwen/qwen3-32b'],
                'social_posts'    => ['mistralai/mistral-small-3.1-24b-instruct', 'deepseek/deepseek-v4-flash'],
                'gutenberg_push'  => ['google/gemini-2.5-flash', 'meta-llama/llama-3.3-70b-instruct'],
            ],
        ],
        'speed' => [
            'label' => 'Speed',
            'models' => [
                'research_phase1' => 'google/gemini-2.5-flash',
                'research_phase2' => 'deepseek/deepseek-v4-flash',
                'research_phase3' => 'meta-llama/llama-4-scout',
                'research_phase4' => 'google/gemini-2.5-flash',
                'framework'       => 'meta-llama/llama-4-scout',
                'draft'           => 'meta-llama/llama-4-scout',
                'abstract'        => 'x-ai/grok-4.3',
                'excerpt'         => 'deepseek/deepseek-v4-flash',
                'seo_schema'      => 'google/gemini-2.5-flash',
                'geo_cards'       => 'x-ai/grok-4.3',
                'social_posts'    => 'meta-llama/llama-4-scout',
                'gutenberg_push'  => 'deepseek/deepseek-v4-flash',
            ],
            'fallback_chains' => [
                'research_phase1' => ['deepseek/deepseek-v4-flash', 'x-ai/grok-4.3'],
                'research_phase2' => ['x-ai/grok-4.3', 'google/gemini-2.5-flash'],
                'research_phase3' => ['deepseek/deepseek-v4-flash', 'x-ai/grok-4.3'],
                'research_phase4' => ['deepseek/deepseek-v4-flash', 'meta-llama/llama-4-scout'],
                'framework'       => ['deepseek/deepseek-v4-flash', 'google/gemini-2.5-flash'],
                'draft'           => ['deepseek/deepseek-v4-flash', 'google/gemini-2.5-flash'],
                'abstract'        => ['deepseek/deepseek-v4-flash', 'google/gemini-2.5-flash'],
                'excerpt'         => ['google/gemini-2.5-flash', 'x-ai/grok-4.3'],
                'seo_schema'      => ['deepseek/deepseek-v4-flash', 'x-ai/grok-4.3'],
                'geo_cards'       => ['deepseek/deepseek-v4-flash', 'google/gemini-2.5-flash'],
                'social_posts'    => ['x-ai/grok-4.3', 'deepseek/deepseek-v4-flash'],
                'gutenberg_push'  => ['google/gemini-2.5-flash', 'meta-llama/llama-4-scout'],
            ],
        ],
        'balanced' => [
            'label' => 'Balanced',
            'models' => [
                'research_phase1' => 'deepseek/deepseek-v4-pro',
                'research_phase2' => 'meta-llama/llama-3.3-70b-instruct',
                'research_phase3' => 'deepseek/deepseek-v4-pro',
                'research_phase4' => 'deepseek/deepseek-v4-pro',
                'framework'       => 'anthropic/claude-sonnet-4.5',
                'draft'           => 'anthropic/claude-sonnet-4.5',
                'abstract'        => 'anthropic/claude-sonnet-4.5',
                'excerpt'         => 'meta-llama/llama-3.3-70b-instruct',
                'seo_schema'      => 'deepseek/deepseek-v4-flash',
                'geo_cards'       => 'deepseek/deepseek-v4-pro',
                'social_posts'    => 'meta-llama/llama-3.3-70b-instruct',
                'gutenberg_push'  => 'deepseek/deepseek-v4-flash',
            ],
            'fallback_chains' => [
                'research_phase1' => ['meta-llama/llama-3.3-70b-instruct', 'anthropic/claude-sonnet-4.5'],
                'research_phase2' => ['mistralai/mistral-small-3.1-24b-instruct', 'deepseek/deepseek-v3'],
                'research_phase3' => ['mistralai/mistral-large', 'anthropic/claude-sonnet-4.5'],
                'research_phase4' => ['meta-llama/llama-3.3-70b-instruct', 'anthropic/claude-sonnet-4.5'],
                'framework'       => ['deepseek/deepseek-v4-pro', 'mistralai/mistral-large'],
                'draft'           => ['meta-llama/llama-3.3-70b-instruct', 'deepseek/deepseek-v4-pro'],
                'abstract'        => ['deepseek/deepseek-v4-pro', 'meta-llama/llama-3.3-70b-instruct'],
                'excerpt'         => ['deepseek/deepseek-v4-flash', 'google/gemini-2.5-flash'],
                'seo_schema'      => ['meta-llama/llama-3.3-70b-instruct', 'google/gemini-2.5-flash'],
                'geo_cards'       => ['meta-llama/llama-3.3-70b-instruct', 'anthropic/claude-sonnet-4.5'],
                'social_posts'    => ['deepseek/deepseek-v4-pro', 'anthropic/claude-sonnet-4.5'],
                'gutenberg_push'  => ['google/gemini-2.5-flash', 'meta-llama/llama-3.3-70b-instruct'],
            ],
        ],
        'performance' => [
            'label' => 'Performance',
            'models' => [
                'research_phase1' => 'meta-llama/llama-4-maverick',
                'research_phase2' => 'deepseek/deepseek-v4-pro',
                'research_phase3' => 'deepseek/deepseek-r1',
                'research_phase4' => 'deepseek/deepseek-r1',
                'framework'       => 'anthropic/claude-sonnet-4.5',
                'draft'           => 'anthropic/claude-sonnet-4.5',
                'abstract'        => 'mistralai/mistral-large',
                'excerpt'         => 'nousresearch/hermes-3-llama-3.1-405b',
                'seo_schema'      => 'deepseek/deepseek-v4-pro',
                'geo_cards'       => 'anthropic/claude-sonnet-4.5',
                'social_posts'    => 'nousresearch/hermes-3-llama-3.1-405b',
                'gutenberg_push'  => 'deepseek/deepseek-v4-pro',
            ],
            'fallback_chains' => [
                'research_phase1' => ['deepseek/deepseek-v4-pro', 'google/gemini-2.5-pro'],
                'research_phase2' => ['anthropic/claude-sonnet-4.5', 'qwen/qwen3-235b-a22b'],
                'research_phase3' => ['anthropic/claude-sonnet-4.5', 'deepseek/deepseek-v4-pro'],
                'research_phase4' => ['anthropic/claude-sonnet-4.5', 'google/gemini-2.5-pro'],
                'framework'       => ['deepseek/deepseek-v4-pro', 'anthropic/claude-fable-latest'],
                'draft'           => ['mistralai/mistral-large', 'anthropic/claude-fable-latest'],
                'abstract'        => ['deepseek/deepseek-v4-pro', 'anthropic/claude-sonnet-4.5'],
                'excerpt'         => ['anthropic/claude-sonnet-4.5', 'deepseek/deepseek-v4-pro'],
                'seo_schema'      => ['anthropic/claude-sonnet-4.5', 'google/gemini-2.5-pro'],
                'geo_cards'       => ['deepseek/deepseek-v4-pro', 'google/gemini-2.5-pro'],
                'social_posts'    => ['mistralai/mistral-large', 'anthropic/claude-sonnet-4.5'],
                'gutenberg_push'  => ['deepseek/deepseek-v4-flash', 'google/gemini-2.5-flash'],
            ],
        ],
    ];

    /**
     * Get the configured OpenAI API key.
     *
     * @return string|null
     */
    public static function get_api_key(): ?string {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : null;
    }

    /**
     * Get the configured OpenRouter API key.
     *
     * @return string|null
     */
    public static function get_openrouter_api_key(): ?string {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openrouter_api_key'] ) ? $settings['openrouter_api_key'] : null;
    }

    /**
     * Get the configured default model (for backward compatibility).
     *
     * @return string
     */
    public static function get_model(): string {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4o-mini';
    }

    /**
     * Resolve which provider and model to use for a given agent.
     * Returns the primary model plus the full 3-tier fallback chain
     * from the active preset profile.
     *
     * @param string $agent One of the AGENTS constants.
     * @param string|null $profile Override profile key, or null to use settings.
     * @return array { provider: string, model: string, fallback_chain: string[] }
     */
    public static function resolve_agent_model( string $agent, string $profile = null ): array {
        $settings = get_option( 'kh_editorial_settings', [] );
        $profile  = $profile ?? ( $settings['preset_profile'] ?? 'speed' );

        // Start with the selected preset profile as base
        if ( isset( self::PRESET_PROFILES[ $profile ] ) ) {
            $profile_data = self::PRESET_PROFILES[ $profile ];
            $model = $profile_data['models'][ $agent ] ?? null;
            $chain = $profile_data['fallback_chains'][ $agent ] ?? [];
        } else {
            $model = null;
            $chain = [];
        }

        // Allow per-agent overrides from user settings (custom models take priority)
        $agent_models = $settings['agent_models'] ?? [];
        if ( isset( $agent_models[ $agent ] ) && ! empty( $agent_models[ $agent ] ) ) {
            $model = $agent_models[ $agent ];
        }

        // Fallback to defaults if no model resolved
        if ( empty( $model ) ) {
            $model = self::DEFAULT_AGENT_MODELS[ $agent ] ?? self::get_model();
        }

        // Allow per-agent fallback overrides from user settings
        $agent_fallbacks = $settings['agent_fallbacks'] ?? [];
        $agent_tertiaries = $settings['agent_tertiaries'] ?? [];
        $override_chain = [];

        if ( isset( $agent_fallbacks[ $agent ] ) && ! empty( $agent_fallbacks[ $agent ] ) ) {
            $override_chain[] = $agent_fallbacks[ $agent ];
        }
        if ( isset( $agent_tertiaries[ $agent ] ) && ! empty( $agent_tertiaries[ $agent ] ) ) {
            $override_chain[] = $agent_tertiaries[ $agent ];
        }

        // If user has set any overrides, use them; otherwise keep preset chain
        if ( ! empty( $override_chain ) ) {
            $chain = $override_chain;
        }

        $route = self::resolve_provider( $model );
        $route['fallback_chain'] = $chain;

        return $route;
    }

    /**
     * Resolve which provider and model to use for a given persona.
     *
     * @param string $persona One of 'journalist', 'analyst', 'veteran', 'editor'.
     * @return array { provider: 'openai'|'openrouter', model: string, temperature: float }
     */
    public static function resolve_persona_model( string $persona ): array {
        $settings       = get_option( 'kh_editorial_settings', [] );
        $persona_models = $settings['persona_models'] ?? [];
        $config         = $persona_models[ $persona ] ?? ( self::DEFAULT_PERSONA_MODELS[ $persona ] ?? self::DEFAULT_PERSONA_MODELS['journalist'] );

        $model       = $config['model'] ?? self::get_model();
        $temperature = (float) ( $config['temperature'] ?? 0.4 );

        $route = self::resolve_provider( $model );
        return [
            'provider'    => $route['provider'],
            'model'       => $route['model'],
            'temperature' => $temperature,
        ];
    }

    /**
     * Internal: resolve provider from model ID, respecting provider priority.
     *
     * Provider priority can be 'openai', 'openrouter', or 'auto' (default —
     * determined by model ID prefix). If the priority provider is set but its
     * API key is missing, falls back to the other provider.
     */
    private static function resolve_provider( string $model ): array {
        $settings         = get_option( 'kh_editorial_settings', [] );
        $provider_priority = $settings['provider_priority'] ?? 'auto';

        $has_forward_slash = strpos( $model, '/' ) !== false;

        if ( $provider_priority === 'openai' ) {
            // Force OpenAI — strip prefix if present
            $provider = 'openai';
            if ( $has_forward_slash ) {
                $parts = explode( '/', $model, 2 );
                $model = $parts[1];
            }
            if ( ! self::get_api_key() ) {
                $provider = 'openrouter';
                $model = self::get_model();
            }
        } elseif ( $provider_priority === 'openrouter' ) {
            // Force OpenRouter — even plain model names go to OpenRouter
            $provider = 'openrouter';
            if ( ! self::get_openrouter_api_key() ) {
                $provider = 'openai';
                $model = self::get_model();
            }
        } else {
            // Auto — detect from model ID
            $provider = $has_forward_slash ? 'openrouter' : 'openai';
            if ( $provider === 'openrouter' && ! self::get_openrouter_api_key() ) {
                $provider = 'openai';
                $model = self::get_model();
            }
        }

        return [ 'provider' => $provider, 'model' => $model ];
    }

    /**
     * Check if any API is configured.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return ! empty( self::get_api_key() ) || ! empty( self::get_openrouter_api_key() );
    }

    /**
     * Execute a chat completion request via OpenAI or OpenRouter.
     *
     * @param array $messages Array of message objects (role, content).
     * @param array $args     Additional API arguments. Supports 'provider' and 'model'.
     * @return array|\WP_Error Result including 'content' and 'usage' or error.
     */
    public static function post_completion( array $messages, array $args = [] ) {
        $provider = $args['provider'] ?? 'openai';
        $model    = $args['model'] ?? self::get_model();

        if ( $provider === 'openrouter' ) {
            return self::post_completion_openrouter( $messages, $model, $args );
        }

        return self::post_completion_openai( $messages, $model, $args );
    }

    /**
     * Execute a chat completion via OpenAI.
     */
    private static function post_completion_openai( array $messages, string $model, array $args = [] ) {
        $api_key = self::get_api_key();
        if ( ! $api_key ) {
            return new \WP_Error( 'llm_unconfigured', 'OpenAI API key is missing.' );
        }

        $defaults = [
            'model'       => $model,
            'temperature' => 0.2,
        ];

        $payload = array_merge( $defaults, $args, [ 'messages' => $messages ] );
        unset( $payload['provider'] );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ]);

        return self::parse_completion_response( $response, $payload );
    }

    /**
     * Execute a chat completion via OpenRouter.
     */
    private static function post_completion_openrouter( array $messages, string $model, array $args = [] ) {
        $api_key = self::get_openrouter_api_key();
        if ( ! $api_key ) {
            return new \WP_Error( 'llm_unconfigured', 'OpenRouter API key is missing.' );
        }

        $defaults = [
            'model'       => $model,
            'temperature' => 0.2,
        ];

        $payload = array_merge( $defaults, $args, [ 'messages' => $messages ] );
        unset( $payload['provider'] );

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization'      => 'Bearer ' . $api_key,
                'Content-Type'       => 'application/json',
                'HTTP-Referer'       => home_url(),
                'X-OpenRouter-Title' => 'KH Editorial Suite',
            ],
            'body' => wp_json_encode( $payload ),
        ]);

        return self::parse_completion_response( $response, $payload );
    }

    /**
     * Parse a chat completion response (shared between OpenAI and OpenRouter).
     */
    private static function parse_completion_response( $response, array $payload ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status  = wp_remote_retrieve_response_code( $response );
        $body    = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $message = $body['error']['message'] ?? 'LLM request failed.';
            return new \WP_Error( 'llm_api_error', $message );
        }

        $content = $body['choices'][0]['message']['content'] ?? '';

        if ( empty( $content ) ) {
            return new \WP_Error( 'llm_empty_response', 'LLM returned an empty response.' );
        }

        return [
            'content' => $content,
            'usage'   => $body['usage'] ?? [ 'total_tokens' => 0 ],
            'model'   => $body['model'] ?? $payload['model'],
        ];
    }

    /**
     * Execute a chat completion request with automatic retry and fallback.
     *
     * Retry strategy: 3 total attempts (initial + 2 retries) with exponential backoff.
     * Retryable errors: network timeouts, connection failures, HTTP 429/500/502/503.
     * Non-retryable errors: missing API key, empty response from API.
     * Fallback chain:
     *   1. `$args['fallback_chain']` — secondary, tertiary models from profile.
     *   2. OpenAI gpt-4o-mini as ultimate fallback.
     *   3. If all fail, returns the last error to the caller.
     *
     * @param array $messages Array of message objects (role, content).
     * @param array $args     Additional API arguments. Supports 'fallback_chain'.
     * @return array|\WP_Error Result including 'content' and 'usage' or error.
     */
    public static function post_completion_with_retry( array $messages, array $args = [] ) {
        $max_attempts = 3;
        $backoff      = [ 2, 4 ];
        $last_error   = null;
        $fallback_chain = $args['fallback_chain'] ?? [];
        unset( $args['fallback_chain'] );

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $result = self::post_completion( $messages, $args );

            if ( ! is_wp_error( $result ) ) {
                return $result;
            }

            $last_error = $result;
            $error_code = $result->get_error_code();

            if ( in_array( $error_code, [ 'llm_unconfigured', 'llm_empty_response' ], true ) ) {
                error_log( '[Editorial AI] LLM retry aborted (non-retryable): ' . $error_code . ' - ' . $result->get_error_message() );
                return $result;
            }

            if ( $attempt === $max_attempts ) {
                // Iterate through fallback chain [secondary, tertiary]
                foreach ( $fallback_chain as $fb_model ) {
                    error_log( '[Editorial AI] Retry exhausted. Trying fallback: ' . $fb_model );
                    $fb_args = $args;
                    $fb_route = self::resolve_provider( $fb_model );
                    $fb_args['provider'] = $fb_route['provider'];
                    $fb_args['model']    = $fb_route['model'];
                    $fb_result = self::post_completion( $messages, $fb_args );

                    if ( ! is_wp_error( $fb_result ) ) {
                        error_log( '[Editorial AI] Fallback succeeded with model: ' . $fb_model );
                        return $fb_result;
                    }

                    error_log( '[Editorial AI] Fallback also failed: ' . $fb_model . ' — ' . $fb_result->get_error_message() );
                }

                // Ultimate fallback: OpenAI gpt-4o-mini
                $fb_args          = $args;
                $fb_args['provider'] = 'openai';
                $fb_args['model']    = 'gpt-4o-mini';
                error_log( '[Editorial AI] All fallbacks exhausted. Trying ultimate fallback gpt-4o-mini.' );
                $fb_result = self::post_completion( $messages, $fb_args );

                if ( ! is_wp_error( $fb_result ) ) {
                    error_log( '[Editorial AI] Ultimate fallback succeeded.' );
                    return $fb_result;
                }

                error_log( '[Editorial AI] Ultimate fallback also failed. Returning error to user.' );
                break;
            }

            $wait = $backoff[ $attempt - 1 ] ?? 4;
            error_log( sprintf(
                '[Editorial AI] LLM attempt %d/%d failed (%s). Retrying in %ds...',
                $attempt,
                $max_attempts,
                $error_code,
                $wait
            ) );
            sleep( $wait );
        }

        return $last_error;
    }
}
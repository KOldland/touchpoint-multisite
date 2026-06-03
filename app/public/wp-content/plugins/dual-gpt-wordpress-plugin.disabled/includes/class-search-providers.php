<?php
/**
 * Search provider registry and configuration helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Search_Providers {
    public function get_primary_provider() {
        return $this->normalize_provider(get_option('dual_gpt_search_provider', 'none'));
    }

    public function get_fallback_providers() {
        $raw = get_option('dual_gpt_search_provider_fallbacks', '');
        if (empty($raw)) {
            return array();
        }

        $parts = array_map('trim', explode(',', $raw));
        $normalized = array();
        foreach ($parts as $provider) {
            if ($provider === '') {
                continue;
            }
            $normalized[] = $this->normalize_provider($provider);
        }

        return array_values(array_filter($normalized));
    }

    public function get_results_limit() {
        return max(1, intval(get_option('dual_gpt_search_results_limit', 16)));
    }

    public function get_cache_ttl_minutes() {
        return max(1, intval(get_option('dual_gpt_search_cache_ttl', 120)));
    }

    public function get_provider_config($provider) {
        $provider = $this->normalize_provider($provider);
        $config = array(
            'provider' => $provider,
            'enabled' => $provider !== 'none',
        );

        switch ($provider) {
            case 'serpapi':
                $config['api_key'] = $this->get_secret('DUAL_GPT_SERPAPI_KEY', 'dual_gpt_serpapi_key');
                break;
            case 'tavily':
                $config['api_key'] = $this->get_secret('DUAL_GPT_TAVILY_KEY', 'dual_gpt_tavily_key');
                break;
            case 'bing':
                $config['api_key'] = $this->get_secret('DUAL_GPT_BING_KEY', 'dual_gpt_bing_key');
                $config['endpoint'] = $this->get_secret('DUAL_GPT_BING_ENDPOINT', 'dual_gpt_bing_endpoint', 'https://api.bing.microsoft.com/v7.0/search');
                break;
            case 'google_cse':
                $config['api_key'] = $this->get_secret('DUAL_GPT_GOOGLE_CSE_KEY', 'dual_gpt_google_cse_key');
                $config['cx'] = $this->get_secret('DUAL_GPT_GOOGLE_CSE_CX', 'dual_gpt_google_cse_cx');
                break;
            case 'brave':
                $config['api_key'] = $this->get_secret('DUAL_GPT_BRAVE_KEY', 'dual_gpt_brave_key');
                break;
            case 'dataforseo':
                $config['login'] = $this->get_secret('DUAL_GPT_DATAFORSEO_LOGIN', 'dual_gpt_dataforseo_login');
                $config['password'] = $this->get_secret('DUAL_GPT_DATAFORSEO_PASSWORD', 'dual_gpt_dataforseo_password');
                break;
            case 'serper':
                $config['api_key'] = $this->get_secret('DUAL_GPT_SERPER_KEY', 'dual_gpt_serper_key');
                break;
        }

        return $config;
    }

    public function get_active_provider_chain() {
        $chain = array();
        $primary = $this->get_primary_provider();
        if ($primary && $primary !== 'none') {
            $chain[] = $primary;
        }
        foreach ($this->get_fallback_providers() as $provider) {
            if ($provider && $provider !== 'none' && !in_array($provider, $chain, true)) {
                $chain[] = $provider;
            }
        }

        return $chain;
    }

    private function normalize_provider($provider) {
        $provider = strtolower(trim((string) $provider));
        if ($provider === '') {
            return 'none';
        }

        $allowed = array('none', 'serpapi', 'tavily', 'bing', 'google_cse', 'brave', 'dataforseo', 'serper');
        if (!in_array($provider, $allowed, true)) {
            return 'none';
        }

        return $provider;
    }

    private function get_secret($env_key, $option_key, $fallback = '') {
        if (defined($env_key)) {
            return constant($env_key);
        }

        $env = getenv($env_key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        $option = get_option($option_key, $fallback);
        return $option !== null ? $option : $fallback;
    }
}

<?php
/**
 * Keyword provider helpers (DataForSEO).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Keyword_Providers {
    private $base_url = 'https://api.dataforseo.com/v3/';

    public function get_dataforseo_credentials() {
        $login = $this->get_secret('DUAL_GPT_DATAFORSEO_LOGIN', 'dual_gpt_dataforseo_login');
        $password = $this->get_secret('DUAL_GPT_DATAFORSEO_PASSWORD', 'dual_gpt_dataforseo_password');

        return array(
            'login' => $login,
            'password' => $password,
        );
    }

    public function keyword_suggestions($seed, $limit = 50, $location = 'United States', $language = 'English') {
        $seed = sanitize_text_field($seed);
        $limit = max(1, intval($limit));

        if ($seed === '') {
            return new WP_Error('keyword_seed_empty', 'Keyword seed cannot be empty.');
        }

        $payload = array(
            array(
                'keywords' => array($seed),
                'location_name' => $location,
                'language_name' => $language,
                'limit' => $limit,
            )
        );

        $response = $this->request('keywords_data/google_ads/keywords_for_keywords/live', $payload);
        if (is_wp_error($response)) {
            return $response;
        }

        return $this->extract_keyword_items($response);
    }

    public function keyword_metrics($keywords, $location = 'United States', $language = 'English') {
        if (!is_array($keywords)) {
            $keywords = array($keywords);
        }

        $keywords = array_values(array_filter(array_map('sanitize_text_field', $keywords)));
        if (empty($keywords)) {
            return new WP_Error('keyword_list_empty', 'Keyword list cannot be empty.');
        }

        $payload = array(
            array(
                'keywords' => $keywords,
                'location_name' => $location,
                'language_name' => $language,
            )
        );

        $response = $this->request('keywords_data/google_ads/search_volume/live', $payload);
        if (is_wp_error($response)) {
            return $response;
        }

        return $this->extract_keyword_items($response);
    }

    public function keyword_difficulty($keywords, $location = 'United States', $language = 'English') {
        if (!is_array($keywords)) {
            $keywords = array($keywords);
        }

        $keywords = array_values(array_filter(array_map('sanitize_text_field', $keywords)));
        if (empty($keywords)) {
            return new WP_Error('keyword_list_empty', 'Keyword list cannot be empty.');
        }

        $payload = array(
            array(
                'keywords' => $keywords,
                'location_name' => $location,
                'language_name' => $language,
            )
        );

        $response = $this->request('keywords_data/google_ads/keywords_difficulty/live', $payload);
        if (is_wp_error($response)) {
            return $response;
        }

        return $this->extract_keyword_items($response);
    }

    public function keyword_trends($keywords, $location = 'United States', $language = 'English') {
        if (!is_array($keywords)) {
            $keywords = array($keywords);
        }

        $keywords = array_values(array_filter(array_map('sanitize_text_field', $keywords)));
        if (empty($keywords)) {
            return new WP_Error('keyword_list_empty', 'Keyword list cannot be empty.');
        }

        $payload = array(
            array(
                'keywords' => $keywords,
                'location_name' => $location,
                'language_name' => $language,
            )
        );

        $response = $this->request('keywords_data/google_trends/explore/live', $payload);
        if (is_wp_error($response)) {
            return $response;
        }

        return $this->extract_keyword_items($response);
    }

    private function request($endpoint, $payload) {
        $credentials = $this->get_dataforseo_credentials();
        if (empty($credentials['login']) || empty($credentials['password'])) {
            return new WP_Error('dataforseo_missing_credentials', 'DataForSEO credentials are not configured.');
        }

        $cache_key = 'dual_gpt_dataforseo_' . md5($endpoint . wp_json_encode($payload));
        $cached = get_transient($cache_key);
        if ($cached) {
            return $cached;
        }

        $url = $this->base_url . ltrim($endpoint, '/');
        $request_args = array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($credentials['login'] . ':' . $credentials['password']),
                'Content-Type' => 'application/json',
            ),
            'sslverify' => false,
            'body' => wp_json_encode($payload),
        );
        if (defined('CURL_SSLVERSION_TLSv1_2')) {
            $request_args['sslversion'] = CURL_SSLVERSION_TLSv1_2;
        }

        $response = wp_remote_post($url, $request_args);

        if (is_wp_error($response)) {
            if ($this->is_ssl_error($response)) {
                $fallback = $this->request_via_curl_binary($url, $credentials, $payload);
                if (!is_wp_error($fallback)) {
                    error_log('[PLANNER][DATAFORSEO] curl fallback used for ' . $url);
                    return $fallback;
                }
                error_log('[PLANNER][DATAFORSEO] curl fallback failed: ' . $fallback->get_error_message());
            }
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
            $message = $decoded['status_message'] ?? $decoded['message'] ?? '';
            if ($status === 404) {
                error_log('[PLANNER][DATAFORSEO] 404 for ' . $url . ' body: ' . substr($body, 0, 300));
            }
            if ($status === 402) {
                $detail = $message !== '' ? ' (' . $message . ')' : '';
                return new WP_Error(
                    'dataforseo_payment_required',
                    'DataForSEO request failed (402). Please add funds or activate your plan' . $detail . '.',
                    array('status' => 402)
                );
            }
            $detail = $message !== '' ? ' (' . $message . ')' : '';
            return new WP_Error(
                'dataforseo_http_error',
                'DataForSEO request failed with status ' . $status . $detail,
                array('status' => $status)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('dataforseo_invalid_json', 'DataForSEO response was invalid JSON.');
        }

        $ttl_minutes = intval(get_option('dual_gpt_search_cache_ttl', 120));
        set_transient($cache_key, $decoded, max(1, $ttl_minutes) * MINUTE_IN_SECONDS);

        return $decoded;
    }

    private function is_ssl_error($error) {
        if (!is_wp_error($error)) {
            return false;
        }
        $message = $error->get_error_message();
        return stripos($message, 'SSL') !== false || stripos($message, 'cURL error 35') !== false;
    }

    private function request_via_curl_binary($url, $credentials, $payload) {
        if (!function_exists('shell_exec')) {
            return new WP_Error('dataforseo_curl_fallback_unavailable', 'DataForSEO fallback unavailable (shell_exec disabled).');
        }

        $curl_path = trim((string) shell_exec('command -v curl'));
        if ($curl_path === '') {
            return new WP_Error('dataforseo_curl_fallback_unavailable', 'DataForSEO fallback unavailable (curl not found).');
        }

        $auth = $credentials['login'] . ':' . $credentials['password'];
        $json = wp_json_encode($payload);
        $cmd = escapeshellcmd($curl_path)
            . ' -sS -u ' . escapeshellarg($auth)
            . ' -H ' . escapeshellarg('Content-Type: application/json')
            . ' --data ' . escapeshellarg($json)
            . ' -w ' . escapeshellarg('HTTPSTATUS:%{http_code}')
            . ' --max-time 20 '
            . escapeshellarg($url);

        $raw = shell_exec($cmd);
        if ($raw === null) {
            return new WP_Error('dataforseo_curl_fallback_failed', 'DataForSEO fallback failed to execute curl.');
        }

        $status = 0;
        $body = $raw;
        $marker = 'HTTPSTATUS:';
        $pos = strrpos($raw, $marker);
        if ($pos !== false) {
            $body = substr($raw, 0, $pos);
            $status = intval(substr($raw, $pos + strlen($marker)));
        }

        if ($status !== 200) {
            $decoded = json_decode($body, true);
            $message = is_array($decoded) ? ($decoded['status_message'] ?? $decoded['message'] ?? '') : '';
            $detail = $message !== '' ? ' (' . $message . ')' : '';
            if ($status === 404) {
                error_log('[PLANNER][DATAFORSEO] curl 404 for ' . $url . ' body: ' . substr($body, 0, 300));
            }
            return new WP_Error(
                'dataforseo_http_error',
                'DataForSEO request failed with status ' . ($status ?: 'unknown') . $detail,
                array('status' => $status ?: 500)
            );
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('dataforseo_invalid_json', 'DataForSEO response was invalid JSON.');
        }

        return $decoded;
    }

    private function extract_keyword_items($response) {
        $items = array();

        $tasks = $response['tasks'] ?? array();
        foreach ($tasks as $task) {
            $result = $task['result'] ?? array();
            foreach ($result as $result_set) {
                if (isset($result_set['items']) && is_array($result_set['items'])) {
                    $entries = $result_set['items'];
                } elseif (isset($result_set['keyword']) || isset($result_set['search_volume'])) {
                    $entries = array($result_set);
                } else {
                    $entries = array();
                }
                foreach ($entries as $entry) {
                    $keyword = $entry['keyword'] ?? ($entry['query'] ?? '');
                    if ($keyword === '') {
                        continue;
                    }
                    $difficulty = $entry['difficulty']
                        ?? $entry['keyword_difficulty']
                        ?? $entry['keyword_difficulty_index']
                        ?? null;
                    $items[] = array(
                        'keyword' => $keyword,
                        'search_volume' => $entry['search_volume'] ?? ($entry['volume'] ?? null),
                        'cpc' => $entry['cpc'] ?? ($entry['average_cpc'] ?? null),
                        'competition' => $entry['competition'] ?? null,
                        'trend' => $entry['trend'] ?? null,
                        'difficulty' => $difficulty,
                        'data' => $entry,
                    );
                }
            }
        }

        return $items;
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

<?php
namespace KH\Editorial\Services\GEO;

/**
 * CurrencyService
 * 
 * Determines currency based on IP or User Settings.
 */
class CurrencyService {

    /**
     * Get currency data based on the visitor's likely location.
     * 
     * @return array [symbol, code, rate]
     */
    public function get_geo_currency() {
        $country_code = $this->get_visitor_country();

        switch ($country_code) {
            case 'GB':
                return ['symbol' => '£', 'code' => 'GBP', 'rate' => 1.0];
            case 'AT': case 'BE': case 'CY': case 'EE': case 'FI': case 'FR': 
            case 'DE': case 'GR': case 'IE': case 'IT': case 'LV': case 'LT': 
            case 'LU': case 'MT': case 'NL': case 'PT': case 'SK': case 'SI': 
            case 'ES':
                return ['symbol' => '€', 'code' => 'EUR', 'rate' => $this->get_rate('EUR')];
            case 'US':
            default:
                return ['symbol' => '$', 'code' => 'USD', 'rate' => $this->get_rate('USD')];
        }
    }

    /**
     * Get exchange rate from GBP to target.
     * Checks admin settings first, falls back to stable defaults.
     */
    private function get_rate($currency) {
        $settings = \KH\Editorial\Core\LLMService::get_settings();
        $rates_option = $settings['currency_rates'] ?? [];

        if (!empty($rates_option[$currency])) {
            return (float) $rates_option[$currency];
        }

        // Hardcoded graceful fallbacks
        $fallbacks = [
            'EUR' => 1.18,
            'USD' => 1.27,
        ];

        return $fallbacks[$currency] ?? 1.0;
    }

    /**
     * Resolve the country code via IP.
     */
    private function get_visitor_country() {
        // Check for Cloudflare header first (standard in our stack)
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
        }

        // Fallback to a basic IP check if needed (in production we would use a DB like MaxMind)
        // For now, we default to GB as the primary market if detection fails.
        return 'GB';
    }
}

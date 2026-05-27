<?php
namespace KH\Attribution\Services\Attribution;

class CommissionCalculator {
    /**
     * Calculate commission based on attribution data
     * 
     * @param array $conversion_data
     * @return float
     */
    public function calculate($conversion_data) {
        $value = $conversion_data['value'] ?? 0;
        
        // Default commission logic: 10%
        $rate = apply_filters('khm_default_commission_rate', 0.10);
        
        // Allow per-affiliate or per-campaign overrides
        $rate = apply_filters('khm_commission_rate', $rate, $conversion_data);
        
        $commission = $value * $rate;
        
        return round($commission, 2);
    }
}
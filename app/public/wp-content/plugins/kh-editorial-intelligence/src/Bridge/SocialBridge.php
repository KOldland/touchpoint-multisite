<?php
namespace KH\Editorial\Bridge;

use KH\Editorial\Services\Membership\MembershipInterface;
use KH\Editorial\Services\GEO\CurrencyService;

/**
 * SocialBridge
 * 
 * Orchestrates member-facing data for the Social Strip and Sharing components.
 * Bridges legacy Membership and ECommerce data with the Modern Editorial REST API.
 */
class SocialBridge {

    /**
     * @var MembershipInterface
     */
    protected $membership;

    /**
     * @var CurrencyService
     */
    protected $currency_service;

    /**
     * Constructor (Dependency Injection)
     * 
     * @param MembershipInterface $membership
     * @param CurrencyService $currency_service
     */
    public function __construct(MembershipInterface $membership, CurrencyService $currency_service) {
        $this->membership = $membership;
        $this->currency_service = $currency_service;
    }

    /**
     * Get comprehensive data for a post from the perspective of a specific member.
     * 
     * @param int $post_id
     * @param int $user_id
     * @return array
     */
    public function get_member_post_data($post_id, $user_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }

        $is_logged_in = ($user_id > 0);
        
        // 1. Membership & Pricing
        $pricing = $this->calculate_pricing($post_id, $user_id);
        
        // 2. Access Status
        $access = $this->get_access_status($post_id, $user_id);
        
        // 3. Sharing & Affiliate
        $sharing = $this->get_sharing_data($post_id, $user_id);

        return [
            'post_id' => $post_id,
            'user' => [
                'is_logged_in' => $is_logged_in,
                'user_id' => $user_id
            ],
            'access' => array_merge($access, [
                'has_downloaded' => $this->membership->has_downloaded($user_id, $post_id)
            ]),
            'pricing' => $pricing,
            'sharing' => $sharing,
            'labels' => [
                'buy' => $access['has_purchased'] ? __('Purchased', 'kh-editorial-intelligence') : sprintf(__('Buy PDF (%s)', 'kh-editorial-intelligence'), $pricing['display_price']),
                'gift' => sprintf(__('Send Article as a Gift (%s%s)', 'kh-editorial-intelligence'), $pricing['currency'], number_format($pricing['member_price'], 2)),
                'save' => $access['is_saved'] ? __('Saved to Library', 'kh-editorial-intelligence') : __('Save to Online Library', 'kh-editorial-intelligence'),
                'download' => $this->membership->has_downloaded($user_id, $post_id) ? __('Redownload PDF', 'kh-editorial-intelligence') : __('Download PDF', 'kh-editorial-intelligence')
            ]
        ];
    }

    /**
     * Calculate pricing based on membership.
     */
    private function calculate_pricing($post_id, $user_id) {
        $base_price_gbp = (float) get_post_meta($post_id, 'kss_article_price', true);
        
        // GEO-aware currency and rate
        $currency_data = $this->currency_service->get_geo_currency();
        $symbol = $currency_data['symbol'];
        $rate = $currency_data['rate'] ?? 1.0;
        
        // Convert base price from GBP to local currency
        $local_base_price = $base_price_gbp * $rate;
        
        $member_price = $local_base_price;
        $discount_percent = 0;

        if ($user_id > 0) {
            $discount_data = $this->membership->get_member_discount($user_id, $local_base_price, 'article');
            $discount_percent = $discount_data['discount_percent'] ?? 0;
            $member_price = $local_base_price * (1 - ($discount_percent / 100));
        }

        return [
            'currency' => $symbol,
            'base_price' => round($local_base_price, 2),
            'member_price' => round($member_price, 2),
            'discount_percent' => $discount_percent,
            'is_free' => ($member_price <= 0),
            'display_price' => ($member_price <= 0) ? __('FREE', 'kh-editorial-intelligence') : $symbol . number_format($member_price, 2)
        ];
    }

    /**
     * Get access status (Purchased, Downloaded, Saved).
     */
    private function get_access_status($post_id, $user_id) {
        if ($user_id <= 0) {
            return [
                'can_download' => false,
                'has_purchased' => false,
                'is_saved' => false
            ];
        }

        $has_purchased = $this->membership->has_purchased($user_id, $post_id);
        $is_saved = $this->membership->is_saved_to_library($user_id, $post_id);

        return [
            'can_download' => ($has_purchased || $this->is_free_to_member($post_id, $user_id)),
            'has_purchased' => $has_purchased,
            'is_saved' => $is_saved
        ];
    }

    /**
     * Get sharing and affiliate data.
     */
    private function get_sharing_data($post_id, $user_id) {
        $raw_url = get_permalink($post_id);
        $affiliate_url = $raw_url;

        // Generate affiliate link if user is logged in
        if ($user_id > 0) {
            if (class_exists('\\KHM\\Services\\AffiliateService')) {
                $service = new \KHM\Services\AffiliateService();
                $affiliate_url = $service->generate_affiliate_url($user_id, $raw_url, $post_id);
            }
        }

        return [
            'raw_url' => $raw_url,
            'affiliate_url' => $affiliate_url,
            'has_affiliate' => ($affiliate_url !== $raw_url),
            'title' => get_the_title($post_id)
        ];
    }

    /**
     * Helper to check if article is free for this specific member.
     */
    private function is_free_to_member($post_id, $user_id) {
        $pricing = $this->calculate_pricing($post_id, $user_id);
        return $pricing['is_free'];
    }
}

<?php

namespace KH\Editorial\Bridge;

use KH\Editorial\Services\Membership\MembershipInterface;
use KH\Editorial\Services\GEO\CurrencyService;
use WP_Post;
use WP_Error;

/**
 * SocialBridge
 * 
 * Orchestrates member-facing data for the Social Strip and Sharing components.
 * Bridges legacy Membership and ECommerce data with the Modern Editorial REST API.
 * 
 * @package KH\Editorial\Bridge
 */
class SocialBridge {

    /**
     * @var MembershipInterface
     */
    protected MembershipInterface $membership;

    /**
     * @var CurrencyService
     */
    protected CurrencyService $currency_service;

    /**
     * Cached instance of the legacy AffiliateService to avoid redundant instantiation.
     * @var object|null
     */
    private static $affiliate_service = null;

    /**
     * Constructor (Dependency Injection)
     */
    public function __construct( MembershipInterface $membership, CurrencyService $currency_service ) {
        $this->membership = $membership;
        $this->currency_service = $currency_service;
    }

    /**
     * Get comprehensive data for a post from the perspective of a specific member.
     * 
     * @param int $post_id The ID of the post (or session/draft).
     * @param int $user_id The ID of the current member.
     * @return array|WP_Error {
     *     @type int    $post_id
     *     @type int    $canonical_id
     *     @type array  $user      { is_logged_in, user_id }
     *     @type array  $access    { can_download, has_purchased, is_saved, has_downloaded }
     *     @type array  $pricing   { currency, base_price, member_price, discount_percent, display_price, ... }
     *     @type array  $sharing   { raw_url, affiliate_url, title, excerpt, image, categories, tags }
     *     @type array  $credits   { available, required, can_download_with_credits }
     *     @type array  $features  { can_download, can_save, can_buy, can_gift, show_login_prompt, ... }
     *     @type array  $labels    { buy, gift, save, download }
     * }
     */
    public function get_member_post_data( int $post_id, int $user_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', 'Post not found', [ 'status' => 404 ] );
        }

        // 1. Resolve Canonical Context (Child CPT Inheritance)
        $canonical_id = $this->resolve_canonical_post_id( $post );
        $canonical_post = ( $canonical_id === $post->ID ) ? $post : get_post( $canonical_id );

        // 2. Pricing and Economy
        $pricing = $this->calculate_pricing( $canonical_post, $user_id );
        $credits = $this->get_credits_data( $canonical_post, $user_id );

        // 3. Access Status (Unified State)
        $access = $this->get_access_status( $canonical_post, $user_id, $pricing, $credits );
        
        // 4. Feature Visibility Flags (Logic-free UI)
        $features = $this->get_feature_flags( $canonical_post, $user_id, $pricing, $access );

        // 5. Sharing & Affiliate
        $sharing = $this->get_sharing_data( $post, $user_id );

        return [
            'post_id'      => $post_id,
            'canonical_id' => $canonical_id,
            'user' => [
                'is_logged_in' => ( $user_id > 0 ),
                'user_id'      => $user_id
            ],
            'access'   => $access,
            'pricing'  => $pricing,
            'sharing'  => $sharing,
            'credits'  => $credits,
            'features' => $features,
            'labels'   => $this->generate_labels( $pricing, $access, $user_id )
        ];
    }

    /**
     * Resolve the ID of the post that owns the commercial metadata.
     * 
     * @param WP_Post $post Current post object.
     * @return int Canonical ID.
     */
    private function resolve_canonical_post_id( WP_Post $post ): int {
        if ( 'post' === $post->post_type || 'author_draft' === $post->post_type ) {
            $session_id = get_post_meta( $post->ID, '_planner_session_id', true );
            // Ensure the session exists before resolving to it
            if ( $session_id && get_post( (int) $session_id ) ) {
                return (int) $session_id;
            }
        }
        return $post->ID;
    }

    /**
     * Calculate pricing based on membership and local currency.
     * 
     * @param WP_Post $post    Post object.
     * @param int     $user_id User ID.
     * @return array Pricing data.
     */
    private function calculate_pricing( WP_Post $post, int $user_id ): array {
        $base_price_gbp = 0.0;
        if ( function_exists( 'get_field' ) ) {
            $base_price_gbp = (float) get_field( 'kss_article_price', $post->ID );
        }
        if ( ! $base_price_gbp ) {
            $base_price_gbp = (float) get_post_meta( $post->ID, 'kss_article_price', true );
        }
        
        $currency_data = $this->currency_service->get_geo_currency();
        $symbol = $currency_data['symbol'];
        $rate   = $currency_data['rate'] ?? 1.0;
        
        $local_base_price = $base_price_gbp * $rate;
        $member_price = $local_base_price;
        $discount_percent = 0;

        if ( $user_id > 0 ) {
            $discount_data = $this->membership->get_member_discount( $user_id, $local_base_price, 'article' );
            $discount_percent = $discount_data['discount_percent'] ?? 0;
            $member_price = $local_base_price * ( 1 - ( $discount_percent / 100 ) );
        }

        return [
            'currency'         => $symbol,
            'base_price'       => round( $local_base_price, 2 ),
            'member_price'     => round( $member_price, 2 ),
            'original_price'   => round( $local_base_price, 2 ),
            'discount_amount'  => round( $local_base_price - $member_price, 2 ),
            'discount_percent' => $discount_percent,
            'has_discount'     => ( $discount_percent > 0 ),
            'is_free'          => ( $member_price <= 0 ),
            'display_price'    => ( $member_price <= 0 ) ? __( 'FREE', 'kh-editorial-intelligence' ) : $symbol . number_format( $member_price, 2 )
        ];
    }

    /**
     * Get credits information for the user and post.
     * 
     * @param WP_Post $post    Post object.
     * @param int     $user_id User ID.
     * @return array Credit status data.
     */
    private function get_credits_data( WP_Post $post, int $user_id ): array {
        $required = (int) get_post_meta( $post->ID, 'kss_credit_cost', true );
        $available = 0;

        if ( $user_id > 0 && method_exists( $this->membership, 'get_user_credits' ) ) {
            $available = $this->membership->get_user_credits( $user_id );
        }

        return [
            'available' => $available,
            'required'  => $required,
            'can_download_with_credits' => ( $required === 0 || $available >= $required )
        ];
    }

    /**
     * Get access status (Purchased, Downloaded, Saved).
     * 
     * @param WP_Post $post    Post object.
     * @param int     $user_id User ID.
     * @param array   $pricing Current pricing data.
     * @param array   $credits Current credits data.
     * @return array Access status booleans.
     */
    private function get_access_status( WP_Post $post, int $user_id, array $pricing, array $credits ): array {
        if ( $user_id <= 0 ) {
            return [
                'can_download'   => false,
                'has_purchased'  => false,
                'is_saved'       => false,
                'has_downloaded' => false
            ];
        }

        $has_purchased  = $this->membership->has_purchased( $user_id, $post->ID );
        $is_saved       = $this->membership->is_saved_to_library( $user_id, $post->ID );
        $has_downloaded = $this->membership->has_downloaded( $user_id, $post->ID );

        // can_download parity: Purchased OR Free OR Has enough credits
        $can_download = ( $has_purchased || $pricing['is_free'] || $credits['can_download_with_credits'] );

        return [
            'can_download'   => $can_download,
            'has_purchased'  => $has_purchased,
            'is_saved'       => $is_saved,
            'has_downloaded' => $has_downloaded
        ];
    }

    /**
     * Generate visibility flags for the UI (Logic-free UI pattern).
     * 
     * @param WP_Post $post    Post object.
     * @param int     $user_id User ID.
     * @param array   $pricing Pricing data.
     * @param array   $access  Access status.
     * @return array Feature visibility flags.
     */
    private function get_feature_flags( WP_Post $post, int $user_id, array $pricing, array $access ): array {
        $is_logged_in = ( $user_id > 0 );
        $is_member = false;
        
        if ( $is_logged_in ) {
            $level = $this->membership->get_user_membership( $user_id );
            $is_member = ( $level !== null );
        }

        return [
            'can_download'         => $access['can_download'],
            'can_save'             => $is_logged_in,
            'can_buy'              => ( $pricing['base_price'] > 0 && ! $access['has_purchased'] ),
            'can_gift'             => ( $is_logged_in && $is_member && $pricing['base_price'] > 0 ),
            'can_share'            => true,
            'show_member_benefits' => $is_member,
            'show_credit_balance'  => $is_logged_in,
            'show_login_prompt'    => ! $is_logged_in
        ];
    }

    /**
     * Get sharing and affiliate data.
     * 
     * @param WP_Post $post    Post object.
     * @param int     $user_id User ID.
     * @return array Sharing metadata and URLs.
     */
    private function get_sharing_data( WP_Post $post, int $user_id ): array {
        $raw_url = get_permalink( $post->ID );
        
        if ( 'publish' !== $post->post_status && ! is_user_logged_in() ) {
            $raw_url = '';
        }

        $affiliate_url = $raw_url ? $this->generate_affiliate_url( $user_id, $raw_url, $post->ID ) : '';

        $excerpt = $post->post_excerpt;
        if ( '' === $excerpt ) {
            $excerpt = wp_strip_all_tags( $post->post_content );
        }

        $image = get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '';
        
        $categories = wp_get_post_terms( $post->ID, 'category', [ 'fields' => 'names' ] );
        $tags       = wp_get_post_terms( $post->ID, 'post_tag', [ 'fields' => 'names' ] );

        return [
            'raw_url'       => $raw_url,
            'affiliate_url' => $affiliate_url,
            'has_affiliate' => ( $raw_url && $affiliate_url !== $raw_url ),
            'title'         => $post->post_title,
            'excerpt'       => $excerpt,
            'image'         => $image,
            'categories'    => is_array( $categories ) ? $categories : [],
            'tags'          => is_array( $tags ) ? $tags : []
        ];
    }

    /**
     * Safe factory-pattern wrapper for legacy Affiliate URL generation.
     */
    private function generate_affiliate_url( int $user_id, string $raw_url, int $post_id ): string {
        if ( $user_id <= 0 ) {
            return $raw_url;
        }

        $service = $this->get_legacy_affiliate_service();
        if ( ! $service ) {
            return $raw_url;
        }

        try {
            if ( method_exists( $service, 'generate_affiliate_url' ) ) {
                return $service->generate_affiliate_url( $user_id, $raw_url, $post_id );
            }
        } catch ( \Throwable $e ) {
        }

        return $raw_url;
    }

    /**
     * Singleton factory for legacy AffiliateService.
     */
    private function get_legacy_affiliate_service() {
        if ( null !== self::$affiliate_service ) {
            return self::$affiliate_service;
        }

        if ( class_exists( '\\KHM\\Services\\AffiliateService' ) ) {
            self::$affiliate_service = new \KHM\Services\AffiliateService();
            return self::$affiliate_service;
        }

        return null;
    }

    /**
     * Generate translated UI labels based on current state.
     * 
     * @param array $pricing Pricing data.
     * @param array $access  Access status.
     * @param int   $user_id User ID.
     * @return array Translated labels.
     */
    private function generate_labels( array $pricing, array $access, int $user_id ): array {
        return [
            'buy'      => $access['has_purchased'] 
                ? __( 'Purchased', 'kh-editorial-intelligence' ) 
                : sprintf( __( 'Buy PDF (%s)', 'kh-editorial-intelligence' ), $pricing['display_price'] ),
            
            'gift'     => sprintf( 
                __( 'Send Article as a Gift (%s)', 'kh-editorial-intelligence' ), 
                $pricing['display_price'] 
            ),
            
            'save'     => $access['is_saved'] 
                ? __( 'Saved to Library', 'kh-editorial-intelligence' ) 
                : __( 'Save to Online Library', 'kh-editorial-intelligence' ),
            
            'download' => ! empty( $access['has_downloaded'] ) 
                ? __( 'Redownload PDF', 'kh-editorial-intelligence' ) 
                : __( 'Download PDF', 'kh-editorial-intelligence' )
        ];
    }
}

<?php

/**
 * DashboardCards — data query helpers for the Ad Studio dashboard.
 *
 * Each static method returns a pre-formatted array ready for
 * the dashboard renderer in AdStudioDashboard.
 */

class KH_AdManager_DashboardCards {

    /**
     * Active Campaigns card.
     *
     * @return array
     */
    public static function active_campaigns() {
        $terms = get_terms( [
            'taxonomy'   => 'ad-campaign',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $campaigns = [];

        foreach ( $terms as $term ) {
            $status = get_term_meta( $term->term_id, 'kh_campaign_status', true );
            $budget = (float) get_term_meta( $term->term_id, 'kh_campaign_budget', true );
            $spend  = (float) get_term_meta( $term->term_id, 'kh_campaign_spend', true );
            $cpc    = (float) get_term_meta( $term->term_id, 'kh_campaign_cpc', true );
            $end    = get_term_meta( $term->term_id, 'kh_campaign_end', true );
            $sponsor_id = get_term_meta( $term->term_id, 'kh_sponsor_id', true );

            // Count associated ad units.
            $ad_count = 0;
            $ads = get_posts( [
                'post_type'      => 'ad_unit',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'tax_query'      => [
                    [
                        'taxonomy' => 'ad-campaign',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ],
                ],
                'fields'         => 'ids',
            ] );
            $ad_count = count( $ads );

            $sponsor_name = '';
            if ( $sponsor_id ) {
                $sponsor_post = get_post( $sponsor_id );
                if ( $sponsor_post ) {
                    $sponsor_name = $sponsor_post->post_title;
                }
            }

            $campaigns[] = [
                'id'            => $term->term_id,
                'name'          => $term->name,
                'status'        => ! empty( $status ) ? $status : 'draft',
                'budget'        => $budget,
                'spend'         => $spend,
                'cpc'           => $cpc,
                'end_date'      => ! empty( $end ) ? date( 'M j, Y', strtotime( $end ) ) : '—',
                'sponsor'       => $sponsor_name ?: '—',
                'active_ads'    => $ad_count,
                'edit_url'      => admin_url( 'term.php?taxonomy=ad-campaign&tag_ID=' . $term->term_id . '&post_type=ad_unit' ),
            ];
        }

        return $campaigns;
    }

    /**
     * Active Ads by Slot card.
     *
     * @return array
     */
    public static function ads_by_slot() {
        $slots = get_terms( [
            'taxonomy'   => 'ad-slot',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $slots ) || empty( $slots ) ) {
            return [];
        }

        $result = [];

        foreach ( $slots as $slot ) {
            $ads = get_posts( [
                'post_type'      => 'ad_unit',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'tax_query'      => [
                    [
                        'taxonomy' => 'ad-slot',
                        'field'    => 'term_id',
                        'terms'    => $slot->term_id,
                    ],
                ],
            ] );

            if ( empty( $ads ) ) {
                $result[] = [
                    'slot'       => $slot->slug,
                    'ad_title'   => '—',
                    'campaign'   => '—',
                    'impressions'=> 0,
                    'clicks'     => 0,
                    'ctr'        => 0,
                    'ad_count'   => 0,
                ];
                continue;
            }

            foreach ( $ads as $ad ) {
                $campaigns = wp_get_post_terms( $ad->ID, 'ad-campaign', [ 'fields' => 'names' ] );
                $campaign_name = ! empty( $campaigns ) ? implode( ', ', $campaigns ) : '—';

                $stats = self::_get_ad_stats( $ad->ID );

                $result[] = [
                    'slot'        => $slot->slug,
                    'ad_title'    => $ad->post_title,
                    'ad_edit_url' => get_edit_post_link( $ad->ID ),
                    'campaign'    => $campaign_name,
                    'impressions' => $stats['impressions'],
                    'clicks'      => $stats['clicks'],
                    'ctr'         => $stats['ctr'],
                    'ad_count'    => count( $ads ),
                ];
            }
        }

        return $result;
    }

    /**
     * Daily Spend card.
     *
     * @return array
     */
    public static function daily_spend() {
        global $wpdb;
        $table = $wpdb->prefix . 'kh_ad_events';

        // Total spend = clicks × CPC for each campaign today.
        $today_start     = date( 'Y-m-d 00:00:00' );
        $month_start     = date( 'Y-m-01 00:00:00' );
        $quarter         = ceil( date( 'n' ) / 3 );
        $quarter_start   = date( 'Y-' ) . str_pad( ( ( $quarter - 1 ) * 3 + 1 ), 2, '0', STR_PAD_LEFT ) . '-01 00:00:00';

        $today_clicks = 0;
        $month_clicks = 0;
        $quarter_clicks = 0;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
            $today_clicks = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_type = 'click' AND created_at >= %s",
                $today_start
            ) );
            $month_clicks = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_type = 'click' AND created_at >= %s",
                $month_start
            ) );
            $quarter_clicks = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_type = 'click' AND created_at >= %s",
                $quarter_start
            ) );
        }

        return [
            'today'   => $today_clicks,
            'month'   => $month_clicks,
            'quarter' => $quarter_clicks,
        ];
    }

    /**
     * Slot Reference card.
     *
     * @return array
     */
    public static function slot_reference() {
        $slots = get_terms( [
            'taxonomy'   => 'ad-slot',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $slots ) || empty( $slots ) ) {
            $slots = [];
        }

        $dimensions = function_exists( 'kh_get_slot_exact_dimensions' )
            ? kh_get_slot_exact_dimensions()
            : [];

        $descriptions = [
            'header'       => __( 'Top-of-page banner (1600×500). High visibility above the fold.', 'kh-ad-manager' ),
            'footer'       => __( 'Bottom-of-page banner (1600×500). Good for secondary impressions.', 'kh-ad-manager' ),
            'sidebar1'     => __( 'Primary sidebar (300×600). Appears on article and category pages.', 'kh-ad-manager' ),
            'sidebar2'     => __( 'Secondary sidebar (300×600). Below sidebar1 on wider layouts.', 'kh-ad-manager' ),
            'pop-up'       => __( 'Central modal overlay (700×700). Triggered by delay or exit intent.', 'kh-ad-manager' ),
            'slide-in'     => __( 'Bottom-right slide-in panel (300×250). Dismissible by user.', 'kh-ad-manager' ),
            'exit_overlay' => __( 'Exit-intent overlay. Fires when mouse leaves the viewport.', 'kh-ad-manager' ),
            'ticker'       => __( 'Horizontal ticker bar. Persistent at the top of the viewport.', 'kh-ad-manager' ),
        ];

        $result = [];

        foreach ( $slots as $slot ) {
            $dims = isset( $dimensions[ $slot->slug ] )
                ? $dimensions[ $slot->slug ]['width'] . '×' . $dimensions[ $slot->slug ]['height']
                : '—';

            $result[] = [
                'slug'        => $slot->slug,
                'dimensions'  => $dims,
                'description' => $descriptions[ $slot->slug ] ?? $slot->description ?? '—',
                'shortcode'   => '[kh_ad slot="' . esc_attr( $slot->slug ) . '"]',
            ];
        }

        return $result;
    }

    /**
     * Quick stats — summary counts for the stat row.
     *
     * @return array
     */
    public static function quick_stats() {
        $ad_counts = wp_count_posts( 'ad_unit' );
        $total_ads = (int) $ad_counts->publish + (int) $ad_counts->draft;

        $campaign_terms = get_terms( [
            'taxonomy'   => 'ad-campaign',
            'hide_empty' => false,
            'fields'     => 'ids',
        ] );
        $total_campaigns = is_wp_error( $campaign_terms ) ? 0 : count( $campaign_terms );

        // Count live campaigns.
        $live_count = 0;
        if ( ! is_wp_error( $campaign_terms ) ) {
            foreach ( $campaign_terms as $term_id ) {
                $status = get_term_meta( $term_id, 'kh_campaign_status', true );
                if ( $status === 'live' ) {
                    $live_count++;
                }
            }
        }

        $sponsor_counts = wp_count_posts( 'kh_sponsor' );
        $total_sponsors = (int) $sponsor_counts->publish;

        return [
            'total_ads'       => $total_ads,
            'total_campaigns' => $total_campaigns,
            'live_campaigns'  => $live_count,
            'total_sponsors'  => $total_sponsors,
        ];
    }

    /**
     * Get impression/click stats for a single ad.
     *
     * @param int $ad_id
     * @return array
     */
    private static function _get_ad_stats( $ad_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kh_ad_events';

        $impressions = 0;
        $clicks      = 0;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
            $impressions = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE ad_id = %d AND event_type = 'impression'",
                $ad_id
            ) );
            $clicks = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE ad_id = %d AND event_type = 'click'",
                $ad_id
            ) );
        }

        $ctr = $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0;

        return [
            'impressions' => $impressions,
            'clicks'      => $clicks,
            'ctr'         => $ctr,
        ];
    }
}
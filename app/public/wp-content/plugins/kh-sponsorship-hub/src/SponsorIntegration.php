<?php
namespace KhSponsorshipHub\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorIntegration {
    public function register(): void {
        // Listen to the membership plugin asking for sponsor data for landing pages
        add_filter( 'khm_membership_landing_sponsor_data', [ $this, 'provide_sponsor_data' ], 10, 2 );
    }

    public function provide_sponsor_data( array $sponsor, string $sponsorId ): array {
        if ( empty( $sponsorId ) ) {
            return $sponsor;
        }

        $numericId = $this->extract_numeric_id( $sponsorId );
        if ( $numericId > 0 && SponsorMigration::table_exists() ) {
            global $wpdb;
            $table = SponsorMigration::sponsors_table_name();
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT id, name FROM {$table} WHERE id = %d LIMIT 1", $numericId ),
                ARRAY_A
            );
            if ( is_array( $row ) ) {
                $sponsor['name'] = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
            }
        }

        // Fetch meta options
        $logoUrl = (string) get_option( 'khm_sponsor_logo_' . $sponsorId, '' );
        $accent = sanitize_text_field( (string) get_option( 'khm_sponsor_accent_' . $sponsorId, '' ) );
        $blurb = (string) get_option( 'khm_sponsor_blurb_' . $sponsorId, '' );

        if ( ! empty( $logoUrl ) ) {
            $sponsor['logo_url'] = $logoUrl;
        }
        if ( ! empty( $accent ) ) {
            $sponsor['accent_color'] = $accent;
        }
        if ( ! empty( $blurb ) ) {
            $sponsor['blurb'] = $blurb;
        }

        return $sponsor;
    }

    private function extract_numeric_id( string $value ): int {
        if ( preg_match( '/(\d+)/', $value, $matches ) ) {
            return absint( $matches[1] );
        }
        return 0;
    }
}

<?php
/**
 * Sponsor Dashboard - Front-end access for sponsor team members.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorDashboard {
    public function register(): void {
        add_shortcode( 'khm_sponsor_dashboard', array( $this, 'render_dashboard' ) );
    }

    /**
     * Render sponsor dashboard shortcode.
     */
    public function render_dashboard( $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '<p class="khm-sponsor-login-required">' . esc_html__( 'Please log in to view the sponsor dashboard.', 'khm-membership' ) . '</p>';
        }

        $user_id = get_current_user_id();
        $sponsor = $this->get_user_sponsor( $user_id );

        if ( ! $sponsor ) {
            return '<p class="khm-sponsor-access-denied">' . esc_html__( 'You do not have access to the sponsor dashboard.', 'khm-membership' ) . '</p>';
        }

        ob_start();
        $this->render_dashboard_content( $user_id, $sponsor );
        return ob_get_clean();
    }

    /**
     * Get sponsor record for current user if they're a team member.
     * 
     * @param int $user_id WordPress user ID
     * @return array|null Sponsor record or null if not authorized
     */
    private function get_user_sponsor( int $user_id ): ?array {
        if ( ! $user_id ) {
            return null;
        }

        global $wpdb;
        $sponsors_table = SponsorMigration::sponsors_table_name();

        // Get all sponsors (we need to check JSON team_members)
        $sponsors = $wpdb->get_results(
            "SELECT * FROM {$sponsors_table} ORDER BY created_at DESC",
            ARRAY_A
        );

        foreach ( $sponsors as $sponsor ) {
            $team_members = json_decode( (string) ( $sponsor['team_members'] ?? '' ), true );
            if ( ! is_array( $team_members ) ) {
                continue;
            }

            // Check if current user is in team members
            foreach ( $team_members as $member ) {
                if ( isset( $member['user_id'] ) && (int) $member['user_id'] === $user_id ) {
                    return $sponsor;
                }
            }
        }

        return null;
    }

    /**
     * Render dashboard content.
     */
    private function render_dashboard_content( int $user_id, array $sponsor ): void {
        $sponsor_id = absint( $sponsor['id'] ?? 0 );
        $sponsor_name = sanitize_text_field( $sponsor['name'] ?? '' );
        $libraries = $this->get_sponsor_libraries( $sponsor_id );
        $team_members = json_decode( (string) ( $sponsor['team_members'] ?? '' ), true );
        if ( ! is_array( $team_members ) ) {
            $team_members = array();
        }

        ?>
        <div class="khm-sponsor-dashboard">
            <div class="khm-sponsor-header">
                <h1><?php echo esc_html( $sponsor_name ); ?> Dashboard</h1>
                <p class="description"><?php esc_html_e( 'Welcome to your sponsor dashboard. Here you can view your libraries and team.', 'khm-membership' ); ?></p>
            </div>

            <div class="khm-sponsor-section">
                <h2><?php esc_html_e( 'Libraries', 'khm-membership' ); ?></h2>
                <?php if ( empty( $libraries ) ) : ?>
                    <p><?php esc_html_e( 'No libraries available yet.', 'khm-membership' ); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Library Name', 'khm-membership' ); ?></th>
                                <th><?php esc_html_e( 'Documents', 'khm-membership' ); ?></th>
                                <th><?php esc_html_e( 'Created', 'khm-membership' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $libraries as $library ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $library['name'] ); ?></strong></td>
                                    <td><?php echo absint( $library['doc_count'] ?? 0 ); ?></td>
                                    <td><?php echo esc_html( $this->format_date( $library['created_at'] ?? '' ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="khm-sponsor-section">
                <h2><?php esc_html_e( 'Team Members', 'khm-membership' ); ?></h2>
                <?php if ( empty( $team_members ) ) : ?>
                    <p><?php esc_html_e( 'No team members listed.', 'khm-membership' ); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'khm-membership' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'khm-membership' ); ?></th>
                                <th><?php esc_html_e( 'Job Title', 'khm-membership' ); ?></th>
                                <th><?php esc_html_e( 'Level', 'khm-membership' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $team_members as $member ) : ?>
                                <tr>
                                    <td>
                                        <?php
                                        $full_name = trim( ( $member['first_name'] ?? '' ) . ' ' . ( $member['last_name'] ?? '' ) );
                                        echo esc_html( $full_name ?: '—' );
                                        ?>
                                    </td>
                                    <td><?php echo esc_html( $member['work_email'] ?? '—' ); ?></td>
                                    <td><?php echo esc_html( $member['job_title'] ?? '—' ); ?></td>
                                    <td><?php echo esc_html( $member['membership_level'] ?? 'sponsor' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .khm-sponsor-dashboard {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            .khm-sponsor-header {
                margin-bottom: 40px;
            }
            .khm-sponsor-section {
                margin-bottom: 40px;
            }
            .khm-sponsor-section h2 {
                margin-bottom: 16px;
                padding-bottom: 8px;
                border-bottom: 2px solid #e0e0e0;
            }
            .khm-sponsor-login-required,
            .khm-sponsor-access-denied {
                padding: 20px;
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                color: #856404;
            }
        </style>
        <?php
    }

    /**
     * Get libraries for a sponsor.
     */
    private function get_sponsor_libraries( int $sponsor_id ): array {
        if ( ! $sponsor_id ) {
            return array();
        }

        $libraries = SponsorIngest::list_libraries( $sponsor_id );
        
        // Add document counts to each library
        foreach ( $libraries as &$library ) {
            $docs = SponsorIngest::list_docs_by_library( absint( $library['id'] ?? 0 ) );
            $library['doc_count'] = is_array( $docs ) ? count( $docs ) : 0;
        }
        unset( $library );

        return $libraries;
    }

    /**
     * Format date for display.
     */
    private function format_date( string $date ): string {
        if ( empty( $date ) ) {
            return '—';
        }

        $timestamp = strtotime( $date );
        if ( ! $timestamp ) {
            return '—';
        }

        return date_i18n( get_option( 'date_format' ), $timestamp );
    }
}

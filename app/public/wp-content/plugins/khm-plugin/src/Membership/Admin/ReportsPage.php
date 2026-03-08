<?php

namespace KHM\Membership\Admin;

use KHM\Services\MembershipRepository;

require_once __DIR__ . '/membership_cache.php';

class ReportsPage {
	private AttributionReportService $service;

    public function __construct() {
        $this->service = new AttributionReportService();
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
        add_action( 'admin_post_khm_membership_reports_export', [ $this, 'handle_export' ] );
        add_action( 'admin_post_khm_membership_reports_anonymize', [ $this, 'handle_anonymize' ] );
        add_action( 'admin_post_khm_membership_retention_settings', [ $this, 'handle_retention_settings' ] );
        add_action( 'khm_membership_attribution_mutated', [ $this, 'invalidate_report_cache' ] );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'khm-main-menu',
            'Membership Reports',
            'Membership Reports',
            'manage_options',
            'khm-membership-reports',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( sprintf( 'unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-membership-reports' ) );
            wp_die( esc_html__( 'You do not have permission to view membership reports.', 'khm-membership' ) );
        }

        $filters = $this->get_filters();
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 50;

        $report = $this->service->query( $filters, $page, $per_page );
        $rows = $report['items'];
        $kpis = $this->service->get_kpis( $filters );
        $redactedRows = 0;
        foreach ( $rows as $row ) {
            $cleaned = $this->service->apply_consent_redaction( $row );
            if ( (string) ( $cleaned['user_email'] ?? '' ) === '' && (string) ( $row['user_email'] ?? '' ) !== '' ) {
                $redactedRows++;
            }
        }
        $retentionDays = (int) get_site_option( 'khm_attribution_retention_days', 730 );

        $this->emit_telemetry( 'membership.report.view', [
            'user_id' => (int) get_current_user_id(),
            'filters' => $filters,
            'page' => $page,
            'rows' => count( $rows ),
        ] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Membership Reports', 'khm-membership' ); ?></h1>

            <form method="get" style="margin-bottom: 14px;">
                <input type="hidden" name="page" value="khm-membership-reports" />
                <input type="number" name="schedule_id" value="<?php echo esc_attr( (string) ( $filters['schedule_id'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Schedule ID', 'khm-membership' ); ?>" />
                <input type="number" name="sponsor_id" value="<?php echo esc_attr( (string) ( $filters['sponsor_id'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Sponsor ID', 'khm-membership' ); ?>" />
                <input type="number" name="user_id" value="<?php echo esc_attr( (string) ( $filters['user_id'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'User ID', 'khm-membership' ); ?>" />
                <input type="text" name="conversion_type" value="<?php echo esc_attr( (string) ( $filters['conversion_type'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Conversion Type', 'khm-membership' ); ?>" />
                <input type="date" name="date_from" value="<?php echo esc_attr( (string) ( $filters['date_from'] ?? '' ) ); ?>" />
                <input type="date" name="date_to" value="<?php echo esc_attr( (string) ( $filters['date_to'] ?? '' ) ); ?>" />
                <input type="search" name="q" value="<?php echo esc_attr( (string) ( $filters['q'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Email or UTM', 'khm-membership' ); ?>" />
                <button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'khm-membership' ); ?></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khm-membership-reports' ) ); ?>"><?php esc_html_e( 'Reset', 'khm-membership' ); ?></a>
            </form>

            <p>
                <strong><?php esc_html_e( 'Total:', 'khm-membership' ); ?></strong> <?php echo esc_html( (string) $kpis['total'] ); ?> &nbsp;|&nbsp;
                <strong><?php esc_html_e( 'Paid:', 'khm-membership' ); ?></strong> <?php echo esc_html( (string) $kpis['paid'] ); ?> &nbsp;|&nbsp;
                <strong><?php esc_html_e( 'Signup:', 'khm-membership' ); ?></strong> <?php echo esc_html( (string) $kpis['signup'] ); ?> &nbsp;|&nbsp;
                <strong><?php esc_html_e( 'No Consent:', 'khm-membership' ); ?></strong> <?php echo esc_html( (string) $kpis['no_consent'] ); ?> &nbsp;|&nbsp;
                <strong><?php esc_html_e( 'Unique Users:', 'khm-membership' ); ?></strong> <?php echo esc_html( (string) $kpis['unique_users'] ); ?> &nbsp;|&nbsp;
                <strong><?php esc_html_e( 'Rows redacted:', 'khm-membership' ); ?></strong> <?php echo esc_html( (string) $redactedRows ); ?>
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 12px;">
                <?php wp_nonce_field( 'khm_membership_retention_settings', 'khm_membership_retention_settings_nonce' ); ?>
                <input type="hidden" name="action" value="khm_membership_retention_settings" />
                <label for="khm_attribution_retention_days"><strong><?php esc_html_e( 'Retention days', 'khm-membership' ); ?></strong></label>
                <input id="khm_attribution_retention_days" type="number" min="1" name="khm_attribution_retention_days" value="<?php echo esc_attr( (string) $retentionDays ); ?>" />
                <button class="button" type="submit"><?php esc_html_e( 'Save retention', 'khm-membership' ); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 12px;">
                <?php wp_nonce_field( 'khm_membership_reports_export', 'khm_membership_reports_export_nonce' ); ?>
                <input type="hidden" name="action" value="khm_membership_reports_export" />
                <input type="hidden" name="schedule_id" value="<?php echo esc_attr( (string) ( $filters['schedule_id'] ?? '' ) ); ?>" />
                <input type="hidden" name="sponsor_id" value="<?php echo esc_attr( (string) ( $filters['sponsor_id'] ?? '' ) ); ?>" />
                <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) ( $filters['user_id'] ?? '' ) ); ?>" />
                <input type="hidden" name="conversion_type" value="<?php echo esc_attr( (string) ( $filters['conversion_type'] ?? '' ) ); ?>" />
                <input type="hidden" name="date_from" value="<?php echo esc_attr( (string) ( $filters['date_from'] ?? '' ) ); ?>" />
                <input type="hidden" name="date_to" value="<?php echo esc_attr( (string) ( $filters['date_to'] ?? '' ) ); ?>" />
                <input type="hidden" name="q" value="<?php echo esc_attr( (string) ( $filters['q'] ?? '' ) ); ?>" />
                <button class="button" type="submit"><?php esc_html_e( 'Export CSV', 'khm-membership' ); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 12px;">
                <?php wp_nonce_field( 'khm_membership_reports_anonymize', 'khm_membership_reports_anonymize_nonce' ); ?>
                <input type="hidden" name="action" value="khm_membership_reports_anonymize" />
                <input type="hidden" name="schedule_id" value="<?php echo esc_attr( (string) ( $filters['schedule_id'] ?? '' ) ); ?>" />
                <input type="hidden" name="sponsor_id" value="<?php echo esc_attr( (string) ( $filters['sponsor_id'] ?? '' ) ); ?>" />
                <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) ( $filters['user_id'] ?? '' ) ); ?>" />
                <input type="hidden" name="conversion_type" value="<?php echo esc_attr( (string) ( $filters['conversion_type'] ?? '' ) ); ?>" />
                <input type="hidden" name="date_from" value="<?php echo esc_attr( (string) ( $filters['date_from'] ?? '' ) ); ?>" />
                <input type="hidden" name="date_to" value="<?php echo esc_attr( (string) ( $filters['date_to'] ?? '' ) ); ?>" />
                <input type="hidden" name="q" value="<?php echo esc_attr( (string) ( $filters['q'] ?? '' ) ); ?>" />
                <button class="button button-secondary" type="submit" onclick="return confirm('<?php echo esc_js( __( 'This anonymizes rows matching the current report filters. Continue?', 'khm-membership' ) ); ?>');"><?php esc_html_e( 'Anonymize Filtered Rows', 'khm-membership' ); ?></button>
            </form>

            <table class="widefat striped" cellspacing="0">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'ID', 'khm-membership' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Schedule', 'khm-membership' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'User', 'khm-membership' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Conversion', 'khm-membership' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'UTM', 'khm-membership' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Date', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $rows ) ) : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <?php $clean = $this->service->apply_consent_redaction( $row ); ?>
                            <tr>
                                <td><?php echo esc_html( (string) $row['id'] ); ?></td>
                                <td>
                                    <?php
                                    $schedule_id = isset( $row['schedule_id'] ) ? absint( $row['schedule_id'] ) : 0;
                                    $schedule_title = isset( $row['schedule_title'] ) ? trim( (string) $row['schedule_title'] ) : '';
                                    if ( $schedule_id > 0 ) {
                                        $label = $schedule_title ? sprintf( '%s (#%d)', $schedule_title, $schedule_id ) : sprintf( '#%d', $schedule_id );
                                        $schedule_link = get_edit_post_link( $schedule_id, '' );
                                        if ( $schedule_link ) {
                                            echo '<a href="' . esc_url( $schedule_link ) . '">' . esc_html( $label ) . '</a>';
                                        } else {
                                            echo esc_html( $label );
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $sponsor_id = isset( $row['sponsor_id'] ) ? absint( $row['sponsor_id'] ) : 0;
                                    $sponsor_name = isset( $row['sponsor_name'] ) ? trim( (string) $row['sponsor_name'] ) : '';
                                    if ( $sponsor_id > 0 ) {
                                        $label = $sponsor_name ? sprintf( '%s (#%d)', $sponsor_name, $sponsor_id ) : sprintf( '#%d', $sponsor_id );
                                        $sponsor_link = admin_url( 'admin.php?page=khm-sponsor-library' );
                                        echo '<a href="' . esc_url( $sponsor_link ) . '">' . esc_html( $label ) . '</a>';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $clean['user_id'] ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-members&s=' . rawurlencode( (string) ( $clean['user_email'] ?? '' ) ) ) ); ?>"><?php echo esc_html( (string) $clean['user_id'] ); ?></a><br />
                                        <?php echo esc_html( (string) ( $clean['user_email'] ?? '' ) ); ?>
                                    <?php else : ?>
                                        <em><?php esc_html_e( 'Redacted', 'khm-membership' ); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( (string) ( $row['conversion_type'] ?? '' ) ); ?></td>
                                <td>
                                    <?php
                                    $utm = trim( implode( ' / ', array_filter( [
                                        (string) ( $clean['utm_source'] ?? '' ),
                                        (string) ( $clean['utm_medium'] ?? '' ),
                                        (string) ( $clean['utm_campaign'] ?? '' ),
                                    ] ) ) );
                                    echo $utm !== '' ? esc_html( $utm ) : '—';
                                    ?>
                                </td>
                                <td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="7"><?php esc_html_e( 'No attribution data found.', 'khm-membership' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $pagination = paginate_links( [
                'base' => add_query_arg( 'paged', '%#%' ),
                'format' => '',
                'current' => (int) $report['page'],
                'total' => max( 1, (int) $report['total_pages'] ),
                'type' => 'array',
            ] );
            if ( is_array( $pagination ) && ! empty( $pagination ) ) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( implode( ' ', $pagination ) ) . '</div></div>';
            }
            ?>
        </div>
        <?php
    }

    public function handle_export(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( sprintf( 'unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-membership-reports-export' ) );
            wp_die( esc_html__( 'You do not have permission to export reports.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_membership_reports_export', 'khm_membership_reports_export_nonce' );

        $filters = $this->get_filters_from_post();
        $this->emit_telemetry( 'membership.export.started', [
            'user_id' => (int) get_current_user_id(),
            'filters' => $filters,
        ] );

        try {
            $export = $this->service->create_csv_export( $filters );
        } catch ( \Throwable $e ) {
            error_log( 'membership_export_failed message=' . $e->getMessage() );
            wp_die( esc_html__( 'Failed to generate report export.', 'khm-membership' ) );
        }

        $this->emit_telemetry( 'membership.export.completed', [
            'user_id' => (int) get_current_user_id(),
            'rows' => (int) $export['rows'],
            'redacted_rows' => (int) ( $export['redacted_rows'] ?? 0 ),
            'checksum' => (string) $export['checksum'],
            'filename' => (string) $export['filename'],
        ] );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( (string) $export['filename'] ) );
        header( 'X-KHM-Export-Checksum: ' . (string) $export['checksum'] );
        header( 'X-KHM-Export-Redacted: ' . (string) ( $export['redacted_rows'] ?? 0 ) );
        readfile( (string) $export['file'] );
        exit;
    }

    public function handle_anonymize(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( sprintf( 'unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-membership-reports-anonymize' ) );
            wp_die( esc_html__( 'You do not have permission to anonymize reports.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_membership_reports_anonymize', 'khm_membership_reports_anonymize_nonce' );
        $filters = $this->get_filters_from_post();

        $repository = new MembershipRepository();
        $mappedFilters = [
            'consent' => strpos( (string) ( $filters['conversion_type'] ?? '' ), 'no_consent' ) !== false ? 0 : null,
            'created_before' => ! empty( $filters['date_to'] ) ? (string) $filters['date_to'] . ' 23:59:59' : null,
        ];
        $mappedFilters = array_filter( $mappedFilters, static fn( $v ) => null !== $v );

        $result = $repository->anonymizeAttributionByFilters(
            $mappedFilters,
            (int) get_current_user_id(),
            'admin_reports_bulk',
            1000,
            false
        );

        MembershipCache::invalidate_all();

        $this->emit_telemetry( 'membership.anonymize.executed', [
            'user_id' => (int) get_current_user_id(),
            'matched' => (int) ( $result['matched'] ?? 0 ),
            'updated' => (int) ( $result['updated'] ?? 0 ),
        ] );

        wp_safe_redirect( admin_url( 'admin.php?page=khm-membership-reports&anonymized=' . (int) ( $result['updated'] ?? 0 ) ) );
        exit;
    }

    public function handle_retention_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( sprintf( 'unauthorized_admin_access user_id=%d resource=%s', (int) get_current_user_id(), 'khm-membership-retention-settings' ) );
            wp_die( esc_html__( 'You do not have permission to update retention settings.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_membership_retention_settings', 'khm_membership_retention_settings_nonce' );

        $days = isset( $_POST['khm_attribution_retention_days'] ) ? absint( $_POST['khm_attribution_retention_days'] ) : 730;
        $days = max( 1, $days );
        update_site_option( 'khm_attribution_retention_days', $days );
        MembershipCache::invalidate_all();

        $this->emit_telemetry( 'membership.retention.updated', [
            'user_id' => (int) get_current_user_id(),
            'retention_days' => $days,
        ] );

        wp_safe_redirect( admin_url( 'admin.php?page=khm-membership-reports&retention_updated=1' ) );
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    private function get_filters(): array {
        return [
            'schedule_id' => isset( $_GET['schedule_id'] ) ? absint( $_GET['schedule_id'] ) : 0,
            'sponsor_id' => isset( $_GET['sponsor_id'] ) ? absint( $_GET['sponsor_id'] ) : 0,
            'user_id' => isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0,
            'conversion_type' => isset( $_GET['conversion_type'] ) ? sanitize_key( wp_unslash( $_GET['conversion_type'] ) ) : '',
            'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
            'date_to' => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
            'q' => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function get_filters_from_post(): array {
        return [
            'schedule_id' => isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0,
            'sponsor_id' => isset( $_POST['sponsor_id'] ) ? absint( $_POST['sponsor_id'] ) : 0,
            'user_id' => isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0,
            'conversion_type' => isset( $_POST['conversion_type'] ) ? sanitize_key( wp_unslash( $_POST['conversion_type'] ) ) : '',
            'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
            'date_to' => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
            'q' => isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '',
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function emit_telemetry( string $metric, array $context = [] ): void {
        do_action( 'khm_membership_reporting_telemetry', $metric, $context );
        error_log( sprintf( 'membership_reporting metric=%s context=%s', $metric, wp_json_encode( $context ) ) );
    }

    public function invalidate_report_cache(): void {
        MembershipCache::invalidate_all();
    }
}

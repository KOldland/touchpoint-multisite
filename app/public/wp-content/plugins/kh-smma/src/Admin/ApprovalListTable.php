<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use function esc_attr;
use function esc_html;
use function esc_html_e;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ApprovalListTable {
    public function render( array $rows ): void {
        ?>
        <table class="widefat striped kh-smma-pending-approvals-table">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="kh-smma-select-all" /></th>
                    <th><?php esc_html_e( 'Schedule ID', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Post / Variant', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Sponsor', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Submitter', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Requested', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Approval Reason', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Compliance', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Last Review', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Approval Status', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'kh-smma' ); ?></th>
                </tr>
            </thead>
            <tbody id="kh-smma-approval-rows">
                <?php if ( empty( $rows ) ) : ?>
                    <tr>
                        <td colspan="10"><?php esc_html_e( 'No schedules found.', 'kh-smma' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rows as $row ) : ?>
                        <?php $can_approve = ! empty( $row['can_approve'] ); ?>
                        <?php $compliance_status = strtoupper( (string) ( $row['compliance_status'] ?? 'OK' ) ); ?>
                        <?php $is_compliance_fail = 'FAIL' === $compliance_status; ?>
                        <?php $approval_reason = (string) ( $row['approval_reason'] ?? '' ); ?>
                        <?php $permission_message = (string) ( $row['permission_message'] ?? 'You do not have permission to approve schedules for this sponsor.' ); ?>
                        <?php if ( $is_compliance_fail ) : ?>
                            <?php $permission_message = 'Compliance failure detected. Variant must be edited and pass compliance before approval.'; ?>
                        <?php endif; ?>
                        <tr data-schedule-id="<?php echo esc_attr( (string) $row['schedule_id'] ); ?>">
                            <td class="check-column">
                                <?php if ( $can_approve && ! $is_compliance_fail ) : ?>
                                    <input type="checkbox" class="kh-smma-row-checkbox" value="<?php echo esc_attr( (string) $row['schedule_id'] ); ?>" />
                                <?php else : ?>
                                    <span class="kh-smma-permission-tooltip" title="<?php echo esc_attr( $permission_message ); ?>">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( (string) $row['schedule_id'] ); ?></td>
                            <td class="kh-smma-approval-post-cell">
                                <strong><?php echo esc_html( (string) ( $row['post_title'] ?? '—' ) ); ?></strong>
                                <div class="kh-smma-approval-variant-id"><?php echo esc_html( (string) ( $row['variant_id'] ?? '' ) ); ?></div>
                                <?php if ( ! empty( $row['variant_preview'] ) ) : ?>
                                    <div class="kh-smma-approval-variant-preview"><?php echo esc_html( (string) $row['variant_preview'] ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( (string) $row['sponsor_name'] ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['submitter'] ?? '—' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['requested_schedule_date'] ?? '—' ) ); ?></td>
                            <td>
                                <?php if ( 'compliance_changed' === $approval_reason ) : ?>
                                    <span class="kh-smma-rereview-badge"><?php esc_html_e( 'Re-review: Compliance Change', 'kh-smma' ); ?></span>
                                <?php elseif ( 'sponsor_claim_change' === $approval_reason ) : ?>
                                    <span class="kh-smma-rereview-badge"><?php esc_html_e( 'Re-review: Claim Permission Change', 'kh-smma' ); ?></span>
                                <?php else : ?>
                                    <?php echo esc_html( '' !== $approval_reason ? $approval_reason : '—' ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="kh-smma-approval-badge kh-smma-approval-<?php echo esc_attr( strtolower( (string) ( $row['compliance_status'] ?? 'OK' ) ) ); ?>">
                                    <?php echo esc_html( strtoupper( (string) ( $row['compliance_status'] ?? 'OK' ) ) ); ?>
                                </span>
                                <?php if ( ! empty( $row['compliance_reason'] ) ) : ?>
                                    <div class="kh-smma-approval-compliance-reason"><?php echo esc_html( (string) $row['compliance_reason'] ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo esc_html( (string) ( $row['last_approved_by'] ?? '—' ) ); ?></div>
                                <div class="kh-smma-approval-last-reviewed"><?php echo esc_html( (string) ( $row['last_approved_at'] ?? '—' ) ); ?></div>
                            </td>
                            <td>
                                <span class="kh-smma-approval-badge kh-smma-approval-<?php echo esc_attr( (string) $row['approval_status'] ); ?>">
                                    <?php echo esc_html( ucfirst( (string) $row['approval_status'] ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $can_approve ) : ?>
                                    <button type="button" class="button button-primary kh-smma-review-action" data-action-type="approve" data-schedule-id="<?php echo esc_attr( (string) $row['schedule_id'] ); ?>" <?php echo $is_compliance_fail ? 'disabled title="' . esc_attr( $permission_message ) . '"' : ''; ?>>
                                        <?php esc_html_e( 'Approve', 'kh-smma' ); ?>
                                    </button>
                                    <button type="button" class="button kh-smma-review-action" data-action-type="reject" data-schedule-id="<?php echo esc_attr( (string) $row['schedule_id'] ); ?>">
                                        <?php esc_html_e( 'Reject', 'kh-smma' ); ?>
                                    </button>
                                    <?php if ( $is_compliance_fail ) : ?>
                                        <div class="kh-smma-permission-tooltip"><?php echo esc_html( $permission_message ); ?></div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="kh-smma-permission-tooltip" title="<?php echo esc_attr( $permission_message ); ?>"><?php esc_html_e( 'Read-only', 'kh-smma' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}

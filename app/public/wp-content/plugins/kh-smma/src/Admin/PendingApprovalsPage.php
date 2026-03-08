<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use KH_SMMA\SponsorApproval\ApprovalPermissionService;
use KH_SMMA\Scheduling\ScheduleRepository;
use function get_current_user_id;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function rest_url;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PendingApprovalsPage {
    private ScheduleRepository $repository;
    private ApprovalPermissionService $permissions;
    private ApprovalListTable $table;
    private string $page_hook = '';

    public function __construct( ScheduleRepository $repository, ?ApprovalPermissionService $permissions = null ) {
        $this->repository = $repository;
        $this->permissions = $permissions ?: new ApprovalPermissionService();
        $this->table      = new ApprovalListTable();
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_menu(): void {
        if ( ! $this->can_access() ) {
            return;
        }

        $this->page_hook = (string) add_submenu_page(
            'kh-smma-dashboard',
            __( 'Pending Sponsor Approvals', 'kh-smma' ),
            __( 'Pending Approvals', 'kh-smma' ),
            'read',
            'smma-pending-approvals',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_style(
            'kh-smma-sponsor-approval',
            KH_SMMA_URL . 'assets/css/sponsor-approval.css',
            array(),
            KH_SMMA_VERSION
        );

        wp_enqueue_script(
            'kh-smma-pending-approvals',
            KH_SMMA_URL . 'assets/js/pending-approvals.js',
            array( 'jquery' ),
            KH_SMMA_VERSION,
            true
        );

        wp_localize_script( 'kh-smma-pending-approvals', 'khSmmaSponsorApproval', array(
            'apiBase' => rest_url( 'kh-smma/v1/sponsor-approvals' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'pageUrl' => admin_url( 'admin.php?page=smma-pending-approvals' ),
            'messages' => array(
                'complianceFailBlocked' => 'Compliance failure detected. Variant must be edited and pass compliance before approval.',
                'approveSuccess'        => 'Approval decision saved and submitter notified.',
                'rejectSuccess'         => 'Rejection saved and submitter notified.',
                'bulkReviewHint'        => 'Bulk actions apply the same reviewer note to every selected schedule.',
            ),
            'permissions' => array(
                'can_manage_approvals' => $this->permissions->can_manage_approvals( get_current_user_id() ),
                'denied_message'       => $this->permissions->permission_denied_message(),
            ),
        ) );
    }

    public function render_page(): void {
        if ( ! $this->can_access() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        $filters = $this->current_filters();
        $filters = $this->permissions->enforce_sponsor_scope( $filters, get_current_user_id() );
        $result  = $this->repository->getPendingApprovals( $filters );
        $result['rows'] = array_map( function ( array $row ): array {
            $row['can_approve'] = $this->permissions->can_approve_schedule( $row, get_current_user_id() );
            $row['permission_message'] = $row['can_approve'] ? '' : $this->permissions->permission_denied_message();
            return $row;
        }, $result['rows'] );
        $sponsors = $this->repository->getSponsors( $result['rows'] );
        $can_manage = $this->permissions->can_manage_approvals( get_current_user_id() );
        ?>
        <div class="wrap kh-smma-pending-approvals">
            <h1><?php esc_html_e( 'Pending Sponsor Approvals', 'kh-smma' ); ?></h1>

            <form id="kh-smma-approval-filters" class="kh-smma-filter-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="smma-pending-approvals" />

                <label>
                    <?php esc_html_e( 'Sponsor', 'kh-smma' ); ?>
                    <select name="sponsor_id" id="kh-smma-filter-sponsor">
                        <option value=""><?php esc_html_e( 'All sponsors', 'kh-smma' ); ?></option>
                        <?php foreach ( $sponsors as $sponsor ) : ?>
                            <option value="<?php echo esc_attr( (string) $sponsor['id'] ); ?>" <?php echo (string) $filters['sponsor_id'] === (string) $sponsor['id'] ? 'selected' : ''; ?>>
                                <?php echo esc_html( (string) $sponsor['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <?php esc_html_e( 'Status', 'kh-smma' ); ?>
                    <select name="status" id="kh-smma-filter-status">
                        <option value="pending" <?php echo 'pending' === $filters['status'] ? 'selected' : ''; ?>><?php esc_html_e( 'Pending', 'kh-smma' ); ?></option>
                        <option value="approved" <?php echo 'approved' === $filters['status'] ? 'selected' : ''; ?>><?php esc_html_e( 'Approved', 'kh-smma' ); ?></option>
                        <option value="rejected" <?php echo 'rejected' === $filters['status'] ? 'selected' : ''; ?>><?php esc_html_e( 'Rejected', 'kh-smma' ); ?></option>
                        <option value="all" <?php echo 'all' === $filters['status'] ? 'selected' : ''; ?>><?php esc_html_e( 'All', 'kh-smma' ); ?></option>
                    </select>
                </label>

                <label>
                    <?php esc_html_e( 'From', 'kh-smma' ); ?>
                    <input type="date" name="date_from" value="<?php echo esc_attr( (string) $filters['date_from'] ); ?>" />
                </label>

                <label>
                    <?php esc_html_e( 'To', 'kh-smma' ); ?>
                    <input type="date" name="date_to" value="<?php echo esc_attr( (string) $filters['date_to'] ); ?>" />
                </label>

                <label class="kh-smma-filter-search">
                    <?php esc_html_e( 'Search', 'kh-smma' ); ?>
                    <input type="search" name="search_term" id="kh-smma-filter-search" placeholder="<?php esc_attr_e( 'Schedule ID or Post Title', 'kh-smma' ); ?>" value="<?php echo esc_attr( (string) $filters['search_term'] ); ?>" />
                </label>

                <button type="submit" class="button"><?php esc_html_e( 'Apply', 'kh-smma' ); ?></button>
            </form>

            <div class="kh-smma-bulk-actions" data-can-manage="<?php echo $can_manage ? '1' : '0'; ?>">
                <?php if ( $can_manage ) : ?>
                    <button type="button" class="button button-primary" id="kh-smma-bulk-approve"><?php esc_html_e( 'Approve Selected', 'kh-smma' ); ?></button>
                    <button type="button" class="button" id="kh-smma-bulk-reject"><?php esc_html_e( 'Reject Selected', 'kh-smma' ); ?></button>
                <?php else : ?>
                    <span class="kh-smma-permission-tooltip"><?php echo esc_html( $this->permissions->permission_denied_message() ); ?></span>
                <?php endif; ?>
            </div>

            <div id="kh-smma-approval-table-wrap" data-page="<?php echo esc_attr( (string) $result['page'] ); ?>" data-total-pages="<?php echo esc_attr( (string) $result['total_pages'] ); ?>">
                <?php $this->table->render( $result['rows'] ); ?>
            </div>

            <div class="kh-smma-pagination" id="kh-smma-pagination"></div>

            <div id="kh-smma-review-modal" class="kh-smma-review-modal" style="display:none;">
                <div class="kh-smma-review-modal__dialog">
                    <h2 id="kh-smma-review-title"><?php esc_html_e( 'Review Decision', 'kh-smma' ); ?></h2>
                    <p id="kh-smma-review-target"></p>
                    <textarea id="kh-smma-review-notes" rows="5" placeholder="<?php esc_attr_e( 'Reviewer notes', 'kh-smma' ); ?>"></textarea>
                    <p id="kh-smma-review-hint"></p>
                    <div class="kh-smma-review-modal__actions">
                        <button type="button" class="button button-primary" id="kh-smma-review-confirm"><?php esc_html_e( 'Confirm', 'kh-smma' ); ?></button>
                        <button type="button" class="button" id="kh-smma-review-cancel"><?php esc_html_e( 'Cancel', 'kh-smma' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function current_filters(): array {
        return array(
            'sponsor_id'  => sanitize_text_field( (string) ( $_GET['sponsor_id'] ?? '' ) ),
            'status'      => sanitize_text_field( (string) ( $_GET['status'] ?? 'pending' ) ),
            'date_from'   => sanitize_text_field( (string) ( $_GET['date_from'] ?? '' ) ),
            'date_to'     => sanitize_text_field( (string) ( $_GET['date_to'] ?? '' ) ),
            'search_term' => sanitize_text_field( (string) ( $_GET['search_term'] ?? '' ) ),
            'page'        => (int) ( $_GET['paged'] ?? 1 ),
            'per_page'    => 25,
        );
    }

    private function can_access(): bool {
        return current_user_can( 'manage_sponsors' )
            || current_user_can( 'edit_schedules' )
            || current_user_can( 'administrator' )
            || current_user_can( 'manage_options' );
    }
}

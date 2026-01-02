<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Services\TokenRepository;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\AnalyticsFeedbackService;
use KH_SMMA\Services\LifecycleSimulator;
use KH_SMMA\Security\CapabilityManager;
use WP_Query;

use function __;
use function absint;
use function add_action;
use function add_menu_page;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function get_the_title;
use function get_current_user_id;
use function get_userdata;
use function get_post_meta;
use function get_posts;
use function is_wp_error;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function in_array;
use function strtotime;
use function submit_button;
use function time;
use function update_post_meta;
use function wp_date;
use function wp_die;
use function wp_insert_post;
use function wp_nonce_field;
use function wp_reset_postdata;
use function wp_safe_redirect;
use function wp_json_encode;
use function number_format_i18n;
use const DAY_IN_SECONDS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminInterface {
    private TokenRepository $tokens;
    private AuditLogger $logger;
    private AnalyticsFeedbackService $analytics;
    private LifecycleSimulator $simulator;

    public function __construct( TokenRepository $tokens, AuditLogger $logger, AnalyticsFeedbackService $analytics, LifecycleSimulator $simulator ) {
        $this->tokens     = $tokens;
        $this->logger     = $logger;
        $this->analytics  = $analytics;
        $this->simulator  = $simulator;
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_kh_smma_connect_account', array( $this, 'handle_account_connect' ) );
        add_action( 'admin_post_kh_smma_schedule_post', array( $this, 'handle_schedule_post' ) );
        add_action( 'admin_post_kh_smma_toggle_sandbox', array( $this, 'handle_toggle_sandbox' ) );
        add_action( 'admin_post_kh_smma_toggle_approval_requirement', array( $this, 'handle_toggle_approval_requirement' ) );
        add_action( 'admin_post_kh_smma_approve_schedule', array( $this, 'handle_schedule_approve' ) );
        add_action( 'admin_post_kh_smma_deny_schedule', array( $this, 'handle_schedule_deny' ) );
        add_action( 'admin_post_kh_smma_simulate_lifecycle', array( $this, 'handle_simulate_lifecycle' ) );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'KH Social Manager', 'kh-smma' ),
            __( 'KH Social', 'kh-smma' ),
            CapabilityManager::CAP_VIEW,
            'kh-smma-dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-share-alt2',
            27
        );
    }

    public function render_dashboard(): void {
        if ( ! CapabilityManager::can_view() ) {
            wp_die( esc_html__( 'You do not have permission to view this dashboard.', 'kh-smma' ) );
        }

        $accounts          = get_posts( array( 'post_type' => 'kh_smma_account', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        $campaigns         = get_posts( array( 'post_type' => 'kh_smma_campaign', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        $queue             = get_posts( array( 'post_type' => 'kh_smma_schedule', 'numberposts' => 10, 'orderby' => 'date', 'order' => 'DESC' ) );
        $library_assets    = apply_filters( 'kh_smma_marketing_assets', array() );
        $analytics_snapshot = $this->analytics->get_snapshot();
        ?>
        <div class="wrap kh-smma-admin">
            <h1><?php esc_html_e( 'KH Social Media Management & Automation', 'kh-smma' ); ?></h1>
            <p><?php esc_html_e( 'Connect accounts, schedule posts, and monitor queue health.', 'kh-smma' ); ?></p>

            <h2><?php esc_html_e( '1. Connect Social Account', 'kh-smma' ); ?></h2>
            <?php if ( CapabilityManager::can_manage_accounts() ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kh-smma-form">
                    <?php wp_nonce_field( 'kh_smma_connect_account' ); ?>
                    <input type="hidden" name="action" value="kh_smma_connect_account" />
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="kh-smma-account-name"><?php esc_html_e( 'Account Label', 'kh-smma' ); ?></label></th>
                            <td><input type="text" id="kh-smma-account-name" name="account_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kh-smma-provider"><?php esc_html_e( 'Provider', 'kh-smma' ); ?></label></th>
                            <td>
                                <select id="kh-smma-provider" name="provider" required>
                                    <option value="manual">Manual Export</option>
                                    <option value="meta">Meta (FB/Instagram)</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="twitter">X / Twitter</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kh-smma-token"><?php esc_html_e( 'Access Token / Notes', 'kh-smma' ); ?></label></th>
                            <td><textarea id="kh-smma-token" name="token" class="large-text" rows="3" placeholder="Store API tokens or connection notes (stored encrypted when possible)"></textarea></td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save Account', 'kh-smma' ) ); ?>
                </form>
            <?php else : ?>
                <p><?php esc_html_e( 'Account management is restricted. Please contact an administrator.', 'kh-smma' ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $accounts ) ) : ?>
                <h3><?php esc_html_e( 'Connected Accounts', 'kh-smma' ); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Account', 'kh-smma' ); ?></th>
                            <th><?php esc_html_e( 'Provider', 'kh-smma' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'kh-smma' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $accounts as $account ) :
                            $provider = get_post_meta( $account->ID, '_kh_smma_provider', true );
                            $status   = get_post_meta( $account->ID, '_kh_smma_status', true );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $account->post_title ); ?></td>
                                <td><?php echo esc_html( ucfirst( $provider ?: 'manual' ) ); ?></td>
                                <td><span class="kh-smma-status kh-smma-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ?: 'disconnected' ) ); ?></span></td>
                                <td>
                                    <?php if ( CapabilityManager::can_manage_accounts() && 'manual' !== $provider ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'kh_smma_oauth_start' ); ?>
                                            <input type="hidden" name="action" value="kh_smma_oauth_start" />
                                            <input type="hidden" name="account_id" value="<?php echo esc_attr( $account->ID ); ?>" />
                                            <input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>" />
                                            <?php submit_button( __( 'Reconnect', 'kh-smma' ), 'secondary', '', false ); ?>
                                        </form>
                                    <?php else : ?>
                                        <?php esc_html_e( 'Manual export only', 'kh-smma' ); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <hr />

            <h2><?php esc_html_e( '2. Quick Schedule', 'kh-smma' ); ?></h2>
            <?php if ( CapabilityManager::can_schedule() ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'kh_smma_schedule_post' ); ?>
                    <input type="hidden" name="action" value="kh_smma_schedule_post" />
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Schedule Title', 'kh-smma' ); ?></th>
                            <td><input type="text" name="schedule_title" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Account', 'kh-smma' ); ?></th>
                            <td>
                                <select name="schedule_account" required>
                                    <option value=""><?php esc_html_e( 'Select account', 'kh-smma' ); ?></option>
                                    <?php foreach ( $accounts as $account ) : ?>
                                        <option value="<?php echo esc_attr( $account->ID ); ?>"><?php echo esc_html( $account->post_title ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Campaign', 'kh-smma' ); ?></th>
                            <td>
                                <select name="schedule_campaign">
                                    <option value=""><?php esc_html_e( 'Optional campaign', 'kh-smma' ); ?></option>
                                    <?php foreach ( $campaigns as $campaign ) : ?>
                                        <option value="<?php echo esc_attr( $campaign->ID ); ?>"><?php echo esc_html( $campaign->post_title ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Message', 'kh-smma' ); ?></th>
                            <td><textarea name="schedule_message" rows="4" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Marketing Asset', 'kh-smma' ); ?></th>
                            <td>
                                <select name="marketing_asset">
                                    <option value=""><?php esc_html_e( 'None', 'kh-smma' ); ?></option>
                                    <?php foreach ( $library_assets as $asset_id => $asset_label ) : ?>
                                        <option value="<?php echo esc_attr( $asset_id ); ?>"><?php echo esc_html( $asset_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Pull pre-approved copy from Marketing Suite library.', 'kh-smma' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Scheduled Time', 'kh-smma' ); ?></th>
                            <td><input type="datetime-local" name="schedule_time" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Delivery Mode', 'kh-smma' ); ?></th>
                            <td>
                                <select name="delivery_mode">
                                    <option value="auto"><?php esc_html_e( 'Auto publish via API', 'kh-smma' ); ?></option>
                                    <option value="manual_export"><?php esc_html_e( 'Manual export queue', 'kh-smma' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Queue Post', 'kh-smma' ) ); ?>
                </form>
            <?php else : ?>
                <p><?php esc_html_e( 'You can view the queue but do not have permission to schedule posts.', 'kh-smma' ); ?></p>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Account QA Controls', 'kh-smma' ); ?></h2>
            <table class="widefat kh-smma-accounts">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Account', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Provider', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Sandbox Mode', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Approval Required', 'kh-smma' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $accounts ) ) : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'No accounts connected yet.', 'kh-smma' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $accounts as $account ) :
                        $provider   = get_post_meta( $account->ID, '_kh_smma_provider', true );
                        $is_sandbox = (bool) get_post_meta( $account->ID, '_kh_smma_sandbox_mode', true );
                        $requires_approval = (bool) get_post_meta( $account->ID, '_kh_smma_require_approval', true );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $account->post_title ); ?></strong></td>
                            <td><?php echo esc_html( ucfirst( $provider ?: 'manual' ) ); ?></td>
                            <td>
                                <?php if ( CapabilityManager::can_manage_accounts() ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'kh_smma_toggle_sandbox' ); ?>
                                        <input type="hidden" name="action" value="kh_smma_toggle_sandbox" />
                                        <input type="hidden" name="account_id" value="<?php echo esc_attr( $account->ID ); ?>" />
                                        <input type="hidden" name="sandbox_enabled" value="<?php echo $is_sandbox ? '0' : '1'; ?>" />
                                        <button type="submit" class="button <?php echo $is_sandbox ? 'button-secondary' : 'button-primary'; ?>">
                                            <?php echo $is_sandbox ? esc_html__( 'Disable Sandbox', 'kh-smma' ) : esc_html__( 'Enable Sandbox', 'kh-smma' ); ?>
                                        </button>
                                    </form>
                                    <p class="description">
                                        <?php
                                        echo $is_sandbox
                                            ? esc_html__( 'Sandbox enabled – live API calls are paused for this account.', 'kh-smma' )
                                            : esc_html__( 'Sandbox disabled – live API calls will run for this account.', 'kh-smma' );
                                        ?>
                                    </p>
                                <?php else : ?>
                                    <?php echo $is_sandbox ? esc_html__( 'Enabled', 'kh-smma' ) : esc_html__( 'Disabled', 'kh-smma' ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( CapabilityManager::can_manage_accounts() ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'kh_smma_toggle_approval_requirement' ); ?>
                                        <input type="hidden" name="action" value="kh_smma_toggle_approval_requirement" />
                                        <input type="hidden" name="account_id" value="<?php echo esc_attr( $account->ID ); ?>" />
                                        <input type="hidden" name="require_approval" value="<?php echo $requires_approval ? '0' : '1'; ?>" />
                                        <button type="submit" class="button <?php echo $requires_approval ? 'button-secondary' : 'button-primary'; ?>">
                                            <?php echo $requires_approval ? esc_html__( 'Disable Approval', 'kh-smma' ) : esc_html__( 'Require Approval', 'kh-smma' ); ?>
                                        </button>
                                    </form>
                                    <p class="description">
                                        <?php
                                        echo $requires_approval
                                            ? esc_html__( 'Approval workflow enforced before dispatch.', 'kh-smma' )
                                            : esc_html__( 'Schedules auto-dispatch without approval.', 'kh-smma' );
                                        ?>
                                    </p>
                                <?php else : ?>
                                    <?php echo $requires_approval ? esc_html__( 'Enabled', 'kh-smma' ) : esc_html__( 'Disabled', 'kh-smma' ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <hr />

            <h2><?php esc_html_e( 'Queue Snapshot', 'kh-smma' ); ?></h2>
            <table class="widefat kh-smma-queue">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Scheduled For', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Account', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Approval', 'kh-smma' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $queue ) ) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e( 'No scheduled posts yet.', 'kh-smma' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $queue as $item ) :
                            $scheduled_at = get_post_meta( $item->ID, '_kh_smma_scheduled_at', true );
                            $status       = get_post_meta( $item->ID, '_kh_smma_schedule_status', true );
                            $account_id   = get_post_meta( $item->ID, '_kh_smma_account_id', true );
                            $approval_status = get_post_meta( $item->ID, '_kh_smma_approval_status', true );
                            $approval_note   = get_post_meta( $item->ID, '_kh_smma_approval_note', true );
                            $approved_by     = get_post_meta( $item->ID, '_kh_smma_approved_by', true );
                            $approved_at     = get_post_meta( $item->ID, '_kh_smma_approved_at', true );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $scheduled_at ? wp_date( 'Y-m-d H:i', (int) $scheduled_at ) : '—' ); ?></td>
                                <td><strong><?php echo esc_html( $item->post_title ); ?></strong></td>
                                <td><?php echo esc_html( $account_id ? get_the_title( $account_id ) : '—' ); ?></td>
                                <td><span class="kh-smma-status kh-smma-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></span></td>
                                <td>
                                    <?php if ( 'pending_approval' === $status ) : ?>
                                        <?php if ( CapabilityManager::can_manage_accounts() ) : ?>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kh-smma-inline-form">
                                                <?php wp_nonce_field( 'kh_smma_approve_schedule' ); ?>
                                                <input type="hidden" name="action" value="kh_smma_approve_schedule" />
                                                <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $item->ID ); ?>" />
                                                <input type="text" name="approval_note" class="regular-text" placeholder="<?php esc_attr_e( 'Note (optional)', 'kh-smma' ); ?>">
                                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'kh-smma' ); ?></button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kh-smma-inline-form">
                                                <?php wp_nonce_field( 'kh_smma_deny_schedule' ); ?>
                                                <input type="hidden" name="action" value="kh_smma_deny_schedule" />
                                                <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $item->ID ); ?>" />
                                                <input type="text" name="approval_note" class="regular-text" placeholder="<?php esc_attr_e( 'Reason for denial', 'kh-smma' ); ?>">
                                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Deny', 'kh-smma' ); ?></button>
                                            </form>
                                            <p class="description"><?php esc_html_e( 'Approval decisions immediately land in telemetry so analytics, sandbox logs, and audit trails stay aligned.', 'kh-smma' ); ?></p>
                                        <?php else : ?>
                                            <p><?php esc_html_e( 'Awaiting approval.', 'kh-smma' ); ?></p>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <?php if ( $approval_status ) : ?>
                                            <p><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $approval_status ) ) ); ?></strong></p>
                                        <?php endif; ?>
                                        <?php if ( $approval_note ) : ?>
                                            <p class="description"><?php echo esc_html( $approval_note ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( $approved_by ) :
                                            $user = get_userdata( (int) $approved_by );
                                            $name = $user ? $user->display_name : sprintf( __( 'User #%d', 'kh-smma' ), (int) $approved_by );
                                            ?>
                                            <p class="description">
                                                <?php
                                                printf(
                                                    esc_html__( 'By %1$s on %2$s', 'kh-smma' ),
                                                    esc_html( $name ),
                                                    $approved_at ? wp_date( 'Y-m-d H:i', (int) $approved_at ) : '—'
                                                );
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Calendar (Next 7 Days)', 'kh-smma' ); ?></h2>
            <table class="widefat kh-smma-calendar">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Scheduled Posts', 'kh-smma' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $this->get_calendar_slots() as $slot ) : ?>
                        <tr>
                            <td><?php echo esc_html( $slot['label'] ); ?></td>
                            <td><?php echo esc_html( $slot['count'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Preview Dispatch Telemetry', 'kh-smma' ); ?></h2>
            <p><?php esc_html_e( 'Review the latest sandbox previews, approval decisions, and live API telemetry captured during dispatch.', 'kh-smma' ); ?></p>
            <table class="widefat kh-smma-telemetry">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Scheduled For', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Mode', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Telemetry Snapshot', 'kh-smma' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php $telemetry_items = $this->get_recent_telemetry_items(); ?>
                <?php if ( empty( $telemetry_items ) ) : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'No telemetry captured yet.', 'kh-smma' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $telemetry_items as $entry ) :
                        $telemetry    = $entry['telemetry'];
                        $schedule     = $entry['post'];
                        $scheduled_at = get_post_meta( $schedule->ID, '_kh_smma_scheduled_at', true );
                        $mode         = $telemetry['mode'] ?? 'live';
                        $summary      = '';
                        if ( ! empty( $telemetry['error'] ) ) {
                            $summary = $telemetry['error'];
                        } elseif ( ! empty( $telemetry['note'] ) ) {
                            $summary = $telemetry['note'];
                        } elseif ( ! empty( $telemetry['response_code'] ) ) {
                            $summary = sprintf( __( 'Response code: %s', 'kh-smma' ), $telemetry['response_code'] );
                        }
                        $payload_preview = $telemetry['payload_preview'] ?? ( $telemetry['request']['body'] ?? '' );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $scheduled_at ? wp_date( 'Y-m-d H:i', (int) $scheduled_at ) : '—' ); ?></td>
                            <td><strong><?php echo esc_html( $schedule->post_title ); ?></strong></td>
                            <td><?php echo esc_html( ucfirst( $mode ) ); ?></td>
                            <td>
                                <?php if ( $summary ) : ?>
                                    <p><?php echo esc_html( $summary ); ?></p>
                                <?php endif; ?>
                                <?php if ( ! empty( $payload_preview ) ) : ?>
                                    <pre><?php echo esc_html( wp_json_encode( $payload_preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Analytics Feedback Loop', 'kh-smma' ); ?></h2>
            <p><?php esc_html_e( 'High-level signal of how each provider and campaign is performing based on telemetry and API responses.', 'kh-smma' ); ?></p>
            <?php if ( CapabilityManager::can_manage_accounts() && ! empty( $accounts ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kh-smma-inline-form">
                    <?php wp_nonce_field( 'kh_smma_simulate_lifecycle' ); ?>
                    <input type="hidden" name="action" value="kh_smma_simulate_lifecycle" />
                    <label>
                        <?php esc_html_e( 'Account for demo run:', 'kh-smma' ); ?>
                        <select name="account_id">
                            <option value="0"><?php esc_html_e( 'Auto-select', 'kh-smma' ); ?></option>
                            <?php foreach ( $accounts as $account ) : ?>
                                <option value="<?php echo esc_attr( $account->ID ); ?>"><?php echo esc_html( $account->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="button"><?php esc_html_e( 'Run Lifecycle Demo', 'kh-smma' ); ?></button>
                </form>
            <?php endif; ?>
            <p class="description">
                <?php
                printf(
                    wp_kses_post( __( 'Tip: Run <code>wp kh-smma lifecycle-sim</code> to seed the same telemetry stream from WP-CLI when CI, QA, or staging jobs need analytics data without provider tokens.', 'kh-smma' ) )
                );
                ?>
            </p>
            <div class="kh-smma-analytics-grid">
                <div class="kh-smma-analytics-card">
                    <h3><?php esc_html_e( 'Provider Summary', 'kh-smma' ); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Provider', 'kh-smma' ); ?></th>
                                <th><?php esc_html_e( 'Completed', 'kh-smma' ); ?></th>
                                <th><?php esc_html_e( 'Failed', 'kh-smma' ); ?></th>
                                <th><?php esc_html_e( 'Sandboxed', 'kh-smma' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $analytics_snapshot['provider_summary'] ) ) : ?>
                            <tr>
                                <td colspan="4"><?php esc_html_e( 'No analytics data captured yet.', 'kh-smma' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $analytics_snapshot['provider_summary'] as $provider => $stats ) : ?>
                                <tr>
                                    <td><?php echo esc_html( ucfirst( $provider ) ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( $stats['completed'] ?? 0 ) ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( $stats['failed'] ?? 0 ) ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( $stats['sandboxed'] ?? 0 ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="kh-smma-analytics-card">
                    <h3><?php esc_html_e( 'Recent Feedback', 'kh-smma' ); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Time', 'kh-smma' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
                                <th><?php esc_html_e( 'Account / Campaign', 'kh-smma' ); ?></th>
                                <th><?php esc_html_e( 'Notes', 'kh-smma' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $analytics_snapshot['recent_events'] ) ) : ?>
                            <tr>
                                <td colspan="4"><?php esc_html_e( 'No feedback captured yet.', 'kh-smma' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $analytics_snapshot['recent_events'] as $event ) :
                                $time_label = ! empty( $event['processed_at'] ) ? wp_date( 'Y-m-d H:i', (int) $event['processed_at'] ) : wp_date( 'Y-m-d H:i', (int) $event['timestamp'] );
                                $note       = $event['telemetry']['note'] ?? ( $event['error'] ?? '' );
                                if ( empty( $note ) && ! empty( $event['telemetry']['error'] ) ) {
                                    $note = $event['telemetry']['error'];
                                }
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $time_label ); ?></td>
                                    <td><span class="kh-smma-status kh-smma-status-<?php echo esc_attr( $event['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $event['status'] ) ) ); ?></span></td>
                                    <td>
                                        <?php echo esc_html( $event['account_label'] ?: __( 'Unknown Account', 'kh-smma' ) ); ?>
                                        <?php if ( $event['campaign_label'] ) : ?>
                                            <br /><span class="description"><?php echo esc_html( $event['campaign_label'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $note ) : ?>
                                            <p><?php echo esc_html( $note ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $event['telemetry']['response_code'] ) ) : ?>
                                            <p class="description"><?php printf( esc_html__( 'Response: %s', 'kh-smma' ), esc_html( $event['telemetry']['response_code'] ) ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $event['metrics']['metrics'] ) ) : ?>
                                            <p class="description">
                                                <?php
                                                $metric_snippets = array();
                                                foreach ( $event['metrics']['metrics'] as $metric_key => $metric_value ) {
                                                    $metric_snippets[] = sprintf(
                                                        '%s: %s',
                                                        esc_html( ucfirst( $metric_key ) ),
                                                        esc_html( number_format_i18n( (int) $metric_value ) )
                                                    );
                                                }
                                                echo esc_html( implode( ' | ', $metric_snippets ) );
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_account_connect(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_connect_account' );

        $account_name = sanitize_text_field( $_POST['account_name'] ?? '' );
        $provider     = sanitize_text_field( $_POST['provider'] ?? '' );
        $token        = sanitize_textarea_field( $_POST['token'] ?? '' );

        $allowed_providers = array( 'manual', 'meta', 'linkedin', 'twitter' );
        if ( ! in_array( $provider, $allowed_providers, true ) ) {
            $provider = 'manual';
        }

        $post_id = wp_insert_post( array(
            'post_type'   => 'kh_smma_account',
            'post_title'  => $account_name,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_die( esc_html( $post_id->get_error_message() ) );
        }

        update_post_meta( $post_id, '_kh_smma_provider', $provider );
        update_post_meta( $post_id, '_kh_smma_status', 'connected' );
        update_post_meta( $post_id, '_kh_smma_credentials', array( 'notes' => $token ) );

        if ( ! empty( $token ) ) {
            $token_id = $this->tokens->save_token( $post_id, array(
                'provider' => $provider,
                'token'    => $token,
            ) );
            update_post_meta( $post_id, '_kh_smma_token_id', $token_id );
        } elseif ( 'manual' !== $provider ) {
            update_post_meta( $post_id, '_kh_smma_status', 'oauth_required' );
        }

        $this->logger->log( 'account_connected', array(
            'object_type' => 'account',
            'object_id'   => $post_id,
            'details'     => array( 'provider' => $provider ),
        ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'account-connected' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_schedule_post(): void {
        if ( ! CapabilityManager::can_schedule() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_schedule_post' );

        $title           = sanitize_text_field( $_POST['schedule_title'] ?? '' );
        $account_id      = absint( $_POST['schedule_account'] ?? 0 );
        $campaign_id     = absint( $_POST['schedule_campaign'] ?? 0 );
        $message         = sanitize_textarea_field( $_POST['schedule_message'] ?? '' );
        $time_input      = sanitize_text_field( $_POST['schedule_time'] ?? '' );
        $delivery        = sanitize_text_field( $_POST['delivery_mode'] ?? 'auto' );
        $marketing_asset = sanitize_text_field( $_POST['marketing_asset'] ?? '' );

        if ( empty( $account_id ) || empty( $time_input ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'missing-fields' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $timestamp = strtotime( $time_input );
        if ( ! $timestamp || $timestamp < time() ) {
            $timestamp = time();
        }

        if ( strlen( $message ) < 5 ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'message-too-short' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $allowed_delivery = array( 'auto', 'manual_export' );
        if ( ! in_array( $delivery, $allowed_delivery, true ) ) {
            $delivery = 'auto';
        }

        $asset_content = null;
        if ( $marketing_asset ) {
            $resolved = apply_filters( 'kh_smma_resolve_asset_content', '', $marketing_asset );
            if ( is_array( $resolved ) ) {
                $asset_content = $resolved;
            } elseif ( is_string( $resolved ) && $resolved !== '' ) {
                $asset_content = array( 'message' => $resolved );
            }
        }

        $schedule_id = wp_insert_post( array(
            'post_type'   => 'kh_smma_schedule',
            'post_title'  => $title,
            'post_content'=> $message,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $schedule_id ) ) {
            wp_die( esc_html( $schedule_id->get_error_message() ) );
        }

        $requires_approval = $account_id ? (bool) get_post_meta( $account_id, '_kh_smma_require_approval', true ) : false;

        update_post_meta( $schedule_id, '_kh_smma_account_id', $account_id );
        update_post_meta( $schedule_id, '_kh_smma_campaign_id', $campaign_id );
        $payload = array( 'message' => $message );
        if ( $asset_content ) {
            $payload['asset'] = $asset_content;
        }

        update_post_meta( $schedule_id, '_kh_smma_payload', $payload );
        $status = $requires_approval ? 'pending_approval' : 'pending';
        update_post_meta( $schedule_id, '_kh_smma_scheduled_at', $timestamp );
        update_post_meta( $schedule_id, '_kh_smma_delivery_mode', $delivery );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', $status );
        update_post_meta( $schedule_id, '_kh_smma_approval_status', $requires_approval ? 'requested' : 'auto_approved' );
        if ( ! $requires_approval ) {
            update_post_meta( $schedule_id, '_kh_smma_approved_by', get_current_user_id() );
            update_post_meta( $schedule_id, '_kh_smma_approved_at', time() );
            update_post_meta( $schedule_id, '_kh_smma_approval_note', '' );
        }

        $this->logger->log( 'schedule_created', array(
            'object_type' => 'schedule',
            'object_id'   => $schedule_id,
            'details'     => array(
                'account_id'  => $account_id,
                'campaign_id' => $campaign_id,
                'delivery'    => $delivery,
                'scheduled_at'=> $timestamp,
                'requires_approval' => $requires_approval,
            ),
        ) );

        $message = $requires_approval ? 'awaiting-approval' : 'scheduled';
        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => $message ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_toggle_sandbox(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_toggle_sandbox' );

        $account_id = absint( $_POST['account_id'] ?? 0 );
        $enabled    = ! empty( $_POST['sandbox_enabled'] ) ? (bool) absint( $_POST['sandbox_enabled'] ) : false;

        if ( ! $account_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'missing-account' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        update_post_meta( $account_id, '_kh_smma_sandbox_mode', $enabled );

        $this->logger->log( 'account_sandbox_toggled', array(
            'object_type' => 'account',
            'object_id'   => $account_id,
            'details'     => array(
                'sandbox_enabled' => $enabled,
            ),
        ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'sandbox-updated' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_toggle_approval_requirement(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_toggle_approval_requirement' );

        $account_id = absint( $_POST['account_id'] ?? 0 );
        $required   = ! empty( $_POST['require_approval'] ) ? (bool) absint( $_POST['require_approval'] ) : false;

        if ( ! $account_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'missing-account' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        update_post_meta( $account_id, '_kh_smma_require_approval', $required );

        $this->logger->log( 'account_require_approval_toggled', array(
            'object_type' => 'account',
            'object_id'   => $account_id,
            'details'     => array(
                'require_approval' => $required,
            ),
        ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'approval-toggle-updated' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_schedule_approve(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_approve_schedule' );

        $schedule_id = absint( $_POST['schedule_id'] ?? 0 );
        $note        = sanitize_text_field( $_POST['approval_note'] ?? '' );

        if ( ! $schedule_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'missing-schedule' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'pending' );
        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'approved' );
        update_post_meta( $schedule_id, '_kh_smma_approved_by', get_current_user_id() );
        update_post_meta( $schedule_id, '_kh_smma_approved_at', time() );
        update_post_meta( $schedule_id, '_kh_smma_approval_note', $note );
        update_post_meta( $schedule_id, '_kh_smma_last_error', '' );

        $this->logger->log( 'schedule_approved', array(
            'object_type' => 'schedule',
            'object_id'   => $schedule_id,
            'details'     => array(
                'note' => $note,
            ),
        ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'schedule-approved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_schedule_deny(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_deny_schedule' );

        $schedule_id = absint( $_POST['schedule_id'] ?? 0 );
        $note        = sanitize_text_field( $_POST['approval_note'] ?? '' );

        if ( ! $schedule_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'missing-schedule' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'denied' );
        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'denied' );
        update_post_meta( $schedule_id, '_kh_smma_approved_by', get_current_user_id() );
        update_post_meta( $schedule_id, '_kh_smma_approved_at', time() );
        update_post_meta( $schedule_id, '_kh_smma_approval_note', $note );
        update_post_meta( $schedule_id, '_kh_smma_last_error', $note ? sprintf( __( 'Approval denied: %s', 'kh-smma' ), $note ) : __( 'Approval denied.', 'kh-smma' ) );

        $this->logger->log( 'schedule_denied', array(
            'object_type' => 'schedule',
            'object_id'   => $schedule_id,
            'details'     => array(
                'note' => $note,
            ),
        ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'schedule-denied' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_simulate_lifecycle(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_simulate_lifecycle' );

        $account_id = absint( $_POST['account_id'] ?? 0 );
        $result     = $this->simulator->run( $account_id );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'lifecycle-error', 'error' => rawurlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'lifecycle-complete' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function get_calendar_slots(): array {
        $slots = array();
        $start = strtotime( 'today midnight' );

        for ( $i = 0; $i < 7; $i++ ) {
            $day_start = $start + ( DAY_IN_SECONDS * $i );
            $day_end   = $day_start + DAY_IN_SECONDS;

            $query = new WP_Query( array(
                'post_type'      => 'kh_smma_schedule',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_kh_smma_scheduled_at',
                        'value'   => array( $day_start, $day_end ),
                        'compare' => 'BETWEEN',
                        'type'    => 'NUMERIC',
                    ),
                ),
            ) );

            $slots[] = array(
                'label' => wp_date( 'l, M j', $day_start ),
                'count' => (int) $query->found_posts,
            );

            wp_reset_postdata();
        }

        return $slots;
    }

    private function get_recent_telemetry_items(): array {
        $posts = get_posts( array(
            'post_type'      => 'kh_smma_schedule',
            'numberposts'    => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => '_kh_smma_last_telemetry',
                    'compare' => 'EXISTS',
                ),
            ),
        ) );

        $items = array();
        foreach ( $posts as $post ) {
            $items[] = array(
                'post'      => $post,
                'telemetry' => get_post_meta( $post->ID, '_kh_smma_last_telemetry', true ),
            );
        }

        return $items;
    }
}

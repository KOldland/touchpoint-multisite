<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Compliance\ComplianceRulesStore;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Sponsor\ApprovalPermissionService;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function explode;
use function get_current_user_id;
use function sanitize_text_field;
use function submit_button;
use function wp_die;
use function wp_nonce_field;
use function wp_safe_redirect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SponsorClaimsPage {
    private ComplianceRulesStore $store;
    private AuditLogger $audit;
    private ApprovalPermissionService $permissions;

    public function __construct( ?ComplianceRulesStore $store = null, ?AuditLogger $audit = null, ?ApprovalPermissionService $permissions = null ) {
        global $wpdb;

        $this->store       = $store ?: new ComplianceRulesStore();
        $this->audit       = $audit ?: new AuditLogger( $wpdb );
        $this->permissions = $permissions ?: new ApprovalPermissionService();
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_kh_smma_sponsor_claims_update', array( $this, 'handle_update' ) );
    }

    public function register_menu(): void {
        add_submenu_page(
            'kh-smma-dashboard',
            __( 'Sponsor Allowed Claims', 'kh-smma' ),
            __( 'Sponsor Claims', 'kh-smma' ),
            'read',
            'kh-smma-sponsor-claims',
            array( $this, 'render' )
        );
    }

    public function render(): void {
        if ( ! $this->permissions->can_view_compliance_audit() && ! $this->permissions->can_manage_banned_phrases() ) {
            wp_die( esc_html__( 'You do not have permission to view sponsor claims.', 'kh-smma' ) );
        }

        $sponsor_id = (int) ( $_GET['sponsor_id'] ?? 0 );
        $claims_row = $sponsor_id > 0 ? $this->store->get_sponsor_claims( $sponsor_id ) : array(
            'allowed_claims' => array(),
            'updated_at' => '',
            'updated_by' => 0,
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Sponsor Allowed Claims', 'kh-smma' ); ?></h1>
            <p><?php echo esc_html__( 'Define sponsor-safe claims used by compliance validation.', 'kh-smma' ); ?></p>

            <form method="get" action="<?php echo esc_attr( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="kh-smma-sponsor-claims" />
                <label>
                    <?php echo esc_html__( 'Sponsor ID', 'kh-smma' ); ?>
                    <input type="number" min="1" name="sponsor_id" value="<?php echo esc_attr( (string) $sponsor_id ); ?>" required />
                </label>
                <?php submit_button( __( 'Load', 'kh-smma' ), 'secondary', 'submit', false ); ?>
            </form>

            <?php if ( $sponsor_id > 0 ) : ?>
                <?php if ( ! $this->permissions->can_manage_sponsor_claims( $sponsor_id ) ) : ?>
                    <p><?php echo esc_html__( 'You cannot edit claims for this sponsor.', 'kh-smma' ); ?></p>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Updating claims may affect future compliance outcomes. Continue?');">
                        <?php wp_nonce_field( 'kh_smma_sponsor_claims_update' ); ?>
                        <input type="hidden" name="action" value="kh_smma_sponsor_claims_update" />
                        <input type="hidden" name="sponsor_id" value="<?php echo esc_attr( (string) $sponsor_id ); ?>" />
                        <p>
                            <label for="kh-smma-allowed-claims"><?php echo esc_html__( 'Allowed Claims (one per line)', 'kh-smma' ); ?></label><br />
                            <textarea id="kh-smma-allowed-claims" name="allowed_claims" class="large-text" rows="8"><?php echo esc_html( implode( "\n", $claims_row['allowed_claims'] ?? array() ) ); ?></textarea>
                        </p>
                        <p>
                            <?php echo esc_html__( 'Last updated:', 'kh-smma' ); ?>
                            <?php echo esc_html( (string) ( $claims_row['updated_at'] ?? '' ) ); ?>
                        </p>
                        <?php submit_button( __( 'Save Allowed Claims', 'kh-smma' ) ); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_update(): void {
        $sponsor_id = (int) ( $_POST['sponsor_id'] ?? 0 );
        if ( $sponsor_id <= 0 ) {
            wp_die( esc_html__( 'Invalid sponsor ID.', 'kh-smma' ) );
        }

        if ( ! $this->permissions->can_manage_sponsor_claims( $sponsor_id ) ) {
            wp_die( esc_html__( 'Permission denied for sponsor claim update.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_sponsor_claims_update' );

        $raw_claims = (string) ( $_POST['allowed_claims'] ?? '' );
        $claims = array_filter( array_map( 'sanitize_text_field', explode( "\n", $raw_claims ) ) );

        $result = $this->store->update_sponsor_claims( $sponsor_id, $claims, get_current_user_id() );

        $this->audit->record_event( 'sponsor.allowed_claims.updated', array(
            'user_id'        => get_current_user_id(),
            'change_type'    => 'update',
            'previous_value' => $result['previous'],
            'new_value'      => $result['current'],
            'timestamp'      => $result['current']['updated_at'] ?? '',
        ) );

        do_action( 'kh_smma_telemetry_event', 'compliance.rules.updated', array(
            'trace_id'    => uniqid( 'com-', true ),
            'user_id'     => get_current_user_id(),
            'change_type' => 'sponsor_allowed_claims_update',
            'timestamp'   => $result['current']['updated_at'] ?? '',
        ) );

        wp_safe_redirect( admin_url( 'admin.php?page=kh-smma-sponsor-claims&sponsor_id=' . $sponsor_id ) );
        exit;
    }
}

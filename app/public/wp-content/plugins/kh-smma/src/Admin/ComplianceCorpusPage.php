<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Compliance\ComplianceRulesStore;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Sponsor\ApprovalPermissionService;
use KH_SMMA\Sponsor\ApprovalSafetyService;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_current_user_id;
use function sanitize_key;
use function sanitize_text_field;
use function submit_button;
use function wp_die;
use function wp_nonce_field;
use function wp_safe_redirect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComplianceCorpusPage {
    private ComplianceRulesStore $store;
    private AuditLogger $audit;
    private ApprovalPermissionService $permissions;
    private ApprovalSafetyService $safety;

    public function __construct( ?ComplianceRulesStore $store = null, ?AuditLogger $audit = null, ?ApprovalPermissionService $permissions = null, ?ApprovalSafetyService $safety = null ) {
        global $wpdb;

        $this->store       = $store ?: new ComplianceRulesStore();
        $this->audit       = $audit ?: new AuditLogger( $wpdb );
        $this->permissions = $permissions ?: new ApprovalPermissionService();
        $this->safety      = $safety ?: new ApprovalSafetyService();
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_kh_smma_compliance_phrase_upsert', array( $this, 'handle_upsert' ) );
        add_action( 'admin_post_kh_smma_compliance_phrase_delete', array( $this, 'handle_delete' ) );
    }

    public function register_menu(): void {
        add_submenu_page(
            'kh-smma-dashboard',
            __( 'Compliance Banned Phrases', 'kh-smma' ),
            __( 'Compliance Corpus', 'kh-smma' ),
            'read',
            'kh-smma-compliance-corpus',
            array( $this, 'render' )
        );
    }

    public function render(): void {
        if ( ! $this->permissions->can_manage_banned_phrases() ) {
            wp_die( esc_html__( 'You do not have permission to manage compliance corpus.', 'kh-smma' ) );
        }

        $search   = sanitize_text_field( $_GET['s'] ?? '' );
        $severity = strtoupper( sanitize_key( $_GET['severity'] ?? '' ) );
        $category = sanitize_key( $_GET['category'] ?? '' );

        $corpus = $this->store->get_corpus();
        $meta   = $this->store->get_corpus_meta();

        $rows = array();
        foreach ( $corpus as $phrase_id => $entry ) {
            $phrase_value = (string) ( $entry['phrase'] ?? '' );
            if ( '' !== $search && false === stripos( $phrase_value, $search ) ) {
                continue;
            }
            if ( '' !== $severity && strtoupper( (string) ( $entry['severity'] ?? '' ) ) !== $severity ) {
                continue;
            }
            if ( '' !== $category && sanitize_key( (string) ( $entry['category'] ?? '' ) ) !== $category ) {
                continue;
            }

            $rows[ $phrase_id ] = $entry;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Compliance Corpus (Banned Phrases)', 'kh-smma' ); ?></h1>
            <p><?php echo esc_html__( 'Manage rule phrases without redeploys. Changes are versioned and auditable.', 'kh-smma' ); ?></p>
            <p>
                <strong><?php echo esc_html__( 'Corpus Version:', 'kh-smma' ); ?></strong>
                <?php echo esc_html( (string) $meta['corpus_version'] ); ?>
            </p>

            <form method="get" action="<?php echo esc_attr( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="kh-smma-compliance-corpus" />
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search phrase" />
                <select name="severity">
                    <option value=""><?php echo esc_html__( 'All severities', 'kh-smma' ); ?></option>
                    <option value="WARN" <?php selected( $severity, 'WARN' ); ?>>WARN</option>
                    <option value="FAIL" <?php selected( $severity, 'FAIL' ); ?>>FAIL</option>
                </select>
                <input type="text" name="category" value="<?php echo esc_attr( $category ); ?>" placeholder="Category" />
                <?php submit_button( __( 'Filter', 'kh-smma' ), 'secondary', 'submit', false ); ?>
            </form>

            <h2><?php echo esc_html__( 'Add / Edit Phrase', 'kh-smma' ); ?></h2>
            <form method="post" action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Updating this phrase may trigger re-review for existing schedules. Continue?');">
                <?php wp_nonce_field( 'kh_smma_compliance_phrase_upsert' ); ?>
                <input type="hidden" name="action" value="kh_smma_compliance_phrase_upsert" />
                <p>
                    <label>
                        <?php echo esc_html__( 'Phrase', 'kh-smma' ); ?>
                        <input type="text" name="phrase" required class="regular-text" />
                    </label>
                </p>
                <p>
                    <label>
                        <?php echo esc_html__( 'Severity', 'kh-smma' ); ?>
                        <select name="severity" required>
                            <option value="FAIL">FAIL</option>
                            <option value="WARN">WARN</option>
                        </select>
                    </label>
                </p>
                <p>
                    <label>
                        <?php echo esc_html__( 'Category', 'kh-smma' ); ?>
                        <input type="text" name="category" value="marketing_claim" class="regular-text" />
                    </label>
                </p>
                <?php submit_button( __( 'Save Phrase', 'kh-smma' ) ); ?>
            </form>

            <h2><?php echo esc_html__( 'Corpus Entries', 'kh-smma' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Phrase', 'kh-smma' ); ?></th>
                        <th><?php echo esc_html__( 'Severity', 'kh-smma' ); ?></th>
                        <th><?php echo esc_html__( 'Category', 'kh-smma' ); ?></th>
                        <th><?php echo esc_html__( 'Updated', 'kh-smma' ); ?></th>
                        <th><?php echo esc_html__( 'Actions', 'kh-smma' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="5"><?php echo esc_html__( 'No phrases found.', 'kh-smma' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $rows as $phrase_id => $entry ) : ?>
                        <tr>
                            <td><?php echo esc_html( (string) ( $entry['phrase'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $entry['severity'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $entry['category'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $entry['updated_at'] ?? '' ) ); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remove phrase and trigger corpus re-version?');" style="display:inline;">
                                    <?php wp_nonce_field( 'kh_smma_compliance_phrase_delete' ); ?>
                                    <input type="hidden" name="action" value="kh_smma_compliance_phrase_delete" />
                                    <input type="hidden" name="phrase_id" value="<?php echo esc_attr( $phrase_id ); ?>" />
                                    <?php submit_button( __( 'Delete', 'kh-smma' ), 'delete', 'submit', false ); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_upsert(): void {
        if ( ! $this->permissions->can_manage_banned_phrases() ) {
            wp_die( esc_html__( 'Permission denied.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_compliance_phrase_upsert' );

        $result = $this->store->add_or_update_phrase(
            sanitize_text_field( $_POST['phrase'] ?? '' ),
            sanitize_text_field( $_POST['severity'] ?? '' ),
            sanitize_text_field( $_POST['category'] ?? '' ),
            get_current_user_id(),
            sanitize_key( $_POST['phrase_id'] ?? '' )
        );

        if ( ! $result['ok'] ) {
            wp_die( esc_html( $result['error'] ) );
        }

        $meta = $this->store->increment_corpus_version( get_current_user_id() );
        $rerouted = $this->safety->trigger_rereview_for_corpus_version( (int) $meta['corpus_version'], get_current_user_id() );

        $this->audit->record_event( 'compliance.phrase.added', array(
            'user_id'        => get_current_user_id(),
            'change_type'    => 'upsert',
            'previous_value' => $result['previous'],
            'new_value'      => $result['record'],
            'timestamp'      => $meta['updated_at'],
        ) );
        $this->audit->record_event( 'compliance.corpus.updated', array(
            'user_id'        => get_current_user_id(),
            'change_type'    => 'version_increment',
            'previous_value' => $result['previous'],
            'new_value'      => array( 'corpus_version' => $meta['corpus_version'] ),
            'timestamp'      => $meta['updated_at'],
            'schedules_reflagged' => $rerouted,
        ) );

        do_action( 'kh_smma_telemetry_event', 'compliance.rules.updated', array(
            'trace_id'    => uniqid( 'com-', true ),
            'user_id'     => get_current_user_id(),
            'change_type' => 'phrase_upsert',
            'timestamp'   => $meta['updated_at'],
        ) );

        wp_safe_redirect( admin_url( 'admin.php?page=kh-smma-compliance-corpus' ) );
        exit;
    }

    public function handle_delete(): void {
        if ( ! $this->permissions->can_manage_banned_phrases() ) {
            wp_die( esc_html__( 'Permission denied.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_compliance_phrase_delete' );

        $removed = $this->store->remove_phrase( sanitize_key( $_POST['phrase_id'] ?? '' ) );
        if ( ! $removed['ok'] ) {
            wp_die( esc_html( $removed['error'] ) );
        }

        $meta = $this->store->increment_corpus_version( get_current_user_id() );
        $rerouted = $this->safety->trigger_rereview_for_corpus_version( (int) $meta['corpus_version'], get_current_user_id() );

        $this->audit->record_event( 'compliance.phrase.removed', array(
            'user_id'        => get_current_user_id(),
            'change_type'    => 'delete',
            'previous_value' => $removed['previous'],
            'new_value'      => null,
            'timestamp'      => $meta['updated_at'],
            'schedules_reflagged' => $rerouted,
        ) );

        do_action( 'kh_smma_telemetry_event', 'compliance.corpus.modified', array(
            'trace_id'    => uniqid( 'com-', true ),
            'user_id'     => get_current_user_id(),
            'change_type' => 'phrase_delete',
            'timestamp'   => $meta['updated_at'],
        ) );

        wp_safe_redirect( admin_url( 'admin.php?page=kh-smma-compliance-corpus' ) );
        exit;
    }
}

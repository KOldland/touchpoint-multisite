<?php
/**
 * Sponsor admin UI.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorAdminUI {
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_khm_sponsor_doc_create', array( $this, 'handle_create' ) );
        add_action( 'admin_post_khm_sponsor_doc_bulk_import', array( $this, 'handle_bulk_import' ) );
        add_action( 'admin_post_khm_sponsor_source_create', array( $this, 'handle_source_create' ) );
        add_action( 'admin_post_khm_sponsor_source_update', array( $this, 'handle_source_update' ) );
        add_action( 'admin_post_khm_sponsor_source_run', array( $this, 'handle_source_run' ) );
        add_action( 'admin_post_khm_sponsor_bulk_approve_imported', array( $this, 'handle_bulk_approve_imported' ) );
        add_action( 'admin_post_khm_sponsor_doc_approve', array( $this, 'handle_approve' ) );
        add_action( 'admin_post_khm_sponsor_create', array( $this, 'handle_sponsor_create' ) );
        add_action( 'khm_sponsor_process_ingest_job', array( $this, 'handle_ingest_job_event' ), 10, 1 );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Sponsor Library', 'khm-membership' ),
            __( 'Sponsor Library', 'khm-membership' ),
            'manage_options',
            'khm-sponsor-library',
            array( $this, 'render_page' ),
            'dashicons-media-document',
            62
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $docs = $wpdb->get_results( "SELECT d.*, s.name as sponsor_name FROM {$table} d LEFT JOIN {$sponsors_table} s ON d.sponsor_id = s.id ORDER BY d.created_at DESC", ARRAY_A );
        $docs = array_map( function( $doc ) {
            $doc['allowed_for_export'] = isset( $doc['allowed_for_export'] ) ? intval( $doc['allowed_for_export'] ) : 1;
            return $doc;
        }, $docs );
        $sponsors = $wpdb->get_results( "SELECT * FROM {$sponsors_table} ORDER BY created_at DESC", ARRAY_A );
        $sources = SponsorIngest::list_sources( 100 );
        $jobs = SponsorIngest::list_jobs( 30 );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sponsor Library', 'khm-membership' ); ?></h1>

            <?php if ( isset( $_GET['bulk_queued'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bulk import queued for background processing.', 'khm-membership' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['bulk_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Bulk import could not be queued. Check Sponsor and URL input.', 'khm-membership' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['source_created'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Library source created.', 'khm-membership' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['source_updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Library source updated.', 'khm-membership' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['source_queued'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Source crawl job queued.', 'khm-membership' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['bulk_approved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Imported documents approved.', 'khm-membership' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Sponsors', 'khm-membership' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_create' ); ?>
                <input type="hidden" name="action" value="khm_sponsor_create" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sponsor_name"><?php esc_html_e( 'Sponsor Name', 'khm-membership' ); ?></label></th>
                        <td><input name="sponsor_name" id="sponsor_name" type="text" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sponsor_url"><?php esc_html_e( 'Sponsor URL', 'khm-membership' ); ?></label></th>
                        <td><input name="sponsor_url" id="sponsor_url" type="url" class="regular-text" placeholder="https://example.com" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="contact_email"><?php esc_html_e( 'Contact Email', 'khm-membership' ); ?></label></th>
                        <td><input name="contact_email" id="contact_email" type="email" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="publish_allowed"><?php esc_html_e( 'Publish Allowed', 'khm-membership' ); ?></label></th>
                        <td><input name="publish_allowed" id="publish_allowed" type="checkbox" value="1" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Add Sponsor', 'khm-membership' ) ); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'URL', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Contact Email', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Publish Allowed', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sponsors ) ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No sponsors found.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $sponsors as $sponsor ) : ?>
                            <tr>
                                <td><?php echo esc_html( $sponsor['id'] ); ?></td>
                                <td><?php echo esc_html( $sponsor['name'] ); ?></td>
                                <td><?php echo esc_html( $sponsor['url'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $sponsor['contact_email'] ); ?></td>
                                <td><?php echo ! empty( $sponsor['publish_allowed'] ) ? esc_html__( 'Yes', 'khm-membership' ) : esc_html__( 'No', 'khm-membership' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Edit Library Source', 'khm-membership' ); ?></h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_source_update' ); ?>
                <input type="hidden" name="action" value="khm_sponsor_source_update" />
                <input type="hidden" name="mode" value="edit" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="edit_source_id"><?php esc_html_e( 'Source', 'khm-membership' ); ?></label></th>
                        <td>
                            <select name="source_id" id="edit_source_id" required>
                                <option value=""><?php esc_html_e( 'Select source', 'khm-membership' ); ?></option>
                                <?php foreach ( $sources as $source ) : ?>
                                    <option value="<?php echo esc_attr( $source['id'] ); ?>"><?php echo esc_html( '#' . $source['id'] . ' - ' . $source['sponsor_name'] . ' - ' . $source['root_url'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="edit_root_url"><?php esc_html_e( 'Library Source URL', 'khm-membership' ); ?></label></th>
                        <td><input name="root_url" id="edit_root_url" type="url" class="regular-text" placeholder="https://sponsor.example.com/resources" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="edit_domain_allowlist"><?php esc_html_e( 'Domain Allowlist', 'khm-membership' ); ?></label></th>
                        <td><input name="domain_allowlist" id="edit_domain_allowlist" type="text" class="regular-text" placeholder="sponsor.example.com, cdn.sponsor.example.com" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="edit_max_pages"><?php esc_html_e( 'Max Pages', 'khm-membership' ); ?></label></th>
                        <td><input name="max_pages" id="edit_max_pages" type="number" min="1" max="500" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="edit_max_depth"><?php esc_html_e( 'Max Depth', 'khm-membership' ); ?></label></th>
                        <td><input name="max_depth" id="edit_max_depth" type="number" min="0" max="6" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="edit_max_response_kb"><?php esc_html_e( 'Max Response KB', 'khm-membership' ); ?></label></th>
                        <td><input name="max_response_kb" id="edit_max_response_kb" type="number" min="64" max="4096" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Update Source', 'khm-membership' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Add Sponsor Document', 'khm-membership' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_doc_create' ); ?>
                <input type="hidden" name="action" value="khm_sponsor_doc_create" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sponsor_id"><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></label></th>
                        <td>
                            <select name="sponsor_id" id="sponsor_id" required>
                                <option value=""><?php esc_html_e( 'Select sponsor', 'khm-membership' ); ?></option>
                                <?php foreach ( $sponsors as $sponsor ) : ?>
                                    <option value="<?php echo esc_attr( $sponsor['id'] ); ?>"><?php echo esc_html( $sponsor['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="title"><?php esc_html_e( 'Title', 'khm-membership' ); ?></label></th>
                        <td><input name="title" id="title" type="text" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="url"><?php esc_html_e( 'URL', 'khm-membership' ); ?></label></th>
                        <td><input name="url" id="url" type="url" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="authors"><?php esc_html_e( 'Authors', 'khm-membership' ); ?></label></th>
                        <td><input name="authors" id="authors" type="text" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="publisher"><?php esc_html_e( 'Publisher', 'khm-membership' ); ?></label></th>
                        <td><input name="publisher" id="publisher" type="text" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pub_date"><?php esc_html_e( 'Publication Date', 'khm-membership' ); ?></label></th>
                        <td><input name="pub_date" id="pub_date" type="date" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="allowed_for_export"><?php esc_html_e( 'Allow Export', 'khm-membership' ); ?></label></th>
                        <td><input name="allowed_for_export" id="allowed_for_export" type="checkbox" value="1" checked /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Add Document', 'khm-membership' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Bulk Import Sponsor Documents', 'khm-membership' ); ?></h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_doc_bulk_import' ); ?>
                <input type="hidden" name="action" value="khm_sponsor_doc_bulk_import" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bulk_sponsor_id"><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></label></th>
                        <td>
                            <select name="sponsor_id" id="bulk_sponsor_id" required>
                                <option value=""><?php esc_html_e( 'Select sponsor', 'khm-membership' ); ?></option>
                                <?php foreach ( $sponsors as $sponsor ) : ?>
                                    <option value="<?php echo esc_attr( $sponsor['id'] ); ?>"><?php echo esc_html( $sponsor['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk_urls"><?php esc_html_e( 'URLs (one per line)', 'khm-membership' ); ?></label></th>
                        <td>
                            <textarea name="bulk_urls" id="bulk_urls" rows="8" class="large-text" placeholder="https://example.com/whitepaper-a&#10;https://example.com/case-study-b"></textarea>
                            <p class="description"><?php esc_html_e( 'Use this for quick link-based bulk import.', 'khm-membership' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk_csv"><?php esc_html_e( 'CSV Upload', 'khm-membership' ); ?></label></th>
                        <td>
                            <input name="bulk_csv" id="bulk_csv" type="file" accept=".csv,text/csv" />
                            <p class="description"><?php esc_html_e( 'Optional. Header supported: url,title,authors,publisher,pub_date', 'khm-membership' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk_files"><?php esc_html_e( 'Bulk File Upload', 'khm-membership' ); ?></label></th>
                        <td>
                            <input name="bulk_files[]" id="bulk_files" type="file" multiple accept=".pdf,.doc,.docx,.txt,.rtf,.ppt,.pptx,.xls,.xlsx,.csv" />
                            <p class="description"><?php esc_html_e( 'Uploads selected files into WordPress Media Library and adds them to the sponsor library queue.', 'khm-membership' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk_allowed_for_export"><?php esc_html_e( 'Allow Export', 'khm-membership' ); ?></label></th>
                        <td><input name="allowed_for_export" id="bulk_allowed_for_export" type="checkbox" value="1" checked /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Queue Bulk Import', 'khm-membership' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Library Sources', 'khm-membership' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_source_create' ); ?>
                <input type="hidden" name="action" value="khm_sponsor_source_create" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="source_sponsor_id"><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></label></th>
                        <td>
                            <select name="sponsor_id" id="source_sponsor_id" required>
                                <option value=""><?php esc_html_e( 'Select sponsor', 'khm-membership' ); ?></option>
                                <?php foreach ( $sponsors as $sponsor ) : ?>
                                    <option value="<?php echo esc_attr( $sponsor['id'] ); ?>"><?php echo esc_html( $sponsor['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="source_root_url"><?php esc_html_e( 'Library Source URL', 'khm-membership' ); ?></label></th>
                        <td><input name="root_url" id="source_root_url" type="url" class="regular-text" required placeholder="https://sponsor.example.com/resources" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="source_allowlist"><?php esc_html_e( 'Domain Allowlist', 'khm-membership' ); ?></label></th>
                        <td><input name="domain_allowlist" id="source_allowlist" type="text" class="regular-text" placeholder="sponsor.example.com, cdn.sponsor.example.com" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="source_max_pages"><?php esc_html_e( 'Max Pages', 'khm-membership' ); ?></label></th>
                        <td><input name="max_pages" id="source_max_pages" type="number" min="1" max="500" value="25" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="source_max_depth"><?php esc_html_e( 'Max Depth', 'khm-membership' ); ?></label></th>
                        <td><input name="max_depth" id="source_max_depth" type="number" min="0" max="6" value="2" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="source_max_response_kb"><?php esc_html_e( 'Max Response KB', 'khm-membership' ); ?></label></th>
                        <td><input name="max_response_kb" id="source_max_response_kb" type="number" min="64" max="4096" value="512" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Add Library Source', 'khm-membership' ) ); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Source', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Allowlist', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Limits', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Last Job', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sources ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'No sources configured.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $sources as $source ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $source['root_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $source['root_url'] ); ?></a></td>
                                <td><?php echo esc_html( $source['sponsor_name'] ?: $source['sponsor_id'] ); ?></td>
                                <td><?php echo esc_html( $source['domain_allowlist'] ); ?></td>
                                <td><?php echo esc_html( sprintf( 'pages:%d depth:%d kb:%d', (int) $source['max_pages'], (int) $source['max_depth'], (int) $source['max_response_kb'] ) ); ?></td>
                                <td><?php echo esc_html( ucfirst( $source['status'] ) ); ?></td>
                                <td><?php echo esc_html( $source['last_job_id'] ? '#' . $source['last_job_id'] : '—' ); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:8px;">
                                        <?php wp_nonce_field( 'khm_sponsor_source_run' ); ?>
                                        <input type="hidden" name="action" value="khm_sponsor_source_run" />
                                        <input type="hidden" name="source_id" value="<?php echo esc_attr( $source['id'] ); ?>" />
                                        <?php submit_button( __( 'Run Now', 'khm-membership' ), 'secondary small', 'submit', false ); ?>
                                    </form>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                                        <?php wp_nonce_field( 'khm_sponsor_source_update' ); ?>
                                        <input type="hidden" name="action" value="khm_sponsor_source_update" />
                                        <input type="hidden" name="source_id" value="<?php echo esc_attr( $source['id'] ); ?>" />
                                        <input type="hidden" name="status" value="<?php echo esc_attr( $source['status'] === 'active' ? 'paused' : 'active' ); ?>" />
                                        <?php submit_button( $source['status'] === 'active' ? __( 'Pause', 'khm-membership' ) : __( 'Activate', 'khm-membership' ), 'small', 'submit', false ); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Bulk Approve Imported Docs', 'khm-membership' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_bulk_approve_imported' ); ?>
                <input type="hidden" name="action" value="khm_sponsor_bulk_approve_imported" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bulk_approve_sponsor_id"><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></label></th>
                        <td>
                            <select name="sponsor_id" id="bulk_approve_sponsor_id" required>
                                <option value=""><?php esc_html_e( 'Select sponsor', 'khm-membership' ); ?></option>
                                <?php foreach ( $sponsors as $sponsor ) : ?>
                                    <option value="<?php echo esc_attr( $sponsor['id'] ); ?>"><?php echo esc_html( $sponsor['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Approve Imported Pending Docs', 'khm-membership' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Bulk Import Jobs', 'khm-membership' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Job ID', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Progress', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Succeeded', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Failed', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Error', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Updated', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $jobs ) ) : ?>
                        <tr><td colspan="9"><?php esc_html_e( 'No ingest jobs found.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $jobs as $job ) : ?>
                            <tr>
                                <td><?php echo esc_html( $job['id'] ); ?></td>
                                <td><?php echo esc_html( $job['sponsor_name'] ?: $job['sponsor_id'] ); ?></td>
                                <td><?php echo esc_html( $job['source_type'] ); ?></td>
                                <td><?php echo esc_html( ucfirst( $job['status'] ) ); ?></td>
                                <td><?php echo esc_html( (int) $job['processed_items'] . ' / ' . (int) $job['total_items'] ); ?></td>
                                <td><?php echo esc_html( $job['succeeded_items'] ); ?></td>
                                <td><?php echo esc_html( $job['failed_items'] ); ?></td>
                                <td><?php echo esc_html( $job['error_message'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( $job['updated_at'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Sponsor Documents', 'khm-membership' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Sponsor ID', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'URL', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Export', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Approved', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $docs ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'No sponsor documents found.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $docs as $doc ) : ?>
                            <tr>
                                <td><?php echo esc_html( $doc['id'] ); ?></td>
                                <td><?php echo esc_html( $doc['sponsor_name'] ?: $doc['sponsor_id'] ); ?></td>
                                <td><?php echo esc_html( $doc['title'] ); ?></td>
                                <td><a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $doc['url'] ); ?></a></td>
                                <td><?php echo ! empty( $doc['allowed_for_export'] ) ? esc_html__( 'Yes', 'khm-membership' ) : esc_html__( 'No', 'khm-membership' ); ?></td>
                                <td><?php echo ! empty( $doc['approved'] ) ? esc_html__( 'Yes', 'khm-membership' ) : esc_html__( 'No', 'khm-membership' ); ?></td>
                                <td>
                                    <?php if ( empty( $doc['approved'] ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'khm_sponsor_doc_approve' ); ?>
                                            <input type="hidden" name="action" value="khm_sponsor_doc_approve" />
                                            <input type="hidden" name="doc_id" value="<?php echo esc_attr( $doc['id'] ); ?>" />
                                            <?php submit_button( __( 'Approve', 'khm-membership' ), 'secondary small', 'submit', false ); ?>
                                        </form>
                                    <?php else : ?>
                                        <span><?php esc_html_e( 'Approved', 'khm-membership' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_create(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_doc_create' );

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $url = esc_url_raw( $_POST['url'] ?? '' );
        $authors = sanitize_text_field( $_POST['authors'] ?? '' );
        $publisher = sanitize_text_field( $_POST['publisher'] ?? '' );
        $pub_date = sanitize_text_field( $_POST['pub_date'] ?? '' );
        $allowed_for_export = ! empty( $_POST['allowed_for_export'] ) ? 1 : 0;

        if ( ! $sponsor_id || ! $title || ! $url ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&error=missing' ) );
            exit;
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $wpdb->insert(
            $table,
            array(
                'sponsor_id' => $sponsor_id,
                'title'      => $title,
                'url'        => $url,
                'authors'    => $authors,
                'publisher'  => $publisher,
                'pub_date'   => $pub_date ?: null,
                'meta'       => null,
                'allowed_for_export' => $allowed_for_export,
                'approved'   => 0,
                'created_by' => get_current_user_id(),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
        );

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&created=1' ) );
        exit;
    }

    public function handle_sponsor_create(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_create' );

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $name = sanitize_text_field( $_POST['sponsor_name'] ?? '' );
        $contact = sanitize_email( $_POST['contact_email'] ?? '' );
        $url = esc_url_raw( $_POST['sponsor_url'] ?? '' );
        $publish_allowed = ! empty( $_POST['publish_allowed'] ) ? 1 : 0;

        if ( ! $name ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&error=missing' ) );
            exit;
        }

        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();
        $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'url' => $url,
                'contact_email' => $contact,
                'publish_allowed' => $publish_allowed,
                'created_by' => get_current_user_id(),
            ),
            array( '%s', '%s', '%s', '%d', '%d' )
        );

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&created=1' ) );
        exit;
    }

    public function handle_bulk_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_doc_bulk_import' );

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        $allowed_for_export = ! empty( $_POST['allowed_for_export'] ) ? 1 : 0;

        $items_from_urls = SponsorIngest::collect_items_from_url_lines( (string) ( $_POST['bulk_urls'] ?? '' ) );
        $items_from_csv = array();
        if ( ! empty( $_FILES['bulk_csv']['tmp_name'] ) ) {
            $items_from_csv = SponsorIngest::collect_items_from_csv_file( $_FILES['bulk_csv'] );
        }
        $items_from_files = SponsorIngest::collect_items_from_uploaded_files( $_FILES['bulk_files'] ?? array() );

        $items = SponsorIngest::normalize_items( array_merge( $items_from_urls, $items_from_csv, $items_from_files ) );
        if ( ! $sponsor_id || empty( $items ) ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        $source_parts = array();
        if ( ! empty( $items_from_urls ) ) {
            $source_parts[] = 'urls';
        }
        if ( ! empty( $items_from_csv ) ) {
            $source_parts[] = 'csv';
        }
        if ( ! empty( $items_from_files ) ) {
            $source_parts[] = 'files';
        }
        $source_type = count( $source_parts ) > 1 ? 'mixed' : ( $source_parts[0] ?? 'urls' );

        $job_id = SponsorIngest::create_job( $sponsor_id, $items, $allowed_for_export, $source_type, get_current_user_id() );
        if ( ! $job_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_queued=1&job_id=' . $job_id ) );
        exit;
    }

    public function handle_source_create(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_source_create' );

        $source_id = SponsorIngest::create_source(
            array(
                'sponsor_id'       => absint( $_POST['sponsor_id'] ?? 0 ),
                'root_url'         => esc_url_raw( $_POST['root_url'] ?? '' ),
                'domain_allowlist' => sanitize_text_field( $_POST['domain_allowlist'] ?? '' ),
                'max_pages'        => absint( $_POST['max_pages'] ?? 25 ),
                'max_depth'        => absint( $_POST['max_depth'] ?? 2 ),
                'max_response_kb'  => absint( $_POST['max_response_kb'] ?? 512 ),
                'created_by'       => get_current_user_id(),
            )
        );

        if ( ! $source_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&source_created=1' ) );
        exit;
    }

    public function handle_source_update(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_source_update' );

        $source_id = absint( $_POST['source_id'] ?? 0 );
        if ( ! $source_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        global $wpdb;
        $table = SponsorMigration::sources_table_name();

        $mode = sanitize_key( $_POST['mode'] ?? '' );
        if ( 'edit' === $mode ) {
            $update_data = array();
            $update_format = array();

            if ( ! empty( $_POST['root_url'] ) ) {
                $update_data['root_url'] = esc_url_raw( $_POST['root_url'] );
                $update_format[] = '%s';
            }
            if ( isset( $_POST['domain_allowlist'] ) ) {
                $update_data['domain_allowlist'] = sanitize_text_field( $_POST['domain_allowlist'] );
                $update_format[] = '%s';
            }
            if ( isset( $_POST['max_pages'] ) && '' !== (string) $_POST['max_pages'] ) {
                $update_data['max_pages'] = max( 1, min( 500, absint( $_POST['max_pages'] ) ) );
                $update_format[] = '%d';
            }
            if ( isset( $_POST['max_depth'] ) && '' !== (string) $_POST['max_depth'] ) {
                $update_data['max_depth'] = max( 0, min( 6, absint( $_POST['max_depth'] ) ) );
                $update_format[] = '%d';
            }
            if ( isset( $_POST['max_response_kb'] ) && '' !== (string) $_POST['max_response_kb'] ) {
                $update_data['max_response_kb'] = max( 64, min( 4096, absint( $_POST['max_response_kb'] ) ) );
                $update_format[] = '%d';
            }

            if ( empty( $update_data ) ) {
                wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
                exit;
            }

            $wpdb->update(
                $table,
                $update_data,
                array( 'id' => $source_id ),
                $update_format,
                array( '%d' )
            );
        } else {
            $status = sanitize_key( $_POST['status'] ?? 'active' );
            if ( ! in_array( $status, array( 'active', 'paused' ), true ) ) {
                wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
                exit;
            }
            $wpdb->update(
                $table,
                array( 'status' => $status ),
                array( 'id' => $source_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&source_updated=1' ) );
        exit;
    }

    public function handle_source_run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_source_run' );
        $source_id = absint( $_POST['source_id'] ?? 0 );
        if ( ! $source_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        $job_id = SponsorIngest::queue_source_crawl_job( $source_id, 1, get_current_user_id() );
        if ( ! $job_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&source_queued=1&job_id=' . $job_id ) );
        exit;
    }

    public function handle_bulk_approve_imported(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_bulk_approve_imported' );
        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        if ( ! $sponsor_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_error=1' ) );
            exit;
        }

        SponsorIngest::approve_imported_docs_by_sponsor( $sponsor_id );
        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&bulk_approved=1' ) );
        exit;
    }

    public function handle_approve(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_doc_approve' );
        $doc_id = absint( $_POST['doc_id'] ?? 0 );
        if ( ! $doc_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&error=missing' ) );
            exit;
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $wpdb->update(
            $table,
            array( 'approved' => 1 ),
            array( 'id' => $doc_id ),
            array( '%d' ),
            array( '%d' )
        );

        wp_redirect( admin_url( 'admin.php?page=khm-sponsor-library&approved=1' ) );
        exit;
    }

    public function handle_ingest_job_event( $job_id ): void {
        SponsorIngest::process_job( absint( $job_id ) );
    }
}

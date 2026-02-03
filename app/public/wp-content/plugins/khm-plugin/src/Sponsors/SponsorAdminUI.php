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
        add_action( 'admin_post_khm_sponsor_doc_approve', array( $this, 'handle_approve' ) );
        add_action( 'admin_post_khm_sponsor_create', array( $this, 'handle_sponsor_create' ) );
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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sponsor Library', 'khm-membership' ); ?></h1>

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
}

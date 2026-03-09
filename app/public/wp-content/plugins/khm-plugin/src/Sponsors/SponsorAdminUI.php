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
        add_action( 'admin_post_khm_sponsor_create', array( $this, 'handle_sponsor_save' ) );
        add_action( 'admin_post_khm_sponsor_update', array( $this, 'handle_sponsor_save' ) );
        add_action( 'admin_post_khm_sponsor_library_create', array( $this, 'handle_library_create' ) );
        add_action( 'admin_post_khm_sponsor_doc_bulk_import', array( $this, 'handle_library_upload' ) );
        add_action( 'admin_post_khm_sponsor_library_bulk_approve', array( $this, 'handle_library_bulk_approve' ) );
        add_action( 'admin_post_khm_sponsor_doc_approve', array( $this, 'handle_approve' ) );
        add_action( 'admin_post_khm_sponsor_doc_update', array( $this, 'handle_doc_update' ) );
        add_action( 'admin_post_khm_sponsor_source_create', array( $this, 'handle_source_create' ) );
        add_action( 'admin_post_khm_sponsor_source_run', array( $this, 'handle_source_run' ) );
        add_action( 'admin_post_khm_sponsor_bulk_approve_imported', array( $this, 'handle_bulk_approve_imported' ) );
        add_action( 'khm_sponsor_process_ingest_job', array( $this, 'handle_ingest_job_event' ), 10, 1 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Sponsorship', 'khm-membership' ),
            __( 'Sponsorship', 'khm-membership' ),
            'manage_options',
            'khm-sponsorship',
            array( $this, 'render_sponsors_page' ),
            'dashicons-groups',
            62
        );

        add_submenu_page(
            'khm-sponsorship',
            __( 'Sponsors', 'khm-membership' ),
            __( 'Sponsors', 'khm-membership' ),
            'manage_options',
            'khm-sponsorship',
            array( $this, 'render_sponsors_page' )
        );

        add_submenu_page(
            'khm-sponsorship',
            __( 'Libraries', 'khm-membership' ),
            __( 'Libraries', 'khm-membership' ),
            'manage_options',
            'khm-sponsorship-libraries',
            array( $this, 'render_libraries_page' )
        );
    }

    public function enqueue_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || ( 'toplevel_page_khm-sponsorship' !== $screen->id && 'sponsorship_page_khm-sponsorship-libraries' !== $screen->id ) ) {
            return;
        }

        wp_enqueue_media();
    }

    public function render_sponsors_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        global $wpdb;
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $sponsors = $wpdb->get_results( "SELECT * FROM {$sponsors_table} ORDER BY created_at DESC", ARRAY_A );
        $levels = $this->get_membership_levels();

        $sponsors_for_js = array_map(
            function( array $sponsor ): array {
                $social_profiles = json_decode( (string) ( $sponsor['social_profiles'] ?? '' ), true );
                if ( ! is_array( $social_profiles ) ) {
                    $social_profiles = array();
                }

                $team_members = json_decode( (string) ( $sponsor['team_members'] ?? '' ), true );
                if ( ! is_array( $team_members ) ) {
                    $team_members = array();
                }
                $logo_attachment_id = absint( $sponsor['logo_attachment_id'] ?? 0 );
                $logo_url = $logo_attachment_id ? wp_get_attachment_image_url( $logo_attachment_id, 'thumbnail' ) : '';

                return array(
                    'id' => absint( $sponsor['id'] ?? 0 ),
                    'name' => sanitize_text_field( $sponsor['name'] ?? '' ),
                    'url' => esc_url_raw( $sponsor['url'] ?? '' ),
                    'contact_email' => sanitize_email( $sponsor['contact_email'] ?? '' ),
                    'primary_contact_first_name' => sanitize_text_field( $sponsor['primary_contact_first_name'] ?? '' ),
                    'primary_contact_last_name' => sanitize_text_field( $sponsor['primary_contact_last_name'] ?? '' ),
                    'primary_contact_job_title' => sanitize_text_field( $sponsor['primary_contact_job_title'] ?? '' ),
                    'primary_contact_email' => sanitize_email( $sponsor['primary_contact_email'] ?? '' ),
                    'publish_allowed' => ! empty( $sponsor['publish_allowed'] ),
                    'logo_attachment_id' => $logo_attachment_id,
                    'logo_url' => esc_url_raw( (string) $logo_url ),
                    'social_profiles' => array(
                        'linkedin' => esc_url_raw( $social_profiles['linkedin'] ?? '' ),
                        'twitter' => esc_url_raw( $social_profiles['twitter'] ?? '' ),
                        'facebook' => esc_url_raw( $social_profiles['facebook'] ?? '' ),
                        'instagram' => esc_url_raw( $social_profiles['instagram'] ?? '' ),
                    ),
                    'team_members' => array_values( array_map( array( __CLASS__, 'normalize_team_member_for_ui' ), $team_members ) ),
                );
            },
            is_array( $sponsors ) ? $sponsors : array()
        );

        $open_sponsor_id = isset( $_GET['sponsor_id'] ) ? absint( $_GET['sponsor_id'] ) : 0;
        $open_mode = isset( $_GET['mode'] ) ? sanitize_key( (string) $_GET['mode'] ) : '';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sponsors', 'khm-membership' ); ?></h1>
            <?php $this->render_notices(); ?>
            <p class="description" style="margin:8px 0 16px;"><?php esc_html_e( 'Manage sponsor profiles, branding, social links, and team access levels.', 'khm-membership' ); ?></p>
            <p>
                <button type="button" class="button button-primary" id="open_sponsor_modal"><?php esc_html_e( 'Add New Sponsor', 'khm-membership' ); ?></button>
            </p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Website', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Contact', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sponsors ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No sponsors found.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $sponsors as $sponsor ) : ?>
                            <tr>
                                <td><?php echo esc_html( $sponsor['name'] ); ?></td>
                                <td><?php echo esc_html( $sponsor['url'] ?? '' ); ?></td>
                                <td>
                                    <?php
                                    $primary_name = trim( (string) ( ( $sponsor['primary_contact_first_name'] ?? '' ) . ' ' . ( $sponsor['primary_contact_last_name'] ?? '' ) ) );
                                    $primary_email = trim( (string) ( $sponsor['primary_contact_email'] ?? '' ) );
                                    if ( '' === $primary_name && '' === $primary_email ) {
                                        echo esc_html( $sponsor['contact_email'] ?? '' );
                                    } else {
                                        echo esc_html( trim( $primary_name . ( $primary_email ? ' (' . $primary_email . ')' : '' ) ) );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-secondary khm-edit-sponsor" data-sponsor-id="<?php echo esc_attr( absint( $sponsor['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'khm-membership' ); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="sponsor_modal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.35);z-index:99999;">
                <div style="background:#fff;max-width:900px;margin:40px auto;padding:20px;max-height:85vh;overflow:auto;">
                    <h2 id="sponsor_modal_title"><?php esc_html_e( 'Create Sponsor', 'khm-membership' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'khm_sponsor_save' ); ?>
                        <input type="hidden" name="action" id="sponsor_form_action" value="khm_sponsor_create" />
                        <input type="hidden" name="sponsor_id" id="modal_sponsor_id" value="0" />
                        <table class="form-table">
                            <tr>
                                <th colspan="2" style="padding-top:0;"><h3 style="margin:0;"><?php esc_html_e( 'Sponsor Details', 'khm-membership' ); ?></h3></th>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_sponsor_name"><?php esc_html_e( 'Sponsor Name', 'khm-membership' ); ?></label></th>
                                <td><input name="sponsor_name" id="modal_sponsor_name" type="text" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_sponsor_url"><?php esc_html_e( 'Sponsor Website', 'khm-membership' ); ?></label></th>
                                <td><input name="sponsor_url" id="modal_sponsor_url" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Logo', 'khm-membership' ); ?></th>
                                <td>
                                    <input type="hidden" name="logo_attachment_id" id="modal_logo_attachment_id" value="0" />
                                    <button type="button" class="button" id="pick_sponsor_logo"><?php esc_html_e( 'Select Logo', 'khm-membership' ); ?></button>
                                    <div style="margin-top:8px;">
                                        <img id="sponsor_logo_preview" src="" style="max-width:120px;display:none;" alt="" />
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="2"><h3 style="margin:0;"><?php esc_html_e( 'Socials', 'khm-membership' ); ?></h3></th>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_linkedin"><?php esc_html_e( 'LinkedIn', 'khm-membership' ); ?></label></th>
                                <td><input name="social_linkedin" id="modal_social_linkedin" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_twitter"><?php esc_html_e( 'X', 'khm-membership' ); ?></label></th>
                                <td><input name="social_twitter" id="modal_social_twitter" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_facebook"><?php esc_html_e( 'Meta', 'khm-membership' ); ?></label></th>
                                <td><input name="social_facebook" id="modal_social_facebook" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_instagram"><?php esc_html_e( 'Instagram', 'khm-membership' ); ?></label></th>
                                <td><input name="social_instagram" id="modal_social_instagram" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th colspan="2"><h3 style="margin:0;"><?php esc_html_e( 'Team Members', 'khm-membership' ); ?></h3></th>
                            </tr>
                            <tr>
                                <th colspan="2" style="padding-left:12px;"><h4 style="margin:8px 0 4px;font-weight:600;"><?php esc_html_e( 'Primary Contact', 'khm-membership' ); ?></h4></th>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_primary_contact_first_name"><?php esc_html_e( 'First Name', 'khm-membership' ); ?></label></th>
                                <td><input name="primary_contact_first_name" id="modal_primary_contact_first_name" type="text" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_primary_contact_last_name"><?php esc_html_e( 'Last Name', 'khm-membership' ); ?></label></th>
                                <td><input name="primary_contact_last_name" id="modal_primary_contact_last_name" type="text" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_primary_contact_job_title"><?php esc_html_e( 'Job Title', 'khm-membership' ); ?></label></th>
                                <td><input name="primary_contact_job_title" id="modal_primary_contact_job_title" type="text" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_primary_contact_email"><?php esc_html_e( 'Email', 'khm-membership' ); ?></label></th>
                                <td><input name="primary_contact_email" id="modal_primary_contact_email" type="email" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_publish_allowed"><?php esc_html_e( 'Allow Sponsor Content Publishing', 'khm-membership' ); ?></label></th>
                                <td>
                                    <input name="publish_allowed" id="modal_publish_allowed" type="checkbox" value="1" />
                                    <p class="description"><?php esc_html_e( 'Enable when this sponsor’s library content can be published to site experiences.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Logo', 'khm-membership' ); ?></th>
                                <td>
                                    <input type="hidden" name="logo_attachment_id" id="modal_logo_attachment_id" value="0" />
                                    <button type="button" class="button" id="pick_sponsor_logo"><?php esc_html_e( 'Select Logo', 'khm-membership' ); ?></button>
                                    <div style="margin-top:8px;">
                                        <img id="sponsor_logo_preview" src="" style="max-width:120px;display:none;" alt="" />
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="2"><h3 style="margin:0;"><?php esc_html_e( 'Socials', 'khm-membership' ); ?></h3></th>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_linkedin"><?php esc_html_e( 'LinkedIn', 'khm-membership' ); ?></label></th>
                                <td><input name="social_linkedin" id="modal_social_linkedin" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_twitter"><?php esc_html_e( 'X', 'khm-membership' ); ?></label></th>
                                <td><input name="social_twitter" id="modal_social_twitter" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_facebook"><?php esc_html_e( 'Meta', 'khm-membership' ); ?></label></th>
                                <td><input name="social_facebook" id="modal_social_facebook" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="modal_social_instagram"><?php esc_html_e( 'Instagram', 'khm-membership' ); ?></label></th>
                                <td><input name="social_instagram" id="modal_social_instagram" type="url" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th colspan="2"><h3 style="margin:0;"><?php esc_html_e( 'Team Members', 'khm-membership' ); ?></h3></th>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Members', 'khm-membership' ); ?></th>
                                <td>
                                    <div id="team_members_container"></div>
                                    <p style="margin-top:10px;">
                                        <button type="button" class="button" id="add_team_member"><?php esc_html_e( 'Add Member', 'khm-membership' ); ?></button>
                                    </p>
                                    <p class="description"><?php esc_html_e( 'Fields: First Name, Last Name, Company, Job Title, Work Phone, Work Email, Membership Level.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <?php submit_button( __( 'Save Sponsor', 'khm-membership' ), 'primary', 'submit', false ); ?>
                            <button type="button" class="button" id="close_sponsor_modal"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const sponsors = <?php echo wp_json_encode( $sponsors_for_js ); ?> || [];
            const membershipLevels = <?php echo wp_json_encode( array_values( array_map( function( array $level ): array { return array( 'id' => (int) ( $level['id'] ?? 0 ), 'name' => (string) ( $level['name'] ?? '' ) ); }, $levels ) ) ); ?> || [];
            const sponsorMap = {};
            sponsors.forEach((item) => {
                sponsorMap[String(item.id)] = item;
            });

            const modal = document.getElementById('sponsor_modal');
            const openBtn = document.getElementById('open_sponsor_modal');
            const closeBtn = document.getElementById('close_sponsor_modal');
            const modalTitle = document.getElementById('sponsor_modal_title');
            const actionField = document.getElementById('sponsor_form_action');
            const sponsorIdField = document.getElementById('modal_sponsor_id');
            const nameField = document.getElementById('modal_sponsor_name');
            const urlField = document.getElementById('modal_sponsor_url');
            const primaryFirstNameField = document.getElementById('modal_primary_contact_first_name');
            const primaryLastNameField = document.getElementById('modal_primary_contact_last_name');
            const primaryJobTitleField = document.getElementById('modal_primary_contact_job_title');
            const primaryEmailField = document.getElementById('modal_primary_contact_email');
            const publishField = document.getElementById('modal_publish_allowed');
            const logoIdField = document.getElementById('modal_logo_attachment_id');
            const logoPreview = document.getElementById('sponsor_logo_preview');
            const linkedinField = document.getElementById('modal_social_linkedin');
            const twitterField = document.getElementById('modal_social_twitter');
            const facebookField = document.getElementById('modal_social_facebook');
            const instagramField = document.getElementById('modal_social_instagram');
            const teamMembersContainer = document.getElementById('team_members_container');
            const addTeamMemberButton = document.getElementById('add_team_member');

            const renderMembershipOptions = (selectedValue) => {
                const normalizedSelected = String(selectedValue || 'sponsor');
                let html = '<option value="sponsor">Sponsor</option>';
                membershipLevels.forEach((level) => {
                    const value = String(level.id || '');
                    const label = String(level.name || '');
                    if (!value || !label) return;
                    html += '<option value="' + value.replace(/"/g, '&quot;') + '">' + label.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</option>';
                });
                return { html, selected: normalizedSelected };
            };

            const addTeamMemberRow = (data) => {
                if (!teamMembersContainer) return;
                const member = data || {};
                const companyName = member.company || (nameField ? nameField.value : '');
                const membership = member.membership_level || 'sponsor';
                const options = renderMembershipOptions(membership);
                const hasUserId = member.user_id && parseInt(member.user_id) > 0;
                const linkedBadge = hasUserId 
                    ? '<span style="display:inline-block;background:#10b981;color:white;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:600;margin-left:8px;">✓ Linked to User</span>' 
                    : '';

                const row = document.createElement('div');
                row.className = 'khm-team-member-row';
                row.style.border = '1px solid #dcdcde';
                row.style.padding = '12px';
                row.style.marginBottom = '10px';
                row.style.borderRadius = '4px';
                row.innerHTML = '' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
                    '<strong style="font-size:13px;">Team Member</strong>' +
                    linkedBadge +
                    '</div>' +
                    '<div style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px;">' +
                    '<p style="margin:0;"><label>First Name<br><input type="text" name="team_members[first_name][]" class="regular-text" value="' + String(member.first_name || '').replace(/"/g, '&quot;') + '"></label></p>' +
                    '<p style="margin:0;"><label>Last Name<br><input type="text" name="team_members[last_name][]" class="regular-text" value="' + String(member.last_name || '').replace(/"/g, '&quot;') + '"></label></p>' +
                    '<p style="margin:0;"><label>Company<br><input type="text" name="team_members[company][]" class="regular-text team-member-company" value="' + String(companyName || '').replace(/"/g, '&quot;') + '"></label></p>' +
                    '<p style="margin:0;"><label>Job Title<br><input type="text" name="team_members[job_title][]" class="regular-text" value="' + String(member.job_title || '').replace(/"/g, '&quot;') + '"></label></p>' +
                    '<p style="margin:0;"><label>Work Phone<br><input type="text" name="team_members[work_phone][]" class="regular-text" value="' + String(member.work_phone || '').replace(/"/g, '&quot;') + '"></label></p>' +
                    '<p style="margin:0;"><label>Work Email<br><input type="email" name="team_members[work_email][]" class="regular-text" value="' + String(member.work_email || '').replace(/"/g, '&quot;') + '"></label></p>' +
                    '<p style="margin:0;"><label>Membership Level<br><select name="team_members[membership_level][]" class="regular-text team-member-membership-level">' + options.html + '</select></label></p>' +
                    '</div>' +
                    '<p style="margin:10px 0 0;"><button type="button" class="button-link-delete remove-team-member">Remove member</button></p>';

                teamMembersContainer.appendChild(row);
                const membershipSelect = row.querySelector('.team-member-membership-level');
                if (membershipSelect) {
                    membershipSelect.value = options.selected;
                }
                const removeButton = row.querySelector('.remove-team-member');
                if (removeButton) {
                    removeButton.addEventListener('click', () => {
                        row.remove();
                    });
                }
            };

            const resetForm = () => {
                if (modalTitle) modalTitle.textContent = 'Create Sponsor';
                if (actionField) actionField.value = 'khm_sponsor_create';
                if (sponsorIdField) sponsorIdField.value = '0';
                if (nameField) nameField.value = '';
                if (urlField) urlField.value = '';
                if (primaryFirstNameField) primaryFirstNameField.value = '';
                if (primaryLastNameField) primaryLastNameField.value = '';
                if (primaryJobTitleField) primaryJobTitleField.value = '';
                if (primaryEmailField) primaryEmailField.value = '';
                if (publishField) publishField.checked = false;
                if (logoIdField) logoIdField.value = '0';
                if (logoPreview) {
                    logoPreview.src = '';
                    logoPreview.style.display = 'none';
                }
                if (linkedinField) linkedinField.value = '';
                if (twitterField) twitterField.value = '';
                if (facebookField) facebookField.value = '';
                if (instagramField) instagramField.value = '';
                if (teamMembersContainer) {
                    teamMembersContainer.innerHTML = '';
                }
            };

            const openCreate = () => {
                resetForm();
                if (modal) modal.style.display = 'block';
            };

            const openEdit = (id) => {
                const sponsor = sponsorMap[String(id)];
                if (!sponsor) return;
                resetForm();
                if (modalTitle) modalTitle.textContent = 'Edit Sponsor';
                if (actionField) actionField.value = 'khm_sponsor_update';
                if (sponsorIdField) sponsorIdField.value = String(sponsor.id || 0);
                if (nameField) nameField.value = sponsor.name || '';
                if (urlField) urlField.value = sponsor.url || '';
                if (primaryFirstNameField) primaryFirstNameField.value = sponsor.primary_contact_first_name || '';
                if (primaryLastNameField) primaryLastNameField.value = sponsor.primary_contact_last_name || '';
                if (primaryJobTitleField) primaryJobTitleField.value = sponsor.primary_contact_job_title || '';
                if (primaryEmailField) primaryEmailField.value = sponsor.primary_contact_email || sponsor.contact_email || '';
                if (publishField) publishField.checked = !!sponsor.publish_allowed;
                if (logoIdField) logoIdField.value = String(sponsor.logo_attachment_id || 0);
                if (logoPreview) {
                    if (sponsor.logo_url) {
                        logoPreview.src = sponsor.logo_url;
                        logoPreview.style.display = '';
                    } else {
                        logoPreview.src = '';
                        logoPreview.style.display = 'none';
                    }
                }
                if (linkedinField) linkedinField.value = (sponsor.social_profiles && sponsor.social_profiles.linkedin) ? sponsor.social_profiles.linkedin : '';
                if (twitterField) twitterField.value = (sponsor.social_profiles && sponsor.social_profiles.twitter) ? sponsor.social_profiles.twitter : '';
                if (facebookField) facebookField.value = (sponsor.social_profiles && sponsor.social_profiles.facebook) ? sponsor.social_profiles.facebook : '';
                if (instagramField) instagramField.value = (sponsor.social_profiles && sponsor.social_profiles.instagram) ? sponsor.social_profiles.instagram : '';
                if (teamMembersContainer) {
                    teamMembersContainer.innerHTML = '';
                    const existingMembers = Array.isArray(sponsor.team_members) ? sponsor.team_members : [];
                    if (existingMembers.length > 0) {
                        existingMembers.forEach((member) => addTeamMemberRow(member));
                    }
                }
                if (modal) modal.style.display = 'block';
            };

            if (openBtn) {
                openBtn.addEventListener('click', openCreate);
            }

            document.querySelectorAll('.khm-edit-sponsor').forEach((button) => {
                button.addEventListener('click', () => {
                    openEdit(button.getAttribute('data-sponsor-id') || '0');
                });
            });

            if (addTeamMemberButton) {
                addTeamMemberButton.addEventListener('click', () => {
                    addTeamMemberRow();
                });
            }

            if (nameField) {
                nameField.addEventListener('input', () => {
                    if (!teamMembersContainer) return;
                    const companyInputs = teamMembersContainer.querySelectorAll('.team-member-company');
                    companyInputs.forEach((input) => {
                        if (!input.value || input.value.trim() === '') {
                            input.value = nameField.value || '';
                        }
                    });
                });
            }

            if (closeBtn && modal) {
                closeBtn.addEventListener('click', () => {
                    modal.style.display = 'none';
                });
            }

            if (modal) {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }

            const pickLogoButton = document.getElementById('pick_sponsor_logo');
            if (pickLogoButton && typeof wp !== 'undefined' && wp.media) {
                pickLogoButton.addEventListener('click', function(){
                    const frame = wp.media({ title: 'Select sponsor logo', button: { text: 'Use logo' }, multiple: false });
                    frame.on('select', function(){
                        const item = frame.state().get('selection').first().toJSON();
                        if (logoIdField) logoIdField.value = item.id || '0';
                        if (logoPreview && item.url) {
                            logoPreview.src = item.url;
                            logoPreview.style.display = '';
                        }
                    });
                    frame.open();
                });
            }

            const queryMode = <?php echo wp_json_encode( $open_mode ); ?>;
            const querySponsorId = <?php echo (int) $open_sponsor_id; ?>;
            if (queryMode === 'new') {
                openCreate();
            } else if (querySponsorId > 0) {
                openEdit(String(querySponsorId));
            }
        })();
        </script>
        <?php
    }

    private function render_sponsor_detail_page( int $sponsor_id ): void {
        global $wpdb;
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $sponsor = array();
        if ( $sponsor_id > 0 ) {
            $sponsor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sponsors_table} WHERE id = %d", $sponsor_id ), ARRAY_A );
            if ( ! is_array( $sponsor ) ) {
                $sponsor = array();
            }
        }

        $social_profiles = json_decode( (string) ( $sponsor['social_profiles'] ?? '' ), true );
        if ( ! is_array( $social_profiles ) ) {
            $social_profiles = array();
        }

        $selected_levels = array_filter( array_map( 'absint', explode( ',', (string) ( $sponsor['team_member_levels'] ?? '' ) ) ) );
        $levels = $this->get_membership_levels();
        $logo_attachment_id = absint( $sponsor['logo_attachment_id'] ?? 0 );
        $logo_url = $logo_attachment_id ? wp_get_attachment_image_url( $logo_attachment_id, 'thumbnail' ) : '';

        ?>
        <div class="wrap">
            <h1><?php echo $sponsor_id > 0 ? esc_html__( 'Edit Sponsor', 'khm-membership' ) : esc_html__( 'Create Sponsor', 'khm-membership' ); ?></h1>
            <?php $this->render_notices(); ?>
            <p class="description" style="margin:8px 0 16px;"><?php esc_html_e( 'Complete sponsor details to support library attribution and approval workflows.', 'khm-membership' ); ?></p>
            <p>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khm-sponsorship' ) ); ?>"><?php esc_html_e( 'Back to Sponsors', 'khm-membership' ); ?></a>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_sponsor_save' ); ?>
                <input type="hidden" name="action" value="<?php echo $sponsor_id > 0 ? 'khm_sponsor_update' : 'khm_sponsor_create'; ?>" />
                <input type="hidden" name="sponsor_id" value="<?php echo esc_attr( $sponsor_id ); ?>" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sponsor_name"><?php esc_html_e( 'Sponsor Name', 'khm-membership' ); ?></label></th>
                        <td><input name="sponsor_name" id="sponsor_name" type="text" class="regular-text" required value="<?php echo esc_attr( $sponsor['name'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sponsor_url"><?php esc_html_e( 'Sponsor URL', 'khm-membership' ); ?></label></th>
                        <td><input name="sponsor_url" id="sponsor_url" type="url" class="regular-text" value="<?php echo esc_attr( $sponsor['url'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="contact_email"><?php esc_html_e( 'Contact Email', 'khm-membership' ); ?></label></th>
                        <td><input name="contact_email" id="contact_email" type="email" class="regular-text" value="<?php echo esc_attr( $sponsor['contact_email'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Logo', 'khm-membership' ); ?></th>
                        <td>
                            <input type="hidden" name="logo_attachment_id" id="logo_attachment_id" value="<?php echo esc_attr( $logo_attachment_id ); ?>" />
                            <button type="button" class="button" id="pick_sponsor_logo"><?php esc_html_e( 'Select Logo', 'khm-membership' ); ?></button>
                            <div style="margin-top:8px;">
                                <img id="sponsor_logo_preview" src="<?php echo esc_url( $logo_url ?: '' ); ?>" style="max-width:120px;<?php echo $logo_url ? '' : 'display:none;'; ?>" alt="" />
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="social_linkedin"><?php esc_html_e( 'LinkedIn', 'khm-membership' ); ?></label></th>
                        <td><input name="social_linkedin" id="social_linkedin" type="url" class="regular-text" value="<?php echo esc_attr( $social_profiles['linkedin'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="social_twitter"><?php esc_html_e( 'Twitter/X', 'khm-membership' ); ?></label></th>
                        <td><input name="social_twitter" id="social_twitter" type="url" class="regular-text" value="<?php echo esc_attr( $social_profiles['twitter'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="social_facebook"><?php esc_html_e( 'Facebook', 'khm-membership' ); ?></label></th>
                        <td><input name="social_facebook" id="social_facebook" type="url" class="regular-text" value="<?php echo esc_attr( $social_profiles['facebook'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="social_instagram"><?php esc_html_e( 'Instagram', 'khm-membership' ); ?></label></th>
                        <td><input name="social_instagram" id="social_instagram" type="url" class="regular-text" value="<?php echo esc_attr( $social_profiles['instagram'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="team_member_levels"><?php esc_html_e( 'Team Member Levels', 'khm-membership' ); ?></label></th>
                        <td>
                            <select name="team_member_levels[]" id="team_member_levels" multiple size="6" style="min-width:320px;">
                                <?php foreach ( $levels as $level ) : ?>
                                    <option value="<?php echo esc_attr( $level['id'] ); ?>" <?php selected( in_array( absint( $level['id'] ), $selected_levels, true ) ); ?>>
                                        <?php echo esc_html( $level['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( $sponsor_id > 0 ? __( 'Update Sponsor', 'khm-membership' ) : __( 'Create Sponsor', 'khm-membership' ) ); ?>
            </form>
        </div>
        <script>
        (function(){
            const pick = document.getElementById('pick_sponsor_logo');
            if (!pick || typeof wp === 'undefined' || !wp.media) return;
            pick.addEventListener('click', function(){
                const frame = wp.media({ title: 'Select sponsor logo', button: { text: 'Use logo' }, multiple: false });
                frame.on('select', function(){
                    const item = frame.state().get('selection').first().toJSON();
                    const idField = document.getElementById('logo_attachment_id');
                    const preview = document.getElementById('sponsor_logo_preview');
                    if (idField) idField.value = item.id || '';
                    if (preview && item.url) {
                        preview.src = item.url;
                        preview.style.display = '';
                    }
                });
                frame.open();
            });
        })();
        </script>
        <?php
    }

    public function render_libraries_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $library_id = isset( $_GET['library_id'] ) ? absint( $_GET['library_id'] ) : 0;
        if ( $library_id > 0 ) {
            $this->render_library_detail_page( $library_id );
            return;
        }

        global $wpdb;
        $sponsors_table = SponsorMigration::sponsors_table_name();
        $sponsors = $wpdb->get_results( "SELECT * FROM {$sponsors_table} ORDER BY name ASC", ARRAY_A );
        $libraries = SponsorIngest::list_libraries( 500 );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Libraries', 'khm-membership' ); ?></h1>
            <?php $this->render_notices(); ?>
            <p class="description" style="margin:8px 0 16px;"><?php esc_html_e( 'Libraries group sponsor files by topic and track approval status.', 'khm-membership' ); ?></p>

            <p>
                <button type="button" class="button button-primary" id="open_library_modal"><?php esc_html_e( 'Add New Library', 'khm-membership' ); ?></button>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Library', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Sponsor Name', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Number of Files', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Number of Files Awaiting Approval', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Last Updated', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $libraries ) ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No libraries yet.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $libraries as $library ) : ?>
                            <tr class="khm-library-row" data-href="<?php echo esc_url( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . absint( $library['id'] ) ) ); ?>">
                                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . absint( $library['id'] ) ) ); ?>"><?php echo esc_html( $library['name'] ); ?></a></td>
                                <td><?php echo esc_html( $library['sponsor_name'] ); ?></td>
                                <td><?php echo esc_html( $library['file_count'] ); ?></td>
                                <td><?php echo esc_html( $library['pending_count'] ); ?></td>
                                <td><?php echo esc_html( $library['last_updated'] ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="library_modal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.35);z-index:99999;">
                <div style="background:#fff;max-width:840px;margin:40px auto;padding:20px;max-height:85vh;overflow:auto;">
                    <h2><?php esc_html_e( 'Add Library Content', 'khm-membership' ); ?></h2>
                    <p class="description" style="margin:0 0 12px;"><?php esc_html_e( 'Choose how you want to populate this library. Metadata (Title, Author, Publication Date, Sponsor, and thumbnail when available) is collected automatically and can be edited later.', 'khm-membership' ); ?></p>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'khm_sponsor_doc_bulk_import' ); ?>
                        <input type="hidden" name="action" value="khm_sponsor_doc_bulk_import" />

                        <table class="form-table">
                            <tr>
                                <th><label for="modal_sponsor_id"><?php esc_html_e( 'Sponsor', 'khm-membership' ); ?></label></th>
                                <td>
                                    <select name="sponsor_id" id="modal_sponsor_id" required>
                                        <option value=""><?php esc_html_e( 'Select sponsor', 'khm-membership' ); ?></option>
                                        <?php foreach ( $sponsors as $sponsor ) : ?>
                                            <option value="<?php echo esc_attr( $sponsor['id'] ); ?>"><?php echo esc_html( $sponsor['name'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="library_name"><?php esc_html_e( 'Library Name', 'khm-membership' ); ?></label></th>
                                <td><input type="text" name="library_name" id="library_name" class="regular-text" placeholder="SponsorXTopic" /></td>
                            </tr>
                            <tr>
                                <th><label for="upload_type"><?php esc_html_e( 'Upload Type', 'khm-membership' ); ?></label></th>
                                <td>
                                    <select name="upload_type" id="upload_type" required>
                                        <option value="single_document"><?php esc_html_e( 'Single document URL', 'khm-membership' ); ?></option>
                                        <option value="library_source"><?php esc_html_e( 'Parent Library URL', 'khm-membership' ); ?></option>
                                        <option value="csv_links"><?php esc_html_e( 'Bulk links via CSV', 'khm-membership' ); ?></option>
                                        <option value="single_file_upload"><?php esc_html_e( 'Single file upload', 'khm-membership' ); ?></option>
                                        <option value="bulk_documents"><?php esc_html_e( 'Multi-file upload', 'khm-membership' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="use_existing_library"><?php esc_html_e( 'Add to Existing Library', 'khm-membership' ); ?></label></th>
                                <td><input type="checkbox" name="use_existing_library" id="use_existing_library" value="1" /></td>
                            </tr>
                            <tr id="existing_library_row" style="display:none;">
                                <th><label for="existing_library_id"><?php esc_html_e( 'Existing Library', 'khm-membership' ); ?></label></th>
                                <td>
                                    <select name="existing_library_id" id="existing_library_id">
                                        <option value=""><?php esc_html_e( 'Select library', 'khm-membership' ); ?></option>
                                        <?php foreach ( $libraries as $library ) : ?>
                                            <option value="<?php echo esc_attr( $library['id'] ); ?>" data-sponsor="<?php echo esc_attr( $library['sponsor_id'] ); ?>"><?php echo esc_html( $library['name'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Only libraries for the selected sponsor are shown.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                            <tr id="single_doc_url_row">
                                <th><label for="single_doc_url"><?php esc_html_e( 'Single Document URL', 'khm-membership' ); ?></label></th>
                                <td><input type="url" name="single_doc_url" id="single_doc_url" class="regular-text" placeholder="https://example.com/doc" /></td>
                            </tr>
                            <tr id="source_url_row" style="display:none;">
                                <th><label for="source_root_url"><?php esc_html_e( 'Parent Library URL', 'khm-membership' ); ?></label></th>
                                <td>
                                    <input type="url" name="source_root_url" id="source_root_url" class="regular-text" placeholder="https://example.com/resources" />
                                    <p class="description"><?php esc_html_e( 'Runs a guarded crawl on allowed pages and queues results to this library.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                            <tr id="csv_row" style="display:none;">
                                <th><label for="bulk_csv"><?php esc_html_e( 'CSV Upload', 'khm-membership' ); ?></label></th>
                                <td>
                                    <input name="bulk_csv" id="bulk_csv" type="file" accept=".csv,text/csv" />
                                    <p class="description"><?php esc_html_e( 'Expected columns: url, title, authors, publisher, pub_date.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                            <tr id="single_file_row" style="display:none;">
                                <th><label for="single_file"><?php esc_html_e( 'Single File Upload', 'khm-membership' ); ?></label></th>
                                <td>
                                    <input name="single_file" id="single_file" type="file" accept=".pdf,application/pdf" />
                                    <p class="description"><?php esc_html_e( 'PDF only.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                            <tr id="files_row" style="display:none;">
                                <th><label for="bulk_files"><?php esc_html_e( 'Bulk File Upload', 'khm-membership' ); ?></label></th>
                                <td>
                                    <input name="bulk_files[]" id="bulk_files" type="file" multiple accept=".pdf,application/pdf" />
                                    <p class="description"><?php esc_html_e( 'PDF only.', 'khm-membership' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Queue Import', 'khm-membership' ); ?></button>
                            <button type="button" class="button" id="close_library_modal"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function(){
            const modal = document.getElementById('library_modal');
            const openBtn = document.getElementById('open_library_modal');
            const closeBtn = document.getElementById('close_library_modal');
            const uploadType = document.getElementById('upload_type');
            const existingToggle = document.getElementById('use_existing_library');
            const existingRow = document.getElementById('existing_library_row');
            const sponsorSelect = document.getElementById('modal_sponsor_id');
            const existingLibrarySelect = document.getElementById('existing_library_id');
            const rows = {
                single_document: document.getElementById('single_doc_url_row'),
                library_source: document.getElementById('source_url_row'),
                csv_links: document.getElementById('csv_row'),
                single_file_upload: document.getElementById('single_file_row'),
                bulk_documents: document.getElementById('files_row')
            };

            const refreshUploadType = () => {
                Object.values(rows).forEach((row) => { if (row) row.style.display = 'none'; });
                const key = uploadType ? uploadType.value : 'single_document';
                if (rows[key]) rows[key].style.display = '';
            };

            const refreshExistingLibraries = () => {
                if (!existingLibrarySelect) return;
                const sponsor = sponsorSelect ? sponsorSelect.value : '';
                Array.from(existingLibrarySelect.options).forEach((opt, idx) => {
                    if (idx === 0) {
                        opt.hidden = false;
                        return;
                    }
                    opt.hidden = !sponsor || opt.getAttribute('data-sponsor') !== sponsor;
                });
            };

            const refreshExistingToggle = () => {
                if (!existingRow) return;
                existingRow.style.display = existingToggle && existingToggle.checked ? '' : 'none';
                refreshExistingLibraries();
            };

            if (openBtn && modal) {
                openBtn.addEventListener('click', () => modal.style.display = 'block');
            }
            if (closeBtn && modal) {
                closeBtn.addEventListener('click', () => modal.style.display = 'none');
            }
            if (modal) {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }
            document.querySelectorAll('.khm-library-row').forEach((row) => {
                row.style.cursor = 'pointer';
                row.addEventListener('click', (event) => {
                    const target = event.target;
                    if (target && target.tagName && target.tagName.toLowerCase() === 'a') {
                        return;
                    }
                    const href = row.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });
            if (uploadType) uploadType.addEventListener('change', refreshUploadType);
            if (existingToggle) existingToggle.addEventListener('change', refreshExistingToggle);
            if (sponsorSelect) sponsorSelect.addEventListener('change', refreshExistingLibraries);

            refreshUploadType();
            refreshExistingToggle();
        })();
        </script>
        <?php
    }

    private function render_library_detail_page( int $library_id ): void {
        $library = SponsorIngest::get_library( $library_id );
        if ( empty( $library ) ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&error=missing' ) );
            exit;
        }

        $docs = SponsorIngest::list_docs_by_library( $library_id, 1000 );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( (string) $library['name'] ); ?></h1>
            <?php $this->render_notices(); ?>
            <p class="description" style="margin:8px 0 16px;"><?php esc_html_e( 'Review and approve imported files for this library.', 'khm-membership' ); ?></p>
            <p>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khm-sponsorship-libraries' ) ); ?>"><?php esc_html_e( 'Back to Libraries', 'khm-membership' ); ?></a>
            </p>
            <p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                    <?php wp_nonce_field( 'khm_sponsor_library_bulk_approve' ); ?>
                    <input type="hidden" name="action" value="khm_sponsor_library_bulk_approve" />
                    <input type="hidden" name="library_id" value="<?php echo esc_attr( $library_id ); ?>" />
                    <?php submit_button( __( 'Bulk Approve', 'khm-membership' ), 'primary', 'submit', false ); ?>
                </form>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Author', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Publication Date', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Cover Thumbnail', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Approve', 'khm-membership' ); ?></th>
                        <th><?php esc_html_e( 'Edit', 'khm-membership' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $docs ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'No files in this library.', 'khm-membership' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $docs as $doc ) : ?>
                            <tr>
                                <td><?php echo esc_html( $doc['title'] ); ?></td>
                                <td><?php echo esc_html( $doc['authors'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $doc['pub_date'] ?? '' ); ?></td>
                                <td>
                                    <?php if ( ! empty( $doc['cover_thumbnail_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $doc['cover_thumbnail_url'] ); ?>" alt="" style="max-width:48px;height:auto;" />
                                    <?php else : ?>
                                        <span>—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( empty( $doc['approved'] ) ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'khm_sponsor_doc_approve' ); ?>
                                            <input type="hidden" name="action" value="khm_sponsor_doc_approve" />
                                            <input type="hidden" name="doc_id" value="<?php echo esc_attr( $doc['id'] ); ?>" />
                                            <input type="hidden" name="redirect_library_id" value="<?php echo esc_attr( $library_id ); ?>" />
                                            <?php submit_button( __( 'Approve', 'khm-membership' ), 'secondary small', 'submit', false ); ?>
                                        </form>
                                    <?php else : ?>
                                        <span style="color:#2e7d32;font-weight:700;">✔</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-secondary small khm-doc-edit"
                                        data-id="<?php echo esc_attr( $doc['id'] ); ?>"
                                        data-title="<?php echo esc_attr( $doc['title'] ); ?>"
                                        data-authors="<?php echo esc_attr( $doc['authors'] ?? '' ); ?>"
                                        data-pubdate="<?php echo esc_attr( $doc['pub_date'] ?? '' ); ?>"
                                        data-thumbnail="<?php echo esc_attr( $doc['cover_thumbnail_url'] ?? '' ); ?>"
                                        data-url="<?php echo esc_attr( $doc['url'] ?? '' ); ?>"
                                    ><?php esc_html_e( 'Edit', 'khm-membership' ); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="doc_edit_modal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.35);z-index:99999;">
                <div style="background:#fff;max-width:760px;margin:50px auto;padding:20px;max-height:85vh;overflow:auto;">
                    <h2><?php esc_html_e( 'Edit Document', 'khm-membership' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'khm_sponsor_doc_update' ); ?>
                        <input type="hidden" name="action" value="khm_sponsor_doc_update" />
                        <input type="hidden" name="doc_id" id="edit_doc_id" value="" />
                        <input type="hidden" name="library_id" value="<?php echo esc_attr( $library_id ); ?>" />
                        <table class="form-table">
                            <tr><th><label for="edit_title"><?php esc_html_e( 'Title', 'khm-membership' ); ?></label></th><td><input type="text" name="title" id="edit_title" class="regular-text" required /></td></tr>
                            <tr><th><label for="edit_url"><?php esc_html_e( 'URL', 'khm-membership' ); ?></label></th><td><input type="url" name="url" id="edit_url" class="regular-text" required /></td></tr>
                            <tr><th><label for="edit_authors"><?php esc_html_e( 'Author', 'khm-membership' ); ?></label></th><td><input type="text" name="authors" id="edit_authors" class="regular-text" /></td></tr>
                            <tr><th><label for="edit_pub_date"><?php esc_html_e( 'Publication Date', 'khm-membership' ); ?></label></th><td><input type="date" name="pub_date" id="edit_pub_date" class="regular-text" /></td></tr>
                            <tr><th><label for="edit_cover_thumbnail_url"><?php esc_html_e( 'Cover Thumbnail URL', 'khm-membership' ); ?></label></th><td><input type="url" name="cover_thumbnail_url" id="edit_cover_thumbnail_url" class="regular-text" /></td></tr>
                        </table>
                        <p>
                            <?php submit_button( __( 'Save Changes', 'khm-membership' ), 'primary', 'submit', false ); ?>
                            <button type="button" class="button" id="doc_edit_close"><?php esc_html_e( 'Cancel', 'khm-membership' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const modal = document.getElementById('doc_edit_modal');
            const close = document.getElementById('doc_edit_close');
            document.querySelectorAll('.khm-doc-edit').forEach((btn) => {
                btn.addEventListener('click', () => {
                    document.getElementById('edit_doc_id').value = btn.getAttribute('data-id') || '';
                    document.getElementById('edit_title').value = btn.getAttribute('data-title') || '';
                    document.getElementById('edit_authors').value = btn.getAttribute('data-authors') || '';
                    document.getElementById('edit_pub_date').value = btn.getAttribute('data-pubdate') || '';
                    document.getElementById('edit_cover_thumbnail_url').value = btn.getAttribute('data-thumbnail') || '';
                    document.getElementById('edit_url').value = btn.getAttribute('data-url') || '';
                    modal.style.display = 'block';
                });
            });
            if (close) close.addEventListener('click', () => modal.style.display = 'none');
        })();
        </script>
        <?php
    }

    public function handle_sponsor_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_save' );

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        $name = sanitize_text_field( $_POST['sponsor_name'] ?? '' );
        $url = esc_url_raw( $_POST['sponsor_url'] ?? '' );
        $logo_attachment_id = absint( $_POST['logo_attachment_id'] ?? 0 );
        $primary_contact_first_name = sanitize_text_field( $_POST['primary_contact_first_name'] ?? '' );
        $primary_contact_last_name = sanitize_text_field( $_POST['primary_contact_last_name'] ?? '' );
        $primary_contact_job_title = sanitize_text_field( $_POST['primary_contact_job_title'] ?? '' );
        $primary_contact_email = sanitize_email( $_POST['primary_contact_email'] ?? '' );

        if ( ! $name ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship&error=missing' ) );
            exit;
        }

        $social_profiles = wp_json_encode(
            array_filter(
                array(
                    'linkedin' => esc_url_raw( $_POST['social_linkedin'] ?? '' ),
                    'twitter' => esc_url_raw( $_POST['social_twitter'] ?? '' ),
                    'facebook' => esc_url_raw( $_POST['social_facebook'] ?? '' ),
                    'instagram' => esc_url_raw( $_POST['social_instagram'] ?? '' ),
                )
            )
        );

        $team_members = self::build_team_members_from_post( $_POST['team_members'] ?? array() );
        
        // Link team members to WordPress users and assign memberships
        $team_members = self::link_team_members_to_users( $team_members, $sponsor_id, $name );
        
        $team_member_levels = array_values(
            array_unique(
                array_filter(
                    array_map(
                        function( array $member ): string {
                            return sanitize_text_field( (string) ( $member['membership_level'] ?? '' ) );
                        },
                        $team_members
                    )
                )
            )
        );

        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();

        $data = array(
            'name' => $name,
            'url' => $url,
            'contact_email' => $primary_contact_email,
            'primary_contact_first_name' => $primary_contact_first_name,
            'primary_contact_last_name' => $primary_contact_last_name,
            'primary_contact_job_title' => $primary_contact_job_title,
            'primary_contact_email' => $primary_contact_email,
            'publish_allowed' => 1,
            'logo_attachment_id' => $logo_attachment_id ?: null,
            'social_profiles' => $social_profiles,
            'team_member_levels' => implode( ',', $team_member_levels ),
            'team_members' => wp_json_encode( $team_members ),
        );

        if ( $sponsor_id > 0 ) {
            $wpdb->update(
                $table,
                $data,
                array( 'id' => $sponsor_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship&updated=1' ) );
            exit;
        }

        $data['created_by'] = get_current_user_id();
        $wpdb->insert(
            $table,
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
        );

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship&created=1' ) );
        exit;
    }

    public function handle_library_create(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_library_create' );

        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        $library_name = sanitize_text_field( $_POST['library_name'] ?? '' );
        $library_topic = sanitize_text_field( $_POST['library_topic'] ?? '' );
        if ( ! $sponsor_id || '' === $library_name ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&error=missing' ) );
            exit;
        }

        $library_id = SponsorIngest::create_library(
            array(
                'sponsor_id' => $sponsor_id,
                'name' => $library_name,
                'topic' => $library_topic,
                'created_by' => get_current_user_id(),
            )
        );

        if ( ! $library_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&error=missing' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . $library_id . '&created=1' ) );
        exit;
    }

    public function handle_library_upload(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_doc_bulk_import' );

        if ( ! SponsorMigration::table_exists() ) {
            SponsorMigration::create_tables();
        }

        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        $upload_type = sanitize_key( $_POST['upload_type'] ?? 'single_document' );
        $use_existing = ! empty( $_POST['use_existing_library'] );
        $library_id = $use_existing ? absint( $_POST['existing_library_id'] ?? 0 ) : 0;

        if ( ! $library_id ) {
            $library_name = sanitize_text_field( $_POST['library_name'] ?? '' );
            $library_topic = sanitize_text_field( $_POST['library_topic'] ?? '' );
            if ( ! $sponsor_id || '' === $library_name ) {
                wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
                exit;
            }

            $library_id = SponsorIngest::create_library(
                array(
                    'sponsor_id' => $sponsor_id,
                    'name' => $library_name,
                    'topic' => $library_topic,
                    'created_by' => get_current_user_id(),
                )
            );
        }

        if ( ! $sponsor_id || ! $library_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        if ( 'library_source' === $upload_type ) {
            $source_id = SponsorIngest::create_source(
                array(
                    'sponsor_id' => $sponsor_id,
                    'library_id' => $library_id,
                    'root_url' => esc_url_raw( $_POST['source_root_url'] ?? '' ),
                    'domain_allowlist' => '',
                    'max_pages' => 25,
                    'max_depth' => 2,
                    'max_response_kb' => 512,
                    'created_by' => get_current_user_id(),
                )
            );
            if ( $source_id ) {
                SponsorIngest::queue_source_crawl_job( $source_id, 0, get_current_user_id() );
            }
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . $library_id . '&source_queued=1' ) );
            exit;
        }

        $items = array();
        $source_type = 'single_document';

        if ( 'single_document' === $upload_type ) {
            $items = SponsorIngest::normalize_items(
                array(
                    array(
                        'url' => esc_url_raw( $_POST['single_doc_url'] ?? '' ),
                    ),
                )
            );
        } elseif ( 'csv_links' === $upload_type ) {
            $source_type = 'csv';
            if ( ! empty( $_FILES['bulk_csv']['tmp_name'] ) ) {
                $items = SponsorIngest::collect_items_from_csv_file( $_FILES['bulk_csv'] );
            }
        } elseif ( 'single_file_upload' === $upload_type ) {
            $source_type = 'file';
            if ( ! empty( $_FILES['single_file']['tmp_name'] ) ) {
                $single_file = $_FILES['single_file'];
                $normalized_files = array(
                    'name' => array( $single_file['name'] ?? '' ),
                    'type' => array( $single_file['type'] ?? '' ),
                    'tmp_name' => array( $single_file['tmp_name'] ?? '' ),
                    'error' => array( $single_file['error'] ?? UPLOAD_ERR_NO_FILE ),
                    'size' => array( $single_file['size'] ?? 0 ),
                );
                $items = SponsorIngest::collect_items_from_uploaded_files( $normalized_files );
            }
        } elseif ( 'bulk_documents' === $upload_type ) {
            $source_type = 'files';
            $items = SponsorIngest::collect_items_from_uploaded_files( $_FILES['bulk_files'] ?? array() );
        }

        if ( empty( $items ) ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        $job_id = SponsorIngest::create_job( $sponsor_id, $items, 0, $source_type, get_current_user_id(), $library_id );
        if ( ! $job_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . $library_id . '&bulk_queued=1&job_id=' . $job_id ) );
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
                'library_id'       => absint( $_POST['library_id'] ?? 0 ),
                'root_url'         => esc_url_raw( $_POST['root_url'] ?? '' ),
                'domain_allowlist' => sanitize_text_field( $_POST['domain_allowlist'] ?? '' ),
                'max_pages'        => absint( $_POST['max_pages'] ?? 25 ),
                'max_depth'        => absint( $_POST['max_depth'] ?? 2 ),
                'max_response_kb'  => absint( $_POST['max_response_kb'] ?? 512 ),
                'created_by'       => get_current_user_id(),
            )
        );

        if ( ! $source_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&source_created=1' ) );
        exit;
    }

    public function handle_source_run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_source_run' );
        $source_id = absint( $_POST['source_id'] ?? 0 );
        if ( ! $source_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        $job_id = SponsorIngest::queue_source_crawl_job( $source_id, 0, get_current_user_id() );
        if ( ! $job_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&source_queued=1&job_id=' . $job_id ) );
        exit;
    }

    public function handle_library_bulk_approve(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_library_bulk_approve' );
        $library_id = absint( $_POST['library_id'] ?? 0 );
        if ( ! $library_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        SponsorIngest::approve_docs_by_library( $library_id );
        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . $library_id . '&bulk_approved=1' ) );
        exit;
    }

    public function handle_doc_update(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_doc_update' );
        $doc_id = absint( $_POST['doc_id'] ?? 0 );
        $library_id = absint( $_POST['library_id'] ?? 0 );
        if ( ! $doc_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        global $wpdb;
        $table = SponsorMigration::docs_table_name();
        $wpdb->update(
            $table,
            array(
                'title' => sanitize_text_field( $_POST['title'] ?? '' ),
                'url' => esc_url_raw( $_POST['url'] ?? '' ),
                'authors' => sanitize_text_field( $_POST['authors'] ?? '' ),
                'pub_date' => ! empty( $_POST['pub_date'] ) ? sanitize_text_field( $_POST['pub_date'] ) : null,
                'cover_thumbnail_url' => esc_url_raw( $_POST['cover_thumbnail_url'] ?? '' ),
            ),
            array( 'id' => $doc_id ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . $library_id . '&updated=1' ) );
        exit;
    }

    public function handle_bulk_approve_imported(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_bulk_approve_imported' );
        $sponsor_id = absint( $_POST['sponsor_id'] ?? 0 );
        if ( ! $sponsor_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_error=1' ) );
            exit;
        }

        SponsorIngest::approve_imported_docs_by_sponsor( $sponsor_id );
        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&bulk_approved=1' ) );
        exit;
    }

    public function handle_approve(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-membership' ) );
        }

        check_admin_referer( 'khm_sponsor_doc_approve' );
        $doc_id = absint( $_POST['doc_id'] ?? 0 );
        if ( ! $doc_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&error=missing' ) );
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

        $library_id = absint( $_POST['redirect_library_id'] ?? 0 );
        if ( $library_id ) {
            wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&library_id=' . $library_id . '&approved=1' ) );
            exit;
        }

        wp_redirect( admin_url( 'admin.php?page=khm-sponsorship-libraries&approved=1' ) );
        exit;
    }

    public function handle_ingest_job_event( $job_id ): void {
        SponsorIngest::process_job( absint( $job_id ) );
    }

    private function get_membership_levels(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'membership_tier';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return array();
        }

        $rows = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY name ASC", ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    private static function normalize_team_member_for_ui( $member ): array {
        if ( ! is_array( $member ) ) {
            return array(
                'first_name' => '',
                'last_name' => '',
                'company' => '',
                'job_title' => '',
                'work_phone' => '',
                'work_email' => '',
                'membership_level' => 'sponsor',
            );
        }

        $normalized = array(
            'first_name' => sanitize_text_field( (string) ( $member['first_name'] ?? '' ) ),
            'last_name' => sanitize_text_field( (string) ( $member['last_name'] ?? '' ) ),
            'company' => sanitize_text_field( (string) ( $member['company'] ?? '' ) ),
            'job_title' => sanitize_text_field( (string) ( $member['job_title'] ?? '' ) ),
            'work_phone' => sanitize_text_field( (string) ( $member['work_phone'] ?? '' ) ),
            'work_email' => sanitize_email( (string) ( $member['work_email'] ?? '' ) ),
            'membership_level' => sanitize_text_field( (string) ( $member['membership_level'] ?? 'sponsor' ) ),
        );

        // Preserve user_id if it exists
        if ( isset( $member['user_id'] ) && absint( $member['user_id'] ) > 0 ) {
            $normalized['user_id'] = absint( $member['user_id'] );
        }

        return $normalized;
    }

    /**
     * Link team members to WordPress users and assign memberships.
     * 
     * @param array $team_members Array of team member data
     * @param int $sponsor_id Sponsor ID for context
     * @param string $sponsor_name Sponsor name for user meta
     * @return array Updated team members with user_id added
     */
    private static function link_team_members_to_users( array $team_members, int $sponsor_id, string $sponsor_name ): array {
        if ( empty( $team_members ) ) {
            return $team_members;
        }

        // Load membership services
        if ( ! class_exists( 'KHM\\Services\\MembershipRepository' ) || ! class_exists( 'KHM\\Services\\LevelRepository' ) ) {
            return $team_members;
        }

        $memberships_repo = new \KHM\Services\MembershipRepository();
        $levels_repo = new \KHM\Services\LevelRepository();

        // Process each team member
        foreach ( $team_members as $index => &$member ) {
            $work_email = trim( (string) ( $member['work_email'] ?? '' ) );
            $first_name = trim( (string) ( $member['first_name'] ?? '' ) );
            $last_name = trim( (string) ( $member['last_name'] ?? '' ) );
            $job_title = trim( (string) ( $member['job_title'] ?? '' ) );
            $company = trim( (string) ( $member['company'] ?? '' ) );
            $membership_level_slug = sanitize_text_field( (string) ( $member['membership_level'] ?? 'sponsor' ) );

            // Skip if no email
            if ( '' === $work_email || ! is_email( $work_email ) ) {
                continue;
            }

            // Find or create WordPress user
            $user = get_user_by( 'email', $work_email );
            
            if ( ! $user ) {
                // Create new user
                $username = self::generate_username_from_email( $work_email );
                $password = wp_generate_password( 24, true, true );
                
                $user_id = wp_insert_user(
                    array(
                        'user_login' => $username,
                        'user_email' => $work_email,
                        'user_pass' => $password,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'display_name' => trim( $first_name . ' ' . $last_name ),
                        'role' => 'subscriber',
                    )
                );

                if ( is_wp_error( $user_id ) ) {
                    error_log( 'KHM: Failed to create user for team member: ' . $user_id->get_error_message() );
                    continue;
                }

                $user = get_user_by( 'id', $user_id );
                
                // Send password reset email
                wp_new_user_notification( $user_id, null, 'user' );
            } else {
                $user_id = (int) $user->ID;
            }

            // Store user_id in team member record
            $member['user_id'] = $user_id;

            // Update user meta
            if ( $job_title ) {
                update_user_meta( $user_id, 'job_title', $job_title );
            }
            if ( $company ) {
                update_user_meta( $user_id, 'company', $company );
            }
            
            // Link to sponsor
            update_user_meta( $user_id, 'sponsor_id', $sponsor_id );
            update_user_meta( $user_id, 'sponsor_name', $sponsor_name );

            // Find membership level by slug
            $levels = $levels_repo->getAll();
            $target_level_id = null;
            foreach ( $levels as $level ) {
                if ( isset( $level->slug ) && sanitize_title( (string) $level->slug ) === $membership_level_slug ) {
                    $target_level_id = (int) $level->id;
                    break;
                }
            }

            // Assign membership if level found
            if ( $target_level_id ) {
                try {
                    $existing_memberships = $memberships_repo->findActive( $user_id );
                    
                    if ( empty( $existing_memberships ) ) {
                        // No existing membership - create new
                        $memberships_repo->assign( $user_id, $target_level_id, array( 'status' => 'active' ) );
                    } else {
                        // Check if they already have this level
                        $has_level = false;
                        foreach ( $existing_memberships as $existing ) {
                            if ( (int) $existing->membership_id === $target_level_id ) {
                                $has_level = true;
                                break;
                            }
                        }
                        
                        // Only change level if different
                        if ( ! $has_level && isset( $existing_memberships[0]->id ) ) {
                            $memberships_repo->changeLevelById( (int) $existing_memberships[0]->id, $target_level_id, array( 'status' => 'active' ) );
                        }
                    }
                } catch ( \Throwable $e ) {
                    error_log( 'KHM: Failed to assign membership for team member: ' . $e->getMessage() );
                }
            }
        }
        unset( $member );

        return $team_members;
    }

    /**
     * Generate a unique username from email address.
     */
    private static function generate_username_from_email( string $email ): string {
        $base = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ?: strlen( $email ) ), true );
        $username = $base;
        $suffix = 1;

        while ( username_exists( $username ) ) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    private static function build_team_members_from_post( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $first_names = is_array( $raw['first_name'] ?? null ) ? $raw['first_name'] : array();
        $last_names = is_array( $raw['last_name'] ?? null ) ? $raw['last_name'] : array();
        $companies = is_array( $raw['company'] ?? null ) ? $raw['company'] : array();
        $job_titles = is_array( $raw['job_title'] ?? null ) ? $raw['job_title'] : array();
        $work_phones = is_array( $raw['work_phone'] ?? null ) ? $raw['work_phone'] : array();
        $work_emails = is_array( $raw['work_email'] ?? null ) ? $raw['work_email'] : array();
        $membership_levels = is_array( $raw['membership_level'] ?? null ) ? $raw['membership_level'] : array();

        $max_count = max(
            count( $first_names ),
            count( $last_names ),
            count( $companies ),
            count( $job_titles ),
            count( $work_phones ),
            count( $work_emails ),
            count( $membership_levels )
        );

        $members = array();
        for ( $index = 0; $index < $max_count; $index++ ) {
            $member = self::normalize_team_member_for_ui(
                array(
                    'first_name' => $first_names[ $index ] ?? '',
                    'last_name' => $last_names[ $index ] ?? '',
                    'company' => $companies[ $index ] ?? '',
                    'job_title' => $job_titles[ $index ] ?? '',
                    'work_phone' => $work_phones[ $index ] ?? '',
                    'work_email' => $work_emails[ $index ] ?? '',
                    'membership_level' => $membership_levels[ $index ] ?? 'sponsor',
                )
            );

            $has_data = '' !== trim( (string) $member['first_name'] )
                || '' !== trim( (string) $member['last_name'] )
                || '' !== trim( (string) $member['work_email'] )
                || '' !== trim( (string) $member['company'] );

            if ( $has_data ) {
                $members[] = $member;
            }
        }

        return $members;
    }

    private function render_notices(): void {
        $success_keys = array( 'created', 'updated', 'bulk_queued', 'bulk_approved', 'approved', 'source_queued', 'source_created' );
        foreach ( $success_keys as $key ) {
            if ( isset( $_GET[ $key ] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Changes saved.', 'khm-membership' ) . '</p></div>';
                return;
            }
        }

        if ( isset( $_GET['bulk_error'] ) || isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Something was missing or could not be processed.', 'khm-membership' ) . '</p></div>';
        }
    }
}

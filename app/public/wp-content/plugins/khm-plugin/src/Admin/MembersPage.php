<?php
namespace KHM\Admin;

use DateTime;
use KHM\Services\LevelRepository;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\MembershipNoteService;
use KHM\Services\SubscriptionManagementService;

class MembersPage {
	public const PAGE_SLUG      = 'khm-members';
	public const SETTINGS_GROUP = 'khm_memberships';

	private MembershipRepository $memberships;
	private LevelRepository $levels;
	private OrderRepository $orders;
	private MembershipNoteService $notes;
	private SubscriptionManagementService $subscriptions;

	public function __construct( ?MembershipRepository $memberships = null, ?LevelRepository $levels = null, ?OrderRepository $orders = null, ?MembershipNoteService $notes = null, ?SubscriptionManagementService $subscriptions = null ) {
		$this->memberships = $memberships ?: new MembershipRepository();
		$this->levels      = $levels ?: new LevelRepository();
		$this->orders      = $orders ?: new OrderRepository();
		$this->notes       = $notes ?: new MembershipNoteService();
		$this->subscriptions = $subscriptions ?: new SubscriptionManagementService( $this->orders, $this->memberships );
	}

	public function register(): void {
		add_action( 'admin_post_khm_membership_cancel', [ $this, 'handle_cancel_request' ] );
		add_action( 'admin_post_khm_membership_reactivate', [ $this, 'handle_reactivate_request' ] );
		add_action( 'admin_post_khm_membership_expire', [ $this, 'handle_expire_request' ] );
		add_action( 'admin_post_khm_membership_delete', [ $this, 'handle_delete_request' ] );
		add_action( 'admin_post_khm_membership_update_end', [ $this, 'handle_update_end_request' ] );
		add_action( 'admin_post_khm_membership_add_note', [ $this, 'handle_add_note' ] );
		add_action( 'admin_post_khm_membership_delete_note', [ $this, 'handle_delete_note' ] );
		add_action( 'admin_post_khm_membership_pause', [ $this, 'handle_pause_request' ] );
		add_action( 'admin_post_khm_membership_resume', [ $this, 'handle_resume_request' ] );
		add_action( 'admin_post_khm_assign_membership', [ $this, 'handle_assign_request' ] );
		add_action( 'admin_post_khm_add_credits', [ $this, 'handle_add_credits_request' ] );
		add_action( 'wp_ajax_khm_user_lookup', [ $this, 'ajax_user_lookup' ] );

		// User profile hooks (edit and add-new user screens)
		add_action( 'show_user_profile', [ $this, 'render_user_membership_fields' ] );
		add_action( 'edit_user_profile', [ $this, 'render_user_membership_fields' ] );
		add_action( 'user_new_form', [ $this, 'render_user_membership_fields_new' ] );
		add_action( 'personal_options_update', [ $this, 'handle_user_membership_save' ] );
		add_action( 'edit_user_profile_update', [ $this, 'handle_user_membership_save' ] );
		add_action( 'user_register', [ $this, 'handle_user_membership_save' ] );
		add_action( 'admin_head-user-new.php', [ $this, 'customize_user_form_fields' ] );
		add_action( 'admin_head-user-edit.php', [ $this, 'customize_user_form_fields' ] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage members.', 'khm-membership' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'add' === $action ) {
			$this->render_add_member();
			return;
		}

		if ( 'add_credits' === $action ) {
			$this->render_add_credits_form();
			return;
		}

		if ( in_array( $action, [ 'view', 'edit' ], true ) ) {
			$membership_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

			if ( ! $membership_id ) {
				$this->add_notice( 'invalid_membership', __( 'Invalid membership selected.', 'khm-membership' ), 'error' );
				$this->render_list();
				return;
			}

			$membership = $this->memberships->getById( $membership_id );

			if ( ! $membership ) {
				$this->add_notice( 'membership_not_found', __( 'Membership record not found.', 'khm-membership' ), 'error' );
				$this->render_list();
				return;
			}

			$this->render_detail( $membership );
			return;
		}

		$this->render_list();
	}

	private function render_list(): void {
		$filters = $this->get_filters();
		$levels  = $this->levels->getNameMap();

		$list_table = new MembersListTable( $this->memberships, $filters, $levels );
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Members', 'khm-membership' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=khm-members&action=add' ) ) . '" class="page-title-action">' . esc_html__( 'Add New Member', 'khm-membership' ) . '</a>';
		echo '<hr class="wp-header-end">';

		$this->display_persisted_notices();

		$this->render_assignment_form();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';

		$list_table->search_box( __( 'Search Members', 'khm-membership' ), 'khm-members' );
		$list_table->display();

		echo '</form>';
		echo '</div>';
	}

	private function render_assignment_form(): void {
		$levels = $this->levels->getNameMap();
		if ( empty( $levels ) ) {
			return;
		}

		echo '<div class="khm-assign-box">';
		echo '<h2>' . esc_html__( 'Assign Membership to User', 'khm-membership' ) . '</h2>';
		echo '<p>' . esc_html__( 'Directly assign a membership to an existing user (no checkout).', 'khm-membership' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khm-assign-form">';
		wp_nonce_field( 'khm_assign_membership', 'khm_assign_membership_nonce' );
		echo '<input type="hidden" name="action" value="khm_assign_membership">';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="khm-assign-user-id">' . esc_html__( 'User ID or email', 'khm-membership' ) . '</label></th>';
		echo '<td><div style="position:relative;">';
		echo '<input type="text" name="user_identifier" id="khm-assign-user-id" class="regular-text" autocomplete="off" required>';
		echo '<input type="hidden" name="user_id" id="khm-assign-user-hidden" value="">';
		echo '<div id="khm-user-suggestions" style="position:absolute;top:100%;left:0;z-index:1000;background:#fff;border:1px solid #ddd;max-height:200px;overflow-y:auto;display:none;width:100%;box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Enter a WordPress user ID or email address.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-assign-level">' . esc_html__( 'Membership Level', 'khm-membership' ) . '</label></th>';
		echo '<td><select name="level_id" id="khm-assign-level" required>';
		echo '<option value="">' . esc_html__( 'Select a level', 'khm-membership' ) . '</option>';
		foreach ( $levels as $id => $name ) {
			echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="khm-assign-status">' . esc_html__( 'Status', 'khm-membership' ) . '</label></th>';
		echo '<td><select name="status" id="khm-assign-status">';
		$statuses = [ 'active' => __( 'Active', 'khm-membership' ), 'pending' => __( 'Pending', 'khm-membership' ), 'cancelled' => __( 'Cancelled', 'khm-membership' ) ];
		foreach ( $statuses as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="khm-assign-start">' . esc_html__( 'Start Date', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="date" name="start_date" id="khm-assign-start"> ';
		echo '<span class="description">' . esc_html__( 'Optional; defaults to now.', 'khm-membership' ) . '</span></td></tr>';

		echo '<tr><th scope="row"><label for="khm-assign-end">' . esc_html__( 'End Date', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="date" name="end_date" id="khm-assign-end"> ';
		echo '<span class="description">' . esc_html__( 'Optional expiration.', 'khm-membership' ) . '</span></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Allocate Monthly Credits', 'khm-membership' ) . '</th>';
		echo '<td><label><input type="checkbox" name="allocate_credits" value="1"> ' . esc_html__( 'Allocate monthly credits now', 'khm-membership' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Credits will be added to the user\'s existing balance.', 'khm-membership' ) . '</p></td></tr>';
		echo '</tbody></table>';

		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Assign Membership', 'khm-membership' ) . '</button></p>';
		echo '</form>';

		// Lightweight lookup script (AJAX search by email/login)
		?>
		<script>
		(function() {
			const input = document.getElementById('khm-assign-user-id');
			const hidden = document.getElementById('khm-assign-user-hidden');
			const suggestions = document.getElementById('khm-user-suggestions');
			if (!input || !hidden || !suggestions) return;

			let debounceTimer;
			let currentUsers = [];

			function showSuggestions(users) {
				currentUsers = users;
				suggestions.innerHTML = '';
				if (!users.length) {
					suggestions.style.display = 'none';
					return;
				}
				users.forEach((user, idx) => {
					const div = document.createElement('div');
					div.textContent = user.label;
					div.dataset.userId = user.id;
					div.dataset.idx = idx;
					div.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;';
					div.addEventListener('mouseenter', () => div.style.background = '#f0f0f1');
					div.addEventListener('mouseleave', () => div.style.background = '#fff');
					div.addEventListener('click', () => selectUser(user));
					suggestions.appendChild(div);
				});
				suggestions.style.display = 'block';
			}

			function selectUser(user) {
				input.value = user.label;
				hidden.value = user.id;
				suggestions.style.display = 'none';
			}

			input.addEventListener('input', function(e) {
				const term = e.target.value.trim();
				hidden.value = '';
				if (term.length < 2) {
					suggestions.style.display = 'none';
					return;
				}
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(() => {
					const params = new URLSearchParams({ action: 'khm_user_lookup', term });
					fetch(ajaxurl + '?' + params.toString(), { credentials: 'same-origin' })
						.then(r => r.json())
						.then(users => {
							if (!Array.isArray(users)) return;
							showSuggestions(users);
						})
						.catch(() => suggestions.style.display = 'none');
				}, 200);
			});

			input.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') {
					suggestions.style.display = 'none';
				}
			});

			document.addEventListener('click', function(e) {
				if (!input.contains(e.target) && !suggestions.contains(e.target)) {
					suggestions.style.display = 'none';
				}
			});
		})();
		</script>
		<?php
		echo '</div>';
	}

	public function handle_cancel_request(): void {
		$this->ensure_capability();

		$membership_id = $this->get_membership_id_from_request( 'khm_membership_cancel_' );
		if ( ! $membership_id ) {
			$this->redirect();
		}

		if ( $this->memberships->cancelById( $membership_id, __( 'Cancelled via admin action.', 'khm-membership' ) ) ) {
			$this->add_notice( 'cancelled', __( 'Membership cancelled.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'cancel_failed', __( 'Failed to cancel membership.', 'khm-membership' ), 'error' );
		}

		$this->redirect( $this->determine_redirect_args() );
	}

	public function handle_reactivate_request(): void {
		$this->ensure_capability();

		$membership_id = $this->get_membership_id_from_request( 'khm_membership_reactivate_' );
		if ( ! $membership_id ) {
			$this->redirect();
		}

		if ( $this->memberships->reactivateById( $membership_id ) ) {
			$this->add_notice( 'reactivated', __( 'Membership reactivated.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'reactivate_failed', __( 'Failed to reactivate membership.', 'khm-membership' ), 'error' );
		}

		$this->redirect( $this->determine_redirect_args() );
	}

	public function handle_expire_request(): void {
		$this->ensure_capability();

		$membership_id = $this->get_membership_id_from_request( 'khm_membership_expire_' );
		if ( ! $membership_id ) {
			$this->redirect();
		}

		if ( $this->memberships->expireById( $membership_id ) ) {
			$this->add_notice( 'expired', __( 'Membership marked as expired.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'expire_failed', __( 'Failed to expire membership.', 'khm-membership' ), 'error' );
		}

		$this->redirect( $this->determine_redirect_args() );
	}

	public function handle_delete_request(): void {
		$this->ensure_capability();

		$membership_id = $this->get_membership_id_from_request( 'khm_membership_delete_' );
		if ( ! $membership_id ) {
			$this->redirect();
		}

		if ( $this->memberships->deleteById( $membership_id ) ) {
			$this->add_notice( 'deleted', __( 'Membership deleted.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'delete_failed', __( 'Failed to delete membership.', 'khm-membership' ), 'error' );
		}

		$this->redirect();
	}

	public function handle_update_end_request(): void {
		$this->ensure_capability();

		$membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;

		check_admin_referer( 'khm_membership_update_end_' . $membership_id );

		if ( ! $membership_id ) {
			$this->add_notice( 'invalid_membership', __( 'Invalid membership.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		$membership = $this->memberships->getById( $membership_id );

		if ( ! $membership ) {
			$this->add_notice( 'membership_missing', __( 'Membership record not found.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		$date_raw = isset( $_POST['expiration_date'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['expiration_date'] ) ) ) : '';

		if ( '' === $date_raw ) {
			$success = $this->memberships->updateEndDate( (int) $membership->user_id, (int) $membership->membership_id, null );
		} else {
			$date = DateTime::createFromFormat( 'Y-m-d', $date_raw );
			if ( false === $date ) {
				$this->add_notice( 'invalid_date', __( 'Please provide a valid expiration date (YYYY-MM-DD).', 'khm-membership' ), 'error' );
				$this->redirect( [ 'action' => 'view', 'id' => $membership_id ] );
			}

			$success = $this->memberships->updateEndDate( (int) $membership->user_id, (int) $membership->membership_id, $date );
		}

		if ( $success ) {
			$this->add_notice( 'expiration_updated', __( 'Expiration updated.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'expiration_failed', __( 'Failed to update expiration.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $membership_id ] );
	}

	public function handle_add_note(): void {
		$this->ensure_capability();

		$membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
		check_admin_referer( 'khm_membership_add_note_' . $membership_id );

		if ( ! $membership_id ) {
			$this->add_notice( 'note_invalid', __( 'Invalid membership for note.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		$content = isset( $_POST['note_content'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ) ) : '';
		if ( '' === $content ) {
			$this->add_notice( 'note_empty', __( 'Please enter a note before saving.', 'khm-membership' ), 'error' );
			$this->redirect( [ 'action' => 'view', 'id' => $membership_id ] );
		}

		$author_id = get_current_user_id();
		$note      = $this->notes->addNote( $membership_id, $author_id, $content );

		if ( $note ) {
			$this->add_notice( 'note_added', __( 'Note added.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'note_failed', __( 'Unable to add note.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $membership_id ] );
	}

	public function handle_delete_note(): void {
		$this->ensure_capability();

		$membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
		$note_id       = isset( $_POST['note_id'] ) ? sanitize_text_field( wp_unslash( $_POST['note_id'] ) ) : '';
		check_admin_referer( 'khm_membership_delete_note_' . $membership_id );

		if ( ! $membership_id || ! $note_id ) {
			$this->add_notice( 'note_delete_invalid', __( 'Unable to remove note.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		if ( $this->notes->deleteNote( $membership_id, $note_id ) ) {
			$this->add_notice( 'note_deleted', __( 'Note removed.', 'khm-membership' ) );
		} else {
			$this->add_notice( 'note_delete_failed', __( 'Failed to remove note.', 'khm-membership' ), 'error' );
		}

		$this->redirect( [ 'action' => 'view', 'id' => $membership_id ] );
	}

	public function handle_pause_request(): void {
		$this->ensure_capability();

		$membership_id = $this->get_membership_id_from_request( 'khm_membership_pause_' );
		if ( ! $membership_id ) {
			$this->redirect();
		}

		$membership = $this->memberships->getById( $membership_id );
		if ( ! $membership ) {
			$this->add_notice( 'pause_invalid', __( 'Membership record not found.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			$this->redirect();
		}

		$result = $this->subscriptions->pause( (int) $membership->user_id, (int) $membership->membership_id );

		if ( ! empty( $result['success'] ) ) {
			$this->add_notice( 'paused', __( 'Membership paused.', 'khm-membership' ), 'success' );
		} else {
			$message = $result['message'] ?? __( 'Unable to pause membership.', 'khm-membership' );
			$this->add_notice( 'pause_failed', $message, 'error' );
		}

		$this->persist_notices();
		$this->redirect( $this->determine_redirect_args() );
	}

	public function handle_resume_request(): void {
		$this->ensure_capability();

		$membership_id = $this->get_membership_id_from_request( 'khm_membership_resume_' );
		if ( ! $membership_id ) {
			$this->redirect();
		}

		$membership = $this->memberships->getById( $membership_id );
		if ( ! $membership ) {
			$this->add_notice( 'resume_invalid', __( 'Membership record not found.', 'khm-membership' ), 'error' );
			$this->persist_notices();
			$this->redirect();
		}

		$result = $this->subscriptions->resume( (int) $membership->user_id, (int) $membership->membership_id );

		if ( ! empty( $result['success'] ) ) {
			$this->add_notice( 'resumed', __( 'Membership resumed.', 'khm-membership' ), 'success' );
		} else {
			$message = $result['message'] ?? __( 'Unable to resume membership.', 'khm-membership' );
			$this->add_notice( 'resume_failed', $message, 'error' );
		}

		$this->persist_notices();
		$this->redirect( $this->determine_redirect_args() );
	}

	private function render_detail( object $membership ): void {
		$user_link = get_edit_user_link( (int) $membership->user_id );
		$back_url  = add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) );

		$orders = $this->orders->findByUser(
			(int) $membership->user_id,
			[
				'membership_id' => (int) $membership->membership_id,
				'limit'         => 10,
			]
		);

		$current_end = $membership->enddate ? gmdate( 'Y-m-d', strtotime( $membership->enddate ) ) : '';

		echo '<div class="wrap khm-member-detail">';
		echo '<h1>' . esc_html__( 'Member Details', 'khm-membership' ) . '</h1>';
		echo '<p><a href="' . esc_url( $back_url ) . '" class="button-secondary">&larr; ' . esc_html__( 'Back to Members', 'khm-membership' ) . '</a></p>';

		settings_errors( self::SETTINGS_GROUP );

		echo '<div class="khm-member-summary">';
		echo '<h2>' . esc_html( $membership->display_name ?: $membership->user_login ) . '</h2>';
		echo '<p>';
		echo '<strong>' . esc_html__( 'Email:', 'khm-membership' ) . '</strong> <a href="mailto:' . esc_attr( $membership->user_email ) . '">' . esc_html( $membership->user_email ) . '</a><br>';
		echo '<strong>' . esc_html__( 'Membership Level:', 'khm-membership' ) . '</strong> ' . esc_html( $membership->level_name ?: __( 'Unknown', 'khm-membership' ) ) . '<br>';
		echo '<strong>' . esc_html__( 'Status:', 'khm-membership' ) . '</strong> ' . esc_html( ucfirst( $membership->status ) ) . '<br>';
		echo '<strong>' . esc_html__( 'Start Date:', 'khm-membership' ) . '</strong> ' . esc_html( $this->format_date_display( $membership->startdate ) ) . '<br>';
		echo '<strong>' . esc_html__( 'End Date:', 'khm-membership' ) . '</strong> ' . esc_html( $this->format_date_display( $membership->enddate ) ?: __( 'Never', 'khm-membership' ) ) . '</p>';

		echo '<p><a class="button" href="' . esc_url( $user_link ) . '">' . esc_html__( 'View WordPress User Profile', 'khm-membership' ) . '</a></p>';
		echo '</div>';

		// Edit Membership Level section
		echo '<div class="khm-member-change-level">';
		echo '<h2>' . esc_html__( 'Change Membership Level', 'khm-membership' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'khm_assign_membership', 'khm_assign_membership_nonce' );
		echo '<input type="hidden" name="action" value="khm_assign_membership">';
		echo '<input type="hidden" name="user_id" value="' . esc_attr( (int) $membership->user_id ) . '">';
		echo '<input type="hidden" name="user_identifier" value="' . esc_attr( (int) $membership->user_id ) . '">';
		
		$levels = $this->levels->getNameMap();
		echo '<p><label for="khm-change-level">' . esc_html__( 'New Membership Level', 'khm-membership' ) . '</label><br>';
		echo '<select name="level_id" id="khm-change-level" required>';
		foreach ( $levels as $id => $name ) {
			$selected = ( (int) $id === (int) $membership->membership_id ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $id ) . '"' . $selected . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select></p>';
		
		echo '<p><label><input type="checkbox" name="allocate_credits" value="1"> ' . esc_html__( 'Allocate monthly credits now', 'khm-membership' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Changing the level will cancel the current membership and assign the new one. Credits will be added to the existing balance.', 'khm-membership' ) . '</p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Change Level', 'khm-membership' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		echo '<div class="khm-member-actions">';
		echo '<h2>' . esc_html__( 'Actions', 'khm-membership' ) . '</h2>';
		echo '<p>';

        if ( in_array( $membership->status, [ 'active', 'grace' ], true ) ) {
            echo '<a class="button button-secondary" href="' . esc_url( $this->action_link( 'khm_membership_cancel', $membership->id, true ) ) . '">' . esc_html__( 'Cancel Membership', 'khm-membership' ) . '</a> ';
            echo '<a class="button button-secondary" href="' . esc_url( $this->action_link( 'khm_membership_expire', $membership->id, true ) ) . '">' . esc_html__( 'Mark as Expired', 'khm-membership' ) . '</a> ';
            echo '<a class="button button-secondary" href="' . esc_url( $this->action_link( 'khm_membership_pause', $membership->id, true ) ) . '">' . esc_html__( 'Pause Membership', 'khm-membership' ) . '</a> ';
        } elseif ( 'paused' === $membership->status ) {
            echo '<a class="button button-secondary" href="' . esc_url( $this->action_link( 'khm_membership_resume', $membership->id, true ) ) . '">' . esc_html__( 'Resume Membership', 'khm-membership' ) . '</a> ';
            echo '<a class="button button-secondary" href="' . esc_url( $this->action_link( 'khm_membership_cancel', $membership->id, true ) ) . '">' . esc_html__( 'Cancel Membership', 'khm-membership' ) . '</a> ';
        } else {
            echo '<a class="button button-secondary" href="' . esc_url( $this->action_link( 'khm_membership_reactivate', $membership->id, true ) ) . '">' . esc_html__( 'Reactivate Membership', 'khm-membership' ) . '</a> ';
        }

		echo '<a class="button button-link-delete" href="' . esc_url( $this->action_link( 'khm_membership_delete', $membership->id ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this membership record?', 'khm-membership' ) ) . '\');">' . esc_html__( 'Delete Membership Record', 'khm-membership' ) . '</a>';
		echo '</p>';
		echo '</div>';

		echo '<div class="khm-member-expiration">';
		echo '<h2>' . esc_html__( 'Expiration', 'khm-membership' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'khm_membership_update_end_' . (int) $membership->id );
		echo '<input type="hidden" name="action" value="khm_membership_update_end">';
		echo '<input type="hidden" name="membership_id" value="' . esc_attr( (int) $membership->id ) . '">';
		echo '<p><label for="khm-expiration-date">' . esc_html__( 'Expiration Date', 'khm-membership' ) . '</label><br>';
		echo '<input type="date" id="khm-expiration-date" name="expiration_date" value="' . esc_attr( $current_end ) . '"> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Update Expiration', 'khm-membership' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Leave blank to remove any expiration date.', 'khm-membership' ) . '</p>';
		echo '</form>';
		echo '</div>';

		echo '<div class="khm-member-orders">';
		echo '<h2>' . esc_html__( 'Recent Orders', 'khm-membership' ) . '</h2>';

		if ( empty( $orders ) ) {
			echo '<p>' . esc_html__( 'No orders found for this member.', 'khm-membership' ) . '</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Order Code', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Total', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Gateway', 'khm-membership' ) . '</th>';
			echo '<th>' . esc_html__( 'Created', 'khm-membership' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $orders as $order ) {
				$order_link = add_query_arg(
					[
						'page' => 'khm-orders',
						'action' => 'view',
						'id' => $order->id,
					],
					admin_url( 'admin.php' )
				);

				echo '<tr>';
				echo '<td><a href="' . esc_url( $order_link ) . '">' . esc_html( $order->code ) . '</a></td>';
				echo '<td>' . esc_html( ucfirst( $order->status ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_price( (float) $order->total ) ) . '</td>';
				echo '<td>' . esc_html( $order->gateway ?: '—' ) . '</td>';
				echo '<td>' . esc_html( $this->format_date_display( $order->timestamp ) ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';

		$this->render_notes_section( $membership );

		echo '</div>';
	}

	public function handle_assign_request(): void {
		$this->ensure_capability();
		check_admin_referer( 'khm_assign_membership', 'khm_assign_membership_nonce' );

		$user_identifier  = isset( $_POST['user_identifier'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_identifier'] ) ) ) : '';
		$user_id_hidden   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$level_id         = isset( $_POST['level_id'] ) ? absint( $_POST['level_id'] ) : 0;
		$status           = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active';
		$start_raw        = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_raw          = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$allocate_credits = isset( $_POST['allocate_credits'] ) ? 1 : 0;

		$user = null;
		if ( $user_id_hidden ) {
			$user = get_user_by( 'id', $user_id_hidden );
		} elseif ( is_numeric( $user_identifier ) ) {
			$user = get_user_by( 'id', (int) $user_identifier );
		} elseif ( is_email( $user_identifier ) ) {
			$user = get_user_by( 'email', $user_identifier );
		}

		if ( ! $user ) {
			$this->add_notice( 'assign_user_missing', __( 'User not found. Please provide a valid user ID or email.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		$level = $this->levels->get( $level_id );
		if ( ! $level ) {
			$this->add_notice( 'assign_level_missing', __( 'Membership level not found.', 'khm-membership' ), 'error' );
			$this->redirect();
		}

		$options = [ 'status' => in_array( $status, [ 'active', 'pending', 'cancelled' ], true ) ? $status : 'active' ];

		if ( $start_raw ) {
			$start = DateTime::createFromFormat( 'Y-m-d', $start_raw );
			if ( $start ) {
				$options['start_date'] = $start;
			} else {
				$this->add_notice( 'assign_bad_start', __( 'Invalid start date. Use YYYY-MM-DD.', 'khm-membership' ), 'error' );
				$this->redirect();
			}
		}

		if ( $end_raw ) {
			$end = DateTime::createFromFormat( 'Y-m-d', $end_raw );
			if ( $end ) {
				$options['end_date'] = $end;
			} else {
				$this->add_notice( 'assign_bad_end', __( 'Invalid end date. Use YYYY-MM-DD.', 'khm-membership' ), 'error' );
				$this->redirect();
			}
		}

		try {
			// Check if user already has an active membership
			$existing_memberships = $this->memberships->findActive( (int) $user->ID );
			$changed_level = false;

			if ( ! empty( $existing_memberships ) ) {
				// User has existing membership(s) - update the first one in place
				$existing = $existing_memberships[0];
				if ( (int) $existing->membership_id !== (int) $level_id ) {
					// Different level - change it
					$this->memberships->changeLevelById( (int) $existing->id, (int) $level_id, $options );
					$changed_level = true;
				} else {
					// Same level - just update options if needed
					$this->memberships->assign( (int) $user->ID, (int) $level_id, $options );
				}
			} else {
				// No existing membership - create new
				$this->memberships->assign( (int) $user->ID, (int) $level_id, $options );
			}

			$credits_allocated = 0;
			// Allocate credits if checkbox was checked
			if ( $allocate_credits ) {
				if ( class_exists( 'KHM\\Services\\CreditService' ) ) {
					$credit_service = new \KHM\Services\CreditService(
						new \KHM\Services\MembershipRepository(),
						new \KHM\Services\LevelRepository()
					);
					$credits_allocated = $credit_service->allocateEnrollmentCredits( (int) $user->ID, (int) $level_id );
				}
			}

			// Build success message
			$message = $changed_level 
				? __( 'Membership level changed successfully.', 'khm-membership' )
				: __( 'Membership assigned successfully.', 'khm-membership' );
			if ( $credits_allocated > 0 ) {
				/* translators: %d: number of credits allocated */
				$message .= ' ' . sprintf( __( '%d credits have been added to the user\'s account.', 'khm-membership' ), $credits_allocated );
			}

			$this->add_notice( 'assign_success', $message );
		} catch ( \Throwable $e ) {
			error_log( 'KHM assign membership error: ' . $e->getMessage() );
			$this->add_notice( 'assign_failed', __( 'Failed to assign membership. Check logs for details.', 'khm-membership' ), 'error' );
		}

		$this->redirect();
	}

	public function handle_add_credits_request(): void {
		$this->ensure_capability();
		check_admin_referer( 'khm_add_credits', 'khm_add_credits_nonce' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$credits_amount = isset( $_POST['credits_amount'] ) ? intval( $_POST['credits_amount'] ) : 0;
		$credits_reason = isset( $_POST['credits_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['credits_reason'] ) ) : '';

		if ( ! $user_id ) {
			$this->add_notice( 'credits_user_missing', __( 'Invalid user.', 'khm-membership' ), 'error' );
			$this->redirect();
			return;
		}

		if ( $credits_amount === 0 ) {
			$this->add_notice( 'credits_amount_invalid', __( 'Credits amount cannot be zero.', 'khm-membership' ), 'error' );
			$this->redirect();
			return;
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			$this->add_notice( 'credits_user_not_found', __( 'User not found.', 'khm-membership' ), 'error' );
			$this->redirect();
			return;
		}

		try {
			if ( class_exists( 'KHM\\Services\\CreditService' ) ) {
				$credit_service = new \KHM\Services\CreditService(
					new \KHM\Services\MembershipRepository(),
					new \KHM\Services\LevelRepository()
				);
				
				$reason = $credits_reason ?: 'manual';
				$credit_service->addBonusCredits( $user_id, $credits_amount, $reason );
				
				if ( $credits_amount > 0 ) {
					$this->add_notice( 
						'credits_added', 
						sprintf( __( 'Successfully added %d credits to %s.', 'khm-membership' ), $credits_amount, $user->display_name ?: $user->user_login )
					);
				} else {
					$this->add_notice( 
						'credits_removed', 
						sprintf( __( 'Successfully removed %d credits from %s.', 'khm-membership' ), abs( $credits_amount ), $user->display_name ?: $user->user_login )
					);
				}
			} else {
				$this->add_notice( 'credits_service_missing', __( 'Credit service unavailable.', 'khm-membership' ), 'error' );
			}
		} catch ( \Throwable $e ) {
			error_log( 'KHM add credits error: ' . $e->getMessage() );
			$this->add_notice( 'credits_failed', __( 'Failed to add credits. Check logs for details.', 'khm-membership' ), 'error' );
		}

		$this->redirect();
	}

	private function get_filters(): array {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$level  = isset( $_GET['level'] ) ? (int) $_GET['level'] : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		return [
			'search' => $search,
			'level'  => $level > 0 ? $level : null,
			'status' => $status,
		];
	}

	private function ensure_capability(): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage members.', 'khm-membership' ) );
		}
	}

	public function ajax_user_lookup(): void {
		$this->ensure_capability();
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json( [] );
		}

		$args = [
			'number' => 10,
			'search' => '*' . esc_attr( $term ) . '*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
		];

		$users = get_users( $args );
		$results = array_map(
			static function ( $user ) {
				return [
					'id'    => $user->ID,
					'label' => sprintf( '%s (%s)', $user->user_email, $user->display_name ?: $user->user_login ),
				];
			},
			$users
		);

		wp_send_json( $results );
	}

	private function get_membership_id_from_request( string $nonce_action_prefix ): int {
		$membership_id = isset( $_GET['membership_id'] ) ? absint( $_GET['membership_id'] ) : 0;
		$nonce         = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( $membership_id && wp_verify_nonce( $nonce, $nonce_action_prefix . $membership_id ) ) {
			return $membership_id;
		}

		return 0;
	}

	private function add_notice( string $code, string $message, string $type = 'success' ): void {
		add_settings_error( self::SETTINGS_GROUP, $code, $message, $type );
		set_transient( 'khm_members_notices', get_settings_errors( self::SETTINGS_GROUP ), 30 );
	}

	/**
	 * Load and display notices from transient (for post-redirect display).
	 */
	private function display_persisted_notices(): void {
		$notices = get_transient( 'khm_members_notices' );
		if ( $notices && is_array( $notices ) ) {
			delete_transient( 'khm_members_notices' );
			foreach ( $notices as $notice ) {
				add_settings_error(
					self::SETTINGS_GROUP,
					$notice['code'] ?? 'notice',
					$notice['message'] ?? '',
					$notice['type'] ?? 'success'
				);
			}
		}
		settings_errors( self::SETTINGS_GROUP );
	}

	public function render_user_membership_fields( $user ): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			return;
		}

		$levels = $this->levels->getNameMap();
		if ( empty( $levels ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'KHM Membership', 'khm-membership' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="khm-user-level">' . esc_html__( 'Assign Membership Level', 'khm-membership' ) . '</label></th>';
		echo '<td><select name="khm_assign_level_id" id="khm-user-level">';
		echo '<option value="">' . esc_html__( '— No change —', 'khm-membership' ) . '</option>';
		foreach ( $levels as $id => $name ) {
			echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Assigns/updates membership for this user. Does not charge.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th><label for="khm-user-status">' . esc_html__( 'Status', 'khm-membership' ) . '</label></th>';
		echo '<td><select name="khm_assign_status" id="khm-user-status">';
		$statuses = [ 'active' => __( 'Active', 'khm-membership' ), 'pending' => __( 'Pending', 'khm-membership' ), 'cancelled' => __( 'Cancelled', 'khm-membership' ) ];
		foreach ( $statuses as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th><label for="khm-user-start">' . esc_html__( 'Start Date', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="date" name="khm_assign_start_date" id="khm-user-start"></td></tr>';

		echo '<tr><th><label for="khm-user-end">' . esc_html__( 'End Date', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="date" name="khm_assign_end_date" id="khm-user-end"></td></tr>';
		echo '</tbody></table>';
	}

	public function render_user_membership_fields_new( $operation ): void {
		$this->render_user_membership_fields( get_userdata( get_current_user_id() ) );
		// Extra profile fields on add-new user form.
		echo '<h2>' . esc_html__( 'KHM Profile Details', 'khm-membership' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="khm-linkedin">' . esc_html__( 'LinkedIn Profile', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="url" name="khm_linkedin" id="khm-linkedin" class="regular-text"></td></tr>';

		echo '<tr><th><label for="khm-job-title">' . esc_html__( 'Job Title', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="text" name="khm_job_title" id="khm-job-title" class="regular-text"></td></tr>';

		echo '<tr><th><label for="khm-company">' . esc_html__( 'Company', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="text" name="khm_company" id="khm-company" class="regular-text"></td></tr>';
		echo '</tbody></table>';
	}

	public function handle_user_membership_save( int $user_id ): void {
		if ( ! current_user_can( 'manage_khm' ) ) {
			return;
		}

		$level_id = isset( $_POST['khm_assign_level_id'] ) ? absint( $_POST['khm_assign_level_id'] ) : 0;
		if ( ! $level_id ) {
			return;
		}

		$status    = isset( $_POST['khm_assign_status'] ) ? sanitize_key( wp_unslash( $_POST['khm_assign_status'] ) ) : 'active';
		$start_raw = isset( $_POST['khm_assign_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['khm_assign_start_date'] ) ) : '';
		$end_raw   = isset( $_POST['khm_assign_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['khm_assign_end_date'] ) ) : '';

		$options = [ 'status' => in_array( $status, [ 'active', 'pending', 'cancelled' ], true ) ? $status : 'active' ];

		if ( $start_raw ) {
			$start = DateTime::createFromFormat( 'Y-m-d', $start_raw );
			if ( $start ) {
				$options['start_date'] = $start;
			}
		}

		if ( $end_raw ) {
			$end = DateTime::createFromFormat( 'Y-m-d', $end_raw );
			if ( $end ) {
				$options['end_date'] = $end;
			}
		}

		try {
			$this->memberships->assign( (int) $user_id, (int) $level_id, $options );
			// Save profile extras if provided.
			if ( isset( $_POST['khm_linkedin'] ) ) {
				update_user_meta( $user_id, 'khm_linkedin', esc_url_raw( wp_unslash( $_POST['khm_linkedin'] ) ) );
			}
			if ( isset( $_POST['khm_job_title'] ) ) {
				update_user_meta( $user_id, 'khm_job_title', sanitize_text_field( wp_unslash( $_POST['khm_job_title'] ) ) );
			}
			if ( isset( $_POST['khm_company'] ) ) {
				update_user_meta( $user_id, 'khm_company', sanitize_text_field( wp_unslash( $_POST['khm_company'] ) ) );
			}
		} catch ( \Throwable $e ) {
			error_log( 'KHM user profile assign membership error: ' . $e->getMessage() );
		}
	}

	/**
	 * Hide Website field and relabel email on add/edit user screens.
	 */
	public function customize_user_form_fields(): void {
		?>
		<style>
			.user-url-wrap,
			#url-wrap,
			tr.user-url-wrap,
			tr.user-url-wrap + tr.form-field { display: none !important; }
		</style>
		<script>
		(function() {
			// Hide website field row and label.
			const urlRow = document.querySelector('tr.user-url-wrap');
			if (urlRow && urlRow.parentNode) {
				urlRow.parentNode.removeChild(urlRow);
			}
			// Relabel email field.
			const emailLabel = document.querySelector('label[for="email"]');
			if (emailLabel) emailLabel.textContent = '<?php echo esc_js( __( 'Work Email', 'khm-membership' ) ); ?>';
			const emailInput = document.getElementById('email');
			if (emailInput) emailInput.placeholder = '<?php echo esc_js( __( 'Work Email', 'khm-membership' ) ); ?>';
		})();
		</script>
		<?php
	}

	private function redirect( array $args = [] ): void {
		$url = add_query_arg(
			array_merge(
				[ 'page' => self::PAGE_SLUG ],
				$args
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	private function action_link( string $action, int $membership_id, bool $return_to_view = false ): string {
		$args = [
			'action'        => $action,
			'membership_id' => $membership_id,
		];

		if ( $return_to_view ) {
			$args['redirect'] = 'view';
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			$action . '_' . $membership_id
		);
	}

	private function determine_redirect_args(): array {
		$redirect = isset( $_GET['redirect'] ) ? sanitize_key( wp_unslash( $_GET['redirect'] ) ) : '';

		if ( 'view' === $redirect ) {
			$membership_id = isset( $_GET['membership_id'] ) ? absint( $_GET['membership_id'] ) : 0;
			if ( $membership_id ) {
				return [
					'action' => 'view',
					'id'     => $membership_id,
				];
			}
		}

		return [];
	}

	private function format_date_display( $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $value );
		if ( ! $timestamp ) {
			return '';
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	private function format_price( float $amount ): string {
		if ( function_exists( 'khm_format_price' ) ) {
			return khm_format_price( $amount );
		}

		return '$' . number_format_i18n( $amount, 2 );
	}

	private function render_notes_section( object $membership ): void {
		$membership_id = (int) $membership->id;
		$notes         = $this->notes->getNotes( $membership_id );

		echo '<div class="khm-member-notes">';
		echo '<h2>' . esc_html__( 'Admin Notes', 'khm-membership' ) . '</h2>';

		if ( empty( $notes ) ) {
			echo '<p class="description">' . esc_html__( 'No notes yet. Use the form below to add the first note.', 'khm-membership' ) . '</p>';
		} else {
			echo '<ul class="khm-notes-list">';
			foreach ( $notes as $note ) {
				$author_id   = (int) ( $note['author_id'] ?? 0 );
				$author      = $author_id ? get_userdata( $author_id ) : null;
				$author_name = $author ? $author->display_name : __( 'System', 'khm-membership' );
				$created     = $note['created_at'] ?? '';
				$formatted   = $created ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created ) ) : '';

				echo '<li class="khm-note-item">';
				echo '<div class="khm-note-meta"><strong>' . esc_html( $author_name ) . '</strong>';
				if ( $formatted ) {
					echo '<span class="khm-note-date"> &middot; ' . esc_html( $formatted ) . '</span>';
				}
				echo '</div>';
				echo '<div class="khm-note-content">' . wpautop( esc_html( $note['content'] ?? '' ) ) . '</div>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khm-note-actions">';
				wp_nonce_field( 'khm_membership_delete_note_' . $membership_id );
				echo '<input type="hidden" name="action" value="khm_membership_delete_note">';
				echo '<input type="hidden" name="membership_id" value="' . esc_attr( $membership_id ) . '">';
				echo '<input type="hidden" name="note_id" value="' . esc_attr( $note['id'] ?? '' ) . '">';
				echo '<button type="submit" class="button-link delete">' . esc_html__( 'Delete', 'khm-membership' ) . '</button>';
				echo '</form>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khm-add-note-form">';
		wp_nonce_field( 'khm_membership_add_note_' . $membership_id );
		echo '<input type="hidden" name="action" value="khm_membership_add_note">';
		echo '<input type="hidden" name="membership_id" value="' . esc_attr( $membership_id ) . '">';
		echo '<textarea name="note_content" class="large-text" rows="4" placeholder="' . esc_attr__( 'Add a note for other administrators...', 'khm-membership' ) . '"></textarea>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Add Note', 'khm-membership' ) . '</button></p>';
		echo '</form>';

		echo '</div>';
	}

	private function render_add_member(): void {
		if ( isset( $GLOBALS['khm_add_member_page'] ) && $GLOBALS['khm_add_member_page'] instanceof \KHM\Admin\AddMemberPage ) {
			$GLOBALS['khm_add_member_page']->render_page();
			return;
		}

		if ( class_exists( \KHM\Admin\AddMemberPage::class ) ) {
			$add_member_page = new \KHM\Admin\AddMemberPage();
			$add_member_page->register();
			$GLOBALS['khm_add_member_page'] = $add_member_page;
			$add_member_page->render_page();
			return;
		}

		esc_html_e( 'Add Member admin is unavailable.', 'khm-membership' );
	}

	private function render_add_credits_form(): void {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$membership_id = isset( $_GET['membership_id'] ) ? absint( $_GET['membership_id'] ) : 0;

		if ( ! $user_id ) {
			$this->add_notice( 'invalid_user', __( 'Invalid user selected.', 'khm-membership' ), 'error' );
			$this->render_list();
			return;
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			$this->add_notice( 'user_not_found', __( 'User not found.', 'khm-membership' ), 'error' );
			$this->render_list();
			return;
		}

		// Get current credits
		$current_credits = 0;
		if ( class_exists( 'KHM\\Services\\CreditService' ) ) {
			$credit_service = new \KHM\Services\CreditService(
				new \KHM\Services\MembershipRepository(),
				new \KHM\Services\LevelRepository()
			);
			$current_credits = $credit_service->getUserCredits( $user_id );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Amend Credit Balance', 'khm-membership' ) . '</h1>';
		
		settings_errors( self::SETTINGS_GROUP );

		echo '<p>' . sprintf( 
			esc_html__( 'Amending credits for: %s (%s)', 'khm-membership' ), 
			'<strong>' . esc_html( $user->display_name ?: $user->user_login ) . '</strong>',
			esc_html( $user->user_email )
		) . '</p>';
		echo '<p>' . sprintf( esc_html__( 'Current balance: %d credits', 'khm-membership' ), $current_credits ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'khm_add_credits', 'khm_add_credits_nonce' );
		echo '<input type="hidden" name="action" value="khm_add_credits">';
		echo '<input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '">';
		echo '<input type="hidden" name="membership_id" value="' . esc_attr( $membership_id ) . '">';

		echo '<table class="form-table">';
		
		echo '<tr><th scope="row"><label for="khm-credits-amount">' . esc_html__( 'Credit Adjustment', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="number" id="khm-credits-amount" name="credits_amount" value="0" class="small-text" required>';
		echo '<p class="description">' . esc_html__( 'Enter a positive number to add credits, or a negative number to remove credits.', 'khm-membership' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="khm-credits-reason">' . esc_html__( 'Reason', 'khm-membership' ) . '</label></th>';
		echo '<td><input type="text" id="khm-credits-reason" name="credits_reason" class="regular-text" placeholder="' . esc_attr__( 'e.g., Promotional bonus, Support gesture', 'khm-membership' ) . '">';
		echo '<p class="description">' . esc_html__( 'Optional note for audit trail.', 'khm-membership' ) . '</p></td></tr>';

		echo '</table>';

		echo '<p>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Update Balance', 'khm-membership' ) . '</button> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '" class="button">' . esc_html__( 'Cancel', 'khm-membership' ) . '</a>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}
}

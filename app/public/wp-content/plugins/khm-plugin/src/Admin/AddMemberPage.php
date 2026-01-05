<?php
namespace KHM\Admin;

use KHM\Services\LevelRepository;
use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\MembershipNoteService;
use WP_Error;

class AddMemberPage {
    public const PAGE_SLUG = 'khm-add-member';

    private LevelRepository $levels;
    private MembershipRepository $memberships;
    private CreditService $credits;
    private MembershipNoteService $notes;

    public function __construct(
        ?LevelRepository $levels = null,
        ?MembershipRepository $memberships = null,
        ?CreditService $credits = null,
        ?MembershipNoteService $notes = null
    ) {
        $this->levels = $levels ?: new LevelRepository();
        $this->memberships = $memberships ?: new MembershipRepository();
        $this->credits = $credits ?: new CreditService($this->memberships, $this->levels);
        $this->notes = $notes ?: new MembershipNoteService();
    }

    public function register(): void {
        add_action('admin_post_khm_create_member', [$this, 'handle_create_request']);
    }

    public function render_page(): void {
        error_log('AddMemberPage render_page called');
        if (!current_user_can('manage_khm')) {
            wp_die(esc_html__('You do not have permission to add members.', 'khm-membership'));
        }

        $form_state = $this->consume_form_state();

        $old_input = isset($form_state['data']) && is_array($form_state['data']) ? $form_state['data'] : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Add Member', 'khm-membership') . '</h1>';

        settings_errors('khm_add_member');

        if (isset($_GET['created']) && $_GET['created'] == 1) {
            $user_id = absint($_GET['user_id'] ?? 0);
            $membership_id = absint($_GET['membership_id'] ?? 0);
            $balance = $this->credits->getUserCredits($user_id);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Member created successfully!', 'khm-membership') . '</p>';
            echo '<p><strong>' . esc_html__('User ID:', 'khm-membership') . '</strong> ' . esc_html($user_id) . '</p>';
            echo '<p><strong>' . esc_html__('Membership ID:', 'khm-membership') . '</strong> ' . esc_html($membership_id) . '</p>';
            echo '<p><strong>' . esc_html__('Current Balance:', 'khm-membership') . '</strong> ' . esc_html($balance) . '</p>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=khm-members&action=view&id=' . $membership_id)) . '">' . esc_html__('View Member Details', 'khm-membership') . '</a></p>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=khm-members&action=edit&id=' . $membership_id)) . '">' . esc_html__('Edit Member', 'khm-membership') . '</a></p>';
            echo '</div>';
        }

        $this->render_form($old_input);

        echo '</div>';
    }

    private function render_form(array $old_input = []): void {
        $levels = $this->levels->getNameMap();

        $defaults = [
            'username' => '',
            'email' => '',
            'first_name' => '',
            'last_name' => '',
            'password' => '',
            'role' => 'subscriber',
            'level_id' => '',
            'status' => 'active',
            'start_date' => date('Y-m-d'),
            'end_date' => '',
            'notes' => '',
            'initial_credits' => 0,
            'allocate_monthly' => 0,
        ];

        $data = wp_parse_args($old_input, $defaults);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="khm-add-member-form">';
        wp_nonce_field('khm_create_member', 'khm_create_member_nonce');
        echo '<input type="hidden" name="action" value="khm_create_member">';

        echo '<h2>' . esc_html__('User Account', 'khm-membership') . '</h2>';
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="khm-username">' . esc_html__('Username', 'khm-membership') . '</label></th>';
        echo '<td><input type="text" id="khm-username" name="username" value="' . esc_attr($data['username']) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="khm-email">' . esc_html__('Email', 'khm-membership') . '</label></th>';
        echo '<td><input type="email" id="khm-email" name="email" value="' . esc_attr($data['email']) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="khm-first-name">' . esc_html__('First Name', 'khm-membership') . '</label></th>';
        echo '<td><input type="text" id="khm-first-name" name="first_name" value="' . esc_attr($data['first_name']) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="khm-last-name">' . esc_html__('Last Name', 'khm-membership') . '</label></th>';
        echo '<td><input type="text" id="khm-last-name" name="last_name" value="' . esc_attr($data['last_name']) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="khm-password">' . esc_html__('Password', 'khm-membership') . '</label></th>';
        echo '<td><input type="password" id="khm-password" name="password" value="' . esc_attr($data['password']) . '">';
        echo '<p class="description">' . esc_html__('Leave blank to auto-generate and send setup email.', 'khm-membership') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="khm-role">' . esc_html__('Role', 'khm-membership') . '</label></th>';
        echo '<td><select id="khm-role" name="role">';
        wp_dropdown_roles($data['role']);
        echo '</select></td></tr>';

        echo '</table>';

        echo '<h2>' . esc_html__('Membership', 'khm-membership') . '</h2>';
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="khm-level-id">' . esc_html__('Membership Level', 'khm-membership') . '</label></th>';
        echo '<td><select id="khm-level-id" name="level_id" required>';
        echo '<option value="">' . esc_html__('Select Level', 'khm-membership') . '</option>';
        foreach ($levels as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected($data['level_id'], $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="khm-status">' . esc_html__('Status', 'khm-membership') . '</label></th>';
        echo '<td><select id="khm-status" name="status">';
        echo '<option value="active"' . selected($data['status'], 'active', false) . '>' . esc_html__('Active', 'khm-membership') . '</option>';
        echo '<option value="pending"' . selected($data['status'], 'pending', false) . '>' . esc_html__('Pending', 'khm-membership') . '</option>';
        echo '<option value="cancelled"' . selected($data['status'], 'cancelled', false) . '>' . esc_html__('Cancelled', 'khm-membership') . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="khm-start-date">' . esc_html__('Start Date', 'khm-membership') . '</label></th>';
        echo '<td><input type="date" id="khm-start-date" name="start_date" value="' . esc_attr($data['start_date']) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="khm-end-date">' . esc_html__('End Date', 'khm-membership') . '</label></th>';
        echo '<td><input type="date" id="khm-end-date" name="end_date" value="' . esc_attr($data['end_date']) . '">';
        echo '<p class="description">' . esc_html__('Optional. Leave blank for no end date.', 'khm-membership') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="khm-notes">' . esc_html__('Notes', 'khm-membership') . '</label></th>';
        echo '<td><textarea id="khm-notes" name="notes" rows="3">' . esc_textarea($data['notes']) . '</textarea></td></tr>';

        echo '</table>';

        echo '<h2>' . esc_html__('Credits', 'khm-membership') . '</h2>';
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="khm-initial-credits">' . esc_html__('Initial Credits', 'khm-membership') . '</label></th>';
        echo '<td><input type="number" min="0" id="khm-initial-credits" name="initial_credits" value="' . esc_attr($data['initial_credits']) . '">';
        echo '<p class="description">' . esc_html__('Optional manual allocation.', 'khm-membership') . '</p></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Allocate Monthly Credits', 'khm-membership') . '</th>';
        echo '<td><label><input type="checkbox" name="allocate_monthly" value="1"' . checked((int)$data['allocate_monthly'], 1, false) . '> ' . esc_html__('Allocate monthly credits now', 'khm-membership') . '</label></td></tr>';

        echo '</table>';

        echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__('Create Member', 'khm-membership') . '"></p>';

        echo '</form>';
    }

    public function handle_create_request(): void {
        if (!current_user_can('manage_khm')) {
            wp_die(esc_html__('You do not have permission to add members.', 'khm-membership'));
        }

        check_admin_referer('khm_create_member', 'khm_create_member_nonce');

        // Sanitize inputs
        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $password = wp_unslash($_POST['password'] ?? '');
        $role = sanitize_text_field(wp_unslash($_POST['role'] ?? 'subscriber'));
        $level_id = absint($_POST['level_id'] ?? 0);
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? 'active'));
        $start_date = sanitize_text_field(wp_unslash($_POST['start_date'] ?? ''));
        $end_date = sanitize_text_field(wp_unslash($_POST['end_date'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $initial_credits = absint($_POST['initial_credits'] ?? 0);
        $allocate_monthly = isset($_POST['allocate_monthly']) ? 1 : 0;

        $form_data = [
            'username' => $username,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'password' => $password,
            'role' => $role,
            'level_id' => $level_id,
            'status' => $status,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'notes' => $notes,
            'initial_credits' => $initial_credits,
            'allocate_monthly' => $allocate_monthly,
        ];

        // Validation
        $errors = [];
        if (empty($username)) {
            $errors[] = __('Username is required.', 'khm-membership');
        }
        if (empty($email) || !is_email($email)) {
            $errors[] = __('Valid email is required.', 'khm-membership');
        }
        if (empty($level_id)) {
            $errors[] = __('Membership level is required.', 'khm-membership');
        }
        if (empty($start_date)) {
            $errors[] = __('Start date is required.', 'khm-membership');
        }

        if (!empty($errors)) {
            $this->store_form_state($form_data);
            foreach ($errors as $error) {
                add_settings_error('khm_add_member', 'validation_error', $error, 'error');
            }
            $this->persist_notices();
            $this->redirect_back();
            return;
        }

        // Check if user exists
        if (email_exists($email) || username_exists($username)) {
            $this->store_form_state($form_data);
            add_settings_error('khm_add_member', 'user_exists', __('A user with this email or username already exists.', 'khm-membership'), 'error');
            $this->persist_notices();
            $this->redirect_back();
            return;
        }

        // Start transaction
        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            // Create user
            $user_id = wp_create_user($username, $password ?: wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                throw new \Exception($user_id->get_error_message());
            }

            // Update user meta
            if (!empty($first_name)) {
                update_user_meta($user_id, 'first_name', $first_name);
            }
            if (!empty($last_name)) {
                update_user_meta($user_id, 'last_name', $last_name);
            }

            // Set role
            $user = new \WP_User($user_id);
            $user->set_role($role);

            // Create membership
            $membership_data = [
                'status' => $status,
                'start_date' => $start_date,
                'end_date' => $end_date ?: null,
            ];
            $membership = $this->memberships->assign($user_id, $level_id, $membership_data);
            if (!$membership) {
                throw new \Exception(__('Failed to create membership.', 'khm-membership'));
            }

            // Add notes if any
            if (!empty($notes)) {
                $this->notes->addNote($membership->id, get_current_user_id(), $notes);
            }

            // Handle credits
            if ($initial_credits > 0) {
                $this->credits->addBonusCredits($user_id, $initial_credits, 'manual');
            }
            if ($allocate_monthly) {
                $this->credits->allocateMonthlyCredits($user_id);
            }

            // Send password setup email if no password
            if (empty($password)) {
                wp_new_user_notification($user_id, null, 'user');
            }

            // Audit log
            $this->notes->addNote($membership->id, get_current_user_id(), sprintf(__('Member created by admin %s. Initial credits: %d', 'khm-membership'), wp_get_current_user()->user_login, $initial_credits));

            // Fire hooks
            do_action('khm_member_created', $user_id, $membership->id, $membership_data);

            $wpdb->query('COMMIT');

            // Success redirect
            wp_redirect(add_query_arg([
                'page' => 'khm-members',
                'action' => 'add',
                'created' => 1,
                'user_id' => $user_id,
                'membership_id' => $membership->id,
            ], admin_url('admin.php')));
            exit;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->store_form_state($form_data);
            add_settings_error('khm_add_member', 'creation_error', $e->getMessage(), 'error');
            $this->persist_notices();
            $this->redirect_back();
        }
    }

    private function store_form_state(array $data): void {
        set_transient('khm_add_member_form_' . get_current_user_id(), [
            'data' => $data,
            'timestamp' => time(),
        ], 300); // 5 minutes
    }

    private function consume_form_state(): array {
        $key = 'khm_add_member_form_' . get_current_user_id();
        $state = get_transient($key);
        if ($state) {
            delete_transient($key);
        }
        return $state ?: [];
    }

    private function persist_notices(): void {
        set_transient('settings_errors', get_settings_errors(), 30);
    }

    private function redirect_back(): void {
        wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }
}
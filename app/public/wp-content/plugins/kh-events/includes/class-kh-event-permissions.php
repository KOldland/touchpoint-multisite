<?php
/**
 * KH Events Advanced Permissions
 * Role-based access control and user group restrictions
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Permissions {

    private static $instance = null;

    // Custom capabilities
    const CAP_MANAGE_EVENTS = 'kh_manage_events';
    const CAP_EDIT_OWN_EVENTS = 'kh_edit_own_events';
    const CAP_EDIT_OTHERS_EVENTS = 'kh_edit_others_events';
    const CAP_DELETE_EVENTS = 'kh_delete_events';
    const CAP_PUBLISH_EVENTS = 'kh_publish_events';
    const CAP_MANAGE_BOOKINGS = 'kh_manage_bookings';
    const CAP_VIEW_BOOKINGS = 'kh_view_bookings';
    const CAP_MANAGE_USERS = 'kh_manage_users';
    const CAP_VIEW_REPORTS = 'kh_view_reports';
    const CAP_MANAGE_SETTINGS = 'kh_manage_settings';

    // User groups
    const GROUP_ADMINISTRATOR = 'kh_administrator';
    const GROUP_EVENT_MANAGER = 'kh_event_manager';
    const GROUP_EVENT_ORGANIZER = 'kh_event_organizer';
    const GROUP_BOOKING_MANAGER = 'kh_booking_manager';
    const GROUP_VIEWER = 'kh_viewer';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->register_capabilities();
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_user_groups'));
        add_action('admin_init', array($this, 'add_capabilities_to_roles'));
        add_action('user_register', array($this, 'assign_default_group'), 10, 1);
        add_action('profile_update', array($this, 'update_user_group'), 10, 2);

        // Permission checks
        add_filter('kh_events_can_create_event', array($this, 'can_create_event'), 10, 2);
        add_filter('kh_events_can_edit_event', array($this, 'can_edit_event'), 10, 3);
        add_filter('kh_events_can_delete_event', array($this, 'can_delete_event'), 10, 2);
        add_filter('kh_events_can_view_bookings', array($this, 'can_view_bookings'), 10, 2);
        add_filter('kh_events_can_manage_bookings', array($this, 'can_manage_bookings'), 10, 2);
        add_filter('kh_events_can_view_reports', array($this, 'can_view_reports'), 10, 1);
        add_filter('kh_events_can_manage_settings', array($this, 'can_manage_settings'), 10, 1);

        // Admin restrictions
        add_action('admin_menu', array($this, 'restrict_admin_menu'), 999);
        add_filter('map_meta_cap', array($this, 'map_meta_capabilities'), 10, 4);
    }

    /**
     * Register custom capabilities
     */
    private function register_capabilities() {
        $capabilities = array(
            self::CAP_MANAGE_EVENTS,
            self::CAP_EDIT_OWN_EVENTS,
            self::CAP_EDIT_OTHERS_EVENTS,
            self::CAP_DELETE_EVENTS,
            self::CAP_PUBLISH_EVENTS,
            self::CAP_MANAGE_BOOKINGS,
            self::CAP_VIEW_BOOKINGS,
            self::CAP_MANAGE_USERS,
            self::CAP_VIEW_REPORTS,
            self::CAP_MANAGE_SETTINGS,
        );

        foreach ($capabilities as $cap) {
            if (!get_role('administrator')) {
                continue;
            }
            get_role('administrator')->add_cap($cap);
        }
    }

    /**
     * Register user groups taxonomy
     */
    public function register_user_groups() {
        register_taxonomy(
            'kh_user_group',
            'user',
            array(
                'public' => false,
                'labels' => array(
                    'name' => __('KH User Groups', 'kh-events'),
                    'singular_name' => __('User Group', 'kh-events'),
                ),
                'capabilities' => array(
                    'manage_terms' => 'manage_options',
                    'edit_terms' => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'manage_options',
                ),
                'show_in_rest' => false,
            )
        );

        // Create default groups
        $this->create_default_groups();
    }

    /**
     * Create default user groups
     */
    private function create_default_groups() {
        $groups = array(
            self::GROUP_ADMINISTRATOR => __('Administrator', 'kh-events'),
            self::GROUP_EVENT_MANAGER => __('Event Manager', 'kh-events'),
            self::GROUP_EVENT_ORGANIZER => __('Event Organizer', 'kh-events'),
            self::GROUP_BOOKING_MANAGER => __('Booking Manager', 'kh-events'),
            self::GROUP_VIEWER => __('Viewer', 'kh-events'),
        );

        foreach ($groups as $slug => $name) {
            if (!term_exists($slug, 'kh_user_group')) {
                wp_insert_term($name, 'kh_user_group', array('slug' => $slug));
            }
        }
    }

    /**
     * Add capabilities to WordPress roles
     */
    public function add_capabilities_to_roles() {
        // Administrator gets all capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap(self::CAP_MANAGE_EVENTS);
            $admin_role->add_cap(self::CAP_EDIT_OWN_EVENTS);
            $admin_role->add_cap(self::CAP_EDIT_OTHERS_EVENTS);
            $admin_role->add_cap(self::CAP_DELETE_EVENTS);
            $admin_role->add_cap(self::CAP_PUBLISH_EVENTS);
            $admin_role->add_cap(self::CAP_MANAGE_BOOKINGS);
            $admin_role->add_cap(self::CAP_VIEW_BOOKINGS);
            $admin_role->add_cap(self::CAP_MANAGE_USERS);
            $admin_role->add_cap(self::CAP_VIEW_REPORTS);
            $admin_role->add_cap(self::CAP_MANAGE_SETTINGS);
        }

        // Editor gets event management capabilities
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap(self::CAP_MANAGE_EVENTS);
            $editor_role->add_cap(self::CAP_EDIT_OWN_EVENTS);
            $editor_role->add_cap(self::CAP_EDIT_OTHERS_EVENTS);
            $editor_role->add_cap(self::CAP_DELETE_EVENTS);
            $editor_role->add_cap(self::CAP_PUBLISH_EVENTS);
            $editor_role->add_cap(self::CAP_VIEW_BOOKINGS);
        }

        // Author gets basic event creation capabilities
        $author_role = get_role('author');
        if ($author_role) {
            $author_role->add_cap(self::CAP_EDIT_OWN_EVENTS);
            $author_role->add_cap(self::CAP_PUBLISH_EVENTS);
        }

        // Contributor gets limited event creation
        $contributor_role = get_role('contributor');
        if ($contributor_role) {
            $contributor_role->add_cap(self::CAP_EDIT_OWN_EVENTS);
        }
    }

    /**
     * Assign default group to new users
     */
    public function assign_default_group($user_id) {
        $default_group = get_option('kh_events_default_user_group', self::GROUP_VIEWER);
        wp_set_object_terms($user_id, array($default_group), 'kh_user_group', false);
    }

    /**
     * Update user group when profile is updated
     */
    public function update_user_group($user_id, $old_user_data) {
        if (isset($_POST['kh_user_group'])) {
            $group = sanitize_text_field($_POST['kh_user_group']);
            wp_set_object_terms($user_id, array($group), 'kh_user_group', false);
        }
    }

    /**
     * Check if user can create events
     */
    public function can_create_event($can_create, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Check user group permissions
        $user_groups = $this->get_user_groups($user_id);
        $group_permissions = $this->get_group_permissions();

        foreach ($user_groups as $group) {
            if (isset($group_permissions[$group]['can_create_events']) && $group_permissions[$group]['can_create_events']) {
                return true;
            }
        }

        // Check WordPress capabilities
        return current_user_can(self::CAP_EDIT_OWN_EVENTS) || current_user_can(self::CAP_MANAGE_EVENTS);
    }

    /**
     * Check if user can edit specific event
     */
    public function can_edit_event($can_edit, $event_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Administrators and event managers can edit all events
        if (current_user_can(self::CAP_MANAGE_EVENTS) || current_user_can(self::CAP_EDIT_OTHERS_EVENTS)) {
            return true;
        }

        // Check if user is the author
        $event_author = get_post_field('post_author', $event_id);
        if ($event_author == $user_id && current_user_can(self::CAP_EDIT_OWN_EVENTS)) {
            return true;
        }

        // Check user group permissions
        $user_groups = $this->get_user_groups($user_id);
        $group_permissions = $this->get_group_permissions();

        foreach ($user_groups as $group) {
            if (isset($group_permissions[$group]['can_edit_events']) && $group_permissions[$group]['can_edit_events']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can delete events
     */
    public function can_delete_event($can_delete, $event_id) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Administrators and event managers can delete events
        if (current_user_can(self::CAP_MANAGE_EVENTS) || current_user_can(self::CAP_DELETE_EVENTS)) {
            return true;
        }

        // Check if user is the author
        $event_author = get_post_field('post_author', $event_id);
        if ($event_author == $user_id && current_user_can(self::CAP_EDIT_OWN_EVENTS)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view bookings
     */
    public function can_view_bookings($can_view, $event_id = null) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Administrators and booking managers can view all bookings
        if (current_user_can(self::CAP_MANAGE_BOOKINGS) || current_user_can(self::CAP_VIEW_BOOKINGS)) {
            return true;
        }

        // Check user group permissions
        $user_groups = $this->get_user_groups($user_id);
        $group_permissions = $this->get_group_permissions();

        foreach ($user_groups as $group) {
            if (isset($group_permissions[$group]['can_view_bookings']) && $group_permissions[$group]['can_view_bookings']) {
                return true;
            }
        }

        // Event authors can view bookings for their events
        if ($event_id) {
            $event_author = get_post_field('post_author', $event_id);
            if ($event_author == $user_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can manage bookings
     */
    public function can_manage_bookings($can_manage, $event_id = null) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Administrators and booking managers can manage all bookings
        if (current_user_can(self::CAP_MANAGE_BOOKINGS)) {
            return true;
        }

        // Check user group permissions
        $user_groups = $this->get_user_groups($user_id);
        $group_permissions = $this->get_group_permissions();

        foreach ($user_groups as $group) {
            if (isset($group_permissions[$group]['can_manage_bookings']) && $group_permissions[$group]['can_manage_bookings']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can view reports
     */
    public function can_view_reports($can_view) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Administrators can view reports
        if (current_user_can(self::CAP_VIEW_REPORTS)) {
            return true;
        }

        // Check user group permissions
        $user_groups = $this->get_user_groups($user_id);
        $group_permissions = $this->get_group_permissions();

        foreach ($user_groups as $group) {
            if (isset($group_permissions[$group]['can_view_reports']) && $group_permissions[$group]['can_view_reports']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can manage settings
     */
    public function can_manage_settings($can_manage) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Administrators can manage settings
        if (current_user_can(self::CAP_MANAGE_SETTINGS)) {
            return true;
        }

        return false;
    }

    /**
     * Get user groups for a user
     */
    public function get_user_groups($user_id) {
        $terms = wp_get_object_terms($user_id, 'kh_user_group', array('fields' => 'slugs'));
        return is_wp_error($terms) ? array() : $terms;
    }

    /**
     * Get permissions for user groups
     */
    public function get_group_permissions() {
        $permissions = get_option('kh_events_group_permissions', array());

        // Default permissions
        $default_permissions = array(
            self::GROUP_ADMINISTRATOR => array(
                'can_create_events' => true,
                'can_edit_events' => true,
                'can_delete_events' => true,
                'can_view_bookings' => true,
                'can_manage_bookings' => true,
                'can_view_reports' => true,
                'can_manage_users' => true,
            ),
            self::GROUP_EVENT_MANAGER => array(
                'can_create_events' => true,
                'can_edit_events' => true,
                'can_delete_events' => true,
                'can_view_bookings' => true,
                'can_manage_bookings' => false,
                'can_view_reports' => true,
                'can_manage_users' => false,
            ),
            self::GROUP_EVENT_ORGANIZER => array(
                'can_create_events' => true,
                'can_edit_events' => true,
                'can_delete_events' => false,
                'can_view_bookings' => true,
                'can_manage_bookings' => false,
                'can_view_reports' => false,
                'can_manage_users' => false,
            ),
            self::GROUP_BOOKING_MANAGER => array(
                'can_create_events' => false,
                'can_edit_events' => false,
                'can_delete_events' => false,
                'can_view_bookings' => true,
                'can_manage_bookings' => true,
                'can_view_reports' => true,
                'can_manage_users' => false,
            ),
            self::GROUP_VIEWER => array(
                'can_create_events' => false,
                'can_edit_events' => false,
                'can_delete_events' => false,
                'can_view_bookings' => false,
                'can_manage_bookings' => false,
                'can_view_reports' => false,
                'can_manage_users' => false,
            ),
        );

        return wp_parse_args($permissions, $default_permissions);
    }

    /**
     * Restrict admin menu based on permissions
     */
    public function restrict_admin_menu() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        // Remove menu items based on permissions
        if (!current_user_can(self::CAP_MANAGE_SETTINGS)) {
            remove_submenu_page('kh-events', 'kh-events-settings');
        }

        if (!current_user_can(self::CAP_VIEW_REPORTS)) {
            remove_submenu_page('kh-events', 'kh-events-reports');
        }

        if (!current_user_can(self::CAP_MANAGE_BOOKINGS)) {
            remove_submenu_page('kh-events', 'edit.php?post_type=kh_booking');
        }
    }

    /**
     * Map meta capabilities for custom post types
     */
    public function map_meta_capabilities($caps, $cap, $user_id, $args) {
        switch ($cap) {
            case 'edit_kh_event':
                $post = get_post($args[0]);
                if ($post && $post->post_type === 'kh_event') {
                    if ($this->can_edit_event(true, $post->ID, $user_id)) {
                        $caps = array('edit_posts');
                    } else {
                        $caps = array('do_not_allow');
                    }
                }
                break;
            case 'delete_kh_event':
                $post = get_post($args[0]);
                if ($post && $post->post_type === 'kh_event') {
                    if ($this->can_delete_event(true, $post->ID)) {
                        $caps = array('delete_posts');
                    } else {
                        $caps = array('do_not_allow');
                    }
                }
                break;
        }

        return $caps;
    }

    /**
     * Get all available user groups
     */
    public function get_available_groups() {
        $terms = get_terms(array(
            'taxonomy' => 'kh_user_group',
            'hide_empty' => false,
        ));

        $groups = array();
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $groups[$term->slug] = $term->name;
            }
        }

        return $groups;
    }

    /**
     * Update group permissions
     */
    public function update_group_permissions($permissions) {
        update_option('kh_events_group_permissions', $permissions);
    }
}
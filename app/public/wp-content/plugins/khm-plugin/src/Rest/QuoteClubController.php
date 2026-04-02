<?php

namespace KHM\Rest;

use KHM\Services\CreditService;
use KHM\Services\MembershipRepository;
use KHM\Services\LevelRepository;
use KHM\Services\SponsorService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class QuoteClubController {

    private CreditService $credits;

    public function __construct() {
        $this->credits = new CreditService(new MembershipRepository(), new LevelRepository());
    }

    public function register(): void {
        register_rest_route('khm/v1', '/portal/quoteclub/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/upcoming', [
            'methods' => 'GET',
            'callback' => [$this, 'upcoming'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_commentary'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_commentary_status'],
            'permission_callback' => [$this, 'check_editorial_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/session/(?P<session_id>[a-zA-Z0-9\-_]+)/commentary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_session_commentary'],
            'permission_callback' => [$this, 'check_editorial_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/saved-searches', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_saved_searches'],
                'permission_callback' => [$this, 'check_sponsor_auth'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_search'],
                'permission_callback' => [$this, 'check_sponsor_auth'],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/saved-searches/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_saved_search'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/sponsor/(?P<sponsor_id>\d+)/invite', [
            'methods' => 'POST',
            'callback' => [$this, 'invite_team_member'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/sponsor/invite/accept', [
            'methods' => 'POST',
            'callback' => [$this, 'accept_team_invite'],
            'permission_callback' => [$this, 'check_invite_accept_auth'],
        ]);
    }

    public function check_sponsor_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            return true;
        }

        return SponsorService::get_user_sponsor($user_id) !== null;
    }

    public function check_editorial_auth(): bool {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    public function check_invite_accept_auth(WP_REST_Request $request): bool {
        if (is_user_logged_in()) {
            return true;
        }

        $token = sanitize_text_field((string) $request->get_param('token'));
        $email = sanitize_email((string) $request->get_param('email'));

        if ($token === '' || strlen($token) < 12) {
            return false;
        }

        return $email !== '' && is_email($email);
    }

    public function search(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(50, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $date_from = sanitize_text_field((string) $request->get_param('date_from'));
        $date_to = sanitize_text_field((string) $request->get_param('date_to'));
        $topics = $request->get_param('topics');
        $portfolios = $request->get_param('portfolios');
        $keywords = sanitize_text_field((string) $request->get_param('keywords'));
        $operator = strtoupper((string) $request->get_param('operator')) === 'OR' ? 'OR' : 'AND';

        if (!is_array($topics)) {
            $topics = [];
        }
        if (!is_array($portfolios)) {
            $portfolios = [];
        }

        $meta_query = ['relation' => 'AND'];

        if (!empty($topics)) {
            $topic_query = ['relation' => 'OR'];
            foreach ($topics as $topic) {
                $topic_query[] = [
                    'key' => 'topics',
                    'value' => sanitize_text_field((string) $topic),
                    'compare' => 'LIKE',
                ];
            }
            $meta_query[] = $topic_query;
        }

        if (!empty($portfolios)) {
            $portfolio_query = ['relation' => 'OR'];
            foreach ($portfolios as $portfolio) {
                $portfolio_query[] = [
                    'key' => 'portfolio',
                    'value' => sanitize_text_field((string) $portfolio),
                    'compare' => 'LIKE',
                ];
            }
            $meta_query[] = $portfolio_query;
        }

        $args = [
            'post_type' => 'planner_session',
            'post_status' => ['publish', 'future', 'draft', 'pending'],
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => $meta_query,
            's' => $keywords,
        ];

        if (!empty($date_from) || !empty($date_to)) {
            $date_query = [];
            if (!empty($date_from)) {
                $date_query['after'] = $date_from;
            }
            if (!empty($date_to)) {
                $date_query['before'] = $date_to;
            }
            $date_query['inclusive'] = true;
            $args['date_query'] = [$date_query];
        }

        $query = new \WP_Query($args);
        $results = [];
        $tokens = $this->tokenize_keywords($keywords, $operator);

        foreach ($query->posts as $post) {
            $brief = (string) get_post_meta($post->ID, 'key_messages', true);
            if ($brief === '') {
                $brief = wp_strip_all_tags((string) $post->post_content);
            }

            $score = $this->calculate_match_score($post, $brief, $tokens);
            $session_id = (string) get_post_meta($post->ID, 'session_id', true);
            if ($session_id === '') {
                $session_id = 'ep-' . $post->ID;
            }

            $results[] = [
                'session_id' => $session_id,
                'title' => get_the_title($post),
                'scheduled_publish' => mysql2date('Y-m-d', $post->post_date, false),
                'portfolio' => (string) get_post_meta($post->ID, 'portfolio', true),
                'topics' => $this->normalize_list_meta((string) get_post_meta($post->ID, 'topics', true)),
                'word_count' => (int) get_post_meta($post->ID, 'word_count', true),
                'brief_snippet' => wp_trim_words($brief, 24, '...'),
                'match_score' => $score,
                'session_brief_url' => add_query_arg(['page' => 'editorial_planner', 'session_id' => $session_id], admin_url('admin.php')),
                'post_id' => (int) $post->ID,
            ];
        }

        usort($results, function(array $a, array $b): int {
            return (int) $b['match_score'] <=> (int) $a['match_score'];
        });

        return new WP_REST_Response([
            'success' => true,
            'meta' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int) $query->found_posts,
            ],
            'results' => $results,
        ], 200);
    }

    public function upcoming(WP_REST_Request $request): WP_REST_Response {
        $limit = min(50, max(1, (int) ($request->get_param('limit') ?: 20)));
        $today = current_time('Y-m-d');
        $end = date('Y-m-d', strtotime($today . ' +42 days'));

        $query = new \WP_Query([
            'post_type' => 'planner_session',
            'post_status' => ['future', 'publish', 'draft', 'pending'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'ASC',
            'date_query' => [[
                'after' => $today,
                'before' => $end,
                'inclusive' => true,
            ]],
        ]);

        $sessions = [];
        foreach ($query->posts as $post) {
            $session_id = (string) get_post_meta($post->ID, 'session_id', true);
            if ($session_id === '') {
                $session_id = 'ep-' . $post->ID;
            }

            $questions_raw = (string) get_post_meta($post->ID, 'quoteclub_questions', true);
            $questions = $this->normalize_questions($questions_raw, (string) $post->post_content, (string) get_post_meta($post->ID, 'key_messages', true));

            $sessions[] = [
                'session_id' => $session_id,
                'post_id' => (int) $post->ID,
                'title' => get_the_title($post),
                'scheduled_publish' => mysql2date('Y-m-d', $post->post_date, false),
                'brief' => wp_trim_words(wp_strip_all_tags((string) $post->post_content), 100, '...'),
                'questions' => $questions,
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'sessions' => $sessions,
        ], 200);
    }

    public function submit_commentary(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('auth_required', 'Login required', ['status' => 401]);
        }

        $sponsor = SponsorService::get_user_sponsor($user_id);
        if (!$sponsor && !current_user_can('manage_options')) {
            return new WP_Error('no_access', 'Not sponsor team member', ['status' => 403]);
        }

        $rate = $this->check_rate_limit($user_id);
        if ($rate !== true) {
            return new WP_REST_Response(['success' => false, 'error' => 'rate_limited'], 429);
        }

        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $question_id = sanitize_text_field((string) $request->get_param('question_id'));
        $text = wp_kses_post((string) $request->get_param('commentary_text'));
        $is_press_release = (bool) $request->get_param('is_press_release');
        $post_id = (int) $request->get_param('post_id');

        if ($text === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'empty_commentary'], 400);
        }

        $word_count = function_exists('khm_count_words') ? khm_count_words($text) : max(1, str_word_count(wp_strip_all_tags($text)));
        $credits_needed = (int) ceil($word_count / 100);

        $charged = false;
        if ($is_press_release) {
            $charged = $this->credits->usePressReleaseCredit($user_id);
            if (!$charged) {
                return new WP_REST_Response(['success' => false, 'error' => 'insufficient_press_release_credits'], 402);
            }
            $credits_used = 1;
        } else {
            $session_hash = abs(crc32($session_id));
            $charged = $this->credits->useEditorialCredits($user_id, $credits_needed, 'quote_club_commentary', $session_hash);
            if (!$charged) {
                return new WP_REST_Response(['success' => false, 'error' => 'insufficient_editorial_credits'], 402);
            }
            $credits_used = $credits_needed;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        $inserted = $wpdb->insert(
            $table,
            [
                'sponsor_id' => (int) ($sponsor['id'] ?? 0),
                'session_id' => $session_id,
                'post_id' => $post_id > 0 ? $post_id : null,
                'question_id' => $question_id,
                'user_id' => $user_id,
                'commentary_text' => $text,
                'word_count' => $word_count,
                'credits_used' => $credits_used,
                'status' => 'pending_editorial',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'db_insert_failed'], 500);
        }

        $commentary_id = (int) $wpdb->insert_id;
        do_action('khm_quoteclub_commentary_submitted', $commentary_id, $session_id, $user_id, (int) ($sponsor['id'] ?? 0));

        return new WP_REST_Response([
            'success' => true,
            'commentary_id' => $commentary_id,
            'credits_used' => $credits_used,
            'new_editorial_balance' => $this->credits->getEditorialCredits($user_id),
        ], 200);
    }

    public function get_session_commentary(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $status = sanitize_text_field((string) ($request->get_param('status') ?: 'pending_editorial'));
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s AND status = %s ORDER BY created_at DESC",
            $session_id,
            $status
        ), ARRAY_A);

        return new WP_REST_Response([
            'success' => true,
            'items' => $rows ?: [],
        ], 200);
    }

    public function update_commentary_status(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        if (!$this->check_editorial_auth()) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $id = (int) $request->get_param('id');
        $status = sanitize_text_field((string) $request->get_param('status'));
        $allowed = ['pending_editorial', 'approved', 'rejected', 'published'];

        if (!in_array($status, $allowed, true)) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_status'], 400);
        }

        $table = $wpdb->prefix . 'khm_sponsor_commentary';
        $updated = $wpdb->update(
            $table,
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            [ 'id' => $id ],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
            'status' => $status,
        ], 200);
    }

    public function save_search(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id = get_current_user_id();
        $sponsor = SponsorService::get_user_sponsor($user_id);

        $name = sanitize_text_field((string) $request->get_param('name'));
        $query = $request->get_param('query');

        if ($name === '' || !is_array($query)) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_payload'], 400);
        }

        $table = $wpdb->prefix . 'khm_saved_searches';
        $inserted = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'sponsor_id' => (int) ($sponsor['id'] ?? 0),
                'name' => $name,
                'query_json' => wp_json_encode($query),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'db_insert_failed'], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'saved_search' => [
                'id' => (int) $wpdb->insert_id,
                'name' => $name,
                'query' => $query,
            ],
        ], 200);
    }

    public function list_saved_searches(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id = get_current_user_id();
        $sponsor = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);

        $table = $wpdb->prefix . 'khm_saved_searches';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND sponsor_id = %d ORDER BY created_at DESC",
            $user_id,
            $sponsor_id
        ), ARRAY_A);

        $items = [];
        foreach ($rows ?: [] as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'query' => json_decode((string) $row['query_json'], true) ?: [],
                'last_run_at' => $row['last_run_at'],
                'created_at' => $row['created_at'],
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'saved_searches' => $items,
        ], 200);
    }

    public function delete_saved_searches(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['success' => false, 'error' => 'not_implemented'], 501);
    }

    public function delete_saved_search(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id = get_current_user_id();
        $id = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'khm_saved_searches';

        $owned = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ));

        if (!$owned) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
        if ($deleted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'delete_failed'], 500);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function invite_team_member(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $sponsor_id = (int) $request->get_param('sponsor_id');

        if (!current_user_can('manage_options') && !SponsorService::is_sponsor_team_member($user_id, $sponsor_id)) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $email = sanitize_email((string) $request->get_param('email'));
        $first_name = sanitize_text_field((string) $request->get_param('first_name'));
        $last_name = sanitize_text_field((string) $request->get_param('last_name'));
        $job_title = sanitize_text_field((string) $request->get_param('job_title'));
        $membership_level = sanitize_text_field((string) ($request->get_param('membership_level') ?: 'sponsor'));

        if (!$email || !is_email($email)) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_email'], 400);
        }

        $token = wp_generate_password(32, false, false);
        $invite = [
            'sponsor_id' => $sponsor_id,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'job_title' => $job_title,
            'membership_level' => $membership_level,
            'token' => $token,
            'created_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + (48 * HOUR_IN_SECONDS)),
        ];

        $pending = get_option('khm_sponsor_pending_invites', []);
        if (!is_array($pending)) {
            $pending = [];
        }
        $pending[] = $invite;
        update_option('khm_sponsor_pending_invites', $pending, false);

        $invite_link = add_query_arg([
            'khm_sponsor_invite' => rawurlencode($token),
            'khm_sponsor_invite_email' => rawurlencode($email),
        ], home_url('/'));

        wp_mail(
            $email,
            __('You have been invited to join a sponsor team', 'khm-membership'),
            sprintf(
                "You have been invited to join the sponsor team.\n\nAccept invite: %s",
                esc_url_raw($invite_link)
            )
        );

        return new WP_REST_Response([
            'success' => true,
            'invite_token' => $token,
        ], 200);
    }

    public function accept_team_invite(WP_REST_Request $request): WP_REST_Response {
        $token = sanitize_text_field((string) $request->get_param('token'));
        $email = sanitize_email((string) $request->get_param('email'));

        if ($token === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'token_required'], 400);
        }

        if ($email === '' || !is_email($email)) {
            return new WP_REST_Response(['success' => false, 'error' => 'email_required'], 400);
        }

        $lock_key = $this->invite_accept_lock_key($token);
        if (get_transient($lock_key)) {
            return new WP_REST_Response(['success' => false, 'error' => 'invite_in_progress'], 409);
        }
        set_transient($lock_key, 1, MINUTE_IN_SECONDS);

        try {

            $pending = get_option('khm_sponsor_pending_invites', []);
            if (!is_array($pending) || empty($pending)) {
                return new WP_REST_Response(['success' => false, 'error' => 'invite_not_found'], 404);
            }

            $invite_index = null;
            $invite = null;
            foreach ($pending as $idx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ((string) ($row['token'] ?? '') === $token) {
                    $invite_index = $idx;
                    $invite = $row;
                    break;
                }
            }

            if ($invite_index === null || !is_array($invite)) {
                return new WP_REST_Response(['success' => false, 'error' => 'invite_not_found'], 404);
            }

            $expires_at = (string) ($invite['expires_at'] ?? '');
            if ($expires_at !== '' && strtotime($expires_at) < time()) {
                return new WP_REST_Response(['success' => false, 'error' => 'invite_expired'], 410);
            }

            $invite_email = sanitize_email((string) ($invite['email'] ?? ''));
            if (!$invite_email || !is_email($invite_email)) {
                return new WP_REST_Response(['success' => false, 'error' => 'invalid_invite_email'], 400);
            }

            if (strcasecmp($email, $invite_email) !== 0) {
                return new WP_REST_Response(['success' => false, 'error' => 'email_mismatch'], 403);
            }

            $existing_user = get_user_by('email', $invite_email);
            if ($existing_user) {
                $user_id = (int) $existing_user->ID;
            } else {
                $base_login = sanitize_user(current(explode('@', $invite_email)), true);
                if ($base_login === '') {
                    $base_login = 'sponsor_user';
                }

                $login = $base_login;
                $suffix = 1;
                while (username_exists($login)) {
                    $suffix++;
                    $login = $base_login . $suffix;
                }

                $password = wp_generate_password(20, true, true);
                $user_id = wp_create_user($login, $password, $invite_email);
                if (is_wp_error($user_id)) {
                    return new WP_REST_Response(['success' => false, 'error' => 'user_create_failed'], 500);
                }

                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => sanitize_text_field((string) ($invite['first_name'] ?? '')),
                    'last_name' => sanitize_text_field((string) ($invite['last_name'] ?? '')),
                ]);

                wp_new_user_notification((int) $user_id, null, 'both');
            }

            $sponsor_id = (int) ($invite['sponsor_id'] ?? 0);
            $added = $this->add_user_to_sponsor_team( $sponsor_id, (int) $user_id, $invite, $invite_email );

            if (!$added) {
                return new WP_REST_Response(['success' => false, 'error' => 'team_member_add_failed'], 500);
            }

            unset($pending[$invite_index]);
            update_option('khm_sponsor_pending_invites', array_values($pending), false);

            do_action('khm_quoteclub_invite_accepted', [
                'user_id' => (int) $user_id,
                'sponsor_id' => $sponsor_id,
                'email' => $invite_email,
                'accepted_at' => current_time('mysql'),
            ]);

            return new WP_REST_Response([
                'success' => true,
                'user_id' => (int) $user_id,
                'sponsor_id' => $sponsor_id,
            ], 200);
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * Wrapper for sponsor team membership updates.
     *
     * This exists to keep invite acceptance testable without static interception.
     */
    protected function add_user_to_sponsor_team( int $sponsor_id, int $user_id, array $invite, string $email ): bool {
        return SponsorService::add_team_member(
            $sponsor_id,
            $user_id,
            [
                'first_name' => sanitize_text_field((string) ($invite['first_name'] ?? '')),
                'last_name' => sanitize_text_field((string) ($invite['last_name'] ?? '')),
                'work_email' => $email,
                'job_title' => sanitize_text_field((string) ($invite['job_title'] ?? 'Member')),
                'membership_level' => sanitize_text_field((string) ($invite['membership_level'] ?? 'sponsor')),
            ]
        );
    }

    private function invite_accept_lock_key(string $token): string {
        return 'khm_qc_invite_accept_lock_' . md5($token);
    }

    private function tokenize_keywords(string $keywords, string $operator): array {
        if ($keywords === '') {
            return [];
        }

        if ($operator === 'OR') {
            $tokens = preg_split('/\s+OR\s+|\s*,\s*/i', $keywords);
        } else {
            $tokens = preg_split('/\s+AND\s+|\s*,\s*/i', $keywords);
        }

        $tokens = array_values(array_filter(array_map('trim', (array) $tokens)));
        return array_map('strtolower', $tokens);
    }

    private function calculate_match_score(\WP_Post $post, string $brief, array $tokens): int {
        if (empty($tokens)) {
            return 50;
        }

        $title = strtolower((string) $post->post_title);
        $content = strtolower((string) $post->post_content);
        $brief_lc = strtolower($brief);

        $score = 0;
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if (strpos($title, $token) !== false) {
                $score += 3;
            }
            if (strpos($brief_lc, $token) !== false) {
                $score += 2;
            }
            if (strpos($content, $token) !== false) {
                $score += 1;
            }
        }

        return max(1, min(100, $score * 5));
    }

    private function normalize_list_meta(string $value): array {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('sanitize_text_field', $decoded)));
        }

        $parts = preg_split('/\s*,\s*/', $value);
        return array_values(array_filter(array_map('sanitize_text_field', (array) $parts)));
    }

    private function normalize_questions(string $questions_raw, string $content, string $key_messages): array {
        $questions = [];

        if ($questions_raw !== '') {
            $decoded = json_decode($questions_raw, true);
            if (is_array($decoded)) {
                foreach (array_slice($decoded, 0, 5) as $idx => $text) {
                    $q = sanitize_text_field((string) $text);
                    if ($q !== '') {
                        $questions[] = ['id' => 'q' . ($idx + 1), 'text' => $q];
                    }
                }
            } else {
                $lines = preg_split('/\r\n|\r|\n/', $questions_raw);
                foreach (array_slice((array) $lines, 0, 5) as $idx => $line) {
                    $q = sanitize_text_field((string) $line);
                    if ($q !== '') {
                        $questions[] = ['id' => 'q' . ($idx + 1), 'text' => $q];
                    }
                }
            }
        }

        if (!empty($questions)) {
            return $questions;
        }

        $seed = trim($key_messages);
        if ($seed === '') {
            $seed = wp_trim_words(wp_strip_all_tags($content), 16, '');
        }

        return [
            ['id' => 'q1', 'text' => 'What is the strongest sponsor perspective for this piece?'],
            ['id' => 'q2', 'text' => 'Which metrics or outcomes should authors prioritize?'],
            ['id' => 'q3', 'text' => 'What concise quote should appear in the intro?'],
            ['id' => 'q4', 'text' => 'Which actionable recommendation should be highlighted?'],
            ['id' => 'q5', 'text' => $seed !== '' ? 'How does this align with: ' . $seed . '?' : 'What challenge does this solve for readers?'],
        ];
    }

    private function check_rate_limit(int $user_id) {
        $key = 'khm_qc_submissions_' . $user_id;
        $current = get_transient($key);
        if (!is_array($current)) {
            $current = ['count' => 0, 'started' => time()];
        }

        if ((int) $current['count'] >= 10) {
            return false;
        }

        $current['count'] = (int) $current['count'] + 1;
        set_transient($key, $current, HOUR_IN_SECONDS);
        return true;
    }
}

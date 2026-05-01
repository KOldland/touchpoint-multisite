<?php

namespace KHM\Rest;

use KHM\Connect\ConnectQuoteClubService;
use KHM\Services\CreditService;
use KHM\Services\MembershipRepository;
use KHM\Services\LevelRepository;
use KHM\Services\SponsorService;
use KHM\Services\QuoteClubCreditBundleService;
use KHM\Services\PressReleaseService;
use KHM\Sponsors\SponsorMigration;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class QuoteClubController {

    private CreditService $credits;
    private QuoteClubCreditBundleService $bundles;
    private PressReleaseService $press_releases;

    public function __construct() {
        $this->credits = new CreditService(new MembershipRepository(), new LevelRepository());
        $this->bundles = new QuoteClubCreditBundleService($this->credits);
        $this->press_releases = new PressReleaseService($this->credits);
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

        register_rest_route('khm/v1', '/portal/quoteclub/topic-suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'topic_suggestions'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_commentary'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_commentary_detail'],
            'permission_callback' => [$this, 'check_editorial_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_commentary'],
            'permission_callback' => [$this, 'check_editorial_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_commentary'],
            'permission_callback' => [$this, 'check_editorial_auth'],
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
        register_rest_route('khm/v1', '/portal/quoteclub/session/(?P<session_id>[a-zA-Z0-9\-_]+)/connect-providers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_session_connect_providers'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
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

        register_rest_route('khm/v1', '/sponsor/(?P<sponsor_id>\d+)/invite/accept', [
            'methods' => 'POST',
            'callback' => [$this, 'accept_team_invite'],
            'permission_callback' => [$this, 'check_invite_accept_auth'],
        ]);

        // Credit bundle routes
        register_rest_route('khm/v1', '/portal/quoteclub/bundles', [
            'methods' => 'GET',
            'callback' => [$this, 'list_bundles'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/bundles/(?P<id>\d+)/purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'create_bundle_checkout'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        // Draft / confirm workflow
        register_rest_route('khm/v1', '/portal/quoteclub/commentary/draft', [
            'methods' => 'POST',
            'callback' => [$this, 'save_draft'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary/(?P<id>\d+)/draft', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_draft'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/commentary/(?P<id>\d+)/confirm', [
            'methods' => 'POST',
            'callback' => [$this, 'confirm_commentary'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        // Sponsor's own commentary history
        register_rest_route('khm/v1', '/portal/quoteclub/my-commentary', [
            'methods' => 'GET',
            'callback' => [$this, 'my_commentary'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases', [
            'methods' => 'GET',
            'callback' => [$this, 'list_press_releases'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/draft', [
            'methods' => 'POST',
            'callback' => [$this, 'create_press_release_draft'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_press_release'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/(?P<id>\d+)/draft', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_press_release_draft'],
                'permission_callback' => [$this, 'check_sponsor_auth'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_press_release_draft'],
                'permission_callback' => [$this, 'check_sponsor_auth'],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/(?P<id>\d+)/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_press_release'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/submitted', [
            'methods' => 'GET',
            'callback' => [$this, 'list_submitted_press_releases'],
            'permission_callback' => [$this, 'check_sponsor_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish_press_release'],
            'permission_callback' => [$this, 'check_editorial_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/quoteclub/press-releases/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_press_release'],
            'permission_callback' => [$this, 'check_editorial_auth'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Credit bundle handlers
    // -------------------------------------------------------------------------

    /**
     * GET /portal/quoteclub/bundles
     * Returns all active credit bundles available for purchase.
     */
    public function list_bundles(WP_REST_Request $request): WP_REST_Response {
        $bundles = $this->bundles->list_bundles(true);

        return new WP_REST_Response([
            'success' => true,
            'bundles' => array_values(array_map(function($b) {
                return [
                    'id'                    => (int) $b->id,
                    'name'                  => $b->name,
                    'description'           => $b->description,
                    'editorial_credits'     => (int) $b->editorial_credits,
                    'press_release_credits' => (int) $b->press_release_credits,
                    'price_cents'           => (int) $b->price_cents,
                    'price_display'         => '$' . number_format((int) $b->price_cents / 100, 2),
                ];
            }, $bundles)),
        ], 200);
    }

    /**
     * POST /portal/quoteclub/bundles/{id}/purchase
     * Creates a Stripe Checkout session for a credit bundle purchase.
     * Responds with { checkout_url } for the frontend to redirect to.
     */
    public function create_bundle_checkout(WP_REST_Request $request): WP_REST_Response {
        $bundle_id = (int) $request->get_param('id');
        $bundle    = $this->bundles->get_bundle($bundle_id);

        if (!$bundle || !$bundle->active) {
            return new WP_REST_Response(['success' => false, 'error' => 'bundle_not_found'], 404);
        }

        $secret = function_exists('khm_get_stripe_secret')
            ? (string) (khm_get_stripe_secret('KH_STRIPE_SECRET_KEY') ?? '')
            : '';

        if (empty($secret)) {
            return new WP_REST_Response(['success' => false, 'error' => 'stripe_not_configured'], 500);
        }

        $user_id = get_current_user_id();
        $user    = get_user_by('id', $user_id);
        $email   = $user ? $user->user_email : '';

        if (empty($email)) {
            return new WP_REST_Response(['success' => false, 'error' => 'user_email_missing'], 400);
        }

        $sponsor    = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = isset($sponsor['id']) ? (int) $sponsor['id'] : 0;

        try {
            \Stripe\Stripe::setApiKey($secret);

            // Use the bundle's Stripe price ID if set; otherwise, build a one-time price inline.
            if (!empty($bundle->stripe_price_id)) {
                $line_items = [['price' => $bundle->stripe_price_id, 'quantity' => 1]];
            } else {
                $line_items = [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => (int) $bundle->price_cents,
                        'product_data' => ['name' => $bundle->name],
                    ],
                    'quantity' => 1,
                ]];
            }

            $success_url = apply_filters(
                'khm_qc_bundle_success_url',
                home_url('/quote-club/?qc_bundle_success=1'),
                $bundle_id,
                $user_id
            );
            $cancel_url = apply_filters(
                'khm_qc_bundle_cancel_url',
                home_url('/quote-club/'),
                $bundle_id,
                $user_id
            );

            $session = \Stripe\Checkout\Session::create([
                'mode'           => 'payment',
                'line_items'     => $line_items,
                'success_url'    => $success_url,
                'cancel_url'     => $cancel_url,
                'customer_email' => $email,
                'metadata'       => [
                    'purchase_type' => 'qc_bundle',
                    'bundle_id'     => (string) $bundle_id,
                    'user_id'       => (string) $user_id,
                    'sponsor_id'    => (string) $sponsor_id,
                ],
            ]);

            // Record the pending purchase.
            $this->bundles->record_pending_purchase($user_id, $bundle_id, $session->id, $sponsor_id);

            return new WP_REST_Response([
                'success'      => true,
                'checkout_url' => $session->url,
            ], 200);

        } catch (\Exception $e) {
            error_log('[KHM QC] Bundle checkout session creation failed: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'error' => 'stripe_error'], 500);
        }
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
        $top_line_category = sanitize_text_field((string) $request->get_param('top_line_category'));
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

        if ($top_line_category !== '' && !in_array($top_line_category, $topics, true)) {
            array_unshift($topics, $top_line_category);
        }

        $topics = array_values(array_filter(array_map(static function ($topic): string {
            return sanitize_text_field((string) $topic);
        }, $topics), static function (string $topic): bool {
            return $topic !== '';
        }));

        if (!empty($topics)) {
            $category_terms = get_terms([
                'taxonomy' => 'category',
                'hide_empty' => false,
                'fields' => 'names',
            ]);

            if (!is_wp_error($category_terms) && is_array($category_terms) && !empty($category_terms)) {
                $category_lookup = [];
                foreach ($category_terms as $term_name) {
                    $name = sanitize_text_field((string) $term_name);
                    if ($name === '') {
                        continue;
                    }
                    $category_lookup[strtolower($name)] = true;
                }

                if (!empty($category_lookup)) {
                    $topic_lookup = [];
                    foreach ($topics as $topic) {
                        $topic_lookup[strtolower($topic)] = $topic;
                    }

                    $has_all_categories = true;
                    foreach (array_keys($category_lookup) as $category_key) {
                        if (!isset($topic_lookup[$category_key])) {
                            $has_all_categories = false;
                            break;
                        }
                    }

                    if ($has_all_categories) {
                        $topics = [];
                        foreach ($topic_lookup as $topic_key => $topic_value) {
                            if (!isset($category_lookup[$topic_key])) {
                                $topics[] = $topic_value;
                            }
                        }
                    }
                }
            }
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
                'topics' => $this->normalize_list_meta((string) get_post_meta($post->ID, 'topics', true)),
                'questions' => $questions,
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'sessions' => $sessions,
        ], 200);
    }

    public function topic_suggestions(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $limit = min(20, max(1, (int) ($request->get_param('limit') ?: 12)));
        $query = sanitize_text_field((string) $request->get_param('q'));

        $suggestions = [];

        // Canonical source: native WP post categories (topics are categories).
        $category_terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'fields' => 'names',
        ]);

        if (!is_wp_error($category_terms) && is_array($category_terms)) {
            foreach ($category_terms as $name) {
                $topic = sanitize_text_field((string) $name);
                if ($topic === '') {
                    continue;
                }
                $suggestions[strtolower($topic)] = $topic;
            }
        }

        // Fallback source: planner_session topics meta.
        $rows = $wpdb->get_col(
            "SELECT pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'planner_session'
               AND p.post_status IN ('publish', 'future', 'draft', 'pending')
               AND pm.meta_key = 'topics'
               AND pm.meta_value <> ''"
        );

        foreach ((array) $rows as $value) {
            foreach ($this->normalize_list_meta((string) $value) as $topic) {
                if ($topic === '') {
                    continue;
                }
                $suggestions[strtolower($topic)] = $topic;
            }
        }

        $suggestions = array_values($suggestions);
        sort($suggestions, SORT_NATURAL | SORT_FLAG_CASE);

        if ($query !== '') {
            $query_lc = strtolower($query);
            $suggestions = array_values(array_filter($suggestions, static function (string $topic) use ($query_lc): bool {
                return strpos(strtolower($topic), $query_lc) !== false;
            }));
        }

        return new WP_REST_Response([
            'success' => true,
            'suggestions' => array_slice($suggestions, 0, $limit),
        ], 200);
    }

    public function submit_commentary(WP_REST_Request $request) {
        $site_error = $this->validate_connect_site_context($request);
        if ($site_error instanceof WP_REST_Response) {
            return $site_error;
        }

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
        $connect_provider_id = (int) $request->get_param('connect_provider_id');
        $title_context = sanitize_text_field((string) $request->get_param('title_context'));
        $connect_provider_snapshot = $this->resolve_connect_provider_snapshot($connect_provider_id, $title_context);
        if ($connect_provider_id > 0 && !is_array($connect_provider_snapshot)) {
            return new WP_REST_Response(['success' => false, 'error' => 'connect_provider_not_available'], 404);
        }

        if ($text === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'empty_commentary'], 400);
        }

        $word_count = $this->count_words($text);
        $credits_needed = (int) ceil($word_count / 120);

        $charged = false;
        if ($is_press_release) {
            $charged = $this->consume_press_release_credit($user_id, $session_id);
            if (!$charged) {
                return new WP_REST_Response(['success' => false, 'error' => 'insufficient_press_release_credits'], 402);
            }
            $credits_used = 1;
        } else {
            $charged = $this->consume_editorial_credits($user_id, $credits_needed, $session_id);
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
                'connect_provider_id' => $connect_provider_id > 0 ? $connect_provider_id : null,
                'connect_provider_snapshot' => $connect_provider_snapshot ? wp_json_encode($connect_provider_snapshot) : null,
                'question_id' => $question_id,
                'user_id' => $user_id,
                'commentary_text' => $text,
                'word_count' => $word_count,
                'credits_used' => $credits_used,
                'is_press_release' => $is_press_release ? 1 : 0,
                'status' => 'pending_editorial',
                'submitted_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'db_insert_failed'], 500);
        }

        $commentary_id = (int) $wpdb->insert_id;
        do_action('khm_quoteclub_commentary_submitted', $commentary_id, $session_id, $user_id, (int) ($sponsor['id'] ?? 0));
        $this->dispatch_connect_ad_targeting_hook('commentary_submitted', [
            'commentary_id' => $commentary_id,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'sponsor_id' => (int) ($sponsor['id'] ?? 0),
            'connect_provider_id' => $connect_provider_id,
            'connect_provider_snapshot' => $connect_provider_snapshot,
            'title_context' => $title_context,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'commentary_id' => $commentary_id,
            'credits_used' => $credits_used,
            'new_editorial_balance' => $this->get_editorial_balance($user_id),
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

    public function get_session_connect_providers(WP_REST_Request $request): WP_REST_Response {
        $site_error = $this->validate_connect_site_context($request);
        if ($site_error instanceof WP_REST_Response) {
            return $site_error;
        }

        $site_id = $this->current_site_id();
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $title_context = sanitize_text_field((string) ($request->get_param('title_context') ?: ''));
        $limit = min(5, max(1, (int) ($request->get_param('limit') ?: 3)));

        $session_context = $this->get_session_context_for_connect($session_id);
        if (empty($session_context)) {
            return new WP_REST_Response(['success' => false, 'error' => 'session_not_found'], 404);
        }

        $providers = $this->get_connect_quoteclub_service()->match_for_session($session_context, $title_context, $limit);
        $providers = apply_filters(
            'khm_connect_quoteclub_provider_candidates',
            $providers,
            $session_context,
            $title_context,
            $site_id
        );
        $providers = $this->enrich_connect_provider_candidates_for_hover_cards(is_array($providers) ? $providers : []);

        $ad_targeting = apply_filters(
            'khm_connect_dynamic_ad_targeting',
            [],
            is_array($providers) ? $providers : [],
            $session_context,
            $title_context,
            $site_id
        );

        $this->dispatch_connect_ad_targeting_hook('providers_listed', [
            'session_id' => $session_id,
            'title_context' => $title_context,
            'providers' => is_array($providers) ? array_values($providers) : [],
            'ad_targeting' => is_array($ad_targeting) ? $ad_targeting : [],
        ]);

        return new WP_REST_Response([
            'success' => true,
            'site_id' => $site_id,
            'session_id' => $session_id,
            'providers' => is_array($providers) ? array_values($providers) : [],
            'ad_targeting' => is_array($ad_targeting) ? $ad_targeting : [],
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

    public function get_commentary_detail(WP_REST_Request $request): WP_REST_Response {
        if (!$this->check_editorial_auth()) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $id = (int) $request->get_param('id');
        $commentary = $this->fetch_commentary($id);
        if (!$commentary) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'commentary' => $commentary,
        ], 200);
    }

    public function approve_commentary(WP_REST_Request $request): WP_REST_Response {
        if (!$this->check_editorial_auth()) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $id = (int) $request->get_param('id');
        $commentary = $this->fetch_commentary($id);
        if (!$commentary) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        $already_approved = (string) ($commentary['status'] ?? '') === 'approved';
        if (!$already_approved) {
            $ok = $this->persist_commentary_status($id, 'approved');
            if (!$ok) {
                return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
            }

            do_action('khm_quoteclub_commentary_approved', $id, (int) ($commentary['post_id'] ?? 0), (int) ($commentary['user_id'] ?? 0));
        }

        $insert = (bool) $request->get_param('insert');
        $target = sanitize_text_field((string) ($request->get_param('insert_target') ?: 'framework'));
        if (!in_array($target, ['framework', 'post_content'], true)) {
            $target = 'framework';
        }

        $inserted = false;
        if ($insert) {
            $inserted = $this->maybe_insert_commentary_content($commentary, $target);
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
            'status' => 'approved',
            'already_approved' => $already_approved,
            'inserted' => $inserted,
            'insert_target' => $target,
        ], 200);
    }

    public function reject_commentary(WP_REST_Request $request): WP_REST_Response {
        if (!$this->check_editorial_auth()) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $id = (int) $request->get_param('id');
        $commentary = $this->fetch_commentary($id);
        if (!$commentary) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        $already_rejected = (string) ($commentary['status'] ?? '') === 'rejected';
        if (!$already_rejected) {
            $rejection_reason = sanitize_textarea_field((string) ($request->get_param('rejection_reason') ?: ''));

            global $wpdb;
            $ok = (bool) $wpdb->update(
                $wpdb->prefix . 'khm_sponsor_commentary',
                [
                    'status'           => 'rejected',
                    'rejection_reason' => $rejection_reason !== '' ? $rejection_reason : null,
                    'updated_at'       => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            if (!$ok) {
                return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
            }

            // Refund the press release credit if applicable.
            if (!empty($commentary['is_press_release'])) {
                $this->credits->refundPressReleaseCredit((int) $commentary['user_id']);
            }

            do_action('khm_quoteclub_commentary_rejected', $id, (int) ($commentary['user_id'] ?? 0), (int) ($commentary['sponsor_id'] ?? 0), $rejection_reason);
        }

        return new WP_REST_Response([
            'success'          => true,
            'id'               => $id,
            'status'           => 'rejected',
            'already_rejected' => $already_rejected,
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

            $sponsor_id = (int) ($invite['sponsor_id'] ?? 0);
            $route_sponsor_id = (int) $request->get_param('sponsor_id');
            if ($route_sponsor_id > 0 && $route_sponsor_id !== $sponsor_id) {
                return new WP_REST_Response(['success' => false, 'error' => 'sponsor_mismatch'], 403);
            }

            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $current_email = sanitize_email((string) ($current_user->user_email ?? ''));
                if ($current_email === '' || strcasecmp($current_email, $invite_email) !== 0) {
                    return new WP_REST_Response(['success' => false, 'error' => 'email_mismatch'], 403);
                }
                $user_id = (int) ($current_user->ID ?? 0);
            } else {
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
            }

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

    protected function fetch_commentary(int $id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'khm_sponsor_commentary';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    protected function get_connect_quoteclub_service(): ConnectQuoteClubService {
        return new ConnectQuoteClubService();
    }

    protected function get_session_context_for_connect(string $session_id): ?array {
        $query = new \WP_Query([
            'post_type' => 'planner_session',
            'post_status' => ['publish', 'future', 'draft', 'pending'],
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => 'session_id',
                'value' => $session_id,
                'compare' => '=',
            ]],
        ]);

        $post = $query->posts[0] ?? null;
        if (!$post) {
            return null;
        }

        return [
            'session_id' => $session_id,
            'post_id' => (int) $post->ID,
            'title' => get_the_title($post),
            'topics' => $this->normalize_list_meta((string) get_post_meta($post->ID, 'topics', true)),
            'portfolio' => (string) get_post_meta($post->ID, 'portfolio', true),
            'key_messages' => (string) get_post_meta($post->ID, 'key_messages', true),
        ];
    }

    protected function resolve_connect_provider_snapshot(int $provider_id, string $title_context = ''): ?array {
        if ($provider_id <= 0) {
            return null;
        }

        return $this->get_connect_quoteclub_service()->get_provider_snapshot($provider_id, $title_context);
    }

    protected function current_site_id(): int {
        $current_site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        return max(1, (int) apply_filters('khm_connect_current_blog_id', $current_site_id));
    }

    protected function validate_connect_site_context(WP_REST_Request $request): ?WP_REST_Response {
        $requested_site_id = (int) $request->get_param('site_id');
        $current_site_id = $this->current_site_id();

        if ($requested_site_id > 0 && $requested_site_id !== $current_site_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'connect_site_context_mismatch',
                'site_id' => $current_site_id,
            ], 403);
        }

        return null;
    }

    protected function dispatch_connect_ad_targeting_hook(string $event, array $context): void {
        do_action(
            'khm_connect_dynamic_ad_targeting_context',
            $event,
            array_merge(
                [
                    'site_id' => $this->current_site_id(),
                    'source' => 'quoteclub',
                ],
                $context
            )
        );
    }

    protected function enrich_connect_provider_candidates_for_hover_cards(array $providers): array {
        if ($providers === []) {
            return [];
        }

        $sponsor_ids = [];
        foreach ($providers as $provider) {
            $sponsor_id = (int) ($provider['sponsor_id'] ?? 0);
            if ($sponsor_id > 0) {
                $sponsor_ids[] = $sponsor_id;
            }
        }

        $sponsor_map = $this->load_sponsors_for_provider_cards($sponsor_ids);
        $enriched = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $sponsor_id = (int) ($provider['sponsor_id'] ?? 0);
            $sponsor = $sponsor_id > 0 ? ($sponsor_map[$sponsor_id] ?? null) : null;
            $membership_levels = $this->extract_sponsor_membership_levels_for_cards($sponsor);
            $is_quote_club_member = $this->levels_match_any($membership_levels, ['quote_club', 'quoteclub', 'quote-club']);
            $is_tech_connect_member = $this->levels_match_any($membership_levels, ['tech.connect', 'tech_connect', 'tech-connect', 'techconnect']);

            $primary_contact_email = $this->resolve_primary_contact_email_for_cards($sponsor);
            $provider_email = sanitize_email((string) ($provider['contact_email'] ?? ''));
            $email = $provider_email !== '' ? $provider_email : $primary_contact_email;

            $provider['hover_card'] = [
                'eligible' => $is_quote_club_member && $is_tech_connect_member,
                'quote_club_member' => $is_quote_club_member,
                'tech_connect_member' => $is_tech_connect_member,
                'contributor_name' => sanitize_text_field((string) ($provider['contact_name'] ?? ($sponsor['name'] ?? ''))),
                'contributor_email' => '',
                'mediated_intro' => true,
            ];

            $provider['contact_email'] = '';
            $enriched[] = $provider;
        }

        return $enriched;
    }

    protected function load_sponsors_for_provider_cards(array $sponsor_ids): array {
        global $wpdb;

        $sponsor_ids = array_values(array_unique(array_filter(array_map('intval', $sponsor_ids))));
        if ($sponsor_ids === []) {
            return [];
        }

        $table = SponsorMigration::sponsors_table_name();
        $placeholders = implode(',', array_fill(0, count($sponsor_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, contact_email, primary_contact_email, team_members, team_member_levels FROM {$table} WHERE id IN ({$placeholders})",
                ...$sponsor_ids
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $mapped[$id] = $row;
            }
        }

        return $mapped;
    }

    protected function extract_sponsor_membership_levels_for_cards(?array $sponsor): array {
        if (!is_array($sponsor)) {
            return [];
        }

        $levels = [];
        $team_levels = (string) ($sponsor['team_member_levels'] ?? '');
        if ($team_levels !== '') {
            $decoded = json_decode($team_levels, true);
            if (is_array($decoded)) {
                foreach ($decoded as $value) {
                    $normalized = strtolower(trim((string) $value));
                    if ($normalized !== '') {
                        $levels[] = $normalized;
                    }
                }
            }
        }

        $team_members_raw = (string) ($sponsor['team_members'] ?? '');
        if ($team_members_raw !== '') {
            $members = json_decode($team_members_raw, true);
            if (is_array($members)) {
                foreach ($members as $member) {
                    if (!is_array($member)) {
                        continue;
                    }

                    $member_level = strtolower(trim((string) ($member['membership_level'] ?? '')));
                    if ($member_level !== '') {
                        $levels[] = $member_level;
                    }
                }
            }
        }

        return array_values(array_unique($levels));
    }

    protected function levels_match_any(array $levels, array $needles): bool {
        if ($levels === [] || $needles === []) {
            return false;
        }

        foreach ($levels as $level) {
            $normalized_level = strtolower(trim((string) $level));
            if ($normalized_level === '') {
                continue;
            }

            foreach ($needles as $needle) {
                $normalized_needle = strtolower(trim((string) $needle));
                if ($normalized_needle !== '' && strpos($normalized_level, $normalized_needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function resolve_primary_contact_email_for_cards(?array $sponsor): string {
        if (!is_array($sponsor)) {
            return '';
        }

        $team_members_raw = (string) ($sponsor['team_members'] ?? '');
        if ($team_members_raw !== '') {
            $members = json_decode($team_members_raw, true);
            if (is_array($members)) {
                foreach ($members as $member) {
                    if (!is_array($member)) {
                        continue;
                    }

                    $work_email = sanitize_email((string) ($member['work_email'] ?? ''));
                    if ($work_email !== '') {
                        return $work_email;
                    }
                }
            }
        }

        $primary = sanitize_email((string) ($sponsor['primary_contact_email'] ?? ''));
        if ($primary !== '') {
            return $primary;
        }

        return sanitize_email((string) ($sponsor['contact_email'] ?? ''));
    }

    protected function count_words(string $text): int {
        if (function_exists('khm_count_words')) {
            return (int) call_user_func('khm_count_words', $text);
        }

        return max(1, str_word_count(wp_strip_all_tags($text)));
    }

    protected function persist_commentary_status(int $id, string $status): bool {
        global $wpdb;

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

        return $updated !== false;
    }

    protected function maybe_insert_commentary_content(array $commentary, string $target): bool {
        $commentary_id = (int) ($commentary['id'] ?? 0);
        $post_id = (int) ($commentary['post_id'] ?? 0);
        if ($commentary_id <= 0 || $post_id <= 0) {
            return false;
        }

        $marker_key = 'khm_qc_commentary_inserted_' . $commentary_id . '_' . $target;
        if (get_option($marker_key, 0)) {
            return false;
        }

        $content = wp_kses_post((string) ($commentary['commentary_text'] ?? ''));
        if ($content === '') {
            return false;
        }

        $content = wp_strip_all_tags($content);

        if ($target === 'post_content') {
            $post = get_post($post_id);
            if (!$post) {
                return false;
            }

            $new_content = trim((string) $post->post_content);
            $new_content = $new_content === '' ? $content : ($new_content . "\n\n" . $content);

            $result = wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content,
            ], true);

            if (is_wp_error($result)) {
                return false;
            }
        } else {
            $existing = (string) get_post_meta($post_id, 'framework', true);
            $new_framework = trim($existing);
            $new_framework = $new_framework === '' ? $content : ($new_framework . "\n\n" . $content);

            $ok = update_post_meta($post_id, 'framework', $new_framework);
            if ($ok === false) {
                return false;
            }
        }

        update_option($marker_key, 1, false);
        return true;
    }

    protected function consume_editorial_credits(int $user_id, int $credits_needed, string $session_id): bool {
        $session_hash = abs(crc32($session_id));

        if (method_exists($this->credits, 'useEditorialCredits')) {
            return (bool) $this->credits->useEditorialCredits($user_id, $credits_needed, 'quote_club_commentary', $session_hash);
        }

        return (bool) $this->credits->useCredits($user_id, $credits_needed, 'quote_club_commentary', $session_hash);
    }

    protected function consume_press_release_credit(int $user_id, string $session_id): bool {
        if (method_exists($this->credits, 'usePressReleaseCredit')) {
            return (bool) $this->credits->usePressReleaseCredit($user_id);
        }

        $session_hash = abs(crc32($session_id));
        return (bool) $this->credits->useCredits($user_id, 1, 'quote_club_press_release', $session_hash);
    }

    protected function get_editorial_balance(int $user_id): int {
        if (method_exists($this->credits, 'getEditorialCredits')) {
            return (int) $this->credits->getEditorialCredits($user_id);
        }

        if (method_exists($this->credits, 'getUserCredits')) {
            return (int) $this->credits->getUserCredits($user_id);
        }

        return 0;
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

    // -------------------------------------------------------------------------
    // Draft / confirm workflow
    // -------------------------------------------------------------------------

    /**
     * POST /portal/quoteclub/commentary/draft
     * Creates a new draft commentary — no credits are consumed yet.
     * Returns the draft ID and a shareable draft_token.
     */
    public function save_draft(WP_REST_Request $request): WP_REST_Response {
        $site_error = $this->validate_connect_site_context($request);
        if ($site_error instanceof WP_REST_Response) {
            return $site_error;
        }

        $user_id = get_current_user_id();
        $sponsor = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);

        $session_id   = sanitize_text_field((string) $request->get_param('session_id'));
        $question_id  = sanitize_text_field((string) $request->get_param('question_id'));
        $text         = wp_kses_post((string) $request->get_param('commentary_text'));
        $post_id      = (int) $request->get_param('post_id');
        $connect_provider_id = (int) $request->get_param('connect_provider_id');
        $title_context = sanitize_text_field((string) $request->get_param('title_context'));
        $connect_provider_snapshot = $this->resolve_connect_provider_snapshot($connect_provider_id, $title_context);
        if ($connect_provider_id > 0 && !is_array($connect_provider_snapshot)) {
            return new WP_REST_Response(['success' => false, 'error' => 'connect_provider_not_available'], 404);
        }

        if ($text === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'empty_commentary'], 400);
        }

        $word_count     = $this->count_words($text);
        $credits_needed = (int) ceil($word_count / 120);
        $draft_token    = wp_generate_password(40, false, false);

        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        $inserted = $wpdb->insert(
            $table,
            [
                'sponsor_id'     => $sponsor_id,
                'session_id'     => $session_id,
                'post_id'        => $post_id > 0 ? $post_id : null,
                'connect_provider_id' => $connect_provider_id > 0 ? $connect_provider_id : null,
                'connect_provider_snapshot' => $connect_provider_snapshot ? wp_json_encode($connect_provider_snapshot) : null,
                'question_id'    => $question_id,
                'user_id'        => $user_id,
                'commentary_text'=> $text,
                'word_count'     => $word_count,
                'credits_used'   => 0,
                'status'         => 'draft',
                'draft_token'    => $draft_token,
                'created_at'     => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'db_insert_failed'], 500);
        }

        $draft_id = (int) $wpdb->insert_id;

        return new WP_REST_Response([
            'success'        => true,
            'draft_id'       => $draft_id,
            'draft_token'    => $draft_token,
            'word_count'     => $word_count,
            'credits_needed' => $credits_needed,
        ], 201);
    }

    /**
     * PUT /portal/quoteclub/commentary/{id}/draft
     * Updates a draft commentary (still no credits consumed).
     * Only the owning user may update their own draft.
     */
    public function update_draft(WP_REST_Request $request): WP_REST_Response {
        $site_error = $this->validate_connect_site_context($request);
        if ($site_error instanceof WP_REST_Response) {
            return $site_error;
        }

        $user_id = get_current_user_id();
        $id      = (int) $request->get_param('id');

        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if ((string) $row['status'] !== 'draft') {
            return new WP_REST_Response(['success' => false, 'error' => 'not_a_draft'], 409);
        }

        $text       = wp_kses_post((string) $request->get_param('commentary_text'));
        $connect_provider_id = (int) $request->get_param('connect_provider_id');
        $title_context = sanitize_text_field((string) $request->get_param('title_context'));
        $connect_provider_snapshot = $this->resolve_connect_provider_snapshot($connect_provider_id, $title_context);
        if ($connect_provider_id > 0 && !is_array($connect_provider_snapshot)) {
            return new WP_REST_Response(['success' => false, 'error' => 'connect_provider_not_available'], 404);
        }
        $question_id = sanitize_text_field((string) ($request->get_param('question_id') ?: $row['question_id']));

        if ($text === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'empty_commentary'], 400);
        }

        $word_count     = $this->count_words($text);
        $credits_needed = (int) ceil($word_count / 120);

        $wpdb->update(
            $table,
            [
                'commentary_text' => $text,
                'connect_provider_id' => $connect_provider_id > 0 ? $connect_provider_id : (int) ($row['connect_provider_id'] ?? 0),
                'connect_provider_snapshot' => $connect_provider_snapshot ? wp_json_encode($connect_provider_snapshot) : (string) ($row['connect_provider_snapshot'] ?? ''),
                'question_id'     => $question_id,
                'word_count'      => $word_count,
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s', '%d', '%s'],
            ['%d']
        );

        return new WP_REST_Response([
            'success'        => true,
            'id'             => $id,
            'word_count'     => $word_count,
            'credits_needed' => $credits_needed,
        ], 200);
    }

    /**
     * POST /portal/quoteclub/commentary/{id}/confirm
     * Finalises a draft: consumes credits and moves status to pending_editorial.
     * Idempotent — calling again on an already-submitted commentary returns success.
     */
    public function confirm_commentary(WP_REST_Request $request): WP_REST_Response {
        $site_error = $this->validate_connect_site_context($request);
        if ($site_error instanceof WP_REST_Response) {
            return $site_error;
        }

        $user_id        = get_current_user_id();
        $id             = (int) $request->get_param('id');
        $is_press_release = (bool) $request->get_param('is_press_release');

        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        // Idempotent — already submitted.
        if ((string) $row['status'] !== 'draft') {
            return new WP_REST_Response([
                'success'        => true,
                'already_submitted' => true,
                'status'         => $row['status'],
                'credits_used'   => (int) $row['credits_used'],
            ], 200);
        }

        $rate = $this->check_rate_limit($user_id);
        if ($rate !== true) {
            return new WP_REST_Response(['success' => false, 'error' => 'rate_limited'], 429);
        }

        $word_count     = (int) $row['word_count'];
        $credits_needed = (int) ceil($word_count / 120);

        $sponsor = SponsorService::get_user_sponsor($user_id);
        $session_id = (string) $row['session_id'];

        if ($is_press_release) {
            $charged = $this->consume_press_release_credit($user_id, $session_id);
            if (!$charged) {
                return new WP_REST_Response(['success' => false, 'error' => 'insufficient_press_release_credits'], 402);
            }
            $credits_used = 1;
        } else {
            $charged = $this->consume_editorial_credits($user_id, $credits_needed, $session_id);
            if (!$charged) {
                // Return balance info so the JS can show the buy-credits CTA.
                $balance = $this->get_editorial_balance($user_id);
                return new WP_REST_Response([
                    'success'          => false,
                    'error'            => 'insufficient_editorial_credits',
                    'credits_needed'   => $credits_needed,
                    'credits_available'=> $balance,
                ], 402);
            }
            $credits_used = $credits_needed;
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'          => 'pending_editorial',
                'credits_used'    => $credits_used,
                'is_press_release'=> $is_press_release ? 1 : 0,
                'submitted_at'    => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            // Credits already consumed — refund on best-effort basis.
            if ($is_press_release) {
                $this->credits->refundPressReleaseCredit($user_id);
            }
            return new WP_REST_Response(['success' => false, 'error' => 'db_update_failed'], 500);
        }

        do_action('khm_quoteclub_commentary_submitted', $id, $session_id, $user_id, (int) ($sponsor['id'] ?? 0));
        $this->dispatch_connect_ad_targeting_hook('commentary_confirmed', [
            'commentary_id' => $id,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'sponsor_id' => (int) ($sponsor['id'] ?? 0),
            'connect_provider_id' => (int) ($row['connect_provider_id'] ?? 0),
            'connect_provider_snapshot' => json_decode((string) ($row['connect_provider_snapshot'] ?? ''), true) ?: null,
        ]);

        return new WP_REST_Response([
            'success'               => true,
            'id'                    => $id,
            'status'                => 'pending_editorial',
            'credits_used'          => $credits_used,
            'new_editorial_balance' => $this->get_editorial_balance($user_id),
        ], 200);
    }

    /**
     * GET /portal/quoteclub/my-commentary
     * Returns the logged-in sponsor's commentary history (all statuses).
     */
    public function my_commentary(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id    = get_current_user_id();
        $sponsor    = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);
        $status     = sanitize_text_field((string) ($request->get_param('status') ?: ''));
        $page       = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page   = min(50, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $offset     = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        if ($status !== '') {
            $allowed_statuses = ['draft', 'pending_editorial', 'approved', 'rejected', 'published'];
            if (!in_array($status, $allowed_statuses, true)) {
                return new WP_REST_Response(['success' => false, 'error' => 'invalid_status'], 400);
            }
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, session_id, question_id, word_count, credits_used, status,
                        rejection_reason, connect_provider_id, connect_provider_snapshot, created_at, submitted_at,
                        LEFT(commentary_text, 200) AS commentary_excerpt
                 FROM {$table}
                 WHERE user_id = %d AND sponsor_id = %d AND status = %s
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id, $sponsor_id, $status, $per_page, $offset
            ), ARRAY_A);
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND sponsor_id = %d AND status = %s",
                $user_id, $sponsor_id, $status
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, session_id, question_id, word_count, credits_used, status,
                        rejection_reason, connect_provider_id, connect_provider_snapshot, created_at, submitted_at,
                        LEFT(commentary_text, 200) AS commentary_excerpt
                 FROM {$table}
                 WHERE user_id = %d AND sponsor_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id, $sponsor_id, $per_page, $offset
            ), ARRAY_A);
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND sponsor_id = %d",
                $user_id, $sponsor_id
            ));
        }

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $total,
            ],
            'items' => $rows ?: [],
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Press Release handlers
    // -------------------------------------------------------------------------

    /**
     * GET /portal/quoteclub/press-releases
     * Lists all press releases for the current sponsor (paginated).
     */
    public function list_press_releases(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id    = get_current_user_id();
        $sponsor    = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);
        $status     = sanitize_text_field((string) ($request->get_param('status') ?: ''));
        $page       = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page   = min(50, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $offset     = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'khm_press_releases';

        if ($status !== '') {
            $allowed_statuses = ['draft', 'submitted', 'published', 'rejected'];
            if (!in_array($status, $allowed_statuses, true)) {
                return new WP_REST_Response(['success' => false, 'error' => 'invalid_status'], 400);
            }
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title, status, excerpt, rejection_reason, created_at, updated_at,
                        submission_date, published_date, LEFT(content, 300) AS fallback_excerpt
                 FROM {$table}
                 WHERE sponsor_id = %d AND status = %s
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $sponsor_id, $status, $per_page, $offset
            ), ARRAY_A);
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE sponsor_id = %d AND status = %s",
                $sponsor_id, $status
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title, status, excerpt, rejection_reason, created_at, updated_at,
                        submission_date, published_date, LEFT(content, 300) AS fallback_excerpt
                 FROM {$table}
                 WHERE sponsor_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $sponsor_id, $per_page, $offset
            ), ARRAY_A);
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE sponsor_id = %d",
                $sponsor_id
            ));
        }

        $rows = array_map(function(array $row): array {
            if (empty($row['excerpt']) && !empty($row['fallback_excerpt'])) {
                $row['excerpt'] = $row['fallback_excerpt'];
            }
            unset($row['fallback_excerpt']);
            return $row;
        }, $rows ?: []);

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $total,
            ],
            'items' => $rows,
        ], 200);
    }

    /**
     * POST /portal/quoteclub/press-releases/draft
     * Creates a new press release draft (no credits consumed yet).
     */
    public function create_press_release_draft(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $sponsor = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);

        $title   = sanitize_text_field((string) $request->get_param('title'));
        $content = wp_kses_post((string) $request->get_param('content'));

        if ($title === '' || $content === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'missing_required_fields'], 400);
        }

        // S7: Portfolio distribution — validate and sanitise site IDs.
        $raw_ids = $request->get_param('distribution_site_ids');
        $dist_ids = is_array($raw_ids) ? array_values(array_map('absint', $raw_ids)) : [];
        if (is_multisite() && !empty($dist_ids)) {
            $valid_blogs = get_sites(['fields' => 'ids', 'public' => 1, 'archived' => 0, 'deleted' => 0, 'number' => 200]);
            $dist_ids = array_values(array_intersect($dist_ids, array_map('intval', $valid_blogs)));
        }
        $dist_json = wp_json_encode($dist_ids);

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $excerpt = wp_trim_words(wp_strip_all_tags($content), 30, '...');

        $row_data    = [
            'sponsor_id'            => $sponsor_id,
            'user_id'               => $user_id,
            'title'                 => $title,
            'content'               => $content,
            'excerpt'               => $excerpt,
            'status'                => 'draft',
            'created_at'            => current_time('mysql'),
            'updated_at'            => current_time('mysql'),
        ];
        $row_formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        // Only include distribution_site_ids if the column exists.
        $has_dist_col = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'distribution_site_ids'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if ($has_dist_col) {
            $row_data['distribution_site_ids'] = $dist_json;
            $row_formats[]                     = '%s';
        }

        $inserted = $wpdb->insert($table, $row_data, $row_formats);

        if ($inserted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'db_insert_failed'], 500);
        }

        $draft_id = (int) $wpdb->insert_id;

        return new WP_REST_Response([
            'success'               => true,
            'draft_id'              => $draft_id,
            'title'                 => $title,
            'excerpt'               => $excerpt,
            'distribution_site_ids' => $dist_ids,
        ], 201);
    }

    /**
     * GET /portal/quoteclub/press-releases/{id}
     * Retrieves a single press release by ID.
     */
    public function get_press_release(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id = get_current_user_id();
        $sponsor = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);
        $id = (int) $request->get_param('id');

        $table = $wpdb->prefix . 'khm_press_releases';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND sponsor_id = %d",
            $id,
            $sponsor_id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'press_release' => $row,
        ], 200);
    }

    /**
     * PUT /portal/quoteclub/press-releases/{id}/draft
     * Updates a press release draft (no credits consumed yet).
     */
    public function update_press_release_draft(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $id = (int) $request->get_param('id');

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if ((string) $row['status'] !== 'draft') {
            return new WP_REST_Response(['success' => false, 'error' => 'not_a_draft'], 409);
        }

        $title   = sanitize_text_field((string) ($request->get_param('title') ?: $row['title']));
        $content = wp_kses_post((string) ($request->get_param('content') ?: $row['content']));

        if ($title === '' || $content === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'missing_required_fields'], 400);
        }

        // S7: Portfolio distribution.
        $raw_ids = $request->get_param('distribution_site_ids');
        if (is_array($raw_ids)) {
            $dist_ids = array_values(array_map('absint', $raw_ids));
            if (is_multisite() && !empty($dist_ids)) {
                $valid_blogs = get_sites(['fields' => 'ids', 'public' => 1, 'archived' => 0, 'deleted' => 0, 'number' => 200]);
                $dist_ids = array_values(array_intersect($dist_ids, array_map('intval', $valid_blogs)));
            }
        } else {
            $existing_json = isset($row['distribution_site_ids']) ? (string) $row['distribution_site_ids'] : '[]';
            $dist_ids = (array) json_decode($existing_json, true);
        }
        $dist_json = wp_json_encode(array_values(array_map('intval', $dist_ids)));

        $excerpt = wp_trim_words(wp_strip_all_tags($content), 30, '...');

        $update_data    = [
            'title'      => $title,
            'content'    => $content,
            'excerpt'    => $excerpt,
            'updated_at' => current_time('mysql'),
        ];
        $update_formats = ['%s', '%s', '%s', '%s'];

        $has_dist_col = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'distribution_site_ids'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if ($has_dist_col) {
            $update_data['distribution_site_ids'] = $dist_json;
            $update_formats[]                     = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            $update_formats,
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
        }

        return new WP_REST_Response([
            'success'               => true,
            'id'                    => $id,
            'title'                 => $title,
            'excerpt'               => $excerpt,
            'distribution_site_ids' => $dist_ids,
        ], 200);
    }

    /**
     * DELETE /portal/quoteclub/press-releases/{id}/draft
     * Deletes a draft press release only if still in draft status.
     */
    public function delete_press_release_draft(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $id = (int) $request->get_param('id');

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if ((string) $row['status'] !== 'draft') {
            return new WP_REST_Response(['success' => false, 'error' => 'cannot_delete_non_draft'], 409);
        }

        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
        if ($deleted === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'delete_failed'], 500);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * POST /portal/quoteclub/press-releases/{id}/submit
     * Submits a draft press release for editorial review (consumes 1 credit).
     */
    public function submit_press_release(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $sponsor = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);
        $id = (int) $request->get_param('id');

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if ((string) $row['status'] !== 'draft') {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_status'], 409);
        }

        // Charge 1 press release credit
        $charged = $this->consume_press_release_credit($user_id, 'press_release_' . $id);
        if (!$charged) {
            return new WP_REST_Response(['success' => false, 'error' => 'insufficient_press_release_credits'], 402);
        }

        $has_dist_col = (bool) $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'distribution_site_ids'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $updated = $wpdb->update(
            $table,
            [
                'status'          => 'submitted',
                'submission_date' => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            // Refund the credit on failure
            $this->credits->refundPressReleaseCredit($user_id);
            return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
        }

        do_action('khm_press_release_submitted', $id, $sponsor_id, $user_id);

        // S7: Fire distribution hook if extra blog IDs were requested.
        if ($has_dist_col ?? false) {
            $dist_raw = $wpdb->get_var($wpdb->prepare("SELECT distribution_site_ids FROM `{$table}` WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $dist_ids = is_string($dist_raw) ? (array) json_decode($dist_raw, true) : [];
            if (!empty($dist_ids)) {
                do_action('khm_press_release_distribution_requested', $id, $sponsor_id, array_map('intval', $dist_ids));
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
            'status' => 'submitted',
            'credits_remaining' => $this->credits->getPressReleaseCredits($user_id),
        ], 200);
    }

    /**
     * GET /portal/quoteclub/press-releases/submitted
     * Lists all submitted press releases for the current sponsor.
     */
    public function list_submitted_press_releases(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id    = get_current_user_id();
        $sponsor    = SponsorService::get_user_sponsor($user_id);
        $sponsor_id = (int) ($sponsor['id'] ?? 0);
        $page       = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page   = min(50, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $offset     = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'khm_press_releases';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, status, created_at, submission_date, published_date, rejection_reason,
                    LEFT(content, 300) AS excerpt
             FROM {$table}
             WHERE sponsor_id = %d AND status IN ('submitted', 'published', 'rejected')
             ORDER BY submission_date DESC
             LIMIT %d OFFSET %d",
            $sponsor_id, $per_page, $offset
        ), ARRAY_A);
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE sponsor_id = %d AND status IN ('submitted', 'published', 'rejected')",
            $sponsor_id
        ));

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $total,
            ],
            'items' => $rows ?: [],
        ], 200);
    }

    /**
     * POST /portal/quoteclub/press-releases/{id}/publish
     * Publishes a submitted press release (editorial-only action).
     */
    public function publish_press_release(WP_REST_Request $request): WP_REST_Response {
        if (!$this->check_editorial_auth()) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $id = (int) $request->get_param('id');

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if ((string) $row['status'] !== 'submitted') {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_status'], 409);
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'         => 'published',
                'published_date' => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
        }

        do_action('khm_press_release_published', $id, (int) $row['sponsor_id'], (int) $row['user_id']);

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
            'status' => 'published',
        ], 200);
    }

    /**
     * POST /portal/quoteclub/press-releases/{id}/reject
     * Rejects a submitted press release and refunds the credit (editorial-only action).
     */
    public function reject_press_release(WP_REST_Request $request): WP_REST_Response {
        if (!$this->check_editorial_auth()) {
            return new WP_REST_Response(['success' => false, 'error' => 'forbidden'], 403);
        }

        $id = (int) $request->get_param('id');
        $reason = sanitize_textarea_field((string) ($request->get_param('reason') ?: ''));

        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if ((string) $row['status'] !== 'submitted') {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_status'], 409);
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'        => 'rejected',
                'rejection_reason' => $reason,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'update_failed'], 500);
        }

        // Refund the press release credit to the submitting user
        $this->credits->refundPressReleaseCredit((int) $row['user_id']);

        do_action('khm_press_release_rejected', $id, (int) $row['sponsor_id'], (int) $row['user_id'], $reason);

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
            'status' => 'rejected',
        ], 200);
    }
}

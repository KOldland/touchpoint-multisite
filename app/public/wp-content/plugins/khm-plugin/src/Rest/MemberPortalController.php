<?php

namespace KHM\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use KHM\Services\MembershipRepository;
use KHM\Services\CreditService;
use KHM\Services\LibraryService;
use KHM\Services\AnswerCardLibraryService;
use KHM\Services\LevelRepository;
use KHM\Services\CreditDownloadService;

/**
 * Member Portal Controller
 * 
 * REST API endpoints for the member portal:
 * - GET /khm/v1/portal/dashboard - Get dashboard overview
 * - GET /khm/v1/portal/credits - Get credits balance and history
 * - POST /khm/v1/portal/credits/topup - Create credit top-up order
 * - GET /khm/v1/portal/downloads - Get download history
 * - GET /khm/v1/portal/library - Get saved library items
 * - POST /khm/v1/portal/library/{post_id} - Save to library
 * - DELETE /khm/v1/portal/library/{post_id} - Remove from library
 * - GET /khm/v1/portal/membership - Get membership details
 * - POST /khm/v1/portal/membership/pause - Pause membership
 * - POST /khm/v1/portal/membership/resume - Resume membership
 * - POST /khm/v1/portal/membership/cancel - Cancel membership
 */
class MemberPortalController {

    private MembershipRepository $memberships;
    private CreditService $credits;
    private LibraryService $library;
    private AnswerCardLibraryService $answercards;
    private LevelRepository $levels;
    private CreditDownloadService $downloads;

    public function __construct() {
        $this->memberships = new MembershipRepository();
        $this->levels = new LevelRepository();
        $this->credits = new CreditService($this->memberships, $this->levels);
        $this->library = new LibraryService($this->memberships);
        $this->answercards = new AnswerCardLibraryService($this->memberships);
        $this->downloads = new CreditDownloadService($this->memberships, $this->credits, $this->library);
    }

    /**
     * Register REST routes
     */
    public function register(): void {
        // Dashboard
        register_rest_route('khm/v1', '/portal/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Credits
        register_rest_route('khm/v1', '/portal/credits', [
            'methods' => 'GET',
            'callback' => [$this, 'get_credits'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 20],
                'offset' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/credits/topup', [
            'methods' => 'POST',
            'callback' => [$this, 'create_topup'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'amount' => ['type' => 'integer', 'required' => true, 'minimum' => 1],
            ],
        ]);

        // Downloads
        register_rest_route('khm/v1', '/portal/downloads', [
            'methods' => 'GET',
            'callback' => [$this, 'get_downloads'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 20],
                'offset' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/downloads/(?P<post_id>\d+)/regenerate', [
            'methods' => 'POST',
            'callback' => [$this, 'regenerate_pdf'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        // Library
        register_rest_route('khm/v1', '/portal/library', [
            'methods' => 'GET',
            'callback' => [$this, 'get_library'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 20],
                'offset' => ['type' => 'integer', 'default' => 0],
                'category' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/library/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'save_to_library'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/library/(?P<post_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'remove_from_library'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        // Library remove via POST (for AJAX compatibility)
        register_rest_route('khm/v1', '/portal/library/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove_from_library_post'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // AnswerCards
        register_rest_route('khm/v1', '/portal/answercards', [
            'methods' => 'GET',
            'callback' => [$this, 'get_answercards'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 20],
                'offset' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/answercards/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'save_answercard'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
                'answer_card_id' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('khm/v1', '/portal/answercards/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove_answercard'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Membership
        register_rest_route('khm/v1', '/portal/membership', [
            'methods' => 'GET',
            'callback' => [$this, 'get_membership'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/membership/pause', [
            'methods' => 'POST',
            'callback' => [$this, 'pause_membership'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/membership/resume', [
            'methods' => 'POST',
            'callback' => [$this, 'resume_membership'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('khm/v1', '/portal/membership/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_membership'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Activity/Transactions
        register_rest_route('khm/v1', '/portal/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 10],
            ],
        ]);

        // Gift voucher redemption
        register_rest_route('khm/v1', '/portal/gift/redeem', [
            'methods' => 'POST',
            'callback' => [$this, 'redeem_gift_voucher'],
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'token' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    /**
     * Check if user is authenticated
     */
    public function check_auth(): bool {
        return is_user_logged_in();
    }

    /**
     * GET /portal/dashboard - Get dashboard overview
     */
    public function get_dashboard(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Get membership info
        $memberships = $this->memberships->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;
        $level = $membership ? $this->levels->get($membership->level_id) : null;

        // Get credits
        $credit_balance = $this->credits->getUserCredits($user_id);

        // Get library stats
        $library_stats = $this->library->get_library_stats($user_id);

        // Get recent downloads
        $downloads = $this->downloads->getUserDownloads($user_id, ['limit' => 5]);

        // Get recent activity
        $activity = $this->get_recent_activity($user_id, 5);

        return new WP_REST_Response([
            'success' => true,
            'user' => [
                'id' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user_id, ['size' => 96]),
            ],
            'membership' => $membership ? [
                'id' => $membership->id,
                'level_id' => $membership->level_id,
                'level_name' => $level ? $level->name : 'Unknown',
                'status' => $membership->status,
                'started_at' => $membership->started_at,
                'expires_at' => $membership->expires_at,
                'next_billing' => $membership->next_billing_at ?? null,
                'monthly_credits' => $level ? ($level->monthly_credits ?? 0) : 0,
            ] : null,
            'credits' => [
                'balance' => $credit_balance,
            ],
            'library' => [
                'total_saved' => $library_stats['total_saved'] ?? 0,
                'favorites' => $library_stats['favorites'] ?? 0,
                'unread' => $library_stats['unread'] ?? 0,
            ],
            'downloads' => [
                'total' => $this->downloads->getUserDownloadCount($user_id),
                'recent' => array_map(function($d) {
                    return [
                        'post_id' => $d->post_id,
                        'post_title' => $d->post_title ?? get_the_title($d->post_id),
                        'credits_used' => $d->credits_used,
                        'last_download' => $d->last_download_at,
                    ];
                }, $downloads),
            ],
            'activity' => $activity,
        ], 200);
    }

    /**
     * POST /portal/gift/redeem - Redeem a gift voucher and add to library
     */
    public function redeem_gift_voucher(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $token = sanitize_text_field($request->get_param('token'));

        if (!$token) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Voucher code is required.',
            ], 400);
        }

        if (!function_exists('khm_call_service')) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Gift service not available.',
            ], 500);
        }

        $result = khm_call_service('redeem_gift', $token, 'library_save', $user_id);

        if (empty($result['success'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $result['error'] ?? 'Unable to redeem voucher.',
            ], 400);
        }

        $post_id = $result['post_id'] ?? 0;
        $post = $post_id ? get_post($post_id) : null;

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'post_title' => $post ? $post->post_title : null,
            'message' => $post
                ? sprintf('Voucher redeemed! "%s" has been added to your library.', $post->post_title)
                : 'Voucher redeemed! Article added to your library.',
        ], 200);
    }

    /**
     * GET /portal/credits - Get credit balance and history
     */
    public function get_credits(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');

        $balance = $this->credits->getUserCredits($user_id);
        $history = function_exists('khm_get_credit_history') 
            ? khm_get_credit_history($user_id, $limit) 
            : [];

        return new WP_REST_Response([
            'success' => true,
            'balance' => $balance,
            'history' => $history,
        ], 200);
    }

    /**
     * POST /portal/credits/topup - Create credit top-up order
     */
    public function create_topup(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $amount = (int) $request->get_param('amount');

        // For now, we'll add credits directly (admin allocation)
        // In production, this would create a Stripe checkout session
        try {
            $this->credits->addBonusCredits($user_id, $amount, 'Top-up purchase');
            
            return new WP_REST_Response([
                'success' => true,
                'message' => sprintf(__('%d credits added to your account.', 'khm-membership'), $amount),
                'new_balance' => $this->credits->getUserCredits($user_id),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /portal/downloads - Get download history
     */
    public function get_downloads(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');

        $downloads = $this->downloads->getUserDownloads($user_id, [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $total = $this->downloads->getUserDownloadCount($user_id);

        $formatted = array_map(function($download) use ($user_id) {
            $post = get_post($download->post_id);
            return [
                'id' => $download->id,
                'post_id' => $download->post_id,
                'post_title' => $post ? $post->post_title : 'Unknown Article',
                'post_url' => $post ? get_permalink($post->ID) : '#',
                'thumbnail' => $post ? get_the_post_thumbnail_url($post->ID, 'thumbnail') : null,
                'credits_used' => $download->credits_used,
                'download_count' => $download->download_count,
                'first_download' => $download->first_download_at,
                'last_download' => $download->last_download_at,
                'can_redownload' => true, // Always free for previously downloaded
            ];
        }, $downloads);

        return new WP_REST_Response([
            'success' => true,
            'downloads' => $formatted,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    /**
     * POST /portal/downloads/{post_id}/regenerate - Regenerate PDF
     */
    public function regenerate_pdf(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');

        // Check if user has downloaded this before (free regeneration)
        $eligibility = $this->downloads->checkDownloadEligibility($user_id, $post_id);

        if (!$eligibility['can_download']) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $eligibility['reason'],
            ], 403);
        }

        // Process the download (will be free if already downloaded)
        $result = $this->downloads->processDownload($user_id, $post_id);

        if (!$result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        // Generate download token
        $token = $this->generateDownloadToken($user_id, $post_id);
        $download_url = rest_url("khm/v1/download/{$post_id}/pdf") . '?token=' . $token;

        return new WP_REST_Response([
            'success' => true,
            'download_url' => $download_url,
            'is_free' => $result['is_free'] ?? false,
            'credits_used' => $result['credits_used'],
        ], 200);
    }

    /**
     * GET /portal/library - Get saved library items
     */
    public function get_library(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');
        $category = $request->get_param('category');

        $items = $this->library->get_member_library($user_id, [
            'limit' => $limit,
            'offset' => $offset,
            'category' => $category,
        ]);

        $purchased_lookup = [];
        if (!empty($items)) {
            global $wpdb;
            $post_ids = array_map(static function($item) {
                return (int) $item->post_id;
            }, $items);

            $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
            $sql = "SELECT post_id FROM {$wpdb->prefix}khm_purchases
                 WHERE user_id = %d AND status = 'completed' AND post_id IN ({$placeholders})";
            $args = array_merge([$sql, $user_id], $post_ids);
            $query = call_user_func_array([$wpdb, 'prepare'], $args);
            $purchased_ids = $wpdb->get_col($query);
            foreach ($purchased_ids as $post_id) {
                $purchased_lookup[(int) $post_id] = true;
            }
        }

        $categories = $this->library->get_member_categories($user_id);
        $stats = $this->library->get_library_stats($user_id);

        $formatted = array_map(function($item) use ($purchased_lookup) {
            $post = get_post($item->post_id);
            return [
                'id' => $item->id,
                'post_id' => $item->post_id,
                'post_title' => $post ? $post->post_title : 'Unknown Article',
                'post_url' => $post ? get_permalink($post->ID) : '#',
                'post_excerpt' => $post ? wp_trim_words($post->post_excerpt ?: $post->post_content, 20) : '',
                'thumbnail' => $post ? get_the_post_thumbnail_url($post->ID, 'medium') : null,
                'category_id' => $item->category_id ?? null,
                'is_favorite' => (bool) ($item->is_favorite ?? false),
                'is_read' => (bool) ($item->is_read ?? false),
                'notes' => $item->notes ?? '',
                'saved_at' => $item->created_at,
                'is_purchased' => !empty($purchased_lookup[(int) $item->post_id]),
            ];
        }, $items);

        return new WP_REST_Response([
            'success' => true,
            'items' => $formatted,
            'categories' => $categories,
            'stats' => $stats,
            'total' => $stats['total_saved'] ?? 0,
        ], 200);
    }

    /**
     * POST /portal/library/{post_id} - Save to library
     */
    public function save_to_library(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');

        // Check if already saved
        if ($this->library->is_saved($user_id, $post_id)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Article already in your library.', 'khm-membership'),
            ], 400);
        }

        try {
            $this->library->save_to_library($user_id, $post_id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Article saved to library.', 'khm-membership'),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /portal/library/{post_id} - Remove from library
     */
    public function remove_from_library(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');

        try {
            $this->library->remove_from_library($user_id, $post_id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Article removed from library.', 'khm-membership'),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /portal/library/remove - Remove from library (AJAX compatible)
     */
    public function remove_from_library_post(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');

        if (!$post_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Invalid post ID.', 'khm-membership'),
            ], 400);
        }

        try {
            $this->library->remove_from_library($user_id, $post_id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Article removed from library.', 'khm-membership'),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /portal/answercards - Get saved answer cards
     */
    public function get_answercards(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');

        $items = $this->answercards->get_member_answercards($user_id, [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $formatted = array_map(function($item) {
            $post = get_post($item->post_id);
            $card = $this->find_answercard($item->post_id, $item->answer_card_id);
            return [
                'id' => $item->id,
                'post_id' => $item->post_id,
                'answer_card_id' => $item->answer_card_id,
                'question' => $card['question'] ?? ($post ? $post->post_title : 'Section Summary'),
                'post_url' => $post ? get_permalink($post->ID) : '#',
                'saved_at' => $item->created_at,
            ];
        }, $items);

        return new WP_REST_Response([
            'success' => true,
            'items' => $formatted,
        ], 200);
    }

    /**
     * POST /portal/answercards/{post_id} - Save answer card
     */
    public function save_answercard(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');
        $answer_card_id = sanitize_text_field($request->get_param('answer_card_id'));
        $question = sanitize_text_field($request->get_param('question'));

        if (!$answer_card_id && $question) {
            $cards = get_post_meta($post_id, '_geo_answercards', true);
            if (is_array($cards)) {
                foreach ($cards as $index => $card) {
                    $card_question = isset($card['question']) ? sanitize_text_field($card['question']) : '';
                    if ($card_question && $card_question === $question) {
                        $answer_card_id = $card['answer_card_id'] ?? '';
                        if (!$answer_card_id && class_exists('\KHM\Migrations\GeoAnswerCardMigration')) {
                            $answer_card_id = \KHM\Migrations\GeoAnswerCardMigration::generate_answer_card_id($post_id);
                            $cards[$index]['answer_card_id'] = $answer_card_id;
                            update_post_meta($post_id, '_geo_answercards', $cards);
                        }
                        break;
                    }
                }
            }
        }

        if (!$answer_card_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Missing answer_card_id.', 'khm-membership'),
            ], 400);
        }

        if ($this->answercards->is_saved($user_id, $answer_card_id)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Section summary already saved.', 'khm-membership'),
            ], 400);
        }

        $success = $this->answercards->save_to_library($user_id, $post_id, $answer_card_id);

        if ($success) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Section summary saved.', 'khm-membership'),
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'error' => __('Failed to save section summary.', 'khm-membership'),
        ], 500);
    }

    /**
     * POST /portal/answercards/remove - Remove answer card
     */
    public function remove_answercard(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $answer_card_id = sanitize_text_field($request->get_param('answer_card_id'));

        if (!$answer_card_id) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Missing answer_card_id.', 'khm-membership'),
            ], 400);
        }

        $success = $this->answercards->remove_from_library($user_id, $answer_card_id);

        if ($success) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Section summary removed.', 'khm-membership'),
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'error' => __('Failed to remove section summary.', 'khm-membership'),
        ], 500);
    }

    private function find_answercard(int $post_id, string $answer_card_id): array {
        $cards = get_post_meta($post_id, '_geo_answercards', true);
        if (!is_array($cards)) {
            return [];
        }
        foreach ($cards as $card) {
            if (!empty($card['answer_card_id']) && $card['answer_card_id'] === $answer_card_id) {
                return $card;
            }
        }
        return [];
    }

    /**
     * GET /portal/membership - Get membership details
     */
    public function get_membership(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();

        $memberships = $this->memberships->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;

        if (!$membership) {
            return new WP_REST_Response([
                'success' => true,
                'has_membership' => false,
                'membership' => null,
                'available_levels' => $this->get_available_levels(),
            ], 200);
        }

        $level = $this->levels->get($membership->level_id);

        return new WP_REST_Response([
            'success' => true,
            'has_membership' => true,
            'membership' => [
                'id' => $membership->id,
                'level_id' => $membership->level_id,
                'level_name' => $level ? $level->name : 'Unknown',
                'level_description' => $level ? ($level->description ?? '') : '',
                'status' => $membership->status,
                'started_at' => $membership->started_at,
                'expires_at' => $membership->expires_at,
                'next_billing' => $membership->next_billing_at ?? null,
                'monthly_credits' => $level ? ($level->monthly_credits ?? 0) : 0,
                'can_pause' => $membership->status === 'active',
                'can_resume' => $membership->status === 'paused',
                'can_cancel' => in_array($membership->status, ['active', 'paused']),
            ],
            'available_levels' => $this->get_available_levels(),
        ], 200);
    }

    /**
     * POST /portal/membership/pause - Pause membership
     */
    public function pause_membership(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();

        $memberships = $this->memberships->findActive($user_id);
        $membership = !empty($memberships) ? $memberships[0] : null;

        if (!$membership || $membership->status !== 'active') {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('No active membership to pause.', 'khm-membership'),
            ], 400);
        }

        try {
            $this->memberships->pauseById($membership->id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Membership paused successfully.', 'khm-membership'),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /portal/membership/resume - Resume membership
     */
    public function resume_membership(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();

        // Find paused memberships - use getById to find the paused one
        $memberships = $this->memberships->findActive($user_id);
        // Also check for paused memberships
        global $wpdb;
        $table = $wpdb->prefix . 'khm_memberships';
        $paused = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'paused' ORDER BY id DESC",
            $user_id
        ));
        $membership = !empty($paused) ? $paused[0] : null;

        if (!$membership) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('No paused membership to resume.', 'khm-membership'),
            ], 400);
        }

        try {
            $this->memberships->resumeById($membership->id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Membership resumed successfully.', 'khm-membership'),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /portal/membership/cancel - Cancel membership
     */
    public function cancel_membership(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();

        $memberships = $this->memberships->findActive($user_id);
        if (empty($memberships)) {
            // Check for paused
            global $wpdb;
            $table = $wpdb->prefix . 'khm_memberships';
            $paused = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND status = 'paused' ORDER BY id DESC",
                $user_id
            ));
            $memberships = $paused;
        }
        
        $membership = !empty($memberships) ? reset($memberships) : null;

        if (!$membership) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('No membership to cancel.', 'khm-membership'),
            ], 400);
        }

        try {
            $this->memberships->cancelById($membership->id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Membership cancelled. You will retain access until the end of your billing period.', 'khm-membership'),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /portal/activity - Get recent activity
     */
    public function get_activity(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit = (int) $request->get_param('limit');

        $activity = $this->get_recent_activity($user_id, $limit);

        return new WP_REST_Response([
            'success' => true,
            'activity' => $activity,
        ], 200);
    }

    /**
     * Get recent activity for user
     */
    private function get_recent_activity(int $user_id, int $limit = 10): array {
        global $wpdb;

        $activity = [];

        // Get credit transactions
        $credit_table = $wpdb->prefix . 'khm_credit_transactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$credit_table}'") === $credit_table) {
            $credits = $wpdb->get_results($wpdb->prepare(
                "SELECT 'credit' as type, amount, reason as description, created_at 
                 FROM {$credit_table} 
                 WHERE user_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $user_id,
                $limit
            ));
            foreach ($credits as $c) {
                $activity[] = [
                    'type' => $c->amount > 0 ? 'credit_add' : 'credit_use',
                    'description' => $c->description,
                    'amount' => abs($c->amount),
                    'date' => $c->created_at,
                ];
            }
        }

        // Get downloads
        $download_table = $wpdb->prefix . 'khm_credit_downloads';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$download_table}'") === $download_table) {
            $downloads = $wpdb->get_results($wpdb->prepare(
                "SELECT 'download' as type, post_id, credits_used, last_download_at as created_at 
                 FROM {$download_table} 
                 WHERE user_id = %d 
                 ORDER BY last_download_at DESC 
                 LIMIT %d",
                $user_id,
                $limit
            ));
            foreach ($downloads as $d) {
                $activity[] = [
                    'type' => 'download',
                    'description' => sprintf('Downloaded: %s', get_the_title($d->post_id)),
                    'post_id' => $d->post_id,
                    'credits_used' => $d->credits_used,
                    'date' => $d->created_at,
                ];
            }
        }

        // Sort by date descending
        usort($activity, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        return array_slice($activity, 0, $limit);
    }

    /**
     * Get available membership levels
     */
    private function get_available_levels(): array {
        $levels = $this->levels->all();
        
        return array_map(function($level) {
            return [
                'id' => $level->id,
                'name' => $level->name,
                'description' => $level->description ?? '',
                'price' => $level->price ?? 0,
                'billing_period' => $level->billing_period ?? 'monthly',
                'monthly_credits' => $level->monthly_credits ?? 0,
            ];
        }, $levels);
    }

    /**
     * Generate download token for PDF access
     */
    private function generateDownloadToken(int $user_id, int $post_id): string {
        $expires = time() + (5 * 60); // 5 minutes
        $data = "{$user_id}|{$post_id}|{$expires}";
        $signature = hash_hmac('sha256', $data, wp_salt('auth'));
        
        return base64_encode("{$data}|{$signature}");
    }
}

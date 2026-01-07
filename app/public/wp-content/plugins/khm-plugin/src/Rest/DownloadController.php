<?php

namespace KHM\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use KHM\Services\CreditDownloadService;
use KHM\Services\CreditService;
use KHM\Services\MembershipRepository;
use KHM\Services\LibraryService;
use KHM\Services\PDFService;

/**
 * Download Controller
 * 
 * REST API endpoints for credit-based PDF downloads:
 * - GET /khm/v1/download/check/{post_id} - Check eligibility and cost
 * - POST /khm/v1/download/{post_id} - Process download (deduct credits, generate PDF)
 * - GET /khm/v1/download/history - Get user's download history
 */
class DownloadController {

    private CreditDownloadService $download_service;
    private PDFService $pdf_service;

    public function __construct() {
        $memberships = new MembershipRepository();
        $credits = new CreditService($memberships, new \KHM\Services\LevelRepository());
        $library = new LibraryService($memberships);
        
        $this->download_service = new CreditDownloadService($memberships, $credits, $library);
        $this->pdf_service = new PDFService();
    }

    public function register(): void {
        // Register routes directly - this method is called from within rest_api_init
        // Check download eligibility (before showing confirmation modal)
        register_rest_route('khm/v1', '/download/check/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'check_eligibility'],
            'permission_callback' => function() { return is_user_logged_in(); },
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
            ],
        ]);

        // Process download (deduct credits and return PDF URL)
        register_rest_route('khm/v1', '/download/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'process_download'],
            'permission_callback' => function() { return is_user_logged_in(); },
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'confirm' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Stream PDF file directly
        register_rest_route('khm/v1', '/download/(?P<post_id>\d+)/pdf', [
            'methods' => 'GET',
            'callback' => [$this, 'stream_pdf'],
            'permission_callback' => [$this, 'verify_download_token'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'token' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Get download history
        register_rest_route('khm/v1', '/download/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_history'],
            'permission_callback' => function() { return is_user_logged_in(); },
            'args' => [
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                ],
                'offset' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);
    }

    /**
     * Check download eligibility
     * Returns credit cost and whether user can download
     */
    public function check_eligibility(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');

        // Verify post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Article not found.', 'khm-membership'),
            ], 404);
        }

        $eligibility = $this->download_service->checkDownloadEligibility($user_id, $post_id);

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'can_download' => $eligibility['can_download'],
            'is_free' => $eligibility['is_free'],
            'credits_required' => $eligibility['credits_required'],
            'reason' => $eligibility['reason'],
            'user_credits' => $eligibility['user_credits'],
            'message' => $this->getReasonMessage($eligibility),
        ], 200);
    }

    /**
     * Process download - deduct credits and return download URL
     */
    public function process_download(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('post_id');
        $confirm = (bool) $request->get_param('confirm');

        // Verify post exists
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Article not found.', 'khm-membership'),
            ], 404);
        }

        // Check eligibility first
        $eligibility = $this->download_service->checkDownloadEligibility($user_id, $post_id);

        // If not free and no confirmation, return eligibility info for modal
        if (!$eligibility['is_free'] && !$confirm) {
            return new WP_REST_Response([
                'success' => true,
                'requires_confirmation' => true,
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'can_download' => $eligibility['can_download'],
                'credits_required' => $eligibility['credits_required'],
                'user_credits' => $eligibility['user_credits'],
                'message' => $this->getReasonMessage($eligibility),
            ], 200);
        }

        // Process the download
        $result = $this->download_service->processDownload($user_id, $post_id);

        if (!$result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $result['error'],
                'credits_remaining' => $result['credits_remaining'],
            ], 400);
        }

        // Generate download token (valid for 5 minutes)
        $token = $this->generateDownloadToken($user_id, $post_id);

        // Build download URL
        $download_url = rest_url("khm/v1/download/{$post_id}/pdf") . '?token=' . $token;

        return new WP_REST_Response([
            'success' => true,
            'download_url' => $download_url,
            'credits_used' => $result['credits_used'],
            'credits_remaining' => $result['credits_remaining'],
            'is_free' => $result['is_free'] ?? false,
            'message' => $result['is_free'] 
                ? __('Re-download started!', 'khm-membership')
                : sprintf(__('Download started! %d credits used.', 'khm-membership'), $result['credits_used']),
        ], 200);
    }

    /**
     * Stream PDF file
     * Note: This outputs directly and exits to avoid WP_REST_Response corrupting binary data
     */
    public function stream_pdf(WP_REST_Request $request) {
        $post_id = (int) $request->get_param('post_id');
        // Use user ID from validated token (set in verify_download_token)
        $user_id = $request->get_param('token_user_id') ?: get_current_user_id();

        // Generate PDF
        $result = $this->pdf_service->generateArticlePDF($post_id, $user_id);

        if (!$result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $result['error'],
            ], 500);
        }

        // Output PDF directly - WP_REST_Response corrupts binary data
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
        header('Content-Length: ' . strlen($result['pdf_data']));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output PDF data
        echo $result['pdf_data'];
        exit;
    }

    /**
     * Get user's download history
     */
    public function get_history(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');

        $downloads = $this->download_service->getUserDownloads($user_id, [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $total = $this->download_service->getUserDownloadCount($user_id);

        // Format downloads for response
        $formatted = array_map(function($download) {
            return [
                'id' => $download->id,
                'post_id' => $download->post_id,
                'post_title' => $download->post_title,
                'credits_used' => $download->credits_used,
                'download_count' => $download->download_count,
                'first_download' => $download->first_download_at,
                'last_download' => $download->last_download_at,
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
     * Generate a time-limited download token
     */
    private function generateDownloadToken(int $user_id, int $post_id): string {
        $expires = time() + (5 * 60); // 5 minutes
        $data = "{$user_id}|{$post_id}|{$expires}";
        $signature = hash_hmac('sha256', $data, wp_salt('auth'));
        
        return base64_encode("{$data}|{$signature}");
    }

    /**
     * Verify download token (permission callback for PDF stream)
     * Note: We verify the token signature itself rather than requiring login,
     * since the redirect from download may not preserve cookies
     */
    public function verify_download_token(WP_REST_Request $request): bool {
        $token = $request->get_param('token');
        $post_id = (int) $request->get_param('post_id');

        if (!$token) {
            return false;
        }

        $decoded = base64_decode($token);
        if (!$decoded) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            return false;
        }

        [$token_user_id, $token_post_id, $expires, $signature] = $parts;

        // Verify signature
        $data = "{$token_user_id}|{$token_post_id}|{$expires}";
        $expected_signature = hash_hmac('sha256', $data, wp_salt('auth'));
        
        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        // Check expiration
        if ((int) $expires < time()) {
            return false;
        }

        // Verify post matches
        if ((int) $token_post_id !== $post_id) {
            return false;
        }

        // Store user ID for use in stream_pdf
        $request->set_param('token_user_id', (int) $token_user_id);

        return true;
    }

    /**
     * Get human-readable message for eligibility reason
     */
    private function getReasonMessage(array $eligibility): string {
        if ($eligibility['is_free']) {
            return __('This article is in your library. Re-download is free!', 'khm-membership');
        }

        if (!$eligibility['can_download']) {
            switch ($eligibility['reason']) {
                case 'membership_required':
                    return __('An active membership is required to use credits.', 'khm-membership');
                case 'insufficient_credits':
                    return sprintf(
                        __('Insufficient credits. You have %d, but this download costs %d.', 'khm-membership'),
                        $eligibility['user_credits'],
                        $eligibility['credits_required']
                    );
                default:
                    return __('Unable to download this article.', 'khm-membership');
            }
        }

        return sprintf(
            __('This download will use %d credit(s). You have %d credits.', 'khm-membership'),
            $eligibility['credits_required'],
            $eligibility['user_credits']
        );
    }
}

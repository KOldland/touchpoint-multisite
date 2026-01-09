<?php

namespace KHM\Services;

use KHM\Services\PluginRegistry;
use KHM\Services\MembershipRepository;
use KHM\Services\OrderRepository;
use KHM\Services\LevelRepository;
use KHM\Services\CreditService;
use KHM\Services\PDFService;
use KHM\Services\LibraryService;
use KHM\Services\ECommerceService;
use KHM\Services\GiftService;
use KHM\Services\EmailService;

/**
 * Marketing Suite Services
 *
 * Provides standardized services that other marketing suite plugins can use
 */
class MarketingSuiteServices {

    private MembershipRepository $memberships;
    private OrderRepository $orders;
    private LevelRepository $levels;
    private CreditService $credits;
    private PDFService $pdf;
    private LibraryService $library;
    private ECommerceService $ecommerce;
    private GiftService $gift;
    private EmailService $email;

    public function __construct(
        MembershipRepository $memberships,
        OrderRepository $orders,
        LevelRepository $levels
    ) {
        $this->memberships = $memberships;
        $this->orders = $orders;
        $this->levels = $levels;
        $this->credits = new CreditService($memberships, $levels);
        $this->pdf = new PDFService();
        $this->library = new LibraryService($memberships);
        $this->ecommerce = new ECommerceService($memberships, $orders);
        
        // Use enhanced email service if available, otherwise fall back to basic email service
        $email_service = isset($GLOBALS['khm_enhanced_email']) && $GLOBALS['khm_enhanced_email'] instanceof \KHM\Services\EnhancedEmailService
            ? $GLOBALS['khm_enhanced_email']
            : new EmailService(__DIR__ . '/../../');
            
        $this->gift = new GiftService($memberships, $orders, $email_service);
        $this->email = $email_service;
    }

    /**
     * Register all services with the plugin registry
     */
    public function register_services(): void {
        // User & Membership Services
        PluginRegistry::register_service('get_user_membership', [$this, 'get_user_membership']);
        PluginRegistry::register_service('check_user_access', [$this, 'check_user_access']);
        PluginRegistry::register_service('get_member_discount', [$this, 'get_member_discount']);
        
        // Payment & Order Services
        PluginRegistry::register_service('create_order', [$this, 'create_order']);
        PluginRegistry::register_service('process_payment', [$this, 'process_payment']);
        PluginRegistry::register_service('get_user_orders', [$this, 'get_user_orders']);
        
        // Credit System Services (Enhanced)
        PluginRegistry::register_service('get_user_credits', [$this, 'get_user_credits']);
        PluginRegistry::register_service('use_credit', [$this, 'use_credit']);
        PluginRegistry::register_service('add_credits', [$this, 'add_credits']);
        PluginRegistry::register_service('allocate_monthly_credits', [$this, 'allocate_monthly_credits']);
        PluginRegistry::register_service('get_credit_history', [$this, 'get_credit_history']);
        
        // PDF & Download Services
        PluginRegistry::register_service('generate_article_pdf', [$this, 'generate_article_pdf']);
        PluginRegistry::register_service('create_download_url', [$this, 'create_download_url']);
        PluginRegistry::register_service('download_with_credits', [$this, 'download_with_credits']);
        
        // Library Services  
        PluginRegistry::register_service('save_to_library', [$this, 'save_to_library']);
        PluginRegistry::register_service('remove_from_library', [$this, 'remove_from_library']);
        PluginRegistry::register_service('is_saved_to_library', [$this, 'is_saved_to_library']);
        PluginRegistry::register_service('toggle_library_save', [$this, 'toggle_library_save']);
        PluginRegistry::register_service('get_member_library', [$this, 'get_member_library']);
        PluginRegistry::register_service('get_library_categories', [$this, 'get_library_categories']);
        PluginRegistry::register_service('create_library_category', [$this, 'create_library_category']);
        PluginRegistry::register_service('update_library_item', [$this, 'update_library_item']);
        PluginRegistry::register_service('share_library_article', [$this, 'share_library_article']);
        PluginRegistry::register_service('get_library_count', [$this, 'get_library_count']);        // eCommerce Services
        PluginRegistry::register_service('get_article_pricing', [$this, 'get_article_pricing']);
        PluginRegistry::register_service('add_to_cart', [$this, 'add_to_cart']);
        PluginRegistry::register_service('remove_from_cart', [$this, 'remove_from_cart']);
        PluginRegistry::register_service('get_cart', [$this, 'get_cart']);
        PluginRegistry::register_service('get_cart_count', [$this, 'get_cart_count']);
        PluginRegistry::register_service('clear_cart', [$this, 'clear_cart']);
        PluginRegistry::register_service('process_purchase', [$this, 'process_purchase']);
        PluginRegistry::register_service('has_purchased', [$this, 'has_purchased']);
        PluginRegistry::register_service('get_purchase_history', [$this, 'get_purchase_history']);
        
        // Gift Services
        PluginRegistry::register_service('send_gift', [$this, 'send_gift']);
        PluginRegistry::register_service('redeem_gift', [$this, 'redeem_gift']);
        PluginRegistry::register_service('get_gift_data', [$this, 'get_gift_data']);
        PluginRegistry::register_service('get_sent_gifts', [$this, 'get_sent_gifts']);
        PluginRegistry::register_service('get_received_gifts', [$this, 'get_received_gifts']);
        
        // Level & Pricing Services
        PluginRegistry::register_service('get_all_levels', [$this, 'get_all_levels']);
        PluginRegistry::register_service('get_level_pricing', [$this, 'get_level_pricing']);
    }

    /**
     * Get user's active membership
     *
     * @param int $user_id
     * @return object|null
     */
    public function get_user_membership(int $user_id): ?object {
        $memberships = $this->memberships->findActive($user_id);
        return !empty($memberships) ? $memberships[0] : null;
    }

    /**
     * Check if user has access to specific content/feature
     *
     * @param int $user_id
     * @param string $access_type Type of access (e.g., 'article_download', 'premium_content')
     * @param array $params Additional parameters
     * @return bool
     */
    public function check_user_access(int $user_id, string $access_type, array $params = []): bool {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership) {
            return false;
        }

        // Apply filters for extensibility
        return apply_filters(
            'khm_check_user_access',
            $this->default_access_check($membership, $access_type, $params),
            $user_id,
            $access_type,
            $params,
            $membership
        );
    }

    /**
     * Get member discount for a given price
     *
     * @param int $user_id
     * @param float $original_price
     * @param string $item_type
     * @return array ['discounted_price' => float, 'discount_percent' => int, 'discount_amount' => float]
     */
    public function get_member_discount(int $user_id, float $original_price, string $item_type = 'general'): array {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership) {
            return [
                'discounted_price' => $original_price,
                'discount_percent' => 0,
                'discount_amount' => 0
            ];
        }

        // Get level-specific discount
        $level = $this->levels->get($membership->membership_id);
        $discount_percent = $level->member_discount ?? 0;

        // Apply filters for custom discount logic
        $discount_percent = apply_filters(
            'khm_member_discount_percent',
            $discount_percent,
            $user_id,
            $original_price,
            $item_type,
            $membership
        );

        $discount_amount = ($original_price * $discount_percent) / 100;
        $discounted_price = max(0, $original_price - $discount_amount);

        return [
            'discounted_price' => $discounted_price,
            'discount_percent' => $discount_percent,
            'discount_amount' => $discount_amount
        ];
    }

    /**
     * Create an order for external plugins
     *
     * @param array $order_data
     * @return object|false
     */
    public function create_order(array $order_data) {
        // Validate required fields
        $required = ['user_id', 'total', 'item_type'];
        foreach ($required as $field) {
            if (!isset($order_data[$field])) {
                return false;
            }
        }

        // Create order array
        $order = array_merge([
            'user_id' => 0,
            'membership_id' => 0,
            'code' => $this->orders->generateCode(),
            'subtotal' => $order_data['total'],
            'total' => $order_data['total'],
            'currency' => 'GBP',
            'status' => 'pending',
            'gateway' => 'khm_external',
            'created_at' => current_time('mysql')
        ], $order_data);

        return $this->orders->create($order);
    }

    /**
     * Get user's credit balance (Enhanced)
     *
     * @param int $user_id
     * @return int
     */
    public function get_user_credits(int $user_id): int {
        return $this->credits->getUserCredits($user_id);
    }

    /**
     * Use credits for a user (Enhanced)
     *
     * @param int $user_id
     * @param int $amount
     * @param string $reason
     * @param int|null $object_id
     * @return bool
     */
    public function use_credit(int $user_id, int $amount = 1, string $reason = 'download', ?int $object_id = null): bool {
        return $this->credits->useCredits($user_id, $amount, $reason, $object_id);
    }

    /**
     * Add credits to a user (Enhanced)
     *
     * @param int $user_id
     * @param int $amount
     * @param string $reason
     * @return bool
     */
    public function add_credits(int $user_id, int $amount, string $reason = 'manual'): bool {
        return $this->credits->addBonusCredits($user_id, $amount, $reason);
    }

    /**
     * Allocate monthly credits for a user
     *
     * @param int $user_id
     * @return bool
     */
    public function allocate_monthly_credits(int $user_id): bool {
        return $this->credits->allocateMonthlyCredits($user_id);
    }

    /**
     * Get credit usage history for a user
     *
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public function get_credit_history(int $user_id, int $limit = 20): array {
        return $this->credits->getCreditHistory($user_id, $limit);
    }

    /**
     * Generate PDF for article download
     *
     * @param int $post_id
     * @param int $user_id
     * @return array
     */
    public function generate_article_pdf(int $post_id, int $user_id): array {
        return $this->pdf->generateArticlePDF($post_id, $user_id);
    }

    /**
     * Create secure download URL
     *
     * @param int $post_id
     * @param int $user_id
     * @param int $expires_hours
     * @return string
     */
    public function create_download_url(int $post_id, int $user_id, int $expires_hours = 2): string {
        return $this->pdf->createDownloadURL($post_id, $user_id, $expires_hours);
    }

    /**
     * Complete download flow with credit usage
     *
     * @param int $post_id
     * @param int $user_id
     * @return array
     */
    public function download_with_credits(int $post_id, int $user_id): array {
        // Check if user has credits
        if ($this->get_user_credits($user_id) < 1) {
            return [
                'success' => false,
                'error' => 'Insufficient credits',
                'credits_remaining' => 0
            ];
        }

        // Use credit
        if (!$this->use_credit($user_id, 1, 'article_download', $post_id)) {
            return [
                'success' => false,
                'error' => 'Failed to process credit usage'
            ];
        }

        // Create download URL
        $download_url = $this->create_download_url($post_id, $user_id);
        
        return [
            'success' => true,
            'download_url' => $download_url,
            'credits_remaining' => $this->get_user_credits($user_id)
        ];
    }

    /**
     * Get all membership levels
     *
     * @return array
     */
    public function get_all_levels(): array {
        return $this->levels->all();
    }

    /**
     * Get pricing details for a specific membership level.
     * Returns null if the level is missing.
     */
    public function get_level_pricing(int $level_id): ?array {
        $level = $this->levels->get($level_id, true);
        if (!$level) {
            return null;
        }

        return [
            'initial_payment' => $level->initial_payment ?? null,
            'billing_amount' => $level->billing_amount ?? null,
            'cycle_number' => $level->cycle_number ?? null,
            'cycle_period' => $level->cycle_period ?? null,
            'billing_limit' => $level->billing_limit ?? null,
            'trial_amount' => $level->trial_amount ?? null,
            'trial_limit' => $level->trial_limit ?? null,
            'allow_signups' => $level->allow_signups ?? null,
        ];
    }

    /**
     * Get user's order history
     *
     * @param int $user_id
     * @param array $args
     * @return array
     */
    public function get_user_orders(int $user_id, array $args = []): array {
        return $this->orders->findByUser($user_id, $args);
    }

    /**
     * Default access check logic
     *
     * @param object $membership
     * @param string $access_type
     * @param array $params
     * @return bool
     */
    private function default_access_check(object $membership, string $access_type, array $params): bool {
        // Basic active membership check
        if ($membership->status !== 'active') {
            return false;
        }

        // Check expiration
        if ($membership->enddate && strtotime($membership->enddate) < time()) {
            return false;
        }

        // Type-specific checks
        switch ($access_type) {
            case 'article_download':
                return $this->get_user_credits($membership->user_id) > 0;
            
            case 'premium_content':
                return true; // Active membership grants access
            
            case 'member_pricing':
                return true; // Active membership gets discounts
            
            default:
                return true;
        }
    }

    // ===================================================================
    // LIBRARY SERVICE WRAPPER METHODS
    // ===================================================================

    /**
     * Save article to member's library
     */
    public function save_to_library(int $member_id, int $post_id, int $category_id = null): bool {
        return $this->library->save_to_library($member_id, $post_id, $category_id);
    }

    /**
     * Remove article from member's library
     */
    public function remove_from_library(int $member_id, int $post_id): bool {
        return $this->library->remove_from_library($member_id, $post_id);
    }

    /**
     * Check if article is saved in member's library
     */
    public function is_saved_to_library(int $member_id, int $post_id): bool {
        return $this->library->is_saved($member_id, $post_id);
    }

    /**
     * Toggle save status for article
     */
    public function toggle_library_save(int $member_id, int $post_id): array {
        return $this->library->toggle_save($member_id, $post_id);
    }

    /**
     * Get member's library items
     */
    public function get_member_library(int $member_id, array $args = []): array {
        return $this->library->get_member_library($member_id, $args);
    }

    /**
     * Get member's library categories
     */
    public function get_library_categories(int $member_id): array {
        return $this->library->get_member_categories($member_id);
    }

    /**
     * Create new library category
     */
    public function create_library_category(int $member_id, string $category_name, string $privacy = 'private'): int {
        return $this->library->create_category($member_id, $category_name, $privacy);
    }

    /**
     * Get library count for member
     */
    public function get_library_count(int $member_id): int {
        return $this->library->get_library_count($member_id);
    }

    // ===================================================================
    // ECOMMERCE SERVICE WRAPPER METHODS
    // ===================================================================

    /**
     * Get article pricing information
     */
    public function get_article_pricing(int $post_id, int $user_id = null): array {
        return $this->ecommerce->get_article_pricing($post_id, $user_id);
    }

    /**
     * Add item to shopping cart
     */
    public function add_to_cart(int $user_id, int $post_id, int $quantity = 1): bool {
        return $this->ecommerce->add_to_cart($user_id, $post_id, $quantity);
    }

    /**
     * Remove item from shopping cart
     */
    public function remove_from_cart(int $user_id, int $post_id): bool {
        return $this->ecommerce->remove_from_cart($user_id, $post_id);
    }

    /**
     * Get user's shopping cart
     */
    public function get_cart(int $user_id): array {
        return $this->ecommerce->get_cart($user_id);
    }

    /**
     * Get cart count for user
     */
    public function get_cart_count(int $user_id): int {
        return $this->ecommerce->get_cart_count($user_id);
    }

    /**
     * Clear user's shopping cart
     */
    public function clear_cart(int $user_id): bool {
        return $this->ecommerce->clear_cart($user_id);
    }

    /**
     * Process purchase
     */
    public function process_purchase(int $user_id, array $purchase_data): array {
        return $this->ecommerce->process_purchase($user_id, $purchase_data);
    }

    /**
     * Check if user has purchased an article
     */
    public function has_purchased(int $user_id, int $post_id): bool {
        return $this->ecommerce->has_purchased($user_id, $post_id);
    }

    /**
     * Get user's purchase history
     */
    public function get_purchase_history(int $user_id, array $args = []): array {
        return $this->ecommerce->get_purchase_history($user_id, $args);
    }

    /**
     * Update library item (notes, status, favorites, category)
     *
     * @param int $member_id
     * @param int $post_id
     * @param array $updates
     * @return bool
     */
    public function update_library_item(int $member_id, int $post_id, array $updates): bool {
        return $this->library->update_item($member_id, $post_id, $updates);
    }

    /**
     * Share library article via email with personal notes
     *
     * @param int $member_id
     * @param int $post_id
     * @param string $recipient_email
     * @param string $personal_message
     * @param bool $include_notes
     * @param bool $include_membership_info
     * @return bool
     */
    public function share_library_article(
        int $member_id, 
        int $post_id, 
        string $recipient_email, 
        string $personal_message = '',
        bool $include_notes = true,
        bool $include_membership_info = true
    ): bool {
        // Get the library item to check if user has it saved
        $library_item = $this->library->get_library_item($member_id, $post_id);
        if (!$library_item) {
            return false; // User doesn't have this article in their library
        }

        // Get article details
        $post = \get_post($post_id);
        if (!$post) {
            return false;
        }

        // Get member details
        $member = \get_userdata($member_id);
        if (!$member) {
            return false;
        }

        // Prepare email data
        $email_data = [
            'member_name' => $member->display_name,
            'member_email' => $member->user_email,
            'article_title' => $post->post_title,
            'article_excerpt' => \wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
            'article_url' => \get_permalink($post_id),
            'personal_message' => $personal_message,
            'personal_notes' => $include_notes ? $library_item->notes : '',
            'include_membership_info' => $include_membership_info,
            'site_name' => \get_bloginfo('name'),
            'site_url' => \home_url(),
            'membership_url' => \home_url('/membership/') // Adjust as needed
        ];

        // Set email configuration
        $this->email->setSubject("📚 {$member->display_name} shared an article with you");
        $this->email->setFrom(\get_option('admin_email'), \get_bloginfo('name'));

        // Send the email
        return $this->email->send('library_share_article', $recipient_email, $email_data);
    }

    // ====================================
    // GIFT SERVICES
    // ====================================

    /**
     * Send a gift to a recipient
     *
     * @param int $post_id Article to gift
     * @param int $sender_id User sending the gift
     * @param string $recipient_email Recipient's email
     * @param string $recipient_name Recipient's name
     * @param string $gift_message Optional personal message
     * @param int $expires_days Days until gift expires (default 30)
     * @return array ['success' => bool, 'gift_id' => int|null, 'message' => string]
     */
    public function send_gift(int $post_id, int $sender_id, string $recipient_email, string $recipient_name, string $gift_message = '', int $expires_days = 30): array {
        // Get sender information
        $sender = \get_user_by('ID', $sender_id);
        if (!$sender) {
            return [
                'success' => false,
                'error' => 'Invalid sender'
            ];
        }

        // Get post information for pricing
        $post = \get_post($post_id);
        if (!$post) {
            return [
                'success' => false,
                'error' => 'Invalid article'
            ];
        }

        // Get article pricing (you may need to adjust this based on your pricing logic)
        $base_price = get_post_meta( $post_id, 'kss_article_price', true );
        $base_price = $base_price !== '' ? (float) $base_price : 0;
        $gift_price = apply_filters( 'khm_gift_article_price', $base_price, $post_id );

        // Prepare gift data
        $gift_data = [
            'post_id' => $post_id,
            'sender_id' => $sender_id,
            'sender_name' => $sender->display_name,
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'gift_message' => $gift_message,
            'gift_price' => $gift_price,
            'expires_days' => $expires_days
        ];

        return $this->gift->create_gift($gift_data);
    }

    /**
     * Redeem a gift using its token
     *
     * @param string $token Gift token
     * @param string $redemption_type Type: 'download', 'library_save', or 'both'
     * @param int $user_id Optional user ID (for library saves)
     * @return array ['success' => bool, 'download_url' => string|null, 'saved_to_library' => bool, 'message' => string]
     */
    public function redeem_gift(string $token, string $redemption_type = 'download', int $user_id = 0): array {
        return $this->gift->redeem_gift($token, $redemption_type, $user_id);
    }

    /**
     * Get gift data by token
     *
     * @param string $token Gift token
     * @return array|null Gift data or null if not found/expired
     */
    public function get_gift_data(string $token): ?array {
        return $this->gift->get_gift_by_token($token);
    }

    /**
     * Get gifts sent by a user
     *
     * @param int $user_id Sender user ID
     * @param int $limit Number of gifts to return
     * @param int $offset Pagination offset
     * @return array List of sent gifts
     */
    public function get_sent_gifts(int $user_id, int $limit = 20, int $offset = 0): array {
        return $this->gift->get_sent_gifts($user_id, $limit, $offset);
    }

    /**
     * Get gifts received by an email address
     *
     * @param string $email Recipient email
     * @param int $limit Number of gifts to return
     * @param int $offset Pagination offset
     * @return array List of received gifts
     */
    public function get_received_gifts(string $email, int $limit = 20, int $offset = 0): array {
        return $this->gift->get_received_gifts($email, $limit, $offset);
    }

    /**
     * Placeholder for payment processing (not yet implemented).
     * Delegates to the eCommerce service so other plugins can trigger purchases.
     */
    public function process_payment(array $payment_data = []): array {
        $user_id = isset($payment_data['user_id']) ? (int) $payment_data['user_id'] : get_current_user_id();

        if ($user_id <= 0) {
            return [
                'success' => false,
                'error' => 'Missing user_id for payment processing'
            ];
        }

        if (!method_exists($this->ecommerce, 'process_purchase')) {
            return [
                'success' => false,
                'error' => 'ECommerceService missing process_purchase'
            ];
        }

        return $this->ecommerce->process_purchase($user_id, $payment_data);
    }
}

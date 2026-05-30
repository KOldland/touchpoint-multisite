<?php
namespace KH\Editorial\Services\Membership;

/**
 * MembershipInterface
 * 
 * Standardized interface for membership operations across the modernized suite.
 */
interface MembershipInterface {
    public function get_user_membership($user_id);
    public function get_member_discount($user_id, $base_price, $item_type);
    public function has_purchased($user_id, $post_id);
    public function is_saved_to_library($user_id, $post_id);
    public function has_downloaded($user_id, $post_id);
}

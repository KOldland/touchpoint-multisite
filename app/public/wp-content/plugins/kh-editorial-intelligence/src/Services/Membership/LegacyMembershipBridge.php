<?php
namespace KH\Editorial\Services\Membership;

/**
 * LegacyMembershipBridge
 * 
 * Bridges the modernized MembershipInterface to the legacy KHM God Plugin.
 */
class LegacyMembershipBridge implements MembershipInterface {

    public function get_user_membership($user_id) {
        if (function_exists('khm_get_user_membership')) {
            return khm_get_user_membership($user_id);
        }
        return null;
    }

    public function get_member_discount($user_id, $base_price, $item_type) {
        if (function_exists('khm_get_member_discount')) {
            return khm_get_member_discount($user_id, $base_price, $item_type);
        }
        return [
            'discount_percent' => 0,
            'discounted_price' => $base_price
        ];
    }

    public function has_purchased($user_id, $post_id) {
        if (function_exists('khm_call_service')) {
            try {
                return (bool) khm_call_service('has_purchased', $user_id, $post_id);
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function is_saved_to_library($user_id, $post_id) {
        if (function_exists('khm_call_service')) {
            try {
                return (bool) khm_call_service('is_saved_to_library', $user_id, $post_id);
            } catch (\Exception $e) {
                return false;
            }
        }
        return (bool) get_user_meta($user_id, '_khm_saved_post_' . $post_id, true);
    }

    public function has_downloaded($user_id, $post_id) {
        if (function_exists('khm_call_service')) {
            try {
                return (bool) khm_call_service('has_downloaded', $user_id, $post_id);
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }
}

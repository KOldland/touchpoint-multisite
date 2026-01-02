<?php
namespace KHFolders\Core;

class Permissions
{
    const CAP_MANAGE = 'kh_manage_folders';
    const CAP_SHARE  = 'kh_manage_shared_folders';

    public static function ensureRoleCaps(array $roles)
    {
        foreach ($roles as $roleName) {
            $role = get_role($roleName);
            if (! $role) {
                continue;
            }
            if (! $role->has_cap(self::CAP_MANAGE)) {
                $role->add_cap(self::CAP_MANAGE);
            }
            if (! $role->has_cap(self::CAP_SHARE)) {
                $role->add_cap(self::CAP_SHARE);
            }
        }
    }

    public static function canManage($userId = 0)
    {
        $userId = $userId ?: get_current_user_id();
        return user_can($userId, self::CAP_MANAGE) || user_can($userId, 'manage_options');
    }

    public static function canManageShared($userId = 0)
    {
        $userId = $userId ?: get_current_user_id();
        return user_can($userId, self::CAP_SHARE) || user_can($userId, 'manage_options');
    }
}

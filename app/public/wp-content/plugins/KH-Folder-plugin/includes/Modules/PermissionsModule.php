<?php
namespace KHFolders\Modules;

use KHFolders\Core\Permissions;

class PermissionsModule implements ModuleInterface
{
    public function register()
    {
        add_action('init', [$this, 'registerCapabilities']);
    }

    public function registerCapabilities()
    {
        $roles = apply_filters('kh_folders_capability_roles', ['administrator', 'editor']);
        Permissions::ensureRoleCaps((array) $roles);
    }
}

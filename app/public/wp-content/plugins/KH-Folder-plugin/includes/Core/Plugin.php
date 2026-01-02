<?php
namespace KHFolders\Core;

use KHFolders\Modules\ModuleRegistry;

class Plugin
{
    private static $instance;
    private $registry;

    private function __construct()
    {
        $this->registry = new ModuleRegistry();
    }

    public static function instance()
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot()
    {
        do_action('kh_folders_pre_boot');
        $this->registry->boot();
        do_action('kh_folders_post_boot');
    }

    public function registry()
    {
        return $this->registry;
    }
}

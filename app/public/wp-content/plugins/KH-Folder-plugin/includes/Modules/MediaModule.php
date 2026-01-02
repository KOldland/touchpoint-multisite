<?php
namespace KHFolders\Modules;

use KHFolders\Services\FolderService;

class MediaModule implements ModuleInterface
{
    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaIntegration']);
    }

    public function enqueueMediaIntegration($hook)
    {
        if (! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $allowed = ['upload', 'post', 'page'];
        if (! in_array($screen->base, $allowed, true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'kh-folders-media',
            KH_FOLDERS_URL . 'assets/js/media-folders.js',
            ['media-views', 'jquery'],
            KH_FOLDERS_VERSION,
            true
        );

        wp_localize_script('kh-folders-media', 'khFoldersMedia', [
            'folders' => FolderService::getFolders(),
            'strings' => [
                'allFolders' => __('All Folders', 'kh-folders'),
            ],
        ]);
    }
}

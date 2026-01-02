<?php
namespace KHFolders\Modules;

use KHFolders\Core\Permissions;
use KHFolders\Services\FolderService;

class AssetsModule implements ModuleInterface
{
    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets($hook)
    {
        $shouldForce = apply_filters('kh_folders_force_assets', false, $hook);

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $supported = apply_filters('kh_folders_supported_post_types', ['attachment', 'page', 'post']);
        $isListScreen = $screen && in_array($screen->base, ['edit', 'upload'], true) && in_array($screen->post_type, (array) $supported, true);

        if (strpos($hook, 'kh-folders') === false && ! $shouldForce && ! $isListScreen) {
            return;
        }

        $userId   = get_current_user_id();
        $folders  = FolderService::getFolders(['user_id' => $userId]);

        $needsAdminUi = strpos($hook, 'kh-folders') !== false || $shouldForce;
        if ($needsAdminUi) {
            wp_register_style(
                'kh-folders-admin',
                KH_FOLDERS_URL . 'assets/css/admin-folders.css',
                [],
                KH_FOLDERS_VERSION
            );
            wp_register_script(
                'kh-folders-admin',
                KH_FOLDERS_URL . 'assets/js/admin-folders.js',
                ['jquery', 'jquery-ui-sortable'],
                KH_FOLDERS_VERSION,
                true
            );

            wp_enqueue_style('kh-folders-admin');
            wp_enqueue_script('kh-folders-admin');

            wp_localize_script('kh-folders-admin', 'khFoldersAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('kh_folders_actions'),
                'taxonomy'=> TaxonomyModule::TAXONOMY,
                'folders' => $folders,
                'permissions' => [
                    'canShare' => Permissions::canManageShared($userId),
                    'userId'   => $userId,
                ],
                'i18n'    => [
                    'enterName' => __('Enter a folder name', 'kh-folders'),
                    'created'   => __('Folder "%s" created', 'kh-folders'),
                    'confirmShared' => __('Make this folder shared? Click Cancel for a personal folder.', 'kh-folders'),
                    ],
                'noticeSuccess' => apply_filters('kh_folders_notice_success_callback', null),
                'noticeError'   => apply_filters('kh_folders_notice_error_callback', null),
                'strings' => [
                    'deleted'     => __('Folder removed.', 'kh-folders'),
                    'updated'     => __('Folder updated.', 'kh-folders'),
                    'delete'      => __('Delete', 'kh-folders'),
                    'empty'       => __('No folders yet.', 'kh-folders'),
                    'deleteLabel' => __('Delete', 'kh-folders'),
                    'bulkDeleted' => __('Selected folders removed.', 'kh-folders'),
                    'bulkConfirm' => __('Delete selected folders? This cannot be undone.', 'kh-folders'),
                    'reordered'   => __('Folder order saved.', 'kh-folders'),
                    'drag'        => __('Drag to reorder', 'kh-folders'),
                    'personal'    => __('Personal', 'kh-folders'),
                    'parentUpdated' => __('Folder parent updated.', 'kh-folders'),
                ],
            ]);
        }

        if ($isListScreen) {
            wp_register_script(
                'kh-folders-list',
                KH_FOLDERS_URL . 'assets/js/list-assign.js',
                ['jquery'],
                KH_FOLDERS_VERSION,
                true
            );

            wp_enqueue_script('kh-folders-list');

            wp_localize_script('kh-folders-list', 'khFoldersList', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('kh_folders_actions'),
                'folders' => $folders,
                'strings' => [
                    'choose'  => __('Choose a folder', 'kh-folders'),
                    'apply'   => __('Move', 'kh-folders'),
                    'saving'  => __('Saving…', 'kh-folders'),
                    'saved'   => __('Saved', 'kh-folders'),
                    'failed'  => __('Could not update folder.', 'kh-folders'),
                    'select'  => __('Select a folder first.', 'kh-folders'),
                ],
            ]);
        }
    }
}

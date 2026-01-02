<?php
namespace KHFolders\Modules;

use KHFolders\Core\Permissions;
use KHFolders\Services\FolderService;

class ImportExportModule implements ModuleInterface
{
    public function register()
    {
        add_action('admin_post_kh_folders_export', [$this, 'handleExport']);
        add_action('admin_post_kh_folders_import', [$this, 'handleImport']);
    }

    public function handleExport()
    {
        if (! Permissions::canManage()) {
            wp_die(__('You are not allowed to export folders.', 'kh-folders'));
        }

        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kh_folders_export')) {
            wp_die(__('Invalid request.', 'kh-folders'));
        }

        $json = FolderService::exportFolders();

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="kh-folders-export-' . gmdate('Ymd-His') . '.json"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function handleImport()
    {
        if (! Permissions::canManage()) {
            wp_die(__('You are not allowed to import folders.', 'kh-folders'));
        }

        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kh_folders_import')) {
            wp_die(__('Invalid request.', 'kh-folders'));
        }

        if (empty($_FILES['kh_folders_file']['tmp_name'])) {
            $this->redirectWithNotice('import_error');
        }

        $contents = file_get_contents($_FILES['kh_folders_file']['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data     = json_decode($contents, true);

        if (! is_array($data)) {
            $this->redirectWithNotice('import_error');
        }

        FolderService::importFolders($data, [
            'shared' => ! empty($_POST['shared']),
            'owner'  => get_current_user_id(),
        ]);

        $this->redirectWithNotice('import_success');
    }

    private function redirectWithNotice($notice)
    {
        $url = add_query_arg('kh_folders_notice', $notice, admin_url('admin.php?page=kh-folders'));
        wp_safe_redirect($url);
        exit;
    }
}

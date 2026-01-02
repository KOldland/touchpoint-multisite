<?php
namespace KHFolders\Modules;

use KHFolders\Core\Permissions;
use KHFolders\Services\FolderService;
use KHFolders\UI\TreeRenderer;

class AdminModule implements ModuleInterface
{
    public function register()
    {
        if (! is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu()
    {
        add_menu_page(
            __('KH Folders', 'kh-folders'),
            __('KH Folders', 'kh-folders'),
            'manage_options',
            'kh-folders',
            [$this, 'renderRootPage'],
            'dashicons-category'
        );
    }

    public function renderRootPage()
    {
        $currentUser = get_current_user_id();
        $folders     = FolderService::getFolders(['user_id' => $currentUser]);
        $tree        = TreeRenderer::render(['user_id' => $currentUser]);
        $canShare    = Permissions::canManageShared($currentUser);

        echo '<div class="wrap"><h1>' . esc_html__('KH Folders', 'kh-folders') . '</h1>';
        $this->renderNotices();
        echo '<p>' . esc_html__('Manage your content folders below.', 'kh-folders') . '</p>';
        echo '<div class="kh-folders-layout">';
        echo '<aside class="kh-folders-sidebar">';
        echo '<h2>' . esc_html__('Folder Tree', 'kh-folders') . '</h2>';
        echo $tree;
        echo '</aside>';
        echo '<section class="kh-folders-content">';
        echo '<div id="kh-folders-notices" class="kh-folders-admin-notice" style="display:none;"></div>';
        echo '<div class="kh-folders-actions">';
        echo '<button class="button button-primary" data-kh-folders-create data-can-share="' . ($canShare ? '1' : '0') . '">' . esc_html__('Create Folder', 'kh-folders') . '</button>';
        echo '<button class="button" data-kh-folders-bulk-delete disabled>' . esc_html__('Delete Selected', 'kh-folders') . '</button>';
        echo '</div>';
        echo '<table class="kh-folders-table widefat striped"><thead><tr>';
        echo '<th class="column-handle" scope="col"></th>';
        echo '<th>' . esc_html__('Name', 'kh-folders') . '</th>';
        echo '<th>' . esc_html__('Color', 'kh-folders') . '</th>';
        echo '<th>' . esc_html__('Order', 'kh-folders') . '</th>';
        echo '<th>' . esc_html__('Actions', 'kh-folders') . '</th>';
        echo '<th class="column-select"><input type="checkbox" id="kh-folders-select-all" /></th>';
        echo '</tr></thead><tbody id="kh-folders-list">';

        if (empty($folders)) {
            echo '<tr class="no-items"><td colspan="6">' . esc_html__('No folders yet.', 'kh-folders') . '</td></tr>';
        } else {
            foreach ($folders as $folder) {
                echo $this->renderRow($folder);
            }
        }

        echo '</tbody></table>';
        $this->renderImportExport();
        echo '</section>';
        echo '</div>';
        echo '</div>';
    }

    private function renderRow($folder)
    {
        $color = esc_attr($folder['color']);
        $order = esc_attr($folder['order']);
        $termId = (int) $folder['term_id'];

        $html  = '<tr data-kh-folder-row data-term-id="' . $termId . '">';
        $html .= '<td class="column-handle"><span class="kh-folder-drag dashicons dashicons-move" title="' . esc_attr__('Drag to reorder', 'kh-folders') . '"></span></td>';
        $label = esc_html($folder['name']);
        if (! $folder['shared']) {
            $label .= ' <span class="kh-folder-badge">' . esc_html__('Personal', 'kh-folders') . '</span>';
        }

        $html .= '<td>' . $label . '</td>';
        $html .= '<td><input type="color" value="' . $color . '" data-kh-folder-color="' . $termId . '"/></td>';
        $html .= '<td><input type="number" class="small-text" value="' . $order . '" data-kh-folder-order="' . $termId . '"/></td>';
        $html .= '<td><button class="button button-link-delete" data-kh-folder-delete="' . $termId . '">' . esc_html__('Delete', 'kh-folders') . '</button></td>';
        $html .= '<td class="column-select"><input type="checkbox" data-kh-folder-select="' . $termId . '"/></td>';
        $html .= '</tr>';

        return $html;
    }

    private function renderImportExport()
    {
        $exportUrl = admin_url('admin-post.php');
        $nonceExport = wp_create_nonce('kh_folders_export');
        $nonceImport = wp_create_nonce('kh_folders_import');

        echo '<div class="kh-folders-import-export">';
        echo '<h2>' . esc_html__('Import / Export', 'kh-folders') . '</h2>';
        echo '<div class="kh-folders-export">';
        echo '<form method="post" action="' . esc_url($exportUrl) . '">';
        echo '<input type="hidden" name="action" value="kh_folders_export" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr($nonceExport) . '" />';
        echo '<button class="button">' . esc_html__('Export Folders', 'kh-folders') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="kh-folders-import">';
        echo '<form method="post" action="' . esc_url($exportUrl) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="kh_folders_import" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr($nonceImport) . '" />';
        echo '<label>' . esc_html__('JSON File', 'kh-folders') . '</label>';
        echo '<input type="file" name="kh_folders_file" accept="application/json" required />';
        echo '<label><input type="checkbox" name="shared" value="1" checked /> ' . esc_html__('Import as shared folders', 'kh-folders') . '</label>';
        echo '<button class="button button-primary">' . esc_html__('Import Folders', 'kh-folders') . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    private function renderNotices()
    {
        if (empty($_GET['kh_folders_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $type    = sanitize_text_field(wp_unslash($_GET['kh_folders_notice'])); // phpcs:ignore WordPress.Security.NonceVerification
        $message = '';
        if ('import_success' === $type) {
            $message = __('Folders imported successfully.', 'kh-folders');
        } elseif ('import_error' === $type) {
            $message = __('Import failed. Please check the JSON file.', 'kh-folders');
        }

        if ($message) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }
    }
}

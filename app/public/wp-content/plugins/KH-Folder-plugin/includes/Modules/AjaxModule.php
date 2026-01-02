<?php
namespace KHFolders\Modules;

use KHFolders\Services\FolderService;
use WP_Error;

class AjaxModule implements ModuleInterface
{
    public function register()
    {
        add_action('wp_ajax_kh_folders_create', [$this, 'handleCreateFolder']);
        add_action('wp_ajax_kh_folders_delete', [$this, 'handleDeleteFolder']);
        add_action('wp_ajax_kh_folders_assign', [$this, 'handleAssignFolder']);
        add_action('wp_ajax_kh_folders_update_meta', [$this, 'handleUpdateMeta']);
        add_action('wp_ajax_kh_folders_reorder', [$this, 'handleReorderFolders']);
        add_action('wp_ajax_kh_folders_bulk_delete', [$this, 'handleBulkDelete']);
        add_action('wp_ajax_kh_folders_update_parent', [$this, 'handleUpdateParent']);
    }

    private function authorize()
    {
        if (! current_user_can('upload_files')) {
            return new WP_Error('kh_folders_forbidden', __('You are not allowed to manage folders.', 'kh-folders'), 403);
        }

        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kh_folders_actions')) {
            return new WP_Error('kh_folders_invalid_nonce', __('Invalid request token.', 'kh-folders'), 403);
        }

        return true;
    }

    private function sendError($message, $status = 400, $code = '')
    {
        wp_send_json_error(
            [
                'message' => $message,
                'code'    => $code,
            ],
            $status
        );
    }

    /**
     * Lightweight per-user rate limiting to avoid heavy bulk abuse.
     */
    private function rateLimit($key, $limit = 20, $window = 60)
    {
        $user_id = get_current_user_id() ?: 0;
        $transient = 'kh_folders_rate_' . $key . '_' . $user_id;
        $data = get_transient($transient);
        $now = time();

        if (empty($data) || ! isset($data['start']) || ($now - $data['start']) > $window) {
            set_transient($transient, ['start' => $now, 'count' => 1], $window);
            return true;
        }

        if ($data['count'] >= $limit) {
            return false;
        }

        $data['count']++;
        set_transient($transient, $data, $window);
        return true;
    }

    public function handleCreateFolder()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if ($name === '') {
            $this->sendError(__('Folder name is required.', 'kh-folders'));
        }

        $parent = isset($_POST['parent']) ? absint($_POST['parent']) : 0;
        $shared = isset($_POST['shared']) ? (bool) $_POST['shared'] : true;

        $result = FolderService::createFolder($name, $parent, [
            'shared' => $shared,
            'owner'  => get_current_user_id(),
        ]);
        if (is_wp_error($result)) {
            $status = $result->get_error_data() && is_numeric($result->get_error_data()) ? (int) $result->get_error_data() : 400;
            $this->sendError($result->get_error_message(), $status, $result->get_error_code());
        }

        wp_send_json_success(FolderService::formatFolderData($result));
    }

    public function handleDeleteFolder()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        $termId = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        if (! $termId) {
            $this->sendError(__('Folder ID is required.', 'kh-folders'));
        }

        $result = FolderService::deleteFolder($termId);
        if (is_wp_error($result)) {
            $status = $result->get_error_data() && is_numeric($result->get_error_data()) ? (int) $result->get_error_data() : 400;
            $this->sendError($result->get_error_message(), $status, $result->get_error_code());
        }

        wp_send_json_success(['term_id' => $termId]);
    }

    public function handleAssignFolder()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        $termId = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $objectId = isset($_POST['object_id']) ? absint($_POST['object_id']) : 0;

        if (! $termId || ! $objectId) {
            $this->sendError(__('Folder and object IDs are required.', 'kh-folders'));
        }

        $result = FolderService::assignToObject($objectId, $termId);
        if (is_wp_error($result)) {
            $status = $result->get_error_data() && is_numeric($result->get_error_data()) ? (int) $result->get_error_data() : 400;
            $this->sendError($result->get_error_message(), $status, $result->get_error_code());
        }

        wp_send_json_success([
            'object_id' => $objectId,
            'term_id'   => $termId,
        ]);
    }

    public function handleUpdateMeta()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        $termId = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        if (! $termId) {
            $this->sendError(__('Folder ID is required.', 'kh-folders'));
        }

        $meta = [];
        if (isset($_POST['color'])) {
            $meta['color'] = sanitize_text_field(wp_unslash($_POST['color']));
        }
        if (isset($_POST['order'])) {
            $meta['order'] = absint($_POST['order']);
        }

        $term = FolderService::updateMeta($termId, $meta);
        if (is_wp_error($term)) {
            $status = $term->get_error_data() && is_numeric($term->get_error_data()) ? (int) $term->get_error_data() : 400;
            $this->sendError($term->get_error_message(), $status, $term->get_error_code());
        }

        FolderService::bootstrapMeta($termId);

        wp_send_json_success(FolderService::formatFolderData($term));
    }

    public function handleReorderFolders()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        if (! $this->rateLimit('reorder')) {
            $this->sendError(__('Too many reorder requests. Please wait a moment.', 'kh-folders'), 429, 'rate_limited');
        }

        if (! isset($_POST['order'])) {
            $this->sendError(__('Folder order payload missing.', 'kh-folders'));
        }

        $order = $_POST['order'];
        if (! is_array($order)) {
            $order = explode(',', sanitize_text_field(wp_unslash($order)));
        }
        $order = array_filter(array_map('absint', $order));
        if (empty($order)) {
            $this->sendError(__('Folder order payload missing.', 'kh-folders'));
        }
        if (count($order) > 300) {
            $this->sendError(__('Reordering too many items at once. Please batch into smaller changes.', 'kh-folders'), 413, 'payload_too_large');
        }

        $folders = FolderService::reorderFolders($order);

        wp_send_json_success([
            'folders' => $folders,
        ]);
    }

    public function handleBulkDelete()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        if (empty($_POST['term_ids'])) {
            $this->sendError(__('No folders selected.', 'kh-folders'));
        }

        if (! $this->rateLimit('bulk_delete')) {
            $this->sendError(__('Too many bulk actions. Please wait a moment.', 'kh-folders'), 429, 'rate_limited');
        }

        $ids = array_map('absint', (array) $_POST['term_ids']);
        $ids = array_filter($ids);

        if (empty($ids)) {
            $this->sendError(__('No folders selected.', 'kh-folders'));
        }
        if (count($ids) > 300) {
            $this->sendError(__('Bulk delete limited to 300 folders at a time. Please try smaller batches.', 'kh-folders'), 413, 'payload_too_large');
        }

        $deleted = FolderService::deleteFolders($ids);

        wp_send_json_success([
            'deleted' => $deleted,
        ]);
    }

    public function handleUpdateParent()
    {
        $authorized = $this->authorize();
        if (is_wp_error($authorized)) {
            $this->sendError(
                $authorized->get_error_message(),
                (int) $authorized->get_error_data() ?: 400,
                $authorized->get_error_code()
            );
        }

        $termId   = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $parentId = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;

        if (! $termId) {
            $this->sendError(__('Folder ID is required.', 'kh-folders'));
        }

        $term = FolderService::updateParent($termId, $parentId);
        if (is_wp_error($term)) {
            $status = $term->get_error_data() && is_numeric($term->get_error_data()) ? (int) $term->get_error_data() : 400;
            $this->sendError($term->get_error_message(), $status, $term->get_error_code());
        }

        if (isset($_POST['siblings'])) {
            $siblings = is_array($_POST['siblings']) ? $_POST['siblings'] : explode(',', sanitize_text_field(wp_unslash($_POST['siblings'])));
            $siblings = array_filter(array_map('absint', $siblings));
            if (!empty($siblings)) {
                FolderService::reorderFolders($siblings);
            }
        }

        wp_send_json_success(FolderService::formatFolderData($term));
    }
}

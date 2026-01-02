<?php
namespace KHFolders\Modules;

use KHFolders\Services\FolderService;
use KHFolders\UI\TreeRenderer;

class ListTableModule implements ModuleInterface
{
    /** @var array|null */
    private $assignableFolders = null;
    /** @var bool */
    private $treeRendered = false;

    public function register()
    {
        add_action('restrict_manage_posts', [$this, 'renderFilter']);
        add_action('pre_get_posts', [$this, 'applyFilter']);
        add_filter('ajax_query_attachments_args', [$this, 'filterAttachments']);

        $postTypes = $this->getSupportedPostTypes();
        foreach ($postTypes as $postType) {
            if ($postType === 'attachment') {
                continue;
            }
            add_filter("manage_{$postType}_posts_columns", [$this, 'addFolderColumn']);
            add_action("manage_{$postType}_posts_custom_column", [$this, 'renderFolderColumn'], 10, 2);
        }

        add_filter('manage_media_columns', [$this, 'addFolderColumn']);
        add_action('manage_media_custom_column', [$this, 'renderFolderColumn'], 10, 2);
        add_action('admin_head', [$this, 'printStyles']);
    }

    public function renderFilter($postType)
    {
        $supported = $this->getSupportedPostTypes();
        if (! in_array($postType, (array) $supported, true)) {
            return;
        }

        $this->renderTreePanel();

        $visibleFolders = FolderService::getFolders(['user_id' => get_current_user_id()]);
        if (empty($visibleFolders)) {
            return;
        }

        $include = wp_list_pluck($visibleFolders, 'term_id');

        $selected = isset($_GET['kh_folder']) ? absint($_GET['kh_folder']) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        wp_dropdown_categories([
            'taxonomy'        => TaxonomyModule::TAXONOMY,
            'show_option_all' => __('All Folders', 'kh-folders'),
            'name'            => 'kh_folder',
            'orderby'         => 'name',
            'selected'        => $selected,
            'hierarchical'    => true,
            'depth'           => 3,
            'show_count'      => true,
            'hide_empty'      => false,
            'include'         => $include,
        ]);
    }

    public function applyFilter($query)
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (! isset($_GET['kh_folder']) || (int) $_GET['kh_folder'] === 0) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $postType = $query->get('post_type');
        if (! $postType) {
            $postType = 'post';
        }
        $supported = apply_filters('kh_folders_supported_post_types', ['attachment', 'page', 'post']);
        if (! in_array($postType, (array) $supported, true)) {
            return;
        }

        $termId = absint($_GET['kh_folder']); // phpcs:ignore WordPress.Security.NonceVerification
        if (! $termId) {
            return;
        }

        $taxQuery = (array) $query->get('tax_query');
        $taxQuery[] = [
            'taxonomy' => TaxonomyModule::TAXONOMY,
            'field'    => 'term_id',
            'terms'    => $termId,
        ];
        $query->set('tax_query', $taxQuery);
    }

    public function filterAttachments($args)
    {
        if (! isset($_REQUEST['kh_folder']) || ! absint($_REQUEST['kh_folder'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return $args;
        }

        $args['tax_query'] = isset($args['tax_query']) ? (array) $args['tax_query'] : [];
        $args['tax_query'][] = [
            'taxonomy' => TaxonomyModule::TAXONOMY,
            'field'    => 'term_id',
            'terms'    => absint($_REQUEST['kh_folder']), // phpcs:ignore WordPress.Security.NonceVerification
        ];

        return $args;
    }

    public function addFolderColumn($columns)
    {
        $columns['kh_folder'] = __('Folders', 'kh-folders');
        return $columns;
    }

    public function renderFolderColumn($column, $postId)
    {
        if ('kh_folder' !== $column) {
            return;
        }

        $terms = wp_get_object_terms($postId, TaxonomyModule::TAXONOMY);
        $chips = [];
        if (! is_wp_error($terms) && ! empty($terms)) {
            foreach ($terms as $term) {
                $color = get_term_meta($term->term_id, FolderService::META_COLOR, true);
                $color = $color ? esc_attr($color) : '#ccd0d4';
                $chips[] = '<span class="kh-folder-chip" style="border-color:' . $color . ';background:' . $color . '1a;">' . esc_html($term->name) . '</span>';
            }
        }

        echo '<div class="kh-folder-chips" data-kh-folder-chips="' . (int) $postId . '">';
        echo $chips ? implode(' ', $chips) : '&mdash;';
        echo '</div>';

        $folders = $this->getAssignableFolders();
        if (empty($folders)) {
            return;
        }

        echo '<div class="kh-folder-assign-control" data-kh-folder-control="' . (int) $postId . '">';
        echo '<select data-kh-folder-select>';
        echo '</select>';
        echo '<button type="button" class="button button-small" data-kh-folder-assign="' . (int) $postId . '">' . esc_html__('Move', 'kh-folders') . '</button>';
        echo '<span class="kh-folder-status" data-kh-folder-status></span>';
        echo '</div>';
    }

    public function printStyles()
    {
        echo '<style>
            .column-kh_folder .kh-folder-chip{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;margin:1px 2px;border:1px solid #ccd0d4;background:#f6f7f7;color:#1d2327;}
            .kh-folder-assign-control{margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
            .kh-folder-assign-control select{min-width:150px;}
            .kh-folder-assign-control .kh-folder-status{font-size:11px;color:#2271b1;}
            .kh-folder-assign-control .kh-folder-status.is-error{color:#b32d2e;}
            .kh-folder-assign-control .kh-folder-status.is-success{color:#2271b1;}
            .kh-folder-tree-panel{position:fixed;left:178px;top:96px;z-index:1000;display:flex;flex-direction:column;gap:8px;align-items:flex-start;}
            body.folded .kh-folder-tree-panel{left:72px;}
            .kh-folder-tree-toggle{width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;}
            .kh-folder-tree-toggle .dashicons{margin:0;}
            .kh-folder-tree-panel .kh-folder-tree-shell{border:1px solid #dcdcde;background:#fff;padding:8px;max-width:260px;max-height:320px;overflow:auto;border-radius:3px;display:none;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
            .kh-folder-tree-panel.is-open .kh-folder-tree-shell{display:block;}
            .kh-folder-tree-panel .kh-folder-tree-list{margin:0;padding-left:14px;}
            .kh-folder-tree-panel .kh-folder-tree-item{display:flex;align-items:center;gap:6px;}
            .kh-folder-tree-panel .kh-folder-tree-label{cursor:pointer;}
            .kh-folder-tree-panel .kh-folder-tree-label:hover{text-decoration:underline;}
        </style>';
    }

    private function getSupportedPostTypes()
    {
        $types = apply_filters('kh_folders_supported_post_types', ['attachment', 'page', 'post']);
        return array_unique(array_filter((array) $types));
    }

    private function renderTreePanel()
    {
        if ($this->treeRendered) {
            return;
        }

        $tree = TreeRenderer::render(['user_id' => get_current_user_id()]);
        echo '<div class="kh-folder-tree-panel" data-kh-folder-tree-panel>';
        echo '<button type="button" class="button kh-folder-tree-toggle" data-kh-folder-tree-toggle data-label-show="' . esc_attr__('Show folder tree', 'kh-folders') . '" data-label-hide="' . esc_attr__('Hide folder tree', 'kh-folders') . '">';
        echo '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
        echo '<span class="screen-reader-text">' . esc_html__('Toggle folder tree', 'kh-folders') . '</span>';
        echo '</button>';
        echo '<div class="kh-folder-tree-shell" data-kh-folder-tree-shell>';
        echo '<p class="description" style="margin:0 0 6px;">' . esc_html__('Click a folder to filter this list.', 'kh-folders') . '</p>';
        echo $tree;
        echo '</div>';
        echo '</div>';

        $this->treeRendered = true;
    }

    private function getAssignableFolders()
    {
        if ($this->assignableFolders === null) {
            $this->assignableFolders = FolderService::getFolders(['user_id' => get_current_user_id()]);
        }

        return $this->assignableFolders;
    }
}

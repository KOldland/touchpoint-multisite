<?php
namespace KHFolders\Modules;

use KHFolders\Services\FolderService;

class TaxonomyModule implements ModuleInterface
{
    const TAXONOMY = 'kh_folder';

    public function register()
    {
        add_action('init', [$this, 'registerTaxonomy']);
        add_action('created_' . self::TAXONOMY, [$this, 'handleTermMeta'], 10, 2);
        add_action('edited_' . self::TAXONOMY, [$this, 'handleTermMeta'], 10, 2);
    }

    public function registerTaxonomy()
    {
        $objectTypes = apply_filters('kh_folders_supported_post_types', ['attachment', 'page', 'post']);
        $objectTypes = array_unique(array_filter((array) $objectTypes));

        $labels = [
            'name'          => _x('KH Folders', 'taxonomy general name', 'kh-folders'),
            'singular_name' => _x('KH Folder', 'taxonomy singular name', 'kh-folders'),
            'search_items'  => __('Search Folders', 'kh-folders'),
            'all_items'     => __('All Folders', 'kh-folders'),
            'edit_item'     => __('Edit Folder', 'kh-folders'),
            'update_item'   => __('Update Folder', 'kh-folders'),
            'add_new_item'  => __('Add New Folder', 'kh-folders'),
            'new_item_name' => __('New Folder Name', 'kh-folders'),
            'menu_name'     => __('Folders', 'kh-folders'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => true,
            'show_ui'           => false,
            'show_admin_column' => false,
            'query_var'         => true,
            'show_in_rest'      => false,
            'rewrite'           => false,
        ];

        register_taxonomy(self::TAXONOMY, $objectTypes, apply_filters('kh_folders_taxonomy_args', $args));
    }

    public function handleTermMeta($termId)
    {
        FolderService::bootstrapMeta($termId);
    }
}

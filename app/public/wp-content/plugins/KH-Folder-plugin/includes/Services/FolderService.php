<?php
namespace KHFolders\Services;

use KHFolders\Core\Permissions;
use KHFolders\Modules\TaxonomyModule;
use WP_Error;

class FolderService
{
    const META_COLOR = 'kh_folder_color';
    const META_ORDER = 'kh_folder_order';
    const META_OWNER = 'kh_folder_owner';

    /**
     * Get list of folders with meta values.
     *
     * @param array $args Optional arguments controlling visibility.
     *
     * @return array
     */
    public static function getFolders($args = [])
    {
        $defaults = [
            'user_id'          => get_current_user_id(),
            'include_personal' => true,
            'include_shared'   => true,
        ];
        $args = wp_parse_args($args, $defaults);

        $terms = get_terms([
            'taxonomy'   => TaxonomyModule::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'meta_value_num',
            'meta_key'   => self::META_ORDER,
            'order'      => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $folders = [];
        foreach ($terms as $term) {
            if (! self::canSeeFolder($term->term_id, $args)) {
                continue;
            }
            $folders[] = self::formatFolderData($term);
        }

        return $folders;
    }

    /**
     * Format a WP_Term as array with meta.
     */
    public static function formatFolderData($term)
    {
        $termId = (int) $term->term_id;
        $owner  = (int) get_term_meta($termId, self::META_OWNER, true);

        return [
            'term_id' => $termId,
            'name'    => $term->name,
            'slug'    => $term->slug,
            'parent'  => (int) $term->parent,
            'color'   => get_term_meta($termId, self::META_COLOR, true) ?: '#2271b1',
            'order'   => (int) (get_term_meta($termId, self::META_ORDER, true) ?: $termId),
            'owner'   => $owner,
            'shared'  => $owner === 0,
        ];
    }

    /**
     * Ensure default meta exists for a term.
     */
    public static function bootstrapMeta($termId)
    {
        if (! get_term_meta($termId, self::META_COLOR, true)) {
            update_term_meta($termId, self::META_COLOR, '#2271b1');
        }

        if (! get_term_meta($termId, self::META_ORDER, true)) {
            $order = (int) get_option('kh_folders_order_counter', 0) + 1;
            update_option('kh_folders_order_counter', $order);
            update_term_meta($termId, self::META_ORDER, $order);
        }

        if ('' === get_term_meta($termId, self::META_OWNER, true)) {
            update_term_meta($termId, self::META_OWNER, 0);
        }
    }

    public static function createFolder($name, $parent = 0, $args = [])
    {
        $defaults = [
            'shared'  => true,
            'owner'   => get_current_user_id(),
        ];
        $args  = wp_parse_args($args, $defaults);

        if (! Permissions::canManageShared($args['owner'])) {
            $args['shared'] = false;
        }

        $result = wp_insert_term($name, TaxonomyModule::TAXONOMY, ['parent' => $parent]);
        if (is_wp_error($result)) {
            return $result;
        }

        $termId = (int) $result['term_id'];
        self::bootstrapMeta($termId);
        update_term_meta($termId, self::META_OWNER, $args['shared'] ? 0 : (int) $args['owner']);

        return get_term($termId, TaxonomyModule::TAXONOMY);
    }

    public static function deleteFolder($termId)
    {
        return wp_delete_term($termId, TaxonomyModule::TAXONOMY);
    }

    public static function assignToObject($objectId, $termId)
    {
        return wp_set_object_terms($objectId, [$termId], TaxonomyModule::TAXONOMY, false);
    }

    public static function updateMeta($termId, array $meta)
    {
        if (isset($meta['color'])) {
            update_term_meta($termId, self::META_COLOR, sanitize_hex_color($meta['color']) ?: '#2271b1');
        }

        if (isset($meta['order'])) {
            update_term_meta($termId, self::META_ORDER, absint($meta['order']));
        }

        return get_term($termId, TaxonomyModule::TAXONOMY);
    }

    public static function updateParent($termId, $parentId)
    {
        $termId   = absint($termId);
        $parentId = absint($parentId);

        if ($termId === $parentId) {
            return new WP_Error('kh_folder_invalid_parent', __('Cannot set a folder as its own parent.', 'kh-folders'));
        }

        $result = wp_update_term($termId, TaxonomyModule::TAXONOMY, ['parent' => $parentId]);
        if (is_wp_error($result)) {
            return $result;
        }

        return get_term($termId, TaxonomyModule::TAXONOMY);
    }

    /**
     * Delete multiple folders.
     */
    public static function deleteFolders(array $termIds)
    {
        $deleted = [];
        foreach ($termIds as $termId) {
            $termId = absint($termId);
            if (! $termId) {
                continue;
            }

            $result = wp_delete_term($termId, TaxonomyModule::TAXONOMY);
            if (! is_wp_error($result)) {
                $deleted[] = $termId;
            }
        }

        return $deleted;
    }

    /**
     * Reorder folders based on provided term IDs.
     */
    public static function reorderFolders(array $termIds)
    {
        $position = 1;
        foreach ($termIds as $termId) {
            $termId = absint($termId);
            if (! $termId) {
                continue;
            }

            update_term_meta($termId, self::META_ORDER, $position);
            $position++;
        }

        return self::getFolders();
    }

    public static function exportFolders($args = [])
    {
        $folders = self::getFolders($args);
        return json_encode($folders, JSON_PRETTY_PRINT);
    }

    public static function importFolders(array $data, $args = [])
    {
        $defaults = [
            'shared' => true,
            'owner'  => get_current_user_id(),
        ];
        $args = wp_parse_args($args, $defaults);

        $map = [];
        foreach ($data as $folder) {
            $name   = sanitize_text_field($folder['name']);
            $parent = isset($folder['parent']) ? (int) $folder['parent'] : 0;

            if ($parent && isset($map[$parent])) {
                $parent = $map[$parent];
            } else {
                $parent = 0;
            }

            $created = self::createFolder($name, $parent, $args);
            if (is_wp_error($created)) {
                continue;
            }
            $termId = (int) $created->term_id;
            $map[(int) $folder['term_id']] = $termId;

            if (isset($folder['color'])) {
                update_term_meta($termId, self::META_COLOR, $folder['color']);
            }
        }
    }

    private static function canSeeFolder($termId, $args)
    {
        $owner = (int) get_term_meta($termId, self::META_OWNER, true);
        $owner = $owner ?: 0;

        if ($owner === 0) {
            return $args['include_shared'];
        }

        if ((int) $args['user_id'] === $owner) {
            return $args['include_personal'];
        }

        return Permissions::canManageShared($args['user_id']);
    }
}

<?php
namespace KHFolders\UI;

use KHFolders\Services\FolderService;

class TreeRenderer
{
    public static function render($args = [])
    {
        $folders = FolderService::getFolders($args);
        $tree    = self::buildTree($folders);

        ob_start();
        echo '<div id="kh-folder-tree" class="kh-folder-tree">';
        echo self::renderTree($tree);
        echo '</div>';

        return ob_get_clean();
    }

    private static function buildTree(array $folders)
    {
        $tree     = [];
        $byParent = [];

        foreach ($folders as $folder) {
            $parent = (int) $folder['parent'];
            if (! isset($byParent[$parent])) {
                $byParent[$parent] = [];
            }
            $byParent[$parent][] = $folder;
        }

        $tree = self::walkTree($byParent, 0);
        return $tree;
    }

    private static function walkTree(array $byParent, $parentId)
    {
        if (! isset($byParent[$parentId])) {
            return [];
        }

        $branch = [];
        foreach ($byParent[$parentId] as $folder) {
            $children = self::walkTree($byParent, $folder['term_id']);
            if (! empty($children)) {
                $folder['children'] = $children;
            }
            $branch[] = $folder;
        }

        return $branch;
    }

    private static function renderTree(array $tree)
    {
        if (empty($tree)) {
            return '<p class="description">' . esc_html__('No folders yet.', 'kh-folders') . '</p>';
        }

        $html = '<ul class="kh-folder-tree-list" data-kh-folder-tree>'; // root list
        foreach ($tree as $node) {
            $html .= self::renderNode($node);
        }
        $html .= '</ul>';

        return $html;
    }

    private static function renderNode(array $node)
    {
        $termId       = (int) $node['term_id'];
        $hasChildren  = ! empty($node['children']);
        $owner        = ! empty($node['owner']) ? (int) $node['owner'] : 0;
        $shared       = empty($owner);
        $childrenId   = 'kh-folder-children-' . $termId;

        $html  = '<li class="kh-folder-tree-node" data-term-id="' . $termId . '" data-owner="' . $owner . '" data-shared="' . ($shared ? '1' : '0') . '">';
        $html .= '<div class="kh-folder-tree-item">';
        if ($hasChildren) {
            $html .= '<button type="button" class="kh-folder-toggle" aria-expanded="true" aria-controls="' . $childrenId . '"><span class="dashicons dashicons-arrow-down"></span></button>';
        } else {
            $html .= '<span class="kh-folder-toggle is-empty" aria-controls="' . $childrenId . '"></span>';
        }
        $html .= '<span class="kh-folder-tree-label" data-kh-folder-select-node="' . $termId . '">' . esc_html($node['name']) . '</span>';
        $html .= '</div>';

        $html .= '<ul id="' . $childrenId . '" class="kh-folder-tree-list kh-folder-tree-children">';
        if ($hasChildren) {
            foreach ($node['children'] as $child) {
                $html .= self::renderNode($child);
            }
        }
        $html .= '</ul>';

        $html .= '</li>';

        return $html;
    }
}

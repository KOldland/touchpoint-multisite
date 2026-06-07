<?php
namespace TouchpointCore\Taxonomies;

class ContentTypeTaxonomy {
    public function register() {
        add_action('init', [$this, 'create_taxonomy']);
    }

    public function create_taxonomy() {
        register_taxonomy(
            'content_type',
            'post',
            array(
                'label' => __('Content Type', 'touchpoint-core'),
                'rewrite' => array('slug' => 'content-type'),
                'hierarchical' => false,
                'show_admin_column' => true,
            )
        );
    }
}

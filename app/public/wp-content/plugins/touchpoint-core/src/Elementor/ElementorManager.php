<?php
namespace TouchpointCore\Elementor;

use TouchpointCore\Elementor\Widgets\StyledExcerptWidget;

class ElementorManager {
    public function register() {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    public function register_widgets($widgets_manager) {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }
        $widgets_manager->register(new StyledExcerptWidget());
    }
}

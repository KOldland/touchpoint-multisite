<?php
namespace TouchpointCore;

use TouchpointCore\Taxonomies\ContentTypeTaxonomy;
use TouchpointCore\Integrations\AcfExtensions;
use TouchpointCore\Shortcodes\StyledExcerpt;
use TouchpointCore\Elementor\ElementorManager;

class Plugin {
    public function init() {
        (new ContentTypeTaxonomy())->register();
        (new AcfExtensions())->register();
        (new StyledExcerpt())->register();
        (new ElementorManager())->register();
    }
}

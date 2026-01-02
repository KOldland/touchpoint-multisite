<?php

namespace KHM\Preview;

use KHM\Preview\Admin\EditorMetaBox;
use KHM\Preview\Admin\PreviewAdminPage;
use KHM\Preview\Database\Repositories\PreviewHitRepository;
use KHM\Preview\Database\Repositories\PreviewLinkRepository;
use KHM\Preview\PublicPreviewHandler;
use KHM\Preview\REST\PreviewRestController;
use KHM\Preview\Services\PreviewAnalyticsService;
use KHM\Preview\Services\PreviewLinkService;
use KHM\Preview\Token\TokenGenerator;

class Plugin {
    /** @var PreviewLinkService */
    private $preview_service;
    /** @var PreviewRestController */
    private $rest_controller;
    /** @var EditorMetaBox|null */
    private $editor_meta_box;
    /** @var PreviewAdminPage|null */
    private $preview_admin_page;
    /** @var PreviewAnalyticsService */
    private $analytics_service;
    /** @var PublicPreviewHandler */
    private $public_handler;

    public function __construct() {
        $link_repository   = new PreviewLinkRepository();
        $token_generator   = new TokenGenerator();
        $this->preview_service   = new PreviewLinkService( $link_repository, $token_generator );
        $hit_repository          = new PreviewHitRepository();
        $this->analytics_service = new PreviewAnalyticsService( $hit_repository );
        $this->rest_controller   = new PreviewRestController( $this->preview_service, $this->analytics_service );

        $this->public_handler    = new PublicPreviewHandler( $link_repository, $this->analytics_service, $token_generator );
        if ( is_admin() ) {
            $this->editor_meta_box    = new EditorMetaBox( $this->preview_service, $this->analytics_service );
            $this->preview_admin_page = new PreviewAdminPage();
        }
    }

    public function boot(): void {
        $this->rest_controller->register();
        $this->public_handler->register();
        if ( $this->editor_meta_box ) {
            $this->editor_meta_box->register();
        }
        if ( $this->preview_admin_page ) {
            $this->preview_admin_page->register();
        }
    }
}

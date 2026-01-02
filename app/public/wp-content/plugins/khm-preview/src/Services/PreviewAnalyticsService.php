<?php

namespace KHM\Preview\Services;

use KHM\Preview\Database\Repositories\PreviewHitRepository;

class PreviewAnalyticsService {
    private $repository;

    public function __construct( PreviewHitRepository $repository ) {
        $this->repository = $repository;
    }

    public function log_hit( int $link_id, array $meta = [] ): int {
        return $this->repository->insert( [
            'link_id'   => $link_id,
            'ip'        => $meta['ip'] ?? null,
            'user_agent'=> $meta['user_agent'] ?? null,
            'meta'      => ! empty( $meta['extra'] ) ? wp_json_encode( $meta['extra'] ) : null,
        ] );
    }

    public function get_recent_hits( int $link_id, int $limit = 20 ): array {
        return $this->repository->get_by_link( $link_id, $limit );
    }
}

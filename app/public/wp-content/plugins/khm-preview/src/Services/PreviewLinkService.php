<?php

namespace KHM\Preview\Services;

use DateTimeImmutable;
use KHM\Preview\Database\Repositories\PreviewLinkRepository;
use KHM\Preview\Token\TokenGenerator;

class PreviewLinkService {
    private $repository;
    private $token_generator;

    public function __construct( PreviewLinkRepository $repository, TokenGenerator $token_generator ) {
        $this->repository     = $repository;
        $this->token_generator = $token_generator;
    }

    public function create_link( int $post_id, int $user_id, DateTimeImmutable $expires_at, array $meta = [] ): array {
        $token      = $this->token_generator->generate();
        $token_hash = $this->token_generator->hash_token( $token );

        $id = $this->repository->insert( [
            'post_id'    => $post_id,
            'token'      => $token,
            'token_hash' => $token_hash,
            'expires_at' => $expires_at->format( 'Y-m-d H:i:s' ),
            'status'     => 'active',
            'created_by' => $user_id,
            'meta'       => wp_json_encode( $meta ),
        ] );

        return [
            'id'          => $id,
            'token'       => $token,
            'token_hash'  => $token_hash,
        ];
    }

    public function get_active_link( int $post_id ): ?array {
        return $this->repository->find_active_by_post( $post_id );
    }

    public function revoke_link( int $id ): bool {
        return $this->repository->update_status( $id, 'revoked' );
    }

    public function extend_link( int $id, DateTimeImmutable $expires_at ): bool {
        return $this->repository->update_expiration( $id, $expires_at->format( 'Y-m-d H:i:s' ) );
    }

    public function get_link( int $id ): ?array {
        return $this->repository->find( $id );
    }
}

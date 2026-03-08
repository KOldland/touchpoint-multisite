<?php

namespace KHM\Services;

class RateLimitService {
    private const DEFAULT_REQUEST_WINDOW_SECONDS = 60;
    private const DEFAULT_REQUEST_MAX = 100;
    private const DEFAULT_BAD_SIGNATURE_THRESHOLD = 5;
    private const DEFAULT_BAD_SIGNATURE_BLOCK_SECONDS = 300;

    /** @var callable|null */
    private $telemetryEmitter;

    public function __construct( ?callable $telemetryEmitter = null ) {
        $this->telemetryEmitter = $telemetryEmitter;
    }

    /**
     * @return array<string,mixed>
     */
    public function consumeWebhookRequest( string $ip ): array {
        $ip = $this->normalizeIp( $ip );
        $window = $this->requestWindowSeconds();
        $max = $this->requestMaxCount();

        if ( $this->isBlocked( $ip ) ) {
            $count = (int) $this->readCounter( $this->requestCountKey( $ip ) );
            $this->emit( 'webhook.rate_limit.exceeded', [
                'ip' => $ip,
                'count' => $count,
                'window' => $window,
                'reason' => 'invalid_signature_block',
            ] );
            return [
                'allowed' => false,
                'ip' => $ip,
                'count' => $count,
                'window' => $window,
                'reason' => 'invalid_signature_block',
            ];
        }

        $count = $this->incrementCounter( $this->requestCountKey( $ip ), $window );
        if ( $count > $max ) {
            $this->emit( 'webhook.rate_limit.exceeded', [
                'ip' => $ip,
                'count' => $count,
                'window' => $window,
                'reason' => 'request_threshold_exceeded',
            ] );
            return [
                'allowed' => false,
                'ip' => $ip,
                'count' => $count,
                'window' => $window,
                'reason' => 'request_threshold_exceeded',
            ];
        }

        return [
            'allowed' => true,
            'ip' => $ip,
            'count' => $count,
            'window' => $window,
            'reason' => null,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function recordInvalidSignature( string $ip, array $context = [] ): array {
        $ip = $this->normalizeIp( $ip );
        $window = $this->requestWindowSeconds();
        $threshold = $this->badSignatureThreshold();
        $blockTtl = $this->badSignatureBlockSeconds();
        $count = $this->incrementCounter( $this->badSignatureKey( $ip ), $window );
        $blocked = $count >= $threshold;

        if ( $blocked ) {
            set_transient( $this->blockedIpKey( $ip ), 1, $blockTtl );
        }

        $payload = array_merge( $context, [
            'ip' => $ip,
            'count' => $count,
            'window' => $window,
            'blocked' => $blocked,
            'block_ttl' => $blocked ? $blockTtl : 0,
        ] );
        $this->emit( 'webhook.invalid_signature', $payload );

        return $payload;
    }

    public function isBlocked( string $ip ): bool {
        return (bool) get_transient( $this->blockedIpKey( $this->normalizeIp( $ip ) ) );
    }

    private function requestWindowSeconds(): int {
        $env = getenv( 'KH_WEBHOOK_RATE_LIMIT_WINDOW_SEC' );
        $default = is_numeric( $env ) ? (int) $env : self::DEFAULT_REQUEST_WINDOW_SECONDS;
        return max( 5, (int) apply_filters( 'khm_membership_webhook_rate_limit_window', $default ) );
    }

    private function requestMaxCount(): int {
        $env = getenv( 'KH_WEBHOOK_RATE_LIMIT_COUNT' );
        $default = is_numeric( $env ) ? (int) $env : self::DEFAULT_REQUEST_MAX;
        return max( 1, (int) apply_filters( 'khm_membership_webhook_rate_limit_max_requests', $default ) );
    }

    private function badSignatureThreshold(): int {
        $env = getenv( 'KH_WEBHOOK_BADSIG_THRESHOLD' );
        $default = is_numeric( $env ) ? (int) $env : self::DEFAULT_BAD_SIGNATURE_THRESHOLD;
        return max( 1, (int) apply_filters( 'khm_membership_webhook_bad_signature_threshold', $default ) );
    }

    private function badSignatureBlockSeconds(): int {
        $env = getenv( 'KH_WEBHOOK_BADSIG_BLOCK_SEC' );
        $default = is_numeric( $env ) ? (int) $env : self::DEFAULT_BAD_SIGNATURE_BLOCK_SECONDS;
        return max( 5, (int) apply_filters( 'khm_membership_webhook_bad_signature_block_seconds', $default ) );
    }

    private function requestCountKey( string $ip ): string {
        return 'khm_wh_rl_' . md5( $ip . '|' . gmdate( 'YmdHi' ) );
    }

    private function badSignatureKey( string $ip ): string {
        return 'khm_wh_badsig_' . md5( $ip . '|' . gmdate( 'YmdHi' ) );
    }

    private function blockedIpKey( string $ip ): string {
        return 'khm_wh_block_' . md5( $ip );
    }

    private function normalizeIp( string $ip ): string {
        $ip = trim( sanitize_text_field( $ip ) );
        return $ip !== '' ? $ip : 'unknown';
    }

    private function readCounter( string $key ): int {
        return (int) get_transient( $key );
    }

    private function incrementCounter( string $key, int $ttl ): int {
        $count = $this->readCounter( $key ) + 1;
        set_transient( $key, $count, $ttl );
        return $count;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function emit( string $metric, array $context ): void {
        if ( is_callable( $this->telemetryEmitter ) ) {
            call_user_func( $this->telemetryEmitter, $metric, $context );
        }
    }
}

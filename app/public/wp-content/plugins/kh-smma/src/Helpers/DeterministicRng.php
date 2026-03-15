<?php
/**
 * DeterministicRng — seeded pseudo-random helpers for sandbox adapters.
 *
 * All methods are pure functions of their inputs: identical inputs always
 * produce identical outputs. No state, no WP dependency, no network.
 *
 * Seed algorithm: sha256("{manifest_id}|{op_id}|{idempotency_key}|{adapter}")
 *
 * @package KH_SMMA\Helpers
 * @see docs/paid/sandbox_adapter.md
 */

namespace KH_SMMA\Helpers;

class DeterministicRng {

    /**
     * Derive a deterministic hex seed for a given manifest operation.
     *
     * @param string $manifest_id   Manifest identifier.
     * @param string $op_id         Operation identifier within the manifest.
     * @param string $idempotency_key  UUID from manifest.meta.idempotency_key.
     * @param string $adapter       Adapter name (e.g. 'linkedin_sandbox').
     * @return string 64-char lowercase hex string (SHA-256 output).
     */
    public static function seed(
        string $manifest_id,
        string $op_id,
        string $idempotency_key,
        string $adapter
    ): string {
        return hash( 'sha256', "{$manifest_id}|{$op_id}|{$idempotency_key}|{$adapter}" );
    }

    /**
     * Map a seed to a deterministic float delta in [$min, $max].
     *
     * Uses the first 8 hex digits of the seed as a 32-bit integer,
     * normalised to [0, 1), then scaled to the requested range.
     *
     * @param string $seed 64-char hex seed from self::seed().
     * @param float  $min  Lower bound (inclusive). Default -0.03.
     * @param float  $max  Upper bound (inclusive). Default +0.03.
     * @return float
     */
    public static function delta( string $seed, float $min = -0.03, float $max = 0.03 ): float {
        $normalised = hexdec( substr( $seed, 0, 8 ) ) / 0xFFFFFFFF;
        return $min + ( $max - $min ) * $normalised;
    }

    /**
     * Derive a deterministic operation ID on the ad channel.
     *
     * Format: "{prefix}_{first_12_chars_of_seed}"
     * Example (linkedin): "li_op_4a9f2b1c3d8e"
     *
     * @param string $seed   64-char hex seed from self::seed().
     * @param string $prefix Adapter-specific prefix (e.g. 'li_op', 'g_op').
     * @return string
     */
    public static function operation_id( string $seed, string $prefix ): string {
        return $prefix . '_' . substr( $seed, 0, 12 );
    }
}

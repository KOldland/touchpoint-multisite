<?php
/**
 * Paid Adapters Configuration
 *
 * Controls sandbox vs production mode and deterministic variance range.
 * In CI/staging, adapter_mode defaults to 'sandbox'.
 * Override via KH_AD_ADAPTER_MODE env var or the kh_ad_adapter_mode WP option.
 *
 * @see docs/paid/sandbox_adapter.md
 */

return [

    /**
     * Adapter mode: 'sandbox' | 'production'
     *
     * - 'sandbox' (default): Use LinkedInSandboxAdapter / GoogleSandboxAdapter.
     *   Deterministic, offline, safe for CI and staging.
     * - 'production': Use real provider adapters (when available).
     *
     * Set KH_AD_ADAPTER_MODE=production in environment to override for production deploys.
     */
    'adapter_mode' => getenv( 'KH_AD_ADAPTER_MODE' ) ?: (
        function_exists( 'get_option' )
            ? get_option( 'kh_ad_adapter_mode', 'sandbox' )
            : 'sandbox'
    ),

    /**
     * Deterministic spend variance range applied to actual_spend in execute().
     * Delta is computed via DeterministicRng::delta(seed, variance_min, variance_max).
     * Repeated calls with the same seed always produce the same delta.
     */
    'variance_min' => -0.03,
    'variance_max' =>  0.03,

    /**
     * Prompt version tag embedded in adapter metadata for CIC traceability.
     */
    'prompt_version' => 'paid-03',

    /**
     * Reconciliation discrepancy alert threshold (percent).
     * Rows with |actual - estimated| / estimated > threshold are flagged as 'discrepancy'.
     */
    'discrepancy_threshold_percent' => 10.0,

    // ── PAID-05: FX + Settlement ─────────────────────────────────────────────

    /**
     * Static FX rates for sandbox settlement (no external API calls).
     * Pair key format: '{FROM}_{TO}' (upper-case ISO 4217).
     * Unknown pairs fall back to 1.0 (passthrough / same-currency).
     */
    'fx_rates' => [
        'AUD_USD' => 0.6453,
        'AUD_GBP' => 0.5142,
        'USD_AUD' => 1.5497,
        'USD_GBP' => 0.7967,
        'GBP_AUD' => 1.9447,
        'GBP_USD' => 1.2551,
    ],

    /**
     * Maximum reconciliation rows processed per settlement batch run.
     */
    'settlement_batch_size' => 500,

    /**
     * Default source currency for settlement grouping.
     */
    'settlement_currency_default' => 'AUD',

    /**
     * Rolling window (days) used by the scheduled settlement cron job.
     * Only reconciliations created within this window are eligible.
     */
    'settlement_window_days' => 30,

    // ── PAID-06: Delivery config ──────────────────────────────────────────────

    /**
     * Default accounting adapter slug: 'sftp' | 'accounting_api'
     * Override via KH_AD_DELIVERY_ADAPTER env var.
     */
    'delivery' => [
        'default_adapter'  => getenv( 'KH_AD_DELIVERY_ADAPTER' ) ?: 'sftp',

        /**
         * Maximum delivery attempts before a row moves to failed_permanent (DLQ).
         */
        'max_retries'      => 3,

        /**
         * Retry backoff intervals in seconds.
         * Index 0 = first retry, index 1 = second, etc.
         */
        'retry_backoff'    => [ 60, 300, 900 ],

        /**
         * SFTP connection settings.
         * Host, port, and user come from env vars; password from CredentialVault.
         */
        'sftp_host'        => getenv( 'KH_SFTP_HOST' ) ?: '',
        'sftp_port'        => (int) ( getenv( 'KH_SFTP_PORT' ) ?: 22 ),
        'sftp_user'        => getenv( 'KH_SFTP_USER' ) ?: '',
        'sftp_remote_path' => getenv( 'KH_SFTP_REMOTE_PATH' ) ?: '/settlements/',

        /**
         * Accounting API endpoint URL (no credentials in config).
         */
        'api_endpoint'     => getenv( 'KH_ACCOUNTING_API_URL' ) ?: '',
    ],

    /**
     * Prompt version tag for PAID-06 delivery adapter CIC traceability.
     */
    'prompt_version_paid06' => 'paid-06',

    // ── PAID-08: Reconciliation run config ────────────────────────────────────

    /**
     * Variance tolerance configuration for run-level reconciliation.
     *
     * tolerance_pct       — ±% band treated as 'matched'. Default 2.0 (±2%).
     * tolerance_min_cents — absolute floor for the band (AUD cents). Default 100 = AUD $1.00.
     * adapter_tolerances  — per-adapter overrides: ['sftp' => 3.0]
     * sponsor_tolerances  — per-sponsor overrides: ['sp_456' => 1.0]
     */
    'reconciliation' => [
        'tolerance_pct'       => 2.0,
        'tolerance_min_cents' => 100,
        'adapter_tolerances'  => [],
        'sponsor_tolerances'  => [],
    ],

    /**
     * Prompt version tag for PAID-08 run reconciliation CIC traceability.
     */
    'prompt_version_paid08' => 'paid-08',
];

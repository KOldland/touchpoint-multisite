<?php
/**
 * FX Service — static rate currency conversion.
 *
 * Provides deterministic, sandbox-safe foreign exchange rate lookup and
 * conversion. Rates are loaded from config/paid_adapters.php['fx_rates'].
 * No external API calls are made; identical inputs always yield identical
 * outputs (safe for CI golden-fixture validation).
 *
 * @package KH_SMMA\Reconciliation
 * @see     config/paid_adapters.php
 * @see     docs/paid/finance_reconciliation_runbook.md
 */

namespace KH_SMMA\Reconciliation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FxService {

    /** @var array<string, float> e.g. ['AUD_USD' => 0.6453] */
    private array $rates;

    /**
     * @param array<string, float> $rates Map of CURRENCY_CURRENCY => rate.
     */
    public function __construct( array $rates = [] ) {
        $this->rates = $rates;
    }

    /**
     * Load rates from config/paid_adapters.php.
     *
     * @return self
     */
    public static function from_config(): self {
        $config = require KH_SMMA_PATH . 'config/paid_adapters.php';
        return new self( $config['fx_rates'] ?? [] );
    }

    /**
     * Return the exchange rate from $from to $to.
     *
     * Falls back to 1.0 if the pair is not configured (sandbox-safe).
     *
     * @param string $from ISO 4217 source currency code.
     * @param string $to   ISO 4217 target currency code.
     * @return float
     */
    public function get_rate( string $from, string $to ): float {
        if ( $from === $to ) {
            return 1.0;
        }

        $key = strtoupper( $from ) . '_' . strtoupper( $to );
        return (float) ( $this->rates[ $key ] ?? 1.0 );
    }

    /**
     * Convert an amount from one currency to another.
     *
     * Rounding: 4 decimal places (financial precision).
     *
     * @param float  $amount Source amount.
     * @param string $from   Source currency.
     * @param string $to     Target currency.
     * @return float
     */
    public function convert( float $amount, string $from, string $to ): float {
        return round( $amount * $this->get_rate( $from, $to ), 4 );
    }
}

<?php
namespace KH_SMMA\CLI;

use KH_SMMA\Services\PhaseEngine;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventCatalogCommand {
    /** @var PhaseEngine */
    private $phase_engine;

    public function __construct( PhaseEngine $phase_engine ) {
        $this->phase_engine = $phase_engine;
    }

    public function register() {
        if ( ! class_exists( '\\WP_CLI' ) ) {
            return;
        }

        call_user_func( array( '\\WP_CLI', 'add_command' ), 'kh-smma event-catalog', $this );
    }

    /**
     * Import an event_catalog CSV.
     *
     * ## OPTIONS
     *
     * --file=<path>
     * : Absolute path to the CSV file.
     *
     * ## EXAMPLES
     *
     *     wp kh-smma event-catalog import --file=/path/to/event_catalog.csv
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function import( $args, $assoc_args ) {
        $file = $assoc_args['file'] ?? '';
        if ( ! $file || ! file_exists( $file ) ) {
            call_user_func( array( '\\WP_CLI', 'error' ), 'CSV file not found. Provide --file=/absolute/path/to/event_catalog.csv' );
        }

        $count = $this->phase_engine->import_event_catalog( $file );
        call_user_func( array( '\\WP_CLI', 'success' ), sprintf( 'Imported %d catalog rows.', $count ) );
    }

    /**
     * Show current catalog status.
     *
     * ## EXAMPLES
     *
     *     wp kh-smma event-catalog status
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function status( $args, $assoc_args ) {
        $count = $this->phase_engine->get_catalog_count();
        call_user_func( array( '\\WP_CLI', 'line' ), sprintf( 'Event catalog rows: %d', $count ) );
    }
}

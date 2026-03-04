<?php

namespace KHM\CLI;

use KHM\Membership\RetentionWorker;

if ( ! defined( 'WP_CLI' ) || ! constant( 'WP_CLI' ) ) {
    return;
}

class RetentionRunCommand {
    /**
     * Run attribution retention cleanup now.
     *
     * @when after_wp_load
     */
    public function __invoke(): void {
        $worker = new RetentionWorker();
        $worker->run();
        \WP_CLI::success( 'Attribution retention run completed.' );
    }
}

if ( class_exists( '\\WP_CLI' ) ) {
    call_user_func( [ '\\WP_CLI', 'add_command' ], 'khm retention:run', RetentionRunCommand::class );
}

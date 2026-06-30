<?php
/**
 * Migration Runner
 *
 * Idempotent migration runner that tracks executed migrations in a
 * dedicated kh_migration_log table. Each migration is run exactly once.
 *
 * @package KH\Editorial\Database
 */

namespace KH\Editorial\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class MigrationRunner
 */
class MigrationRunner {

    /**
     * The name of the migration log table.
     *
     * @var string
     */
    private string $log_table;

    /**
     * Registered migrations: [ 'name' => callable ].
     *
     * @var array<string, callable>
     */
    private array $migrations = array();

    /**
     * Constructor.
     *
     * @param string|null $log_table Custom log table name (optional).
     */
    public function __construct( string $log_table = null ) {
        global $wpdb;
        $this->log_table = $log_table ?: $wpdb->prefix . 'kh_migration_log';
    }

    /**
     * Install the migration log table schema.
     *
     * @return void
     */
    public function install_schema(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            migration_name VARCHAR(255) NOT NULL,
            run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) NOT NULL DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_migration_name (migration_name)
        ) {$charset_collate} ENGINE=InnoDB;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Register a migration.
     *
     * @param string   $name   Unique migration name (e.g. '20260704_fulltext_indexes').
     * @param callable $runner Callable that executes the migration logic.
     * @return void
     */
    public function register( string $name, callable $runner ): void {
        $this->migrations[ $name ] = $runner;
    }

    /**
     * Run all registered migrations that haven't been executed yet.
     *
     * @return array{
     *   executed: array<int, string>,
     *   skipped: array<int, string>,
     *   failed: array<int, array{name: string, error: string}>
     * }
     */
    public function run_all(): array {
        $result = array(
            'executed' => array(),
            'skipped'  => array(),
            'failed'   => array(),
        );

        foreach ( $this->migrations as $name => $runner ) {
            $status = $this->run_single( $name, $runner );
            if ( 'executed' === $status['status'] ) {
                $result['executed'][] = $name;
            } elseif ( 'skipped' === $status['status'] ) {
                $result['skipped'][] = $name;
            } else {
                $result['failed'][] = array(
                    'name'  => $name,
                    'error' => $status['error'],
                );
            }
        }

        return $result;
    }

    /**
     * Run a single migration by name (if not already executed).
     *
     * @param string $name   Migration name.
     * @return array{status: string, error?: string}
     */
    public function run_named( string $name ): array {
        if ( ! isset( $this->migrations[ $name ] ) ) {
            return array(
                'status' => 'failed',
                'error'  => "Migration '{$name}' is not registered.",
            );
        }

        return $this->run_single( $name, $this->migrations[ $name ] );
    }

    /**
     * Check if a migration has already been executed.
     *
     * @param string $name Migration name.
     * @return bool
     */
    public function has_run( string $name ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->log_table} WHERE migration_name = %s AND success = 1",
                $name
            )
        );
    }

    /**
     * Get the status of all registered migrations.
     *
     * @return array<int, array{name: string, ran_at: string|null, success: bool, error: string|null}>
     */
    public function get_status(): array {
        global $wpdb;
        $status = array();

        foreach ( array_keys( $this->migrations ) as $name ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT run_at, success, error_message FROM {$this->log_table} WHERE migration_name = %s ORDER BY run_at DESC LIMIT 1",
                    $name
                )
            );

            $status[] = array(
                'name'    => $name,
                'ran_at'  => $row ? $row->run_at : null,
                'success' => $row ? (bool) $row->success : false,
                'error'   => $row ? $row->error_message : null,
            );
        }

        return $status;
    }

    /**
     * Run a single migration if not already executed.
     *
     * @param string   $name   Migration name.
     * @param callable $runner Callable migration logic.
     * @return array{status: string, error?: string}
     */
    private function run_single( string $name, callable $runner ): array {
        global $wpdb;

        // Idempotency check — skip if already succeeded.
        if ( $this->has_run( $name ) ) {
            return array( 'status' => 'skipped' );
        }

        try {
            call_user_func( $runner );

            // Log success.
            $wpdb->insert(
                $this->log_table,
                array(
                    'migration_name' => $name,
                    'success'        => 1,
                    'error_message'  => null,
                ),
                array( '%s', '%d', '%s' )
            );

            return array( 'status' => 'executed' );
        } catch ( \Throwable $e ) {
            // Log failure.
            $wpdb->insert(
                $this->log_table,
                array(
                    'migration_name' => $name,
                    'success'        => 0,
                    'error_message'  => $e->getMessage(),
                ),
                array( '%s', '%d', '%s' )
            );

            return array(
                'status' => 'failed',
                'error'  => $e->getMessage(),
            );
        }
    }
}
<?php
/* phpcs:disable WordPress.DB.RestrictedClasses */
namespace KHM\Services;

class Migration {
    private $pdo;
    private $backupDir;
    private $migrationDir;
    private $isDryRun = true;

    public function __construct( \PDO $pdo, string $migrationDir, string $backupDir = null ) {
        $this->pdo = $pdo;
        $this->migrationDir = rtrim($migrationDir, '/');
        $this->backupDir = $backupDir ?? dirname($migrationDir) . '/backups';
    }

    public function setDryRun( bool $isDryRun ): void {
        $this->isDryRun = $isDryRun;
    }

    public function run( array $migrations = [] ): array {
        $results = [];

        // Ensure migrations table exists
        if ( ! $this->isDryRun ) {
            $this->ensureMigrationsTable();
        }

        // Get list of migrations to run
        $files = $migrations ?: $this->getMigrationFiles();
        $files = array_map( fn( $path ) => $this->normalizeMigrationPath( $path ), $files );
        if ( empty($files) ) {
            return [ 'status' => 'no-migrations' ];
        }

        // Check which migrations are needed
        $applied = $this->getAppliedMigrations();
        $toRun = array_filter($files, fn( $f ) => ! in_array(basename($f), $applied));
        if ( empty($toRun) ) {
            return [ 'status' => 'up-to-date' ];
        }

        // Create backup if not dry run
        if ( ! $this->isDryRun ) {
            $this->createBackup();
        }

        // Run or simulate each migration
        foreach ( $toRun as $file ) {
            $migration = basename( $file );
            $sql       = file_get_contents( $file );

            // Convert SQL for SQLite if needed.
            $sql = $this->convertSqlForSqlite( $sql );

            $statements = $this->splitSqlStatements( $sql );

            if ( $this->isDryRun ) {
                $results[ $migration ] = [
                    'status'          => 'would-run',
                    'statement_count' => count( $statements ),
                ];
                continue;
            }

            try {
                $this->pdo->beginTransaction();

                foreach ( $statements as $statement ) {
                    if ( $statement === '' ) {
                        continue;
                    }
                    $this->pdo->exec( $statement );
                }

                $this->recordMigration( $migration );
                $this->pdo->commit();
                $results[ $migration ] = [ 'status' => 'success' ];
            } catch ( \PDOException $e ) {
                $this->pdo->rollBack();
                $results[ $migration ] = [
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ];
                break;
            }
        }

        return $results;
    }

    private function ensureMigrationsTable(): void {
            $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

		if ( $driver === 'sqlite' ) {
			$sql = 'CREATE TABLE IF NOT EXISTS khm_migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    applied_at TEXT DEFAULT CURRENT_TIMESTAMP
                )';
		} else {
			$sql = 'CREATE TABLE IF NOT EXISTS khm_migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY migration_name (migration)
                )';
		}

            $this->pdo->exec($sql);
    }

    private function getAppliedMigrations(): array {
        if ( $this->isDryRun ) {
            return [];
        }
        $stmt = $this->pdo->query('SELECT migration FROM khm_migrations ORDER BY id');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array {
        $pattern = $this->migrationDir . '/*.sql';
        return glob($pattern);
    }

    private function recordMigration( string $migration ): void {
        $stmt = $this->pdo->prepare('INSERT INTO khm_migrations (migration) VALUES (?)');
        $stmt->execute([ $migration ]);
    }

    private function createBackup(): void {
        if ( ! is_dir($this->backupDir) ) {
            mkdir($this->backupDir, 0755, true);
        }

        $tables = [
            'khm_membership_levels',
            'khm_membership_levelmeta',
            'khm_memberships_users',
            'khm_membership_orders',
            'pmpro_membership_levels',
            'pmpro_membership_levelmeta',
            'pmpro_memberships_users',
            'pmpro_membership_orders',
        ];
		$filename = $this->backupDir . '/backup-' . gmdate('Y-m-d-His') . '.sql';
        $fh = fopen($filename, 'w');

        foreach ( $tables as $table ) {
            try {
                // Get create table
                $stmt = $this->pdo->query("SHOW CREATE TABLE $table");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ( $row ) {
                    fwrite($fh, $row['Create Table'] . ";\n\n");

                    // Get data
                    $stmt = $this->pdo->query("SELECT * FROM $table");
                    while ( $row = $stmt->fetch(\PDO::FETCH_ASSOC) ) {
                        $values = array_map(function ( $v ) {
                            return $v === null ? 'NULL' : $this->pdo->quote($v);
                        }, $row);
                        fwrite($fh, "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n");
                    }
                    fwrite($fh, "\n");
                }
            } catch ( \PDOException $e ) {
                // Skip tables that don't exist yet
                continue;
            }
        }
        fclose($fh);
    }

    /**
     * Convert MySQL-specific SQL to SQLite-compatible SQL for testing.
     *
     * @param string $sql
     * @return string
     */
    private function convertSqlForSqlite( string $sql ): string {
        // Check if we're using SQLite
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ( $driver !== 'sqlite' ) {
            return $sql;
        }

        // Remove KEY index definitions (SQLite doesn't support inline KEY in CREATE TABLE)
        $sql = preg_replace('/,\s*KEY\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);

        // Convert AUTO_INCREMENT to AUTOINCREMENT
        $sql = preg_replace('/AUTO_INCREMENT/i', 'AUTOINCREMENT', $sql);

        // Convert UNIQUE KEY to UNIQUE
        $sql = preg_replace('/UNIQUE\s+KEY\s+(\w+)\s+\(([^)]+)\)/i', 'UNIQUE($2)', $sql);

        // Remove ENGINE and CHARSET clauses
        $sql = preg_replace('/ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/COLLATE\s*=?\s*\w+/i', '', $sql);

        // Convert DATETIME to TEXT (SQLite doesn't have native DATETIME)
        $sql = preg_replace('/DATETIME/i', 'TEXT', $sql);

        // Convert ENUM to TEXT
        $sql = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $sql);

        // Remove ON UPDATE clauses
        $sql = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);

        // Convert BIGINT UNSIGNED to INTEGER
        $sql = preg_replace('/BIGINT\s+UNSIGNED/i', 'INTEGER', $sql);

        // Convert DECIMAL to REAL
        $sql = preg_replace('/DECIMAL\s*\(\d+,\d+\)/i', 'REAL', $sql);

        // Convert VARCHAR to TEXT
        $sql = preg_replace('/VARCHAR\s*\(\d+\)/i', 'TEXT', $sql);

        // Convert CHAR to TEXT
        $sql = preg_replace('/CHAR\s*\(\d+\)/i', 'TEXT', $sql);

        // Convert INT to INTEGER
        $sql = preg_replace('/\bINT\b/i', 'INTEGER', $sql);

        return $sql;
    }

    /**
     * Ensure a migration reference resolves to an absolute path within the migration directory.
     */
    private function normalizeMigrationPath( string $file ): string {
        $file = ltrim( $file, '= ' );
        if ( strpos( $file, DIRECTORY_SEPARATOR ) === false ) {
            return $this->migrationDir . '/' . $file;
        }
        return $file;
    }

    /**
     * Break a SQL script into executable statements while accounting for strings and comments.
     *
     * @param string $sql Raw SQL.
     * @return array<int,string>
     */
    private function splitSqlStatements( string $sql ): array {
        $statements     = [];
        $length         = strlen( $sql );
        $buffer         = '';
        $inSingleQuote  = false;
        $inDoubleQuote  = false;
        $inLineComment  = false;
        $inBlockComment = false;

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $sql[ $i ];
            $next = ( $i + 1 < $length ) ? $sql[ $i + 1 ] : '';

            if ( $inLineComment ) {
                if ( $char === "\n" ) {
                    $inLineComment = false;
                    $buffer       .= $char;
                }
                continue;
            }

            if ( $inBlockComment ) {
                if ( $char === '*' && $next === '/' ) {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if ( $char === "'" && ! $inDoubleQuote ) {
                $escaped = $i > 0 && $sql[ $i - 1 ] === '\\';
                if ( ! $escaped ) {
                    $inSingleQuote = ! $inSingleQuote;
                }
                $buffer .= $char;
                continue;
            }

            if ( $char === '"' && ! $inSingleQuote ) {
                $escaped = $i > 0 && $sql[ $i - 1 ] === '\\';
                if ( ! $escaped ) {
                    $inDoubleQuote = ! $inDoubleQuote;
                }
                $buffer .= $char;
                continue;
            }

            if ( ! $inSingleQuote && ! $inDoubleQuote ) {
                if ( $char === '-' && $next === '-' ) {
                    $inLineComment = true;
                    $i++;
                    continue;
                }

                if ( $char === '/' && $next === '*' ) {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }

                if ( $char === ';' ) {
                    $statement = trim( $buffer );
                    if ( $statement !== '' ) {
                        $statements[] = $statement;
                    }
                    $buffer = '';
                    continue;
                }
            }

            $buffer .= $char;
        }

        $remainder = trim( $buffer );
        if ( $remainder !== '' ) {
            $statements[] = $remainder;
        }

        return $statements;
    }
}

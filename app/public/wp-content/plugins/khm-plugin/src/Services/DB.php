<?php
/* phpcs:disable WordPress.DB.RestrictedClasses */
namespace KHM\Services;

class DB {
    private static $instance = null;
    private $pdo = null;

    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO(): \PDO {
        if ( $this->pdo === null ) {
            $creds = $this->getWPConfig();
            if ( ! $creds ) {
                throw new \RuntimeException(
                    'Could not find WordPress wp-config.php. Run from WordPress root or provide credentials.'
                );
            }

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $this->normalizeHost($creds['DB_HOST']),
                $creds['DB_NAME']
            );

            $this->pdo = new \PDO(
                $dsn,
                $creds['DB_USER'],
                $creds['DB_PASSWORD'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::MYSQL_ATTR_FOUND_ROWS => true,
                ]
            );
        }

        return $this->pdo;
    }

    /**
     * Normalize host value for environments where "localhost" socket resolution fails.
     */
    private function normalizeHost( string $host ): string {
        if ( $host === 'localhost' || $host === '' ) {
            return '127.0.0.1';
        }
        return $host;
    }

    private function getWPConfig(): ?array {
        $path = $this->resolveWpConfigPath();
        if ( ! $path || ! file_exists( $path ) ) {
            return null;
        }

        $content = file_get_contents( $path );
        $creds = [
            'DB_NAME'     => null,
            'DB_USER'     => null,
            'DB_PASSWORD' => null,
            'DB_HOST'     => null,
        ];

        foreach ( array_keys( $creds ) as $key ) {
            if ( preg_match( "/define\\s*\\(\\s*'{$key}'\\s*,\\s*'([^']*)'\\s*\\)/", $content, $match ) ) {
                $creds[ $key ] = $match[1];
            }
        }

        return ( in_array( null, $creds, true ) ) ? null : $creds;
    }

    private function resolveWpConfigPath(): ?string {
        $current = __DIR__;

        for ( $i = 0; $i < 8; $i++ ) {
            $candidate = $current . '/wp-config.php';
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
            $parent = dirname( $current );
            if ( $parent === $current ) {
                break;
            }
            $current = $parent;
        }

        // Attempt from document root/ABSPATH if available.
        if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-config.php' ) ) {
            return ABSPATH . 'wp-config.php';
        }

        return null;
    }
}

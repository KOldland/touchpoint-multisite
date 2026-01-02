<?php

namespace KHM\Seeds;

use KHM\Services\DB;
use PDO;

/**
 * Loads canonical touchpoint weight definitions from JSON into cp_weights.
 */
class CpWeightSeeder {

    private PDO $pdo;
    private string $seedFile;

    public function __construct( ?PDO $pdo = null, ?string $seedFile = null ) {
        $this->pdo      = $pdo ?: DB::getInstance()->getPDO();
        $this->seedFile = $seedFile ?: dirname( __DIR__, 2 ) . '/db/seeds/cp_weights_seed.json';
    }

    /**
     * Insert or update weight rows from the JSON seed file.
     *
     * @param bool $truncate When true, clears cp_weights before loading.
     * @return array{file:string,rows_processed:int}
     */
    public function seed( bool $truncate = false ): array {
        if ( ! file_exists( $this->seedFile ) ) {
            throw new \RuntimeException( "Seed file not found: {$this->seedFile}" );
        }

        $payload = json_decode( file_get_contents( $this->seedFile ), true );
        if ( ! is_array( $payload ) ) {
            throw new \RuntimeException( 'Seed file must decode to an array of weight objects.' );
        }

        if ( $truncate ) {
            $this->pdo->exec( 'TRUNCATE TABLE `cp_weights`' );
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `cp_weights`
                    (`touchpoint`,`stage_default`,`category`,`base_weight`,`description`,`is_active`,`created_at`,`updated_at`)
                 VALUES
                    (:touchpoint,:stage_default,:category,:base_weight,:description,:is_active,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE
                    `stage_default` = VALUES(`stage_default`),
                    `category` = VALUES(`category`),
                    `base_weight` = VALUES(`base_weight`),
                    `description` = VALUES(`description`),
                    `is_active` = VALUES(`is_active`),
                    `updated_at` = NOW()"
            );

            $processed = 0;
            foreach ( $payload as $row ) {
                $normalized = $this->normalizeRow( $row );
                $stmt->execute( $normalized );
                $processed++;
            }

            $this->pdo->commit();

            return [
                'file'           => $this->seedFile,
                'rows_processed' => $processed,
            ];
        } catch ( \Throwable $e ) {
            if ( $this->pdo->inTransaction() ) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Validate and normalize a single row before insert/update.
     *
     * @param array $row Raw JSON row.
     * @return array<string,mixed>
     */
    private function normalizeRow( array $row ): array {
        $required = [ 'touchpoint', 'stage_default', 'category', 'base_weight' ];
        foreach ( $required as $field ) {
            if ( ! isset( $row[ $field ] ) || $row[ $field ] === '' ) {
                throw new \InvalidArgumentException( "Seed row missing required field: {$field}" );
            }
        }

        $categoryMap = [
            'low'    => 'Low',
            'medium' => 'Medium',
            'high'   => 'High',
            'pos'    => 'PoS',
            'point-of-sale' => 'PoS',
        ];

        $categoryKey = strtolower( (string) $row['category'] );
        if ( ! isset( $categoryMap[ $categoryKey ] ) ) {
            throw new \InvalidArgumentException( "Invalid category value in seed row: {$row['category']}" );
        }

        $baseWeight = (float) $row['base_weight'];
        if ( $baseWeight <= 0 ) {
            throw new \InvalidArgumentException( "Base weight must be positive for touchpoint {$row['touchpoint']}" );
        }

        return [
            ':touchpoint'   => strtolower( trim( (string) $row['touchpoint'] ) ),
            ':stage_default'=> trim( (string) $row['stage_default'] ),
            ':category'     => $categoryMap[ $categoryKey ],
            ':base_weight'  => $baseWeight,
            ':description'  => isset( $row['description'] ) ? trim( (string) $row['description'] ) : null,
            ':is_active'    => isset( $row['is_active'] ) && $row['is_active'] ? 1 : 0,
        ];
    }
}

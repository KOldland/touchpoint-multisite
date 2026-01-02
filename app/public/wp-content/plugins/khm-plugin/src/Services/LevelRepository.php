<?php

namespace KHM\Services;

use KHM\Models\MembershipLevel;

/**
 * LevelRepository
 *
 * Data access layer for membership levels and level metadata.
 */
class LevelRepository {

    private string $levelsTable;
    private string $metaTable;

    /**
     * Map of column formats for wpdb operations.
     *
     * @var array<string,string>
     */
    private array $formatMap = [
        'name'              => '%s',
        'description'       => '%s',
        'confirmation'      => '%s',
        'initial_payment'   => '%f',
        'billing_amount'    => '%f',
        'cycle_number'      => '%d',
        'cycle_period'      => '%s',
        'billing_limit'     => '%d',
        'trial_amount'      => '%f',
        'trial_limit'       => '%d',
        'allow_signups'     => '%d',
        'expiration_number' => '%d',
        'expiration_period' => '%s',
        'created_at'        => '%s',
        'updated_at'        => '%s',
    ];

    public function __construct() {
        global $wpdb;

        $this->levelsTable = $wpdb->prefix . 'khm_membership_levels';
        $this->metaTable   = $wpdb->prefix . 'khm_membership_levelmeta';
    }

    /**
     * Retrieve all membership levels.
     *
     * @param bool $withMeta Whether to hydrate meta values.
     * @return MembershipLevel[]
     */
    public function all( bool $withMeta = false ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->levelsTable} ORDER BY id ASC"
        );

        if ( empty($rows) ) {
            return [];
        }

        return array_map(
            fn( $row ) => $this->mapRow($row, $withMeta),
            $rows
        );
    }

    /**
     * Fetch a membership level by ID.
     *
     * @param int  $id
     * @param bool $withMeta Whether to hydrate meta values.
     * @return MembershipLevel|null
     */
    public function get( int $id, bool $withMeta = false ): ?MembershipLevel {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->levelsTable} WHERE id = %d LIMIT 1",
                $id
            )
        );

        if ( ! $row ) {
            return null;
        }

        return $this->mapRow($row, $withMeta);
    }

    /**
     * Return an associative array of level id => name pairs.
     *
     * @return array<int,string>
     */
    public function getNameMap(): array {
        global $wpdb;

        $pairs = $wpdb->get_results(
            "SELECT id, name FROM {$this->levelsTable} ORDER BY name ASC"
        );

        if ( empty($pairs) ) {
            return [];
        }

        $map = [];
        foreach ( $pairs as $pair ) {
            $map[ (int) $pair->id ] = $pair->name;
        }

        return $map;
    }

    /**
     * Create a new membership level.
     *
     * @param array<string,mixed> $payload Level data.
     * @param array<string,mixed> $meta Optional meta data.
     * @return MembershipLevel|null
     */
    public function create( array $payload, array $meta = [] ): ?MembershipLevel {
        global $wpdb;

        $data = $this->sanitizePayload( $payload );
        $defaults = [
            'description'       => '',
            'confirmation'      => '',
            'initial_payment'   => 0.0,
            'billing_amount'    => 0.0,
            'cycle_number'      => 0,
            'cycle_period'      => 'Month',
            'billing_limit'     => 0,
            'trial_amount'      => 0.0,
            'trial_limit'       => 0,
            'allow_signups'     => 1,
            'expiration_number' => 0,
            'expiration_period' => 'Month',
        ];
        $data = array_merge( $defaults, $data );

        if ( empty( $data['name'] ) ) {
            return null;
        }

        $now = current_time( 'mysql' );
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $result = $wpdb->insert(
            $this->levelsTable,
            $data,
            $this->buildFormats( $data )
        );

        if ( false === $result ) {
            return null;
        }

        $levelId = (int) $wpdb->insert_id;
        $this->syncMeta( $levelId, $meta );

        return $this->get( $levelId, true );
    }

    /**
     * Update an existing membership level.
     *
     * @param int $id Level ID.
     * @param array<string,mixed> $payload Level data.
     * @param array<string,mixed> $meta Optional meta updates.
     * @return bool
     */
    public function update( int $id, array $payload, array $meta = [] ): bool {
        global $wpdb;

        $data = $this->sanitizePayload( $payload );

        if ( ! empty( $data ) ) {
            $data['updated_at'] = current_time( 'mysql' );

            $result = $wpdb->update(
                $this->levelsTable,
                $data,
                [ 'id' => $id ],
                $this->buildFormats( $data ),
                [ '%d' ]
            );

            if ( false === $result ) {
                return false;
            }
        }

        if ( ! $this->syncMeta( $id, $meta ) ) {
            return false;
        }

        return true;
    }

    /**
     * Delete a membership level and its meta.
     *
     * @param int $id Level ID.
     * @return bool
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $wpdb->delete(
            $this->metaTable,
            [ 'level_id' => $id ],
            [ '%d' ]
        );

        $result = $wpdb->delete(
            $this->levelsTable,
            [ 'id' => $id ],
            [ '%d' ]
        );

        return (bool) $result;
    }

    /**
     * Get a single meta value for a level.
     *
     * @param int         $levelId
     * @param string      $key
     * @param mixed|null  $default
     * @return mixed|null
     */
    public function getMeta( int $levelId, string $key, $default = null ) {
        global $wpdb;

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$this->metaTable} WHERE level_id = %d AND meta_key = %s LIMIT 1",
                $levelId,
                $key
            )
        );

        if ( null === $value ) {
            return $default;
        }

        return maybe_unserialize($value);
    }

    /**
     * Update a meta value for a level.
     *
     * @param int    $levelId
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function updateMeta( int $levelId, string $key, $value ): bool {
        global $wpdb;

        $serialized = maybe_serialize($value);

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_id FROM {$this->metaTable} WHERE level_id = %d AND meta_key = %s LIMIT 1",
                $levelId,
                $key
            )
        );

        if ( $existing ) {
            $result = $wpdb->update(
                $this->metaTable,
                [
                    'meta_value' => $serialized,
                ],
                [
                    'meta_id' => (int) $existing,
                ],
                [ '%s' ],
                [ '%d' ]
            );

            return $result !== false;
        }

        $result = $wpdb->insert(
            $this->metaTable,
            [
                'level_id'  => $levelId,
                'meta_key'  => $key,
                'meta_value'=> $serialized,
            ],
            [ '%d', '%s', '%s' ]
        );

        return (bool) $result;
    }

    /**
     * Delete a meta value for a level.
     *
     * @param int    $levelId
     * @param string $key
     * @return bool
     */
    public function deleteMeta( int $levelId, string $key ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->metaTable,
            [
                'level_id' => $levelId,
                'meta_key' => $key,
            ],
            [ '%d', '%s' ]
        );

        return (bool) $result;
    }

    /**
     * Hydrate model from raw database row.
     *
     * @param object $row
     * @param bool   $withMeta
     * @return MembershipLevel
     */
    private function mapRow( object $row, bool $withMeta ): MembershipLevel {
        $level = new MembershipLevel((array) $row);

        if ( $withMeta ) {
            $level->meta = $this->getAllMeta((int) $level->id);
        }

        return $level;
    }

    /**
     * Retrieve all meta key/value pairs for a level.
     *
     * @param int $levelId
     * @return array<string,mixed>
     */
    private function getAllMeta( int $levelId ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$this->metaTable} WHERE level_id = %d",
                $levelId
            )
        );

        if ( empty($rows) ) {
            return [];
        }

        $meta = [];
        foreach ( $rows as $row ) {
            $meta[ $row->meta_key ] = maybe_unserialize($row->meta_value);
        }

        return $meta;
    }

    /**
     * Prepare format array aligned with provided data.
     *
     * @param array<string,mixed> $data Data array.
     * @return array<int,string>
     */
    private function buildFormats( array $data ): array {
        $formats = [];
        foreach ( $data as $key => $value ) {
            if ( isset( $this->formatMap[ $key ] ) ) {
                $formats[] = $this->formatMap[ $key ];
            }
        }

        return $formats;
    }

    /**
     * Synchronize level meta values with database.
     *
     * @param int $levelId Level ID.
     * @param array<string,mixed> $meta Meta data.
     * @return bool
     */
    private function syncMeta( int $levelId, array $meta ): bool {
        if ( empty( $meta ) ) {
            return true;
        }

        foreach ( $meta as $key => $value ) {
            if ( null === $value ) {
                if ( ! $this->deleteMeta( $levelId, $key ) ) {
                    return false;
                }
                continue;
            }

            if ( ! $this->updateMeta( $levelId, $key, $value ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize incoming payload before persistence.
     *
     * @param array<string,mixed> $payload Raw payload.
     * @return array<string,mixed>
     */
    private function sanitizePayload( array $payload ): array {
        $schema = [
            'name'              => 'string',
            'description'       => 'string',
            'confirmation'      => 'string',
            'initial_payment'   => 'amount',
            'billing_amount'    => 'amount',
            'cycle_number'      => 'int',
            'cycle_period'      => 'period',
            'billing_limit'     => 'int',
            'trial_amount'      => 'amount',
            'trial_limit'       => 'int',
            'allow_signups'     => 'bool',
            'expiration_number' => 'int',
            'expiration_period' => 'period',
        ];

        $periods = [ 'Day', 'Week', 'Month', 'Year' ];
        $data    = [];

        foreach ( $schema as $field => $type ) {
            if ( ! array_key_exists( $field, $payload ) ) {
                continue;
            }

            $value = $payload[ $field ];

            switch ( $type ) {
                case 'string':
                    $data[ $field ] = (string) $value;
                    break;
                case 'amount':
                    $amount = (float) $value;
                    $data[ $field ] = round( max( 0, $amount ), 2 );
                    break;
                case 'int':
                    $data[ $field ] = max( 0, (int) $value );
                    break;
                case 'bool':
                    $data[ $field ] = $value ? 1 : 0;
                    break;
                case 'period':
                    $period = (string) $value;
                    if ( ! in_array( $period, $periods, true ) ) {
                        $period = 'Month';
                    }
                    $data[ $field ] = $period;
                    break;
            }
        }

        return $data;
    }
}

<?php

namespace Cookiez\Modules\Scanner\Database;

use Cookiez\Classes\Database\Entry;
use Cookiez\Modules\Scanner\Classes\Enums\Scan_Status;
use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scan_Entry extends Entry {
	protected static function get_helper_class(): string {
		return Scan_Table::class;
	}

	public static function find_many( array $where = [], ?int $limit = null, ?int $offset = null ): array {
		$fields = [
			's.id AS id',
			's.api_id AS api_id',
			's.status AS status',
			's.type AS type',
			's.initiator AS initiator',
			's.summary AS summary',
			's.created_at AS created_at',
			's.updated_at AS updated_at',
			'COUNT(DISTINCT u.id) AS total_urls',
			"COUNT(DISTINCT CASE WHEN u.scan_status = '" . esc_sql( Scan_Status::COMPLETED ) . "' THEN u.id END) AS scanned_urls",
		];

		$join = [
			[ 'LEFT JOIN', Scan_Url_Table::class, 'u', 'u.scan_id = s.id' ],
		];

		return (array) Scan_Table::select(
			$fields,
			! empty( $where ) ? $where : '1',
			$limit,
			$offset,
			$join,
			[ 's.created_at' => 'DESC' ],
			's.id',
			's'
		);
	}

	public static function find_by_id( int $scan_id ): ?stdClass {
		$results = self::find_many( [ 's.id' => $scan_id ], 1 );

		if ( empty( $results ) ) {
			return null;
		}

		return $results[0];
	}

	public static function is_active_scan(): bool {
		$result = Scan_Table::first(
			Scan_Table::ID,
			[ Scan_Table::STATUS => Scan_Status::IN_PROGRESS ]
		);

		return (bool) $result;
	}

	/**
	 * Oldest scan row with status completed (by created_at). Used for product timing (e.g. review prompt).
	 *
	 * @return \stdClass|null Row with id, api_id, status, created_at, updated_at or null.
	 */
	public static function get_first_completed_scan(): ?\stdClass {
		return Scan_Table::first(
			'*',
			[ Scan_Table::STATUS => Scan_Status::COMPLETED ],
			1,
			null,
			'',
			[ Scan_Table::UPDATED_AT => 'asc' ]
		);
	}

	/**
	 * Return the first scan that is currently active (in-progress or paused), or null.
	 *
	 * @return bool
	 * Service-first insert: `$api_id` is obtained before reaching this method
	 * so every row has the service id stamped atomically.
	 */
	public static function insert_one(
		string $type,
		string $initiator,
		string $api_id,
		string $status = Scan_Status::IN_PROGRESS
	): Scan_Entry {
		$entry = new self();

		$entry->set( Scan_Table::STATUS, $status );
		$entry->set( Scan_Table::TYPE, $type );
		$entry->set( Scan_Table::INITIATOR, $initiator );
		$entry->set( Scan_Table::API_ID, $api_id );

		$entry->create();

		return $entry;
	}

	/**
	 * Whitelisted update. Unknown keys are dropped silently so callers can't
	 * round-trip arbitrary columns. Immutable columns are never in the list.
	 */
	public static function update_one( int $scan_id, array $fields ) {
		$mutable = [
			Scan_Table::API_ID,
			Scan_Table::STATUS,
			Scan_Table::TYPE,
			Scan_Table::INITIATOR,
			Scan_Table::SUMMARY,
		];

		$clean = array_intersect_key( $fields, array_flip( $mutable ) );

		if ( empty( $clean ) ) {
			return false;
		}

		return Scan_Table::update( $clean, [ Scan_Table::ID => $scan_id ] );
	}
}

<?php

namespace Cookiez\Modules\Scanner\Database;

use Cookiez\Classes\Database\{
	Database_Constants,
	Table
};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scan_Table
 * Stores scan metadata and status
 */
class Scan_Table extends Table {
	const DB_VERSION = '2';
	const DB_VERSION_FLAG_NAME = 'cookiez_scan_db_version';

	const ID = 'id';
	const API_ID = 'api_id';
	const STATUS = 'status';
	const TYPE = 'type';
	const INITIATOR = 'initiator';
	const SUMMARY = 'summary';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';

	public static $table_name = 'cookiez_scans';

	/**
	 * Get table columns definition
	 * @return array
	 */
	public static function get_columns(): array {
		return [
			self::ID => [
				'type' => Database_Constants::get_col_type( Database_Constants::BIGINT ) . ' UNSIGNED',
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::AUTO_INCREMENT,
				] ),
				'key' => Database_Constants::get_primary_key_string( self::ID ),
			],
			self::API_ID => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 36 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'NULL',
				] ),
			],
			self::STATUS => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::TYPE => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::INITIATOR => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::SUMMARY => [
				'type' => Database_Constants::get_col_type( Database_Constants::LONGTEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'NULL',
				] ),
			],
			self::CREATED_AT => [
				'type' => Database_Constants::get_col_type( Database_Constants::DATETIME ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::DEFAULT,
					Database_Constants::CURRENT_TIMESTAMP,
				] ),
			],
			self::UPDATED_AT => [
				'type' => Database_Constants::get_col_type( Database_Constants::DATETIME ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::DEFAULT,
					Database_Constants::CURRENT_TIMESTAMP,
					Database_Constants::ON_UPDATE,
					Database_Constants::CURRENT_TIMESTAMP,
				] ),
			],
		];
	}

	/**
	 * Get additional keys for the table
	 * @return array
	 */
	protected static function get_extra_keys(): array {
		return [
			Database_Constants::build_key_string( Database_Constants::KEY, self::STATUS ),
			Database_Constants::build_key_string( Database_Constants::KEY, self::CREATED_AT ),
		];
	}
}

<?php

namespace Cookiez\Modules\Cookie\Database;

use Cookiez\Classes\Database\{
	Database_Constants,
	Table
};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cookie_Table
 * Stores cookies from both scans and manual entries
 */
class Cookie_Table extends Table {
	const DB_VERSION = '1';
	const DB_VERSION_FLAG_NAME = 'cookiez_cookie_db_version';

	const ID = 'id';
	const SCAN_ID = 'scan_id';
	const NAME = 'name';
	const DOMAIN = 'domain';
	const DURATION = 'duration';
	const CATEGORY = 'category';
	const DESCRIPTION = 'description';
	const TYPE = 'type';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';

	public static $table_name = 'cookiez_cookies';

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
			self::SCAN_ID => [
				'type' => Database_Constants::get_col_type( Database_Constants::BIGINT ) . ' UNSIGNED',
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NULL,
					Database_Constants::DEFAULT,
					'NULL',
				] ),
			],
			self::NAME => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 255 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::DOMAIN => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 255 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::DURATION => [
				'type' => Database_Constants::get_col_type( Database_Constants::BIGINT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NULL,
					Database_Constants::DEFAULT,
					'NULL', // null === session cookie
				] ),
			],
			self::CATEGORY => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::DESCRIPTION => [
				'type' => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NULL,
				] ),
			],
			self::TYPE => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
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
			Database_Constants::UNIQUE . ' ' . Database_Constants::KEY . ' name_domain (' . self::NAME . ', ' . self::DOMAIN . ')',
			Database_Constants::build_key_string( Database_Constants::KEY, self::SCAN_ID ),
			Database_Constants::build_key_string( Database_Constants::KEY, self::CATEGORY ),
			Database_Constants::build_key_string( Database_Constants::KEY, self::TYPE ),
		];
	}
}

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
 * Class Scan_Url_Table
 * Stores URLs scanned in each scan
 */
class Scan_Url_Table extends Table {
	const DB_VERSION = '3';
	const DB_VERSION_FLAG_NAME = 'cookiez_scan_url_db_version';

	const ID = 'id';
	const SCAN_ID = 'scan_id';
	const URL = 'url';
	const SCAN_STATUS = 'scan_status';
	const ERROR_CODE = 'error_code';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';

	public static $table_name = 'cookiez_scan_urls';

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
					Database_Constants::NOT_NULL,
				] ),
			],
			self::URL => [
				'type' => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::SCAN_STATUS => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::ERROR_CODE => [
				'type' => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NULL,
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
			Database_Constants::build_key_string( Database_Constants::KEY, self::SCAN_ID ),
		];
	}
}

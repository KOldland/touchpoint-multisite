<?php

namespace Cookiez\Modules\Script\Database;

use Cookiez\Classes\Database\{
	Database_Constants,
	Table
};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Script_Table
 * Stores many-to-many relationship between cookies and script patterns
 */
class Script_Table extends Table {
	const DB_VERSION = '1';
	const DB_VERSION_FLAG_NAME = 'cookiez_script_db_version';

	const ID = 'id';
	const NAME = 'name';
	const COOKIE_ID = 'cookie_id';
	const TYPE = 'type';
	const VALUE = 'value';
	const DESCRIPTION = 'description';
	const CATEGORY = 'category';
	const BLOCKING_MODE = 'blocking_mode';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';

	public static $table_name = 'cookiez_scripts';

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
			self::NAME => [
				'type' => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::COOKIE_ID => [
				'type' => Database_Constants::get_col_type( Database_Constants::BIGINT ) . ' UNSIGNED',
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'NULL',
				] ),
			],
			self::TYPE => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::VALUE => [
				'type' => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::DESCRIPTION => [
				'type' => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NULL,
					Database_Constants::DEFAULT,
					'NULL',
				] ),
			],
			self::CATEGORY => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 20 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
				] ),
			],
			self::BLOCKING_MODE => [
				'type' => Database_Constants::get_col_type( Database_Constants::VARCHAR, 30 ),
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
			Database_Constants::build_key_string( Database_Constants::KEY, self::COOKIE_ID ),
			'UNIQUE KEY `value_unique` (`' . self::VALUE . '`(255))',
		];
	}
}

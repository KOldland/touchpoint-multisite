<?php

namespace Cookiez\Modules\Scanner\Database;

use Cookiez\Classes\Database\Entry;
use Cookiez\Modules\Scanner\Classes\Dto\Scan_Url_DTO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scan_Url_Entry extends Entry {
	protected static function get_helper_class(): string {
		return Scan_Url_Table::class;
	}

	/**
	 * @param Scan_Url_DTO[] $dtos
	 * @return false|int
	 */
	public static function insert_many( array $dtos ) {
		if ( empty( $dtos ) ) {
			return false;
		}

		$values = array_map( function ( Scan_Url_DTO $dto ) {
			$scan_id = Scan_Url_Table::prepare_value( (int) $dto->scan_id, '%d' );
			$url = Scan_Url_Table::prepare_value( esc_url_raw( $dto->url ) );
			$status = Scan_Url_Table::prepare_value( $dto->status );
			$error_code = Scan_Url_Table::prepare_value( $dto->error_code );

			return "({$scan_id}, {$url}, {$status}, {$error_code}, NOW())";
		}, $dtos );

		$columns = sprintf(
			'(`%s`, `%s`, `%s`, `%s`, `%s`)',
			Scan_Url_Table::SCAN_ID,
			Scan_Url_Table::URL,
			Scan_Url_Table::SCAN_STATUS,
			Scan_Url_Table::ERROR_CODE,
			Scan_Url_Table::CREATED_AT
		);

		return Scan_Url_Table::insert_many( $values, $columns );
	}

	/**
	 * @return false|int Rows updated, or false on error.
	 */
	public static function update_many( array $set, array $where ) {
		return Scan_Url_Table::update( $set, $where );
	}

	/**
	 * @return Scan_Url_DTO[]
	 */
	public static function find_many( array $where = [] ): array {
		$rows = Scan_Url_Table::select(
			'*',
			! empty( $where ) ? $where : '1',
			null,
			null,
			'',
			[ Scan_Url_Table::ID => 'ASC' ]
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( fn( $row ) => Scan_Url_DTO::from_entry( $row ), $rows );
	}
}

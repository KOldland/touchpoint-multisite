<?php

namespace Cookiez\Modules\Script\Database;

use Cookiez\Classes\Database\Entry;
use Cookiez\Modules\Script\Classes\Dto\Script_DTO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Script_Entry extends Entry {
	protected static function get_helper_class(): string {
		return Script_Table::class;
	}

	public static function find_all(): array {
		return (array) Script_Table::select( '*' );
	}

	public static function find_by_value( string $value ): ?Script_Entry {
		$result = Script_Table::first( '*', [ Script_Table::VALUE => $value ] );

		if ( ! $result ) {
			return null;
		}

		return new self( [ 'data' => $result ] );
	}

	public static function insert_one( Script_DTO $dto ): Script_Entry {
		$entry = new self();

		foreach ( $dto->to_entry() as $key => $value ) {
			$entry->set( $key, $value );
		}

		$entry->create();

		return $entry;
	}

	public static function update_one( int $id, Script_DTO $dto ): void {
		$entry = new self( [ 'id' => $id ] );

		foreach ( $dto->to_entry() as $key => $value ) {
			$entry->set( $key, $value );
		}

		$entry->save();
	}

	public static function detach_from_cookie( int $cookie_id ): void {
		Script_Table::update(
			[ Script_Table::COOKIE_ID => null ],
			[ Script_Table::COOKIE_ID => $cookie_id ]
		);
	}

	public static function retag_for_cookie( int $cookie_id, string $category ): void {
		Script_Table::update(
			[ Script_Table::CATEGORY => $category ],
			[ Script_Table::COOKIE_ID => $cookie_id ]
		);
	}
}

<?php

namespace Cookiez\Modules\Script\Components;

use Cookiez\Modules\Cookie\Components\Cookie;
use Cookiez\Modules\Script\Classes\Dto\Script_DTO;
use Cookiez\Modules\Script\Database\Script_Entry;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Script {
	public function create( Script_DTO $dto ): Script_DTO {
		$existing = Script_Entry::find_by_value( $dto->value );

		if ( $existing && $existing->id ) {
			throw new RuntimeException( 'script_duplicate' );
		}

		if ( null !== $dto->cookie_id ) {
			$dto->category = ( new Cookie() )->get( (int) $dto->cookie_id )->category;
		}

		$entry = Script_Entry::insert_one( $dto );

		if ( ! $dto->name ) {
			$entry->name = sprintf( 'Script %d', (int) $entry->id );
			$entry->save();
		}

		return Script_DTO::from_entry( $entry );
	}

	public function replace( int $id, Script_DTO $dto ): Script_DTO {
		return $this->write( $id, $dto );
	}

	public function patch( int $id, Script_DTO $dto ): Script_DTO {
		return $this->write( $id, $dto );
	}

	public function delete( int $id ): void {
		$entry = new Script_Entry( [ 'id' => $id ] );

		if ( ! $entry->id ) {
			throw new RuntimeException( 'script_not_found' );
		}

		$entry->delete();
	}

	public function get( int $id ): Script_DTO {
		$entry = new Script_Entry( [ 'id' => $id ] );

		if ( ! $entry->id ) {
			throw new RuntimeException( 'script_not_found' );
		}

		return Script_DTO::from_entry( $entry );
	}

	public function list(): array {
		$rows = Script_Entry::find_all();

		return array_map(
			fn( $row ) => Script_DTO::from_entry( new Script_Entry( [ 'data' => $row ] ) ),
			$rows
		);
	}

	public function upsert_from_scan( Script_DTO $dto ): Script_DTO {
		$existing = Script_Entry::find_by_value( $dto->value );

		if ( $existing && $existing->id ) {
			return Script_DTO::from_entry( $existing );
		}

		return $this->create( $dto );
	}

	public function sync_category_for_cookie( int $cookie_id, string $category ): void {
		Script_Entry::retag_for_cookie( $cookie_id, $category );
	}

	public function detach_from_cookie( int $cookie_id ): void {
		Script_Entry::detach_from_cookie( $cookie_id );
	}

	private function write( int $id, Script_DTO $dto ): Script_DTO {
		$entry = new Script_Entry( [ 'id' => $id ] );

		if ( ! $entry->id ) {
			throw new RuntimeException( 'script_not_found' );
		}

		if ( null !== $dto->value && '' !== $dto->value ) {
			$clash = Script_Entry::find_by_value( $dto->value );

			if ( $clash && $clash->id && (int) $clash->id !== $id ) {
				throw new RuntimeException( 'script_duplicate' );
			}
		}

		if ( null === $dto->cookie_id && null !== $entry->cookie_id ) {
			$dto->cookie_id = $entry->cookie_id;
		}

		if ( null !== $dto->cookie_id ) {
			$dto->category = ( new Cookie() )->get( $dto->cookie_id )->category;
		}

		foreach ( $dto->to_entry() as $key => $value ) {
			$entry->set( $key, $value );
		}

		$entry->save();

		return Script_DTO::from_entry( $entry );
	}
}

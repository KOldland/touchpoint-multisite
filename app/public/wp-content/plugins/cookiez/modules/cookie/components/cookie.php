<?php

namespace Cookiez\Modules\Cookie\Components;

use Cookiez\Modules\Cookie\Classes\Dto\Cookie_DTO;
use Cookiez\Modules\Cookie\Database\Cookie_Entry;
use Cookiez\Modules\Script\Components\Script;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cookie {
	public function create( Cookie_DTO $dto ): Cookie_DTO {
		// "no domain provided" collapses to the empty-string canonical form
		// on insert; PATCH keeps null to mean "don't touch the column".
		$dto->domain = $dto->domain ?? '';

		$existing = Cookie_Entry::find_by_name_domain( $dto->name, $dto->domain );

		if ( $existing && $existing->id ) {
			throw new RuntimeException( 'cookie_duplicate' );
		}

		return Cookie_DTO::from_entry( Cookie_Entry::insert_one( $dto ) );
	}

	public function replace( int $id, Cookie_DTO $dto ): Cookie_DTO {
		return $this->write( $id, $dto );
	}

	public function patch( int $id, Cookie_DTO $dto ): Cookie_DTO {
		return $this->write( $id, $dto );
	}

	public function delete( int $id ): void {
		$entry = new Cookie_Entry( [ 'id' => $id ] );

		if ( ! $entry->id ) {
			throw new RuntimeException( 'cookie_not_found' );
		}

		( new Script() )->detach_from_cookie( $id );

		$entry->delete();
	}

	public function get( int $id ): Cookie_DTO {
		$entry = new Cookie_Entry( [ 'id' => $id ] );

		if ( ! $entry->id ) {
			throw new RuntimeException( 'cookie_not_found' );
		}

		return Cookie_DTO::from_entry( $entry );
	}

	public function list( array $filters = [] ): array {
		$limit = isset( $filters['limit'] ) ? (int) $filters['limit'] : null;
		$offset = isset( $filters['offset'] ) ? (int) $filters['offset'] : null;

		unset( $filters['limit'], $filters['offset'] );

		$rows = Cookie_Entry::find_all( $filters, $limit, $offset );

		return array_map(
			fn( $row ) => Cookie_DTO::from_entry( new Cookie_Entry( [ 'data' => $row ] ) ),
			$rows
		);
	}

	public function upsert_from_scan( Cookie_DTO $dto ): Cookie_DTO {
		$existing = Cookie_Entry::find_by_name_domain( $dto->name, $dto->domain );

		if ( $existing && $existing->id ) {
			return Cookie_DTO::from_entry( $existing );
		}

		return Cookie_DTO::from_entry( Cookie_Entry::insert_one( $dto ) );
	}

	private function write( int $id, Cookie_DTO $dto ): Cookie_DTO {
		$entry = new Cookie_Entry( [ 'id' => $id ] );

		if ( ! $entry->id ) {
			throw new RuntimeException( 'cookie_not_found' );
		}

		$previous_category = (string) ( $entry->category ?? '' );

		foreach ( $dto->to_entry() as $key => $value ) {
			$entry->set( $key, $value );
		}

		$entry->save();

		if ( null !== $dto->category && $dto->category !== $previous_category ) {
			( new Script() )->sync_category_for_cookie( $id, $dto->category );
		}

		return Cookie_DTO::from_entry( $entry );
	}
}

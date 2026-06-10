<?php

namespace Cookiez\Modules\Cookie\Database;

use Cookiez\Classes\Database\Entry;
use Cookiez\Modules\Cookie\Classes\Dto\Cookie_DTO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cookie_Entry extends Entry {
	protected static function get_helper_class(): string {
		return Cookie_Table::class;
	}

	public static function find_all( array $where = [], ?int $limit = null, ?int $offset = null ): array {
		if ( empty( $where ) ) {
			$where = '1';
		}

		return (array) Cookie_Table::select(
			'*',
			$where,
			$limit,
			$offset,
			'',
			[ Cookie_Table::NAME => 'ASC' ]
		);
	}

	public static function find_by_name_domain( string $name, string $domain ): ?Cookie_Entry {
		$result = Cookie_Table::first(
			'*',
			[
				Cookie_Table::NAME => $name,
				Cookie_Table::DOMAIN => $domain,
			]
		);

		if ( ! $result ) {
			return null;
		}

		return new self( [ 'data' => $result ] );
	}

	public static function insert_one( Cookie_DTO $dto ): Cookie_Entry {
		$entry = new self();

		foreach ( $dto->to_entry() as $key => $value ) {
			$entry->set( $key, $value );
		}

		$entry->create();

		return $entry;
	}
}

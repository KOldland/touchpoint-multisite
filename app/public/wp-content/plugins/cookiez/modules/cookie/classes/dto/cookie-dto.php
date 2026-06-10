<?php

namespace Cookiez\Modules\Cookie\Classes\Dto;

use Cookiez\Modules\Cookie\Classes\Enums\Cookie_Type;
use Cookiez\Modules\Cookie\Database\{
	Cookie_Entry,
	Cookie_Table
};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cookie_DTO
 */
final class Cookie_DTO {
	public ?int $id;
	public ?int $scan_id;
	public ?string $name;
	public ?string $domain;
	public ?int $duration;
	public ?string $category;
	public ?string $description;
	public ?string $type;
	public ?string $created_at;
	public ?string $updated_at;

	public static function from_rest_api( array $data ): Cookie_DTO {
		$dto = new self();

		$dto->id = $data['id'] ?? null;
		$dto->scan_id = $data['scan_id'] ?? null;
		$dto->name = $data['name'] ?? null;
		$dto->domain = $data['domain'] ?? null;
		$dto->duration = $data['duration'] ?? null;
		$dto->category = $data['category'] ?? null;
		$dto->description = $data['description'] ?? null;
		$dto->type = $data['type'] ?? null;

		return $dto;
	}

	public static function from_scan( int $scan_id, array $data ): Cookie_DTO {
		$dto = new self();

		$dto->id = null;
		$dto->scan_id = $scan_id;
		$dto->name = $data['name'];
		$dto->domain = $data['domain'];
		$dto->duration = $data['duration'];
		$dto->category = $data['category'];
		$dto->description = $data['description'];

		$dto->type = Cookie_Type::SCAN;

		return $dto;
	}

	public static function from_entry( Cookie_Entry $cookie ): Cookie_DTO {
		$dto = new self();

		$dto->id = $cookie->id;
		$dto->scan_id = $cookie->scan_id;
		$dto->name = $cookie->name;
		$dto->domain = $cookie->domain;
		$dto->duration = $cookie->duration;
		$dto->category = $cookie->category;
		$dto->description = $cookie->description;
		$dto->type = $cookie->type;
		$dto->created_at = $cookie->created_at;
		$dto->updated_at = $cookie->updated_at;

		return $dto;
	}

	public function to_entry(): array {
		$fields = [
			Cookie_Table::NAME => $this->name,
			Cookie_Table::DOMAIN => $this->domain,
			Cookie_Table::DURATION => $this->duration,
			Cookie_Table::CATEGORY => $this->category,
			Cookie_Table::DESCRIPTION => $this->description,
			Cookie_Table::TYPE => $this->type,
			Cookie_Table::SCAN_ID => $this->scan_id,
		];

		return array_filter( $fields, fn( $value ) => null !== $value );
	}

	public function to_array(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'domain' => $this->domain,
			'duration' => $this->duration,
			'category' => $this->category,
			'description' => $this->description,
		];
	}
}

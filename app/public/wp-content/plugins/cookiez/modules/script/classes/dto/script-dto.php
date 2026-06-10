<?php

namespace Cookiez\Modules\Script\Classes\Dto;

use Cookiez\Modules\Script\Database\Script_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Script_DTO
 */
class Script_DTO {
	public ?int $id;
	public ?int $cookie_id;
	public ?string $name;
	public ?string $value;
	public ?string $type;
	public ?string $description;
	public ?string $category;
	public ?string $blocking_mode;
	public ?string $created_at;
	public ?string $updated_at;

	public static function from_rest_api( array $data ): Script_DTO {
		$dto = new self();

		$dto->id = $data['id'] ?? null;
		$dto->cookie_id = $data['cookie_id'] ?? null;
		$dto->name = $data['name'] ?? null;
		$dto->value = $data['value'] ?? null;
		$dto->type = $data['type'] ?? null;
		$dto->description = $data['description'] ?? null;
		$dto->category = $data['category'] ?? null;
		$dto->blocking_mode = $data['blocking_mode'] ?? null;

		return $dto;
	}

	public static function from_scan( array $data ): Script_DTO {
		$dto = new self();

		$dto->id = null;
		$dto->cookie_id = null;
		$dto->name = $data['name'] ?? '';
		$dto->value = $data['value'] ?? '';
		$dto->type = $data['type'] ?? null;
		$dto->description = $data['description'] ?? '';
		$dto->category = $data['category'] ?? '';
		$dto->blocking_mode = $data['blocking_mode'] ?? '';

		return $dto;
	}

	public static function from_entry( $entry ): Script_DTO {
		$dto = new self();

		$dto->id = $entry->id;
		$dto->cookie_id = $entry->cookie_id;
		$dto->name = $entry->name;
		$dto->value = $entry->value;
		$dto->type = $entry->type;
		$dto->description = $entry->description;
		$dto->category = $entry->category;
		$dto->blocking_mode = $entry->blocking_mode;
		$dto->created_at = $entry->created_at;
		$dto->updated_at = $entry->updated_at;

		return $dto;
	}

	public function to_entry(): array {
		$fields = [
			Script_Table::COOKIE_ID => $this->cookie_id,
			Script_Table::NAME => $this->name,
			Script_Table::VALUE => $this->value,
			Script_Table::TYPE => $this->type,
			Script_Table::DESCRIPTION => $this->description,
			Script_Table::CATEGORY => $this->category,
			Script_Table::BLOCKING_MODE => $this->blocking_mode,
		];

		return array_filter( $fields, fn( $value ) => null !== $value );
	}

	public function to_array(): array {
		return [
			'id' => $this->id,
			'cookie_id' => $this->cookie_id,
			'name' => $this->name,
			'value' => $this->value,
			'description' => $this->description,
			'category' => $this->category,
			'blocking_mode' => $this->blocking_mode,
		];
	}
}

<?php

namespace App\Models\Traits;

use App\Enums\FieldType;

trait FieldTrait
{
	public static function fieldTypes(): array
	{
		return FieldType::toLabels();
	}

	public static function fieldTypesWithOptions(): array
	{
		return FieldType::optionValues();
	}

	public static function fieldTypesWithUpload(): array
	{
		return FieldType::uploadValues();
	}

	public static function fieldTypesHasOptions(?string $fieldType): bool
	{
		if (empty($fieldType)) {
			return false;
		}

		return in_array($fieldType, self::fieldTypesWithOptions());
	}
}

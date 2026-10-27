<?php

/*
 * LaraClassifier - Classified Ads Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://bedigit.com
 * Author: Mayeul Akpovi (BeDigit)
 *
 * LICENSE
 * -------
 * This software is provided under a license agreement and may only be used or copied
 * in accordance with its terms, including the inclusion of the above copyright notice.
 * As this software is sold exclusively on CodeCanyon,
 * please review the full license details here: https://codecanyon.net/licenses/standard
 */

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

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

namespace App\Casts;

use App\Helpers\Common\Date;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class NullableDateTimeCast implements CastsAttributes
{
	/**
	 * Cast the given value.
	 *
	 * @param \Illuminate\Database\Eloquent\Model $model
	 * @param string $key
	 * @param mixed $value
	 * @param array<string, mixed> $attributes
	 * @return \Illuminate\Support\Carbon|null
	 */
	public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
	{
		return Date::forDisplay($value, nullable: true);
	}
	
	/**
	 * Prepare the given value for storage.
	 *
	 * @param \Illuminate\Database\Eloquent\Model $model
	 * @param string $key
	 * @param mixed $value
	 * @param array<string, mixed> $attributes
	 * @return string|null
	 */
	public function set(Model $model, string $key, mixed $value, array $attributes): ?string
	{
		return Date::forStorage($value, nullable: true);
	}
}

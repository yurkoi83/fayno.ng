<?php

namespace App\Casts;

use App\Helpers\Common\Date;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DateTimeCast implements CastsAttributes
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
		return Date::forDisplay($value);
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
		return Date::forStorage($value);
	}
}

<?php

namespace App\Helpers\Common\Date;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Validates date-related inputs
 */
class DateValidator
{
	/**
	 * Validate if a string represents a valid date
	 *
	 * @param string|null $value
	 * @return bool
	 */
	public static function isValid(?string $value): bool
	{
		if (empty($value)) return false;

		try {
			$date = Carbon::parse($value);
		} catch (Throwable $e) {
			return false;
		}

		return true;
	}
}

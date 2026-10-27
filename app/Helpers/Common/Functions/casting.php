<?php

use App\Helpers\Common\JsonUtils;

/**
 * Casts a value to string or returns null if not stringable.
 *
 * @param mixed $value The value to attempt to cast.
 * @return string|null
 */
function castToStringOrNull(mixed $value): ?string
{
	if (is_numeric($value)) {
		$value = (string)$value;
	}

	return isStringable($value) ? (string)$value : null;
}

/**
 * Casts a value to string with a default fallback.
 *
 * @param mixed $value The value to attempt to cast.
 * @param string|null $default The default string value to return if casting fails.
 * @return string
 */
function castToString(mixed $value, ?string $default = ''): string
{
	$fallback = $default ?? '';

	return castToStringOrNull($value) ?? $fallback;
}

/**
 * Casts a value to array or returns default fallback if not an array.
 *
 * @param mixed $value The value to check.
 * @return array
 */
function castToArray(mixed $value): array
{
	return JsonUtils::jsonToArray($value);
}

/**
 * Returns the value as an array, or an empty array if the value is not an array.
 *
 * @param mixed $value The value to check.
 * @param array $default
 * @return array
 */
function ensureCastedToArray(mixed $value, array $default = []): array
{
	return is_array($value) ? $value : $default;
}

/**
 * Wraps a non-array value inside a new array, otherwise returns the array as is.
 *
 * This normalizes single or array inputs so they can be processed as a collection.
 *
 * @param mixed $value The value to wrap or return.
 * @return array
 */
function wrapInArray(mixed $value): array
{
	return is_array($value) ? $value : [$value];
}

/**
 * Casts a value to JSON string or return default fallback if not encodable.
 *
 * @param mixed $value The value to convert
 * @return string The JSON string or null
 */
function castToJson(mixed $value): string
{
	return JsonUtils::ensureJson($value);
}

/**
 * Casts a value to integer with a default fallback.
 *
 * @param mixed $value The value to attempt to cast.
 * @param int $default The default integer to return if casting fails.
 * @return int
 */
function castToInt(mixed $value, int $default = 0): int
{
	// Handle direct integers
	if (is_int($value)) {
		return $value;
	}

	// Handle boolean values
	if (is_bool($value)) {
		return (int)$value;
	}

	// Handle integer values wrapped as string
	// Note: Make sure to check early pure integer value to avoid some ASCII code issues like 42
	// where ctype_digit(42) === false, since ASCII 42 is the * character
	if (is_string($value) && ctype_digit($value)) {
		$value = (int)$value;
	}

	// Handle floats values
	if (is_float($value)) {
		$value = (int)$value;
	}

	return is_int($value) ? $value : $default;
}

/**
 * Casts a value to a float (double) with a default fallback.
 *
 * Useful for: Monetary values, percentages, scientific data, measurements,
 *             Calculations, financial data, any decimal number processing
 *
 * @param mixed $value The value to attempt to cast.
 * @param float $default The default float value to return if casting fails.
 * @return float
 */
function castToFloat(mixed $value, float $default = 0.0): float
{
	if (is_float($value)) {
		return $value;
	}

	if (is_int($value)) {
		return (float)$value;
	}

	if (is_string($value) && is_numeric(trim($value))) {
		return (float)trim($value);
	}

	return $default;
}

/**
 * Casts a value to a boolean, safely handling strings like 'true', 'false', '1', '0'.
 *
 * @param mixed $value The value to attempt to cast.
 * @param bool $default The default boolean to return if casting fails.
 * @return bool
 */
function castToBool(mixed $value, bool $default = false): bool
{
	// If it's already a boolean, return it immediately.
	if (is_bool($value)) {
		return $value;
	}

	// Handle common string representations of boolean values.
	if (is_string($value)) {
		$lower = strtolower($value);
		if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
			return true;
		}
		if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
			return false;
		}
	}

	// Handle integer/float representation.
	if (is_numeric($value)) {
		// Only 0 (false) and 1 (true) should be considered standard.
		// PHP's default cast (bool) will treat any non-zero as true, but this
		// explicit check is safer for strict data.
		$intVal = (int)$value;
		if ($intVal === 1) return true;
		if ($intVal === 0) return false;
	}

	// Fall back to the default if an explicit cast couldn't be made.
	return $default;
}

/**
 * Casts an integer (expected) value to a boolean
 *
 * @param mixed $value
 * @return bool
 */
function castIntToBool(mixed $value): bool
{
	if (is_bool($value)) {
		return $value;
	}

	return ($value === 1 || $value === '1');
}

/**
 * Casts a value to DateTime or return default fallback if not convertible.
 *
 * @param mixed $value The value to convert (string, int timestamp, DateTime)
 * @param \DateTimeZone|string|null $timezone Timezone for the DateTime object
 * @param \DateTime|null $default
 * @return \DateTime|null The DateTime object or null
 */
function castToDateTime(mixed $value, \DateTimeZone|string|null $timezone = null, ?\DateTime $default = null): ?\DateTime
{
	// Handle the date TimeZone
	if (is_string($value)) {
		try {
			$timezone = new \DateTimeZone($timezone);
		} catch (\Exception $e) {
			$timezone = null;
		}
	}

	if ($value instanceof \DateTime) {
		return $timezone ? $value->setTimezone($timezone) : $value;
	}

	if ($value instanceof \DateTimeImmutable) {
		$result = \DateTime::createFromImmutable($value);

		return $timezone ? $result->setTimezone($timezone) : $result;
	}

	// Handle timestamps
	if (is_int($value) || (is_string($value) && ctype_digit($value))) {
		try {
			return new \DateTime("@{$value}", $timezone);
		} catch (\Exception $e) {
			return $default;
		}
	}

	// Handle date strings
	if (is_string($value) && !empty($value)) {
		try {
			return new \DateTime($value, $timezone);
		} catch (\Exception $e) {
			return $default;
		}
	}

	return $default;
}

/**
 * Casts a comma-separated string (expected) value to an array
 *
 * @param mixed $value
 * @param array $default
 * @return array
 */
function castCommaSeparatedStrToArray(mixed $value, array $default = []): array
{
	if (is_array($value)) {
		return $value;
	}

	$array = is_string($value) ? explode(',', $value) : $value;

	return ensureCastedToArray($array, $default);
}

/**
 * Check if the variable is string or numeric
 * or if the variable is an object and has a __toString method that can be called
 *
 * @param $value
 * @return bool
 */
function isStringable($value): bool
{
	return (isStringableStrict($value) || (is_object($value) && method_exists($value, '__toString')));
}

/**
 * Check if the variable is string or numeric
 *
 * @param $value
 * @return bool
 */
function isStringableStrict($value): bool
{
	return (is_string($value) || is_numeric($value));
}

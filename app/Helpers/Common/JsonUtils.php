<?php

namespace App\Helpers\Common;

use Illuminate\Support\Str;

/**
 * Minimal, framework-friendly helpers for casting between JSON strings
 * and PHP arrays.  Nothing more, nothing less.
 */
final class JsonUtils
{
	/* ---------------------------------------------------------------------
	 |  Casting helpers
	 | ------------------------------------------------------------------ */

	/**
	 * Cast any "JSON-ish" input to a **PHP array**.
	 *
	 * • Arrays come back unchanged.
	 * • Objects are recursively cast to arrays.
	 * • JSON strings are decoded (even when “double-encoded”).
	 * • All other types yield an empty array.
	 */
	public static function jsonToArray(mixed $value): array
	{
		// Already an array
		if (is_array($value)) {
			return $value;
		}

		// Plain object or Laravel collection → encode + decode
		if (is_object($value)) {
			// return json_decode(json_encode($value), true) ?? [];
			return Arr::fromObject($value);
		}

		// String that looks like JSON
		if (self::isJson($value)) {
			$array = json_decode($value, true);

			// Handle accidental double-encoding
			return is_array($array) ? $array : self::jsonToArray($array);
		}

		// Fallback
		return [];
	}

	/**
	 * Encode an array (or anything castable to array) into a JSON string.
	 *
	 * `pretty` adds JSON_PRETTY_PRINT but otherwise leaves encoding flags
	 * friendly for APIs (unescaped slashes + unicode).
	 */
	public static function arrayToJson(array $data, bool $pretty = false): string
	{
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0);

		return json_encode($data, $flags);
	}

	/**
	 * Ensure the given value is returned as a JSON string.
	 *
	 * • Valid JSON strings are returned untouched.
	 * • Arrays / objects are encoded.
	 * • Scalars are wrapped into a JSON string (e.g. `"42"` → `"42"`).
	 */
	public static function ensureJson(mixed $value, bool $pretty = false): string
	{
		return self::isJson($value)
			? $value
			: self::arrayToJson(self::jsonToArray($value), $pretty);
	}

	/**
	 * Laravel-style "is this a valid JSON string?" check.
	 */
	public static function isJson(mixed $value): bool
	{
		return is_string($value) && Str::of($value)->isJson();
	}
}

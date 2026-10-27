<?php

namespace App\Helpers\Common;

use App\Helpers\Common\Date\DateFormatter;
use App\Helpers\Common\Date\DateValidator;
use App\Helpers\Common\Date\TimeZoneManager;
use DateTime;
use Illuminate\Support\Carbon;

/*
 * The system locale needs to be set in the 'AppServiceProvider'
 * by calling this method: systemLocale()->setLocale($locale);
 *
 * IMPORTANT
 * ---------
 * use Illuminate\Support\Carbon; - Uses Laravel's app.timezone config
 * $date1 = Carbon::now(); // Uses config('app.timezone') automatically
 *
 * use Carbon\Carbon; - Uses system timezone by default
 * $date2 = Carbon::now(); // Uses system timezone (often UTC on servers)
 */

class Date
{
	/**
	 * Convert date to Carbon instance for user display with timezone preferences
	 *
	 * This method takes a date value and converts it to a Carbon instance with the appropriate
	 * timezone for display purposes. It respects user timezone preferences, IP-based country
	 * timezone detection, and admin panel requirements. The original Carbon instance is never
	 * mutated - a copy is always created to prevent side effects.
	 *
	 * @param $value
	 * @param bool $nullable
	 * @param string|null $tz
	 * @return \Illuminate\Support\Carbon|null
	 */
	public static function forDisplay($value = null, bool $nullable = false, ?string $tz = null): ?Carbon
	{
		if ($nullable && empty($value)) return null;

		// Define UTC timezone for consistency
		$dbStorageTimezone = 'UTC';

		if ($value instanceof Carbon) {
			// IMPORTANT: Create a copy to avoid mutating the original instance
			// This prevents timezone changes from affecting other references to the same Carbon object
			$carbon = $value->copy();
		} else if ($value instanceof DateTime) {
			// Convert standard DateTime objects to Carbon instances
			$carbon = Carbon::instance($value);

			// If DateTime has a different timezone, convert to UTC for consistency
			if ($carbon->timezone->getName() !== $dbStorageTimezone) {
				$carbon = $carbon->utc();
			}
		} else {
			// Validate and parse the value into a Carbon instance
			$value = DateValidator::isValid($value) ? $value : now();
			$carbon = Carbon::parse($value, $dbStorageTimezone);
		}

		// Determine the appropriate timezone for display
		// Priority: Method parameter > User preference > IP country > Default
		if (empty($tz)) {
			$tz = TimeZoneManager::getContextualTimeZone(nullable: true);
			if (empty($tz)) {
				return $carbon;
			}
		}

		if ($tz == $carbon->timezone->getName()) {
			return $carbon;
		}

		return $carbon->timezone($tz);
	}

	/**
	 * Prepare the date value for database storage in UTC format
	 *
	 * This method converts any date input type to a UTC-formatted string suitable for database
	 * storage. It handles various input formats (Carbon, DateTime, string, numeric timestamps)
	 * and ensures consistent UTC storage regardless of input timezone. This method is primarily
	 * used by Eloquent casts to prepare dates before database insertion.
	 *
	 * @param $value
	 * @param bool $nullable
	 * @return string|null
	 */
	public static function forStorage($value = null, bool $nullable = false): ?string
	{
		if ($nullable && empty($value)) return null;

		// Define variables for consistency and clarity
		$dbStorageTimezone = 'UTC';
		$dbStorageFormat = 'Y-m-d H:i:s';

		// Handle different input types and convert to Carbon instance
		if ($value instanceof Carbon) {
			// Create a copy to avoid mutating the original Carbon instance
			// This is crucial when the same Carbon object is used elsewhere
			$carbon = $value->copy();
		} else if ($value instanceof DateTime) {
			// Convert standard DateTime objects to Carbon instances
			$carbon = Carbon::instance($value);
		} else {
			// Validate and parse the value into a Carbon instance
			$value = DateValidator::isValid($value) ? $value : now();
			$carbon = Carbon::parse($value, $dbStorageTimezone);
		}

		// Always convert to UTC for consistent database storage
		// This ensures all dates are stored in the same timezone regardless of input
		return $carbon->utc()->format($dbStorageFormat);
	}

	/**
	 * Format Carbon instance using locale-specific formatting
	 */
	public static function format($value, string $dateType = 'date'): string
	{
		return DateFormatter::format($value, $dateType);
	}

	/**
	 * Generate human-readable relative time with optional popover
	 */
	public static function fromNow($value): string
	{
		return DateFormatter::fromNow($value);
	}

	/**
	 * Context-aware date formatting based on the current route and settings
	 */
	public static function customFromNow($value, bool $force = false): string
	{
		return DateFormatter::customFromNow($value, $force);
	}
}

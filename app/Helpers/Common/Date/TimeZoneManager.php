<?php

namespace App\Helpers\Common\Date;

use DateTimeZone;
use Throwable;

/**
 * Handles timezone operations and management
 */
class TimeZoneManager
{
	/**
	 * Get list of available timezones
	 *
	 * @param null $countryCode
	 * @return array
	 */
	public static function getTimeZones($countryCode = null): array
	{
		$timeZones = [];

		try {
			$timeZones = !empty($countryCode)
				? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode)
				: DateTimeZone::listIdentifiers();
		} catch (Throwable $e) {
		}

		if (empty($timeZones)) {
			$timeZones = getTimeZoneRefList();
		}

		return collect($timeZones)
			->mapWithKeys(fn ($tz) => [$tz => $tz])
			->toArray();
	}

	/**
	 * Determine the appropriate timezone for the current application context
	 * Return the timezone identifier or null for UTC/no conversion
	 *
	 * @param bool $nullable
	 * @return string|null
	 */
	public static function getContextualTimeZone(bool $nullable = false): ?string
	{
		$defaultTz = !$nullable ? 'UTC' : null;

		$tz = config('country.time_zone');
		$tz = config('ipCountry.time_zone', $tz) ?? $tz;

		/*
		 * Admin Panel timezone handling
		 * Use null as timezone to let dates to retrieved in UTC in the Admin Panel
		 */
		if (isAdminPanelRoute()) {
			return $defaultTz;
		}

		$guard = getAuthGuard();
		$authUser = auth($guard)->user();

		if (!empty($authUser)) {
			$tz = !empty($authUser->time_zone) ? $authUser->time_zone : $tz;
		}

		$tz = self::isValidTimeZone($tz) ? $tz : $defaultTz;

		return castToStringOrNull($tz);
	}

	/**
	 * Validate if a timezone identifier is supported by PHP
	 *
	 * @param $timeZoneId
	 * @return bool
	 */
	public static function isValidTimeZone($timeZoneId): bool
	{
		$timeZones = self::getTimeZones();

		return !empty($timeZones[$timeZoneId]);
	}
}

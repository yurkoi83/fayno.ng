<?php

namespace App\Helpers\Common\Date;

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Web\Front\Account\AccountBaseController;
use App\Http\Controllers\Web\Front\HomeController;
use App\Http\Controllers\Web\Front\Listing\Show\ShowController;
use App\Http\Controllers\Web\Front\Search\SearchController;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Handles date formatting operations
 */
class DateFormatter
{
	/**
	 * Format Carbon instance using locale-specific formatting
	 * (i.e. using the current locale)
	 *
	 * You can set the current locale using setlocale() https://www.php.net/manual/en/function.setlocale.php
	 *
	 * @param $value
	 * @param string $dateType
	 * @return string
	 */
	public static function format($value, string $dateType = 'date'): string
	{
		if ($value instanceof Carbon) {
			$dateFormat = self::getAppDateFormat($dateType);

			try {
				if (DateFormatConverter::isIsoFormat($dateFormat)) {
					$value = $value->isoFormat($dateFormat);
				} else {
					$value = $value->translatedFormat($dateFormat);
				}
			} catch (Throwable $e) {
			}
		}

		return castToString($value);
	}

	/**
	 * Generate human-readable relative time with optional popover for exact timestamp
	 *
	 * @param $value
	 * @return string
	 */
	public static function fromNow($value): string
	{
		if (!$value instanceof Carbon) {
			return castToString($value);
		}

		$formattedDate = self::format($value, 'datetime');

		// From Now Parameters
		$modifier = setting('app_date_from_now_modifier', 'DIFF_RELATIVE_TO_NOW');
		$syntax = defined('\Carbon\CarbonInterface::' . $modifier)
			? constant('\Carbon\CarbonInterface::' . $modifier)
			: CarbonInterface::DIFF_RELATIVE_TO_NOW;
		$short = (setting('app_date_from_now_short', '0') == '1');

		if (doesRequestIsFromWebClient() || isAdminPanelRoute()) {
			$popover = ' data-bs-container="body"';
			$popover .= ' data-bs-toggle="popover"';
			$popover .= ' data-bs-trigger="hover"';
			$popover .= ' data-bs-placement="bottom"';
			$popover .= ' data-bs-content="' . $formattedDate . '"';

			if (config('lang.direction') == 'rtl') {
				$popover = ' data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . $formattedDate . '"';
			}

			$out = '<span style="cursor: help;"' . $popover . '>';
			$out .= $value->fromNow($syntax, $short);
			$out .= '</span>';

			$value = $out;
		} else {
			$value = $value->fromNow($syntax, $short);
		}

		return castToString($value);
	}

	/**
	 * Context-aware date formatting based on the current route and settings
	 *
	 * @param $value
	 * @param bool $force
	 * @return string
	 */
	public static function customFromNow($value, bool $force = false): string
	{
		$isFromListingsList = (
			setting('listings_list_date_from_now')
			&& (
				(
					isApiRoute()
					&& (
						(
							routeActionHas(ListingController::class . '@index')
							&& !request()->filled('belongLoggedUser')
						)
						|| routeActionHas(SectionController::class)
						|| routeActionHas(AlertController::class)
					)
				)
				|| (
					!isApiRoute()
					&& (
						routeActionHas(getClassNamespaceName(SearchController::class))
						|| routeActionHas(HomeController::class)
						|| routeActionHas(getClassNamespaceName(AccountBaseController::class))
					)
				)
			)
		);

		$isFromListingDetails = (
			setting('listing_page_date_from_now')
			&& (
				(isApiRoute() && (routeActionHas(ListingController::class . '@show')))
				|| (!isApiRoute() && (routeActionHas(ShowController::class)))
			)
		);

		if ($force) {
			return self::fromNow($value);
		}
		if ($isFromListingsList) {
			return self::fromNow($value);
		} else if ($isFromListingDetails) {
			return self::fromNow($value);
		} else {
			if (!$value instanceof Carbon) {
				return castToString($value);
			}

			return self::format($value, 'datetime');
		}
	}

	/**
	 * Get the appropriate date format based on context and configuration
	 *
	 * @param string $dateType
	 * @return string
	 */
	private static function getAppDateFormat(string $dateType = 'date'): string
	{
		$adminDateFormat = ($dateType == 'datetime')
			? setting('app_datetime_format', config('larapen.core.datetimeFormat.default'))
			: setting('app_date_format', config('larapen.core.dateFormat.default'));

		$langFrontDateFormat = ($dateType == 'datetime') ? config('lang.datetime_format') : config('lang.date_format');
		$frontDateFormat = !empty($langFrontDateFormat) ? $langFrontDateFormat : $adminDateFormat;

		$countryFrontDateFormat = ($dateType == 'datetime') ? config('country.datetime_format') : config('country.date_format');
		$frontDateFormat = !empty($countryFrontDateFormat) ? $countryFrontDateFormat : $frontDateFormat;

		$dateFormat = isAdminPanelRoute() ? $adminDateFormat : $frontDateFormat;

		if (empty($dateFormat)) {
			$dateFormat = ($dateType == 'datetime') ? config('larapen.core.datetimeFormat.default') : config('larapen.core.dateFormat.default');
		}

		// For stats short dates
		if ($dateType == 'stats') {
			$dateFormat = !setting('app_php_specific_date_format') ? 'MMM DD' : '%b %d';
		}

		// For backup dates
		if ($dateType == 'backup') {
			$dateFormat = !setting('app_php_specific_date_format') ? 'DD MMMM YYYY, HH:mm' : '%d %B %Y, %H:%M';
		}

		// For values rendered alongside the datepicker (custom field display /
		// form pre-fill). Mirrors the JS picker's `format` option, so the displayed
		// value always matches the input format for the current page language.
		if ($dateType == 'datepicker' || $dateType == 'datepicker_datetime') {
			$transKey = ($dateType == 'datepicker_datetime') ? 'datepicker_format_datetime' : 'datepicker_format';
			$transFormat = castToString(trans('global.' . $transKey));
			if ($transFormat !== '') {
				$dateFormat = $transFormat;
			}
		}

		if (str_contains($dateFormat, '%')) {
			$dateFormat = DateFormatConverter::strftimeToDateFormat($dateFormat);
		}

		if (!is_string($dateFormat)) {
			$dateFormat = !setting('app_php_specific_date_format')
				? (($dateType == 'datetime') ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD')
				: (($dateType == 'datetime') ? '%Y-%m-%d %H:%M' : '%Y-%m-%d');
		}

		return $dateFormat;
	}
}

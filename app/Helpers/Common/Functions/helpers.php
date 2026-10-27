<?php

use App\Helpers\Common\CacheManager;
use App\Helpers\Common\Flash\FlashNotifier;
use App\Helpers\Common\Response\Ajax;
use App\Helpers\Common\Response\Api;
use App\Helpers\Common\SystemLocale;
use App\Helpers\Services\UrlBuilder;
use App\Helpers\Services\UrlGen;
use Illuminate\Support\ViewErrorBag;

/**
 * SystemLocale Object
 * Note: This is different to the app()->setLocale() and app()->getLocale() that are related to the app's locale
 */
function systemLocale(): SystemLocale
{
	return new SystemLocale;
}

/**
 * API Response Object
 */
function apiResponse(): Api
{
	return new Api;
}

/**
 * AJAX Response Object
 */
function ajaxResponse(): Ajax
{
	return new Ajax;
}

function urlBuilder(?string $url = null): UrlBuilder
{
	return new UrlBuilder($url);
}

function urlGen(): UrlGen
{
	return new UrlGen;
}

/**
 * Get an empty Laravel view errors collection
 */
function getEmptyViewErrors(): ViewErrorBag
{
	return new ViewErrorBag;
}

/**
 * Flash a message with chainable methods
 */
function flash(string $message): object
{
	return new FlashNotifier($message);
}

/**
 * CacheManager Helper
 * smartCache()
 */
function caching(): CacheManager
{
	return new CacheManager;
}

/**
 * Resolve an icon class string for the larapen-style chrome.
 * Stored values are normalized: bare "bi-star" → "bi bi-star"; FontAwesome
 * "fa-solid fa-star" passes through; anything else is returned as-is.
 */
function icon_class(?string $value, string $default = 'bi bi-grid-3x3-gap'): string
{
	$value = trim($value ?? '');

	if ($value === '') {
		return $default;
	}

	if (str_starts_with($value, 'fa-')) {
		return $value;
	}

	if (str_starts_with($value, 'bi-')) {
		return 'bi ' . $value;
	}

	return $value;
}

/**
 * Favicon URL for layout templates. Bridges larapen's `setting('site_favicon')`
 * convention to laraclassifier's existing `app_favicon_url` setting.
 */
function site_favicon_url(): string
{
	$url = setting('app_favicon_url');

	return $url ?: asset('images/favicon.png');
}

/**
 * Stub of larapen's `current_locale()` — returns Laravel's current app locale.
 * Used by the larapen installer/auth/admin views ported in Phase 7.
 * The full locale-system port (Phase 8) will replace this with a richer impl.
 */
function current_locale(): string
{
	return app()->getLocale();
}

/**
 * Display timezone — used by larapen's date helpers. Falls back to the
 * configured app timezone since laraclassifier doesn't expose a per-user
 * timezone preference.
 */
function display_timezone(): string
{
	return (string)(setting('timezone') ?: config('app.timezone', 'UTC'));
}

/**
 * Stub of larapen's `format_date()` — locale-aware date formatter using
 * Carbon's isoFormat tokens. Mirrors larapen's API so the ported installer/
 * admin views render without modification.
 */
function format_date(
	?\DateTimeInterface $date,
	?string             $format = null,
	string              $context = 'front',
	?string             $timezone = null,
): string
{
	if ($date === null) {
		return '-';
	}

	$carbon = ($date instanceof \Carbon\Carbon) ? $date->copy() : \Carbon\Carbon::instance($date);
	$carbon->setTimezone($timezone ?? display_timezone());

	if ($format === null) {
		$format = ($context === 'admin')
			? 'MMM D, YYYY'
			: ((string)(setting('date_format') ?: 'MMM D, YYYY'));
	}

	try {
		return $carbon->isoFormat($format);
	} catch (\Throwable) {
		return $carbon->toDateString();
	}
}

/**
 * Stub of larapen's `format_datetime()` — same shape as format_date() but
 * uses a datetime format. Mirrors larapen's API.
 */
function format_datetime(
	?\DateTimeInterface $date,
	?string             $format = null,
	string              $context = 'front',
	?string             $timezone = null,
): string
{
	if ($date === null) {
		return '-';
	}

	$carbon = ($date instanceof \Carbon\Carbon) ? $date->copy() : \Carbon\Carbon::instance($date);
	$carbon->setTimezone($timezone ?? display_timezone());

	if ($format === null) {
		$format = ($context === 'admin')
			? 'MMM D, YYYY HH:mm'
			: ((string)(setting('datetime_format') ?: 'MMM D, YYYY HH:mm'));
	}

	try {
		return $carbon->isoFormat($format);
	} catch (\Throwable) {
		return $carbon->toDateTimeString();
	}
}

/**
 * Stub of larapen's `localized_route()` — generates a route URL for the
 * given route name. Larapen's full impl prefixes the URL with the active
 * locale and supports a `.localized` variant per route; that machinery
 * lands in Phase 8. For now this falls through to standard route().
 *
 * If a `<name>.localized` route happens to be registered, prefer it and
 * pass the current locale as a parameter — that way once the locale port
 * lands, callers automatically pick up the prefixed URL without churn.
 */
function localized_route(string $name, array $parameters = [], bool $absolute = true): string
{
	$localizedName = $name . '.localized';
	if (\Illuminate\Support\Facades\Route::has($localizedName)) {
		$parameters['locale'] = $parameters['locale'] ?? app()->getLocale();

		return route($localizedName, $parameters, $absolute);
	}

	if (\Illuminate\Support\Facades\Route::has($name)) {
		unset($parameters['locale']);

		return route($name, $parameters, $absolute);
	}

	return url('/');
}

/**
 * Sanitize HTML for safe Blade output. Backed by mews/purifier; falls back
 * to escaped + nl2br'd output if HTMLPurifier rejects the input.
 */
function purify(?string $string, ?string $config = null): string
{
	if (empty($string)) {
		return '';
	}

	try {
		return $config ? clean($string, $config) : clean($string);
	} catch (\Throwable) {
		return nl2br(e($string));
	}
}

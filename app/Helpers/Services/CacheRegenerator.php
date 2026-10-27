<?php

namespace App\Helpers\Services;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class CacheRegenerator
{
	/**
	 * Regenerate route cache if enabled
	 *
	 * Reads the `optimization_route_cache_enabled` flat setting; when enabled, runs
	 * route:clear then route:cache. Errors are swallowed — cache regeneration is best
	 * effort and the admin can manually rerun if anything goes wrong.
	 *
	 * Note that, if the route cache is enabled, it will be necessary to be regenerated when:
	 * - the "routes.php" file is created or updated (from "larapen/routes.php")
	 * - the getCountryCodeRoutePattern() fn source is updated (not programmatically)
	 */
	public static function regenerateRouteCacheIfEnabled(): void
	{
		try {
			$routeCacheEnabled = (setting('optimization_route_cache_enabled') == 1);

			// Clear the existing route cache first
			Artisan::call('route:clear');

			// Generate a new route cache if enabled
			if ($routeCacheEnabled) {
				Artisan::call('route:cache');
			}
		} catch (Throwable) {
			// Silently fail - cache regeneration is not critical
		}
	}

	/**
	 * Regenerate config cache if enabled
	 *
	 * Reads the `optimization_config_cache_enabled` flat setting; when enabled, runs
	 * config:clear then config:cache.
	 *
	 * Note that, since all the system dynamic settings are stored as config,
	 * if the config cache is enabled, it will be necessary to be regenerated when any setting is saved.
	 */
	public static function regenerateConfigCacheIfEnabled(): void
	{
		try {
			$configCacheEnabled = (setting('optimization_config_cache_enabled') == 1);

			// Clear the existing config cache first
			Artisan::call('config:clear');

			// Generate new config cache if enabled
			if ($configCacheEnabled) {
				Artisan::call('config:cache');
			}
		} catch (Throwable) {
			// Silently fail - cache regeneration is not critical
		}
	}

	/**
	 * Regenerate both route and config cache if enabled.
	 */
	public static function regenerateAllCaches(): void
	{
		self::regenerateRouteCacheIfEnabled();
		self::regenerateConfigCacheIfEnabled();
	}
}

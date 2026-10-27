<?php

namespace App\Providers\AppService;

use App\Models\Setting;
use App\Services\SettingService;
use Throwable;

trait SettingsToConfigTrait
{
	/**
	 * Seed the `app_purchase_code` and `app_logo` defaults that some early-boot
	 * code paths read via `setting()` before any DB row exists (fresh install,
	 * broken DB connection). Everything else is resolved on demand by
	 * SettingService::all() against the flat `settings` table — no per-row
	 * hydration needed post-Stage-4.
	 */
	private function loadDatabaseSettingsToConfig(): void
	{
		// Default purchase code — registered as an override so a fresh install
		// (no DB row yet) still resolves a value via setting('app_purchase_code').
		// A real DB row will be returned by SettingService::all() in priority via
		// the standard cascade once the row exists, but the override below stays
		// as the boot-time fallback for the single request.
		SettingService::setOverride('app_purchase_code', config('larapen.core.purchaseCode'));

		try {
			// Touch the flat settings cache so a query failure surfaces here
			// (mirrors the original boot-time DB probe).
			Setting::query()->limit(1)->get(['group', 'key', 'value']);
		} catch (Throwable $e) {
			// DB / settings table not yet available (installation, broken connection).
			// Fall back to the framework default.
			SettingService::setOverride('app_logo', config('larapen.images.defaults.logo'));
		}
	}
}

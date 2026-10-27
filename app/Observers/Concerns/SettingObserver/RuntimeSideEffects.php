<?php

/*
 * LaraClassifier - Classified Ads Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://bedigit.com
 * Author: Mayeul Akpovi (BeDigit)
 *
 * LICENSE
 * -------
 * This software is provided under a license agreement and may only be used or copied
 * in accordance with its terms, including the inclusion of the above copyright notice.
 * As this software is sold exclusively on CodeCanyon,
 * please review the full license details here: https://codecanyon.net/licenses/standard
 */

declare(strict_types=1);

namespace App\Observers\Concerns\SettingObserver;

use App\Helpers\Common\Cookie;
use App\Models\Country;
use App\Models\Setting;
use Throwable;

/**
 * Per-request runtime state mutations triggered by setting changes — cookies and
 * sessions that need to flip immediately so the admin and front-side reflect
 * the new configuration without waiting for the usual middleware re-resolution.
 */
trait RuntimeSideEffects
{
	/**
	 * Drop browser- / country-derived language session keys when the auto-detect
	 * mode changes, so the next request re-runs detection against the new policy.
	 * The legacy trait keyed off the old `app.auto_detect_language` row; in the
	 * flat schema the setting moved to `localization_auto_detect_language`.
	 */
	private function clearLanguageDetectionSessions(Setting $setting): void
	{
		if ($setting->key !== 'localization_auto_detect_language') return;
		if (!$setting->wasChanged('value')) return;
		
		foreach (['browserLangCode', 'countryLangCode'] as $name) {
			if (session()->has($name)) {
				session()->forget($name);
			}
		}
		
		try {
			Country::all()->each(function ($country) {
				$sessionName = strtolower((string)$country->code) . 'CountryLangCode';
				if (session()->has($sessionName)) {
					session()->forget($sessionName);
				}
			});
		} catch (Throwable) {
		}
	}
	
	/**
	 * Push the new default list mode into the visitor's cookie so the change
	 * shows up immediately in the admin's own browser. Front visitors pick it
	 * up via the same cookie on their next request.
	 */
	private function setDisplayModeCookie(Setting $setting): void
	{
		if ($setting->key !== 'listings_list_display_mode') return;
		if (!$setting->wasChanged('value')) return;
		
		$value = $setting->value;
		if (empty($value) || !is_scalar($value)) return;
		
		Cookie::forget('display_mode');
		Cookie::set('display_mode', (string)$value, 60 * 24 * 7);
	}
	
	/**
	 * Snap the visitor's session country onto the new default so subsequent
	 * requests reflect the change without waiting for the cookie / IP-detection
	 * middleware to re-resolve it.
	 */
	private function updateDefaultCountrySession(Setting $setting): void
	{
		if ($setting->key !== 'localization_default_country_code') return;
		if (!$setting->wasChanged('value')) return;
		
		$value = $setting->value;
		if (empty($value) || !is_scalar($value)) return;
		
		session()->forget('countryCode');
		session()->put('countryCode', (string)$value);
	}
}

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

use App\Helpers\Common\DBUtils\DBEncoding;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Scopes\ActiveScope;
use App\Models\Scopes\LocalizedScope;
use App\Models\Setting;
use App\Models\SubAdmin1;
use App\Models\SubAdmin2;
use Illuminate\Support\Facades\DB;
use Larapen\LaravelDistance\Libraries\mysql\DistanceHelper;
use Throwable;

/**
 * Database mutations triggered by setting changes that go beyond the
 * Setting row itself — date-format invalidation, charset/collation fix
 * for diacritics, MySQL distance-function rebuilds.
 */
trait DatabaseSideEffects
{
	/**
	 * Wipe stored date / datetime format strings whenever the format MODE flips.
	 *
	 * The PHP-style and ISO-style format syntaxes are not interchangeable — a value
	 * that's valid under one mode is meaningless under the other. Clearing the
	 * global formats AND every per-language / per-country override forces fall-
	 * through to the new-mode default until the admin re-enters them.
	 *
	 * The wipe of the two `app_date_format` / `app_datetime_format` rows uses
	 * `DB::table()` rather than Eloquent so we don't fire a recursive observer
	 * dispatch on those rows.
	 */
	private function resetDateFormatsIfDateModeChanged(Setting $setting): void
	{
		if ($setting->key !== 'app_php_specific_date_format') return;
		if (!$setting->wasChanged('value')) return;
		
		try {
			DB::table($setting->getTable())
				->whereIn('key', ['app_date_format', 'app_datetime_format'])
				->update(['value' => null]);
		} catch (Throwable) {
		}
		
		// `date_format` / `datetime_format` are plain fillable string columns on
		// both models — no accessors, no mutators, no observers keyed to either
		// column. Bulk-update them in a single statement instead of hydrating
		// every row and calling `save()` per row.
		//
		// The per-row `save()` loop previously fired `InvalidatesCache::saved`
		// N times, each bumping the model's cache version AND cascading through
		// `$invalidatesCacheFor` (Country → SubAdmin1, SubAdmin2, City, Currency)
		// — i.e., ~1200+ version bumps for a single date-mode flip. We replace
		// that with one bulk UPDATE + one explicit `caching()->invalidateMultiple()`
		// covering the same model namespaces, so caches stay consistent without
		// the per-row event storm.
		try {
			Language::query()
				->withoutGlobalScopes([ActiveScope::class])
				->update(['date_format' => null, 'datetime_format' => null]);
			
			caching()->invalidate(Language::class);
		} catch (Throwable) {
		}
		
		try {
			Country::query()
				->withoutGlobalScopes([ActiveScope::class, LocalizedScope::class])
				->update(['date_format' => null, 'datetime_format' => null]);
			
			// Mirror the cascade `Country::$invalidatesCacheFor` would have
			// triggered on a per-row `save()` (kept in sync with that property).
			caching()->invalidateMultiple([
				Country::class,
				SubAdmin1::class,
				SubAdmin2::class,
				City::class,
				Currency::class,
			]);
		} catch (Throwable) {
		}
	}
	
	/**
	 * Apply the connection charset / collation fix only when diacritic support is
	 * being enabled — the inverse direction is non-destructive and doesn't need
	 * a forced ALTER pass.
	 */
	private function fixCharsetIfDiacriticsEnabled(Setting $setting): void
	{
		if ($setting->key !== 'listings_list_enable_diacritics') return;
		if (!$setting->wasChanged('value')) return;
		if ((string)$setting->value !== '1') return;
		
		try {
			DBEncoding::tryToFixConnectionCharsetAndCollation();
		} catch (Throwable) {
		}
	}
	
	/**
	 * Drop both candidate MySQL distance functions and recreate the selected one
	 * so the new formula takes effect on the next distance query. The cached
	 * "function exists" probe also has to go — otherwise the next caller picks
	 * the stale entry and skips the recreate path.
	 */
	private function refreshDistanceCalculationFunction(Setting $setting): void
	{
		if ($setting->key !== 'listings_list_distance_calculation_formula') return;
		if (!$setting->wasChanged('value')) return;
		
		$newFormula = $setting->value;
		if (empty($newFormula) || !is_string($newFormula)) return;
		
		foreach (['haversine', 'orthodromy'] as $fn) {
			try {
				DB::statement('DROP FUNCTION IF EXISTS ' . $fn);
			} catch (Throwable) {
			}
			try {
				$cacheId = 'checkIfMySQLFunctionExists.' . $fn;
				if (cache()->has($cacheId)) {
					cache()->forget($cacheId);
				}
			} catch (Throwable) {
			}
		}
		
		try {
			if (!DistanceHelper::checkIfDistanceCalculationFunctionExists($newFormula)) {
				DistanceHelper::createDistanceCalculationFunction($newFormula);
			}
		} catch (Throwable) {
		}
	}
}

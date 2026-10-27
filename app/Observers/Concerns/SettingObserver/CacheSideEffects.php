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

use App\Helpers\Services\CacheRegenerator;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Compiled-cache rebuilds triggered by setting saves.
 *
 * Two effects live here, both self-gated with `wasChanged()` so a save that
 * doesn't actually mutate the value is a no-op:
 *
 *   - `recompileStyleCssIfNeeded()` — rebuilds the compiled `style.css` bundle
 *     when a style / header / footer field changes. The
 *     `EnsureCachedAssetsExist` middleware also regenerates lazily on the next
 *     request, so a missed eager call here just delays the new CSS by one hop.
 *   - `regenerateCachesIfNeeded()` — busts the route + config caches via
 *     `CacheRegenerator`. The Setting row itself is invalidated in the per-row
 *     cache by `InvalidatesCache`, but compiled framework caches still need an
 *     explicit kick.
 */
trait CacheSideEffects
{
	private function recompileStyleCssIfNeeded(Setting $setting): void
	{
		if (!in_array($setting->group, ['style', 'header', 'footer'], true)) return;
		if (!$setting->wasChanged('value')) return;
		
		try {
			Artisan::call('style-css:cache');
		} catch (Throwable) {
			// Silent — cache will regenerate on the next request.
		}
	}
	
	private function regenerateCachesIfNeeded(Setting $setting): void
	{
		if (!$setting->wasChanged('value')) return;
		
		CacheRegenerator::regenerateAllCaches();
	}
}

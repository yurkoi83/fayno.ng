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

use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Helpers\Common\PhpArrayFile;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Filesystem-touching side effects for the Setting save chain.
 *
 * Each method is keyed to one or more flat keys and short-circuits when the
 * saved row isn't one of them — composing the trait is free on saves that don't
 * concern these effects.
 */
trait FileSideEffects
{
	/**
	 * Delete the previous file when an image-bearing setting flips to a new path.
	 *
	 * The flat-schema controller delegates upload + resize to each Setting subclass'
	 * passedValidation(); none of those hooks bother to remove the file they're
	 * replacing, so without this pass every logo / favicon / og_image change leaves
	 * an orphan on the disk.
	 *
	 * The bundled defaults (config('larapen.images.defaults.*')) are never deleted
	 * from the disk: their DB path may be cleared or overwritten, but the underlying
	 * shared asset must survive. `$old` is therefore checked against *every*
	 * default path — not just the one mapped to the saved key — so a value that
	 * points at any bundled default (e.g., an `app_logo` row still holding the
	 * avatar default) is left untouched on disk.
	 */
	private function cleanupReplacedFile(Setting $setting): void
	{
		$imageKeys = [
			'app_logo',
			'app_logo_dark',
			'app_logo_light',
			'app_favicon',
			'social_share_og_image',
		];
		
		if (!in_array($setting->key, $imageKeys, true)) return;
		if (!$setting->wasChanged('value')) return;
		
		$old = $setting->getOriginal('value');
		if (!is_string($old) || $old === '') return;
		
		// Guard *all* bundled defaults from disk deletion — the DB path can change,
		// but the shared asset on disk must stay.
		foreach ((array) config('larapen.images.defaults', []) as $defaultPath) {
			if (is_string($defaultPath) && $defaultPath !== '' && str_contains($old, $defaultPath)) {
				return;
			}
		}
		
		try {
			$disk = StorageDisk::getDisk();
			if ($disk->exists($old)) {
				$disk->delete($old);
			}
		} catch (Throwable) {
			// Storage is best-effort — the orphan is recoverable, the save isn't.
		}
	}
	
	/**
	 * Force `public/robots.txt` regeneration when robots-related settings change.
	 *
	 * `RobotsTxtTrait::checkRobotsTxtFile()` only writes the file when it's missing,
	 * so deleting the existing copy is what actually picks up edits to the textarea
	 * or the sitemap-indexes toggle on the next front request.
	 */
	private function forceRobotsTxtRegeneration(Setting $setting): void
	{
		if (!in_array($setting->key, ['seo_robots_txt', 'seo_robots_txt_sm_indexes'], true)) return;
		if (!$setting->wasChanged('value')) return;
		
		try {
			$path = public_path('robots.txt');
			if (File::exists($path)) {
				File::delete($path);
			}
		} catch (Throwable) {
		}
	}
	
	/**
	 * Rewrite `config/routes.php` from the canonical `config/larapen/routes.php`
	 * template.
	 *
	 * The compiled file is the only thing the URL builder reads at runtime, so
	 * without regenerating it after a permalink-related change, the new pattern
	 * never ships. `multi_country_urls` is forced off when the Domain Mapping
	 * addon is active — domain-mapping's own URL strategy is incompatible with
	 * country-prefix routing.
	 */
	private function regenerateDynamicRoutesFile(Setting $setting): void
	{
		$relevantKeys = ['seo_listing_permalink', 'seo_listing_permalink_ext', 'seo_multi_country_urls'];
		if (!in_array($setting->key, $relevantKeys, true)) return;
		if (!$setting->wasChanged('value')) return;
		
		try {
			// Force `seo_multi_country_urls` off when the Domain Mapping addon is
			// active — its own URL strategy is incompatible with country-prefix
			// routing. setOverride() shadows whatever the admin just saved so the
			// regenerated routes template (which reads via setting()) sees `0`.
			if ($setting->key === 'seo_multi_country_urls' && addon_active('domainmapping')) {
				SettingService::setOverride('seo_multi_country_urls', false);
			}
			
			$origRoutes = PhpArrayFile::getFileContent(config_path('larapen/routes.php'));
			PhpArrayFile::writeFile(config_path('routes.php'), $origRoutes);
		} catch (Throwable $e) {
			notification($e->getMessage(), 'error');
		}
	}
}

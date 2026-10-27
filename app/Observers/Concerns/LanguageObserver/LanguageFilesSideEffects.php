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

namespace App\Observers\Concerns\LanguageObserver;

use App\Helpers\Services\Lang\LangManager;
use App\Models\Language;
use Illuminate\Support\Facades\Artisan;

/**
 * On-disk language file management.
 *
 *   - On creation → seed the new locale's PHP translation files by copying
 *     the current default language's tree, so the admin has a complete
 *     starting point to translate.
 *   - On deletion → remove the locale's translation files and clear the
 *     fileinput plugin's locale cache so the dropdown stops listing it.
 *   - On any save → regenerate the fileinput locale cache so newly added /
 *     re-activated languages show up immediately in the upload widget.
 */
trait LanguageFilesSideEffects
{
	private function seedLanguageFilesFromDefault(Language $language): void
	{
		$defaultLang = Language::where('default', 1)->first();
		if (empty($defaultLang)) return;
		
		(new LangManager())->copyFiles($defaultLang->code, $language->code);
	}
	
	/**
	 * Block deletion of the English language. Its `lang/en/*` files are the
	 * translation source every other locale is seeded from, and the app's
	 * fallback locale — losing them would break translations site-wide.
	 * Returning `false` cancels the delete (Eloquent observer convention).
	 */
	private function preventEnglishLanguageDeletion(Language $language): bool
	{
		if ($language->code !== 'en') {
			return true;
		}
		
		notification(trans('admin.You cannot delete the English language'), 'warning');
		
		return false;
	}
	
	private function removeLanguageFiles(Language $language): void
	{
		(new LangManager())->removeFiles($language->code);
	}
	
	/**
	 * Regenerate the fileinput plugin's per-locale list. Cheap enough to fire
	 * on every save — it's just an Artisan command writing one cache file.
	 */
	private function regenerateFileinputLocaleCache(): void
	{
		Artisan::call('fileinput:cache-locales');
	}
}

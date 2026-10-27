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

use App\Helpers\Common\DotenvEditor;
use App\Models\Language;

/**
 * Keep the "default language" invariant in sync with `.env`.
 *
 *   - Flipping a language to default → unset the previous default, set this
 *     one, mirror the locale code into `APP_LOCALE` in `.env`.
 *   - Flipping a language away from default → only allowed when ANOTHER
 *     language is already / will be the default.
 *   - Flipping the default to inactive → blocked outright; the app needs an
 *     active default to function.
 *
 * `enforceDefaultLanguageInvariants()` is the entry point used from
 * `updating()`. It returns `false` when the change should be cancelled (the
 * Eloquent observer convention for vetoing a save).
 */
trait DefaultLocaleSideEffects
{
	/**
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	private function enforceDefaultLanguageInvariants(Language $language): bool
	{
		$original = $language->getOriginal();
		
		if ($language->default == ($original['default'] ?? null)) {
			// Default flag didn't move — only one corner case left: don't let
			// the admin disable the default language (`active = 0`).
			if ($language->default == 1 && $language->active != 1) {
				notification(trans('admin.You cannot disable the default language'), 'warning');
				
				return false;
			}
			
			return true;
		}
		
		// Default flag flipped.
		if ($language->default == 1 || $language->default == 'on') {
			$this->promoteToDefaultLanguage($language->code);
			
			return true;
		}
		
		// Demoting from default — make sure another row will still hold the flag.
		$existingDefaultLang = Language::where('default', 1)->where('code', '!=', $language->code);
		if ($existingDefaultLang->count() <= 0) {
			notification(trans('admin.The app requires a default language'), 'warning');
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Atomically swap the default flag onto the new code and mirror the locale
	 * into `.env` so subsequent boots pick it up.
	 *
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	private function promoteToDefaultLanguage(string $code): void
	{
		// Unset the old default(s) — `whereIn` so inactive rows are also reset.
		Language::whereIn('active', [0, 1])->update(['default' => 0]);
		
		Language::where('code', '=', $code)->update(['default' => 1]);
		
		DotenvEditor::setKey('APP_LOCALE', $code);
		DotenvEditor::save();
	}
	
	/**
	 * Block deletion of the language whose code matches the active app locale.
	 * Returning `false` cancels the delete (Eloquent observer convention).
	 */
	private function preventDefaultLanguageDeletion(Language $language): bool
	{
		if ($language->code != config('appLang.code')) {
			return true;
		}
		
		notification(trans('admin.You cannot delete the default language'), 'warning');
		
		return false;
	}
}

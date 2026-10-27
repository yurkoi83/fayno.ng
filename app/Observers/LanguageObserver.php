<?php

namespace App\Observers;

use App\Models\Language;
use App\Observers\Concerns\LanguageObserver\DefaultLocaleSideEffects;
use App\Observers\Concerns\LanguageObserver\LanguageFilesSideEffects;
use App\Observers\Concerns\LanguageObserver\TranslationsSideEffects;

/**
 * Language model observer.
 *
 * Per-key concerns live in small traits under
 * `App\Observers\Concerns\LanguageObserver\` so this file stays an
 * orchestrator. Trait helpers self-gate where appropriate; the demo-domain
 * guard runs first on every hook, so visitors poking the demo site can't
 * mutate the language fixture.
 */
class LanguageObserver extends BaseObserver
{
	use DefaultLocaleSideEffects;
	use LanguageFilesSideEffects;
	use TranslationsSideEffects;

	public function created(Language $language): void
	{
		$this->seedLanguageFilesFromDefault($language);
	}

	/**
	 * @throws \App\Exceptions\Custom\CustomException
	 */
	public function updating(Language $language): bool
	{
		return $this->enforceDefaultLanguageInvariants($language);
	}

	public function deleting(Language $language): bool
	{
		if (!$this->preventDefaultLanguageDeletion($language)) {
			return false;
		}

		if (!$this->preventEnglishLanguageDeletion($language)) {
			return false;
		}

		$this->forgetAllTranslationsForLocale($language->code);

		if ($language->deleteTranslationFiles) {
			$this->removeLanguageFiles($language);
		}

		return true;
	}

	public function saved(Language $language): void
	{
		$this->regenerateFileinputLocaleCache();
	}
}

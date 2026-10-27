<?php

namespace App\Observers;

use App\Helpers\Services\CacheRegenerator;
use App\Models\Setting;
use App\Observers\Concerns\SettingObserver\CacheSideEffects;
use App\Observers\Concerns\SettingObserver\DatabaseSideEffects;
use App\Observers\Concerns\SettingObserver\EnvSideEffects;
use App\Observers\Concerns\SettingObserver\FileSideEffects;
use App\Observers\Concerns\SettingObserver\RuntimeSideEffects;
use App\Services\SettingService;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Setting model observer (flat schema).
 *
 * After the schema swap to one-row-per-setting, every save fires per-row. This
 * observer reacts to each saved row with the side effects the legacy
 * `App\Observers\Traits\Setting\*` traits used to handle (file deletion, .env
 * writes, robots / routes regen, date-format reset, cookie / session updates,
 * style-CSS recompile, …).
 *
 * The per-key effects are split across small traits under
 * `App\Observers\Concerns\SettingObserver\` so this file stays an orchestrator.
 * Each trait method short-circuits on saves whose flat key it doesn't care
 * about (and on no-op saves via `wasChanged()`), so composing all of them costs
 * nothing on unrelated saves.
 *
 * Effects fire per row. Multi-key form submissions therefore trigger each
 * matching effect once per row — wasteful for the .env writes / CSS recompile
 * but simple, with no per-request buffering.
 */
class SettingObserver extends BaseObserver
{
	use CacheSideEffects;
	use DatabaseSideEffects;
	use EnvSideEffects;
	use FileSideEffects;
	use RuntimeSideEffects;

	public function saved(Setting $setting): void
	{
		// SettingService keeps a per-process memo of resolved setting() reads;
		// drop it so the side-effect handlers below — and any subsequent reads
		// in this request — see the just-saved value instead of the pre-save
		// cascade result.
		SettingService::flushResolvedMemo();

		// FileSideEffects.
		$this->cleanupReplacedFile($setting);
		$this->forceRobotsTxtRegeneration($setting);
		$this->regenerateDynamicRoutesFile($setting);

		// DatabaseSideEffects.
		$this->resetDateFormatsIfDateModeChanged($setting);
		$this->fixCharsetIfDiacriticsEnabled($setting);
		$this->refreshDistanceCalculationFunction($setting);

		// RuntimeSideEffects.
		$this->clearLanguageDetectionSessions($setting);
		$this->setDisplayModeCookie($setting);
		$this->updateDefaultCountrySession($setting);

		// EnvSideEffects.
		$this->writeOptimizationEnv($setting);
		$this->writeQueueEnv($setting);
		$this->writeSmsEnv($setting);

		// CacheSideEffects.
		$this->recompileStyleCssIfNeeded($setting);
		$this->regenerateCachesIfNeeded($setting);
	}

	public function deleted(Setting $setting): void
	{
		SettingService::flushResolvedMemo();

		// Delete events have no `wasChanged('value')` signal — the trait helpers
		// would short-circuit. Force the regen here so the compiled caches don't
		// keep referencing a row that's gone.
		if (in_array($setting->group, ['style', 'header', 'footer'], true)) {
			try {
				Artisan::call('style-css:cache');
			} catch (Throwable) {
				// Silent — cache will regenerate on the next request.
			}
		}

		CacheRegenerator::regenerateAllCaches();
	}
}

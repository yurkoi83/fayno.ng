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

use App\Helpers\Common\DBUtils;
use Illuminate\Support\Facades\DB;

/**
 * Drop every per-row translation that targets a language being deleted.
 *
 * Spatie's translatable models store all translations as a JSON object on
 * each translatable column (`{"en": "...", "fr": "..."}`); to remove the
 * whole locale we need `JSON_REMOVE` over every translatable column on every
 * translatable model. Older MySQL / MariaDB without JSON-function support
 * are silently skipped (the legacy translations stay; nothing's broken,
 * they're just dead keys until the next manual cleanup).
 */
trait TranslationsSideEffects
{
	private function forgetAllTranslationsForLocale(string $locale): void
	{
		if (!$this->databaseSupportsJsonFunctions()) return;
		
		$modelClasses = DBUtils::getAppModelClasses(translatable: true);
		if (empty($modelClasses)) return;
		
		foreach ($modelClasses as $modelClass) {
			$model = new $modelClass;
			
			$columns = method_exists($model, 'getTranslatableAttributes')
				? $model->getTranslatableAttributes()
				: [];
			if (empty($columns)) continue;
			
			$tableName = $model->getTable();
			foreach ($columns as $column) {
				$value = 'JSON_REMOVE(' . $column . ', \'$.' . $locale . '\')';
				$filter = $column . ' LIKE \'%"' . $locale . '":%\'';
				
				DB::table($tableName)
					->whereNotNull($column)
					->whereRaw($column . ' != ""')
					->whereRaw($filter)
					->update([$column => DB::raw($value)]);
			}
		}
	}
	
	/**
	 * JSON column manipulation needs MySQL 5.7+ or MariaDB 10.2.3+. Anything
	 * older silently skips the wipe — the orphan keys are harmless.
	 */
	private function databaseSupportsJsonFunctions(): bool
	{
		return (!DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('5.7'))
			|| (DBUtils::isMariaDB() && DBUtils::isMySqlMinVersion('10.2.3'));
	}
}

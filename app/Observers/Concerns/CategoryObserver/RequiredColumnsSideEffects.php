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

namespace App\Observers\Concerns\CategoryObserver;

use App\Enums\CategoryType;
use App\Models\Category;

/**
 * Fill required columns that the controller doesn't set explicitly.
 *
 * The `type` column is an enum on the categories table — bulk imports and
 * admin-form edits don't always carry a value, so we backfill from the
 * parent (when present) or fall back to `CategoryType::Classified`.
 *
 * The empty-check reads the raw attribute via `getAttributes()` rather
 * than `$category->type` because the `CategoryType` cast resolves a
 * stored value to an enum case — we specifically want to detect rows
 * that have never received a `type` (NULL / empty raw column) so we can
 * fill them in before the row is persisted.
 */
trait RequiredColumnsSideEffects
{
	private function applyRequiredColumnFallbacks(Category $category): Category
	{
		$rawType = $category->getAttributes()['type'] ?? null;
		if (empty($rawType)) {
			$parentRawType = !empty($category->parent)
				? ($category->parent->getAttributes()['type'] ?? null)
				: null;
			
			$category->type = !empty($parentRawType)
				? $parentRawType
				: CategoryType::Classified->value;
		}
		
		return $category;
	}
}

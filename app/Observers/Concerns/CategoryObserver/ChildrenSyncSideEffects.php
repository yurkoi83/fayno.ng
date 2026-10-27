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

use App\Models\Category;

/**
 * Propagate the `active` flag up to the parent and (optionally) down to the
 * children whenever a category is activated or deactivated.
 *
 *   - Activating a child re-activates an inactive parent so the path from the
 *     root stays reachable. Both directions only flip nodes that DON'T already
 *     match the target state, which prevents the recursive observer chain
 *     from looping back through the same branch twice.
 *   - Deactivating a parent deactivates all children regardless of the form
 *     toggle (data integrity: a child of a hidden parent can't be reachable).
 *   - Activating a parent only cascades to children when the admin opted in
 *     via the "Activate children" checkbox on the form.
 */
trait ChildrenSyncSideEffects
{
	private function syncCategoryActiveStateAcrossTree(Category $category): void
	{
		if ($category->active == 1) {
			$this->reactivateParentIfDisabled($category);
			$this->maybeActivateChildren($category);
		} else {
			$this->deactivateChildren($category);
		}
	}
	
	private function reactivateParentIfDisabled(Category $category): void
	{
		if (empty($category->parent_id)) return;
		
		$parentCat = Category::find($category->parent_id);
		if (empty($parentCat)) return;
		
		if ($parentCat->active != 1) {
			$parentCat->active = 1;
			$parentCat->save();
		}
	}
	
	private function maybeActivateChildren(Category $category): void
	{
		if (!request()->has('activateChildren')) return;
		if (!(bool)request()->input('activateChildren')) return;
		
		$subCats = Category::childrenOf($category->id)->get();
		if ($subCats->count() <= 0) return;
		
		foreach ($subCats as $subCat) {
			if ($subCat->active != 1) {
				$subCat->active = 1;
				$subCat->save();
			}
		}
	}
	
	private function deactivateChildren(Category $category): void
	{
		$subCats = Category::childrenOf($category->id)->get();
		if ($subCats->count() <= 0) return;
		
		foreach ($subCats as $subCat) {
			if ($subCat->active != 0) {
				$subCat->active = 0;
				$subCat->save();
			}
		}
	}
}

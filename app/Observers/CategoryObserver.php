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

namespace App\Observers;

use App\Concerns\HasNestedColumns;
use App\Models\Category;
use App\Observers\Concerns\CategoryObserver\CascadingDeletesSideEffects;
use App\Observers\Concerns\CategoryObserver\ChildrenSyncSideEffects;
use App\Observers\Concerns\CategoryObserver\MenuItemSyncSideEffects;
use App\Observers\Concerns\CategoryObserver\RequiredColumnsSideEffects;

/**
 * Category model observer.
 *
 * Per-key concerns live in small traits under
 * `App\Observers\Concerns\CategoryObserver\`. The shared nested-set bookkeeping
 * (creating / updating / deleting tree positions) comes in via
 * `App\Concerns\HasNestedColumns` since the same logic also runs on the menu
 * tree.
 */
class CategoryObserver extends BaseObserver
{
	use HasNestedColumns;
	use CascadingDeletesSideEffects;
	use ChildrenSyncSideEffects;
	use MenuItemSyncSideEffects;
	use RequiredColumnsSideEffects;
	
	public function creating(Category $category): mixed
	{
		$category = $this->applyRequiredColumnFallbacks($category);
		
		return $this->creatingNestedItem($category);
	}
	
	public function updating(Category $category): mixed
	{
		$category = $this->applyRequiredColumnFallbacks($category);
		
		return $this->updatingNestedItem($category);
	}
	
	public function deleting(Category $category): void
	{
		$this->deletingNestedItem($category);
		$this->deleteCategoryRelatedRecords($category);
	}
	
	public function updated(Category $category): void
	{
		$this->syncCategoryActiveStateAcrossTree($category);
		$this->syncRelatedMenuItemsOnUpdate($category);
	}
	
	public function deleted(Category $category): void
	{
		$this->removeRelatedMenuItemsForCategory($category);
	}
}

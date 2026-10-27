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

use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Models\Page;
use App\Observers\Concerns\PageObserver\MenuItemSyncSideEffects;

/**
 * Page model observer.
 *
 * The MenuItem-sync concern lives in a trait under
 * `App\Observers\Concerns\PageObserver\` (mirrors the equivalent
 * CategoryObserver split). The single picture cleanup on `deleting()` is small
 * enough to stay inline.
 */
class PageObserver extends BaseObserver
{
	use MenuItemSyncSideEffects;
	
	public function deleting(Page $page): void
	{
		// Drop the page's standalone picture from disk. Skipped silently when
		// the path is empty or already missing.
		if (empty($page->image_path)) return;
		
		$disk = StorageDisk::getDisk();
		if ($disk->exists($page->image_path)) {
			$disk->delete($page->image_path);
		}
	}
	
	public function updated(Page $page): void
	{
		$this->syncRelatedMenuItemsOnUpdate($page);
	}
	
	public function deleted(Page $page): void
	{
		$this->removeRelatedMenuItemsForPage($page);
	}
}

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

namespace App\Observers\Concerns\PageObserver;

use App\Enums\MenuItemUrlType;
use App\Models\Language;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Scopes\ActiveScope;

/**
 * Keep the `page.<slug>` menu items aligned with the underlying page.
 *
 * Mirrors the equivalent CategoryObserver concern: slug change → rewrite the
 * route name; name change → re-translate the label across every language;
 * active flipped off → deactivate the menu item; page deleted → drop the
 * menu item. Lookup uses `getOriginal('slug')` on update (the new slug isn't
 * persisted yet from the menu's perspective), and the live slug on delete.
 */
trait MenuItemSyncSideEffects
{
	private function syncRelatedMenuItemsOnUpdate(Page $page): void
	{
		if (!$page->isDirty(['slug', 'name', 'active'])) return;
		
		$prefix = 'page.';
		$routeName = "{$prefix}{$page->getOriginal('slug')}";
		
		$menuItems = MenuItem::query()
			->with(['menu'])
			->where('url_type', MenuItemUrlType::Route->value)
			->where('route_name', $routeName)
			->get();
		
		foreach ($menuItems as $menuItem) {
			if (!$page->active) {
				$menuItem->update(['active' => 0]);
				continue;
			}
			
			if ($page->isDirty('slug')) {
				$menuItem->update(['route_name' => "{$prefix}{$page->slug}"]);
			}
			
			if ($page->isDirty('name')) {
				$this->retranslateMenuItemLabel($menuItem, $page);
			}
		}
	}
	
	private function removeRelatedMenuItemsForPage(Page $page): void
	{
		$prefix = 'page.';
		$routeName = "{$prefix}{$page->slug}";
		
		$menuItems = MenuItem::query()
			->with(['menu'])
			->where('url_type', MenuItemUrlType::Route->value)
			->where('route_name', $routeName)
			->get();
		
		foreach ($menuItems as $menuItem) {
			$menuItem->delete();
		}
	}
	
	/**
	 * Pull every language (including inactive ones, so re-activating a language
	 * later resurrects its translation) and assign the page's translated name
	 * to the matching label slot on the menu item.
	 */
	private function retranslateMenuItemLabel(MenuItem $menuItem, Page $page): void
	{
		$currentLabel = method_exists($menuItem, 'getLabelTranslations')
			? $menuItem->getLabelTranslations()
			: [];
		
		$languages = Language::query()->withoutGlobalScopes([ActiveScope::class])->get(['code']);
		if ($languages->count() > 0) {
			foreach ($languages as $language) {
				$currentLabel[$language->code] = $page->getTranslation('name', $language->code);
			}
		}
		
		if (!empty($currentLabel)) {
			$menuItem->setTranslations('label', $currentLabel);
			$menuItem->save();
		}
	}
}

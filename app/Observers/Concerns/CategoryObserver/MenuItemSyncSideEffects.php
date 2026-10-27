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

use App\Enums\MenuItemUrlType;
use App\Models\Category;
use App\Models\Language;
use App\Models\MenuItem;
use App\Models\Scopes\ActiveScope;

/**
 * Keep the `category.<slug>` menu items aligned with the underlying category.
 *
 *   - Slug change → rewrite the route name on every matching menu item.
 *   - Name change → re-translate the menu item label across every language.
 *   - Active flipped off → deactivate the menu item (it'd 404 otherwise).
 *   - Category deleted → drop the menu item entirely.
 *
 * The "find by old slug" lookup uses `getOriginal('slug')` so the menu items
 * are still resolvable on update; the `removeRelatedMenuItemsForCategory`
 * helper uses the live slug since by then the slug change (if any) has
 * already been persisted.
 */
trait MenuItemSyncSideEffects
{
	private function syncRelatedMenuItemsOnUpdate(Category $category): void
	{
		if (!$category->isDirty(['slug', 'name', 'active'])) return;
		
		$prefix = 'category.';
		$routeName = "{$prefix}{$category->getOriginal('slug')}";
		
		$menuItems = MenuItem::query()
			->with(['menu'])
			->where('url_type', MenuItemUrlType::Route->value)
			->where('route_name', $routeName)
			->get();
		
		foreach ($menuItems as $menuItem) {
			if (!$category->active) {
				$menuItem->update(['active' => 0]);
				continue;
			}
			
			if ($category->isDirty('slug')) {
				$menuItem->update(['route_name' => "{$prefix}{$category->slug}"]);
			}
			
			if ($category->isDirty('name')) {
				$this->retranslateMenuItemLabel($menuItem, $category);
			}
		}
	}
	
	private function removeRelatedMenuItemsForCategory(Category $category): void
	{
		$prefix = 'category.';
		$routeName = "{$prefix}{$category->slug}";
		
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
	 * later resurrects its translation) and assign the category's translated
	 * name to the matching label slot on the menu item.
	 */
	private function retranslateMenuItemLabel(MenuItem $menuItem, Category $category): void
	{
		$currentLabel = method_exists($menuItem, 'getLabelTranslations')
			? $menuItem->getLabelTranslations()
			: [];
		
		$languages = Language::query()->withoutGlobalScopes([ActiveScope::class])->get(['code']);
		if ($languages->count() > 0) {
			foreach ($languages as $language) {
				$currentLabel[$language->code] = $category->getTranslation('name', $language->code);
			}
		}
		
		if (!empty($currentLabel)) {
			$menuItem->setTranslations('label', $currentLabel);
			$menuItem->save();
		}
	}
}

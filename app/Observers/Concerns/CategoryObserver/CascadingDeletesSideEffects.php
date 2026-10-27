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

use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Listing;

/**
 * Manual cascade for category deletion. Custom fields, listings, the on-disk
 * picture, and child categories all need to be cleared explicitly because the
 * DB schema doesn't carry the cascading FKs (and the picture obviously isn't
 * a row at all).
 *
 * The picture deletion guards against three categories of "default" pictures
 * so a misconfigured `image_path` can never wipe the bundled defaults that
 * other categories also point at.
 *
 * `deleteChildrenRecursively()` is provided by the shared `HasNestedColumns`
 * concern that the host observer also composes.
 */
trait CascadingDeletesSideEffects
{
	private function deleteCategoryRelatedRecords(Category $category): void
	{
		$this->deleteCategoryCustomFields($category);
		$this->deleteCategoryListings($category);
		$this->deleteCategoryPictureFile($category);
		$this->deleteChildrenRecursively($category);
	}
	
	private function deleteCategoryCustomFields(Category $category): void
	{
		$catFields = CategoryField::where('category_id', $category->id)->get();
		if ($catFields->count() > 0) {
			foreach ($catFields as $catField) {
				$catField->delete();
			}
		}
	}
	
	private function deleteCategoryListings(Category $category): void
	{
		$listings = Listing::where('category_id', $category->id);
		if ($listings->count() > 0) {
			foreach ($listings->cursor() as $listing) {
				$listing->delete();
			}
		}
	}
	
	/**
	 * Three layers of "default" pictures are protected:
	 *   - the global default (`fa-folder-default.png`)
	 *   - the per-skin folder icon (`fa-folder-<skin>.png`)
	 *   - any picture under the active skin's category directory
	 */
	private function deleteCategoryPictureFile(Category $category): void
	{
		if (empty($category->image_path)) return;
		
		$defaultPicture = 'app/default/categories/fa-folder-default.png';
		$skin = setting('style_skin', 'default');
		$skinPicture = 'app/default/categories/fa-folder-' . $skin . '.png';
		$skinDirectory = 'app/categories/' . $skin . '/';
		
		if (str_contains($category->image_path, $defaultPicture)) return;
		if (str_contains($category->image_path, $skinPicture)) return;
		if (str_contains($category->image_path, $skinDirectory)) return;
		
		$disk = StorageDisk::getDisk();
		if ($disk->exists($category->image_path)) {
			$disk->delete($category->image_path);
		}
	}
}

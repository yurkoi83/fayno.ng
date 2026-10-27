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

namespace App\Models\Traits;

use App\Helpers\Common\Arr;
use App\Models\Category;
use App\Models\ListingValue;
use Illuminate\Support\Collection;

trait CategoryFieldTrait
{
	/**
	 * Get Fields details
	 *
	 * @param null $listingId
	 * @param null $languageCode (Required for AJAX Requests in v4.8 and lower)
	 */
	public static function getFields($catId, $listingId = null, $languageCode = null): Collection
	{
		$fields = [];
		
		// Make sure that the category nested IDs variable is not empty
		if (empty($catId)) {
			return collect();
		}
		
		// Get Listing's Custom Fields values
		$listingFieldsValues = collect();
		if (!empty($listingId) && trim($listingId) != '') {
			// Cache Parameters
			$cacheParams = [
				'action'    => 'get.listingValues',
				'listingId' => $listingId,
			];
			
			// Cached Query
			$listingFieldsValues = caching()->remember(ListingValue::class, $cacheParams, function () use ($listingId) {
				return ListingValue::where('listing_id', $listingId)->get();
			});
			
			$listingFieldsValues = self::keyingByFieldId($listingFieldsValues);
		}
		
		$parentIds = Category::getParentsIds($catId);
		
		// Cache Parameters
		$classBaseName = str(class_basename(self::class))->camel()->toString();
		$relations = ['field', 'field.options'];
		$cacheParams = [
			'action'     => "get.{$classBaseName}",
			'relations'  => implode(',', $relations),
			'categoryId' => $catId,
			'parentIds'  => implode(',', $parentIds),
		
		];
		
		// Get Category's fields
		$catFields = caching()->remember(self::class, $cacheParams, function () use ($relations, $catId, $parentIds) {
			$catFields = self::with($relations);
			if (!empty($parentIds)) {
				$catFields = $catFields->where(function ($query) use ($parentIds) {
					$i = 0;
					foreach ($parentIds as $parentId) {
						if ($i == 0) {
							$query->where(function ($q) use ($parentId) {
								$q->where('category_id', $parentId)->availableForSubCats();
							});
						} else {
							$query->orWhere(function ($q) use ($parentId) {
								$q->where('category_id', $parentId)->availableForSubCats();
							});
						}
						$i++;
					}
				});
				$catFields = $catFields->orWhere('category_id', $catId);
			} else {
				$catFields = $catFields->where('category_id', $catId);
			}
			
			return $catFields->orderBy('position')->get();
		});
		
		// Match Fields & Fields Values
		if ($catFields->count() > 0) {
			foreach ($catFields as $key => $catField) {
				if (!empty($catField->field)) {
					$fields[$key] = Arr::toObject($catField->field->toArray());
					
					if (isset($fields[$key]->options)) {
						$fields[$key]->options = collect($fields[$key]->options);
					}
					
					// Retrieve the Field's Default value
					if ($listingFieldsValues->count() > 0) {
						if ($listingFieldsValues->has($catField->field->id)) {
							$listingValue = $listingFieldsValues->get($catField->field->id);
							if (isset($listingValue->value)) {
								$defaultValue = $listingValue->value;
							} else {
								if ($catField->field->options->count() > 0) {
									$selectedOptions = [];
									foreach ($catField->field->options as $option) {
										if (isset($listingValue[$option->id])) {
											$selectedOptions[$option->id] = $option;
										}
									}
									$defaultValue = $selectedOptions;
								} else {
									$defaultValue = [];
								}
							}
							
							$fields[$key]->default_value = $defaultValue;
						}
					}
					
				}
			}
		}
		
		return collect($fields);
	}
	
	private static function keyingByFieldId($values): Collection
	{
		if (empty($values) || $values->count() <= 0) {
			return $values;
		}
		
		$listingValues = [];
		foreach ($values as $value) {
			if (!empty($value->option_id)) {
				$listingValues[$value->field_id][$value->option_id] = $value;
			} else {
				$listingValues[$value->field_id] = $value;
			}
		}
		
		return collect($listingValues);
	}
}

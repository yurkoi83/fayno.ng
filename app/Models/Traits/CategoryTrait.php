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

use App\Helpers\Common\DBUtils;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

trait CategoryTrait
{
	/**
	 * Return the sluggable configuration array for this model.
	 */
	public function sluggable(): array
	{
		return [
			'slug' => [
				'source' => ['slug', 'name'],
			],
		];
	}
	
	/**
	 * Count Listings by Category
	 */
	public static function countListingsPerCategory($cityId = null): array
	{
		$whereCity = '';
		if (!empty($cityId)) {
			$whereCity = ' AND tListing.city_id = ' . $cityId;
		}
		
		$categoriesTable = (new Category)->getTable();
		$listingsTable = (new Listing)->getTable();
		
		$sql = 'SELECT parent.id, COUNT(*) AS total
				FROM ' . DBUtils::table($categoriesTable) . ' AS node,
						' . DBUtils::table($categoriesTable) . ' AS parent,
						' . DBUtils::table($listingsTable) . ' AS tListing
				WHERE node.lft BETWEEN parent.lft AND parent.rgt
						AND node.id = tListing.category_id
						AND tListing.country_code = :countryCode' . $whereCity . '
						AND ((tListing.email_verified_at IS NOT NULL) AND (tListing.phone_verified_at IS NOT NULL))
						AND (tListing.archived_at IS NULL)
						AND (tListing.deleted_at IS NULL)
				GROUP BY parent.id';
		$bindings = [
			'countryCode' => config('country.code'),
		];
		$cats = DB::select($sql, $bindings);
		
		return collect($cats)->keyBy('id')->toArray();
	}
}

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

use App\Models\FieldOption;
use App\Models\ListingValue;

class FieldOptionObserver extends BaseObserver
{
	/**
	 * Listen to the Entry deleting event.
	 *
	 * @param FieldOption $fieldOption
	 * @return void
	 */
	public function deleting($fieldOption)
	{
		// Delete all Listings Custom Field's Values
		$listingValues = ListingValue::with(['field'])->where('value', $fieldOption->id)->get();
		if ($listingValues->count() > 0) {
			foreach ($listingValues as $listingValue) {
				$listingValue->delete();
			}
		}
	}
}

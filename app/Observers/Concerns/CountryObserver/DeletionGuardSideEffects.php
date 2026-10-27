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

namespace App\Observers\Concerns\CountryObserver;

use App\Models\Country;

/**
 * Pre-flight checks for country deletion.
 *
 * Returning `false` cancels the delete (Eloquent observer convention).
 *
 * Currently one rule: when the Domain Mapping addon is active you cannot
 * delete the country that's currently set as `localization_default_country_code`,
 * since the addon's per-country routing strategy assumes that row exists.
 */
trait DeletionGuardSideEffects
{
	private function preventDefaultCountryDeletionWhenDomainmappingActive(Country $country): bool
	{
		if (!addon_active('domainmapping')) return true;
		
		if (strtolower($country->code) != strtolower(setting('localization_default_country_code'))) {
			return true;
		}
		
		notification(trans('admin.Cannot delete the current country when the Domain Mapping addon is installed'), 'error');
		
		return false;
	}
}

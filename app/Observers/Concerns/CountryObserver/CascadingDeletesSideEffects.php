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

use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\SubAdmin1;
use App\Models\SubAdmin2;
use Addons\Domainmapping\Models\Domain;
use Throwable;

/**
 * Manual cascade for country deletion. The DB schema doesn't carry FK cascades
 * for these relations (some are wired by `country_code` rather than an FK to
 * `countries.id`), so the observer drains them in order: SubAdmin1 →
 * SubAdmin2 → City → Listing → addon Domain row.
 */
trait CascadingDeletesSideEffects
{
	private function deleteCountryRelatedRecords(Country $country): void
	{
		$this->deleteCountrySubAdmin1($country);
		$this->deleteCountrySubAdmin2($country);
		$this->deleteCountryCities($country);
		$this->deleteCountryListings($country);
		$this->deleteCountryDomainmappingRowIfAddonActive($country);
	}
	
	private function deleteCountrySubAdmin1(Country $country): void
	{
		$rows = SubAdmin1::inCountry($country->code);
		if ($rows->count() > 0) {
			foreach ($rows->cursor() as $row) {
				$row->delete();
			}
		}
	}
	
	private function deleteCountrySubAdmin2(Country $country): void
	{
		$rows = SubAdmin2::inCountry($country->code);
		if ($rows->count() > 0) {
			foreach ($rows->cursor() as $row) {
				$row->delete();
			}
		}
	}
	
	private function deleteCountryCities(Country $country): void
	{
		$cities = City::inCountry($country->code);
		if ($cities->count() > 0) {
			foreach ($cities->cursor() as $city) {
				$city->delete();
			}
		}
	}
	
	private function deleteCountryListings(Country $country): void
	{
		$listings = Listing::inCountry($country->code);
		if ($listings->count() > 0) {
			foreach ($listings->cursor() as $listing) {
				$listing->delete();
			}
		}
	}
	
	/**
	 * Drop the addon's Domain row if the addon is active. Wrapped in try/catch
	 * to be safe even when the addon is partially uninstalled.
	 */
	private function deleteCountryDomainmappingRowIfAddonActive(Country $country): void
	{
		if (!addon_active('domainmapping')) return;
		
		try {
			$domain = Domain::where('country_code', '=', $country->code)->first();
			if (!empty($domain)) {
				$domain->delete();
			}
		} catch (Throwable) {
		}
	}
}

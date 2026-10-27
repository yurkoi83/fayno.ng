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

namespace App\Observers\Concerns\ListingObserver;

use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Models\Listing;
use App\Models\ListingValue;
use App\Models\Payment;
use App\Models\Picture;
use App\Models\SavedListing;
use App\Models\Scopes\StrictActiveScope;
use App\Models\Scopes\ValidPeriodScope;
use App\Models\Thread;
use Addons\Reviews\Models\Review;
use Throwable;

/**
 * Manual cascade for listing deletion. Custom-field values, threads, saved
 * bookmarks, pictures, payments and (optionally) reviews each have to be
 * cleared explicitly because the schema doesn't carry FK cascades for these
 * relations — and the on-disk media folder isn't a row at all.
 *
 * Iterating with `cursor()` keeps memory steady on listings with lots of
 * pictures or messages.
 */
trait CascadingDeletesSideEffects
{
	private function deleteListingRelatedRecords(Listing $listing): void
	{
		$this->deleteListingValues($listing);
		$this->deleteListingThreads($listing);
		$this->deleteListingSavedBookmarks($listing);
		$this->deleteListingPictures($listing);
		$this->deleteListingPayments($listing);
		$this->deleteListingReviewsIfAddonActive($listing);
		$this->deleteListingMediaFolder($listing);
	}
	
	private function deleteListingValues(Listing $listing): void
	{
		$values = ListingValue::where('listing_id', $listing->id)->get();
		if ($values->count() > 0) {
			foreach ($values as $value) {
				$value->delete();
			}
		}
	}
	
	/**
	 * `forceDelete()` because Thread uses soft deletes — no point in keeping
	 * orphan tombstones once the listing is gone for good.
	 */
	private function deleteListingThreads(Listing $listing): void
	{
		$threads = Thread::where('listing_id', $listing->id);
		if ($threads->count() > 0) {
			foreach ($threads->cursor() as $thread) {
				$thread->forceDelete();
			}
		}
	}
	
	private function deleteListingSavedBookmarks(Listing $listing): void
	{
		$savedListings = SavedListing::where('listing_id', $listing->id);
		if ($savedListings->count() > 0) {
			foreach ($savedListings->cursor() as $savedListing) {
				$savedListing->delete();
			}
		}
	}
	
	private function deleteListingPictures(Listing $listing): void
	{
		$pictures = Picture::where('listing_id', $listing->id);
		if ($pictures->count() > 0) {
			foreach ($pictures->cursor() as $picture) {
				$picture->delete();
			}
		}
	}
	
	/**
	 * Polymorphic relation — drop both validity scopes so expired and inactive
	 * payment rows are cleared too.
	 */
	private function deleteListingPayments(Listing $listing): void
	{
		$payments = Payment::query()
			->withoutGlobalScopes([ValidPeriodScope::class, StrictActiveScope::class])
			->whereMorphedTo('payable', $listing)
			->get();
		
		if ($payments->count() > 0) {
			foreach ($payments as $payment) {
				$payment->delete();
			}
		}
	}
	
	/**
	 * Reviews live in an addon, so the model only exists when the addon is
	 * loaded. Wrapped in try/catch to be safe even when the addon is partially
	 * uninstalled.
	 */
	private function deleteListingReviewsIfAddonActive(Listing $listing): void
	{
		if (!addon_active('reviews')) return;
		
		try {
			$reviews = Review::where('listing_id', $listing->id);
			if ($reviews->count() > 0) {
				foreach ($reviews->cursor() as $review) {
					$review->delete();
				}
			}
		} catch (Throwable) {
		}
	}
	
	/**
	 * Drop the per-listing media tree (`files/<countryCode>/<listingId>/`).
	 * Pictures are already deleted as rows above; this catches any non-Picture
	 * artefacts that landed in the listing folder.
	 */
	private function deleteListingMediaFolder(Listing $listing): void
	{
		if (empty($listing->country_code) || empty($listing->id)) return;
		
		$disk = StorageDisk::getDisk();
		$directoryPath = 'files/' . strtolower($listing->country_code) . '/' . $listing->id;
		
		if ($disk->exists($directoryPath)) {
			$disk->deleteDirectory($directoryPath);
		}
	}
}

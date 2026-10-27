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

use App\Models\Listing;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\ListingActivated;
use App\Notifications\ListingNotification;
use App\Notifications\ListingReviewed;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Outgoing notifications driven by listing lifecycle changes.
 *
 *   - New listing → admin staff get a heads-up email.
 *   - First time the listing flips to fully verified → owner gets ListingActivated
 *     (or ListingReviewed when admin review is required and the row was just
 *     reviewed).
 */
trait NotificationSideEffects
{
	/**
	 * Notify staff when a new listing is created. Reads the
	 * `mail_admin_notification` toggle so admins can opt out.
	 */
	private function sendAdminListingNotification(Listing $listing): void
	{
		if (setting('mail_admin_notification') != '1') return;
		
		try {
			$admins = User::permission(Permission::getStaffPermissions())->get();
			if ($admins->count() > 0) {
				Notification::send($admins, new ListingNotification($listing));
			}
		} catch (Throwable) {
		}
	}
	
	/**
	 * Notify the listing owner the first time their listing transitions into a
	 * publishable state. The chain depends on whether admin review is required
	 * (`listingsNeedToBeReviewed()`):
	 *
	 *   - Review required:
	 *     - Just reviewed → ListingReviewed
	 *     - Just verified (already reviewed) → ListingActivated
	 *   - Review NOT required:
	 *     - Just verified → ListingReviewed (the only notification needed)
	 *
	 * "Just verified" means email AND phone now both have `*_verified_at`, and
	 * at least one of those columns flipped on the current save (or the row
	 * was just created). "Just reviewed" means `reviewed_at` flipped on this save.
	 */
	private function sendActivationOrReviewedNotification(Listing $listing): void
	{
		try {
			[$justVerified, $justReviewed] = $this->resolveVerificationAndReviewTransitions($listing);
			
			$listingIsVerified = (!empty($listing->email_verified_at) && !empty($listing->phone_verified_at));
			if (!$listingIsVerified) return;
			
			$listingIsReviewed = !empty($listing->reviewed_at);
			$listingHasJustBeenReviewed = ($listingIsReviewed && $justReviewed);
			
			if (listingsNeedToBeReviewed()) {
				if ($listingHasJustBeenReviewed) {
					$listing->notify(new ListingReviewed($listing));
					
					return;
				}
				if ($justVerified) {
					$listing->notify(new ListingActivated($listing));
				}
				
				return;
			}
			
			// Admin review NOT required — owner gets a single ListingReviewed on
			// first verification.
			if ($justVerified) {
				$listing->notify(new ListingReviewed($listing));
			}
		} catch (Throwable $e) {
			abort(500, $e->getMessage());
		}
	}
	
	/**
	 * @return array{0: bool, 1: bool} [justVerified, justReviewed]
	 */
	private function resolveVerificationAndReviewTransitions(Listing $listing): array
	{
		if ($listing->wasRecentlyCreated) {
			return [true, true];
		}
		
		$original = $listing->getOriginal();
		
		$emailJustVerified = ($listing->wasChanged('email_verified_at') && empty(data_get($original, 'email_verified_at')));
		$phoneJustVerified = ($listing->wasChanged('phone_verified_at') && empty(data_get($original, 'phone_verified_at')));
		$justVerified = ($emailJustVerified || $phoneJustVerified);
		
		$justReviewed = ($listing->wasChanged('reviewed_at') && empty(data_get($original, 'reviewed_at')));
		
		return [$justVerified, $justReviewed];
	}
}

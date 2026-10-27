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

namespace App\Observers\Concerns\UserObserver;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\SavedListing;
use App\Models\Scopes\ReviewedScope;
use App\Models\Scopes\StrictActiveScope;
use App\Models\Scopes\ValidPeriodScope;
use App\Models\Scopes\VerifiedScope;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use App\Models\User;
use Addons\Reviews\Models\Review;
use Throwable;

/**
 * Manual cascade for user deletion. The DB schema doesn't carry foreign-key
 * cascades for these relations (some of them cross polymorphic boundaries that
 * FKs can't express), so the observer drains them in order.
 *
 * Each helper iterates with `cursor()` to keep memory steady on users with
 * lots of history. Sanctum tokens and social-login rows go first because they
 * can revoke active sessions independently of the rest of the cascade.
 */
trait CascadingDeletesSideEffects
{
	private function deleteUserRelatedRecords(User $user): void
	{
		$this->revokeAuthArtifacts($user);
		$this->deleteUserListings($user);
		$this->deleteUserMessagingHistory($user);
		$this->deleteUserSavedItems($user);
		$this->deleteUserPayments($user);
		$this->deleteUserReviewsIfAddonActive($user);
	}
	
	private function revokeAuthArtifacts(User $user): void
	{
		try {
			$user->tokens()->delete();
		} catch (Throwable) {
		}
		
		try {
			$user->socialLogins()->delete();
		} catch (Throwable) {
		}
	}
	
	/**
	 * Bypass the listing global scopes (`Verified`, `Reviewed`) so unpublished
	 * drafts are also removed — leaving them behind would orphan rows pointing
	 * at a non-existent `user_id`.
	 */
	private function deleteUserListings(User $user): void
	{
		$listings = Listing::query()
			->withoutGlobalScopes([VerifiedScope::class, ReviewedScope::class])
			->where('user_id', $user->id);
		
		if ($listings->count() > 0) {
			foreach ($listings->cursor() as $listing) {
				$listing->delete();
			}
		}
	}
	
	/**
	 * `forceDelete()` because messaging models use soft deletes and the user
	 * is going away for good — no point in keeping orphan tombstones.
	 */
	private function deleteUserMessagingHistory(User $user): void
	{
		$messages = ThreadMessage::where('user_id', $user->id);
		if ($messages->count() > 0) {
			foreach ($messages->cursor() as $message) {
				$message->forceDelete();
			}
		}
		
		$participants = ThreadParticipant::where('user_id', $user->id);
		if ($participants->count() > 0) {
			foreach ($participants->cursor() as $participant) {
				$participant->forceDelete();
			}
		}
	}
	
	private function deleteUserSavedItems(User $user): void
	{
		$savedListings = SavedListing::where('user_id', $user->id);
		if ($savedListings->count() > 0) {
			foreach ($savedListings->cursor() as $savedListing) {
				$savedListing->delete();
			}
		}
		
		$alerts = Alert::where('user_id', $user->id);
		if ($alerts->count() > 0) {
			foreach ($alerts->cursor() as $alert) {
				$alert->delete();
			}
		}
	}
	
	/**
	 * Polymorphic relation — drop both validity scopes so expired and inactive
	 * payment rows are cleared too.
	 */
	private function deleteUserPayments(User $user): void
	{
		$payments = Payment::query()
			->withoutGlobalScopes([ValidPeriodScope::class, StrictActiveScope::class])
			->whereMorphedTo('payable', $user)
			->get();
		
		if ($payments->count() > 0) {
			foreach ($payments as $payment) {
				$payment->delete();
			}
		}
	}
	
	/**
	 * Reviews live in an addon, so the model class only exists when the addon
	 * is loaded. Wrapped in a try/catch to be safe even when the addon is
	 * partially uninstalled.
	 */
	private function deleteUserReviewsIfAddonActive(User $user): void
	{
		if (!addon_active('reviews')) return;
		
		try {
			$reviews = Review::where('user_id', $user->id);
			if ($reviews->count() > 0) {
				foreach ($reviews->cursor() as $review) {
					$review->delete();
				}
			}
		} catch (Throwable) {
		}
	}
}

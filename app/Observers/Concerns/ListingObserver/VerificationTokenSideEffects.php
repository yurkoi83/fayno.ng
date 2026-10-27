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

/**
 * Make sure unverified listings always have a fresh email / phone token to
 * redeem. Both helpers run from `saved()` and use `saveQuietly()` so
 * persisting the generated token doesn't loop us back through the observer
 * chain.
 */
trait VerificationTokenSideEffects
{
	private function ensureEmailToken(Listing $listing): void
	{
		if (!empty($listing->email_verified_at)) return;
		if (!empty($listing->email_token)) return;
		
		$listing->email_token = generateToken(hashed: true);
		$listing->saveQuietly();
	}
	
	private function ensurePhoneToken(Listing $listing): void
	{
		if (!empty($listing->phone_verified_at)) return;
		if (!empty($listing->phone_token)) return;
		
		$listing->phone_token = generateOtp(defaultOtpLength());
		$listing->saveQuietly();
	}
}

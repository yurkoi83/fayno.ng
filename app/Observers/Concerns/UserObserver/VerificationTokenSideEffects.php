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

use App\Models\User;

/**
 * Make sure unverified users always have a fresh email / phone token to redeem.
 *
 * Both helpers run from `saved()` and use `saveQuietly()` so persisting the
 * generated token doesn't loop us back through the observer chain.
 */
trait VerificationTokenSideEffects
{
	private function ensureEmailToken(User $user): void
	{
		if (!empty($user->email_verified_at)) return;
		if (!empty($user->email_token)) return;
		
		$user->email_token = generateToken(hashed: true);
		$user->saveQuietly();
	}
	
	private function ensurePhoneToken(User $user): void
	{
		if (!empty($user->phone_verified_at)) return;
		if (!empty($user->phone_token)) return;
		
		$user->phone_token = generateOtp(defaultOtpLength());
		$user->saveQuietly();
	}
}

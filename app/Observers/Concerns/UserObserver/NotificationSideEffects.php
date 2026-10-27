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

use App\Exceptions\Custom\CustomException;
use App\Helpers\Common\Arr;
use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\App\Notifications\AccountActivated;
use App\Services\Auth\App\Notifications\AccountSuspended;
use App\Services\Auth\App\Notifications\NewUserRegistered;
use App\Services\Auth\App\Notifications\TwoFactorSetup;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Outgoing notifications driven by user lifecycle changes.
 *
 *   - New-user registration → admins get a heads-up email.
 *   - 2FA toggle → user notified, with rollback if delivery fails.
 *   - Suspension → user notified (configurable hard / soft delivery).
 *   - Just-verified user → user gets the AccountActivated notification.
 */
trait NotificationSideEffects
{
	/**
	 * Notify staff when a new user account is created.
	 *
	 * Reads the `mail_admin_notification` toggle so admins can opt out of the
	 * volume on busy sites. Failures are swallowed — a missed notification
	 * shouldn't block the user creation.
	 */
	private function sendAdminRegistrationNotification(User $user): void
	{
		if (setting('mail_admin_notification') != '1') return;
		
		try {
			$admins = User::permission(Permission::getStaffPermissions())->get();
			if ($admins->count() > 0) {
				Notification::send($admins, new NewUserRegistered($user));
			}
		} catch (Throwable) {
		}
	}
	
	/**
	 * Notify the user when their 2FA toggle flips, and roll the toggle back if
	 * the delivery channel (mail or SMS) isn't properly configured. Without the
	 * rollback the user would be locked into 2FA they can't actually receive.
	 *
	 * @throws CustomException when delivery fails — surfaces to the controller.
	 */
	private function sendTwoFactorToggleNotification(User $user): void
	{
		if (!Arr::keyExists('two_factor_enabled', $user)) return;
		if (!$user->isDirty('two_factor_enabled')) return;
		
		$isTwoFactorEnabled = ($user->two_factor_enabled == 1);
		try {
			$user->notify(new TwoFactorSetup($user, enable: $isTwoFactorEnabled));
		} catch (Throwable $e) {
			// Delivery failed — undo the toggle so the user isn't stranded.
			if ($isTwoFactorEnabled) {
				$user->two_factor_enabled = 0;
				$user->resetTwoFactorCode();
			}
			
			throw new CustomException($e->getMessage());
		}
		
		// Disabling 2FA also clears any pending OTP.
		if (!$isTwoFactorEnabled) {
			$user->resetTwoFactorCode();
		}
	}
	
	/**
	 * Notify the user when their account flips into suspension. The configured
	 * delivery mode (`auth_send_notification_on_user_suspension`) controls
	 * whether a delivery failure is silent (`send`) or hard-fails the save
	 * (`forceToSend`).
	 *
	 * @throws CustomException when forceToSend is configured and delivery fails.
	 */
	private function sendSuspensionNotification(User $user): void
	{
		if (!Arr::keyExists('suspended_at', $user)) return;
		if (!$user->isDirty('suspended_at')) return;
		if (empty($user->suspended_at)) return;
		
		$mode = castToString(setting('auth_send_notification_on_user_suspension'), 'none');
		if (!in_array($mode, ['send', 'forceToSend'], true)) return;
		
		try {
			$user->notify(new AccountSuspended($user));
		} catch (Throwable $e) {
			if ($mode == 'forceToSend') {
				throw new CustomException($e->getMessage());
			}
		}
	}
	
	/**
	 * Send the AccountActivated notification when the user transitions from
	 * unverified → fully verified (both email AND phone). For brand-new accounts
	 * the trigger is the first save; for existing accounts it's the moment one
	 * of the two `*_verified_at` columns flips.
	 *
	 * @throws CustomException
	 */
	private function sendAccountActivatedNotificationIfJustVerified(User $user): void
	{
		try {
			if ($user->wasRecentlyCreated) {
				$userWasNotVerified = true;
			} else {
				$original = $user->getOriginal();
				$emailWasJustVerified = ($user->wasChanged('email_verified_at') && empty(data_get($original, 'email_verified_at')));
				$phoneWasJustVerified = ($user->wasChanged('phone_verified_at') && empty(data_get($original, 'phone_verified_at')));
				$userWasNotVerified = ($emailWasJustVerified || $phoneWasJustVerified);
			}
			$userIsVerified = (!empty($user->email_verified_at) && !empty($user->phone_verified_at));
			
			if ($userIsVerified && $userWasNotVerified) {
				$user->notify(new AccountActivated($user));
			}
		} catch (Throwable $e) {
			throw new CustomException($e->getMessage());
		}
	}
}

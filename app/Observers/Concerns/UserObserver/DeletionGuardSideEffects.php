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

use App\Exceptions\Custom\ModelOperationCancelledException;
use App\Models\User;

/**
 * Pre-flight checks for user deletions originating from the admin panel.
 *
 * Throwing `ModelOperationCancelledException` propagates the message to the
 * controller (returning `false` would cancel without a message). Three rules:
 *
 *   - Staff cannot delete their own account.
 *   - Non-staff visitors hitting an admin route get a forbidden / unauthorized
 *     short-circuit instead of silent failure.
 *   - On the demo domain, the seeded demo accounts are off-limits.
 */
trait DeletionGuardSideEffects
{
	/**
	 * @throws ModelOperationCancelledException
	 */
	private function guardAdminPanelUserDeletion(User $user): void
	{
		if (!isAdminPanelRoute()) return;
		
		$message = null;
		$authUser = auth()->user();
		
		if (!empty($authUser)) {
			if (doesUserHaveStaffPermission($authUser)) {
				if ($authUser->getAuthIdentifier() == $user->id) {
					$message = trans('admin.your_account_cannot_be_deleted_by_yourself');
				}
			} else {
				$message = trans('admin.forbidden');
			}
		} else {
			$message = trans('admin.unauthorized');
		}
		
		if (isDemoDomain()) {
			if (in_array($user->email, getDemoEmailAddresses(), true)) {
				$message = trans('global.demo_mode_message');
			}
		}
		
		if (!empty($message)) {
			throw new ModelOperationCancelledException($message);
		}
	}
}

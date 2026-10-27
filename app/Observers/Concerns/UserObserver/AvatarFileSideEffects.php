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
use App\Services\Image\ImageStore;

/**
 * User avatar housekeeping. Deletes the previous file (and its thumbs)
 * when the user uploads a new photo, and the whole avatar tree when
 * the user is deleted.
 *
 * Routes through `ImageStore::delete()` so the master + every derived
 * variant come off disk via one call — the store's `delete()` already
 * walks the variant chain (thumb-webp → thumb → master) and skips the
 * configured default placeholder, so the bundled default avatar is
 * safe.
 */
trait AvatarFileSideEffects
{
	/**
	 * Drop the previous avatar from disk when the user uploads a new
	 * one. Called from `updating()` so we can read
	 * `getOriginal('photo_path')` before the row is rewritten.
	 */
	private function cleanupReplacedAvatar(User $user): void
	{
		if (!$user->isDirty('photo_path')) {
			return;
		}
		
		$oldFilePath = $user->getOriginal('photo_path');
		if (empty($oldFilePath)) {
			return;
		}
		
		app(ImageStore::class)->delete($oldFilePath, User::AVATAR_PROFILE);
	}
	
	/**
	 * Delete the user's avatar tree on account removal. Called from `deleting()`.
	 */
	private function deleteAvatarOnAccountRemoval(User $user): void
	{
		$path = $user->getRawOriginal('photo_path');
		if (empty($path)) {
			return;
		}
		
		app(ImageStore::class)->delete($path, User::AVATAR_PROFILE);
	}
}

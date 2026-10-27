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

namespace App\Services\Image;

/**
 * Return value of `ImageStore::store()` and `ImageStore::adoptFrom()`.
 *
 * `masterPath` is the only required field — it's what callers persist
 * on their model (e.g. `Picture::$file_path`, `User::$photo_path`).
 * The thumb / webp paths are derived deterministically from the master
 * path by the URL helper, but they're also returned here so callers
 * that want to inspect / log / hot-cache them don't have to redo the
 * derivation.
 */
final class StoredImage
{
	public function __construct(
		public readonly string  $masterPath,
		public readonly ?string $thumbPath = null,
		public readonly ?string $thumbWebpPath = null,
	) {}
}

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

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Low-level image-resize primitive.
 *
 * Reads bytes (or an already-decoded `ImageInterface`), applies one of
 * two transforms, encodes the result. No filesystem, no awareness of
 * profiles — that lives in `ImageStore`.
 *
 * Two transforms only. The legacy pipeline exposed `resize`/`fit`/`pad`/
 * `resizeCanvas` + 8 sub-knobs per size; this class exposes the two
 * shapes that 95% of upload UIs ever need:
 *   - `cap()`    : never upscale, scale-down to fit a bounding box (master)
 *   - `cover()`  : fill an exact box, crop excess (thumb of photos)
 *   - `contain()`: fit inside an exact box, no crop, no upscale (thumb of logos)
 */
final class Resizer
{
	/**
	 * Decode raw bytes into an Intervention image so callers can run
	 * multiple transforms (master cap, thumb cover, thumb-webp encode)
	 * off the same in-memory copy — re-decoding for every variant is
	 * easily the most expensive thing in the whole pipeline.
	 */
	public function read(string $bytesOrPath): ImageInterface
	{
		return Image::read($bytesOrPath);
	}
	
	/**
	 * Scale-down to fit inside a $w × $h box without upscaling. The
	 * source is mutated in place when smaller than the box (no-op);
	 * larger sources shrink while preserving aspect ratio.
	 *
	 * Used for the `master` variant: keep the upload as-is when it's
	 * already small, otherwise downscale to a sane bounding box. The
	 * raw multi-megapixel camera-original is never persisted.
	 */
	public function cap(ImageInterface $image, int $maxW, int $maxH): ImageInterface
	{
		if ($maxW <= 0 || $maxH <= 0) {
			return $image;
		}
		if ($image->width() <= $maxW && $image->height() <= $maxH) {
			return $image;
		}
		
		return $image->scaleDown($maxW, $maxH);
	}
	
	/**
	 * Fill an exact $w × $h box, cropping excess. The output's pixel
	 * dimensions are guaranteed to equal ($w, $h). Used for photo
	 * thumbnails in card grids.
	 */
	public function cover(ImageInterface $image, int $w, int $h): ImageInterface
	{
		return $image->cover($w, $h);
	}
	
	/**
	 * Fit inside a $w × $h box without cropping. Output is at most
	 * ($w, $h); aspect ratio is preserved; no upscaling. Used for
	 * logo-shaped thumbnails where cropping would lose meaning.
	 */
	public function contain(ImageInterface $image, int $w, int $h): ImageInterface
	{
		return $image->scaleDown($w, $h);
	}
	
	/**
	 * Encode the image to the given extension. Returns the encoded
	 * binary string ready for `Filesystem::put($path, $bytes)`.
	 *
	 * On encode failure (Intervention occasionally throws on
	 * malformed sources) we return `null` so the caller can decide:
	 * skip this variant vs. raise. The legacy pipeline used to
	 * swallow these in a try/catch inside the resize itself, leaving
	 * the disk with no thumb and no log entry — we make the failure
	 * visible to the caller instead.
	 */
	public function encode(
		ImageInterface $image,
		string         $extension,
		int            $quality,
		bool           $progressive
	): ?string
	{
		try {
			return $image
				->encodeByExtension($extension, progressive: $progressive, quality: $quality)
				->toString();
		} catch (Throwable) {
			return null;
		}
	}
	
	/**
	 * Re-encode the source bytes WITHOUT a geometry transform. Used
	 * when the master cap is a no-op (profile.master.max_w/h = 0,
	 * e.g. favicon): we still strip EXIF and pick the canonical
	 * extension/quality, but the pixels are untouched.
	 */
	public function passthroughEncode(
		string $sourcePath,
		string $extension,
		int    $quality,
		bool   $progressive
	): ?string
	{
		try {
			return Image::read($sourcePath)
				->encodeByExtension($extension, progressive: $progressive, quality: $quality)
				->toString();
		} catch (Throwable) {
			return null;
		}
	}
}

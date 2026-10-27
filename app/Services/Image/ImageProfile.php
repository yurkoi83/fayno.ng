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

use InvalidArgumentException;

/**
 * Immutable description of one image-storage profile.
 *
 * Built by `ImageStore::profile()` from `config/larapen/images.php`,
 * never mutated. Consumers (the resizer, the model accessors) read the
 * fields directly — there is no `getXxx()` indirection.
 *
 * The two `null` cases each carry meaning:
 *   - `master` with `max_w/max_h = 0` → store the upload as-is, no
 *     resize (used for favicon, where the source IS the canonical size).
 *   - `thumb = null` → don't generate a thumb at all for this profile;
 *     the URL helper falls back to the master variant.
 */
final class ImageProfile
{
	public function __construct(
		public readonly string  $name,
		
		// Master cap. `(0, 0)` means "no resize — store as uploaded".
		public readonly int     $masterMaxW,
		public readonly int     $masterMaxH,
		
		// Thumb dims + fit. `null` means "no thumb for this profile".
		public readonly ?int    $thumbW,
		public readonly ?int    $thumbH,
		public readonly ?string $thumbFit, // 'cover' | 'contain'
		
		public readonly int     $quality,
		public readonly bool    $webp,
		public readonly bool    $stripExif,
		public readonly bool    $progressive,
		
		// Optional disk-relative placeholder served when the master is
		// missing/deleted. `null` lets the URL helper return null.
		public readonly ?string $defaultPlaceholder,
	)
	{
		if ($masterMaxW < 0 || $masterMaxH < 0) {
			throw new InvalidArgumentException("ImageProfile[{$name}]: master max dimensions must be >= 0.");
		}
		if ($thumbW !== null) {
			if ($thumbH === null || $thumbFit === null) {
				throw new InvalidArgumentException("ImageProfile[{$name}]: thumb.w requires thumb.h and thumb.fit.");
			}
			if ($thumbW <= 0 || $thumbH <= 0) {
				throw new InvalidArgumentException("ImageProfile[{$name}]: thumb dimensions must be > 0.");
			}
			if (!in_array($thumbFit, ['cover', 'contain'], true)) {
				throw new InvalidArgumentException("ImageProfile[{$name}]: thumb.fit must be 'cover' or 'contain'.");
			}
		}
		if ($quality < 1 || $quality > 100) {
			throw new InvalidArgumentException("ImageProfile[{$name}]: quality must be between 1 and 100.");
		}
	}
	
	/**
	 * `true` when the profile wants the master resized to a bounded box.
	 * `false` when the master is stored as-is.
	 */
	public function hasMasterCap(): bool
	{
		return $this->masterMaxW > 0 && $this->masterMaxH > 0;
	}
	
	public function hasThumb(): bool
	{
		return $this->thumbW !== null;
	}
	
	/**
	 * `true` when this profile produces a `.webp` twin of the thumb.
	 * (The master is never WebP-encoded — that's a deliberate choice:
	 * the master is the lossless-ish full image, WebP only makes sense
	 * for the bandwidth-sensitive thumb served in grid cards.)
	 */
	public function hasWebpThumb(): bool
	{
		return $this->webp && $this->hasThumb();
	}
	
	/**
	 * Build a profile from the raw `config/larapen/images.php` shape.
	 * `$globals` are the top-level defaults; per-profile keys override.
	 *
	 * @param array<string, mixed> $raw
	 * @param array<string, mixed> $globals
	 */
	public static function fromConfig(string $name, array $raw, array $globals): self
	{
		$master = $raw['master'] ?? null;
		$thumb = $raw['thumb'] ?? null;
		
		return new self(
			name: $name,
			masterMaxW: is_array($master) ? (int)($master['max_w'] ?? 0) : 0,
			masterMaxH: is_array($master) ? (int)($master['max_h'] ?? 0) : 0,
			thumbW: is_array($thumb) ? (int)($thumb['w'] ?? 0) : null,
			thumbH: is_array($thumb) ? (int)($thumb['h'] ?? 0) : null,
			thumbFit: is_array($thumb) ? (string)($thumb['fit'] ?? 'cover') : null,
			quality: (int)($raw['quality'] ?? $globals['quality'] ?? 85),
			webp: (bool)($raw['webp'] ?? $globals['webp'] ?? true),
			stripExif: (bool)($raw['strip_exif'] ?? $globals['strip_exif'] ?? true),
			progressive: (bool)($raw['progressive'] ?? $globals['progressive'] ?? true),
			defaultPlaceholder: isset($raw['default']) ? (string)$raw['default'] : null,
		);
	}
}

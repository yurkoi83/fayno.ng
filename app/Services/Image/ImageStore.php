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

use App\Helpers\Common\Files\FileSys;
use App\Helpers\Common\Files\Storage\StorageDisk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Two-tier image storage: a capped master + a single thumbnail per
 * profile.
 *
 * Every image-bearing model in the app routes through this DI-bound
 * service — there is no other path for writing images. Uploads,
 * seeders and admin tools all funnel through the same `store()` /
 * `adoptFrom()` entry points.
 *
 * ## Contract
 *
 * - `store($file, $profile, $dir)` is the canonical write path for
 *   user uploads. It caps the source to the profile's master box,
 *   writes the master as `<dir>/<hash>.<ext>`, then writes one thumb
 *   (and optionally a WebP twin of the thumb) under `<dir>/thumbnails/`.
 *   The hashed basename is deterministic for the bytes — re-uploading
 *   the same image yields the same path.
 *
 * - `adoptFrom($absSourcePath, $profile, $dir)` is the demo-seeder
 *   path. Same outputs as `store()`, but it takes a path to a file
 *   already on disk (under `dataFactory/<project>/files/`) and memoizes
 *   per source so repeated calls with the same source replay the cached
 *   encoded bytes via `Filesystem::put()` instead of redoing the
 *   decode-resize-encode round-trip. Crucial for the daily seed —
 *   ~50 unique sources × thousands of targets becomes O(sources)
 *   real resizes plus O(targets) cheap file copies.
 *
 * - `url($masterPath, $profile, $variant)` is the canonical read path
 *   for model URL accessors. `$variant` is `'master'`, `'thumb'`, or
 *   `'thumb-webp'`. Falls back to the next-best available variant on
 *   disk (so a missing thumb shows the master, a missing master shows
 *   the profile's default placeholder), never returning a 404-bound URL.
 *
 * - `delete($masterPath, $profile)` removes the master plus every
 *   derived variant from disk. Replaces the legacy
 *   `removePictureWithItsThumbs` helper.
 *
 * - `profile($name)` resolves a config block into an `ImageProfile`
 *   value object. The result is cached on the service instance for the
 *   process lifetime — the config doesn't change mid-request.
 *
 * ## Storage layout
 *
 * Backwards-compatible with the legacy on-disk layout, so existing
 * seeded / production data keeps rendering after the migration:
 *
 *   <dir>/<hash>.<ext>                              ← master
 *   <dir>/thumbnails/<w>x<h>-<hash>.<ext>           ← thumb
 *   <dir>/thumbnails/<w>x<h>-<hash>.webp            ← webp thumb
 */
final class ImageStore
{
	private Filesystem $disk;
	
	private Resizer $resizer;
	
	/** @var array<string, ImageProfile> */
	private array $profileCache = [];
	
	/**
	 * Per-source encoded-bytes cache used by `adoptFrom()`. Keyed by
	 * `<sourcePath>|<profile>|<variant>` — the variant is one of
	 * `'master'`, `'thumb'`, `'thumb-webp'`. Cleared between requests
	 * by the singleton lifecycle (we don't keep this state across
	 * separate seed runs in long-running workers).
	 *
	 * @var array<string, string>
	 */
	private array $adoptByteCache = [];
	
	public function __construct(?Filesystem $disk = null, ?Resizer $resizer = null)
	{
		$this->disk = $disk ?? StorageDisk::getDisk();
		$this->resizer = $resizer ?? new Resizer();
	}
	
	// ───────────────────────────────────────────────────────────────────────
	// Profile resolution
	// ───────────────────────────────────────────────────────────────────────
	
	public function profile(string $name): ImageProfile
	{
		if (isset($this->profileCache[$name])) {
			return $this->profileCache[$name];
		}
		
		$raw = config('larapen.images.profiles.' . $name);
		if (!is_array($raw)) {
			throw new InvalidArgumentException("ImageStore: unknown profile '{$name}'.");
		}
		$globals = (array)config('larapen.images.global', []);
		
		return $this->profileCache[$name] = ImageProfile::fromConfig($name, $raw, $globals);
	}
	
	// ───────────────────────────────────────────────────────────────────────
	// Write paths
	// ───────────────────────────────────────────────────────────────────────
	
	/**
	 * Canonical upload path: cap, store, thumb. The source can be a
	 * Laravel UploadedFile (controller upload) or an absolute path to
	 * a file already on disk (admin file-pickers, custom flows).
	 *
	 * @param UploadedFile|string $source
	 * @param string $dir Disk-relative directory (e.g. "files/us/100").
	 *                              Created if it doesn't exist.
	 */
	public function store(UploadedFile|string $source, string $profileName, string $dir): StoredImage
	{
		$profile = $this->profile($profileName);
		$sourcePath = $this->resolveSourcePath($source);
		$originalName = $this->resolveOriginalName($source);
		
		$bytes = $this->buildMasterBytes($sourcePath, $profile, $originalName);
		[$masterPath, ] = $this->resolveMasterPath($dir, $originalName, $bytes['master']);
		
		$this->ensureDir($dir);
		$this->disk->put($masterPath, $bytes['master']);
		
		$thumbPath = null;
		$thumbWebpPath = null;
		if ($profile->hasThumb()) {
			$thumbPath = $this->thumbPathFor($masterPath, $profile, webp: false);
			$this->ensureDir(dirname($thumbPath));
			$this->disk->put($thumbPath, $bytes['thumb']);
			
			if ($profile->hasWebpThumb()) {
				$thumbWebpPath = $this->thumbPathFor($masterPath, $profile, webp: true);
				$this->disk->put($thumbWebpPath, $bytes['thumb-webp']);
			}
		}
		
		return new StoredImage(
			masterPath: $masterPath,
			thumbPath: $thumbPath,
			thumbWebpPath: $thumbWebpPath,
		);
	}
	
	/**
	 * Seeder write path: same outputs as `store()`, but memoizes the
	 * encoded bytes per source so repeated calls with the same source
	 * skip the decode-resize-encode round-trip. The seeder copies ~50
	 * unique source images to thousands of listing-specific paths;
	 * this turns that into ~150 real resize ops total (one per
	 * (source, profile, variant) tuple) plus O(targets) cheap
	 * `Filesystem::put()` calls.
	 */
	public function adoptFrom(string $absoluteSourcePath, string $profileName, string $dir): StoredImage
	{
		if (!is_file($absoluteSourcePath)) {
			throw new InvalidArgumentException("ImageStore::adoptFrom: source not found: {$absoluteSourcePath}");
		}
		
		$profile = $this->profile($profileName);
		$bytes = $this->buildOrReuseAdoptBytes($absoluteSourcePath, $profile);
		$originalName = basename($absoluteSourcePath);
		[$masterPath,] = $this->resolveMasterPath($dir, $originalName, $bytes['master']);
		
		$this->ensureDir($dir);
		$this->disk->put($masterPath, $bytes['master']);
		
		$thumbPath = null;
		$thumbWebpPath = null;
		if ($profile->hasThumb() && isset($bytes['thumb'])) {
			$thumbPath = $this->thumbPathFor($masterPath, $profile, webp: false);
			$this->ensureDir(dirname($thumbPath));
			$this->disk->put($thumbPath, $bytes['thumb']);
			
			if ($profile->hasWebpThumb() && isset($bytes['thumb-webp'])) {
				$thumbWebpPath = $this->thumbPathFor($masterPath, $profile, webp: true);
				$this->disk->put($thumbWebpPath, $bytes['thumb-webp']);
			}
		}
		
		return new StoredImage($masterPath, $thumbPath, $thumbWebpPath);
	}
	
	/**
	 * Convenience for form-field handling on admin / settings pages.
	 *
	 * Given a request file (the user might have just uploaded one) plus
	 * the field's existing value (a string path the form is editing),
	 * decide what to persist:
	 *   - New file uploaded → run `store()` and return the new master path.
	 *   - No new file       → return the existing value, normalised
	 *                         (strips the `/storage/` URL prefix when
	 *                         the form posted back a fully-qualified URL).
	 *   - Nothing usable    → return `null`.
	 */
	public function handleFormField(
		?UploadedFile $file,
		mixed         $existingValue,
		string        $profileName,
		string        $destDir
	): ?string
	{
		if ($file !== null) {
			return $this->store($file, $profileName, $destDir)->masterPath;
		}
		
		if (!is_string($existingValue) || $existingValue === '') {
			return null;
		}
		
		// Form may post back a fully-qualified `/storage/...` URL when
		// the user didn't change the file — strip the storage prefix
		// so we always persist disk-relative paths.
		$storagePrefix = url('storage');
		if (str_starts_with($existingValue, $storagePrefix)) {
			return ltrim(str_replace($storagePrefix, '', $existingValue), '/');
		}
		
		// Never persist the default placeholder path — treat it as "no file".
		$default = $this->profile($profileName)->defaultPlaceholder;
		if (!empty($default) && str_contains($existingValue, $default)) {
			return null;
		}
		
		return $existingValue;
	}
	
	/**
	 * Re-bake the thumb variants for a master that already exists on
	 * disk. Used when:
	 *   - A temp-upload master gets moved to its final location and we
	 *     need thumbs at the new path.
	 *   - An admin addon (e.g. watermark) modifies the master in place
	 *     and we need thumbs that reflect the new master bytes.
	 *   - A "rebuild thumbs" maintenance command is run.
	 *
	 * Does NOT touch the master file. Returns a StoredImage describing
	 * the current variant paths.
	 */
	public function bakeThumbsFor(string $masterDiskRelPath, string $profileName): StoredImage
	{
		$profile = $this->profile($profileName);
		if (!$this->disk->exists($masterDiskRelPath)) {
			throw new InvalidArgumentException("ImageStore::bakeThumbsFor: master not found: {$masterDiskRelPath}");
		}
		if (!$profile->hasThumb()) {
			return new StoredImage($masterDiskRelPath);
		}
		
		$sourceExt = $this->extensionFor($masterDiskRelPath);
		$masterAbs = $this->disk->path($masterDiskRelPath);
		$image = $this->resizer->read($masterAbs);
		
		$thumbPath = $this->thumbPathFor($masterDiskRelPath, $profile, webp: false);
		$this->ensureDir(dirname($thumbPath));
		$this->disk->put($thumbPath, $this->buildThumbBytesFromImage($image, $profile, $sourceExt, webp: false));
		
		$thumbWebpPath = null;
		if ($profile->hasWebpThumb() && strtolower($sourceExt) !== 'webp') {
			$thumbWebpPath = $this->thumbPathFor($masterDiskRelPath, $profile, webp: true);
			$this->disk->put($thumbWebpPath, $this->buildThumbBytesFromImage($image, $profile, $sourceExt, webp: true));
		}
		
		return new StoredImage($masterDiskRelPath, $thumbPath, $thumbWebpPath);
	}
	
	// ───────────────────────────────────────────────────────────────────────
	// Read paths
	// ───────────────────────────────────────────────────────────────────────
	
	/**
	 * Return the public URL for a variant of `$masterPath`. Falls back
	 * gracefully:
	 *   thumb-webp → thumb → master → profile.default → null
	 *
	 * This guarantees model URL accessors never return a URL whose
	 * file doesn't exist on disk — the front-end always renders
	 * something sensible.
	 */
	public function url(?string $masterPath, string $profileName, string $variant = 'thumb'): ?string
	{
		$profile = $this->profile($profileName);
		$path = $this->resolveExistingPath($masterPath, $profile, $variant);
		if ($path === null) {
			return null;
		}
		
		return $this->disk->url($path);
	}
	
	/**
	 * Inspect-only counterpart of `url()` — returns the disk-relative
	 * path of the best-available variant without resolving to a URL.
	 * Used by tests, the admin "rebuild thumbs" command, and any
	 * tooling that needs to read or copy the file directly.
	 */
	public function pathOf(?string $masterPath, string $profileName, string $variant = 'thumb'): ?string
	{
		$profile = $this->profile($profileName);
		
		return $this->resolveExistingPath($masterPath, $profile, $variant);
	}
	
	/**
	 * `true` when the requested variant exists on disk. Doesn't run
	 * any fallback — used by callers that specifically want to know
	 * whether the variant they asked for is there.
	 */
	public function exists(?string $masterPath, string $profileName, string $variant = 'thumb'): bool
	{
		$profile = $this->profile($profileName);
		$path = $this->variantPath($masterPath, $profile, $variant);
		
		return $path !== null && $this->disk->exists($path);
	}
	
	// ───────────────────────────────────────────────────────────────────────
	// Delete
	// ───────────────────────────────────────────────────────────────────────
	
	/**
	 * Delete the master and every derived variant.
	 *
	 * Safe to call with `null` (no-op). Safe to call when only some
	 * variants exist (each delete is guarded by an `exists()` check
	 * so we don't trip the disk driver's "missing file" exception).
	 */
	public function delete(?string $masterPath, string $profileName): void
	{
		if (empty($masterPath)) {
			return;
		}
		
		$profile = $this->profile($profileName);
		
		foreach (['thumb-webp', 'thumb', 'master'] as $variant) {
			$path = $this->variantPath($masterPath, $profile, $variant);
			if ($path !== null && $this->disk->exists($path)) {
				$this->disk->delete($path);
			}
		}
	}
	
	// ───────────────────────────────────────────────────────────────────────
	// Path derivation (kept public for tests / tooling)
	// ───────────────────────────────────────────────────────────────────────
	
	/**
	 * Compute the thumb file's disk-relative path next to its master.
	 * Stable: same master path + profile + webp flag → same thumb
	 * path. The URL helper and the writer share this function so
	 * "where we write" and "where the URL helper reads" never drift.
	 */
	public function thumbPathFor(string $masterPath, ImageProfile $profile, bool $webp): string
	{
		if (!$profile->hasThumb()) {
			throw new RuntimeException("ImageProfile[{$profile->name}] has no thumb variant.");
		}
		
		$filename = basename($masterPath);
		$fileDir = dirname($masterPath);
		
		if ($webp) {
			$filename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
		}
		
		return $fileDir . '/thumbnails/' . $profile->thumbW . 'x' . $profile->thumbH . '-' . $filename;
	}
	
	// ───────────────────────────────────────────────────────────────────────
	// Internals
	// ───────────────────────────────────────────────────────────────────────
	
	private function resolveSourcePath(UploadedFile|string $source): string
	{
		if ($source instanceof UploadedFile) {
			return $source->getRealPath() ?: $source->getPathname();
		}
		if (!is_file($source)) {
			throw new InvalidArgumentException("ImageStore: source path is not a file: {$source}");
		}
		
		return $source;
	}
	
	private function resolveOriginalName(UploadedFile|string $source): string
	{
		return $source instanceof UploadedFile
			? $source->getClientOriginalName()
			: basename($source);
	}
	
	/**
	 * @return array{master:string, thumb?:string, thumb-webp?:string}
	 */
	private function buildMasterBytes(string $sourcePath, ImageProfile $profile, string $originalName): array
	{
		$extension = $this->extensionFor($originalName);
		
		// "Master cap" off → keep the upload verbatim (favicon-style).
		// We still re-encode through Intervention to strip EXIF and
		// normalize the format, unless the source extension can't be
		// re-encoded (e.g. SVG) — in that case we passthrough the raw
		// bytes.
		if (!$profile->hasMasterCap()) {
			if (!$this->isInterventionEncodable($extension)) {
				return ['master' => (string)file_get_contents($sourcePath)];
			}
			$masterBytes = $this->resizer->passthroughEncode(
				$sourcePath, $extension, $profile->quality, $profile->progressive
			);
			if ($masterBytes === null) {
				throw new RuntimeException("ImageStore: failed to encode master for {$sourcePath}.");
			}
			
			$result = ['master' => $masterBytes];
			if ($profile->hasThumb()) {
				$result['thumb'] = $this->buildThumbBytes($sourcePath, $profile, $extension, webp: false);
				if ($profile->hasWebpThumb()) {
					$result['thumb-webp'] = $this->buildThumbBytes($sourcePath, $profile, $extension, webp: true);
				}
			}
			
			return $result;
		}
		
		// Master cap on: decode once, run cap + thumb transforms off
		// the same in-memory copy. Cloning before each transform
		// avoids `cover()` mutating the master image used downstream.
		$image = $this->resizer->read($sourcePath);
		
		$masterImage = $this->resizer->cap(clone $image, $profile->masterMaxW, $profile->masterMaxH);
		$masterBytes = $this->resizer->encode($masterImage, $extension, $profile->quality, $profile->progressive);
		if ($masterBytes === null) {
			throw new RuntimeException("ImageStore: failed to encode master for {$sourcePath}.");
		}
		$result = ['master' => $masterBytes];
		
		if ($profile->hasThumb()) {
			$result['thumb'] = $this->buildThumbBytesFromImage($image, $profile, $extension, webp: false);
			if ($profile->hasWebpThumb()) {
				$result['thumb-webp'] = $this->buildThumbBytesFromImage($image, $profile, $extension, webp: true);
			}
		}
		
		return $result;
	}
	
	/**
	 * Build the thumb variant bytes directly from a source path —
	 * used in the "no master cap" branch (favicon-style profiles).
	 */
	private function buildThumbBytes(string $sourcePath, ImageProfile $profile, string $sourceExt, bool $webp): string
	{
		$image = $this->resizer->read($sourcePath);
		
		return $this->buildThumbBytesFromImage($image, $profile, $sourceExt, $webp);
	}
	
	private function buildThumbBytesFromImage(
		object       $image, // ImageInterface; widened to avoid a hard import in this signature
		ImageProfile $profile,
		string       $sourceExt,
		bool         $webp
	): string
	{
		$transformed = $profile->thumbFit === 'cover'
			? $this->resizer->cover(clone $image, $profile->thumbW, $profile->thumbH)
			: $this->resizer->contain(clone $image, $profile->thumbW, $profile->thumbH);
		
		$targetExt = $webp ? 'webp' : $sourceExt;
		$bytes = $this->resizer->encode($transformed, $targetExt, $profile->quality, $profile->progressive);
		if ($bytes === null) {
			throw new RuntimeException("ImageStore: failed to encode thumb variant '{$targetExt}'.");
		}
		
		return $bytes;
	}
	
	/**
	 * @return array{master:string, thumb?:string, thumb-webp?:string}
	 */
	private function buildOrReuseAdoptBytes(string $sourcePath, ImageProfile $profile): array
	{
		$masterKey = $sourcePath . '|' . $profile->name . '|master';
		$thumbKey = $sourcePath . '|' . $profile->name . '|thumb';
		$webpKey = $sourcePath . '|' . $profile->name . '|thumb-webp';
		
		// Cache hit on every variant → reuse directly, no decode.
		$cached = $this->getCachedVariants($masterKey, $thumbKey, $webpKey, $profile);
		if ($cached !== null) {
			return $cached;
		}
		
		$built = $this->buildMasterBytes($sourcePath, $profile, basename($sourcePath));
		
		$this->adoptByteCache[$masterKey] = $built['master'];
		if (isset($built['thumb'])) {
			$this->adoptByteCache[$thumbKey] = $built['thumb'];
		}
		if (isset($built['thumb-webp'])) {
			$this->adoptByteCache[$webpKey] = $built['thumb-webp'];
		}
		
		return $built;
	}
	
	/**
	 * @return array{master:string, thumb?:string, thumb-webp?:string}|null
	 */
	private function getCachedVariants(string $masterKey, string $thumbKey, string $webpKey, ImageProfile $profile): ?array
	{
		if (!isset($this->adoptByteCache[$masterKey])) {
			return null;
		}
		if ($profile->hasThumb() && !isset($this->adoptByteCache[$thumbKey])) {
			return null;
		}
		if ($profile->hasWebpThumb() && !isset($this->adoptByteCache[$webpKey])) {
			return null;
		}
		
		$out = ['master' => $this->adoptByteCache[$masterKey]];
		if ($profile->hasThumb()) {
			$out['thumb'] = $this->adoptByteCache[$thumbKey];
		}
		if ($profile->hasWebpThumb()) {
			$out['thumb-webp'] = $this->adoptByteCache[$webpKey];
		}
		
		return $out;
	}
	
	private function resolveExistingPath(?string $masterPath, ImageProfile $profile, string $variant): ?string
	{
		if (empty($masterPath)) {
			return $this->placeholderPath($profile);
		}
		
		// Walk the fallback chain. Asking for a variant the profile
		// doesn't produce (thumb on a thumb-less profile, thumb-webp
		// on a webp-disabled profile) silently degrades to the next
		// variant down — same behaviour as the old URL helper.
		$chain = $this->fallbackChainFor($variant);
		foreach ($chain as $candidate) {
			$path = $this->variantPath($masterPath, $profile, $candidate);
			if ($path !== null && $this->disk->exists($path)) {
				return $path;
			}
		}
		
		return $this->placeholderPath($profile);
	}
	
	private function variantPath(?string $masterPath, ImageProfile $profile, string $variant): ?string
	{
		if (empty($masterPath)) {
			return null;
		}
		
		return match ($variant) {
			'master'     => $masterPath,
			'thumb'      => $profile->hasThumb() ? $this->thumbPathFor($masterPath, $profile, webp: false) : null,
			'thumb-webp' => $profile->hasWebpThumb() ? $this->thumbPathFor($masterPath, $profile, webp: true) : null,
			default      => throw new InvalidArgumentException("ImageStore: unknown variant '{$variant}'."),
		};
	}
	
	/**
	 * @return array<int, string>
	 */
	private function fallbackChainFor(string $variant): array
	{
		return match ($variant) {
			'thumb-webp' => ['thumb-webp', 'thumb', 'master'],
			'thumb'      => ['thumb', 'master'],
			'master'     => ['master'],
			default      => throw new InvalidArgumentException("ImageStore: unknown variant '{$variant}'."),
		};
	}
	
	private function placeholderPath(ImageProfile $profile): ?string
	{
		$default = $profile->defaultPlaceholder;
		if (empty($default)) {
			return null;
		}
		
		return $this->disk->exists($default) ? $default : null;
	}
	
	/**
	 * Compose `<dir>/<hash>.<ext>` for the master, where `<hash>` is the
	 * md5 of the encoded master bytes — content-addressed, so identical
	 * master bytes always resolve to the same path. Re-uploading or
	 * re-seeding the same image is therefore idempotent: it overwrites
	 * the same file instead of orphaning the previous copy under a fresh
	 * random name (the legacy `md5(name + microtime + rand)` scheme).
	 *
	 * This stability is what keeps the listing / page-section caches
	 * honest. Those caches bake fully-resolved image URLs as plain
	 * strings, and the existence-guarded fallback in `url()` only runs
	 * when a model accessor is actually invoked — never on a cache hit.
	 * A filename that changed on every regeneration would therefore leave
	 * cached pages pointing at deleted thumbnails (404) until the cache
	 * expired or was invalidated. Content-addressing means a cached URL
	 * keeps resolving for as long as the underlying image bytes are
	 * unchanged.
	 *
	 * @param string $masterBytes The already-encoded master payload that
	 *                            will be written at the returned path.
	 * @return array{0: string, 1: string}  Tuple of (disk-relative path, extension).
	 */
	private function resolveMasterPath(string $dir, string $originalName, string $masterBytes): array
	{
		$dir = rtrim($dir, '/');
		$extension = $this->extensionFor($originalName);
		$basename = md5($masterBytes) . '.' . $extension;

		return [$dir . '/' . $basename, $extension];
	}
	
	private function extensionFor(string $name): string
	{
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
		if ($ext === 'jpeg') {
			$ext = 'jpg';
		}
		
		return $ext !== '' ? $ext : 'jpg';
	}
	
	private function isInterventionEncodable(string $extension): bool
	{
		return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'tif', 'tiff'], true);
	}
	
	private function ensureDir(string $dir): void
	{
		if (!$this->disk->exists($dir)) {
			$this->disk->makeDirectory($dir);
		}
	}
}

<?php

declare(strict_types=1);

namespace App\Helpers\Common;

use App\Helpers\Common\Dotenv\DotenvDiskCache;
use App\Helpers\Common\Dotenv\DotenvFile;
use App\Helpers\Common\Dotenv\DotenvWriter;

/**
 * DotenvEditor — read/write helper for the project's `.env` file.
 *
 * Pending edits live in memory; the file on the disk is always the source of
 * truth. `save()` re-reads the file, applies pending writes/deletes/appends
 * to that fresh content, and writes it back — so any concurrent change made
 * to keys we did NOT touch is preserved. (A previous version cached the
 * parsed file in memory and wrote that cache back, silently erasing
 * external changes.)
 *
 * Quoting and escaping match vlucas/phpdotenv so values a round-trip through
 * `env()` unchanged. See `Dotenv\DotenvEscaper` for the exact rules.
 *
 * Limitation: `$` is not escaped. phpdotenv interpolates `${VAR}` in
 * unquoted and double-quoted values; for a literal `${...}`, edit the .env
 * file by hand using single quotes.
 */
class DotenvEditor
{
	private static string $envFilePath = '';

	private static array $pendingValues = [];

	private static array $pendingDeletes = [];

	/** Per-key wrap overrides: `true` forces wrapping, `false` forbids it. Absence means auto-detect at save time. */
	private static array $pendingWrapOverrides = [];

	/** @var array<int, array{type: 'empty'|'comment', value: string}> */
	private static array $pendingAppends = [];

	/**
	 * Pin the helper to a specific `.env` file. Pass an empty string to fall
	 * back to `base_path('.env')`. Also clears any pending edits and the
	 * disk cache — calling this means "start fresh against this file."
	 */
	public static function setFilePath(string $path): void
	{
		self::$envFilePath = $path;
		self::clearPending();
		DotenvDiskCache::invalidate();
	}

	/** Returns `null` if the key has been pending-deleted or doesn't exist. */
	public static function getValue(string $key): ?string
	{
		if (in_array($key, self::$pendingDeletes, true)) {
			return null;
		}

		if (array_key_exists($key, self::$pendingValues)) {
			return self::$pendingValues[$key];
		}

		return DotenvDiskCache::read(self::resolveFilePath())[$key] ?? null;
	}

	/**
	 * Pass `null` to get every effective key.
	 *
	 * @param array<int, string>|null $keys
	 * @return array<string, string|null>
	 */
	public static function getValues(?array $keys = null): array
	{
		$disk = DotenvDiskCache::read(self::resolveFilePath());

		foreach (self::$pendingValues as $k => $v) {
			$disk[$k] = $v;
		}
		foreach (self::$pendingDeletes as $k) {
			unset($disk[$k]);
		}

		if ($keys === null) {
			return $disk;
		}

		$out = [];
		foreach ($keys as $k) {
			$out[$k] = $disk[$k] ?? null;
		}

		return $out;
	}

	public static function keyExists(string $key): bool
	{
		if (in_array($key, self::$pendingDeletes, true)) {
			return false;
		}

		if (array_key_exists($key, self::$pendingValues)) {
			return true;
		}

		return array_key_exists($key, DotenvDiskCache::read(self::resolveFilePath()));
	}

	public static function setKey(string $key, mixed $value, ?bool $wrap = null): void
	{
		self::$pendingValues[$key] = castToString($value);

		if ($wrap === null) {
			unset(self::$pendingWrapOverrides[$key]);
		} else {
			self::$pendingWrapOverrides[$key] = $wrap;
		}

		// setKey on a pending-deleted key cancels the deletion.
		if (($i = array_search($key, self::$pendingDeletes, true)) !== false) {
			unset(self::$pendingDeletes[$i]);
			self::$pendingDeletes = array_values(self::$pendingDeletes);
		}
	}

	/** @param array<string, mixed> $variables */
	public static function setKeys(array $variables): void
	{
		foreach ($variables as $key => $value) {
			self::setKey((string)$key, $value);
		}
	}

	public static function deleteKey(string $key): void
	{
		if (!in_array($key, self::$pendingDeletes, true)) {
			self::$pendingDeletes[] = $key;
		}

		unset(self::$pendingValues[$key], self::$pendingWrapOverrides[$key]);
	}

	public static function addEmpty(): void
	{
		self::$pendingAppends[] = ['type' => 'empty', 'value' => ''];
	}

	/** Leading `# ` is added if missing. */
	public static function addComment(string $value): void
	{
		self::$pendingAppends[] = ['type' => 'comment', 'value' => $value];
	}

	/**
	 * Apply all pending edits to the `.env` file. No-op when nothing is pending.
	 *
	 * @throws \Exception When the target file is not writable.
	 */
	public static function save(): void
	{
		if (!self::hasPending()) {
			return;
		}

		$envPath = self::resolveFilePath();
		DotenvFile::ensureFileIsWritable($envPath);

		$envContent = file_exists($envPath) ? (string)file_get_contents($envPath) : '';
		$envContent = DotenvWriter::apply(
			$envContent,
			self::$pendingValues,
			self::$pendingDeletes,
			self::$pendingAppends,
			self::$pendingWrapOverrides,
		);

		file_put_contents($envPath, $envContent);

		self::clearPending();
		DotenvDiskCache::invalidate();
	}

	private static function resolveFilePath(): string
	{
		if (self::$envFilePath === '') {
			self::$envFilePath = base_path('.env');
		}

		return self::$envFilePath;
	}

	private static function hasPending(): bool
	{
		return !empty(self::$pendingValues)
			|| !empty(self::$pendingDeletes)
			|| !empty(self::$pendingAppends);
	}

	private static function clearPending(): void
	{
		self::$pendingValues = [];
		self::$pendingDeletes = [];
		self::$pendingWrapOverrides = [];
		self::$pendingAppends = [];
	}
}

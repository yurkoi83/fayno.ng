<?php

declare(strict_types=1);

namespace App\Helpers\Common\Dotenv;

/**
 * DotenvDiskCache — read+parse the `.env` file, with a mtime-keyed cache.
 *
 * The parsed result is reused as long as the file's mtime hasn't changed
 * since the last read, so several calls in the same request don't re-open
 * or re-parse the file. Call `invalidate()` after writing the file (or when
 * switching files), so the next read picks up the new content.
 */
class DotenvDiskCache
{
	/** @var array<string, string>|null */
	private static ?array $cache = null;
	private static ?int $cacheMtime = null;

	/**
	 * Read and parse the file. Returns an empty array when the file is missing.
	 *
	 * @return array<string, string>
	 */
	public static function read(string $path): array
	{
		if (!file_exists($path)) {
			self::$cache = [];
			self::$cacheMtime = null;

			return [];
		}

		clearstatcache(true, $path);
		$mtime = filemtime($path);

		if (self::$cache !== null && self::$cacheMtime === $mtime) {
			return self::$cache;
		}

		self::$cache = DotenvParser::parse((string)file_get_contents($path));
		self::$cacheMtime = $mtime;

		return self::$cache;
	}

	/**
	 * Drop the cached parsed content. Call after writing the file or
	 * switching to a different `.env` path.
	 */
	public static function invalidate(): void
	{
		self::$cache = null;
		self::$cacheMtime = null;
	}
}

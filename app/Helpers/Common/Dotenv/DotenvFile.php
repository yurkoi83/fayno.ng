<?php

declare(strict_types=1);

namespace App\Helpers\Common\Dotenv;

/**
 * DotenvFile — small filesystem helpers used when writing the `.env` file.
 *
 * Stateless.
 */
class DotenvFile
{
	/**
	 * Check that the file is writable. If it doesn't exist yet, check the
	 * parent directory so the file can be created.
	 *
	 * @throws \Exception When the file (or directory) is not writable.
	 */
	public static function ensureFileIsWritable(string $filePath): void
	{
		$exists = is_file($filePath);

		if (($exists && !is_writable($filePath)) || (!$exists && !is_writable(dirname($filePath)))) {
			throw new \Exception(sprintf('The %s file is not writable.', $filePath));
		}
	}

	/**
	 * Ensure the buffer ends with a `\n` so the next appending starts on a new line.
	 */
	public static function ensureTrailingNewline(string $content): string
	{
		if ($content === '' || str_ends_with($content, "\n")) {
			return $content;
		}

		return $content . "\n";
	}
}

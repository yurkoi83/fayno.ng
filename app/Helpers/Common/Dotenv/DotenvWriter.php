<?php

declare(strict_types=1);

namespace App\Helpers\Common\Dotenv;

/**
 * DotenvWriter — apply pending edits to a `.env` content string.
 *
 * Stateless. Pure function on `(content, pending edits) → new content`. The
 * caller is responsible for reading the file before and writing it back
 * after.
 */
class DotenvWriter
{
	/**
	 * Apply pending edits to `$envContent` and return the new content.
	 *
	 * Order: writes (update or append per key), then deletes, then appends
	 * (empty lines / comments at the end). Trailing 3+ blank lines are
	 * collapsed to one, and a final newline is enforced.
	 *
	 * @param array<string, string> $pendingValues `[key => raw value]`
	 * @param array<int, string> $pendingDeletes list of keys to remove
	 * @param array<int, array{type: 'empty'|'comment', value: string}> $pendingAppends
	 * @param array<string, bool> $wrapOverrides explicit per-key wrap choices
	 */
	public static function apply(
		string $envContent,
		array  $pendingValues,
		array  $pendingDeletes,
		array  $pendingAppends,
		array  $wrapOverrides,
	): string
	{
		$wrapStatusOnDisk = DotenvParser::detectWrapStatus($envContent);

		// Apply pending sets — update an existing line or append a new one.
		foreach ($pendingValues as $key => $rawValue) {
			$shouldWrap = self::resolveWrap($key, $rawValue, $wrapOverrides, $wrapStatusOnDisk);
			$writtenValue = $shouldWrap
				? '"' . DotenvEscaper::escapeForDoubleQuote($rawValue) . '"'
				: $rawValue;

			$pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
			if (preg_match($pattern, $envContent)) {
				$envContent = preg_replace($pattern, "{$key}={$writtenValue}", $envContent, 1);
			} else {
				$envContent = DotenvFile::ensureTrailingNewline($envContent);
				$envContent .= "{$key}={$writtenValue}\n";
			}
		}

		// Apply pending deletes — remove the whole `KEY=…` line.
		foreach ($pendingDeletes as $key) {
			$envContent = preg_replace('/^' . preg_quote($key, '/') . '=.*\R?/m', '', (string)$envContent);
		}

		// Apply pending appends — empty lines / comments at the end.
		foreach ($pendingAppends as $append) {
			$envContent = DotenvFile::ensureTrailingNewline($envContent);
			if ($append['type'] === 'empty') {
				$envContent .= "\n";
			} else { // comment
				$line = str_starts_with($append['value'], '#')
					? $append['value']
					: '# ' . $append['value'];
				$envContent .= $line . "\n";
			}
		}

		// Tidy up the result: collapse 3+ blank lines in a row down to
		// one and make sure the file ends with a newline. Trailing blank
		// lines added via addEmpty() are kept.
		$envContent = preg_replace("/\n{3,}/", "\n\n", (string)$envContent);
		if (!str_ends_with($envContent, "\n")) {
			$envContent .= "\n";
		}

		return $envContent;
	}

	/**
	 * Decide whether the next-written line for `$key` should be quoted.
	 *
	 * Priority: explicit override > value-needs-wrapping > preserve disk wrap.
	 *
	 * @param array<string, bool> $wrapOverrides
	 * @param array<string, bool> $wrapStatusOnDisk
	 */
	private static function resolveWrap(string $key, string $value, array $wrapOverrides, array $wrapStatusOnDisk): bool
	{
		if (array_key_exists($key, $wrapOverrides)) {
			return $wrapOverrides[$key];
		}

		if (DotenvEscaper::valueNeedsWrapping($value)) {
			return true;
		}

		return $wrapStatusOnDisk[$key] ?? false;
	}
}

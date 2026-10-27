<?php

declare(strict_types=1);

namespace App\Helpers\Common\Dotenv;

/**
 * DotenvParser — turns raw `.env` content into structured data.
 *
 * Stateless. Mirrors phpdotenv's grammar so the parsed view matches
 * what `env()` would return at runtime.
 */
class DotenvParser
{
	/**
	 * Parse `.env` content into a plain `[key => value]` map.
	 *
	 * Skips blank lines, comments, and malformed lines without `=`.
	 * Mirrors phpdotenv's quoting/escaping rules.
	 *
	 * @return array<string, string>
	 */
	public static function parse(string $content): array
	{
		$result = [];
		$lines = preg_split('/\r\n|\r|\n/', $content);

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}

			if (!str_contains($line, '=')) {
				continue;
			}

			[$key, $rawValue] = explode('=', $line, 2);
			$key = trim($key);
			$rawValue = ltrim($rawValue);

			if (preg_match('/^"((?:\\\\.|[^"\\\\])*)"\s*(?:#.*)?$/', $rawValue, $m)) {
				// Double-quoted: unescape per phpdotenv grammar.
				$value = DotenvEscaper::unescapeFromDoubleQuote($m[1]);
			} else if (preg_match("/^'((?:\\\\.|[^'\\\\])*)'\s*(?:#.*)?$/", $rawValue, $m)) {
				// Single-quoted: literal (no escape interpretation).
				$value = $m[1];
			} else {
				// Unquoted: strip inline comment only when whitespace precedes '#'.
				$value = preg_replace('/\s+#.*$/', '', $rawValue);
				$value = rtrim((string)$value);
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * Detect, for each key currently in the file, whether its line is
	 * quoted (single or double). Used to preserve manual quoting choices
	 * across saves.
	 *
	 * @return array<string, bool>
	 */
	public static function detectWrapStatus(string $content): array
	{
		$result = [];
		$lines = preg_split('/\r\n|\r|\n/', $content);

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
				continue;
			}
			[$key, $rawValue] = explode('=', $line, 2);
			$key = trim($key);
			$rawValue = ltrim($rawValue);
			$result[$key] = str_starts_with($rawValue, '"') || str_starts_with($rawValue, "'");
		}

		return $result;
	}
}

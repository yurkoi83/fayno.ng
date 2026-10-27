<?php

declare(strict_types=1);

namespace App\Helpers\Common\Dotenv;

/**
 * DotenvEscaper — quoting/escaping rules for `.env` values.
 *
 * Stateless. Mirrors the quoting/escaping behavior of vlucas/phpdotenv so
 * values written here round-trip identically through `env()`.
 */
class DotenvEscaper
{
	/**
	 * Whether a value must be wrapped in double quotes to be a valid .env entry.
	 * Triggers on whitespace, quotes, `\`, `#`, `=`, or any C0/DEL control char.
	 */
	public static function valueNeedsWrapping(string $value): bool
	{
		if ($value === '') {
			return false; // KEY= is valid (phpdotenv reads as empty string)
		}

		return preg_match('/[\s"\'\\\\#=]|[\x00-\x1F\x7F]/', $value) === 1;
	}

	/**
	 * Escape a value for inclusion inside a double-quoted .env entry.
	 * Mirrors the escape sequences vlucas/phpdotenv decodes on read.
	 */
	public static function escapeForDoubleQuote(string $value): string
	{
		return strtr($value, [
			'\\' => '\\\\',
			'"'  => '\\"',
			"\n" => '\\n',
			"\r" => '\\r',
			"\t" => '\\t',
			"\f" => '\\f',
			"\v" => '\\v',
		]);
	}

	/**
	 * Decode a double-quoted .env value back to its raw PHP string.
	 * Inverse of escapeForDoubleQuote(); matches phpdotenv's read behavior.
	 */
	public static function unescapeFromDoubleQuote(string $value): string
	{
		return strtr($value, [
			'\\\\' => '\\',
			'\\"'  => '"',
			'\\n'  => "\n",
			'\\r'  => "\r",
			'\\t'  => "\t",
			'\\f'  => "\f",
			'\\v'  => "\v",
		]);
	}
}

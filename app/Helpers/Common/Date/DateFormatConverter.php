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

namespace App\Helpers\Common\Date;

/**
 * Handles date format conversion between different syntaxes
 */
class DateFormatConverter
{
	/**
	 * Determine if a date format uses ISO formatting syntax
	 *
	 * @param $format
	 * @return bool
	 */
	public static function isIsoFormat($format): bool
	{
		$isIsoFormat = false;
		
		$splitChars = preg_split('#([ \-/.,:;])#', $format);
		$splitChars = array_filter($splitChars);
		
		if (!empty($splitChars)) {
			foreach ($splitChars as $char) {
				if (in_array($char, self::diffBetweenIsoAndDateTimeFormats())) {
					$isIsoFormat = true;
					break;
				}
			}
		}
		
		return $isIsoFormat;
	}
	
	/**
	 * Convert strftime format to standard date format
	 * Equivalent to `date_format_to( $format, 'date' )`
	 *
	 * @param string $strfFormat A `strftime()` date/time format
	 * @return bool|string
	 */
	public static function strftimeToDateFormat(string $strfFormat): bool|string
	{
		return self::dateFormatTo($strfFormat, 'date');
	}
	
	/**
	 * Convert standard date format to strftime format
	 * Equivalent to `convert_datetime_format_to( $format, 'strf' )`
	 *
	 * @param string $dateFormat A `date()` date/time format
	 * @return bool|string
	 */
	public static function dateToStrftimeFormat(string $dateFormat): bool|string
	{
		return self::dateFormatTo($dateFormat, 'strf');
	}
	
	/**
	 * Convert an ISO date format to its PHP `date()` equivalent
	 * (i.e. 'YYYY-MM-DD HH:mm' to 'Y-m-d H:i')
	 *
	 * ISO tokens follow the Carbon/moment.js `isoFormat()` syntax.
	 * Literal text wrapped in square brackets (e.g. '[at]') is unwrapped
	 * and escaped for `date()` with a backslash.
	 *
	 * @param string $isoFormat An ISO (Carbon `isoFormat()`) date/time format
	 * @return string A PHP `date()` date/time format
	 *
	 * @example Convert 'YYYY-MM-DD HH:mm' to 'Y-m-d H:i'
	 */
	public static function isoToDateFormat(string $isoFormat): string
	{
		return self::convertFormatTokens($isoFormat, self::isoToDateMap(), 'iso');
	}
	
	/**
	 * Convert a PHP `date()` format to its ISO equivalent
	 * (i.e. 'Y-m-d H:i' to 'YYYY-MM-DD HH:mm')
	 *
	 * ISO tokens follow the Carbon/moment.js `isoFormat()` syntax.
	 * Backslash-escaped literal characters (e.g. '\a\t') are wrapped in
	 * square brackets so `isoFormat()` keeps them literal.
	 *
	 * @param string $dateFormat A PHP `date()` date/time format
	 * @return string An ISO (Carbon `isoFormat()`) date/time format
	 *
	 * @example Convert 'Y-m-d H:i' to 'YYYY-MM-DD HH:mm'
	 */
	public static function dateToIsoFormat(string $dateFormat): string
	{
		$isoFormat = self::convertFormatTokens($dateFormat, self::dateToIsoMap(), 'date');
		
		// Merge adjacent bracket-quoted literals: '[H][i]' becomes '[Hi]'
		return str_replace('][', '', $isoFormat);
	}
	
	/**
	 * Convert date/time format between `date()` and `strftime()` syntaxes
	 *
	 * Timezone conversion is done for Unix. Windows users must exchange %z and %Z.
	 *
	 * Unsupported date formats : S, n, t, L, B, G, u, e, I, P, Z, c, r
	 * Unsupported strftime formats : %U, %W, %C, %g, %r, %R, %T, %X, %c, %D, %F, %x
	 *
	 * @param string $format The format to parse.
	 * @param string $syntax The format's syntax. Either 'strf' for `strtime()` or 'date' for `date()`.
	 * @return bool|string Returns a string formatted according $syntax using the given $format or `false`.
	 * @link http://php.net/manual/en/function.strftime.php#96424
	 *
	 * @example Convert `%A, %B %e, %Y, %l:%M %P` to `l, F j, Y, g:i a`, and vice versa for "Saturday, March 10, 2001, 5:16 pm"
	 */
	private static function dateFormatTo(string $format, string $syntax): bool|string
	{
		// http://php.net/manual/en/function.strftime.php
		$strfSyntax = [
			// Day - no strf eq : S (created one called %O)
			'%O', '%d', '%a', '%e', '%A', '%u', '%w', '%j',
			// Week - no date eq : %U, %W
			'%V',
			// Month - no strf eq : n, t
			'%B', '%m', '%b', '%-m',
			// Year - no strf eq : L; no date eq : %C, %g
			'%G', '%Y', '%y',
			// Time - no strf eq : B, G, u; no date eq : %r, %R, %T, %X
			'%P', '%p', '%l', '%I', '%H', '%M', '%S',
			// Timezone - no strf eq : e, I, P, Z
			'%z', '%Z',
			// Full Date / Time - no strf eq : c, r; no date eq : %c, %D, %F, %x
			'%s',
		];
		
		// http://php.net/manual/en/function.date.php
		$dateSyntax = [
			'S', 'd', 'D', 'j', 'l', 'N', 'w', 'z',
			'W',
			'F', 'm', 'M', 'n',
			'o', 'Y', 'y',
			'a', 'A', 'g', 'h', 'H', 'i', 's',
			'O', 'T',
			'U',
		];
		
		switch ($syntax) {
			case 'date':
				$from = $strfSyntax;
				$to = $dateSyntax;
				break;
			
			case 'strf':
				$from = $dateSyntax;
				$to = $strfSyntax;
				break;
			
			default:
				return false;
		}
		
		$pattern = array_map(
			function ($s) {
				return '/(?<!\\\\|\%)' . $s . '/';
			},
			$from
		);
		
		$format = preg_replace($pattern, $to, $format);
		
		return castToString($format);
	}
	
	/**
	 * Convert a date/time format from one token syntax to another
	 *
	 * Walks the format left to right, greedily matching the longest known
	 * token at each position, so multi-character tokens (e.g. 'YYYY') are
	 * never partially re-matched. Literal sections are preserved:
	 *  - 'iso'  source: square-bracket literals '[…]' are escaped for `date()`
	 *  - 'date' source: backslash-escaped chars '\X' become bracket literals '[X]'
	 * Any character that is neither a literal nor a known token is copied as-is
	 * (covers separators such as '-', '/', ':', '.', ',' and spaces).
	 *
	 * @param string $format The format to convert
	 * @param array $map Map of source tokens to target tokens
	 * @param string $sourceSyntax The source syntax. Either 'iso' or 'date'.
	 * @return string
	 */
	private static function convertFormatTokens(string $format, array $map, string $sourceSyntax): string
	{
		// Match longer tokens before their prefixes (e.g. 'YYYY' before 'YY' before 'Y')
		$tokens = array_keys($map);
		usort($tokens, fn ($a, $b) => strlen($b) <=> strlen($a));
		
		$result = '';
		$length = strlen($format);
		$i = 0;
		
		while ($i < $length) {
			$char = $format[$i];
			
			// ISO bracket-quoted literal: '[text]' -> escaped `date()` literal
			if ($sourceSyntax === 'iso' && $char === '[') {
				$end = strpos($format, ']', $i + 1);
				if ($end !== false) {
					$literal = substr($format, $i + 1, $end - $i - 1);
					$result .= preg_replace('/([A-Za-z])/', '\\\\$1', $literal);
					$i = $end + 1;
					continue;
				}
			}
			
			// PHP `date()` backslash-escaped literal char -> ISO bracket literal
			if ($sourceSyntax === 'date' && $char === '\\') {
				if ($i + 1 < $length) {
					$result .= '[' . $format[$i + 1] . ']';
					$i += 2;
					continue;
				}
			}
			
			// Match the longest known token at the current position
			$matched = false;
			foreach ($tokens as $token) {
				$tokenLength = strlen($token);
				if ($tokenLength > 0 && substr($format, $i, $tokenLength) === $token) {
					$result .= $map[$token];
					$i += $tokenLength;
					$matched = true;
					break;
				}
			}
			
			if (!$matched) {
				$result .= $char;
				$i++;
			}
		}
		
		return $result;
	}
	
	/**
	 * Get the ISO -> PHP `date()` token map
	 *
	 * Keys are Carbon/moment.js `isoFormat()` tokens, values are the matching
	 * PHP `date()` tokens. Tokens with no exact PHP equivalent are mapped to
	 * the closest token (noted inline); tokens absent from the map (e.g., the
	 * quarter token 'Q') are copied through unchanged.
	 *
	 * @return array
	 * @link https://carbon.nesbot.com/guide/getting-started/localization.html
	 * @link https://www.php.net/manual/en/datetime.format.php
	 */
	private static function isoToDateMap(): array
	{
		return [
			// Year
			'YYYY'   => 'Y', 'YY' => 'y', 'Y' => 'Y',
			'GGGG'   => 'o', 'gggg' => 'o',
			// Month
			'MMMM'   => 'F', 'MMM' => 'M', 'MM' => 'm', 'M' => 'n',
			// Week of year
			'WW'     => 'W', 'W' => 'W', 'ww' => 'W', 'w' => 'W',
			// Day of year (ISO is 1-based, PHP 'z' is 0-based: approximate)
			'DDDD'   => 'z', 'DDD' => 'z',
			// Day of month
			'DD'     => 'd', 'Do' => 'jS', 'D' => 'j',
			// Day of week
			'dddd'   => 'l', 'ddd' => 'D', 'd' => 'w', 'E' => 'N', 'e' => 'w',
			// Hour
			'HH'     => 'H', 'H' => 'G', 'hh' => 'h', 'h' => 'g',
			// Minute (ISO 'm' has no zero-less PHP equivalent: maps to 'i')
			'mm'     => 'i', 'm' => 'i',
			// Second (ISO 's' has no zero-less PHP equivalent: maps to 's')
			'ss'     => 's', 's' => 's',
			// Fractional second
			'SSSSSS' => 'u', 'SSS' => 'v',
			// Meridiem
			'A'      => 'A', 'a' => 'a',
			// Timezone
			'ZZ'     => 'O', 'Z' => 'P', 'zz' => 'T', 'z' => 'T',
			// Unix timestamp (seconds)
			'X'      => 'U',
		];
	}
	
	/**
	 * Get the PHP `date()` -> ISO token map
	 *
	 * Keys are PHP `date()` tokens, values are the matching Carbon/moment.js
	 * `isoFormat()` tokens. Tokens with no exact ISO equivalent are mapped to
	 * the closest token (noted inline); tokens absent from the map (e.g. 'S',
	 * 't', 'L', 'B') are copied through unchanged.
	 *
	 * @return array
	 * @link https://www.php.net/manual/en/datetime.format.php
	 * @link https://carbon.nesbot.com/guide/getting-started/localization.html
	 */
	private static function dateToIsoMap(): array
	{
		return [
			// Year
			'Y'  => 'YYYY', 'y' => 'YY', 'o' => 'GGGG',
			// Month
			'F'  => 'MMMM', 'M' => 'MMM', 'm' => 'MM', 'n' => 'M',
			// Week of year (ISO-8601)
			'W'  => 'WW',
			// Day of year (PHP 'z' is 0-based, ISO 'DDD' is 1-based: approximate)
			'z'  => 'DDD',
			// Day of month ('jS' -> 'Do' must precede the lone 'j')
			'jS' => 'Do', 'd' => 'DD', 'j' => 'D',
			// Day of week
			'l'  => 'dddd', 'D' => 'ddd', 'N' => 'E', 'w' => 'd',
			// Hour
			'H'  => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h',
			// Minute
			'i'  => 'mm',
			// Second
			's'  => 'ss',
			// Fractional second
			'u'  => 'SSSSSS', 'v' => 'SSS',
			// Meridiem
			'A'  => 'A', 'a' => 'a',
			// Timezone
			'O'  => 'ZZ', 'P' => 'Z', 'T' => 'z', 'e' => 'zz',
			// Unix timestamp (seconds)
			'U'  => 'X',
		];
	}
	
	/**
	 * Get format tokens that differ between ISO and DateTime formats
	 *
	 * @return array Array of format tokens unique to ISO format
	 */
	private static function diffBetweenIsoAndDateTimeFormats(): array
	{
		return array_diff(self::isoFormatReplacement(), self::dateTimeFormatReplacement());
	}
	
	/**
	 * Get ISO date format replacement tokens
	 *
	 * @return array Array of ISO format tokens
	 * @link https://carbon.nesbot.com/guide/getting-started/localization.html
	 */
	private static function isoFormatReplacement(): array
	{
		return [
			'OD', 'OM', 'OY', 'OH', 'Oh', 'Om', 'Os', 'D', 'DD', 'Do',
			'd', 'dd', 'ddd', 'dddd', 'DDD', 'DDDD', 'DDDo', 'e', 'E',
			'H', 'HH', 'h', 'hh', 'k', 'kk', 'm', 'mm', 'a', 'A', 's', 'ss', 'S', 'SS', 'SSS', 'SSSS', 'SSSSS', 'SSSSSS', 'SSSSSSS', 'SSSSSSSS', 'SSSSSSSSS',
			'M', 'MM', 'MMM', 'MMMM', 'Mo', 'Q', 'Qo',
			'G', 'GG', 'GGG', 'GGGG', 'GGGGG', 'g', 'gg', 'ggg', 'gggg', 'ggggg', 'W', 'WW', 'Wo', 'w', 'ww', 'wo',
			'x', 'X',
			'Y', 'YY', 'YYYY', 'YYYYY',
			'z', 'zz', 'Z', 'ZZ',
			// Macro-formats
			'LT', 'LTS', 'L', 'l', 'LL', 'll', 'LLL', 'lll', 'LLLL', 'llll',
		];
	}
	
	/**
	 * Get standard DateTime format replacement tokens
	 *
	 * @return array Array of DateTime format tokens
	 * @link https://www.php.net/manual/en/datetime.format.php
	 */
	private static function dateTimeFormatReplacement(): array
	{
		return [
			// Day
			'd', 'D', 'j', 'l', 'N', 'S', 'w', 'z',
			// Week
			'W',
			// Month
			'F', 'm', 'M', 'n', 't',
			// Year
			'L', 'o', 'Y', 'y',
			// Time
			'a', 'A', 'B', 'g', 'G', 'h', 'H', 'i', 's', 'u', 'v',
			// Timezone
			'e', 'I', 'O', 'P', 'p', 'T', 'Z',
			// Full Date/Time
			'c', 'r', 'U',
		];
	}
	
	/**
	 * Get strftime format replacement tokens
	 *
	 * @return array Array of strftime format tokens
	 * @link https://www.php.net/manual/en/function.strftime.php
	 */
	private static function strftimeFormatReplacement(): array
	{
		return [
			// Day
			'%a', '%A', '%d', '%e', '%j', '%u', '%w',
			// Week
			'%U', '%V', '%W',
			// Month
			'%b', '%B', '%h', '%m',
			// Year
			'%C', '%g', '%G', '%y', '%Y',
			// Time
			'%H', '%k', '%I', '%l', '%M', '%p', '%P', '%r', '%R', '%S', '%T', '%X', '%z', '%Z',
			// Time and Date Stamps
			'%c', '%D', '%F', '%s', '%x',
			// Miscellaneous
			'%n', '%t', '%%',
		];
	}
}

<?php

/**
 * Email address prefix (local-part) mask
 *
 * @param string|null $value
 * @param int $escapedChars
 * @return string|null
 */
function emailPrefixMask(?string $value, int $escapedChars = 1): ?string
{
	if ($value === null) {
		return null;
	}

	$atPos = mb_stripos($value, '@');
	if ($atPos === false) {
		return $value;
	}

	$emailUsername = mb_substr($value, 0, $atPos);
	$emailDomain = mb_substr($value, ($atPos + 1));

	if (!empty($emailUsername) && !empty($emailDomain)) {
		$emailUsername = str($emailUsername)->mask('x', $escapedChars)->toString();
		$value = $emailUsername . '@' . $emailDomain;
	}

	return $value;
}

/**
 * Replace newlines with a space
 *
 * I.e., Convert \n, \r\n, and \r to simple space in string
 * Note: PHP_EOL catches newlines that \n, \r\n, \r miss.
 *
 * Note: This function doesn't remove spaces duplication.
 *
 * @param string|null $string $string
 * @return string
 */
function replaceNewlinesWithSpace(?string $string): string
{
	$string = str_replace(PHP_EOL, ' ', strval($string));

	return trim($string);
}

/**
 * Replace all non-breaking space (NBSP) characters (\x{00A0}) with simple space
 *
 * Note: In HTML, the common non-breaking space,
 * which is the same width as the ordinary space character, is encoded as &nbsp; or &#160;.
 * In Unicode, it is encoded as U+00A0.
 *
 * More info: https://en.wikipedia.org/wiki/Non-breaking_space
 *            https://graphemica.com/00A0
 *
 * @param string|null $string
 * @return string
 */
function replaceNonBreakingSpaceWithSpace(?string $string): string
{
	$string = preg_replace('~\x{00a0}~u', ' ', $string);

	return trim($string);
}

/**
 * Normalize simple spaces in a string
 * i.e., Replace multiple spaces with a single space but preserve other whitespace characters
 *
 * @param string|null $string
 * @return string
 */
function normalizeSpace(?string $string): string
{
	$string = strval($string);

	$string = replaceNonBreakingSpaceWithSpace($string);
	$string = preg_replace('/ +/', ' ', $string);

	return trim($string);
}

/**
 * Normalize whitespace in a string
 * i.e., Replace newlines with space and remove duplicate spaces
 *
 * @param string|null $string
 * @return string
 */
function normalizeWhitespace(?string $string): string
{
	$string = strval($string);

	$string = replaceNonBreakingSpaceWithSpace($string);
	$string = preg_replace('/\s\s+/u', ' ', $string);

	return trim($string);
}

/**
 * Remove diacritics from a string
 *
 * @param string|null $string
 * @return string|null
 */
function removeDiacritics(?string $string): ?string
{
	if (is_null($string)) return null;
	$diacritics = getCachedReferrerList('diacritics');

	return strtr($string, $diacritics);
}

/**
 * Sanitize input to prevent XSS attacks and remove malicious characters.
 *
 * @param string|null $input
 * @return array|string
 */
function sanitizeInput(?string $input): array|string
{
	if (empty($input)) return '';

	// 1. Remove all HTML tags
	$input = strip_tags($input);

	// 2.a. Convert HTML entities to their corresponding characters
	$input = html_entity_decode($input, ENT_QUOTES);

	// 2.b. And remove the converted HTML tags
	$input = strip_tags($input);

	// 3.a. Convert special characters to HTML entities
	$input = htmlspecialchars($input, ENT_QUOTES);

	// 3.b. Remove the converted HTML tags
	$input = strip_tags($input);

	// 3.c. Convert special HTML entities back to characters
	$input = htmlspecialchars_decode($input, ENT_QUOTES);

	// 4. Normalize all simple spaces (except other whitespaces)
	$input = normalizeSpace($input);

	// Remove any remaining non-printable characters
	return trim($input);
}

/**
 * Single line string cleaner
 *
 * @param string|null $string
 * @return string
 */
function singleLineStringCleaner(?string $string): string
{
	$string = strval($string);

	$string = sanitizeInput($string);                 // Sanitize input to prevent XSS attacks and remove malicious characters
	$string = stripUtf8mb4CharsIfNotEnabled($string); // Remove 4(+)-byte characters (If it is not enabled)
	$string = normalizeWhitespace($string);           // Normalize all whitespaces

	return trim($string);
}

/**
 * Strict single-line string cleaner
 *
 * @param string|null $string
 * @param bool $allowEmojis
 * @return string
 */
function singleLineStringCleanerStrict(?string $string, bool $allowEmojis = true): string
{
	$string = strval($string);

	$string = sanitizeInput($string);                 // Sanitize input to prevent XSS attacks and remove malicious characters
	$string = stripUtf8mb4CharsIfNotEnabled($string); // Remove 4(+)-byte characters (If it is not enabled)
	if (!$allowEmojis) {
		$string = stripUtf8mb4Chars($string);
		$string = stripEmojis($string);
	}
	$string = normalizeWhitespace($string); // Normalize all whitespaces
	$string = trim($string);

	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Multi lines string cleaner
 *
 * @param string|null $string
 * @return string
 */
function multiLinesStringCleaner(?string $string): string
{
	$string = strval($string);

	$string = strip_tags($string, '<br><br/>');                  // Remove HTML tags (except <br>)
	$string = preg_replace('/<br\s*\/?[^>]*>/i', "\n", $string); // Convert <br> tags to \n
	$string = preg_replace("/[\r\n]+/", "\n", $string);
	$string = stripUtf8mb4CharsIfNotEnabled($string); // Remove 4(+)-byte characters (If it is not enabled)
	$string = normalizeSpace($string);                // Normalize all simple spaces (except other whitespaces)

	return mb_ucfirst(trim($string));
}

/**
 * Convert a given entry (string|array) to (clean) tags
 *
 * Note:
 * - Explode to array the given string by one of following characters: ":,;#_\|\n\t"
 * - Remove all tags staring and ending by a number, preventing issues with the #hashtags when they are only numeric
 * - Remove special chars from each exploded element (i.e. from each tag)
 * - Change all the tags case to lowercase
 * - Select only tags with more than 1 character (minimum with 2 characters)
 * - Remove duplicated tags
 *
 * @param array|string|null $value
 * @param int $limit
 * @param bool $asArray
 * @return array|string|null
 */
function taggable(array|string|null $value, int $limit = 15, bool $asArray = false): array|string|null
{
	if (!is_array($value) && !is_string($value)) {
		return $asArray ? [] : null;
	}

	$arrayExpected = false;
	if (is_array($value)) {
		$arraySrc = $value;
		$arrayExpected = true;
	} else {
		$arraySrc = preg_split('|[:,;#_\|\n\t]+|ui', $value);
	}

	$tags = [];
	$i = 0;
	foreach ($arraySrc as $tag) {
		$tag = singleLineStringCleaner($tag);

		// Remove all tags (simultaneously) staring and ending by a number
		$tag = preg_replace('/\b\d+\b/ui', '', $tag);

		// Remove special characters
		$tag = str_replace([':', ',', ';', '_', '\\', '/', '|', '+'], '', $tag);

		// Change the tag case (lowercase)
		$tag = mb_strtolower(trim($tag));

		// Check valid tag (tag must have more one character)
		$isValid = (!empty($tag) && mb_strlen($tag) > 1);

		// Save the tag in array
		if ($isValid) {
			if ($i <= $limit) {
				$tags[] = $tag;
			}
			$i++;
		}
	}

	$tags = array_unique($tags);
	if ($arrayExpected || $asArray) {
		return $tags;
	}

	return !empty($tags) ? implode(',', $tags) : null;
}

/**
 * @return string
 */
function tagRegexPattern(): string
{
	/*
	 * Tags (Only allow letters, numbers, spaces and ',;_-' symbols)
	 *
	 * Explanation:
	 * [] 	=> character class definition
	 * p{L} => matches any kind of letter character from any language
	 * p{N} => matches any kind of numeric character
	 * _- 	=> matches underscore and hyphen
	 * + 	=> Quantifier — Matches between one to unlimited times (greedy)
	 * /u 	=> Unicode modifier. Pattern strings are treated as UTF-16. Also causes escape sequences to match unicode characters
	 */
	return '/^[\p{L}\p{N} ,;_-]+$/u';
}

/**
 * Ensure the given string contains at least one letter
 *
 * Validates that the string includes one or more alphabetic characters
 * (from any language script). Useful for rejecting strings that consist
 * only of numbers, symbols, or whitespace.
 *
 * Example:
 * - "John123" → "John123"
 * - "12345" → ""
 * - "@@@ 789" → ""
 *
 * @param string|null $string Input string to validate
 * @return string The original string if it contains at least one letter, or an empty string otherwise
 */
function ensureContainsLetters(?string $string): string
{
	return containsLetters($string) ? $string : '';
}

/**
 * Check if a string contains at least one letter
 *
 * @param string|null $string
 * @return bool True if the string contains at least one alphabetic character
 */
function containsLetters(?string $string): bool
{
	if ($string === null || $string === '') {
		return false;
	}

	// Remove special characters and whitespace for reliable checking
	$string = stripSpecialChars($string);
	$string = stripWhitespace($string);

	// Check for at least one alphabetic character (any language script)
	return preg_match('/\p{L}/u', $string) === 1;
}

/**
 * Remove all whitespace from a multibyte string
 *
 * @param string|null $string
 * @param string $replacement
 * @return string
 */
function stripWhitespace(?string $string, string $replacement = ''): string
{
	if ($string === null || $string === '') {
		return '';
	}

	// White-space = [ \t\r\n\f];
	$string = preg_replace('/\s+/u', $replacement, $string);

	return castToString($string);
}

/**
 * Strip special chars from a multibyte string
 * i.e., Remove all non-alphanumeric characters except spaces in any language script
 *
 * $string = "Héllo, Wørld! This is a t€st. 123_456! 你好，こんにちは";
 * $string = stripSpecialChars($string);
 * Output: "Héllo Wørld This is a tst 123456 你好こんにちは"
 *
 * @param string|null $string
 * @param string $replacement
 * @return string
 */
function stripSpecialChars(?string $string, string $replacement = ''): string
{
	if ($string === null || $string === '') {
		return '';
	}

	/*
	 * \p{L} matches any kind of letter from any language script
	 * \p{N} matches any kind of numeric character in any language script
	 * \s matches any whitespace
	 * ...and keep them. i.e.: Remove non-word characters except whitespaces
	 *
	 * Note: Non-word means:
	 * Word characters are English letters and digits
	 * Word characters in multibyte are letters (in all languages) and digits
	 */
	$string = preg_replace('/[^\p{L}\p{N}\s]/u', $replacement, $string);

	return castToString($string);
}

/**
 * Remove all emoji characters but keep symbols like €, $, etc.
 *
 * More Info: https://bedigit.com/article/the-essentials-of-utf-8-what-are-4-byte-characters/
 *
 * @param string|null $string
 * @return string
 */
function stripEmojis(?string $string): string
{
	$string = strval($string);

	$string = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $string); // Emoticons
	$string = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $string); // Misc Symbols and Pictographs
	$string = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $string); // Transport and Map Symbols
	$string = preg_replace('/[\x{1F700}-\x{1F77F}]/u', '', $string); // Alchemical Symbols
	$string = preg_replace('/[\x{1F780}-\x{1F7FF}]/u', '', $string); // Geometric Shapes Extended
	$string = preg_replace('/[\x{1F800}-\x{1F8FF}]/u', '', $string); // Supplemental Arrows-C
	$string = preg_replace('/[\x{1F900}-\x{1F9FF}]/u', '', $string); // Supplemental Symbols and Pictographs
	$string = preg_replace('/[\x{1FA00}-\x{1FA6F}]/u', '', $string); // Chess Symbols
	$string = preg_replace('/[\x{1FA70}-\x{1FAFF}]/u', '', $string); // Symbols and Pictographs Extended-A
	$string = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $string);   // Miscellaneous Symbols
	$string = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $string);   // Dingbats

	return castToString($string);
}

/**
 * Remove 4(+)-byte characters from a UTF-8 string (those supported by utf8mb4 but not by utf8).
 *
 * More Info: https://bedigit.com/article/the-essentials-of-utf-8-what-are-4-byte-characters/
 *
 * @param string|null $string
 * @return string
 */
function stripUtf8mb4Chars(?string $string): string
{
	// Matches 4(+)-byte UTF-8 sequences and remove them
	// $string = preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', strval($string));
	$string = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', strval($string));

	return castToString($string);
}

/**
 * Remove all non-ASCII and extended UTF-8 characters, including emojis.
 *
 * @param string|null $string
 * @return string
 */
function stripNonAsciiAndExtendedChars(?string $string): string
{
	/*
	 * \p{L} matches any kind of letter from any language script
	 * \p{N} matches any kind of numeric character in any language script (Optional)
	 * \p{M} matches a character intended to be combined with another character (e.g., accents, umlauts, enclosing boxes, etc.)
	 * [:ascii:] matches a character with ASCII value 0 through 127
	 */
	$string = preg_replace('/[^\p{L}\p{N}\p{M}[:ascii:]]+/ui', '', strval($string));

	return castToString($string);
}

/**
 * Get URL host
 *
 * @param string|null $url
 * @return string|null
 */
function getUrlHost(?string $url): ?string
{
	if (empty($url)) return null;

	// In case scheme relative URI is passed, e.g., //www.google.com/
	$url = trim($url, '/');

	// If a scheme not included, prepend it
	if (!preg_match('#^http(s)?://#', $url)) {
		$url = 'http' . '://' . $url;
	}

	$parts = parse_url($url);
	$host = preg_replace('/^www\./', '', $parts['host']); // remove www

	return castToStringOrNull($host);
}

/**
 * Add rel="nofollow" to links in string
 *
 * @param string|null $html
 * @param string|null $skip
 * @return string
 */
function noFollowLinks(?string $html, ?string $skip = null): string
{
	$callback = function ($mach) use ($skip) {
		$link = $mach[1] ?? null;
		$orig = $mach[0] ?? null;
		$isSkipped = (!empty($skip) && str_contains($link, $skip));
		$hasNoFollow = str_contains($link, 'rel=');

		return (!$isSkipped && !$hasNoFollow) ? $link . ' rel="nofollow">' : $orig;
	};
	$html = preg_replace_callback("#(<a[^>]+?)>#is", $callback, strval($html));

	return castToString($html);
}

/**
 * Create auto-links for URLs in string
 *
 * @param string|null $str
 * @param array $attributes
 * @return string
 */
function urlsToLinks(?string $str, array $attributes = []): string
{
	// Transform URL to an HTML link
	$attrs = '';
	foreach ($attributes as $attribute => $value) {
		$attrs .= " {$attribute}=\"{$value}\"";
	}

	$str = ' ' . $str;

	$pattern = '`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i';
	$replacement = '$1<a rel="nofollow" href="$2"' . $attrs . ' target="_blank">$2</a>';
	$str = preg_replace($pattern, $replacement, $str);

	$str = substr($str, 1);

	// Add rel="nofollow" to links
	$httpHost = request()->server('HTTP_HOST');
	$parse = parse_url('http' . '://' . $httpHost);
	$str = noFollowLinks($str, $parse['host']);

	// Find and attach target="_blank" to all href links from text
	return targetBlankLinks($str);
}

/**
 * Find and attach target="_blank" to all href links in string
 *
 * @param string|null $content
 * @return string
 */
function targetBlankLinks(?string $content): string
{
	// Find all links
	preg_match_all('/<a ((?!target)[^>])+?>/ui', strval($content), $matches);

	// Loop only the first array to modify links
	if (is_array($matches) && isset($matches[0])) {
		foreach ($matches[0] as $key => $value) {
			// Take orig link
			$origLink = $value;

			// Does it have target="_blank"
			if (!preg_match('/target="_blank"/ui', $origLink)) {
				// Add target = "_blank"
				$newLink = preg_replace("/<a(.*?)>/ui", "<a$1 target=\"_blank\">", $origLink);

				// Replace the old link in content with the new link
				$content = str_replace($origLink, $newLink, $content);
			}
		}
	}

	return castToString($content);
}

/**
 * Convert hex value to rgb array
 *
 * Example usage
 * $hex = "#0072B5";
 * $rgbArray = hexToRgb($hex);
 * print_r($rgbArray); // Output: ['r' => 0, 'g' => 114, 'b' => 181]
 *
 * @param string $hex
 * @param string|null $invalidColor
 * @return array
 */
function hexToRgb(string $hex, ?string $invalidColor = 'white'): array
{
	// Handle 'transparent' keyword
	if ($hex === 'transparent') {
		return ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]; // Fully transparent
	}

	// Remove the '#' if it exists
	$hex = str_replace('#', '', $hex);

	// Handle shorthand hex (e.g., #03F) by expanding it
	if (strlen($hex) == 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	// Check if the hex code is valid
	if (strlen($hex) != 6 || !ctype_xdigit($hex)) {
		// Return white or black RGB based on the $invalidColor parameter
		return $invalidColor === 'white'
			? ['r' => 255, 'g' => 255, 'b' => 255]
			: ['r' => 0, 'g' => 0, 'b' => 0];
	}

	// Split the hex into red, green, and blue components
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	// Return the RGB associative array
	return ['r' => $r, 'g' => $g, 'b' => $b];
}

/**
 * Convert hex value to rgb(a) array
 *
 * Example usage
 * $hex = "#0072B5";
 * $rgbaArray = hexToRgba($hex, 0.5); // Alpha = 0.5
 *
 * @param string $hex
 * @param float $alpha
 * @param string|null $invalidColor
 * @return array
 */
function hexToRgba(string $hex, float $alpha = 1, ?string $invalidColor = 'white'): array
{
	// Handle 'transparent' keyword
	if ($hex === 'transparent') {
		return ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]; // Fully transparent
	}

	// Remove the '#' if it exists
	$hex = str_replace('#', '', $hex);

	// Handle shorthand hex (e.g., #03F) by expanding it
	if (strlen($hex) == 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	// Check if the hex code is valid
	if (strlen($hex) != 6 || !ctype_xdigit($hex)) {
		// Return white or black RGBA based on the $invalidColor parameter
		return $invalidColor === 'white'
			? ['r' => 255, 'g' => 255, 'b' => 255, 'a' => $alpha]
			: ['r' => 0, 'g' => 0, 'b' => 0, 'a' => $alpha];
	}

	// Split the hex into red, green, and blue components
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	// Return the RGBA associative array
	return ['r' => $r, 'g' => $g, 'b' => $b, 'a' => $alpha];
}

/**
 * Convert RGB/RGBA Array to CSS Code
 *
 * Example usage with hexToRgb()
 * $hex = "#0072B5";
 * $rgbArray = hexToRgb($hex);
 * $cssRgb = rgbToCss($rgbArray);
 * echo $cssRgb; // Output: rgb(0, 114, 181)
 * ---
 * Example usage with hexToRgba()
 * $rgbaArray = hexToRgba($hex, 0.5); // Alpha = 0.5
 * $cssRgba = rgbToCss($rgbaArray);
 * echo $cssRgba; // Output: rgba(0, 114, 181, 0.50)
 *
 * @param array $rgbArray
 * @param bool $raw
 * @return string
 */
function rgbToCss(array $rgbArray, bool $raw = false): string
{
	// Handle 'transparent' case
	if (isset($rgbArray['a']) && $rgbArray['a'] == 0) {
		return $raw ? '0, 0, 0, 0' : 'rgba(0, 0, 0, 0)';
	}

	// Check if the array has an alpha channel
	if (isset($rgbArray['a'])) {
		$css = sprintf(
			'%d, %d, %d, %.2f',
			$rgbArray['r'],
			$rgbArray['g'],
			$rgbArray['b'],
			$rgbArray['a']
		);
	} else {
		$css = sprintf(
			'%d, %d, %d',
			$rgbArray['r'],
			$rgbArray['g'],
			$rgbArray['b']
		);
	}

	// Return raw values if $raw is true, otherwise wrap in rgb() or rgba()
	return $raw ? $css : (isset($rgbArray['a']) ? "rgba($css)" : "rgb($css)");
}

if (!function_exists('mb_ucfirst')) {
	/**
	 * ucfirst() function for multibyte character encodings
	 *
	 * @param string|null $string
	 * @param string $encoding
	 * @return string
	 */
	function mb_ucfirst(?string $string, string $encoding = 'utf-8'): string
	{
		$string = strval($string);
		$strLen = mb_strlen($string, $encoding);
		$firstChar = mb_substr($string, 0, 1, $encoding);
		$then = mb_substr($string, 1, $strLen - 1, $encoding);

		return mb_strtoupper($firstChar, $encoding) . $then;
	}
}

/**
 * ucwords() function for multibyte character encodings
 *
 * @param string|null $string
 * @param string $encoding
 * @return string
 */
function mb_ucwords(?string $string, string $encoding = 'utf-8'): string
{
	$tab = [];

	// Split the phrase by any number of space characters, which include " ", \r, \t, \n and \f
	$words = preg_split('/\s+/ui', strval($string));
	if (!empty($words)) {
		foreach ($words as $key => $word) {
			$tab[$key] = mb_ucfirst($word, $encoding);
		}
	}

	return !empty($tab) ? implode(' ', $tab) : '';
}

/**
 * Multi-Byte Safe URL Parser (Fully Compatible with parse_url())
 *
 * This function is a polyfill for PHP's parse_url() that correctly handles multi-bytes (non-ASCII) characters
 * in URLs. It preserves the behavior of parse_url() while ensuring proper encoding and decoding of Unicode characters.
 *
 * @param string|null $url The URL to parse.
 * @param int $component (Optional) One of the PHP_URL_* constants to retrieve a specific part of the URL.
 * @return false|int|array|string|null
 */
function mb_parse_url(?string $url, int $component = -1): false|int|array|string|null
{
	if (empty($url)) return false;

	// Encode multi-bytes characters safely
	$encodedUrl = preg_replace_callback('/[^:\/@?&=#]+/u', fn ($matches) => urlencode($matches[0]), $url);

	// If the first encoding failed, use native PHP functions to convert the URL to UTF-8 encoding
	if (empty($encodedUrl)) {
		$encodedUrl = mb_convert_encoding($url, 'UTF-8', mb_detect_encoding($url));
		if (empty($encodedUrl)) {
			return null;
		}
	}

	// Use native parse_url on the encoded URL
	$parsed = parse_url($encodedUrl, $component);
	if ($parsed === false) {
		return false; // Invalid URL
	}

	// Decode all parts back to their original multi-bytes form
	if ($component !== -1) {
		// Handle specific component extraction
		if (is_string($parsed)) {
			$parsed = urldecode($parsed);
		}
	} else {
		// No specific component extraction
		if (is_array($parsed) && count($parsed) > 0) {
			foreach ($parsed as $key => $value) {
				$parsed[$key] = urldecode($value);
			}
		}
	}

	return $parsed;
}

/**
 * Friendly UTF-8 URL for all languages
 *
 * @param string|null $string
 * @param string $separator
 * @return string|null
 */
function slugify(?string $string, string $separator = '-'): ?string
{
	// Remove accents using the WordPress API method.
	$string = remove_accents($string);

	// Slug
	$string = mb_strtolower($string);
	$string = @trim($string);
	$replace = "/(\\s|\\" . $separator . ")+/mu";
	$subst = $separator;
	$string = preg_replace($replace, $subst, $string);

	// Remove unwanted punctuation, convert some to '-'
	$punctuationTable = [
		// remove
		"'"  => '',
		'"'  => '',
		'`'  => '',
		'='  => '',
		'+'  => '',
		'*'  => '',
		'&'  => '',
		'^'  => '',
		''   => '',
		'%'  => '',
		'$'  => '',
		'#'  => '',
		'@'  => '',
		'!'  => '',
		'<'  => '',
		'>'  => '',
		'?'  => '',
		// convert to minus
		'['  => '-',
		']'  => '-',
		'{'  => '-',
		'}'  => '-',
		'('  => '-',
		')'  => '-',
		' '  => '-',
		','  => '-',
		';'  => '-',
		':'  => '-',
		'/'  => '-',
		'|'  => '-',
		'\\' => '-',
	];
	$string = str_replace(array_keys($punctuationTable), array_values($punctuationTable), $string);

	// Clean up multiple '-' characters
	$string = preg_replace('/-{2,}/', '-', $string);

	// Remove trailing '-' character if string not just '-'
	if ($string != '-') {
		$string = rtrim($string, '-');
	}

	if ($separator != '-') {
		$string = str_replace('-', $separator, $string);
	}

	return castToStringOrNull($string);
}

/**
 * Get file/folder permissions
 *
 * @param string $path
 * @return int
 */
function getPerms(string $path): int
{
	$permissions = fileperms($path);
	$readablePermissions = substr(sprintf('%o', $permissions), -4);

	return intval($readablePermissions);
}

/**
 * Get number plural (0 and 1 for singular, >=2 for plural)
 * Required for Russian pluralization
 *
 * @param $number
 * @param bool|null $isRussianLangPluralization
 * @return float|int
 */
function numberPlural($number, ?bool $isRussianLangPluralization = false): float|int
{
	if (!is_numeric($number)) {
		$number = (int)$number;
	}

	if ($isRussianLangPluralization === true) {
		// Russian pluralization rules
		$typeOfPlural = (($number % 10 == 1) && ($number % 100 != 11))
			? 0
			: ((($number % 10 >= 2)
				&& ($number % 10 <= 4)
				&& (($number % 100 < 10)
					|| ($number % 100 >= 20)))
				? 1
				: 2
			);
	} else {
		// No rule for other languages
		$typeOfPlural = $number;
	}

	return $typeOfPlural;
}

/**
 * Sanitize setting an array to contain only allowed data types
 *
 * Filters an array to ensure all values are strings, numbers, boolean, null, or arrays (i.e. JSON).
 * Invalid values are converted to null. Returns null if input is not an array.
 *
 * @param $inputArray
 * @param bool $recursive
 * @return array|null
 */
function sanitizeSettingArray($inputArray, bool $recursive = true): ?array
{
	if (!is_array($inputArray)) {
		return null;
	}

	if (!empty($inputArray)) {
		$sanitizedArray = [];
		foreach ($inputArray as $key => $value) {
			if (is_array($value) && $recursive) {
				// Recursively sanitize nested arrays
				$sanitizedArray[$key] = sanitizeSettingArray($value, $recursive);
			} else {
				$isAllowedType = (
					is_string($value)
					|| is_numeric($value)
					|| is_bool($value)
					|| is_null($value)
					|| is_array($value)
				);

				$sanitizedArray[$key] = $isAllowedType ? $value : null;
			}
		}
		$inputArray = $sanitizedArray;
	}

	return $inputArray;
}

/**
 * Check if the PHP exec() function is available
 *
 * @return boolean
 */
function isExecFunctionEnabled(): bool
{
	try {
		// Make a small test
		exec('ls');

		return (isFunctionEnabled('exec') && function_exists('exec'));
	} catch (Throwable $e) {
		return false;
	}
}

/**
 * Check if a PHP function is enabled
 *
 * @param string $name
 * @return bool
 */
function isFunctionEnabled(string $name): bool
{
	try {
		$disabled = array_map('trim', explode(',', ini_get('disable_functions')));

		return !in_array($name, $disabled);
	} catch (Throwable $e) {
		return false;
	}
}

/**
 * Check if the PHP Exif component is enabled
 *
 * @return bool
 */
function isExifExtensionEnabled(): bool
{
	try {
		if (extension_loaded('exif') && function_exists('exif_read_data')) {
			return true;
		}

		return false;
	} catch (Throwable $e) {
		return false;
	}
}

/**
 * Build HTML attributes with a PHP array
 *
 * @param array|null $attributes
 * @return string
 */
function buildAttributes(?array $attributes): string
{
	if (empty($attributes)) {
		return '';
	}

	$attributePairs = [];
	foreach ($attributes as $key => $val) {
		if (is_int($key)) {
			$attributePairs[] = $val;
		} else {
			$val = htmlspecialchars($val, ENT_QUOTES);
			$attributePairs[] = "{$key}=\"{$val}\"";
		}
	}

	$out = trim(implode(' ', $attributePairs));

	if (!empty($out)) {
		$out = ' ' . $out;
	}

	return $out;
}

/**
 * Remove all unmatched variables patterns (e.g. {foo}) from a string
 *
 * @param string|null $string
 * @return string
 */
function removeUnmatchedPatterns(?string $string): string
{
	$string = strval($string);
	$string = preg_replace('|\{[^}]+}|ui', '', $string);
	$string = preg_replace('|,(\s*,)+|ui', ',', $string);
	$string = preg_replace('|\s\s+|ui', ' ', $string);

	return trim($string, " \n\r\t\v\0,-");
}

/**
 * Check if an array contains only empty items/elements (recursively)
 *
 * @param array|null $array
 * @return bool
 */
function isArrayOfEmptyElements(?array $array): bool
{
	// Check if $array is empty
	if (empty($array)) {
		return true;
	}

	// Iterate through each element in the array
	foreach ($array as $element) {
		// If the element is an array, recursively check it
		if (is_array($element)) {
			if (!isArrayOfEmptyElements($element)) {
				return false;
			}
		} else {
			// If the element is not empty, return false
			if (!empty($element)) {
				return false;
			}
		}
	}

	// If all elements are empty or arrays with only empty elements, return true
	return true;
}

/**
 * Redirect (Prevent Browser Cache)
 *
 * @param string $url
 * @param int $status (301 => Moved Permanently | 302 => Moved Temporarily)
 * @param array $headers
 */
function redirectUrl(string $url, int $status = 301, array $headers = [])
{
	// Headers have been sent
	// Any more header lines can not be added using the header() function once the header block has already been sent.
	if (headers_sent()) {
		redirectUrlWithHtml($url);
		exit();
	}

	// Apply headers (by adding new header lines)
	if (is_array($headers) && !empty($headers)) {
		foreach ($headers as $key => $value) {
			if (str_contains($value, 'post-check') || str_contains($value, 'pre-check')) {
				header($key . ": " . $value, false);
			} else {
				header($key . ": " . $value);
			}
		}
	}

	// Redirect
	header("Location: " . $url, true, $status);
	exit();
}

/**
 * Redirect URL (with GET method) in HTML
 * Note: Don't prevent browser cache
 *
 * @param string $url
 * @return void
 */
function redirectUrlWithHtml(string $url)
{
	$out = '<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Redirection...</title>
        <script type="text/javascript">
            window.location.href = "' . $url . '"
        </script>
        <noscript>
        	<meta http-equiv="refresh" content="0; url=' . $url . '">
        </noscript>
    </head>
    <body>
        If you are not redirected automatically, follow this <a href="' . $url . '">link</a>.
    </body>
</html>';

	echo $out;
	exit();
}

/**
 * Split a name into the first name and last name
 *
 * @param string|null $input
 * @return array
 */
function splitName(?string $input): array
{
	$output = [];

	$space = mb_strpos($input, ' ');
	if ($space !== false) {
		$output['firstName'] = mb_substr($input, 0, $space);
		$output['lastName'] = mb_substr($input, $space, strlen($input));
	} else {
		$output['firstName'] = '';
		$output['lastName'] = $input;
	}

	return $output;
}

/**
 * Keep only numeric characters
 *
 * @param string|null $value
 * @param int|null $default
 * @return string
 */
function keepOnlyNumericChars(?string $value, ?int $default = null): string
{
	// Use regular expression to keep only numeric characters
	$value = preg_replace('/[^0-9]/', '', strval($value));
	$value = trim(castToString($value));
	if (empty($value)) {
		$value = strval($default);
	}

	return $value;
}

/**
 * @param float|int|string|null $value
 * @param int $default
 * @return int
 */
function forceToInt(float|int|string|null $value, int $default = 0): int
{
	return (int)keepOnlyNumericChars(strval($value), $default);
}

/**
 * PHP round() function that always return a float value in any language
 *
 * @param float|int $val
 * @param int $precision
 * @param int $mode
 * @return string
 */
function roundVal($val, int $precision = 0, int $mode = PHP_ROUND_HALF_UP): string
{
	return number_format((float)round($val, $precision, $mode), $precision, '.', '');
}

/**
 * Print JavaScript code in HTML
 *
 * @param string|null $code
 * @return string
 */
function printJs(?string $code): string
{
	if (empty($code)) return '';

	// Define patterns for external and inline JS
	$externalJsPattern = '/<script([a-z0-9\-_ ]+)src=([^>]+)>(.*?)<\/script>/ius';
	$inlineJsPattern = '/<script([^>]*)>(.*?)<\/script>/ius';

	// Check for external JS and replace with proper tags
	$code = preg_replace($externalJsPattern, '<script$1src=$2>$3</script>', $code);

	// Check for inline JS, wrap any unwrapped code with <script> tags
	$code = preg_replace_callback($inlineJsPattern, function ($matches) {
		return '<script' . $matches[1] . '>' . $matches[2] . '</script>';
	}, $code);

	// Wrap any remaining unwrapped JS code with <script> tags
	if (!preg_match($inlineJsPattern, $code)) {
		$code = '<script type="text/javascript">' . "\n" . $code . "\n" . '</script>';
	}

	return castToString($code);
}

/**
 * Print CSS codes in HTML
 *
 * @param string|null $code
 * @return string
 */
function printCss(?string $code): string
{
	if (empty($code)) return '';

	// Remove HTML tags from the input to avoid injection attacks
	$sanitizedCode = strip_tags($code);

	// Return the CSS wrapped in style tags
	return '<style>' . "\n" . $sanitizedCode . "\n" . '</style>';
}

/**
 * Count the total number of lines of a given file without loading the entire file.
 * This is effective for a large file
 *
 * @param string $path
 * @return int
 */
function lineCount(string $path): int
{
	$file = new SplFileObject($path, 'r');
	$file->seek(PHP_INT_MAX);

	return $file->key() + 1;
}

/**
 * Escape string for JS
 * Escape characters with slashes like in C and white spaces like \r\n, \r, \n, etc.
 *
 * @param string|null $string $string
 * @param string $charsToEscape
 * @param array $additionalEscapes
 * @return string
 */
function escapeStringForJs(?string $string, string $charsToEscape = '"', array $additionalEscapes = []): string
{
	// Use addcslashes to escape the specified characters
	$string = addcslashes(strval($string), $charsToEscape);

	// Replace newline characters with \n
	// $string = str_replace(["\r\n", "\r", "\n"], '\\n', $string);
	$string = preg_replace('/\s+/ui', ' ', $string);

	// Escape additional characters
	if (!empty($additionalEscapes)) {
		foreach ($additionalEscapes as $char => $escapeWith) {
			$string = str_replace($char, $escapeWith, $string);
		}
	}

	return castToString($string);
}

/**
 * Add http:// if it doesn't exist in the URL
 * Recognizes ftp://, ftps://, http:// and https:// in a case-insensitive way.
 *
 * @param string|null $url
 * @return string|null
 */
function addHttp(?string $url): ?string
{
	if (!empty($url)) {
		if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
			$url = 'http' . '://' . $url;
		}
	}

	return $url;
}

/**
 * Determine if php is running at the command line
 *
 * @return bool
 */
function isCli(): bool
{
	if (defined('STDIN')) {
		return true;
	}

	if (php_sapi_name() === 'cli') {
		return true;
	}

	if (array_key_exists('SHELL', $_ENV)) {
		return true;
	}

	if (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['argv'])) {
		return true;
	}

	if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
		return true;
	}

	return false;
}

/**
 * Convert UTF-8 HTML to ANSI
 *
 * https://stackoverflow.com/a/7061511
 * https://onlinehelp.coveo.com/en/ces/7.0/administrator/what_is_the_difference_between_ansi_and_utf-8_uri_formats.htm
 * https://stackoverflow.com/questions/701882/what-is-ansi-format
 *
 * @param string|null $string
 * @return string|null
 */
function convertUTF8HtmlToAnsi(?string $string): ?string
{
	/*
	 * 1. Escaped Unicode characters to HTML hex references. E.g. \u00e9 => &#x00e9;
	 * 2. Convert HTML entities to their corresponding characters. E.g. &#x00e9; => é
	 */
	$string = preg_replace('/\\\\u([a-fA-F0-9]{4})/ui', '&#x\\1;', strval($string));

	return html_entity_decode($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
}

/**
 * @param string|null $string
 * @return string|null
 */
function nlToBr(?string $string): ?string
{
	// Replace multiple (one or more) line breaks with a single one.
	$string = preg_replace("/[\r\n]+/", "\n", strval($string));

	return nl2br(castToString($string));
}

/**
 * Convert array values to JSON strings for database insertion
 *
 * @param array|null $entry
 * @param bool $unescapedUnicode
 * @return array|null
 */
function prepareArraysForDatabase(?array $entry, bool $unescapedUnicode = true): ?array
{
	if (empty($entry)) {
		return $entry;
	}

	$preparedEntry = [];
	$jsonFlags = $unescapedUnicode ? JSON_UNESCAPED_UNICODE : 0;

	foreach ($entry as $key => $value) {
		if (is_array($value)) {
			$preparedEntry[$key] = json_encode($value, $jsonFlags);
		} else {
			$preparedEntry[$key] = $value;
		}
	}

	return $preparedEntry;
}

/**
 * @param int|null $decimalPlaces
 * @return string
 */
function getInputNumberStep(?int $decimalPlaces = null): string
{
	if (empty($decimalPlaces) || $decimalPlaces <= 0) {
		$decimalPlaces = 2;
	}

	return '0.' . (str_pad('1', $decimalPlaces, '0', STR_PAD_LEFT));
}

/**
 * Create Random String
 *
 * @param int $length
 * @return string
 */
function createRandomString(int $length = 6): string
{
	$str = '';
	$chars = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'));
	$max = count($chars) - 1;
	for ($i = 0; $i < $length; $i++) {
		$rand = mt_rand(0, $max);
		$str .= $chars[$rand];
	}

	return $str;
}

/**
 * Increases or decreases the brightness of a color by a percentage of the current brightness.
 *
 * Supported formats: '#FFF', '#FFFFFF', 'FFF', 'FFFFFF'
 * A number between -1 and 1. E.g. 0.3 = 30% lighter; -0.4 = 40% darker.
 *
 * @param string|null $hexCode
 * @param float $percent
 * @return string
 */
function colourBrightness(?string $hexCode, float $percent): string
{
	$hexCode = ltrim($hexCode, '#');

	if (strlen($hexCode) == 3) {
		$hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
	}

	$hexCode = array_map('hexdec', str_split($hexCode, 2));

	foreach ($hexCode as & $color) {
		$adjustableLimit = $percent < 0 ? $color : 255 - $color;
		$adjustAmount = ceil($adjustableLimit * $percent);

		$color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
	}

	return '#' . implode($hexCode);
}

/**
 * Luminosity Contrast algorithm
 * Given a background color, black or white text
 *
 * Will return '#FFFFFF'
 * echo getContrastColor('#FF0000');
 *
 * @param string|null $hexColor
 * @return string
 */
function getContrastColor(?string $hexColor): string
{
	// hexColor RGB
	$r1 = hexdec(substr($hexColor, 1, 2));
	$g1 = hexdec(substr($hexColor, 3, 2));
	$b1 = hexdec(substr($hexColor, 5, 2));

	// Black RGB
	$blackColor = '#000000';
	$rToBlackColor = hexdec(substr($blackColor, 1, 2));
	$gToBlackColor = hexdec(substr($blackColor, 3, 2));
	$bToBlackColor = hexdec(substr($blackColor, 5, 2));

	// Calc contrast ratio
	$l1 = 0.2126 * pow($r1 / 255, 2.2)
		+ 0.7152 * pow($g1 / 255, 2.2)
		+ 0.0722 * pow($b1 / 255, 2.2);

	$l2 = 0.2126 * pow($rToBlackColor / 255, 2.2)
		+ 0.7152 * pow($gToBlackColor / 255, 2.2)
		+ 0.0722 * pow($bToBlackColor / 255, 2.2);

	$contrastRatio = 0;
	if ($l1 > $l2) {
		$contrastRatio = (int)(($l1 + 0.05) / ($l2 + 0.05));
	} else {
		$contrastRatio = (int)(($l2 + 0.05) / ($l1 + 0.05));
	}

	// If contrast is more than 5, return black color
	if ($contrastRatio > 5) {
		return '#000000';
	} else {
		// If not, return white color.
		return '#FFFFFF';
	}
}

/**
 * CSS Minify
 * Note: This works only for CSS code
 *
 * @param string|null $code
 * @return string
 */
function cssMinify(?string $code): string
{
	// Make it into one long line
	$code = str_replace(["\n", "\r"], '', $code);

	// Replace all multiple spaces by one space
	$code = preg_replace('!\s+!', ' ', $code);

	// Replace some unneeded spaces, modify as needed
	$code = str_replace([' {', ' }', '{ ', '; '], ['{', '}', '{', ';'], $code);

	// Remove comments
	$code = str_replace('/*', '_COMMENT_START', $code);
	$code = str_replace('*/', 'COMMENT_END_', $code);
	$code = preg_replace('/_COMMENT_START.*?COMMENT_END_/s', '', $code);

	return trim($code);
}

/**
 * Get file list including those in subdirectories using glob()
 * Note: Does not support flag GLOB_BRACE
 *
 * @param string $pattern
 * @param int $flags
 * @return bool|array
 */
function recursiveGlob(string $pattern, int $flags = 0): bool|array
{
	$files = glob($pattern, $flags);
	foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
		$files = array_merge($files, recursiveGlob($dir . '/' . basename($pattern), $flags));
	}

	return $files;
}

/**
 * Recursively delete a directory
 * The directory itself may be optionally preserved
 *
 * @param string $dir
 * @param bool $preserve
 * @return bool
 */
function removeDirectory(string $dir, bool $preserve = false): bool
{
	if (!is_dir($dir)) {
		return false;
	}

	$objects = scandir($dir);
	foreach ($objects as $object) {
		if ($object != '.' && $object != '..') {
			if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . '/' . $object)) {
				removeDirectory($dir . DIRECTORY_SEPARATOR . $object);
			} else {
				unlink($dir . DIRECTORY_SEPARATOR . $object);
			}
		}
	}

	if (!$preserve) {
		rmdir($dir);
	}

	return true;
}

/**
 * Check if a directory exists and is not a symlink
 * Note: The is_dir() function returns true for symlink
 *
 * @param string $directory
 * @return bool
 */
function isRealDirectory(string $directory): bool
{
	return is_dir($directory) && !is_link($directory);
}

/**
 * Zip a directory and its contents
 *
 * @param $sourceDir
 * @param $zipFile
 * @return bool
 */
function zipDirectory($sourceDir, $zipFile): bool
{
	if (!(extension_loaded('zip') && class_exists('\ZipArchive'))) {
		return false;
	}

	if (!file_exists($sourceDir)) {
		return false;
	}

	try {
		// Check if the destination directory exists, if not, create it
		// Get the zip file directory
		$destinationDir = dirname($zipFile);
		if (!is_dir($destinationDir)) {
			mkdir($destinationDir, 0777, true);
		}

		// Zip the file
		$zip = new ZipArchive();

		if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			return false;
		}

		$sourceDir = realpath($sourceDir);

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($files as $file) {
			$file = realpath($file);

			if (is_dir($file)) {
				$zip->addEmptyDir(str_replace($sourceDir . '/', '', $file . '/'));
			} else if (is_file($file)) {
				$zip->addFile($file, str_replace($sourceDir . '/', '', $file));
			}
		}

		$zip->close();

		return file_exists($zipFile);
	} catch (Throwable $e) {
	}

	return false;
}

/**
 * Extract a zip file
 *
 * @param $zipFile
 * @param $extractTo
 * @return bool
 */
function extractZip($zipFile, $extractTo): bool
{
	if (!(extension_loaded('zip') && class_exists('\ZipArchive'))) {
		return false;
	}

	if (!file_exists($zipFile)) {
		return false;
	}

	try {
		$zip = new ZipArchive();
		$zip->open($zipFile);
		$zip->extractTo($extractTo);
		$zip->close();

		return true;
	} catch (Throwable $e) {
	}

	return false;
}

/**
 * Escape <code></code> tag content
 *
 * @param $html
 * @return string|null
 */
function escapeCodeTagContent($html): ?string
{
	if (!is_string($html)) return null;

	preg_match_all('/<code>(.+?)<\/code>/u', $html, $matches);
	$array = $matches[1] ?? [];
	if (!empty($array)) {
		foreach ($array as $codeStr) {
			$codeStrEnc = $codeStr;

			$codeStrEnc = preg_replace('/<([^>]*)>/u', '&lt;$1&gt;', $codeStrEnc);
			$codeStrEnc = str_replace('&amp;', '&', $codeStrEnc);

			$search = '<code>' . $codeStr . '</code>';
			$replace = '<code>' . $codeStrEnc . '</code>';
			$html = str_replace($search, $replace, $html);
		}
	}

	return is_string($html) ? $html : null;
}

/**
 * Generate a number range array
 *
 * @param int $min
 * @param int $max
 * @param int $interval
 * @param bool $includeBounds
 * @param int|null $requiredValue
 * @return array
 * @author: edwardayen
 *
 */
function generateNumberRange(int $min, int $max, int $interval, bool $includeBounds = false, ?int $requiredValue = null): array
{
	if ($interval <= 0) {
		throw new InvalidArgumentException("Interval must be a positive number");
	}

	$range = [];
	for ($i = $min; $i <= $max; $i += $interval) {
		$range[] = $i;
	}
	if ($includeBounds && !in_array($min, $range)) {
		array_unshift($range, $min);
	}
	if ($includeBounds && !in_array($max, $range)) {
		$range[] = $max;
	}
	if (!is_null($requiredValue) && !in_array($requiredValue, $range)) {
		$range[] = $requiredValue;
	}

	$range = array_unique($range);
	sort($range);

	return $range;
}

/**
 * Check if an object is empty
 *
 * @param $obj
 * @return bool
 * @author: edwardayen
 *
 * Note: Provide a TypeError exception when a non object is given
 */
function isObjectEmpty($obj): bool
{
	return empty(get_object_vars($obj));
}

/**
 * Reduce a given consecutive character in a given string
 * i.e. Keep only N consecutive amount of a character in a given string
 *
 * Note: By filling the '$replacement' argument,
 * this value will be kept instead of found '$char'
 *
 * @param string $input
 * @param string $char
 * @param int $numToKeep
 * @param string|null $replacement
 * @return string
 */
function reduceConsecutiveChar(string $input, string $char, int $numToKeep, ?string $replacement = null): string
{
	if (empty($input) || empty($char)) {
		return $input;
	}

	$exceeding = $numToKeep + 1;
	if ($numToKeep > 0) {
		$replacement = !empty($replacement) ? $replacement : $char;
		$replacement = ($numToKeep > 1) ? str_repeat($replacement, $numToKeep) : $replacement;
	} else {
		$replacement = '';
	}

	// Replace '$exceeding' or more '$char' with '$numToKeep' '$char'
	$escapedChar = preg_quote($char, '/');
	$pattern = '/' . $escapedChar . '{' . $exceeding . ',}/u';
	$output = preg_replace($pattern, $replacement, $input);

	return castToString($output);
}

/**
 * Generate String Acronyms
 *
 * Usage:
 * generateStringAcronym('Steve Jobs'); // SJ
 * generateStringAcronym('Steven Paul Jobs'); // SPJ
 * generateStringAcronym('Steve Jobs', '.'); // S.J.
 * generateStringAcronym('Steven P. Jobs', '.'); // S.P.J.
 *
 * @param string $string
 * @param string $delimiter
 * @return string
 */
function generateStringAcronym(string $string, string $delimiter = ''): string
{
	if (empty($string)) {
		return '';
	}

	$acronym = '';
	foreach (preg_split('/[^\p{L}]+/u', $string) as $word) {
		if (!empty($word)) {
			$firstLetter = mb_substr($word, 0, 1);
			$acronym .= $firstLetter . $delimiter;
		}
	}

	return $acronym;
}

/**
 * Check if an email is valid
 * Or if a string is a valid email address
 *
 * @param $email
 * @return bool
 */
function isValidEmail($email): bool
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Find all email addresses containing in a string
 *
 * @param $string
 * @return array
 */
function findEmailAddresses($string): array
{
	$pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
	preg_match_all($pattern, $string, $matches);

	return $matches[0] ?? [];
}

/**
 * @param \Throwable $e
 * @return string
 */
function getExceptionMessage(Throwable $e): string
{
	$message = $e->getMessage();

	if (config('app.debug')) {
		if (!empty($message)) {
			$message = 'Error: ' . $message . ' in "' . $e->getFile() . '" on line ' . $e->getLine();
		}
	}

	return $message;
}

/**
 * Check if a string is a valid HEX color code
 * Check for 3 or 6 characters HEX color with optional #
 *
 * @param string|null $color
 * @return bool
 */
function isHexColor(?string $color): bool
{
	if (empty($color)) return false;

	return (bool)preg_match('/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $color);
}

/**
 * Retrieves or removes a class namespace based on depth and direction.
 *
 * Usage
 * -----
 * Get top-level namespace:
 * echo getClassNamespaceName(MyApp\Controllers\Admin\UserController::class, 1); // 'MyApp'
 * Get last two namespace parts:
 * echo getClassNamespaceName(Project\Modules\Auth\Services\LoginService::class, 2, 'right'); // 'Services\LoginService'
 * Remove top-level namespace:
 * echo getClassNamespaceName(Project\Modules\Auth\Services\LoginService::class, 1, 'left', true); // 'Modules\Auth\Services\LoginService'
 * Remove last namespace part:
 * echo getClassNamespaceName(Project\Modules\Auth\Services\LoginService::class, 1, 'right', true); // 'Project\Modules\Auth\Services'
 *
 * @param string|object $class The class name or instance.
 * @param int $depth The number of namespace levels to keep or remove (0 for full namespace).
 * @param string $direction 'left' to keep/remove from start, 'right' to keep/remove from end.
 * @param bool $remove Whether to remove the specified depth instead of keeping it.
 * @return string The resulting namespace, or empty string if invalid.
 */
function getClassNamespaceName($class, int $depth = 0, string $direction = 'right', bool $remove = true): string
{
	// Handle object instances and string class names
	if (is_object($class)) {
		$class = get_class($class);
	}

	// Validate class existence and string type
	if (!is_string($class) || !class_exists($class)) {
		return '';
	}

	// Get the namespace using ReflectionClass
	$namespace = (new \ReflectionClass($class))->getNamespaceName();

	// If depth is 0 or namespace is empty, return full namespace (unless removing)
	if (empty($namespace)) {
		return '';
	}
	if ($depth === 0 && !$remove) {
		return $namespace;
	}

	// Split namespace into parts
	$parts = explode('\\', trim($namespace, '\\'));
	$totalParts = count($parts);

	// Handle negative or excessive depth
	if ($depth < 0 || ($depth > $totalParts && !$remove)) {
		return '';
	}

	// Adjust depth for removal if it exceeds total parts
	if ($remove && $depth > $totalParts) {
		$depth = $totalParts;
	}

	// Process based on direction and remove flag
	if ($direction === 'right') {
		if ($remove) {
			// Remove $depth parts from the right
			return implode('\\', array_slice($parts, 0, $totalParts - $depth));
		}

		// Keep $depth parts from the right
		return implode('\\', array_slice($parts, -$depth));
	}

	// Default: left direction
	if ($remove) {
		// Remove $depth parts from the left
		return implode('\\', array_slice($parts, $depth));
	}

	// Keep $depth parts from the left
	return implode('\\', array_slice($parts, 0, $depth));
}

/**
 * Normalizes numeric values by converting numeric strings to appropriate types.
 *
 * Converts numeric strings to int or float based on their content while preserving
 * existing numeric types. Non-numeric values are returned unchanged.
 *
 * Examples:
 * "123" → 123 (int)
 * "123.45" → 123.45 (float)
 * "1.23e4" → 12300.0 (float)
 * 42 → 42 (int, unchanged)
 * "hello" → "hello" (unchanged)
 *
 * @param mixed $value The value to check and convert
 * @return int|float|mixed Returns int, float, or original value
 */
function normalizeNumeric(mixed $value): mixed
{
	// If already numeric type, return as is
	if (is_int($value) || is_float($value)) {
		return $value;
	}

	// If not a string, or an empty string, return the original value.
	if (!is_string($value) || $value === '') {
		return $value;
	}

	$value = trim($value);

	// Check for an integer match first, as it's the more precise type.
	// Check if it's an integer (no decimal point and no scientific notation)
	$intResult = filter_var($value, FILTER_VALIDATE_INT);
	if ($intResult !== false) {
		return $intResult;
	}

	// Check for a float match next.
	$floatResult = filter_var($value, FILTER_VALIDATE_FLOAT);
	if ($floatResult !== false) {
		return $floatResult;
	}

	return $value;
}

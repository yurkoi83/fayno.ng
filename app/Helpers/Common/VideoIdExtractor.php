<?php

namespace App\Helpers\Common;

class VideoIdExtractor
{
	/**
	 * Extract Video ID from video platforms
	 *
	 * Supported platforms:
	 * youtube, vimeo, tiktok, facebook, instagram, twitter
	 *
	 * @param string|null $url
	 * @return array|null
	 */
	public static function extractId(?string $url): ?array
	{
		// Try to extract from YouTube
		if ($id = self::extractYoutubeId($url)) {
			return ['platform' => 'youtube', 'videoId' => $id];
		}

		// Try to extract from Vimeo
		if ($id = self::extractVimeoId($url)) {
			return ['platform' => 'vimeo', 'videoId' => $id];
		}

		// Try to extract from TikTok
		if ($id = self::extractTiktokId($url)) {
			return ['platform' => 'tiktok', 'videoId' => $id];
		}

		// Try to extract from Facebook
		if ($id = self::extractFacebookId($url)) {
			return ['platform' => 'facebook', 'videoId' => $id];
		}

		// Try to extract from Instagram
		if ($id = self::extractInstagramId($url)) {
			return ['platform' => 'instagram', 'videoId' => $id];
		}

		// Try to extract from X (Twitter)
		if ($id = self::extractTwitterId($url)) {
			return ['platform' => 'twitter', 'videoId' => $id];
		}

		// No valid ID found
		return null;
	}

	/**
	 * @param string|null $url
	 * @return string|null
	 */
	private static function extractYoutubeId(?string $url): ?string
	{
		if (empty($url)) return null;

		// Parse the URL and get the query parameters
		$parsedUrl = parse_url($url);

		// Check the platform host
		if (empty($parsedUrl['host'])) return null;
		if (!str_contains($parsedUrl['host'], 'youtu')) return null;

		// Try to extract the video ID

		// If the URL has a query string (e.g., ?v=abcd1234)
		if (!empty($parsedUrl['query'])) {
			parse_str($parsedUrl['query'], $queryParams);
			if (!empty($queryParams['v'])) {
				return castToStringOrNull($queryParams['v']);
			}
		}

		// If the URL is in a shortened form (e.g., youtu.be/abcd1234)
		if ($parsedUrl['host'] === 'youtu.be') {
			$videoId = ltrim($parsedUrl['path'], '/');
			if (!empty($videoId)) {
				return castToStringOrNull($videoId);
			}
		}

		// If the URL is in an embed form (e.g., youtube.com/embed/abcd1234)
		if (!empty($parsedUrl['path'])) {
			$pathParts = explode('/', trim($parsedUrl['path'], '/'));
			if (in_array('embed', $pathParts) || in_array('v', $pathParts)) {
				$videoId = end($pathParts);
				if (!empty($videoId)) {
					return castToStringOrNull($videoId);
				}
			}
		}

		// If the URL is in any other valid YouTube format
		if (str_contains($parsedUrl['host'], 'youtube.com')) {
			if (preg_match('/\/(v|embed|shorts)\/([a-zA-Z0-9_-]+)/', $parsedUrl['path'], $matches)) {
				$videoId = $matches[2] ?? null;

				return castToStringOrNull($videoId);
			}
		}

		return null;
	}

	/**
	 * @param string|null $url
	 * @return string|null
	 */
	private static function extractVimeoId(?string $url): ?string
	{
		if (empty($url)) return null;

		// Parse the URL and get the path
		$parsedUrl = parse_url($url);

		// Check the platform host
		if (empty($parsedUrl['host'])) return null;
		if (!str_contains($parsedUrl['host'], 'vimeo.com')) return null;

		// Try to extract the video ID
		// Check for video URL with vimeo.com (e.g., https://vimeo.com/12345678)
		// or player.vimeo.com (e.g., https://player.vimeo.com/video/12345678)
		if (!empty($parsedUrl['path'])) {
			$pathParts = explode('/', trim($parsedUrl['path'], '/'));

			// The last part (segment) of the path should be numeric for a valid ID
			$lastSegment = end($pathParts);
			if (is_numeric($lastSegment)) {
				return castToStringOrNull($lastSegment);
			}

			// For URLs like /video/12345678
			$countPathParts = count($pathParts);
			if ($countPathParts > 1 && $pathParts[$countPathParts - 2] == 'video') {
				$videoId = $pathParts[$countPathParts - 1] ?? null;

				return castToStringOrNull($videoId);
			}
		}

		return null;
	}

	/**
	 * @param string|null $url
	 * @return string|null
	 */
	private static function extractTiktokId(?string $url): ?string
	{
		if (empty($url)) return null;

		$parsedUrl = parse_url($url);

		// Check the platform host
		if (empty($parsedUrl['host'])) return null;
		if (!str_contains($parsedUrl['host'], 'tiktok.com')) return null;

		// Try to extract the video ID
		if (!empty($parsedUrl['path'])) {
			$pathParts = explode('/', trim($parsedUrl['path'], '/'));
			if (in_array('video', $pathParts)) {
				return castToStringOrNull(end($pathParts));
			}
		}

		return null;
	}

	/**
	 * @param string|null $url
	 * @return string|null
	 */
	private static function extractFacebookId(?string $url): ?string
	{
		if (empty($url)) return null;

		$parsedUrl = parse_url($url);

		// Check the platform host
		if (empty($parsedUrl['host'])) return null;
		if (!str_contains($parsedUrl['host'], 'facebook.com')) return null;

		// Try to extract the video ID
		if (!empty($parsedUrl['path'])) {
			$pathParts = explode('/', trim($parsedUrl['path'], '/'));

			// https://web.facebook.com/watch/?v=1234567890
			if (in_array('watch', $pathParts)) {
				if (!empty($parsedUrl['query'])) {
					parse_str($parsedUrl['query'], $queryParams);
					if (!empty($queryParams['v'])) {
						return castToStringOrNull($queryParams['v']);
					}
				}
			}

			// https://www.facebook.com/username/videos/title-of-the-video/1234567890/
			if (in_array('videos', $pathParts)) {
				return castToStringOrNull(end($pathParts));
			}
		}

		return null;
	}

	/**
	 * @param string|null $url
	 * @return string|null
	 */
	private static function extractInstagramId(?string $url): ?string
	{
		if (empty($url)) return null;

		$parsedUrl = parse_url($url);

		// Check the platform host
		if (empty($parsedUrl['host'])) return null;
		if (!str_contains($parsedUrl['host'], 'instagram.com')) return null;

		// Try to extract the video ID
		if (!empty($parsedUrl['path'])) {
			$pathParts = explode('/', trim($parsedUrl['path'], '/'));

			$countPathParts = count($pathParts);
			if (in_array('p', $pathParts) || in_array('reel', $pathParts)) {
				$videoId = $pathParts[$countPathParts - 1] ?? null;

				return castToStringOrNull($videoId);
			}
		}

		return null;
	}

	/**
	 * @param string|null $url
	 * @return string|null
	 */
	private static function extractTwitterId(?string $url): ?string
	{
		if (empty($url)) return null;

		$parsedUrl = parse_url($url);

		// Check the platform host
		if (empty($parsedUrl['host'])) return null;
		if (!str_contains($parsedUrl['host'], 'twitter.com') && !str_contains($parsedUrl['host'], 'x.com')) return null;

		// Try to extract the video ID
		if (!empty($parsedUrl['path'])) {
			$pathParts = explode('/', trim($parsedUrl['path'], '/'));
			if (in_array('status', $pathParts)) {
				return castToStringOrNull(end($pathParts));
			}
		}

		return null;
	}
}

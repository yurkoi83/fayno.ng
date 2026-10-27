<?php

namespace App\Helpers\Common;

use Illuminate\Support\Number;
use ReflectionClass;
use ReflectionMethod;

class VideoEmbedder
{
	/**
	 * @param $url
	 * @param int $width
	 * @param int $height
	 * @return string|null
	 */
	public static function getEmbedCode($url, int $width = 640, int $height = 360): ?string
	{
		$extracted = VideoIdExtractor::extractId($url);
		if (empty($extracted)) {
			return null;
		}

		$method = 'get' . ucfirst($extracted['platform']) . 'EmbedCode';
		if (!method_exists(__CLASS__, $method)) {
			return null;
		}

		$width = Number::clamp($width, min: 560, max: 1920);
		$height = Number::clamp($height, min: 315, max: 1080);

		return self::$method($extracted['videoId'], $width, $height);
	}

	/**
	 * @param $videoId
	 * @param int $width
	 * @param int $height
	 * @return string|null
	 */
	private static function getYoutubeEmbedCode($videoId, int $width, int $height): ?string
	{
		$code = null;

		if (!empty($videoId)) {
			$videoUrl = 'https://www.youtube.com/embed/' . $videoId;

			$code = '<iframe width="' . $width . '" height="' . $height . '"
				src="' . $videoUrl . '"
				class="embed-responsive-item"
				frameborder="0"
				allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
				></iframe>';
		}

		return $code;
	}

	/**
	 * @param $videoId
	 * @param int $width
	 * @param int $height
	 * @return string|null
	 */
	private static function getVimeoEmbedCode($videoId, int $width, int $height): ?string
	{
		$code = null;

		if (!empty($videoId)) {
			$videoUrl = 'https://player.vimeo.com/video/' . $videoId;

			$code = '<iframe width="' . $width . '" height="' . $height . '"
				src="' . $videoUrl . '"
				class="embed-responsive-item"
				frameborder="0"
				allow="autoplay; fullscreen" allowfullscreen></iframe>';
		}

		return $code;
	}

	/**
	 * @param $videoId
	 * @param int $width
	 * @param int $height
	 * @return string|null
	 */
	private static function getTiktokEmbedCode($videoId, int $width, int $height): ?string
	{
		$code = null;

		if (!empty($videoId)) {
			$videoUrl = 'https://www.tiktok.com/@tiktok/video/' . $videoId;

			$code = '<blockquote class="tiktok-embed" cite="' . $videoUrl . '" data-video-id="' . $videoId . '"
				style="max-width: ' . $width . 'px;min-width: ' . $height . 'px;">
				<section></section></blockquote><script async src="https://www.tiktok.com/embed.js"></script>';
		}

		return $code;
	}

	public static function getFacebookEmbedCode($videoId, int $width, int $height): string
	{
		$code = null;

		if (!empty($videoId)) {
			$params = ['show_text' => 'false', 'width' => $width, 't' => 0];
			$videoUrl = 'https://web.facebook.com/watch/?v=' . $videoId;
			$videoUrl = urlBuilder($videoUrl)->setParameters($params)->toString();

			$code = '<iframe src="https://www.facebook.com/plugins/video.php?height=' . $height . '&href=' . $videoUrl . '"
				width="' . $width . '" height="' . $height . '" style="border:none;overflow:hidden"
				scrolling="no" frameborder="0" allowfullscreen="true"
				allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowFullScreen="true"
				></iframe>';
		}

		return $code;
	}

	public static function getInstagramEmbedCode($videoId, int $width, int $height): string
	{
		$code = null;

		if (!empty($videoId)) {
			$videoUrl = 'https://www.instagram.com/p/' . $videoId . '/';

			$code = '<blockquote class="instagram-media"
			data-instgrm-permalink="' . $videoUrl . '" data-instgrm-version="13"></blockquote>
			<script async src="//www.instagram.com/embed.js"></script>';
		}

		return $code;
	}

	/**
	 * @param $videoId
	 * @param int $width
	 * @param int $height
	 * @return string|null
	 */
	private static function getTwitterEmbedCode($videoId, int $width, int $height): ?string
	{
		$code = null;

		if (!empty($videoId)) {
			$videoUrl = 'https://twitter.com/user/status/' . $videoId;

			$code = '<blockquote class="twitter-tweet"><a href="' . $videoUrl . '"></a></blockquote>
				<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
		}

		return $code;
	}

	/**
	 * Get the video embedding platforms
	 *
	 * @return string
	 */
	public static function getPlatforms(): string
	{
		$platforms = collect((new ReflectionClass(__CLASS__))->getMethods())
			->map(function (ReflectionMethod $item) {
				$method = $item->getName();
				$from = 'get';
				$to = 'EmbedCode';

				if (!str_starts_with($method, $from) || !str_ends_with($method, $to)) {
					return null;
				}

				if (str_contains($method, 'Twitter')) {
					$method = str($method)->replace('Twitter', 'X/Twitter')->toString();
				}

				return str($method)
					->between($from, $to)
					->ucfirst()
					->toString();
			})
			->filter(fn ($item) => !empty($item))
			->implode(', ');

		if (empty($platforms)) return '';

		return str($platforms)->wrap('(', ')')->toString();
	}
}

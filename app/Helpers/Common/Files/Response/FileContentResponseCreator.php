<?php

namespace App\Helpers\Common\Files\Response;

use Illuminate\Filesystem\FilesystemAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FileContentResponseCreator
{
	/**
	 * @var ImageResponse
	 */
	private static ImageResponse $imageResponse;

	/**
	 * @var AudioVideoResponse
	 */
	private static AudioVideoResponse $audioVideoResponse;

	/**
	 * @param ImageResponse $imageResponse
	 * @param AudioVideoResponse $audioVideoResponse
	 */
	public function __construct(ImageResponse $imageResponse, AudioVideoResponse $audioVideoResponse)
	{
		self::$imageResponse = $imageResponse;
		self::$audioVideoResponse = $audioVideoResponse;
	}

	/**
	 * Return download or preview response for given file.
	 *
	 * @param $disk
	 * @param string|null $filePath
	 * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse|void
	 * @throws \League\Flysystem\FilesystemException
	 */
	public static function create($disk, ?string $filePath)
	{
		if (!$disk instanceof FilesystemAdapter) {
			abort(500);
		}

		if (empty($filePath) || !$disk->exists($filePath)) {
			abort(404);
		}

		$mime = $disk->mimeType($filePath);
		$type = self::getFileType($mime);

		if ($type === 'image') {
			return self::$imageResponse->create($disk, $filePath);
		} else if (self::shouldStream($mime, $type)) {
			return self::$audioVideoResponse->create($disk, $filePath);
		} else {
			return self::createBasicResponse($disk, $filePath);
		}
	}

	/**
	 * Create a basic response for specified upload content.
	 *
	 * @param $disk
	 * @param string|null $filePath
	 * @return \Symfony\Component\HttpFoundation\StreamedResponse
	 */
	private static function createBasicResponse($disk, ?string $filePath): StreamedResponse
	{
		if (!$disk instanceof FilesystemAdapter) {
			abort(404);
		}

		if (empty($filePath)) {
			abort(404);
		}

		$stream = $disk->readStream($filePath);
		$mime = $disk->mimeType($filePath);
		try {
			$size = $disk->fileSize($filePath);
		} catch (Throwable $e) {
			$size = 0;
		}
		$shortName = last(explode(DIRECTORY_SEPARATOR, $filePath));

		$headers = [
			"Content-Type"        => $mime,
			"Content-Length"      => $size,
			"Content-disposition" => "inline; filename=\"" . $shortName . "\"",
		];
		$callback = function () use ($stream) {
			fpassthru($stream);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Extract file type
	 *
	 * @param string $mime
	 * @return string
	 */
	private static function getFileType(string $mime): string
	{
		if (str($mime)->contains('video/')) {
			return 'video';
		} else if (str($mime)->contains('audio/')) {
			return 'audio';
		} else if (str($mime)->contains('image/')) {
			return 'image';
		} else {
			return 'file';
		}
	}

	/**
	 * Should file with given mime be streamed.
	 *
	 * @param string $mime
	 * @param string $type
	 *
	 * @return bool
	 */
	private static function shouldStream(string $mime, string $type): bool
	{
		return $type === 'video' || $type === 'audio' || $mime === 'application/ogg';
	}
}

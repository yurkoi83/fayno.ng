<?php

namespace App\Helpers\Common\Files\Response;

use Illuminate\Filesystem\FilesystemAdapter;

class ImageResponse
{
	/**
	 * Create response for previewing specified image.
	 * Optionally resize image to specified size.
	 *
	 * @param $disk
	 * @param string|null $filePath
	 * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
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
		$content = $disk->get($filePath);

		return response($content, 200)->header('Content-Type', $mime);
	}
}

<?php

namespace App\Helpers\Common\Files;

use App\Helpers\Common\Files\Storage\StorageDisk;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Throwable;

/**
 * Temporary upload helper for the listing create/edit flow's
 * custom-field files (PDFs, docs, etc.).
 *
 * Generic non-image temp-file helper. Image uploads go through
 * `App\Services\Image\ImageStore::store()` (the temp dir is just
 * another `$destDir` argument).
 */
class TmpUpload
{
	/**
	 * @param $file
	 * @param string $tmpUploadDir
	 * @param string|null $filename
	 * @return string|null
	 */
	public static function file($file, string $tmpUploadDir, ?string $filename = null): ?string
	{
		if (!$file instanceof SymfonyUploadedFile) {
			return null;
		}

		$disk = StorageDisk::getDisk();

		try {
			$origFilename = $file->getClientOriginalName();
			$origExtension = $file->getClientOriginalExtension();

			$filename = normalizeFilename($origFilename, $filename);
			$filename = $filename . '.' . $origExtension;

			$filePath = $tmpUploadDir . '/' . $filename;

			$disk->put($filePath, File::get($file->getrealpath()));

			return $filePath;
		} catch (Throwable $e) {
		}

		return null;
	}
}

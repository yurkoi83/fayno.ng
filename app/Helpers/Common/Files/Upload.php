<?php

namespace App\Helpers\Common\Files;

use App\Helpers\Common\Files\Storage\StorageDisk;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Throwable;

/**
 * Non-image upload helpers. Image uploads go through
 * `App\Services\Image\ImageStore::store()`.
 *
 *   - `file($file, $destPath, $diskName)` — generic file upload.
 *   - `fromPath($path)` — wrap a path on disk in an UploadedFile.
 *   - `fromBase64($string)` — wrap a `data:…` blob in a UploadedFile.
 */
class Upload
{
	/**
	 * @param $file
	 * @param string|null $destPath
	 * @param string|null $diskName
	 * @return string|null
	 */
	public static function file($file, ?string $destPath, ?string $diskName = null): ?string
	{
		if (empty($file)) {
			return null;
		}

		if (!$file instanceof SymfonyUploadedFile) {
			if (!is_string($file)) {
				return null;
			}

			if (str_contains($file, $destPath) && !str_starts_with($file, $destPath)) {
				$file = $destPath . last(explode($destPath, $file));
			}

			if (str_starts_with($file, url('storage'))) {
				$file = ltrim(str_replace(url('storage'), '', $file), '/');
			}

			return $file;
		}

		$disk = StorageDisk::getDisk($diskName);

		try {
			$origFilename = $file->getClientOriginalName();
			$origExtension = $file->getClientOriginalExtension();

			$filename = normalizeFilename($origFilename) . '.' . $origExtension;
			$filePath = $destPath . '/' . $filename;

			$disk->put($filePath, File::get($file->getrealpath()));

			return $filePath;
		} catch (Throwable $e) {
			return null;
		}
	}

	/**
	 * Create an UploadedFile object from base64 file content.
	 *
	 * @param string|null $string
	 * @param bool $test
	 * @return false|\Illuminate\Http\UploadedFile
	 */
	public static function fromBase64(?string $string, bool $test = true): bool|UploadedFile
	{
		$hasBase64String = (!empty($string) && str_starts_with($string, 'data:'));
		if (!$hasBase64String) {
			return false;
		}

		$origMimeType = FileSys::getBase64EncodedFileMimeType($string);
		$origExtension = FileSys::getMimeTypeExtension($origMimeType);

		$base64String = FileSys::extractBase64String($string);
		$decodedData = base64_decode($base64String);

		$tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
		file_put_contents($tmpFilePath, $decodedData);

		$tmpFile = new HttpFile($tmpFilePath);

		$path = $tmpFile->getPathname();
		$origFilename = $tmpFile->getFilename() . '.' . $origExtension;
		$mimeType = $tmpFile->getMimeType();
		$mimeType = !empty($mimeType) ? $mimeType : $origMimeType;
		$error = null;

		return new UploadedFile($path, $origFilename, $mimeType, $error, $test);
	}

	/**
	 * Create an UploadedFile object from a file's full path.
	 *
	 * @param string $path
	 * @param bool $test
	 * @return false|\Illuminate\Http\UploadedFile
	 */
	public static function fromPath(string $path, bool $test = true): bool|UploadedFile
	{
		if (empty($path) || !Storage::exists($path)) {
			return false;
		}

		$path = Storage::path($path);

		$filesystem = new Filesystem();
		$originalName = $filesystem->name($path) . '.' . $filesystem->extension($path);
		$mimeType = $filesystem->mimeType($path);
		$error = null;

		return new UploadedFile($path, $originalName, $mimeType, $error, $test);
	}
}

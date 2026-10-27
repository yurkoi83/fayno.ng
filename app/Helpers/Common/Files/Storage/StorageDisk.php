<?php

namespace App\Helpers\Common\Files\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class StorageDisk
{
	/**
	 * Get the default disk name
	 *
	 * @return string|null
	 */
	public static function getDiskName(): ?string
	{
		$defaultDisk = config('filesystems.default', 'public');

		// $defaultDisk = config('filesystems.cloud'); // Only for tests purpose!

		return castToStringOrNull($defaultDisk);
	}

	/**
	 * Get the default disk resources
	 *
	 * @param string|null $name
	 * @return \Illuminate\Contracts\Filesystem\Filesystem
	 */
	public static function getDisk(?string $name = null): Filesystem
	{
		$defaultDisk = !is_null($name) ? $name : self::getDiskName();

		return Storage::disk($defaultDisk);
	}
}

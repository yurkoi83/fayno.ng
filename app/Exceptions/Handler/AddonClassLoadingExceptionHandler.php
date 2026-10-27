<?php

namespace App\Exceptions\Handler;

use App\Exceptions\Handler\Addon\FixFolderName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Check if there is no addon class loading issue (inside composer class loader)
 */

trait AddonClassLoadingExceptionHandler
{
	use FixFolderName;
	
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isAddonClassLoadingException(Throwable $e): bool
	{
		// Check if there is no addon class loading issue (inside composer class loader)
		return (
			method_exists($e, 'getFile') && method_exists($e, 'getMessage')
			&& !empty($e->getFile()) && !empty($e->getMessage())
			&& str_contains($e->getFile(), '/vendor/composer/ClassLoader.php')
			&& str_contains($e->getMessage(), '/extensions/addons/')
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseAddonClassLoadingException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getAddonClassLoadingExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string|null
	 */
	private function getAddonClassLoadingExceptionMessage(Throwable $e, Request $request): ?string
	{
		$message = $e->getMessage();
		
		return !empty($message) ? $this->tryToFixAddonDirName($message) : null;
	}
}

<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

trait CachingExceptionHandler
{
	/**
	 * Check if it is caching exception (APC or Redis)
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isCachingException(Throwable $e): bool
	{
		return ($this->isAPCCachingException($e) || $this->isRedisCachingException($e));
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response|false|\Illuminate\Http\JsonResponse
	 */
	protected function responseCachingException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getCachingExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getCachingExceptionMessage(Throwable $e, Request $request): string
	{
		$message = $e->getMessage() . "\n";
		if ($this->isAPCCachingException($e)) {
			$message .= 'This looks like that the <a href="https://www.php.net/manual/en/book.apcu.php" target="_blank">APC extension</a> ';
			$message .= 'is not installed (or not properly installed) for PHP.' . "\n";
		}
		$message .= 'Make sure you have properly installed the components related to the selected cache driver on your server.' . "\n";
		$message .= 'To get your website up and running again you have to change the cache driver in the /.env file ';
		$message .= 'with the "file" or "array" driver (example: CACHE_STORE=file).' . "\n";
		
		return $message;
	}
	
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	private function isAPCCachingException(Throwable $e): bool
	{
		return (bool)preg_match('#apc_#ui', $e->getMessage());
	}
	
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	private function isRedisCachingException(Throwable $e): bool
	{
		return (bool)preg_match('#/predis/#i', $e->getFile());
	}
}

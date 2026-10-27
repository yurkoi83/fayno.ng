<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Post Too Large Exception
 */

trait Http413ExceptionHandler
{
	/**
	 * Check it is a 'Post Too Large' exception
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isHttp413Exception(Throwable $e): bool
	{
		return (
			$e instanceof PostTooLargeException
			|| (
				$this->isHttpException($e)
				&& method_exists($e, 'getStatusCode')
				&& $e->getStatusCode() == Response::HTTP_REQUEST_ENTITY_TOO_LARGE
			));
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseHttp413Exception(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getHttp413ExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getHttp413ExceptionMessage(Throwable $e, Request $request): string
	{
		$message = 'Maximum data (including files to upload) size to post and memory usage are limited on the server.';
		$message = 'Payload Too Large. ' . $message;
		
		if (!isApiRoute($request)) {
			$backLink = ' <a href="' . url()->previous() . '">' . trans('global.Back') . '</a>';
			$message = $message . $backLink;
		}
		
		return $message;
	}
}

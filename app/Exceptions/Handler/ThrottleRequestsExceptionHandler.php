<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\Exceptions\ThrottleRequestsException as OrigThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Throttle Requests Exception
 */

trait ThrottleRequestsExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isThrottleRequestsException(Throwable $e): bool
	{
		return ($e instanceof OrigThrottleRequestsException);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseThrottleRequestsException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getThrottleRequestsExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message, Response::HTTP_TOO_MANY_REQUESTS);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getThrottleRequestsExceptionMessage(Throwable $e, Request $request): string
	{
		$message = 'Too Many Requests, Please Slow Down.';
		
		if (!empty($e->getMessage())) {
			$message .= "\n" . $e->getMessage();
		}
		
		return $message;
	}
}

<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Maximum execution time exceeded exception
 */

trait MaximumExecutionTimeExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isMaximumExecutionTimeException(Throwable $e): bool
	{
		return (
			str_contains($e->getMessage(), 'Maximum execution time')
			&& str_contains($e->getMessage(), 'exceeded')
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseMaximumExecutionTimeException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getMaximumExecutionTimeExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getMaximumExecutionTimeExceptionMessage(Throwable $e, Request $request): string
	{
		// Maximum execution time exceeded
		$message = $e->getMessage() . ". \n";
		$message .= 'The server\'s maximum execution time must be increased so that it can support the execution time of the request.';
		$message .= "\n\n";
		$message .= 'For quick fix to complete the execution of the current request, you can refresh this page as many times until this error disappears.
		If the error persists you must be increase your server\'s "max_execution_time" and "max_input_time" directives.';
		
		return $message;
	}
}

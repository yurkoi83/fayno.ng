<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Memory is full exception
 * Note: Called only when reporting some Laravel error traces
 */

trait FullMemoryExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isFullMemoryException(Throwable $e): bool
	{
		return (
			str_contains($e->getMessage(), 'Allowed memory size of')
			&& str_contains($e->getMessage(), 'tried to allocate')
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseFullMemoryException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getFullMemoryExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getFullMemoryExceptionMessage(Throwable $e, Request $request): string
	{
		// Memory is full
		$message = $e->getMessage() . ". \n";
		$message .= 'The server\'s memory must be increased so that it can support the load of the requested resource.';
		
		return $message;
	}
}

<?php

namespace App\Exceptions\Handler;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Model Not Found Exception
 */

trait ModelNotFoundExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isModelNotFoundException(Throwable $e): bool
	{
		return ($e instanceof ModelNotFoundException);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseModelNotFoundException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getModelNotFoundExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message, 404);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getModelNotFoundExceptionMessage(Throwable $e, Request $request): string
	{
		$message = null;
		
		if (method_exists($e, 'getModel')) {
			$message = 'Entry for ' . str_replace('App\\', '', $e->getModel()) . ' not found.';
		}
		if (!empty($e->getMessage())) {
			$message .= !empty($message) ? "\n" : '';
			$message .= $e->getMessage();
		}
		
		return $message;
	}
}

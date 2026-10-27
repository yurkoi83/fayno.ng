<?php

namespace App\Exceptions\Handler;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * DB Query Exception
 */

trait DBQueryExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isDBQueryException(Throwable $e): bool
	{
		return ($e instanceof QueryException);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseDBQueryException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getDBQueryExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getDBQueryExceptionMessage(Throwable $e, Request $request): string
	{
		$message = 'There was issue with the query.';
		
		if (!empty($e->getMessage())) {
			$message .= "\n" . $e->getMessage();
		}
		
		return $message;
	}
}

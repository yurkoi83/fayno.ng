<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Too Many Connections Exception
 */

trait DBTooManyConnectionsExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isDBTooManyConnectionsException(Throwable $e): bool
	{
		$isMaxUserConnectionsException = (
			str_contains($e->getMessage(), 'max_user_connections')
			&& str_contains($e->getMessage(), 'active connections')
		);
		$isMaxConnectionsException = str_contains($e->getMessage(), 'max_connections');
		$isTooManyConnectionsException = str_contains($e->getMessage(), 'Too many connections');
		
		return (
			appInstallFilesExist()
			&& (
				$isMaxUserConnectionsException
				|| $isMaxConnectionsException
				|| $isTooManyConnectionsException
			)
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseDBTooManyConnectionsException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getDBTooManyConnectionsExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getDBTooManyConnectionsExceptionMessage(Throwable $e, Request $request): string
	{
		$message = 'Too many connections. ' . "\n";
		$message .= 'We are experiencing a high volume of connections at the moment. ' . "\n";
		$message .= 'Please try again later. ' . "\n";
		$message .= 'We sincerely apologize for any inconvenience caused.' . "\n";
		
		return $message;
	}
}

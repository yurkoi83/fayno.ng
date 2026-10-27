<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * DB Errors Exception
 */

trait DBErrorsExceptionHandler
{
	/**
	 * Check if it is a DB connection exception
	 *
	 * DB Connection Error:
	 * http://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isDBErrorsException(Throwable $e): bool
	{
		$databaseErrorCodes = [
			'mysql'        => ['1042', '1044', '1045', '1046', '1049'],
			'standardized' => ['08S01', '42000', '28000', '3D000', '42000', '42S22'],
		];
		
		return (
			$this->isPDOException($e)
			&& (
				in_array($e->getCode(), $databaseErrorCodes['mysql'])
				|| in_array($e->getCode(), $databaseErrorCodes['standardized'])
			)
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseDBErrorsException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		return $this->responseCustomError($e, $request);
	}
}

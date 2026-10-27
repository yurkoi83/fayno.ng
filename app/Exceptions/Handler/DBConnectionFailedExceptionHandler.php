<?php

namespace App\Exceptions\Handler;

use App\Helpers\Common\DBUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PDO;
use Throwable;

trait DBConnectionFailedExceptionHandler
{
	private ?string $connectionErrorMessage = null;
	
	/**
	 * Test Database Connection
	 *
	 * @return bool
	 */
	private function isDBConnectionFailedException(): bool
	{
		$pdo = null;
		
		try {
			$pdo = DBUtils::getPdoConnection();
		} catch (Throwable $e) {
			$this->connectionErrorMessage = $e->getMessage();
		}
		
		return (appInstallFilesExist() && !($pdo instanceof PDO));
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseDBConnectionFailedException(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getDBConnectionFailedExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getDBConnectionFailedExceptionMessage(Throwable $e, Request $request): string
	{
		$message = $this->connectionErrorMessage;
		if (empty($message)) {
			$message = 'Connection to the database failed.';
		}
		
		$exceptionMessage = $e->getMessage();
		if (!empty($exceptionMessage)) {
			$message .= " \n" . $exceptionMessage;
		}
		
		return $message;
	}
}

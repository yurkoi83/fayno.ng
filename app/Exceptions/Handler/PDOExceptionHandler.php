<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PDOException;
use Throwable;

trait PDOExceptionHandler
{
	/**
	 * Is a PDO Exception
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isPDOException(Throwable $e): bool
	{
		if (
			($e instanceof PDOException)
			|| $e->getCode() == 1045
			|| str_contains($e->getMessage(), 'SQLSTATE')
			|| str_contains($e->getFile(), 'Database/Connectors/Connector.php')
		) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
	 */
	protected function responsePDOException(Throwable $e, Request $request): Response|false|JsonResponse|RedirectResponse
	{
		// Check if the app installation files exist,
		// to prevent any DB error (from the Admin Panel) when the app is not installed yet.
		if (!appInstallFilesExist()) {
			if ($request->input('exception') != 'PDO') {
				$message = $e->getMessage();
				if (!empty($message)) {
					return $this->responseCustomError($e, $request, $message);
				}
				
				$this->clearLog();
				
				return redirect()->to(getRawBaseUrl() . '/install?exception=PDO');
			}
		}
		
		return $this->responseCustomError($e, $request);
	}
}

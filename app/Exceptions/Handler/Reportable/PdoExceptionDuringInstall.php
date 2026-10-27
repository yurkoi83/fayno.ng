<?php

declare(strict_types=1);

namespace App\Exceptions\Handler\Reportable;

use PDOException;
use Throwable;

/**
 * Clear PDO error logs during installation.
 *
 * When the app is not yet installed, PDO exceptions are expected
 * (wrong credentials, missing database, etc.). We clear the log files
 * to avoid confusing noise and stop propagation to the default logging stack.
 */
class PdoExceptionDuringInstall
{
	public function __invoke(Throwable $e): ?bool
	{
		if (app_installed()) {
			return null; // Fall through to default logging
		}

		$isPdoException = (
			($e instanceof PDOException)
			|| $e->getCode() == 1045
			|| str_contains($e->getMessage(), 'SQLSTATE')
			|| str_contains($e->getFile(), 'Database/Connectors/Connector.php')
		);

		if (!$isPdoException) {
			return null; // Fall through to default logging
		}

		// Clear the error log
		$mask = storage_path('logs') . DIRECTORY_SEPARATOR . '*.log';
		$logFiles = glob($mask);
		if (is_array($logFiles) && !empty($logFiles)) {
			foreach ($logFiles as $filename) {
				@unlink($filename);
			}
		}

		// Stop the propagation of the exception to the default logging stack
		return false;
	}
}

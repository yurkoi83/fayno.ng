<?php

namespace App\Exceptions\Handler;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

trait HttpExceptionHandler
{
	/**
	 * Determine if the given exception is an HTTP exception.
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isHttpException(Throwable $e): bool
	{
		return $e instanceof HttpExceptionInterface;
	}
}

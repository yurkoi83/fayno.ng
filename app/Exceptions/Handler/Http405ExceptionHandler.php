<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

/*
 * HTTP Method Not Allowed Exception
 */

trait Http405ExceptionHandler
{
	/**
	 * Check if it is an HTTP Method Not Allowed exception
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isHttp405Exception(Throwable $e): bool
	{
		return (
			$e instanceof MethodNotAllowedHttpException
			|| (
				$this->isHttpException($e)
				&& method_exists($e, 'getStatusCode')
				&& $e->getStatusCode() == 405
			)
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	protected function responseHttp405Exception(Throwable $e, Request $request): Response|false|JsonResponse
	{
		$message = $this->getHttp405ExceptionMessage($e, $request);
		
		return $this->responseCustomError($e, $request, $message, 405);
	}
	
	// PRIVATE
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	private function getHttp405ExceptionMessage(Throwable $e, Request $request): string
	{
		$message = "Whoops! Seems you use a bad request method. Please try again.";
		
		if (!isApiRoute($request)) {
			$backLink = ' <a href="' . url()->previous() . '">' . trans('global.Back') . '</a>';
			$message = $message . $backLink;
		}
		
		return $message;
	}
}

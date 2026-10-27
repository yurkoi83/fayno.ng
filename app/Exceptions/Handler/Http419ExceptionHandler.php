<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * Authentication Timeout Exception
 */

trait Http419ExceptionHandler
{
	/**
	 * Check if the page is expired
	 *
	 * Note:
	 * - The 419 Page Expired error created by the Laravel PHP Framework message is received when the CSRF validation fails
	 * - This overrides the "Token Mismatch Exception"
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isHttp419Exception(Throwable $e): bool
	{
		return (
			$this->isHttpException($e)
			&& method_exists($e, 'getStatusCode')
			&& $e->getStatusCode() == 419
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
	 */
	protected function responseHttp419Exception(Throwable $e, Request $request): Response|false|JsonResponse|RedirectResponse
	{
		$message = getHttp419ExceptionMessage($request);
		
		if (!isApiRoute($request) && !isFromAjax($request)) {
			$previousUrl = $this->getHttp419ExceptionPreviousUrl();
			if (!empty($previousUrl)) {
				notification(message: $message, level: 'error', targetUrl: $previousUrl);
				
				return redirect()->to($previousUrl)->withInput();
			}
		}
		
		return $this->responseCustomError($e, $request, $message, 419);
	}
	
	// PRIVATE
	
	/**
	 * @return string|null
	 */
	private function getHttp419ExceptionPreviousUrl(): ?string
	{
		$previousUrl = url()->previous();
		
		$param = 'error=AuthTimeout';
		if (!str_contains($previousUrl, $param)) {
			$queryString = (parse_url($previousUrl, PHP_URL_QUERY) ? '&' : '?') . $param;
			
			return $previousUrl . $queryString;
		}
		
		return null;
	}
}

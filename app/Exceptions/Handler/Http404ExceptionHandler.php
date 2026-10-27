<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/*
 * HTTP Page Not Found
 */

trait Http404ExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isHttp404Exception(Throwable $e): bool
	{
		return (
			$this->isHttpException($e)
			&& method_exists($e, 'getStatusCode')
			&& $e->getStatusCode() == 404
		);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
	 */
	protected function responseHttp404Exception(Throwable $e, Request $request): Response|false|JsonResponse|RedirectResponse
	{
		if (!isApiRoute($request) && !isFromAjax($request)) {
			/*
			 * Check if the app is installed when page is not found (or when 404 page is called),
			 * to prevent any DB error when the app is not installed yet
			 */
			if (!appIsInstalled()) {
				if ($request->input('exception') != '404') {
					return redirect()->to(getRawBaseUrl() . '/install?exception=404');
				}
			}
			
			return $this->renderCustomExceptionViews($e, $request);
		}
		
		$message = $e->getMessage();
		$message = !empty($message) ? $message : 'Page not found.';
		
		return $this->responseCustomError($e, $request, $message, 404);
	}
}

<?php

namespace App\Exceptions\Handler;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

trait AuthenticationExceptionHandler
{
	/**
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isAuthenticationException(Throwable $e): bool
	{
		return ($e instanceof AuthenticationException);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
	 */
	protected function responseAuthenticationException(Throwable $e, Request $request): Response|false|JsonResponse|RedirectResponse
	{
		$message = trans('global.unauthenticated_or_token_expired');
		
		if (!isApiRoute($request) && !isFromAjax($request)) {
			$loginUrl = urlGen()->signIn();
			notification(message: $message, level: 'error', targetUrl: $loginUrl);
			
			return redirect()->guest($loginUrl);
		}
		
		return $this->responseCustomError($e, $request, $message, Response::HTTP_UNAUTHORIZED);
	}
}

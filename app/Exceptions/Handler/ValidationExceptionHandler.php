<?php

namespace App\Exceptions\Handler;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ValidationExceptionHandler
{
	/**
	 * Check it is a Validation exception
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	protected function isValidationException(Throwable $e): bool
	{
		return ($e instanceof ValidationException);
	}
	
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return false|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
	 */
	protected function responseValidationException(Throwable $e, Request $request): false|JsonResponse|RedirectResponse
	{
		if (!isApiRoute($request) && !isFromAjax($request)) {
			/*
			 * Temporary fix when forms (after failed validation) are not redirect to back with explicit error messages per field
			 * Issue found on type of server: Apache/2.4.52 (Win64) OpenSSL/1.1.1m PHP/8.1.2
			 */
			if (method_exists($e, 'errors')) {
				return redirect()->back()->withErrors($e->errors())->withInput();
			}
			
			return false;
		}
		
		// For API & AJAX calls only
		$message = $e->getMessage();
		
		$data = [
			'success' => false,
			'message' => $message,
		];
		
		// Get validation error messages
		$errors = [];
		if (method_exists($e, 'errors')) {
			$errors = $e->errors();
			$data['errors'] = $errors;
		}
		
		if (doesRequestIsFromWebClient($request) || isFromAjax($request)) {
			// Get errors (as String)
			if (is_array($errors) && count($errors) > 0) {
				$errorsTxt = '';
				foreach ($errors as $value) {
					if (is_array($value)) {
						foreach ($value as $v) {
							$errorsTxt .= empty($errorsTxt) ? '- ' . $v : '<br>- ' . $v;
						}
					} else {
						$errorsTxt .= empty($errorsTxt) ? '- ' . $value : '<br>- ' . $value;
					}
				}
			} else {
				$errorsTxt = $message;
			}
			
			// NOTE: 'bootstrap-fileinput' need 'error' (text) element,
			// & the optional 'errorkeys' (array) element.
			$data['error'] = $errorsTxt; // for bootstrap-fileinput
		}
		
		return apiResponse()->json($data, Response::HTTP_UNPROCESSABLE_ENTITY);
	}
}

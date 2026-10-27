<?php

namespace App\Exceptions\Handler\Traits;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

trait HandlerTrait
{
	/**
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @param string|null $message
	 * @param int|null $status
	 * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|false
	 */
	protected function responseCustomError(Throwable $e, Request $request, ?string $message = null, ?int $status = null): Response|JsonResponse|false
	{
		// Get status code
		$defaultStatus = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
		$status = !empty($status) ? $status : $defaultStatus;
		$status = isValidHttpStatus($status) ? $status : 500;
		
		// Get error message
		$message = !empty($message) ? $message : $e->getMessage();
		$message = !empty($message) ? $message : getHttpStatusMessage($status);
		
		if (isApiRoute($request) || isFromAjax($request)) {
			$strippedMessage = $message;
			if (isApiRoute($request) && !doesRequestIsFromWebClient($request)) {
				$strippedMessage = strip_tags($message);
			}
			$data = [
				'success'   => false,
				'message'   => $strippedMessage,
				'status'    => $status,
				'exception' => $this,
			];
			
			if (doesRequestIsFromWebClient($request) || isFromAjax($request)) {
				$data['error'] = $message; // for bootstrap-fileinput
			}
			
			return apiResponse()->json($data, $status);
		}
		
		// Get message as styled string
		$message = $this->getStyledString($message);
		
		$data = [
			'message'   => $message,
			'status'    => $status,
			'exception' => $this,
		];
		
		// Get the theme error view path
		$viewPath = $this->getThemeErrorViewPath('custom');
		
		// Render the exception (if its view found)
		if (!empty($viewPath)) {
			// Make `errors::*` resolvable from inside the rendered view (e.g. our
			// 503/404 blades that `@extends('errors::layout')`). Laravel's core
			// exception path runs this automatically; manual `response()->view()`
			// rendering does not, so we invoke it ourselves.
			(new RegisterErrorViewPaths())();
			
			return response()->view($viewPath, $data, $status);
		}
		
		// or EXIT (i.e. Allow the Laravel core to handle the error)
		return false;
	}
	
	/**
	 * Response for all non-handled exceptions
	 *
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	protected function jsonResponseCustomError(Throwable $e, Request $request): JsonResponse
	{
		// Get status code
		$status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
		$status = isValidHttpStatus($status) ? $status : 500;
		
		// Get error message
		$message = $e->getMessage();
		if (!empty($message)) {
			$message = !empty($e->getLine()) ? $message . ' Line: ' . $e->getLine() : $message;
			$message = !empty($e->getFile()) ? $message . ' in file: ' . $e->getFile() : $message;
		} else {
			$message = getHttpStatusMessage($status);
		}
		
		$data = [
			'success'   => false,
			'message'   => $message,
			'status'    => $status,
			'exception' => $e,
		];
		
		if (doesRequestIsFromWebClient($request) || isFromAjax($request)) {
			$data['error'] = $message; // for bootstrap-fileinput
		}
		
		return apiResponse()->json($data, $status);
	}
	
	/**
	 * Change the error views path in Laravel
	 *
	 * @param \Throwable $e
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response|false
	 */
	protected function renderCustomExceptionViews(Throwable $e, Request $request): Response|false
	{
		if (!isApiRoute($request) && !isFromAjax($request)) {
			// Get status code
			$defaultStatus = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
			$status = !empty($status) ? $status : $defaultStatus;
			$status = isValidHttpStatus($status) ? $status : 500;
			
			// Get the theme error view path
			$viewPath = $this->getThemeErrorViewPath($status);
			
			// Render the exception (if its view found)
			if (!empty($viewPath)) {
				// Make `errors::*` resolvable from inside the rendered view (e.g. our
				// 503/404 blades that `@extends('errors::layout')`). Laravel's core
				// exception path runs this automatically; manual `response()->view()`
				// rendering does not, so we invoke it ourselves.
				(new RegisterErrorViewPaths())();
				
				return response()->view($viewPath, ['exception' => $e], $status);
			}
			
			// or EXIT (i.e. Allow the Laravel core to handle the error)
			return false;
		}
		
		// EXIT
		// (i.e. Allow the Laravel core to handle the error)
		return false;
	}
	
	// PRIVATE
	
	/**
	 * Create a config var for current language
	 *
	 * @return void
	 */
	private function getLanguage(): void
	{
		// Get the language only the app is already installed
		// to prevent HTTP 500 error through DB connexion during the installation process.
		if (appInstallFilesExist()) {
			$this->config->set('lang.code', config('app.locale'));
		}
	}
	
	/**
	 * Clear Laravel Log files
	 *
	 * @return void
	 */
	private function clearLog(): void
	{
		$mask = storage_path('logs') . DIRECTORY_SEPARATOR . '*.log';
		$logFiles = glob($mask);
		if (is_array($logFiles) && !empty($logFiles)) {
			foreach ($logFiles as $filename) {
				@unlink($filename);
			}
		}
	}
	
	/**
	 * Get message as styled string
	 *
	 * @param string $message
	 * @return string
	 */
	private function getStyledString(string $message): string
	{
		// Explode the message by new line
		$lines = preg_split('/\r\n|\r|\n/', $message);
		$countLines = is_array($lines) ? count($lines) : 0;
		if ($countLines > 0 && $countLines <= 3) {
			$message = '<div class="align-center text-danger">' . $message . '</div>';
		}
		
		return $message;
	}
}

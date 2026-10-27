<?php

namespace App\Exceptions;

use App\Exceptions\Handler\AuthenticationExceptionHandler;
use App\Exceptions\Handler\CachingExceptionHandler;
use App\Exceptions\Handler\DBCollationErrorExceptionHandler;
use App\Exceptions\Handler\DBErrorsExceptionHandler;
use App\Exceptions\Handler\DBConnectionFailedExceptionHandler;
use App\Exceptions\Handler\DBQueryExceptionHandler;
use App\Exceptions\Handler\DBTableExceptionHandler;
use App\Exceptions\Handler\DBTooManyConnectionsExceptionHandler;
use App\Exceptions\Handler\FullMemoryExceptionHandler;
use App\Exceptions\Handler\Http404ExceptionHandler;
use App\Exceptions\Handler\Http405ExceptionHandler;
use App\Exceptions\Handler\Http413ExceptionHandler;
use App\Exceptions\Handler\Http419ExceptionHandler;
use App\Exceptions\Handler\HttpExceptionHandler;
use App\Exceptions\Handler\MaximumExecutionTimeExceptionHandler;
use App\Exceptions\Handler\ModelNotFoundExceptionHandler;
use App\Exceptions\Handler\PDOExceptionHandler;
use App\Exceptions\Handler\AddonClassLoadingExceptionHandler;
use App\Exceptions\Handler\AddonTypeDeclarationsExceptionHandler;
use App\Exceptions\Handler\ThrottleRequestsExceptionHandler;
use App\Exceptions\Handler\TokenMismatchExceptionHandler;
use App\Exceptions\Handler\Traits\ExceptionTrait;
use App\Exceptions\Handler\Traits\HandlerTrait;
use App\Exceptions\Handler\Traits\NotificationTrait;
use App\Exceptions\Handler\UnserializeExceptionHandler;
use App\Exceptions\Handler\ValidationExceptionHandler;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Throwable;

class Handler
{
	use ExceptionTrait, HandlerTrait, NotificationTrait;
	
	use AuthenticationExceptionHandler;
	use CachingExceptionHandler;
	use DBCollationErrorExceptionHandler;
	use DBConnectionFailedExceptionHandler;
	use DBErrorsExceptionHandler;
	use DBQueryExceptionHandler;
	use DBTableExceptionHandler;
	use DBTooManyConnectionsExceptionHandler;
	use FullMemoryExceptionHandler;
	use HttpExceptionHandler;
	use Http404ExceptionHandler;
	use Http405ExceptionHandler;
	use Http413ExceptionHandler;
	use Http419ExceptionHandler;
	use MaximumExecutionTimeExceptionHandler;
	use ModelNotFoundExceptionHandler;
	use PDOExceptionHandler;
	use AddonClassLoadingExceptionHandler;
	use AddonTypeDeclarationsExceptionHandler;
	use ThrottleRequestsExceptionHandler;
	use TokenMismatchExceptionHandler;
	use UnserializeExceptionHandler;
	use ValidationExceptionHandler;
	
	protected mixed $app;
	protected ConfigRepository $config;
	
	public function __construct()
	{
		$this->app = app();
		$this->config = $this->app->instance('config', new ConfigRepository());
		
		// Fix the 'files' & 'filesystem' binging.
		$this->app->register(FilesystemServiceProvider::class);
		
		// Create a config var for the current language
		$this->getLanguage();
	}
	
	public function __invoke(Exceptions $exceptions): void
	{
		/*
		 * Report or log an exception
		 */
		$exceptions->report(function (Throwable $e) {
			// Clear PDO error log during installation
			if (!appInstallFilesExist()) {
				if ($this->isPDOException($e)) {
					$this->clearLog();
					
					// Stop the propagation of the exception to the default logging stack
					return false;
				}
			}
			
			if (appInstallFilesExist()) {
				$this->sendNotification($e);
			}
		});
		
		/*
		 * Render an exception into an HTTP response
		 */
		$exceptions->render(function (Throwable $e, Request $request) {
			// Restore the request headers back to the original state
			// saved before the API call (using a sub request option)
			if (config('request.original.headers')) {
				request()->headers->replace(config('request.original.headers'));
			}
			
			// Maximum execution time exceeded exception
			if ($this->isMaximumExecutionTimeException($e)) {
				return $this->responseMaximumExecutionTimeException($e, $request);
			}
			
			// Memory is a full exception
			if ($this->isFullMemoryException($e)) {
				return $this->responseFullMemoryException($e, $request);
			}
			
			// HTTP Exception
			if ($this->isHttpException($e)) {
				// HTTP Page Not Found
				if ($this->isHttp404Exception($e)) {
					return $this->responseHttp404Exception($e, $request);
				}
				
				// HTTP Method Not Allowed Exception
				if ($this->isHttp405Exception($e)) {
					return $this->responseHttp405Exception($e, $request);
				}
				
				// Post Too Large Exception
				if ($this->isHttp413Exception($e)) {
					return $this->responseHttp413Exception($e, $request);
				}
				
				// Authentication Timeout Exception
				if ($this->isHttp419Exception($e)) {
					return $this->responseHttp419Exception($e, $request);
				}
				
				// Use custom error views to handle the next HTTP exceptions
				// or let Laravel handles them
				return $this->renderCustomExceptionViews($e, $request);
			}
			
			// Token Mismatch Exception (Deprecated)
			if ($this->isTokenMismatchException($e)) {
				return $this->responseTokenMismatchException($e, $request);
			}
			
			// Throttle Requests Exception
			if ($this->isThrottleRequestsException($e)) {
				return $this->responseThrottleRequestsException($e, $request);
			}
			
			// Validation Exception
			if ($this->isValidationException($e)) {
				return $this->responseValidationException($e, $request);
			}
			
			// Caching (APC or Redis) Exception
			if ($this->isCachingException($e)) {
				return $this->responseCachingException($e, $request);
			}
			
			// PDO & DB Exception
			if ($this->isPDOException($e)) {
				// Too Many Connections Exception
				if ($this->isDBTooManyConnectionsException($e)) {
					return $this->responseDBTooManyConnectionsException($e, $request);
				}
				
				if ($this->isDBConnectionFailedException()) {
					return $this->responseDBConnectionFailedException($e, $request);
				}
				
				// DB Collation Error Exception
				if ($this->isDBCollationErrorException($e)) {
					return $this->responseDBCollationErrorException($e, $request);
				}
				
				// DB Errors Exception
				if ($this->isDBErrorsException($e)) {
					return $this->responseDBErrorsException($e, $request);
				}
				
				// DB Tables & Columns Errors Exception
				if ($this->isDBTableException($e)) {
					return $this->responseDBTableException($e, $request);
				}
				
				// DB Query Exception
				if ($this->isDBQueryException($e)) {
					return $this->responseDBQueryException($e, $request);
				}
				
				// Only PDO Exception response
				return $this->responsePDOException($e, $request);
			}
			
			// Model Not Found Exception
			if ($this->isModelNotFoundException($e)) {
				return $this->responseModelNotFoundException($e, $request);
			}
			
			// Convert an authentication exception into an unauthenticated response
			if ($this->isAuthenticationException($e)) {
				return $this->responseAuthenticationException($e, $request);
			}
			
			// Try to fix the cookies issue related to the Laravel security release:
			// https://laravel.com/docs/5.6/upgrade#upgrade-5.6.30
			if ($this->isUnserializeException($e)) {
				return $this->responseUnserializeException($e, $request);
			}
			
			// Check if there is no addon class loading issue (inside composer class loader)
			if ($this->isAddonClassLoadingException($e)) {
				return $this->responseAddonClassLoadingException($e, $request);
			}
			
			// Check if there are no problems in an addon code
			if ($this->isAddonTypeDeclarationsException($e)) {
				return $this->responseAddonTypeDeclarationsException($e, $request);
			}
			
			// API or AJAX requests exception
			if (isApiRoute($request) || isFromAjax($request)) {
				return $this->jsonResponseCustomError($e, $request);
			}
			
			// Use custom error views to handle the next exceptions
			// or let Laravel handles them
			return $this->renderCustomExceptionViews($e, $request);
		});
	}
}

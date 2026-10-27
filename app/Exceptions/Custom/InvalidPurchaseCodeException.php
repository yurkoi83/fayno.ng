<?php

namespace App\Exceptions\Custom;

use App\Exceptions\Handler\Traits\ExceptionTrait;
use App\Exceptions\Handler\Traits\HandlerTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class InvalidPurchaseCodeException extends Exception
{
	use ExceptionTrait, HandlerTrait;
	
	/**
	 * Report the exception.
	 */
	public function report(): void
	{
		Log::warning($this->getMessage());
	}
	
	/**
	 * Render the exception into an HTTP response.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	public function render(Request $request): Response|JsonResponse
	{
		$message = $this->getMessage();
		$message = '<div class="align-center text-danger">' . $message . '</div>';
		
		return $this->responseCustomError($this, $request, $message, 401);
	}
}

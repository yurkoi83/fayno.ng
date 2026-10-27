<?php

namespace App\Exceptions\Custom;

use App\Exceptions\Handler\Traits\ExceptionTrait;
use App\Exceptions\Handler\Traits\HandlerTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ModelOperationCancelledException extends Exception
{
	use ExceptionTrait, HandlerTrait;

	/**
	 * @param $message
	 */
	public function __construct($message = "Operation was cancelled")
	{
		parent::__construct($message);
	}

	/**
	 * Report the exception.
	 */
	public function report(): void
	{
		// Nothing.
	}

	/**
	 * Render the exception into an HTTP response.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
	 */
	public function render(Request $request): Response|JsonResponse
	{
		return $this->responseCustomError($this, $request);
	}
}

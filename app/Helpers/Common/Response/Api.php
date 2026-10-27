<?php

namespace App\Helpers\Common\Response;

use App\Http\Resources\EmptyCollection;
use App\Http\Resources\EmptyResource;
use Error;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

class Api
{
	/**
	 * Return generic json response with the given data.
	 * https://laracasts.com/discuss/channels/laravel/pagination-data-missing-from-api-resource
	 *
	 * @param array $data
	 * @param int $status
	 * @param array $headers
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function json(array $data = [], int $status = 200, array $headers = []): JsonResponse
	{
		// Parse the given data
		$result = $this->parseGivenData($data, $status, $headers);

		// Get formatted data for Laravel JSON response arguments
		$data = $result['formattedData'];
		$status = $result['status'];
		$headers = $result['headers'];

		try {

			$headers = addContentTypeHeader('application/json', $headers);
			$statusText = getHttpStatusMessage($status);

			return response()
				->json($data, $status, $headers, JSON_UNESCAPED_UNICODE)
				->setStatusCode($status, $statusText);

		} catch (Throwable $e) {
			return $this->internalError(getExceptionMessage($e));
		}
	}

	/**
	 * @param array $data
	 * @param int $status
	 * @param array $headers
	 * @return array
	 */
	private function parseGivenData(array $data = [], int $status = 200, array $headers = []): array
	{
		$formattedData = [
			'success' => $data['success'],
			'message' => $data['message'] ?? null,
			'result'  => $data['result'] ?? null,
		];

		if (isset($data['extra'])) {
			$formattedData['extra'] = $data['extra'];
		}

		if (isset($data['errors'])) {
			$formattedData['errors'] = $data['errors'];
		}

		// Get status code
		$requestedStatus = $data['status'] ?? $status;
		$status = isValidHttpStatus($requestedStatus) ? $requestedStatus : $status;
		$status = isValidHttpStatus($status) ? $status : 200;

		// NOTE: 'bootstrap-fileinput' need 'error' (text) element & the optional 'errorkeys' (array) element
		if (isset($data['error'])) {
			$formattedData['error'] = $data['error'];
		}

		if (isset($data['exception']) && ($data['exception'] instanceof Error || $data['exception'] instanceof Exception)) {
			if (config('app.env') !== 'production') {
				$formattedData['exception'] = [
					'message' => $data['exception']->getMessage(),
					'file'    => $data['exception']->getFile(),
					'line'    => $data['exception']->getLine(),
					'code'    => $data['exception']->getCode(),
					'trace'   => $data['exception']->getTrace(),
				];
			}

			if ($status === 200) {
				$status = 500;
			}
		}

		return ['formattedData' => $formattedData, 'status' => $status, 'headers' => $headers];
	}

	/**
	 * @param \Illuminate\Http\Resources\Json\JsonResource $resource
	 * @param string|null $message
	 * @param int $status
	 * @param array $headers
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function withResource(
		JsonResource $resource,
		?string      $message = null,
		int          $status = 200,
		array        $headers = []
	): JsonResponse
	{
		// https://laracasts.com/discuss/channels/laravel/pagination-data-missing-from-api-resource

		return $this->json([
			'success' => true,
			'result'  => $resource,
			'message' => $message,
		], $status, $headers);
	}

	/**
	 * @param \Illuminate\Http\Resources\Json\ResourceCollection $resourceCollection
	 * @param string|null $message
	 * @param int $status
	 * @param array $headers
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function withCollection(
		ResourceCollection $resourceCollection,
		?string            $message = null,
		int                $status = 200,
		array              $headers = []
	): JsonResponse
	{
		// https://laracasts.com/discuss/channels/laravel/pagination-data-missing-from-api-resource

		return $this->json([
			'success' => true,
			'result'  => $resourceCollection->response()->getData(),
			'message' => $message,
		], $status, $headers);
	}

	/**
	 * Respond with success.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function success(?string $message = ''): JsonResponse
	{
		return $this->json(['success' => true, 'message' => $message]);
	}

	/**
	 * Respond with error.
	 *
	 * @param $message
	 * @param int $status
	 * @param \Exception|null $exception
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function error($message, int $status = 400, ?Exception $exception = null): JsonResponse
	{
		$message = $message ?? 'There was an internal error, Pls try again later';

		return $this->json([
			'success'   => false,
			'message'   => $message,
			'exception' => $exception,
		], $status);
	}

	/**
	 * Respond with created.
	 *
	 * @param $data
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function created($data): JsonResponse
	{
		return $this->json($data, Response::HTTP_CREATED);
	}

	/**
	 * Respond with update.
	 *
	 * @param $data
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updated($data): JsonResponse
	{
		return $this->json($data, 200);
	}

	/**
	 * Respond with no content.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function noContent(?string $message = 'No Content Found'): JsonResponse
	{
		return $this->json(['success' => false, 'message' => $message], 200);
	}

	/**
	 * Respond with no content.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function noContentResource(?string $message = 'No Content Found'): JsonResponse
	{
		return $this->withResource(new EmptyResource([]), $message);
	}

	/**
	 * Respond with no content.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function noContentCollection(?string $message = 'No Content Found'): JsonResponse
	{
		return $this->withCollection(new EmptyCollection([]), $message);
	}

	/**
	 * Respond with unauthorized.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function unauthorized(?string $message = 'Unauthorized'): JsonResponse
	{
		return $this->error($message, Response::HTTP_UNAUTHORIZED);
	}

	/**
	 * Respond with forbidden.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function forbidden(?string $message = 'Forbidden'): JsonResponse
	{
		return $this->error($message, Response::HTTP_FORBIDDEN);
	}

	/**
	 * Respond with not found.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function notFound(?string $message = 'Not Found'): JsonResponse
	{
		return $this->error($message, 404);
	}

	/**
	 * Respond with internal error.
	 *
	 * @param string|null $message
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function internalError(?string $message = 'Internal Error'): JsonResponse
	{
		return $this->error($message, 500);
	}

	/**
	 * @param \Illuminate\Validation\ValidationException $exception
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function validationErrors(ValidationException $exception): JsonResponse
	{
		return $this->json([
			'success' => false,
			'message' => $exception->getMessage(),
			'errors'  => $exception->errors(),
		], Response::HTTP_UNPROCESSABLE_ENTITY);
	}
}

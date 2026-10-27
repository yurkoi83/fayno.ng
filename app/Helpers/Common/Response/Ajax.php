<?php

namespace App\Helpers\Common\Response;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class Ajax
{
	/**
	 * @param array|null $data
	 * @param int $status
	 * @param array $headers
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function json(?array $data = [], int $status = 200, array $headers = []): JsonResponse
	{
		$data = is_array($data) ? $data : [];

		$headers = addContentTypeHeader('application/json', $headers);
		$status = castToInt($status);
		$status = isValidHttpStatus($status) ? $status : 200;
		$statusText = getHttpStatusMessage($status);

		return response()
			->json($data, $status, $headers, JSON_UNESCAPED_UNICODE)
			->setStatusCode($status, $statusText);
	}

	/**
	 * @param string|null $content
	 * @param int $status
	 * @param array $headers
	 * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
	 */
	public function text(?string $content = '', int $status = 200, array $headers = []): Response|ResponseFactory
	{
		$content = is_string($content) ? $content : '';

		$headers = addContentTypeHeader('text/plain', $headers);
		$status = castToInt($status);
		$status = isValidHttpStatus($status) ? $status : 200;
		$statusText = getHttpStatusMessage($status);

		return response($content, $status)
			->withHeaders($headers)
			->setStatusCode($status, $statusText);
	}
}

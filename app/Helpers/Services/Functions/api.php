<?php

use Illuminate\Http\Request;

/**
 * Generate a Token for API calls
 */
function generateApiToken(): string
{
	return base64_encode(createRandomString(32));
}

/**
 * Check if the current route/URL is under the API area
 */
function isApiRoute(?Request $request = null): bool
{
	if (!$request instanceof Request) {
		$request = request();
	}

	return
		str_starts_with($request->path(), 'api/')
		|| $request->is('api/*')
		|| $request->segment(1) == 'api'
		|| ($request->hasHeader('X-API-CALLED') && $request->header('X-API-CALLED'));
}

/**
 * @deprecated
 */
function isFromApi(?Request $request = null): bool
{
	return isApiRoute($request);
}

/**
 * Does the (current) request is from a Web Application?
 * Check if the current request is made from the official(s) web version(s) of the app
 *
 * Info: This function allows applying web features during API code execution
 * Note: This assumes the "X-AppType=web" header is sent from the web application
 */
function doesRequestIsFromWebClient(?Request $request = null): bool
{
	if (!$request instanceof Request) {
		$request = request();
	}

	if (!isApiRoute($request)) {
		return true;
	}

	return isApiRoute($request) && $request->header('X-AppType') == 'web';
}

/**
 * @return mixed
 */
function setPaginationBaseUrl($paginatedCollection)
{
	// If the request is made from the app's Web environment,
	// use the Web URL as the pagination's base URL
	if (doesRequestIsFromWebClient()) {
		if (request()->hasHeader('X-WEB-REQUEST-URL')) {
			if (method_exists($paginatedCollection, 'setPath')) {
				$paginatedCollection->setPath(request()->header('X-WEB-REQUEST-URL'));
			}
		}
	}

	return $paginatedCollection;
}

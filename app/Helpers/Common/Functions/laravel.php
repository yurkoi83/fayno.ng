<?php

use App\Enums\ThemePreference;
use App\Exceptions\Custom\CustomException;
use App\Helpers\Common\Arr;
use App\Helpers\Common\Cookie;
use App\Helpers\Common\DotenvEditor;
use App\Helpers\Common\Ip;
use App\Helpers\Common\JsonUtils;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Traits\Macroable;

/**
 * Get the current Laravel route action
 * Wraps Route::currentRouteAction() to prevent null returns
 *
 * Note: app()->hasBeenBootstrapped() vs app()->isBooted()
 *
 * app()->hasBeenBootstrapped():
 * - Core bootstrappers have run (loading environment, configuration, exception handling, facades, etc.)
 * - The application's basic foundation is set up
 * - This happens before service providers are booted
 * i.e.
 * - Returns true after bootstrappers run, but before providers boot
 * - If run true, config, env vars are loaded, but not all services are ready
 *
 * app()->isBooted():
 * - All service providers have been registered
 * - All service providers have had their boot() methods called
 * - The application is ready to handle requests
 * i.e.
 * - Returns true after all service providers are booted
 * - If run true, safe to use services that depend on booted providers
 */
function currentRouteAction(): string
{
    // Check if the application is booted
    if (! app()->isBooted()) {
        return '';
    }

    $value = Route::currentRouteAction();

    return castToString($value);
}

/**
 * Check if the current route action contains a specific substring
 * Useful for checking controller methods like '@index', '@create', etc.
 *
 * @param  string|array  $string  $string
 */
function routeActionHas(string|array $string): bool
{
    // Check if the application is booted
    if (! app()->isBooted()) {
        return false;
    }

    if (is_string($string)) {
        return str_contains(currentRouteAction(), $string);
    }

    $found = false;
    foreach ($string as $item) {
        $found = str_contains(currentRouteAction(), $item);
        if ($found) {
            break;
        }
    }

    return $found;
}

/**
 * Get a specific segment from the URL.
 *
 * Note: For an accurate result, make sure that $requestUri starts by "/"
 * or $requestUri is a valid URL starting by the "http(s)://" protocol
 */
function getUrlSegment(string $requestUri, int $index, ?string $default = null): ?string
{
    $requestUri = trim(strtolower($requestUri));

    // Normalize the URL by removing the query string if present
    $requestUri = str_starts_with($requestUri, 'http')
        ? urlBuilder($requestUri)->removeAllParameters()->toString()
        : parse_url($requestUri, PHP_URL_PATH);

    // Find the right $index value
    if (str_starts_with($requestUri, 'http')) {
        // When $requestUri starts by the "http(s)://" protocol
        $index = $index + 2;
    } else {
        if (! str_starts_with($requestUri, '/')) {
            // $requestUri starts by the first segment,
            // so we need to consider that segments keys stars by 0 (i.e. $index - 1)
            $index = $index - 1;
        }
    }

    // Split into segments
    $segments = explode('/', $requestUri);
    $value = $segments[$index] ?? $default;

    return castToStringOrNull($value);
}

/**
 * Check if a model has translation columns
 */
function isTranslatableModel($model): bool
{
    $isTranslatable = false;

    try {
        if (! ($model instanceof Model)) {
            return false;
        }

        $isTranslatableModel = (
            property_exists($model, 'translatable')
            && ! empty($model->translatable)
        );

        if ($isTranslatableModel) {
            $isTranslatable = true;
        }
    } catch (Throwable $e) {
        return false;
    }

    return $isTranslatable;
}

/**
 * Check if a model's column is translatable
 */
function isTranslatableColumn($model, string|Closure $column): bool
{
    if (! is_string($column)) {
        return false;
    }

    /** @var Page $model (for example) */
    if (isTranslatableModel($model)) {
        return in_array($column, $model->translatable);
    }

    return false;
}

/**
 * Default translator (e.g. en/global.php)
 *
 * @deprecated
 */
function t(?string $key = null, array $replace = [], string $file = 'global', ?string $locale = null): string
{
    if (is_null($locale)) {
        $locale = config('app.locale');
    }

    $translation = trans($file.'.'.$key, $replace, $locale);

    return castToString($translation);
}

/**
 * Generate new App Key using Artisan
 *
 * Note:
 * - The APP_KEY variable in the /.env will be updated
 * - The App Key mays be regenerated (or updated) for security reasons
 * - Generating a new App Key using artisan invalidates all the sessions and cookies
 */
function updateAppKeyWithArtisan(): void
{
    try {
        // Generating a new App Key, removes|clears
        // (or invalidates) all the sessions and cookies
        Artisan::call('key:generate', ['--force' => true]);
    } catch (Throwable $e) {
    }
}

/**
 * Update the App Key without Artisan
 *
 * @throws CustomException
 */
function updateAppKeyWithoutArtisan(bool $clearCookies = false): void
{
    // Clear all cookies
    if ($clearCookies) {
        Cookie::forgetAll();
    }

    $appKey = generateAppKey();
    DotenvEditor::setKey('APP_KEY', $appKey);
    DotenvEditor::save();
}

/**
 * Generate a new App Key (with base64 of a random string)
 */
function generateAppKey(): string
{
    $base64RandomString = base64_encode(createRandomString(32));

    return 'base64:'.$base64RandomString;
}

function getIp(?string $defaultIp = ''): string
{
    return Ip::get($defaultIp);
}

/**
 * Get host (domain with subdomain)
 */
function getHost(?string $url = null): string
{
    if (! empty($url)) {
        $host = parse_url($url, PHP_URL_HOST);
    } else {
        $host = (trim(request()->server('HTTP_HOST')) != '') ? request()->server('HTTP_HOST') : ($_SERVER['HTTP_HOST'] ?? '');
    }

    if ($host == '') {
        $host = parse_url(url()->current(), PHP_URL_HOST);
    }

    return castToString($host);
}

/**
 * Get domain (host without a subdomain)
 */
function getDomain(?string $url = null): string
{
    if (! empty($url)) {
        $host = parse_url($url, PHP_URL_HOST);
    } else {
        $host = getHost();
    }

    $tmp = explode('.', $host);
    if (count($tmp) > 2) {
        $itemsToKeep = count($tmp) - 2;
        $tldArray = getTopLevelDomainRefList();
        if (isset($tmp[$itemsToKeep]) && isset($tldArray[$tmp[$itemsToKeep]])) {
            $itemsToKeep = $itemsToKeep - 1;
        }
        for ($i = 0; $i < $itemsToKeep; $i++) {
            Arr::forget($tmp, $i);
        }
        $domain = implode('.', $tmp);
    } else {
        $domain = @implode('.', $tmp);
    }

    return $domain;
}

/**
 * Get subdomain name
 *
 * NOTE:
 * The subdomains of the fetched subdomain are not retrieved
 * Example: xxx.yyy.zzz.foo.com, only "xxx" will be retrieved
 */
function getSubDomainName(): string
{
    $host = getHost();

    return (substr_count($host, '.') > 1) ? trim(current(explode('.', $host))) : '';
}

function getCookieDomain(): string
{
    $host = getHost();
    $array = mb_parse_url($host);

    return (is_array($array) && ! empty($array['path']))
        ? $array['path']
        : $host;
}

/**
 * Check local environment
 */
function isLocalEnv(?string $url = null): bool
{
    if (empty($url)) {
        $url = config('app.url');
    }

    return
        str_contains($url, '127.0.0.1')
        || str_contains($url, '::1')
        || (! str_contains($url, '.'))
        || str_ends_with(getDomain($url), '.local')
        || str_ends_with(getDomain($url), '.localhost');
}

/**
 * Check tld is a valid tld
 */
function checkTld(?string $url): bool
{
    if (empty($url)) {
        return false;
    }

    $parsedUrl = parse_url($url);
    if ($parsedUrl === false) {
        return false;
    }

    $tldArray = getTopLevelDomainRefList();
    $patten = implode('|', array_keys($tldArray));

    $matched = preg_match('/\.('.$patten.')$/i', $parsedUrl['host']);

    return (bool) $matched;
}

function vTime(): string
{
    $timeStamp = '?v='.time();
    if (app()->environment(['staging', 'production'])) {
        $timeStamp = '';
    }

    return $timeStamp;
}

/**
 * Get the app's possible URL base
 */
function getRawBaseUrl(): string
{
    // Get the Laravel app public path name
    $publicPathName = basename(rtrim(public_path(), '/'));

    // Get the HTTPS value
    $https = request()->server('HTTPS');
    $https = is_string($https) ? strtolower($https) : $https;
    $protocol = ($https !== 'off') ? 'https' : 'http';

    // Get the HTTP_HOST value
    $httpHost = trim(request()->server('HTTP_HOST'));
    $httpHost = rtrim($httpHost, '/');

    // Get the REQUEST_URI value
    $requestUri = trim(request()->server('REQUEST_URI'));
    $requestUri = strtok($requestUri, '?');
    $requestUri = trim($requestUri, '/');
    $requestUri = str_starts_with($requestUri, $publicPathName)
        ? '/'.$publicPathName
        : '';

    // Get the base URL of the current URL
    $baseUrl = $protocol.'://'.$httpHost.$requestUri;

    // Fixing the base URL from admin or install
    $baseUrl = head(explode('/'.urlGen()->adminUri(), $baseUrl));
    $baseUrl = head(explode('/install', $baseUrl));

    return rtrim($baseUrl, '/');
}

/**
 * Get the current request path by pattern
 */
function getRequestPath(?string $pattern = null): string
{
    $currentPath = request()->path();

    if (empty($pattern)) {
        return $currentPath;
    }

    $pattern = '#('.$pattern.')#ui';

    $matches = [];
    preg_match($pattern, $currentPath, $matches);

    return ! empty($matches[1]) ? $matches[1] : $currentPath;
}

/**
 * Generate a random string of a given length
 *
 * This function returns a random string of length $length,
 * consisting of either numeric, alphabetic, or alphanumeric
 * characters, depending on the $type parameter.
 *
 * @param  int  $length  The length of the resulting string
 * @param  string  $type  The character type: 'numeric', 'alpha', or 'alphanumeric'
 */
function generateRandomString(int $length = 6, string $type = 'alphanumeric'): string
{
    // Define character sets
    $numeric = '0123456789';
    $alpha = 'abcdefghijklmnopqrstuvwxyz';
    $alphanumeric = $numeric.$alpha;

    // Choose a character set based on type
    $characters = match ($type) {
        'alpha' => $alpha,
        'alphanumeric' => $alphanumeric,
        default => $numeric,
    };

    // Get the selected characters type length
    $charactersLength = strlen($characters);

    // Generate a random string of specified length
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        try {
            $out .= $characters[random_int(0, $charactersLength - 1)];
        } catch (Throwable $e) {
            $out .= $characters[rand(0, $charactersLength - 1)];
        }
    }

    return $out;
}

/**
 * Get random password
 */
function generateRandomPassword(int $length): string
{
    $allowedCharacters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!$%^&#!$%^&#';
    $random = str_shuffle($allowedCharacters);
    $password = substr($random, 0, $length);

    if (empty($password)) {
        $password = Str::random($length);
    }

    return $password;
}

/**
 * Get a unique code
 */
function generateUniqueCode(int $limit): string
{
    $uniqueCode = substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);

    if (empty($uniqueCode)) {
        $uniqueCode = Str::random($limit);
    }

    return $uniqueCode;
}

/**
 * Get locale without codeset|encoding
 * Examples: de_CH.UTF-8 => de_CH, en_GB.ISO8859-15 => en_GB, ...
 */
function removeLocaleCodeset(?string $locale, bool $nullable = true): string
{
    $default = 'en_US';
    $default = castToString(config('app.locale'), $default);
    $default = $nullable ? null : (! str_contains($default, '.') ? $default : 'en_US');

    if (empty($locale)) {
        return castToString($default);
    }

    if (str_contains($locale, '.')) {
        $tmp = explode('.', $locale);
        $locale = current($tmp);
    }

    return castToString($locale, $default);
}

/**
 * Get locale without country code
 * Examples: de_CH => de, en_GB => en, ...
 */
function getPrimaryLocaleCode(?string $locale, bool $nullable = true): ?string
{
    $default = $nullable ? null : 'en';

    $locale = removeLocaleCodeset($locale, $nullable);
    if (extension_loaded('intl') && class_exists('\Locale')) {
        return Locale::getPrimaryLanguage($locale);
    }
    if (isRegionalLocaleCode($locale)) {
        $locale = str($locale)->substr(0, -3)->toString();
    }

    return castToString($locale, $default);
}

/**
 * Get a language name from its ISO code
 * Examples: de => German, ro => Romanian, ...
 *
 * Note: Use the getRegionalLocaleName() function instead for more options
 */
function getPrimaryLocaleName(?string $code, bool $nullable = true): ?string
{
    $default = $nullable ? null : (! empty($code) ? $code : 'English');

    if (empty($code)) {
        return $default;
    }

    // Get language list
    $languages = getLanguageRefList();

    $code = getPrimaryLocaleCode($code, $nullable);
    $name = $languages[$code] ?? $default;

    return castToString($name, $default);
}

/**
 * Get a language regional locale from its ISO code (or from its locale)
 * Examples: de => de_DE, ro => ro_RO, ...
 */
function getRegionalLocaleCode(?string $locale, bool $nullable = true): ?string
{
    $default = $nullable ? null : (! empty($locale) ? $locale : 'en_US');

    if (empty($locale)) {
        return $default;
    }

    if (isRegionalLocaleCode($locale)) {
        return $locale;
    }

    // Get languages linked to their main country
    $isoLanguageCountries = getLanguagesLinkedToTheirMainCountry();

    if (! empty($isoLanguageCountries[$locale]['locale'])) {
        $locale = $isoLanguageCountries[$locale]['locale'];
    }

    return castToString($locale, $default);
}

/**
 * Get a language regional locale name from its ISO code (or from its locale)
 * Examples: de_CH => German (Switzerland), en_GB => English (United Kingdom), ...
 */
function getRegionalLocaleName(?string $locale, bool $nullable = true): ?string
{
    if (! isRegionalLocaleCode($locale)) {
        return getPrimaryLocaleName($locale, $nullable);
    }

    // Get localized locale
    $locale = getRegionalLocaleCode($locale, $nullable);

    // Get locales with name
    $localesWithName = getLocalesWithName('merged', false);

    $name = $localesWithName[$locale] ?? $locale;

    return castToString($name, $locale);
}

/**
 * Check if a language ISO code (or locale) is regional
 * Examples: de_CH => true, de => false, ...
 */
function isRegionalLocaleCode(?string $locale): bool
{
    if (empty($locale)) {
        return false;
    }

    if (extension_loaded('intl') && class_exists('\Locale')) {
        return ! empty(Locale::getRegion($locale));
    }
    $countryCodeList = array_keys(getCountryRefList());
    $tmp = explode('_', $locale);
    $countryCode = end($tmp);

    return in_array($countryCode, $countryCodeList);
}

if (! function_exists('getLangTag')) {
    /**
     * Get locale's language tag
     * Example: en-US, pt-BR, fr-CA, ... (Usage of "-" instead of "_")
     *
     * The language tag syntax is defined by the IETF's BCP 47
     * Info: https://www.w3.org/International/articles/language-tags/
     */
    function getLangTag(?string $locale = null, bool $nullable = true): ?string
    {
        $default = 'en-US';
        $default = castToString(config('app.locale'), $default);
        $default = $nullable ? null : (! str_contains($default, '_') ? $default : 'en');

        if (empty($locale)) {
            return $default;
        }

        $locale = str_replace('_', '-', $locale);
        $locale = castToString($locale, $default);

        return removeLocaleCodeset($locale, $nullable);
    }
}

if (! function_exists('getLangDirection')) {
    /**
     * Resolve the writing direction ('ltr' or 'rtl') for a locale.
     *
     * The HTTP middleware stack populates config('lang.direction') for the current
     * request, but queue workers rendering notifications never run that middleware —
     * Laravel only sets app()->setLocale($notifiable->preferredLocale()). Falling back
     * to the cached Language list keeps email rendering correct in that context.
     */
    function getLangDirection(?string $locale = null): string
    {
        if (empty($locale) || $locale === config('lang.code')) {
            $direction = config('lang.direction');
            if (! empty($direction)) {
                return $direction instanceof \App\Enums\LanguageDirection ? $direction->value : (string) $direction;
            }
        }

        if (empty($locale)) {
            $locale = app()->getLocale();
        }

        $code = removeLocaleCodeset($locale);
        $activeLanguages = \App\Models\Language::getActiveLanguagesArray();
        $direction = $activeLanguages[$code]['direction'] ?? null;

        if ($direction instanceof \App\Enums\LanguageDirection) {
            return $direction->value;
        }

        return is_string($direction) && $direction !== '' ? $direction : 'ltr';
    }
}

function linkStrLimit(string $url, string $string, int $length = 0, string $attributes = ''): string
{
    if (! is_string($attributes)) {
        $attributes = '';
    }

    if (! empty($attributes)) {
        $attributes = ' '.$attributes;
    }

    $tooltip = '';
    if (is_numeric($length) && $length > 0 && str($string)->length() > $length) {
        $tooltip = ' data-bs-toggle="tooltip" title="'.$string.'"';
    }

    $out = '<a href="'.$url.'"'.$attributes.$tooltip.'>';
    if ($length > 0) {
        $out .= str($string)->limit($length);
    } else {
        $out .= $string;
    }
    $out .= '</a>';

    return $out;
}

/**
 * Get Translation from Column (from Json, Array or String)
 */
function getColumnTranslation($column, ?string $locale = null): string
{
    if (empty($locale)) {
        $locale = app()->getLocale();
    }

    if (! is_array($column)) {
        if (JsonUtils::isJson($column)) {
            $column = json_decode($column, true);
        } else {
            $column = [$column];
        }
    }

    $fallbackLocale = config('app.fallback_locale');
    $translation = $column[$locale] ?? ($column[$fallbackLocale] ?? head($column));

    return castToString($translation);
}

/**
 * Convert a full path to a relative path
 * Old name: relativeAppPath
 */
function getRelativePath(?string $path): string
{
    $documentRoot = request()->server('DOCUMENT_ROOT');
    $path = str_replace($documentRoot, '', $path);

    $basePath = base_path();
    $path = str_replace($basePath, '', $path);

    return (! empty($path) && is_string($path)) ? $path : '/';
}

/**
 * Parse the HTTP Accept-Language header
 * NOTE: Get the preferred language: $firstKey = array_key_first($array);
 */
function parseAcceptLanguageHeader(?string $acceptLanguage = null): array
{
    if (empty($acceptLanguage)) {
        $acceptLanguage = request()->server('HTTP_ACCEPT_LANGUAGE');
    }

    $acceptLanguageTab = explode(',', $acceptLanguage);

    $array = [];
    if (! empty($acceptLanguageTab)) {
        foreach ($acceptLanguageTab as $key => $value) {
            $tmp = explode(';', $value);
            if (empty($tmp)) {
                continue;
            }

            if (isset($tmp[0]) && isset($tmp[1])) {
                $q = str_replace('q=', '', $tmp[1]);
                $array[$tmp[0]] = (float) $q;
            } else {
                $array[$tmp[0]] = 1;
            }
        }
    }
    arsort($array);

    return $array;
}

/**
 * During a cURL request (using the Laravel HTTP Client),
 * Should the request be retried?
 *
 * Note:
 * - The initial request encounters can be a ConnectionException, then the request can be retried.
 * - The request can also be retried, for GET request, when the exception error contains:
 *   "cURL error 28: Connection timed out after {x} milliseconds"
 * - Don't retry in the other cases
 * - More info: https://laravel.com/docs/master/http-client#retries
 */
function shouldHttpRequestBeRetried(Exception $e, PendingRequest $request, ?string $method = null): bool
{
    // cURL error found
    $msg = $e->getMessage();
    $isHttpGetRequest = (! empty($method) && strtolower($method) == 'get');
    $isTimeoutError = (str_contains($msg, 'cURL') && str_contains($msg, 'Connection'));
    $isTimeoutError = ($isTimeoutError && $isHttpGetRequest);

    // Connection exception encountered
    $isConnectionException = ($e instanceof ConnectionException);

    return $isConnectionException || $isTimeoutError;
}

/**
 * Parse and get error from HTTP client request's exception or response as string
 */
function parseHttpRequestError($exceptionOrResponse): string
{
    if (is_string($exceptionOrResponse)) {
        return $exceptionOrResponse;
    }

    $message = null;

    if (
        $exceptionOrResponse instanceof Throwable
        && method_exists($exceptionOrResponse, 'getMessage')
    ) {
        $message = $exceptionOrResponse->getMessage();
    }

    if ($exceptionOrResponse instanceof Illuminate\Http\Client\Response) {
        $responseErrorMessage = null;

        if (method_exists($exceptionOrResponse, 'reason')) {
            try {
                $responseErrorMessage = $exceptionOrResponse->reason();
            } catch (Throwable $e) {
            }
        }
        if (empty($responseErrorMessage)) {
            if (method_exists($exceptionOrResponse, 'json')) {
                try {
                    $responseErrorMessage = $exceptionOrResponse->json();
                } catch (Throwable $e) {
                }
            }
        }
        if (empty($responseErrorMessage)) {
            if (method_exists($exceptionOrResponse, 'body')) {
                try {
                    $responseErrorMessage = $exceptionOrResponse->body();
                } catch (Throwable $e) {
                }
            }
        }
        if (! empty($responseErrorMessage)) {
            $message = $responseErrorMessage;
        }
    }

    if (is_array($message)) {
        $message = json_encode($message);
    }
    if (is_string($message)) {
        $message = strip_tags($message);
    }
    if (empty($message) || ! is_string($message)) {
        $message = 'Failed to get the request\'s data.';
    }

    return $message;
}

function getHttpStatusCodes(): array
{
    $statusTexts = Response::$statusTexts;
    $statusTexts[419] = getHttp419ExceptionMessage();

    return $statusTexts;
}

function isValidHttpStatus(&$status): bool
{
    $requestedStatus = $status;

    if (empty($requestedStatus)) {
        return false;
    }

    $requestedStatus = castToInt($requestedStatus);
    $isValid = array_key_exists($requestedStatus, getHttpStatusCodes());

    if ($isValid) {
        $status = $requestedStatus;
    }

    return $isValid;
}

function getHttpStatusMessage($status): string
{
    $default = 'Unknown status text';

    if (! isValidHttpStatus($status)) {
        return $default;
    }

    $message = getHttpStatusCodes()[$status];

    return castToString($message, $default);
}

function getHttp419ExceptionMessage(?Request $request = null): string
{
    if (is_null($request)) {
        $request = request();
    }

    $message = (isApiRoute($request) || isFromAjax($request))
        ? trans('global.page_expired_reload_needed')
        : trans('global.page_expired');

    return castToString($message);
}

/**
 * Check if the current request is from an AJAX call
 */
function isFromAjax(?Request $request = null): bool
{
    if (! $request instanceof Request) {
        $request = request();
    }

    return $request->ajax() || $request->wantsJson();
}

function isCharsetConflictFound(?string $charset = null): bool
{
    if (empty($charset)) {
        $charset = config('larapen.core.charset', 'utf-8');
    }

    $systemCharset = @ini_get('default_charset');
    $systemCharset = is_string($systemCharset) ? $systemCharset : '';

    return strtolower($charset) != strtolower($systemCharset);
}

/**
 * Add Content-Type Header (Only if missing)
 */
function addContentTypeHeader(string $type, ?array $headers = []): array
{
    $headers = is_array($headers) ? $headers : [];

    $charset = config('larapen.core.charset', 'utf-8');
    $defaultHeaders = ['Content-Type' => $type.'; charset='.strtoupper($charset)];

    return array_merge($defaultHeaders, $headers);
}

function isFromValidReferrer(?array $referrers = [], bool $nullable = false): bool
{
    if (empty($referrers)) {
        $referrers = [getUrlHost(url('/'))];
    }

    $isFromValidReferrer = false;

    $httpReferrer = request()->server('HTTP_REFERER');
    if ($nullable && empty($httpReferrer)) {
        return true;
    }

    foreach ($referrers as $referrer) {
        $isPattern = (
            str_contains($referrer, 'https?')
            || str_contains($referrer, '.*')
            || str_contains($referrer, '\.')
        );

        // Check to see what the referrer is
        $isFromValidReferrer = $isPattern
            ? preg_match('|'.$referrer.'|ui', $httpReferrer)
            : str_contains($httpReferrer, $referrer);
        if ($isFromValidReferrer) {
            break;
        }
    }

    return $isFromValidReferrer;
}

/**
 * The front-end light/dark mode toggle is now driven by the active theme.
 * "Allow Dark Mode in Front" maps to the theme's `allow_mode_toggle` flag —
 * when truthy, visitors can switch modes; when false, the theme's
 * `default_mode` is fixed.
 */
function isSettingsAppDarkModeEnabled(): bool
{
    return (bool) setting('allow_mode_toggle', false);
}

/**
 * The legacy "Allow the user's OS appearance theme" option no longer ships
 * as an admin toggle (Phase-4 dropped it). Always returns false so any caller
 * still asking is short-circuited to the theme's chosen `default_mode`.
 */
function isSettingsAppSystemThemeEnabled(): bool
{
    return false;
}

/**
 * @param  null  $theme
 */
function getFormattedThemes($theme = null, bool $iconOnly = false): array
{
    $userThemes = ThemePreference::all(orderBy: false);
    $userThemes = collect($userThemes)
        ->map(function ($item) use ($iconOnly) {
            $themeIcons = [
                'light' => '<i class="bi bi-sun"></i>',
                'dark' => '<i class="bi bi-moon-stars"></i>', // bi bi-moon | bi bi-moon-stars
                'system' => '<i class="bi bi-circle-half"></i>', // bi bi-gear | bi bi-circle-half
            ];

            $icon = $themeIcons[$item['id'] ?? '-'] ?? $themeIcons['system'];
            $item['label'] = $iconOnly ? $icon : $icon.'&nbsp;'.$item['label'];

            return $item;
        })->reject(function ($item) {
            $key = $item['id'] ?? '-';

            return ! isSettingsAppSystemThemeEnabled() && $key == 'system';
        })->keyBy('id');

    if (! empty($theme)) {
        $obj = $userThemes->get($theme);

        return is_array($obj) ? $obj : [];
    }

    return $userThemes->toArray();
}

/**
 * Resolve the theme's `default_mode` setting (light/dark) — what the front
 * loads with when no user/cookie preference is in play. Anything outside the
 * known set degrades to 'light' so the body never gets stuck on an unknown
 * value.
 */
function settingsAppDefaultMode(): string
{
    $mode = (string) setting('default_mode', 'light');

    return in_array($mode, ['light', 'dark'], true) ? $mode : 'light';
}

/**
 * Get the resolved theme preference for the current request.
 *
 * Priority (when the toggle is enabled):
 *   account preference → cookie preference → theme's `default_mode`.
 *
 * When the toggle is disabled, the theme's `default_mode` is locked in —
 * users can't override it via cookie or per-account preference.
 */
function getThemePreference(): ?string
{
    $defaultMode = settingsAppDefaultMode();

    if (! isSettingsAppDarkModeEnabled()) {
        return $defaultMode;
    }

    $accountTheme = currentUserThemePreference();
    $cookieTheme = currentDeviceThemePreference();

    return $accountTheme ?? $cookieTheme ?? $defaultMode;
}

/**
 * Per-account theme override. Only honored when the dark-mode toggle is
 * enabled — otherwise the theme's `default_mode` wins.
 */
function currentUserThemePreference(): ?string
{
    if (isSettingsAppDarkModeEnabled()) {
        return auth()->user()?->theme_preference ?? null;
    }

    return null;
}

/**
 * Per-device theme override stored in a cookie. Same gate as the per-account
 * preference: only honored when the toggle is enabled.
 */
function currentDeviceThemePreference(): ?string
{
    if (isSettingsAppDarkModeEnabled()) {
        return Cookie::has('themePreference') ? Cookie::get('themePreference') : null;
    }

    return null;
}

/**
 * Flash message notification
 */
function notification(
    ?string $message = null,
    ?string $level = 'info',
    ?string $targetUrl = null,
    ?Request $request = null
): void {
    if (isApiRoute($request) || isFromAjax($request)) {
        return;
    }
    if (empty($message)) {
        return;
    }

    $level = ! empty($level) ? $level : 'info';

    /*
     * If the $targetUrl contains the "goTo=path/to/resource" parameter, retrieve it for the $goToUrl.
     */
    if (! empty($targetUrl)) {
        $query = [];
        parse_str(mb_parse_url($targetUrl, PHP_URL_QUERY), $query);
        $goToPath = $query['goTo'] ?? null;
    } else {
        $request = is_null($request) ? request() : $request;
        $goToPath = $request->input('goTo');
    }

    $goToUrl = null;
    if (! empty($goToPath)) {
        $goToPath = ltrim($goToPath, '/');
        $goToUrl = url($goToPath);
    }

    try {
        // Levels: info, success, error, warning
        flash($message)->$level();
    } catch (Throwable $e) {
    }
}

/**
 * Check if the class uses a specific trait (recursively or not)
 *
 * Note:
 * The PHP class_uses() only checks the immediate traits used by the class,
 * not those used by parent classes. Make it recursive can take
 * to account traits used by parent classes, we need to iterate through the class hierarchy.
 */
function doesClassUse($class, $trait, bool $recursively = false): bool
{
    if (is_string($class)) {
        $class = str($class)->start('\\')->toString();
    }

    if (! is_object($class) && (is_string($class) && ! class_exists($class))) {
        return false;
    }

    if ($recursively) {
        try {
            $reflectionClass = new ReflectionClass($class);
            while ($reflectionClass) {
                $traits = $reflectionClass->getTraitNames();
                if (in_array($trait, $traits)) {
                    return true;
                }

                $reflectionClass = $reflectionClass->getParentClass();
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    return in_array($trait, class_uses($class));
}

/**
 * Check if method exists in a class, and if it is a static method
 */
function staticMethodExists($class, string $method): bool
{
    if (is_string($class)) {
        $class = str($class)->start('\\')->toString();
    }

    if (! is_object($class) && (is_string($class) && ! class_exists($class))) {
        return false;
    }

    if (! method_exists($class, $method)) {
        return false;
    }

    try {
        $reflectionMethod = new ReflectionMethod($class, $method);

        return $reflectionMethod->isStatic();
    } catch (Throwable $e) {
    }

    return false;
}

/**
 * Check if Laravel class use the Macroable trait
 */
function isMacroable($class): bool
{
    $trait = Macroable::class;

    $usesMacroableTrait = doesClassUse($class, $trait);
    $macroFunctionExists = staticMethodExists($class, 'macro');
    $isMacroable = ($usesMacroableTrait && $macroFunctionExists);

    $macroableCanBeBypassed = false;
    if (class_exists($class)) {
        /*
         * For now, no way to verify if these Laravel classes uses the framework Macroable trait (recursively)
         * or if these Laravel classes have a macro() function
         */
        $bypassMacroableCheckFor = [
            '\Illuminate\Database\Eloquent\Builder',
        ];

        try {
            $reflectionClass = new ReflectionClass($class);
            $namespace = $reflectionClass->getNamespaceName();
            $className = $reflectionClass->getShortName();
            $classFullName = $namespace.'\\'.$className;
            $classFullName = str($classFullName)->start('\\')->toString();

            $macroableCanBeBypassed = in_array($classFullName, $bypassMacroableCheckFor);
        } catch (Throwable $e) {
        }
    }

    return $isMacroable || $macroableCanBeBypassed;
}

function getHtmlColor(?string $color): ?string
{
    if (empty($color)) {
        return $color;
    }

    $color = str($color);
    $color = isHexColor($color) ? $color->start('#') : $color->ltrim('#');

    return $color->toString();
}

/**
 * Handle service data
 * Convert JsonResponse to an associative array
 */
function getServiceData(?JsonResponse $data, bool $assoc = true): array
{
    if (! ($data instanceof JsonResponse)) {
        return [];
    }

    $data = $data->getData($assoc);

    return is_array($data) ? $data : [];
}

/**
 * Normalize filename
 * Note: The $originalName and $name are without file extension
 */
function normalizeFilename(string $originalName, ?string $name = null): string
{
    $filenameWithoutExtension = str_contains($originalName, '.')
        ? pathinfo($originalName, PATHINFO_FILENAME)
        : $originalName;

    $filenameWithoutExtension = str($filenameWithoutExtension)
        ->slug()
        ->take(100)
        ->trim('-')
        ->toString();

    $randomIntLength = 4;

    if (! empty($name)) {
        $filenameWithoutExtension = $name;
        $randomIntLength = 12;
    }

    $randomInt = generateRandomString(length: $randomIntLength, type: 'numeric');
    $filenameWithoutExtension = $filenameWithoutExtension.'-'.$randomInt;

    return str($filenameWithoutExtension)->trim('-')->toString();
}

/**
 * Add mask (***) to string by keep some characters in left or/and in right
 */
function addMaskToString(string $string, int $keepRgt = 0, int $keepLft = 0): string
{
    $charsToShowRgt = $keepRgt - str($string)->length();
    $charsToShowLft = str($string)->length() - $keepLft;

    return str($string)->mask('*', $charsToShowRgt, $charsToShowLft)->toString();
}

function getFlashNotificationData(): array
{
    if (! session()->has('flash_messages')) {
        return [];
    }

    /** @var Collection $flashCollection */
    $flashCollection = session('flash_messages');

    /** @var array $flashMessage */
    $flashMessage = $flashCollection[0] ?? [];

    // Level to method mapping
    $levelList = [
        'info' => 'info',
        'success' => 'success',
        'danger' => 'error',
        'warning' => 'warning',
    ];

    if (empty($flashMessage['message'])) {
        return [];
    }

    $level = $flashMessage['level'] ?? 'info';
    $method = array_key_exists($level, $levelList) ? $levelList[$level] : 'info';

    return [
        'message' => $flashMessage['message'],
        'method' => $method,
    ];
}

/**
 * Convert an HTML “array”-style field name (e.g. `user[address][city]`)
 * to Laravel’s dot-notation (`user.address.city`).
 *
 * Empty array suffixes such as `[]` are removed, so `tags[]`
 * becomes simply `tags`.
 *
 * @example arrayFieldToDot('user[address][city]');       // user.address.city
 * @example arrayFieldToDot('user[address][city]', true); // "user.*"
 * @example arrayFieldToDot('items[0][name]');            // items.0.name
 * @example arrayFieldToDot('items[0][name]', true);      // "items.*"
 * @example arrayFieldToDot('tags[]');                    // tags
 * @example arrayFieldToDot('tags[]', true);              // "tags.*"
 *
 * @param  string|null  $field  Raw field name from the form.
 * @param  bool  $rootWildcard  When true, return "<root>.*".
 */
function arrayFieldToDotNotation(?string $field, bool $rootWildcard = false): string
{
    if (empty($field)) {
        return '';
    }

    try {
        // Split on "[" or "]", drop empty segments, then join with dots
        $segments = preg_split('/[\[\]]+/', $field, -1, PREG_SPLIT_NO_EMPTY);
        $converted = implode('.', $segments);
    } catch (Throwable $e) {
        $converted = str_replace(['[]', '][', '[', ']'], ['', '.', '.', ''], $fieldName);
    }

    if ($rootWildcard) {
        // Convert to wildcard notation by keeping only the first part
        $parts = explode('.', $converted);
        $converted = $parts[0].'.*';
    }

    return is_string($converted) ? $converted : '';
}

/**
 * Build the list of Blade “slot helpers” to be pushed with @pushOnce().
 *
 * @param  string|null  $path  Relative path inside resources/ (defaults to views/helpers/forms/fields)
 * @param  bool  $snakeCase  If true, replace dashes with underscores.
 * @return array Unique, indexed list of helper names.
 */
function getViewHelpersNames(?string $path = null, bool $snakeCase = false): array
{
    // Define custom slots
    // Note: Some entries have been added to the list below only for reorder purpose.
    // e.g. select2, fileinput, daterangepicker_date, ..., intl_tel_input, ...
    $customSlots = [
        'jquery',
        'momentjs', // for daterangepicker-*
        'daterangepicker_date',
        'select2',
        'select2_basic',
        'fileinput',
        'fileinput_multiple_selections',
        'fileinput_preview_frame',
        'intl_tel_input',
        'shared_iti',
    ];

    // Initialize with custom slots mapped to _assets
    $helpers = array_map(fn ($slot) => "{$slot}_assets", $customSlots);

    // Set default path if not provided
    $path = $path ?? resource_path('views/helpers/forms/fields');

    // Get all files from path
    $files = File::allFiles($path);

    // Process files once for both _assets and _helper suffixes
    foreach ($files as $file) {
        $name = str_replace(['.blade.php', '.php'], '', $file->getRelativePathname());
        $name = $snakeCase ? str_replace('-', '_', $name) : $name;

        $_assets = "{$name}_assets";
        if (! in_array($_assets, $helpers)) {
            $helpers[] = $_assets;
        }

        $_helper = "{$name}_helper";
        if (! in_array($_helper, $helpers)) {
            $helpers[] = $_helper;
        }
    }

    // Return unique helper names
    return array_unique($helpers);
}

/**
 * @throws CustomException
 */
function validateCacheDriver(?string $driver = null, ?string $fallbackDriver = null): string
{
    $fallbackDriver = empty($fallbackDriver) ? 'file' : $fallbackDriver;

    if (empty($driver)) {
        return $fallbackDriver;
    }

    $cacheDrivers = (array) config('larapen.options.cache');
    if (! array_key_exists($driver, $cacheDrivers)) {
        return $fallbackDriver;
    }

    $rules = [
        'redis' => (extension_loaded('redis') && class_exists('\Redis')),
        'memcached' => (extension_loaded('memcached') && class_exists('\Memcached')),
        'apc' => (extension_loaded('apc') || extension_loaded('apcu')),
    ];

    $validatedDrivers = collect($cacheDrivers)
        ->map(function ($item, $key) use ($rules) {
            return ! array_key_exists($key, $rules) || $rules[$key];
        })->toArray();

    $isValid = $validatedDrivers[$driver] ?? false;

    if (! $isValid) {
        $message = "The server component/extension for the '<strong>$driver</strong>' cache driver is not installed or not enabled.";
        $message .= ' Please contact your system administrator.';
        $message .= ' If you prefer to switch to a different cache driver and remove the error, you can update your .env file by setting the CACHE_STORE value (e.g. <strong>CACHE_STORE=file</strong>).';
        throw new CustomException($message);
    }

    return $driver;
}

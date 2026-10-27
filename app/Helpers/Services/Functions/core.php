<?php

use App\Enums\BootstrapColor;
use App\Enums\CountryAdminType;
use App\Enums\PlanType;
use App\Helpers\Common\Arr;
use App\Helpers\Common\DBUtils;
use App\Helpers\Common\DotenvEditor;
use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Helpers\Services\Localization\Country as CountryHelper;
use App\Helpers\Services\Localization\Helpers\Country;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Front\Account\AccountBaseController;
use App\Http\Controllers\Web\Front\Listing\ReportController;
use App\Http\Controllers\Web\Front\Search\SearchController;
use App\Http\Controllers\Web\Front\SitemapController;
use App\Http\Controllers\Web\Front\SitemapsController;
use App\Http\Controllers\Installer\Steps\SiteInfoController;
use App\Models\Language;
use App\Models\MetaTag;
use App\Models\Page;
use App\Enums\PageSectionType;
use App\Models\PageSection;
use App\Models\User;
use App\Services\SettingService;
use Addons\Domainmapping\Domainmapping;
use Hashids\Hashids;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Intervention\Image\Laravel\Facades\Image as FacadeImage;
use Larapen\LaravelDistance\Helper;
use Larapen\TextToImage\Facades\TextToImage;
use Mews\Purifier\Facades\Purifier;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Get a setting value by flat key (e.g. 'app.app_name', 'mail.driver').
 *
 * Reads from the cached DB table via SettingService::get(). Falls back to
 * config/larapen/settings.php (when present), then to the caller's $default.
 *
 * Replaces direct config('settings.*') calls. The dotted key shape mirrors the
 * old config path with the 'settings.' prefix stripped — i.e. the legacy call
 * config('settings.app.app_name') is now setting('app_name').
 */
function setting(string $key, mixed $default = null): mixed
{
	return app(SettingService::class)->get($key, $default);
}

/**
 * Get View Content
 */
function getViewContent(string $view, array $data = [], array $mergeData = []): string
{
	return view($view, $data, $mergeData)->render();
}

/**
 * Get View Content If Exists
 */
function getViewContentIfExists(string $view, array $data = [], array $mergeData = []): string
{
	if (!view()->exists($view)) {
		return '';
	}

	return view($view, $data, $mergeData)->render();
}

/**
 * Add options' group's JavaScript Code
 * (for settings models in the Admin Panel)
 */
function addOptionsGroupJavaScript(
	string $settingNamespace,
	string $groupClassName,
	array  $fields,
	array  $data = []
): array
{
	// Normalize addons namespaces
	$namespaceBase = 'App';
	if (
		str_starts_with($settingNamespace, 'extensions\addons\\')
		&& str_contains($settingNamespace, strtolower($namespaceBase) . '\\')
	) {
		$settingNamespace = str($settingNamespace)
			->after(strtolower($namespaceBase))
			->prepend($namespaceBase)
			->toString();
		$groupClassName = str($groupClassName)
			->after(strtolower($namespaceBase))
			->prepend($namespaceBase)
			->toString();
	}

	$parts = explode('\\', $settingNamespace);
	$modelName = $parts[2] ?? '';
	$belongsTo = $parts[3] ?? '';

	$modelJsDirName = str($modelName)->kebab()->toString();
	$belongsToJsDirName = !empty($belongsTo) ? '.' . $belongsTo : '';
	$optionsGroupKey = str($groupClassName)->classBasename()->remove($modelName)->kebab()->toString();

	$view = 'admin.js.' . $modelJsDirName . $belongsToJsDirName . '.' . $optionsGroupKey;

	$js = getViewContentIfExists($view, $data);

	return array_merge($fields, [
		[
			'name'  => 'javascript',
			'type'  => 'custom_html',
			'value' => $js,
		],
	]);
}

/**
 * Configuring Eloquent Strictness
 */
function preventLazyLoadingForModelRelations(bool $isEnabled = true): void
{
	/*
	 * Configuring Eloquent Strictness
	 * - Disable lazy loading (completely) to increase performance optimization
	 * - Prevent silently discarding attributes
	 * WARNING: Never apply that on production to prevent exception errors.
	 */
	if (!appIsBeingInstalledOrUpgraded()) {
		$isEnabled = (
			$isEnabled
			&& !app()->isProduction()
			&& config('larapen.core.performance.preventLazyLoading')
			&& request()->segment(1) != 'feed'
		);
		Model::preventLazyLoading($isEnabled);
	}
}

/**
 * Get all countries from the PHP array (umpirsky)
 */
function getCountriesFromArray(bool $raw = false): ?array
{
	$countries = new Country;
	$countries = $countries->all();

	if (empty($countries)) {
		return null;
	}

	$arr = [];
	foreach ($countries as $code => $value) {
		if (!$raw) {
			$filePath = storage_path('database/geonames/countries/' . strtolower($code) . '.sql');
			if (!file_exists($filePath)) {
				continue;
			}
		}
		$row = ['value' => $code, 'text' => $value];
		$arr[] = $row;
	}

	return $arr;
}

/**
 * Get all countries from DB (Geonames) & Translate them
 */
function getCountries(bool $includeNonActive = false): array
{
	$arr = [];

	// Get installed countries list
	$countries = CountryHelper::getCountries($includeNonActive);

	if ($countries->count() > 0) {
		foreach ($countries as $code => $country) {
			// The country entry must be a Laravel Collection object
			if (!$country instanceof Collection) {
				$country = collect($country);
			}

			// Get the country data
			$code = $country->has('code') ? $country->get('code') : $code;
			$name = $country->has('name') ? $country->get('name') : '';
			$arr[$code] = $name;
		}
	}

	return $arr;
}

function getCountriesCodes(): array
{
	// Get the countries from the umpirsky database
	$countries = getCountriesFromArray(raw: true);
	$umpirskyCodes = collect($countries)->keyBy('value')->keys();

	// Get countries from the app's Geonames SQL files
	$filesCodes = collect();
	$filesDirPath = storage_path('database/geonames/countries/');
	if (is_dir($filesDirPath)) {
		$files = array_filter(glob($filesDirPath . '*.sql'), 'is_file');
		$filesCodes = collect($files)
			->map(function ($item) {
				$pathParts = pathinfo($item);
				$code = $pathParts['filename'] ?? null;

				return !empty($code) ? strtoupper($code) : null;
			})->flip()->keys();
	}

	// All countries codes
	$codes = $umpirskyCodes->merge($filesCodes)->unique();

	return $codes->toArray();
}

function getCountryCodeRoutePattern(): string
{
	// Country Code Pattern
	$countriesCodes = collect(getCountriesCodes())->map(fn ($item) => strtolower($item));
	$countryCodePattern = $countriesCodes->isNotEmpty() ? $countriesCodes->join('|') : null;
	$countryCodePattern = !empty($countryCodePattern) ? $countryCodePattern : 'us';

	/*
	 * NOTE:
	 * '(?i:foo)' : Make 'foo' case-insensitive
	 */

	return '(?i:' . $countryCodePattern . ')';
}

function doesCountriesPageCanBeHomepage(): bool
{
	return
		file_exists(storage_path('framework/addons/domainmapping'))
		&& (config('larapen.core.dmCountriesListAsHomepage') == true)
		&& (getHost() == getHost(config('app.url')));
}

function doesCountriesPageCanBeLinkedToTheHomepage(): bool
{
	return
		file_exists(storage_path('framework/addons/domainmapping'))
		&& (config('larapen.core.dmCountriesListAsHomepage') == true)
		&& (getHost() != getHost(config('app.url')));
}

/**
 * Get URL (based on Country Domain) related to the given country (or country code)
 * This is the url() function to match country domains
 */
function dmUrl(Collection|string|null $country, ?string $path = '/', bool $forceCountry = false, bool $forceLocale = false): string
{
	if (empty($path)) {
		$path = '/';
	}

	$country = getValidCountry($country);
	if (empty($country)) {
		return castToString(url($path));
	}

	// Clear the path
	$path = ltrim($path, '/');

	// Get the country's main language code
	$langCode = getCountryMainLangCode($country);

	// Get the country's main language path
	$langPath = '';
	if ($forceLocale) {
		if (!empty($langCode)) {
			$parseUrl = mb_parse_url(url($path));
			if (!isset($parseUrl['path']) || ($parseUrl['path'] == '/')) {
				$langPath = '/locale/' . $langCode;
			}
			if (isFromUrlAlwaysContainingCountryCode($path)) {
				$langPath = '/' . $langCode;
			}
		}
	}

	$countryCode = $country->get('code') ?? '';

	// Get the country domain data from the Domain Mapping addon,
	// And get a new URL related to domain, country language & given path
	$domain = collect((array)config('domains'))->firstWhere('country_code', $countryCode);
	if (!empty($domain['url'])) {
		$path = preg_replace('#' . $countryCode . '/#ui', '', $path, 1);

		$url = rtrim($domain['url'], '/') . $langPath;
		$url = $url . (!empty($path) ? '/' . $path : '');
	} else {
		$url = rtrim(config('app.url', ''), '/') . $langPath;
		$url = $url . (!empty($path) ? '/' . $path : '');
		if ($forceCountry) {
			$url = $url . ('?country=' . $countryCode);
		}
	}

	return $url;
}

/**
 * Get Valid Country's Object (as Laravel Collection)
 */
function getValidCountry(Collection|string|null $country): ?Collection
{
	// If the given country value is a string & having 2 characters (like country code),
	// Get the country collection by the country code.
	if (is_string($country)) {
		if (strlen($country) == 2) {
			$country = CountryHelper::getCountryInfo($country);
			if ($country->isEmpty() || !$country->has('code')) {
				return null;
			}
		} else {
			return null;
		}
	}

	// Country collection is required to continue
	if (!($country instanceof Collection)) {
		return null;
	}

	// Country collection code is required to continue
	if (!$country->has('code')) {
		return null;
	}

	return $country;
}

/**
 * Get Country Main Language Code
 */
function getCountryMainLangCode(Collection|string|null $country): ?string
{
	$country = getValidCountry($country);
	if (empty($country)) {
		return null;
	}

	// Get the country's main language code
	$langCode = null;
	if ($country->has('lang')) {
		$countryLang = $country->get('lang');
		if ($countryLang instanceof Collection && $countryLang->has('code')) {
			$langCode = $countryLang->get('code');
		}
	} else {
		if ($country->has('languages')) {
			$countryLang = CountryHelper::getLangFromCountry($country->get('languages'));
			if ($countryLang->has('code')) {
				$langCode = $countryLang->get('code');
			}
		} else {
			// From XML Sitemaps
			if ($country->has('locale')) {
				$langCode = $country->get('locale');
			}
		}
	}

	return $langCode;
}

/**
 * If the Domain Mapping addon is installed, apply its configs.
 * NOTE: Don't apply them if the session is shared.
 */
function applyDomainMappingConfig($countryCode): void
{
	if (empty($countryCode)) {
		return;
	}

	if (addon_active('domainmapping')) {
		/*
		 * When the session is shared, the domain name and logo columns are disabled.
		 * The dashboard per-country feature is also disabled.
		 * So, it is recommended to access to the Admin panel through the main URL from the /.env file (i.e.: APP_URL/admin)
		 */
		if (!setting('domainmapping_share_session')) {
			$domain = collect((array)config('domains'))->firstWhere('country_code', $countryCode);
			if (!empty($domain)) {
				if (!empty($domain['url'])) {
					// \URL::forceRootUrl($domain['url']);
				}
			}
		}
	}
}

if (!function_exists('app_installed')) {
	/**
	 * Return true when the app has been fully installed (storage/installed marker exists).
	 *
	 * This is a plain file-existence check; no DB, no service container, so it is safe
	 * to call anywhere: service providers, middleware, view composers, helpers.
	 * Use it to guard every block of code that would otherwise query the database
	 * before installation is complete.
	 */
	function app_installed(): bool
	{
		return file_exists((string)config('installer.installed_file', storage_path('installed')));
	}
}

if (!function_exists('is_install_route')) {
	/**
	 * Check whether the current request is already on an installer route.
	 *
	 * Uses getPathInfo() (which strips the base URL / subdirectory prefix) so
	 * that locale-prefixed URLs like /fr/install are NOT treated as installer
	 * routes; only the canonical /install path matches.
	 */
	function is_install_route(?Request $request = null): bool
	{
		$request = $request ?? request();

		return (bool)preg_match('#^/install(/|$)#', $request->getPathInfo());
	}
}

if (!function_exists('is_upgrade_route')) {
	/**
	 * Check whether the current request is on the legacy-upgrade bridge.
	 *
	 * Same getPathInfo() approach as `is_install_route()` so that locale
	 * prefixes (e.g. /fr/upgrade) don't accidentally match — only the
	 * canonical /upgrade path triggers, which matches what the installer
	 * route group registers.
	 */
	function is_upgrade_route(?Request $request = null): bool
	{
		$request = $request ?? request();

		return (bool)preg_match('#^/upgrade(/|$)#', $request->getPathInfo());
	}
}

function isAppInstallOrUpgradeRoute(): bool
{
	return isAppInstallRoute() || isAppUpgradeRoute();
}

function isAppInstallRoute(): bool
{
	return
		routeActionHas(getClassNamespaceName(SiteInfoController::class))
		&& request()->segment(1) == 'install';
}

function isAppUpgradeRoute(): bool
{
	// Legacy upgrade route is gone (Web/Setup/Update was deleted in Phase 7).
	return false;
}

/**
 * Check if the current route/URL is under the Admin panel area
 * NOTE: Please see the provider of the package: lab404/laravel-impersonate
 */
function isAdminPanelRoute(?string $url = null): bool
{
	if (empty($url)) {
		$isValid = (
			request()->segment(1) == urlGen()->adminUri()
			|| request()->segment(1) == 'impersonate'
			|| routeActionHas(getClassNamespaceName(DashboardController::class))
		);
	} else {
		try {
			$urlPath = str(parse_url($url, PHP_URL_PATH))->start('/')->toString();
			$adminUri = str(urlGen()->adminUri())->start('/')->toString();

			$isValid = (
				str_starts_with($urlPath, $adminUri)
				|| str_starts_with($urlPath, '/impersonate')
			);
		} catch (Throwable $e) {
			$isValid = false;
		}
	}

	return $isValid;
}

function isAuthRoute(): bool
{
	return request()->is(urlGen()->getAuthBasePath() . '/*');
}

/**
 * @deprecated
 */
function isFromInstallOrUpgradeProcess(): bool
{
	return isAppInstallOrUpgradeRoute();
}

/**
 * @deprecated
 */
function isFromInstallProcess(): bool
{
	return isAppInstallRoute();
}

/**
 * @deprecated
 */
function isFromUpgradeProcess(): bool
{
	return isAppUpgradeRoute();
}

/**
 * @deprecated
 */
function isAdminPanel($url = null): bool
{
	return isAdminPanelRoute($url);
}

/**
 * @deprecated
 */
function isFromAdminPanel($url = null): bool
{
	return isAdminPanelRoute($url);
}

/**
 * @deprecated
 */
function isFromAuthPage(): bool
{
	return isAuthRoute();
}

/**
 * Check dev environment
 */
function isDevEnv(?string $url = null): bool
{
	if (empty($url)) {
		$url = config('app.url');
	}

	$domain = getDomain($url);

	return
		str_contains($domain, 'bedigit.local')
		|| str_contains($domain, 'laraclassifier.local');
}

/**
 * Check the demo environment
 */
function isDemoEnv(?string $url = null): bool
{
	if (empty($url)) {
		$url = config('app.url');
	}

	return
		getDomain($url) == config('larapen.core.demo.domain')
		|| in_array(getHost($url), (array)config('larapen.core.demo.hosts'));
}

/**
 * Check the demo website domain
 */
function isDemoDomain(?string $url = null): bool
{
	$isDemoDomain = isDemoEnv($url);

	if (!$isDemoDomain) {
		return false;
	}

	$guard = getAuthGuard();
	$authUser = auth($guard)->check() ? auth($guard)->user() : null;

	if (!empty($authUser)) {
		if (doesUserHaveStaffPermission($authUser) && isDemoAdmin($authUser)) {
			$isDemoDomain = false;
		}
	}

	return $isDemoDomain;
}

/**
 * Check if the filled email is one demo reserved email
 */
function isDemoEmailAddress(?string $email): bool
{
	if (empty($email)) {
		return false;
	}

	return in_array($email, getDemoEmailAddresses());
}

function isDemoAdmin($authUser): bool
{
	if (empty($authUser)) {
		return false;
	}
	if (empty($authUser->email)) {
		return false;
	}

	return md5($authUser->email) == '5208f2db43b64766fe77fd0047dd9a22';
}

/**
 * Get demo skin color's image
 */
function getDemoSkinColorImage($skin): ?string
{
	$skinsArray = getCachedReferrerList('skins');
	if (!isset($skinsArray[$skin])) {
		return null;
	}

	$skinInfo = $skinsArray[$skin];

	$filename = $skin . '.png';
	$filePath = public_path('vendor/demo/preview/images/icons/' . $filename);
	$fileUrl = '/vendor/demo/preview/images/icons/' . $filename;

	if (!file_exists($filePath)) {
		try {

			// Create a new empty image resource with a red background
			$image = FacadeImage::create(70, 35)->fill($skinInfo['color']);

			// Save the file in png format
			$image->save($filePath);

		} catch (Throwable $e) {
			return null;
		}
	}

	return $fileUrl;
}

function getDemoFilesBasePath(?string $path = null): string
{
	$appSlug = config('larapen.core.item.slug');
	$appSlug = !empty($appSlug) ? $appSlug . '/' : '';
	$path = !empty($path) ? $path . '/' : '';

	return __DIR__ . '/../../../../../dataFactory/' . $appSlug . $path;
}

/**
 * Get the Country Code from URI Path
 */
function getCountryCodeFromPath(): ?string
{
	$countryCode = null;

	// With these URLs, the language code and the country code can be available in the segments
	// (If the "Multi-countries URLs" is enabled)
	if (isFromUrlThatCanContainCountryCode()) {
		$countryCode = request()->segment(1);
	}

	// With these URLs, the language code and the country code are available in the segments
	if (isFromUrlAlwaysContainingCountryCode()) {
		$countryCode = request()->segment(2);
	}

	return $countryCode;
}

/**
 * Check if the user is coming from a URL that can contain the country code
 * With these URLs, the language code and the country code can be available in the segments
 * (If the "Multi-countries URLs" is enabled)
 */
function isFromUrlThatCanContainCountryCode(): bool
{
	if (isMultiCountriesUrlsEnabled()) {
		if (
			routeActionHas(getClassNamespaceName(SearchController::class))
			|| routeActionHas(SitemapController::class)
		) {
			return true;
		}
	}

	return false;
}

/**
 * Check if the called page can always have the country code
 * With these URLs, the language code and the country code are available in the segments
 */
function isFromUrlAlwaysContainingCountryCode(?string $url = null): bool
{
	if (empty($url)) {
		$isValid = (
			str_ends_with(request()->url(), '.xml')
			|| routeActionHas(SitemapsController::class)
		);
	} else {
		$isValid = (str_ends_with($url, '.xml'));
	}

	return $isValid;
}

/**
 * Is 'utf8mb4' set as the database Charset
 * and 'utf8mb4_unicode_ci' is set as the database collation?
 */
function isUtf8mb4Available(): bool
{
	// Get the default charset & collation
	$defaultConnection = config('database.default');
	$databaseCharset = config("database.connections.{$defaultConnection}.charset");
	$databaseCollation = config("database.connections.{$defaultConnection}.collation");

	// Get the 4-Byte charset & collations
	$configDbEncodingKey = 'larapen.core.database.encoding';
	$fourBytesCharset = config("{$configDbEncodingKey}.default.charset", 'utf8mb4');
	$fourBytesCollations = config("{$configDbEncodingKey}.recommended.{$fourBytesCharset}");
	$fourBytesCollations = $fourBytesCollations ?? ['utf8mb4_unicode_ci'];

	// Allow Emojis when the database charset is 'utf8mb4'
	// and the database collation is 'utf8mb4_unicode_ci' or 'utf8mb4_0900_ai_ci'
	if ($databaseCharset == $fourBytesCharset && in_array($databaseCollation, $fourBytesCollations)) {
		return true;
	}

	return false;
}

/**
 * Check if UTF-8 4(+)-byte characters are enabled
 */
function isUtf8mb4Enabled(): bool
{
	return isUtf8mb4Available() && setting('listing_form_utf8mb4_enabled') == '1';
}

/**
 * Check if emojis characters are enabled
 */
function isEmojisEnabled(): bool
{
	return isUtf8mb4Enabled() && setting('listing_form_allow_emojis') == '1';
}

/**
 * Check if the front-end WYSIWYG editor is enabled.
 *
 * Front pages (listing form, etc.) read from `wysiwyg_front_editor`; admin
 * pages have their own selector (`wysiwyg_admin_editor`). 'none' disables
 * the editor and falls back to a plain textarea, in which case input HTML
 * is stripped to keep stored content consistent.
 */
function isWysiwygEnabled(): bool
{
	return setting('wysiwyg_front_editor', 'tinymce7') !== 'none';
}

/**
 * HTML Purifier cleaner
 *
 * More Info:
 * http://htmlpurifier.org/
 */
function htmlPurifierCleaner(?string $string): string
{
	if (empty($string)) {
		return '';
	}

	if (isWysiwygEnabled()) {
		try {
			$string = Purifier::clean($string);
		} catch (Throwable $e) {
		}
		$string = stripUtf8mb4CharsIfNotEnabled($string);
	} else {
		$string = multiLinesStringCleaner($string);

		if (request()->isMethod('get')) {
			$string = nl2br($string);
		}
	}

	if (request()->isMethod('get')) {
		$string = urlsToLinks($string);
	}

	$string = (isApiRoute() && !doesRequestIsFromWebClient())
		? singleLineStringCleaner($string)
		: $string;

	return castToString($string);
}

/**
 * Remove 4(+)-byte characters (If it is not enabled)
 */
function stripUtf8mb4CharsIfNotEnabled(?string $string): string
{
	if (empty($string)) {
		return '';
	}

	if (!isUtf8mb4Enabled()) {
		$string = stripUtf8mb4Chars($string);
	} else {
		if (!isEmojisEnabled()) {
			$string = stripEmojis($string);
		}
	}

	return castToString($string);
}

/**
 * Tags Cleaner
 */
function tagCleaner($value, bool $asArray = false): array|string|null
{
	$limit = (int)setting('listing_form_tags_limit', 15);

	return taggable($value, $limit, $asArray);
}

/**
 * Return an array of spoken languages in the selected country
 */
function getCountrySpokenLanguages(): array
{
	$supportedLanguages = getSupportedLanguages();

	$spokenLanguages = config('country.languages');
	$spokenLanguages = explode(',', $spokenLanguages);
	if (setting('localization_show_country_spoken_languages') == 'active_with_en') {
		$spokenLanguages[] = 'en';
	}
	if (setting('localization_show_country_spoken_languages') == 'active_with_main') {
		$spokenLanguages[] = strtolower(config('appLang.code'));
	}

	if (empty($spokenLanguages)) {
		return [];
	}

	return collect($spokenLanguages)
		->unique()
		->map(function ($item) use ($supportedLanguages) {
			if (empty($supportedLanguages)) {
				return $item;
			}

			foreach ($supportedLanguages as $code => $lang) {
				if (str_starts_with($code, $item)) {
					$item = $lang;
					break; // Important
				}
			}

			return $item;
		})
		->filter(fn ($item) => is_array($item))
		->keyBy('code')
		->toArray();
}

/**
 * Return an array of all supported languages
 */
function getSupportedLanguages(): array
{
	// Get supported languages from a database
	try {
		// Get all DB Languages
		$cacheParams = [
			'action'  => 'get.languages',
			'active'  => true,
			'orderBy' => 'position',
		];
		$supportedLanguages = caching()->remember(Language::class, $cacheParams, function () {
			return Language::where('active', 1)->orderBy('position')->get();
		});

		$supportedLanguages = collect($supportedLanguages->toArray());

		if ($supportedLanguages->isNotEmpty()) {
			$supportedLanguages = $supportedLanguages->keyBy('code');
		}
	} catch (Throwable $e) {
		/*
		 * Database or tables don't exist.
		 * The script will display an error or will start the installation.
		 * Please don't change anything here.
		 */
		$supportedLanguages = collect();
	}

	return $supportedLanguages->toArray();
}

/**
 * Check if language code is available
 */
function isAvailableLang(?string $code): bool
{
	$cacheParams = [
		'action' => 'get.language',
		'code'   => $code,
	];

	$lang = caching()->remember(Language::class, $cacheParams, function () use ($code) {
		return Language::where('code', '=', $code)->first();
	});

	return !empty($lang);
}

function detectLocale(): string
{
	$lang = detectLanguage();

	$defaultLocale = 'en_US';
	$locale = !$lang->isEmpty() ? $lang->get('locale') : $defaultLocale;

	return castToString($locale, $defaultLocale);
}

function detectLanguage(): Collection
{
	$obj = new App\Helpers\Services\Localization\Language;

	return $obj->find();
}

/**
 * Pluralization
 */
function getPlural($number): float|int
{
	return numberPlural($number, config('lang.russian_pluralization'));
}

/**
 * Get URL of Page by page's type
 */
function getUrlPageByType(?string $type, ?string $locale = null): string
{
	if (is_null($locale)) {
		$locale = config('app.locale');
	}

	$cacheParams = [
		'action' => 'get.page',
		'type'   => $type,
		'locale' => $locale,
	];

	$page = caching()->remember(Page::class, $cacheParams, function () use ($type, $locale) {
		$page = Page::type($type)->first();

		if (!empty($page)) {
			$page->setLocale($locale);
		}

		return $page;
	});

	$linkTarget = '';
	$linkRel = '';
	if (!empty($page)) {
		if (!empty($page->target)) {
			$linkTarget = ' target="' . $page->target . '"';
		}
		if (!empty($page->external_link)) {
			$linkRel = ' rel="nofollow"';
			$url = $page->external_link;
		} else {
			$url = urlGen()->page($page);
		}
	} else {
		$url = '#';
	}
	$linkClass = ' class="' . linkClass() . '"';

	// Get attributes
	return 'href="' . $url . '"' . $linkClass . $linkRel . $linkTarget;
}

function getRecommendedFileFormats(): array
{
	$defaultFileFormats = ['pdf', 'doc', 'docx', 'rtf', 'rtx', 'ppt', 'pptx', 'odt', 'odp', 'wps'];
	$imageInstalledFormats = getServerInstalledImageFormats();

	return array_merge($defaultFileFormats, $imageInstalledFormats);
}

function getAllowedFileFormats(): array
{
	$recommendedFormats = getRecommendedFileFormats();

	$formatList = setting('upload_file_types');
	$formatList = normalizeSeparatedList($formatList);

	$formats = explode(',', $formatList);
	$formats = array_filter($formats, fn ($item) => $item !== '');

	return !empty($formats) ? $formats : $recommendedFormats;
}

function getAllowedFileFormatsHint(?string $typeGroup = 'file'): string
{
	$formats = ($typeGroup == 'image')
		? getServerAllowedImageFormats()
		: getAllowedFileFormats();

	return collect($formats)->join(', ', trans('global._and_'));
}

/**
 * Normalize a string-separated list by replacing unwanted separators and joining elements.
 */
function normalizeSeparatedList(array|string|null $value, ?string $separator = ',', array|string $charsToGuard = ''): string
{
	if (empty($value)) {
		return '';
	}

	$separator = $separator ?: ',';
	$badSeparators = [trans('global._and_'), trans('global._or_'), '|', '-', ';', '.', '/', '_', ' '];

	if (is_string($charsToGuard)) {
		$charsToGuard = explode(',', $charsToGuard);
	}

	if (is_string($value)) {
		$badSeparators = array_diff($badSeparators, $charsToGuard);
		$value = str_replace($badSeparators, $separator, $value);
		$value = explode($separator, $value);
	}

	$value = array_filter($value, fn ($item) => is_string($item) && $item !== '');

	return collect($value)->join($separator);
}

/**
 * Get Public File's URL
 */
function fileUrl(?string $filePath): string
{
	// Storage Disk Init.
	$disk = StorageDisk::getDisk();

	try {
		$url = $disk->url($filePath);
	} catch (Throwable $e) {
		$url = url('common/file?path=' . $filePath);
	}

	return castToString($url);
}

/**
 * Get Private File's URL
 */
function privateFileUrl(?string $filePath, ?string $diskName = 'private'): string
{
	$queryString = 'path=' . $filePath;

	// For JC
	if (str_starts_with($filePath, 'resumes/')) {
		$diskName = 'private';
	}

	if (!empty($diskName)) {
		$queryString = 'disk=' . $diskName . '&' . $queryString;
	}

	$url = url('common/file?' . $queryString);

	return castToString($url);
}

function generateImageHtml(?string $srcFallback, ?string $alt = '', ?string $srcWebP = '', array $attributes = []): string
{
	$srcFallback = strval($srcFallback);
	$alt = strval($alt);
	$srcWebP = strval($srcWebP);

	// Initialize the attributes string
	$attributesString = '';

	// Loop through the attributes array and build the attributes string
	foreach ($attributes as $key => $value) {
		$attributesString .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars($value));
	}

	// Check if a WebP source is provided
	if (!empty($srcWebP)) {
		// Return the HTML code for a <picture> element with WebP support
		return sprintf(
			'<picture>
                <source srcset="%s" type="image/webp">
                <img src="%s" alt="%s"%s>
            </picture>',
			htmlspecialchars($srcWebP),
			htmlspecialchars($srcFallback),
			htmlspecialchars($alt),
			$attributesString
		);
	} else {
		// Return the conventional <img> tag
		return sprintf(
			'<img src="%s" alt="%s"%s>',
			htmlspecialchars($srcFallback),
			htmlspecialchars($alt),
			$attributesString
		);
	}
}

/**
 * Get versioned static files
 */
function mixStaticFile(string $url): string
{
	$param = [];
	if (config('larapen.core.fileVersioned')) {
		$fileVersion = config('larapen.core.fileVersion') ?? time();
		$param = ['v' => $fileVersion];
	}

	return urlBuilder($url)->setParameters($param)->toString();
}

/**
 * Replace global variables patterns from string
 */
function replaceGlobalPatterns(?string $string, bool $removeUnmatchedPatterns = true): string
{
	$string = str_replace('{app.name}', config('app.name'), $string);
	$string = str_replace('{country.name}', config('country.name'), $string);
	$string = str_replace('{country}', config('country.name'), $string);

	if (setting('app_slogan')) {
		$string = str_replace('{app.slogan}', setting('app_slogan'), $string);
	}

	if (str_contains($string, '{count.listings}')) {
		try {
			$countListings = Listing::query()->inCountry()->has('country')->unarchived()->count();
		} catch (Throwable $e) {
			$countListings = 0;
		}
		$string = str_replace('{count.listings}', $countListings, $string);
	}
	if (str_contains($string, '{count.users}')) {
		try {
			$countUsers = User::query()->count();
		} catch (Throwable $e) {
			$countUsers = 0;
		}
		$string = str_replace('{count.users}', $countUsers, $string);
	}

	if ($removeUnmatchedPatterns) {
		$string = removeUnmatchedPatterns($string);
	}

	return castToString($string);
}

/**
 * Get meta tag from settings
 */
function getMetaTag(?string $page): array
{
	$metaTag = ['title' => '', 'description' => '', 'keywords' => ''];

	// Check if the Domain Mapping addon is available
	if (addon_active('domainmapping')) {
		$domainMappingClass = Domainmapping::class;
		if (class_exists($domainMappingClass)) {
			$metaTag = $domainMappingClass::getMetaTag($page);
			if (!empty($metaTag) && !isArrayOfEmptyElements($metaTag)) {
				return $metaTag;
			}
		}
	}

	// Get the current Language
	// $languageCode = config('lang.code', config('app.locale'));
	$languageCode = config('app.locale', config('lang.code'));

	// Get the Page's MetaTag
	$model = null;
	try {
		$cacheParams = [
			'action' => 'get.metaTag',
			'page'   => $page,
			'locale' => $languageCode,
		];

		$model = caching()->remember(MetaTag::class, $cacheParams, function () use ($languageCode, $page) {
			$model = MetaTag::where('page', $page)->first(['title', 'description', 'keywords']);

			if (!empty($model)) {
				$model->setLocale($languageCode);
				$model = $model->toArray();
			}

			return $model;
		});
	} catch (Throwable $e) {
	}

	if (!empty($model)) {
		$metaTag = $model;

		$metaTag['title'] = getColumnTranslation($metaTag['title'], $languageCode);
		$metaTag['description'] = getColumnTranslation($metaTag['description'], $languageCode);
		$metaTag['keywords'] = getColumnTranslation($metaTag['keywords'], $languageCode);

		$metaTag['title'] = replaceGlobalPatterns($metaTag['title'], false);
		$metaTag['description'] = replaceGlobalPatterns($metaTag['description'], false);
		$metaTag['keywords'] = mb_strtolower(replaceGlobalPatterns($metaTag['keywords'], false));

		$metaTag = normalizeMetaTagValues($metaTag);

		return array_values($metaTag);
	}

	$pagesThatHaveTheirOwnDefaultMetaTags = [
		'search',
		'searchCategory',
		'searchLocation',
		'searchProfile',
		'searchTag',
		'listingDetails',
		'staticPage',
	];

	if (!in_array($page, $pagesThatHaveTheirOwnDefaultMetaTags)) {
		if (setting('app_slogan')) {
			$metaTag['title'] = config('app.name') . ' - ' . setting('app_slogan');
		} else {
			$metaTag['title'] = config('app.name') . ' - ' . config('country.name');
		}
		$metaTag['description'] = $metaTag['title'];
	}

	if (!is_array($metaTag)) {
		$metaTag = [];
	}
	$metaTag['title'] = $metaTag['title'] ?? null;
	$metaTag['description'] = $metaTag['description'] ?? null;
	$metaTag['keywords'] = $metaTag['keywords'] ?? null;

	$metaTag = normalizeMetaTagValues($metaTag);

	return array_values($metaTag);
}

function normalizeMetaTagValues(array $tags): array
{
	return collect($tags)
		->map(fn ($item) => singleLineStringCleanerStrict($item))
		->toArray();
}

/**
 * Get the Distance Calculation Unit
 */
function getDistanceUnit(?string $countryCode = null): string
{
	if (empty($countryCode)) {
		$countryCode = config('country.code');
	}
	$unit = Helper::getDistanceUnit($countryCode);
	$unit = trans('global.' . $unit);

	return castToString($unit);
}

/**
 * Get Front Skin
 */
function getFrontSkin(?string $skin = null): ?string
{
	$savedSkin = setting('style_skin', 'default');

	if (!empty($skin)) {
		$skinsArray = getCachedReferrerList('skins');
		if (!array_key_exists($skin, $skinsArray)) {
			$skin = $savedSkin;
		}
	} else {
		$skin = $savedSkin;
	}

	return castToStringOrNull($skin);
}

/**
 * Hashids is a small PHP library to generate YouTube-like ids from numbers.
 * Use it when you don't want to expose your database numeric ids to users
 *
 * @return array|mixed|string|null
 */
function hashId($in, bool $toNum = false, bool $withPrefix = true, int $minHashLength = 11, string $salt = '')
{
	if (!setting('seo_listing_hashed_id_enabled') && !isHashedId($in)) {
		return $in;
	}

	$hidPrefix = $withPrefix ? config('larapen.core.hashableIdPrefix') : '';
	$hidPrefix = is_string($hidPrefix) ? $hidPrefix : '';

	$hashIds = new Hashids($salt, $minHashLength);

	if (!$toNum) {
		$out = $hidPrefix . $hashIds->encode($in);
	} else {
		$in = ltrim($in, $hidPrefix);
		$out = $hashIds->decode($in);
		if (isset($out[0])) {
			$out = $out[0];
		}
	}

	return !empty($out) ? $out : null;
}

function isHashedId($in, int $minHashLength = 11): bool
{
	$hidPrefix = config('larapen.core.hashableIdPrefix');
	$hidPrefixLength = is_string($hidPrefix) ? strlen($hidPrefix) : 0;

	return
		preg_match('/[a-z0-9A-Z]+/', $in)
		&& (strlen($in) == ($minHashLength + $hidPrefixLength));
}

/**
 * Get routes prefixes to ban to match listing route's path
 */
function regexSimilarRoutesPrefixes(): array
{
	$routes = (array)config('routes');
	if (empty($routes)) {
		return [];
	}

	$prefixes = [];
	foreach ($routes as $route) {
		if (!isStringable($route)) {
			continue;
		}

		$prefix = head(explode('/', (string)$route));
		if (!str_starts_with($prefix, '{')) {
			$prefixes[] = $prefix;
		}
	}

	return array_unique($prefixes);
}

/**
 * Check if the user browser is the given value.
 * The given value can be:
 * 'Firefox', 'Chrome', 'Safari', 'Opera', 'MSIE', 'Trident', 'Edge'
 *
 * Usage: doesUserBrowserIs('Chrome') or doesUserBrowserIs() == 'Chrome'
 */
function doesUserBrowserIs(?string $browserName = null): bool|string
{
	$userAgent = request()->server('HTTP_USER_AGENT');
	$browsers = [
		'Edge'              => 'Edg', // Prioritize Edge detection
		'Opera'             => 'OPR', // Prioritize Opera detection
		'Brave'             => 'Brave', // Prioritize Brave detection
		'Vivaldi'           => 'Vivaldi', // Prioritize Vivaldi detection
		'Samsung Internet'  => 'SamsungBrowser', // Prioritize Samsung Internet detection
		'Chrome'            => 'Chrome', // Chrome must come after Edge, Opera, Brave, and Vivaldi
		'Safari'            => 'Safari', // Safari must come after Chrome
		'Firefox'           => 'Firefox', // Firefox can be checked after Chrome and Safari
		'Internet Explorer' => ['MSIE', 'Trident/7'], // Check IE last
	];

	foreach ($browsers as $name => $keywords) {
		$keywords = (array)$keywords; // Ensure the keyword is an array
		foreach ($keywords as $keyword) {
			if (str_contains($userAgent, $keyword)) {
				$detectedBrowser = $name;

				return $browserName
					? strcasecmp($detectedBrowser, $browserName) === 0
					: $detectedBrowser;
			}
		}
	}

	return 'Unknown';
}

/**
 * Get sitemaps indexes
 */
function getSitemapsIndexes(bool $htmlFormat = false): string
{
	$out = '';

	$countries = Country::transAll(CountryHelper::getCountries());
	if (!$countries->isEmpty()) {
		if ($htmlFormat) {
			$cmFieldStyle = ($countries->count() > 10) ? ' style="height: 205px; overflow-y: scroll;"' : '';
			$out .= '<ul' . $cmFieldStyle . '>';
		}
		foreach ($countries as $country) {
			if (!$country instanceof Collection) {
				continue;
			}

			$country = CountryHelper::getCountryInfo($country->get('code'));
			if ($country->isEmpty()) {
				continue;
			}

			/*
			// Get the Country's Language Code
			$countryLang = $country->has('lang') ? $country->get('lang') : collect();
			$countryLang = ($countryLang instanceof Collection)? $countryLang : collect();
			$countryLangCode = $countryLang->has('code') ? $countryLang->get('code') : config('app.locale');
			*/

			// Add the Sitemap Index
			if ($htmlFormat) {
				$out .= '<li>' . dmUrl($country, $country->get('icode') . '/sitemaps.xml') . '</li>';
			} else {
				$out .= 'Sitemap: ' . dmUrl($country, $country->get('icode') . '/sitemaps.xml') . "\n";
			}
		}
		if ($htmlFormat) {
			$out .= '</ul>';
		}
	}

	return $out;
}

/**
 * Default robots.txt content
 *
 * Admins can tighten this from
 * /admin/settings/seo (the "Robots.txt file content" field).
 */
function getDefaultRobotsTxtContent(): string
{
	$out = 'User-agent: *' . "\n";
	$out .= 'Allow: /' . "\n";
	$out .= "\n";
	$out .= 'User-agent: *' . "\n";
	$out .= 'Disallow: /' . urlGen()->adminUri() . '/' . "\n";
	$out .= 'Disallow: /assets/' . "\n";
	$out .= 'Disallow: /css/' . "\n";
	$out .= 'Disallow: /js/' . "\n";
	$out .= 'Disallow: /vendor/' . "\n";
	$out .= 'Disallow: /main.php' . "\n";
	$out .= 'Disallow: /index.php' . "\n";
	$out .= 'Disallow: /mix-manifest.json' . "\n";
	$out .= 'Disallow: /*?display=*' . "\n"; // Listings list display mode

	$languages = getSupportedLanguages();
	if (!empty($languages)) {
		foreach ($languages as $code => $lang) {
			$out .= 'Disallow: /locale/' . $code . "\n";
		}
	}

	$providers = ['facebook', 'linkedin', 'twitter', 'google'];
	foreach ($providers as $provider) {
		$out .= 'Disallow: /auth/connect/' . $provider . "\n";
	}

	return $out;
}

function getCreateListingLinkInfo(): array
{
	$authUser = auth()->check() ? auth()->user() : null;

	$linkUrl = urlGen()->addListing();
	$linkAttr = '';

	if (setting('listing_form_mandatory_plan_selection') == '1') {
		if (!empty($authUser)) {
			/*
			 * If the user doesn't have any valid subscription,
			 * Force the user to select a package (on the pricing page) to allow him to create a new listing
			 *
			 * IMPORTANT:
			 * To avoid excessive memory consumption that could degrade the application performance,
			 * checking the limitation of the number of listings linked to the users' subscription
			 * will be done downstream (when trying to publish new listings).
			 */
			$authUser->loadMissing('payment');
			if (empty($authUser->payment)) {
				$linkUrl = urlGen()->pricing();
			}
		} else {
			// Force the guest to select a package (on the pricing page) to allow him to create a new listing
			$linkUrl = urlGen()->pricing();
		}
	}

	// Can create listings?
	if (!doesGuestHaveAbilityToCreateListings($authUser)) {
		$linkUrl = '#quickLogin';
		$linkAttr = ' data-bs-toggle="modal" data-bs-target="#quickLogin"';
	}

	return [$linkUrl, $linkAttr];
}

/**
 * Can create listings?
 */
function doesGuestHaveAbilityToCreateListings($authUser = null): bool
{
	if (empty($authUser)) {
		try {
			$guard = getAuthGuard();
			$authUser = auth($guard)->check() ? auth($guard)->user() : null;
		} catch (Throwable $e) {
		}
	}

	return !empty($authUser) || guestCanSubmitListings();
}

/**
 * Generate the Email Form button
 *
 * @param null $listing
 */
function genEmailContactBtn($listing = null, bool $btnBlock = false, bool $iconOnly = false): string
{
	$listing = is_array($listing) ? Arr::toObject($listing) : $listing;

	$out = '';

	if (!isVerifiedListing($listing)) {
		return $out;
	}

	$smsNotificationCanBeSent = (
		isPhoneAsAuthFieldEnabled()
		&& setting('sms_messenger_notifications') == '1'
		&& $listing->auth_field === \App\Enums\AuthField::Phone
		&& !empty($listing->phone)
	);
	if (empty($listing->email) && !$smsNotificationCanBeSent) {
		if ($iconOnly) {
			$out = '<i class="fa-regular fa-envelope" style="color: #dadada"></i>';
		}

		return $out;
	}

	$btnLink = '#contactUser';
	$btnClass = '';
	if (!auth()->check()) {
		if (setting('listing_page_guest_can_contact_authors') != '1') {
			$btnLink = '#quickLogin';
		}
	}

	if ($iconOnly) {
		$out .= '<a href="' . $btnLink . '" data-bs-toggle="modal" class="' . linkClass() . '">';
		$out .= '<i class="fa-regular fa-envelope" data-bs-toggle="tooltip" title="' . trans('global.Send a message') . '"></i>';
	} else {
		if ($btnBlock) {
			$btnClass = $btnClass . ' btn-block';
		}

		$out .= '<a href="' . $btnLink . '" data-bs-toggle="modal" class="btn btn-secondary' . $btnClass . '">';
		$out .= '<i class="fa-regular fa-envelope"></i> ';
		$out .= trans('global.Send a message');
	}
	$out .= '</a>';

	return $out;
}

/**
 * Generate the Phone Number button
 */
function genPhoneNumberBtn($listing, bool $btnBlock = false): string
{
	$listing = is_array($listing) ? Arr::toObject($listing) : $listing;

	// Options
	$isWhatsappBtnEnabled = (setting('listing_page_enable_whatsapp_btn') == '1');
	$isPreFilledWhatsappMessageEnabled = (setting('listing_page_pre_filled_whatsapp_message') == '1');
	$hidePhoneNumberOption = setting('listing_page_hide_phone_number');
	$isHiddenPhoneNumberEnabled = in_array($hidePhoneNumberOption, ['1', '2', '3']);
	$isPhoneNumberToImgEnabled = (setting('listing_page_convert_phone_number_to_img') == '1');
	$isSecurityTipsEnabled = (setting('listing_page_show_security_tips') == '1');
	$doesGuestCanContactAuthors = (setting('listing_page_guest_can_contact_authors') == '1');

	$out = '';

	if (empty($listing->phone_intl) || $listing->phone_hidden == 1) {
		return $out;
	}

	$dataListingId = ' data-listing-id="' . $listing->id . '"';

	$whatsAppPreFilledMessage = $isPreFilledWhatsappMessageEnabled
		? '?text=' . rawurlencode(trans('global.whatsapp_pre_filled_message', [
			'url'     => urlGen()->listing($listing),
			'title'   => $listing->title,
			'appName' => config('app.name'),
		])) : '';
	$whatsAppLink = 'https://wa.me/' . keepOnlyNumericChars($listing->phone) . $whatsAppPreFilledMessage;
	$waBtnClass = '';

	$btnLink = 'tel:' . $listing->phone;
	$btnAttr = '';
	$btnClass = ' phoneBlock'; /* for the showPhone() JS function */
	$btnHint = trans('global.Click to see');
	$phone = $listing->phone_intl;

	if ($isHiddenPhoneNumberEnabled) {
		$phoneToHide = normalizePhoneNumber($phone);
		if ($hidePhoneNumberOption == '1') {
			$phone = str($phoneToHide)->mask('X', -str($phoneToHide)->length(), str($phoneToHide)->length() - 3)->toString();
		}
		if ($hidePhoneNumberOption == '2') {
			$phone = str($phoneToHide)->mask('X', 3)->toString();
		}
		if ($hidePhoneNumberOption == '3') {
			$phone = str($phoneToHide)->mask('X', 0)->toString();
		}
		$btnLink = '';
		$btnAttrTooltip = 'data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . $btnHint . '"';
		$btnClassTooltip = '';

		$btnAttr = $btnAttrTooltip;
		$btnClass = $btnClass . $btnClassTooltip;

		$isWhatsappBtnEnabled = false;
	} else {
		$btnClass = '';
		if ($isPhoneNumberToImgEnabled) {
			try {
				$phone = TextToImage::make($phone, config('larapen.core.textToImage'));
			} catch (Throwable $e) {
				$phone = $listing->phone;
			}
		}
	}

	if ($isSecurityTipsEnabled) {
		/*
			Set multiple data-bs-toggle for link in Bootstrap
			Tooltip + modal in button - Bootstrap

			Usage of '[rel="tooltip"]' as selector instead of '[data-bs-toggle="tooltip"]' for the tooltip,
			and trigger that with on hover event from JS
		*/
		$btnAttrTooltip = 'rel="tooltip" data-bs-placement="bottom" title="' . $btnHint . '"';
		$btnClassTooltip = '';
		$btnAttrModal = 'data-bs-toggle="modal"';

		$btnLink = '#securityTips';
		$btnAttr = $btnAttrModal . ' ' . $btnAttrTooltip;
		$btnClass = ' phoneBlock'; /* for the showPhone() JS function */
		if (!$isHiddenPhoneNumberEnabled) {
			$phone = trans('global.phone_number');
		}
		$btnClass = $btnClass . ' ' . $btnClassTooltip;
	}

	if (!auth()->check()) {
		if (!$doesGuestCanContactAuthors) {
			$btnAttrModal = 'data-bs-toggle="modal"';

			$phone = $btnHint;
			$btnLink = '#quickLogin';
			$btnAttr = $btnAttrModal;
			$btnClass = '';

			$isWhatsappBtnEnabled = false;
		}
	}

	if ($btnBlock) {
		$waBtnClass = $waBtnClass . ' btn-block';
		$btnClass = $btnClass . ' btn-block';
	}

	// Generate the Phone Number button
	$class = 'btn btn-warning' . $btnClass;
	$out .= '<a href="' . $btnLink . '"' . $dataListingId . ' ' . $btnAttr . ' class="' . $class . '">';
	$out .= '<i class="fa-solid fa-mobile-screen-button"></i> ';
	$out .= $phone;
	$out .= '</a>';

	if ($isWhatsappBtnEnabled) {
		$waBtnAttr = 'data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . trans('global.chat_on_whatsapp') . '"';

		// Generate the WhatsApp button
		$class = 'btn btn-success' . $waBtnClass;
		$out .= '<a href="' . $whatsAppLink . '"' . $dataListingId . ' ' . $waBtnAttr . ' target="_blank" class="' . $class . '">';
		$out .= '<i class="fa-brands fa-whatsapp"></i> ';
		$out .= 'WhatsApp';
		$out .= '</a>';
	}

	return $out;
}

/**
 * Set the Backup config vars
 */
function setBackupConfig(?string $typeOfBackup = null): void
{
	// Get the current version value
	$version = preg_replace('/[^\d+]/', '', config('larapen.version.app'));

	// All backup filename prefix
	config()->set('backup.backup.destination.filename_prefix', 'site-v' . $version . '-');

	// Database backup
	if ($typeOfBackup == 'database') {
		config()->set('backup.backup.admin_flags', [
			'--disable-notifications' => true,
			'--only-db'               => true,
		]);
		config()->set('backup.backup.destination.filename_prefix', 'database-v' . $version . '-');
	}

	// Languages' files backup
	if ($typeOfBackup == 'languages') {
		$include = [
			lang_path(),
		];
		$addonsDirs = glob(base_path('extensions/addons') . '/*', GLOB_ONLYDIR);
		if (!empty($addonsDirs)) {
			foreach ($addonsDirs as $addonDir) {
				$addonLangFolder = $addonDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang';
				if (file_exists($addonLangFolder)) {
					$include[] = $addonLangFolder;
				}
			}
		}

		config()->set('backup.backup.admin_flags', [
			'--disable-notifications' => true,
			'--only-files'            => true,
		]);
		config()->set('backup.backup.source.files.include', $include);
		config()->set('backup.backup.source.files.exclude', [
			// ...
		]);
		config()->set('backup.backup.destination.filename_prefix', 'languages-');
	}

	// Generated files backup
	if ($typeOfBackup == 'files') {
		config()->set('backup.backup.admin_flags', [
			'--disable-notifications' => true,
			'--only-files'            => true,
		]);
		config()->set('backup.backup.source.files.include', [
			base_path('.env'),
			storage_path('app/public'),
			storage_path('installed'),
		]);
		config()->set('backup.backup.source.files.exclude', [
			// ...
		]);
		config()->set('backup.backup.destination.filename_prefix', 'files-');
	}

	// App files backup
	if ($typeOfBackup == 'app') {
		config()->set('backup.backup.admin_flags', [
			'--disable-notifications' => true,
			'--only-files'            => true,
		]);
		config()->set('backup.backup.source.files.include', [
			base_path(),
			// base_path('.gitattributes'),
			base_path('.gitignore'),
		]);
		config()->set('backup.backup.source.files.exclude', [
			base_path('node_modules'),
			base_path('.git'),
			base_path('.idea'),
			base_path('.env'),
			base_path('bootstrap/cache') . DIRECTORY_SEPARATOR . '*',
			public_path('robots.txt'),
			storage_path('app/backup-temp'),
			storage_path('app/database'),
			storage_path('app/public/app/categories/custom') . DIRECTORY_SEPARATOR . '*',
			storage_path('app/public/app/ico') . DIRECTORY_SEPARATOR . '*',
			storage_path('app/public/app/logo') . DIRECTORY_SEPARATOR . '*',
			storage_path('app/public/app/page') . DIRECTORY_SEPARATOR . '*',
			storage_path('app/public/files') . DIRECTORY_SEPARATOR . '*',
			storage_path('app/public/temporary') . DIRECTORY_SEPARATOR . '*',
			storage_path('app/purifier') . DIRECTORY_SEPARATOR . '*',
			storage_path('database/demo'),
			storage_path('backups'),
			storage_path('dotenv-editor') . DIRECTORY_SEPARATOR . '*',
			storage_path('framework/cache') . DIRECTORY_SEPARATOR . '*',
			storage_path('framework/sessions') . DIRECTORY_SEPARATOR . '*',
			storage_path('framework/testing') . DIRECTORY_SEPARATOR . '*',
			storage_path('framework/views') . DIRECTORY_SEPARATOR . '*',
			storage_path('installed'),
			storage_path('laravel-backups'),
			storage_path('logs') . DIRECTORY_SEPARATOR . '*',
		]);
		config()->set('backup.backup.destination.filename_prefix', 'app-v' . $version . '-');
	}
}

/**
 * Check if User is online
 *
 * @throws InvalidArgumentException
 */
function isUserOnline($user): bool
{
	$user = (is_array($user)) ? Arr::toObject($user) : $user;

	$isOnline = false;

	if (!empty($user) && isset($user->id)) {
		if (setting('optimization_cache_driver') == 'array') {
			$isOnline = $user->p_is_online;
		} else {
			$isOnline = cache()->store('file')->has('user-is-online-' . $user->id);
		}
	}

	// Allow only logged users to get the other users status
	$guard = getAuthGuard();

	return auth($guard)->check() ? $isOnline : false;
}

/**
 * @return Repository|Application|mixed
 */
function dynamicRoute(string $key)
{
	return config($key);
}

/**
 * Set the Db Fallback Locale
 */
function setDbFallbackLocale(?string $locale): void
{
	try {
		DotenvEditor::setKey('FALLBACK_LOCALE_FOR_DB', $locale);
		DotenvEditor::save();
	} catch (Throwable $e) {
	}
}

/**
 * Remove the Db Fallback Locale
 */
function removeDbFallbackLocale(): void
{
	try {
		DotenvEditor::setKey('FALLBACK_LOCALE_FOR_DB', 'null');
		DotenvEditor::save();
	} catch (Throwable $e) {
	}
}

function addMissingTranslations($locale): void
{
	$masterLocale = config('appLang.code');
	if (empty($locale) || empty($masterLocale)) {
		return;
	}

	if ($locale == $masterLocale) {
		return;
	}

	// Backfill missing per-locale text on the homepage search-form section.
	// The new schema keeps title / sub_title as `title_<locale>` / `sub_title_<locale>`
	// keys inside `page_sections.data`, so we copy from the master locale into
	// the just-added one when the row carries the master version but not yet
	// the new one.
	$section = PageSection::query()->where('type', PageSectionType::SearchForm->value)->first();
	if (!empty($section)) {
		$data = (array)($section->data ?? []);

		$masterKey = 'title_' . $masterLocale;
		$localeKey = 'title_' . $locale;
		if (isset($data[$masterKey])) {
			$data[$localeKey] = $data[$masterKey];
		}

		$masterKey = 'sub_title_' . $masterLocale;
		$localeKey = 'sub_title_' . $locale;
		if (isset($data[$masterKey])) {
			$data[$localeKey] = $data[$masterKey];
		}

		$section->data = $data;

		if ($section->isDirty()) {
			$section->saveQuietly();
		}
	}

	// Update the translatable tables columns
	$modelClasses = DBUtils::getAppModelClasses(translatable: true);
	if (empty($modelClasses)) {
		return;
	}

	foreach ($modelClasses as $modelClass) {
		$model = new $modelClass;

		// Get the translatable columns
		$columns = method_exists($model, 'getTranslatableAttributes')
			? $model->getTranslatableAttributes()
			: [];
		if (empty($columns)) {
			continue;
		}

		$modelCollection = $modelClass::query()->withoutGlobalScopes();
		if ($modelCollection->doesntExist()) {
			continue;
		}

		foreach ($modelCollection->cursor() as $item) {
			foreach ($columns as $column) {
				$value = $item->getTranslations($column);

				if (isset($value[$masterLocale])) {
					$value[$locale] = $value[$masterLocale];

					$item->setTranslations($column, $value)
						->saveQuietly();
				}
			}
		}
	}
}

/**
 * SEO Website Verification using meta tags
 * Allow full HTML tag or content="" value
 */
function seoSiteVerification(): string
{
	$engines = [
		'google' => [
			'name'    => 'google-site-verification',
			'content' => setting('seo_google_site_verification'),
		],
		'bing'   => [
			'name'    => 'msvalidate.01',
			'content' => setting('seo_msvalidate'),
		],
		'yandex' => [
			'name'    => 'yandex-verification',
			'content' => setting('seo_yandex_verification'),
		],
		'alexa'  => [
			'name'    => 'alexaVerifyID',
			'content' => setting('seo_alexa_verify_id'),
		],
	];

	$out = '';
	foreach ($engines as $engine) {
		if (isset($engine['name'], $engine['content']) && $engine['content']) {
			if (preg_match('|<meta[^>]+>|i', $engine['content'])) {
				$out .= $engine['content'] . "\n";
			} else {
				$out .= '<meta name="' . $engine['name'] . '" content="' . $engine['content'] . '" />' . "\n";
			}
		}
	}

	return $out;
}

function relativeAppPath(?string $path): ?string
{
	if (isDemoDomain()) {
		return getRelativePath($path);
	}

	return $path;
}

function getFilterClearBtn(?string $url): ?string
{
	$out = '';
	if (!empty($url)) {
		$out .= '<a href="' . $url . '" class="' . linkClass() . '" title="' . trans('global.Remove this filter') . '">';
		$out .= '<i class="bi bi-x-lg"></i>';
		$out .= '</a>';
	}

	return $out;
}

function isOldSocialAuthEnabled(?string $socialNetwork = null, array $overrides = []): bool
{
	// Optional flat-keyed `social_auth_*` overrides — used by admin "test" UX.
	$read = function (string $innerKey) use ($overrides) {
		$flat = 'social_auth_' . $innerKey;
		if (array_key_exists($flat, $overrides)) {
			return $overrides[$flat];
		}
		if (array_key_exists($innerKey, $overrides)) {
			return $overrides[$innerKey];
		}

		return setting($flat);
	};

	$isFacebookOauthEnabled = ($read('facebook_client_id') && $read('facebook_client_secret'));
	$isLinkedInOauthEnabled = ($read('linkedin_client_id') && $read('linkedin_client_secret'));
	$isTwitterOauth2Enabled = ($read('twitter_oauth_2_client_id') && $read('twitter_oauth_2_client_secret'));
	$isTwitterOauth1Enabled = ($read('twitter_client_id') && $read('twitter_client_secret'));
	$isGoogleOauthEnabled = ($read('google_client_id') && $read('google_client_secret'));

	$isSocialAuthEnabled = (
		$read('social_login_activation')
		&& (
			$isFacebookOauthEnabled
			|| $isLinkedInOauthEnabled
			|| $isTwitterOauth2Enabled
			|| $isTwitterOauth1Enabled
			|| $isGoogleOauthEnabled
		)
	);

	$socialNetworkList = [
		'facebook'      => $isFacebookOauthEnabled,
		'linkedin'      => $isLinkedInOauthEnabled,
		'twitterOauth2' => $isTwitterOauth2Enabled,
		'twitterOauth1' => $isTwitterOauth1Enabled,
		'google'        => $isGoogleOauthEnabled,
	];

	if (!empty($socialNetwork)) {
		return array_key_exists($socialNetwork, $socialNetworkList) && $socialNetworkList[$socialNetwork];
	}

	return $isSocialAuthEnabled;
}

function isSocialSharesEnabled(?string $socialNetwork = null, array $overrides = []): bool
{
	// Optional flat-keyed `social_share_*` overrides — used by admin "test" UX.
	$read = function (string $innerKey) use ($overrides) {
		$flat = 'social_share_' . $innerKey;
		if (array_key_exists($flat, $overrides)) {
			return $overrides[$flat];
		}
		if (array_key_exists($innerKey, $overrides)) {
			return $overrides[$innerKey];
		}

		return setting($flat);
	};

	$isFacebookEnabled = ($read('facebook'));
	$isTwitterEnabled = ($read('twitter'));
	$isLinkedInEnabled = ($read('linkedin'));
	$isWhatsappEnabled = ($read('whatsapp'));
	$isTelegramEnabled = ($read('telegram'));
	$isSnapchatEnabled = ($read('snapchat'));
	$isMessengerEnabled = ($read('messenger') && $read('facebook_app_id'));
	$isPinterestEnabled = ($read('pinterest'));
	$isVkEnabled = ($read('vk'));
	$isTumblrEnabled = ($read('tumblr'));

	$isSocialSharesEnabled = (
		$isFacebookEnabled
		|| $isTwitterEnabled
		|| $isLinkedInEnabled
		|| $isWhatsappEnabled
		|| $isTelegramEnabled
		|| $isSnapchatEnabled
		|| $isMessengerEnabled
		|| $isPinterestEnabled
		|| $isVkEnabled
		|| $isTumblrEnabled
	);

	$socialNetworkList = [
		'facebook'  => $isFacebookEnabled,
		'twitter'   => $isTwitterEnabled,
		'linkedin'  => $isLinkedInEnabled,
		'whatsapp'  => $isWhatsappEnabled,
		'telegram'  => $isTelegramEnabled,
		'snapchat'  => $isSnapchatEnabled,
		'messenger' => $isMessengerEnabled,
		'pinterest' => $isPinterestEnabled,
		'vk'        => $isVkEnabled,
		'tumblr'    => $isTumblrEnabled,
	];

	if (!empty($socialNetwork)) {
		return array_key_exists($socialNetwork, $socialNetworkList) && $socialNetworkList[$socialNetwork];
	}

	return $isSocialSharesEnabled;
}

/**
 * Get Form Border Radius CSS
 */
function getFormBorderRadiusCSS($formBorderRadius, $fieldsBorderRadius): string
{
	$searchFormOptions['form_border_radius'] = $formBorderRadius . 'px';
	$searchFormOptions['fields_border_radius'] = $fieldsBorderRadius . 'px';

	// Use CSS logical-corner properties so the same rules apply in LTR and RTL.
	// The output is cached to `public/cache/css/front-homepage.css` and served
	// to every language, so the rules can't be branched on the build-time
	// `lang.direction`. The first column (`:first-child`) is always the
	// inline-START column in flex flow — its outer corners are the inline-start
	// corners (left in LTR, right in RTL). The button column is always the
	// inline-END one — its outer corners are the inline-end corners.
	$out = "\n";
	$out .= '#homepage .search-row .search-col:first-child > div {' . "\n";
	$out .= 'border-start-start-radius: ' . $searchFormOptions['form_border_radius'] . ' !important;' . "\n";
	$out .= 'border-end-start-radius: ' . $searchFormOptions['form_border_radius'] . ' !important;' . "\n";
	$out .= '}' . "\n";
	$out .= '#homepage .search-row .search-col:first-child .form-control {' . "\n";
	$out .= 'border-start-start-radius: ' . $searchFormOptions['fields_border_radius'] . ' !important;' . "\n";
	$out .= 'border-end-start-radius: ' . $searchFormOptions['fields_border_radius'] . ' !important;' . "\n";
	$out .= '}' . "\n";
	$out .= '#homepage .search-row .search-col button {' . "\n";
	$out .= 'border-start-end-radius: ' . $searchFormOptions['form_border_radius'] . ' !important;' . "\n";
	$out .= 'border-end-end-radius: ' . $searchFormOptions['form_border_radius'] . ' !important;' . "\n";
	$out .= '}' . "\n";
	$out .= '#homepage .search-row .search-col .btn {' . "\n";
	$out .= 'border-start-end-radius: ' . $searchFormOptions['fields_border_radius'] . ' !important;' . "\n";
	$out .= 'border-end-end-radius: ' . $searchFormOptions['fields_border_radius'] . ' !important;' . "\n";
	$out .= '}' . "\n";

	$out .= '@media (max-width: 767px) {' . "\n";
	$out .= '#homepage .search-row .search-col:first-child > div,' . "\n";
	$out .= '#homepage .search-row .search-col:first-child .form-control,' . "\n";
	$out .= '#homepage .search-row .search-col > div,' . "\n";
	$out .= '#homepage .search-row .search-col .form-control,' . "\n";
	$out .= '#homepage .search-row .search-col button,' . "\n";
	$out .= '#homepage .search-row .search-col .btn {' . "\n";
	$out .= 'border-radius: ' . $searchFormOptions['form_border_radius'] . ' !important;' . "\n";
	$out .= '}' . "\n";
	$out .= '}' . "\n";

	return $out;
}

/**
 * Get the user's possible subscription features
 *
 * @return int|int[]|null
 */
function getUserSubscriptionFeatures($user, ?string $feature = null): array|int|null
{
	$array = [
		'listingsLimit'  => null,
		'picturesLimit'  => null,
		'expirationTime' => null,
	];

	if (empty($user)) {
		return empty($feature) ? $array : ($array[$feature] ?? null);
	}

	/*
	 * With the 120 seconds of caching, we have to:
	 * - Accept that the current payment will expire 2 minutes later than expected.
	 * - Make sure that a new payment cannot be make in 2 minutes.
	 */
	$seconds = 120;
	$cacheParams = [
		'action' => 'load.payment',
		'with'   => 'plan',
	];
	$user = caching()->remember(User::class, $cacheParams, function () use ($user) {
		/*
		 * Important:
		 * The basic packages can be saved as paid in the "payments" table by the OfflinePayment addon
		 * So, don't apply the fake basic features, so we have to exclude packages whose price is 0.
		 */
		$isNotBasic = fn ($q) => $q->where('price', '>', 0);
		$user->loadMissing(['payment' => fn ($q) => $q->withWhereHas('plan', $isNotBasic)]);

		return $user;
	}, $seconds);

	if (!empty($user->payment) && !empty($user->payment->plan)) {
		$basicListingsLimit = setting('listing_form_listings_limit', 5);
		$basicPicturesLimit = setting('listing_form_pictures_limit', 5);
		$basicExpirationTime = setting('cron_activated_listings_expiration', 30);

		$listingsLimit = $user->payment->plan->listings_limit ?? $basicListingsLimit;
		$picturesLimit = $user->payment->plan->pictures_limit ?? $basicPicturesLimit;
		$expirationTime = $user->payment->plan->expiration_time ?? $basicExpirationTime;

		$listingsLimit = ($listingsLimit > 0) ? $listingsLimit : $basicListingsLimit;
		$picturesLimit = ($picturesLimit > 0) ? $picturesLimit : $basicPicturesLimit;
		$expirationTime = ($expirationTime > 0) ? $expirationTime : $basicExpirationTime;

		$array['listingsLimit'] = $listingsLimit;
		$array['picturesLimit'] = $picturesLimit;
		$array['expirationTime'] = $expirationTime;
	}

	return empty($feature) ? $array : ($array[$feature] ?? null);
}

/**
 * Get possible promotion features to a listing
 *
 * @return int|int[]|null
 */
function getListingPromotionFeatures(Listing $listing, ?string $feature = null): array|int|null
{
	$array = [
		'picturesLimit'  => null,
		'expirationTime' => null,
	];

	/*
	 * Important:
	 * The basic packages can be saved as paid in the "payments" table by the OfflinePayment addon
	 * So, don't apply the fake basic features, so we have to exclude packages whose price is 0.
	 */
	$isNotBasic = fn ($q) => $q->where('price', '>', 0);
	$listing->loadMissing(['payment' => fn ($q) => $q->withWhereHas('plan', $isNotBasic)]);

	if (!empty($listing->payment) && !empty($listing->payment->plan)) {
		$basicPicturesLimit = setting('listing_form_pictures_limit', 5);
		$basicExpirationTime = setting('cron_activated_listings_expiration', 30);

		$picturesLimit = $listing->payment->plan->pictures_limit ?? $basicPicturesLimit;
		$expirationTime = $listing->payment->plan->expiration_time ?? $basicExpirationTime;

		$picturesLimit = ($picturesLimit > 0) ? $picturesLimit : $basicPicturesLimit;
		$expirationTime = ($expirationTime > 0) ? $expirationTime : $basicExpirationTime;

		$array['picturesLimit'] = $picturesLimit;
		$array['expirationTime'] = $expirationTime;
	}

	return empty($feature) ? $array : ($array[$feature] ?? null);
}

/**
 * Get plan ID request through POST method (plan_id) or GET method (planId)
 */
function requestPlanId(): ?int
{
	$planId = null;

	if (request()->filled('plan_id')) {
		$planId = request()->input('plan_id');
	}

	if (empty($planId)) {
		if (request()->filled('planId')) {
			$planId = request()->query('planId');
		}
	}

	if (empty($planId)) {
		$planId = (int)old('plan_id');
		if (!empty($planId)) {
			if (!request()->has('plan_id')) {
				request()->request->add(['plan_id' => $planId]);
			}

			return $planId;
		}
	}

	return (int)$planId;
}

/**
 * Get package by ID
 */
function getPlanById($planId): ?Plan
{
	$cacheParams = [
		'action' => 'get.package',
		'id'     => $planId,
		'with'   => 'currency',
		'locale' => app()->getLocale(),
	];

	return caching()->remember(Plan::class, $cacheParams, function () use ($planId) {
		return Plan::with(['currency'])->where('id', $planId)->first();
	});
}

/**
 * Check if no package is selected or if a premium one is selected from pricing page
 */
function doesNoPlanOrPremiumOneSelected(Plan|array|null $plan = null): bool
{
	if (empty($plan)) {
		$planId = request()->query('planId');
		$plan = !empty($planId) ? getPlanById($planId) : null;
	}

	$planPrice = !empty($plan) ? data_get($plan, 'price') : null;

	return is_null($planPrice) || (is_numeric($planPrice) && $planPrice > 0);
}

/**
 * Get the package type relating to the current request
 */
function getRequestPlanType(): ?string
{
	$isPromoting = isApiRoute()
		? routeActionHas(ListingController::class)
		: (
			routeActionHas(ListingController::class)
			|| routeActionHas(getClassNamespaceName(ReportController::class))
		);

	$isSubscripting = isApiRoute()
		? routeActionHas(UserController::class)
		: (
			routeActionHas(UserController::class)
			|| routeActionHas(getClassNamespaceName(RegisterController::class))
			|| routeActionHas(getClassNamespaceName(AccountBaseController::class))
		);

	$type = null;
	if ($isPromoting) {
		$type = PlanType::Promotion->value;
	}
	if ($isSubscripting) {
		$type = PlanType::Subscription->value;
	}

	return $type;
}

/**
 * Get the display mode (key => value) list
 */
function getDisplayModeList(): array
{
	return [
		'list'    => 'list-view',
		'compact' => 'compact-view',
		'grid'    => 'grid-view',
	];
}

/**
 * Get a display mode by key
 */
function getDisplayMode(?string $key): ?string
{
	return getDisplayModeList()[$key] ?? null;
}

function isValidDisplayModeKey(?string $key): bool
{
	$modes = getDisplayModeList();

	return !empty($key) && !empty($modes[$key]);
}

function isValidDisplayMode(?string $mode): bool
{
	$flipped = array_flip(getDisplayModeList());

	return !empty($mode) && in_array($mode, array_keys($flipped));
}

/**
 * Country table's 'admin_type' enum column possible values
 */
function enumCountryAdminTypes(): array
{
	return collect(CountryAdminType::cases())
		->mapWithKeys(fn (CountryAdminType $case) => [$case->value => $case->label()])
		->all();
}

function getCountryFlagShapes(): array
{
	return [
		'rectangle' => 'Rectangle Flags', // Default
		'circle'    => 'Circle Flags',
		'hexagon'   => 'Hexagon Flags',
	];
}

function getCountryFlagUrl(?string $countryCode, ?int $size = 16): ?string
{
	if (empty($countryCode)) {
		return null;
	}
	$size = !empty($size) ? $size : 16;

	$flagUrl = null;

	$shape = setting('localization_country_flag_shape', 'rectangle');
	if ($shape == 'rectangle') {
		$missingIslandFlags = [
			'BQ' => 'NL', // Bonaire, Sint Eustatius, and Saba (Kingdom of the Netherlands)
			'BV' => 'NO', // Bouvet Island (Norway)
			'GF' => 'FR', // French Guiana (France)
			'GP' => 'FR', // Guadeloupe (France)
			'PM' => 'FR', // Saint Pierre and Miquelon (France)
			'RE' => 'FR', // Réunion (France)
			'SX' => 'NL', // Sint Maarten (Kingdom of the Netherlands)
		];
	} else {
		$missingIslandFlags = [
			'BV' => 'NO', // Bouvet Island (Norway)
			'CW' => 'NL', // Curaçao (Kingdom of the Netherlands)
			'AN' => 'NL', // Netherlands Antilles (Kingdom of the Netherlands)
			'FM' => 'US', // Federated States of Micronesia (US | UN)
			'CC' => 'AU', // Cocos (Keeling) Islands (Australia)
			'AX' => 'FI', // Åland Islands (Finland)
			'IM' => 'GB', // Isle of Man (United Kingdom)
		];
	}
	$code = $missingIslandFlags[$countryCode] ?? $countryCode;

	$flagPath = 'images/flags/' . $shape . '/' . $size . '/' . strtolower($code) . '.png';
	if (file_exists(public_path($flagPath))) {
		$flagUrl = mixStaticFile(url($flagPath));
	}

	return castToStringOrNull($flagUrl);
}

/**
 * Get the number of items per page
 *
 * @param null $perPage
 */
function getNumberOfItemsPerPage(?string $entity = null, $perPage = null, ?int $default = null): float|int
{
	$entity = castToString($entity);

	$isPerPageValid = (is_int($perPage) && $perPage > 0);
	if (!$isPerPageValid) {
		$defaultIntValue = 10;
		$default = (is_int($default) || (is_string($default) && ctype_digit($default))) ? (int)$default : null;
		$default = (is_int($default) && $default > 0) ? $default : $defaultIntValue;

		$perPage = setting('pagination_per_page', $default);
		$perPage = setting('pagination_' . $entity . '_per_page', $perPage);
		$perPage = (is_int($perPage) || (is_string($perPage) && ctype_digit($perPage))) ? (int)$perPage : $default;

		$perPage = (is_int($perPage) && $perPage > 0)
			? $perPage
			: $default;
	}

	return Number::clamp($perPage, min: 1, max: getMaxItemsPerPage($entity));
}

/**
 * Get the maximum items per page
 */
function getMaxItemsPerPage(?string $entity = null): float|int
{
	$defaultMaxItemsPerPage = ($entity == 'listings') ? 100 : 500;
	$maxItemsPerPage = ($entity == 'listings')
		? config('larapen.core.maxItemsPerPage.listings', $defaultMaxItemsPerPage)
		: config('larapen.core.maxItemsPerPage.global', $defaultMaxItemsPerPage);
	$maxItemsPerPage = (is_numeric($maxItemsPerPage) && $maxItemsPerPage >= $defaultMaxItemsPerPage)
		? $maxItemsPerPage
		: $defaultMaxItemsPerPage;

	$maxItemsPerPage = ($entity == 'subadmin1_select') ? 200 : $maxItemsPerPage;
	$maxItemsPerPage = ($entity == 'subadmin2_select') ? 5000 : $maxItemsPerPage;

	return Number::clamp($maxItemsPerPage, min: $maxItemsPerPage, max: 100000);
}

/**
 * Get the number of items to take
 */
function getNumberOfItemsToTake(?string $entity = null): float|int
{
	$limit = setting('pagination_' . $entity . '_limit');

	return getNumberOfItemsPerPage($entity, $limit);
}

function isSubscriptionAvailable(): bool
{
	return (bool)version_compare(getCurrentVersion(), '14.0.0', '>=');
}

/**
 * Get listing's labeled dates
 */
function getListingDates($listing, ?string $status): array
{
	$dates = [];

	if (empty($status)) {
		return $dates;
	}

	if (in_array($status, ['list', 'pending-approval'])) {
		$createdAtFormatted = data_get($listing, 'created_at_formatted');
		$updatedAtFormatted = data_get($listing, 'updated_at_formatted');

		if (!empty($updatedAtFormatted)) {
			if ($createdAtFormatted != $updatedAtFormatted) {
				$dates[trans('global.updated_on')] = trans('global.updated_on') . ': ' . $updatedAtFormatted;
			}
		}

		if (!empty($createdAtFormatted)) {
			$dates[trans('global.created_on')] = trans('global.created_on') . ': ' . $createdAtFormatted;
		}
	}

	if ($status == 'archived') {
		$createdAtFormatted = data_get($listing, 'created_at_formatted');
		$archivedManuallyAtFormatted = data_get($listing, 'archived_manually_at_formatted');
		$archivedAtFormatted = data_get($listing, 'archived_at_formatted');

		if (!empty($archivedManuallyAtFormatted)) {
			$dates[trans('global.archived_manually_on')] = trans('global.archived_manually_on')
				. ': ' . $archivedManuallyAtFormatted;
		} else {
			if (!empty($archivedAtFormatted)) {
				$dates[trans('global.archived_on')] = trans('global.archived_on') . ': ' . $archivedAtFormatted;
			}
		}

		if (!empty($createdAtFormatted)) {
			$dates[trans('global.created_on')] = trans('global.created_on') . ': ' . $createdAtFormatted;
		}
	}

	if ($status == 'favourite') {
		$publishedAtFormatted = data_get($listing, 'created_at_formatted');
		$savedAtFormatted = data_get($listing, 'saved_at_formatted');

		if (!empty($savedAtFormatted)) {
			$dates[trans('global.saved_on')] = trans('global.saved_on') . ': ' . $savedAtFormatted;
		}

		if (!empty($publishedAtFormatted)) {
			$dates[trans('global.published_on')] = trans('global.published_on') . ': ' . $publishedAtFormatted;
		}
	}

	return $dates;
}

/**
 * Check if the "Multi-countries URLs" option is enabled
 */
function isMultiCountriesUrlsEnabled(): bool
{
	return setting('seo_multi_country_urls') == '1';
}

/**
 * Check if a file has a temporary path
 * Note: Temporary files are stored in: "/storage/app/public/temporary/" directory
 */
function hasTemporaryPath(string $filePath): bool
{
	return str_starts_with($filePath, 'temporary' . DIRECTORY_SEPARATOR);
}

/**
 * Get the item's address for Google Maps
 *
 * When a non-empty $itemAddress is provided (e.g. the listing's own `address`
 * column), it is prepended to the city/admin1/country trail so the geocoder
 * resolves the exact street address rather than only the city.
 */
function getItemAddressForMap($city, ?string $itemAddress = null): ?string
{
	$admin1 = data_get($city, 'subAdmin1');

	$countryName = config('country.name');
	$admin1Name = data_get($admin1, 'name');
	$cityName = data_get($city, 'name');

	$cityName = !empty($admin1Name)
		? (!empty($cityName) ? $cityName . ',' . $admin1Name : $admin1Name)
		: $cityName;

	$address = !empty($cityName) ? $cityName . ',' . $countryName : $countryName;

	$itemAddress = trim((string) $itemAddress);
	if ($itemAddress !== '') {
		$address = $itemAddress . ',' . $address;
	}

	return castToStringOrNull($address);
}

/**
 * Get Google Maps API JavaScript's URL
 *
 * https://developers.google.com/maps/documentation/geocoding/overview
 * https://developers.google.com/maps/documentation/geocoding/requests-geocoding
 * https://developers.google.com/maps/documentation/javascript/versions
 */
function getGoogleMapsApiUrl(
	?string $apiKey,
	bool    $useAsyncGeocoding = false,
	bool    $decode = true
): ?string
{
	if (empty($apiKey)) {
		return null;
	}

	$baseUrl = 'https://maps.googleapis.com/maps/api/js';
	$query = [
		'key'      => $apiKey,
		'loading'  => 'async',
		'region'   => config('country.code'),
		'language' => getLangTag(app()->getLocale()),
		'callback' => 'initGoogleMap',
		'v'        => 'weekly',
	];
	if (!$useAsyncGeocoding) {
		$query['v'] = 'beta';
		$query['libraries'] = 'marker';
	}

	$url = $baseUrl . '?' . Arr::query($query);

	return $decode ? html_entity_decode($url) : $url;
}

/**
 * Get Google Maps Embed URL
 * https://developers.google.com/maps/documentation/embed/get-started
 * https://developers.google.com/maps/documentation/embed/embedding-map
 *
 * Note: The Embed URL is called from an iframe
 */
function getGoogleMapsEmbedApiUrl(?string $apiKey, ?string $q, bool $decode = true): string
{
	$baseUrl = 'https://www.google.com/maps/embed/v1/place';
	$query = [
		'key'      => $apiKey,
		'q'        => $q,
		'zoom'     => 9,         // Values ranging from 0 (the whole world) to 21 (individual buildings)
		'maptype'  => 'roadmap', // roadmap (default) or satellite
		'region'   => config('country.code'),
		'language' => getLangTag(app()->getLocale()),
	];
	$url = $baseUrl . '?' . Arr::query($query);

	return $decode ? html_entity_decode($url) : $url;
}

/**
 * Check if the user account closure option is enabled
 */
function isAccountClosureEnabled(): bool
{
	return setting('other_account_closure_enabled') == '1';
}

/**
 * Get a selected (checked) entry ID list
 */
function getSelectedEntryIds($entryId = null, array|string|null $entryIdList = [], bool $asString = false): array|string
{
	$array = [];
	if (!empty($entryIdList)) {
		if (isStringableStrict($entryIdList)) {
			$entryIdList = explode(',', $entryIdList);
		}
		$array = is_array($entryIdList) ? $entryIdList : [];
	} else {
		if (!empty($entryId)) {
			if (isStringableStrict($entryId)) {
				$array[] = (string)$entryId;
			}
			if (is_array($entryId)) {
				$array = $entryId;
			}
		}
	}

	return $asString ? implode(',', $array) : $array;
}

function linkClass(?string $color = 'primary'): string
{
	$colorClass = BootstrapColor::Link->getColorClass($color);
	$altBaseClass = BootstrapColor::Link->altBase();

	return "$colorClass $altBaseClass";
}

function unsavedFormGuard(): string
{
	$isEnabled = (setting('security_unsaved_form_guard') == '1');

	return $isEnabled ? 'unsaved-guard' : '';
}

/**
 * Get the search results page offcanvas breakpoint data
 */
function getSerpOffcanvasBreakpoint(?string $breakpointKey = null, bool $isLeftSidebarEnabled = true): array
{
	$pageBreakpointList = [
		'md'  => [
			'label'                   => trans('admin.on_small_screen'),
			'leftColSize'             => $isLeftSidebarEnabled ? 'col-md-3' : '',
			'rightColSize'            => $isLeftSidebarEnabled ? 'col-md-9' : 'col-md-12',
			'showInlineOnSmallScreen' => ' d-inline-block d-md-none', // Hide on large screens
			'showOnLargeScreen'       => ' d-none d-md-block',        // Hide on small screens
			'size'                    => 768,
		],
		'lg'  => [
			'label'                   => trans('admin.on_medium_screen'),
			'leftColSize'             => $isLeftSidebarEnabled ? 'col-lg-3' : '',
			'rightColSize'            => $isLeftSidebarEnabled ? 'col-lg-9' : 'col-lg-12',
			'showInlineOnSmallScreen' => ' d-inline-block d-lg-none', // Hide on large screens
			'showOnLargeScreen'       => ' d-none d-lg-block',        // Hide on small screens
			'size'                    => 992,
		],
		'xl'  => [
			'label'                   => trans('admin.on_large_screen'),
			'leftColSize'             => $isLeftSidebarEnabled ? 'col-xl-3' : '',
			'rightColSize'            => $isLeftSidebarEnabled ? 'col-xl-9' : 'col-xl-12',
			'showInlineOnSmallScreen' => ' d-inline-block d-xl-none', // Hide on large screens
			'showOnLargeScreen'       => ' d-none d-xl-block',        // Hide on small screens
			'size'                    => 1200,
		],
		'xxl' => [
			'label'                   => trans('admin.on_extra_large_screen'),
			'leftColSize'             => $isLeftSidebarEnabled ? 'col-xxl-3' : '',
			'rightColSize'            => $isLeftSidebarEnabled ? 'col-xxl-9' : 'col-xxl-12',
			'showInlineOnSmallScreen' => ' d-inline-block d-xxl-none', // Hide on large screens
			'showOnLargeScreen'       => ' d-none d-xxl-block',        // Hide on small screens
			'size'                    => 1400,
		],
		'all' => [
			'label'                   => trans('admin.on_all_sizes_screen'),
			'leftColSize'             => '',
			'rightColSize'            => 'col-12',
			'showInlineOnSmallScreen' => ' d-inline-block', // Hide on large screens
			'showOnLargeScreen'       => ' d-block',        // Hide on small screens
			'size'                    => 7680,              // 8K:7680 | 6K:6144 | 4K:4096
		],
	];

	if (empty($breakpointKey)) {
		return collect($pageBreakpointList)
			->map(fn ($item, $key) => $item['label'] ?? $key)
			->toArray();
	}

	return $pageBreakpointList[$breakpointKey] ?? [];
}

function getFieldIdentifier(?string $id = null, ?string $name = null, ?string $prefix = null, string $separator = '-'): string
{
	$dotSepName = arrayFieldToDotNotation($name);
	$fallbackId = str_replace('.', $separator, $dotSepName);

	$id = !empty($id) ? $id : $fallbackId;
	$id = str_replace(['_', '-', ' '], $separator, $id);

	$prefix = (!empty($prefix) && !str_ends_with($prefix, $separator)) ? "{$prefix}{$separator}" : '';

	return "{$prefix}{$id}";
}

function getGlobalCacheTtl(): int
{
	$fallback = 3600; // 3600 (1h) 86400 (24h)

	return (int)setting('optimization_cache_expiration', $fallback);
}

function getSchemaCacheTtl(): int
{
	return 86400 * 365; // 24h x 365
}

/**
 * Determines if guests can submit listings based on the application configuration.
 */
function guestCanSubmitListings(): bool
{
	return setting('listing_form_guest_can_submit_listings') == '1';
}

/**
 * Determines if listings need to be reviewed (by admin) based on the application configuration.
 */
function listingsNeedToBeReviewed(): bool
{
	return setting('listing_form_listings_review_activation') == '1';
}

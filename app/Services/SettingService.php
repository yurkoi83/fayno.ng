<?php

namespace App\Services;

use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Models\Setting;
use App\Services\Image\ImageStore;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * NB: This service intentionally does NOT extend BaseService.
 *
 * BaseService::__construct() calls getGlobalCacheTtl(), which itself calls setting() →
 * SettingService::get(). If SettingService extended BaseService, instantiating it via
 * the container would recurse infinitely (BaseService → getGlobalCacheTtl → setting →
 * SettingService → BaseService → …) and exhaust memory. Keep this class standalone.
 */
class SettingService
{
	/**
	 * Setting groups whose values were folded into the active theme's
	 * `themes.settings` JSON (Phase 4). Reads of `setting('header_*')`,
	 * `setting('footer_*')`, `setting('style_*')` — and the group-shape
	 * variants `setting('header')` etc. — fall through to ThemeService.
	 */
	public const array THEME_SETTING_GROUPS = ['header', 'footer', 'style'];

	/**
	 * Cached flattened defaults from config/larapen/settings.php — built once per process.
	 */
	private static ?array $configDefaults = null;

	/**
	 * Process-local transient overrides — used by the admin "test driver" UX to apply
	 * unsaved form input on top of the persisted settings for the duration of a single
	 * test run, without committing to the DB. Populated/cleared via withOverrides().
	 */
	private static array $overrides = [];

	/**
	 * Per-process memoization of resolved `get($key)` values.
	 *
	 * `setting()` is called dozens of times per request from blade partials,
	 * search relations, ordering, sidebar — each call walks the cascade
	 * (overrides → theme JSON → flat cache → overlay → group fallback). The
	 * underlying stores are themselves cached, but the cascade still does a
	 * sequence of array_key_exists / string ops every time.
	 *
	 * Stored on the class (not the instance) because `SettingService` is not
	 * a singleton — `app(SettingService::class)` returns a fresh instance per
	 * call. A per-instance memo would never warm up.
	 *
	 * The memo is bypassed whenever transient overrides are present (admin
	 * test endpoints), so a context-specific value can't leak across the
	 * boundary. A registered resolver overlay (Domain Mapping addon) does
	 * NOT disable the memo: the overlay closure is itself memoized via
	 * `$overlayMemo` below and returns a stable per-request map, so the
	 * cascade resolution captures the right value on the first call and
	 * subsequent calls are safe to serve from memo. The memo is cleared on:
	 *  - `clearCache()` (admins saving settings)
	 *  - `withOverrides()` entering/leaving
	 *  - `setResolverOverlay()` mutating the overlay closure
	 */
	private static array $resolvedCache = [];

	/**
	 * Per-process memo of `all()` result. The underlying `caching()->remember()`
	 * call returns the same map for the whole request, but file-driver caches
	 * fire a version-key read + value-key read on every invocation. Memoizing
	 * here collapses ~1k cache reads per request (one pair per `setting()`
	 * call going through `resolveValue()`) down to one. Cleared whenever the
	 * Setting cache is invalidated.
	 */
	private static ?array $allMemo = null;

	/**
	 * Per-process memo of `activeThemeSettings()` result. Same rationale as
	 * `$allMemo` — `ThemeService::getSettings()` walks the cached `themes`
	 * row on every call, which translates to two cache reads per `setting()`
	 * invocation under the file driver. Cleared by `flushMemos()` whenever
	 * the underlying theme or setting cache is invalidated.
	 */
	private static ?array $themeSettingsMemo = null;

	/**
	 * Per-process memo of the resolver-overlay closure's return value. The
	 * Domain Mapping addon's overlay performs a `domain_settings` cache
	 * lookup on every invocation; memoizing it avoids paying that cost on
	 * every `setting()` call. Cleared whenever the overlay closure or the
	 * underlying cache changes.
	 */
	private static ?array $overlayMemo = null;

	/**
	 * Per-request resolver overlay — lets addons (e.g., the Domain Mapping
	 * addon for per-country setting overrides) layer a context-specific
	 * flat-key map on top of the global flat settings table. The closure
	 * runs whenever `get()` / `getByGroupFlat()` need to consult the overlay
	 * and must return a flat-keyed array (`['mail_driver' => 'smtp', …]`)
	 * scoped to the current request. Returning `[]` defers entirely to the
	 * global table.
	 *
	 * Single registration site to keep precedence deterministic; passing
	 * `null` (or omitting the call) means no overlay.
	 */
	private static ?Closure $resolverOverlay = null;

	/**
	 * Register a per-request resolver overlay. The closure is invoked from
	 * `get()` / `getByGroupFlat()` and must return a flat-keyed array of
	 * overrides for the current request (e.g., the per-country settings for
	 * the Domain Mapping addon's active host).
	 *
	 * Pass `null` to unregister.
	 */
	public static function setResolverOverlay(?Closure $callback): void
	{
		self::$resolverOverlay = $callback;
		self::flushMemos();
	}

	/**
	 * Drop every per-process memo (per-key resolutions, `all()`, theme
	 * settings, overlay map). Called from every mutation site that
	 * previously cleared only `$resolvedCache` — the three new memos feed
	 * into the same cascade and must be invalidated together.
	 */
	private static function flushMemos(): void
	{
		self::$resolvedCache = [];
		self::$allMemo = null;
		self::$themeSettingsMemo = null;
		self::$overlayMemo = null;
	}

	/**
	 * Run the registered overlay closure and return its flat-keyed map.
	 * Wrapped in try/catch so a buggy/expensive addon overlay can never
	 * take down core settings reads.
	 *
	 * @return array<string, mixed>
	 */
	private function overlay(): array
	{
		if (self::$overlayMemo !== null) {
			return self::$overlayMemo;
		}
		if (self::$resolverOverlay === null) {
			return self::$overlayMemo = [];
		}
		try {
			$result = (self::$resolverOverlay)();

			return self::$overlayMemo = is_array($result) ? $result : [];
		} catch (Throwable) {
			// Same caveat as `activeThemeSettings()` above: if the
			// overlay closure throws because the container isn't
			// fully booted yet, return `[]` WITHOUT caching so the
			// next call can retry.
			return [];
		}
	}

	/**
	 * Run $fn with the given flat-keyed overrides taking precedence over persisted
	 * settings, then restore the previous override stack. Used by the Settings test
	 * endpoints (testMailConfig, testSmsConfig, testGeoIPConfig) so the form's unsaved
	 * driver credentials can be tested live without writing them to the DB first.
	 *
	 * Keys must be the canonical flat-prefixed form (`mail_smtp_host`, not `smtp_host`).
	 */
	public static function withOverrides(array $overrides, callable $fn): mixed
	{
		$previous = self::$overrides;
		self::$overrides = array_replace($previous, $overrides);
		// Only the per-key memo depends on overrides — `$allMemo`, the theme
		// settings memo, and the overlay memo are sourced from the cache /
		// overlay closure and are unaffected by transient override changes.
		self::$resolvedCache = [];
		try {
			return $fn();
		} finally {
			self::$overrides = $previous;
			self::$resolvedCache = [];
		}
	}

	/**
	 * Register a single transient override for the remainder of the request.
	 * Unlike withOverrides(), this does NOT auto-restore — callers use it when
	 * the runtime choice (e.g., the search-page display mode picked via cookie
	 * or `?display=…` query) must shadow the persisted setting for every
	 * subsequent setting() read in the same request.
	 *
	 * Pass null as $value to drop the override.
	 */
	public static function setOverride(string $key, mixed $value): void
	{
		if ($value === null) {
			unset(self::$overrides[$key]);
		} else {
			self::$overrides[$key] = $value;
		}
		// Per-key memo only — see comment in withOverrides().
		self::$resolvedCache = [];
	}

	/**
	 * Drop every transient override set via setOverride(). The static $overrides
	 * map persists for the PHP process lifetime, so callers that mutate it
	 * outside withOverrides() (notably PHPUnit tests sharing a worker) must
	 * call this between scopes to keep the state from leaking.
	 */
	public static function clearOverrides(): void
	{
		self::$overrides = [];
		// Per-key memo only — see comment in withOverrides().
		self::$resolvedCache = [];
	}

	/**
	 * Get a setting value by globally unique flat key (e.g. 'app_email', 'mail_driver').
	 *
	 * Priority: transient override → DB row → config/larapen/settings.php default → caller's $default → null.
	 *
	 * Two back-compat shortcuts are honored:
	 *
	 *   • Group access — when $key matches a knownGroup() name AND no flat row exists
	 *     with exactly that key, returns the whole group as an inner→value map.
	 *
	 *   • Dotted form — when $key contains a dot ('group.inner'), translates it to
	 *     the flat shape ('group_inner') and looks up.
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		// Transient override (Settings test endpoints) — wins over everything,
		// never memoized.
		if (array_key_exists($key, self::$overrides)) {
			return self::$overrides[$key];
		}

		// Per-process memo. Skipped only when transient overrides are active —
		// `setOverride()` / `withOverrides()` clear `$resolvedCache` on each
		// mutation, but the check protects against any in-flight `setting()`
		// inside an override window resolving against a stale memo. A
		// resolver overlay (Domain Mapping) is safe to memoize against
		// because its closure runs once per request (via `$overlayMemo`) and
		// the resulting cascade is stable for the rest of the request.
		// The `$default` is intentionally NOT part of the memo key: the
		// underlying resolver returns null when nothing in the cascade matches,
		// and the caller-supplied default is applied at the very end.
		$canMemo = (self::$overrides === []);
		if ($canMemo && array_key_exists($key, self::$resolvedCache)) {
			return self::$resolvedCache[$key] ?? $default;
		}

		$resolved = $this->resolveValue($key);

		if ($canMemo) {
			self::$resolvedCache[$key] = $resolved;
		}

		return $resolved ?? $default;
	}

	/**
	 * Walk the resolution cascade for `$key` without applying any caller-supplied
	 * default. Returns `null` when nothing in the cascade matches, so the public
	 * `get()` can layer the caller default on top — and so the per-request memo
	 * can store the cascade result independently of any specific caller.
	 */
	private function resolveValue(string $key): mixed
	{
		$settings = $this->all();
		$themeSettings = self::activeThemeSettings();
		// Resolver overlay (e.g., the Domain Mapping addon's per-country map)
		// — sits between transient test-overrides and the flat DB cascade,
		// shadowing the global value when the overlay sets the same key.
		$overlay = $this->overlay();

		// Theme-owned keys (`header_*`, `footer_*`, `style_*`): the active
		// theme's `themes.settings` JSON is the source of truth post-Phase-4.
		// We must check it BEFORE the flat `settings` table because the legacy
		// `SettingDataSeeder` (and any pre-Phase-4 install) wrote duplicate
		// rows into the flat table; left to win the cascade, those stale rows
		// shadow whatever the admin saves via /admin/themes/{id}/settings and
		// the front-end keeps rendering the old values.
		if (self::isThemeOwnedKey($key) && array_key_exists($key, $themeSettings)) {
			return $themeSettings[$key];
		}

		// Overlay → flat — the per-country override, if any, wins.
		if (array_key_exists($key, $overlay)) {
			return $overlay[$key];
		}

		// Direct flat lookup — the canonical path for post-Stage-4 callers.
		if (array_key_exists($key, $settings)) {
			return $settings[$key];
		}

		// Theme-owned lookup fallback for non-theme-prefixed keys declared by
		// the active theme's `config.php` (e.g. `primary_color`, `default_mode`,
		// `allow_mode_toggle`) that aren't covered by the early-return above.
		if (array_key_exists($key, $themeSettings)) {
			return $themeSettings[$key];
		}

		// Group-shape access — `setting('app')` returns the flat-keyed group array.
		if (!str_contains($key, '.') && !str_contains($key, '_')) {
			if (in_array($key, self::THEME_SETTING_GROUPS, true)) {
				// Theme groups: build the same flat-keyed inner-name shape that
				// the legacy `getByGroup()` produced.
				$prefix = $key . '_';
				$bucket = [];
				foreach ($themeSettings as $k => $v) {
					if (str_starts_with($k, $prefix)) {
						$bucket[substr($k, strlen($prefix))] = $v;
					}
				}

				return $bucket !== [] ? $bucket : null;
			}

			if (in_array($key, self::knownGroups(), true)) {
				$group = $this->getByGroup($key);

				return $group !== [] ? $group : null;
			}
		}

		// Dotted-form back-compat.
		if (str_contains($key, '.')) {
			[$group, $inner] = explode('.', $key, 2);
			$flatKey = self::flattenKey($group, $inner);
			if (array_key_exists($flatKey, $overlay)) {
				return $overlay[$flatKey];
			}
			if (array_key_exists($flatKey, $settings)) {
				return $settings[$flatKey];
			}
			// Theme-folded dotted form (`setting('header.default_dark')`).
			if (in_array($group, self::THEME_SETTING_GROUPS, true) && array_key_exists($flatKey, $themeSettings)) {
				return $themeSettings[$flatKey];
			}
		}

		// Synthetic image-URL keys (`app_logo_url`, `app_logo_dark_url`,
		// `app_logo_light_url`, `app_favicon_url`): not persisted, computed on
		// demand from the matching path setting + ImageStore profile. Mirrors
		// the URLs that `AppSetting::getFieldValues()` materializes for the
		// admin form, so `setting('app_*_url')` resolves at runtime too.
		$synthesized = $this->synthesizeImageUrl($key, $settings);
		if ($synthesized !== null) {
			return $synthesized;
		}

		return $this->getConfigDefault($key);
	}

	/**
	 * Build a public URL for the image-bearing settings the admin App form
	 * exposes as path inputs (logo / logo-dark / logo-light / favicon). The
	 * persisted value is a disk-relative path; views call
	 * `setting('app_logo_url')` etc. and expect a ready-to-render URL.
	 * Returns `null` when `$key` isn't one of the synthetic image-URL keys so
	 * the cascade falls through to `getConfigDefault()`.
	 *
	 * Mirrors the dark/light fallback in `AppSetting::getFieldValues()`: if
	 * the dark or light slot is unset (or still pointing at its default
	 * placeholder) AND the main logo has been customized, the dark/light URL
	 * inherits from the main logo — so uploading a single logo replaces all
	 * three previews without forcing the admin to upload three copies.
	 */
	private function synthesizeImageUrl(string $key, array $settings): ?string
	{
		static $map = [
			'app_logo_url'       => ['path' => 'app_logo', 'default' => 'logo', 'profile' => 'site-logo'],
			'app_logo_dark_url'  => ['path' => 'app_logo_dark', 'default' => 'logo-dark', 'profile' => 'site-logo'],
			'app_logo_light_url' => ['path' => 'app_logo_light', 'default' => 'logo-light', 'profile' => 'site-logo'],
			'app_favicon_url'    => ['path' => 'app_favicon', 'default' => 'favicon', 'profile' => 'favicon'],
		];
		$spec = $map[$key] ?? null;
		if ($spec === null) {
			return null;
		}

		try {
			$disk = StorageDisk::getDisk();
			$mainLogo = $settings['app_logo'] ?? null;
			$mainDefault = config('larapen.images.defaults.logo');
			$mainLogoIsCustom = is_string($mainLogo) && $mainLogo !== '' && $mainLogo !== $mainDefault && $disk?->exists($mainLogo);

			$path = $settings[$spec['path']] ?? null;
			$defaultPath = config('larapen.images.defaults.' . $spec['default']);
			if (!is_string($path) || $path === '' || !$disk?->exists($path)) {
				$path = $defaultPath;
			}
			if (in_array($key, ['app_logo_dark_url', 'app_logo_light_url'], true)
				&& $path === $defaultPath && $mainLogoIsCustom) {
				$path = $mainLogo;
			}

			// `app_logo_url` falls back to `app_logo_dark` when the main slot is
			// still on its default placeholder. Lets every consumer of
			// `setting('app_logo_url')` (admin sidebar, mail templates, front
			// chrome) surface a sensible logo as soon as the admin uploads any
			// of the variants, without forcing them to also fill in the main
			// slot. The App Logo *form input* preview is not affected: that
			// preview reads `$values['app_logo_url']` straight off
			// `getByGroupFlat()` (which never hits this synthesizer), and the
			// blade falls back to a direct `$disk->url($current)` — so an
			// unset main slot still renders as "no preview" in the form, exactly
			// as the admin expects when deciding what to upload.
			if ($key === 'app_logo_url' && $path === $defaultPath) {
				$darkPath = $settings['app_logo_dark'] ?? null;
				if (is_string($darkPath) && $darkPath !== '' && $disk?->exists($darkPath)) {
					$path = $darkPath;
				}
			}

			return app(ImageStore::class)->url($path, $spec['profile'], 'master');
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * Whether `$key` is a flat-form member of one of the theme-owned groups
	 * (`header_*`, `footer_*`, `style_*`). These keys live in the active
	 * theme's `themes.settings` JSON, and the JSON wins over any stale
	 * row carrying the same name in the flat `settings` table.
	 */
	private static function isThemeOwnedKey(string $key): bool
	{
		foreach (self::THEME_SETTING_GROUPS as $group) {
			if (str_starts_with($key, $group . '_')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the active theme's merged settings (defaults + DB row).
	 * Wrapped in a try/catch because `setting()` is called during install /
	 * upgrade where the `themes` table may not yet exist.
	 */
	private static function activeThemeSettings(): array
	{
		if (self::$themeSettingsMemo !== null) {
			return self::$themeSettingsMemo;
		}
		try {
			return self::$themeSettingsMemo = app(ThemeService::class)->getSettings();
		} catch (Throwable) {
			// `setting()` is called from `config/larapen/routes.php` at
			// config-load time (top-level `isMultiCountriesUrlsEnabled()`).
			// At that point Laravel's facade root isn't bound yet, so
			// `app(ThemeService::class)` throws. CRITICAL: do NOT memoize
			// the empty fallback — if we did, every subsequent
			// `setting()` call in the rest of the request would read the
			// poisoned memo and return null even after the container is
			// fully booted. Returning `[]` without caching keeps the
			// next call free to retry the real fetch.
			return [];
		}
	}

	/**
	 * Set a setting value by globally unique flat key.
	 *
	 *   ('app_email', 'foo@bar')           → upsert by key='app_email', group=current-or-'general'
	 *   ('email',     'foo@bar', 'app')    → upsert by key='app_email' (auto-flattened)
	 *   ('app.email', 'foo@bar')           → translates dotted form, upsert by key='app_email'
	 *
	 * Triggers InvalidatesCache on save, so the next setting() read picks up the change.
	 */
	public function set(string $key, mixed $value, ?string $group = null): void
	{
		// Dotted-form back-compat: 'app.email' → group='app', inner='email'.
		if (str_contains($key, '.')) {
			[$dottedGroup, $inner] = explode('.', $key, 2);
			$group ??= $dottedGroup;
			$key = $inner;
		}

		$group ??= 'general';
		$flatKey = self::flattenKey($group, $key);

		Setting::setValue($flatKey, $value, $group);
	}

	public function setMany(array $settings, ?string $group = null): void
	{
		foreach ($settings as $key => $value) {
			$this->set($key, $value, $group);
		}
	}

	/**
	 * Return every setting as a flat key→value map keyed by '<group>.<inner_key>'.
	 *
	 * Cached in the Setting::class bucket; auto-invalidated on row save/delete via
	 * the InvalidatesCache trait. Empty values are filtered out so callers fall through
	 * to config/larapen/settings.php defaults — matching the legacy
	 * loadDatabaseSettingsToConfig() behavior byte-for-byte.
	 */
	/**
	 * Return every setting as a flat key→value map keyed by the row's globally unique
	 * `key` column. Empty values are filtered out so callers fall through to config
	 * defaults / caller defaults — matches the legacy loadDatabaseSettingsToConfig()
	 * filter byte-for-byte.
	 */
	public function all(): array
	{
		if (self::$allMemo !== null) {
			return self::$allMemo;
		}
		try {
			return self::$allMemo = caching()->remember(Setting::class, ['flat'], function () {
				$flat = [];
				$settings = Setting::query()->get(['group', 'key', 'value']);
				foreach ($settings as $row) {
					$value = $row->value;
					if (empty($value) && !is_numeric($value)) {
						continue;
					}
					$flat[$row->key] = $value;
				}

				return $flat;
			});
		} catch (QueryException|Throwable) {
			// DB or settings table not yet available (e.g., during installation).
			return [];
		}
	}

	/**
	 * Return every key in `$group` as an inner-key→value map (group prefix stripped).
	 *
	 * Example for the 'mail' group with rows ('mail_driver' => 'smtp', 'mail_smtp_host' => '...'):
	 *   getByGroup('mail')  // => ['driver' => 'smtp', 'smtp_host' => '...']
	 *
	 * The stripped form preserves the legacy ConfigTrait contract — `updateMailConfig`,
	 * `updateSmsConfig`, etc. still receive `['driver' => 'smtp']` exactly as they did
	 * before Stage 4. Callers that want the flat-keyed form can use ::getByGroupFlat().
	 */
	public function getByGroup(string $group): array
	{
		$flat = $this->getByGroupFlat($group);
		$prefix = $group . '_';
		$inner = [];
		foreach ($flat as $key => $value) {
			$innerKey = str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
			$inner[$innerKey] = $value;
		}

		return $inner;
	}

	/**
	 * Same as getByGroup() but keeps the canonical flat-prefixed key names. Use this
	 * when you want the storage-shape map (e.g., when feeding the admin2 form which
	 * renders `name="value[mail_driver]"`).
	 */
	public function getByGroupFlat(string $group): array
	{
		try {
			$base = caching()->remember(Setting::class, ['group', $group], function () use ($group) {
				$out = [];
				$settings = Setting::query()->where('group', $group)->get(['key', 'value']);
				foreach ($settings as $row) {
					$value = $row->value;
					if (empty($value) && !is_numeric($value)) {
						continue;
					}
					$out[$row->key] = $value;
				}

				return $out;
			});

			// Overlay any per-request overrides that fall under this group's
			// flat-prefix (e.g. `mail_*` for `getByGroupFlat('mail')`). Keeps
			// the cascade consistent with `get()`.
			$overlay = $this->overlay();
			if (!empty($overlay)) {
				$prefix = $group . '_';
				foreach ($overlay as $k => $v) {
					if (is_string($k) && str_starts_with($k, $prefix)) {
						$base[$k] = $v;
					}
				}
			}

			return $base;
		} catch (QueryException|Throwable) {
			return [];
		}
	}

	/**
	 * Compute a globally unique flat key from a (group, inner_key) pair.
	 *
	 * Used by:
	 *   • The Stage-4 rename migration when flattening DB rows.
	 *   • SettingService::set() / get() when admins pass dotted-form back-compat keys.
	 *   • SettingService::convertLegacyTable() when migrating field_values JSON rows.
	 *   • The admin2 SettingController when re-keying form-side inputs to storage shape.
	 *
	 * Rule:
	 *   ('mail',  'driver')      → 'mail_driver'
	 *   ('app',   'app_name')    → 'app_name'   (already prefixed → kept as-is)
	 *   ('',      'site_name')   → 'site_name'  (no group → no prefix)
	 */
	public static function flattenKey(string $group, string $key): string
	{
		if ($group === '' || str_starts_with($key, $group . '_')) {
			return $key;
		}

		return $group . '_' . $key;
	}

	public function clearCache(): void
	{
		caching()->invalidate(Setting::class);
		self::flushMemos();
	}

	/**
	 * Drop the per-process resolved-value memo without touching the underlying
	 * cache manager. Called by SettingObserver on every Setting row save/delete
	 * so subsequent reads in the same request see the new value. Clears all
	 * three sub-memos as well — the cached `all()` map is now stale after a
	 * row write.
	 */
	public static function flushResolvedMemo(): void
	{
		self::flushMemos();
	}

	/**
	 * List of recognized setting group names (matches the per-group Setting subclasses
	 * under app/Models/Setting/*Setting.php). Used by the converter to know which group
	 * a given flat key belongs to when migrating from the legacy JSON shape.
	 *
	 * Order is the canonical sidebar order — preserved so admin2 lists groups consistently.
	 */
	public static function knownGroups(): array
	{
		// `header` and `style` are theme-owned (folded into the active theme's
		// `themes.settings` JSON in Phase 4) and don't surface in the sidebar.
		// `footer` is split: cosmetic keys (`footer_dark`, `footer_*_color`,
		// `footer_border_*`, `footer_high_spacing`, `footer_full_width`,
		// `footer_bg_image`) live in `themes.settings`; the non-cosmetic flags
		// (powered-by, hide-links, app store URLs, …) live in the flat
		// `settings` table under this `footer` group so they survive a theme
		// switch. Reads of `setting('footer_*')` resolve through ThemeService
		// first (theme JSON wins for cosmetic keys) and fall through to the
		// flat table for the rest — see `resolveValue()`.
		return [
			'app',
			'listing_form',
			'listings_list',
			'listing_page',
			'mail',
			'sms',
			'upload',
			'localization',
			'security',
			'auth',
			'social_auth',
			'social_link',
			'footer',
			'social_share',
			'optimization',
			'queue',
			'seo',
			'pagination',
			'wysiwyg',
			'other',
			'ai',
			'translator',
			'cron',
			'backup',
		];
	}

	/**
	 * Resolve a group's display label. Translates via the existing `settings.<group>` keys
	 * when present (so the admin2 UI stays multilingual without duplicate translation files).
	 */
	public static function groupLabel(string $group): string
	{
		$transKey = 'settings.' . $group;
		if (trans()->has($transKey)) {
			return trans($transKey);
		}

		return mb_ucfirst(str_replace('_', ' ', $group));
	}

	/**
	 * Resolve the per-group Setting subclass FQN (e.g., App\Models\Setting\AppSetting).
	 * Returns null when no matching class exists — addons may register their own under
	 * `<addon>\app\Models\Setting\*Setting`.
	 */
	public static function resolveGroupClass(string $group): ?string
	{
		$className = str($group)->camel()->ucfirst()->append('Setting')->toString();
		$core = "\\App\\Models\\Setting\\{$className}";
		try {
			if (class_exists($core)) {
				return $core;
			}
		} catch (Throwable) {
			// A legacy/orphan Setting class file may still sit on disk yet fail
			// to autoload — e.g. a pre-v19 subclass that still `extends
			// BaseSetting` (removed in v19). Treat it as absent instead of
			// letting the fatal autoload Error abort settings seeding / admin
			// pages. The v19 Updater deletes these orphans, but installs that
			// already ran the earlier (incomplete) updater may still carry them.
			return null;
		}

		// Addon-side fallback. Two layouts to try:
		//   - new (larapen-style): `Addons\<Studly>\Models\Setting\<X>Setting`
		//   - legacy:              `extensions\addons\<name>\app\Models\Setting\<X>Setting`
		// `addon_namespace()` returns the right base prefix automatically;
		// the suffix differs (`\Models\…` vs `\app\Models\…`).
		try {
			$addonNamespace = addon_namespace($group);
			$candidates = [
				$addonNamespace . '\\Models\\Setting\\' . $className,        // new
				$addonNamespace . '\\app\\Models\\Setting\\' . $className,   // legacy
			];
			foreach ($candidates as $fqcn) {
				if (class_exists($fqcn)) {
					return $fqcn;
				}
			}
		} catch (Throwable) {
			// addon_namespace() not available or addon not installed — ignore.
		}

		return null;
	}

	/**
	 * Walk the per-group Setting subclass field schema, pulling default values for keys
	 * that haven't been customized yet. Used by the seeder and admin2 form rendering.
	 *
	 * Returns ['inner_key' => $default, ...]. Calls the subclass's getFieldValues([], $disk)
	 * which is the canonical defaults source — same path admin1 used.
	 */
	public static function defaultsForGroup(string $group): array
	{
		$class = self::resolveGroupClass($group);
		if ($class === null || !method_exists($class, 'getFieldValues')) {
			return [];
		}

		$disk = StorageDisk::getDisk();
		try {
			$values = $class::getFieldValues([], $disk);
		} catch (Throwable) {
			return [];
		}

		return is_array($values) ? $values : [];
	}

	/**
	 * Convert legacy "one-row-per-group" settings into the new flat key/value shape.
	 *
	 * Reads each row from `$legacyTable` (default 'settings_legacy'), JSON-decodes its
	 * `field_values` blob, and writes one row per inner key into `$targetTable`
	 * (default 'settings') as ('group' => row.name, 'key' => inner_key, 'value' => json).
	 *
	 * IDEMPOTENT — uses upsert on the unique `key` index. Re-running the converter is
	 * safe and only updates rows whose values actually changed in the legacy table.
	 *
	 * Returns ['groups' => N, 'rows_inserted' => N, 'rows_updated' => N] for logging.
	 *
	 * Designed to be called from the v19.0.0 Updater (database/updates/19.0.0/19.0.0/Updater.php)
	 * after the legacy table has been renamed.
	 */
	public static function convertLegacyTable(
		string $legacyTable = 'settings_legacy',
		string $targetTable = 'settings',
	): array
	{
		$stats = ['groups' => 0, 'rows_inserted' => 0, 'rows_updated' => 0];

		if (!Schema::hasTable($legacyTable) || !Schema::hasTable($targetTable)) {
			return $stats;
		}

		// Pull the legacy schema we care about. Don't load with the model — the Setting
		// model now points at the flat table, and we need raw access to the JSON column.
		$rows = DB::table($legacyTable)->get(['name', 'field_values']);
		$now = now();

		foreach ($rows as $row) {
			$group = (string)$row->name;
			$raw = $row->field_values;
			$values = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
			if (!is_array($values)) {
				continue;
			}

			$stats['groups']++;

			foreach ($values as $innerKey => $value) {
				if (!is_string($innerKey) || $innerKey === '') {
					continue;
				}

				// Promote each legacy inner key to a globally unique flat key by
				// prefixing with `<group>_` (skip if already prefixed) — same rule the
				// rename migration applies, so the converter and rename produce
				// identical end states.
				$flatKey = self::flattenKey($group, $innerKey);
				$encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
				$existing = DB::table($targetTable)
					->where('key', $flatKey)
					->first(['value']);

				if ($existing === null) {
					DB::table($targetTable)->insert([
						'group'      => $group,
						'key'        => $flatKey,
						'value'      => $encoded,
						'created_at' => $now,
						'updated_at' => $now,
					]);
					$stats['rows_inserted']++;
				} else if ($existing->value !== $encoded) {
					DB::table($targetTable)
						->where('key', $flatKey)
						->update([
							'group'      => $group,
							'value'      => $encoded,
							'updated_at' => $now,
						]);
					$stats['rows_updated']++;
				}
			}
		}

		// Bust the per-process and persistent cache so subsequent setting() reads see the
		// freshly converted values.
		try {
			caching()->invalidate(Setting::class);
		} catch (Throwable) {
			// Cache backend unavailable — converter still succeeded; reads will be cold.
		}

		return $stats;
	}

	/**
	 * One-shot upgrade entry point for existing installations.
	 *
	 * Detects the old `settings` table shape (presence of `field_values`, absence of `value`),
	 * renames it to `<legacyTable>`, creates the new flat `<targetTable>` if missing,
	 * and runs the data converter.
	 *
	 * Designed to be called from the v19.0.0 Updater (database/updates/19.0.0/19.0.0/Updater.php)
	 * — wraps the three steps so the upgrade file stays readable.
	 *
	 * Returns the converter stats; no-op (zeros) on fresh installs that never had the
	 * legacy shape.
	 */
	public static function upgradeFromLegacy(
		string $legacyTable = 'settings_legacy',
		string $targetTable = 'settings',
	): array
	{
		// 1. Rename old shape → legacy if we still see it.
		$hasOldShape = (
			Schema::hasTable($targetTable)
			&& Schema::hasColumn($targetTable, 'field_values')
			&& !Schema::hasColumn($targetTable, 'value')
		);
		if ($hasOldShape && !Schema::hasTable($legacyTable)) {
			Schema::rename($targetTable, $legacyTable);
		}

		// 2. Create the new flat table if it doesn't exist yet.
		// Schema matches the post-Stage-4 shape: globally unique `key` column
		// (no composite (group, key) — the converter writes flat-prefixed keys
		// like 'app_email' that are unique on their own).
		if (!Schema::hasTable($targetTable)) {
			Schema::create($targetTable, function (Blueprint $table) {
				$table->id();
				$table->string('group')->default('general');
				$table->string('key')->unique();
				$table->json('value')->nullable();
				$table->timestamps();
				$table->index('group');
			});
		}

		// 3. Walk the legacy rows and write flat rows.
		return self::convertLegacyTable($legacyTable, $targetTable);
	}

	/**
	 * Look up a default from config/larapen/settings.php.
	 *
	 * Flat key map is built lazily on the first call and cached for the request lifetime.
	 */
	private function getConfigDefault(string $key): mixed
	{
		// Empty cache → try to populate. We do NOT cache an empty result, because
		// `config/larapen/routes.php` calls setting() at config-load time (top-level
		// `if (isMultiCountriesUrlsEnabled()) { … }`); at that moment Laravel hasn't
		// finished iterating sibling config files, so config('larapen.settings')
		// returns []. Caching that empty result would shadow real defaults forever
		// — instead, retry on each call until the populate yields a non-empty map.
		if (self::$configDefaults === null || self::$configDefaults === []) {
			$flat = [];
			$config = config('larapen.settings', []);
			if (is_array($config)) {
				foreach ($config as $group => $values) {
					if (!is_array($values)) {
						continue;
					}
					foreach ($values as $k => $v) {
						// Index under BOTH the flat key (Stage-4 canonical form,
						// `app_date_format`) AND the dotted key (legacy back-compat,
						// `app.date_format`) so both call shapes resolve to the
						// same default without forcing the rest of the codebase to
						// migrate in a single sweep.
						$flatKey = self::flattenKey((string)$group, (string)$k);
						$flat[$flatKey] = $v;
						$flat[$group . '.' . $k] = $v;
					}
				}
			}

			// Only freeze once we have something — otherwise stay null for the next call.
			self::$configDefaults = $flat !== [] ? $flat : null;

			if ($flat === []) {
				return null;
			}
		}

		return self::$configDefaults[$key] ?? null;
	}

	/**
	 * List settings (used by the public API endpoint that exposes settings to clients).
	 *
	 * Returns groups → inner-key → value, with `purchase_code` and the rest of
	 * optionsThatNeedToBeHidden() stripped. Empty groups are dropped.
	 */
	public function getEntries(): array
	{
		// Re-build the legacy nested response shape from the flat table — group rows
		// by their `group` column, strip the `<group>_` prefix from inner keys to
		// match the historical API contract.
		$grouped = [];
		try {
			$settings = Setting::query()->get(['group', 'key', 'value']);
			foreach ($settings as $row) {
				$value = $row->value;
				if (empty($value) && !is_numeric($value)) {
					continue;
				}
				$inner = str_starts_with($row->key, $row->group . '_')
					? substr($row->key, strlen($row->group) + 1)
					: $row->key;
				$grouped[$row->group][$inner] = $value;
			}
		} catch (QueryException|Throwable) {
			return [];
		}

		$hidden = Setting::optionsThatNeedToBeHidden();

		return collect($grouped)
			->mapWithKeys(function ($values, $group) use ($hidden) {
				$filtered = collect($values)
					->reject(fn ($v, $k) => in_array($k, $hidden, true) || ($group === 'app' && $k === 'purchase_code'));

				return [$group => $filtered];
			})
			->reject(fn ($v) => $v->count() <= 0)
			->toArray();
	}

	/**
	 * Get a single group or flat key for the public API endpoint.
	 *
	 * @throws ModelNotFoundException when the key is unknown, hidden, or empty.
	 */
	public function getEntry(string $key): array|string
	{
		$value = $this->get($key);

		if ($value === null) {
			throw new ModelNotFoundException;
		}

		$hidden = Setting::optionsThatNeedToBeHidden();

		// Group access — drop hidden keys + purchase_code, strip the group prefix
		// from each inner key so the public API contract stays nested-shape.
		if (is_array($value)) {
			$prefix = $key . '_';
			$rebuilt = [];
			foreach ($value as $k => $v) {
				$inner = str_starts_with($k, $prefix) ? substr($k, strlen($prefix)) : $k;
				if (in_array($inner, $hidden, true)) {
					continue;
				}
				if ($key === 'app' && $inner === 'purchase_code') {
					continue;
				}
				$rebuilt[$inner] = $v;
			}

			if (empty($rebuilt)) {
				throw new ModelNotFoundException;
			}

			return $rebuilt;
		}

		// Scalar access — refuse hidden keys outright. Strip group prefix to find
		// the inner key for the hidden-list comparison; supports both flat
		// (`mail_smtp_password`) and dotted (`mail.smtp_password`) input forms.
		if (is_string($value) || is_numeric($value)) {
			$inner = $key;
			if (str_contains($key, '.')) {
				$inner = substr($key, strrpos($key, '.') + 1);
			} else {
				// Strip the longest matching known group prefix.
				foreach (self::knownGroups() as $g) {
					if (str_starts_with($key, $g . '_')) {
						$inner = substr($key, strlen($g) + 1);
						break;
					}
				}
			}
			if (in_array($inner, $hidden, true) || $inner === 'purchase_code') {
				throw new ModelNotFoundException;
			}

			return (string)$value;
		}

		throw new ModelNotFoundException;
	}
}

<?php

namespace App\Helpers\Common;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Model Cache Manager
 *
 * Intelligent caching system that adapts to different cache drivers.
 * For drivers that support tags (Redis, Memcached, Database), it uses native tag-based invalidation.
 * For drivers that don't support tags (File), it uses versioning for cache invalidation.
 * This allows seamless switching between cache drivers without code changes.
 */
class CacheManager
{
	/**
	 * Cached driver support check result
	 *
	 * @var bool|null
	 */
	private bool|null $supportsTags = null;

	/**
	 * Sentinel value to distinguish null from cache miss
	 *
	 * @var string
	 */
	private const string NULL_CACHE_VALUE = '__LARAVEL_CACHE_NULL_SENTINEL__';

	/**
	 * Cache data with automatic invalidation strategy
	 *
	 * This method intelligently caches data using the best strategy for the current cache driver:
	 * - For the "redis", "memcached" or "database" driver: Uses cache tags for native invalidation
	 * - For the "file" driver: Uses versioning for invalidation
	 *
	 * @param object|string $model The model class or instance
	 * @param array $params Parameters that make the cache key unique (search terms, filters, etc.)
	 * @param callable $callback Function that returns the data to cache
	 * @param int|null $ttl Time to live in seconds (default: 1 hour)
	 * @return mixed The cached or freshly computed data
	 *
	 * @throws \InvalidArgumentException When callback is not callable
	 *
	 * @example
	 * $users = $cache->remember(User::class, [
	 *     'search' => 'john',
	 *     'country' => 'US',
	 *     'page' => 1
	 * ], function() {
	 *     return User::where('name', 'like', '%john%')->paginate();
	 * });
	 */
	public function remember(object|string $model, array $params, callable $callback, ?int $ttl = null): mixed
	{
		if (!is_callable($callback)) {
			throw new \InvalidArgumentException('Callback must be callable');
		}

		// Use provided TTL or get default
		$ttl = $ttl ?? $this->getDefaultTtl($model);

		// Wrap $callback data to handle null values
		$wrappedCallback = function () use ($callback) {
			return $this->wrapValue($callback());
		};

		if ($this->supportsTags()) {
			// Use cache tags for drivers that support them
			$key = $this->generateBaseKey($model, $params);
			$tags = $this->getModelTags($model);

			$data = Cache::tags($tags)->remember($key, $ttl, $wrappedCallback);
		} else {
			// Use versioning for the "file" driver
			$version = $this->getModelVersion($model);
			$baseKey = $this->generateBaseKey($model, $params);
			$versionedKey = $baseKey . '_v' . $version;

			$data = Cache::remember($versionedKey, $ttl, $wrappedCallback);
		}

		// Unwrap the callback data
		return $this->unwrapValue($data);
	}

	/**
	 * Store data in cache with automatic invalidation strategy
	 *
	 * Stores data using the appropriate method for the current cache driver.
	 *
	 * @param object|string $model The model class or instance
	 * @param array $params Parameters that make the cache key unique
	 * @param mixed $data The data to cache
	 * @param int|null $ttl Time to live in seconds (default: 1 hour)
	 * @return mixed The stored data
	 *
	 * @example
	 * $cache->put(User::class, ['search' => 'john'], $users, 7200);
	 */
	public function put(object|string $model, array $params, mixed $data, ?int $ttl = null): mixed
	{
		// Use provided TTL or get default
		$ttl = $ttl ?? $this->getDefaultTtl($model);

		// Wrap data to handle null values
		$wrappedData = $this->wrapValue($data);

		if ($this->supportsTags()) {
			$key = $this->generateBaseKey($model, $params);
			$tags = $this->getModelTags($model);

			Cache::tags($tags)->put($key, $wrappedData, $ttl);
		} else {
			$version = $this->getModelVersion($model);
			$baseKey = $this->generateBaseKey($model, $params);
			$versionedKey = $baseKey . '_v' . $version;

			Cache::put($versionedKey, $wrappedData, $ttl);
		}

		return $data;
	}

	/**
	 * Retrieve data from cache if exists
	 *
	 * Gets cached data using the appropriate strategy for the current driver.
	 *
	 * @param object|string $model The model class or instance
	 * @param array $params Parameters that make the cache key unique
	 * @return mixed|null The cached data or null if not found
	 */
	public function get(object|string $model, array $params): mixed
	{
		if ($this->supportsTags()) {
			$key = $this->generateBaseKey($model, $params);
			$tags = $this->getModelTags($model);

			$wrapped = Cache::tags($tags)->get($key);
		} else {
			$version = $this->getModelVersion($model);
			$baseKey = $this->generateBaseKey($model, $params);
			$versionedKey = $baseKey . '_v' . $version;

			$wrapped = Cache::get($versionedKey);
		}

		return $wrapped === null ? null : $this->unwrapValue($wrapped);
	}

	/**
	 * Check if data exists in the cache
	 *
	 * @param object|string $model The model class or instance
	 * @param array $params Parameters that make the cache key unique
	 * @return bool True if data exists
	 */
	public function has(object|string $model, array $params): bool
	{
		if ($this->supportsTags()) {
			$key = $this->generateBaseKey($model, $params);
			$tags = $this->getModelTags($model);

			$wrapped = Cache::tags($tags)->get($key);
		} else {
			$version = $this->getModelVersion($model);
			$baseKey = $this->generateBaseKey($model, $params);
			$versionedKey = $baseKey . '_v' . $version;

			$wrapped = Cache::get($versionedKey);
		}

		// Check if wrapped value exists (not null means it's cached)
		return ($wrapped !== null);
	}

	/**
	 * Remove specific cached data
	 *
	 * @param object|string $model The model class or instance
	 * @param array $params Parameters that make the cache key unique
	 * @return bool True if data was removed
	 */
	public function forget(object|string $model, array $params): bool
	{
		if ($this->supportsTags()) {
			$key = $this->generateBaseKey($model, $params);
			$tags = $this->getModelTags($model);

			return Cache::tags($tags)->forget($key);
		} else {
			$version = $this->getModelVersion($model);
			$baseKey = $this->generateBaseKey($model, $params);
			$versionedKey = $baseKey . '_v' . $version;

			return Cache::forget($versionedKey);
		}
	}

	/**
	 * Invalidate all cache entries for a specific model
	 *
	 * Uses the most efficient invalidation method for the current cache driver:
	 * - For the "redis", "memcached" or "database" driver: Flushes cache tags (instant invalidation)
	 * - For the "file" driver: Increments version number (makes old keys inaccessible)
	 *
	 * @param object|string $model The model class or instance to invalidate
	 * @return int|bool Version number for the "file" driver, true for tag-based drivers
	 *
	 * @example
	 * // After creating/updating/deleting a user
	 * $cache->invalidate(User::class);
	 */
	public function invalidate(object|string $model): bool|int
	{
		$modelName = $this->getModelName($model);
		$versionTtl = $this->getVersionTtl();

		if ($this->supportsTags()) {
			// Use cache tags for immediate invalidation
			$tags = $this->getModelTags($model);
			Cache::tags($tags)->flush();

			if ($this->isLoggingEnabled()) {
				Log::info("Cache invalidated using tags for model: {$modelName}");
			}

			return true;
		} else {
			// Use versioning for the "file" driver
			$versionKey = $this->getVersionPrefix() . $modelName;

			try {
				// Get the current version or initialize to 0
				$currentVersion = Cache::get($versionKey, 0);
				$currentVersion = castToInt($currentVersion);

				// Increment and store a new version
				$newVersion = $currentVersion + 1;
				Cache::put($versionKey, $newVersion, $versionTtl);

				if ($this->isLoggingEnabled()) {
					Log::info("Cache invalidated using versioning for model: {$modelName}, new version: {$newVersion}");
				}

				return $newVersion;
			} catch (\Exception $e) {
				// If the operation fails, use timestamp as a fallback version
				if ($this->isLoggingEnabled()) {
					Log::warning("Cache increment failed for {$modelName}, using timestamp fallback: " . $e->getMessage());
				}

				$newVersion = time();
				Cache::put($versionKey, $newVersion, $versionTtl);

				return $newVersion;
			}
		}
	}

	/**
	 * Invalidate cache for multiple models at once
	 *
	 * Efficiently invalidates cache for multiple models using the best method for each driver.
	 *
	 * @param array $models Array of model classes or instances
	 * @return array Results of invalidation operations
	 *
	 * @example
	 * // After an operation that affects both users and listings
	 * $cache->invalidateMultiple([User::class, Listing::class]);
	 */
	public function invalidateMultiple(array $models): array
	{
		$results = [];

		if ($this->supportsTags()) {
			// Batch invalidate using tags for efficiency
			$allTags = [];
			foreach ($models as $model) {
				$modelName = $this->getModelName($model);
				$tags = $this->getModelTags($model);
				$allTags = array_merge($allTags, $tags);
				$results[$modelName] = true;
			}

			// Remove duplicates and flush all tags at once
			$allTags = array_unique($allTags);
			Cache::tags($allTags)->flush();

			if ($this->isLoggingEnabled()) {
				Log::info("Batch cache invalidation using tags for models: " . implode(', ', array_keys($results)));
			}
		} else {
			// Individual invalidation for the "file" driver
			foreach ($models as $model) {
				$modelName = $this->getModelName($model);
				$results[$modelName] = $this->invalidate($model);
			}
		}

		return $results;
	}

	/**
	 * Clear all cache data (driver-specific implementation)
	 *
	 * Uses Laravel's flush() for a complete clear across drivers.
	 * Use with extreme caution - this invalidates ALL cached data.
	 *
	 * @return bool True if successful
	 */
	public function clearAllCache(): bool
	{
		try {
			Cache::flush();

			if ($this->isLoggingEnabled()) {
				Log::warning('Full cache cleared for all data. Performance impact expected');
			}

			return true;
		} catch (\Exception $e) {
			return $this->clearAllCacheManually();
		}
	}

	/**
	 * Clear all cache data (driver-specific implementation)
	 *
	 * For tag-based drivers: This is not needed since tags provide granular control
	 * For the "file" driver: Clears all version keys making all cache effectively invalid
	 *
	 * Use with extreme caution - this invalidates ALL cached data.
	 *
	 * @return bool True if successful
	 */
	public function clearAllCacheManually(): bool
	{
		try {
			if ($this->supportsTags()) {
				// For tag-based drivers, we could flush all tags, but it's better to be specific
				// This is a placeholder - in practice you'd want to track used tags
				if ($this->isLoggingEnabled()) {
					Log::info("Cache clearing not implemented for tag-based drivers. Use specific model invalidation");
				}
			} else {
				// Clear all version keys for the "file" driver
				$cachePath = storage_path('framework/cache/data');

				if (!is_dir($cachePath)) {
					return true; // Nothing to clear
				}

				// Get all cache files that look like version keys
				$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cachePath));

				$cleared = 0;
				foreach ($iterator as $file) {
					if ($file->isFile()) {
						$content = file_get_contents($file->getPathname());

						// Look for a version key pattern in file content
						if (str_contains($content, $this->getVersionPrefix())) {
							unlink($file->getPathname());
							$cleared++;
						}
					}
				}

				if ($this->isLoggingEnabled()) {
					Log::warning("Cleared {$cleared} version cache files. Performance impact expected");
				}
			}

			return true;
		} catch (\Exception $e) {
			if ($this->isLoggingEnabled()) {
				Log::error("Failed to clear cache: " . $e->getMessage());
			}

			return false;
		}
	}

	// PRIVATE

	/**
	 * Get default TTL for cached data
	 *
	 * @param object|string|null $model Optional model to get specific TTL
	 * @return int TTL in seconds
	 */
	private function getDefaultTtl(object|string|null $model = null): int
	{
		// Check for model-specific TTL
		if ($model) {
			$modelName = $this->getModelName($model);
			$modelConfig = config("cache-manager.models.{$modelName}");

			$defaultTtl = (int)($modelConfig['default_ttl'] ?? 0);
			if ($defaultTtl > 0) {
				return $defaultTtl;
			}
		}

		// Check for environment-specific TTL
		$environment = app()->environment();
		$envTtl = (int)config("cache-manager.environments.{$environment}.default_ttl");

		if ($envTtl > 0) {
			return $envTtl;
		}

		// Get global default TTL
		$defaultTtl = (int)config('cache-manager.default_ttl');

		$fallbackTtl = 3600; // 1 hour

		// Return global default or fallback TTL
		return $defaultTtl > 0 ? $defaultTtl : $fallbackTtl;
	}

	/**
	 * Get TTL for version keys
	 *
	 * @return int TTL in seconds
	 */
	private function getVersionTtl(): int
	{
		$fallbackTtl = 2592000; // 30 days

		// Check for environment-specific version TTL
		$environment = app()->environment();
		$envTtl = config("cache-manager.environments.{$environment}.version_ttl");

		if ($envTtl !== null) {
			return castToInt($envTtl, $fallbackTtl);
		}

		// Return global version TTL
		$ttl = config('cache-manager.version_ttl');

		return castToInt($ttl, $fallbackTtl);
	}

	/**
	 * Get version key prefix
	 *
	 * @return string
	 */
	private function getVersionPrefix(): string
	{
		$fallback = 'cache_version_';
		$versionPrefix = config('cache-manager.version_prefix', $fallback);

		return castToString($versionPrefix, $fallback);
	}

	/**
	 * Check if logging is enabled
	 *
	 * @return bool
	 */
	private function isLoggingEnabled(): bool
	{
		// Check for environment-specific override first (mirrors the lookup
		// pattern used by getDefaultTtl() / getVersionTtl()). The
		// `cache-manager.environments.{env}.enable_logging` value, when
		// present, fully overrides the global `cache-manager.enable_logging`
		// flag — so prod can ship with logging off even if a dev forgot to
		// unset the top-level toggle.
		$environment = app()->environment();
		$envFlag = config("cache-manager.environments.{$environment}.enable_logging");

		if ($envFlag !== null) {
			return castToBool($envFlag);
		}

		// Fall back to the global flag.
		return castToBool(config('cache-manager.enable_logging', false));
	}

	/**
	 * Get the current version number for a model (used only for the "file" driver)
	 *
	 * Retrieves the current version number for cache invalidation.
	 * If no version exists, creates version 1.
	 *
	 * @param object|string $model The model class or instance
	 * @return int Current version number
	 */
	private function getModelVersion(object|string $model): int
	{
		$modelName = $this->getModelName($model);
		$versionKey = $this->getVersionPrefix() . $modelName;

		// Get or create a version number with longer TTL
		return Cache::remember($versionKey, $this->getVersionTtl(), fn () => 1);
	}

	/**
	 * Check if the current cache driver supports tags
	 *
	 * Cache tags are supported by Redis, Memcached, DynamoDB, and Database drivers.
	 * File and Array drivers do not support tags.
	 *
	 * @return bool True if tags are supported
	 */
	private function supportsTags(): bool
	{
		// Reduces overhead of checking if a driver supports tags on every operation
		$cacheDriverCheck = (bool)config('cache-manager.performance.cache_driver_check', true);

		// Use cached result if available and caching is enabled
		if ($cacheDriverCheck && $this->supportsTags !== null) {
			return $this->supportsTags;
		}

		$storeName = config('cache.default');
		$storeConfig = config("cache.stores.{$storeName}");
		$driver = $storeConfig['driver'] ?? ''; // Always use inner driver, default empties (non-support)

		// Cache Tags (Supported Drivers)
		// https://laravel.com/docs/12.x/cache#cache-tags
		$supportedDrivers = ['redis', 'memcached'];
		$unsupportedDrivers = ['file', 'database', 'dynamodb', 'array'];

		if (in_array($driver, $unsupportedDrivers, true)) {
			$this->supportsTags = false;

			return $this->supportsTags;
		}

		$this->supportsTags = in_array($driver, $supportedDrivers, true);

		return $this->supportsTags;
	}

	/**
	 * Get cache tags for a model
	 *
	 * Generates appropriate cache tags for tag-based invalidation.
	 * Includes basic model tag plus relational tags if the model supports them.
	 * Override in models via getCacheTags() for custom logic.
	 *
	 * @param object|string $model The model class or instance
	 * @return array Array of cache tags
	 */
	private function getModelTags(object|string $model): array
	{
		$modelName = $this->getModelName($model);

		$tags = ["models:{$modelName}"]; // Single, namespaced tag

		// Add custom tags from model
		// Check if model has custom tags (e.g., via trait or method)
		try {
			$customTags = [];
			if (method_exists($model, 'getCacheTags')) {
				$customTags = is_string($model)
					? call_user_func([$model, 'getCacheTags'])
					: $model->getCacheTags();
			}
			$customTags = is_array($customTags) ? $customTags : [$customTags];
			$tags = array_merge($tags, $customTags);
		} catch (\Exception $e) {
			if ($this->isLoggingEnabled()) {
				Log::warning("Error getting cache tags for {$modelName}: " . $e->getMessage());
			}
		}

		// Remove duplicates and return
		return array_unique($tags);
	}

	/**
	 * Generate a base cache key from a model and parameters
	 *
	 * Creates a unique, deterministic cache key based on the model
	 * and provided parameters. Uses MD5 hash to keep key length manageable.
	 * Or uses SHA-256 hash to minimize collision risk, and short the hash
	 * to keep key length manageable (since the first 16 chars are enough for uniqueness).
	 *
	 * @param object|string $model The model class or instance
	 * @param array $params Parameters to include in the key
	 * @return string Base cache key (without a version)
	 */
	private function generateBaseKey(object|string $model, array $params): string
	{
		$modelName = $this->getModelName($model);

		// Sort params to ensure consistent key generation regardless of order
		ksort($params);

		// Get a configured serialization method
		$serializeMethod = config('cache-manager.performance.serialize_method', 'serialize');

		// Hash serialized parameters using a configured algorithm
		$serializedParams = ($serializeMethod === 'json')
			? json_encode($params)
			: serialize($params);
		$paramHash = $this->hashValue($serializedParams);

		// Truncate hash for brevity (Note: first 16 chars sufficient for uniqueness)
		$shortHash = substr($paramHash, 0, 32);

		return "{$modelName}_{$shortHash}";
	}

	/**
	 * Hash a value using the configured algorithm
	 *
	 * Retrieves the hash algorithm from config('cache-manager.hash_algorithm').
	 * Falls back to 'md5' if invalid. Supports PHP 8.1+ for xxHash variants.
	 *
	 * @param string $data The data to hash (e.g., serialized params)
	 * @return string The hex-encoded hash
	 */
	private function hashValue(string $data): string
	{
		$allowedAlgorithms = ['md5', 'sha1', 'sha256', 'xxh64', 'xxh128'];

		$fallbackAlgorithm = 'md5';
		$algorithm = config('cache-manager.hash_algorithm', $fallbackAlgorithm);

		if (!in_array($algorithm, $allowedAlgorithms, true)) {
			Log::warning("Invalid hash algorithm '{$algorithm}' in cache-manager config; falling back to '{$fallbackAlgorithm}'");
			$algorithm = $fallbackAlgorithm;
		}

		return hash($algorithm, $data);
	}

	/**
	 * Extract model name from class or instance
	 *
	 * Handles both class strings and object instances to get a
	 * consistent model name for cache keys.
	 *
	 * @param object|string $model The model class or instance
	 * @return string Lowercase model name
	 *
	 * @throws \InvalidArgumentException When model parameter is invalid
	 */
	private function getModelName(object|string $model): string
	{
		if (is_string($model)) {
			// Handle class string like "App\Models\User"
			return strtolower(class_basename($model));
		} else if (is_object($model)) {
			// Handle model instance
			return strtolower(class_basename(get_class($model)));
		}

		throw new \InvalidArgumentException('Model must be a class string or object instance');
	}

	/**
	 * Wrap value to handle null caching
	 *
	 * Laravel's cache drivers ignore null values, so we wrap them
	 * in an array to distinguish between "cache miss" and "cached null".
	 *
	 * Info: https://laravel.com/docs/12.x/cache#determining-item-existence
	 *
	 * @param mixed $value The value to wrap
	 * @return array Wrapped value
	 */
	private function wrapValue(mixed $value): array
	{
		return [
			'value'   => $value === null ? self::NULL_CACHE_VALUE : $value,
			'is_null' => $value === null,
		];
	}

	/**
	 * Unwrap value from a cache
	 *
	 * Extracts the original value from the wrapper, properly handling null.
	 *
	 * @param mixed $wrapped The wrapped value from the cache
	 * @return mixed The original value
	 */
	private function unwrapValue(mixed $wrapped): mixed
	{
		// If not wrapped (backward compatibility or direct cache access)
		if (!is_array($wrapped) || !isset($wrapped['is_null'])) {
			return $wrapped;
		}

		// Return null if it was explicitly cached as null
		if ($wrapped['is_null'] === true) {
			return null;
		}

		return $wrapped['value'];
	}

	// FOR DEBUG

	/**
	 * Get cache statistics for a model
	 *
	 * Returns information about the current cache state for debugging.
	 * Information differs based on a cache driver.
	 *
	 * @param object|string $model The model class or instance
	 * @return array Statistics about cache state
	 */
	public function getStats(object|string $model): array
	{
		$modelName = $this->getModelName($model);
		$supportsTags = $this->supportsTags();

		$stats = [
			'model'               => $modelName,
			'driver'              => config('cache.default'),
			'supports_tags'       => $supportsTags,
			'invalidation_method' => $supportsTags ? 'tags' : 'versioning',
		];

		if ($supportsTags) {
			$stats['tags'] = $this->getModelTags($model);
		} else {
			$versionKey = $this->getVersionPrefix() . $modelName;
			$stats['version_key'] = $versionKey;
			$stats['current_version'] = $this->getModelVersion($model);
			$stats['version_exists'] = Cache::has($versionKey);
		}

		return $stats;
	}

	/**
	 * Get current cache driver information
	 *
	 * @return array Information about the current cache configuration
	 */
	public function getDriverInfo(): array
	{
		$driver = config('cache.default');
		$driverConfig = config("cache.stores.{$driver}");

		return [
			'current_driver'        => $driver,
			'driver_config'         => $driverConfig,
			'supports_tags'         => $this->supportsTags(),
			'invalidation_strategy' => $this->supportsTags() ? 'tags' : 'versioning',
		];
	}
}

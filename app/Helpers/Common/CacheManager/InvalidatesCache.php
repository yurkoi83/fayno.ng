<?php

namespace App\Helpers\Common\CacheManager;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * Trait for automatic cache invalidation on model changes
 *
 * This trait automatically invalidates model-specific cache when
 * the model is saved, updated, or deleted. It integrates with
 * the CacheManager service to handle cache versioning.
 *
 * @example
 * class User extends Model
 * {
 *     use InvalidatesCache;
 *
 *     // Optional: specify related models that should also be invalidated
 *     protected array $invalidatesCacheFor = [Listing::class, Comment::class];
 *
 *     public function getCacheTags()
 *     {
 *         $tags = ['users:premium']; // Custom: premium user caches
 *         if ($this->category_id) {
 *             $tags[] = "categories:{$this->category_id}"; // Flush category caches
 *         }
 *         return $tags;
 *     }
 * }
 */
trait InvalidatesCache
{
	protected static bool $invalidatingCache = false;

	/**
	 * Boot the trait and register model event listeners
	 *
	 * Laravel automatically calls this method when the model is booted.
	 * It sets up event listeners for saved (created or updated), deleted, and restored events.
	 * Each event is only registered if its corresponding config setting is enabled.
	 *
	 * Save/update/delete - cache clears automatically!
	 * $user = User::create(['name' => 'John']); // Cache invalidated
	 * $user->update(['name' => 'Jane']); // Cache invalidated
	 * $user->delete(); // Cache invalidated
	 */
	protected static function bootInvalidatesCache(): void
	{
		// Get auto-invalidation config
		$autoInvalidationConfig = config('cache-manager.auto_invalidation', []);
		$globallyEnabled = $autoInvalidationConfig['enabled'] ?? true;

		// If globally disabled, skip all event listeners
		if (!$globallyEnabled) {
			return;
		}

		$isEnabledOnCreate = $autoInvalidationConfig['on_create'] ?? true;
		$isEnabledOnUpdate = $autoInvalidationConfig['on_update'] ?? true;
		$isEnabledOnDelete = $autoInvalidationConfig['on_delete'] ?? true;
		$isEnabledOnRestore = $autoInvalidationConfig['on_restore'] ?? true;

		if ($isEnabledOnCreate && $isEnabledOnUpdate) {
			// Register a 'saved' event listener if enabled
			static::saved(function ($model) {
				/** @var static $model */
				$model->invalidateModelCache();
			});
		} else {
			// Register a 'created' event listener if enabled
			if ($isEnabledOnCreate) {
				static::created(function ($model) {
					$model->invalidateModelCache();
				});
			}
			// Register a 'updated' event listener if enabled
			if ($isEnabledOnUpdate) {
				static::updated(function ($model) {
					$model->invalidateModelCache();
				});
			}
		}

		// Register a 'deleted' event listener if enabled
		if ($isEnabledOnDelete) {
			static::deleted(function ($model) {
				/** @var static $model */
				$model->invalidateModelCache();
			});
		}

		// Register a 'restored' event listener if enabled (only if the model uses SoftDeletes)
		if ($isEnabledOnRestore) {
			if (self::usesSoftDeletes()) {
				static::restored(function ($model) {
					/** @var static $model */
					$model->invalidateModelCache();
				});
			}
		}
	}

	/**
	 * Invalidate cache for this model and related models
	 *
	 * This method handles the actual cache invalidation logic.
	 * It can be called manually if needed, or is automatically
	 * called by the model event listeners. Includes recursion guard.
	 *
	 * @return void
	 */
	protected function invalidateModelCache(): void
	{
		// Recursion guard: Skip if already invalidating to prevent loops
		if (property_exists(static::class, 'invalidatingCache') && static::$invalidatingCache) {
			Log::debug("Skipping recursive cache invalidation for " . static::class);

			return;
		}

		static::$invalidatingCache = true;

		try {
			// Always invalidate cache for the current model
			$modelsToInvalidate = [static::class];

			// Add related models from the 'invalidatesCacheFor' property (if specified)
			if (property_exists($this, 'invalidatesCacheFor')) {
				$this->invalidatesCacheFor = ensureCastedToArray($this->invalidatesCacheFor);
				$modelsToInvalidate = array_merge($modelsToInvalidate, $this->invalidatesCacheFor);
			}

			// Invalidate cache for all specified models
			caching()->invalidateMultiple($modelsToInvalidate);
		} finally {
			static::$invalidatingCache = false;
		}
	}

	/**
	 * Manually invalidate cache for this model
	 *
	 * Useful for manual cache invalidation outside model events.
	 * This bypasses the auto-invalidation config settings.
	 *
	 * @return int|bool The new version number for the "file" driver, true for tag-based drivers
	 *
	 * @example
	 * $user = User::find(1);
	 * $user->invalidateCache(); // Manually clear cache
	 */
	public function invalidateCache(): int|bool
	{
		return caching()->invalidate(static::class);
	}

	// EXTRA METHODS

	/**
	 * Check if cache exists for specific parameters
	 *
	 * @param array $params Parameters to check
	 * @return bool True if cached data exists
	 *
	 * @example
	 * if ($picture->hasCachedData(['listing_id' => 123])) {
	 *     // Cache exists
	 * }
	 */
	public function hasCachedData(array $params): bool
	{
		return caching()->has(static::class, $params);
	}

	/**
	 * Get cached data for specific parameters
	 *
	 * @param array $params Parameters to retrieve
	 * @return mixed|null Cached data or null if not found
	 *
	 * @example
	 * $cached = $picture->getCachedData(['listing_id' => 123]);
	 */
	public function getCachedData(array $params): mixed
	{
		return caching()->get(static::class, $params);
	}

	/**
	 * Cache data for this model
	 *
	 * @param array $params Parameters that make the cache key unique
	 * @param mixed $data Data to cache
	 * @param int|null $ttl Time to live in seconds (uses config default if null)
	 * @return mixed The cached data
	 *
	 * @example
	 * $picture->cacheData(['id' => 1], $pictureData, 3600);
	 */
	public function cacheData(array $params, mixed $data, ?int $ttl = null): mixed
	{
		return caching()->put(static::class, $params, $data, $ttl);
	}

	/**
	 * Remember cached data with callback
	 *
	 * Retrieves data from cache or executes callback if not cached.
	 *
	 * @param array $params Parameters that make the cache key unique
	 * @param callable $callback Function to execute if the cache misses
	 * @param int|null $ttl Time to live in seconds (uses config default if null)
	 * @return mixed Cached or fresh data
	 *
	 * @example
	 * $data = $picture->rememberCachedData(['id' => 1], function() {
	 *     return $this->expensiveOperation();
	 * }, 3600);
	 */
	public function rememberCachedData(array $params, callable $callback, ?int $ttl = null): mixed
	{
		return caching()->remember(static::class, $params, $callback, $ttl);
	}

	/**
	 * Forget specific cached data
	 *
	 * Removes a specific cache entry for this model.
	 *
	 * @param array $params Parameters of the cache to remove
	 * @return bool True if the cache was removed
	 *
	 * @example
	 * $picture->forgetCachedData(['id' => 1]);
	 */
	public function forgetCachedData(array $params): bool
	{
		return caching()->forget(static::class, $params);
	}

	// DEBUG

	/**
	 * Get cache statistics for this model
	 *
	 * Returns debugging information about the cache state.
	 *
	 * @return array Cache statistics
	 *
	 * @example
	 * $stats = $picture->getCacheStats();
	 * // Returns: ['model' => 'picture', 'driver' => 'file', 'supports_tags' => false, ...]
	 */
	public function getCacheStats(): array
	{
		return caching()->getStats(static::class);
	}

	// PRIVATE

	/**
	 * Check if the model uses SoftDeletes trait
	 *
	 * @return bool True if the model uses SoftDeletes
	 */
	private static function usesSoftDeletes(): bool
	{
		return in_array(SoftDeletes::class, class_uses_recursive(static::class), true);
	}
}

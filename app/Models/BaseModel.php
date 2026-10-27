<?php

namespace App\Models;

use App\Helpers\Common\CacheManager\InvalidatesCache;
use App\Models\Builders\HasGlobalBuilder;
use App\Models\Traits\Common\HasActiveColumn;
use App\Models\Traits\Common\HasVerifiedAtColumn;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
	use HasActiveColumn, HasVerifiedAtColumn;
	use HasGlobalBuilder;
	use InvalidatesCache;

	/**
	 * Enable/disable cache invalidation per model
	 */
	protected bool $enableCacheInvalidation = true;

	/**
	 * Indicates if the model should use UUID as primary key
	 */
	protected bool $useUuid = false;

	/**
	 * Indicates if the model should automatically generate slugs
	 */
	protected bool $autoSlug = false;

	/**
	 * The attribute to use for slug generation
	 */
	protected string $slugSource = 'name';

	/**
	 * Boot the base model
	 *
	 * @return void
	 */
	protected static function boot()
	{
		parent::boot();

		static::creating(function ($model) {
			// Auto-generate UUID if enabled
			if ($model->useUuid) {
				if (empty($model->{$model->getKeyName()})) {
					$model->{$model->getKeyName()} = Str::uuid()->toString();
				}
			}

			// Auto-generate slug if enabled
			if ($model->autoSlug) {
				if (empty($model->slug) && isset($model->{$model->slugSource})) {
					$model->slug = Str::slug($model->{$model->slugSource})->toString();
				}
			}
		});

		static::updating(function ($model) {
			// Update slug if source field changed and autoSlug is enabled
			if ($model->autoSlug) {
				if ($model->isDirty($model->slugSource)) {
					$model->slug = Str::slug($model->{$model->slugSource})->toString();
				}
			}
		});
	}

	/**
	 * Check if cache invalidation is enabled for this model
	 */
	public function shouldInvalidateCache(): bool
	{
		return $this->enableCacheInvalidation;
	}

	/**
	 * Scope to filter non-soft-deleted records
	 */
	#[Scope]
	public function notTrashed(Builder $query): void
	{
		if (in_array(SoftDeletes::class, class_uses_recursive($this))) {
			$query->whereNull('deleted_at');
		}
	}

	/**
	 * Get the model's fillable attributes excluding timestamps
	 */
	public function getFillableWithoutTimestamps(): array
	{
		return array_diff($this->getFillable(), [
			$this->getCreatedAtColumn(),
			$this->getUpdatedAtColumn(),
			'deleted_at',
		]);
	}

	/**
	 * Convert the model to an array with only fillable attributes
	 */
	public function toFillableArray(): array
	{
		return $this->only($this->getFillable());
	}
}

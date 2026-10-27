<?php

namespace App\Models;

use App\Enums\FieldBelongsTo;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Common\HasTranslations;
use App\Models\Traits\FieldTrait;
use App\Observers\FieldObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([FieldObserver::class])]
#[ScopedBy([ActiveScope::class])]
class Field extends BaseModel
{
	use AppendsTrait, HasTranslations;
	use FieldTrait;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'fields';

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $guarded = ['id'];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'belongs_to',
		'machine_name',
		'name',
		'type',
		'max',
		'default_value',
		'required',
		'use_as_filter',
		'help',
		'active',
	];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'belongs_to' => FieldBelongsTo::class,
		];
	}

	/**
	 * @var array<int, string>
	 */
	public array $translatable = ['name', 'default_value', 'help'];

	// Optional: Specify related models that should be invalidated when this model changes
	protected array $invalidatesCacheFor = [
		FieldOption::class,
		CategoryField::class,
		ListingValue::class,
	];

	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function category(): BelongsTo
	{
		return $this->belongsTo(Category::class, 'category_id');
	}

	public function categories(): BelongsToMany
	{
		return $this->belongsToMany(Category::class, 'category_field', 'field_id', 'category_id');
	}

	public function options(): HasMany
	{
		return $this->hasMany(FieldOption::class, 'field_id')
			->orderBy('position')
			->orderBy('value');
	}

	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	public function scopeByMachineName(Builder $query, string $machineName, string|FieldBelongsTo $belongsTo = FieldBelongsTo::Listing): Builder
	{
		$scope = $belongsTo instanceof FieldBelongsTo ? $belongsTo->value : $belongsTo;

		return $query->where('belongs_to', $scope)->where('machine_name', $machineName);
	}

	/**
	 * Resolve a Field by its machine_name (scoped to a belongs_to bucket).
	 * Returns null when no match exists — callers must handle missing fields,
	 * since a referenced machine_name could have been renamed or deleted.
	 */
	public static function findByMachineName(string $machineName, string|FieldBelongsTo $belongsTo = FieldBelongsTo::Listing): ?self
	{
		return static::query()
			->withoutGlobalScopes()
			->byMachineName($machineName, $belongsTo)
			->first();
	}

	/*
	|--------------------------------------------------------------------------
	| ACCESSORS | MUTATORS
	|--------------------------------------------------------------------------
	*/

	/*
	|--------------------------------------------------------------------------
	| OTHER PRIVATE METHODS
	|--------------------------------------------------------------------------
	*/
}

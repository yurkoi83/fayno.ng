<?php

namespace App\Models;

use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Common\HasTranslations;
use App\Observers\FieldOptionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([FieldOptionObserver::class])]
class FieldOption extends BaseModel
{
	use AppendsTrait, HasTranslations;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'fields_options';

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
		'field_id',
		'value',
		'parent_id',
		'position',
	];

	/**
	 * @var array<int, string>
	 */
	public array $translatable = ['value'];

	// Optional: Specify related models that should be invalidated when this model changes
	protected array $invalidatesCacheFor = [
		Field::class,
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
	public function field(): BelongsTo
	{
		return $this->belongsTo(Field::class, 'field_id');
	}

	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/

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

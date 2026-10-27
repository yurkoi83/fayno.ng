<?php

namespace App\Models;

use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Common\HasTranslations;
use App\Models\Traits\MetaTagTrait;
use App\Observers\MetaTagObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ObservedBy([MetaTagObserver::class])]
#[ScopedBy([ActiveScope::class])]
class MetaTag extends BaseModel
{
	use AppendsTrait, HasTranslations;
	use MetaTagTrait;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'meta_tags';

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
	protected $fillable = ['page', 'title', 'description', 'keywords', 'active'];

	/**
	 * @var array<int, string>
	 */
	public array $translatable = ['title', 'description', 'keywords'];

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

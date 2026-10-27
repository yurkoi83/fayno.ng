<?php

namespace App\Models;

use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Observers\MenuObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([MenuObserver::class])]
#[ScopedBy([ActiveScope::class])]
class Menu extends BaseModel
{
	use AppendsTrait;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'menus';

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
		'name',
		'location',
		'description',
		'active',
		'position',
	];

	// Optional: Specify related models that should be invalidated when this model changes
	protected array $invalidatesCacheFor = [
		MenuItem::class,
	];

	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'active'   => 'boolean',
			'position' => 'integer',
		];
	}

	public static function getAvailableLocations(): array
	{
		$predefined = static::getLocations();

		// Get locations that are already taken
		$takenLocations = static::pluck('location')->toArray();

		return array_diff_key($predefined, array_flip($takenLocations));
	}

	public static function getLocationDisplayName(string $location): string
	{
		$locations = static::getLocations();

		return $locations[$location] ?? ucfirst($location) . ' Menu';
	}

	public static function getLocations(): array
	{
		$locations = config('larapen.menu.locations');

		return (array)$locations;
	}

	public function canChangeLocation(string $newLocation): bool
	{
		if (isset($this->location) && $this->location === $newLocation) {
			return true;
		}

		return !static::where('location', $newLocation)->exists();
	}

	public function getVisibleRootMenuItems()
	{
		return $this->rootMenuItems()
			->isActive()
			->with([
				// MenuItems still use the nested-set `lft` column for tree ordering;
				// only the parent `menus` table dropped its lft/rgt/depth.
				'children' => fn ($query) => $query->isActive()->orderBy('lft'),
			])
			->get()
			->filter(fn ($item) => $item->is_visible);
	}

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function menuItems(): HasMany
	{
		return $this->hasMany(MenuItem::class, 'menu_id');
	}

	public function rootMenuItems(): HasMany
	{
		return $this->menuItems()->columnIsEmpty('parent_id')->orderBy('lft')->orderBy('id');
	}

	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	#[Scope]
	protected function isActive(Builder $query): void
	{
		$query->where('active', 1);
	}

	#[Scope]
	protected function forLocation(Builder $query, string $location): void
	{
		$query->where('location', '=', $location);
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

<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Helpers\Common\JsonUtils;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\CategoryTrait;
use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Common\HasNestedSelectOptions;
use App\Models\Traits\Common\HasTranslations;
use App\Observers\CategoryObserver;
use App\Services\Image\ImageStore;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CategoryObserver::class])]
#[ScopedBy([ActiveScope::class])]
class Category extends BaseModel
{
	/**
	 * ImageStore profile for the category icon. See config/larapen/images.php.
	 */
	public const string IMAGE_PROFILE = 'category-icon';

	use AppendsTrait, Sluggable, SluggableScopeHelpers;
	use CategoryTrait;
	use HasNestedSelectOptions, HasTranslations;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'categories';

	/**
	 * @var array<int, string>
	 */
	protected $appends = ['image_url', 'url'];

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
		'parent_id',
		'name',
		'slug',
		'description',
		'hide_description',
		'image_path',
		'icon_class',
		'seo_title',
		'seo_description',
		'seo_keywords',
		'lft',
		'rgt',
		'depth',
		'type',
		'is_for_permanent',
		'active',
	];

	/**
	 * @var array<int, string>
	 */
	public array $translatable = ['name', 'description', 'seo_title', 'seo_description', 'seo_keywords'];

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
			'type' => CategoryType::class,
		];
	}

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function listings(): HasMany
	{
		$hasMany = $this->hasMany(Listing::class, 'category_id');
		if (!isAdminPanelRoute()) {
			$hasMany->where('country_code', '=', config('country.code'));
		}

		return $hasMany;
	}

	public function children(): HasMany
	{
		return $this->hasMany(Category::class, 'parent_id')->orderBy('lft');
	}

	public function parent(): BelongsTo
	{
		return $this->belongsTo(Category::class, 'parent_id');
	}

	public function fields(): BelongsToMany
	{
		return $this->belongsToMany(Field::class, 'category_field', 'category_id', 'field_id');
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

	/*
	|--------------------------------------------------------------------------
	| ACCESSORS | MUTATORS
	|--------------------------------------------------------------------------
	*/
	protected function name(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (isset($this->attributes['name']) && !JsonUtils::isJson($this->attributes['name'])) {
					return $this->attributes['name'];
				}

				return $value;
			},
		);
	}

	protected function iconClass(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$defaultIconClass = 'bi bi-folder-fill';

				if (empty($value)) {
					return $defaultIconClass;
				}

				$defaultFontIconSet = config('larapen.core.defaultFontIconSet', 'bootstrap');

				// This part will be removed at: 2022-10-14
				$filePath = config("larapen.core.fontIconSet.{$defaultFontIconSet}.path");
				$buffer = file_get_contents($filePath);

				$ifVersion = config("larapen.core.fontIconSet.{$defaultFontIconSet}.version");
				$ifVersion = str_replace('.', '\.', $ifVersion);

				$matches = [];
				preg_match('#version:[^\']+\'' . $ifVersion . '\',[^i]+icons:[^\[]*\[([^]]+)]#s', $buffer, $matches);
				$iClasses = $matches[1] ?? '';
				$iClasses = str_replace("'", '', $iClasses);
				$iClasses = preg_replace('#[\n\t]*#', '', $iClasses);

				$iClassesArray = array_map('trim', explode(',', $iClasses));

				if (!empty($iClassesArray)) {
					if (!in_array($value, $iClassesArray)) {
						return $defaultIconClass;
					}
				}

				return $value;
			},
		);
	}

	protected function description(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (isset($this->attributes['description']) && !JsonUtils::isJson($this->attributes['description'])) {
					return $this->attributes['description'];
				}

				return $value;
			},
		);
	}

	/**
	 * Category icons flow through the `category-icon` ImageStore profile
	 * (config/larapen/images.php). Upload controllers call
	 * `ImageStore::store($file, Category::IMAGE_PROFILE, $dir)` and
	 * persist the resulting `masterPath` on `image_path`. The setter
	 * stays pass-through.
	 */
	protected function imagePath(): Attribute
	{
		return Attribute::make(
			get: fn ($value, $attributes) => $this->getImage($value, $attributes),
		);
	}

	protected function imageUrl(): Attribute
	{
		return Attribute::make(
			get: fn () => app(ImageStore::class)
				->url($this->getRawOriginal('image_path'), self::IMAGE_PROFILE, 'master'),
		);
	}

	protected function url(): Attribute
	{
		return Attribute::make(
			get: function () {
				return urlGen()->category($this);
			},
		);
	}

	/*
	|--------------------------------------------------------------------------
	| OTHER PRIVATE METHODS
	|--------------------------------------------------------------------------
	*/
	private function getImage($value, $attributes)
	{
		// OLD PATH
		$oldValue = $this->getImageFromOriginPath($value);
		if (!empty($oldValue)) {
			return $oldValue;
		}

		// NEW PATH
		if (empty($value)) {
			$value = $attributes['image_path'] ?? null;
		}

		$disk = StorageDisk::getDisk();

		$defaultIcon = 'app/default/categories/fa-folder-default.png';
		$skin = getFrontSkin(request()->input('skin'));
		$defaultSkinnedIcon = "app/default/categories/fa-folder-{$skin}.png";

		// File path is empty
		if (empty($value)) {
			if ($disk->exists($defaultSkinnedIcon)) {
				return $defaultSkinnedIcon;
			}

			return $defaultIcon;
		}

		// File not found
		if (!$disk->exists($value)) {
			if ($disk->exists($defaultSkinnedIcon)) {
				return $defaultSkinnedIcon;
			}

			return $defaultIcon;
		}

		// If the Category contains a skinnable icon,
		// Change it by the selected skin icon.
		if (str_contains($value, 'app/categories/') && !str_contains($value, '/custom/')) {
			$pattern = '/app\/categories\/[^\/]+\//iu';
			$replacement = "app/categories/{$skin}/";
			$value = preg_replace($pattern, $replacement, $value);
		}

		// (Optional)
		// If the Category contains a skinnable default icon,
		// Change it by the selected skin default icon.
		if (str_contains($value, 'app/default/categories/fa-folder-')) {
			$pattern = '/app\/default\/categories\/fa-folder-[^.]+\./iu';
			$replacement = "app/default/categories/fa-folder-{$skin}.";
			$value = preg_replace($pattern, $replacement, $value);
		}

		if (!$disk->exists($value)) {
			if ($disk->exists($defaultSkinnedIcon)) {
				return $defaultSkinnedIcon;
			}

			return $defaultIcon;
		}

		return $value;
	}

	/**
	 * Category icons images from the original version
	 * Only the file name is set in the Category 'image_path' field
	 * Example: fa-car.png
	 */
	private function getImageFromOriginPath($value): ?string
	{
		// Fix path
		$skin = setting('style_skin', 'default');
		$value = "app/categories/{$skin}/{$value}";

		$disk = StorageDisk::getDisk();
		if (!$disk->exists($value)) {
			return null;
		}

		return $value;
	}
}

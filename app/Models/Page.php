<?php

namespace App\Models;

use App\Casts\DateTimeCast;
use App\Enums\PageType;
use App\Helpers\Common\Files\Storage\StorageDisk;
use App\Helpers\Common\JsonUtils;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Common\HasTranslations;
use App\Observers\PageObserver;
use App\Services\Image\ImageStore;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

#[ObservedBy([PageObserver::class])]
#[ScopedBy([ActiveScope::class])]
class Page extends BaseModel
{
	/**
	 * ImageStore profile for the page header background image.
	 * See config/larapen/images.php.
	 */
	public const string IMAGE_PROFILE = 'page-header-bg';

	use AppendsTrait, HasTranslations, Sluggable, SluggableScopeHelpers;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'pages';

	/**
	 * @var array<int, string>
	 */
	protected $appends = ['image_url', 'url', 'is_external'];

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
		'type',
		'name',
		'slug',
		'image_path',
		'title',
		'content',
		'external_link',
		'name_color',
		'title_color',
		'target',
		'seo_title',
		'seo_description',
		'seo_keywords',
		'active',
		'position',
	];

	/**
	 * @var array<int, string>
	 */
	public array $translatable = ['name', 'title', 'content', 'seo_title', 'seo_description', 'seo_keywords'];

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
			'type'       => PageType::class,
			'position'   => 'integer',
			'active'     => 'boolean',
			'created_at' => DateTimeCast::class,
			'updated_at' => DateTimeCast::class,
		];
	}

	/**
	 * Return the sluggable configuration array for this model.
	 */
	public function sluggable(): array
	{
		return [
			'slug' => [
				'source' => ['slug', 'name'],
			],
		];
	}

	public function hasChildren(): bool
	{
		return $this->children()->isActive()->count() > 0;
	}

	public function getActiveChildren()
	{
		return $this->children()->isActive()->get();
	}

	// Hierarchical helpers — recursion via `parent_id` since the nested-set
	// `lft`/`rgt` columns were dropped from the pages table.

	public function getAncestors(): SupportCollection
	{
		$ancestors = collect();
		$cursor = $this->parent;
		while ($cursor) {
			$ancestors->push($cursor);
			$cursor = $cursor->parent;
		}

		return $ancestors->reverse();
	}

	public function getDescendants(): SupportCollection
	{
		$descendants = collect();
		foreach ($this->children as $child) {
			$descendants->push($child);
			$descendants = $descendants->merge($child->getDescendants());
		}

		return $descendants;
	}

	public function getBreadcrumbs(): SupportCollection
	{
		return $this->getAncestors()->push($this);
	}

	// Get hierarchical name for display
	public function getHierarchicalName(): string
	{
		$ancestors = $this->getAncestors();
		$names = $ancestors->pluck('name')->toArray();
		$names[] = $this->name ?? null;

		return implode(' → ', $names);
	}

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function parent(): BelongsTo
	{
		return $this->belongsTo(Page::class, 'parent_id');
	}

	public function children(): HasMany
	{
		return $this->hasMany(Page::class, 'parent_id')->orderBy('position');
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
	protected function type(Builder $query, PageType|string|null $type): void
	{
		$value = $type instanceof PageType ? $type->value : $type;
		$query->where('type', $value)->orderByDesc('id');
	}

	#[Scope]
	protected function roots(Builder $query): void
	{
		$query->whereNull('parent_id');
	}

	#[Scope]
	protected function withSlug(Builder $query): void
	{
		$query->whereNotNull('slug')->where('slug', '!=', '');
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

	protected function title(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (isset($this->attributes['title']) && !JsonUtils::isJson($this->attributes['title'])) {
					return $this->attributes['title'];
				}

				return $value;
			},
			set: function ($value) {
				$name = $this->name ?? null;

				return empty($value) ? $name : $value;
			},
		);
	}

	protected function content(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (isset($this->attributes['content']) && !JsonUtils::isJson($this->attributes['content'])) {
					return $this->attributes['content'];
				}

				return $value;
			},
		);
	}

	protected function imagePath(): Attribute
	{
		return Attribute::make(
			get: function ($value, $attributes) {
				if (empty($value)) {
					$value = $attributes['image_path'] ?? null;
				}
				if (empty($value)) {
					return null;
				}
				$disk = StorageDisk::getDisk();
				if (!$disk->exists($value)) {
					$value = null;
				}

				return $value;
			},
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
				if (!empty($this->external_link)) {
					return $this->external_link;
				}

				return urlGen()->page($this);
			},
		);
	}

	protected function isExternal(): Attribute
	{
		return Attribute::make(
			get: fn () => !empty($this->external_link),
		);
	}

	protected function seoTitle(): Attribute
	{
		return Attribute::make(
			get: fn () => $this->seo_title ?? $this->title ?? $this->name ?? null,
		);
	}

	protected function seoDescription(): Attribute
	{
		$content = $this->content ?? '';
		$content = strip_tags($content);

		return Attribute::make(
			get: fn () => $this->seo_description ?? str($content)->limit(160)->toString(),
		);
	}

	protected function seoKeywords(): Attribute
	{
		return Attribute::make(
			get: fn () => $this->seo_keywords ?? '',
		);
	}

	/*
	|--------------------------------------------------------------------------
	| OTHER PRIVATE METHODS
	|--------------------------------------------------------------------------
	*/
}

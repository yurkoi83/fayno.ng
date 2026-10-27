<?php

namespace App\Models;

use App\Enums\LanguageDirection;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Observers\LanguageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

#[ObservedBy([LanguageObserver::class])]
#[ScopedBy([ActiveScope::class])]
class Language extends BaseModel
{
	use AppendsTrait;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'languages';

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'code';

	protected $keyType = 'string';

	public $incrementing = false;

	/**
	 * Transient (non-persisted) flag read by {@see LanguageObserver::deleting()}
	 * to decide whether the locale's `lang/<code>` translation files are also
	 * removed from disk when the row is deleted. Set per-request from the admin
	 * "Danger zone" toggle; defaults to false so the files are kept unless
	 * deletion is explicitly requested.
	 */
	public bool $deleteTranslationFiles = false;

	/**
	 * Bind admin language routes by the `code` column (also the table's primary
	 * key) so URLs read `/admin/languages/en/edit` instead of an opaque numeric
	 * id. Application code can still look up `Language::find('en')`.
	 */
	public function getRouteKeyName(): string
	{
		return 'code';
	}

	/**
	 * Generate route URLs from the raw `code` column to match
	 * {@see getRouteKeyName()}, e.g. `route('admin.languages.edit', $lang)`
	 * → `/admin/languages/en/edit`. Reading the raw attribute keeps it in sync
	 * with the `where('code', …)` lookup route-model binding performs.
	 */
	public function getRouteKey()
	{
		return $this->getAttributes()['code'] ?? null;
	}

	/**
	 * @var array<int, string>
	 */
	protected $appends = [
		'iso_locale',
		'tag',
		'primary', // Language ISO 639-1 Code
	];

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
		'code',
		'system_locale',
		'name',
		'native_name',
		'flag_icon',
		'direction',
		'russian_pluralization',
		'date_format',
		'datetime_format',
		'active',
		'default',
		'parent_id',
		'position',
		'created_at',
		'updated_at',
	];

	// Optional: Specify related models that should be invalidated when this model changes
	protected array $invalidatesCacheFor = [
		Category::class,
		Page::class,
		MenuItem::class,
		Field::class,
		MetaTag::class,
	];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'direction' => LanguageDirection::class,
		];
	}

	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	public static function getActiveLanguagesArray(): array
	{
		$cacheParams = [
			'action' => 'get.languages',
			'active' => true,
		];

		$activeLanguages = caching()->remember(self::class, $cacheParams, function () {
			return self::where('active', 1)->get();
		});

		return collect($activeLanguages)->keyBy('code')->toArray();
	}

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
	protected function id(): Attribute
	{
		return Attribute::make(
			get: function () {
				return $this->code ?? ($this->attributes['code'] ?? null);
			},
		);
	}

	protected function code(): Attribute
	{
		return Attribute::make(
			get: fn ($value) => removeLocaleCodeset($value),
			set: fn ($value) => removeLocaleCodeset($value),
		);
	}

	protected function systemLocale(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$code = $this->code ?? ($this->attributes['code'] ?? null);

				return !empty($value) ? $value : $code;
			},
			set: function ($value) {
				$code = $this->code ?? ($this->attributes['code'] ?? null);

				return !empty($value) ? $value : $code;
			},
		);
	}

	/*
	 * `system_locale` without codeset/encoding (e.g. en_US.UTF-8 → en_US).
	 */
	protected function isoLocale(): Attribute
	{
		return Attribute::make(
			get: function () {
				$locale = $this->system_locale ?? ($this->attributes['system_locale'] ?? null);

				return removeLocaleCodeset($locale);
			},
		);
	}

	protected function nativeName(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$name = $this->name ?? ($this->attributes['name'] ?? null);

				return !empty($value) ? $value : $name;
			},
			set: function ($value) {
				$name = $this->name ?? ($this->attributes['name'] ?? null);

				return !empty($value) ? $value : $name;
			},
		);
	}

	protected function tag(): Attribute
	{
		return Attribute::make(
			get: function () {
				$code = $this->code ?? ($this->attributes['code'] ?? null);

				return getLangTag($code);
			},
		);
	}

	/*
	 * Language ISO 639-1 Code
	 */
	protected function primary(): Attribute
	{
		return Attribute::make(
			get: function () {
				$code = $this->code ?? ($this->attributes['code'] ?? null);

				return getPrimaryLocaleCode($code);
			},
		);
	}

	/*
	|--------------------------------------------------------------------------
	| OTHER PRIVATE METHODS
	|--------------------------------------------------------------------------
	*/
}

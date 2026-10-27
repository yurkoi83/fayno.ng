<?php

namespace App\Models;

use App\Enums\MenuItemType;
use App\Enums\MenuItemUrlType;
use App\Helpers\Common\JsonUtils;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Common\HasNestedSelectOptions;
use App\Models\Traits\Common\HasTranslations;
use App\Observers\MenuItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Throwable;

#[ObservedBy([MenuItemObserver::class])]
#[ScopedBy([ActiveScope::class])]
class MenuItem extends BaseModel
{
	use AppendsTrait;
	use HasNestedSelectOptions, HasTranslations;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'menu_items';

	/**
	 * @var array<int, string>
	 */
	protected $appends = [
		'page_slug',
		'category_slug',
		'formatted_label',
		'route_url',
		'full_url',
		'should_display',
		'is_current_page',
		'css_classes',
		'has_children',
		'breadcrumb_trail',
		'menu_depth_class',
		'icon_html',
		'label_html',
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
		'menu_id',
		'type',
		'parent_id',
		'icon',
		'label',
		'url_type',
		'url',
		'route_name',
		'route_parameters',
		'target',
		'nofollow',
		'btn_class',
		'btn_outline',
		'css_class',
		'conditions',
		'html_attributes',
		'roles',
		'permissions',
		'description',
		'lft',
		'rgt',
		'depth',
		'active',
	];

	/**
	 * @var array<int, string>
	 */
	public array $translatable = ['label'];

	// Optional: Specify related models that should be invalidated when this model changes
	protected array $invalidatesCacheFor = [
		Menu::class,
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
			'type'             => MenuItemType::class,
			'url_type'         => MenuItemUrlType::class,
			'btn_outline'      => 'boolean',
			'route_parameters' => 'array',
			'nofollow'         => 'boolean',
			'conditions'       => 'array',
			'html_attributes'  => 'array',
			'permissions'      => 'array',
			'roles'            => 'array',
			'lft'              => 'integer',
			'rgt'              => 'integer',
			'depth'            => 'integer',
			'active'           => 'boolean',
		];
	}

	public function getVisibleMenuItems()
	{
		return $this->children()
			->isActive()
			->with([
				'children' => fn ($query) => $query->isActive()->orderBy('lft'),
			])
			->get()
			->filter(fn ($item) => $item->is_visible);
	}

	public function getVisibleChildren()
	{
		return $this->children()
			->isActive()
			->get()
			->filter(fn ($item) => $item->is_visible);
	}

	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function menu(): BelongsTo
	{
		return $this->belongsTo(Menu::class, 'menu_id');
	}

	public function children(): HasMany
	{
		return $this->hasMany(MenuItem::class, 'parent_id')
			->with(['menu', 'page', 'category', 'ancestors', 'children'])
			->orderBy('lft')
			->orderBy('label');
	}

	public function parent(): BelongsTo
	{
		return $this->belongsTo(MenuItem::class, 'parent_id')->with(['menu']);
	}

	public function ancestors(): HasMany
	{
		return $this->hasMany(MenuItem::class, 'menu_id', 'menu_id')
			->where('lft', '<', (int)$this->lft)
			->where('rgt', '>', (int)$this->rgt)
			->orderBy('lft')
			->select(['id', 'label', 'route_name', 'url', 'url_type']);
	}

	/**
	 * Define the relationship to the Page model.
	 *
	 * This BelongsTo relationship links a MenuItem to a Page model by matching the page_slug
	 * attribute (derived from route_name) to the Page model's slug column. Only active pages
	 * are included in the relationship. The relationship is used to eager load Page data
	 * for menu items with route_name starting with 'page.' to avoid N+1 query issues.
	 */
	public function page(): BelongsTo
	{
		return $this->belongsTo(Page::class, 'page_slug', 'slug')->isActive();
	}

	/**
	 * Define the relationship to the Category model.
	 *
	 * This BelongsTo relationship links a MenuItem to a Category model by matching the category_slug
	 * attribute (derived from route_name) to the Category model's slug column. Only active categories
	 * are included in the relationship. The relationship is used to eager load Category data
	 * for menu items with route_name starting with 'category.' to avoid N+1 query issues.
	 */
	public function category(): BelongsTo
	{
		return $this->belongsTo(Category::class, 'category_slug', 'slug')->isActive();
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
	/**
	 * Get the slug for the related Page model by extracting it from the route_name.
	 *
	 * This accessor extracts the slug portion from the route_name column when it starts with 'page.'.
	 * For example, if route_name is 'page.about', it returns 'about'. This is used as the foreign key
	 * for the page() relationship to match against the Page model's slug column.
	 */
	protected function pageSlug(): Attribute
	{
		return Attribute::make(
			get: function () {
				$prefix = 'page.';

				$routeName = $this->route_name ?? '';
				if (str_starts_with($routeName, $prefix)) {
					// Remove 'page.' prefix
					return substr($routeName, strlen($prefix));
				}

				return null;
			}
		);
	}

	/**
	 * Get the slug for the related Category model by extracting it from the route_name.
	 *
	 * This accessor extracts the slug portion from the route_name column when it starts with 'category.'.
	 * For example, if route_name is 'category.automobile', it returns 'automobile'. This is used as the foreign key
	 * for the category() relationship to match against the Category model's slug column.
	 */
	protected function categorySlug(): Attribute
	{
		return Attribute::make(
			get: function () {
				$prefix = 'category.';

				$routeName = $this->route_name ?? '';
				if (str_starts_with($routeName, $prefix)) {
					// Remove 'category.' prefix
					return substr($routeName, strlen($prefix));
				}

				return null;
			}
		);
	}

	public function routeName(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				// Legacy menu items recorded against the historical
				// `listing.create.{ss,ms}.showForm` route names are
				// normalized to the current flat name on read.
				if ($value === 'listing.create.ss.showForm' || $value === 'listing.create.ms.showForm') {
					return 'listing.create.showForm';
				}

				return $value;
			},
		);
	}

	public function url(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$urlType = $this->url_type;
				$defaultUrl = '#';

				return match ($urlType) {
					MenuItemUrlType::Route                               => $this->getRouteUrl(),
					MenuItemUrlType::External, MenuItemUrlType::Internal => $value ?? $defaultUrl,
					default                                              => $defaultUrl,
				};
			},
		);
	}

	/*
	 * The computed URL for this menu item
	 * route_url
	 */
	public function routeUrl(): Attribute
	{
		return Attribute::make(
			get: fn () => $this->url,
		);
	}

	/*
	 * full_url - Complete URL with domain (for external APIs)
	 */
	public function fullUrl(): Attribute
	{
		return Attribute::make(
			get: function () {
				if ($this->route_name == 'auth.login.showForm') {
					$isModalEnabled = (setting('auth_open_login_in_modal') == '1');
					if ($isModalEnabled) {
						return '#quickLogin';
					}
				}

				if ($this->route_name === 'listing.create.showForm') {
					[$createListingLinkUrl] = getCreateListingLinkInfo();
					if (!empty($createListingLinkUrl)) {
						return $createListingLinkUrl;
					}
				}

				$url = $this->url;

				// If it's already a full URL, return as is
				if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
					return $url;
				}

				// If it's just a fragment, return as is
				if ($url === '#') {
					return $url;
				}

				// Build full URL
				return url($url);
			},
		);
	}

	/*
	 * should_display - Combined check for rendering
	 */
	public function shouldDisplay(): Attribute
	{
		return Attribute::make(
			get: fn () => $this->active && $this->is_visible,
		);
	}

	/*
	 * is_visible - Check user permissions, roles, and conditions
	 */
	public function isVisible(): Attribute
	{
		return Attribute::make(
			get: function () {
				$isActive = $this->active ?? false;

				if (!$isActive) {
					return false;
				}

				$permissions = $this->permissions ?? [];
				$roles = $this->roles ?? [];
				$conditions = $this->conditions ?? [];

				// Check permissions
				if (auth()->check() && !empty($permissions)) {
					foreach ($permissions as $permission) {
						if (!auth()->user()->can($permission)) {
							return false;
						}
					}
				}

				// Check roles
				if (auth()->check() && !empty($roles)) {
					if (!auth()->user()->hasAnyRole($roles)) {
						return false;
					}
				}

				// Check custom conditions
				if (!empty($conditions)) {
					foreach ($conditions as $condition => $conditionValue) {
						if (!$this->evaluateCondition($condition, $conditionValue)) {
							return false;
						}
					}
				}

				return true;
			},
		);
	}

	/*
	 * Navigation state - Check if this menu item represents current page/route
	 * Matches current URL (Used for: CSS active class, breadcrumbs, highlighting current navigation)
	 * is_current_page
	 */
	public function isCurrentPage(): Attribute
	{
		return Attribute::make(
			get: function () {
				if (!$this->active) {
					return false;
				}

				$urlType = $this->url_type;
				$routeName = $this->route_name ?? '';
				$url = $this->url ?? '';

				// Check if current route matches this menu item
				if ($urlType === MenuItemUrlType::Route && !empty($routeName)) {
					// Handle page routes specially
					if (str_starts_with($routeName, 'page.')) {
						$slug = substr($routeName, 5);

						return
							request()->is("page/{$slug}")
							|| (
								Route::currentRouteName() === 'page.show'
								&& request()->route('slug') === $slug
							);
					}

					// Regular route matching
					return Route::currentRouteName() === $routeName;
				}

				if ($urlType === MenuItemUrlType::Internal && !empty($url)) {
					return request()->is(ltrim($url, '/'));
				}

				return false;
			},
		);
	}

	/*
	 * has_children - Check if menu item has visible children
	 */
	public function hasChildren(): Attribute
	{
		return Attribute::make(
			get: function () {
				// Use the eager-loaded count if available
				if (isset($this->children_count)) {
					return $this->children_count > 0;
				}

				// Use loaded relationship if available
				if ($this->relationLoaded('children')) {
					return $this->children->isNotEmpty();
				}

				// Fallback to the original method
				return $this->children()->isActive()->exists();
			},
		);
	}

	/*
	 * breadcrumb_trail - Get breadcrumb trail for this item
	 */
	public function breadcrumbTrail(): Attribute
	{
		return Attribute::make(
			get: function () {
				$breadcrumbs = [];

				// Use the eager-loaded ancestors if available, otherwise query
				$ancestors = $this->relationLoaded('ancestors')
					? $this->ancestors
					: $this->ancestors()->get();

				foreach ($ancestors as $ancestor) {
					$breadcrumbs[] = [
						'id'         => $ancestor->id ?? 0,
						'label'      => $ancestor->getTranslation('label', app()->getLocale()),
						'url'        => $ancestor->url ?? null,
						'is_current' => false,
					];
				}

				// Add current item
				$breadcrumbs[] = [
					'id'         => $this->id,
					'label'      => $this->translated_label,
					'url'        => $this->route_url,
					'is_current' => true,
				];

				return $breadcrumbs;
			},
		);
	}

	/*
	 * menu_depth_class - CSS class based on menu depth
	 */
	public function menuDepthClass(): Attribute
	{
		return Attribute::make(
			get: fn () => "menu-depth-{$this->depth}",
		);
	}

	public function icon(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				if (empty($value)) {
					return null;
				}

				return str_replace('bi bi-', 'bi-', $value);
			},
		);
	}

	/*
	 * icon_html - Complete HTML for icon
	 */
	public function iconHtml(): Attribute
	{
		return Attribute::make(
			get: function () {
				if (empty($this->icon)) {
					return '';
				}

				// Handle different icon formats
				if (str_starts_with($this->icon, '<')) {
					// Already HTML
					return $this->icon;
				}

				if (str_contains($this->icon, 'fa-') || str_contains($this->icon, 'fas ') || str_contains($this->icon, 'far ')) {
					// Font Awesome
					return "<i class=\"{$this->icon}\"></i>";
				}

				if (str_starts_with($this->icon, 'bi-')) {
					// Bootstrap Icons
					return "<i class=\"bi {$this->icon}\"></i>";
				}

				// Default wrapper
				return "<i class=\"{$this->icon}\"></i>";
			},
		);
	}

	/*
	 * css_classes - Get CSS classes for this menu item
	 */
	public function cssClasses(): Attribute
	{
		return Attribute::make(
			get: function () {
				$classes = [];

				// Default CSS Class (related to menu location)
				$menuLoaded = ($this->relationLoaded('menu') && !empty($this->menu));
				if ($menuLoaded) {
					if (!empty($this->menu->location)) {
						if ($this->menu->location == 'header') {
							if (!empty($this->parent_id)) {
								$classes[] = 'dropdown-item';
							} else {
								if (in_array($this->type, [MenuItemType::Link, MenuItemType::Title, MenuItemType::Button], true)) {
									if (in_array($this->type, [MenuItemType::Link, MenuItemType::Title], true)) {
										$classes[] = 'nav-link';

										if ($this->type === MenuItemType::Link) {
											// $classes[] = linkClass('body-emphasis');
											$linkColorClass = setting('header_default_link_color_class');
											if (!empty($linkColorClass)) {
												$classes[] = $linkColorClass;
											}
										}

										if ($this->type === MenuItemType::Title) {
											$textColorClass = setting('header_default_text_color_class');
											if (!empty($textColorClass)) {
												$classes[] = $textColorClass;
											}
										}

										$isDefaultHeaderItemShadowEnabled = setting('header_default_item_shadow');
										if ($isDefaultHeaderItemShadowEnabled) {
											$classes[] = 'text-shadow';
										}
									}
									if ($this->has_children) {
										$classes[] = 'dropdown-toggle';
									}
								}
							}

							// Add active class if current page
							if ($this->is_current_page) {
								$classes[] = 'active';
								$classes[] = (empty($this->parent_id) && $this->type !== MenuItemType::Button) ? 'fw-bold' : '';
							}
						}

						if ($this->menu->location == 'footer') {
							if ($this->type === MenuItemType::Title) {
								// fs-6 fw-bold text-uppercase mb-4
								$customCssClass = $this->css_class ?? '';
								if (!str_contains($customCssClass, 'fs-6')) {
									$classes[] = 'fs-6';
								}
								if (!str_contains($customCssClass, 'fw-bold')) {
									$classes[] = 'fw-bold';
								}
								if (!str_contains($customCssClass, 'text-uppercase')) {
									$classes[] = 'text-uppercase';
								}
								if (!str_contains($customCssClass, 'mb-4')) {
									$classes[] = 'mb-4';
								}
							}
							if ($this->type === MenuItemType::Link) {
								$isFooterDarkThemeEnabled = (setting('footer_dark') == '1');
								$classes[] = $isFooterDarkThemeEnabled
									? linkClass('light') . ' link-opacity-75'
									: linkClass('body-emphasis');
							}

							// Add active class if current page
							if ($this->is_current_page) {
								$classes[] = 'active';
								$classes[] = ($this->type !== MenuItemType::Button) ? 'fw-bold' : '';
							}
						}
					}
				}

				// Add custom CSS class
				if (!empty($this->css_class)) {
					$classes[] = $this->css_class;
				}

				// Add type-specific classes
				if (!empty($this->type)) {
					$classes[] = "menu-type-{$this->type->value}";
				}

				// Add button classes if it's a button type
				if ($this->type === MenuItemType::Button) {
					$classes[] = 'btn';
					if (!empty($this->btn_class)) {
						$classes[] = $this->btn_outline
							? str_replace('btn-', 'btn-outline-', $this->btn_class)
							: $this->btn_class;
					}
				}

				return implode(' ', array_unique($classes));
			},
		);
	}

	public function labelHtml(): Attribute
	{
		return Attribute::make(
			get: function () {
				$label = $this->formatted_label ?? '';

				if ($this->type !== MenuItemType::Divider && empty($label)) {
					return '';
				}

				$menuLoaded = ($this->relationLoaded('menu') && !empty($this->menu));
				$menuLocation = $menuLoaded ? ($this->menu->location ?? null) : null;
				$url = $this->full_url ?? '';
				$target = $this->target ?? '';
				$nofollow = $this->nofollow ?? false;
				$cssClasses = $this->css_classes ?? '';

				$iconHtml = !empty($this->icon_html) ? "{$this->icon_html} " : '';
				$labelHtml = $this->has_children ? "<span>{$label}</span>" : $label;
				$labelHtml = "{$iconHtml}{$labelHtml}";

				$hrefAttr = !empty($url) ? " href=\"{$url}\"" : '';
				$targetAttr = !empty($target) ? " target=\"{$target}\"" : '';
				$classAttr = !empty($cssClasses) ? " class=\"{$cssClasses}\"" : '';
				$relAttr = $nofollow ? ' rel="nofollow"' : '';
				$dropdownAttr = $this->has_children ? ' data-bs-toggle="dropdown"' : '';
				// $dropdownAttr .= ($this->has_children && $this->type == 'button') ? ' role="button" aria-expanded="false"' : '';
				$activeAttr = str_contains($cssClasses, ' active') ? ' aria-current="page"' : '';

				$loginModalAttr = '';
				if ($this->route_name == 'auth.login.showForm') {
					$isModalEnabled = (setting('auth_open_login_in_modal') == '1');
					$loginModalAttr = $isModalEnabled ? ' data-bs-toggle="modal" data-bs-target="#quickLogin"' : '';
				}

				$createListingLinkAttr = '';
				if ($this->route_name === 'listing.create.showForm') {
					[, $createListingLinkAttr] = getCreateListingLinkInfo();
				}

				if (in_array($this->type, [MenuItemType::Link, MenuItemType::Button, MenuItemType::Title], true)) {
					$specificLinksAttr = "{$loginModalAttr}{$createListingLinkAttr}";
					$linkAttr = "{$hrefAttr}{$targetAttr}{$relAttr}{$specificLinksAttr}";
					$linkBaseAttr = "{$classAttr}{$activeAttr}{$dropdownAttr}";

					$value = match ($this->type) {
						MenuItemType::Link, MenuItemType::Button => "<a{$linkAttr}{$linkBaseAttr}>{$labelHtml}</a>",
						MenuItemType::Title                      => ($menuLocation == 'footer')
							? "<h4{$classAttr}>{$label}</h4>"
							: "<a{$linkBaseAttr}>{$labelHtml}</a>",
						default                                  => $labelHtml,
					};
				} else if ($this->type === MenuItemType::Divider) {
					$value = '<hr>';
					if ($menuLocation == 'header') {
						$value = !empty($this->parent_id) ? '<hr class="dropdown-divider">' : '';
					}
				} else {
					$value = $labelHtml;
				}

				return $value;
			},
		);
	}

	public function formattedLabel(): Attribute
	{
		return Attribute::make(
			get: function () {
				$value = $this->label ?? null;

				if (empty($value)) {
					return '';
				}

				$userNamePattern = '{user.name}';
				if (str_contains($value, $userNamePattern)) {
					$authUser = auth(getAuthGuard())->user();
					if (!empty($authUser->name)) {
						$value = str_replace($userNamePattern, $authUser->name, $value);
					}
				}

				if (!isAdminPanelRoute()) {
					if ($this->route_name == 'account.messages') {
						$xhrClass = 'count-threads-with-new-messages';
						$class = 'badge rounded-pill text-bg-danger';
						$class = "$class $xhrClass";
						$badgeHtml = '<span class="' . $class . '">0</span>';
						$value = "{$value} {$badgeHtml}";
					}
				}

				return $value;
			},
		);
	}

	// html_attributes
	public function htmlAttributes(): Attribute
	{
		return Attribute::make(
			get: function ($value) {
				$htmlAttributes = $value ?? [];
				$htmlAttributes = JsonUtils::jsonToArray($htmlAttributes);

				if (!empty($this->css_class)) {
					$attrClass = $htmlAttributes['class'] ?? '';
					$htmlAttributes['class'] = trim($attrClass . ' ' . $this->css_class);
				}

				if (!empty($this->target) && $this->target !== '_self') {
					$htmlAttributes['target'] = $this->target;
				}

				return $htmlAttributes;
			},
		);
	}

	/*
	|--------------------------------------------------------------------------
	| OTHER PRIVATE METHODS
	|--------------------------------------------------------------------------
	*/
	protected function evaluateCondition(string $condition, $value): bool
	{
		if (in_array($condition, ['auth', 'guest', 'impersonating', 'authentic'])) {
			if (is_string($value)) {
				$lowerValue = strtolower($value);
				if (in_array($lowerValue, ['true', 'false', '0', '1'])) {
					$value = (bool)$lowerValue;
				}
			}
		}

		if (in_array($condition, ['role', 'permission'])) {
			if (is_string($value) && str_contains($value, ',')) {
				$value = explode(',', $value);
				$value = array_map('trim', $value);
			}
		}

		return match ($condition) {
			'auth'          => auth()->check() === $value,
			'guest'         => auth()->guest() === $value,
			'impersonating' => app('impersonate')->isImpersonating() === $value,
			'authentic'     => app('impersonate')->isImpersonating() !== $value,
			'role'          => auth()->check() && auth()->user()->hasRole($value),
			'permission'    => auth()->check() && auth()->user()->can($value),
			default         => true,
		};
	}

	protected function getRouteUrl(): string
	{
		$defaultUrl = '#';
		$routeName = $this->route_name ?? '';

		// Handle page routes specially
		if (str_starts_with($routeName, 'page.')) {
			return $this->getPageRouteUrl($routeName, $defaultUrl);
		}

		// Handle category routes specially
		if (str_starts_with($routeName, 'category.')) {
			return $this->getCategoryRouteUrl($routeName, $defaultUrl);
		}

		// Handle regular Laravel routes
		try {
			return route($routeName, $this->route_parameters ?? []);
		} catch (Throwable $e) {
			return $defaultUrl;
		}
	}

	protected function getPageRouteUrl(string $routeName, string $defaultUrl = '#'): string
	{
		$prefix = 'page.';

		if (!str_starts_with($routeName, $prefix)) {
			return $defaultUrl;
		}

		// Use the eager-loaded page relationship if available
		if ($this->relationLoaded('page') && $this->page) {
			return !empty($this->page->url) ? $this->page->url : $defaultUrl;
		}

		return $defaultUrl;
	}

	protected function getCategoryRouteUrl(string $routeName, string $defaultUrl = '#'): string
	{
		$prefix = 'category.';

		if (!str_starts_with($routeName, $prefix)) {
			return $defaultUrl;
		}

		// Use the eager-loaded category relationship if available
		if ($this->relationLoaded('category') && $this->category) {
			return !empty($this->category->url) ? $this->category->url : $defaultUrl;
		}

		return $defaultUrl;
	}
}

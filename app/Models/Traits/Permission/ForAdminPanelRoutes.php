<?php

namespace App\Models\Traits\Permission;

use App\Http\Controllers\Web\Admin\AddonController;
use App\Http\Controllers\Web\Admin\BackupController;
use App\Http\Controllers\Web\Admin\BlacklistController;
use App\Http\Controllers\Web\Admin\CountryController;
use App\Http\Controllers\Web\Admin\ListingController;
use App\Http\Controllers\Web\Admin\PageSectionController;
use App\Http\Controllers\Web\Admin\PaymentController;
use App\Http\Controllers\Web\Admin\PermissionController;
use App\Http\Controllers\Web\Admin\SettingController;
use App\Http\Controllers\Web\Admin\SystemActionController;
use App\Http\Controllers\Web\Admin\SystemPhpInfoController;
use App\Http\Controllers\Web\Admin\ThemeController;
use App\Http\Controllers\Web\Admin\UpdateController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Models\Permission;
use Illuminate\Support\Facades\Route;

/**
 * Auto-derives the canonical admin permissions list from the registered routes.
 *
 * Permission shape:
 *   admin.<controller-slug>.<verb>      e.g. `admin.category.view`, `admin.user.edit`
 *   admin.<kebab-method>                for SystemActionController only
 *                                        (e.g. `admin.clear-route-cache`)
 *
 * Canonical verbs: `view`, `create`, `edit`, `delete` for the CRUD axis, plus
 * a handful of action-specific verbs — `install`, `uninstall`, `download`,
 * `ban-users`, `bulk-actions`, `resend-verification-notification`. The
 * `getControllerActionAccessMap()` map outputs these verbs directly; there is
 * no second translation layer.
 *
 * Adding a new controller does NOT require touching this trait — the parser
 * extracts the slug from the controller class and the verb from the standard
 * method name (`index`, `store`, `update`, `destroy`, `bulkDelete`, etc.). Only
 * non-CRUD methods (e.g. `download`, `banUser`, custom toggles) need a hint
 * registered in `getControllerActionAccessMap()`.
 */
trait ForAdminPanelRoutes
{
	/**
	 * Get admin panel permissions (by parsing the admin panel routes).
	 */
	public static function getAdminPanelPermissions(): array
	{
		$permissions = Permission::parseAdminPanelRoutesPermissions();
		$permissions = collect($permissions)->pluck('permission', 'permission')->sort();

		return $permissions->toArray();
	}

	/**
	 * Walk every registered route and emit a list of `[permission, …]` rows for
	 * the ones that live under the admin base path. Returned shape (one entry
	 * per route):
	 *   [
	 *     'methods'      => ['GET'|'POST'|…],
	 *     'uri'          => 'admin/users/*',
	 *     'actionName'   => 'App\…\UserController@edit',
	 *     'actionMethod' => 'edit',
	 *     'permission'   => 'admin.user.edit',
	 *   ]
	 */
	public static function parseAdminPanelRoutesPermissions(): array
	{
		// Method-name → access-verb map, plus controller-specific overrides for
		// non-CRUD endpoints (download, banUser, install, …).
		$accessMap = self::getControllerActionAccessMap();

		$adminBasePath = urlGen()->getAdminBasePath();
		$tab = [];

		foreach (Route::getRoutes() as $key => $routeObj) {
			/** @var \Illuminate\Routing\Route $routeObj */

			// Skip routes outside the admin prefix. `getPrefix()` may include
			// nested segments (e.g. `admin/system/actions`); the first segment
			// is what we compare against.
			$routePrefix = trim((string)$routeObj->getPrefix(), '/');
			$rootPrefix = $routePrefix === '' ? '' : head(explode('/', $routePrefix));
			if ($rootPrefix !== $adminBasePath) {
				continue;
			}

			$routeActionName = $routeObj->getActionName();
			$routeActionMethod = $routeObj->getActionMethod();

			// Resolve the access verb: prefer FQN@method (per-controller override),
			// then bare method name (standard CRUD), then null = skip.
			$access = $accessMap[$routeActionName] ?? $accessMap[$routeActionMethod] ?? null;

			$inferredPermission = self::inferPermissionString($routeActionName, $routeActionMethod, $access);
			if ($inferredPermission === null) {
				continue;
			}

			$tab[$key] = [
				'methods'      => $routeObj->methods(),
				'uri'          => preg_replace('#\{[^}]+}#', '*', $routeObj->uri()),
				'actionName'   => $routeActionName,
				'actionMethod' => $routeActionMethod,
				'permission'   => $inferredPermission,
			];
		}

		return $tab;
	}

	// PRIVATE

	/**
	 * Build the permission string for a given route action.
	 *
	 *   - SystemActionController gets a flat `admin.<kebab-method>` slug because
	 *     its actions are heterogeneous one-offs (clear caches, regenerate
	 *     routes, …) — bundling them under a single `admin.system-action.*`
	 *     namespace would lose the per-action gating that admins want.
	 *   - Everything else gets `admin.<controller-slug>.<verb>` where the slug
	 *     is the kebab-cased controller basename minus the trailing
	 *     `Controller`, and the verb is the access map output verbatim.
	 */
	private static function inferPermissionString(
		string  $routeActionName,
		string  $routeActionMethod,
		?string $access,
	): ?string
	{
		// Heterogeneous system actions — one permission per method.
		if (str_contains($routeActionName, SystemActionController::class)) {
			$kebab = str($routeActionMethod)->kebab()->toString();

			return $kebab !== '' ? "admin.{$kebab}" : null;
		}

		if ($access === null) {
			return null;
		}

		$matches = [];
		if (!preg_match('#\\\([a-zA-Z0-9]+)Controller@#', $routeActionName, $matches)) {
			return null;
		}

		$slug = str($matches[1])->kebab()->toString();

		return "admin.{$slug}.{$access}";
	}

	/**
	 * Method-name (or FQN@method) → internal access verb.
	 *
	 * The bare-method entries cover every controller that follows the standard
	 * CRUD shape — adding `index()` / `store()` / `bulkDelete()` to a new
	 * controller is enough to get permissions auto-generated.
	 *
	 * Per-controller FQN@method entries are only needed when a method's name
	 * doesn't suggest its access verb (e.g. `download`, `banUser`, `raw`).
	 */
	private static function getControllerActionAccessMap(): array
	{
		// 1) Standard CRUD method-name conventions.
		$map = [
			'index'           => 'view',
			'show'            => 'view',
			'create'          => 'create',
			'store'           => 'create',
			'edit'            => 'edit',
			'update'          => 'edit',
			'destroy'         => 'delete',
			'bulkDelete'      => 'delete',
			'bulkActivate'    => 'edit',
			'bulkDeactivate'  => 'edit',
			'bulkReview'      => 'edit',
			'bulkArchive'     => 'edit',
			'bulkUnarchive'   => 'edit',
			'bulkActions'     => 'bulk-actions',
			'reorder'         => 'edit',
			'saveReorder'     => 'edit',
			'toggle'          => 'edit',
			'toggleActive'    => 'edit',
			'toggleFeatured'  => 'edit',
			'listRevisions'   => 'edit',
			'restoreRevision' => 'edit',
			'banUser'         => 'ban-users',
		];

		// 2) Per-controller method overrides — only for non-CRUD endpoints whose
		// method names don't map to a standard verb.
		$map = array_merge($map, [
			AddonController::class . '@activate'              => 'edit',
			AddonController::class . '@deactivate'            => 'edit',
			AddonController::class . '@upload'                => 'create',
			AddonController::class . '@sync'                  => 'create',
			BackupController::class . '@download'             => 'download',
			BlacklistController::class . '@banUser'           => 'ban-users',
			CountryController::class . '@toggleActive'        => 'edit',
			ListingController::class . '@toggleFeatured'      => 'edit',
			PermissionController::class . '@importPredefined' => 'create',
			PageSectionController::class . '@reorder'         => 'edit',
			PageSectionController::class . '@toggle'          => 'edit',
			PaymentController::class . '@toggleActive'        => 'edit',
			SettingController::class . '@clearCache'          => 'edit',
			SystemPhpInfoController::class . '@raw'           => 'view',
			UpdateController::class . '@update'               => 'edit',
			UpdateController::class . '@dismiss'              => 'view',
			ThemeController::class . '@activate'              => 'edit',
			ThemeController::class . '@upload'                => 'create',
			ThemeController::class . '@destroy'               => 'delete',
			ThemeController::class . '@settings'              => 'view',
			ThemeController::class . '@updateSettings'        => 'edit',
			ThemeController::class . '@resetSettings'         => 'edit',
			ThemeController::class . '@deleteSettingImage'    => 'edit',
			ThemeController::class . '@thumbnail'             => 'view',
			UserController::class . '@toggleFeatured'         => 'edit',
		]);

		// 3) Add-on (domainmapping) hints — only meaningful when the addon is
		// installed; otherwise the routes simply don't register and the entries
		// are inert. PSR-4 namespace registered by the addon's service provider
		// (see `Addons\Domainmapping\DomainmappingServiceProvider`).
		$dmNamespace = 'Addons\\Domainmapping\\Http\\Controllers\\Admin';
		$map = array_merge($map, [
			"$dmNamespace\\DomainController@createBulkCountriesSubDomain" => 'create',
			"$dmNamespace\\DomainMetaTagController@generate"              => 'create',
			"$dmNamespace\\DomainPageSectionController@generate"          => 'create',
			"$dmNamespace\\DomainSettingController@generate"              => 'create',
		]);

		// User-side resend-verification overrides (used by user/listing actions).
		return array_merge($map, [
			'resendEmailVerification' => 'resend-verification-notification',
			'resendPhoneVerification' => 'resend-verification-notification',
		]);
	}
}

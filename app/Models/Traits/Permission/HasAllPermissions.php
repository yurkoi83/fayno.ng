<?php

namespace App\Models\Traits\Permission;

use App\Helpers\Common\DBUtils;
use App\Models\Permission;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

trait HasAllPermissions
{
	use ForAdminPanelRoutes;
	use HasAdminPermissions;

	/**
	 * Get all default permissions
	 *
	 * @return array<int, string>
	 */
	public static function getDefaultPermissions(): array
	{
		return array_merge(
			Permission::getAdminPermissions(),
			Permission::getStaffPermissions()
		);
	}

	/**
	 * Get default permissions (from DB)
	 */
	public static function getDefaultPermissionsFromDb(): array
	{
		$permissionList = [];
		$permissions = collect();
		try {
			$permissionList = Permission::getDefaultPermissions();
			if (!empty($permissionList)) {
				$permissions = Permission::whereIn('name', $permissionList)->get();
			}
		} catch (Throwable $e) {
		}

		if (empty($permissionList) || $permissions->count() <= 0) {
			return [];
		}

		if (count($permissionList) !== $permissions->count()) {
			return [];
		}

		return $permissions->toArray();
	}

	/**
	 * Check default permissions
	 */
	public static function checkDefaultPermissions(): bool
	{
		if (
			!Permission::checkAdminRoleAndPermissions()
		) {
			return false;
		}

		return true;
	}

	/**
	 * Reset default permissions
	 * NOTE: Must use try {...} catch {...}
	 */
	public static function resetDefaultPermissions(): void
	{
		try {
			// Create the default admin role
			$roles = Role::resetDefaultRoles();
			if (empty($roles)) {
				return;
			}

			// Remove all current permissions & their relationship
			$permissionsOld = Permission::all();
			foreach ($permissionsOld as $permissionOld) {
				if ($permissionOld->roles()->count() > 0) {
					$permissionOld->roles()->detach();
				}
				if (!in_array($permissionOld->name, Permission::getDefaultPermissions())) {
					$permissionOld->delete();
				}
			}

			// Reset permissions table ID auto-increment
			$permissionsTable = DBUtils::table(config('permission.table_names.permissions'));
			DB::statement("ALTER TABLE $permissionsTable AUTO_INCREMENT = 1;");

			foreach ($roles as $role) {
				// Create default admin permissions
				if ($role->name == Role::getAdminRole()) {
					$permissionList = array_merge(Permission::getAdminPermissions(), Permission::getStaffPermissions());
					if (!empty($permissionList)) {
						foreach ($permissionList as $permissionName) {
							$permission = Permission::firstOrCreate(['name' => $permissionName]);
							$role->givePermissionTo($permission);
						}
					}
				}

				// ...
			}
		} catch (Exception $e) {
		}
	}

	/**
	 * Ensure default roles and permissions exist
	 */
	public static function ensureDefaultRolesAndPermissionsExist(): void
	{
		if (!Permission::checkDefaultPermissions()) {
			Permission::resetDefaultPermissions();
		}
	}
}

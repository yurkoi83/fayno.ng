<?php

namespace App\Models\Traits\Permission;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait HasAdminPermissions
{
	/**
	 * Default admin users permissions
	 *
	 * @return array<int, string>
	 */
	public static function getAdminPermissions(): array
	{
		return [
			'admin.roles.view',
			'admin.roles.create',
			'admin.roles.edit',
			'admin.roles.delete',
			'admin.permissions.view',
			'admin.permissions.create',
			'admin.permissions.edit',
			'admin.permissions.delete',
		];
	}

	/**
	 * Default staff users permissions
	 *
	 * @return array<int, string>
	 */
	public static function getStaffPermissions(): array
	{
		return [
			'admin.dashboard.view',
		];
	}

	/**
	 * Get admin users permissions (from DB)
	 */
	public static function getAdminPermissionsFromDb(): array
	{
		$permissionList = [];
		$permissions = collect();
		try {
			$permissionList = array_merge(Permission::getAdminPermissions(), Permission::getStaffPermissions());
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
	 * Check admin permissions
	 * NOTE: Must use try {...} catch {...}
	 */
	public static function checkAdminPermissions(): bool
	{
		$permissions = Permission::getAdminPermissionsFromDb();

		return !empty($permissions);
	}

	/**
	 * Check admin role and permissions
	 */
	public static function checkAdminRoleAndPermissions(?Role $role = null): bool
	{
		$doesRoleExist = !empty($role);
		$doesRoleExist = $doesRoleExist || Role::checkAdminRole();

		if (!$doesRoleExist || !Permission::checkAdminPermissions()) {
			return false;
		}

		return true;
	}

	/**
	 * Reset admin permissions
	 * NOTE: Must use try {...} catch {...}
	 */
	public static function resetAdminPermissions(?Role $role = null): void
	{
		try {
			// Create the default admin role
			$role = empty($role) ? Role::ensureAdminRoleExists() : $role;
			if (empty($role)) {
				return;
			}

			$permissionList = array_merge(Permission::getAdminPermissions(), Permission::getStaffPermissions());

			// Remove all current permissions & their relationship
			$permissionsOld = Permission::whereIn('name', $permissionList)->get();
			foreach ($permissionsOld as $permissionOld) {
				if ($permissionOld->roles()->count() > 0) {
					$permissionOld->roles()->detach();
				}
				if (!in_array($permissionOld->name, $permissionList)) {
					$permissionOld->delete();
				}
			}

			// Create default admin permissions
			if (!empty($permissionList)) {
				foreach ($permissionList as $permissionName) {
					$permission = Permission::firstOrCreate(['name' => $permissionName]);
					$role->givePermissionTo($permission);
				}
			}
		} catch (Exception $e) {
		}
	}

	/**
	 * Ensure an admin role and permissions exist
	 * NOTE: Return the Role object
	 */
	public static function ensureAdminRoleAndPermissionsExist(): ?Role
	{
		$role = empty($role) ? Role::ensureAdminRoleExists() : $role;
		if (!Permission::checkAdminRoleAndPermissions($role)) {
			Permission::resetAdminPermissions($role);
		}

		return $role;
	}

	/**
	 * Ensure at least one admin user exists in the system.
	 *
	 * Creates an admin user if none exists by following a priority fallback strategy:
	 * 1. First, assigns an admin role to existing users with is_admin = 1
	 * 2. If no admin users exist, assigns the role to the first user in the system
	 * 3. As a final fallback, assigns the role to the user with the configured app email
	 *
	 * Resets default permissions if needed before processing. This function is typically
	 * used during system initialization or migration to ensure proper admin access.
	 *
	 * NOTE: Must use try {...} catch {...}
	 *
	 * USAGE: This method is primarily used to upgrade the app's data. However, due to its low memory usage,
	 * it should also be called during app initialization to consistently ensure an admin account exists.
	 */
	public static function ensureAdminExists(): void
	{
		// Ensure admin role and permissions exist
		$role = Permission::ensureAdminRoleAndPermissionsExist();
		if (empty($role) || empty($role->name)) {
			return;
		}

		if (Permission::doesAdminUserExist()) {
			return;
		}

		// Auto-define default admin user(s)
		try {
			// Temporarily disable the lazy loading prevention
			preventLazyLoadingForModelRelations(false);

			$isAdminRoleSet = false;

			// Assign the admin role to the old admin users
			if (Schema::hasColumn((new User)->getTable(), 'is_admin')) {
				$admins = User::query()->where('is_admin', 1)->get();
				if ($admins->count() > 0) {
					foreach ($admins as $admin) {
						$admin->removeRole($role->name);
						$admin->assignRole($role->name);
						if (!$isAdminRoleSet) {
							$isAdminRoleSet = true;
						}
					}
				}
			}

			if (!$isAdminRoleSet) {
				// Assign the admin role to the single user (If only one user exists)
				$users = User::query();
				$admin = ($users->count() === 1) ? $users->first() : null;
				if (!empty($admin)) {
					$admin->removeRole($role->name);
					$admin->assignRole($role->name);
					$isAdminRoleSet = true;
				}
			}

			if (!$isAdminRoleSet) {
				$appEmail = setting('app_email');
				if (!empty($appEmail)) {
					$admin = User::query()->where('email', $appEmail)->first();
					if (!empty($admin)) {
						$admin->removeRole($role->name);
						$admin->assignRole($role->name);
						$isAdminRoleSet = true;
					}
				}
			}

			if (!$isAdminRoleSet) {
				// Assign the admin role to the first user
				$admin = User::query()->orderBy('id')->first();
				if (!empty($admin)) {
					$admin->removeRole($role->name);
					$admin->assignRole($role->name);
					$isAdminRoleSet = true;
				}
			}

			// Re-enable the lazy loading prevention if needed
			preventLazyLoadingForModelRelations();
		} catch (Throwable $e) {
		}
	}

	/**
	 * Check admin user(s) exist(s)
	 */
	public static function doesAdminUserExist(): bool
	{
		try {
			$admins = User::role(Role::getAdminRole());

			return $admins->count() > 0;
		} catch (Throwable $e) {
			return false;
		}
	}

	// LEGACY

	/**
	 * Admin users legacy permissions
	 *
	 * @return array<int, string>
	 */
	public static function getAdminLegacyPermissions(): array
	{
		return [
			'permission-list',
			'permission-create',
			'permission-update',
			'permission-delete',
			'role-list',
			'role-create',
			'role-update',
			'role-delete',
		];
	}

	/**
	 * Staff users legacy permissions
	 *
	 * @return array<int, string>
	 */
	public static function getStaffLegacyPermissions(): array
	{
		return [
			'dashboard-access',
		];
	}
}

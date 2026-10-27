<?php


namespace App\Models\Traits\Role;

use App\Helpers\Common\DBUtils;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

trait HasAllRoles
{
	use HasAdminRole;

	/**
	 * Get all default roles
	 */
	public static function getDefaultRoles(): array
	{
		return [
			Role::getAdminRole(),
		];
	}

	/**
	 * Get default roles (from DB)
	 */
	public static function getDefaultRolesFromDb(): array
	{
		$roleList = [];
		$roles = collect();
		try {
			$roleList = Role::getDefaultRoles();
			if (!empty($roleList)) {
				$roles = Role::whereIn('name', $roleList)->get();
			}
		} catch (Throwable $e) {
		}

		if (empty($roleList) || $roles->count() <= 0) {
			return [];
		}

		if (count($roleList) !== $roles->count()) {
			return [];
		}

		return $roles->toArray();
	}

	/**
	 * Check default roles
	 * NOTE: Must use try {...} catch {...}
	 */
	public static function checkDefaultRoles(): bool
	{
		$roles = Role::getDefaultRolesFromDb();

		return !empty($roles);
	}

	/**
	 * Reset default roles
	 * NOTE: Must use try {...} catch {...}
	 */
	public static function resetDefaultRoles(): array
	{
		$roles = [];

		try {
			// Remove all current roles & their relationship
			$rolesOld = Role::all();
			foreach ($rolesOld as $roleOld) {
				if ($roleOld->permissions()) {
					$roleOld->permissions()->detach();
				}
				if (!in_array($roleOld->name, Role::getDefaultRoles())) {
					$roleOld->delete();
				}
			}

			// Reset roles table ID auto-increment
			$rolesTable = DBUtils::table(config('permission.table_names.roles'));
			DB::statement("ALTER TABLE $rolesTable AUTO_INCREMENT = 1;");

			// Get default system roles
			$defaultRoles = Role::getDefaultRoles();

			// Create the default roles (in DB)
			foreach ($defaultRoles as $defaultRole) {
				$roles[] = Role::firstOrCreate(['name' => $defaultRole]);
			}
		} catch (Exception $e) {
		}

		return $roles;
	}
}

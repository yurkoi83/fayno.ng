<?php


namespace App\Models\Traits\Role;

use App\Models\Role;
use Throwable;

trait HasAdminRole
{
	/**
	 * Default admin users role
	 */
	public static function getAdminRole(): string
	{
		return 'admin';
	}

	/**
	 * Get an admin users role (from DB)
	 */
	public static function getAdminRoleFromDb(): ?Role
	{
		try {
			return Role::where('name', Role::getAdminRole())->first();
		} catch (Throwable $e) {
			return null;
		}
	}

	/**
	 * Check admin role
	 * NOTE: Must use try {...} catch {...}
	 */
	public static function checkAdminRole(): bool
	{
		$role = Role::getAdminRoleFromDb();

		return !empty($role);
	}

	/**
	 * Ensure an admin role exists
	 */
	public static function ensureAdminRoleExists(): ?Role
	{
		try {
			return Role::firstOrCreate(['name' => Role::getAdminRole()]);
		} catch (Throwable $e) {
			return null;
		}
	}
}

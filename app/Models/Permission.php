<?php

namespace App\Models;

use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Permission\HasAllPermissions;
use App\Observers\PermissionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Spatie\Permission\Models\Permission as OriginalPermission;

#[ObservedBy([PermissionObserver::class])]
class Permission extends OriginalPermission
{
	use AppendsTrait;
	use HasAllPermissions;

	/**
	 * @var array<int, string>
	 */
	protected $fillable = ['name', 'guard_name', 'updated_at', 'created_at'];

	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/

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

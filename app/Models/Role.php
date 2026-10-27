<?php

namespace App\Models;

use App\Models\Traits\Common\AppendsTrait;
use App\Models\Traits\Role\HasAllRoles;
use App\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Spatie\Permission\Models\Role as OriginalRole;

#[ObservedBy([RoleObserver::class])]
class Role extends OriginalRole
{
	use AppendsTrait;
	use HasAllRoles;

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

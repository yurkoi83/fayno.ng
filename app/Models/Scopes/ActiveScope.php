<?php

/*
 * LaraClassifier - Classified Ads Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://bedigit.com
 * Author: Mayeul Akpovi (BeDigit)
 *
 * LICENSE
 * -------
 * This software is provided under a license agreement and may only be used or copied
 * in accordance with its terms, including the inclusion of the above copyright notice.
 * As this software is sold exclusively on CodeCanyon,
 * please review the full license details here: https://codecanyon.net/licenses/standard
 */

namespace App\Models\Scopes;

use App\Http\Controllers\Web\Admin\SettingController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActiveScope implements Scope
{
	/**
	 * Apply the scope to a given Eloquent query builder.
	 */
	public function apply(Builder $builder, Model $model): void
	{
		// Load only activated entries on Settings selection
		if (routeActionHas(SettingController::class)) {
			$builder->where('active', 1);
			
			return;
		}
		
		// Load all entries for the Admin panel
		if (isAdminPanelRoute()) {
			return;
		}
		
		// Load only activated entries for the front
		$builder->where('active', 1);
	}
}

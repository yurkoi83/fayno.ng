<?php

namespace App\Models\Builders;

use App\Models\Builders\Classes\GlobalBuilder;

trait HasGlobalBuilder
{
	use HasEnumFields;

	/**
	 * Get a new query builder instance for the connection
	 * that extends the Laravel eloquent core builder
	 *
	 * @return GlobalBuilder
	 */
	public function newEloquentBuilder($query)
	{
		return new GlobalBuilder($query);
	}
}

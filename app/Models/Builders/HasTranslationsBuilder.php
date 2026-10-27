<?php

namespace App\Models\Builders;

use App\Models\Builders\Classes\TranslationsBuilder;

trait HasTranslationsBuilder
{
	/**
	 * Get a new query builder instance for the connection for translatable models
	 * This overwrites the custom global builder in the 'HasGlobalBuilder.php' file
	 *
	 * @return TranslationsBuilder
	 */
	public function newEloquentBuilder($query)
	{
		return new TranslationsBuilder($query);
	}
}

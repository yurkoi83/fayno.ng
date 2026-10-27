<?php


namespace App\Models\Traits\Common;

use Illuminate\Database\Eloquent\Builder;

trait AppendsTrait
{
	private static bool $withoutAppends = false;

	private static array $withAppends = [];

	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	protected function getArrayableAppends(): array
	{
		if (self::$withoutAppends) {
			return [];
		} else {
			if (!empty(self::$withAppends)) {
				return self::$withAppends;
			}
		}

		return parent::getArrayableAppends();
	}

	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	public function scopeWithoutAppends(Builder $builder): Builder
	{
		self::$withoutAppends = true;

		return $builder;
	}

	public function scopeWithAppends(Builder $builder, array $withAppends = []): Builder
	{
		self::$withAppends = $withAppends;

		return $builder;
	}
}

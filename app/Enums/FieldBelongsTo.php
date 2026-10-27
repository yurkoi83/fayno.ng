<?php

namespace App\Enums;

enum FieldBelongsTo: string
{
	use EnumToArray;

	case Listing = 'listing';
	case User = 'user';

	public function label(): string
	{
		return match ($this) {
			self::Listing => trans('admin.listing'),
			self::User    => trans('admin.user'),
		};
	}
}

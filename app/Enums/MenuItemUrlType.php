<?php

namespace App\Enums;

/**
 * Allowed values for `menu_items.url_type`. The column is nullable, so a
 * `null` value (no URL configured — divider/title items) is also valid and
 * sits outside this enum.
 */
enum MenuItemUrlType: string
{
	use EnumToArray;

	case Route = 'route';
	case Internal = 'internal';
	case External = 'external';

	public function label(): string
	{
		return match ($this) {
			self::Route    => trans('admin.Route'),
			self::Internal => trans('admin.Internal'),
			self::External => trans('admin.External'),
		};
	}
}

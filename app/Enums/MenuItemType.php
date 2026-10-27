<?php

namespace App\Enums;

enum MenuItemType: string
{
	use EnumToArray;

	case Link = 'link';
	case Button = 'button';
	case Divider = 'divider';
	case Title = 'title';

	public function label(): string
	{
		return match ($this) {
			self::Link    => trans('admin.Link'),
			self::Button  => trans('admin.Button'),
			self::Divider => trans('admin.Divider'),
			self::Title   => trans('admin.Title'),
		};
	}
}

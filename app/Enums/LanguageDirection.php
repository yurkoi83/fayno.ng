<?php

namespace App\Enums;

enum LanguageDirection: string
{
	use EnumToArray;

	case Ltr = 'ltr';
	case Rtl = 'rtl';

	public function label(): string
	{
		return match ($this) {
			self::Ltr => 'LTR',
			self::Rtl => 'RTL',
		};
	}
}

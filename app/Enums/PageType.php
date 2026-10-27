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

namespace App\Enums;

enum PageType: string
{
	use EnumToArray;
	
	case Standard = 'standard';
	case Terms = 'terms';
	case Privacy = 'privacy';
	case Tips = 'tips';
	
	public function label(): string
	{
		return match ($this) {
			self::Standard => trans('admin.Standard'),
			self::Terms    => trans('admin.Terms of Service'),
			self::Privacy  => trans('admin.Privacy Policy'),
			self::Tips     => trans('admin.Tips'),
		};
	}
}

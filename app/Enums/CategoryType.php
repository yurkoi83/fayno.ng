<?php

namespace App\Enums;

/**
 * Allowed values for `categories.type`. The backing values are hyphenated
 * for historical reasons (the column has shipped as `enum('classified',
 * 'job-offer', 'job-search', 'rent', 'not-salable')` since v2.5.0); the
 * case names are PascalCase per the convention but the backing values
 * keep the hyphens — changing the values would silently break every
 * existing `categories.type` row.
 */
enum CategoryType: string
{
	use EnumToArray;

	case Classified = 'classified';
	case JobOffer = 'job-offer';
	case JobSearch = 'job-search';
	case Rent = 'rent';
	case NotSalable = 'not-salable';

	public function label(): string
	{
		return match ($this) {
			self::Classified => trans('admin.Classified'),
			self::JobOffer   => trans('admin.Job offer'),
			self::JobSearch  => trans('admin.Job search'),
			self::Rent       => trans('admin.Rent'),
			self::NotSalable => trans('admin.Not salable'),
		};
	}
}

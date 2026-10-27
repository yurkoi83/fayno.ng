<?php
namespace App\Enums;

enum Gender: int
{
	use EnumToArray;

	case MALE = 1;
	case FEMALE = 2;

	// case OTHER = 3;

	public function label(): string
	{
		return match ($this) {
            self::MALE => trans('nova::enum.male'),
			self::FEMALE => trans('nova::enum.female'),
			// self::OTHER => trans('enum.other'),
		};
	}

	public function title(): string
	{
		return match ($this) {
			self::MALE => trans('nova::enum.mr'),
			self::FEMALE => trans('nova::enum.mrs'),
			// self::OTHER => trans('enum.mx'), // In English, the most common gender-neutral title is "Mx." (most often pronounced "miks").
		};
	}

	/**
	 * @param $value
	 * @return array
	 */
	public static function find($value = null): array
	{
		if (empty($value)) return [];

		$item = self::tryFrom($value);
		if (empty($item)) return [];

		return [
			'id'    => $item->value,
			'name'  => $item->name,
			'label' => $item->label(),
			'title' => $item->title(),
		];
	}
}

<?php

namespace App\Enums;

use App\Helpers\Common\Arr;
use Throwable;

trait EnumToArray
{
	/**
	 * @param bool|string|null $orderBy
	 * @param string $order
	 * @return array
	 */
	public static function all(bool|string|null $orderBy = null, string $order = 'asc'): array
	{
		$entries = collect(self::cases())
			->mapWithKeys(fn ($item) => [$item->value => self::find($item->value)])
			->toArray();

		$isSortEnabled = (
			(is_bool($orderBy) && $orderBy)
			|| is_null($orderBy)
			|| is_string($orderBy)
		);

		if ($isSortEnabled) {
			$orderBy = is_bool($orderBy) ? null : $orderBy;

			if (empty($orderBy)) {
				$orderBy = 'label';
			}

			try {
				$entries = Arr::mbSortBy($entries, $orderBy, $order);
			} catch (Throwable $e) {
			}
		}

		return $entries;
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
		];
	}

	/**
	 * @return array
	 */
	public static function names(): array
	{
		return array_column(self::cases(), 'name');
	}

	/**
	 * @return array
	 */
	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}
}

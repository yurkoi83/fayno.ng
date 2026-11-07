<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CampShift;
use App\Models\OrderCamper;

class CampShiftCapacityService
{
	/**
	 * Count places for orders that are not new, processing or rejected,
	 * and have a booking with at least one successful payment.
	 */
	public function bookedPlaces(CampShift $shift): int
	{
		return OrderCamper::query()
			->whereHas('order', fn ($query) => $query
				->where('camp_shift_id', $shift->getKey())
				->whereNotIn('status', [
					OrderStatus::NEW->value,
					OrderStatus::PROCESSING->value,
					OrderStatus::REJECTED->value,
				])
			)
			->whereHas('booking', fn ($query) => $query
				->where('status', '!=', BookingStatus::CANCELLED->value)
				->whereHas('payments', fn ($query) => $query
					->where('status', PaymentStatus::PAID->value)
				)
			)
			->count();
	}

	public function hasCapacity(CampShift $shift, int $additionalPlaces = 1): bool
	{
		if ($additionalPlaces < 0) {
			return false;
		}

		return $this->bookedPlaces($shift) + $additionalPlaces <= (int) $shift->capacity;
	}

	public function remainingPlaces(CampShift $shift): int
	{
		return max(0, (int) $shift->capacity - $this->bookedPlaces($shift));
	}
}

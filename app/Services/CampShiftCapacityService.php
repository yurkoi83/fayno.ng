<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\CampShift;
use App\Models\OrderCamper;

class CampShiftCapacityService
{
	/**
	 * Count places reserved by orders that have not been rejected
	 * and bookings that have not been cancelled.
	 */
	public function bookedPlaces(CampShift $shift): int
	{
		return OrderCamper::query()
			->whereHas('order', fn ($query) => $query
				->where('camp_shift_id', $shift->getKey())
				->where('status', '!=', OrderStatus::REJECTED->value)
			)
			->whereHas('booking', fn ($query) => $query
				->where('status', '!=', BookingStatus::CANCELLED->value)
			)
			->count();
	}

	public function hasCapacity(CampShift $shift, int $additionalPlaces = 1): bool
	{
		return $this->bookedPlaces($shift) + $additionalPlaces <= (int) $shift->capacity;
	}

	public function remainingPlaces(CampShift $shift): int
	{
		return max(0, (int) $shift->capacity - $this->bookedPlaces($shift));
	}
}

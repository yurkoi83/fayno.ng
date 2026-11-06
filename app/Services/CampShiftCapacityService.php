<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\CampShift;
use App\Models\OrderCamper;

class CampShiftCapacityService
{
	/**
	 * Count places reserved by orders that have moved beyond processing
	 * and have not been rejected, with bookings that have not been cancelled.
	 *
	 * An order camper without a booking still occupies a place.
	 */
	public function bookedPlaces(CampShift $shift): int
	{
		return OrderCamper::query()
			->whereHas('order', fn ($query) => $query
				->where('camp_shift_id', $shift->getKey())
				->whereNotIn('status', [
					OrderStatus::PROCESSING->value,
					OrderStatus::REJECTED->value,
				])
			)
			->where(function ($query) {
				$query
					->whereDoesntHave('booking')
					->orWhereHas('booking', fn ($query) => $query
						->where('status', '!=', BookingStatus::CANCELLED->value)
					);
			})
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

<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\CampShift;
use App\Models\OrderCamper;

class CampShiftCapacityService
{
    /**
     * Count all non-cancelled camper bookings for a shift.
     */
    public function bookedPlaces(CampShift $shift): int
    {
        return OrderCamper::query()
            ->whereHas('order', fn ($query) => $query->where('camp_shift_id', $shift->getKey()))
            ->whereHas('booking', fn ($query) => $query->where('status', '!=', BookingStatus::CANCELLED->value))
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

<?php

namespace App\Nova\Metrics;

use App\Enums\CampShiftStatus;
use App\Models\CampShift;
use App\Services\CampShiftCapacityService;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class CampShiftOccupancy extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        $shifts = CampShift::query()
            ->where('status', CampShiftStatus::ACTIVE->value)
            ->get();

        $capacity = (int) $shifts->sum('capacity');

        if ($capacity === 0) {
            return $this->result(0)->suffix('%');
        }

        $capacityService = app(CampShiftCapacityService::class);

        $bookedPlaces = $shifts->sum(
            fn (CampShift $shift) => $capacityService->bookedPlaces($shift)
        );

        return $this->result(round($bookedPlaces / $capacity * 100))
            ->suffix('%');
    }

    public function name(): string
    {
        return 'Заполненность активных смен';
    }
}

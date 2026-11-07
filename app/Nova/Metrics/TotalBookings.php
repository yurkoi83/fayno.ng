<?php

namespace App\Nova\Metrics;

use App\Models\Booking;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalBookings extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Booking::query()->count())
            ->suffix(' бронирований');
    }

    public function name(): string
    {
        return 'Бронирования';
    }
}

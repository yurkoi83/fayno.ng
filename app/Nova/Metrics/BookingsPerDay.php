<?php

namespace App\Nova\Metrics;

use App\Models\Booking;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;

class BookingsPerDay extends Trend
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        return $this->countByMonths($request, Booking::class)
            ->showSumValue();
    }

    /**
     * Get the ranges available for the metric.
     */
    public function ranges(): array
    {
        return [
            6 => '6 месяцев',
            12 => '12 месяцев',
        ];
    }

    public function name(): string
    {
        return 'Динамика бронирований';
    }
}

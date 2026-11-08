<?php

namespace App\Nova\Metrics;

use App\Models\Order;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalOrders extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Order::query()->count())
            ->suffix(' ' . __('nova::resources.Applications count suffix'));
    }

    public function name(): string
    {
        return __('nova::resources.Applications');
    }
}

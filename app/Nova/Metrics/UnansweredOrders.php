<?php

namespace App\Nova\Metrics;

use App\Enums\OrderStatus;
use App\Models\Order;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class UnansweredOrders extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        $count = Order::query()
            ->whereNull('responded_at')
            ->where('status', '!=', OrderStatus::REJECTED->value)
            ->count();

        return $this->result($count)
            ->suffix(' ' . __('nova::resources.Applications count suffix'));
    }

    public function name(): string
    {
        return __('nova::resources.Applications without response');
    }
}

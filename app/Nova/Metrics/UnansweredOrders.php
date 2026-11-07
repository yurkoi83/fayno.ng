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
            ->suffix(' заявок');
    }

    public function name(): string
    {
        return 'Заявки без ответа';
    }
}

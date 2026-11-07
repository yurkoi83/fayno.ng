<?php

namespace App\Nova\Metrics;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalPayments extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Payment::query()
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount'))
            ->prefix('₴ ')
            ->format('0,0.00');
    }

    public function name(): string
    {
        return 'Поступившие оплаты';
    }
}

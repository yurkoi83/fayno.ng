<?php

namespace App\Nova\Metrics;

use App\Models\Camp;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalCamps extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Camp::query()->count())
            ->suffix(' ' . __('nova::resources.Camps count suffix'));
    }

    public function name(): string
    {
        return __('nova::navigation.camps');
    }
}

<?php

namespace App\Nova\Metrics;

use App\Models\Camper;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalCampers extends Value
{
    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Camper::query()->count())
            ->suffix(' ' . __('nova::resources.Participants count suffix'));
    }

    public function name(): string
    {
        return __('nova::resources.Participants');
    }
}

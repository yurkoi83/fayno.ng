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
            ->suffix(' участников');
    }

    public function name(): string
    {
        return 'Участники';
    }
}

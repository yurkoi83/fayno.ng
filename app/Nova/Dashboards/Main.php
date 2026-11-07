<?php

namespace App\Nova\Dashboards;

use App\Nova\Cards\Applications;
use App\Nova\Cards\CampShiftOccupancy;
use App\Nova\Metrics\TotalBookings;
use App\Nova\Metrics\TotalCampers;
use App\Nova\Metrics\TotalCamps;
use App\Nova\Metrics\TotalPayments;
use App\Nova\Metrics\BookingsPerDay;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(): array
    {
        return [
            (new TotalBookings)->width('1/4'),
            (new TotalCampers)->width('1/4'),
            (new TotalCamps)->width('1/4'),
            (new TotalPayments)->width('1/4'),
            (new BookingsPerDay)->width('full'),
            new CampShiftOccupancy,
            new Applications,
        ];
    }
}

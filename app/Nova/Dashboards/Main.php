<?php

namespace App\Nova\Dashboards;

use App\Nova\Cards\Applications;
use App\Nova\Metrics\BookingsPerDay;
use App\Nova\Metrics\CampShiftOccupancy;
use App\Nova\Metrics\TotalOrders;
use App\Nova\Metrics\UnansweredOrders;
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
            (new TotalOrders)->width('1/3'),
            (new UnansweredOrders)->width('1/3'),
            (new CampShiftOccupancy)->width('1/3'),
            (new BookingsPerDay)->width('full'),
            new Applications,
        ];
    }
}

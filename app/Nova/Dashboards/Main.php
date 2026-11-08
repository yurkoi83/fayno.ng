<?php

namespace App\Nova\Dashboards;

use App\Nova\Cards\DashboardOverview;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Get the displayable name of the dashboard.
     */
    public function label(): string
    {
        return 'Огляд';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(): array
    {
        return [
            new DashboardOverview,
            new \App\Nova\Cards\Applications,
        ];
    }
}

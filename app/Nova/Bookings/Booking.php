<?php

namespace App\Nova\Bookings;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class Booking extends Resource
{
    public static $model = \App\Models\Booking::class;
    public static $title = 'id';
    public static $search = ['id', 'status'];
    public static $group = 'Бронювання';
    public static $displayInNavigation = false;

    public static function label(): string { return __('nova::resources.Bookings'); }
    public static function singularLabel(): string { return __('nova::resources.Bookings'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(__('nova::resources.Camper in order'), 'orderCamper', OrderCamper::class)->searchable(),
            Text::make(__('nova::resources.Status'), 'status')->sortable(),
            Number::make(__('nova::resources.Amount'), 'total_amount')->step(0.01)->sortable(),
            Text::make(__('nova::resources.Currency'), 'currency'),
            DateTime::make(__('nova::resources.Confirmed at'), 'confirmed_at')->nullable(),
        ];
    }
}

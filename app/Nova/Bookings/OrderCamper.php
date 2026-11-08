<?php

namespace App\Nova\Bookings;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class OrderCamper extends Resource
{
    public static $model = \App\Models\OrderCamper::class;
    public static $title = 'id';
    public static $displayInNavigation = false;

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Заявка', 'order', Order::class),
            BelongsTo::make('Учасник', 'camper', \App\Nova\People\Camper::class),
            Number::make('Ціна', 'price')->step(0.01),
            Number::make('Знижка', 'discount')->step(0.01),
            Text::make('Валюта', 'currency'),
        ];
    }
}

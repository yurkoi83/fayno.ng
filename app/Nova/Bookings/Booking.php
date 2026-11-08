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

    public static function label(): string { return 'Бронювання'; }
    public static function singularLabel(): string { return 'Бронювання'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Учасник у заявці', 'orderCamper', OrderCamper::class)->searchable(),
            Text::make('Статус', 'status')->sortable(),
            Number::make('Сума', 'total_amount')->step(0.01)->sortable(),
            Text::make('Валюта', 'currency'),
            DateTime::make('Підтверджено', 'confirmed_at')->nullable(),
        ];
    }
}

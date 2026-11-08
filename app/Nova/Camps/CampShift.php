<?php

namespace App\Nova\Camps;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class CampShift extends Resource
{
    public static $model = \App\Models\CampShift::class;
    public static $title = 'name';
    public static $search = ['id', 'name'];
    public static $group = 'Табори';
    public static $displayInNavigation = false;

    public static function label(): string { return 'Зміни'; }
    public static function singularLabel(): string { return 'Зміна'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Табір', 'camp', Camp::class)->searchable()->sortable(),
            Text::make('Назва', 'name')->sortable()->rules('required'),
            Date::make('Початок', 'starts_at')->sortable()->rules('required'),
            Date::make('Завершення', 'ends_at')->sortable()->rules('required'),
            Number::make('Місткість', 'capacity')->min(1)->rules('required', 'integer', 'min:1'),
            Number::make('Звичайна ціна', 'regular_price')->step(0.01)->rules('required', 'numeric', 'min:0'),
            Number::make('Рання ціна', 'early_price')->step(0.01)->nullable(),
            Date::make('Раннє бронювання до', 'early_booking_until')->nullable(),
            Text::make('Валюта', 'currency')->rules('required', 'size:3'),
            Text::make('Статус', 'status')->sortable()->rules('required'),
        ];
    }
}

<?php

namespace App\Nova\People;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Camper extends Resource
{
    public static $model = \App\Models\Camper::class;
    public static $title = 'last_name';
    public static $search = ['id', 'first_name', 'last_name'];
    public static $group = 'Учасники';

    public static function label(): string { return 'Учасники'; }
    public static function singularLabel(): string { return 'Учасник'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Батьки', 'customer', Customer::class)->searchable(),
            Text::make('Прізвище', 'last_name')->sortable()->rules('required'),
            Text::make('Ім’я', 'first_name')->sortable()->rules('required'),
            Text::make('По батькові', 'middle_name')->nullable()->hideFromIndex(),
            Date::make('Дата народження', 'birth_date')->sortable(),
            Number::make('Стать (ID)', 'gender_id')->nullable()->hideFromIndex(),
            Textarea::make('Нотатки', 'notes')->alwaysShow()->hideFromIndex(),
        ];
    }
}

<?php

namespace App\Nova\People;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Customer extends Resource
{
    public static $model = \App\Models\Customer::class;
    public static $title = 'last_name';
    public static $search = ['id', 'first_name', 'last_name', 'phone', 'email'];
    public static $group = 'Батьки';

    public static function label(): string { return 'Батьки'; }
    public static function singularLabel(): string { return 'Батьки'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Прізвище', 'last_name')->sortable()->rules('required'),
            Text::make('Ім’я', 'first_name')->sortable()->rules('required'),
            Text::make('По батькові', 'middle_name')->nullable(),
            Text::make('Телефон', 'phone')->sortable()->rules('required'),
            Text::make('Email', 'email')->nullable()->rules('nullable', 'email'),
            Text::make('Місто', 'city')->nullable(),
            Text::make('Адреса', 'address')->nullable()->hideFromIndex(),
            Textarea::make('Нотатки', 'notes')->alwaysShow()->hideFromIndex(),
        ];
    }
}

<?php

namespace App\Nova\Camps;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Camp extends Resource
{
    public static $model = \App\Models\Camp::class;
    public static $title = 'name';
    public static $search = ['id', 'name', 'city'];
    public static $group = 'Табори';

    public static function label(): string { return 'Табори'; }
    public static function singularLabel(): string { return 'Табір'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Назва', 'name')->sortable()->rules('required', 'max:255'),
            Text::make('Slug', 'slug')->hideFromIndex(),
            Text::make('Країна', 'country'),
            Text::make('Місто', 'city')->sortable(),
            Text::make('Адреса', 'address')->hideFromIndex(),
            Number::make('Мін. вік', 'min_age')->min(0)->max(18),
            Number::make('Макс. вік', 'max_age')->min(0)->max(18),
            Boolean::make('Активний', 'active'),
            Textarea::make('Опис', 'description')->alwaysShow()->hideFromIndex(),
        ];
    }
}

<?php

namespace App\Nova\Camps;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Camp extends Resource
{
    public static $model = \App\Models\Camp::class;
    public static $title = 'name';
    public static $search = ['id', 'name', 'city'];
    public static $group = 'Табори';

    public static function label(): string { return __('nova::navigation.camps'); }
    public static function singularLabel(): string { return __('nova::resources.Camp'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make(__('nova::resources.Camp name'), 'name')->sortable()->rules('required', 'max:255'),
            Text::make(__('nova::resources.Slug'), 'slug')->hideFromIndex(),
            Image::make(__('nova::resources.Photo'), 'photo_path')->disk('public')->path('camps')->prunable()->hideFromIndex(),
            Text::make(__('nova::resources.Country'), 'country'),
            Text::make(__('nova::resources.City'), 'city')->sortable(),
            Text::make(__('nova::resources.Address'), 'address')->hideFromIndex(),
            Number::make(__('nova::resources.Min age'), 'min_age')->min(0)->max(18),
            Number::make(__('nova::resources.Max age'), 'max_age')->min(0)->max(18),
            Boolean::make(__('nova::resources.Active'), 'active'),
            Textarea::make(__('nova::resources.Description'), 'description')->alwaysShow()->hideFromIndex(),
        ];
    }
}

<?php

namespace App\Nova\People;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Camper extends Resource
{
    public static $model = \App\Models\Camper::class;
    public static $title = 'last_name';
    public static $search = ['id', 'first_name', 'last_name'];
    public static $group = 'Учасники';

    public static function label(): string { return __('nova::navigation.campers'); }
    public static function singularLabel(): string { return __('nova::resources.Camper'); }

    public function title()
    {
        return trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ])));
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(__('nova::resources.Parent'), 'customer', Customer::class)->searchable(),
            Text::make(__('nova::resources.Last name'), 'last_name')->sortable()->rules('required'),
            Text::make(__('nova::resources.First name'), 'first_name')->sortable()->rules('required'),
            Text::make(__('nova::resources.Middle name'), 'middle_name')->nullable()->hideFromIndex(),
            Date::make(__('nova::resources.Birth date'), 'birth_date')->sortable(),
            Image::make(__('nova::resources.Photo'), 'photo_path')->disk('public')->path('campers')->prunable()->hideFromIndex(),
            Number::make(__('nova::resources.Gender'), 'gender_id')->nullable()->hideFromIndex(),
            Textarea::make(__('nova::resources.Notes'), 'notes')->alwaysShow()->hideFromIndex(),
        ];
    }
}

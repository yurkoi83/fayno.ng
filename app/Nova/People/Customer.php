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

    public static function label(): string { return __('nova::navigation.parents'); }
    public static function singularLabel(): string { return __('nova::navigation.parents'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make(__('nova::resources.Last name'), 'last_name')->sortable()->rules('required'),
            Text::make(__('nova::resources.First name'), 'first_name')->sortable()->rules('required'),
            Text::make(__('nova::resources.Middle name'), 'middle_name')->nullable(),
            Text::make(__('nova::resources.Phone'), 'phone')->sortable()->rules('required'),
            Text::make(__('nova::resources.Email'), 'email')->nullable()->rules('nullable', 'email'),
            Text::make(__('nova::resources.City'), 'city')->nullable(),
            Text::make(__('nova::resources.Address'), 'address')->nullable()->hideFromIndex(),
            Textarea::make(__('nova::resources.Notes'), 'notes')->alwaysShow()->hideFromIndex(),
        ];
    }
}

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

    public static function label(): string { return __('nova::resources.Camp shifts'); }
    public static function singularLabel(): string { return __('nova::resources.Camp shift'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(__('nova::resources.Camp'), 'camp', Camp::class)->searchable()->sortable(),
            Text::make(__('nova::resources.Name'), 'name')->sortable()->rules('required'),
            Date::make(__('nova::resources.Start date'), 'starts_at')->sortable()->rules('required'),
            Date::make(__('nova::resources.End date'), 'ends_at')->sortable()->rules('required'),
            Number::make(__('nova::resources.Capacity'), 'capacity')->min(1)->rules('required', 'integer', 'min:1'),
            Number::make(__('nova::resources.Regular price'), 'regular_price')->step(0.01)->rules('required', 'numeric', 'min:0'),
            Number::make(__('nova::resources.Early price'), 'early_price')->step(0.01)->nullable(),
            Date::make(__('nova::resources.Early booking until'), 'early_booking_until')->nullable(),
            Text::make(__('nova::resources.Currency'), 'currency')->rules('required', 'size:3'),
            Text::make(__('nova::resources.Status'), 'status')->sortable()->rules('required'),
        ];
    }
}

<?php

namespace App\Nova\Settings;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Setting extends Resource
{
    public static $model = \App\Models\Setting::class;
    public static $title = 'key';
    public static $search = ['id', 'group', 'key'];
    public static $group = 'Налаштування';

    public static function label(): string { return __('nova::navigation.settings'); }
    public static function singularLabel(): string { return __('nova::navigation.settings'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make(__('nova::resources.Group'), 'group')->sortable()->rules('required'),
            Text::make(__('nova::resources.Key'), 'key')->sortable()->rules('required'),
            Textarea::make(__('nova::resources.Value (JSON)'), 'value')->alwaysShow(),
        ];
    }
}

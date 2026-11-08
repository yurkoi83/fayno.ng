<?php

namespace App\Nova\Bookings;

use App\Nova\Resource;
use App\Nova\Camps\CampShift;
use App\Nova\People\Customer;
use App\Nova\Users\User;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;

class Order extends Resource
{
    public static $model = \\App\\Models\\Order::class;
    public static $title = 'number';
    public static $search = ['id', 'number', 'status'];
    public static $group = 'Бронювання';

    public static function label(): string { return __('nova::navigation.bookings'); }
    public static function singularLabel(): string { return __('nova::resources.Order'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make(__('nova::resources.Number'), 'number')->sortable()->rules('required'),

            BelongsTo::make(__('nova::resources.Parent'), 'customer', Customer::class)->searchable(),
            Text::make(__('nova::resources.Parent phone'), fn () => $this->customer?->phone)->onlyOnDetail(),
            Text::make(__('nova::resources.Parent email'), fn () => $this->customer?->email)->onlyOnDetail(),
            Text::make(__('nova::resources.Parent city'), fn () => $this->customer?->city)->onlyOnDetail(),
            Text::make(__('nova::resources.Parent address'), fn () => $this->customer?->address)->onlyOnDetail(),

            BelongsTo::make(__('nova::resources.Camp shift'), 'campShift', CampShift::class)->searchable(),
            Text::make(__('nova::resources.Camp'), fn () => $this->campShift?->camp?->name)->onlyOnDetail(),
            Text::make(__('nova::resources.Shift dates'), fn () => $this->campShift
                ? $this->campShift->starts_at?->format('d.m.Y').' — '.$this->campShift->ends_at?->format('d.m.Y')
                : null)->onlyOnDetail(),

            Text::make(__('nova::resources.Status'), 'status')->sortable(),
            Text::make(__('nova::resources.Source'), 'source')->hideFromIndex(),
            Text::make(__('nova::resources.Departure city'), 'departure_city')->hideFromIndex(),
            BelongsTo::make(__('nova::resources.Assigned to'), 'assignedTo', User::class)->nullable()->searchable(),
            DateTime::make(__('nova::resources.Responded at'), 'responded_at')->nullable()->hideFromIndex(),
            Textarea::make(__('nova::resources.Comment'), 'comment')->alwaysShow()->hideFromIndex(),
            Textarea::make(__('nova::resources.Admin notes'), 'admin_notes')->alwaysShow()->hideFromIndex(),

            HasMany::make(__('nova::resources.Children'), 'orderCampers', OrderCamper::class),
        ];
    }
}

<?php

namespace App\Nova\Bookings;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class OrderCamper extends Resource
{
    public static $model = \App\Models\OrderCamper::class;
    public static $title = 'id';

    public function title()
    {
        return $this->camper
            ? trim(implode(' ', array_filter([$this->camper->last_name, $this->camper->first_name, $this->camper->middle_name])))
            : (string) $this->id;
    }
    public static $displayInNavigation = false;

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Panel::make(__('nova::resources.Child details'), [
                BelongsTo::make(__('nova::resources.Camper'), 'camper', \App\Nova\People\Camper::class)->searchable(),
                Image::make(__('nova::resources.Photo'), fn () => $this->camper?->photo_path)->disk('public')->maxWidth(56),
                Text::make(__('nova::resources.Child full name'), fn () => $this->camper
                    ? trim(implode(' ', array_filter([
                        $this->camper->last_name,
                        $this->camper->first_name,
                        $this->camper->middle_name,
                    ])))
                    : null),
                Text::make(__('nova::resources.Birth date'), fn () => $this->camper?->birth_date?->format('d.m.Y')),
                Text::make(__('nova::resources.Age'), fn () => $this->camper?->birth_date
                    ? $this->camper->birth_date->age.' '.__('nova::resources.Years')
                    : null),
                Text::make(__('nova::resources.Gender'), fn () => $this->camper?->gender_id)->onlyOnDetail(),
                Textarea::make(__('nova::resources.Notes'), fn () => $this->camper?->notes)->onlyOnDetail(),
            ]),

            Panel::make(__('nova::resources.Cost'), [
                Number::make(__('nova::resources.Price'), 'price')->step(0.01)->sortable(),
                Number::make(__('nova::resources.Discount'), 'discount')->step(0.01),
                Text::make(__('nova::resources.Currency'), 'currency'),
            ]),

            HasOne::make(__('nova::resources.Individual booking'), 'booking', Booking::class),
        ];
    }
}

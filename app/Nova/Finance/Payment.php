<?php

namespace App\Nova\Finance;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class Payment extends Resource
{
    public static $model = \App\Models\Payment::class;
    public static $title = 'id';
    public static $search = ['id', 'transaction_id', 'status'];
    public static $group = 'Платежі';

    public static function label(): string { return __('nova::navigation.payments'); }
    public static function singularLabel(): string { return __('nova::resources.Payment'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(__('nova::resources.Bookings'), 'booking', \App\Nova\Bookings\Booking::class)->searchable(),
            Number::make(__('nova::resources.Amount'), 'amount')->step(0.01)->sortable()->rules('required', 'numeric', 'min:0'),
            Text::make(__('nova::resources.Currency'), 'currency')->rules('required', 'size:3'),
            Text::make(__('nova::resources.Status'), 'status')->sortable(),
            Text::make(__('nova::resources.Payment method'), 'payment_method')->nullable(),
            Text::make(__('nova::resources.Transaction ID'), 'transaction_id')->nullable()->hideFromIndex(),
            DateTime::make(__('nova::resources.Paid at'), 'paid_at')->nullable(),
        ];
    }
}

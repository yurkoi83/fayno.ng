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

    public static function label(): string { return 'Платежі'; }
    public static function singularLabel(): string { return 'Платіж'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Бронювання', 'booking', \App\Nova\Bookings\Booking::class)->searchable(),
            Number::make('Сума', 'amount')->step(0.01)->sortable()->rules('required', 'numeric', 'min:0'),
            Text::make('Валюта', 'currency')->rules('required', 'size:3'),
            Text::make('Статус', 'status')->sortable(),
            Text::make('Спосіб оплати', 'payment_method')->nullable(),
            Text::make('ID транзакції', 'transaction_id')->nullable()->hideFromIndex(),
            DateTime::make('Оплачено', 'paid_at')->nullable(),
        ];
    }
}

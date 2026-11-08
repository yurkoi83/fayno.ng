<?php

namespace App\Nova\Bookings;

use App\Nova\Resource;
use App\Nova\Camps\CampShift;
use App\Nova\People\Customer;
use App\Nova\Users\User;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;

class Order extends Resource
{
    public static $model = \App\Models\Order::class;
    public static $title = 'number';
    public static $search = ['id', 'number', 'status'];
    public static $group = 'Бронювання';

    public static function label(): string { return 'Бронювання'; }
    public static function singularLabel(): string { return 'Заявка'; }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Номер', 'number')->sortable()->rules('required'),
            BelongsTo::make('Батьки', 'customer', Customer::class)->searchable(),
            BelongsTo::make('Зміна', 'campShift', CampShift::class)->searchable(),
            Text::make('Статус', 'status')->sortable(),
            Text::make('Джерело', 'source')->hideFromIndex(),
            Text::make('Місто виїзду', 'departure_city')->hideFromIndex(),
            BelongsTo::make('Відповідальний', 'assignedTo', User::class)->nullable()->searchable(),
            DateTime::make('Відповіли', 'responded_at')->nullable()->hideFromIndex(),
            Textarea::make('Коментар', 'comment')->alwaysShow()->hideFromIndex(),
            Textarea::make('Нотатки адміністратора', 'admin_notes')->alwaysShow()->hideFromIndex(),
        ];
    }
}

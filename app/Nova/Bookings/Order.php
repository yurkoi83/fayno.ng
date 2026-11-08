<?php

namespace App\Nova\Bookings;

use App\Enums\Gender;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Nova\Camps\CampShift;
use App\Nova\People\Customer;
use App\Nova\Resource;
use App\Nova\Users\User;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;

class Order extends Resource
{
    public static $model = \App\Models\Order::class;
    public static $title = 'number';
    public static $search = ['id', 'number', 'status'];
    public static $group = 'Бронювання';

    public static function label(): string { return __('nova::navigation.bookings'); }
    public static function singularLabel(): string { return __('nova::resources.Order'); }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Panel::make(__('nova::resources.Application details'), [
                Text::make(__('nova::resources.Number'), 'number')->sortable()->rules('required'),
                Text::make(__('nova::resources.Status'), fn () => $this->statusLabel())->onlyOnDetail(),
                Text::make(__('nova::resources.Created at'), fn () => $this->created_at?->format('d.m.Y H:i'))->onlyOnDetail(),
            ]),

            Panel::make(__('nova::resources.Parent details'), [
                BelongsTo::make(__('nova::resources.Parent'), 'customer', Customer::class)->searchable(),
                Text::make(__('nova::resources.Parent phone'), fn () => $this->customer?->phone)->onlyOnDetail(),
                Text::make(__('nova::resources.Parent email'), fn () => $this->customer?->email)->onlyOnDetail(),
                Text::make(__('nova::resources.Parent city'), fn () => $this->customer?->city)->onlyOnDetail(),
                Text::make(__('nova::resources.Parent address'), fn () => $this->customer?->address)->onlyOnDetail(),
            ]),

            Panel::make(__('nova::resources.Camp and shift'), [
                BelongsTo::make(__('nova::resources.Camp shift'), 'campShift', CampShift::class)->searchable(),
                Text::make(__('nova::resources.Camp'), fn () => $this->campShift?->camp?->name)->onlyOnDetail(),
                Text::make(__('nova::resources.Shift dates'), fn () => $this->campShift
                    ? $this->campShift->starts_at?->format('d.m.Y').' — '.$this->campShift->ends_at?->format('d.m.Y')
                    : null)->onlyOnDetail(),
            ]),

            Panel::make(__('nova::resources.Children'), [
                HasMany::make(__('nova::resources.Children'), 'orderCampers', OrderCamper::class),
            ]),

            Panel::make(__('nova::resources.Financial information'), [
                Text::make(__('nova::resources.Total amount'), fn () => $this->money($this->totalAmount()))->onlyOnDetail(),
                Text::make(__('nova::resources.Paid'), fn () => $this->money($this->paidAmount()))->onlyOnDetail(),
                Text::make(__('nova::resources.Balance'), fn () => $this->money(max(0, $this->totalAmount() - $this->paidAmount())))->onlyOnDetail(),
            ]),

            Panel::make(__('nova::resources.Processing and comments'), [
                BelongsTo::make(__('nova::resources.Assigned to'), 'assignedTo', User::class)->nullable()->searchable(),
                DateTime::make(__('nova::resources.Responded at'), 'responded_at')->nullable(),
                Text::make(__('nova::resources.Source'), 'source')->hideFromIndex(),
                Text::make(__('nova::resources.Departure city'), 'departure_city')->hideFromIndex(),
                Textarea::make(__('nova::resources.Comment'), 'comment')->alwaysShow()->hideFromIndex(),
                Textarea::make(__('nova::resources.Admin notes'), 'admin_notes')->alwaysShow()->hideFromIndex(),
            ]),
        ];
    }

    protected function statusLabel(): string
    {
        return match ($this->status) {
            OrderStatus::NEW->value => __('nova::resources.New'),
            OrderStatus::PROCESSING->value => __('nova::resources.In progress'),
            OrderStatus::WAITING_CUSTOMER->value => __('nova::resources.Waiting for customer'),
            OrderStatus::CONTRACT_SENT->value => __('nova::resources.Contract sent'),
            OrderStatus::AWAITING_PAYMENT->value => __('nova::resources.Awaiting payment'),
            OrderStatus::CONFIRMED->value => __('nova::resources.Confirmed'),
            OrderStatus::REJECTED->value => __('nova::resources.Rejected'),
            default => (string) $this->status,
        };
    }

    protected function totalAmount(): float
    {
        return (float) $this->bookings()->sum('total_amount');
    }

    protected function paidAmount(): float
    {
        return (float) Payment::query()
            ->whereHas('booking.orderCamper', fn ($query) => $query->where('order_id', $this->getKey()))
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');
    }

    protected function money(float $amount): string
    {
        $currency = $this->orderCampers()->value('currency') ?: 'UAH';

        return number_format($amount, 2, ',', ' ').' '.$currency;
    }
}

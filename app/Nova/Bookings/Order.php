<?php

namespace App\Nova\Bookings;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Nova\Camps\CampShift;
use App\Nova\People\Customer;
use App\Nova\Resource;
use App\Nova\Users\User;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

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
            ID::make()->sortable()->hideFromDetail(),

            Panel::make('Заявка № '.$this->number, [
                Badge::make(__('nova::resources.Status'), 'status')
                    ->labels([
                        OrderStatus::NEW->value => __('nova::resources.New'),
                        OrderStatus::PROCESSING->value => __('nova::resources.In progress'),
                        OrderStatus::WAITING_CUSTOMER->value => __('nova::resources.Waiting for customer'),
                        OrderStatus::CONTRACT_SENT->value => __('nova::resources.Contract sent'),
                        OrderStatus::AWAITING_PAYMENT->value => __('nova::resources.Awaiting payment'),
                        OrderStatus::CONFIRMED->value => __('nova::resources.Confirmed'),
                        OrderStatus::REJECTED->value => __('nova::resources.Rejected'),
                    ])
                    ->map([
                        OrderStatus::NEW->value => 'warning',
                        OrderStatus::PROCESSING->value => 'success',
                        OrderStatus::WAITING_CUSTOMER->value => 'info',
                        OrderStatus::CONTRACT_SENT->value => 'info',
                        OrderStatus::AWAITING_PAYMENT->value => 'warning',
                        OrderStatus::CONFIRMED->value => 'success',
                        OrderStatus::REJECTED->value => 'danger',
                    ])
                    ->withIcons(),
                Text::make(__('nova::resources.Created at'), fn () => $this->created_at?->format('d.m.Y H:i')),
            ])->withAttribute('fayno-order-header')->withToolbar(),

            Panel::make(__('nova::resources.Parent details'), [
                BelongsTo::make(__('nova::resources.Parent'), 'customer', Customer::class)->searchable(),
                Text::make(__('nova::resources.Parent phone'), fn () => $this->customer?->phone)->onlyOnDetail(),
                Text::make(__('nova::resources.Parent email'), fn () => $this->customer?->email)->onlyOnDetail(),
                Text::make(__('nova::resources.Parent address'), fn () => $this->customer
                    ? trim(implode(', ', array_filter([$this->customer->city, $this->customer->address])))
                    : null)->onlyOnDetail(),
            ])->withAttribute('fayno-order-parent'),

            Panel::make(__('nova::resources.Camp and shift'), [
                BelongsTo::make(__('nova::resources.Camp shift'), 'campShift', CampShift::class)->searchable(),
                Text::make(__('nova::resources.Camp'), fn () => $this->campShift?->camp?->name)->onlyOnDetail(),
                Image::make(__('nova::resources.Photo'), fn () => $this->campShift?->camp?->photo_path)->disk('public')->onlyOnDetail(),
                Text::make(__('nova::resources.Shift dates'), fn () => $this->campShift
                    ? $this->campShift->starts_at?->format('d.m.Y').' — '.$this->campShift->ends_at?->format('d.m.Y')
                    : null)->onlyOnDetail(),
                Text::make(__('nova::resources.Duration'), fn () => $this->campShift
                    ? $this->campShift->starts_at?->diffInDays($this->campShift->ends_at).' '.__('nova::resources.Days')
                    : null)->onlyOnDetail(),
            ])->withAttribute('fayno-order-camp'),

            Panel::make(__('nova::resources.Children'), [
                HasMany::make(__('nova::resources.Children'), 'orderCampers', OrderCamper::class),
            ])->withAttribute('fayno-order-children'),

            Panel::make(__('nova::resources.Application status'), [
                Badge::make(__('nova::resources.Status'), 'status')
                    ->labels([
                        OrderStatus::NEW->value => __('nova::resources.New'),
                        OrderStatus::PROCESSING->value => __('nova::resources.In progress'),
                        OrderStatus::WAITING_CUSTOMER->value => __('nova::resources.Waiting for customer'),
                        OrderStatus::CONTRACT_SENT->value => __('nova::resources.Contract sent'),
                        OrderStatus::AWAITING_PAYMENT->value => __('nova::resources.Awaiting payment'),
                        OrderStatus::CONFIRMED->value => __('nova::resources.Confirmed'),
                        OrderStatus::REJECTED->value => __('nova::resources.Rejected'),
                    ])
                    ->map([
                        OrderStatus::NEW->value => 'success',
                        OrderStatus::PROCESSING->value => 'success',
                        OrderStatus::WAITING_CUSTOMER->value => 'warning',
                        OrderStatus::CONTRACT_SENT->value => 'info',
                        OrderStatus::AWAITING_PAYMENT->value => 'warning',
                        OrderStatus::CONFIRMED->value => 'success',
                        OrderStatus::REJECTED->value => 'danger',
                    ])
                    ->withIcons(),
                Text::make(__('nova::resources.Status description'), fn () => $this->statusDescription())->onlyOnDetail(),
            ])->withAttribute('fayno-order-status'),

            Panel::make(__('nova::resources.Financial information'), [
                Text::make(__('nova::resources.Total amount'), fn () => $this->money($this->totalAmount()))->onlyOnDetail(),
                Text::make(__('nova::resources.Paid'), fn () => $this->money($this->paidAmount()))->onlyOnDetail(),
                Text::make(__('nova::resources.Balance'), fn () => $this->money(max(0, $this->totalAmount() - $this->paidAmount())))->onlyOnDetail(),
                Text::make(__('nova::resources.Payment progress'), fn () => sprintf(
                    '<div class="fayno-payment-progress"><div class="fayno-payment-progress__track"><span style="width:%d%%"></span></div><strong>%d%%</strong></div>',
                    $this->paymentProgress(),
                    $this->paymentProgress(),
                ))->asHtml()->onlyOnDetail(),
            ])->withAttribute('fayno-order-finance'),

            Panel::make(__('nova::resources.Parent comment'), [
                Textarea::make(__('nova::resources.Comment'), 'comment')->alwaysShow()->hideFromIndex(),
            ])->withAttribute('fayno-order-comment'),

            Panel::make(__('nova::resources.Responsible manager'), [
                BelongsTo::make(__('nova::resources.Assigned to'), 'assignedTo', User::class)->nullable()->searchable(),
                Image::make(__('nova::resources.Avatar'), fn () => $this->assignedTo?->avatar_path)->disk('public')->onlyOnDetail(),
                DateTime::make(__('nova::resources.Responded at'), 'responded_at')->nullable(),
            ])->withAttribute('fayno-order-manager'),

            Panel::make(__('nova::resources.Application timeline'), [
                Text::make(__('nova::resources.Status changes'), fn () => $this->statusHistoryHtml())->asHtml()->onlyOnDetail(),
            ])->withAttribute('fayno-order-history'),

            Panel::make(__('nova::resources.Payments'), [
                Text::make(__('nova::resources.Payment history'), fn () => $this->paymentListHtml())->asHtml()->onlyOnDetail(),
            ])->withAttribute('fayno-order-payments'),

            Panel::make(__('nova::resources.Internal information'), [
                Text::make(__('nova::resources.Source'), 'source')->hideFromIndex(),
                Text::make(__('nova::resources.Departure city'), 'departure_city')->hideFromIndex(),
                Textarea::make(__('nova::resources.Admin notes'), 'admin_notes')->alwaysShow()->hideFromIndex(),
            ])->withAttribute('fayno-order-internal')->collapsedByDefault(),
        ];
    }


    protected function statusHistoryHtml(): string
    {
        $items = $this->resource->statusHistories()->with('changedBy')->latest()->limit(8)->get();

        if ($items->isEmpty()) {
            return '<p class="fayno-order-empty">'.e(__('nova::resources.No status changes yet')).'</p>';
        }

        return '<div class="fayno-order-timeline">'. $items->map(function ($item) {
            $label = $item->from_status
                ? __('nova::resources.Status changed to').' '.$this->statusLabelFor($item->to_status)
                : __('nova::resources.Application created').' — '.$this->statusLabelFor($item->to_status);
            $date = $item->created_at?->format('d.m.Y H:i') ?? '';
            $actor = $item->changedBy?->name ?? __('nova::resources.System');

            return '<div class="fayno-order-timeline__item">'
                .'<span class="fayno-order-timeline__dot"></span>'
                .'<div class="fayno-order-timeline__body">'
                .'<time>'.e($date).'</time>'
                .'<strong>'.e($label).'</strong>'
                .'<small>'.e($actor).'</small>'
                .($item->comment ? '<p>'.e($item->comment).'</p>' : '')
                .'</div></div>';
        })->implode('').'</div>';
    }

    protected function paymentListHtml(): string
    {
        $payments = Payment::query()
            ->whereHas('booking.orderCamper', fn ($query) => $query->where('order_id', $this->resource->getKey()))
            ->latest('created_at')
            ->limit(5)
            ->get();

        if ($payments->isEmpty()) {
            return '<p class="fayno-order-empty">'.e(__('nova::resources.No payments yet')).'</p>';
        }

        return '<div class="fayno-payment-list">'. $payments->map(function ($payment) {
            $paid = $payment->status === PaymentStatus::PAID->value;
            $status = $paid ? __('nova::resources.Paid') : match ($payment->status) {
                PaymentStatus::PENDING->value => __('nova::resources.Awaiting payment'),
                PaymentStatus::FAILED->value => __('nova::resources.Failed'),
                PaymentStatus::REFUNDED->value => __('nova::resources.Refunded'),
                default => (string) $payment->status,
            };
            $date = ($payment->paid_at ?? $payment->created_at)?->format('d.m.Y, H:i') ?? '';
            $method = $payment->payment_method ?: __('nova::resources.Payment');
            $amount = number_format((float) $payment->amount, 2, ',', ' ').' '.$payment->currency;

            return '<div class="fayno-payment-item">'
                .'<span class="fayno-payment-item__icon '.($paid ? 'is-paid' : 'is-pending').'">'.($paid ? '✓' : '◷').'</span>'
                .'<div class="fayno-payment-item__details"><strong>'.e($method).'</strong><small>'.e($date).' · '.e($status).'</small></div>'
                .'<b>'.e($amount).'</b>'
                .'</div>';
        })->implode('').'</div>';
    }

    protected function statusLabelFor(?string $status): string
    {
        return match ($status) {
            OrderStatus::NEW->value => __('nova::resources.New'),
            OrderStatus::PROCESSING->value => __('nova::resources.In progress'),
            OrderStatus::WAITING_CUSTOMER->value => __('nova::resources.Waiting for customer'),
            OrderStatus::CONTRACT_SENT->value => __('nova::resources.Contract sent'),
            OrderStatus::AWAITING_PAYMENT->value => __('nova::resources.Awaiting payment'),
            OrderStatus::CONFIRMED->value => __('nova::resources.Confirmed'),
            OrderStatus::REJECTED->value => __('nova::resources.Rejected'),
            default => '',
        };
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

    protected function statusDescription(): string
    {
        return match ($this->status) {
            OrderStatus::NEW->value => __('nova::resources.New application awaiting review'),
            OrderStatus::PROCESSING->value => __('nova::resources.Application is being processed'),
            OrderStatus::WAITING_CUSTOMER->value => __('nova::resources.Waiting for parent response'),
            OrderStatus::CONTRACT_SENT->value => __('nova::resources.Contract sent to parent'),
            OrderStatus::AWAITING_PAYMENT->value => __('nova::resources.Waiting for payment'),
            OrderStatus::CONFIRMED->value => __('nova::resources.Application confirmed'),
            OrderStatus::REJECTED->value => __('nova::resources.Application rejected'),
            default => '',
        };
    }

    protected function paymentProgress(): int
    {
        $total = $this->totalAmount();

        return $total > 0 ? min(100, (int) round($this->paidAmount() / $total * 100)) : 0;
    }

    protected function totalAmount(): float
    {
        return (float) $this->resource->bookings()->sum('total_amount');
    }

    protected function paidAmount(): float
    {
        return (float) Payment::query()
            ->whereHas('booking.orderCamper', fn ($query) => $query->where('order_id', $this->resource->getKey()))
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');
    }

    protected function pendingAmount(): float
    {
        return (float) Payment::query()
            ->whereHas('booking.orderCamper', fn ($query) => $query->where('order_id', $this->resource->getKey()))
            ->where('status', PaymentStatus::PENDING->value)
            ->sum('amount');
    }

    protected function lastPaymentLabel(): string
    {
        $payment = Payment::query()
            ->whereHas('booking.orderCamper', fn ($query) => $query->where('order_id', $this->resource->getKey()))
            ->latest('paid_at')
            ->first();

        if (! $payment) {
            return '—';
        }

        return trim(($payment->paid_at?->format('d.m.Y H:i') ?? '').' '.$payment->payment_method);
    }

    protected function money(float $amount): string
    {
        $currency = $this->resource->orderCampers()->value('currency') ?: 'UAH';

        return number_format($amount, 2, ',', ' ').' '.$currency;
    }
}

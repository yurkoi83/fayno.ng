<?php

namespace App\\Nova\\Bookings;

use App\\Enums\\OrderStatus;
use App\\Enums\\PaymentStatus;
use App\\Models\\Payment;
use App\\Nova\\Camps\\CampShift as CampShiftResource;
use App\\Nova\\People\\Customer as CustomerResource;
use App\\Nova\\Resource;
use App\\Nova\\Users\\User as UserResource;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Storage;
use Laravel\\Nova\\Fields\\BelongsTo;
use Laravel\\Nova\\Fields\\DateTime;
use Laravel\\Nova\\Fields\\HasMany;
use Laravel\\Nova\\Fields\\ID;
use Laravel\\Nova\\Fields\\Select;
use Laravel\\Nova\\Fields\\Textarea;
use Laravel\\Nova\\Fields\\Text;
use Laravel\\Nova\\Panel;

class Order extends Resource
{
    public static $model = \\App\\Models\\Order::class;
    public static $title = 'number';
    public static $search = ['id', 'number', 'status'];
    public static $group = 'Бронювання';

    public static function label(): string
    {
        return __('nova::navigation.bookings');
    }

    public static function singularLabel(): string
    {
        return __('nova::resources.Order');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable()->hideFromDetail(),

            Panel::make('fayno-crm-layout', [
                Text::make(' ', fn () => $this->detailHtml())
                    ->asHtml()
                    ->onlyOnDetail(),
            ])->withToolbar(),

            Panel::make(__('nova::resources.Application details'), [
                BelongsTo::make(__('nova::resources.Parent'), 'customer', CustomerResource::class)
                    ->searchable()
                    ->onlyOnForms(),
                BelongsTo::make(__('nova::resources.Camp shift'), 'campShift', CampShiftResource::class)
                    ->searchable()
                    ->onlyOnForms(),
                Select::make(__('nova::resources.Status'), 'status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($status) => [
                        $status->value => __('nova::resources.'.match ($status) {
                            OrderStatus::NEW => 'New',
                            OrderStatus::PROCESSING => 'In progress',
                            OrderStatus::WAITING_CUSTOMER => 'Waiting for customer',
                            OrderStatus::CONTRACT_SENT => 'Contract sent',
                            OrderStatus::AWAITING_PAYMENT => 'Awaiting payment',
                            OrderStatus::CONFIRMED => 'Confirmed',
                            OrderStatus::REJECTED => 'Rejected',
                        }),
                    ])->all())
                    ->onlyOnForms(),
                BelongsTo::make(__('nova::resources.Assigned to'), 'assignedTo', UserResource::class)
                    ->nullable()
                    ->searchable()
                    ->onlyOnForms(),
                DateTime::make(__('nova::resources.Responded at'), 'responded_at')
                    ->nullable()
                    ->onlyOnForms(),
                Textarea::make(__('nova::resources.Comment'), 'comment')
                    ->alwaysShow()
                    ->onlyOnForms(),
                Text::make(__('nova::resources.Source'), 'source')->onlyOnForms(),
                Text::make(__('nova::resources.Departure city'), 'departure_city')->onlyOnForms(),
                Textarea::make(__('nova::resources.Admin notes'), 'admin_notes')->alwaysShow()->onlyOnForms(),
                HasMany::make(__('nova::resources.Children'), 'orderCampers', OrderCamper::class)->onlyOnForms(),
            ]),
        ];
    }

    protected function detailHtml(): string
    {
        $order = $this->resource;
        $customer = $order->customer;
        $shift = $order->campShift;
        $camp = $shift?->camp;
        $manager = $order->assignedTo;
        $children = $order->orderCampers()->with('camper', 'booking')->get();

        $total = (float) $order->bookings()->sum('total_amount');
        $paymentsQuery = Payment::query()
            ->whereHas('booking.orderCamper', fn ($query) => $query->where('order_id', $order->getKey()));
        $paid = (float) (clone $paymentsQuery)->where('status', PaymentStatus::PAID->value)->sum('amount');
        $pending = (float) (clone $paymentsQuery)->where('status', PaymentStatus::PENDING->value)->sum('amount');
        $payments = (clone $paymentsQuery)->with('booking')->latest('created_at')->limit(5)->get();
        $balance = max(0, $total - $paid);
        $progress = $total > 0 ? min(100, (int) round($paid / $total * 100)) : 0;
        $currency = $children->first()?->currency ?: 'UAH';

        $parentName = $customer
            ? trim(implode(' ', array_filter([$customer->last_name, $customer->first_name, $customer->middle_name])))
            : '—';
        $parentAddress = $customer
            ? trim(implode(', ', array_filter([$customer->city, $customer->address])))
            : '';
        $campPhoto = $camp?->photo_path ? Storage::disk('public')->url($camp->photo_path) : null;
        $managerPhoto = $manager?->avatar_path ? Storage::disk('public')->url($manager->avatar_path) : null;
        $statusLabel = $this->statusLabel((string) $order->status);
        $statusDescription = $this->statusDescription((string) $order->status);
        $statusClass = $this->statusClass((string) $order->status);

        $html = '<div class="fayno-crm">';
        $html .= '<div class="fayno-crm__main">';

        $html .= '<section class="fayno-crm-card fayno-crm-parent">'
            . $this->cardHeading('◉', __('nova::resources.Parent details'))
            . '<h3>'.e($parentName).'</h3>'
            . '<div class="fayno-crm-lines">'
            . $this->detailLine('☎', $customer?->phone ?: '—')
            . $this->detailLine('✉', $customer?->email ?: '—')
            . ($parentAddress !== '' ? $this->detailLine('⌖', $parentAddress) : '')
            . '</div></section>';

        $html .= '<section class="fayno-crm-card fayno-crm-camp">'
            . $this->cardHeading('▲', __('nova::resources.Camp and shift'));
        if ($campPhoto) {
            $html .= '<img class="fayno-crm-camp__image" src="'.e($campPhoto).'" alt="">';
        }
        $html .= '<div class="fayno-crm-camp__copy">'
            . '<h3>'.e($camp?->name ?: '—').'</h3>'
            . '<p>'.e($shift?->name ?: '—').'</p>'
            . '<div class="fayno-crm-lines">'
            . $this->detailLine('▦', $shift ? $shift->starts_at?->format('d.m.Y').' — '.$shift->ends_at?->format('d.m.Y') : '—')
            . $this->detailLine('◷', $shift ? (string) $shift->starts_at?->diffInDays($shift->ends_at).' '.__('nova::resources.Days') : '—')
            . '</div></div></section>';

        $html .= '<section class="fayno-crm-card fayno-crm-children">'
            . $this->cardHeading('●', __('nova::resources.Children'), '<span class="fayno-crm-count">'.$children->count().' '.__('nova::resources.Children').'</span>');
        if ($children->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">'.e(__('nova::resources.No children added')).'</p>';
        } else {
            $html .= '<div class="fayno-crm-child-grid">';
            foreach ($children as $item) {
                $camper = $item->camper;
                $childName = $camper
                    ? trim(implode(' ', array_filter([$camper->last_name, $camper->first_name, $camper->middle_name])))
                    : '—';
                $photo = $camper?->photo_path ? Storage::disk('public')->url($camper->photo_path) : null;
                $age = $camper?->birth_date ? $camper->birth_date->age.' '.__('nova::resources.Years') : '—';
                $price = number_format((float) $item->price, 0, ',', ' ').' '.($item->currency ?: $currency);
                $html .= '<article class="fayno-crm-child">';
                $html .= $photo ? '<img src="'.e($photo).'" alt="">' : '<span class="fayno-crm-child__avatar">'.e(mb_substr($childName, 0, 1)).'</span>';
                $html .= '<div class="fayno-crm-child__identity"><strong>'.e($childName).'</strong><small>'.e($age).'</small></div>';
                $html .= '<div class="fayno-crm-child__price"><strong>'.e($price).'</strong>';
                if ((float) $item->discount > 0) {
                    $html .= '<small>'.e(__('nova::resources.Discount')).': '.e(number_format((float) $item->discount, 0, ',', ' ').' '.($item->currency ?: $currency)).'</small>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';

        if ($order->comment) {
            $html .= '<section class="fayno-crm-card fayno-crm-comment">'
                . $this->cardHeading('●', __('nova::resources.Parent comment'))
                . '<p>'.nl2br(e($order->comment)).'</p></section>';
        }

        $history = $order->statusHistories()->with('changedBy')->latest()->limit(8)->get();
        $html .= '<section class="fayno-crm-card fayno-crm-history">'
            . $this->cardHeading('◷', __('nova::resources.Application timeline'));
        if ($history->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">'.e(__('nova::resources.No status changes yet')).'</p>';
        } else {
            $html .= '<div class="fayno-crm-timeline">';
            foreach ($history->reverse() as $entry) {
                $label = $entry->from_status
                    ? __('nova::resources.Status changed to').' '.$this->statusLabel((string) $entry->to_status)
                    : __('nova::resources.Application created').' — '.$this->statusLabel((string) $entry->to_status);
                $html .= '<div class="fayno-crm-timeline__item">'
                    . '<time>'.e($entry->created_at?->format('d.m.Y H:i') ?: '').'</time>'
                    . '<span class="fayno-crm-timeline__dot"></span>'
                    . '<div><strong>'.e($label).'</strong><small>'.e($entry->changedBy?->name ?: __('nova::resources.System')).'</small></div>'
                    . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';
        $html .= '</div>';

        $html .= '<aside class="fayno-crm__aside">';
        $html .= '<section class="fayno-crm-card fayno-crm-status is-'.e($statusClass).'">'
            . '<div class="fayno-crm-status__icon">✓</div><div><strong>'.e($statusLabel).'</strong><p>'.e($statusDescription).'</p></div></section>';

        $html .= '<section class="fayno-crm-card fayno-crm-finance">'
            . $this->cardHeading('▣', __('nova::resources.Financial information'))
            . '<div class="fayno-crm-money-row"><span>'.e(__('nova::resources.Total amount')).'</span><strong>'.e($this->money($total, $currency)).'</strong></div>'
            . '<div class="fayno-crm-money-row"><span>'.e(__('nova::resources.Paid')).'</span><strong class="is-paid">'.e($this->money($paid, $currency)).'</strong></div>'
            . '<div class="fayno-crm-money-row"><span>'.e(__('nova::resources.Balance')).'</span><strong class="is-balance">'.e($this->money($balance, $currency)).'</strong></div>'
            . '<div class="fayno-crm-progress"><span style="width:'.$progress.'%"></span></div>'
            . '<div class="fayno-crm-progress-label"><strong>'.$progress.'%</strong><span>'.e(__('nova::resources.Payment progress')).'</span></div>'
            . '<a class="fayno-crm-link" href="/nova/resources/payments">'.e(__('nova::resources.Go to payments')).' →</a>'
            . '</section>';

        $html .= '<section class="fayno-crm-card fayno-crm-manager">'
            . '<span class="fayno-crm-eyebrow">'.e(__('nova::resources.Responsible manager')).'</span>'
            . '<div class="fayno-crm-person">';
        $html .= $managerPhoto ? '<img src="'.e($managerPhoto).'" alt="">' : '<span class="fayno-crm-person__avatar">'.e(mb_substr($manager?->name ?: '—', 0, 1)).'</span>';
        $html .= '<div><strong>'.e($manager?->name ?: '—').'</strong><small>'.e(__('nova::resources.Responsible manager')).'</small></div></div>'
            . '<div class="fayno-crm-manager__response"><span>'.e(__('nova::resources.Responded at')).'</span><strong>'.e($order->responded_at?->format('d.m.Y H:i') ?: '—').'</strong></div>'
            . '</section>';

        $html .= '<section class="fayno-crm-card fayno-crm-payments">'
            . $this->cardHeading('▣', __('nova::resources.Payments'), '<a href="/nova/resources/payments">'.e(__('nova::resources.All payments')).' →</a>');
        if ($payments->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">'.e(__('nova::resources.No payments yet')).'</p>';
        } else {
            foreach ($payments as $payment) {
                $isPaid = $payment->status === PaymentStatus::PAID->value;
                $paymentStatus = $isPaid ? __('nova::resources.Paid') : (string) $payment->status;
                $html .= '<div class="fayno-crm-payment"><span class="fayno-crm-payment__icon '.($isPaid ? 'is-paid' : '').'">'.($isPaid ? '✓' : '◷').'</span>'
                    . '<div><strong>'.e($payment->payment_method ?: __('nova::resources.Payment')).'</strong><small>'.e(($payment->paid_at ?? $payment->created_at)?->format('d.m.Y, H:i') ?: '').' · '.e($paymentStatus).'</small></div>'
                    . '<b>'.e($this->money((float) $payment->amount, $payment->currency ?: $currency)).'</b></div>';
            }
        }
        $html .= '</section>';
        $html .= '</aside></div>';

        return $html;
    }

    protected function cardHeading(string $icon, string $title, string $right = ''): string
    {
        return '<div class="fayno-crm-card__heading"><span class="fayno-crm-card__icon">'.e($icon).'</span><h2>'.e($title).'</h2>'.$right.'</div>';
    }

    protected function detailLine(string $icon, string $value): string
    {
        return '<div class="fayno-crm-line"><span>'.e($icon).'</span><p>'.e($value).'</p></div>';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            OrderStatus::NEW->value => __('nova::resources.New'),
            OrderStatus::PROCESSING->value => __('nova::resources.In progress'),
            OrderStatus::WAITING_CUSTOMER->value => __('nova::resources.Waiting for customer'),
            OrderStatus::CONTRACT_SENT->value => __('nova::resources.Contract sent'),
            OrderStatus::AWAITING_PAYMENT->value => __('nova::resources.Awaiting payment'),
            OrderStatus::CONFIRMED->value => __('nova::resources.Confirmed'),
            OrderStatus::REJECTED->value => __('nova::resources.Rejected'),
            default => $status,
        };
    }

    protected function statusDescription(string $status): string
    {
        return match ($status) {
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

    protected function statusClass(string $status): string
    {
        return match ($status) {
            OrderStatus::CONFIRMED->value, OrderStatus::PROCESSING->value => 'success',
            OrderStatus::REJECTED->value => 'danger',
            OrderStatus::WAITING_CUSTOMER->value, OrderStatus::AWAITING_PAYMENT->value => 'warning',
            default => 'info',
        };
    }

    protected function money(float $amount, string $currency = 'UAH'): string
    {
        return number_format($amount, 0, ',', ' ').' '.$currency;
    }
}

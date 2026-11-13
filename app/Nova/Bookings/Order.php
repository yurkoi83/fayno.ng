<?php

namespace App\Nova\Bookings;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Nova\Camps\CampShift as CampShiftResource;
use App\Nova\Campers\Customer as CustomerResource;
use App\Nova\Resource;
use App\Nova\Users\User as UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

class Order extends Resource
{
    public static $model = \App\Models\Order::class;
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

    public static function indexQuery(\Laravel\Nova\Http\Requests\NovaRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        return $query->with([
            'customer',
            'campShift.camp',
            'orderCampers.camper',
            'bookings',
        ]);
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable()->hideFromDetail()->hideFromIndex(),

            Text::make('Номер заявки', 'number')
                ->sortable()
                ->copyable()
                ->onlyOnIndex(),

            Text::make('Батьки', function () {
                $customer = $this->customer;

                return $customer
                    ? trim(implode(' ', array_filter([
                        $customer->last_name,
                        $customer->first_name,
                        $customer->middle_name,
                    ])))
                    : '—';
            })->onlyOnIndex(),

            Text::make('Учасники', function () {
                return $this->orderCampers
                    ->map(function ($item) {
                        $camper = $item->camper;

                        return $camper
                            ? trim(implode(' ', array_filter([
                                $camper->last_name,
                                $camper->first_name,
                                $camper->middle_name,
                            ])))
                            : null;
                    })
                    ->filter()
                    ->implode(', ') ?: '—';
            })->onlyOnIndex(),

            Text::make('Табір / зміна', function () {
                $camp = $this->campShift?->camp?->name;
                $shift = $this->campShift?->name;

                return collect([$camp, $shift])->filter()->implode(' / ') ?: '—';
            })->onlyOnIndex(),

            Badge::make('Статус', 'status')
                ->map([
                    OrderStatus::NEW->value => 'info',
                    OrderStatus::PROCESSING->value => 'info',
                    OrderStatus::WAITING_CUSTOMER->value => 'warning',
                    OrderStatus::CONTRACT_SENT->value => 'warning',
                    OrderStatus::AWAITING_PAYMENT->value => 'warning',
                    OrderStatus::CONFIRMED->value => 'success',
                    OrderStatus::REJECTED->value => 'danger',
                ])
                ->labels([
                    OrderStatus::NEW->value => 'Нова',
                    OrderStatus::PROCESSING->value => 'В обробці',
                    OrderStatus::WAITING_CUSTOMER->value => 'Очікує відповіді',
                    OrderStatus::CONTRACT_SENT->value => 'Договір надіслано',
                    OrderStatus::AWAITING_PAYMENT->value => 'Очікує оплати',
                    OrderStatus::CONFIRMED->value => 'Підтверджена',
                    OrderStatus::REJECTED->value => 'Відхилена',
                ])
                ->sortable()
                ->onlyOnIndex(),

            Text::make('Сума', function () {
                $booking = $this->bookings->first();
                $amount = (float) $this->bookings->sum('total_amount');
                $currency = $booking?->currency ?: 'UAH';

                return number_format($amount, 0, ',', ' ').' '.$currency;
            })->onlyOnIndex(),

            DateTime::make('Дата заявки', 'created_at')
                ->sortable()
                ->onlyOnIndex(),

            Panel::make('', [
                Text::make(' ', fn () => $this->detailHtml())
                    ->asHtml()
                    ->onlyOnDetail(),
            ]),

            Panel::make('Деталі заявки', [
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
        $payments = (clone $paymentsQuery)->with('booking')->latest('created_at')->limit(8)->get();
        $balance = max(0, $total - $paid);
        $progress = $total > 0 ? min(100, (int) round($paid / $total * 100)) : 0;
        $currency = $children->first()?->currency ?: 'UAH';

        $parentName = $customer
            ? trim(implode(' ', array_filter([$customer->last_name, $customer->first_name, $customer->middle_name])))
            : '—';
        $campPhoto = $camp?->photo_path
            ? Storage::disk('public')->url($camp->photo_path)
            : (str_contains(mb_strtolower((string) $camp?->name), 'zakopane')
                ? 'https://fayno.camp/wp-content/uploads/9-3-300x200.jpg'
                : null);
        $managerPhoto = $manager?->avatar_path ? Storage::disk('public')->url($manager->avatar_path) : null;
        $statusLabel = $this->statusLabel((string) $order->status);
        $statusDescription = $this->statusDescription((string) $order->status);
        $statusClass = $this->statusClass((string) $order->status);
        $editUrl = url('/nova/resources/orders/'.$order->getKey().'/edit');
        $indexUrl = url('/nova/resources/orders');

        $html = '<div class="fayno-crm">';
        $html .= '<header class="fayno-crm__heading">'
            . '<div class="fayno-crm__heading-main">'
            . '<nav class="fayno-crm__breadcrumbs"><a href="'.$indexUrl.'">⌂</a><span>›</span><a href="'.$indexUrl.'">Заявки</a><span>›</span><strong>'.e($order->number ?: '#'.$order->getKey()).'</strong></nav>'
            . '<div class="fayno-crm__title-row"><span class="fayno-crm__title-icon">'.$this->iconSvg('file-text').'</span><div><h1>Заявка #'.e($order->number ?: $order->getKey()).'</h1><p>Створена '.e($order->created_at?->format('d.m.Y, H:i') ?: '—').'</p></div></div>'
            . '</div><div class="fayno-crm__actions">'
            . ($customer?->email ? '<a class="fayno-crm-action fayno-crm-action--secondary" href="mailto:'.e($customer->email).'">'.$this->iconSvg('message-square').' Повідомлення</a>' : '')
            . '<a class="fayno-crm-action fayno-crm-action--primary" href="'.$editUrl.'">'.$this->iconSvg('pencil').' Редагувати</a>'
            . '</div></header>';

        $html .= '<div class="fayno-crm__content">';
        $html .= '<div class="fayno-crm__top">';

        $html .= '<section class="fayno-crm-card fayno-crm-parent">'
            . $this->cardHeading('users-round', 'Батьки')
            . '<h3>'.e($parentName).'</h3><div class="fayno-crm-lines">'
            . $this->detailLine('phone', $customer?->phone ?: '—')
            . $this->detailLine('mail', $customer?->email ?: '—')
            . '</div></section>';

        $firstChild = $children->first()?->camper;
        $firstChildName = $firstChild
            ? trim(implode(' ', array_filter([$firstChild->last_name, $firstChild->first_name, $firstChild->middle_name])))
            : '—';
        $firstChildAge = $firstChild?->birth_date ? $firstChild->birth_date->age.' '.__('nova::resources.Years') : '—';
        $firstChildPhoto = $firstChild?->photo_path ? Storage::disk('public')->url($firstChild->photo_path) : null;
        $firstChildAvatar = $firstChildPhoto
            ? '<img src="'.e($firstChildPhoto).'" alt="">'
            : $this->demoAvatar('child');
        $html .= '<section class="fayno-crm-card fayno-crm-child-summary">'
            . $this->cardHeading('user-round', 'Дитина')
            . '<div class="fayno-crm-summary-person">'.$firstChildAvatar
            . '<div><strong>'.e($firstChildName).'</strong><small>'.e($firstChildAge).'</small></div></div></section>';

        $html .= '<section class="fayno-crm-card fayno-crm-camp">'
            . $this->cardHeading('tent', 'Табір та зміна');
        if ($campPhoto) {
            $html .= '<img class="fayno-crm-camp__image" src="'.e($campPhoto).'" alt="">';
        }
        $html .= '<div class="fayno-crm-camp__copy"><h3>'.e($camp?->name ?: '—').'</h3>'
            . '<p>'.e($shift?->name ?: '—').'</p><div class="fayno-crm-lines">'
            . $this->detailLine('calendar-days', $shift ? $shift->starts_at?->format('d.m').' — '.$shift->ends_at?->format('d.m.Y') : '—')
            . '</div></div></section>';
        $html .= '</div>';

        $html .= '<section class="fayno-crm-card fayno-crm-details">'
            . $this->cardHeading('list-details', 'Дані заявки')
            . '<div class="fayno-crm-details__grid">'
            . $this->detailValue('Кількість дітей', (string) $children->count())
            . $this->detailValue('Знижка', $children->sum('discount') > 0 ? $this->money((float) $children->sum('discount'), $currency) : '—')
            . $this->detailValue('Коментар батьків', $order->comment ?: '—')
            . $this->detailValue('Промокод', '—')
            . $this->detailValue('Джерело', $order->source ?: '—')
            . $this->detailValue('Місто виїзду', $order->departure_city ?: '—')
            . '</div></section>';

        $html .= '<section class="fayno-crm-card fayno-crm-children">'
            . $this->cardHeading('users-round', 'Дитина / Учасник', '<span class="fayno-crm-count">'.$children->count().' '.e('учасн.').'</span>');
        if ($children->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">Дітей ще не додано</p>';
        } else {
            $html .= '<div class="fayno-crm-participants">';
            foreach ($children as $item) {
                $camper = $item->camper;
                $childName = $camper
                    ? trim(implode(' ', array_filter([$camper->last_name, $camper->first_name, $camper->middle_name])))
                    : '—';
                $photo = $camper?->photo_path ? Storage::disk('public')->url($camper->photo_path) : null;
                $age = $camper?->birth_date ? $camper->birth_date->age.' '.__('nova::resources.Years') : '—';
                $price = number_format((float) $item->price, 0, ',', ' ').' '.($item->currency ?: $currency);
                $html .= '<article class="fayno-crm-participant">';
                $html .= $photo
                    ? '<img src="'.e($photo).'" alt="">'
                    : $this->demoAvatar('child');
                $html .= '<div class="fayno-crm-participant__identity"><strong>'.e($childName).'</strong><small>'.e($age).'</small></div>'
                    . '<span class="fayno-crm-pill">Основний учасник</span>'
                    . '<span class="fayno-crm-health">'.$this->iconSvg('check').' Учасник</span>'
                    . '<strong class="fayno-crm-participant__price">'.e($price).'</strong>'
                    . '</article>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';

        $html .= '<section class="fayno-crm-card fayno-crm-payments">'
            . $this->cardHeading('credit-card', 'Платежі', '<a href="/nova/resources/payments">Усі платежі →</a>');
        if ($payments->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">Платежів ще немає</p>';
        } else {
            $html .= '<div class="fayno-crm-table-wrap"><table class="fayno-crm-table"><thead><tr><th>Дата</th><th>Опис</th><th>Сума</th><th>Статус</th><th></th></tr></thead><tbody>';
            foreach ($payments as $payment) {
                $isPaid = $payment->status === PaymentStatus::PAID->value;
                $paymentStatus = $isPaid ? 'Оплачено' : (string) $payment->status;
                $html .= '<tr><td>'.e(($payment->paid_at ?? $payment->created_at)?->format('d.m.Y H:i') ?: '—').'</td>'
                    . '<td>'.e($payment->payment_method ?: 'Оплата заявки').'</td>'
                    . '<td><strong>'.e($this->money((float) $payment->amount, $payment->currency ?: $currency)).'</strong></td>'
                    . '<td><span class="fayno-crm-payment-status '.($isPaid ? 'is-paid' : 'is-pending').'">'.$this->iconSvg($isPaid ? 'check' : 'clock-3').e($paymentStatus).'</span></td>'
                    . '<td>↓</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        $history = $order->statusHistories()->with('changedBy')->latest()->limit(8)->get();
        $html .= '<section class="fayno-crm-card fayno-crm-history">'
            . $this->cardHeading('history', 'Хронологія заявки');
        if ($history->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">Історії змін поки немає</p>';
        } else {
            $html .= '<div class="fayno-crm-timeline">';
            foreach ($history->reverse() as $entry) {
                $label = $entry->from_status
                    ? 'Статус змінено на '.$this->statusLabel((string) $entry->to_status)
                    : 'Заявку створено — '.$this->statusLabel((string) $entry->to_status);
                $html .= '<div class="fayno-crm-timeline__item"><time>'.e($entry->created_at?->format('d.m.Y H:i') ?: '').'</time>'
                    . '<span class="fayno-crm-timeline__dot"></span><div><strong>'.e($label).'</strong><small>'.e($entry->changedBy?->name ?: 'Система').'</small></div></div>';
            }
            $html .= '</div>';
        }
        $html .= '</section></div>';

        $html .= '<aside class="fayno-crm__aside">';
        $html .= '<section class="fayno-crm-card fayno-crm-status is-'.e($statusClass).'">'
            . '<div class="fayno-crm-status__icon">'.$this->iconSvg('clock-3').'</div><div><strong>'.e($statusLabel).'</strong><p>'.e($statusDescription).'</p></div></section>';
        $html .= '<section class="fayno-crm-card fayno-crm-finance">'
            . $this->cardHeading('wallet', 'Фінанси')
            . '<div class="fayno-crm-finance__total"><strong>'.e($this->money($total, $currency)).'</strong><span class="fayno-crm-payment-status '.($balance <= 0 && $total > 0 ? 'is-paid' : 'is-pending').'">'.($balance <= 0 && $total > 0 ? '✓ Оплачено' : 'Очікує оплати').'</span></div>'
            . '<div class="fayno-crm-money-row"><span>До сплати</span><strong>'.e($this->money($balance, $currency)).'</strong></div>'
            . '<div class="fayno-crm-progress"><span style="width:'.$progress.'%"></span></div>'
            . '<a class="fayno-crm-link" href="/nova/resources/payments">Деталі платежів →</a></section>';

        $html .= '<section class="fayno-crm-card fayno-crm-manager">'
            . $this->cardHeading('user-round', 'Менеджер')
            . '<div class="fayno-crm-person">';
        $html .= $managerPhoto
            ? '<img src="'.e($managerPhoto).'" alt="">'
            : $this->demoAvatar('manager');
        $html .= '<div><strong>'.e($manager?->name ?: 'Не призначений').'</strong><small>Менеджер з бронювання</small></div></div>'
            . ($manager?->email ? '<a class="fayno-crm-manager__message" href="mailto:'.e($manager->email).'">'.$this->iconSvg('mail').' Написати</a>' : '')
            . '</section>';

        $html .= '<section class="fayno-crm-promo"><div class="fayno-crm-promo__art">'.$this->iconSvg('tent').'</div>'
            . '<strong>Fayno Camp</strong><span>Більше, ніж табір</span>'
            . '<a href="https://fayno.camp/" target="_blank" rel="noopener">Перейти на сайт →</a></section>';
        $html .= '</aside></div></div>';

        return $html;
    }

    protected function detailValue(string $label, string $value): string
    {
        return '<div class="fayno-crm-detail-value"><span>'.e($label).'</span><strong>'.e($value).'</strong></div>';
    }

    protected function iconSvg(string $name): string
    {
        $paths = [
            'user-round' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.2 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72l.45 3.1a2 2 0 0 1-.57 1.7L7.1 10.4a16 16 0 0 0 6 6l1.88-1.89a2 2 0 0 1 1.7-.57l3.1.45a2 2 0 0 1 1.72 2.53z"/>',
            'mountain' => '<path d="m3 20 7-13 4 7 2-3 5 9H3Z"/><path d="m8 11 2 2 2-2"/>',
            'message-square' => '<path d="M21 11.5a8.5 8.5 0 0 1-9 8.5 9 9 0 0 1-4-.9L3 21l1.9-4.5A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 11h8M8 15h5"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
            'tent' => '<path d="m3 21 9-18 9 18M3 21h18M12 3l-3 18m6 0-3-18"/>',
            'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h.01M12 15h.01M16 15h.01M8 18h.01M12 18h.01"/>',
            'clock-3' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'message-square' => '<path d="M21 11.5a8.5 8.5 0 0 1-9 8.5 9 9 0 0 1-4-.9L3 21l1.9-4.5A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 11h8M8 15h5"/>',
            'users-round' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
            'wallet' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 8h18M16 14h.01"/>',
            'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h3"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'pencil' => '<path d="m16 4 4 4L8 20H4v-4L16 4Z"/><path d="m14 6 4 4"/>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
            'list-details' => '<path d="M13 5h8M13 12h8M13 19h8"/><path d="M3 5h.01M3 12h.01M3 19h.01"/>',
            'arrow-left' => '<path d="m12 19-7-7 7-7M5 12h14"/>',
        ];

        $content = $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';
        return '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$content.'</svg>';
    }

    protected function demoAvatar(string $type = 'child'): string
    {
        $palette = $type === 'manager'
            ? ['#d9f2eb', '#21866c', '#155b50', '#f2bd91']
            : ['#e2edff', '#3979c9', '#244f91', '#f0bd98'];

        [$background, $shirt, $hair, $skin] = $palette;

        return '<span class="fayno-demo-avatar fayno-demo-avatar--'.e($type).'" aria-hidden="true">'
            . '<svg viewBox="0 0 64 64" role="presentation" focusable="false">'
            . '<circle cx="32" cy="32" r="32" fill="'.$background.'"/>'
            . '<path d="M10 64c2-13 10-20 22-20s20 7 22 20" fill="'.$shirt.'"/>'
            . '<path d="M20 27c0-12 5-19 13-19 9 0 14 7 13 19l-3 9H23z" fill="'.$hair.'"/>'
            . '<path d="M23 25c0-8 4-13 9-13s9 5 9 13v8c0 8-4 13-9 13s-9-5-9-13z" fill="'.$skin.'"/>'
            . '<path d="M22 25c1-10 5-16 12-16 7 0 11 5 12 14-4-1-8-4-10-8-3 5-8 8-14 10z" fill="'.$hair.'"/>'
            . '<circle cx="29" cy="31" r="1.2" fill="#35445b"/>'
            . '<circle cx="37" cy="31" r="1.2" fill="#35445b"/>'
            . '<path d="M29 38c2 2 4 2 6 0" fill="none" stroke="#a76e5c" stroke-width="1.4" stroke-linecap="round"/>'
            . '</svg></span>';
    }

    protected function cardHeading(string $icon, string $title, string $right = ''): string
    {
        return '<div class="fayno-crm-card__heading"><span class="fayno-crm-card__icon">'.$this->iconSvg($icon).'</span><h2>'.e($title).'</h2>'.$right.'</div>';
    }

    protected function detailLine(string $icon, string $value): string
    {
        return '<div class="fayno-crm-line"><span class="fayno-crm-line__icon">'.$this->iconSvg($icon).'</span><p>'.e($value).'</p></div>';
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

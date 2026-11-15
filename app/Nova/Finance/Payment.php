<?php

namespace App\Nova\Finance;

use App\Nova\Resource;
use App\Nova\Support\FaynoDetailHeader;
use App\Nova\Support\FaynoNovaUrl;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

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
            Panel::make('', [
                Text::make(' ', fn () => $this->detailHtml())
                    ->asHtml()
                    ->onlyOnDetail(),
            ]),

            ID::make()->sortable()->onlyOnIndex(),
            BelongsTo::make(__('nova::resources.Bookings'), 'booking', \App\Nova\Bookings\Booking::class)->searchable()->onlyOnIndex(),
            Number::make(__('nova::resources.Amount'), 'amount')->step(0.01)->sortable()->onlyOnIndex(),
            Text::make(__('nova::resources.Currency'), 'currency')->onlyOnIndex(),
            Text::make(__('nova::resources.Status'), 'status')->sortable()->onlyOnIndex(),

            ID::make()->sortable()->onlyOnForms(),
            BelongsTo::make(__('nova::resources.Bookings'), 'booking', \App\Nova\Bookings\Booking::class)->searchable()->onlyOnForms(),
            Number::make(__('nova::resources.Amount'), 'amount')->step(0.01)->rules('required', 'numeric', 'min:0')->onlyOnForms(),
            Text::make(__('nova::resources.Currency'), 'currency')->rules('required', 'size:3')->onlyOnForms(),
            Text::make(__('nova::resources.Status'), 'status')->sortable()->onlyOnForms(),
            Text::make(__('nova::resources.Payment method'), 'payment_method')->nullable()->onlyOnForms(),
            Text::make(__('nova::resources.Transaction ID'), 'transaction_id')->nullable()->onlyOnForms(),
            DateTime::make(__('nova::resources.Paid at'), 'paid_at')->nullable()->onlyOnForms(),
        ];
    }

    protected function detailHtml(): string
    {
        $payment = $this->resource;
        $booking = $payment->booking;
        $orderCamper = $booking?->orderCamper;
        $order = $orderCamper?->order;
        $customer = $order?->customer;
        $camper = $orderCamper?->camper;
        $shift = $order?->campShift;
        $camp = $shift?->camp;

        $editUrl = FaynoNovaUrl::resource('payments', $payment->getKey(), 'edit');
        $bookingUrl = $booking ? FaynoNovaUrl::resource('bookings', $booking->getKey()) : null;
        $orderUrl = $order ? FaynoNovaUrl::resource('orders', $order->getKey()) : null;
        $status = (string) ($payment->status ?: '—');
        $statusLabel = match (strtolower($status)) {
            'paid', 'completed', 'success', 'succeeded' => 'Оплачено',
            'failed', 'cancelled', 'canceled', 'error' => 'Неуспішно',
            'pending' => 'Очікує оплати',
            'processing' => 'Обробляється',
            default => $status,
        };
        $statusClass = match (strtolower($status)) {
            'paid', 'completed', 'success', 'succeeded' => 'success',
            'failed', 'cancelled', 'canceled', 'error' => 'danger',
            'pending', 'processing' => 'warning',
            default => 'neutral',
        };
        $currency = (string) ($payment->currency ?: $booking?->currency ?: 'EUR');
        $amount = number_format((float) $payment->amount, 2, '.', ' ');
        $bookingTotal = (float) ($booking?->total_amount ?? 0);
        $bookingPaid = (float) ($booking?->payments()->whereIn('status', ['paid', 'completed', 'success', 'succeeded'])->sum('amount') ?? 0);
        $bookingBalance = max(0, $bookingTotal - $bookingPaid);
        $fullName = $customer
            ? trim(implode(' ', array_filter([$customer->last_name, $customer->first_name, $customer->middle_name])))
            : '—';
        $camperName = $camper
            ? trim(implode(' ', array_filter([$camper->last_name, $camper->first_name, $camper->middle_name])))
            : '—';
        $campLabel = collect([$camp?->name, $shift?->name])->filter()->implode(' / ') ?: '—';
        $shiftDates = $shift
            ? ($shift->starts_at?->format('d.m.Y') ?: '—').' — '.($shift->ends_at?->format('d.m.Y') ?: '—')
            : '—';
        $paidAt = $payment->paid_at?->format('d.m.Y H:i') ?: '—';
        $createdAt = $payment->created_at?->format('d.m.Y H:i') ?: '—';
        $icon = '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M16 15h2"/></svg>';
        $pencil = '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 4 4 4L8 20H4v-4L16 4Z"/><path d="m14 6 4 4"/></svg>';
        $printer = '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>';

        $html = '<div class="fayno-payment">';
        $html .= FaynoDetailHeader::render(
            __('nova::navigation.payments'),
            FaynoNovaUrl::resource('payments'),
            'Платіж №'.$payment->getKey(),
            $icon,
            'Деталі платежу №'.$payment->getKey(),
            'Інформація про оплату, бронювання та транзакцію',
            '<button type="button" class="fayno-crm-action fayno-payment-print-button" onclick="window.print(); return false;">'.$printer.' Друкувати ордер</button>'
                . '<a class="fayno-crm-action fayno-crm-action--primary" href="'.e($editUrl).'">'.$pencil.' Редагувати</a>'
        );

        $html .= '<section class="fayno-payment-card">'
            . '<div class="fayno-payment-card__top"><div><span class="fayno-payment-label">СУМА ПЛАТЕЖУ</span><strong class="fayno-payment-amount">'.e($amount).' <small>'.e($currency).'</small></strong></div>'
            . '<span class="fayno-payment-status is-'.e($statusClass).'">'.e($statusLabel).'</span></div>'
            . '<div class="fayno-payment-summary">'
            . '<div><span>Номер платежу</span><strong>#'.e((string) $payment->getKey()).'</strong></div>'
            . '<div><span>Дата створення</span><strong>'.e($createdAt).'</strong></div>'
            . '<div><span>Дата оплати</span><strong>'.e($paidAt).'</strong></div>'
            . '</div></section>'
            . '<div class="fayno-payment__sections">'
            . '<section class="fayno-payment-section"><div class="fayno-payment-section__heading"><span class="fayno-payment-section__icon">₴</span><div><h2>Деталі платежу</h2><p>Основна інформація про оплату</p></div></div>'
            . '<div class="fayno-payment-section__grid">'
            . $this->detailItem('Ідентифікатор', '#'.$payment->getKey())
            . $this->detailItem('Статус платежу', e($statusLabel))
            . $this->detailItem('Сума платежу', $amount.' '.$currency)
            . $this->detailItem('Спосіб оплати', $payment->payment_method ?: 'Не вказано')
            . $this->detailItem('ID транзакції', $payment->transaction_id ?: '—')
            . $this->detailItem('Створено', $createdAt)
            . $this->detailItem('Оплачено', $paidAt)
            . '</div></section>'
            . '<section class="fayno-payment-section"><div class="fayno-payment-section__heading"><span class="fayno-payment-section__icon">▤</span><div><h2>Пов’язане бронювання</h2><p>Фінансовий стан бронювання</p></div></div>'
            . '<div class="fayno-payment-section__grid">'
            . $this->detailItem('Номер бронювання', $booking ? '<a href="'.e($bookingUrl).'">№'.e((string) $booking->getKey()).'</a>' : '—', true)
            . $this->detailItem('Статус бронювання', $booking?->status ?: '—')
            . $this->detailItem('Сума бронювання', number_format($bookingTotal, 2, '.', ' ').' '.$currency)
            . $this->detailItem('Усього сплачено', number_format($bookingPaid, 2, '.', ' ').' '.$currency)
            . $this->detailItem('Залишок до сплати', number_format($bookingBalance, 2, '.', ' ').' '.$currency)
            . $this->detailItem('Підтверджено', $booking?->confirmed_at?->format('d.m.Y H:i') ?: '—')
            . '</div></section>'
            . '<section class="fayno-payment-section"><div class="fayno-payment-section__heading"><span class="fayno-payment-section__icon">♙</span><div><h2>Платник та учасник</h2><p>Контактні дані родини й дитини</p></div></div>'
            . '<div class="fayno-payment-section__grid">'
            . $this->detailItem('Батьки / платник', $fullName)
            . $this->detailItem('Телефон', $customer?->phone ?: '—')
            . $this->detailItem('Електронна пошта', $customer?->email ?: '—')
            . $this->detailItem('Місто', $customer?->city ?: '—')
            . $this->detailItem('Учасник', $camperName)
            . $this->detailItem('Дата народження', $camper?->birth_date?->format('d.m.Y') ?: '—')
            . '</div></section>'
            . '<section class="fayno-payment-section"><div class="fayno-payment-section__heading"><span class="fayno-payment-section__icon">⌂</span><div><h2>Заявка та табір</h2><p>До якої поїздки належить оплата</p></div></div>'
            . '<div class="fayno-payment-section__grid">'
            . $this->detailItem('Номер заявки', $order ? '<a href="'.e($orderUrl).'">'.e((string) ($order->number ?: '#'.$order->getKey())).'</a>' : '—', true)
            . $this->detailItem('Статус заявки', $order?->status ?: '—')
            . $this->detailItem('Табір / зміна', $campLabel)
            . $this->detailItem('Дати зміни', $shiftDates)
            . $this->detailItem('Місто виїзду', $order?->departure_city ?: '—')
            . $this->detailItem('Джерело заявки', $order?->source ?: '—')
            . '</div></section></div>';

        $html .= '<section class="fayno-payment-print" aria-hidden="true">'
            . '<div class="fayno-payment-print__brand"><strong>FAYNO CAMP</strong><span>Більше, ніж табір</span></div>'
            . '<div class="fayno-payment-print__title"><h1>ПЛАТІЖНИЙ ОРДЕР</h1><p>Підтвердження інформації про платіж</p></div>'
            . '<div class="fayno-payment-print__number"><span>Ордер №</span><strong>'.e((string) $payment->getKey()).'</strong><span>від '.e($paidAt !== '—' ? $paidAt : $createdAt).'</span></div>'
            . '<div class="fayno-payment-print__amount"><span>Сума платежу</span><strong>'.e($amount).' '.e($currency).'</strong><em>'.e($statusLabel).'</em></div>'
            . '<h2>Платник</h2><div class="fayno-payment-print__row"><span>ПІБ</span><strong>'.e($fullName).'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>Телефон</span><strong>'.e($customer?->phone ?: '—').'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>E-mail</span><strong>'.e($customer?->email ?: '—').'</strong></div>'
            . '<h2>Призначення платежу</h2>'
            . '<div class="fayno-payment-print__row"><span>Заявка</span><strong>'.e($order?->number ?: '—').'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>Бронювання</span><strong>'.e($booking ? '#'.$booking->getKey() : '—').'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>Учасник</span><strong>'.e($camperName).'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>Табір / зміна</span><strong>'.e($campLabel).'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>Період</span><strong>'.e($shiftDates).'</strong></div>'
            . '<h2>Реквізити транзакції</h2>'
            . '<div class="fayno-payment-print__row"><span>Спосіб оплати</span><strong>'.e($payment->payment_method ?: '—').'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>ID транзакції</span><strong>'.e($payment->transaction_id ?: '—').'</strong></div>'
            . '<div class="fayno-payment-print__row"><span>Статус</span><strong>'.e($statusLabel).'</strong></div>'
            . '<div class="fayno-payment-print__signatures"><span>Підпис платника __________________</span><span>Підпис відповідальної особи __________________</span></div>'
            . '<footer>Fayno Camp · fayno.camp · Документ сформовано '.e(now()->format('d.m.Y H:i')).'</footer>'
            . '</section></div>';

        return $html;
    }

    protected function detailItem(string $label, string $value, bool $html = false): string
    {
        return '<div class="fayno-payment-item"><span>'.e($label).'</span><strong>'.($html ? $value : e($value)).'</strong></div>';
    }
}

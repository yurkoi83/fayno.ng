<?php

namespace App\Nova\Finance;

use App\Nova\Resource;
use App\Nova\Support\FaynoDetailHeader;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

class Payment extends Resource
{
    public static $model = \\App\\Models\\Payment::class;
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
            BelongsTo::make(__('nova::resources.Bookings'), 'booking', \\App\\Nova\\Bookings\\Booking::class)->searchable()->onlyOnIndex(),
            Number::make(__('nova::resources.Amount'), 'amount')->step(0.01)->sortable()->onlyOnIndex(),
            Text::make(__('nova::resources.Currency'), 'currency')->onlyOnIndex(),
            Text::make(__('nova::resources.Status'), 'status')->sortable()->onlyOnIndex(),

            ID::make()->sortable()->onlyOnForms(),
            BelongsTo::make(__('nova::resources.Bookings'), 'booking', \\App\\Nova\\Bookings\\Booking::class)->searchable()->onlyOnForms(),
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
        $editUrl = url('/nova/resources/payments/'.$payment->getKey().'/edit');
        $bookingUrl = $booking ? url('/nova/resources/bookings/'.$booking->getKey()) : null;
        $status = (string) ($payment->status ?: '—');
        $statusClass = match (strtolower($status)) {
            'paid', 'completed', 'success', 'succeeded' => 'success',
            'failed', 'cancelled', 'canceled', 'error' => 'danger',
            'pending', 'processing' => 'warning',
            default => 'neutral',
        };
        $amount = number_format((float) $payment->amount, 2, '.', ' ');
        $icon = '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M16 15h2"/></svg>';
        $pencil = '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 4 4 4L8 20H4v-4L16 4Z"/><path d="m14 6 4 4"/></svg>';

        $html = '<div class="fayno-payment">';
        $html .= FaynoDetailHeader::render(
            __('nova::navigation.payments'),
            url('/nova/resources/payments'),
            'Платіж №'.$payment->getKey(),
            $icon,
            'Деталі платежу №'.$payment->getKey(),
            'Інформація про оплату та транзакцію',
            '<a class="fayno-crm-action fayno-crm-action--primary" href="'.e($editUrl).'">'.$pencil.' Редагувати</a>'
        );

        $html .= '<section class="fayno-payment-card">'
            . '<div class="fayno-payment-card__top"><div><span class="fayno-payment-label">СУМА ПЛАТЕЖУ</span><strong class="fayno-payment-amount">'.e($amount).' <small>'.e($payment->currency ?: '').'</small></strong></div>'
            . '<span class="fayno-payment-status is-'.e($statusClass).'">'.e($status).'</span></div>'
            . '<div class="fayno-payment-grid">'
            . $this->detailItem('Ідентифікатор', '#'.$payment->getKey())
            . $this->detailItem('Бронювання', $booking ? '<a href="'.e($bookingUrl).'">№'.e($booking->getKey()).'</a>' : '—', true)
            . $this->detailItem('Спосіб оплати', $payment->payment_method ?: 'Не вказано')
            . $this->detailItem('ID транзакції', $payment->transaction_id ?: '—')
            . $this->detailItem('Статус', e($status))
            . $this->detailItem('Дата оплати', $payment->paid_at?->format('d.m.Y H:i') ?: '—')
            . '</div></section></div>';

        return $html;
    }

    protected function detailItem(string $label, string $value, bool $html = false): string
    {
        return '<div class="fayno-payment-item"><span>'.e($label).'</span><strong>'.($html ? $value : e($value)).'</strong></div>';
    }
}

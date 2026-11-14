<?php

namespace App\Nova\Campers;

use App\Enums\OrderStatus;
use App\Nova\Resource;
use App\Nova\Support\FaynoBreadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Customer extends Resource
{
    public static $model = \App\Models\Customer::class;
    public static $title = 'last_name';
    public static $search = ['id', 'first_name', 'last_name', 'phone', 'email'];
    public static $group = 'Батьки';

    public static function label(): string { return __('nova::navigation.parents'); }
    public static function singularLabel(): string { return __('nova::navigation.parents'); }

    public function title()
    {
        return trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ])));
    }

    public function fields(Request $request): array
    {
        return [
            Panel::make('', [
                Text::make(' ', fn () => $this->detailHtml())
                    ->asHtml()
                    ->onlyOnDetail(),
            ]),

            ID::make()->sortable()->onlyOnIndex(),
            Text::make('ПІБ', fn () => $this->title())->sortable()->onlyOnIndex(),
            Text::make(__('nova::resources.Phone'), 'phone')->onlyOnIndex(),
            Text::make(__('nova::resources.Email'), 'email')->onlyOnIndex(),
            Text::make(__('nova::resources.City'), 'city')->onlyOnIndex(),

            ID::make()->sortable()->onlyOnForms(),
            Text::make(__('nova::resources.Last name'), 'last_name')->sortable()->rules('required')->onlyOnForms(),
            Text::make(__('nova::resources.First name'), 'first_name')->sortable()->rules('required')->onlyOnForms(),
            Text::make(__('nova::resources.Middle name'), 'middle_name')->nullable()->onlyOnForms(),
            Text::make(__('nova::resources.Phone'), 'phone')->sortable()->rules('required')->onlyOnForms(),
            Text::make(__('nova::resources.Email'), 'email')->nullable()->rules('nullable', 'email')->onlyOnForms(),
            Text::make(__('nova::resources.City'), 'city')->nullable()->onlyOnForms(),
            Text::make(__('nova::resources.Address'), 'address')->nullable()->onlyOnForms(),
            Textarea::make(__('nova::resources.Notes'), 'notes')->alwaysShow()->onlyOnForms(),
        ];
    }

    protected function detailHtml(): string
    {
        $customer = $this->resource;
        $fullName = trim(implode(' ', array_filter([
            $customer->last_name,
            $customer->first_name,
            $customer->middle_name,
        ])));

        $children = $customer->campers()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $orders = $customer->orders()
            ->with(['campShift.camp', 'orderCampers.camper', 'bookings'])
            ->latest('created_at')
            ->get();

        $initials = mb_strtoupper(
            mb_substr((string) $customer->first_name, 0, 1)
            . mb_substr((string) $customer->last_name, 0, 1)
        );

        $indexUrl = url('/nova/resources/customers');
        $editUrl = url('/nova/resources/customers/'.$customer->getKey().'/edit');

        $html = '<div class="fayno-crm fayno-customer">';
        $html .= '<header class="fayno-crm__heading">'
            . '<div class="fayno-crm__heading-main">'
            . FaynoBreadcrumbs::render(__('nova::navigation.parents'), $indexUrl, $fullName)
            . '<div class="fayno-crm__title-row"><span class="fayno-crm__title-icon">'.$this->iconSvg('users-round').'</span><div><h1>'.e($fullName).'</h1><p>Картка батьків · ID '.$customer->getKey().'</p></div></div>'
            . '</div><div class="fayno-crm__actions">'
            . '<a class="fayno-crm-action fayno-crm-action--primary" href="'.$editUrl.'">'.$this->iconSvg('pencil').' Редагувати</a>'
            . '</div></header>';

        $html .= '<div class="fayno-customer__profile">'
            . '<div class="fayno-customer__avatar">'.e($initials ?: 'FC').'</div>'
            . '<div class="fayno-customer__identity"><span class="fayno-customer__eyebrow">БАТЬКИ / КОНТАКТНА ОСОБА</span><h2>'.e($fullName ?: 'Без імені').'</h2>'
            . '<div class="fayno-customer__quick">'
            . ($customer->phone ? '<a href="tel:'.e($customer->phone).'">'.$this->iconSvg('phone').e($customer->phone).'</a>' : '')
            . ($customer->email ? '<a href="mailto:'.e($customer->email).'">'.$this->iconSvg('mail').e($customer->email).'</a>' : '')
            . '</div></div><div class="fayno-customer__profile-meta">'
            . '<span>'.$this->iconSvg('map-pin').e($customer->city ?: 'Місто не вказано').'</span>'
            . '<span>'.$this->iconSvg('calendar-days').'Клієнт з '.e($customer->created_at?->format('d.m.Y') ?: '—').'</span>'
            . '</div></div>';

        $html .= '<div class="fayno-customer__stats">'
            . $this->statCard('users-round', 'Діти', (string) $children->count(), 'Додано до профілю', 'purple')
            . $this->statCard('file-text', 'Заявки', (string) $orders->count(), 'Усі заявки родини', 'blue')
            . $this->statCard('wallet', 'Сума заявок', $this->money((float) $orders->sum(fn ($order) => $order->bookings->sum('total_amount'))), 'За всіма бронюваннями', 'green')
            . '</div>';

        $html .= '<section class="fayno-crm-card fayno-customer__contacts">'
            . $this->cardHeading('contact-round', 'Контактна інформація')
            . '<div class="fayno-customer__contact-grid">'
            . $this->contactValue('Телефон', $customer->phone ?: 'Не вказано')
            . $this->contactValue('Електронна пошта', $customer->email ?: 'Не вказано')
            . $this->contactValue('Місто', $customer->city ?: 'Не вказано')
            . $this->contactValue('Адреса', $customer->address ?: 'Не вказано')
            . '</div>'
            . ($customer->notes ? '<div class="fayno-customer__notes"><strong>Примітки</strong><p>'.nl2br(e($customer->notes)).'</p></div>' : '')
            . '</section>';

        $html .= '<section class="fayno-crm-card fayno-customer__children">'
            . $this->cardHeading('users-round', 'Діти родини', '<span class="fayno-crm-count">'.$children->count().'</span>');
        if ($children->isEmpty()) {
            $html .= '<div class="fayno-customer__empty">'.$this->iconSvg('user-round').'<strong>Дітей ще не додано</strong><span>Додайте дитину до профілю, щоб бачити її участь у таборах та заявках</span></div>';
        } else {
            $html .= '<div class="fayno-customer__children-list">';
            foreach ($children as $child) {
                $childName = trim(implode(' ', array_filter([$child->last_name, $child->first_name, $child->middle_name])));
                $photo = $child->photo_path ? Storage::disk('public')->url($child->photo_path) : null;
                $avatar = $photo
                    ? '<img src="'.e($photo).'" alt="">'
                    : '<span class="fayno-customer__child-avatar">'.$this->iconSvg('user-round').'</span>';
                $age = $child->birth_date ? $child->birth_date->age.' р.' : 'Вік не вказано';
                $childOrders = $child->orderCampers()->count();
                $html .= '<a class="fayno-customer__child" href="'.url('/nova/resources/campers/'.$child->getKey()).'">'
                    . $avatar.'<span class="fayno-customer__child-main"><strong>'.e($childName ?: 'Без імені').'</strong><small>'.e($age).'</small></span>'
                    . '<span class="fayno-customer__child-orders">'.$childOrders.' '.e($this->plural($childOrders, 'заявка', 'заявки', 'заявок')).'</span>'
                    . '<span class="fayno-customer__arrow">›</span></a>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';

        $html .= '<section class="fayno-crm-card fayno-customer__orders">'
            . $this->cardHeading('file-text', 'Заявки родини', '<span class="fayno-crm-count">'.$orders->count().'</span>');
        if ($orders->isEmpty()) {
            $html .= '<div class="fayno-customer__empty">'.$this->iconSvg('file-text').'<strong>Заявок поки немає</strong><span>Коли батьки оформлять бронювання, воно з’явиться в цьому блоці</span></div>';
        } else {
            $html .= '<div class="fayno-customer__orders-table-wrap"><table class="fayno-customer__orders-table"><thead><tr><th>Заявка</th><th>Діти</th><th>Табір / зміна</th><th>Дата</th><th>Статус</th><th class="is-amount">Сума</th><th></th></tr></thead><tbody>';
            foreach ($orders as $order) {
                $orderChildren = $order->orderCampers->map(function ($item) {
                    $camper = $item->camper;
                    return $camper ? trim(implode(' ', array_filter([$camper->last_name, $camper->first_name]))) : null;
                })->filter()->values();
                $camp = $order->campShift?->camp?->name;
                $shift = $order->campShift?->name;
                $campLabel = collect([$camp, $shift])->filter()->implode(' / ') ?: '—';
                $amount = (float) $order->bookings->sum('total_amount');
                $currency = $order->bookings->first()?->currency ?: 'UAH';
                $status = $this->statusLabel((string) $order->status);
                $statusClass = $this->statusClass((string) $order->status);
                $orderNumber = $order->number ?: '#'.$order->getKey();

                $html .= '<tr><td><a class="fayno-customer__order-number" href="'.url('/nova/resources/orders/'.$order->getKey()).'">'.e($orderNumber).'</a></td>'
                    . '<td><span class="fayno-customer__order-children">'.e($orderChildren->implode(', ') ?: '—').'</span></td>'
                    . '<td>'.e($campLabel).'</td>'
                    . '<td>'.e($order->created_at?->format('d.m.Y') ?: '—').'</td>'
                    . '<td><span class="fayno-customer__status is-'.e($statusClass).'">'.e($status).'</span></td>'
                    . '<td class="is-amount"><strong>'.e($this->money($amount, $currency)).'</strong></td>'
                    . '<td><a class="fayno-customer__open" href="'.url('/nova/resources/orders/'.$order->getKey()).'" aria-label="Відкрити заявку">›</a></td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        $html .= '</div>';

        return $html;
    }

    protected function statCard(string $icon, string $label, string $value, string $hint, string $tone): string
    {
        return '<article class="fayno-customer-stat is-'.e($tone).'"><span class="fayno-customer-stat__icon">'.$this->iconSvg($icon).'</span><div><small>'.e($label).'</small><strong>'.e($value).'</strong><span>'.e($hint).'</span></div></article>';
    }

    protected function contactValue(string $label, string $value): string
    {
        return '<div class="fayno-customer__contact"><span>'.e($label).'</span><strong>'.e($value).'</strong></div>';
    }

    protected function cardHeading(string $icon, string $title, string $right = ''): string
    {
        return '<div class="fayno-crm-card__heading"><span class="fayno-crm-card__icon">'.$this->iconSvg($icon).'</span><h2>'.e($title).'</h2>'.$right.'</div>';
    }

    protected function iconSvg(string $name): string
    {
        $paths = [
            'users-round' => '<circle cx="9" cy="7.5" r="3.5"/><circle cx="17" cy="9" r="2.7"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0M15.5 14.8a5.2 5.2 0 0 1 5.7 5.2"/>',
            'user-round' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.2 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72l.45 3.1a2 2 0 0 1-.57 1.7L7.1 10.4a16 16 0 0 0 6 6l1.88-1.89a2 2 0 0 1 1.7-.57l3.1.45a2 2 0 0 1 1.72 2.53z"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
            'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h.01M12 15h.01M16 15h.01"/>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
            'wallet' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 8h18M16 14h.01"/>',
            'contact-round' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0M19 8h3M20.5 6.5v3"/>',
            'pencil' => '<path d="m16 4 4 4L8 20H4v-4L16 4Z"/><path d="m14 6 4 4"/>',
        ];
        $content = $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';
        return '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$content.'</svg>';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            OrderStatus::NEW->value => 'Нова',
            OrderStatus::PROCESSING->value => 'В обробці',
            OrderStatus::WAITING_CUSTOMER->value => 'Очікує відповіді',
            OrderStatus::CONTRACT_SENT->value => 'Договір надіслано',
            OrderStatus::AWAITING_PAYMENT->value => 'Очікує оплати',
            OrderStatus::CONFIRMED->value => 'Підтверджена',
            OrderStatus::REJECTED->value => 'Відхилена',
            default => $status ?: '—',
        };
    }

    protected function statusClass(string $status): string
    {
        return match ($status) {
            OrderStatus::CONFIRMED->value, OrderStatus::PROCESSING->value => 'success',
            OrderStatus::REJECTED->value => 'danger',
            OrderStatus::WAITING_CUSTOMER->value, OrderStatus::AWAITING_PAYMENT->value, OrderStatus::CONTRACT_SENT->value => 'warning',
            default => 'info',
        };
    }

    protected function money(float $amount, string $currency = 'UAH'): string
    {
        return number_format($amount, 0, ',', ' ').' '.$currency;
    }

    protected function plural(int $count, string $one, string $few, string $many): string
    {
        $n = abs($count) % 100;
        $last = $n % 10;
        if ($n > 10 && $n < 20) return $many;
        if ($last > 1 && $last < 5) return $few;
        if ($last === 1) return $one;
        return $many;
    }
}

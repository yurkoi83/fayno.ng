<?php

namespace App\Nova\Campers;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Panel;

class Camper extends Resource
{
    public static $model = \App\Models\Camper::class;
    public static $title = 'last_name';
    public static $search = ['id', 'first_name', 'last_name'];
    public static $group = 'Кемпери';

    public static function label(): string { return __('nova::navigation.campers'); }
    public static function singularLabel(): string { return __('nova::resources.Camper'); }

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
            ID::make()->sortable()->hideFromDetail()->hideFromIndex(),

            Panel::make('', [
                Text::make(' ', fn () => $this->detailHtml())
                    ->asHtml()
                    ->onlyOnDetail(),
            ]),

            Panel::make(__('nova::resources.Camper'), [
                BelongsTo::make(__('nova::resources.Parent'), 'customer', Customer::class)->searchable(),
                Text::make(__('nova::resources.Last name'), 'last_name')->sortable()->rules('required'),
                Text::make(__('nova::resources.First name'), 'first_name')->sortable()->rules('required'),
                Text::make(__('nova::resources.Middle name'), 'middle_name')->nullable()->hideFromIndex(),
                Date::make(__('nova::resources.Birth date'), 'birth_date')->sortable(),
                Image::make(__('nova::resources.Photo'), 'photo_path')->disk('public')->path('campers')->prunable()->hideFromIndex(),
                Number::make(__('nova::resources.Gender'), 'gender_id')->nullable()->hideFromIndex(),
                Textarea::make(__('nova::resources.Notes'), 'notes')->alwaysShow()->hideFromIndex(),
            ]),

        ];
    }

    protected function detailHtml(): string
    {
        $camper = $this->resource;
        $customer = $camper->customer;
        $orders = $camper->orders()
            ->with(['campShift.camp', 'bookings'])
            ->latest('orders.created_at')
            ->limit(8)
            ->get();

        $fullName = trim(implode(' ', array_filter([
            $camper->last_name,
            $camper->first_name,
            $camper->middle_name,
        ]))) ?: '—';

        $parentName = $customer
            ? trim(implode(' ', array_filter([
                $customer->last_name,
                $customer->first_name,
                $customer->middle_name,
            ])))
            : '—';

        $birthDate = $camper->birth_date;
        $age = $birthDate ? $birthDate->age : null;
        $photo = $camper->photo_path
            ? Storage::disk('public')->url($camper->photo_path)
            : null;

        $indexUrl = url('/nova/resources/campers');
        $editUrl = url('/nova/resources/campers/'.$camper->getKey().'/edit');
        $parentUrl = $customer ? url('/nova/resources/customers/'.$customer->getKey()) : null;

        $html = '<div class="fayno-camper">';
        $html .= '<div class="fayno-camper__header">'
            . '<nav class="fayno-camper__breadcrumbs"><a href="'.$indexUrl.'">'
            .__('nova::navigation.campers')
            . '</a><span>/</span><span>'.e($fullName).'</span></nav>'
            . '<div class="fayno-camper__profile">'
            . '<div class="fayno-camper__avatar">';
        $html .= $photo
            ? '<img src="'.e($photo).'" alt="">'
            : '<span>'.e($this->initials($camper->first_name, $camper->last_name)).'</span>';
        $html .= '</div><div class="fayno-camper__identity">'
            . '<div class="fayno-camper__name-row"><h1>'.e($fullName).'</h1></div>'
            . '<p>Учасник табору <span>·</span> ID '.$camper->getKey().'</p>';
        if ($customer) {
            $html .= '<a class="fayno-camper__parent-link" href="'.e($parentUrl).'">'.$this->iconSvg('users-round').' Батьки: '.e($parentName).'</a>';
        }
        $html .= '</div><a class="fayno-camper__edit" href="'.$editUrl.'">'.$this->iconSvg('pencil').' Редагувати</a>'
            . ($age !== null ? '<span class="fayno-camper__age">'.$this->iconSvg('cake').'<strong>'.e($age.' '.($age === 1 ? 'рік' : ($age >= 2 && $age <= 4 ? 'роки' : 'років'))).'</strong></span>' : '')
            . '</div></header>';

        $html .= '<div class="fayno-camper__grid">';
        $html .= '<section class="fayno-camper-card">'
            . $this->cardHeading('user-round', 'Особисті дані')
            . '<div class="fayno-camper__details">'
            . $this->detailLine('calendar-days', 'Дата народження', $birthDate?->format('d.m.Y') ?: 'Не вказана')
            . $this->detailLine('cake', 'Вік', $age !== null ? $age.' '.($age === 1 ? 'рік' : ($age >= 2 && $age <= 4 ? 'роки' : 'років')) : '—')
            . $this->detailLine('hash', 'ID учасника', (string) $camper->getKey())
            . '</div>';
        if ($camper->notes) {
            $html .= '<div class="fayno-camper__notes"><strong>Примітки</strong><p>'.nl2br(e($camper->notes)).'</p></div>';
        }
        $html .= '</section>';

        $html .= '<section class="fayno-camper-card">'
            . $this->cardHeading('users-round', 'Батьки та контакти');
        if ($customer) {
            $html .= '<a class="fayno-camper__parent-name" href="'.e($parentUrl).'">'.e($parentName).'</a>'
                . '<div class="fayno-camper__details">'
                . $this->detailLine('phone', 'Телефон', $customer->phone ?: 'Не вказаний')
                . $this->detailLine('mail', 'Електронна пошта', $customer->email ?: 'Не вказана')
                . $this->detailLine('map-pin', 'Місто / адреса', trim(implode(', ', array_filter([$customer->city, $customer->address]))) ?: '—')
                . '</div>';
        } else {
            $html .= '<p class="fayno-camper__empty">До учасника ще не прив’язано батьків.</p>';
        }
        $html .= '</section>';

        $html .= '<section class="fayno-camper-card fayno-camper-card--history">'
            . $this->cardHeading('tent', 'Заявки та заїзди', '<span class="fayno-camper__count">'.$orders->count().'</span>');
        if ($orders->isEmpty()) {
            $html .= '<p class="fayno-camper__empty">Історія заявок поки порожня.</p>';
        } else {
            $html .= '<div class="fayno-camper__table-wrap"><table class="fayno-camper__table"><thead><tr>'
                . '<th>Заявка</th><th>Табір / зміна</th><th>Дати</th><th>Статус</th><th>Сума</th>'
                . '</tr></thead><tbody>';
            foreach ($orders as $order) {
                $shift = $order->campShift;
                $campName = $shift?->camp?->name ?: 'Табір не вказаний';
                $shiftName = $shift?->name;
                $dates = $shift
                    ? (($shift->starts_at?->format('d.m.Y') ?: '—').' — '.($shift->ends_at?->format('d.m.Y') ?: '—'))
                    : '—';
                $amount = (float) $order->bookings->sum('total_amount');
                $currency = $order->bookings->first()?->currency ?: 'UAH';
                $status = $this->statusLabel((string) $order->status);
                $html .= '<tr><td><a href="'.e(url('/nova/resources/orders/'.$order->getKey())).'">№ '.e($order->number ?: $order->getKey()).'</a>'
                    . '<small>'.e($order->created_at?->format('d.m.Y') ?: '').'</small></td>'
                    . '<td><strong>'.e($campName).'</strong>'.($shiftName ? '<small>'.e($shiftName).'</small>' : '').'</td>'
                    . '<td>'.e($dates).'</td>'
                    . '<td><span class="fayno-camper__status">'.e($status).'</span></td>'
                    . '<td><strong>'.e(number_format($amount, 0, ',', ' ').' '.$currency).'</strong></td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section></div></div>';

        return $html;
    }

    protected function initials(?string $firstName, ?string $lastName): string
    {
        $first = mb_substr(trim((string) $firstName), 0, 1);
        $last = mb_substr(trim((string) $lastName), 0, 1);

        return mb_strtoupper($first.$last) ?: 'FC';
    }

    protected function iconSvg(string $name): string
    {
        $paths = [
            'user-round' => '<circle cx="12" cy="7.5" r="4" fill="currentColor" stroke="none"/><path d="M4 21a8 8 0 0 1 16 0" fill="currentColor" stroke="none"/>',
            'users-round' => '<circle cx="9" cy="7" r="4" fill="currentColor" stroke="none"/><path d="M2 21a7 7 0 0 1 14 0" fill="currentColor" stroke="none"/><circle cx="17" cy="8" r="3" fill="currentColor" stroke="none"/><path d="M16 15a6 6 0 0 1 6 6h-4a7 7 0 0 0-2-6Z" fill="currentColor" stroke="none"/>',
            'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'cake' => '<path d="M20 10H4a2 2 0 0 0-2 2v8h20v-8a2 2 0 0 0-2-2Z"/><path d="M12 2v3M8 5v2m8-2v2M2 16c2 0 2-2 5-2s3 2 5 2 3-2 5-2 3 2 5 2"/>',
            'hash' => '<path d="M4 9h16M4 15h16M10 3 8 21m8-18-2 18"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.2 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72l.45 3.1a2 2 0 0 1-.57 1.7L7.1 10.4a16 16 0 0 0 6 6l1.88-1.89a2 2 0 0 1 1.7-.57l3.1.45a2 2 0 0 1 1.72 2.53z"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
            'tent' => '<path d="m12 2 10 19H2L12 2Zm0 6-4.5 13h9L12 8Z" fill="currentColor" stroke="none"/>',
            'pencil' => '<path d="m16 4 4 4L8 20H4v-4L16 4Z"/><path d="m14 6 4 4"/>',
        ];

        $content = $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';

        return '<svg class="fayno-camper__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$content.'</svg>';
    }

    protected function cardHeading(string $icon, string $title, string $right = ''): string
    {
        return '<div class="fayno-camper-card__heading"><span>'.$this->iconSvg($icon).'</span><h2>'.e($title).'</h2>'.$right.'</div>';
    }

    protected function detailLine(string $icon, string $label, string $value): string
    {
        return '<div class="fayno-camper__detail"><span class="fayno-camper__detail-icon">'.$this->iconSvg($icon).'</span><div><small>'.e($label).'</small><strong>'.e($value).'</strong></div></div>';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Нова',
            'processing' => 'В обробці',
            'waiting_customer' => 'Очікує відповіді',
            'contract_sent' => 'Договір надіслано',
            'awaiting_payment' => 'Очікує оплати',
            'confirmed' => 'Підтверджена',
            'rejected' => 'Відхилена',
            default => $status ?: '—',
        };
    }
}

<?php

namespace App\Nova\Camps;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Camp extends Resource
{
    public static $model = \App\Models\Camp::class;
    public static $title = 'name';
    public static $search = ['id', 'name', 'city'];
    public static $group = 'Табори';

    public static function label(): string { return __('nova::navigation.camps'); }
    public static function singularLabel(): string { return __('nova::resources.Camp'); }

    public function fields(Request $request): array
    {
        return [
            Text::make(' ', fn () => $this->detailHtml())
                ->asHtml()
                ->onlyOnDetail(),

            ID::make()->sortable()->onlyOnIndex(),
            Text::make(__('nova::resources.Camp name'), 'name')->sortable()->onlyOnIndex(),
            Text::make(__('nova::resources.Country'), 'country')->onlyOnIndex(),
            Text::make(__('nova::resources.City'), 'city')->sortable()->onlyOnIndex(),
            Boolean::make(__('nova::resources.Active'), 'active')->onlyOnIndex(),

            ID::make()->sortable()->onlyOnForms(),
            Text::make(__('nova::resources.Camp name'), 'name')->sortable()->rules('required', 'max:255')->onlyOnForms(),
            Text::make(__('nova::resources.Slug'), 'slug')->onlyOnForms(),
            Image::make(__('nova::resources.Photo'), 'photo_path')->disk('public')->path('camps')->prunable()->onlyOnForms(),
            Text::make(__('nova::resources.Country'), 'country')->onlyOnForms(),
            Text::make(__('nova::resources.City'), 'city')->sortable()->onlyOnForms(),
            Text::make(__('nova::resources.Address'), 'address')->onlyOnForms(),
            Number::make(__('nova::resources.Min age'), 'min_age')->min(0)->max(18)->onlyOnForms(),
            Number::make(__('nova::resources.Max age'), 'max_age')->min(0)->max(18)->onlyOnForms(),
            Boolean::make(__('nova::resources.Active'), 'active')->onlyOnForms(),
            Textarea::make(__('nova::resources.Description'), 'description')->alwaysShow()->onlyOnForms(),
        ];
    }

    protected function detailHtml(): string
    {
        $camp = $this->resource;
        $shifts = $camp->shifts()->orderBy('starts_at')->get();
        $photo = $camp->photo_path ? Storage::disk('public')->url($camp->photo_path) : null;
        $editUrl = url('/nova/resources/camps/'.$camp->getKey().'/edit');
        $indexUrl = url('/nova/resources/camps');
        $ageRange = trim(($camp->min_age !== null ? $camp->min_age : '—').'–'.($camp->max_age !== null ? $camp->max_age : '—'));

        $html = '<div class="fayno-crm fayno-entity-detail fayno-camp-detail">';
        $html .= '<header class="fayno-crm__heading"><div class="fayno-crm__heading-main">'
            . '<nav class="fayno-crm__breadcrumbs"><a href="'.$indexUrl.'">⌂</a><span>›</span><a href="'.$indexUrl.'">Табори</a><span>›</span><strong>'.e($camp->name).'</strong></nav>'
            . '<div class="fayno-crm__title-row"><span class="fayno-crm__title-icon">'.$this->iconSvg('tent').'</span><div><h1>Табір: '.e($camp->name).'</h1><p>Інформація про табір та його зміни</p></div></div>'
            . '</div><div class="fayno-crm__actions"><a class="fayno-crm-action fayno-crm-action--primary" href="'.$editUrl.'">'.$this->iconSvg('pencil').' Редагувати</a></div></header>';

        $html .= '<div class="fayno-crm__content"><div class="fayno-crm__top">';
        $html .= '<section class="fayno-crm-card fayno-camp-identity">'
            . $this->cardHeading('tent', 'Табір')
            . ($photo ? '<img class="fayno-camp-detail__photo" src="'.e($photo).'" alt="">' : '<div class="fayno-camp-detail__photo-placeholder">'.$this->iconSvg('mountain').'</div>')
            . '<h3>'.e($camp->name).'</h3><p class="fayno-camp-detail__muted">ID табору: '.e((string) $camp->getKey()).'</p></section>';
        $html .= '<section class="fayno-crm-card fayno-camp-location">'
            . $this->cardHeading('map-pin', 'Розташування')
            . '<div class="fayno-camp-detail__value">'.e($camp->country ?: 'Країну не вказано').'</div>'
            . '<p>'.e($camp->city ?: 'Місто не вказано').'</p><small>'.e($camp->address ?: 'Адресу не вказано').'</small></section>';
        $html .= '<section class="fayno-crm-card fayno-camp-age">'
            . $this->cardHeading('users-round', 'Вік учасників')
            . '<strong class="fayno-camp-detail__age">'.e($ageRange).' <small>років</small></strong>'
            . '<p>Мінімальний та максимальний вік</p></section>';
        $html .= '</div>';

        $html .= '<section class="fayno-crm-card fayno-camp-about">'
            . $this->cardHeading('file-text', 'Основна інформація')
            . '<div class="fayno-crm-details__grid">'
            . $this->detailValue('Назва табору', (string) ($camp->name ?: '—'))
            . $this->detailValue('ЧПУ', (string) ($camp->slug ?: '—'))
            . $this->detailValue('Країна', (string) ($camp->country ?: '—'))
            . $this->detailValue('Місто', (string) ($camp->city ?: '—'))
            . $this->detailValue('Адреса', (string) ($camp->address ?: '—'))
            . $this->detailValue('Віковий діапазон', $ageRange.' років')
            . '</div>'
            . '<div class="fayno-camp-detail__description"><span>Опис табору</span><p>'.nl2br(e($camp->description ?: 'Опис поки не додано.')).'</p></div>'
            . '</section>';

        $html .= '<section class="fayno-crm-card fayno-camp-shifts">'
            . $this->cardHeading('calendar-days', 'Зміни табору', '<span class="fayno-crm-count">'.$shifts->count().' змін</span>');
        if ($shifts->isEmpty()) {
            $html .= '<p class="fayno-crm-muted">Для цього табору ще не створено змін.</p>';
        } else {
            $html .= '<div class="fayno-crm-table-wrap"><table class="fayno-crm-table"><thead><tr><th>Назва зміни</th><th>Дати</th><th>Місць</th><th>Вартість</th><th>Раннє бронювання</th><th>Статус</th></tr></thead><tbody>';
            foreach ($shifts as $shift) {
                $dates = ($shift->starts_at?->format('d.m.Y') ?: '—').' — '.($shift->ends_at?->format('d.m.Y') ?: '—');
                $price = $shift->regular_price !== null ? number_format((float) $shift->regular_price, 0, ',', ' ').' '.($shift->currency ?: 'UAH') : '—';
                $early = $shift->early_price !== null ? number_format((float) $shift->early_price, 0, ',', ' ').' '.($shift->currency ?: 'UAH') : '—';
                $status = $shift->status ?: '—';
                $html .= '<tr><td><strong>'.e($shift->name ?: 'Без назви').'</strong></td><td>'.e($dates).'</td><td>'.e((string) ($shift->capacity ?? '—')).'</td><td><strong>'.e($price).'</strong></td><td>'.e($early).'</td><td><span class="fayno-camp-detail__status">'.e($status).'</span></td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section></div>';

        $html .= '<aside class="fayno-crm__aside">'
            . '<section class="fayno-crm-card fayno-camp-status"><div class="fayno-camp-detail__status-icon '.($camp->active ? 'is-active' : 'is-inactive').'">'.$this->iconSvg($camp->active ? 'check' : 'x').'</div><div><strong>'.($camp->active ? 'Активний табір' : 'Неактивний табір').'</strong><p>'.($camp->active ? 'Табір доступний для використання' : 'Табір вимкнений').'</p></div></section>'
            . '<section class="fayno-crm-card fayno-camp-summary">'.$this->cardHeading('chart-no-axes-column', 'Коротко про табір')
            . '<div class="fayno-camp-detail__summary-row"><span>Кількість змін</span><strong>'.e((string) $shifts->count()).'</strong></div>'
            . '<div class="fayno-camp-detail__summary-row"><span>Загальна місткість</span><strong>'.e((string) $shifts->sum('capacity')).'</strong></div>'
            . '<div class="fayno-camp-detail__summary-row"><span>Вікова група</span><strong>'.e($ageRange).' років</strong></div></section>'
            . '</aside></div></div>';

        return $html;
    }

    protected function detailValue(string $label, string $value): string
    {
        return '<div class="fayno-crm-detail-value"><span>'.e($label).'</span><strong>'.e($value).'</strong></div>';
    }

    protected function iconSvg(string $name): string
    {
        $paths = [
            'tent' => '<path d="M3 20.5 12 4l9 16.5H3Z"/><path d="m12 4-1.5 16.5M12 4l1.5 16.5M8.5 20.5l3.5-7 3.5 7"/>',
            'mountain' => '<path d="m3 20 7-13 4 7 2-3 5 9H3Z"/>',
            'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
            'users-round' => '<circle cx="9" cy="7.5" r="3.5"/><circle cx="17" cy="9" r="2.7"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0M15.5 14.8a5.2 5.2 0 0 1 5.7 5.2"/>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
            'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'x' => '<path d="m18 6-12 12M6 6l12 12"/>',
            'pencil' => '<path d="m16 4 4 4L8 20H4v-4L16 4Z"/>',
            'chart-no-axes-column' => '<path d="M4 19V5m0 14h17M8 16v-5h3v5m2 0V7h3v9m2 0v-8h3v8"/>',
        ];
        $content = $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';
        return '<svg class="fayno-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$content.'</svg>';
    }
}

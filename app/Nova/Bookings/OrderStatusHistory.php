<?php

namespace App\Nova\Bookings;

use App\Enums\OrderStatus;
use App\Nova\Resource;
use App\Nova\Users\User;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class OrderStatusHistory extends Resource
{
    public static $model = \App\Models\OrderStatusHistory::class;
    public static $title = 'id';
    public static $displayInNavigation = false;

    public function title()
    {
        return $this->statusLabel($this->to_status);
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(__('nova::resources.Order'), 'order', Order::class)->hideFromIndex(),
            Text::make(__('nova::resources.Previous status'), fn () => $this->statusLabel($this->from_status) ?: '—'),
            Text::make(__('nova::resources.New status'), fn () => $this->statusLabel($this->to_status)),
            BelongsTo::make(__('nova::resources.Changed by'), 'changedBy', User::class)->nullable(),
            Textarea::make(__('nova::resources.Comment'), 'comment')->alwaysShow()->nullable(),
            DateTime::make(__('nova::resources.Changed at'), 'created_at')->exceptOnForms(),
        ];
    }

    protected function statusLabel(?string $status): string
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
}

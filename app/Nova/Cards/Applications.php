<?php

namespace App\Nova\Cards;

use App\Enums\OrderStatus;
use App\Models\Order;
use Laravel\Nova\Card;

class Applications extends Card
{
    /**
     * The width of the card.
     *
     * @var string
     */
    public $width = 'full';

    public function __construct()
    {
        parent::__construct();

        $this->withMeta([
            'recentOrders' => $this->recentOrders(),
            'translations' => [
                'recentTitle' => __('nova::resources.Applications'),
                'recentDescription' => __('nova::resources.Recently received applications from parents'),
                'unansweredTitle' => __('nova::resources.Applications without response'),
                'unansweredDescription' => __('nova::resources.Require administrator attention'),
                'noCustomer' => __('nova::resources.Client not specified'),
                'noApplications' => __('nova::resources.No applications yet'),
                'noUnanswered' => __('nova::resources.No applications awaiting response'),
                'noResponse' => __('nova::resources.No response'),
            ],
            'unansweredOrders' => $this->unansweredOrders(),
        ]);
    }

    /**
     * Get the component name for the card.
     */
    public function component(): string
    {
        return 'fayno-applications';
    }

    private function recentOrders(): array
    {
        return Order::query()
            ->with(['customer', 'campShift.camp'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->all();
    }

    private function unansweredOrders(): array
    {
        return Order::query()
            ->with(['customer', 'campShift.camp'])
            ->whereNull('responded_at')
            ->where('status', '!=', OrderStatus::REJECTED->value)
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => [
                ...$this->formatOrder($order),
                'waiting' => $order->created_at?->locale('uk')->diffForHumans(),
            ])
            ->all();
    }

    private function formatOrder(Order $order): array
    {
        $customer = $order->customer;

        return [
            'id' => $order->getKey(),
            'number' => $order->number,
            'customer' => trim(implode(' ', array_filter([
                $customer?->last_name,
                $customer?->first_name,
                $customer?->middle_name,
            ]))),
            'camp' => $order->campShift?->camp?->name,
            'shift' => $order->campShift?->name,
            'status' => $order->status,
            'statusLabel' => $this->statusLabel($order->status),
            'createdAt' => $order->created_at?->format('d.m.Y H:i'),
        ];
    }

    private function statusLabel(string $status): string
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
}

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
                'waiting' => $order->created_at?->locale('ru')->diffForHumans(),
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
            OrderStatus::NEW->value => 'Новая',
            OrderStatus::PROCESSING->value => 'В обработке',
            OrderStatus::WAITING_CUSTOMER->value => 'Ожидает клиента',
            OrderStatus::CONTRACT_SENT->value => 'Договор отправлен',
            OrderStatus::AWAITING_PAYMENT->value => 'Ожидает оплаты',
            OrderStatus::CONFIRMED->value => 'Подтверждена',
            OrderStatus::REJECTED->value => 'Отклонена',
            default => $status,
        };
    }
}

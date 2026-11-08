<?php

namespace App\Nova\Cards;

use App\Enums\BookingStatus;
use App\Enums\CampShiftStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Camp;
use App\Models\Camper;
use App\Models\CampShift;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CampShiftCapacityService;
use Laravel\Nova\Card;

class DashboardOverview extends Card
{
    public $width = 'full';

    public function __construct()
    {
        parent::__construct();

        $this->withMeta([
            'stats' => [
                'bookings' => Booking::query()->count(),
                'campers' => Camper::query()->count(),
                'camps' => Camp::query()->count(),
                'revenue' => (float) Payment::query()
                    ->where('status', PaymentStatus::PAID->value)
                    ->sum('amount'),
            ],
            'chart' => $this->bookingsChart(),
            'shifts' => $this->shifts(),
            'bookingsList' => $this->recentBookings(),
            'attention' => [
                'unanswered' => Order::query()
                    ->whereNull('responded_at')
                    ->where('status', '!=', OrderStatus::REJECTED->value)
                    ->count(),
                'pendingPayments' => Payment::query()
                    ->where('status', PaymentStatus::PENDING->value)
                    ->count(),
            ],
        ]);
    }

    public function component(): string
    {
        return 'fayno-dashboard-overview';
    }

    private function bookingsChart(): array
    {
        $from = now()->startOfMonth()->subMonths(9);
        $counts = Booking::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->get()
            ->keyBy(fn ($row) => sprintf('%04d-%02d', $row->year, $row->month));

        return collect(range(0, 9))->map(function (int $offset) use ($from, $counts) {
            $date = $from->copy()->addMonths($offset);

            return [
                'label' => $date->locale('ru')->translatedFormat('M'),
                'value' => (int) ($counts[$date->format('Y-m')]->total ?? 0),
            ];
        })->all();
    }

    private function shifts(): array
    {
        $service = app(CampShiftCapacityService::class);

        return CampShift::query()
            ->with('camp')
            ->where('status', CampShiftStatus::ACTIVE->value)
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(function (CampShift $shift) use ($service) {
                $capacity = (int) $shift->capacity;
                $booked = $service->bookedPlaces($shift);

                return [
                    'id' => $shift->getKey(),
                    'camp' => $shift->camp?->name,
                    'name' => $shift->name,
                    'booked' => $booked,
                    'capacity' => $capacity,
                    'percentage' => $capacity > 0 ? min(100, round($booked / $capacity * 100)) : 0,
                ];
            })
            ->all();
    }

    private function recentBookings(): array
    {
        return Booking::query()
            ->with(['orderCamper.camper', 'orderCamper.order.campShift.camp', 'payments'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Booking $booking) {
                $camper = $booking->orderCamper?->camper;
                $shift = $booking->orderCamper?->order?->campShift;

                return [
                    'id' => $booking->getKey(),
                    'camper' => trim(implode(' ', array_filter([$camper?->first_name, $camper?->last_name]))) ?: 'Участник не указан',
                    'camp' => $shift?->camp?->name ?? '—',
                    'shift' => $shift?->name ?? '—',
                    'dates' => $shift?->starts_at?->format('d.m.Y').' — '.$shift?->ends_at?->format('d.m.Y'),
                    'status' => $booking->status,
                    'statusLabel' => match ($booking->status) {
                        BookingStatus::CONFIRMED->value => 'Подтверждено',
                        BookingStatus::CANCELLED->value => 'Отменено',
                        default => 'Ожидает',
                    },
                    'amount' => (float) $booking->total_amount,
                    'paid' => (float) $booking->payments
                        ->where('status', PaymentStatus::PAID->value)
                        ->sum('amount'),
                ];
            })
            ->all();
    }
}

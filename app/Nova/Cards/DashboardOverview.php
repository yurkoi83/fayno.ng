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
            'translations' => [
                'title' => __('nova::resources.Camp overview'),
                'description' => __('nova::resources.Manage bookings and programs in one place'),
                'activeCamps' => __('nova::resources.Active camps'),
                'bookings' => __('nova::resources.Bookings'),
                'participants' => __('nova::resources.Participants'),
                'receivedPayments' => __('nova::resources.Received payments'),
                'bookingDynamics' => __('nova::resources.Booking dynamics'),
                'lastMonths' => __('nova::resources.Last 10 months'),
                'occupancy' => __('nova::resources.Camp occupancy'),
                'activeShifts' => __('nova::resources.Active shifts'),
                'places' => __('nova::resources.Places'),
                'noActiveShifts' => __('nova::resources.No active shifts'),
                'latestBookings' => __('nova::resources.Latest bookings'),
                'camper' => __('nova::resources.Camper'),
                'campShift' => __('nova::resources.Camp shift'),
                'dates' => __('nova::resources.Dates'),
                'status' => __('nova::resources.Status'),
                'amount' => __('nova::resources.Amount'),
                'paid' => __('nova::resources.Paid at'),
                'noBookings' => __('nova::resources.No bookings yet'),
                'needsAttention' => __('nova::resources.Needs attention'),
                'unanswered' => __('nova::resources.Applications without response'),
                'contactParents' => __('nova::resources.Contact parents'),
                'pendingPayments' => __('nova::resources.Pending payments'),
                'checkPayments' => __('nova::resources.Check incoming payments'),
                'notSpecified' => __('nova::resources.Client not specified'),
                'confirmed' => __('nova::resources.Confirmed'),
                'cancelled' => __('nova::resources.Cancelled'),
                'waiting' => __('nova::resources.Waiting'),
            ],
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
                'label' => $date->locale('uk')->translatedFormat('M'),
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
                    'camper' => trim(implode(' ', array_filter([$camper?->first_name, $camper?->last_name]))) ?: __('nova::resources.Client not specified'),
                    'camp' => $shift?->camp?->name ?? '—',
                    'shift' => $shift?->name ?? '—',
                    'dates' => $shift?->starts_at?->format('d.m.Y').' — '.$shift?->ends_at?->format('d.m.Y'),
                    'status' => $booking->status,
                    'statusLabel' => match ($booking->status) {
                        BookingStatus::CONFIRMED->value => __('nova::resources.Confirmed'),
                        BookingStatus::CANCELLED->value => __('nova::resources.Cancelled'),
                        default => __('nova::resources.Waiting'),
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

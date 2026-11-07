<?php

namespace App\Nova\Cards;

use App\Enums\CampShiftStatus;
use App\Models\CampShift;
use App\Services\CampShiftCapacityService;
use Laravel\Nova\Card;

class CampShiftOccupancy extends Card
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

        $capacityService = app(CampShiftCapacityService::class);

        $this->withMeta([
            'shifts' => CampShift::query()
                ->with('camp')
                ->where('status', CampShiftStatus::ACTIVE->value)
                ->orderBy('starts_at')
                ->get()
                ->map(function (CampShift $shift) use ($capacityService) {
                    $capacity = (int) $shift->capacity;
                    $booked = $capacityService->bookedPlaces($shift);

                    return [
                        'id' => $shift->getKey(),
                        'camp' => $shift->camp?->name,
                        'name' => $shift->name,
                        'startsAt' => $shift->starts_at?->format('d.m.Y'),
                        'endsAt' => $shift->ends_at?->format('d.m.Y'),
                        'capacity' => $capacity,
                        'booked' => $booked,
                        'available' => max(0, $capacity - $booked),
                        'percentage' => $capacity > 0
                            ? min(100, round($booked / $capacity * 100))
                            : 0,
                    ];
                })
                ->all(),
        ]);
    }

    /**
     * Get the component name for the card.
     */
    public function component(): string
    {
        return 'fayno-camp-shift-occupancy';
    }
}

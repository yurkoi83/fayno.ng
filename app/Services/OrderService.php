<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\Camp;
use App\Models\CampShift;
use App\Models\Camper;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
	public function __construct(
		private readonly PricingService $pricingService,
		private readonly CampShiftCapacityService $capacityService,
	) {
	}

	/**
	 * Create an order for one camp shift and one or more existing campers.
	 *
	 * Expected input:
	 * [
	 *     'customer_id' => 1,
	 *     'camp_shift_id' => 1,
	 *     'campers' => [
	 *         ['camper_id' => 10, 'discount' => 0],
	 *     ],
	 * ]
	 */
	public function create(array $data): Order
	{
		$customerId = (int) ($data['customer_id'] ?? 0);
		$shiftId = (int) ($data['camp_shift_id'] ?? 0);
		$campersData = $data['campers'] ?? [];

		if ($customerId < 1 || $shiftId < 1 || !is_array($campersData) || empty($campersData)) {
			throw ValidationException::withMessages([
				'order' => __('A customer, a camp shift and at least one camper are required.'),
			]);
		}

		foreach ($campersData as $camperData) {
			if (!is_array($camperData) || (int) ($camperData['camper_id'] ?? 0) < 1) {
				throw ValidationException::withMessages([
					'campers' => __('Each camper must have a valid identifier.'),
				]);
			}
		}

		return DB::transaction(function () use ($data, $customerId, $shiftId, $campersData) {
			/** @var CampShift|null $shift */
			$shift = CampShift::query()->lockForUpdate()->find($shiftId);

			if (!$shift) {
				throw ValidationException::withMessages([
					'camp_shift_id' => __('The selected camp shift is unavailable.'),
				]);
			}

			/** @var Camp|null $camp */
			$camp = Camp::withoutGlobalScopes()->find($shift->camp_id);

			if (!$camp || !$camp->active) {
				throw ValidationException::withMessages([
					'camp_shift_id' => __('The selected camp is not active.'),
				]);
			}

			if ($shift->status !== 'published') {
				throw ValidationException::withMessages([
					'camp_shift_id' => __('The selected camp shift is not open for booking.'),
				]);
			}

			if ($shift->ends_at->lt(today())) {
				throw ValidationException::withMessages([
					'camp_shift_id' => __('The selected camp shift has already ended.'),
				]);
			}

			$camperIds = collect($campersData)
				->pluck('camper_id')
				->map(fn ($id) => (int) $id)
				->values();

			if ($camperIds->unique()->count() !== $camperIds->count()) {
				throw ValidationException::withMessages([
					'campers' => __('Each camper must be specified once.'),
				]);
			}

			$campers = Camper::query()
				->where('customer_id', $customerId)
				->whereIn('id', $camperIds)
				->get()
				->keyBy('id');

			if ($campers->count() !== $camperIds->count()) {
				throw ValidationException::withMessages([
					'campers' => __('One or more campers do not belong to this customer.'),
				]);
			}

			foreach ($campers as $camper) {
				$age = (int) Carbon::parse($camper->birth_date)
					->diffInYears(Carbon::parse($shift->starts_at));

				if ($camp->min_age !== null && $age < $camp->min_age) {
					throw ValidationException::withMessages([
						'campers' => __('A camper is younger than the minimum age for this camp.'),
					]);
				}

				if ($camp->max_age !== null && $age > $camp->max_age) {
					throw ValidationException::withMessages([
						'campers' => __('A camper is older than the maximum age for this camp.'),
					]);
				}
			}

			if (!$this->capacityService->hasCapacity($shift, $camperIds->count())) {
				throw ValidationException::withMessages([
					'camp_shift_id' => __('There are not enough places available for this camp shift.'),
				]);
			}

			$order = Order::query()->create([
				'number' => $this->generateOrderNumber(),
				'customer_id' => $customerId,
				'camp_shift_id' => $shift->getKey(),
				'status' => OrderStatus::NEW->value,
				'source' => Arr::get($data, 'source'),
				'departure_city' => Arr::get($data, 'departure_city'),
				'comment' => Arr::get($data, 'comment'),
			]);

			foreach ($campersData as $camperData) {
				$price = $this->pricingService->calculate($shift, $camperData['discount'] ?? 0);

				$orderCamper = $order->orderCampers()->create([
					'camper_id' => (int) $camperData['camper_id'],
					'price' => $price['price'],
					'discount' => $price['discount'],
					'currency' => $price['currency'],
				]);

				$orderCamper->booking()->create([
					'status' => BookingStatus::PENDING->value,
					'total_amount' => $price['total'],
					'currency' => $price['currency'],
				]);
			}

			return $order->load([
				'customer',
				'campShift.camp',
				'orderCampers.camper',
				'orderCampers.booking',
			]);
		}, attempts: 3);
	}

	private function generateOrderNumber(): string
	{
		do {
			$number = 'FC-' . now()->format('ymd') . '-' . Str::upper(Str::random(8));
		} while (Order::query()->where('number', $number)->exists());

		return $number;
	}
}

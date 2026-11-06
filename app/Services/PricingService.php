<?php

namespace App\Services;

use App\Models\CampShift;
use Illuminate\Validation\ValidationException;

class PricingService
{
	/**
	 * Calculate the price for one camper.
	 *
	 * Discount is an absolute amount in the shift currency, not a percentage.
	 *
	 * @return array{price: string, discount: string, total: string, currency: string}
	 */
	public function calculate(CampShift $shift, int|float|string $discount = 0): array
	{
		$useEarlyPrice = (
			$shift->early_price !== null
			&& $shift->early_booking_until !== null
			&& today()->lte($shift->early_booking_until)
		);

		$price = $this->toCents($useEarlyPrice ? $shift->early_price : $shift->regular_price);
		$discount = $this->toCents($discount);

		if ($discount > $price) {
			throw ValidationException::withMessages([
				'discount' => __('The discount must not exceed the selected price.'),
			]);
		}

		return [
			'price'    => $this->fromCents($price),
			'discount' => $this->fromCents($discount),
			'total'    => $this->fromCents($price - $discount),
			'currency' => $shift->currency,
		];
	}

	/**
	 * Convert an amount to cents to avoid floating-point arithmetic.
	 */
	private function toCents(int|float|string $amount): int
	{
		if (!is_numeric($amount) || (float) $amount < 0) {
			throw ValidationException::withMessages([
				'price' => __('The amount must be a non-negative number.'),
			]);
		}

		return (int) round((float) $amount * 100);
	}

	private function fromCents(int $amount): string
	{
		return number_format($amount / 100, 2, '.', '');
	}
}

<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\CampShiftStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Camp;
use App\Models\Camper;
use App\Models\CampShift;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderCamper;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardDemoSeeder extends Seeder
{
	/**
	 * Seed demo data for the Nova dashboard.
	 */
	public function run(): void
	{
		DB::transaction(function () {
			$camp = Camp::firstOrCreate(
				['slug' => 'demo-fayno-camp'],
				[
					'name' => 'Fayno Camp — демо',
					'country' => 'Украина',
					'city' => 'Карпаты',
					'address' => 'Демо-адрес',
					'min_age' => 7,
					'max_age' => 17,
					'active' => true,
				]
			);

			$firstShift = CampShift::firstOrCreate(
				['camp_id' => $camp->getKey(), 'name' => 'Демо — летняя смена №1'],
				[
					'starts_at' => now()->addMonths(7)->startOfMonth()->toDateString(),
					'ends_at' => now()->addMonths(7)->startOfMonth()->addDays(13)->toDateString(),
					'capacity' => 35,
					'regular_price' => 590,
					'early_price' => 540,
					'early_booking_until' => now()->addMonths(6)->endOfMonth()->toDateString(),
					'currency' => 'EUR',
					'status' => CampShiftStatus::ACTIVE->value,
				]
			);

			$secondShift = CampShift::firstOrCreate(
				['camp_id' => $camp->getKey(), 'name' => 'Демо — летняя смена №2'],
				[
					'starts_at' => now()->addMonths(8)->startOfMonth()->toDateString(),
					'ends_at' => now()->addMonths(8)->startOfMonth()->addDays(13)->toDateString(),
					'capacity' => 45,
					'regular_price' => 620,
					'early_price' => 570,
					'early_booking_until' => now()->addMonths(7)->endOfMonth()->toDateString(),
					'currency' => 'EUR',
					'status' => CampShiftStatus::ACTIVE->value,
				]
			);

			$scenarios = [
				[
					'number' => 'DEMO-2026-001',
					'first_name' => 'Александр',
					'last_name' => 'Мельник',
					'email' => 'demo.parent.001@example.com',
					'phone' => '+380501110001',
					'camper_first_name' => 'Максим',
					'camper_last_name' => 'Мельник',
					'age' => 11,
					'status' => OrderStatus::CONFIRMED,
					'booking_status' => BookingStatus::CONFIRMED,
					'payment_status' => PaymentStatus::PAID,
					'payment_amount' => 150,
					'responded_days_ago' => 5,
					'created_days_ago' => 8,
					'shift' => 1,
				],
				[
					'number' => 'DEMO-2026-002',
					'first_name' => 'Елена',
					'last_name' => 'Коваль',
					'email' => 'demo.parent.002@example.com',
					'phone' => '+380501110002',
					'camper_first_name' => 'София',
					'camper_last_name' => 'Коваль',
					'age' => 9,
					'status' => OrderStatus::AWAITING_PAYMENT,
					'booking_status' => BookingStatus::PENDING,
					'payment_status' => PaymentStatus::PENDING,
					'payment_amount' => 100,
					'responded_days_ago' => null,
					'created_days_ago' => 3,
					'shift' => 1,
				],
				[
					'number' => 'DEMO-2026-003',
					'first_name' => 'Ирина',
					'last_name' => 'Шевченко',
					'email' => 'demo.parent.003@example.com',
					'phone' => '+380501110003',
					'camper_first_name' => 'Артём',
					'camper_last_name' => 'Шевченко',
					'age' => 13,
					'status' => OrderStatus::WAITING_CUSTOMER,
					'booking_status' => BookingStatus::PENDING,
					'payment_status' => PaymentStatus::PAID,
					'payment_amount' => 100,
					'responded_days_ago' => null,
					'created_days_ago' => 2,
					'shift' => 1,
				],
				[
					'number' => 'DEMO-2026-004',
					'first_name' => 'Дмитрий',
					'last_name' => 'Бондарь',
					'email' => 'demo.parent.004@example.com',
					'phone' => '+380501110004',
					'camper_first_name' => 'Даниил',
					'camper_last_name' => 'Бондарь',
					'age' => 10,
					'status' => OrderStatus::NEW,
					'booking_status' => BookingStatus::PENDING,
					'payment_status' => null,
					'payment_amount' => null,
					'responded_days_ago' => null,
					'created_days_ago' => 1,
					'shift' => 1,
				],
				[
					'number' => 'DEMO-2026-005',
					'first_name' => 'Оксана',
					'last_name' => 'Ткаченко',
					'email' => 'demo.parent.005@example.com',
					'phone' => '+380501110005',
					'camper_first_name' => 'Анна',
					'camper_last_name' => 'Ткаченко',
					'age' => 12,
					'status' => OrderStatus::PROCESSING,
					'booking_status' => BookingStatus::PENDING,
					'payment_status' => null,
					'payment_amount' => null,
					'responded_days_ago' => null,
					'created_days_ago' => 1,
					'shift' => 1,
				],
				[
					'number' => 'DEMO-2026-006',
					'first_name' => 'Наталья',
					'last_name' => 'Романюк',
					'email' => 'demo.parent.006@example.com',
					'phone' => '+380501110006',
					'camper_first_name' => 'Марк',
					'camper_last_name' => 'Романюк',
					'age' => 14,
					'status' => OrderStatus::CONTRACT_SENT,
					'booking_status' => BookingStatus::PENDING,
					'payment_status' => PaymentStatus::PENDING,
					'payment_amount' => 50,
					'responded_days_ago' => 1,
					'created_days_ago' => 4,
					'shift' => 2,
				],
				[
					'number' => 'DEMO-2026-007',
					'first_name' => 'Сергей',
					'last_name' => 'Мороз',
					'email' => 'demo.parent.007@example.com',
					'phone' => '+380501110007',
					'camper_first_name' => 'Ева',
					'camper_last_name' => 'Мороз',
					'age' => 8,
					'status' => OrderStatus::CONFIRMED,
					'booking_status' => BookingStatus::CONFIRMED,
					'payment_status' => PaymentStatus::PAID,
					'payment_amount' => 620,
					'responded_days_ago' => 2,
					'created_days_ago' => 6,
					'shift' => 2,
				],
				[
					'number' => 'DEMO-2026-008',
					'first_name' => 'Мария',
					'last_name' => 'Лисенко',
					'email' => 'demo.parent.008@example.com',
					'phone' => '+380501110008',
					'camper_first_name' => 'Полина',
					'camper_last_name' => 'Лисенко',
					'age' => 15,
					'status' => OrderStatus::REJECTED,
					'booking_status' => BookingStatus::CANCELLED,
					'payment_status' => null,
					'payment_amount' => null,
					'responded_days_ago' => 3,
					'created_days_ago' => 7,
					'shift' => 2,
				],
			];

			foreach ($scenarios as $scenario) {
				$this->seedOrder($scenario, $scenario['shift'] === 1 ? $firstShift : $secondShift);
			}
		});
	}

	/**
	 * Create one demo application with its camper, booking and optional payment.
	 */
	private function seedOrder(array $data, CampShift $shift): void
	{
		$customer = Customer::firstOrCreate(
			['email' => $data['email']],
			[
				'first_name' => $data['first_name'],
				'last_name' => $data['last_name'],
				'phone' => $data['phone'],
				'city' => 'Киев',
			]
		);

		$camper = Camper::firstOrCreate(
			[
				'customer_id' => $customer->getKey(),
				'first_name' => $data['camper_first_name'],
				'last_name' => $data['camper_last_name'],
			],
			[
				'birth_date' => now()->subYears($data['age'])->subMonths(4)->toDateString(),
			]
		);

		$orderCreatedAt = now()->subDays($data['created_days_ago']);

		$order = Order::firstOrCreate(
			['number' => $data['number']],
			[
				'customer_id' => $customer->getKey(),
				'camp_shift_id' => $shift->getKey(),
				'status' => $data['status']->value,
				'source' => 'website',
				'departure_city' => 'Киев',
				'comment' => 'Демо-заявка для проверки дашборда',
				'responded_at' => $data['responded_days_ago'] === null
					? null
					: now()->subDays($data['responded_days_ago']),
			]
		);

		// Keep the demo dates stable when the seeder is run again.
		DB::table('orders')->where('id', $order->getKey())->update([
			'created_at' => $orderCreatedAt,
			'updated_at' => $orderCreatedAt,
		]);

		$orderCamper = OrderCamper::firstOrCreate(
			[
				'order_id' => $order->getKey(),
				'camper_id' => $camper->getKey(),
			],
			[
				'price' => $shift->regular_price,
				'discount' => 0,
				'currency' => $shift->currency,
			]
		);

		$booking = Booking::firstOrCreate(
			['order_camper_id' => $orderCamper->getKey()],
			[
				'status' => $data['booking_status']->value,
				'total_amount' => $shift->regular_price,
				'currency' => $shift->currency,
				'confirmed_at' => $data['booking_status'] === BookingStatus::CONFIRMED
					? $orderCreatedAt
					: null,
			]
		);

		if ($data['payment_status'] !== null) {
			Payment::firstOrCreate(
				['transaction_id' => 'DEMO-PAY-' . str_replace('-', '', $data['number'])],
				[
					'booking_id' => $booking->getKey(),
					'amount' => $data['payment_amount'],
					'currency' => $shift->currency,
					'status' => $data['payment_status']->value,
					'payment_method' => 'demo',
					'paid_at' => $data['payment_status'] === PaymentStatus::PAID
						? $orderCreatedAt
						: null,
				]
			);
		}
	}
}

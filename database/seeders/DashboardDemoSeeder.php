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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Seed demo data for the Nova dashboard.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $camp = Camp::updateOrCreate(
                ['slug' => 'FC-fayno-camp'],
                [
                    'name' => 'Fayno Camp — демо',
                    'country' => 'Україна',
                    'city' => 'Карпати',
                    'address' => 'Демо-адреса',
                    'min_age' => 7,
                    'max_age' => 17,
                    'active' => true,
                ]
            );

            $firstShift = CampShift::updateOrCreate(
                ['camp_id' => $camp->getKey(), 'starts_at' => now()->addMonths(7)->startOfMonth()->toDateString()],
                [
                    'name' => 'Демо — літня зміна №1',
                    'ends_at' => now()->addMonths(7)->startOfMonth()->addDays(13)->toDateString(),
                    'capacity' => 35,
                    'regular_price' => 590,
                    'early_price' => 540,
                    'early_booking_until' => now()->addMonths(6)->endOfMonth()->toDateString(),
                    'currency' => 'EUR',
                    'status' => CampShiftStatus::ACTIVE->value,
                ]
            );

            $secondShift = CampShift::updateOrCreate(
                ['camp_id' => $camp->getKey(), 'starts_at' => now()->addMonths(8)->startOfMonth()->toDateString()],
                [
                    'name' => 'Демо — літня зміна №2',
                    'ends_at' => now()->addMonths(8)->startOfMonth()->addDays(13)->toDateString(),
                    'capacity' => 45,
                    'regular_price' => 620,
                    'early_price' => 570,
                    'early_booking_until' => now()->addMonths(7)->endOfMonth()->toDateString(),
                    'currency' => 'EUR',
                    'status' => CampShiftStatus::ACTIVE->value,
                ]
            );

            $manager = User::updateOrCreate(
                ['email' => 'demo.manager@fayno.camp'],
                [
                    'name' => 'Олена Кравченко',
                    'password' => Hash::make(Str::random(48)),
                ]
            );

            $scenarios = [
                [
                    'number' => 'FC-2026-001',
                    'first_name' => 'Олександр',
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
                    'number' => 'FC-2026-002',
                    'first_name' => 'Олена',
                    'last_name' => 'Коваль',
                    'email' => 'demo.parent.002@example.com',
                    'phone' => '+380501110002',
                    'camper_first_name' => 'Софія',
                    'camper_last_name' => 'Коваль',
                    'age' => 9,
                    'status' => OrderStatus::AWAITING_PAYMENT,
                    'booking_status' => BookingStatus::PENDING,
                    'payment_status' => PaymentStatus::PENDING,
                    'payment_amount' => 100,
                    'responded_days_ago' => 2,
                    'created_days_ago' => 3,
                    'shift' => 1,
                ],
                [
                    'number' => 'FC-2026-003',
                    'first_name' => 'Ірина',
                    'last_name' => 'Шевченко',
                    'email' => 'demo.parent.003@example.com',
                    'phone' => '+380501110003',
                    'camper_first_name' => 'Артем',
                    'camper_last_name' => 'Шевченко',
                    'age' => 13,
                    'status' => OrderStatus::WAITING_CUSTOMER,
                    'booking_status' => BookingStatus::PENDING,
                    'payment_status' => PaymentStatus::PAID,
                    'payment_amount' => 100,
                    'responded_days_ago' => 1,
                    'created_days_ago' => 2,
                    'shift' => 1,
                ],
                [
                    'number' => 'FC-2026-004',
                    'first_name' => 'Дмитро',
                    'last_name' => 'Бондарь',
                    'email' => 'demo.parent.004@example.com',
                    'phone' => '+380501110004',
                    'camper_first_name' => 'Данило',
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
                    'number' => 'FC-2026-005',
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
                    'number' => 'FC-2026-006',
                    'first_name' => 'Наталія',
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
                    'number' => 'FC-2026-007',
                    'first_name' => 'Сергій',
                    'last_name' => 'Мороз',
                    'email' => 'demo.parent.007@example.com',
                    'phone' => '+380501110007',
                    'camper_first_name' => 'Єва',
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
                    'number' => 'FC-2026-008',
                    'first_name' => 'Марія',
                    'last_name' => 'Лисенко',
                    'email' => 'demo.parent.008@example.com',
                    'phone' => '+380501110008',
                    'camper_first_name' => 'Поліна',
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
                $this->seedOrder(
                    $scenario,
                    $scenario['shift'] === 1 ? $firstShift : $secondShift,
                    $manager
                );
            }
        });

        $this->seedDemoAvatars();
    }

    /**
     * Create one demo application with its camper, booking and optional payment.
     */
    private function seedOrder(array $data, CampShift $shift, User $manager): void
    {
        $customer = Customer::updateOrCreate(
            ['email' => $data['email']],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'city' => 'Київ',
            ]
        );

        $camper = Camper::updateOrCreate(
            ['customer_id' => $customer->getKey()],
            [
                'first_name' => $data['camper_first_name'],
                'last_name' => $data['camper_last_name'],
                'birth_date' => now()->subYears($data['age'])->subMonths(4)->toDateString(),
            ]
        );

        $orderCreatedAt = now()->subDays($data['created_days_ago']);

        $order = Order::updateOrCreate(
            ['number' => $data['number']],
            [
                'customer_id' => $customer->getKey(),
                'camp_shift_id' => $shift->getKey(),
                'status' => $data['status']->value,
                'source' => 'website',
                'departure_city' => 'Київ',
                'comment' => 'Демо-заявка для перевірки CRM',
                'assigned_to' => $manager->getKey(),
                'responded_at' => $data['responded_days_ago'] === null
                    ? null
                    : now()->subDays($data['responded_days_ago']),
            ]
        );

        // Set relative demo dates only when the order is created for the first time.
        if ($order->wasRecentlyCreated) {
            DB::table('orders')->where('id', $order->getKey())->update([
                'created_at' => $orderCreatedAt,
                'updated_at' => $orderCreatedAt,
            ]);
        }

        if (! $order->assigned_to) {
            $order->forceFill(['assigned_to' => $manager->getKey()])->save();
        }

        $orderCamper = OrderCamper::updateOrCreate(
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

        $booking = Booking::updateOrCreate(
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
            Payment::updateOrCreate(
                ['transaction_id' => 'FC-PAY-' . str_replace('-', '', $data['number'])],
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

    /**
     * Download deterministic demo avatars from DiceBear and keep them locally.
     */
    private function seedDemoAvatars(): void
    {
        $campers = Camper::query()
            ->whereHas('customer', fn ($query) => $query->where('email', 'like', 'demo.parent.%@example.com'))
            ->get();

        foreach ($campers as $camper) {
            if ($camper->photo_path) {
                continue;
            }

            $path = $this->downloadDiceBearAvatar(
                'adventurer-neutral',
                'fayno-camper-'.$camper->getKey(),
                'FC-avatars/діти/'.$camper->getKey().'.svg',
                'cfe8ff'
            );

            if ($path) {
                $camper->forceFill(['photo_path' => $path])->save();
            }
        }

        $manager = User::query()->where('email', 'demo.manager@fayno.camp')->first();

        if ($manager && ! $manager->avatar_path) {
            $path = $this->downloadDiceBearAvatar(
                'lorelei',
                'fayno-manager-'.$manager->getKey(),
                'FC-avatars/менеджери/'.$manager->getKey().'.svg',
                'd9f2eb'
            );

            if ($path) {
                $manager->forceFill(['avatar_path' => $path])->save();
            }
        }
    }

    private function downloadDiceBearAvatar(
        string $style,
        string $seed,
        string $path,
        string $background
    ): ?string {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        try {
            $response = Http::accept('image/svg+xml')
                ->timeout(15)
                ->retry(2, 300)
                ->get("https://api.dicebear.com/10.x/{$style}/svg", [
                    'seed' => $seed,
                    'backgroundColor' => $background,
                ]);

            $svg = $response->successful() ? $response->body() : '';

            if (! str_contains($svg, '<svg')) {
                Log::warning('DiceBear did not return a valid SVG avatar.', [
                    'style' => $style,
                    'seed' => $seed,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $disk->put($path, $svg);

            return $path;
        } catch (\Throwable $exception) {
            Log::warning('Unable to download a demo avatar from DiceBear.', [
                'style' => $style,
                'seed' => $seed,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}

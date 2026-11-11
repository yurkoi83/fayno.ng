<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->select(['id', 'status', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                $now = now();

                DB::table('order_status_histories')->insert(
                    $orders->map(fn ($order) => [
                        'order_id' => $order->id,
                        'from_status' => null,
                        'to_status' => $order->status,
                        'changed_by' => null,
                        'comment' => null,
                        'created_at' => $order->created_at ?? $now,
                        'updated_at' => $order->created_at ?? $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        // Historical snapshots are intentionally retained if this migration is rolled back.
    }
};

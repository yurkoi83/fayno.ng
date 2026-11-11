<?php

namespace App\Observers;

use App\Models\Order;

class OrderObserver extends BaseObserver
{
    public function created(Order $order): void
    {
        $order->statusHistories()->create([
            'to_status' => $order->status,
            'changed_by' => auth()->id(),
        ]);
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = $order->getPrevious();

        $order->statusHistories()->create([
            'from_status' => $previous['status'] ?? null,
            'to_status' => $order->status,
            'changed_by' => auth()->id(),
        ]);
    }
}

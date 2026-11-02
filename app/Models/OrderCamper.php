<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class OrderCamper extends BaseModel
{
    protected $fillable = ['order_id', 'camper_id', 'price', 'discount', 'currency'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'discount' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function camper(): BelongsTo
    {
        return $this->belongsTo(Camper::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }
}

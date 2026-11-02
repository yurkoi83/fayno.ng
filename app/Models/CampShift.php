<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CampShift extends BaseModel
{
    protected $fillable = ['camp_id', 'name', 'starts_at', 'ends_at', 'capacity', 'regular_price', 'early_price', 'early_booking_until', 'currency', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'capacity' => 'integer', 'regular_price' => 'decimal:2', 'early_price' => 'decimal:2', 'early_booking_until' => 'date'];
    }

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

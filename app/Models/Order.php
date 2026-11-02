<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasManyThrough};

class Order extends BaseModel
{
    protected $fillable = ['number', 'customer_id', 'camp_shift_id', 'status', 'source', 'departure_city', 'comment', 'admin_notes', 'assigned_to', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campShift(): BelongsTo
    {
        return $this->belongsTo(CampShift::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function orderCampers(): HasMany
    {
        return $this->hasMany(OrderCamper::class);
    }

    public function campers(): BelongsToMany
    {
        return $this->belongsToMany(Camper::class, 'order_campers')->withPivot(['id', 'price', 'discount', 'currency'])->withTimestamps();
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, OrderCamper::class, 'order_id', 'order_camper_id', 'id', 'id');
    }
}

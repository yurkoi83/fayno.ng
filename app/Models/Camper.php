<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Camper extends BaseModel
{
    protected $fillable = ['customer_id', 'first_name', 'last_name', 'middle_name', 'birth_date', 'gender_id', 'notes'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'gender_id' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderCampers(): HasMany
    {
        return $this->hasMany(OrderCamper::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_campers')->withPivot(['id', 'price', 'discount', 'currency'])->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasManyThrough(Booking::class, OrderCamper::class, 'camper_id', 'order_camper_id', 'id', 'id');
    }
}

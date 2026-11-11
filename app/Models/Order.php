<?php

namespace App\Models;

use App\Casts\DateTimeCast;
use App\Models\Traits\Common\AppendsTrait;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasManyThrough};
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([OrderObserver::class])]
class Order extends BaseModel
{
    use AppendsTrait;

    protected $table = 'orders';

    protected $guarded = ['id'];

    protected $fillable = [
        'number',
        'customer_id',
        'camp_shift_id',
        'status',
        'source',
        'departure_city',
        'comment',
        'admin_notes',
        'assigned_to',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => DateTimeCast::class,
            'updated_at' => DateTimeCast::class,
            'responded_at' => DateTimeCast::class,
        ];
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

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
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

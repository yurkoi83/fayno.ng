<?php

namespace App\Models;

use App\Casts\DateTimeCast;
use App\Models\Traits\Common\AppendsTrait;
use App\Observers\BookingObserver;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([BookingObserver::class])]
class Booking extends BaseModel
{
    use AppendsTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bookings';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
//    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_camper_id',
        'status',
        'total_amount',
        'currency',
        'confirmed_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'confirmed_at' => DateTimeCast::class,
            'created_at' => DateTimeCast::class,
            'updated_at' => DateTimeCast::class,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    /**
     * @return BelongsTo
     */
    public function orderCamper(): BelongsTo
    {
        return $this->belongsTo(OrderCamper::class);
    }

    /**
     * @return HasMany
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | ACCESSORS | MUTATORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | OTHER PRIVATE METHODS
    |--------------------------------------------------------------------------
    */
}

<?php

namespace App\Models;

use App\Casts\DateTimeCast;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Observers\BookingObserver;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([ActiveScope::class])]
#[ObservedBy([BookingObserver::class])]

class Camp extends BaseModel
{
    use AppendsTrait, Sluggable, SluggableScopeHelpers;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'Camps';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        'name',
        'slug',
        'country',
        'city',
        'address',
        'description',
        'min_age',
        'max_age',
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
            'min_age'   => 'integer',
            'max_age'   => 'integer',
            'active'    => 'boolean',
            'created_at' => DateTimeCast::class,
            'updated_at' => DateTimeCast::class,

        ];
    }

    /**
     * Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => ['slug', 'name'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function shifts(): HasMany
    {
        return $this->hasMany(CampShift::class);
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

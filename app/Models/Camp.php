<?php

namespace App\Models;

use App\Casts\DateTimeCast;
use App\Models\Scopes\ActiveScope;
use App\Models\Traits\Common\AppendsTrait;
use App\Observers\CampObserver;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ScopedBy([ActiveScope::class])]
#[ObservedBy([CampObserver::class])]
class Camp extends BaseModel
{
    use AppendsTrait, Sluggable, SluggableScopeHelpers;

    protected $table = 'camps';

    protected $guarded = ['id'];

    protected $fillable = [
        'name',
        'slug',
        'country',
        'city',
        'address',
        'description',
        'photo_path',
        'min_age',
        'max_age',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'active' => 'boolean',
            'created_at' => DateTimeCast::class,
            'updated_at' => DateTimeCast::class,
        ];
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CampShift::class);
    }
}

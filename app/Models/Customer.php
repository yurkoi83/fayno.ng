<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends BaseModel
{
    protected $fillable = ['first_name', 'last_name', 'middle_name', 'phone', 'email', 'city', 'address', 'notes'];

    public function campers(): HasMany
    {
        return $this->hasMany(Camper::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'capacity_m3',
    ];

    protected $casts = [
        'capacity_m3' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}

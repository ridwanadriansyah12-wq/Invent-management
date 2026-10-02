<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryDefault extends Model
{
    // Tabel hanya punya satu timestamp (updated_at), tanpa created_at
    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    protected $fillable = [
        'category_id',
        'avg_daily_demand',
        'safety_days',
        'source',
    ];

    protected $casts = [
        'avg_daily_demand' => 'float',
        'safety_days'      => 'integer',
        'updated_at'       => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

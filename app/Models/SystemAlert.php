<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAlert extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'item_id',
        'warehouse_id',
        'message',
        'created_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function resolve(int $userId): bool
    {
        return $this->update([
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
    }
}

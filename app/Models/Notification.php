<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'item_id', 'type', 'title', 'message', 'is_read', 'target_role',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function item() { return $this->belongsTo(Item::class); }

    public function getIconAttribute(): string
    {
        return match($this->type) {
            'out_of_stock' => '⛔',
            'reorder'      => '🔄',
            'low_stock'    => '⚠️',
            'overstock'    => '📦',
            'po_received'  => '✅',
            default        => 'ℹ️',
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return match($this->type) {
            'out_of_stock' => 'badge-danger',
            'reorder'      => 'badge-warning',
            'low_stock'    => 'badge-warning',
            'overstock'    => 'badge-info',
            'po_received'  => 'badge-success',
            default        => 'badge-secondary',
        };
    }

    /** Scope: visible to a given role */
    public function scopeForRole($query, string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->where('target_role', 'all')->orWhere('target_role', $role);
        });
    }

    public function scopeUnread($query) { return $query->where('is_read', false); }
}

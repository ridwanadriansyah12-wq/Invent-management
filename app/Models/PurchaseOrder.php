<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number', 'item_id', 'supplier_id', 'user_id',
        'quantity', 'quantity_received', 'status',
        'order_date', 'expected_date', 'received_date', 'notes',
    ];

    protected $casts = [
        'quantity'          => 'float',
        'quantity_received' => 'float',
        'order_date'        => 'date',
        'expected_date'     => 'date',
        'received_date'     => 'date',
    ];

    public function item()     { return $this->belongsTo(Item::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function user()     { return $this->belongsTo(User::class); }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'Menunggu',
            'partial'   => 'Sebagian Diterima',
            'received'  => 'Diterima',
            'cancelled' => 'Dibatalkan',
            default     => $this->status,
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'badge-warning',
            'partial'   => 'badge-info',
            'received'  => 'badge-success',
            'cancelled' => 'badge-danger',
            default     => 'badge-secondary',
        };
    }
}

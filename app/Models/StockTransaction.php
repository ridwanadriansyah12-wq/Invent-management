<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'item_id', 'user_id', 'type', 'quantity',
        'stock_before', 'stock_after', 'reference_no', 'notes', 'transaction_date',
    ];

    protected $casts = [
        'quantity'         => 'float',
        'stock_before'     => 'float',
        'stock_after'      => 'float',
        'transaction_date' => 'date',
    ];

    public function item() { return $this->belongsTo(Item::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'in'         => 'Stok Masuk',
            'out'        => 'Stok Keluar',
            'adjustment' => 'Penyesuaian',
            default      => $this->type,
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DailyDemand — Ledger permintaan harian per SKU.
 *
 * Ditulis oleh Python FastAPI via SQLAlchemy (upsert pada item_id + date).
 * Laravel hanya membaca tabel ini.
 *
 * PENTING: Kolom issued_qty tidak boleh dimodifikasi dari Laravel.
 *          Hanya demand_clean yang merupakan nilai bersih untuk ML.
 */
class DailyDemand extends Model
{
    protected $table = 'daily_demand';

    /**
     * Kolom yang boleh diisi dari Laravel (untuk seed/test saja).
     * Produksi: Python yang mengisi via upsert.
     */
    protected $fillable = [
        'item_id',
        'date',
        'opening_stock',
        'receipt_qty',
        'issued_qty',
        'is_censored',
        'demand_clean',
        'imputation_method',
    ];

    protected $casts = [
        'date'             => 'date',
        'opening_stock'    => 'float',
        'receipt_qty'      => 'float',
        'issued_qty'       => 'float',
        'is_censored'      => 'boolean',
        'demand_clean'     => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

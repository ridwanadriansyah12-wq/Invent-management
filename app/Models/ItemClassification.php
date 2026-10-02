<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ItemClassification — Hasil klasifikasi ABC-XYZ dan pola permintaan per SKU.
 *
 * Satu record per SKU (unique pada item_id).
 * Ditulis oleh Python FastAPI (POST /api/v1/classification/batch) mingguan via upsert.
 * Laravel membaca demand_pattern untuk routing model forecast (Tahap 3).
 */
class ItemClassification extends Model
{
    protected $table = 'item_classifications';

    protected $fillable = [
        'item_id',
        'computed_at',
        'abc_class',
        'annual_value',
        'xyz_class',
        'adi',
        'cv2',
        'demand_pattern',
        'history_days',
        'is_reliable',
    ];

    protected $casts = [
        'computed_at'   => 'datetime',
        'annual_value'  => 'float',
        'adi'           => 'float',
        'cv2'           => 'float',
        'history_days'  => 'integer',
        'is_reliable'   => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Apakah SKU ini diklasifikasikan sebagai intermittent atau lumpy?
     * Routing ke model SBA/Croston di Tahap 3.
     */
    public function usesIntermittentModel(): bool
    {
        return in_array($this->demand_pattern, ['intermittent', 'lumpy'], true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PurchaseRequisition — PR yang diterbitkan otomatis oleh sistem (Tahap 7).
 *
 * Dibuat HANYA oleh Laravel PurchaseRequisitionTriggerService.
 *
 * Invariant:
 *   - Hanya satu PR berstatus OPEN per item pada satu waktu
 *   - q_final selalu = max(moq, ceil(q_raw/lot_size) × lot_size)  [via OrderQuantitySanitizer]
 *   - q_final dan quantity dalam snapshot tidak boleh negatif atau nol
 */
class PurchaseRequisition extends Model
{
    protected $fillable = [
        'item_id',
        'parameter_id',
        'inventory_position',
        'effective_rop_snapshot',
        'effective_max_snapshot',
        'q_raw',
        'q_final',
        'moq_snapshot',
        'lot_size_snapshot',
        'status',
        'volume_flag',
        'notes',
    ];

    protected $casts = [
        'inventory_position'     => 'float',
        'effective_rop_snapshot' => 'float',
        'effective_max_snapshot' => 'float',
        'q_raw'                  => 'float',
        'q_final'                => 'float',
        'moq_snapshot'           => 'float',
        'lot_size_snapshot'      => 'float',
        'volume_flag'            => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(InventoryParameter::class, 'parameter_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** PR yang masih terhitung dalam inventory_position */
    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['OPEN', 'ORDERED']);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }
}

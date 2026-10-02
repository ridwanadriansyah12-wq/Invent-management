<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * InventoryParameter — Sumber kebenaran tunggal ROP/SS/MAX efektif per SKU.
 *
 * Hanya Laravel yang menulis tabel ini (Tahap 4 & 5 pipeline harian).
 *
 * Status lifecycle:
 *   ACTIVE          → berlaku, dalam batas clamping
 *   PENDING_REVIEW  → ROP melompat > 50% dari baseline; menunggu approval procurement
 *   APPROVED        → disetujui; effective = proposed
 *   REJECTED        → ditolak; effective tetap nilai clamp
 *   SUPERSEDED      → digantikan oleh record baru ACTIVE/APPROVED
 *
 * Saat parameter baru ACTIVE/APPROVED dibuat, semua record lain untuk item yang sama
 * harus di-update ke SUPERSEDED. Logika ini ada di ParameterCalculatorService.
 */
class InventoryParameter extends Model
{
    protected $fillable = [
        'item_id',
        'computed_at',
        'source',
        'forecast_run_id',
        'proposed_ss',
        'proposed_rop',
        'proposed_max',
        'effective_ss',
        'effective_rop',
        'effective_max',
        'effective_max_before_capacity',
        'status',
        'flag_reason',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'snapshot_mu_daily',
        'snapshot_sigma_daily',
        'snapshot_z_score',
        'snapshot_lead_time_days',
        'snapshot_lead_time_std',
    ];

    protected $casts = [
        'computed_at'                    => 'datetime',
        'proposed_ss'                    => 'float',
        'proposed_rop'                   => 'float',
        'proposed_max'                   => 'float',
        'effective_ss'                   => 'float',
        'effective_rop'                  => 'float',
        'effective_max'                  => 'float',
        'effective_max_before_capacity'  => 'float',
        'reviewed_at'                    => 'datetime',
        'snapshot_mu_daily'              => 'float',
        'snapshot_sigma_daily'           => 'float',
        'snapshot_z_score'               => 'float',
        'snapshot_lead_time_std'         => 'float',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class, 'forecast_run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'parameter_id');
    }

    // ── Scope Helpers ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ACTIVE', 'APPROVED']);
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'PENDING_REVIEW');
    }

    /**
     * Apakah parameter ini dapat dipakai untuk trigger PR?
     */
    public function isEffective(): bool
    {
        return in_array($this->status, ['ACTIVE', 'APPROVED'], true)
            && $this->effective_rop !== null
            && $this->effective_max !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockMovement — Ledger append-only gerakan stok per SKU.
 *
 * Sebelumnya: StockTransaction (tabel: stock_transactions).
 * Sekarang   : StockMovement   (tabel: stock_movements).
 *
 * Kolom `reason` adalah kunci semantik untuk pipeline ML:
 *   ISSUE            → demand pelanggan (dihitung sebagai issued_qty di daily_demand)
 *   RECEIPT          → barang masuk dari supplier
 *   RETURN           → barang dikembalikan ke gudang
 *   ADJUSTMENT       → koreksi opname/rusak (TIDAK dihitung sebagai demand)
 *   OPENING_BALANCE  → saldo awal SKU (satu kali, saat SKU pertama kali dibuat)
 *
 * Kolom `type` (IN|OUT) masih ada untuk backward compat (direname dari enum lama).
 * Gunakan `reason` untuk logika domain baru.
 *
 * Kolom `movement_date` menggantikan `transaction_date` (via rename migrasi).
 * Kolom `qty` menggantikan `quantity` (via rename migrasi, DECIMAL(12,3)).
 */
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'item_id',
        'user_id',
        'type',
        'reason',
        'qty',
        'stock_before',
        'stock_after',
        'reference_no',
        'notes',
        'movement_date',
    ];

    protected $casts = [
        'qty'           => 'float',
        'stock_before'  => 'float',
        'stock_after'   => 'float',
        'movement_date' => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Label human-readable untuk tipe gerakan.
     * Menggunakan `reason` sebagai sumber semantik utama.
     */
    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'ISSUE'            => 'Pengeluaran (Demand)',
            'RECEIPT'          => 'Penerimaan Barang',
            'RETURN'           => 'Retur ke Gudang',
            'ADJUSTMENT'       => 'Penyesuaian Stok',
            'OPENING_BALANCE'  => 'Saldo Awal',
            default            => $this->reason,
        };
    }

    /**
     * Label backward-compat untuk type.
     * @deprecated Gunakan getReasonLabelAttribute() untuk logika domain baru.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'in'         => 'Stok Masuk',
            'out'        => 'Stok Keluar',
            'adjustment' => 'Penyesuaian',
            'IN'         => 'Stok Masuk',
            'OUT'        => 'Stok Keluar',
            default      => $this->type,
        };
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Hanya movement yang dihitung sebagai demand pelanggan */
    public function scopeIssues($query)
    {
        return $query->where('reason', 'ISSUE');
    }

    /** Hanya penerimaan barang */
    public function scopeReceipts($query)
    {
        return $query->where('reason', 'RECEIPT');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Item — Master SKU.
 *
 * Kolom `sku` adalah identifier utama (sebelumnya: `code`).
 *
 * Sumber kebenaran ROP/SS/MAX: tabel inventory_parameters (bukan kolom items langsung).
 * Gunakan relasi activeParameter() atau metode helper getEffectiveRop() untuk mengakses
 * parameter yang berlaku.
 *
 * @property-read InventoryParameter|null $activeParameter
 * @property-read float                   $inventoryPosition
 *
 * ── Kolom Deprecated (JANGAN dibaca untuk logika baru) ────────────────────────
 * Kolom-kolom berikut ada di DB untuk backward compat tetapi sudah digantikan:
 *   safety_stock, rop, max_stock  → gunakan activeParameter->effective_ss/rop/max
 *   avg_usage, planning_usage     → gunakan activeParameter->forecastRun->mu_daily
 *   cv_value, demand_type         → gunakan classification->cv2 / demand_pattern
 *   coverage_period               → gunakan config('inventory.target_cover_days')
 *   last_ml_update                → gunakan activeParameter->computed_at
 *   ml_rop, ml_safety_stock       → gunakan activeParameter->proposed_rop/ss
 *   is_manual_override            → gunakan activeParameter->status == APPROVED/REJECTED
 *   manual_rop, manual_safety_stock → gunakan activeParameter->proposed_rop/ss + review
 *   override_reason               → gunakan activeParameter->flag_reason
 *   override_updated_by           → gunakan activeParameter->reviewed_by
 *   override_updated_at           → gunakan activeParameter->reviewed_at
 * Lihat: docs/deprecated-columns.md
 */
class Item extends Model
{
    protected $fillable = [
        // ── Identitas ───────────────────────────────────────────
        'category_id',
        'warehouse_id',
        'supplier_id',      // retain, operasional
        'sku',              // sebelumnya: code
        'name',
        'unit',
        'description',      // retain, operasional

        // ── Stok (ledger ringkasan, sumber detail: stock_movements) ─
        'stock_on_hand',
        'stock_on_order',
        'stock_reserved',

        // ── Parameter ML domain baru ─────────────────────────────
        'unit_cost',
        'volume_m3',
        'moq',
        'lot_size',
        'lead_time_days',
        'lead_time_std_days',
        'first_movement_date',

        'is_active',

        // ── Deprecated (nullable, retain untuk backward compat) ──
        // Tidak diisi oleh kode baru. Lihat docs/deprecated-columns.md
        'coverage_period',
        'safety_stock',
        'rop',
        'max_stock',
        'avg_usage',
        'planning_usage',
        'cv_value',
        'demand_type',
        'last_ml_update',
        'ml_safety_stock',
        'ml_rop',
        'is_manual_override',
        'manual_safety_stock',
        'manual_rop',
        'override_reason',
        'override_updated_by',
        'override_updated_at',
    ];

    protected $casts = [
        'stock_on_hand'       => 'float',
        'stock_on_order'      => 'float',
        'stock_reserved'      => 'float',
        'unit_cost'           => 'float',
        'volume_m3'           => 'float',
        'moq'                 => 'float',
        'lot_size'            => 'float',
        'is_active'           => 'boolean',
        'first_movement_date' => 'date',

        // Deprecated — cast retained agar kode lama tidak crash saat membaca
        'safety_stock'        => 'float',
        'rop'                 => 'float',
        'max_stock'           => 'float',
        'avg_usage'           => 'float',
        'planning_usage'      => 'float',
        'cv_value'            => 'float',
        'is_manual_override'  => 'boolean',
        'manual_safety_stock' => 'float',
        'manual_rop'          => 'float',
        'last_ml_update'      => 'datetime',
        'override_updated_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @deprecated Retain untuk fitur operasional lama */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Sumber kebenaran parameter ROP/SS/MAX yang berlaku.
     * Hanya satu record ACTIVE atau APPROVED per item pada satu waktu.
     */
    public function activeParameter(): HasOne
    {
        return $this->hasOne(InventoryParameter::class)
                    ->whereIn('status', ['ACTIVE', 'APPROVED'])
                    ->latestOfMany('computed_at');
    }

    public function inventoryParameters(): HasMany
    {
        return $this->hasMany(InventoryParameter::class);
    }

    /** Gerakan stok (ledger sumber kebenaran) */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function classification(): HasOne
    {
        return $this->hasOne(ItemClassification::class);
    }

    public function dailyDemands(): HasMany
    {
        return $this->hasMany(DailyDemand::class);
    }

    public function forecastRuns(): HasMany
    {
        return $this->hasMany(ForecastRun::class);
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class);
    }

    /** @deprecated Gunakan movements() */
    public function transactions(): HasMany
    {
        return $this->movements();
    }

    public function monthlyUsages(): HasMany
    {
        return $this->hasMany(MonthlyUsage::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @deprecated Gunakan activeParameter->reviewer */
    public function overrideUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_updated_by');
    }

    // ── Computed Accessors ────────────────────────────────────────────────────

    /**
     * Inventory Position = OnHand + OnOrder - Reserved.
     * Dipakai untuk membandingkan dengan effective_rop di PR trigger.
     */
    public function getInventoryPositionAttribute(): float
    {
        return $this->stock_on_hand + $this->stock_on_order - $this->stock_reserved;
    }

    /**
     * Status stok berdasarkan activeParameter.
     * Jika activeParameter belum dimuat, fallback ke deprecated kolom.
     */
    public function getStockStatusAttribute(): string
    {
        $param = $this->relationLoaded('activeParameter')
            ? $this->activeParameter
            : null;

        $effectiveSS  = $param?->effective_ss  ?? (float) ($this->safety_stock ?? 0);
        $effectiveRop = $param?->effective_rop ?? (float) ($this->rop ?? 0);

        if ($this->stock_on_hand <= 0)                   return 'out_of_stock';
        if ($this->stock_on_hand <= $effectiveSS)        return 'critical';
        if ($this->inventory_position <= $effectiveRop)  return 'low';
        return 'normal';
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * SKU yang inventory_position <= effective_rop dari inventory_parameters.
     * Digunakan untuk menentukan reorder list. Memerlukan JOIN ke tabel baru.
     */
    public function scopeNeedsReorder($query)
    {
        return $query->whereExists(function ($sub) {
            $sub->select(\DB::raw(1))
                ->from('inventory_parameters as ip')
                ->whereColumn('ip.item_id', 'items.id')
                ->whereIn('ip.status', ['ACTIVE', 'APPROVED'])
                ->whereRaw('(items.stock_on_hand + items.stock_on_order - items.stock_reserved) <= ip.effective_rop')
                ->whereRaw('ip.effective_rop > 0');
        });
    }

    /**
     * @deprecated Pakai scopeNeedsReorder() untuk logika baru.
     * Retained untuk backward compat dengan controller lama yang belum dimigrasikan.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_on_hand <= rop AND stock_on_hand > 0');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_on_hand', '<=', 0);
    }

    /**
     * @deprecated Pakai scopeNeedsReorder() + activeParameter.
     */
    public function scopeCritical($query)
    {
        return $query->whereRaw('stock_on_hand <= safety_stock AND stock_on_hand > 0');
    }

    /**
     * Model boot validation logic:
     * - moq >= 1
     * - lot_size >= 1
     * - moq must be a multiple of lot_size
     * - volume_m3 > 0
     */
    protected static function booted(): void
    {
        static::saving(function (Item $item) {
            if ($item->moq !== null && $item->moq < 1) {
                throw new \InvalidArgumentException("moq must be >= 1, got {$item->moq}");
            }
            if ($item->lot_size !== null && $item->lot_size < 1) {
                throw new \InvalidArgumentException("lot_size must be >= 1, got {$item->lot_size}");
            }
            if ($item->moq !== null && $item->lot_size !== null && $item->lot_size > 0) {
                $rem = fmod((float)$item->moq, (float)$item->lot_size);
                if (abs($rem) > 1e-5 && abs($rem - (float)$item->lot_size) > 1e-5) {
                    throw new \InvalidArgumentException("moq ({$item->moq}) must be a multiple of lot_size ({$item->lot_size})");
                }
            }
            if ($item->volume_m3 !== null && $item->volume_m3 <= 0) {
                throw new \InvalidArgumentException("volume_m3 must be > 0, got {$item->volume_m3}");
            }
        });
    }
}

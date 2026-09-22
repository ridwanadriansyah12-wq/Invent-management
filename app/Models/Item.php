<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'category_id', 'supplier_id', 'code', 'name', 'unit', 'description',
        'stock_on_hand', 'stock_on_order', 'stock_reserved',
        'lead_time_days', 'coverage_period',
        'safety_stock', 'rop', 'max_stock',
        'avg_usage', 'planning_usage', 'cv_value', 'demand_type',
        'last_ml_update', 'is_active',
        'ml_safety_stock', 'ml_rop',
        'is_manual_override', 'manual_safety_stock', 'manual_rop',
        'override_reason', 'override_updated_by', 'override_updated_at',
    ];

    protected $casts = [
        'stock_on_hand'        => 'float',
        'stock_on_order'       => 'float',
        'stock_reserved'       => 'float',
        'safety_stock'         => 'float',
        'rop'                  => 'float',
        'max_stock'            => 'float',
        'avg_usage'            => 'float',
        'planning_usage'       => 'float',
        'cv_value'             => 'float',
        'is_active'            => 'boolean',
        'last_ml_update'       => 'datetime',
        'ml_safety_stock'      => 'float',
        'ml_rop'               => 'float',
        'is_manual_override'   => 'boolean',
        'manual_safety_stock'  => 'float',
        'manual_rop'           => 'float',
        'override_updated_at'  => 'datetime',
    ];

    // ── Computed Accessors & Priority Logic ─────────────────────────────────

    /** Priority: Manual Override > ML Output */
    public function getSafetyStockAttribute($value): float
    {
        if ($this->is_manual_override && $this->manual_safety_stock !== null) {
            return (float) $this->manual_safety_stock;
        }
        return (float) ($value ?? $this->ml_safety_stock ?? 0);
    }

    /** Priority: Manual Override > ML Output */
    public function getRopAttribute($value): float
    {
        if ($this->is_manual_override && $this->manual_rop !== null) {
            return (float) $this->manual_rop;
        }
        return (float) ($value ?? $this->ml_rop ?? 0);
    }

    /** Inventory Position = OnHand + OnOrder - Reserved */
    public function getInventoryPositionAttribute(): float
    {
        return $this->stock_on_hand + $this->stock_on_order - $this->stock_reserved;
    }

    /** Coverage Days = (OnHand / PlanningUsage) * 30 */
    public function getCoverageDaysAttribute(): float
    {
        if ($this->planning_usage <= 0) return 0;
        return round(($this->stock_on_hand / $this->planning_usage) * 30, 1);
    }

    /** Days to ROP = MAX(0, (OnHand - ROP) / PlanningUsage * 30) */
    public function getDaysToRopAttribute(): float
    {
        if ($this->planning_usage <= 0) return 0;
        return max(0, round((($this->stock_on_hand - $this->rop) / $this->planning_usage) * 30, 1));
    }

    /** Stock status label */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock_on_hand <= 0)              return 'out_of_stock';
        if ($this->stock_on_hand <= $this->safety_stock) return 'critical';
        if ($this->inventory_position <= $this->rop) return 'low';
        return 'normal';
    }

    /** Recommended order quantity */
    public function getRecommendedOrderQtyAttribute(): float
    {
        $ip = $this->inventory_position;
        return max(0, $this->max_stock - $ip);
    }

    // ── Relations ────────────────────────────────────────────────────────────
    public function category()  { return $this->belongsTo(Category::class); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }

    public function transactions()
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function monthlyUsages()
    {
        return $this->hasMany(MonthlyUsage::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function overrideUser()
    {
        return $this->belongsTo(User::class, 'override_updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)       { return $query->where('is_active', true); }
    public function scopeLowStock($query)     { return $query->whereRaw('stock_on_hand <= rop AND stock_on_hand > 0'); }
    public function scopeOutOfStock($query)   { return $query->where('stock_on_hand', '<=', 0); }
    public function scopeCritical($query)     { return $query->whereRaw('stock_on_hand <= safety_stock AND stock_on_hand > 0'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ForecastRun — Riwayat setiap run forecasting per SKU (append-only).
 *
 * Ditulis oleh Python FastAPI setiap run harian.
 * Laravel membaca baris terbaru dengan status='SUCCESS' untuk Tahap 4 (SS/ROP/MAX).
 *
 * Idempotency: run_id (UUID dari Laravel) memastikan request ulang tidak
 * menggandakan baris (Python mengabaikan run_id yang sudah ada).
 */
class ForecastRun extends Model
{
    protected $fillable = [
        'run_id',
        'item_id',
        'run_at',
        'mu_daily',
        'sigma_daily',
        'model_used',
        'demand_pattern_used',
        'backtest_mae',
        'backtest_rmse',
        'backtest_mase',
        'baseline_mase',
        'status',
        'error_message',
    ];

    protected $casts = [
        'run_at'              => 'datetime',
        'mu_daily'            => 'float',
        'sigma_daily'         => 'float',
        'backtest_mae'        => 'float',
        'backtest_rmse'       => 'float',
        'backtest_mase'       => 'float',
        'baseline_mase'       => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Apakah output run ini valid untuk dipakai Tahap 4?
     * mu_daily dan sigma_daily harus ada, positif, dan finite.
     */
    public function isValidForCalculation(): bool
    {
        if ($this->status !== 'SUCCESS') {
            return false;
        }

        foreach (['mu_daily', 'sigma_daily'] as $field) {
            $val = $this->{$field};
            if ($val === null || !is_finite((float) $val) || $val < 0) {
                return false;
            }
        }

        return true;
    }
}

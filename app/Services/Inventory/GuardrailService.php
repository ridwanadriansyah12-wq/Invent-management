<?php

namespace App\Services\Inventory;

use App\Models\CategoryDefault;
use App\Models\InventoryParameter;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GuardrailService — Tahap 5: Filter Keamanan & Clamping Parameter Persediaan.
 *
 * Menerapkan prinsip zero-trust terhadap output Machine Learning:
 * 1. Validasi Output ML (Finite, non-negative, ketersediaan FastAPI) -> Fallback jika gagal.
 * 2. Umur SKU (< 30 hari sejak first_movement_date) -> Wajib STATIC_CATEGORY.
 * 3. Clamping (SKU >= 30 hari dari jalur ML):
 *    - Batas deviasi [0.5 * baseline, 1.5 * baseline].
 *    - Jika melanggar: clamp nilai ROP, status = PENDING_REVIEW, flag = ROP_DEVIATION_GT_50PCT.
 * 4. Supersede Lifecycle: Parameter lama otomatis SUPERSEDED saat parameter baru ACTIVE/APPROVED.
 */
class GuardrailService
{
    public function __construct(
        protected ParameterCalculator $calculator,
        protected ?\App\Services\Settings\SettingsService $settings = null
    ) {
        $this->settings = $this->settings ?? app(\App\Services\Settings\SettingsService::class);
    }

    /**
     * Proses evaluasi guardrail untuk satu SKU dan persist ke inventory_parameters.
     *
     * @param Item $item Model Item dengan relasi category dan activeParameter
     * @param array{mu_daily: float, sigma_daily: float, forecast_run_id?: int|null}|null $forecastOutput Hasil forecast dari Python
     * @param Carbon|null $asOfDate Tanggal acuan evaluasi
     * @return InventoryParameter Parameter yang baru dibuat
     */
    public function processItem(
        Item $item,
        ?array $forecastOutput = null,
        ?Carbon $asOfDate = null
    ): InventoryParameter {
        $now = $asOfDate ?? Carbon::now();
        $minHistoryDays = (int) $this->settings->get('min_history_days_ml', 30);
        $leadTime = (float) ($item->lead_time_days ?? $this->settings->get('lead_time_days_default', 15));
        $leadTimeStd = (float) ($item->lead_time_std_days ?? $this->settings->get('lead_time_std_days_default', 0.0));
        $abcClass = $item->classification?->abc_class ?? 'C';

        // ── 1. Cek Umur SKU ──────────────────────────────────────────────────
        $skuAgeDays = $this->calculateSkuAgeDays($item, $now);

        if ($skuAgeDays === null || $skuAgeDays < $minHistoryDays) {
            return $this->applyStaticCategoryRoute($item, $now, "SKU age ({$skuAgeDays}d) < {$minHistoryDays}d threshold");
        }

        // ── 2. Validasi Output ML ────────────────────────────────────────────
        $isMlValid = $this->validateMlOutput($forecastOutput);

        if (!$isMlValid) {
            return $this->applyFallbackRoute($item, $now, 'ML_INVALID_OR_UNAVAILABLE');
        }

        $muDaily = (float) $forecastOutput['mu_daily'];
        $sigmaDaily = (float) $forecastOutput['sigma_daily'];
        $forecastRunId = $forecastOutput['forecast_run_id'] ?? null;

        // ── 3. Hitung Nilai Usulan (Proposed) dari ML ─────────────────────────
        $calcResult = $this->calculator->calculate(
            muDaily: $muDaily,
            sigmaDaily: $sigmaDaily,
            leadTimeDays: $leadTime,
            leadTimeStdDays: $leadTimeStd,
            abcClass: $abcClass
        );

        $proposedSS = $calcResult['proposed_ss'];
        $proposedRop = $calcResult['proposed_rop'];
        $proposedMax = $calcResult['proposed_max'];
        $qTarget = $calcResult['q_target'];
        $zScore = $calcResult['z_score'];

        // ── 4. Clamping Evaluasi ─────────────────────────────────────────────
        $baselineRop = $this->resolveBaselineRop($item, $now);
        $clampPct = (float) $this->settings->get('clamp_pct', 0.50);
        $lowerBound = max(0.0, (1.0 - $clampPct) * $baselineRop);
        $upperBound = (1.0 + $clampPct) * $baselineRop;

        $isClamped = false;
        $flagReason = null;
        $status = 'ACTIVE';

        if ($proposedRop < $lowerBound) {
            $isClamped = true;
            $effectiveRop = (int) ceil($lowerBound);
            $status = 'PENDING_REVIEW';
            $flagReason = 'ROP_DEVIATION_GT_50PCT';
        } elseif ($proposedRop > $upperBound) {
            $isClamped = true;
            $effectiveRop = (int) ceil($upperBound);
            $status = 'PENDING_REVIEW';
            $flagReason = 'ROP_DEVIATION_GT_50PCT';
        } else {
            $effectiveRop = $proposedRop;
        }

        if ($isClamped) {
            // effective_ss = max(0, effective_rop - mu_daily x LT)
            $effectiveSS = (int) ceil(max(0.0, $effectiveRop - ($muDaily * $leadTime)));
            // effective_max = effective_rop + Q_target
            $effectiveMax = (int) ceil($effectiveRop + $qTarget);
        } else {
            $effectiveSS = $proposedSS;
            $effectiveMax = $proposedMax;
        }

        return DB::transaction(function () use (
            $item, $now, $forecastRunId, $proposedSS, $proposedRop, $proposedMax,
            $effectiveSS, $effectiveRop, $effectiveMax, $status, $flagReason,
            $muDaily, $sigmaDaily, $zScore, $leadTime, $leadTimeStd
        ) {
            $param = InventoryParameter::create([
                'item_id'                        => $item->id,
                'computed_at'                    => $now,
                'source'                         => 'ML',
                'forecast_run_id'                => $forecastRunId,
                'proposed_ss'                    => $proposedSS,
                'proposed_rop'                   => $proposedRop,
                'proposed_max'                   => $proposedMax,
                'effective_ss'                   => $effectiveSS,
                'effective_rop'                  => $effectiveRop,
                'effective_max'                  => $effectiveMax,
                'effective_max_before_capacity'  => $effectiveMax,
                'status'                         => $status,
                'flag_reason'                    => $flagReason,
                'snapshot_mu_daily'              => $muDaily,
                'snapshot_sigma_daily'           => $sigmaDaily,
                'snapshot_z_score'               => $zScore,
                'snapshot_lead_time_days'        => (int) round($leadTime),
                'snapshot_lead_time_std'         => $leadTimeStd,
            ]);

            // Jika status langsung ACTIVE, superseded parameter sebelumnya
            if ($status === 'ACTIVE') {
                $this->supersedePreviousParameters($item->id, $param->id);
            }

            return $param;
        });
    }

    /**
     * Hitung umur SKU dalam hari sejak first_movement_date.
     */
    public function calculateSkuAgeDays(Item $item, Carbon $asOfDate): ?int
    {
        if (!$item->first_movement_date) {
            return null;
        }

        $firstDate = Carbon::parse($item->first_movement_date)->startOfDay();
        $targetDate = $asOfDate->copy()->startOfDay();

        if ($targetDate->lt($firstDate)) {
            return 0;
        }

        return (int) $firstDate->diffInDays($targetDate);
    }

    /**
     * Validasi finiteness dan non-negativity dari hasil ML.
     */
    protected function validateMlOutput(?array $output): bool
    {
        if ($output === null) {
            return false;
        }

        if (!isset($output['mu_daily']) || !isset($output['sigma_daily'])) {
            return false;
        }

        $mu = (float) $output['mu_daily'];
        $sigma = (float) $output['sigma_daily'];

        return is_finite($mu) && $mu >= 0.0 && is_finite($sigma) && $sigma >= 0.0;
    }

    /**
     * Jalur STATIC_CATEGORY untuk SKU muda (< 30 hari) atau fallback pertama kali.
     */
    public function applyStaticCategoryRoute(Item $item, Carbon $asOfDate, ?string $reason = null): InventoryParameter
    {
        $defaults = CategoryDefault::where('category_id', $item->category_id)->first();

        $avgDailyDemand = (float) ($defaults?->avg_daily_demand ?? 1.0);
        $safetyDays = (float) ($defaults?->safety_days ?? 7.0);
        $leadTime = (float) ($item->lead_time_days ?? $this->settings->get('lead_time_days_default', 15));
        $abcClass = $item->classification?->abc_class ?? 'C';
        $coverDays = $this->settings->getCoverDaysForClass($abcClass);

        // Rumus SRS Tahap 5.2:
        // ROP = avg_daily_demand x LT + (avg_daily_demand x safety_days)
        // SS  = avg_daily_demand x safety_days
        // MAX = ROP + avg_daily_demand x target_cover_days
        $ss = (int) ceil(max(0.0, $avgDailyDemand * $safetyDays));
        $rop = (int) ceil(max(0.0, ($avgDailyDemand * $leadTime) + $ss));
        $maxStock = (int) ceil(max(0.0, $rop + ($avgDailyDemand * $coverDays)));

        return DB::transaction(function () use ($item, $asOfDate, $ss, $rop, $maxStock, $reason, $leadTime, $avgDailyDemand) {
            $param = InventoryParameter::create([
                'item_id'                        => $item->id,
                'computed_at'                    => $asOfDate,
                'source'                         => 'STATIC_CATEGORY',
                'proposed_ss'                    => $ss,
                'proposed_rop'                   => $rop,
                'proposed_max'                   => $maxStock,
                'effective_ss'                   => $ss,
                'effective_rop'                  => $rop,
                'effective_max'                  => $maxStock,
                'effective_max_before_capacity'  => $maxStock,
                'status'                         => 'ACTIVE',
                'flag_reason'                    => $reason,
                'snapshot_mu_daily'              => $avgDailyDemand,
                'snapshot_sigma_daily'           => 0.0,
                'snapshot_z_score'               => 0.0,
                'snapshot_lead_time_days'        => (int) round($leadTime),
                'snapshot_lead_time_std'         => 0.0,
            ]);

            $this->supersedePreviousParameters($item->id, $param->id);

            return $param;
        });
    }

    /**
     * Jalur Fallback saat output ML tidak valid / microservice down.
     */
    protected function applyFallbackRoute(Item $item, Carbon $asOfDate, string $flagReason): InventoryParameter
    {
        $lastApproved = InventoryParameter::where('item_id', $item->id)
            ->whereIn('status', ['ACTIVE', 'APPROVED'])
            ->latest('computed_at')
            ->first();

        if (!$lastApproved) {
            return $this->applyStaticCategoryRoute($item, $asOfDate, 'FALLBACK_TO_STATIC_CATEGORY_DUE_TO_NO_PREVIOUS_PARAM');
        }

        return DB::transaction(function () use ($item, $asOfDate, $lastApproved, $flagReason) {
            $param = InventoryParameter::create([
                'item_id'                        => $item->id,
                'computed_at'                    => $asOfDate,
                'source'                         => 'FALLBACK_LAST_APPROVED',
                'forecast_run_id'                => $lastApproved->forecast_run_id,
                'proposed_ss'                    => $lastApproved->effective_ss,
                'proposed_rop'                   => $lastApproved->effective_rop,
                'proposed_max'                   => $lastApproved->effective_max,
                'effective_ss'                   => $lastApproved->effective_ss,
                'effective_rop'                  => $lastApproved->effective_rop,
                'effective_max'                  => $lastApproved->effective_max,
                'effective_max_before_capacity'  => $lastApproved->effective_max,
                'status'                         => 'ACTIVE',
                'flag_reason'                    => $flagReason,
                'snapshot_mu_daily'              => $lastApproved->snapshot_mu_daily,
                'snapshot_sigma_daily'           => $lastApproved->snapshot_sigma_daily,
                'snapshot_z_score'               => $lastApproved->snapshot_z_score,
                'snapshot_lead_time_days'        => $lastApproved->snapshot_lead_time_days,
                'snapshot_lead_time_std'         => $lastApproved->snapshot_lead_time_std,
            ]);

            $this->supersedePreviousParameters($item->id, $param->id);

            return $param;
        });
    }

    /**
     * Dapatkan baseline ROP dari rata-rata 30 hari terakhir.
     * Jika tidak ada riwayat cukup, fallback ke ROP statis kategori.
     */
    public function resolveBaselineRop(Item $item, Carbon $asOfDate): float
    {
        $thirtyDaysAgo = $asOfDate->copy()->subDays(30);

        $avgRop = InventoryParameter::where('item_id', $item->id)
            ->whereIn('status', ['ACTIVE', 'APPROVED', 'SUPERSEDED'])
            ->where('computed_at', '>=', $thirtyDaysAgo)
            ->avg('effective_rop');

        if ($avgRop !== null && (float) $avgRop > 0) {
            return (float) $avgRop;
        }

        // Fallback: hitung statis kategori
        $defaults = CategoryDefault::where('category_id', $item->category_id)->first();
        $avgDailyDemand = (float) ($defaults?->avg_daily_demand ?? 1.0);
        $safetyDays = (float) ($defaults?->safety_days ?? 7.0);
        $leadTime = (float) ($item->lead_time_days ?? $this->settings->get('lead_time_days_default', 15));

        $staticRop = ceil(($avgDailyDemand * $leadTime) + ($avgDailyDemand * $safetyDays));

        return max(1.0, (float) $staticRop);
    }

    /**
     * Ubah status record parameter aktif sebelumnya menjadi SUPERSEDED.
     */
    public function supersedePreviousParameters(int $itemId, int $excludeId): int
    {
        return InventoryParameter::where('item_id', $itemId)
            ->where('id', '!=', $excludeId)
            ->whereIn('status', ['ACTIVE', 'APPROVED'])
            ->update(['status' => 'SUPERSEDED']);
    }

    /**
     * Approve manual override parameter PENDING_REVIEW oleh Procurement/Gudang.
     */
    public function approveReview(InventoryParameter $param, int $userId, ?string $notes = null): InventoryParameter
    {
        return DB::transaction(function () use ($param, $userId, $notes) {
            $param->update([
                'effective_ss'   => $param->proposed_ss,
                'effective_rop'  => $param->proposed_rop,
                'effective_max'  => $param->proposed_max,
                'status'         => 'APPROVED',
                'reviewed_by'    => $userId,
                'reviewed_at'    => Carbon::now(),
                'review_notes'   => $notes,
            ]);

            $this->supersedePreviousParameters($param->item_id, $param->id);

            return $param;
        });
    }

    /**
     * Reject manual override parameter PENDING_REVIEW (tetap memakai nilai clamped).
     */
    public function rejectReview(InventoryParameter $param, int $userId, ?string $notes = null): InventoryParameter
    {
        return DB::transaction(function () use ($param, $userId, $notes) {
            $param->update([
                'status'       => 'REJECTED',
                'reviewed_by'  => $userId,
                'reviewed_at'  => Carbon::now(),
                'review_notes' => $notes,
            ]);

            return $param;
        });
    }
}

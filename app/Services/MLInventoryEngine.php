<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MonthlyUsage;
use Carbon\Carbon;

/**
 * ML Inventory Engine
 *
 * Implements Dynamic ROP & Safety Stock calculation using
 * Coefficient of Variation (CV) for demand classification.
 *
 * Formulas:
 *   AvgUsage     = TotalOUT12M / 12
 *   RecentUsage  = Recent3Months / 3
 *   CV           = StdDev(monthly_out) / AvgUsage
 *   DemandType   = CV > 0.5 ? intermittent : regular
 *   PlanningUsage = regular   → AvgUsage
 *                 = intermittent → MAX(AvgUsage, RecentUsage)
 *   SS           = (MaxUsage - AvgUsage) × LeadTime/30
 *   LTD          = PlanningUsage × LeadTime/30
 *   ROP          = LTD + SS
 *   MAX          = PlanningUsage × Coverage + SS
 */
class MLInventoryEngine
{
    public function __construct(
        private InventoryParameterService $paramService
    ) {}

    /**
     * Full recalculation pipeline for a single item.
     * Called after every transaction and on daily schedule.
     */
    public function recalculate(Item $item): void
    {
        $usages = $this->getMonthlyUsages($item);

        if ($usages->isEmpty()) {
            // No usage history yet — set defaults
            $defaultData = [
                'avg_usage'       => 0,
                'planning_usage'  => 0,
                'cv_value'        => 0,
                'demand_type'     => 'regular',
                'ml_safety_stock' => 0,
                'ml_rop'          => 0,
                'max_stock'       => max($item->stock_on_hand, 0),
                'last_ml_update'  => now(),
            ];

            if (!$item->is_manual_override) {
                $defaultData['safety_stock'] = 0;
                $defaultData['rop']          = 0;
            }

            $item->update($defaultData);
            $this->paramService->cacheParameters($item->fresh());
            return;
        }

        // ── Step 1: Average Usage (12-month) ─────────────────────────────
        $totalOut12M = $usages->sum('total_out');
        $monthCount  = $usages->count();
        $avgUsage    = $monthCount > 0 ? $totalOut12M / 12 : 0; // always divide by 12

        // ── Step 2: Recent Usage (last 3 months) ──────────────────────────
        $recentUsages = $usages->sortByDesc(function ($u) {
            return $u->year * 100 + $u->month;
        })->take(3);
        $recentUsage  = $recentUsages->count() > 0
            ? $recentUsages->sum('total_out') / 3
            : $avgUsage;

        // ── Step 3: Max Monthly Usage ─────────────────────────────────────
        $maxMonthlyUsage = $usages->max('total_out') ?? 0;

        // ── Step 4: CV = StdDev / AvgUsage ───────────────────────────────
        $cv = $this->calculateCV($usages->pluck('total_out')->toArray(), $avgUsage);

        // ── Step 5: Demand Classification ────────────────────────────────
        $demandType    = $cv > 0.5 ? 'intermittent' : 'regular';
        $planningUsage = $demandType === 'intermittent'
            ? max($avgUsage, $recentUsage)
            : $avgUsage;

        // Avoid division by zero — use at least a tiny value
        $planningUsage = max($planningUsage, 0.001);

        $leadTime = $item->lead_time_days;
        $coverage = $item->coverage_period;

        // ── Step 6: Safety Stock ─────────────────────────────────────────
        $ss = max(0, ($maxMonthlyUsage - $avgUsage) * ($leadTime / 30));

        // ── Step 7: Lead Time Demand ──────────────────────────────────────
        $ltd = $planningUsage * ($leadTime / 30);

        // ── Step 8: ROP ───────────────────────────────────────────────────
        $rop = $ltd + $ss;

        // ── Step 9: Max Stock ─────────────────────────────────────────────
        $maxStock = $planningUsage * $coverage + $ss;

        // ── Persist ───────────────────────────────────────────────────────
        $updateData = [
            'avg_usage'       => round($avgUsage, 4),
            'planning_usage'  => round($planningUsage, 4),
            'cv_value'        => round($cv, 4),
            'demand_type'     => $demandType,
            'ml_safety_stock' => round($ss, 2),
            'ml_rop'          => round($rop, 2),
            'max_stock'       => round($maxStock, 2),
            'last_ml_update'  => now(),
        ];

        // Jika tidak di-override oleh user, update juga nilai aktif yang digunakan sistem
        if (!$item->is_manual_override) {
            $updateData['safety_stock'] = round($ss, 2);
            $updateData['rop']          = round($rop, 2);
        }

        $item->update($updateData);

        // Sync ke Redis Hash In-Memory
        $this->paramService->cacheParameters($item->fresh());
    }

    /**
     * Update the monthly usage aggregate when a stock-out occurs.
     * Should be called immediately after recording an 'out' transaction.
     */
    public function updateMonthlyAggregate(Item $item, float $qty, ?Carbon $date = null): void
    {
        $date  = $date ?? now();
        $year  = (int) $date->format('Y');
        $month = (int) $date->format('n');

        $record = MonthlyUsage::firstOrCreate(
            ['item_id' => $item->id, 'year' => $year, 'month' => $month],
            ['total_out' => 0, 'max_daily_out' => 0]
        );

        $record->total_out     += $qty;
        $record->max_daily_out = max($record->max_daily_out, $qty);
        $record->save();
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Fetch last 12 months of monthly usages for an item.
     */
    private function getMonthlyUsages(Item $item)
    {
        $cutoff = now()->subMonths(12);

        return $item->monthlyUsages()
            ->where(function ($q) use ($cutoff) {
                $q->where('year', '>', $cutoff->year)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->where('year', $cutoff->year)
                         ->where('month', '>=', $cutoff->month);
                  });
            })
            ->get();
    }

    /**
     * Calculate Coefficient of Variation.
     * CV = StdDev / Mean
     */
    private function calculateCV(array $values, float $mean): float
    {
        if ($mean <= 0 || count($values) < 2) return 0;

        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values);
        $stdDev   = sqrt($variance);

        return $stdDev / $mean;
    }
}

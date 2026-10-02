<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventoryHealthController extends Controller
{
    /**
     * Tampilkan Diagnostik & Analisis Kesehatan Persediaan.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'stockout');
        $warehouses = Warehouse::orderBy('name')->get();

        $items = Item::with(['activeParameter.forecastRun', 'classification', 'category', 'warehouse'])
            ->where('is_active', true)
            ->get();

        // 1. Potensi Stockout & SKU Kritis (Inv Position <= ROP)
        $stockoutSkus = $items->filter(function ($i) {
            $rop = (float) ($i->activeParameter?->effective_rop ?? 0);
            return $rop > 0 && (float)$i->inventory_position <= $rop;
        })->map(function ($i) {
            $mu = (float) ($i->activeParameter?->forecastRun?->mu_daily ?? 0);
            $onHand = (float) $i->stock_on_hand;
            $daysOfCover = $mu > 0 ? ($onHand / $mu) : null;
            return [
                'item'          => $i,
                'on_hand'       => $onHand,
                'rop'           => (float) $i->activeParameter->effective_rop,
                'mu'            => $mu,
                'days_of_cover' => $daysOfCover,
                'lead_time'     => (int) $i->lead_time_days,
                'ratio'         => $i->activeParameter->effective_rop > 0 ? ($i->inventory_position / $i->activeParameter->effective_rop) : 1,
            ];
        })->sortBy('ratio');

        // 2. Overstock / Modal Mengendap (Stok On-Hand > MAX)
        $overstockSkus = $items->filter(function ($i) {
            $max = (float) ($i->activeParameter?->effective_max ?? 0);
            return $max > 0 && (float)$i->stock_on_hand > $max;
        })->map(function ($i) {
            $onHand = (float) $i->stock_on_hand;
            $max = (float) $i->activeParameter->effective_max;
            $excessQty = $onHand - $max;
            $excessValue = $excessQty * (float) $i->unit_cost;
            return [
                'item'         => $i,
                'on_hand'      => $onHand,
                'max'          => $max,
                'excess_qty'   => $excessQty,
                'excess_value' => $excessValue,
            ];
        })->sortByDesc('excess_value');

        $totalOverstockValue = $overstockSkus->sum('excess_value');

        // 3. Slow Moving / Dead Stock (Tidak ada transaksi ISSUE dalam 30 hari terakhir)
        $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateString();
        $recentIssueItemIds = StockMovement::where('reason', 'ISSUE')
            ->whereDate('movement_date', '>=', $thirtyDaysAgo)
            ->distinct()
            ->pluck('item_id')
            ->toArray();

        $slowMovingSkus = $items->filter(function ($i) use ($recentIssueItemIds) {
            return (float)$i->stock_on_hand > 0 && !in_array($i->id, $recentIssueItemIds, true);
        })->map(function ($i) {
            $tiedCapital = (float)$i->stock_on_hand * (float)$i->unit_cost;
            return [
                'item'         => $i,
                'on_hand'      => (float) $i->stock_on_hand,
                'tied_capital' => $tiedCapital,
                'last_issue'   => StockMovement::where('item_id', $i->id)
                    ->where('reason', 'ISSUE')
                    ->orderByDesc('movement_date')
                    ->value('movement_date'),
            ];
        })->sortByDesc('tied_capital');

        $totalDeadStockCapital = $slowMovingSkus->sum('tied_capital');

        // 4. SKU Tanpa Parameter Aktif
        $unparameterizedSkus = $items->filter(function ($i) {
            return $i->activeParameter === null;
        });

        return view('inventory.health', compact(
            'tab',
            'stockoutSkus',
            'overstockSkus',
            'totalOverstockValue',
            'slowMovingSkus',
            'totalDeadStockCapital',
            'unparameterizedSkus',
            'warehouses'
        ));
    }

    /**
     * Simulator Kebijakan Persediaan Interaktif (What-If Analysis).
     */
    public function simulator(Request $request)
    {
        $items = Item::with(['activeParameter.forecastRun', 'category', 'warehouse'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedItem = null;
        if ($itemId = $request->get('item_id')) {
            $selectedItem = $items->firstWhere('id', (int) $itemId);
        }

        return view('inventory.simulator', compact('items', 'selectedItem'));
    }
}

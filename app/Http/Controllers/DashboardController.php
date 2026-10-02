<?php

namespace App\Http\Controllers;

use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Models\SystemAlert;
use App\Models\Warehouse;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    public function index(Request $request)
    {
        // ── 1. KPI Metrik Utama ──────────────────────────────────────────────
        $totalActiveSkus = Item::where('is_active', true)->count();

        // Nilai persediaan (Rp) = SUM(stock_on_hand * unit_cost)
        $inventoryValue = (float) Item::where('is_active', true)
            ->sum(DB::raw('stock_on_hand * unit_cost'));

        // Volume terpakai (m³) = SUM(stock_on_hand * volume_m3)
        $usedVolume = (float) Item::where('is_active', true)
            ->sum(DB::raw('stock_on_hand * volume_m3'));

        $totalWarehouseCapacity = (float) Warehouse::sum('capacity_m3');
        $volumeUtilizationPct = $totalWarehouseCapacity > 0
            ? ($usedVolume / $totalWarehouseCapacity) * 100
            : 0;

        // Subquery outstanding PR (status OPEN atau ORDERED) untuk menghitung inventory_position
        $prOutstandingSubquery = 'COALESCE((
            SELECT SUM(pr.q_final) FROM purchase_requisitions pr
            WHERE pr.item_id = items.id AND pr.status IN (\'OPEN\', \'ORDERED\')
        ), 0)';

        // Ambil ID parameter aktif terbaru per item
        $activeParamSubquery = 'SELECT ip_sub.id FROM inventory_parameters ip_sub
            WHERE ip_sub.item_id = items.id
              AND ip_sub.status IN (\'ACTIVE\', \'APPROVED\')
            ORDER BY ip_sub.computed_at DESC, ip_sub.id DESC
            LIMIT 1';

        // KPI: SKU di bawah ROP (inventory_position <= effective_rop DAN effective_rop > 0)
        $skusBelowRopCount = Item::where('is_active', true)
            ->whereExists(function ($query) use ($activeParamSubquery, $prOutstandingSubquery) {
                $query->select(DB::raw(1))
                    ->from('inventory_parameters as ip')
                    ->whereRaw("ip.id = ({$activeParamSubquery})")
                    ->where('ip.effective_rop', '>', 0)
                    ->whereRaw("(items.stock_on_hand + {$prOutstandingSubquery}) <= ip.effective_rop");
            })
            ->count();

        // KPI: PR OPEN
        $openPrCount = PurchaseRequisition::where('status', 'OPEN')->count();

        // KPI: Pending Review (ROP deviasi > 50%)
        $pendingReviewsCount = InventoryParameter::where('status', 'PENDING_REVIEW')->count();

        // KPI: Alert belum resolved
        $unresolvedAlertsCount = SystemAlert::whereNull('resolved_at')->count();

        // ── 2. Data Grafik Utilisasi Gudang (Chart.js Bar) ─────────────────────
        $maxUtilizationRate = (float) $this->settings->get('max_warehouse_utilization', 0.85);
        $warehouses = Warehouse::all();

        $warehouseChartData = [
            'labels'             => [],
            'currentVolumes'     => [],
            'plannedMaxVolumes'  => [],
            'capacityLimits'     => [],
            'rawCapacities'      => [],
        ];

        foreach ($warehouses as $wh) {
            $warehouseChartData['labels'][] = $wh->name;
            $rawCap = (float) $wh->capacity_m3;
            $warehouseChartData['rawCapacities'][] = $rawCap;
            $warehouseChartData['capacityLimits'][] = round($rawCap * $maxUtilizationRate, 2);

            // Volume saat ini (on_hand * volume_m3)
            $currVol = (float) Item::where('warehouse_id', $wh->id)
                ->where('is_active', true)
                ->sum(DB::raw('stock_on_hand * volume_m3'));
            $warehouseChartData['currentVolumes'][] = round($currVol, 3);

            // Volume rencana pada MAX (effective_max * volume_m3)
            $planVol = (float) DB::table('items')
                ->join('inventory_parameters as ip', function ($join) use ($activeParamSubquery) {
                    $join->on('ip.id', '=', DB::raw("({$activeParamSubquery})"));
                })
                ->where('items.warehouse_id', $wh->id)
                ->where('items.is_active', true)
                ->sum(DB::raw('ip.effective_max * items.volume_m3'));
            $warehouseChartData['plannedMaxVolumes'][] = round($planVol, 3);
        }

        // ── 3. Distribusi ABC-XYZ & Pola Permintaan ─────────────────────────
        $abcXyzMatrix = [
            'A' => ['X' => 0, 'Y' => 0, 'Z' => 0, 'total' => 0],
            'B' => ['X' => 0, 'Y' => 0, 'Z' => 0, 'total' => 0],
            'C' => ['X' => 0, 'Y' => 0, 'Z' => 0, 'total' => 0],
        ];

        $matrixCounts = DB::table('item_classifications')
            ->join('items', 'items.id', '=', 'item_classifications.item_id')
            ->where('items.is_active', true)
            ->select('abc_class', 'xyz_class', DB::raw('count(*) as count'))
            ->groupBy('abc_class', 'xyz_class')
            ->get();

        foreach ($matrixCounts as $row) {
            $abc = strtoupper($row->abc_class ?? 'C');
            $xyz = strtoupper($row->xyz_class ?? 'Z');
            if (isset($abcXyzMatrix[$abc][$xyz])) {
                $abcXyzMatrix[$abc][$xyz] = (int) $row->count;
                $abcXyzMatrix[$abc]['total'] += (int) $row->count;
            }
        }

        $demandPatterns = [
            'smooth'       => 0,
            'intermittent' => 0,
            'erratic'      => 0,
            'lumpy'        => 0,
        ];

        $patternCounts = DB::table('item_classifications')
            ->join('items', 'items.id', '=', 'item_classifications.item_id')
            ->where('items.is_active', true)
            ->select('demand_pattern', DB::raw('count(*) as count'))
            ->groupBy('demand_pattern')
            ->get();

        foreach ($patternCounts as $row) {
            $pat = strtolower($row->demand_pattern ?? 'smooth');
            if (array_key_exists($pat, $demandPatterns)) {
                $demandPatterns[$pat] = (int) $row->count;
            }
        }

        // ── 4. Tabel 5 SKU Paling Kritis ──────────────────────────────────────
        // Hanya SKU aktif dengan parameter aktif (effective_rop > 0),
        // diurutkan berdasarkan rasio terkecil (inventory_position / effective_rop).
        $criticalSkus = Item::query()
            ->select('items.*')
            ->selectRaw("({$prOutstandingSubquery}) as outstanding_pr_qty")
            ->selectRaw("(items.stock_on_hand + {$prOutstandingSubquery}) as calculated_inventory_position")
            ->selectRaw("ip.effective_rop as param_effective_rop")
            ->selectRaw("ip.effective_ss as param_effective_ss")
            ->selectRaw("ip.status as param_status")
            ->selectRaw("ip.source as param_source")
            ->selectRaw("((items.stock_on_hand + {$prOutstandingSubquery}) / ip.effective_rop) as critical_ratio")
            ->join('inventory_parameters as ip', function ($join) use ($activeParamSubquery) {
                $join->on('ip.id', '=', DB::raw("({$activeParamSubquery})"));
            })
            ->where('items.is_active', true)
            ->where('ip.effective_rop', '>', 0)
            ->orderBy('critical_ratio', 'asc')
            ->limit(5)
            ->with(['category', 'warehouse'])
            ->get();

        return view('dashboard.index', compact(
            'totalActiveSkus',
            'inventoryValue',
            'usedVolume',
            'totalWarehouseCapacity',
            'volumeUtilizationPct',
            'skusBelowRopCount',
            'openPrCount',
            'pendingReviewsCount',
            'unresolvedAlertsCount',
            'warehouseChartData',
            'abcXyzMatrix',
            'demandPatterns',
            'criticalSkus'
        ));
    }
}

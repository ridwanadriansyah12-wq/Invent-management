<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DailyDemand;
use App\Models\ForecastRun;
use App\Models\InventoryParameter;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * Tampilkan daftar Master SKU dengan filter lengkap & proteksi performa.
     */
    public function index(Request $request)
    {
        $prOutstandingSubquery = 'COALESCE((
            SELECT SUM(pr.q_final) FROM purchase_requisitions pr
            WHERE pr.item_id = items.id AND pr.status IN (\'OPEN\', \'ORDERED\')
        ), 0)';

        $activeParamSubquery = 'SELECT ip_sub.id FROM inventory_parameters ip_sub
            WHERE ip_sub.item_id = items.id
              AND ip_sub.status IN (\'ACTIVE\', \'APPROVED\')
            ORDER BY ip_sub.computed_at DESC, ip_sub.id DESC
            LIMIT 1';

        $query = Item::query()
            ->select('items.*')
            ->selectRaw("({$prOutstandingSubquery}) as outstanding_pr_qty")
            ->selectRaw("(items.stock_on_hand + {$prOutstandingSubquery}) as inventory_position_calc")
            ->with(['category', 'warehouse', 'activeParameter', 'classification'])
            ->where('items.is_active', true);

        // 1. Filter Pencarian SKU / Nama
        if ($search = trim($request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('items.sku', 'like', "%{$search}%")
                  ->orWhere('items.name', 'like', "%{$search}%");
            });
        }

        // 2. Filter Kategori
        if ($catId = $request->get('category_id')) {
            $query->where('items.category_id', $catId);
        }

        // 3. Filter Gudang
        if ($whId = $request->get('warehouse_id')) {
            $query->where('items.warehouse_id', $whId);
        }

        // 4. Filter Kelas ABC
        if ($abc = $request->get('abc_class')) {
            $query->whereHas('classification', fn($q) => $q->where('abc_class', strtoupper($abc)));
        }

        // 5. Filter Kelas XYZ
        if ($xyz = $request->get('xyz_class')) {
            $query->whereHas('classification', fn($q) => $q->where('xyz_class', strtoupper($xyz)));
        }

        // 6. Filter Pola Permintaan
        if ($pattern = $request->get('demand_pattern')) {
            $query->whereHas('classification', fn($q) => $q->where('demand_pattern', strtolower($pattern)));
        }

        // 7. Filter Sumber Parameter
        if ($source = $request->get('source')) {
            $query->whereHas('activeParameter', fn($q) => $q->where('source', $source));
        }

        // 8. Filter Status Parameter
        if ($paramStatus = $request->get('param_status')) {
            if ($paramStatus === 'NONE') {
                $query->whereDoesntHave('activeParameter');
            } else {
                $query->whereHas('activeParameter', fn($q) => $q->where('status', $paramStatus));
            }
        }

        // 9. Filter Toggle "Di bawah ROP saja"
        if ($request->boolean('below_rop')) {
            $query->whereExists(function ($q) use ($activeParamSubquery, $prOutstandingSubquery) {
                $q->select(DB::raw(1))
                    ->from('inventory_parameters as ip')
                    ->whereRaw("ip.id = ({$activeParamSubquery})")
                    ->where('ip.effective_rop', '>', 0)
                    ->whereRaw("(items.stock_on_hand + {$prOutstandingSubquery}) <= ip.effective_rop");
            });
        }

        $items = $query->orderBy('items.name')->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('items.index', compact('items', 'categories', 'warehouses'));
    }

    /**
     * Tampilkan formulir pembuatan Master SKU baru (Khusus Admin).
     */
    public function create()
    {
        $this->authorize('manage-sku');

        $categories = Category::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('items.create', compact('categories', 'warehouses'));
    }

    /**
     * Simpan Master SKU baru ke basis data (Khusus Admin).
     */
    public function store(Request $request)
    {
        $this->authorize('manage-sku');

        $validator = Validator::make($request->all(), [
            'sku'                => ['required', 'string', 'max:50', 'unique:items,sku'],
            'name'               => ['required', 'string', 'max:200'],
            'category_id'        => ['required', 'exists:categories,id'],
            'warehouse_id'       => ['required', 'exists:warehouses,id'],
            'unit'               => ['required', 'string', 'max:30'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'stock_on_hand'      => ['required', 'numeric', 'min:0'],
            'unit_cost'          => ['required', 'numeric', 'min:0'],
            'volume_m3'          => ['required', 'numeric', 'gt:0'],
            'moq'                => ['required', 'numeric', 'gt:0'],
            'lot_size'           => ['required', 'numeric', 'gt:0'],
            'lead_time_days'     => ['required', 'integer', 'min:1', 'max:365'],
            'lead_time_std_days' => ['nullable', 'numeric', 'min:0', 'max:90'],
        ], [
            'sku.required'            => 'Kode SKU wajib diisi.',
            'sku.unique'              => 'Kode SKU ini sudah terdaftar.',
            'name.required'           => 'Nama barang wajib diisi.',
            'category_id.required'    => 'Kategori barang wajib dipilih.',
            'warehouse_id.required'   => 'Gudang penempatan wajib dipilih.',
            'unit.required'           => 'Satuan barang wajib diisi.',
            'stock_on_hand.min'       => 'Stok fisik tidak boleh bernilai negatif.',
            'unit_cost.min'           => 'Harga pokok satuan (Unit Cost) tidak boleh bernilai negatif.',
            'volume_m3.gt'            => 'Volume per unit harus lebih besar dari 0 m³.',
            'moq.gt'                  => 'MOQ (Minimum Order Quantity) harus lebih besar dari 0.',
            'lot_size.gt'             => 'Lot Size harus lebih besar dari 0.',
            'lead_time_days.min'      => 'Lead time minimal 1 hari.',
            'lead_time_days.max'      => 'Lead time maksimal 365 hari.',
        ]);

        // Validasi kelipatan Lot Size menggunakan bcmath sesuai spesifikasi
        $validator->after(function ($validator) use ($request) {
            $moq = $request->input('moq');
            $lotSize = $request->input('lot_size');

            if (is_numeric($moq) && is_numeric($lotSize) && (float)$lotSize > 0) {
                $mod = bcmod((string)$moq, (string)$lotSize, 4);
                if (bccomp($mod, '0', 4) !== 0) {
                    $validator->errors()->add('moq', "Nilai MOQ ({$moq}) harus merupakan kelipatan bulat dari Lot Size ({$lotSize}).");
                }
            }
        });

        $validated = $validator->validate();

        $item = DB::transaction(function () use ($validated) {
            $data = $validated;
            $data['is_active'] = true;
            $data['lead_time_std_days'] = $data['lead_time_std_days'] ?? 0.0;
            $data['first_movement_date'] = now()->toDateString();

            // ROP, SS, dan MAX TIDAK boleh diisi dari form ini
            unset($data['rop'], $data['safety_stock'], $data['max_stock'], $data['proposed_rop'], $data['effective_rop']);

            return Item::create($data);
        });

        return redirect()->route('items.show', $item)
            ->with('success', "Master SKU [{$item->sku}] {$item->name} berhasil ditambahkan. Parameter inventaris akan dihitung pada kalkulasi pipeline berikutnya.");
    }

    /**
     * Tampilkan detail SKU lengkap dengan 5 tab interaktif.
     */
    public function show(Request $request, Item $item)
    {
        $item->load([
            'category',
            'warehouse',
            'activeParameter.forecastRun',
            'classification',
        ]);

        // 1. Data Tab Permintaan (Histori Demand 90 hari terakhir)
        $dailyDemands = DailyDemand::where('item_id', $item->id)
            ->orderBy('date', 'asc')
            ->limit(90)
            ->get();

        // 2. Data Tab Parameter (Riwayat Inventory Parameters)
        $parametersHistory = InventoryParameter::where('item_id', $item->id)
            ->orderByDesc('computed_at')
            ->limit(30)
            ->get();

        // 3. Data Tab Model (Riwayat Forecast Runs)
        $forecastRuns = ForecastRun::where('item_id', $item->id)
            ->orderByDesc('run_at')
            ->limit(20)
            ->get();

        // 4. Data Tab Stok & Pergerakan (Riwayat Stock Movements dengan Filter)
        $movementsQuery = StockMovement::where('item_id', $item->id)
            ->with('user');

        if ($reason = $request->get('movement_reason')) {
            $movementsQuery->where('reason', $reason);
        }

        if ($startDate = $request->get('start_date')) {
            $movementsQuery->whereDate('movement_date', '>=', $startDate);
        }

        if ($endDate = $request->get('end_date')) {
            $movementsQuery->whereDate('movement_date', '<=', $endDate);
        }

        $stockMovements = $movementsQuery->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Hitung inventory position terkini
        $outstandingPrQty = (float) $item->purchaseRequisitions()
            ->whereIn('status', ['OPEN', 'ORDERED'])
            ->sum('q_final');
        $inventoryPosition = (float) $item->stock_on_hand + $outstandingPrQty;

        // Baseline ROP dan Pita Deviasi Clamping (±50%)
        $activeParam = $item->activeParameter;
        $baselineRop = $activeParam ? (float) ($activeParam->effective_rop) : 0;
        $clampLower = max(0, $baselineRop * 0.5);
        $clampUpper = $baselineRop * 1.5;

        return view('items.show', compact(
            'item',
            'dailyDemands',
            'parametersHistory',
            'forecastRuns',
            'stockMovements',
            'inventoryPosition',
            'outstandingPrQty',
            'baselineRop',
            'clampLower',
            'clampUpper'
        ));
    }

    /**
     * Tampilkan formulir edit Master SKU (Khusus Admin).
     */
    public function edit(Item $item)
    {
        $this->authorize('manage-sku');

        $categories = Category::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('items.edit', compact('item', 'categories', 'warehouses'));
    }

    /**
     * Simpan pembaruan Master SKU (Khusus Admin).
     */
    public function update(Request $request, Item $item)
    {
        $this->authorize('manage-sku');

        $validator = Validator::make($request->all(), [
            'sku'                => ['required', 'string', 'max:50', Rule::unique('items', 'sku')->ignore($item->id)],
            'name'               => ['required', 'string', 'max:200'],
            'category_id'        => ['required', 'exists:categories,id'],
            'warehouse_id'       => ['required', 'exists:warehouses,id'],
            'unit'               => ['required', 'string', 'max:30'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'unit_cost'          => ['required', 'numeric', 'min:0'],
            'volume_m3'          => ['required', 'numeric', 'gt:0'],
            'moq'                => ['required', 'numeric', 'gt:0'],
            'lot_size'           => ['required', 'numeric', 'gt:0'],
            'lead_time_days'     => ['required', 'integer', 'min:1', 'max:365'],
            'lead_time_std_days' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'is_active'          => ['nullable', 'boolean'],
        ], [
            'sku.required'            => 'Kode SKU wajib diisi.',
            'sku.unique'              => 'Kode SKU ini sudah digunakan oleh barang lain.',
            'name.required'           => 'Nama barang wajib diisi.',
            'category_id.required'    => 'Kategori barang wajib dipilih.',
            'warehouse_id.required'   => 'Gudang penempatan wajib dipilih.',
            'unit.required'           => 'Satuan barang wajib diisi.',
            'unit_cost.min'           => 'Harga pokok satuan (Unit Cost) tidak boleh bernilai negatif.',
            'volume_m3.gt'            => 'Volume per unit harus lebih besar dari 0 m³.',
            'moq.gt'                  => 'MOQ (Minimum Order Quantity) harus lebih besar dari 0.',
            'lot_size.gt'             => 'Lot Size harus lebih besar dari 0.',
            'lead_time_days.min'      => 'Lead time minimal 1 hari.',
            'lead_time_days.max'      => 'Lead time maksimal 365 hari.',
        ]);

        // Validasi kelipatan Lot Size menggunakan bcmath sesuai spesifikasi
        $validator->after(function ($validator) use ($request) {
            $moq = $request->input('moq');
            $lotSize = $request->input('lot_size');

            if (is_numeric($moq) && is_numeric($lotSize) && (float)$lotSize > 0) {
                $mod = bcmod((string)$moq, (string)$lotSize, 4);
                if (bccomp($mod, '0', 4) !== 0) {
                    $validator->errors()->add('moq', "Nilai MOQ ({$moq}) harus merupakan kelipatan bulat dari Lot Size ({$lotSize}).");
                }
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($item, $validated, $request) {
            $data = $validated;
            $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : $item->is_active;
            $data['lead_time_std_days'] = $data['lead_time_std_days'] ?? 0.0;

            // ROP, SS, dan MAX TIDAK BOLEH BISA DIUBAH DARI FORM INI
            unset($data['rop'], $data['safety_stock'], $data['max_stock'], $data['proposed_rop'], $data['effective_rop']);

            $item->update($data);
        });

        return redirect()->route('items.show', $item)
            ->with('success', "Master SKU [{$item->sku}] berhasil diperbarui. Perubahan parameter fisik akan berlaku pada kalkulasi pipeline berikutnya.");
    }

    /**
     * Nonaktifkan SKU (is_active = false) — Tanpa hapus permanen agar integritas riwayat terjaga.
     */
    public function destroy(Item $item)
    {
        $this->authorize('manage-sku');

        if ($item->stock_on_hand > 0) {
            return back()->with('error', "SKU [{$item->sku}] tidak dapat dinonaktifkan karena masih memiliki saldo stok fisik (" . format_number_id($item->stock_on_hand) . " {$item->unit}). Lakukan adjustment atau pengeluaran terlebih dahulu.");
        }

        $item->update(['is_active' => false]);

        return redirect()->route('items.index')
            ->with('success', "Master SKU [{$item->sku}] {$item->name} berhasil dinonaktifkan.");
    }
}

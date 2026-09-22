<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Supplier;
use App\Services\InventoryParameterService;
use App\Services\MLInventoryEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function __construct(
        private MLInventoryEngine $ml,
        private InventoryParameterService $paramService
    ) {}

    public function index(Request $request)
    {
        $query = Item::with(['category', 'supplier'])->active();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($cat = $request->get('category_id')) {
            $query->where('category_id', $cat);
        }

        if ($status = $request->get('status')) {
            match($status) {
                'out'      => $query->outOfStock(),
                'low'      => $query->lowStock(),
                'critical' => $query->critical(),
                'normal'   => $query->whereRaw('stock_on_hand > rop'),
                default    => null,
            };
        }

        // Quick filter: Hanya barang yang butuh reorder (Stok <= ROP)
        if ($request->boolean('reorder_only')) {
            $query->whereRaw('stock_on_hand <= rop AND rop > 0');
        }

        // Filter berdasarkan mode parameter: Manual Override vs ML Prediction
        if ($mode = $request->get('override_mode')) {
            if ($mode === 'manual') {
                $query->where('is_manual_override', true);
            } elseif ($mode === 'ml') {
                $query->where('is_manual_override', false);
            }
        }

        $items      = $query->orderBy('name')->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();

        // Metrik cepat untuk quick-filter tabs / counter pills
        $stats = [
            'total'          => Item::active()->count(),
            'reorder_needed' => Item::active()->whereRaw('stock_on_hand <= rop AND rop > 0')->count(),
            'critical'       => Item::active()->whereRaw('stock_on_hand <= safety_stock AND safety_stock > 0')->count(),
            'overridden'     => Item::active()->where('is_manual_override', true)->count(),
        ];

        return view('items.index', compact('items', 'categories', 'stats'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $suppliers  = Supplier::orderBy('name')->get();
        return view('items.create', compact('categories', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'     => ['required', 'exists:categories,id'],
            'supplier_id'     => ['nullable', 'exists:suppliers,id'],
            'code'            => ['required', 'string', 'max:50', 'unique:items,code'],
            'name'            => ['required', 'string', 'max:200'],
            'unit'            => ['required', 'string', 'max:30'],
            'description'     => ['nullable', 'string'],
            'stock_on_hand'   => ['required', 'numeric', 'min:0'],
            'lead_time_days'  => ['required', 'integer', 'min:1', 'max:365'],
            'coverage_period' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $item = Item::create($validated);

        // Initial ML calculation (no history yet, will use defaults)
        $this->ml->recalculate($item);

        return redirect()->route('items.show', $item)
            ->with('success', "Barang [{$item->code}] {$item->name} berhasil ditambahkan.");
    }

    public function show(Item $item)
    {
        $item->load(['category', 'supplier', 'overrideUser', 'monthlyUsages' => function ($q) {
            $q->orderBy('year')->orderBy('month');
        }]);

        $effectiveParams = $this->paramService->getEffectiveParameters($item->id);

        $recentTransactions = $item->transactions()
            ->with('user')
            ->orderByDesc('transaction_date')
            ->limit(10)->get();

        return view('items.show', compact('item', 'recentTransactions', 'effectiveParams'));
    }

    public function edit(Item $item)
    {
        $categories = Category::orderBy('name')->get();
        $suppliers  = Supplier::orderBy('name')->get();
        return view('items.edit', compact('item', 'categories', 'suppliers'));
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'category_id'     => ['required', 'exists:categories,id'],
            'supplier_id'     => ['nullable', 'exists:suppliers,id'],
            'code'            => ['required', 'string', 'max:50', "unique:items,code,{$item->id}"],
            'name'            => ['required', 'string', 'max:200'],
            'unit'            => ['required', 'string', 'max:30'],
            'description'     => ['nullable', 'string'],
            'lead_time_days'  => ['required', 'integer', 'min:1', 'max:365'],
            'coverage_period' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $item->update($validated);

        // Recalculate after parameter changes
        $this->ml->recalculate($item->fresh());

        return redirect()->route('items.show', $item)
            ->with('success', "Barang [{$item->code}] berhasil diperbarui.");
    }

    public function destroy(Item $item)
    {
        if ($item->stock_on_hand > 0) {
            return back()->with('error', 'Tidak bisa menghapus barang yang masih memiliki stok.');
        }

        $item->update(['is_active' => false]);

        return redirect()->route('items.index')
            ->with('success', "Barang [{$item->code}] {$item->name} berhasil dinonaktifkan.");
    }

    /**
     * Force ML recalculation for an item.
     */
    public function recalculate(Item $item)
    {
        $this->ml->recalculate($item->fresh());
        return back()->with('success', 'Kalkulasi ML berhasil diperbarui.');
    }

    /**
     * Terapkan Manual Override (Human-in-the-Loop)
     */
    public function overrideParameters(Request $request, Item $item)
    {
        $validated = $request->validate([
            'manual_rop'          => ['required', 'numeric', 'min:0'],
            'manual_safety_stock' => ['nullable', 'numeric', 'min:0'],
            'override_reason'     => ['required', 'string', 'max:255'],
        ]);

        $this->paramService->applyManualOverride(
            $item,
            (float) $validated['manual_rop'],
            isset($validated['manual_safety_stock']) && $validated['manual_safety_stock'] !== '' ? (float) $validated['manual_safety_stock'] : null,
            $validated['override_reason'],
            auth()->id()
        );

        $freshItem = $item->fresh();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Manual override berhasil diterapkan untuk [{$freshItem->code}].",
                'item'    => [
                    'id'                 => $freshItem->id,
                    'code'               => $freshItem->code,
                    'name'               => $freshItem->name,
                    'unit'               => $freshItem->unit,
                    'rop'                => (float) $freshItem->rop,
                    'safety_stock'       => (float) $freshItem->safety_stock,
                    'ml_rop'             => (float) $freshItem->ml_rop,
                    'ml_safety_stock'    => (float) $freshItem->ml_safety_stock,
                    'is_manual_override' => (bool) $freshItem->is_manual_override,
                    'override_reason'    => $freshItem->override_reason,
                    'stock_status'       => $freshItem->stock_status,
                    'is_reorder_needed'  => $freshItem->stock_on_hand <= $freshItem->rop,
                ],
            ]);
        }

        return back()->with('success', "Manual override berhasil diterapkan untuk [{$item->code}]. ROP Efektif sekarang: {$validated['manual_rop']} {$item->unit}.");
    }

    /**
     * Reset Manual Override ke kalkulasi Machine Learning
     */
    public function resetOverride(Request $request, Item $item)
    {
        $this->paramService->resetManualOverride($item);
        $freshItem = $item->fresh();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Manual override dicabut. [{$freshItem->code}] kembali mengikuti prediksi Machine Learning.",
                'item'    => [
                    'id'                 => $freshItem->id,
                    'code'               => $freshItem->code,
                    'name'               => $freshItem->name,
                    'unit'               => $freshItem->unit,
                    'rop'                => (float) $freshItem->rop,
                    'safety_stock'       => (float) $freshItem->safety_stock,
                    'ml_rop'             => (float) $freshItem->ml_rop,
                    'ml_safety_stock'    => (float) $freshItem->ml_safety_stock,
                    'is_manual_override' => (bool) $freshItem->is_manual_override,
                    'override_reason'    => null,
                    'stock_status'       => $freshItem->stock_status,
                    'is_reorder_needed'  => $freshItem->stock_on_hand <= $freshItem->rop,
                ],
            ]);
        }

        return back()->with('success', "Manual override dicabut. Parameter [{$item->code}] kembali mengikuti prediksi Machine Learning.");
    }

    /**
     * Import items from Excel/CSV (Deep Analysis Mode)
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            // Baca raw data semua sheet
            $sheets = \Maatwebsite\Excel\Facades\Excel::toArray(new \stdClass, $request->file('file'));
            
            $headerIndex = -1;
            $headerSheet = -1;
            $headers = [];

            // 1. Cari baris header di SEMUA sheet
            foreach ($sheets as $sIndex => $rows) {
                foreach ($rows as $rIndex => $row) {
                    $isHeader = false;
                    foreach ($row as $cell) {
                        $c = strtolower(trim((string)$cell));
                        if (str_contains($c, 'material') || str_contains($c, 'kode') || str_contains($c, 'code') || str_contains($c, 'item')) {
                            $isHeader = true;
                            break;
                        }
                    }

                    if ($isHeader) {
                        $headerSheet = $sIndex;
                        $headerIndex = $rIndex;
                        
                        // Map headers
                        foreach ($row as $i => $val) {
                            $slug = Str::slug((string)$val, '_');
                            if (str_contains($slug, 'material') && str_contains($slug, 'desc')) $slug = 'material_description';
                            elseif (str_contains($slug, 'material') || str_contains($slug, 'kode') || str_contains($slug, 'code')) $slug = 'material';
                            
                            if (str_contains($slug, 'physical_stock') || str_contains($slug, 'on_hand') || str_contains($slug, 'stok')) $slug = 'physical_stock_on_hand';
                            if (str_contains($slug, 'safety_stock') || str_contains($slug, 'min')) $slug = 'safety_stock_min';
                            if (str_contains($slug, 'rop') || str_contains($slug, 'reorder')) $slug = 'rop';
                            if (str_contains($slug, 'max')) $slug = 'max_stock';
                            
                            $headers[$i] = $slug;
                        }
                        break 2; // Keluar dari kedua loop
                    }
                }
            }

            if ($headerIndex === -1) {
                $preview = isset($sheets[0][0]) ? implode(' | ', $sheets[0][0]) : 'File Kosong';
                throw new \Exception("Gagal menemukan baris judul di semua sheet! Pastikan ada kata 'Material' atau 'Kode'. \nBaris 1 Sheet 1: [ " . $preview . " ]");
            }

            // 2. Proses data
            $importedCount = 0; $updatedCount = 0; $duplicateCount = 0; $errorCount = 0;
            $processedCodes = [];
            
            $defaultCatId = Category::firstOrCreate(['name' => 'Uncategorized'], ['description' => 'Imported'])->id;

            $dataRows = $sheets[$headerSheet];
            foreach ($dataRows as $rIndex => $row) {
                if ($rIndex <= $headerIndex) continue; // Skip header dan di atasnya

                $rowData = [];
                foreach ($row as $i => $val) {
                    if (isset($headers[$i]) && $headers[$i] !== '') {
                        $rowData[$headers[$i]] = $val;
                    }
                }

                $code = $rowData['material'] ?? null;
                if (empty($code)) {
                    if (!empty(array_filter($rowData))) $errorCount++;
                    continue;
                }

                if (in_array($code, $processedCodes)) {
                    $duplicateCount++;
                    continue;
                }
                $processedCodes[] = $code;

                $item = Item::firstOrNew(['code' => $code]);
                $isNew = !$item->exists;

                $item->category_id    = $defaultCatId;
                $item->name           = $rowData['material_description'] ?? ($item->name ?: 'Unknown Item');
                $item->unit           = $rowData['base_unit'] ?? ($item->unit ?: 'pcs');

                $item->stock_on_hand  = max(0, floatval($rowData['physical_stock_on_hand'] ?? 0));
                $item->stock_on_order = isset($rowData['on_order_po_running']) ? max(0, floatval($rowData['on_order_po_running'])) : $item->stock_on_order;
                $item->stock_reserved = isset($rowData['reserved_qty']) ? max(0, floatval($rowData['reserved_qty'])) : $item->stock_reserved;
                $item->safety_stock   = isset($rowData['safety_stock_min']) ? max(0, floatval($rowData['safety_stock_min'])) : $item->safety_stock;
                $item->rop            = isset($rowData['rop']) ? max(0, floatval($rowData['rop'])) : $item->rop;
                $item->max_stock      = isset($rowData['max_stock']) ? max(0, floatval($rowData['max_stock'])) : $item->max_stock;

                if ($isNew) {
                    $item->lead_time_days = 7;
                    $item->coverage_period = 30;
                    $importedCount++;
                } else {
                    $updatedCount++;
                }
                $item->save();
            }

            $msg = "Import Selesai! ";
            if ($importedCount > 0) $msg .= "{$importedCount} baru. ";
            if ($updatedCount > 0)  $msg .= "{$updatedCount} diupdate. ";
            if ($duplicateCount > 0)$msg .= "{$duplicateCount} ganda (skip). ";
            if ($errorCount > 0)    $msg .= "{$errorCount} error (skip).";

            return back()->with('success', trim($msg));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengimpor file: ' . $e->getMessage());
        }
    }
}

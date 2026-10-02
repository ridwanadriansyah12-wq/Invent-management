<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockMovementController extends Controller
{
    /**
     * Tampilkan buku besar pergerakan stok (Stock Movement Ledger).
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['item.category', 'item.warehouse', 'user'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        // Filter: Tipe (IN / OUT)
        if ($type = $request->get('type')) {
            $query->where('type', strtoupper($type));
        }

        // Filter: Alasan (Reason)
        if ($reason = $request->get('reason')) {
            $query->where('reason', $reason);
        }

        // Filter: Gudang
        if ($warehouseId = $request->get('warehouse_id')) {
            $query->whereHas('item', fn($q) => $q->where('warehouse_id', $warehouseId));
        }

        // Filter: Pencarian SKU atau Nama
        if ($search = $request->get('search')) {
            $query->whereHas('item', fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
            );
        }

        // Filter: Rentang Tanggal
        if ($startDate = $request->get('start_date')) {
            $query->whereDate('movement_date', '>=', $startDate);
        }
        if ($endDate = $request->get('end_date')) {
            $query->whereDate('movement_date', '<=', $endDate);
        }

        // Ringkasan Statistik 30 Hari Terakhir
        $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateString();

        $stats = [
            'total_movements_30d' => StockMovement::whereDate('movement_date', '>=', $thirtyDaysAgo)->count(),
            'total_receipt_qty'   => (float) StockMovement::whereDate('movement_date', '>=', $thirtyDaysAgo)
                ->where('type', 'IN')
                ->sum('qty'),
            'total_issue_qty'     => (float) StockMovement::whereDate('movement_date', '>=', $thirtyDaysAgo)
                ->where('type', 'OUT')
                ->sum('qty'),
            'total_adjustments'   => StockMovement::whereDate('movement_date', '>=', $thirtyDaysAgo)
                ->where('reason', 'ADJUSTMENT')
                ->count(),
        ];

        $movements = $query->paginate(25)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock-movements.index', compact('movements', 'stats', 'warehouses'));
    }

    /**
     * Form pencatatan mutasi stok baru.
     */
    public function create(Request $request)
    {
        $this->authorize('record-movement');

        $items = Item::with(['category', 'warehouse', 'activeParameter'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedItem = null;
        if ($itemId = $request->get('item_id')) {
            $selectedItem = $items->firstWhere('id', (int) $itemId);
        }

        return view('stock-movements.create', compact('items', 'selectedItem'));
    }

    /**
     * Simpan pergerakan stok dan perbarui stock_on_hand secara transaksional.
     */
    public function store(Request $request)
    {
        $this->authorize('record-movement');

        $validated = $request->validate([
            'item_id'       => ['required', 'exists:items,id'],
            'reason'        => ['required', 'in:RECEIPT,ISSUE,ADJUSTMENT,RETURN,OPENING_BALANCE'],
            'qty'           => ['required', 'numeric', 'gt:0'],
            'reference_no'  => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:500'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'adjustment_direction' => ['nullable', 'in:ADD,SUB'], // Khusus jika alasan ADJUSTMENT
        ], [
            'item_id.required'       => 'Pilih barang yang mengalami pergerakan stok.',
            'item_id.exists'         => 'Barang yang dipilih tidak ditemukan.',
            'reason.required'        => 'Pilih jenis transaksi/alasan pergerakan stok.',
            'qty.required'           => 'Jumlah (qty) pergerakan stok wajib diisi.',
            'qty.gt'                 => 'Jumlah (qty) harus lebih besar dari 0.',
            'movement_date.required' => 'Tanggal pergerakan stok wajib diisi.',
            'movement_date.before_or_equal' => 'Tanggal transaksi tidak boleh mendahului hari ini.',
        ]);

        // Tentukan arah type (IN atau OUT) berdasarkan semantik reason
        $reason = $validated['reason'];
        $qty = (float) $validated['qty'];

        if ($reason === 'ADJUSTMENT') {
            $direction = $request->input('adjustment_direction', 'ADD');
            $type = $direction === 'SUB' ? 'OUT' : 'IN';
        } elseif (in_array($reason, ['RECEIPT', 'RETURN', 'OPENING_BALANCE'], true)) {
            $type = 'IN';
        } else {
            // ISSUE
            $type = 'OUT';
        }

        return DB::transaction(function () use ($validated, $type, $reason, $qty) {
            /** @var Item $item */
            $item = Item::lockForUpdate()->findOrFail($validated['item_id']);
            $stockBefore = (float) $item->stock_on_hand;

            // Validasi stok cukup jika pengeluaran
            if ($type === 'OUT' && $qty > $stockBefore) {
                return back()->withInput()->with('error', "Stok tidak mencukupi untuk pengeluaran! Stok saat ini: " . number_format($stockBefore, 3, ',', '.') . " {$item->unit}, diminta keluar: " . number_format($qty, 3, ',', '.') . " {$item->unit}.");
            }

            $stockAfter = $type === 'IN' ? ($stockBefore + $qty) : ($stockBefore - $qty);

            // Guardrail data quality: tidak boleh negatif
            if ($stockAfter < 0) {
                Log::warning("StockMovement: Saldo negatif terdeteksi untuk SKU {$item->sku}, diset 0.");
                $stockAfter = 0;
            }

            // Catat record ledger StockMovement
            $movement = StockMovement::create([
                'item_id'       => $item->id,
                'user_id'       => auth()->id(),
                'type'          => $type,
                'reason'        => $reason,
                'qty'           => $qty,
                'stock_before'  => $stockBefore,
                'stock_after'   => $stockAfter,
                'reference_no'  => $validated['reference_no'] ?? null,
                'notes'         => $validated['notes'] ?? null,
                'movement_date' => $validated['movement_date'],
            ]);

            // Perbarui master stok on-hand di tabel items
            $item->stock_on_hand = $stockAfter;

            // Inisialisasi first_movement_date jika baru pertama kali bergerak
            if ($item->first_movement_date === null) {
                $item->first_movement_date = Carbon::parse($validated['movement_date'])->toDateString();
            }

            $item->save();

            $statusText = $type === 'IN' ? 'bertambah' : 'berkurang';
            $msg = "Gerakan stok untuk {$item->sku} berhasil dicatat ({$item->name}: {$statusText} " . number_format($qty, 3, ',', '.') . " {$item->unit}). Saldo baru: " . number_format($stockAfter, 3, ',', '.') . " {$item->unit}.";

            return redirect()->route('stock-movements.index')->with('success', $msg);
        });
    }

    /**
     * Unduh laporan pergerakan stok dalam format CSV.
     */
    public function export(Request $request)
    {
        return app(ReportController::class)->exportMovements($request);
    }
}

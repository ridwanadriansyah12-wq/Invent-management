<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockTransaction;
use App\Services\MLInventoryEngine;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(
        private MLInventoryEngine  $ml,
        private NotificationService $notifService
    ) {}

    public function index(Request $request)
    {
        $query = StockTransaction::with(['item', 'user'])->orderByDesc('transaction_date')->orderByDesc('id');

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->get('search')) {
            $query->whereHas('item', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        if ($date = $request->get('date')) {
            $query->whereDate('transaction_date', $date);
        }

        $transactions = $query->paginate(25)->withQueryString();

        return view('transactions.index', compact('transactions'));
    }

    public function create(Request $request)
    {
        $items = Item::active()->with('category')->orderBy('name')->get();
        $selectedItem = $request->get('item_id') ? Item::find($request->get('item_id')) : null;
        return view('transactions.create', compact('items', 'selectedItem'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id'          => ['required', 'exists:items,id'],
            'type'             => ['required', 'in:in,out,adjustment'],
            'quantity'         => ['required', 'numeric', 'min:0.01'],
            'reference_no'     => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $item = Item::lockForUpdate()->findOrFail($validated['item_id']);

        // Validate out quantity doesn't exceed stock
        if ($validated['type'] === 'out' && $validated['quantity'] > $item->stock_on_hand) {
            return back()->withInput()
                ->with('error', "Stok tidak mencukupi. Stok saat ini: {$item->stock_on_hand} {$item->unit}");
        }

        DB::transaction(function () use ($validated, $item) {
            $stockBefore = $item->stock_on_hand;

            $newStock = match($validated['type']) {
                'in'         => $stockBefore + $validated['quantity'],
                'out'        => $stockBefore - $validated['quantity'],
                'adjustment' => $validated['quantity'], // set to exact value
            };

            // Record transaction
            StockTransaction::create([
                'item_id'          => $item->id,
                'user_id'          => auth()->id(),
                'type'             => $validated['type'],
                'quantity'         => $validated['quantity'],
                'stock_before'     => $stockBefore,
                'stock_after'      => $newStock,
                'reference_no'     => $validated['reference_no'],
                'notes'            => $validated['notes'],
                'transaction_date' => $validated['transaction_date'],
            ]);

            // Update stock
            $item->update(['stock_on_hand' => max(0, $newStock)]);

            // Update monthly aggregate for 'out' type
            if ($validated['type'] === 'out') {
                $this->ml->updateMonthlyAggregate(
                    $item,
                    $validated['quantity'],
                    Carbon::parse($validated['transaction_date'])
                );
            }

            // ML Recalculation
            $this->ml->recalculate($item->fresh());

            // Check notifications
            $this->notifService->checkAndNotify($item->fresh());
        });

        return redirect()->route('transactions.index')
            ->with('success', 'Transaksi berhasil dicatat.');
    }
}

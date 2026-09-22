<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\MLInventoryEngine;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private MLInventoryEngine   $ml,
        private NotificationService $notifService
    ) {}

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['item', 'supplier', 'user'])->orderByDesc('order_date');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $pos = $query->paginate(20)->withQueryString();
        return view('purchase-orders.index', compact('pos'));
    }

    public function create(Request $request)
    {
        $items     = Item::active()->with('supplier')->orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $preItem   = $request->get('item_id') ? Item::with('supplier')->find($request->get('item_id')) : null;

        return view('purchase-orders.create', compact('items', 'suppliers', 'preItem'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id'       => ['required', 'exists:items,id'],
            'supplier_id'   => ['nullable', 'exists:suppliers,id'],
            'quantity'      => ['required', 'numeric', 'min:0.01'],
            'order_date'    => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes'         => ['nullable', 'string'],
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Generate PO number
        $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(
            PurchaseOrder::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
        );

        $po = PurchaseOrder::create([
            ...$validated,
            'po_number'   => $poNumber,
            'user_id'     => auth()->id(),
            'supplier_id' => $validated['supplier_id'] ?? $item->supplier_id,
            'status'      => 'pending',
        ]);

        // Update stock on order
        $item->increment('stock_on_order', $validated['quantity']);

        return redirect()->route('purchase-orders.show', $po)
            ->with('success', "PO {$poNumber} berhasil dibuat.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['item', 'supplier', 'user']);
        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return back()->with('error', 'PO ini sudah selesai atau dibatalkan.');
        }

        $validated = $request->validate([
            'quantity_received' => ['required', 'numeric', 'min:0.01',
                'max:' . ($purchaseOrder->quantity - $purchaseOrder->quantity_received)],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $purchaseOrder) {
            $item    = Item::lockForUpdate()->findOrFail($purchaseOrder->item_id);
            $qtyRecv = $validated['quantity_received'];

            $purchaseOrder->increment('quantity_received', $qtyRecv);

            $newStatus = $purchaseOrder->quantity_received >= $purchaseOrder->quantity
                ? 'received' : 'partial';

            $purchaseOrder->update([
                'status'        => $newStatus,
                'received_date' => $newStatus === 'received' ? today() : null,
                'notes'         => $validated['notes'] ?? $purchaseOrder->notes,
            ]);

            // Update item stock
            $item->increment('stock_on_hand', $qtyRecv);
            $item->decrement('stock_on_order', $qtyRecv);

            // ML Recalculation
            $this->ml->recalculate($item->fresh());
            $this->notifService->checkAndNotify($item->fresh());
            $this->notifService->notifyPOReceived(
                $purchaseOrder->po_number,
                $item->name,
                $qtyRecv,
                $item->unit
            );
        });

        return back()->with('success', 'Penerimaan PO berhasil dicatat.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'Hanya PO berstatus Menunggu yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($purchaseOrder) {
            $remaining = $purchaseOrder->quantity - $purchaseOrder->quantity_received;
            $purchaseOrder->item->decrement('stock_on_order', $remaining);
            $purchaseOrder->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'PO berhasil dibatalkan.');
    }
}

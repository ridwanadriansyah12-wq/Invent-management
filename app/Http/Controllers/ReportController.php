<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function exportStock()
    {
        $items = Item::with(['category', 'supplier', 'activeParameter'])
            ->active()
            ->orderBy('name')
            ->get();

        $filename = 'laporan_stok_' . date('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($items) {
            $fp = fopen('php://output', 'w');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($fp, [
                'SKU', 'Nama', 'Kategori', 'Supplier', 'Satuan',
                'Stok', 'Safety Stock', 'ROP', 'Max Stok',
                'mu_daily (forecast)', 'Lead Time (hari)', 'MOQ', 'Lot Size',
                'Volume/unit (m³)', 'IP', 'Status', 'Sumber Parameter',
            ]);

            foreach ($items as $item) {
                $param = $item->activeParameter;
                fputcsv($fp, [
                    $item->sku,
                    $item->name,
                    $item->category?->name ?? '-',
                    $item->supplier?->name ?? '-',
                    $item->unit,
                    $item->stock_on_hand,
                    $param?->effective_ss  ?? '-',
                    $param?->effective_rop ?? '-',
                    $param?->effective_max ?? '-',
                    $param?->forecastRun?->mu_daily ?? '-',
                    $item->lead_time_days,
                    $item->moq,
                    $item->lot_size,
                    $item->volume_m3,
                    $item->inventory_position,
                    $item->stock_status,
                    $param?->source ?? '-',
                ]);
            }

            fclose($fp);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportMovements(Request $request)
    {
        $query = StockMovement::with(['item', 'user'])
            ->orderBy('movement_date')->orderBy('id');

        if ($from = $request->get('from')) {
            $query->whereDate('movement_date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('movement_date', '<=', $to);
        }
        if ($reason = $request->get('reason')) {
            $query->where('reason', $reason);
        }
        if ($type = $request->get('type')) {
            $query->where('type', strtoupper($type));
        }

        $movements = $query->get();

        $filename = 'laporan_gerakan_stok_' . date('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($movements) {
            $fp = fopen('php://output', 'w');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($fp, [
                'Tanggal', 'SKU', 'Nama Barang', 'Tipe', 'Alasan (Reason)',
                'Qty', 'Stok Sebelum', 'Stok Sesudah', 'Ref No', 'Catatan', 'User',
            ]);

            foreach ($movements as $m) {
                fputcsv($fp, [
                    $m->movement_date->format('d/m/Y'),
                    $m->item?->sku ?? '-',
                    $m->item?->name ?? '-',
                    $m->type,
                    $m->reason_label,
                    $m->qty,
                    $m->stock_before,
                    $m->stock_after,
                    $m->reference_no ?? '-',
                    $m->notes ?? '-',
                    $m->user?->name ?? '-',
                ]);
            }

            fclose($fp);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @deprecated Rute lama untuk backward compat. Gunakan exportMovements().
     */
    public function exportTransactions(Request $request)
    {
        return $this->exportMovements($request);
    }
}

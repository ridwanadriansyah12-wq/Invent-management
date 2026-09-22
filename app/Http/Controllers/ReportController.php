<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockTransaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function exportStock()
    {
        $items = Item::with(['category', 'supplier'])->active()->orderBy('name')->get();

        $filename = 'laporan_stok_' . date('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($items) {
            $fp = fopen('php://output', 'w');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($fp, [
                'Kode', 'Nama', 'Kategori', 'Supplier', 'Satuan',
                'Stok', 'Safety Stock', 'ROP', 'Max Stok',
                'Avg Usage/bln', 'Planning Usage/bln',
                'Demand Type', 'CV', 'Lead Time (hari)', 'Coverage (hari)',
                'IP', 'Coverage Days', 'Days to ROP', 'Status',
            ]);

            foreach ($items as $item) {
                fputcsv($fp, [
                    $item->code,
                    $item->name,
                    $item->category?->name ?? '-',
                    $item->supplier?->name ?? '-',
                    $item->unit,
                    $item->stock_on_hand,
                    $item->safety_stock,
                    $item->rop,
                    $item->max_stock,
                    $item->avg_usage,
                    $item->planning_usage,
                    $item->demand_type,
                    $item->cv_value,
                    $item->lead_time_days,
                    $item->coverage_period,
                    $item->inventory_position,
                    $item->coverage_days,
                    $item->days_to_rop,
                    $item->stock_status,
                ]);
            }

            fclose($fp);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportTransactions(Request $request)
    {
        $query = StockTransaction::with(['item', 'user'])
            ->orderBy('transaction_date')->orderBy('id');

        if ($from = $request->get('from')) $query->whereDate('transaction_date', '>=', $from);
        if ($to   = $request->get('to'))   $query->whereDate('transaction_date', '<=', $to);
        if ($type = $request->get('type'))  $query->where('type', $type);

        $transactions = $query->get();

        $filename = 'laporan_transaksi_' . date('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($transactions) {
            $fp = fopen('php://output', 'w');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($fp, ['Tanggal', 'Kode Barang', 'Nama Barang', 'Tipe', 'Qty', 'Stok Sebelum', 'Stok Sesudah', 'Ref No', 'Catatan', 'User']);

            foreach ($transactions as $t) {
                fputcsv($fp, [
                    $t->transaction_date->format('d/m/Y'),
                    $t->item?->code ?? '-',
                    $t->item?->name ?? '-',
                    $t->type_label,
                    $t->quantity,
                    $t->stock_before,
                    $t->stock_after,
                    $t->reference_no ?? '-',
                    $t->notes ?? '-',
                    $t->user?->name ?? '-',
                ]);
            }

            fclose($fp);
        };

        return response()->stream($callback, 200, $headers);
    }
}

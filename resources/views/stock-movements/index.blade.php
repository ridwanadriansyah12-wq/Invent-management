@extends('layouts.app', ['title' => 'Buku Besar Pergerakan Stok', 'header' => 'Buku Besar & Riwayat Mutasi Stok'])

@section('content')
<div class="space-y-6">

    <!-- ── Header Action Bar ─────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Pergerakan Stok (Stock Ledger)</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Catatan mutasi riil barang masuk, keluar (demand), penyesuaian opname, dan retur gudang.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('reports.export-transactions', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 shadow-sm transition-all">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Ekspor CSV
            </a>

            @can('record-movement')
            <a href="{{ route('stock-movements.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-950/20 transition-all focus:ring-2 focus:ring-emerald-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Catat Gerakan Stok
            </a>
            @endcan
        </div>
    </div>

    <!-- ── KPI Stat Cards 30 Hari ────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Transaksi -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Transaksi (30h)</span>
                <span class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ number_format($stats['total_movements_30d'], 0, ',', '.') }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">mutasi ledger dicatat</span>
            </div>
        </div>

        <!-- 2. Total Inbound (Barang Masuk) -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Barang Masuk (30h)</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                    +{{ number_format($stats['total_receipt_qty'], 2, ',', '.') }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">unit penerimaan supplier</span>
            </div>
        </div>

        <!-- 3. Total Outbound (Demand Pelanggan) -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Pengeluaran Demand (30h)</span>
                <span class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400">
                    -{{ number_format($stats['total_issue_qty'], 2, ',', '.') }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">unit konsumsi & penjualan</span>
            </div>
        </div>

        <!-- 4. Penyesuaian Opname -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Penyesuaian Opname</span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400">
                    {{ number_format($stats['total_adjustments'], 0, ',', '.') }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">koreksi fisik / kerusakan</span>
            </div>
        </div>
    </div>

    <!-- ── Filter & Search Panel ─────────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
        <form method="GET" action="{{ route('stock-movements.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">

                <!-- Pencarian SKU / Nama -->
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Cari SKU / Nama Barang</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Ketik SKU atau nama..."
                               class="w-full pl-9 pr-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Alasan Transaksi (Reason) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Alasan (Reason)</label>
                    <select name="reason" class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Semua Alasan</option>
                        <option value="RECEIPT" {{ request('reason') === 'RECEIPT' ? 'selected' : '' }}>📥 Penerimaan (Receipt)</option>
                        <option value="ISSUE" {{ request('reason') === 'ISSUE' ? 'selected' : '' }}>📤 Pengeluaran (Issue)</option>
                        <option value="ADJUSTMENT" {{ request('reason') === 'ADJUSTMENT' ? 'selected' : '' }}>⚖️ Penyesuaian (Adjustment)</option>
                        <option value="RETURN" {{ request('reason') === 'RETURN' ? 'selected' : '' }}>🔄 Retur Gudang (Return)</option>
                        <option value="OPENING_BALANCE" {{ request('reason') === 'OPENING_BALANCE' ? 'selected' : '' }}>🏁 Saldo Awal</option>
                    </select>
                </div>

                <!-- Gudang -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Gudang</label>
                    <select name="warehouse_id" class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Arah Tipe -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Arah Stok</label>
                    <select name="type" class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Semua Arah</option>
                        <option value="IN" {{ request('type') === 'IN' ? 'selected' : '' }}>Masuk (IN)</option>
                        <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>Keluar (OUT)</option>
                    </select>
                </div>
            </div>

            <!-- Rentang Tanggal & Tombol Aksi -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Dari:</span>
                    <input type="date" name="start_date" value="{{ request('start_date') }}"
                           class="px-2.5 py-1.5 rounded-lg text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <span class="text-xs text-slate-500 dark:text-slate-400">s/d:</span>
                    <input type="date" name="end_date" value="{{ request('end_date') }}"
                           class="px-2.5 py-1.5 rounded-lg text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div class="flex items-center gap-2">
                    @if(request()->hasAny(['search', 'reason', 'warehouse_id', 'type', 'start_date', 'end_date']))
                        <a href="{{ route('stock-movements.index') }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors">
                            Reset Filter
                        </a>
                    @endif
                    <button type="submit"
                            class="px-4 py-1.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-all focus:ring-2 focus:ring-emerald-400">
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── Tabel Buku Besar (Ledger Table) ───────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">No. Referensi</th>
                        <th class="px-4 py-3.5">Barang / SKU</th>
                        <th class="px-4 py-3.5">Jenis Mutasi</th>
                        <th class="px-4 py-3.5 text-right">Jumlah Qty</th>
                        <th class="px-4 py-3.5 text-right">Saldo Sebelum</th>
                        <th class="px-4 py-3.5 text-right">Saldo Sesudah</th>
                        <th class="px-4 py-3.5">Dicatat Oleh</th>
                        <th class="px-4 py-3.5">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($movements as $m)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                            <!-- Tanggal -->
                            <td class="px-4 py-3 whitespace-nowrap text-slate-700 dark:text-slate-300 font-medium">
                                {{ $m->movement_date->format('d/m/Y') }}
                                <span class="text-[10px] text-slate-400 block">{{ $m->created_at->format('H:i') }}</span>
                            </td>

                            <!-- Referensi -->
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-slate-600 dark:text-slate-300 font-medium">
                                {{ $m->reference_no ?: '-' }}
                            </td>

                            <!-- SKU & Nama Barang -->
                            <td class="px-4 py-3">
                                @if($m->item)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('items.show', $m->item) }}"
                                           class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                            {{ $m->item->sku }}
                                        </a>
                                        <span class="text-xs text-slate-700 dark:text-slate-200 font-medium line-clamp-1">
                                            {{ $m->item->name }}
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $m->item->warehouse?->name ?? 'Gudang' }} &bull; {{ $m->item->category?->name ?? 'Kategori' }}
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">Item tidak ditemukan</span>
                                @endif
                            </td>

                            <!-- Jenis Mutasi (Reason Badge) -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $reasonConfig = match($m->reason) {
                                        'RECEIPT'         => ['class' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800', 'icon' => '📥', 'label' => 'Penerimaan'],
                                        'ISSUE'           => ['class' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800', 'icon' => '📤', 'label' => 'Pengeluaran Demand'],
                                        'ADJUSTMENT'      => ['class' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800', 'icon' => '⚖️', 'label' => 'Penyesuaian Opname'],
                                        'RETURN'          => ['class' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800', 'icon' => '🔄', 'label' => 'Retur Masuk'],
                                        'OPENING_BALANCE' => ['class' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700', 'icon' => '🏁', 'label' => 'Saldo Awal'],
                                        default           => ['class' => 'bg-slate-100 text-slate-700 border-slate-200', 'icon' => '•', 'label' => $m->reason],
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $reasonConfig['class'] }}">
                                    <span>{{ $reasonConfig['icon'] }}</span>
                                    <span>{{ $reasonConfig['label'] }}</span>
                                </span>
                            </td>

                            <!-- Jumlah Qty -->
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono font-bold">
                                @if($m->type === 'IN')
                                    <span class="text-emerald-600 dark:text-emerald-400">
                                        +{{ number_format($m->qty, 3, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-rose-600 dark:text-rose-400">
                                        -{{ number_format($m->qty, 3, ',', '.') }}
                                    </span>
                                @endif
                                <span class="text-[10px] font-normal text-slate-400 ml-0.5">{{ $m->item?->unit }}</span>
                            </td>

                            <!-- Saldo Sebelum -->
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-slate-500 dark:text-slate-400">
                                {{ number_format($m->stock_before, 3, ',', '.') }}
                            </td>

                            <!-- Saldo Sesudah -->
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format($m->stock_after, 3, ',', '.') }}
                            </td>

                            <!-- Dicatat Oleh -->
                            <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                <div class="font-medium">{{ $m->user?->name ?? 'Sistem' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $m->user?->role_label ?? '-' }}</div>
                            </td>

                            <!-- Catatan -->
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 max-w-[200px] truncate" title="{{ $m->notes }}">
                                {{ $m->notes ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada catatan mutasi pergerakan stok</p>
                                <p class="text-xs text-slate-400 mt-1">Gunakan tombol "Catat Gerakan Stok" di atas untuk menambah transaksi pertama.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginasi -->
        @if($movements->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $movements->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

@extends('layouts.app', ['title' => 'Diagnostik Kesehatan Persediaan', 'header' => 'Diagnostik & Anomali Persediaan'])

@section('content')
<div class="space-y-6" x-data="{ activeTab: '{{ request('tab', 'stockout') }}' }">

    <!-- ── Header Action Bar ─────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Diagnostik Kesehatan Persediaan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Deteksi otomatis anomali operasional: potensi stockout, overstock (modal tertahan), barang mati (dead stock), dan SKU tanpa parameter.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('inventory.simulator') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-950/20 transition-all focus:ring-2 focus:ring-emerald-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Buka Simulator Kebijakan &rarr;
            </a>
        </div>
    </div>

    <!-- ── 4 Tab Diagnostik Kategori ─────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Tab 1: Kritis / Stockout -->
        <button type="button" @click="activeTab = 'stockout'"
                class="p-5 rounded-2xl border text-left transition-all cursor-pointer"
                :class="activeTab === 'stockout' ? 'bg-rose-50 dark:bg-rose-950/30 border-rose-500 ring-2 ring-rose-500/20 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300'">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">1. Potensi Stockout</span>
                <span class="p-2 rounded-xl bg-rose-100 dark:bg-rose-900/50 text-rose-600">🚨</span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ $stockoutSkus->count() }} <span class="text-sm font-normal text-slate-400">SKU</span>
                </span>
                <span class="text-xs text-rose-600 dark:text-rose-400 block mt-0.5 font-medium">Stok &le; ROP (Perlu Pesan)</span>
            </div>
        </button>

        <!-- Tab 2: Overstock -->
        <button type="button" @click="activeTab = 'overstock'"
                class="p-5 rounded-2xl border text-left transition-all cursor-pointer"
                :class="activeTab === 'overstock' ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-500 ring-2 ring-amber-500/20 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300'">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">2. Kelebihan Stok (Overstock)</span>
                <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600">📦</span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ $overstockSkus->count() }} <span class="text-sm font-normal text-slate-400">SKU</span>
                </span>
                <span class="text-xs text-amber-600 dark:text-amber-400 block mt-0.5 font-medium">
                    Rp {{ number_format($totalOverstockValue, 0, ',', '.') }} modal mengendap
                </span>
            </div>
        </button>

        <!-- Tab 3: Slow Moving / Dead Stock -->
        <button type="button" @click="activeTab = 'slowmoving'"
                class="p-5 rounded-2xl border text-left transition-all cursor-pointer"
                :class="activeTab === 'slowmoving' ? 'bg-blue-50 dark:bg-blue-950/30 border-blue-500 ring-2 ring-blue-500/20 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300'">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">3. Barang Mati (Dead Stock)</span>
                <span class="p-2 rounded-xl bg-blue-100 dark:bg-blue-900/50 text-blue-600">💤</span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ $slowMovingSkus->count() }} <span class="text-sm font-normal text-slate-400">SKU</span>
                </span>
                <span class="text-xs text-blue-600 dark:text-blue-400 block mt-0.5 font-medium">
                    Rp {{ number_format($totalDeadStockCapital, 0, ',', '.') }} modal tanpa gerak
                </span>
            </div>
        </button>

        <!-- Tab 4: Gap Parameter -->
        <button type="button" @click="activeTab = 'noparam'"
                class="p-5 rounded-2xl border text-left transition-all cursor-pointer"
                :class="activeTab === 'noparam' ? 'bg-purple-50 dark:bg-purple-950/30 border-purple-500 ring-2 ring-purple-500/20 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300'">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-400">4. Tanpa Parameter</span>
                <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-900/50 text-purple-600">⚠️</span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ $unparameterizedSkus->count() }} <span class="text-sm font-normal text-slate-400">SKU</span>
                </span>
                <span class="text-xs text-purple-600 dark:text-purple-400 block mt-0.5 font-medium">Belum diproses pipeline ROP</span>
            </div>
        </button>
    </div>

    <!-- ── Konten Masing-Masing Tab ──────────────────────────────────────── -->

    <!-- TAB 1: Potensi Stockout -->
    <div x-show="activeTab === 'stockout'" x-cloak class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                    SKU di Bawah Reorder Point (Risiko Stockout Terdekat)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    SKU dengan posisi persediaan (on-hand + on-order) &le; ROP. Diurutkan dari rasio paling darurat.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">SKU & Nama Barang</th>
                        <th class="px-4 py-3.5">Gudang</th>
                        <th class="px-4 py-3.5 text-right">Stok On Hand</th>
                        <th class="px-4 py-3.5 text-right">ROP</th>
                        <th class="px-4 py-3.5 text-right">Rasio IP / ROP</th>
                        <th class="px-4 py-3.5 text-right">Demand Harian (&mu;)</th>
                        <th class="px-4 py-3.5 text-right">Estimasi Hari Habis</th>
                        <th class="px-4 py-3.5 text-center">Lead Time</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($stockoutSkus as $row)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('items.show', $row['item']) }}"
                                   class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                    {{ $row['item']->sku }}
                                </a>
                                <div class="text-xs text-slate-700 dark:text-slate-200 font-medium line-clamp-1">
                                    {{ $row['item']->name }}
                                </div>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                {{ $row['item']->warehouse?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-rose-600 dark:text-rose-400">
                                {{ format_number_id($row['on_hand']) }} {{ $row['item']->unit }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ format_number_id($row['rop']) }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold">
                                <span class="px-2 py-0.5 rounded-full text-[11px] bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                    {{ number_format($row['ratio'] * 100, 1) }}%
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">
                                {{ $row['mu'] > 0 ? number_format($row['mu'], 2, ',', '.') : '-' }} /hari
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold">
                                @if($row['days_of_cover'] !== null)
                                    <span class="{{ $row['days_of_cover'] < $row['lead_time'] ? 'text-rose-600 font-extrabold' : 'text-amber-600' }}">
                                        ~{{ round($row['days_of_cover'], 1) }} hari
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center font-mono text-slate-600 dark:text-slate-300">
                                {{ $row['lead_time'] }} hari
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('items.show', $row['item']) }}"
                                   class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 transition-colors">
                                    Detail SKU &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                ✅ Luar biasa! Tidak ada SKU yang berada di bawah Reorder Point saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: Overstock -->
    <div x-show="activeTab === 'overstock'" x-cloak class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    SKU Melebihi Batas Maksimum (Overstock / Modal Mengendap)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Stok saat ini melebihi target MAX, memakan ruang gudang dan membekukan modal kerja.
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 block">Total Modal Tertahan:</span>
                <span class="text-base font-mono font-bold text-amber-600 dark:text-amber-400">
                    Rp {{ number_format($totalOverstockValue, 0, ',', '.') }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">SKU & Nama Barang</th>
                        <th class="px-4 py-3.5">Gudang</th>
                        <th class="px-4 py-3.5 text-right">Stok On Hand</th>
                        <th class="px-4 py-3.5 text-right">Target MAX</th>
                        <th class="px-4 py-3.5 text-right">Kelebihan Qty</th>
                        <th class="px-4 py-3.5 text-right">Harga Satuan (Cost)</th>
                        <th class="px-4 py-3.5 text-right">Modal Mengendap (Rp)</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($overstockSkus as $row)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('items.show', $row['item']) }}"
                                   class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                    {{ $row['item']->sku }}
                                </a>
                                <div class="text-xs text-slate-700 dark:text-slate-200 font-medium line-clamp-1">
                                    {{ $row['item']->name }}
                                </div>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                {{ $row['item']->warehouse?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ format_number_id($row['on_hand']) }} {{ $row['item']->unit }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">
                                {{ format_number_id($row['max']) }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                +{{ format_number_id($row['excess_qty']) }} {{ $row['item']->unit }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">
                                Rp {{ number_format($row['item']->unit_cost, 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                Rp {{ number_format($row['excess_value'], 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('items.show', $row['item']) }}"
                                   class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200 transition-colors">
                                    Detail SKU &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                ✅ Tidak ada SKU yang mengalami kelebihan stok di atas batas MAX.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: Slow Moving / Dead Stock -->
    <div x-show="activeTab === 'slowmoving'" x-cloak class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                    Barang Mati / Lambat Bergerak (Tidak Ada Pengeluaran &gt;30 Hari)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    SKU yang memiliki stok fisik di gudang tetapi tidak memiliki mutasi demand (ISSUE) sama sekali dalam 30 hari terakhir.
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 block">Total Modal Tertidur:</span>
                <span class="text-base font-mono font-bold text-blue-600 dark:text-blue-400">
                    Rp {{ number_format($totalDeadStockCapital, 0, ',', '.') }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">SKU & Nama Barang</th>
                        <th class="px-4 py-3.5">Gudang</th>
                        <th class="px-4 py-3.5 text-right">Stok Fisik Tersimpan</th>
                        <th class="px-4 py-3.5 text-right">Nilai Modal Tertahan</th>
                        <th class="px-4 py-3.5 text-center">Pengeluaran Terakhir</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($slowMovingSkus as $row)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('items.show', $row['item']) }}"
                                   class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                    {{ $row['item']->sku }}
                                </a>
                                <div class="text-xs text-slate-700 dark:text-slate-200 font-medium line-clamp-1">
                                    {{ $row['item']->name }}
                                </div>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                {{ $row['item']->warehouse?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ format_number_id($row['on_hand']) }} {{ $row['item']->unit }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-blue-600 dark:text-blue-400">
                                Rp {{ number_format($row['tied_capital'], 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap font-mono text-slate-500">
                                {{ $row['last_issue'] ? \Carbon\Carbon::parse($row['last_issue'])->format('d/m/Y') : 'Belum pernah' }}
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('items.show', $row['item']) }}"
                                   class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200 transition-colors">
                                    Detail SKU &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                ✅ Seluruh SKU memiliki mutasi pergerakan aktif dalam 30 hari terakhir.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 4: Tanpa Parameter -->
    <div x-show="activeTab === 'noparam'" x-cloak class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-purple-500"></span>
                    SKU Belum Memiliki Parameter Aktif
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    SKU aktif ini belum memiliki ROP/SS/MAX sehingga tidak terlindungi dalam otomatisasi reorder gudang.
                </p>
            </div>
            @can('run-pipeline')
            <form action="{{ route('pipeline.run-now') }}" method="POST">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-600 hover:bg-purple-500 text-white">
                    ⚡ Jalankan Pipeline Sekarang
                </button>
            </form>
            @endcan
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">SKU & Nama Barang</th>
                        <th class="px-4 py-3.5">Kategori</th>
                        <th class="px-4 py-3.5">Gudang</th>
                        <th class="px-4 py-3.5 text-right">Stok On Hand</th>
                        <th class="px-4 py-3.5 text-center">Tanggal Dibuat</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($unparameterizedSkus as $item)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('items.show', $item) }}"
                                   class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                                    {{ $item->sku }}
                                </a>
                                <div class="text-xs text-slate-700 dark:text-slate-200 font-medium line-clamp-1">
                                    {{ $item->name }}
                                </div>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                {{ $item->category?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                {{ $item->warehouse?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ format_number_id($item->stock_on_hand) }} {{ $item->unit }}
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap font-mono text-slate-500">
                                {{ $item->created_at->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('items.show', $item) }}"
                                   class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 hover:underline">
                                    Lihat Detail &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                ✅ Seluruh SKU aktif telah memiliki parameter operasional.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

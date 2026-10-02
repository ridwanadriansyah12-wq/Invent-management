@extends('layouts.app', ['title' => 'Kapasitas Gudang', 'header' => 'Kapasitas & Utilisasi Gudang'])

@section('content')
<div class="space-y-6" x-data="{ addModalOpen: false, editModalOpen: false, editWarehouse: { id: null, name: '', capacity_m3: '' } }">

    <!-- ── Header Action Bar ─────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Kapasitas & Utilisasi Gudang</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Pantau volume fisik terpakai saat ini vs proyeksi pesanan pada batas MAX dan limit aman kapasitas (85%).
            </p>
        </div>

        @can('manage-settings')
        <div class="flex items-center gap-2.5">
            <button type="button" @click="addModalOpen = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-950/20 transition-all focus:ring-2 focus:ring-emerald-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                + Tambah Gudang Baru
            </button>
        </div>
        @endcan
    </div>

    <!-- ── KPI Ringkasan Sistem Global ───────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Kapasitas Sistem -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Kapasitas Fisik</span>
                <span class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ format_volume_id($totalSystemCapacity, 2) }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">{{ $warehouseData->count() }} lokasi gudang aktif</span>
            </div>
        </div>

        <!-- 2. Volume Terpakai Saat Ini -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Terpakai Saat Ini</span>
                <span class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400">
                    {{ format_volume_id($totalSystemUsed, 2) }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">
                    Utilisasi Sistem: <strong class="text-slate-700 dark:text-slate-200">{{ number_format($systemUtilPct, 1, ',', '.') }}%</strong>
                </span>
            </div>
        </div>

        <!-- 3. Proyeksi Rencana MAX -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-400">Proyeksi Pada MAX</span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-purple-600 dark:text-purple-400">
                    {{ format_volume_id($totalSystemPlannedMax, 2) }}
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">jika seluruh SKU terisi sampai MAX</span>
            </div>
        </div>

        <!-- 4. Ambang Batas Kebijakan -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Limit Kebijakan Aman</span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                    {{ round($maxUtilizationRate * 100) }}%
                </span>
                <span class="text-xs text-slate-400 block mt-0.5">batas atas sebelum reduksi C &rarr; B &rarr; A</span>
            </div>
        </div>
    </div>

    <!-- ── Daftar Kartu Gudang ───────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($warehouseData as $w)
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">

                <!-- Header Gudang -->
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-lg">
                            🏢
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $w['model']->name }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Kapasitas: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ format_volume_id($w['total_capacity'], 1) }}</strong> &bull;
                                Batas Aman 85%: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ format_volume_id($w['limit_safety_m3'], 1) }}</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Badge Status Kapasitas -->
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                        {{ $w['status'] === 'CRITICAL' ? 'bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800' :
                          ($w['status'] === 'WARNING' ? 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800' :
                           'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800') }}">
                        {{ $w['status_label'] }}
                    </span>
                </div>

                <!-- Progress Bar: Utilisasi Saat Ini -->
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="font-medium text-slate-600 dark:text-slate-400">1. Utilisasi Fisik Saat Ini</span>
                        <span class="font-mono font-bold {{ $w['current_util_pct'] > 85 ? 'text-rose-600' : 'text-slate-900 dark:text-white' }}">
                            {{ format_volume_id($w['current_volume_m3'], 2) }} ({{ number_format($w['current_util_pct'], 1, ',', '.') }}%)
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden relative">
                        <div class="h-3 rounded-full transition-all duration-500 {{ $w['current_util_pct'] > 100 ? 'bg-rose-600' : ($w['current_util_pct'] > 85 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                             style="width: {{ min(100, $w['current_util_pct']) }}%">
                        </div>
                        <!-- Marker 85% -->
                        <div class="absolute top-0 bottom-0 w-0.5 bg-slate-400 dark:bg-slate-500 z-10" style="left: 85%" title="Batas Aman 85%"></div>
                    </div>
                </div>

                <!-- Progress Bar: Proyeksi Rencana MAX -->
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="font-medium text-slate-600 dark:text-slate-400">2. Proyeksi Rencana Pada MAX</span>
                        <span class="font-mono font-bold {{ $w['max_planned_util_pct'] > 85 ? 'text-amber-600' : 'text-slate-700 dark:text-slate-300' }}">
                            {{ format_volume_id($w['max_planned_volume_m3'], 2) }} ({{ number_format($w['max_planned_util_pct'], 1, ',', '.') }}%)
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden relative">
                        <div class="h-3 rounded-full transition-all duration-500 {{ $w['max_planned_util_pct'] > 100 ? 'bg-rose-500' : ($w['max_planned_util_pct'] > 85 ? 'bg-purple-500' : 'bg-blue-500') }}"
                             style="width: {{ min(100, $w['max_planned_util_pct']) }}%">
                        </div>
                        <!-- Marker 85% -->
                        <div class="absolute top-0 bottom-0 w-0.5 bg-slate-400 dark:bg-slate-500 z-10" style="left: 85%" title="Batas Aman 85%"></div>
                    </div>
                </div>

                <!-- Snapshot Metrik & Top SKU -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block">Jumlah SKU Aktif:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 block font-mono">
                            {{ $w['active_skus_count'] }} SKU
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Nilai Persediaan Gudang:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 block font-mono">
                            Rp {{ number_format($w['total_inventory_value'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <!-- Top 3 SKU Konsumen Ruang Terbesar -->
                @if($w['top_consumers']->isNotEmpty())
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-2">
                        Top SKU Pemakan Ruang Fisik Terbanyak:
                    </span>
                    <div class="space-y-1.5">
                        @foreach($w['top_consumers']->take(3) as $c)
                            <div class="flex items-center justify-between text-xs bg-slate-50 dark:bg-slate-800/60 px-3 py-1.5 rounded-lg">
                                <div class="flex items-center gap-2 truncate">
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $c->sku }}</span>
                                    <span class="text-slate-700 dark:text-slate-300 truncate">{{ $c->name }}</span>
                                </div>
                                <span class="font-mono font-bold text-slate-700 dark:text-slate-300 shrink-0 ml-2">
                                    {{ format_volume_id((float)$c->stock_on_hand * (float)$c->volume_m3, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Aksi Detail & Edit -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                    @can('manage-settings')
                    <button type="button"
                            @click="editWarehouse = { id: {{ $w['model']->id }}, name: '{{ $w['model']->name }}', capacity_m3: '{{ $w['model']->capacity_m3 }}' }; editModalOpen = true"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
                        Edit Kapasitas
                    </button>
                    @else
                    <span></span>
                    @endcan

                    <a href="{{ route('warehouses.show', $w['model']) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                        Buka Detail Gudang &rarr;
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <!-- ── Modal Tambah Gudang Baru (Admin Only) ─────────────────────────── -->
    <div x-show="addModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xl max-w-md w-full space-y-4"
             @click.away="addModalOpen = false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tambah Gudang Baru</h3>
            <form action="{{ route('warehouses.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Gudang</label>
                    <input type="text" name="name" required placeholder="Contoh: Gudang Bahan Baku Cikarang"
                           class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Kapasitas Fisik (m³)</label>
                    <input type="number" step="0.01" min="0.1" name="capacity_m3" required placeholder="Contoh: 500"
                           class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button type="button" @click="addModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-medium border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white">
                        Simpan Gudang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal Edit Gudang (Admin Only) ────────────────────────────────── -->
    <div x-show="editModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xl max-w-md w-full space-y-4"
             @click.away="editModalOpen = false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ubah Data Gudang</h3>
            <form :action="'/warehouses/' + editWarehouse.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Gudang</label>
                    <input type="text" name="name" x-model="editWarehouse.name" required
                           class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Kapasitas Fisik (m³)</label>
                    <input type="number" step="0.01" min="0.1" name="capacity_m3" x-model="editWarehouse.capacity_m3" required
                           class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button type="button" @click="editModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-medium border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

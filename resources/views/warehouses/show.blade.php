@extends('layouts.app', ['title' => 'Detail Gudang ' . $warehouse->name, 'header' => 'Detail & Alokasi Kapasitas: ' . $warehouse->name])

@section('content')
<div class="space-y-6">

    <!-- ── Top Header Profile Gudang ─────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-2xl shadow-md shrink-0">
                    🏢
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $warehouse->name }}</h1>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold
                            {{ $currentUtilPct > 100 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' :
                              ($currentUtilPct >= ($maxUtilizationRate * 100) ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' :
                               'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300') }}">
                            {{ $currentUtilPct > 100 ? 'Over Capacity' : ($currentUtilPct >= ($maxUtilizationRate * 100) ? 'Waspada' : 'Aman') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Kapasitas Maksimum: <strong class="text-slate-700 dark:text-slate-300 font-mono">{{ format_volume_id($totalCapacity, 2) }}</strong> &bull;
                        Batas Aman 85%: <strong class="text-slate-700 dark:text-slate-300 font-mono">{{ format_volume_id($limitSafetyM3, 2) }}</strong> &bull;
                        Total SKU: <strong class="text-slate-700 dark:text-slate-300 font-mono">{{ $skuBreakdown->count() }} SKU</strong>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start md:self-center">
                <a href="{{ route('warehouses.index') }}"
                   class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    &larr; Kembali
                </a>

                @can('manage-settings')
                <form action="{{ route('warehouses.check-capacity', $warehouse) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-all focus:ring-2 focus:ring-emerald-400">
                        ⚡ Jalankan Evaluasi Kapasitas
                    </button>
                </form>
                @endcan
            </div>
        </div>

        <!-- KPI Mini Stats Snapshot -->
        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Volume Terpakai Saat Ini</span>
                <span class="text-lg font-bold font-mono {{ $currentUtilPct > 85 ? 'text-rose-600' : 'text-slate-900 dark:text-white' }} mt-0.5 block">
                    {{ format_volume_id($currentVolumeM3, 2) }}
                </span>
                <span class="text-xs text-slate-500">{{ number_format($currentUtilPct, 1, ',', '.') }}% dari kapasitas</span>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Volume Proyeksi Pada MAX</span>
                <span class="text-lg font-bold font-mono text-purple-600 dark:text-purple-400 mt-0.5 block">
                    {{ format_volume_id($maxPlannedVolumeM3, 2) }}
                </span>
                <span class="text-xs text-slate-500">{{ number_format($maxPlannedUtilPct, 1, ',', '.') }}% dari kapasitas</span>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Total Nilai Persediaan</span>
                <span class="text-lg font-bold font-mono text-slate-900 dark:text-white mt-0.5 block">
                    Rp {{ number_format($totalInventoryValue, 0, ',', '.') }}
                </span>
                <span class="text-xs text-slate-500">modal kerja di gudang ini</span>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Status Guardrail Kapasitas</span>
                <span class="text-lg font-bold font-mono mt-0.5 block {{ $evaluation['is_over_capacity'] ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ $evaluation['is_over_capacity'] ? 'OVER CAPACITY' : ($evaluation['skus_adjusted'] > 0 ? 'ADJUSTED (' . $evaluation['skus_adjusted'] . ' SKU)' : 'OPTIMAL') }}
                </span>
                <span class="text-xs text-slate-500">evaluasi pipeline Tahap 6</span>
            </div>
        </div>
    </div>

    <!-- ── Visualisasi Bar Banding Kapasitas ──────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
            Alokasi & Utilisasi Ruang Fisik
        </h3>

        <div class="space-y-4">
            <!-- Utilisasi Fisik Saat Ini -->
            <div>
                <div class="flex justify-between text-xs mb-1.5">
                    <span class="font-medium text-slate-600 dark:text-slate-400">1. Stok Fisik Saat Ini (On-Hand)</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">
                        {{ format_volume_id($currentVolumeM3, 2) }} / {{ format_volume_id($totalCapacity, 2) }} ({{ number_format($currentUtilPct, 1, ',', '.') }}%)
                    </span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-4 overflow-hidden relative">
                    <div class="h-4 rounded-full transition-all duration-500 {{ $currentUtilPct > 100 ? 'bg-rose-600' : ($currentUtilPct > 85 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                         style="width: {{ min(100, $currentUtilPct) }}%"></div>
                    <!-- Garis 85% -->
                    <div class="absolute top-0 bottom-0 w-0.5 bg-slate-500 z-10" style="left: 85%" title="Batas Aman 85%"></div>
                </div>
            </div>

            <!-- Proyeksi MAX -->
            <div>
                <div class="flex justify-between text-xs mb-1.5">
                    <span class="font-medium text-slate-600 dark:text-slate-400">2. Proyeksi Rencana Pada MAX (Semua SKU Penuh)</span>
                    <span class="font-mono font-bold text-purple-600 dark:text-purple-400">
                        {{ format_volume_id($maxPlannedVolumeM3, 2) }} / {{ format_volume_id($totalCapacity, 2) }} ({{ number_format($maxPlannedUtilPct, 1, ',', '.') }}%)
                    </span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-4 overflow-hidden relative">
                    <div class="h-4 rounded-full transition-all duration-500 {{ $maxPlannedUtilPct > 100 ? 'bg-rose-500' : ($maxPlannedUtilPct > 85 ? 'bg-purple-500' : 'bg-blue-500') }}"
                         style="width: {{ min(100, $maxPlannedUtilPct) }}%"></div>
                    <!-- Garis 85% -->
                    <div class="absolute top-0 bottom-0 w-0.5 bg-slate-500 z-10" style="left: 85%" title="Batas Aman 85%"></div>
                </div>
            </div>
        </div>

        <p class="text-[11px] text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
            Garis vertikal abu-abu menandai batas aman kebijakan <strong>{{ round($maxUtilizationRate * 100) }}%</strong> ({{ format_volume_id($limitSafetyM3, 2) }}).
            Jika proyeksi MAX melampaui garis ini, sistem otomatis memangkas MAX berurutan dari kelas C &rarr; B &rarr; A hingga mencapai batas bawah ROP + MOQ.
        </p>
    </div>

    <!-- ── Tabel Rincian Seluruh SKU di Gudang Ini ──────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                Daftar SKU & Konsumsi Volume (Diurutkan Volume Terbesar)
            </h3>
            <span class="text-xs text-slate-400 font-mono">{{ $skuBreakdown->count() }} item terdaftar</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">SKU & Nama Barang</th>
                        <th class="px-4 py-3.5">Kategori</th>
                        <th class="px-4 py-3.5 text-center">Kelas</th>
                        <th class="px-4 py-3.5 text-right">Stok On Hand</th>
                        <th class="px-4 py-3.5 text-right">Vol / Unit</th>
                        <th class="px-4 py-3.5 text-right">Vol Terpakai (m³)</th>
                        <th class="px-4 py-3.5 text-right">ROP</th>
                        <th class="px-4 py-3.5 text-right">MAX</th>
                        <th class="px-4 py-3.5 text-right">Proyeksi MAX (m³)</th>
                        <th class="px-4 py-3.5 text-right">Nilai Stok (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($skuBreakdown as $row)
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
                                {{ $row['item']->category?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="font-mono font-bold px-2 py-0.5 rounded text-[11px]
                                    {{ $row['abc_class'] === 'A' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                      ($row['abc_class'] === 'B' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' :
                                       'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                                    Kelas {{ $row['abc_class'] }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ format_number_id($row['on_hand']) }} <span class="text-[10px] font-normal text-slate-400">{{ $row['item']->unit }}</span>
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-slate-500">
                                {{ format_volume_id($row['vol_per_unit'], 3) }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                {{ format_volume_id($row['used_m3'], 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">
                                {{ $row['effective_rop'] > 0 ? format_number_id($row['effective_rop']) : '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">
                                {{ $row['effective_max'] > 0 ? format_number_id($row['effective_max']) : '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold text-purple-600 dark:text-purple-400">
                                {{ format_volume_id($row['max_m3'], 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-700 dark:text-slate-300">
                                Rp {{ number_format($row['inv_val'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-10 text-center text-slate-400">
                                Tidak ada SKU aktif yang dialokasikan di gudang ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

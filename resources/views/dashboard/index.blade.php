@extends('layouts.app', ['title' => 'Dashboard', 'header' => 'Ringkasan Persediaan Terpadu'])

@section('content')
<div class="space-y-6">

    <!-- ── 1. KPI Metrik Utama ──────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: SKU Aktif -->
        <x-stat-card
            title="Total SKU Aktif"
            :value="format_number_id($totalActiveSkus, 0)"
            subtitle="Barang terdaftar aktif di sistem"
            color="slate"
            :href="route('items.index')"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-slate-700 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- KPI 2: Nilai Persediaan (Rp) -->
        <x-stat-card
            title="Nilai Persediaan"
            :value="'Rp ' . format_number_id($inventoryValue, 0)"
            subtitle="Berdasarkan saldo stok x unit cost"
            color="emerald"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- KPI 3: Volume Terpakai (m³) -->
        <x-stat-card
            title="Volume Terpakai"
            :value="format_volume_id($usedVolume, 2)"
            :subtitle="format_percent_id($volumeUtilizationPct, 1) . ' dari total ' . format_volume_id($totalWarehouseCapacity, 0)"
            color="{{ $volumeUtilizationPct > 85 ? 'rose' : ($volumeUtilizationPct > 70 ? 'amber' : 'blue') }}"
            :href="Route::has('warehouses.index') ? route('warehouses.index') : null"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- KPI 4: SKU di Bawah ROP -->
        <x-stat-card
            title="SKU di Bawah ROP"
            :value="format_number_id($skusBelowRopCount, 0)"
            subtitle="Inventory position &le; Effective ROP"
            color="{{ $skusBelowRopCount > 0 ? 'rose' : 'emerald' }}"
            :href="route('items.index', ['below_rop' => 1])"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <!-- ── 2. Baris KPI Operasional & Alert Link ────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- KPI 5: PR OPEN -->
        <x-stat-card
            title="Purchase Requisition Terbuka"
            :value="format_number_id($openPrCount, 0)"
            subtitle="Menunggu pemesanan PO atau approval"
            color="blue"
            :href="Route::has('purchase-requisitions.index') ? route('purchase-requisitions.index') : null"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- KPI 6: Pending Review -->
        <x-stat-card
            title="Pending Review ROP"
            :value="format_number_id($pendingReviewsCount, 0)"
            subtitle="Deviasi ROP usulan ML &gt; 50%"
            color="{{ $pendingReviewsCount > 0 ? 'amber' : 'slate' }}"
            :href="Route::has('review.index') ? route('review.index') : null"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- KPI 7: Alert Belum Resolved -->
        <x-stat-card
            title="Alert Sistem Belum Selesai"
            :value="format_number_id($unresolvedAlertsCount, 0)"
            subtitle="Perlu investigasi kapasitas / integrasi"
            color="{{ $unresolvedAlertsCount > 0 ? 'rose' : 'slate' }}"
            :href="Route::has('system-status.index') ? route('system-status.index') : null"
        >
            <x-slot:icon>
                <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <!-- ── 3. Visualisasi Grafik: Utilisasi Kapasitas Gudang & Matriks ABC-XYZ ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Grafik Bar Utilisasi Gudang (2 Kolom) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Utilisasi Kapasitas Gudang</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Membandingkan volume saat ini vs volume rencana (MAX) terhadap batas utilisasi 85%
                    </p>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <span class="w-3 h-3 rounded-xs bg-emerald-500"></span> Saat Ini
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <span class="w-3 h-3 rounded-xs bg-indigo-500"></span> Rencana MAX
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-rose-600 font-medium">
                        <span class="w-3 h-0.5 bg-rose-500"></span> Batas 85%
                    </span>
                </div>
            </div>

            <div class="h-64 sm:h-72 w-full">
                @if(empty($warehouseChartData['labels']))
                    <div class="h-full flex items-center justify-center text-slate-400 text-sm">
                        Belum ada data gudang terdaftar.
                    </div>
                @else
                    <canvas id="warehouseCapacityChart"></canvas>
                @endif
            </div>
        </div>

        <!-- Matriks 3x3 ABC-XYZ & Pola Permintaan (1 Kolom) -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="mb-3 pb-2 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Matriks Klasifikasi ABC-XYZ</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Distribusi SKU berdasarkan nilai pemakaian (A-B-C) dan variabilitas (X-Y-Z)
                    </p>
                </div>

                <!-- Tabel Matriks 3x3 -->
                <div class="overflow-x-auto">
                    <table class="w-full text-center border-collapse text-xs">
                        <thead>
                            <tr class="text-slate-400 font-semibold uppercase">
                                <th class="p-1.5 text-left text-slate-500">Kelas</th>
                                <th class="p-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-t-lg">X (Stabil)</th>
                                <th class="p-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-t-lg">Y (Variasi)</th>
                                <th class="p-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-t-lg">Z (Volatil)</th>
                                <th class="p-1.5 text-slate-500">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @foreach(['A' => 'A (Prioritas)', 'B' => 'B (Menengah)', 'C' => 'C (Umum)'] as $tier => $label)
                                <tr>
                                    <td class="p-2 text-left font-bold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                        {{ $tier }}
                                    </td>
                                    <td class="p-2 bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-300 font-semibold">
                                        {{ $abcXyzMatrix[$tier]['X'] }}
                                    </td>
                                    <td class="p-2 bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-semibold">
                                        {{ $abcXyzMatrix[$tier]['Y'] }}
                                    </td>
                                    <td class="p-2 bg-amber-50/50 dark:bg-amber-950/20 text-amber-900 dark:text-amber-300 font-semibold">
                                        {{ $abcXyzMatrix[$tier]['Z'] }}
                                    </td>
                                    <td class="p-2 text-slate-500 font-bold bg-slate-50/60 dark:bg-slate-800/40">
                                        {{ $abcXyzMatrix[$tier]['total'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ringkasan Distribusi Pola Permintaan -->
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-2">
                    Distribusi Pola Permintaan (Syntetos-Boylan):
                </span>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">Smooth:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $demandPatterns['smooth'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">Intermittent:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $demandPatterns['intermittent'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">Erratic:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $demandPatterns['erratic'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">Lumpy:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $demandPatterns['lumpy'] }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── 4. Tabel 5 SKU Paling Kritis ────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/50">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                    5 SKU Paling Kritis
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Diurutkan berdasarkan rasio <code>Inventory Position / Effective ROP</code> paling kecil
                </p>
            </div>
            <a href="{{ route('items.index', ['below_rop' => 1]) }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 inline-flex items-center gap-1">
                Lihat semua SKU kritis &rarr;
            </a>
        </div>

        <div class="overflow-x-auto table-scroll-container">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 whitespace-nowrap">SKU</th>
                        <th class="px-4 py-3">Nama Barang</th>
                        <th class="px-4 py-3 whitespace-nowrap">Gudang</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">On Hand</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Inv. Position</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Effective ROP</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">Rasio ke ROP</th>
                        <th class="px-4 py-3 whitespace-nowrap">Status Parameter</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-900">
                    @forelse($criticalSkus as $item)
                        @php
                            $isBelowRop = (float) $item->critical_ratio <= 1.0;
                            $pctRatio = (float) $item->critical_ratio * 100;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors {{ $isBelowRop ? 'bg-rose-50/30 dark:bg-rose-950/10' : '' }}">
                            <td class="px-4 py-3 font-mono font-bold text-xs text-slate-900 dark:text-white whitespace-nowrap">
                                <a href="{{ route('items.show', $item) }}" class="hover:text-emerald-600 hover:underline">
                                    {{ $item->sku }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 max-w-xs truncate">
                                {{ $item->name }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                {{ $item->warehouse?->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                {{ format_number_id($item->stock_on_hand) }} <span class="text-xs text-slate-400">{{ $item->unit }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                {{ format_number_id($item->calculated_inventory_position) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                {{ format_number_id($item->param_effective_rop) }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($isBelowRop)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        {{ number_format($pctRatio, 1, ',', '.') }}% (Di bawah ROP)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        {{ number_format($pctRatio, 1, ',', '.') }}%
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-badge :status="$item->param_status ?? 'ACTIVE'" />
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('items.show', $item) }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline">
                                    Detail &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-10 text-center text-slate-400">
                                Belum ada SKU aktif dengan parameter yang dihitung.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = @json($warehouseChartData);

    if (chartData && chartData.labels && chartData.labels.length > 0) {
        const ctx = document.getElementById('warehouseCapacityChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Utilisasi Saat Ini (m³)',
                            data: chartData.currentVolumes,
                            backgroundColor: 'rgba(16, 185, 129, 0.85)', // Emerald
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            label: 'Rencana MAX (m³)',
                            data: chartData.plannedMaxVolumes,
                            backgroundColor: 'rgba(99, 102, 241, 0.85)', // Indigo
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            label: 'Batas 85% Kapasitas (m³)',
                            data: chartData.capacityLimits,
                            type: 'line',
                            borderColor: 'rgb(244, 63, 94)', // Rose
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 4,
                            pointBackgroundColor: 'rgb(244, 63, 94)',
                            fill: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false, // Digantikan custom legend di HTML
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += window.formatNumberId(context.parsed.y, 2) + ' m³';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Volume (m³)',
                                font: { size: 11 }
                            },
                            ticks: {
                                callback: function(value) {
                                    return window.formatNumberId(value, 0) + ' m³';
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
@endpush
@endsection

@extends('layouts.app', ['title' => 'Detail SKU ' . $item->sku, 'header' => 'Detail Master SKU: ' . $item->sku])

@section('content')
<div class="space-y-6" x-data="{ activeTab: '{{ request('tab', 'summary') }}' }">

    <!-- ── Top Header Profile SKU ────────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-bold font-mono text-xl shadow-md shrink-0">
                    {{ substr($item->sku, 0, 3) }}
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $item->sku }}</span>
                        @if($item->activeParameter)
                            <x-badge :status="$item->activeParameter->status" />
                        @else
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                Belum ada parameter
                            </span>
                        @endif
                        @if($item->classification)
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                {{ $item->classification->abc_class }}{{ $item->classification->xyz_class }} &bull; {{ ucfirst($item->classification->demand_pattern) }}
                            </span>
                        @endif
                    </div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $item->name }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Kategori: <strong class="text-slate-700 dark:text-slate-300">{{ $item->category?->name ?? '-' }}</strong> &bull;
                        Gudang: <strong class="text-slate-700 dark:text-slate-300">{{ $item->warehouse?->name ?? '-' }}</strong> &bull;
                        Satuan: <strong class="text-slate-700 dark:text-slate-300">{{ $item->unit }}</strong>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start md:self-center">
                <a href="{{ route('items.index') }}"
                   class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 transition-colors">
                    &larr; Kembali
                </a>
                @can('manage-sku')
                    <a href="{{ route('items.edit', $item) }}"
                       class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-colors focus:ring-2 focus:ring-emerald-400">
                        Edit Master SKU
                    </a>
                @endcan
            </div>
        </div>

        <!-- KPI Mini Stats Snapshot -->
        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Stok On Hand</span>
                <span class="text-lg font-bold text-slate-900 dark:text-white mt-0.5 block">
                    {{ format_number_id($item->stock_on_hand) }} <span class="text-xs font-normal text-slate-500">{{ $item->unit }}</span>
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Inv. Position</span>
                <span class="text-lg font-bold {{ $item->activeParameter && $inventoryPosition <= $item->activeParameter->effective_rop ? 'text-rose-600' : 'text-slate-900 dark:text-white' }} mt-0.5 block">
                    {{ format_number_id($inventoryPosition) }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Safety Stock (SS)</span>
                <span class="text-lg font-bold text-slate-700 dark:text-slate-200 mt-0.5 block">
                    {{ $item->activeParameter ? format_number_id($item->activeParameter->effective_ss) : '-' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Reorder Point (ROP)</span>
                <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 block">
                    {{ $item->activeParameter ? format_number_id($item->activeParameter->effective_rop) : '-' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Max Stock (MAX)</span>
                <span class="text-lg font-bold text-slate-700 dark:text-slate-200 mt-0.5 block">
                    {{ $item->activeParameter ? format_number_id($item->activeParameter->effective_max) : '-' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Unit Cost / Vol</span>
                <span class="text-xs font-bold text-slate-900 dark:text-white mt-1 block">
                    Rp {{ format_number_id($item->unit_cost, 0) }}
                </span>
                <span class="text-[11px] text-slate-500 block">
                    {{ format_volume_id($item->volume_m3, 3) }}
                </span>
            </div>
        </div>
    </div>

    <!-- ── 5 Tab Navigation ──────────────────────────────────────────────── -->
    <div class="border-b border-slate-200 dark:border-slate-800">
        <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto" aria-label="Tabs">
            <button type="button"
                    x-on:click="activeTab = 'summary'"
                    :class="activeTab === 'summary' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-3 border-b-2 text-xs sm:text-sm whitespace-nowrap cursor-pointer transition-colors">
                1. Ringkasan & Master Data
            </button>

            <button type="button"
                    x-on:click="activeTab = 'demand'"
                    :class="activeTab === 'demand' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-3 border-b-2 text-xs sm:text-sm whitespace-nowrap cursor-pointer transition-colors">
                2. Permintaan (Demand & Censoring)
            </button>

            <button type="button"
                    x-on:click="activeTab = 'parameters'"
                    :class="activeTab === 'parameters' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-3 border-b-2 text-xs sm:text-sm whitespace-nowrap cursor-pointer transition-colors">
                3. Parameter & Clamping
            </button>

            <button type="button"
                    x-on:click="activeTab = 'model'"
                    :class="activeTab === 'model' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-3 border-b-2 text-xs sm:text-sm whitespace-nowrap cursor-pointer transition-colors">
                4. Model & Forecast Run
            </button>

            <button type="button"
                    x-on:click="activeTab = 'movements'"
                    :class="activeTab === 'movements' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                    class="py-3 px-3 border-b-2 text-xs sm:text-sm whitespace-nowrap cursor-pointer transition-colors">
                5. Stok & Pergerakan (Ledger)
            </button>
        </nav>
    </div>

    <!-- ── TAB 1: RINGKASAN & MASTER DATA ────────────────────────────────── -->
    <div x-show="activeTab === 'summary'" x-cloak class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Card: Parameter Fisik & Pengadaan -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
                    Parameter Pengadaan & Fisik SKU
                </h3>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div>
                        <dt class="text-slate-400">MOQ (Minimum Order):</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ format_number_id($item->moq) }} {{ $item->unit }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Lot Size (Kelipatan Pemesanan):</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ format_number_id($item->lot_size) }} {{ $item->unit }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Lead Time Pemasok (LT):</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ $item->lead_time_days ?? 15 }} hari</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Variabilitas LT (&sigma; LT):</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ $item->lead_time_std_days ?? 0.0 }} hari</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Volume Satuan:</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ format_volume_id($item->volume_m3, 3) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Harga Pokok (Unit Cost):</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">Rp {{ format_number_id($item->unit_cost, 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Pergerakan Pertama:</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ format_date_id($item->first_movement_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Status Aktif:</dt>
                        <dd class="font-semibold mt-0.5 {{ $item->is_active ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $item->is_active ? 'Aktif' : 'Non-Aktif' }}
                        </dd>
                    </div>
                </dl>
                @if($item->description)
                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <span class="text-slate-400 block mb-1">Deskripsi SKU:</span>
                        <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $item->description }}</p>
                    </div>
                @endif
            </div>

            <!-- Card: Profil Klasifikasi & Machine Learning -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
                    Profil Klasifikasi ABC-XYZ & Pola Demand
                </h3>
                @if($item->classification)
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                        <div>
                            <dt class="text-slate-400">Kelas ABC (Nilai Penggunaan):</dt>
                            <dd class="font-bold text-slate-900 dark:text-white mt-0.5">Kelas {{ $item->classification->abc_class }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Kelas XYZ (Volatilitas):</dt>
                            <dd class="font-bold text-slate-900 dark:text-white mt-0.5">Kelas {{ $item->classification->xyz_class }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">ADI (Average Demand Interval):</dt>
                            <dd class="font-mono text-slate-900 dark:text-white mt-0.5">{{ number_format($item->classification->adi, 2, ',', '.') }} hari</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">CV&sup2; (Coefficient of Variation Squared):</dt>
                            <dd class="font-mono text-slate-900 dark:text-white mt-0.5">{{ number_format($item->classification->cv2, 3, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Pola Permintaan (Syntetos-Boylan):</dt>
                            <dd class="font-bold text-emerald-600 dark:text-emerald-400 uppercase mt-0.5">{{ $item->classification->demand_pattern }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Riwayat Tersedia:</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ $item->classification->history_days }} hari</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-400">Terakhir Diklasifikasi:</dt>
                            <dd class="text-slate-600 dark:text-slate-300 mt-0.5">{{ format_datetime_id($item->classification->computed_at) }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="py-8 text-center text-slate-400 text-xs">
                        <p>Belum ada riwayat klasifikasi ABC-XYZ untuk SKU ini.</p>
                        <p class="text-[11px] mt-1 text-slate-500">Pipeline mingguan akan mengklasifikasikan SKU secara otomatis.</p>
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- ── TAB 2: PERMINTAAN (DEMAND & CENSORING) ────────────────────────── -->
    <div x-show="activeTab === 'demand'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Histori Permintaan Harian & Imputasi Stockout</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Membandingkan <code>issued_qty</code> asli terhadap <code>demand_clean</code> hasil imputasi hari-hari tersensor (stockout)
                    </p>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <span class="w-3 h-0.5 bg-slate-400"></span> Permintaan Asli (Issued)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-emerald-600 font-semibold">
                        <span class="w-3 h-0.5 bg-emerald-500"></span> Demand Bersih (ML)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-rose-600 font-semibold">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Hari Tersensor
                    </span>
                </div>
            </div>

            @if($dailyDemands->isEmpty())
                <div class="py-16 text-center text-slate-400 text-sm">
                    <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Belum ada ledger permintaan harian (daily demand) untuk SKU ini.
                </div>
            @else
                <div class="h-72 w-full">
                    <canvas id="dailyDemandChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    <!-- ── TAB 3: PARAMETER & CLAMPING ──────────────────────────────────── -->
    <div x-show="activeTab === 'parameters'" x-cloak class="space-y-6">

        <!-- Info Pita Clamping Saat Ini -->
        <div class="bg-slate-50 dark:bg-slate-800/40 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                Pita Deviasi Guardrail Clamping (&plusmn;50% Baseline)
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <span class="text-slate-400 block">Batas Bawah (50% Baseline):</span>
                    <span class="text-base font-bold text-slate-900 dark:text-white mt-0.5 block">{{ format_number_id($clampLower) }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <span class="text-slate-400 block">Baseline ROP (30 hari terakhir):</span>
                    <span class="text-base font-bold text-emerald-600 mt-0.5 block">{{ format_number_id($baselineRop) }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <span class="text-slate-400 block">Batas Atas (150% Baseline):</span>
                    <span class="text-base font-bold text-slate-900 dark:text-white mt-0.5 block">{{ format_number_id($clampUpper) }}</span>
                </div>
            </div>
        </div>

        <!-- Tabel Riwayat Inventory Parameters -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 font-bold text-sm text-slate-800 dark:text-slate-200">
                Riwayat Parameter Inventaris (Audit Trail)
            </div>
            <div class="overflow-x-auto table-scroll-container">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 font-semibold uppercase text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 whitespace-nowrap">Waktu Kalkulasi</th>
                            <th class="px-4 py-3 whitespace-nowrap">Sumber</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Proposed ROP</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Effective ROP</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">SS</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">MAX</th>
                            <th class="px-4 py-3 whitespace-nowrap">Status</th>
                            <th class="px-4 py-3 whitespace-nowrap">Flag Reason</th>
                            <th class="px-4 py-3 whitespace-nowrap">Catatan Review</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse($parametersHistory as $param)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 whitespace-nowrap font-mono">{{ format_datetime_id($param->computed_at) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-mono">{{ $param->source }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ format_number_id($param->proposed_rop) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white whitespace-nowrap">{{ format_number_id($param->effective_rop) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ format_number_id($param->effective_ss) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ format_number_id($param->effective_max) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-badge :status="$param->status" size="sm" />
                                </td>
                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $param->flag_reason ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $param->review_notes ?? $param->review_note ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-slate-400">
                                    Belum ada histori parameter untuk SKU ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ── TAB 4: MODEL & FORECAST RUN ─────────────────────────────────── -->
    <div x-show="activeTab === 'model'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 font-bold text-sm text-slate-800 dark:text-slate-200">
                Riwayat Eksekusi Model Forecasting (FastAPI Microservice)
            </div>
            <div class="overflow-x-auto table-scroll-container">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 font-semibold uppercase text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 whitespace-nowrap">Run ID</th>
                            <th class="px-4 py-3 whitespace-nowrap">Waktu Run</th>
                            <th class="px-4 py-3 whitespace-nowrap">Model Digunakan</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">&mu; Daily</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">&sigma; Daily</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Backtest MASE</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">RMSE</th>
                            <th class="px-4 py-3 whitespace-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse($forecastRuns as $run)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-[11px] whitespace-nowrap" title="{{ $run->run_id }}">{{ substr($run->run_id, 0, 8) }}...</td>
                                <td class="px-4 py-3 font-mono whitespace-nowrap">{{ format_datetime_id($run->run_at) }}</td>
                                <td class="px-4 py-3 font-bold whitespace-nowrap text-emerald-600">{{ $run->model_used ?? '-' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($run->mu_daily, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($run->sigma_daily, 3, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-mono">{{ $run->backtest_mase ? number_format($run->backtest_mase, 3, ',', '.') : '-' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-mono">{{ $run->backtest_rmse ? number_format($run->backtest_rmse, 3, ',', '.') : '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-badge :status="$run->status" size="sm" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-slate-400">
                                    Belum ada catatan forecast run dari microservice untuk SKU ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── TAB 5: STOK & PERGERAKAN (LEDGER) ────────────────────────────── -->
    <div x-show="activeTab === 'movements'" x-cloak class="space-y-6">

        <!-- Filter Form Riwayat Pergerakan Stok -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm">
            <form method="GET" action="{{ route('items.show', $item) }}" class="flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="tab" value="movements">

                <div class="w-full sm:w-48">
                    <select name="movement_reason" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-2">
                        <option value="">Semua Alasan</option>
                        <option value="ISSUE" {{ request('movement_reason') == 'ISSUE' ? 'selected' : '' }}>ISSUE (Demand)</option>
                        <option value="RECEIPT" {{ request('movement_reason') == 'RECEIPT' ? 'selected' : '' }}>RECEIPT (Masuk)</option>
                        <option value="RETURN" {{ request('movement_reason') == 'RETURN' ? 'selected' : '' }}>RETURN (Retur)</option>
                        <option value="ADJUSTMENT" {{ request('movement_reason') == 'ADJUSTMENT' ? 'selected' : '' }}>ADJUSTMENT (Penyesuaian)</option>
                    </select>
                </div>

                <div class="w-full sm:w-40">
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-2">
                </div>

                <div class="w-full sm:w-40">
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-2">
                </div>

                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900">
                    Filter Ledger
                </button>
            </form>
        </div>

        <!-- Tabel Pergerakan Stok -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="overflow-x-auto table-scroll-container">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 font-semibold uppercase text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 whitespace-nowrap">Tanggal</th>
                            <th class="px-4 py-3 whitespace-nowrap">Tipe / Alasan</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Qty</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Stok Sebelum</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Stok Sesudah</th>
                            <th class="px-4 py-3 whitespace-nowrap">No. Referensi</th>
                            <th class="px-4 py-3 whitespace-nowrap">Operator</th>
                            <th class="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse($stockMovements as $mv)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono whitespace-nowrap">{{ format_date_id($mv->movement_date) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ in_array($mv->reason, ['ISSUE', 'OUT']) ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $mv->reason_label ?? $mv->reason }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold whitespace-nowrap {{ in_array($mv->reason, ['ISSUE', 'OUT']) ? 'text-indigo-600' : 'text-emerald-600' }}">
                                    {{ in_array($mv->reason, ['ISSUE', 'OUT']) ? '-' : '+' }}{{ format_number_id($mv->qty) }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ format_number_id($mv->stock_before) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white whitespace-nowrap">{{ format_number_id($mv->stock_after) }}</td>
                                <td class="px-4 py-3 font-mono text-slate-500 whitespace-nowrap">{{ $mv->reference_no ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $mv->user?->name ?? 'Sistem' }}</td>
                                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $mv->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-slate-400">
                                    Belum ada transaksi pergerakan stok untuk SKU ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($stockMovements->hasPages())
                <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50">
                    {{ $stockMovements->links() }}
                </div>
            @endif
        </div>

    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Chart Permintaan Harian (Tab 2) ──────────────────────────────────────
    const demandData = @json($dailyDemands);
    if (demandData && demandData.length > 0) {
        const ctxDemand = document.getElementById('dailyDemandChart');
        if (ctxDemand) {
            const labels = demandData.map(d => d.date);
            const issuedData = demandData.map(d => d.issued_qty);
            const cleanData = demandData.map(d => d.demand_clean);
            const censoredPoints = demandData.map(d => d.is_censored ? d.demand_clean : null);

            new Chart(ctxDemand, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Permintaan Asli (Issued Qty)',
                            data: issuedData,
                            borderColor: 'rgb(148, 163, 184)',
                            borderWidth: 1.5,
                            pointRadius: 2,
                            fill: false,
                        },
                        {
                            label: 'Demand Bersih (Clean / Imputed)',
                            data: cleanData,
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 2,
                            pointRadius: 2,
                            fill: false,
                        },
                        {
                            label: 'Hari Tersensor (Stockout)',
                            data: censoredPoints,
                            borderColor: 'rgb(244, 63, 94)',
                            backgroundColor: 'rgb(244, 63, 94)',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            showLine: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Kuantitas Permintaan' }
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

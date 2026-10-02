@extends('layouts.app', ['title' => 'Simulator Kebijakan Persediaan', 'header' => 'Kalkulator & Simulator Kebijakan Persediaan'])

@section('content')
<div class="space-y-6"
     x-data="{
         selectedId: '{{ $selectedItem?->id ?? '' }}',
         lt: 15,
         ltStd: 0,
         mu: 10,
         sigma: 3.5,
         serviceLevelZ: 2.05,
         serviceLevelLabel: '98% (Kelas A)',
         coverDays: 14,
         moq: 20,
         lotSize: 10,
         unitCost: 25000,
         volumeM3: 0.02,
         unit: 'pcs',

         items: @js($items->mapWithKeys(fn($i) => [$i->id => [
             'id' => $i->id,
             'sku' => $i->sku,
             'name' => $i->name,
             'unit' => $i->unit,
             'lt' => (int)($i->lead_time_days ?: 15),
             'ltStd' => (float)($i->lead_time_std_days ?: 0),
             'moq' => (int)($i->moq ?: 1),
             'lotSize' => (int)($i->lot_size ?: 1),
             'unitCost' => (float)$i->unit_cost,
             'volumeM3' => (float)$i->volume_m3,
             'mu' => (float)($i->activeParameter?->forecastRun?->mu_daily ?: 10),
             'sigma' => (float)($i->activeParameter?->forecastRun?->sigma_daily ?: 3.5),
             'abc' => $i->classification?->abc_class ?: 'A',
         ]])),

         init() {
             if (this.selectedId && this.items[this.selectedId]) {
                 this.loadItem(this.items[this.selectedId]);
             }
         },

         loadItem(item) {
             this.lt = item.lt;
             this.ltStd = item.ltStd;
             this.mu = item.mu;
             this.sigma = item.sigma;
             this.moq = item.moq;
             this.lotSize = item.lotSize;
             this.unitCost = item.unitCost;
             this.volumeM3 = item.volumeM3;
             this.unit = item.unit;

             if (item.abc === 'A') {
                 this.serviceLevelZ = 2.05;
                 this.serviceLevelLabel = '98% (Kelas A)';
                 this.coverDays = 14;
             } else if (item.abc === 'B') {
                 this.serviceLevelZ = 1.65;
                 this.serviceLevelLabel = '95% (Kelas B)';
                 this.coverDays = 21;
             } else {
                 this.serviceLevelZ = 1.28;
                 this.serviceLevelLabel = '90% (Kelas C)';
                 this.coverDays = 30;
             }
         },

         get ss() {
             let varianceDemand = this.lt * Math.pow(this.sigma, 2);
             let varianceLt = Math.pow(this.mu, 2) * Math.pow(this.ltStd, 2);
             let combinedStd = Math.sqrt(varianceDemand + varianceLt);
             return Math.ceil(this.serviceLevelZ * combinedStd);
         },

         get rop() {
             let leadDemand = this.mu * this.lt;
             return Math.ceil(leadDemand + this.ss);
         },

         get qTarget() {
             return Math.round(this.mu * this.coverDays);
         },

         get maxStock() {
             return Math.ceil(this.rop + this.qTarget);
         },

         get orderQty() {
             let qRaw = Math.max(0, this.maxStock - this.rop);
             let lots = Math.ceil(qRaw / Math.max(1, this.lotSize));
             let sanitized = lots * this.lotSize;
             return Math.max(this.moq, sanitized);
         },

         get maxInventoryValue() {
             return this.maxStock * this.unitCost;
         },

         get maxVolumeM3() {
             return this.maxStock * this.volumeM3;
         }
     }">

    <!-- ── Header ────────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Simulator Kebijakan Persediaan (What-If Analysis)</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Uji skenario kebijakan sebelum diterapkan: bagaimana perubahan lead time supplier, variabilitas demand, atau target service level memengaruhi Safety Stock, ROP, dan MAX?
            </p>
        </div>

        <a href="{{ route('inventory.health') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50">
            &larr; Diagnostik Kesehatan
        </a>
    </div>

    <!-- ── Grid Input & Output ───────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Panel Input Parameter (5 Kolom) -->
        <div class="lg:col-span-5 space-y-5">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
                    Parameter Skenario Simulasi
                </h3>

                <!-- Pilih SKU Template -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Muat Parameter Dari SKU Yang Ada (Opsional):
                    </label>
                    <select x-model="selectedId" @change="if(selectedId) loadItem(items[selectedId])"
                            class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">-- Input Manual Skenario Bebas --</option>
                        @foreach($items as $i)
                            <option value="{{ $i->id }}">
                                [{{ $i->sku }}] {{ $i->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Demand Harian (mu) & Simpangan Baku (sigma) -->
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Rata-rata Demand (&mu;)
                        </label>
                        <input type="number" step="0.1" min="0.1" x-model.number="mu"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-[10px] text-slate-400">unit/hari</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Simpangan Error (&sigma;)
                        </label>
                        <input type="number" step="0.1" min="0" x-model.number="sigma"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-[10px] text-slate-400">RMSE harian</span>
                    </div>
                </div>

                <!-- Lead Time (LT) & Standar Deviasi LT -->
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Lead Time Supplier (LT)
                        </label>
                        <input type="number" step="1" min="1" x-model.number="lt"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-[10px] text-slate-400">hari kalender</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Ketidakpastian LT (&sigma;<sub>LT</sub>)
                        </label>
                        <input type="number" step="0.5" min="0" x-model.number="ltStd"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-[10px] text-slate-400">hari variasi supplier</span>
                    </div>
                </div>

                <!-- Target Service Level (Z) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Target Service Level (Tingkat Layanan)
                    </label>
                    <select x-model.number="serviceLevelZ"
                            class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="2.33">99% (Z = 2.33) - Kritis / High Availability</option>
                        <option value="2.05">98% (Z = 2.05) - Default Kelas A</option>
                        <option value="1.65">95% (Z = 1.65) - Default Kelas B</option>
                        <option value="1.28">90% (Z = 1.28) - Default Kelas C</option>
                    </select>
                </div>

                <!-- Target Cover Days -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Target Cover Days (Siklus Pemesanan)
                    </label>
                    <input type="number" step="1" min="1" x-model.number="coverDays"
                           class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <span class="text-[10px] text-slate-400">hari permintaan target (A=14, B=21, C=30)</span>
                </div>

                <!-- MOQ & Lot Size -->
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            MOQ Supplier
                        </label>
                        <input type="number" step="1" min="1" x-model.number="moq"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Kelipatan Lot Size
                        </label>
                        <input type="number" step="1" min="1" x-model.number="lotSize"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Unit Cost & Volume -->
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Harga Pokok (Rp/unit)
                        </label>
                        <input type="number" step="100" min="0" x-model.number="unitCost"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Volume Fisik (m³/unit)
                        </label>
                        <input type="number" step="0.001" min="0.0001" x-model.number="volumeM3"
                               class="w-full px-3 py-2 rounded-xl text-xs font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Hasil Kalkulasi & Visualisasi (7 Kolom) -->
        <div class="lg:col-span-7 space-y-5">
            <!-- Kartu 4 Nilai Utama Persediaan -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
                    Hasil Kalkulasi Parameter Dinamis
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <!-- Safety Stock -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Safety Stock (SS)</span>
                        <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white mt-1 block" x-text="ss.toLocaleString('id-ID')"></span>
                        <span class="text-[10px] text-slate-500" x-text="unit"></span>
                    </div>

                    <!-- Reorder Point -->
                    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                        <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Reorder Point (ROP)</span>
                        <span class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1 block" x-text="rop.toLocaleString('id-ID')"></span>
                        <span class="text-[10px] text-emerald-600/70" x-text="'Pemicu PR (' + unit + ')'"></span>
                    </div>

                    <!-- Q Target -->
                    <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800">
                        <span class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider block">Target Pesan (Q)</span>
                        <span class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 mt-1 block" x-text="orderQty.toLocaleString('id-ID')"></span>
                        <span class="text-[10px] text-blue-600/70" x-text="'Sanitized MOQ/Lot'"></span>
                    </div>

                    <!-- MAX Stock -->
                    <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800">
                        <span class="text-[11px] font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider block">Max Stock (MAX)</span>
                        <span class="text-2xl font-bold font-mono text-purple-600 dark:text-purple-400 mt-1 block" x-text="maxStock.toLocaleString('id-ID')"></span>
                        <span class="text-[10px] text-purple-600/70" x-text="'Batas atas (' + unit + ')'"></span>
                    </div>
                </div>

                <!-- Dampak Keuangan & Ruang Fisik -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-slate-500 block">Proyeksi Modal Kerja Tertahan (Pada MAX):</span>
                        <span class="text-lg font-bold font-mono text-slate-900 dark:text-white mt-0.5 block"
                              x-text="'Rp ' + maxInventoryValue.toLocaleString('id-ID')"></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 block">Proyeksi Ruang Fisik Gudang Terpakai (Pada MAX):</span>
                        <span class="text-lg font-bold font-mono text-slate-900 dark:text-white mt-0.5 block"
                              x-text="maxVolumeM3.toFixed(3) + ' m³'"></span>
                    </div>
                </div>
            </div>

            <!-- Kartu Penjelasan Rumus Transparan -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                    Transparansi Rumus Persediaan (Supply Chain Logic)
                </h4>

                <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 font-mono">
                        <strong class="text-slate-900 dark:text-white">1. Safety Stock (SS):</strong><br>
                        SS = Z &times; &radic;( LT &times; &sigma;&sup2; + &mu;&sup2; &times; &sigma;<sub>LT</sub>&sup2; )<br>
                        <span class="text-emerald-600 dark:text-emerald-400">
                            SS = <span x-text="serviceLevelZ"></span> &times; &radic;( (<span x-text="lt"></span> &times; <span x-text="sigma"></span>&sup2;) + (<span x-text="mu"></span>&sup2; &times; <span x-text="ltStd"></span>&sup2;) ) = <strong x-text="ss"></strong> unit
                        </span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 font-mono">
                        <strong class="text-slate-900 dark:text-white">2. Reorder Point (ROP):</strong><br>
                        ROP = (&mu; &times; LT) + SS<br>
                        <span class="text-emerald-600 dark:text-emerald-400">
                            ROP = (<span x-text="mu"></span> &times; <span x-text="lt"></span>) + <span x-text="ss"></span> = <strong x-text="rop"></strong> unit
                        </span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 font-mono">
                        <strong class="text-slate-900 dark:text-white">3. Maximum Stock (MAX):</strong><br>
                        MAX = ROP + (&mu; &times; Target Cover Days)<br>
                        <span class="text-emerald-600 dark:text-emerald-400">
                            MAX = <span x-text="rop"></span> + (<span x-text="mu"></span> &times; <span x-text="coverDays"></span>) = <strong x-text="maxStock"></strong> unit
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

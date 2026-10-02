@extends('layouts.app', ['title' => 'Catat Gerakan Stok', 'header' => 'Pencatatan Gerakan Stok Baru'])

@section('content')
<div class="max-w-4xl mx-auto space-y-6"
     x-data="{
         selectedId: '{{ old('item_id', $selectedItem?->id ?? '') }}',
         reason: '{{ old('reason', 'RECEIPT') }}',
         adjustmentDirection: '{{ old('adjustment_direction', 'ADD') }}',
         qty: '{{ old('qty', '') }}',
         items: @js($items->mapWithKeys(fn($i) => [$i->id => [
             'id' => $i->id,
             'sku' => $i->sku,
             'name' => $i->name,
             'unit' => $i->unit,
             'stock_on_hand' => (float)$i->stock_on_hand,
             'unit_cost' => (float)$i->unit_cost,
             'volume_m3' => (float)$i->volume_m3,
             'category' => $i->category?->name ?? '-',
             'warehouse' => $i->warehouse?->name ?? '-',
             'rop' => (float)($i->activeParameter?->effective_rop ?? 0),
             'max' => (float)($i->activeParameter?->effective_max ?? 0),
         ]])),

         get currentItem() {
             return this.selectedId ? this.items[this.selectedId] : null;
         },

         get isOutbound() {
             if (this.reason === 'ISSUE') return true;
             if (this.reason === 'ADJUSTMENT' && this.adjustmentDirection === 'SUB') return true;
             return false;
         },

         get stockAfter() {
             if (!this.currentItem) return null;
             let val = parseFloat(this.qty);
             if (isNaN(val) || val <= 0) return this.currentItem.stock_on_hand;
             return this.isOutbound
                 ? this.currentItem.stock_on_hand - val
                 : this.currentItem.stock_on_hand + val;
         },

         get isInsufficient() {
             if (!this.currentItem || !this.isOutbound) return false;
             let val = parseFloat(this.qty);
             return !isNaN(val) && val > this.currentItem.stock_on_hand;
         },

         get isBelowRop() {
             if (!this.currentItem || this.currentItem.rop <= 0) return false;
             let after = this.stockAfter;
             return after !== null && after <= this.currentItem.rop;
         },

         get isAboveMax() {
             if (!this.currentItem || this.currentItem.max <= 0) return false;
             let after = this.stockAfter;
             return after !== null && after > this.currentItem.max;
         }
     }">

    <!-- ── Header ────────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('stock-movements.index') }}"
               class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 mb-1">
                &larr; Kembali ke Buku Besar Stok
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Catat Pergerakan Stok</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Mutasi stok riil akan otomatis memperbarui saldo on-hand dan dicatat ke dalam audit ledger.
            </p>
        </div>
    </div>

    <!-- ── Form & Preview Grid ───────────────────────────────────────────── -->
    <form action="{{ route('stock-movements.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf

        <!-- Kolom Form Input (2 Kolom) -->
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
                    Informasi Transaksi Mutasi
                </h3>

                <!-- Pilih SKU -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Pilih Barang / SKU <span class="text-rose-500">*</span>
                    </label>
                    <select name="item_id" x-model="selectedId" required
                            class="w-full px-3 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">-- Pilih SKU Barang --</option>
                        @foreach($items as $i)
                            <option value="{{ $i->id }}">
                                [{{ $i->sku }}] {{ $i->name }} (Stok: {{ number_format($i->stock_on_hand, 2, ',', '.') }} {{ $i->unit }}) - {{ $i->warehouse?->name ?? 'Gudang' }}
                            </option>
                        @endforeach
                    </select>
                    @error('item_id')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Jenis Transaksi (Reason) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Jenis Mutasi / Alasan Transaksi <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="reason === 'RECEIPT' ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                            <input type="radio" name="reason" value="RECEIPT" x-model="reason" class="text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">📥 Penerimaan (Receipt)</span>
                                <span class="text-[11px] text-slate-500 block">Barang masuk dari PO / supplier</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="reason === 'ISSUE' ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                            <input type="radio" name="reason" value="ISSUE" x-model="reason" class="text-blue-600 focus:ring-blue-500">
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">📤 Pengeluaran (Issue)</span>
                                <span class="text-[11px] text-slate-500 block">Demand pelanggan / konsumsi</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="reason === 'ADJUSTMENT' ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                            <input type="radio" name="reason" value="ADJUSTMENT" x-model="reason" class="text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">⚖️ Penyesuaian (Adjustment)</span>
                                <span class="text-[11px] text-slate-500 block">Koreksi opname / kerusakan fisik</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="reason === 'RETURN' ? 'border-purple-500 bg-purple-50/50 dark:bg-purple-950/20' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
                            <input type="radio" name="reason" value="RETURN" x-model="reason" class="text-purple-600 focus:ring-purple-500">
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">🔄 Retur Gudang (Return)</span>
                                <span class="text-[11px] text-slate-500 block">Pengembalian barang ke stok</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Sub-opsi Khusus Penyesuaian (Adjustment) -->
                <div x-show="reason === 'ADJUSTMENT'" x-cloak class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 space-y-2">
                    <span class="text-xs font-bold text-amber-800 dark:text-amber-300 block">Arah Koreksi Penyesuaian:</span>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-amber-900 dark:text-amber-200">
                            <input type="radio" name="adjustment_direction" value="ADD" x-model="adjustmentDirection" class="text-amber-600">
                            Tambah Stok (+) Fisik Lebih Banyak
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-amber-900 dark:text-amber-200">
                            <input type="radio" name="adjustment_direction" value="SUB" x-model="adjustmentDirection" class="text-amber-600">
                            Kurangi Stok (-) Fisik Hilang/Rusak/Selisih Kurang
                        </label>
                    </div>
                </div>

                <!-- Input Qty & Tanggal Transaksi -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Jumlah (Qty) <span class="text-rose-500">*</span>
                            <span class="text-slate-400 font-normal" x-show="currentItem" x-text="'(' + currentItem.unit + ')'"></span>
                        </label>
                        <input type="number" step="0.001" min="0.001" name="qty" x-model="qty" required
                               placeholder="Contoh: 50"
                               class="w-full px-3 py-2.5 rounded-xl text-sm font-mono font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('qty')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Tanggal Pergerakan <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="movement_date" value="{{ old('movement_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required
                               class="w-full px-3 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('movement_date')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- No Referensi & Catatan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            No. Referensi Dokumen
                        </label>
                        <input type="text" name="reference_no" value="{{ old('reference_no') }}"
                               placeholder="No. PO, Surat Jalan, DO..."
                               class="w-full px-3 py-2.5 rounded-xl text-sm font-mono bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Catatan / Keterangan Tambahan
                        </label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                               placeholder="Alasan selisih, batch no, dsb..."
                               class="w-full px-3 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('stock-movements.index') }}"
                   class="px-4 py-2.5 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    Batal
                </a>
                <button type="submit"
                        :disabled="isInsufficient"
                        :class="isInsufficient ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-emerald-600 hover:bg-emerald-500'"
                        class="px-6 py-2.5 rounded-xl text-xs font-bold text-white shadow-md shadow-emerald-950/20 transition-all focus:ring-2 focus:ring-emerald-400">
                    Simpan Mutasi Stok
                </button>
            </div>
        </div>

        <!-- Kolom Preview Saldo & Kartu Informasi (1 Kolom) -->
        <div class="space-y-5">
            <!-- Kartu Simulasi Saldo On-Hand -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Simulasi Saldo On-Hand
                </h4>

                <template x-if="currentItem">
                    <div class="space-y-3.5">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-xs text-slate-500">Stok Saat Ini:</span>
                            <span class="text-sm font-bold font-mono text-slate-800 dark:text-slate-200"
                                  x-text="currentItem.stock_on_hand.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 3}) + ' ' + currentItem.unit">
                            </span>
                        </div>

                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-xs text-slate-500">Perubahan:</span>
                            <span class="text-sm font-bold font-mono"
                                  :class="isOutbound ? 'text-rose-600' : 'text-emerald-600'"
                                  x-text="(isOutbound ? '-' : '+') + (parseFloat(qty) || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 3}) + ' ' + currentItem.unit">
                            </span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Proyeksi Stok Akhir</span>
                            <span class="text-xl font-bold font-mono mt-0.5 block"
                                  :class="stockAfter < 0 ? 'text-rose-600' : 'text-slate-900 dark:text-white'"
                                  x-text="stockAfter !== null ? stockAfter.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 3}) + ' ' + currentItem.unit : '-'">
                            </span>
                        </div>

                        <!-- Peringatan Validasi Stok Kurang -->
                        <div x-show="isInsufficient" x-cloak class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs">
                            <strong>❌ Stok Tidak Cukup:</strong> Jumlah pengeluaran melebihi sisa fisik di gudang!
                        </div>

                        <!-- Peringatan Di Bawah ROP -->
                        <div x-show="!isInsufficient && isBelowRop" x-cloak class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-xs">
                            <strong>⚠️ Peringatan ROP:</strong> Saldo akhir berada pada atau di bawah Reorder Point (<span x-text="currentItem.rop"></span> unit). Sistem akan memicu Purchase Requisition.
                        </div>

                        <!-- Peringatan Melebihi MAX -->
                        <div x-show="!isInsufficient && isAboveMax" x-cloak class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-xs">
                            <strong>ℹ️ Melebihi MAX:</strong> Saldo akhir akan melebihi Maximum Stock (<span x-text="currentItem.max"></span> unit).
                        </div>
                    </div>
                </template>

                <template x-if="!currentItem">
                    <div class="py-6 text-center text-slate-400 text-xs">
                        Pilih barang di formulir sebelah kiri untuk melihat simulasi saldo.
                    </div>
                </template>
            </div>

            <!-- Kartu Info Parameter Logistik -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-3"
                 x-show="currentItem" x-cloak>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Parameter Logistik Barang
                </h4>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Gudang Penyimpanan:</span>
                        <strong class="text-slate-800 dark:text-slate-200" x-text="currentItem.warehouse"></strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Kategori Barang:</span>
                        <strong class="text-slate-800 dark:text-slate-200" x-text="currentItem.category"></strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Harga Pokok (Unit Cost):</span>
                        <strong class="text-slate-800 dark:text-slate-200" x-text="'Rp ' + currentItem.unit_cost.toLocaleString('id-ID')"></strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Volume Fisik / Unit:</span>
                        <strong class="text-slate-800 dark:text-slate-200" x-text="currentItem.volume_m3 + ' m³'"></strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Reorder Point (ROP):</span>
                        <strong class="text-emerald-600 dark:text-emerald-400 font-mono" x-text="currentItem.rop ? currentItem.rop.toLocaleString('id-ID') + ' ' + currentItem.unit : '-'"></strong>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-500">Target Maksimum (MAX):</span>
                        <strong class="text-slate-800 dark:text-slate-200 font-mono" x-text="currentItem.max ? currentItem.max.toLocaleString('id-ID') + ' ' + currentItem.unit : '-'"></strong>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app', ['title' => 'Tambah Master SKU Baru', 'header' => 'Tambah Master SKU Baru'])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- ── Header Info & Tombol Kembali ──────────────────────────────────── -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Formulir Master SKU Baru</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Tambahkan profil SKU inventaris baru ke sistem PRISM Stock
            </p>
        </div>
        <a href="{{ route('items.index') }}"
           class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors">
            &larr; Kembali ke Daftar
        </a>
    </div>

    <!-- ── Warning Banner Sesuai Spesifikasi ─────────────────────────────── -->
    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-xs flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="space-y-1">
            <span class="font-bold">Catatan Kebijakan Parameter Sistem:</span>
            <p class="text-amber-800 dark:text-amber-300 leading-relaxed">
                Pengaturan <strong>MOQ</strong>, <strong>Lot Size</strong>, <strong>Lead Time</strong>, dan <strong>Volume</strong> akan langsung disimpan sebagai data fisik barang, namun nilai kalkulasi <strong>Safety Stock (SS)</strong>, <strong>Reorder Point (ROP)</strong>, dan <strong>Max Stock (MAX)</strong> dihitung secara otomatis oleh pipeline Machine Learning dan Guardrail pada run terjadwal berikutnya. Nilai tersebut <em>tidak dapat dimanipulasi secara manual</em> dari formulir ini.
            </p>
        </div>
    </div>

    <!-- ── Form Master SKU ───────────────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
        <form action="{{ route('items.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Bagian 1: Identitas & Klasifikasi Fisik -->
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
                    1. Identitas & Lokasi Barang
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                    <!-- SKU -->
                    <div>
                        <label for="sku" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Kode SKU <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               name="sku"
                               id="sku"
                               value="{{ old('sku') }}"
                               placeholder="Contoh: RAW-ALM-001"
                               class="w-full text-xs font-mono rounded-xl border {{ $errors->has('sku') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required maxlength="50">
                        @error('sku')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nama Barang -->
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Nama Barang <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name') }}"
                               placeholder="Contoh: Aluminium Ingot Grade A 99.7%"
                               class="w-full text-xs rounded-xl border {{ $errors->has('name') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required maxlength="200">
                        @error('name')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Kategori -->
                    <div>
                        <label for="category_id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Kategori Barang <span class="text-rose-500">*</span>
                        </label>
                        <select name="category_id"
                                id="category_id"
                                class="w-full text-xs rounded-xl border {{ $errors->has('category_id') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Gudang Penempatan -->
                    <div>
                        <label for="warehouse_id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Gudang Penempatan <span class="text-rose-500">*</span>
                        </label>
                        <select name="warehouse_id"
                                id="warehouse_id"
                                class="w-full text-xs rounded-xl border {{ $errors->has('warehouse_id') ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }} (Kapasitas: {{ format_volume_id($wh->capacity_m3, 0) }})
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Satuan Unit -->
                    <div>
                        <label for="unit" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Satuan Fisik <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               name="unit"
                               id="unit"
                               value="{{ old('unit', 'pcs') }}"
                               placeholder="pcs, kg, liter, drum..."
                               class="w-full text-xs rounded-xl border {{ $errors->has('unit') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required maxlength="30">
                        @error('unit')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Saldo Stok Awal Fisik -->
                    <div>
                        <label for="stock_on_hand" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Saldo Stok Awal Fisik <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               step="0.001"
                               name="stock_on_hand"
                               id="stock_on_hand"
                               value="{{ old('stock_on_hand', '0') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('stock_on_hand') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="0">
                        @error('stock_on_hand')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Unit Cost -->
                    <div>
                        <label for="unit_cost" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Harga Pokok Satuan (Rp) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               name="unit_cost"
                               id="unit_cost"
                               value="{{ old('unit_cost', '0') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('unit_cost') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="0">
                        @error('unit_cost')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Volume Satuan (m3) -->
                    <div>
                        <label for="volume_m3" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Volume per Unit (m&sup3;) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               step="0.0001"
                               name="volume_m3"
                               id="volume_m3"
                               value="{{ old('volume_m3', '0.01') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('volume_m3') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="0.000001">
                        @error('volume_m3')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Deskripsi (Full Width) -->
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="description" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Deskripsi atau Keterangan Teknis
                        </label>
                        <textarea name="description"
                                  id="description"
                                  rows="2"
                                  placeholder="Catatan spesifikasi material, lokasi rak, atau vendor preferensi..."
                                  class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Bagian 2: Parameter Pengadaan & Sanitasi Order -->
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
                    2. Parameter Pengadaan & Logistik
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- MOQ -->
                    <div>
                        <label for="moq" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            MOQ (Min. Order Qty) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               step="0.001"
                               name="moq"
                               id="moq"
                               value="{{ old('moq', '10') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('moq') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="0.001">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Wajib kelipatan Lot Size</span>
                        @error('moq')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Lot Size -->
                    <div>
                        <label for="lot_size" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Lot Size (Kelipatan) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               step="0.001"
                               name="lot_size"
                               id="lot_size"
                               value="{{ old('lot_size', '5') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('lot_size') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="0.001">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Ukuran batch kemasan</span>
                        @error('lot_size')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Lead Time Days -->
                    <div>
                        <label for="lead_time_days" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Lead Time (Hari) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number"
                               name="lead_time_days"
                               id="lead_time_days"
                               value="{{ old('lead_time_days', '15') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('lead_time_days') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="1" max="365">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Default sistem: 15 hari</span>
                        @error('lead_time_days')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Lead Time Std Days -->
                    <div>
                        <label for="lead_time_std_days" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Std Dev Lead Time (&sigma; LT)
                        </label>
                        <input type="number"
                               step="0.01"
                               name="lead_time_std_days"
                               id="lead_time_std_days"
                               value="{{ old('lead_time_std_days', '0.0') }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('lead_time_std_days') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               min="0" max="90">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">0 jika pengiriman stabil</span>
                        @error('lead_time_std_days')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                <a href="{{ route('items.index') }}"
                   class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="px-6 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-colors focus:ring-2 focus:ring-emerald-400 cursor-pointer">
                    Simpan Master SKU
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@extends('layouts.app', ['title' => 'Ubah Master SKU: ' . $item->sku, 'header' => 'Ubah Master SKU: ' . $item->sku])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- ── Header Info & Tombol Navigasi ─────────────────────────────────── -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Ubah Data Master SKU: {{ $item->sku }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Perbarui parameter fisik dan logistik SKU
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('items.show', $item) }}"
               class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors">
                Lihat Detail
            </a>
            <a href="{{ route('items.index') }}"
               class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors">
                &larr; Daftar SKU
            </a>
        </div>
    </div>

    <!-- ── Warning Banner Sesuai Spesifikasi ─────────────────────────────── -->
    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-xs flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="space-y-1">
            <span class="font-bold">Pemberitahuan Efek Perubahan Parameter:</span>
            <p class="text-amber-800 dark:text-amber-300 leading-relaxed">
                Perubahan pada <strong>MOQ</strong>, <strong>Lot Size</strong>, <strong>Lead Time</strong>, dan <strong>Volume</strong> akan langsung tersimpan di master data barang dan mulai berlaku pada <em>perhitungan pipeline ML dan Guardrail berikutnya</em>. Nilai operasional <strong>SS</strong>, <strong>ROP</strong>, dan <strong>MAX</strong> tidak dapat diubah langsung dari formulir ini demi menjamin integritas kalkulasi matematis.
            </p>
        </div>
    </div>

    <!-- ── Readonly Card Parameter Operasional Saat Ini ──────────────────── -->
    <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
        <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-2">
            Parameter Persediaan Aktif Saat Ini (Hanya Dibaca)
        </h4>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-slate-400 block text-[11px]">Safety Stock (SS):</span>
                <span class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-0.5 block">
                    {{ $item->activeParameter ? format_number_id($item->activeParameter->effective_ss) : '-' }}
                </span>
            </div>
            <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-slate-400 block text-[11px]">Reorder Point (ROP):</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm mt-0.5 block">
                    {{ $item->activeParameter ? format_number_id($item->activeParameter->effective_rop) : '-' }}
                </span>
            </div>
            <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-slate-400 block text-[11px]">Max Stock (MAX):</span>
                <span class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-0.5 block">
                    {{ $item->activeParameter ? format_number_id($item->activeParameter->effective_max) : '-' }}
                </span>
            </div>
            <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-slate-400 block text-[11px]">Status Parameter:</span>
                <span class="mt-1 block">
                    @if($item->activeParameter)
                        <x-badge :status="$item->activeParameter->status" size="sm" />
                    @else
                        <span class="text-[11px] text-slate-400">Belum ada</span>
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- ── Form Edit Master SKU ──────────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
        <form action="{{ route('items.update', $item) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Bagian 1: Identitas & Lokasi -->
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
                               value="{{ old('sku', $item->sku) }}"
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
                               value="{{ old('name', $item->name) }}"
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
                                <option value="{{ $cat->id }}" {{ old('category_id', $item->category_id) == $cat->id ? 'selected' : '' }}>
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
                                <option value="{{ $wh->id }}" {{ old('warehouse_id', $item->warehouse_id) == $wh->id ? 'selected' : '' }}>
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
                               value="{{ old('unit', $item->unit) }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('unit') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required maxlength="30">
                        @error('unit')
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
                               value="{{ old('unit_cost', $item->unit_cost) }}"
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
                               value="{{ old('volume_m3', $item->volume_m3) }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('volume_m3') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="0.000001">
                        @error('volume_m3')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status Keaktifan SKU -->
                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active', $item->is_active) ? 'checked' : '' }}
                                   class="rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">SKU Aktif Operasional</span>
                        </label>
                    </div>

                    <!-- Deskripsi (Full Width) -->
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="description" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Deskripsi atau Keterangan Teknis
                        </label>
                        <textarea name="description"
                                  id="description"
                                  rows="2"
                                  class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $item->description) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Bagian 2: Parameter Pengadaan & Logistik -->
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
                               value="{{ old('moq', $item->moq) }}"
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
                               value="{{ old('lot_size', $item->lot_size) }}"
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
                               value="{{ old('lead_time_days', $item->lead_time_days) }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('lead_time_days') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               required min="1" max="365">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Hari kerja pemasok</span>
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
                               value="{{ old('lead_time_std_days', $item->lead_time_std_days) }}"
                               class="w-full text-xs rounded-xl border {{ $errors->has('lead_time_std_days') ? 'border-rose-500 ring-rose-500' : 'border-slate-300 dark:border-slate-700' }} bg-white dark:bg-slate-800 p-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                               min="0" max="90">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">0 jika tidak ada deviasi</span>
                        @error('lead_time_std_days')
                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Tombol Submit & Aksi Nonaktifkan -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    @if($item->is_active)
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'deactivate-sku-modal')"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700 cursor-pointer">
                            &times; Nonaktifkan SKU Ini
                        </button>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('items.show', $item) }}"
                       class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-colors focus:ring-2 focus:ring-emerald-400 cursor-pointer">
                        Perbarui Master SKU
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── Modal Konfirmasi Nonaktifkan SKU ───────────────────────────────── -->
    <x-modal name="deactivate-sku-modal" title="Konfirmasi Penonaktifan Master SKU" maxWidth="md">
        <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
            <p>
                Apakah Anda yakin ingin menonaktifkan SKU <strong class="text-slate-900 dark:text-white font-mono">[{{ $item->sku }}]</strong>?
            </p>
            <p class="text-slate-500">
                Data transaksi, parameter, dan pergerakan historis tidak akan dihapus. SKU yang dinonaktifkan tidak akan disertakan pada run pipeline otomatis berikutnya.
            </p>
            @if($item->stock_on_hand > 0)
                <div class="p-3 rounded-lg bg-rose-50 text-rose-800 font-semibold border border-rose-200">
                    Peringatan: SKU ini masih memiliki saldo stok fisik ({{ format_number_id($item->stock_on_hand) }} {{ $item->unit }}). Penonaktifan akan ditolak oleh sistem sampai stok di-nol-kan.
                </div>
            @endif
        </div>

        <x-slot:footer>
            <button type="button"
                    x-on:click="$dispatch('close-modal', 'deactivate-sku-modal')"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
                Batal
            </button>
            <form action="{{ route('items.destroy', $item) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-500 text-white shadow-xs cursor-pointer"
                        {{ $item->stock_on_hand > 0 ? 'disabled' : '' }}>
                    Ya, Nonaktifkan SKU
                </button>
            </form>
        </x-slot:footer>
    </x-modal>

</div>
@endsection

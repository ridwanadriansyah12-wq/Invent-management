@extends('layouts.app', ['title' => 'Master SKU', 'header' => 'Master SKU & Parameter Inventaris'])

@section('content')
<div class="space-y-5">

    <!-- ── Header Action Bar ─────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Daftar Master SKU</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Monitoring parameter Safety Stock (SS), Reorder Point (ROP), dan Max Stock (MAX) per SKU
            </p>
        </div>
        <div class="flex items-center gap-2">
            @can('manage-sku')
                <a href="{{ route('items.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-950/30 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Tambah SKU Baru</span>
                </a>
            @endcan
        </div>
    </div>

    <!-- ── Filter & Pencarian Bar (Server-Side) ────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm"
         x-data="{ showAdvanced: {{ request()->hasAny(['category_id', 'warehouse_id', 'abc_class', 'xyz_class', 'demand_pattern', 'source', 'param_status']) ? 'true' : 'false' }} }">
        <form method="GET" action="{{ route('items.index') }}" class="space-y-3">
            <div class="flex flex-col md:flex-row items-stretch md:items-center gap-3">
                <!-- Search Input -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari berdasarkan SKU atau Nama barang..."
                           class="w-full pl-9 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <!-- Toggle: Di Bawah ROP Saja -->
                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-rose-200 bg-rose-50/60 dark:bg-rose-950/20 text-rose-800 dark:text-rose-300 text-xs font-semibold cursor-pointer select-none shrink-0">
                    <input type="checkbox"
                           name="below_rop"
                           value="1"
                           {{ request()->boolean('below_rop') ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="rounded text-rose-600 focus:ring-rose-500 w-4 h-4">
                    <span>🚨 Di bawah ROP saja</span>
                </label>

                <!-- Tombol Submit & Toggle Filter Lanjutan -->
                <div class="flex items-center gap-2">
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-500 cursor-pointer">
                        Filter
                    </button>
                    <button type="button"
                            x-on:click="showAdvanced = !showAdvanced"
                            class="px-3 py-2 rounded-xl text-xs font-medium border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 transition-colors cursor-pointer">
                        <span x-text="showAdvanced ? 'Tutup Filter' : 'Filter Lanjutan'"></span>
                    </button>
                    @if(request()->hasAny(['search', 'category_id', 'warehouse_id', 'abc_class', 'xyz_class', 'demand_pattern', 'source', 'param_status', 'below_rop']))
                        <a href="{{ route('items.index') }}"
                           class="px-3 py-2 rounded-xl text-xs font-medium text-slate-500 hover:text-rose-600 hover:underline">
                            Reset
                        </a>
                    @endif
                </div>
            </div>

            <!-- Panel Filter Lanjutan -->
            <div x-show="showAdvanced"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

                <!-- Kategori -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Kategori</label>
                    <select name="category_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Gudang -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Gudang</label>
                    <select name="warehouse_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Kelas ABC -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Kelas ABC</label>
                    <select name="abc_class" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua ABC</option>
                        @foreach(['A', 'B', 'C'] as $cls)
                            <option value="{{ $cls }}" {{ request('abc_class') == $cls ? 'selected' : '' }}>Kelas {{ $cls }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Kelas XYZ -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Kelas XYZ</label>
                    <select name="xyz_class" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua XYZ</option>
                        @foreach(['X', 'Y', 'Z'] as $cls)
                            <option value="{{ $cls }}" {{ request('xyz_class') == $cls ? 'selected' : '' }}>Kelas {{ $cls }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Pola Permintaan -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Pola Demand</label>
                    <select name="demand_pattern" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua Pola</option>
                        <option value="smooth" {{ request('demand_pattern') == 'smooth' ? 'selected' : '' }}>Smooth</option>
                        <option value="intermittent" {{ request('demand_pattern') == 'intermittent' ? 'selected' : '' }}>Intermittent</option>
                        <option value="erratic" {{ request('demand_pattern') == 'erratic' ? 'selected' : '' }}>Erratic</option>
                        <option value="lumpy" {{ request('demand_pattern') == 'lumpy' ? 'selected' : '' }}>Lumpy</option>
                    </select>
                </div>

                <!-- Sumber Parameter -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Sumber Parameter</label>
                    <select name="source" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua Sumber</option>
                        <option value="ML" {{ request('source') == 'ML' ? 'selected' : '' }}>ML (FastAPI)</option>
                        <option value="STATIC_CATEGORY" {{ request('source') == 'STATIC_CATEGORY' ? 'selected' : '' }}>Statis Kategori</option>
                        <option value="FALLBACK_LAST_APPROVED" {{ request('source') == 'FALLBACK_LAST_APPROVED' ? 'selected' : '' }}>Fallback</option>
                    </select>
                </div>

                <!-- Status Parameter -->
                <div class="sm:col-span-2 lg:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Status Parameter</label>
                    <select name="param_status" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-2">
                        <option value="">Semua Status</option>
                        <option value="ACTIVE" {{ request('param_status') == 'ACTIVE' ? 'selected' : '' }}>Aktif (ACTIVE)</option>
                        <option value="PENDING_REVIEW" {{ request('param_status') == 'PENDING_REVIEW' ? 'selected' : '' }}>Perlu Review (PENDING_REVIEW)</option>
                        <option value="APPROVED" {{ request('param_status') == 'APPROVED' ? 'selected' : '' }}>Disetujui (APPROVED)</option>
                        <option value="REJECTED" {{ request('param_status') == 'REJECTED' ? 'selected' : '' }}>Ditolak (REJECTED)</option>
                        <option value="SUPERSEDED" {{ request('param_status') == 'SUPERSEDED' ? 'selected' : '' }}>Tergantikan (SUPERSEDED)</option>
                        <option value="NONE" {{ request('param_status') == 'NONE' ? 'selected' : '' }}>Belum Ada Parameter</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- ── Data Table Master SKU ─────────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto table-scroll-container w-full">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 whitespace-nowrap">SKU</th>
                        <th class="px-4 py-3.5">Nama Barang</th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Kategori</th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Gudang</th>
                        <th class="px-4 py-3.5 text-center whitespace-nowrap">Kelas</th>
                        <th class="px-4 py-3.5 hidden lg:table-cell whitespace-nowrap">Pola</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">On Hand</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">Inv. Position</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">ROP</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">SS</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">MAX</th>
                        <th class="px-4 py-3.5 hidden lg:table-cell whitespace-nowrap">Sumber</th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Status Parameter</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-900">
                    @forelse($items as $item)
                        @php
                            $param = $item->activeParameter;
                            $classification = $item->classification;
                            $effectiveRop = $param ? (float)$param->effective_rop : 0;
                            $invPosition = (float)($item->inventory_position_calc ?? $item->inventory_position);
                            $isBelowRop = $param && $effectiveRop > 0 && ($invPosition <= $effectiveRop);
                            $abcXyz = ($classification?->abc_class ?? '-') . ($classification?->xyz_class ?? '-');
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors {{ $isBelowRop ? 'bg-rose-50/30 dark:bg-rose-950/15' : '' }}">
                            <!-- SKU -->
                            <td class="px-4 py-3 font-mono font-bold text-xs text-slate-900 dark:text-white whitespace-nowrap">
                                <a href="{{ route('items.show', $item) }}" class="hover:text-emerald-600 hover:underline">
                                    {{ $item->sku }}
                                </a>
                            </td>

                            <!-- Nama & Peringatan Jika di bawah ROP -->
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 max-w-xs">
                                <div class="truncate" title="{{ $item->name }}">{{ $item->name }}</div>
                                @if($isBelowRop)
                                    <div class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 dark:text-rose-400">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>Di bawah ROP</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Kategori -->
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                {{ $item->category?->name ?? '-' }}
                            </td>

                            <!-- Gudang -->
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                {{ $item->warehouse?->name ?? '-' }}
                            </td>

                            <!-- Kelas ABC-XYZ -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($classification)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                        {{ $abcXyz }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>

                            <!-- Pola Permintaan -->
                            <td class="px-4 py-3 hidden lg:table-cell text-xs capitalize text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                {{ $classification?->demand_pattern ?? '-' }}
                            </td>

                            <!-- On Hand -->
                            <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                {{ format_number_id($item->stock_on_hand) }} <span class="text-[11px] text-slate-400">{{ $item->unit }}</span>
                            </td>

                            <!-- Inventory Position -->
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $isBelowRop ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
                                {{ format_number_id($invPosition) }}
                            </td>

                            <!-- ROP -->
                            <td class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                {{ $param ? format_number_id($param->effective_rop) : '-' }}
                            </td>

                            <!-- SS -->
                            <td class="px-4 py-3 text-right text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                {{ $param ? format_number_id($param->effective_ss) : '-' }}
                            </td>

                            <!-- MAX -->
                            <td class="px-4 py-3 text-right text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                {{ $param ? format_number_id($param->effective_max) : '-' }}
                            </td>

                            <!-- Sumber Parameter -->
                            <td class="px-4 py-3 hidden lg:table-cell text-xs whitespace-nowrap">
                                @if($param)
                                    <span class="text-[11px] font-mono px-2 py-0.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ $param->source }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            <!-- Status Parameter Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($param)
                                    <x-badge :status="$param->status" />
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        <span>Belum ada parameter</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('items.show', $item) }}"
                                       class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 hover:underline">
                                        Detail &rarr;
                                    </a>
                                    @can('manage-sku')
                                        <a href="{{ route('items.edit', $item) }}"
                                           class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1"
                                           title="Edit SKU">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <span class="font-medium text-slate-600 dark:text-slate-300">Tidak ada SKU yang cocok dengan filter.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($items->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $items->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

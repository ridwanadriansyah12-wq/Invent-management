<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — PRISM Stock</title>
    <meta name="description" content="PRISM Stock: Predictive Reorder & Inventory Safety Management Enterprise ML System">

    <!-- Fonts: Inter via Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="h-full antialiased text-slate-800 dark:text-slate-100"
      x-data="{
          sidebarOpen: false,
          sidebarCollapsed: false,
          pendingReviews: {{ $navPendingReviewsCount ?? 0 }},
          unresolvedAlerts: {{ $navUnresolvedAlertsCount ?? 0 }},
          openPrs: {{ $navOpenPrCount ?? 0 }},
          pollCounters() {
              fetch('{{ route('nav.counters') }}', { credentials: 'same-origin' })
                  .then(r => r.json())
                  .then(data => {
                      if (data.pending_reviews !== undefined) this.pendingReviews = data.pending_reviews;
                      if (data.unresolved_alerts !== undefined) this.unresolvedAlerts = data.unresolved_alerts;
                      if (data.open_prs !== undefined) this.openPrs = data.open_prs;
                  }).catch(() => {});
          }
      }"
      x-init="setInterval(() => pollCounters(), 60000)">

    <div class="min-h-full flex">
        <!-- ── Mobile Sidebar Backdrop ──────────────────────────────────────── -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-950/70 z-40 lg:hidden backdrop-blur-xs"
             x-on:click="sidebarOpen = false"
             x-cloak
             aria-hidden="true"></div>

        <!-- ── SIDEBAR ──────────────────────────────────────────────────────── -->
        <aside :class="{
                   'translate-x-0': sidebarOpen,
                   '-translate-x-full': !sidebarOpen,
                   'lg:w-64': !sidebarCollapsed,
                   'lg:w-20': sidebarCollapsed
               }"
               class="fixed inset-y-0 left-0 z-50 flex flex-col bg-slate-900 border-r border-slate-800 text-slate-200 transition-all duration-300 ease-in-out lg:static lg:translate-x-0">

            <!-- Logo & Brand Header -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800 bg-slate-950/40">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-lg p-1">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center font-bold text-white shadow-md shadow-emerald-900/30 shrink-0">
                        <span>P</span>
                    </div>
                    <div x-show="!sidebarCollapsed" class="flex flex-col whitespace-nowrap transition-opacity duration-200">
                        <span class="font-bold text-base tracking-wide text-white leading-tight">PRISM Stock</span>
                        <span class="text-[10px] text-emerald-400 font-medium tracking-wider uppercase">ML Inventory OS</span>
                    </div>
                </a>

                <!-- Collapse Toggle for Desktop / Tablet -->
                <button type="button"
                        x-on:click="sidebarCollapsed = !sidebarCollapsed"
                        class="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        :title="sidebarCollapsed ? 'Perluas Sidebar' : 'Perkecil Sidebar'"
                        aria-label="Toggle collapse sidebar">
                    <svg class="w-5 h-5 transition-transform duration-200" :class="{ 'rotate-180': sidebarCollapsed }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Close button for Mobile Drawer -->
                <button type="button"
                        x-on:click="sidebarOpen = false"
                        class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        aria-label="Tutup menu navigasi">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- User Info Badge in Sidebar -->
            <div class="px-4 py-3 border-b border-slate-800/80 bg-slate-900/60" x-show="!sidebarCollapsed">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-emerald-400 shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="overflow-hidden">
                        <div class="text-xs font-semibold text-white truncate">{{ auth()->user()->name ?? 'Pengguna' }}</div>
                        <div class="text-[11px] text-slate-400">
                            @if(auth()->user()->isAdmin())
                                <span class="text-purple-400 font-medium">Administrator</span>
                            @elseif(auth()->user()->isApprover())
                                <span class="text-blue-400 font-medium">Approver</span>
                            @else
                                <span class="text-slate-300 font-medium">Staff Gudang</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5 focus:outline-none" aria-label="Menu Utama">

                <!-- 1. Dashboard (All roles) -->
                @php $active = request()->routeIs('dashboard'); @endphp
                <a href="{{ route('dashboard') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? '{{ __('nav.dashboard') }}' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.dashboard') }}</span>
                </a>

                <!-- 2. Master SKU (All roles - staff read-only) -->
                @php $active = request()->routeIs('items.*'); @endphp
                <a href="{{ Route::has('items.index') ? route('items.index') : '#' }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? '{{ __('nav.sku_master') }}' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.sku_master') }}</span>
                </a>

                <!-- 3. Pergerakan Stok (All roles) -->
                @php $active = request()->routeIs('stock-movements.*') || request()->routeIs('transactions.*'); @endphp
                <a href="{{ route('stock-movements.index') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? 'Pergerakan Stok' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Pergerakan Stok</span>
                </a>

                <!-- 4. Kapasitas Gudang (All roles) -->
                @php $active = request()->routeIs('warehouses.*') || request()->routeIs('capacity.*'); @endphp
                <a href="{{ route('warehouses.index') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? 'Kapasitas Gudang' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Kapasitas Gudang</span>
                </a>

                <!-- 5. Diagnostik Kesehatan Persediaan (All roles) -->
                @php $active = request()->routeIs('inventory.health'); @endphp
                <a href="{{ route('inventory.health') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? 'Kesehatan Persediaan' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Kesehatan Stok</span>
                </a>

                <!-- 6. Simulator Kebijakan Persediaan (All roles) -->
                @php $active = request()->routeIs('inventory.simulator'); @endphp
                <a href="{{ route('inventory.simulator') }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? 'Simulator Kebijakan' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-emerald-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Simulator Kebijakan</span>
                </a>

                <!-- Divider untuk Menu Otorisasi Lebih Tinggi -->
                @can('review-parameters')
                <div class="pt-3 pb-1" x-show="!sidebarCollapsed">
                    <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Otorisasi & Review</div>
                </div>

                <!-- 5. Antrean Review (Approver & Admin) -->
                @php $active = request()->routeIs('review.*') || request()->routeIs('inventory-parameters.review'); @endphp
                <a href="{{ Route::has('review.index') ? route('review.index') : '#' }}"
                   class="group flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? '{{ __('nav.review_queue') }}' : ''">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.review_queue') }}</span>
                    </div>
                    <!-- Badge Counter Pending Review -->
                    <template x-if="pendingReviews > 0">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40"
                              :class="{ 'hidden': sidebarCollapsed }">
                            <span x-text="pendingReviews"></span>
                            <span class="sr-only">item pending review</span>
                        </span>
                    </template>
                </a>

                <!-- 6. Purchase Requisition (Approver & Admin) -->
                @php $active = request()->routeIs('purchase-requisitions.*'); @endphp
                <a href="{{ Route::has('purchase-requisitions.index') ? route('purchase-requisitions.index') : '#' }}"
                   class="group flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? '{{ __('nav.purchase_requisitions') }}' : ''">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-blue-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.purchase_requisitions') }}</span>
                    </div>
                    <template x-if="openPrs > 0">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/40"
                              :class="{ 'hidden': sidebarCollapsed }">
                            <span x-text="openPrs"></span>
                            <span class="sr-only">PR terbuka</span>
                        </span>
                    </template>
                </a>

                <!-- 7. Status Sistem & Alerts (Approver & Admin) -->
                @php $active = request()->routeIs('system-status.*'); @endphp
                <a href="{{ Route::has('system-status.index') ? route('system-status.index') : '#' }}"
                   class="group flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? '{{ __('nav.system_status') }}' : ''">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-teal-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.system_status') }}</span>
                    </div>
                    <!-- Badge Counter Alert Belum Resolved -->
                    <template x-if="unresolvedAlerts > 0">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/40"
                              :class="{ 'hidden': sidebarCollapsed }">
                            <span x-text="unresolvedAlerts"></span>
                            <span class="sr-only">alert aktif</span>
                        </span>
                    </template>
                </a>
                @endcan

                <!-- Menu Khusus Administrator -->
                @can('manage-settings')
                <div class="pt-3 pb-1" x-show="!sidebarCollapsed">
                    <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Administrasi</div>
                </div>

                <!-- 8. Pengaturan Sistem (Admin Only) -->
                @php $active = request()->routeIs('settings.*'); @endphp
                <a href="{{ Route::has('settings.index') ? route('settings.index') : '#' }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? '{{ __('nav.settings') }}' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-purple-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.settings') }}</span>
                </a>

                <!-- 9. Kelola Pengguna (Admin Only) -->
                @php $active = request()->routeIs('users.*'); @endphp
                <a href="{{ Route::has('users.index') ? route('users.index') : '#' }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-950/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/70' }}"
                   :title="sidebarCollapsed ? 'Kelola Pengguna' : ''">
                    <svg class="w-5 h-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-purple-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Kelola Pengguna</span>
                </a>
                @endcan

            </nav>

            <!-- Action Section & Logout Footer -->
            <div class="p-3 border-t border-slate-800 space-y-2 bg-slate-950/40">

                <!-- Admin Action: Run Pipeline Now Button -->
                @can('run-pipeline')
                <div x-show="!sidebarCollapsed">
                    <form action="{{ route('pipeline.run-now') }}" method="POST" onsubmit="return confirm('Jalankan kalkulasi pipeline harian persediaan sekarang?');">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-950/50 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400 cursor-pointer">
                            <svg class="w-4 h-4 animate-spin-hover" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('nav.pipeline_run_now') }}</span>
                        </button>
                    </form>
                </div>
                @endcan

                <!-- Logout Form (POST + CSRF) -->
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-rose-400 hover:text-white hover:bg-rose-950/40 transition-colors focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer"
                            :title="sidebarCollapsed ? '{{ __('nav.logout') }}' : ''">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="truncate">{{ __('nav.logout') }}</span>
                    </button>
                </form>

            </div>

        </aside>

        <!-- ── MAIN CONTENT AREA ────────────────────────────────────────────── -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            <!-- Top Header Bar -->
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 z-10">
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger Button -->
                    <button type="button"
                            x-on:click="sidebarOpen = true"
                            class="lg:hidden p-2 rounded-lg text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            aria-label="Buka menu navigasi">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                        {{ $header ?? $title ?? 'Dashboard' }}
                    </h1>
                </div>

                <div class="flex items-center gap-3 sm:gap-4">
                    <!-- Quick Alert Pill if unresolved alerts exist -->
                    <template x-if="unresolvedAlerts > 0">
                        <a href="{{ Route::has('system-status.index') ? route('system-status.index') : '#' }}"
                           class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition focus:outline-none focus:ring-2 focus:ring-rose-500">
                            <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span>
                            <span x-text="unresolvedAlerts"></span> <span>Alert Perlu Perhatian</span>
                        </a>
                    </template>

                    <!-- User Profile Dropdown / Indicator -->
                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200 dark:border-slate-800">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-950 border border-emerald-300 dark:border-emerald-800 flex items-center justify-center font-bold text-xs text-emerald-800 dark:text-emerald-300">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="hidden md:flex flex-col text-left">
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ auth()->user()->name ?? 'Pengguna' }}</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 capitalize">{{ auth()->user()->role ?? 'staff' }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Messages Notification Banner -->
            <div class="px-4 sm:px-6 pt-4">
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 flex items-center justify-between text-sm shadow-xs">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ session('success') }}</span>
                        </div>
                        <button type="button" x-on:click="show = false" class="text-emerald-600 hover:text-emerald-900 p-1 rounded-lg" aria-label="Tutup pesan">
                            &times;
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-200 flex items-center justify-between text-sm shadow-xs">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>{{ session('error') }}</span>
                        </div>
                        <button type="button" x-on:click="show = false" class="text-rose-600 hover:text-rose-900 p-1 rounded-lg" aria-label="Tutup pesan">
                            &times;
                        </button>
                    </div>
                @endif

                @if($errors->any())
                    <div x-data="{ show: true }" x-show="show" class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-sm shadow-xs">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <div class="font-semibold mb-1">Terdapat beberapa kesalahan validasi:</div>
                                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                                        @foreach($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <button type="button" x-on:click="show = false" class="text-amber-600 hover:text-amber-900 p-1 rounded-lg" aria-label="Tutup pesan">
                                &times;
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Page Body Slot -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                @if(isset($slot) && $slot->isNotEmpty())
                    {{ $slot }}
                @else
                    @yield('content')
                @endif
            </main>

            <!-- Global Footer -->
            <footer class="py-3 px-6 text-center text-xs text-slate-400 dark:text-slate-500 border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50">
                PRISM Stock &copy; {{ date('Y') }} — Predictive Reorder & Inventory Safety Management.
            </footer>

        </div>
    </div>

    @stack('scripts')
</body>
</html>

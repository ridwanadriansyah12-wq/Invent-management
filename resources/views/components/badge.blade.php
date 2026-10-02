@props(['status', 'label' => null, 'size' => 'md'])

@php
    $key = strtoupper((string) $status);
    $text = $label ?? __('status.' . $key);

    // Pemetaan warna status sesuai panduan desain & spesifikasi PRISM
    $classes = match($key) {
        'ACTIVE', 'APPROVED', 'COMPLETED' => 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
        'PENDING_REVIEW', 'OPEN' => 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
        'REJECTED', 'CANCELLED', 'FAILED' => 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
        'SUPERSEDED' => 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        'STATIC_CATEGORY' => 'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800',
        'FALLBACK_LAST_APPROVED' => 'bg-orange-50 text-orange-900 border-orange-300 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-800',
        'WAREHOUSE_OVER_CAPACITY' => 'bg-red-900 text-red-100 border-red-950 shadow-sm font-semibold',
        'ORDERED' => 'bg-blue-50 text-blue-800 border-blue-300 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
        'RECEIVED' => 'bg-teal-50 text-teal-800 border-teal-300 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800',
        'IN' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'OUT' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'ADJUSTMENT' => 'bg-purple-50 text-purple-700 border-purple-200',
        'ADMIN' => 'bg-purple-100 text-purple-900 border-purple-300 font-semibold',
        'APPROVER' => 'bg-blue-100 text-blue-900 border-blue-300 font-semibold',
        'STAFF' => 'bg-slate-100 text-slate-800 border-slate-300 font-medium',
        default => 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-800 dark:text-gray-300',
    };

    $sizeClasses = match($size) {
        'sm' => 'text-[11px] px-2 py-0.5',
        'lg' => 'text-sm px-3.5 py-1.5',
        default => 'text-xs px-2.5 py-1',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-medium rounded-full border {$classes} {$sizeClasses}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
    <span>{{ $text }}</span>
</span>

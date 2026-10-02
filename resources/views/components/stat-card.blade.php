@props([
    'title',
    'value',
    'subtitle' => null,
    'icon' => null,
    'color' => 'slate', // slate, emerald, amber, rose, blue, purple
    'href' => null,
])

@php
    $colorMap = [
        'slate' => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
        'rose' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300',
        'blue' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
        'purple' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300',
    ];
    $iconBg = $colorMap[$color] ?? $colorMap['slate'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                {{ $title }}
            </p>
            <h3 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white mt-1.5">
                {{ $value }}
            </h3>
        </div>
        @if ($icon)
            <div class="p-2.5 rounded-lg border {{ $iconBg }} flex items-center justify-center shrink-0">
                {{ $icon }}
            </div>
        @endif
    </div>

    @if ($subtitle || $href)
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            @if ($subtitle)
                <span>{{ $subtitle }}</span>
            @endif
            @if ($href)
                <a href="{{ $href }}" class="font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 inline-flex items-center gap-1 ml-auto">
                    Lihat detail &rarr;
                </a>
            @endif
        </div>
    @endif
</div>

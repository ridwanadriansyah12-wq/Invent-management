@props([
    'headers' => [],
    'emptyMessage' => 'Tidak ada data yang tersedia.',
    'isEmpty' => false,
])

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
    @if(isset($headerActions))
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/50">
            {{ $headerActions }}
        </div>
    @endif

    <div class="overflow-x-auto table-scroll-container w-full">
        <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200 text-xs font-semibold uppercase tracking-wider">
                <tr>
                    @foreach($headers as $header)
                        <th scope="col" class="px-4 py-3.5 whitespace-nowrap {{ is_array($header) ? ($header['class'] ?? '') : '' }}">
                            {{ is_array($header) ? ($header['label'] ?? '') : $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-slate-900">
                @if($isEmpty)
                    <tr>
                        <td colspan="{{ count($headers) ?: 10 }}" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <span class="font-medium text-slate-600 dark:text-slate-300">{{ $emptyMessage }}</span>
                            </div>
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @if(isset($pagination))
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            {{ $pagination }}
        </div>
    @endif
</div>

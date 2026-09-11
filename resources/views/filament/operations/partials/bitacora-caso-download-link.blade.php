@php
    $available = (bool) ($available ?? false);
    $url = filled($url ?? null) ? (string) $url : null;
    $filename = filled($filename ?? null) && ($filename ?? '') !== '—' ? (string) $filename : null;
@endphp

@if ($available && filled($url))
    <a
        href="{{ $url }}"
        @if (filled($filename)) download="{{ $filename }}" @endif
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center justify-center gap-1.5 rounded-full border border-cyan-400/50 bg-cyan-500/10 px-3 py-1.5 text-xs font-bold text-cyan-800 transition hover:bg-cyan-500/20 dark:border-cyan-400/40 dark:text-cyan-200 dark:hover:bg-cyan-500/25"
    >
        <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-3.5" />
        Descargar
    </a>
@else
    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">
        Sin archivo
    </span>
@endif

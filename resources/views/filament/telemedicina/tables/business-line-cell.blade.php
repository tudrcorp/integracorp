@php
    /** @var \App\Models\TelemedicineCase $record */
    $record = $getRecord();
    $patient = $record->telemedicinePatient;
    $line = trim((string) ($patient?->businessLine?->definition ?? ''));
    $unit = trim((string) ($patient?->specific_business_unit ?? ''));
    $upperLine = mb_strtoupper($line);

    [$lineIcon, $lineClasses] = match (true) {
        str_contains($upperLine, 'CORPORATIV') => [
            'heroicon-m-building-office-2',
            'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-400/30',
        ],
        str_contains($upperLine, 'INDIVIDUAL') => [
            'heroicon-m-user',
            'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/15 dark:text-teal-200 dark:ring-teal-400/30',
        ],
        default => [
            'heroicon-m-briefcase',
            'bg-slate-100 text-slate-700 ring-slate-600/15 dark:bg-white/10 dark:text-slate-200 dark:ring-white/15',
        ],
    };
@endphp

<div class="flex min-w-0 flex-col items-start gap-1 px-3 py-3">
    @if ($line === '')
        <span class="text-xs text-slate-400 dark:text-slate-500">Sin línea de negocio</span>
    @else
        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $lineClasses }}">
            <x-filament::icon :icon="$lineIcon" class="size-3.5 shrink-0" />
            {{ $line }}
        </span>
    @endif

    @if ($unit !== '')
        <span
            class="inline-flex max-w-[13rem] items-center gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
            title="Unidad de negocio específica: {{ $unit }}"
        >
            <x-filament::icon icon="heroicon-m-building-storefront" class="size-3.5 shrink-0 text-slate-400 dark:text-slate-500" />
            <span class="truncate">{{ $unit }}</span>
        </span>
    @endif
</div>

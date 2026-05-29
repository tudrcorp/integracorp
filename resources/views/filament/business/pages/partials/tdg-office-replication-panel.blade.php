@php
    $sourceDateLabel = \Carbon\Carbon::parse($selectedDate)->translatedFormat('d M Y');
    $selectedReplicationCount = count($officeReplicationDates);
@endphp

<section class="mt-6 rounded-2xl border border-[#7BBDE8]/50 bg-gradient-to-br from-[#BDD8E9]/35 via-white to-white p-4 shadow-sm dark:border-[#4E8EA2]/40 dark:from-[#0A4174]/25 dark:via-slate-900/80 dark:to-slate-950/60">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#0A4174] dark:text-[#BDD8E9]">
                Replicar oficinas
            </p>
            <h4 class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                Copia la configuración de este día a otros días del mes
            </h4>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                Origen: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $sourceDateLabel }}</span>.
                Selecciona los días destino en el mini calendario (por ejemplo, lunes, miércoles y viernes).
            </p>
        </div>

        @if ($selectedReplicationCount > 0)
            <button
                type="button"
                wire:click="clearOfficeReplicationDates"
                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200/80 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
            >
                Limpiar selección
            </button>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap gap-1.5">
        <p class="w-full text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
            Atajos por día de la semana
        </p>
        @foreach ([
            ['iso' => 1, 'label' => 'Lun'],
            ['iso' => 2, 'label' => 'Mar'],
            ['iso' => 3, 'label' => 'Mié'],
            ['iso' => 4, 'label' => 'Jue'],
            ['iso' => 5, 'label' => 'Vie'],
            ['iso' => 6, 'label' => 'Sáb'],
            ['iso' => 7, 'label' => 'Dom'],
        ] as $weekday)
            <button
                type="button"
                wire:click="toggleOfficeReplicationWeekday({{ $weekday['iso'] }})"
                class="inline-flex min-w-[2.75rem] items-center justify-center rounded-full border border-slate-200/80 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition hover:border-[#7BBDE8] hover:bg-[#BDD8E9]/50 dark:border-white/10 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:border-[#6EA2B3] dark:hover:bg-[#0A4174]/35"
            >
                {{ $weekday['label'] }}
            </button>
        @endforeach
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
        <div class="flex items-center justify-between gap-2">
            <button
                type="button"
                wire:click="previousOfficeReplicationMonth"
                class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200/80 text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800"
                aria-label="Mes anterior"
            >
                <x-filament::icon icon="heroicon-m-chevron-left" class="size-4" />
            </button>

            <p class="text-sm font-semibold capitalize text-slate-900 dark:text-slate-100">
                {{ $this->officeReplicationMonthLabel }}
            </p>

            <button
                type="button"
                wire:click="nextOfficeReplicationMonth"
                class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200/80 text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800"
                aria-label="Mes siguiente"
            >
                <x-filament::icon icon="heroicon-m-chevron-right" class="size-4" />
            </button>
        </div>

        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $weekdayLabel)
                <span>{{ $weekdayLabel }}</span>
            @endforeach
        </div>

        <div class="mt-1 grid grid-cols-7 gap-1">
            @foreach ($this->officeReplicationCalendarDays as $day)
                @php
                    $isSource = $day['is_source_day'];
                    $isSelected = $day['is_selected'];
                    $isDisabled = $day['is_disabled'];
                @endphp
                <button
                    type="button"
                    wire:key="office-replication-day-{{ $day['date'] }}"
                    @disabled($isDisabled)
                    wire:click="toggleOfficeReplicationDate('{{ $day['date'] }}')"
                    @class([
                        'relative flex aspect-square items-center justify-center rounded-xl text-xs font-semibold transition',
                        'cursor-not-allowed opacity-40' => $isDisabled,
                        'border-2 border-[#0A4174] bg-[#0A4174] text-[#BDD8E9] shadow-sm dark:border-[#7BBDE8] dark:bg-[#0A4174]' => $isSource,
                        'border-2 border-[#4E8EA2] bg-[#BDD8E9] text-[#0A4174] shadow-[0_4px_12px_rgba(78,142,162,0.25)] dark:border-[#7BBDE8] dark:bg-[#4E8EA2]/40 dark:text-[#BDD8E9]' => $isSelected && ! $isSource,
                        'border border-transparent text-slate-400 hover:bg-slate-100 dark:text-slate-500 dark:hover:bg-white/5' => ! $day['is_current_month'] && ! $isSelected && ! $isSource && ! $isDisabled,
                        'border border-slate-200/60 bg-white text-slate-800 hover:border-[#7BBDE8] hover:bg-[#BDD8E9]/40 dark:border-white/10 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:border-[#6EA2B3]' => $day['is_current_month'] && ! $isSelected && ! $isSource && ! $isDisabled,
                    ])
                >
                    {{ $day['day_number'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-600 dark:text-slate-400">
            @if ($selectedReplicationCount > 0)
                <span class="font-semibold text-[#0A4174] dark:text-[#BDD8E9]">{{ $selectedReplicationCount }}</span>
                {{ $selectedReplicationCount === 1 ? 'día seleccionado' : 'días seleccionados' }} para replicar.
            @else
                Selecciona uno o más días en el calendario o usa los atajos Lun–Dom.
            @endif
        </p>

        <button
            type="button"
            wire:click="replicateOfficeAssignmentsToSelectedDays"
            wire:loading.attr="disabled"
            wire:target="replicateOfficeAssignmentsToSelectedDays"
            @disabled($selectedReplicationCount === 0)
            class="inline-flex items-center justify-center rounded-xl bg-[#0A4174] px-4 py-2 text-sm font-semibold text-[#BDD8E9] transition hover:bg-[#49769F] disabled:cursor-not-allowed disabled:opacity-50"
        >
            <x-filament::icon icon="heroicon-o-document-duplicate" wire:loading.remove wire:target="replicateOfficeAssignmentsToSelectedDays" class="mr-1.5 size-4" />
            <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="replicateOfficeAssignmentsToSelectedDays" class="mr-1.5 size-4 animate-spin" />
            Replicar configuración
        </button>
    </div>
</section>

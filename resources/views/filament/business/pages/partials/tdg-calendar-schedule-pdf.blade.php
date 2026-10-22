@php
    $pdfMonth = \Carbon\Carbon::parse($this->cursorMonth !== '' ? $this->cursorMonth : now()->toDateString())->format('Y-m');
    $pdfKind = $this->tdgSchedulePdfKind();
    $pdfEveryone = $this->canDownloadEntireTdgSchedule();
    $pdfPeople = $pdfEveryone ? $this->tdgSchedulePdfPeople() : [];
    $pdfOwnUrl = $this->tdgOwnSchedulePdfUrl();
@endphp

<div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
    @if ($pdfEveryone)
        <a
            href="{{ route('tdg-calendar.schedule-pdf', ['tipo' => $pdfKind, 'mes' => $pdfMonth]) }}"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-white/5"
        >
            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-4" />
            <span>Descargar todos</span>
        </a>

        @if ($pdfPeople !== [])
            <form method="GET" action="{{ route('tdg-calendar.schedule-pdf') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tipo" value="{{ $pdfKind }}">
                <input type="hidden" name="mes" value="{{ $pdfMonth }}">
                <label class="sr-only" for="tdg-schedule-pdf-person-{{ $pdfKind }}">Colaborador</label>
                <select
                    id="tdg-schedule-pdf-person-{{ $pdfKind }}"
                    name="colaborador"
                    required
                    class="max-w-64 rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                >
                    <option value="">Una persona…</option>
                    @foreach ($pdfPeople as $person)
                        <option value="{{ $person['id'] }}">{{ $person['name'] }}</option>
                    @endforeach
                </select>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-xl border border-cyan-200/80 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-100 dark:hover:bg-cyan-500/25"
                >
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-4" />
                    <span>Descargar esta persona</span>
                </button>
            </form>
        @endif
    @elseif ($pdfOwnUrl !== null)
        <a
            href="{{ $pdfOwnUrl }}"
            class="inline-flex items-center gap-2 rounded-xl border border-cyan-200/80 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-100 dark:hover:bg-cyan-500/25"
        >
            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-4" />
            <span>Descargar mi cronograma</span>
        </a>
    @endif
</div>

@php
    $entries = $entries ?? [];
    $caseCode = (string) ($caseCode ?? '—');
    $total = (int) ($total ?? count($entries));
    $withDocument = (int) ($withDocument ?? 0);

    $vitalSigns = static fn (array $entry): array => [
        ['label' => 'PA', 'value' => $entry['pa'], 'hint' => 'mmHg', 'tone' => 'rose'],
        ['label' => 'FC', 'value' => $entry['fc'], 'hint' => 'lpm', 'tone' => 'sky'],
        ['label' => 'FR', 'value' => $entry['fr'], 'hint' => 'rpm', 'tone' => 'cyan'],
        ['label' => 'Temp', 'value' => $entry['temp'], 'hint' => '°C', 'tone' => 'amber'],
        ['label' => 'Sat', 'value' => $entry['saturacion'], 'hint' => '%', 'tone' => 'emerald'],
        ['label' => 'Peso', 'value' => $entry['peso'], 'hint' => '', 'tone' => 'violet'],
        ['label' => 'Estatura', 'value' => $entry['estatura'], 'hint' => '', 'tone' => 'indigo'],
        ['label' => 'IMC', 'value' => $entry['imc'], 'hint' => '', 'tone' => 'fuchsia'],
    ];

    $toneClasses = [
        'rose' => 'border-rose-200/80 bg-rose-50/70 dark:border-rose-500/25 dark:bg-rose-950/25',
        'sky' => 'border-sky-200/80 bg-sky-50/70 dark:border-sky-500/25 dark:bg-sky-950/25',
        'cyan' => 'border-cyan-200/80 bg-cyan-50/70 dark:border-cyan-500/25 dark:bg-cyan-950/25',
        'amber' => 'border-amber-200/80 bg-amber-50/70 dark:border-amber-500/25 dark:bg-amber-950/25',
        'emerald' => 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/25 dark:bg-emerald-950/25',
        'violet' => 'border-violet-200/80 bg-violet-50/70 dark:border-violet-500/25 dark:bg-violet-950/25',
        'indigo' => 'border-indigo-200/80 bg-indigo-50/70 dark:border-indigo-500/25 dark:bg-indigo-950/25',
        'fuchsia' => 'border-fuchsia-200/80 bg-fuchsia-50/70 dark:border-fuchsia-500/25 dark:bg-fuchsia-950/25',
    ];
@endphp

<div class="fi-scoped space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-sky-200/80 bg-sky-50/80 px-4 py-3 dark:border-sky-500/25 dark:bg-sky-950/30">
            <p class="text-xs font-medium uppercase tracking-wide text-sky-700/80 dark:text-sky-300/80">Caso</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $caseCode }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Informes AMD</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $total }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 dark:border-emerald-500/25 dark:bg-emerald-950/20">
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Documentos disponibles</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $withDocument }}</p>
        </div>
    </div>

    @if ($total === 0)
        <div class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/70 px-6 py-10 text-center dark:border-white/15 dark:bg-white/[0.03]">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Sin bitácoras AMD registradas</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Los informes generados desde la consulta AMD aparecerán aquí.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($entries as $entry)
                <article class="overflow-hidden rounded-[1.5rem] border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:bg-gray-900/80 dark:ring-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800 dark:bg-sky-500/20 dark:text-sky-200">
                                    AMD #{{ $entry['id'] }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-white/10 dark:text-slate-300">
                                    {{ $entry['document_extension'] }}
                                </span>
                                @if ($entry['document_exists'])
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200">
                                        Disponible
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-500/20 dark:text-rose-200">
                                        No encontrado
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $entry['document_name'] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $entry['created_at_label'] }}
                                @if ($entry['created_at_relative'] !== '')
                                    · {{ $entry['created_at_relative'] }}
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($entry['document_exists'] && filled($entry['download_url']))
                                <a
                                    href="{{ $entry['download_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl bg-sky-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                                >
                                    Descargar
                                </a>
                            @else
                                <span class="inline-flex min-h-[2.5rem] items-center justify-center rounded-xl bg-slate-200 px-4 py-2 text-xs font-semibold text-slate-500 dark:bg-white/10 dark:text-slate-400">
                                    Descargar
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-px border-y border-sky-200/80 bg-sky-100/50 dark:border-sky-500/25 dark:bg-sky-950/30 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="bg-gradient-to-br from-sky-50/95 via-sky-100/70 to-white px-5 py-4 dark:from-sky-950/70 dark:via-sky-900/50 dark:to-sky-950/40">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-sky-700/80 dark:text-sky-300/80">Consulta</p>
                            <p class="mt-1.5 text-sm font-semibold text-sky-950 dark:text-sky-50">{{ $entry['consultation_reference'] }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-sky-50/95 via-sky-100/70 to-white px-5 py-4 dark:from-sky-950/70 dark:via-sky-900/50 dark:to-sky-950/40">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-sky-700/80 dark:text-sky-300/80">Médico</p>
                            <p class="mt-1.5 text-sm font-semibold text-sky-950 dark:text-sky-50">{{ $entry['doctor_name'] }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-sky-50/95 via-sky-100/70 to-white px-5 py-4 dark:from-sky-950/70 dark:via-sky-900/50 dark:to-sky-950/40">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-sky-700/80 dark:text-sky-300/80">Proveedor</p>
                            <p class="mt-1.5 text-sm font-semibold text-sky-950 dark:text-sky-50">{{ $entry['supplier_name'] }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-sky-50/95 via-sky-100/70 to-white px-5 py-4 dark:from-sky-950/70 dark:via-sky-900/50 dark:to-sky-950/40">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-sky-700/80 dark:text-sky-300/80">Registrado por</p>
                            <p class="mt-1.5 text-sm font-semibold text-sky-950 dark:text-sky-50">{{ $entry['created_by_name'] }}</p>
                        </div>
                    </div>

                    <div class="border-b border-slate-200/80 dark:border-white/10">
                        <x-filament::section
                            heading="Signos vitales"
                            description="Presión arterial, frecuencias, temperatura, saturación e índice de masa corporal."
                            icon="heroicon-o-heart"
                            collapsible
                            :collapsed="true"
                            class="!rounded-none !border-0 !shadow-none !ring-0"
                        >
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                                @foreach ($vitalSigns($entry) as $sign)
                                    <div @class([
                                        'rounded-2xl border px-3 py-3',
                                        $toneClasses[$sign['tone']] ?? $toneClasses['sky'],
                                    ])>
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            {{ $sign['label'] }}
                                        </p>
                                        <p class="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                            {{ $sign['value'] }}
                                        </p>
                                        @if ($sign['hint'] !== '')
                                            <p class="mt-0.5 text-[0.65rem] text-slate-500 dark:text-slate-400">{{ $sign['hint'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    </div>

                    <x-filament::section
                        heading="Información clínica"
                        description="Motivo de consulta, enfermedad actual, antecedentes e impresión diagnóstica."
                        icon="heroicon-o-clipboard-document-list"
                        collapsible
                        :collapsed="true"
                        class="!rounded-none !border-0 !shadow-none !ring-0"
                    >
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Motivo de consulta</p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ $entry['reason_consultation'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Enfermedad actual</p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ $entry['actual_phatology'] }}</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Antecedentes</p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ $entry['background'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Impresión diagnóstica</p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ $entry['diagnostic_impression'] }}</p>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>
                </article>
            @endforeach
        </div>
    @endif
</div>

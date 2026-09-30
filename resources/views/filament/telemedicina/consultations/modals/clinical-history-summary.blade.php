@php
    /** @var array{exists: bool, meta: array<string, string>, alerts: list<array{title: string, items: list<string>}>, sections: list<array{title: string, icon: string, items: list<array{label: string, detail: string|null}>, notes: list<string>}>} $summary */
    /** @var array{name: string, age: string|null, sex: string|null} $patient */
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-white/5">
        <span class="font-semibold text-slate-900 dark:text-white">{{ $patient['name'] }}</span>
        @if (filled($patient['age']))
            <span class="text-slate-600 dark:text-slate-300">{{ $patient['age'] }} años</span>
        @endif
        @if (filled($patient['sex']))
            <span class="text-slate-600 dark:text-slate-300">{{ $patient['sex'] }}</span>
        @endif
        @foreach ($summary['meta'] as $label => $value)
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $value }}</span></span>
        @endforeach
    </div>

    @if (! $summary['exists'])
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-white/15 dark:text-slate-400">
            El paciente aún no tiene historia clínica registrada.
        </p>
    @else
        <section aria-label="Datos clave">
            @if ($summary['alerts'] === [])
                <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <x-filament::icon icon="heroicon-o-check-circle" class="size-5 shrink-0" />
                    Sin alergias, enfermedades ni medicación habitual registradas.
                </div>
            @else
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($summary['alerts'] as $alert)
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/30 dark:bg-rose-500/10">
                            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                                <x-filament::icon icon="heroicon-s-exclamation-triangle" class="size-4" />
                                {{ $alert['title'] }}
                            </p>
                            <ul class="mt-2 space-y-1 text-sm text-rose-950 dark:text-rose-100">
                                @foreach ($alert['items'] as $item)
                                    <li class="flex gap-1.5"><span aria-hidden="true">•</span><span>{{ $item }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-3 lg:grid-cols-2">
            @foreach ($summary['sections'] as $section)
                @php($isEmpty = $section['items'] === [] && $section['notes'] === [])
                <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <x-filament::icon :icon="$section['icon']" class="size-4 text-primary-600 dark:text-primary-400" />
                        {{ $section['title'] }}
                    </h3>

                    @if ($isEmpty)
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-500">Sin registros.</p>
                    @else
                        @if ($section['items'] !== [])
                            <ul class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($section['items'] as $item)
                                    <li class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-800 dark:bg-white/10 dark:text-slate-100">
                                        <span class="font-semibold">{{ $item['label'] }}</span>
                                        @if (filled($item['detail']))
                                            <span class="text-slate-600 dark:text-slate-300">· {{ $item['detail'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @foreach ($section['notes'] as $note)
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $note }}</p>
                        @endforeach
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</div>

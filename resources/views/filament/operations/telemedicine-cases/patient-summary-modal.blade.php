@php
    /** @var \App\Models\TelemedicineCase $record */
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $patient = $record->telemedicinePatient;
    $aff = $patient?->afilliation;

    $format = static fn (?string $value): ?string =>
        ($value !== null && trim((string) $value) !== '') ? trim((string) $value) : null;

    $display = static fn (?string $primary, ?string $fallback): string => $format($primary) ?? $format($fallback) ?? '—';

    $normalizeType = static fn (?string $v): ?string => $v !== null && trim((string) $v) !== ''
        ? mb_strtoupper(Str::ascii(trim((string) $v)))
        : null;

    $patientType = $normalizeType($patient?->type_affiliation);

    $showAffiliationBlock =
        $patient &&
        ($patient->plan_id ||
            $patient->coverage_id ||
            $patient->afilliation_id ||
            filled($patient->code_affiliation) ||
            filled($patient->status_affiliation));

    $safeDate = static function ($value, string $pattern = 'd/m/Y'): string {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->format($pattern);
        } catch (\Throwable) {
            return '—';
        }
    };

    $hasAffiliationExtras =
        $aff !== null &&
        ($format($aff->full_name_ti ?? null) !== null
            || $format($aff->full_name_payer ?? null) !== null
            || ($aff->code !== null && trim((string) $aff->code) !== ''));
@endphp

<div class="patient-case-summary-sheet space-y-5 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
    {{-- Encabezado: caso + estado --}}
    <div
        class="rounded-xl border border-gray-200 bg-gradient-to-br from-primary-50/50 via-white to-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:from-primary-950/30 dark:via-gray-900 dark:to-gray-900 dark:ring-white/10 sm:flex sm:items-start sm:justify-between sm:gap-4"
    >
        <div class="min-w-0 flex-1">
            <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                Resumen rápido
            </p>
            <p class="mt-1 truncate text-lg font-semibold tracking-tight text-gray-950 dark:text-white">
                {{ $display($patient?->full_name, $record->patient_name) }}
            </p>
            @if ($format($patient?->nro_identificacion))
                <p class="mt-0.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                    ID {{ $patient->nro_identificacion }}
                </p>
            @endif
        </div>
        <div class="mt-4 flex shrink-0 flex-wrap items-center gap-2 sm:mt-0 sm:flex-col sm:items-end">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-primary-200/90 bg-white px-3 py-1 text-xs font-semibold text-primary-950 shadow-sm ring-1 ring-gray-950/5 dark:border-primary-500/35 dark:bg-primary-950/45 dark:text-primary-50 dark:ring-white/10"
            >
                <span class="h-2 w-2 rounded-full bg-primary-500 dark:bg-primary-400" aria-hidden="true"></span>
                Caso {{ $record->code }}
            </span>
            <span
                class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-[0.6875rem] font-medium text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
            >
                {{ mb_strtoupper($record->managed_by ?: 'Sin origen') }}
            </span>
        </div>
    </div>

    @if (!$patient)
        <div
            role="note"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-500/35 dark:bg-amber-950/40 dark:text-amber-50"
        >
            <p class="font-semibold">Sin expediente enlazado</p>
            <p class="mt-1 text-xs opacity-95">
                Solo se muestran los datos capturados en el caso (la fila puede ser histórico o caso sin paciente en el
                maestro).
            </p>
        </div>
    @endif

    @php
        $valEdad = $display($patient?->age ?? null ? (string) $patient->age : null, (string) $record->patient_age);
        $valSexo = $display($patient?->sex, $record->patient_sex);
        $valTel1 = $display($patient?->phone, $record->patient_phone);
        $valTel2 = $display($patient?->phone_contact, $record->patient_phone_2);
        $valMail = $display($patient?->email, null);
        $valMailC = $display($patient?->email_contact, null);
        $valNac = $safeDate($patient?->birth_date);
        $valDir = $display($patient?->address, $record->patient_address);
        $valPais = $display($patient?->country?->name, $record->country?->name);
        $valEstado = $display($patient?->state?->definition, $record->state?->definition);
        $valCiudad = $display($patient?->city?->definition, $record->city?->definition);
        $valRegion = $format($patient?->region ?? null) ?: '—';

        $inlinePieces = collect([
            ['label' => 'Tel. principal', 'value' => $valTel1, 'icon' => 'heroicon-o-phone'],
            ['label' => 'Tel. alterno', 'value' => $valTel2, 'icon' => 'heroicon-o-phone'],
            ['label' => 'Correo', 'value' => $valMail, 'icon' => 'heroicon-o-envelope'],
            ['label' => 'Correo contacto', 'value' => $valMailC, 'icon' => 'heroicon-o-envelope'],
            ['label' => 'Nacimiento', 'value' => $valNac, 'icon' => 'heroicon-o-calendar-days'],
        ])->reject(fn (array $p): bool => $p['value'] === '—');
    @endphp
    <section
        class="rounded-xl border border-gray-200 bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:flex sm:items-start sm:justify-between sm:gap-4"
        aria-labelledby="patient-main-heading"
    >
        <div class="min-w-0 flex-1">
                <h2 id="patient-main-heading" class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Información principal del paciente
                </h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Contacto y ubicación (expediente; si falta, datos del caso).
                </p>

                @if ($inlinePieces->isNotEmpty())
                    <div class="mt-3 flex flex-wrap items-start gap-x-6 gap-y-3">
                        @foreach ($inlinePieces as $piece)
                            <div class="min-w-0 max-w-[min(100%,13rem)] shrink-0">
                                <p class="flex items-center gap-1 text-[0.6rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <x-filament::icon :icon="$piece['icon']" class="h-3 w-3 shrink-0 opacity-70" />
                                    {{ $piece['label'] }}
                                </p>
                                <p class="mt-0.5 truncate font-medium tabular-nums text-gray-950 dark:text-white" title="{{ $piece['value'] }}">
                                    {{ $piece['value'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 border-t border-gray-200 pt-3 dark:border-white/10">
                    <p class="flex items-center gap-1 text-[0.6rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-map-pin" class="h-3 w-3 opacity-70" />
                        Dirección
                    </p>
                    <p class="mt-0.5 break-words font-medium leading-snug text-gray-950 dark:text-white">
                        {{ $valDir }}
                    </p>
                </div>

                @php
                    $ubicacionLinea = collect([$valPais, $valEstado, $valCiudad, $valRegion])
                        ->filter(fn (string $v): bool => $v !== '—')
                        ->unique()
                        ->values();
                @endphp
                @if ($ubicacionLinea->isNotEmpty())
                    <p class="mt-2 text-xs font-medium leading-relaxed text-gray-700 dark:text-gray-200">
                        <span class="text-[0.6rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Ubicación</span>
                        <span class="mx-1.5 text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                        {{ $ubicacionLinea->implode(' · ') }}
                    </p>
                @endif
            </div>
            <div class="mt-4 flex shrink-0 flex-wrap items-center gap-2 sm:mt-0 sm:flex-col sm:items-end">
                @if ($valEdad !== '—')
                    @php
                        $edadRaw = trim($valEdad);
                        $edadTieneUnidad =
                            preg_match('/\b(años?|año|meses?|mes|d[ií]as?|dia)\b/ui', $edadRaw) === 1;
                    @endphp
                    <span
                        class="inline-flex items-center rounded-full border border-sky-200/80 bg-sky-50/90 px-3 py-1 text-xs font-semibold text-sky-950 shadow-sm dark:border-sky-500/35 dark:bg-sky-950/45 dark:text-sky-50"
                    >
                        {{ $valEdad }}{{ !$edadTieneUnidad && preg_match('/^\d+$/', $edadRaw) === 1 ? ' años' : '' }}
                    </span>
                @endif
                @if ($valSexo !== '—')
                    <span
                        class="inline-flex items-center rounded-full border border-emerald-200/80 bg-emerald-50/90 px-3 py-1 text-xs font-semibold text-emerald-950 shadow-sm dark:border-emerald-500/35 dark:bg-emerald-950/45 dark:text-emerald-50"
                    >
                        {{ mb_strtoupper($valSexo) }}
                    </span>
                @endif
            </div>
        </section>

    @if ($showAffiliationBlock)
        @php
            $corp = $patient->afilliationCorporate;
            $planDesc = $patient->plan?->description ?? '—';
            $coverageDisplay = ($patient->coverage !== null ? $format((string) $patient->coverage->price) : null)
                ?? ($patient->coverage_id ? 'Ref. #'.$patient->coverage_id : null)
                ?? '—';

            $businessLineLabel = collect([optional($patient->businessUnit)->definition, optional($patient->businessLine)->definition])
                ->map(fn (?string $v): string => trim((string) $v))
                ->filter()
                ->implode(' · ');

            $affiliationSubtitleParts = [];
            if ($corp?->name_corporate && trim((string) $corp->name_corporate) !== '') {
                $affiliationSubtitleParts[] = trim((string) $corp->name_corporate);
            }
            $affSubtitle = count($affiliationSubtitleParts)
                ? implode(' · ', $affiliationSubtitleParts)
                : 'Plan, cobertura y vínculos con el programa.';
        @endphp
        <section
            class="rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/80 via-white to-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-emerald-500/25 dark:from-emerald-950/35 dark:via-gray-900 dark:to-gray-900 dark:ring-white/10"
            aria-labelledby="patient-affiliation-heading"
        >
            <div class="flex flex-col gap-2 border-b border-emerald-200/80 pb-3 sm:flex-row sm:items-start sm:justify-between dark:border-emerald-500/20">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600/12 text-emerald-950 ring-1 ring-emerald-600/25 dark:bg-emerald-400/15 dark:text-emerald-50 dark:ring-emerald-400/25"
                        aria-hidden="true"
                    >
                        <x-filament::icon icon="heroicon-o-identification" class="h-6 w-6" />
                    </span>
                    <div>
                        <h2 id="patient-affiliation-heading" class="text-[0.8rem] font-semibold tracking-tight text-gray-950 dark:text-white">
                            Afiliación
                        </h2>
                        <p class="text-xs text-emerald-900/85 dark:text-emerald-100/80">
                            {{ $affSubtitle }}
                        </p>
                    </div>
                </div>
                @if ($format($patient->status_affiliation))
                    <span
                        class="inline-flex w-fit items-center rounded-full border border-emerald-400/50 bg-emerald-600 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wide text-white shadow-sm dark:border-emerald-300/60 dark:bg-emerald-700"
                    >
                        {{ mb_strtoupper($patient->status_affiliation) }}
                    </span>
                @endif
            </div>

            <dl class="mt-4 grid gap-3 lg:grid-cols-4">
                @foreach ([
                    'Código paciente / afiliación' => $display($patient->code_affiliation, $patient->code),
                    'Tipo' => $format($patient->type_affiliation) ?? '—',
                    'Plan' => Str::limit($planDesc === '—' ? '—' : $planDesc, 80),
                    'Cobertura' => Str::limit($coverageDisplay !== '' ? $coverageDisplay : '—', 48),
                    'Nombre corporativo / convenio' => $display($patient->name_corporate, null),
                    'Unidad / línea negocio' => $businessLineLabel !== '' ? $businessLineLabel : '—',
                    'Fecha afiliación' => $safeDate($patient->date_affiliation),
                ] as $label => $value)
                    <div class="rounded-lg border border-emerald-200/70 bg-white px-3 py-2.5 dark:border-emerald-500/20 dark:bg-emerald-950/30">
                        <dt class="text-[0.6rem] font-bold uppercase tracking-wide text-emerald-900/85 dark:text-emerald-100/70">
                            {{ $label }}
                        </dt>
                        <dd class="mt-1 break-words font-medium text-gray-950 dark:text-white">
                            {{ $value ?: '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if ($hasAffiliationExtras)
                <details
                    class="group mt-4 rounded-lg border border-emerald-200/70 bg-gray-50/80 px-3 py-2 dark:border-emerald-500/25 dark:bg-white/5"
                    @if ($format($aff->full_name_ti ?? null)) open @endif
                >
                    <summary
                        class="cursor-pointer select-none text-xs font-semibold text-emerald-900 underline decoration-dashed underline-offset-2 hover:text-emerald-950 dark:text-emerald-100 dark:hover:text-emerald-50 [&::-webkit-details-marker]:hidden"
                    >
                        <span class="inline-flex items-center gap-1">
                            Datos registrados en la afiliación
                            <x-filament::icon
                                icon="heroicon-m-chevron-down"
                                class="h-4 w-4 shrink-0 transition group-open:rotate-180"
                            />
                        </span>
                    </summary>
                    <dl class="mt-3 grid gap-2 border-t border-emerald-200/60 pt-3 text-xs dark:border-emerald-500/20 sm:grid-cols-2">
                        @foreach ([
                            'Código solicitud' => $aff->code ?? null,
                            'Titular' => $aff->full_name_ti ?? null,
                            'Pagador' => $aff->full_name_payer ?? null,
                            'Teléfono titular' => $aff->phone_ti ?? null,
                            'Email titular' => $aff->email_ti ?? null,
                            'Teléfono pagador' => $aff->phone_payer ?? null,
                        ] as $l => $v)
                            @php
                                $slice = isset($v) ? trim((string) $v) : '';
                            @endphp
                            @if ($slice !== '')
                                <div>
                                    <dt class="font-semibold text-emerald-900 dark:text-emerald-200">{{ $l }}</dt>
                                    <dd class="text-gray-800 dark:text-gray-50">{{ $slice }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </details>
            @endif

            {{-- Fallback: tipo externo con algún vínculo (raro pero posible) --}}
            @if ($patientType && str_contains((string) $patientType, 'TIT'))
                <p class="mt-3 text-[0.65rem] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Rol en póliza: {{ $patientType }}
                </p>
            @endif
        </section>
    @elseif ($patient && in_array($patientType, ['EXTERNO', 'PARTICULAR'], true))
        <section
            class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
            role="note"
        >
            <p class="text-sm font-semibold text-gray-950 dark:text-gray-100">Sin datos de afiliación en expediente</p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                El tipo registrado es
                <span class="font-semibold">{{ $patient->type_affiliation }}</span>
                y no hay plan, cobertura ni códigos de afiliación asociados.
            </p>
        </section>
    @endif

    @livewire(
        \App\Livewire\Operations\PatientCaseObservationPanel::class,
        ['telemedicineCaseId' => $record->id],
        key('patient-case-obs-panel-'.$record->id)
    )
</div>

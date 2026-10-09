@props([
    /** @var \App\Models\TelemedicinePatient $patient */
    'patient',
    /** @var array{has_plan: bool, is_complete: bool, message: string|null, rows: list<array<string, mixed>>} $quota */
    'quota',
])

@php
    $tones = [
        'success' => ['color' => '#16a34a', 'soft' => 'rgba(22, 163, 74, .12)'],
        'warning' => ['color' => '#d97706', 'soft' => 'rgba(217, 119, 6, .14)'],
        'danger' => ['color' => '#dc2626', 'soft' => 'rgba(220, 38, 38, .12)'],
        'gray' => ['color' => '#64748b', 'soft' => 'rgba(100, 116, 139, .12)'],
    ];

    $icons = [
        'labs' => 'heroicon-o-beaker',
        'studies' => 'heroicon-o-photo',
        'specialists' => 'heroicon-o-user-group',
        'medications' => 'heroicon-o-eye-dropper',
    ];

    $initials = collect(preg_split('/\s+/u', trim((string) $patient->full_name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    $facts = collect([
        'Plan' => $patient->plan?->description,
        'Unidad de negocio' => $patient->businessUnit?->definition,
        'U.N. específica' => $patient->specific_business_unit,
        'Gestiona' => $patient->managed_by,
    ])->filter(fn (mixed $value): bool => filled($value));
@endphp

<div style="display: flex; flex-direction: column; gap: 16px;">
    {{-- Ficha del paciente --}}
    <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 16px;">
        <div style="width: 44px; height: 44px; flex-shrink: 0; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0ea5e9, #2563eb); color: #fff; font-weight: 800; font-size: .95rem;">
            {{ $initials !== '' ? $initials : '?' }}
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px; min-width: 0; flex: 1;">
            <div>
                <div class="text-gray-900 dark:text-white" style="font-size: 1rem; font-weight: 700; line-height: 1.2;">{{ $patient->full_name }}</div>
                <div class="text-gray-500 dark:text-gray-400" style="font-size: .8rem;">
                    {{ collect([filled($patient->nro_identificacion) ? 'C.I. '.$patient->nro_identificacion : null, $patient->code])->filter()->implode(' · ') }}
                </div>
            </div>
            @if ($facts->isNotEmpty())
                <div style="display: flex; flex-wrap: wrap; gap: 8px 24px;">
                    @foreach ($facts as $label => $value)
                        <div style="display: flex; flex-direction: column; min-width: 0;">
                            <span class="text-gray-500 dark:text-gray-400" style="font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;">{{ $label }}</span>
                            <span class="text-gray-900 dark:text-white" style="font-size: .875rem; font-weight: 600;">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Cupo clínico --}}
    <div style="display: flex; flex-direction: column; gap: 10px;">
        <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <span class="text-gray-900 dark:text-white" style="font-size: .9rem; font-weight: 700;">Cupo clínico</span>
            <span class="text-gray-500 dark:text-gray-400" style="font-size: .75rem;">Solo lo cubierto descuenta cupo.</span>
        </div>

        @if (filled($quota['message']))
            <div style="padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(220, 38, 38, .35); background: rgba(220, 38, 38, .08); color: #dc2626; font-size: .8rem; font-weight: 600;">
                {{ $quota['message'] }}
            </div>
        @endif

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
            @foreach ($quota['rows'] as $row)
                @php
                    $tone = $tones[$row['tone']] ?? $tones['gray'];
                    $hasNumbers = $row['kind'] === 'quota' && (int) $row['quota'] > 0;
                    $usedPercent = $hasNumbers ? min(100, (int) round(((int) $row['used'] / max(1, (int) $row['quota'])) * 100)) : 0;
                @endphp
                <div wire:key="quota-{{ $row['category'] }}" class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5" style="display: flex; flex-direction: column; gap: 10px; padding: 14px; border-top: 3px solid {{ $tone['color'] }};">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="width: 32px; height: 32px; flex-shrink: 0; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: {{ $tone['soft'] }}; color: {{ $tone['color'] }};">
                            <x-filament::icon :icon="$icons[$row['category']] ?? 'heroicon-o-clipboard-document-list'" style="width: 18px; height: 18px;" />
                        </span>
                        <span class="text-gray-900 dark:text-white" style="font-size: .85rem; font-weight: 700; line-height: 1.2; flex: 1; min-width: 0;">{{ $row['label'] }}</span>
                        <span style="flex-shrink: 0; padding: 2px 9px; border-radius: 999px; font-size: .7rem; font-weight: 700; background: {{ $tone['soft'] }}; color: {{ $tone['color'] }};">{{ $row['status'] }}</span>
                    </div>

                    @if ($hasNumbers)
                        <div style="display: flex; align-items: baseline; gap: 6px;">
                            <span style="font-size: 1.75rem; font-weight: 800; line-height: 1; color: {{ $tone['color'] }}; font-variant-numeric: tabular-nums;">{{ $row['remaining'] }}</span>
                            <span class="text-gray-600 dark:text-gray-300" style="font-size: .8rem;">de {{ $row['quota'] }} {{ $row['unit'] }} disponibles</span>
                        </div>
                        <div role="progressbar" aria-valuemin="0" aria-valuemax="{{ $row['quota'] }}" aria-valuenow="{{ $row['used'] }}" aria-label="Usados {{ $row['used'] }} de {{ $row['quota'] }}" style="height: 6px; border-radius: 999px; background: rgba(148, 163, 184, .25); overflow: hidden;">
                            <div style="height: 100%; width: {{ $usedPercent }}%; border-radius: 999px; background: {{ $tone['color'] }};"></div>
                        </div>
                        <div class="text-gray-500 dark:text-gray-400" style="font-size: .72rem;">
                            Usados {{ $row['used'] }} · {{ $row['scope'] }}
                        </div>
                    @else
                        <div style="font-size: 1.05rem; font-weight: 800; color: {{ $tone['color'] }};">{{ $row['status'] }}</div>
                    @endif

                    <div class="text-gray-600 dark:text-gray-300" style="font-size: .75rem; line-height: 1.35;">{{ $row['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

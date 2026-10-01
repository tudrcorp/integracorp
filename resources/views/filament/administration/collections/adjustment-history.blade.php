{{--
    Historial de ajustes manuales de una cuota (Gestión de Cobranza → «Historial de ajustes»).
    Estilos en línea y utilidades de texto ya compiladas: no requiere recompilar el tema.
--}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\CollectionAdjustment> $adjustments */
    /** @var array<string, string> $labels */
@endphp

<div style="display:flex;flex-direction:column;gap:12px;">
    @forelse ($adjustments as $adjustment)
        <div style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.25);background:rgba(148,163,184,.06);">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;margin-bottom:8px;">
                <span class="text-gray-900 dark:text-white" style="font-weight:700;font-size:.9rem;">
                    {{ $adjustment->performed_by_name ?: 'Usuario desconocido' }}
                </span>
                <span class="text-gray-600 dark:text-gray-300" style="font-size:.8rem;">
                    {{ $adjustment->created_at?->format('d/m/Y h:i A') }}
                    · {{ $adjustment->mode === \App\Models\CollectionAdjustment::MODE_BULK ? 'Ajuste masivo' : 'Ajuste individual' }}
                </span>
            </div>

            @foreach ((array) $adjustment->changes as $field => $change)
                <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:baseline;font-size:.85rem;padding:2px 0;">
                    <span class="text-gray-600 dark:text-gray-300" style="min-width:110px;font-weight:600;">{{ $labels[$field] ?? $field }}</span>
                    <span style="text-decoration:line-through;opacity:.7;">{{ $change['before'] ?? '—' }}</span>
                    <span>→</span>
                    <span class="text-gray-900 dark:text-white" style="font-weight:700;">{{ $change['after'] ?? '—' }}</span>
                </div>
            @endforeach

            <div class="text-gray-600 dark:text-gray-300" style="margin-top:8px;font-size:.85rem;">
                <span style="font-weight:600;">Motivo:</span> {{ $adjustment->reason }}
            </div>
        </div>
    @empty
        <p class="text-gray-600 dark:text-gray-300" style="font-size:.9rem;">Esta cuota no tiene ajustes manuales.</p>
    @endforelse
</div>

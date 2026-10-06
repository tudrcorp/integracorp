{{-- Sección del bloque de cálculos del plan generado (incluida desde plan-generator-plan-body). --}}
    @if ($conditions !== '')
        <div class="matrix-section">
            <p class="section-title">Condiciones</p>
            <div class="conditions-block">{{ $conditions }}</div>
        </div>
    @endif


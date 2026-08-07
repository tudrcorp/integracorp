@php
    /** @var array{current_label: string, previous_label: string, entity_label: string, entity_count: int, entity_help: string, conversion_label: string, conversion_help: string, top_label: string, top_total: int, top_help: string, intro: string, quotes: array{current: int, previous: int, percent_label: string, delta_label: string, delta_sentence: string, trend: string}, executed: array{current: int, previous: int, percent_label: string, delta_label: string, delta_sentence: string, trend: string}} $summary */
    $summary = $summary ?? [];
    $quotes = $summary['quotes'] ?? [
        'current' => 0,
        'previous' => 0,
        'percent_label' => 'Sin cambios',
        'delta_label' => '0',
        'delta_sentence' => '',
        'trend' => 'flat',
    ];
    $executed = $summary['executed'] ?? [
        'current' => 0,
        'previous' => 0,
        'percent_label' => 'Sin cambios',
        'delta_label' => '0',
        'delta_sentence' => '',
        'trend' => 'flat',
    ];
@endphp

<div class="fi-metrics-mom-summary" aria-label="Comparación del mes actual con el mes pasado">
    <div class="fi-metrics-mom-summary__period">
        <span class="fi-metrics-mom-summary__period-eyebrow">Para entenderlo fácil</span>
        <p class="fi-metrics-mom-summary__period-title">
            <span class="tabular-nums">{{ $summary['current_label'] ?? '—' }}</span>
            <span class="fi-metrics-mom-summary__period-vs">frente a</span>
            <span class="tabular-nums">{{ $summary['previous_label'] ?? '—' }}</span>
        </p>
        <p class="fi-metrics-mom-summary__intro">
            {{ $summary['intro'] ?? 'Aquí comparamos lo que va del mes actual con todo el mes pasado, para ver si vamos mejor, igual o peor.' }}
        </p>
    </div>

    <div class="fi-metrics-mom-summary__cards">
        <article class="fi-metrics-mom-summary__card fi-metrics-mom-summary__card--{{ $quotes['trend'] }}">
            <div class="fi-metrics-mom-summary__card-head">
                <span class="fi-metrics-mom-summary__card-label">Total de cotizaciones</span>
                <span class="fi-metrics-mom-summary__badge fi-metrics-mom-summary__badge--{{ $quotes['trend'] }}">
                    {{ $quotes['percent_label'] }}
                </span>
            </div>
            <p class="fi-metrics-mom-summary__card-help">
                Cuántas cotizaciones se han hecho en el mes actual.
            </p>
            <p class="fi-metrics-mom-summary__card-value tabular-nums">
                {{ number_format((int) $quotes['current'], 0, ',', '.') }}
            </p>
            <p class="fi-metrics-mom-summary__card-current-hint">Este mes (hasta hoy)</p>
            <div class="fi-metrics-mom-summary__card-foot">
                <span>
                    El mes pasado hubo
                    <strong class="tabular-nums">{{ number_format((int) $quotes['previous'], 0, ',', '.') }}</strong>
                </span>
                <span class="fi-metrics-mom-summary__delta fi-metrics-mom-summary__delta--{{ $quotes['trend'] }} tabular-nums">
                    Diferencia: {{ $quotes['delta_label'] }}
                </span>
            </div>
            @if (filled($quotes['delta_sentence'] ?? null))
                <p class="fi-metrics-mom-summary__card-sentence">{{ $quotes['delta_sentence'] }}</p>
            @endif
        </article>

        <article class="fi-metrics-mom-summary__card fi-metrics-mom-summary__card--{{ $executed['trend'] }}">
            <div class="fi-metrics-mom-summary__card-head">
                <span class="fi-metrics-mom-summary__card-label">Convertidas en afiliación</span>
                <span class="fi-metrics-mom-summary__badge fi-metrics-mom-summary__badge--{{ $executed['trend'] }}">
                    {{ $executed['percent_label'] }}
                </span>
            </div>
            <p class="fi-metrics-mom-summary__card-help">
                Cotizaciones que ya quedaron ejecutadas y con una afiliación vinculada.
            </p>
            <p class="fi-metrics-mom-summary__card-value tabular-nums">
                {{ number_format((int) $executed['current'], 0, ',', '.') }}
            </p>
            <p class="fi-metrics-mom-summary__card-current-hint">Este mes (hasta hoy)</p>
            <div class="fi-metrics-mom-summary__card-foot">
                <span>
                    El mes pasado hubo
                    <strong class="tabular-nums">{{ number_format((int) $executed['previous'], 0, ',', '.') }}</strong>
                </span>
                <span class="fi-metrics-mom-summary__delta fi-metrics-mom-summary__delta--{{ $executed['trend'] }} tabular-nums">
                    Diferencia: {{ $executed['delta_label'] }}
                </span>
            </div>
            @if (filled($executed['delta_sentence'] ?? null))
                <p class="fi-metrics-mom-summary__card-sentence">{{ $executed['delta_sentence'] }}</p>
            @endif
        </article>
    </div>

    <div class="fi-metrics-mom-summary__meta">
        <div class="fi-metrics-mom-summary__meta-item">
            <span class="fi-metrics-mom-summary__meta-label">¿Cuántas se convierten?</span>
            <span class="fi-metrics-mom-summary__meta-value tabular-nums">{{ $summary['conversion_label'] ?? '0%' }}</span>
            <span class="fi-metrics-mom-summary__meta-help">
                {{ $summary['conversion_help'] ?? 'De cada 100 cotizaciones, cuántas terminan en afiliación.' }}
            </span>
        </div>
        <div class="fi-metrics-mom-summary__meta-item">
            <span class="fi-metrics-mom-summary__meta-label">{{ $summary['entity_label'] ?? 'Participantes' }}</span>
            <span class="fi-metrics-mom-summary__meta-value tabular-nums">{{ number_format((int) ($summary['entity_count'] ?? 0), 0, ',', '.') }}</span>
            <span class="fi-metrics-mom-summary__meta-help">
                {{ $summary['entity_help'] ?? 'Cantidad de personas o agencias con actividad este mes.' }}
            </span>
        </div>
        <div class="fi-metrics-mom-summary__meta-item fi-metrics-mom-summary__meta-item--accent">
            <span class="fi-metrics-mom-summary__meta-label">Quién va primero</span>
            <span class="fi-metrics-mom-summary__meta-value">
                {{ $summary['top_label'] ?? '—' }}
                <span class="fi-metrics-mom-summary__meta-sub tabular-nums">
                    · {{ number_format((int) ($summary['top_total'] ?? 0), 0, ',', '.') }} cotizaciones
                </span>
            </span>
            <span class="fi-metrics-mom-summary__meta-help">
                {{ $summary['top_help'] ?? 'Es quien más cotizaciones tiene en el mes actual.' }}
            </span>
        </div>
    </div>
</div>

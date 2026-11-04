@php
    $money = fn ($value) => 'US$ '.number_format((float) $value, 2, ',', '.');
    $brand = '#052F60';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 104px 30px 64px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }

        header { position: fixed; top: -86px; left: 0; right: 0; height: 78px; }
        footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 36px; }

        table { border-collapse: collapse; }
        .bar { width: 100%; table-layout: fixed; }
        .bar td { vertical-align: middle; }
        .bar img { max-height: 44px; max-width: 150px; }
        .bar h1 { margin: 0; font-size: 13px; color: {{ $brand }}; letter-spacing: .3px; }
        .bar p { margin: 3px 0 0; font-size: 8px; color: #6b7280; }
        .rule { height: 3px; background: {{ $brand }}; margin-top: 8px; }

        .foot { width: 100%; table-layout: fixed; border-top: 1px solid #e5e7eb; font-size: 7px; color: #6b7280; }
        .foot td { padding-top: 6px; }
        .page-number:after { content: counter(page); }

        .layout { width: 100%; table-layout: fixed; }
        .layout > tbody > tr > td { padding: 0 0 14px 0; vertical-align: top; }

        .kpis { width: 100%; table-layout: fixed; }
        .kpis td { padding: 9px 10px; border: 1px solid #e5e7eb; background: #f8fafc; }
        .kpi-label { font-size: 6.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .kpi-value { font-size: 14px; font-weight: bold; color: {{ $brand }}; margin-top: 3px; }

        .data { width: 100%; table-layout: fixed; }
        .data th {
            background: {{ $brand }}; color: #fff; font-size: 7px; text-transform: uppercase;
            letter-spacing: .4px; padding: 6px 5px; text-align: left; border: 1px solid {{ $brand }};
        }
        .data td { padding: 5px; border: 1px solid #e5e7eb; font-size: 8px; vertical-align: top; word-wrap: break-word; }
        .data tr.even td { background: #f9fafb; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; font-size: 7px; }

        .plan-head { width: 100%; table-layout: fixed; border: 1px solid #dbe3ee; }
        .plan-head td { padding: 10px 12px; vertical-align: top; background: #f4f7fb; }
        .plan-name { font-size: 13px; font-weight: bold; color: {{ $brand }}; }
        .plan-provider { font-size: 8.5px; color: #374151; margin-top: 3px; }
        .price { font-size: 16px; font-weight: bold; color: {{ $brand }}; text-align: right; }
        .price-note { font-size: 7.5px; color: #6b7280; text-align: right; margin-top: 2px; }
        .badge { font-size: 7px; font-weight: bold; padding: 2px 6px; border-radius: 8px; }
        .badge-on { background: #dcfce7; color: #166534; }
        .badge-off { background: #e5e7eb; color: #374151; }

        .facts { width: 100%; table-layout: fixed; }
        .facts td { padding: 6px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .fact-label { font-size: 6.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .fact-value { font-size: 8.5px; color: #111827; margin-top: 2px; }

        .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: {{ $brand }}; padding: 0 0 4px 0; }
        .prose { padding: 8px 10px; border: 1px solid #e5e7eb; font-size: 8.5px; line-height: 1.5; }
        .divider { height: 1px; background: #cbd5e1; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <header>
        <table class="bar">
            <tr>
                <td style="width: 26%;">
                    @if ($tdgLogo !== '')
                        <img src="{{ $tdgLogo }}" alt="Tu Dr Group">
                    @endif
                </td>
                <td style="width: 74%; text-align: right;">
                    <h1>{{ $isSingle ? 'FICHA DEL PLAN DE CAPITADO' : 'REPORTE DE PLANES DE CAPITADO' }}</h1>
                    <p>Red de proveedores Outsourcing · Emitido el {{ $issuedAt }}</p>
                </td>
            </tr>
        </table>
        <div class="rule"></div>
    </header>

    <footer>
        <table class="foot">
            <tr>
                <td style="width: 75%;">Tu Dr Group · Documento interno de negociación con proveedores. Los montos están expresados en dólares estadounidenses.</td>
                <td style="width: 25%; text-align: right;">Página <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

    @unless ($isSingle)
        <table class="layout">
            <tbody>
                <tr>
                    <td>
                        <table class="kpis">
                            <tr>
                                <td style="width: 25%;"><div class="kpi-label">Planes</div><div class="kpi-value">{{ $summary['plans'] }}</div></td>
                                <td style="width: 25%;"><div class="kpi-label">Activos</div><div class="kpi-value">{{ $summary['active'] }}</div></td>
                                <td style="width: 25%;"><div class="kpi-label">Proveedores</div><div class="kpi-value">{{ $summary['providers'] }}</div></td>
                                <td style="width: 25%;"><div class="kpi-label">Promedio mensual (activos)</div><div class="kpi-value">{{ $summary['average_monthly'] !== null ? $money($summary['average_monthly']) : '—' }}</div></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="section-title">Resumen de planes</div>
                        <table class="data">
                            <thead>
                                <tr>
                                    <th style="width: 26%;">Plan</th>
                                    <th style="width: 24%;">Proveedor</th>
                                    <th style="width: 13%;" class="num">Costo</th>
                                    <th style="width: 11%;">Frecuencia</th>
                                    <th style="width: 12%;" class="num">≈ Mensual</th>
                                    <th style="width: 14%;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $index => $row)
                                    <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                                        <td>
                                            {{ $row['plan']->name }}
                                            <div class="muted">{{ $row['plan']->benefits->count() }} {{ $row['plan']->benefits->count() === 1 ? 'beneficio' : 'beneficios' }} · {{ $row['validity'] }}</div>
                                        </td>
                                        <td>
                                            {{ $row['provider_name'] }}
                                            <div class="muted">{{ $row['provider_type'] }}</div>
                                        </td>
                                        <td class="num">{{ $money($row['plan']->cost_per_affiliate_usd) }}</td>
                                        <td>{{ $row['plan']->payment_frequency->label() }}</td>
                                        <td class="num">{{ $money($row['monthly']) }}</td>
                                        <td>{{ $row['plan']->status === \App\Models\CapitationPlan::STATUS_ACTIVE ? 'Activo' : 'Inactivo' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
    @endunless

    @foreach ($rows as $index => $row)
        @php($plan = $row['plan'])
        <table class="layout {{ ! $isSingle ? 'page-break' : '' }}">
            <tbody>
                <tr>
                    <td>
                        <table class="plan-head">
                            <tr>
                                <td style="width: 64%;">
                                    <div class="plan-name">{{ $plan->name }}</div>
                                    <div class="plan-provider">
                                        {{ $row['provider_type'] }} · {{ $row['provider_name'] }}@if ($row['provider_rif'] !== '') · {{ $row['provider_rif'] }}@endif
                                    </div>
                                    <div style="margin-top: 6px;">
                                        <span class="badge {{ $plan->status === \App\Models\CapitationPlan::STATUS_ACTIVE ? 'badge-on' : 'badge-off' }}">
                                            {{ $plan->status === \App\Models\CapitationPlan::STATUS_ACTIVE ? 'ACTIVO' : 'INACTIVO' }}
                                        </span>
                                    </div>
                                </td>
                                <td style="width: 36%;">
                                    <div class="price">{{ $money($plan->cost_per_affiliate_usd) }}</div>
                                    <div class="price-note">por afiliado · {{ mb_strtolower($plan->payment_frequency->label()) }}</div>
                                    @if ($plan->payment_frequency !== \App\Enums\CapitationPaymentFrequency::Monthly)
                                        <div class="price-note">≈ {{ $money($row['monthly']) }} por mes</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table class="facts">
                            <tr>
                                <td style="width: 33%;"><div class="fact-label">Vigencia</div><div class="fact-value">{{ $row['validity'] }}</div></td>
                                <td style="width: 33%;"><div class="fact-label">Beneficios</div><div class="fact-value">{{ $plan->benefits->count() }}</div></td>
                                <td style="width: 34%;"><div class="fact-label">Última actualización</div><div class="fact-value">{{ $plan->updated_by_name ?: '—' }} · {{ $plan->updated_at?->format('d/m/Y') }}</div></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table class="facts">
                            <tr>
                                <td><div class="fact-label">Planes TDEC que cubre</div><div class="fact-value">{{ $row['covered_plans'] !== '' ? $row['covered_plans'] : 'Ninguno' }}</div></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="section-title">Beneficios y servicios</div>
                        <table class="data">
                            <thead>
                                <tr>
                                    <th style="width: 6%;">#</th>
                                    <th style="width: 56%;">Beneficio</th>
                                    <th style="width: 38%;">Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($plan->benefits as $position => $benefit)
                                    <tr class="{{ $position % 2 === 1 ? 'even' : '' }}">
                                        <td><span style="font-family: DejaVu Sans, sans-serif; color: #16a34a;">✓</span> {{ $position + 1 }}</td>
                                        <td>{{ $benefit->name }}</td>
                                        <td>{{ $benefit->note ?: ($benefit->benefit_id !== null ? 'Del catálogo' : 'Texto libre') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="muted">Sin beneficios registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="section-title">Tipo de negociación</div>
                        <div class="prose">{!! nl2br(e((string) $plan->negotiation_type)) !!}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="section-title">Documentos de respaldo</div>
                        <div class="prose">
                            @if ($row['document_names'] === [])
                                <span class="muted">Sin documentos cargados.</span>
                            @else
                                @foreach ($row['document_names'] as $name)
                                    • {{ $name }}@if (! $loop->last)<br>@endif
                                @endforeach
                            @endif
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>

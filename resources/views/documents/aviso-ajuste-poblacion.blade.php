@php
    /** @var \App\Models\CorporateBillingAdjustment $adjustment */
    /** @var array<string, mixed> $summary */
    /** @var \App\Models\Collection|null $charge */
    /** @var \Illuminate\Support\Collection<int, \App\Models\CorporateBillingAdjustmentLine> $lines */
    $money = static fn (int $cents): string => \App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator::money($cents);
    $adds = $lines->where('kind', \App\Models\CorporateBillingAdjustmentLine::KIND_ADD)->values();
    $removes = $lines->where('kind', \App\Models\CorporateBillingAdjustmentLine::KIND_REMOVE)->values();
    $perYear = (int) ($summary['per_year'] ?? 1);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de cobro por ajuste de población</title>
    <style>
        @page { margin: 15mm 20mm 62mm 20mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; word-wrap: break-word; }
        .header td { padding: 0 0 10px 0; }
        .header img { width: 180px; }
        .meta { text-align: right; font-size: 11px; line-height: 1.5; }
        .title { font-size: 15px; font-weight: bold; color: #052F60; }
        .block td { padding: 6px 0; }
        .box td { padding: 8px 10px; background: #F3F4F6; line-height: 1.5; }
        .section { font-size: 12px; font-weight: bold; color: #052F60; padding: 10px 0 4px 0; }
        .grid th { background: #052F60; color: #ffffff; font-weight: bold; padding: 5px 6px; text-align: left; font-size: 9.5px; }
        .grid td { padding: 4px 6px; border-bottom: 1px solid #E5E7EB; font-size: 9.5px; }
        .num { text-align: right; }
        .total td { padding: 6px; font-weight: bold; border-top: 2px solid #052F60; font-size: 11px; }
        .muted { color: #4B5563; }
        .footer-legal { position: fixed; bottom: -30mm; left: 0; right: 0; text-align: right; font-size: 8.5px; text-transform: uppercase; line-height: 1.15; }
        .footer-banner { position: fixed; bottom: -62mm; left: -20mm; right: -20mm; line-height: 0; }
        .footer-banner img { width: 100%; height: auto; display: block; }
    </style>
</head>
<body>
    <div class="footer-legal">
        TU DOCTOR EN CASA, C. A. J-503583681<br>
        Oficina Comercial: Av. Francisco de Miranda, Centro Lido, Torre A, Ofic.124, El Rosal Caracas - Venezuela<br>
        Teléfono.: (+58) 212 308 28 55 / 0424-287-5732 · Email: administracion@tudrencasa.com · www.tudrencasa.com
    </div>
    <div class="footer-banner"><img src="{{ public_path('storage/bannerFooterv2.png') }}" alt=""></div>

    <table class="header">
        <tr>
            <td style="width: 45%;"><img src="{{ public_path('storage/administracion/logoNewPdfTDEC.png') }}" alt="Tu Doctor en Casa"></td>
            <td style="width: 55%;" class="meta">
                <div class="title">AVISO DE COBRO POR AJUSTE DE POBLACIÓN</div>
                @if ($charge)
                    <strong>Aviso Nro.: {{ $charge->collection_invoice_number }}</strong><br>
                    Vence: {{ $charge->next_payment_date }}<br>
                @endif
                Fecha de emisión: {{ $adjustment->created_at?->format('d/m/Y') }}<br>
                Condiciones de pago: Contado
            </td>
        </tr>
    </table>

    <table class="block">
        <tr><td>
            <strong>A nombre de:</strong> {{ $summary['affiliation_name'] ?? $adjustment->affiliation_code }}<br>
            <strong>Afiliación:</strong> {{ $adjustment->affiliation_code }} · <strong>Frecuencia:</strong> {{ $summary['frequency_label'] ?? $adjustment->payment_frequency }}
            · <strong>Ciclo:</strong> {{ $summary['cycle_start'] ?? '' }} al {{ $summary['cycle_end'] ?? '' }}
        </td></tr>
    </table>

    <table class="box">
        <tr><td>
            Cambio de población con fecha efectiva <strong>{{ $summary['effective_date'] }}</strong>:
            <strong>{{ $adjustment->persons_added }}</strong> {{ $adjustment->persons_added === 1 ? 'persona incluida' : 'personas incluidas' }} y
            <strong>{{ $adjustment->persons_removed }}</strong> {{ $adjustment->persons_removed === 1 ? 'persona excluida' : 'personas excluidas' }}.
            Las personas incluidas pagan, del período ya facturado, la tarifa de su cuota por los días que les quedan
            (tarifa anual ÷ {{ $perYear }} × días ÷ días del período). Las exclusiones no generan crédito del período ya facturado.
        </td></tr>
    </table>

    @foreach ($summary['charged_periods'] ?? [] as $period)
        <table class="block"><tr><td>
            Período {{ $period['period'] }}: del {{ $period['start'] }} al {{ $period['end'] }} ({{ $period['days'] }} días).
            Desde el {{ $period['from'] }} quedan <strong>{{ $period['covered_days'] }} de {{ $period['days'] }} días</strong>.
        </td></tr></table>
    @endforeach

    <table><tr><td class="section">Resumen del cobro</td></tr></table>
    <table class="grid">
        <thead><tr>
            <th style="width: 30%;">Personas</th>
            <th style="width: 16%;" class="num">Tarifa anual</th>
            <th style="width: 15%;" class="num">Por cuota</th>
            <th style="width: 23%;">Cálculo por persona</th>
            <th style="width: 16%;" class="num">Total</th>
        </tr></thead>
        <tbody>
            @foreach (collect($summary['groups'] ?? [])->where('kind', \App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator::KIND_ADD) as $group)
                <tr>
                    <td>{{ $group['count'] }} {{ $group['count'] === 1 ? 'persona incluida' : 'personas incluidas' }}</td>
                    <td class="num">{{ $money($group['annual_cents']) }}</td>
                    <td class="num">{{ $money($group['period_cents']) }}</td>
                    <td>{{ $money($group['period_cents']) }} × {{ $group['days'] }} ÷ {{ $group['period_days'] }} = {{ $money($group['unit_charge_cents']) }}</td>
                    <td class="num">{{ $money($group['charge_cents']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="total"><tr>
        <td style="width: 70%;">MONTO TOTAL DEL AVISO</td>
        <td style="width: 30%;" class="num">{{ $money((int) ($summary['charge_cents'] ?? 0)) }}</td>
    </tr></table>

    @if (($summary['adjusted'] ?? []) !== [])
        <table><tr><td class="section">Cuotas por pagar que cambian de monto</td></tr></table>
        <table class="grid">
            <thead><tr>
                <th style="width: 22%;">Aviso</th>
                <th style="width: 18%;">Vence</th>
                <th style="width: 20%;" class="num">Monto anterior</th>
                <th style="width: 20%;" class="num">Ajuste</th>
                <th style="width: 20%;" class="num">Monto nuevo</th>
            </tr></thead>
            <tbody>
                @foreach ($summary['adjusted'] as $row)
                    <tr>
                        <td>{{ $row['invoice'] }}</td>
                        <td>{{ $row['due'] }}</td>
                        <td class="num">{{ $money((int) $row['before_cents']) }}</td>
                        <td class="num">{{ $money((int) $row['delta_cents']) }}</td>
                        <td class="num">{{ $money((int) $row['after_cents']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($adds->isNotEmpty())
        <table><tr><td class="section">Anexo: personas incluidas</td></tr></table>
        <table class="grid">
            <thead><tr>
                <th style="width: 36%;">Nombre</th>
                <th style="width: 17%;">Documento</th>
                <th style="width: 15%;">Movimiento</th>
                <th style="width: 16%;">Desde</th>
                <th style="width: 7%;" class="num">Días</th>
                <th style="width: 9%;" class="num">Monto</th>
            </tr></thead>
            <tbody>
                @foreach ($adds as $line)
                    <tr>
                        <td>{{ $line->full_name }}</td>
                        <td>{{ $line->document }}</td>
                        <td>{{ $line->movement }}</td>
                        <td>{{ $line->covered_from?->format('d/m/Y') ?? '—' }}</td>
                        <td class="num">{{ $line->days }}</td>
                        <td class="num">{{ number_format((float) $line->prorated_amount, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($removes->isNotEmpty())
        <table><tr><td class="section">Anexo: personas excluidas (sin cargo ni crédito en este aviso)</td></tr></table>
        <table class="grid">
            <thead><tr>
                <th style="width: 50%;">Nombre</th>
                <th style="width: 25%;">Documento</th>
                <th style="width: 25%;">Movimiento</th>
            </tr></thead>
            <tbody>
                @foreach ($removes as $line)
                    <tr>
                        <td>{{ $line->full_name }}</td>
                        <td>{{ $line->document }}</td>
                        <td>{{ $line->movement }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="block"><tr><td class="muted">
        Cada monto por persona se redondea a centavos una sola vez y el total es la suma de las personas.
        Referencia interna del ajuste: {{ $adjustment->public_id }}.
    </td></tr></table>
</body>
</html>

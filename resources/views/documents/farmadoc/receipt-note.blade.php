@php
    $brand = '#052F60';
    $date = fn ($value) => $value?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?? '—';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de recibido {{ $order->receipt_number }}</title>
    <style>
        @page { margin: 34px 34px 50px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; }
        .layout { width: 100%; table-layout: fixed; }
        .layout > tbody > tr > td { padding: 0 0 14px 0; vertical-align: top; }
        .bar { width: 100%; table-layout: fixed; }
        .bar td { vertical-align: middle; }
        .bar img { max-height: 46px; max-width: 160px; }
        .bar h1 { margin: 0; font-size: 15px; color: {{ $brand }}; }
        .bar p { margin: 3px 0 0; font-size: 8px; color: #6b7280; }
        .stamp { display: inline-block; padding: 5px 10px; border: 2px solid #15803d; color: #15803d; font-weight: bold; font-size: 10px; letter-spacing: .5px; }
        .rule { height: 3px; background: {{ $brand }}; }
        .facts { width: 100%; table-layout: fixed; }
        .facts td { padding: 7px 9px; border: 1px solid #e5e7eb; background: #f8fafc; vertical-align: top; }
        .label { font-size: 6.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .value { font-size: 10px; font-weight: bold; color: #111827; margin-top: 2px; }
        .data { width: 100%; table-layout: fixed; }
        .data th { background: {{ $brand }}; color: #fff; font-size: 7px; text-transform: uppercase; letter-spacing: .4px; padding: 6px 5px; text-align: left; border: 1px solid {{ $brand }}; }
        .data td { padding: 6px 5px; border: 1px solid #e5e7eb; vertical-align: top; word-wrap: break-word; }
        .num { text-align: center; }
        .short { color: #b45309; font-weight: bold; }
        .muted { color: #6b7280; font-size: 7.5px; }
        .notes { padding: 8px 10px; border: 1px solid #e5e7eb; background: #fffbeb; }
        .signs { width: 100%; table-layout: fixed; }
        .signs td { padding: 26px 18px 0 18px; text-align: center; font-size: 8px; color: #374151; }
        .line { border-top: 1px solid #9ca3af; padding-top: 4px; }
        .foot { font-size: 7px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
<table class="layout">
    <tbody>
    <tr>
        <td>
            <table class="bar">
                <tr>
                    <td style="width: 34%;">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="Tu Dr Group">
                        @endif
                    </td>
                    <td style="width: 42%;">
                        <h1>Nota de recibido y aprobado</h1>
                        <p>Pedido de medicamentos a Farmadoc · IntegraCorp</p>
                    </td>
                    <td style="width: 24%; text-align: right;">
                        <span class="stamp">{{ $complete ? 'RECIBIDO COMPLETO' : 'RECIBIDO PARCIAL' }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr><td><div class="rule"></div></td></tr>
    <tr>
        <td>
            <table class="facts">
                <tr>
                    <td><div class="label">Nota de recibido</div><div class="value">{{ $order->receipt_number }}</div></td>
                    <td><div class="label">Pedido</div><div class="value">{{ $order->order_number }}</div></td>
                    <td><div class="label">Nota de entrega Farmadoc</div><div class="value">{{ $order->delivery_note_number ?: '—' }}</div></td>
                    <td><div class="label">Orden de servicio</div><div class="value">{{ $order->service_order_number ?: '—' }}</div></td>
                </tr>
                <tr>
                    <td><div class="label">Recibido en</div><div class="value">{{ $ubication }}</div></td>
                    <td><div class="label">Fecha de recepción</div><div class="value">{{ $date($order->received_at) }}</div></td>
                    <td><div class="label">Recibido y aprobado por</div><div class="value">{{ $order->received_by_name ?: '—' }}</div></td>
                    <td><div class="label">Unidades</div><div class="value">{{ $received }} de {{ $ordered }}</div></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table class="data">
                <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 40%;">Medicamento</th>
                    <th style="width: 20%;">Código de barras</th>
                    <th style="width: 18%;">SKU</th>
                    <th style="width: 9%;" class="num">Pedido</th>
                    <th style="width: 9%;" class="num">Recibido</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($lines as $index => $line)
                    @php($short = (int) ($line['quantity_received'] ?? 0) < (int) ($line['quantity_ordered'] ?? 0))
                    <tr>
                        <td class="num">{{ $index + 1 }}</td>
                        <td>
                            {{ $line['name'] ?? '—' }}
                            @if ((int) ($line['quantity_received'] ?? 0) === 0)
                                <div class="muted">No llegó</div>
                            @endif
                        </td>
                        <td>{{ $line['barcode'] ?? '—' }}</td>
                        <td>{{ $line['sku'] ?? '—' }}</td>
                        <td class="num">{{ (int) ($line['quantity_ordered'] ?? 0) }}</td>
                        <td class="num {{ $short ? 'short' : '' }}">{{ (int) ($line['quantity_received'] ?? 0) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </td>
    </tr>
    @if (filled($order->receipt_notes))
        <tr>
            <td>
                <div class="notes"><div class="label">Observaciones de la recepción</div>{{ $order->receipt_notes }}</div>
            </td>
        </tr>
    @endif
    <tr>
        <td>
            <table class="signs">
                <tr>
                    <td><div class="line">{{ $order->received_by_name ?: 'Analista de Operaciones' }}<br>Recibido y aprobado · Tu Dr Group</div></td>
                    <td><div class="line">Farmadoc<br>Despacho</div></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="foot">Documento generado por IntegraCorp el {{ now()->format('d/m/Y h:i a') }}. No incluye montos: los precios constan en la nota de entrega de Farmadoc.</td>
    </tr>
    </tbody>
</table>
</body>
</html>

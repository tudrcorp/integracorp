{{-- Factura a pacientes (medicamentos, laboratorios, estudios o especialistas) con el formato de la factura de Tu Dr. en Casa. Determinista: nada de now() ni valores al azar (la huella del HTML decide si se regenera). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $invoice->displayNumber() }}</title>
    <style>
        /* Margen superior de 5 cm: la factura se imprime en hoja membretada (sin logo ni marca de agua). */
        @page { margin: 5cm 0 30px 0; }
        body { margin: 0; font-family: 'Times New Roman', Times, serif; font-size: 11px; color: #1f2937; }
        table.page { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td.page-cell { padding: 0 40px 14px 40px; vertical-align: top; }
        td.page-cell-first { padding: 0 40px 22px 40px; vertical-align: top; }
        td.page-cell-last { padding: 6px 40px 0 40px; vertical-align: top; }
        table.head { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.head td { vertical-align: top; padding: 0; }
        .doc-data { text-align: right; text-transform: uppercase; font-weight: bold; line-height: 1.55; font-size: 11.5px; color: #374151; }
        .client { text-transform: uppercase; font-weight: bold; line-height: 1.6; color: #374151; }
        .patient { text-transform: uppercase; font-size: 10px; color: #4b5563; padding-top: 6px; }
        table.lines { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.lines th { text-transform: uppercase; font-size: 10.5px; color: #374151; text-align: left; padding: 0 4px 6px 4px; border-bottom: 1.5px solid #374151; }
        table.lines td { text-transform: uppercase; padding: 7px 4px 3px 4px; vertical-align: top; word-wrap: break-word; color: #374151; }
        .num, table.lines th.num { text-align: right; }
        .small { font-size: 9px; color: #6b7280; text-transform: none; }
        table.totals { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.totals td { padding: 2px 4px; text-transform: uppercase; font-weight: bold; color: #374151; }
        .details { text-transform: uppercase; font-weight: bold; color: #374151; line-height: 1.6; }
        table.details-row { border-collapse: collapse; }
        table.details-row td { padding: 0 24px 0 0; text-transform: uppercase; font-weight: bold; color: #374151; white-space: nowrap; vertical-align: top; }
        table.details-row td.bcv { padding-right: 0; }
        .annulled-banner { border: 2px solid #ef4444; color: #b91c1c; font-weight: bold; text-align: center; padding: 8px; font-size: 12px; text-transform: uppercase; }
        .paid { font-size: 10px; color: #047857; text-transform: uppercase; font-weight: bold; border-top: 1px solid #d1d5db; padding-top: 8px; }
        .legal { font-size: 8.5px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding: 6px 0 0 0; }
    </style>
</head>
<body>
@use('App\Models\OperationMedicationInvoice')
@use('App\Support\Operations\QuoteInvoicePayer')
@php
    $rate = (float) $invoice->bcv_rate;
    $bs = static fn ($value): string => number_format((float) $value, 2, ',', '.');
    $isCorporate = $invoice->payer_type === OperationMedicationInvoice::PAYER_CORPORATE;
    $clientName = filled($invoice->payer_name) ? $invoice->payer_name : $invoice->patient_name;
    $clientId = filled($invoice->payer_identification) ? $invoice->payer_identification : $invoice->patient_identification;
    if ($isCorporate) {
        $clientId = QuoteInvoicePayer::formatRif($clientId);
    }
    $issuedAt = $invoice->issued_at;
    $dueAt = $invoice->dueDate();
@endphp
<table class="page">
    <tr>
        <td class="page-cell-first">
            <table class="head">
                <tr>
                    <td style="width: 45%;"></td>
                    <td style="width: 55%;" class="doc-data">
                        Factura No. {{ $invoice->displayNumber() }}<br>
                        Fecha de emisión: {{ $issuedAt?->format('d/m/Y') }}<br>
                        Nro. de siniestro: {{ $invoice->claim_number ?: '—' }}<br>
                        Condiciones de pago: {{ OperationMedicationInvoice::PAYMENT_TERMS }}<br>
                        F. Vcto de la factura: {{ $dueAt?->format('d/m/Y') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if ($invoice->status === OperationMedicationInvoice::STATUS_ANNULLED)
        <tr>
            <td class="page-cell">
                <div class="annulled-banner">Factura anulada el {{ $invoice->annulled_at?->format('d/m/Y') }} — sin validez para el cobro</div>
            </td>
        </tr>
    @endif
    <tr>
        <td class="page-cell" style="padding-bottom: 26px;">
            <div class="client">
                Cliente: {{ $clientName }}<br>
                {{ $isCorporate ? 'RIF' : 'C.I.' }}: {{ $clientId ?: '—' }}<br>
                Dirección: {{ $invoice->payer_address ?: '—' }}<br>
                Teléfono: {{ $invoice->payer_phone ?: '—' }}&nbsp;&nbsp;&nbsp;Correo: <span style="text-transform: none;">{{ $invoice->payer_email ?: '—' }}</span>
            </div>
            <div class="patient">
                @if ($isCorporate)
                    Paciente: {{ $invoice->patient_name }}@if (filled($invoice->patient_identification)) · C.I.: {{ $invoice->patient_identification }}@endif · Caso: {{ $invoice->case_code ?: '—' }}
                @else
                    Caso: {{ $invoice->case_code ?: '—' }} · {{ $typeLabel }}
                @endif
            </div>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <table class="lines">
                <tr>
                    <th style="width: 55%;">Descripción</th>
                    <th style="width: 10%;" class="num">Cant</th>
                    <th style="width: 17%;" class="num">Precio unit</th>
                    <th style="width: 18%;" class="num">Monto Bs.</th>
                </tr>
                @foreach ($items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name }}
                            @if (filled($item->product_code))
                                <div class="small">Cód. {{ $item->product_code }}</div>
                            @endif
                        </td>
                        <td class="num">{{ number_format((int) $item->quantity, 0, ',', '.') }}</td>
                        <td class="num">{{ $bs((float) $item->unit_price_usd * $rate) }}</td>
                        <td class="num">{{ $bs((float) $item->line_total_usd * $rate) }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell" style="padding-top: 18px;">
            <table class="totals">
                <tr>
                    <td style="width: 52%;"></td>
                    <td style="width: 28%;">Sub total Bs.:</td>
                    <td style="width: 20%;" class="num">{{ $bs($invoice->total_ves) }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Monto exento Bs.:</td>
                    <td class="num">{{ $bs($invoice->total_ves) }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Base imponible Bs.:</td>
                    <td class="num"></td>
                </tr>
                <tr>
                    <td></td>
                    <td>I.V.A. (16%) Bs.:</td>
                    <td class="num"></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Monto total Bs.:</td>
                    <td class="num">{{ $bs($invoice->total_ves) }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell" style="padding-top: 20px;">
            {{-- La tasa va justo debajo de «B.C.V:»: comparten columna, así no depende del ancho del texto. --}}
            <div class="details">Detalles:</div>
            <table class="details-row">
                <tr>
                    <td colspan="2" style="padding-right: 4px;">Importe en (USD), a la tasa de cambio vigente según</td>
                    <td class="bcv">B.C.V:</td>
                </tr>
                <tr>
                    <td>De fecha: {{ $issuedAt?->format('d/m/Y') }}</td>
                    <td>${{ $bs($invoice->total_usd) }}</td>
                    <td class="bcv">{{ number_format($rate, 4, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    @if ($invoice->status === OperationMedicationInvoice::STATUS_PAID)
        <tr>
            <td class="page-cell">
                <div class="paid">
                    Pagada el {{ $invoice->paid_at?->format('d/m/Y') }} · {{ $paymentMethodLabel }}@if (filled($invoice->payment_reference)) · Ref. {{ $invoice->payment_reference }}@endif
                </div>
            </td>
        </tr>
    @endif
    <tr>
        <td class="page-cell-last">
            <div class="legal">
                @if ($invoice->isFromQuote())
                    {{ $typeLabel }} no cubierto(s) por el plan del paciente, según la cotización aprobada{{ $invoice->operation_quote_generator_id ? ' #'.$invoice->operation_quote_generator_id : '' }}.
                @elseif ($isMedications)
                    Medicamentos no cubiertos por el plan del paciente, entregados desde el inventario Diagnomóvil.
                @else
                    {{ $typeLabel }} no cubierto(s) por el plan del paciente.
                @endif
                Emitida por {{ $invoice->issued_by_name ?: 'Operaciones' }} · Ref. interna {{ $invoice->invoice_number }}.
            </div>
        </td>
    </tr>
</table>
</body>
</html>

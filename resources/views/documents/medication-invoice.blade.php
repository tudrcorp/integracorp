{{-- Factura al paciente (medicamentos, laboratorios, estudios o especialistas). Determinista: nada de now() ni valores al azar (la huella del HTML decide si se regenera). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 24px 0 28px 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }
        table.page { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td.page-cell { padding: 0 34px 12px 34px; vertical-align: top; }
        td.page-cell-first { padding: 6px 34px 14px 34px; vertical-align: top; }
        td.page-cell-last { padding: 4px 34px 0 34px; vertical-align: top; }
        .brand { width: 100%; border-collapse: collapse; table-layout: fixed; border-bottom: 2px solid #052F60; }
        .brand td { padding: 0 0 10px 0; vertical-align: middle; }
        .title { font-size: 16px; font-weight: bold; color: #052F60; }
        .muted { color: #6b7280; }
        .section { font-size: 11px; font-weight: bold; color: #052F60; text-transform: uppercase; letter-spacing: .5px; padding: 0 0 6px 0; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; word-wrap: break-word; }
        td.label { background: #f8fafc; color: #475569; font-weight: bold; }
        table.lines { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.lines th { background: #052F60; color: #ffffff; font-size: 9px; text-align: left; padding: 6px 6px; }
        table.lines td { border-bottom: 1px solid #e5e7eb; padding: 6px 6px; vertical-align: top; word-wrap: break-word; }
        .num, table.lines th.num { text-align: right; }
        .small { font-size: 8.5px; color: #6b7280; }
        table.totals { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.totals td { padding: 4px 8px; }
        .total-usd { font-size: 15px; font-weight: bold; color: #047857; }
        .status { font-weight: bold; padding: 3px 8px; border: 1px solid; }
        .status-pending { color: #b45309; border-color: #f59e0b; }
        .status-paid { color: #047857; border-color: #10b981; }
        .status-annulled { color: #b91c1c; border-color: #ef4444; }
        .annulled-banner { border: 2px solid #ef4444; color: #b91c1c; font-weight: bold; text-align: center; padding: 8px; font-size: 12px; }
        .legal { font-size: 8.5px; color: #6b7280; border-top: 1px solid #e5e7eb; padding: 8px 0 0 0; }
    </style>
</head>
<body>
@php
    $statusClass = match ($invoice->status) {
        \App\Models\OperationMedicationInvoice::STATUS_PAID => 'status-paid',
        \App\Models\OperationMedicationInvoice::STATUS_ANNULLED => 'status-annulled',
        default => 'status-pending',
    };
    $usd = static fn ($value): string => 'US$ '.number_format((float) $value, 2, ',', '.');
@endphp
<table class="page">
    <tr>
        <td class="page-cell-first">
            <table class="brand">
                <tr>
                    <td style="width: 55%;">
                        <div class="title">Factura al paciente</div>
                        <div class="muted">{{ $typeLabel }} · N.º {{ $invoice->invoice_number }} · Emitida el {{ $invoice->issued_at?->format('d/m/Y h:i A') }}</div>
                        <div style="padding-top: 6px;"><span class="status {{ $statusClass }}">{{ mb_strtoupper($statusLabel) }}</span></div>
                    </td>
                    <td style="width: 45%; text-align: right;">
                        @if ($logoDataUri !== '')
                            <img src="{{ $logoDataUri }}" alt="Tu Doctor en Casa" style="height: 42px;">
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if ($invoice->status === \App\Models\OperationMedicationInvoice::STATUS_ANNULLED)
        <tr>
            <td class="page-cell">
                <div class="annulled-banner">FACTURA ANULADA el {{ $invoice->annulled_at?->format('d/m/Y') }} — sin validez para el cobro</div>
            </td>
        </tr>
    @endif
    <tr>
        <td class="page-cell">
            <div class="section">Paciente</div>
            <table class="grid">
                <tr>
                    <td class="label" style="width: 18%;">Nombre</td>
                    <td style="width: 32%;">{{ $invoice->patient_name }}</td>
                    <td class="label" style="width: 18%;">Cédula</td>
                    <td style="width: 32%;">{{ $invoice->patient_identification ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Caso</td>
                    <td>{{ $invoice->case_code ?: '—' }}</td>
                    <td class="label">Servicio médico</td>
                    <td>{{ $invoice->operation_coordination_service_id ? '#'.$invoice->operation_coordination_service_id : '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
    @if (filled($invoice->payer_type))
        <tr>
            <td class="page-cell">
                <div class="section">Responsable de pago · {{ \App\Models\OperationMedicationInvoice::payerLabel($invoice->payer_type) }}</div>
                <table class="grid">
                    <tr>
                        <td class="label" style="width: 18%;">{{ $invoice->payer_type === \App\Models\OperationMedicationInvoice::PAYER_CORPORATE ? 'Razón social' : 'Nombre y apellido' }}</td>
                        <td style="width: 32%;">{{ $invoice->payer_name }}</td>
                        <td class="label" style="width: 18%;">{{ $invoice->payer_type === \App\Models\OperationMedicationInvoice::PAYER_CORPORATE ? 'RIF' : 'Cédula de identidad' }}</td>
                        <td style="width: 32%;">{{ $invoice->payer_identification ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Dirección</td>
                        <td colspan="3">{{ $invoice->payer_address ?: '—' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif
    <tr>
        <td class="page-cell">
            <div class="section">Detalle</div>
            <table class="lines">
                <tr>
                    <th style="width: 40%;">{{ $isMedications ? 'Medicamento' : 'Concepto' }}</th>
                    <th style="width: 20%;">Recetado</th>
                    <th style="width: 10%;" class="num">Cant.</th>
                    <th style="width: 15%;" class="num">Precio unit.</th>
                    <th style="width: 15%;" class="num">Subtotal</th>
                </tr>
                @foreach ($items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name }}
                            @if (filled($item->product_code))
                                <div class="small">Cód. {{ $item->product_code }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $item->prescribed_name }}
                            @if (filled($item->prescribed_indications))
                                <div class="small">{{ $item->prescribed_indications }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ $usd($item->unit_price_usd) }}</td>
                        <td class="num">{{ $usd($item->line_total_usd) }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <table class="totals">
                <tr>
                    <td style="width: 60%;"></td>
                    <td style="width: 20%;" class="num muted">Total</td>
                    <td style="width: 20%;" class="num total-usd">{{ $usd($invoice->total_usd) }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td class="num muted">Equivalente</td>
                    <td class="num">Bs. {{ number_format((float) $invoice->total_ves, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td class="num muted">Tasa BCV</td>
                    <td class="num">{{ number_format((float) $invoice->bcv_rate, 2, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    @if ($invoice->status === \App\Models\OperationMedicationInvoice::STATUS_PAID)
        <tr>
            <td class="page-cell">
                <div class="section">Pago</div>
                <table class="grid">
                    <tr>
                        <td class="label" style="width: 18%;">Fecha</td>
                        <td style="width: 32%;">{{ $invoice->paid_at?->format('d/m/Y') }}</td>
                        <td class="label" style="width: 18%;">Forma de pago</td>
                        <td style="width: 32%;">{{ $paymentMethodLabel }}</td>
                    </tr>
                    <tr>
                        <td class="label">Referencia</td>
                        <td colspan="3">{{ $invoice->payment_reference ?: '—' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif
    <tr>
        <td class="page-cell-last">
            <div class="legal">
                Documento interno de Tu Doctor en Casa: no constituye factura fiscal.
                @if ($invoice->isFromQuote())
                    {{ $typeLabel }} no cubierto(s) por el plan del paciente, según la cotización aprobada{{ $invoice->operation_quote_generator_id ? ' #'.$invoice->operation_quote_generator_id : '' }}.
                @elseif ($isMedications)
                    Medicamentos no cubiertos por el plan del paciente, entregados desde el inventario Diagnomóvil.
                @else
                    {{ $typeLabel }} no cubierto(s) por el plan del paciente.
                @endif
                Emitida por {{ $invoice->issued_by_name ?: 'Operaciones' }}.
            </div>
        </td>
    </tr>
</table>
</body>
</html>

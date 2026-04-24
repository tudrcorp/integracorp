<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de agencia</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #14532d; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { display: table; width: 100%; }
        .brand-logo { display: table-cell; width: 38%; vertical-align: middle; }
        .brand-logo img { max-width: 200px; height: auto; }
        .brand-text { display: table-cell; vertical-align: middle; text-align: right; }
        .brand-text h1 { margin: 0; font-size: 18px; color: #14532d; }
        .brand-text p { margin: 4px 0 0; font-size: 9px; color: #64748b; }
        h2 { font-size: 12px; color: #14532d; margin: 18px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 4px 6px; vertical-align: top; border: 1px solid #e2e8f0; }
        table.meta td.k { width: 26%; background: #f0fdf4; font-weight: bold; color: #334155; }
        .section-notes { page-break-before: always; }
        table.notes { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.notes th, table.notes td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; vertical-align: top; }
        table.notes th { background: #14532d; color: #fff; font-size: 9px; }
        .note-body { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
@php
    $val = fn ($v) => filled($v) ? (string) $v : '—';
    $fmtDate = function ($v): string {
        if (! filled($v)) {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $v;
        }
    };
    $codeLabel = \App\Services\AgencyFichaPdfService::codeLabel($agency);
@endphp

<div class="header">
    <div class="brand">
        <div class="brand-logo">
            @if ($logoDataUri !== '')
                <img src="{{ $logoDataUri }}" alt="Tu Dr en Casa">
            @else
                <strong style="font-size:16px;color:#14532d;">Tu Dr en Casa</strong>
            @endif
        </div>
        <div class="brand-text">
            <h1>Ficha de agencia</h1>
            <p>Generado: {{ $generatedAt->format('d/m/Y H:i') }} · {{ config('app.name') }}</p>
        </div>
    </div>
</div>

<h2>1. Identificación y estructura</h2>
<table class="meta">
    <tr><td class="k">Razón social</td><td>{{ $val($agency->name_corporative) }}</td></tr>
    <tr><td class="k">Código / tipo</td><td>{{ $val($codeLabel) }}</td></tr>
    <tr><td class="k">Estado</td><td>{{ $val($agency->status) }}</td></tr>
    <tr><td class="k">Owner (owner_code)</td><td>{{ $val($agency->owner_code) }}</td></tr>
    <tr><td class="k">Código agente / agencia</td><td>{{ $val($agency->code_agent) }} / {{ $val($agency->code_agency) }}</td></tr>
    <tr><td class="k">Representante</td><td>{{ $val($agency->name_representative) }}</td></tr>
    <tr><td class="k">Account manager</td><td>{{ $val($agency->accountManager?->full_name) }}</td></tr>
    <tr><td class="k">Agentes vinculados</td><td>{{ (int) ($agency->agents_count ?? 0) }}</td></tr>
    <tr><td class="k">Registro</td><td>{{ $fmtDate($agency->date_register) }}</td></tr>
    <tr><td class="k">Alta sistema</td><td>{{ optional($agency->created_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Última modificación</td><td>{{ optional($agency->updated_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Creado por / Actualizado por</td><td>{{ $val($agency->created_by) }} / {{ $val($agency->updated_by) }}</td></tr>
</table>

<h2>Contacto y ubicación</h2>
<table class="meta">
    <tr><td class="k">Correo</td><td>{{ $val($agency->email) }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $val($agency->phone) }}</td></tr>
    <tr><td class="k">Instagram</td><td>{{ $val($agency->user_instagram) }}</td></tr>
    <tr><td class="k">Dirección</td><td>{{ $val($agency->address) }}</td></tr>
    <tr><td class="k">País / Estado / Ciudad</td><td>{{ $val($agency->country?->name) }} / {{ $val($agency->state?->name) }} / {{ $val($agency->city?->name) }}</td></tr>
    <tr><td class="k">Región (texto)</td><td>{{ $val($agency->region) }}</td></tr>
</table>

<h2>Identificación fiscal</h2>
<table class="meta">
    <tr><td class="k">RIF</td><td>{{ $val($agency->rif) }}</td></tr>
    <tr><td class="k">CI responsable</td><td>{{ $val($agency->ci_responsable) }}</td></tr>
</table>

<h2>Contacto secundario</h2>
<table class="meta">
    <tr><td class="k">Nombre</td><td>{{ $val($agency->name_contact_2) }}</td></tr>
    <tr><td class="k">Correo</td><td>{{ $val($agency->email_contact_2) }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $val($agency->phone_contact_2) }}</td></tr>
</table>

<h2>Comisiones y parámetros</h2>
<table class="meta">
    <tr><td class="k">TDEC / TDEV</td><td>{{ $val($agency->tdec) }} / {{ $val($agency->tdev) }}</td></tr>
    <tr><td class="k">Comisión TDEC</td><td>{{ $val($agency->commission_tdec) }}</td></tr>
    <tr><td class="k">Comisión TDEC renovación</td><td>{{ $val($agency->commission_tdec_renewal) }}</td></tr>
    <tr><td class="k">Comisión TDEV</td><td>{{ $val($agency->commission_tdev) }}</td></tr>
    <tr><td class="k">Comisión TDEV renovación</td><td>{{ $val($agency->commission_tdev_renewal) }}</td></tr>
    <tr><td class="k">Frecuencia mensual activa</td><td>{{ $agency->activate_monthly_frequency ? 'Sí' : 'No' }}</td></tr>
    <tr><td class="k">Cumple aniversario / tipo gráfico</td><td>{{ $fmtDate($agency->anniversary_date) }} / {{ $val($agency->type_chart) }}</td></tr>
</table>

<h2>Observaciones</h2>
<table class="meta">
    <tr><td colspan="2" class="note-body">{{ $val($agency->comments) }}</td></tr>
</table>

<h2>Datos bancarios (resumen)</h2>
<table class="meta">
    <tr><td class="k">Beneficiario local</td><td>{{ $val($agency->local_beneficiary_name) }}</td></tr>
    <tr><td class="k">RIF beneficiario</td><td>{{ $val($agency->local_beneficiary_rif) }}</td></tr>
    <tr><td class="k">Cuenta local</td><td>{{ $val($agency->local_beneficiary_account_number) }} · {{ $val($agency->local_beneficiary_account_bank) }}</td></tr>
    <tr><td class="k">Beneficiario extranjero</td><td>{{ $val($agency->extra_beneficiary_name) }}</td></tr>
    <tr><td class="k">Cuenta extranjera</td><td>{{ $val($agency->extra_beneficiary_account_number) }} · {{ $val($agency->extra_beneficiary_account_bank) }}</td></tr>
    <tr><td class="k">Zelle / SWIFT / ABA</td><td>{{ $val($agency->extra_beneficiary_zelle) }} / {{ $val($agency->extra_beneficiary_swift) }} / {{ $val($agency->extra_beneficiary_aba) }}</td></tr>
</table>

<h2>Documentación declarada (sí / no)</h2>
<table class="meta">
    <tr><td class="k">Firma digital</td><td>{{ filled($agency->doc_digital_signature) ? 'Sí' : 'No' }}</td></tr>
    <tr><td class="k">Identidad</td><td>{{ filled($agency->doc_document_identity) ? 'Sí' : 'No' }}</td></tr>
    <tr><td class="k">W8/W9</td><td>{{ filled($agency->doc_w8_w9) ? 'Sí' : 'No' }}</td></tr>
    <tr><td class="k">Datos bancarios VES / USD</td><td>{{ filled($agency->doc_bank_data_ves) ? 'Sí' : 'No' }} / {{ filled($agency->doc_bank_data_usd) ? 'Sí' : 'No' }}</td></tr>
</table>

<div class="section-notes">
    <h2>2. Notas internas (más recientes primero)</h2>
    @if ($notes->isEmpty())
        <p>No hay notas registradas para esta agencia.</p>
    @else
        <table class="notes">
            <thead>
            <tr>
                <th style="width:18%">Fecha</th>
                <th style="width:18%">Autor</th>
                <th>Nota</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($notes as $n)
                <tr>
                    <td>{{ optional($n->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $val($n->created_by) }}</td>
                    <td class="note-body">{{ $val($n->note) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>

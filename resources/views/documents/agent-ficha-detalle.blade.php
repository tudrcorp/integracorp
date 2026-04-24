<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de agente</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #1e3a5f; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { display: table; width: 100%; }
        .brand-logo { display: table-cell; width: 38%; vertical-align: middle; }
        .brand-logo img { max-width: 200px; height: auto; }
        .brand-text { display: table-cell; vertical-align: middle; text-align: right; }
        .brand-text h1 { margin: 0; font-size: 18px; color: #1e3a5f; }
        .brand-text p { margin: 4px 0 0; font-size: 9px; color: #64748b; }
        h2 { font-size: 12px; color: #1e3a5f; margin: 18px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 4px 6px; vertical-align: top; border: 1px solid #e2e8f0; }
        table.meta td.k { width: 26%; background: #f1f5f9; font-weight: bold; color: #334155; }
        .section-notes { page-break-before: always; }
        table.notes { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.notes th, table.notes td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; vertical-align: top; }
        table.notes th { background: #1e3a5f; color: #fff; font-size: 9px; }
        .note-body { white-space: pre-wrap; word-break: break-word; }
        .muted { color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
@php
    $code = 'AGT-000'.$agent->getKey();
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
@endphp

<div class="header">
    <div class="brand">
        <div class="brand-logo">
            @if ($logoDataUri !== '')
                <img src="{{ $logoDataUri }}" alt="Tu Dr en Casa">
            @else
                <strong style="font-size:16px;color:#1e3a5f;">Tu Dr en Casa</strong>
            @endif
        </div>
        <div class="brand-text">
            <h1>Ficha de agente</h1>
            <p>Generado: {{ $generatedAt->format('d/m/Y H:i') }} · {{ config('app.name') }}</p>
        </div>
    </div>
</div>

<h2>1. Identificación y estado</h2>
<table class="meta">
    <tr><td class="k">Código</td><td>{{ $code }}</td></tr>
    <tr><td class="k">Nombre</td><td>{{ $val($agent->name) }}</td></tr>
    <tr><td class="k">Estado registro</td><td>{{ $val($agent->status) }}</td></tr>
    <tr><td class="k">Tipo de agente</td><td>{{ $val($agent->typeAgent?->definition ?? $agent->typeAgent?->name) }}</td></tr>
    <tr><td class="k">Rol / jerarquía</td><td>{{ $val($agent->role) }}</td></tr>
    <tr><td class="k">Código agencia</td><td>{{ $val($agent->code_agency) }}</td></tr>
    <tr><td class="k">Owner code</td><td>{{ $val($agent->owner_code) }}</td></tr>
    <tr><td class="k">Agencia</td><td>{{ $val($agent->agency?->name_corporative ?? $agent->agency?->code) }}</td></tr>
    <tr><td class="k">Registro</td><td>{{ $fmtDate($agent->date_register) }}</td></tr>
    <tr><td class="k">Alta sistema</td><td>{{ optional($agent->created_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Última modificación</td><td>{{ optional($agent->updated_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Creado por / Actualizado por</td><td>{{ $val($agent->created_by) }} / {{ $val($agent->updated_by) }}</td></tr>
</table>

<h2>Datos de contacto</h2>
<table class="meta">
    <tr><td class="k">Correo</td><td>{{ $val($agent->email) }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $val($agent->phone) }}</td></tr>
    <tr><td class="k">Instagram</td><td>{{ $val($agent->user_instagram) }}</td></tr>
    <tr><td class="k">Dirección</td><td>{{ $val($agent->address) }}</td></tr>
    <tr><td class="k">País / Estado / Ciudad</td><td>{{ $val($agent->country?->name) }} / {{ $val($agent->state?->name) }} / {{ $val($agent->city?->name) }}</td></tr>
    <tr><td class="k">Región (texto)</td><td>{{ $val($agent->region) }}</td></tr>
</table>

<h2>Identificación fiscal y personal</h2>
<table class="meta">
    <tr><td class="k">RIF</td><td>{{ $val($agent->rif) }}</td></tr>
    <tr><td class="k">CI</td><td>{{ $val($agent->ci) }}</td></tr>
    <tr><td class="k">Sexo</td><td>{{ $val($agent->sex) }}</td></tr>
    <tr><td class="k">Estado civil</td><td>{{ $val($agent->marital_status) }}</td></tr>
    <tr><td class="k">Fecha de nacimiento</td><td>{{ $fmtDate($agent->birth_date) }}</td></tr>
</table>

<h2>Contacto secundario</h2>
<table class="meta">
    <tr><td class="k">Nombre</td><td>{{ $val($agent->name_contact_2) }}</td></tr>
    <tr><td class="k">Correo</td><td>{{ $val($agent->email_contact_2) }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $val($agent->phone_contact_2) }}</td></tr>
</table>

<h2>Comisiones y parámetros</h2>
<table class="meta">
    <tr><td class="k">TDEC / TDEV</td><td>{{ $val($agent->tdec) }} / {{ $val($agent->tdev) }}</td></tr>
    <tr><td class="k">Comisión TDEC</td><td>{{ $val($agent->commission_tdec) }}</td></tr>
    <tr><td class="k">Comisión TDEC renovación</td><td>{{ $val($agent->commission_tdec_renewal) }}</td></tr>
    <tr><td class="k">Comisión TDEV</td><td>{{ $val($agent->commission_tdev) }}</td></tr>
    <tr><td class="k">Comisión TDEV renovación</td><td>{{ $val($agent->commission_tdev_renewal) }}</td></tr>
    <tr><td class="k">Frecuencia mensual activa</td><td>{{ $agent->activate_monthly_frequency ? 'Sí' : 'No' }}</td></tr>
</table>

<h2>Observaciones generales</h2>
<table class="meta">
    <tr><td colspan="2" class="note-body">{{ $val($agent->comments) }}</td></tr>
</table>

<h2>Datos bancarios (resumen)</h2>
<table class="meta">
    <tr><td class="k">Beneficiario local</td><td>{{ $val($agent->local_beneficiary_name) }}</td></tr>
    <tr><td class="k">RIF beneficiario</td><td>{{ $val($agent->local_beneficiary_rif) }}</td></tr>
    <tr><td class="k">Cuenta local</td><td>{{ $val($agent->local_beneficiary_account_number) }} · {{ $val($agent->local_beneficiary_account_bank) }} ({{ $val($agent->local_beneficiary_account_type) }})</td></tr>
    <tr><td class="k">Beneficiario extranjero</td><td>{{ $val($agent->extra_beneficiary_name) }}</td></tr>
    <tr><td class="k">Cuenta extranjera</td><td>{{ $val($agent->extra_beneficiary_account_number) }} · {{ $val($agent->extra_beneficiary_account_bank) }}</td></tr>
    <tr><td class="k">Zelle / SWIFT / ABA</td><td>{{ $val($agent->extra_beneficiary_zelle) }} / {{ $val($agent->extra_beneficiary_swift) }} / {{ $val($agent->extra_beneficiary_aba) }}</td></tr>
</table>

<div class="section-notes">
    <h2>2. Notas internas (más recientes primero)</h2>
    <p class="muted">Historial del blog de notas asociado al agente. Uso interno.</p>
    @if ($notes->isEmpty())
        <p>No hay notas registradas para este agente.</p>
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

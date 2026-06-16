<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de agencia de viajes</title>
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
        table.meta td.k { width: 28%; background: #f0fdf4; font-weight: bold; color: #334155; }
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
    $codeLabel = \App\Services\TravelAgencyFichaPdfService::codeLabel($travelAgency);
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
            <h1>Ficha de agencia de viajes</h1>
            <p>Generado: {{ $generatedAt->format('d/m/Y H:i') }} · {{ config('app.name') }}</p>
        </div>
    </div>
</div>

<h2>1. Identificación</h2>
<table class="meta">
    <tr><td class="k">Nombre</td><td>{{ $val($travelAgency->name) }}</td></tr>
    <tr><td class="k">Identificación</td><td>{{ $val($codeLabel) }}</td></tr>
    <tr><td class="k">Estado</td><td>{{ $val($travelAgency->status) }}</td></tr>
    <tr><td class="k">Clasificación</td><td>{{ $val($travelAgency->classification) }}</td></tr>
    <tr><td class="k">Nivel</td><td>{{ $val($travelAgency->nivel) }}</td></tr>
    <tr><td class="k">Representante</td><td>{{ $val($travelAgency->representante) }} ({{ $val($travelAgency->idRepresentante) }})</td></tr>
    <tr><td class="k">Fecha nac. representante</td><td>{{ $fmtDate($travelAgency->FechaNacimientoRepresentante) }}</td></tr>
    <tr><td class="k">Aniversario</td><td>{{ $fmtDate($travelAgency->aniversary) }}</td></tr>
    <tr><td class="k">Fecha ingreso</td><td>{{ $fmtDate($travelAgency->fechaIngreso) }}</td></tr>
    <tr><td class="k">Usuario portal web</td><td>{{ $val($travelAgency->userPortalWeb) }}</td></tr>
    <tr><td class="k">Creado / Actualizado</td><td>{{ $val($travelAgency->created_by) }} / {{ $val($travelAgency->updated_by) }}</td></tr>
    <tr><td class="k">Alta sistema</td><td>{{ optional($travelAgency->created_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
</table>

<h2>Contacto y ubicación</h2>
<table class="meta">
    <tr><td class="k">Correo</td><td>{{ $val($travelAgency->email) }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $val($travelAgency->phone) }} · Adicional: {{ $val($travelAgency->phoneAdditional) }}</td></tr>
    <tr><td class="k">Instagram</td><td>{{ $val($travelAgency->userInstagram) }}</td></tr>
    <tr><td class="k">Dirección</td><td>{{ $val($travelAgency->address) }}</td></tr>
    <tr><td class="k">País / Estado / Ciudad</td><td>{{ $val($travelAgency->country?->name) }} / {{ $val($travelAgency->state?->definition) }} / {{ $val($travelAgency->city?->definition) }}</td></tr>
</table>

<h2>Contacto administrativo</h2>
<table class="meta">
    <tr><td class="k">Nombre</td><td>{{ $val($travelAgency->nameSecundario) }}</td></tr>
    <tr><td class="k">Correo</td><td>{{ $val($travelAgency->emailSecundario) }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $val($travelAgency->phoneSecundario) }}</td></tr>
    <tr><td class="k">Fecha nacimiento</td><td>{{ $fmtDate($travelAgency->fechaNacimientoSecundario) }}</td></tr>
</table>

<h2>Jerarquía y comisiones</h2>
<table class="meta">
    <tr><td class="k">Comisión (%)</td><td>{{ $val($travelAgency->comision) }}</td></tr>
    <tr><td class="k">Monto crédito aprobado</td><td>{{ $val($travelAgency->montoCreditoAprobado) }}</td></tr>
    <tr><td class="k">Agente superior N3</td><td>{{ $val($travelAgency->agenteSuperiorNivel3) }}</td></tr>
    <tr><td class="k">Agencia superior N2</td><td>{{ $val($travelAgency->agenciaSuperiorNivel2) }}</td></tr>
    <tr><td class="k">Agencia principal N1</td><td>{{ $val($travelAgency->agenciaPpalNivel1) }}</td></tr>
</table>

<h2>Datos bancarios (resumen)</h2>
<table class="meta">
    <tr><td class="k">Beneficiario local</td><td>{{ $val($travelAgency->local_beneficiary_name) }} · RIF: {{ $val($travelAgency->local_beneficiary_rif) }}</td></tr>
    <tr><td class="k">Pago móvil</td><td>{{ $val($travelAgency->local_beneficiary_phone_pm) }}</td></tr>
    <tr><td class="k">Cuenta Bs.</td><td>{{ $val($travelAgency->local_beneficiary_account_number) }} · {{ $val($travelAgency->local_beneficiary_account_bank) }}</td></tr>
    <tr><td class="k">Cuenta USD/EUR</td><td>{{ $val($travelAgency->local_beneficiary_account_number_mon_inter) }} · {{ $val($travelAgency->local_beneficiary_account_bank_mon_inter) }}</td></tr>
    <tr><td class="k">Beneficiario extranjero</td><td>{{ $val($travelAgency->extra_beneficiary_name) }}</td></tr>
    <tr><td class="k">Cuenta extranjera</td><td>{{ $val($travelAgency->extra_beneficiary_account_number) }} · {{ $val($travelAgency->extra_beneficiary_account_bank) }}</td></tr>
    <tr><td class="k">Zelle / SWIFT / ACH / ABA</td><td>{{ $val($travelAgency->extra_beneficiary_zelle) }} / {{ $val($travelAgency->extra_beneficiary_swift) }} / {{ $val($travelAgency->extra_beneficiary_ach) }} / {{ $val($travelAgency->extra_beneficiary_aba) }}</td></tr>
</table>

<h2>Agentes asociados</h2>
@if ($travelAgency->travelAgents->isEmpty())
    <p>No hay agentes registrados.</p>
@else
    <table class="notes">
        <thead>
        <tr>
            <th>Nombre</th>
            <th>Cargo</th>
            <th>Correo</th>
            <th>Teléfono</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($travelAgency->travelAgents as $agent)
            <tr>
                <td>{{ $val($agent->name) }}</td>
                <td>{{ $val($agent->cargo) }}</td>
                <td>{{ $val($agent->email) }}</td>
                <td>{{ $val($agent->phone) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="section-notes">
    <h2>2. Observaciones comerciales</h2>
    @if ($observations->isEmpty())
        <p>No hay observaciones registradas.</p>
    @else
        <table class="notes">
            <thead>
            <tr>
                <th style="width:18%">Fecha</th>
                <th style="width:18%">Autor</th>
                <th>Observación</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($observations as $observation)
                <tr>
                    <td>{{ $val($observation->date) }}</td>
                    <td>{{ $val($observation->created_by) }}</td>
                    <td class="note-body">{{ $val($observation->observation) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>

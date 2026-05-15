<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FICHA DE AFILIACIÓN CORPORATIVA - {{ $affiliationCorporate->name_corporate ?? $affiliationCorporate->code }}</title>
    <style>
        @page {
            margin: 8mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #333333;
            margin-top: 62px;
            margin-bottom: 44px;
        }

        .page-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
        }

        .page-header-logo {
            text-align: right;
        }

        .page-header-logo img {
            height: 36px;
        }

        .page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 34px;
            padding: 4px 18px 4px;
            border-top: 1.2px solid #0cb7f2;
            font-size: 7px;
            color: #374151;
        }

        .page-footer-inner {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 2px;
            text-align: center;
        }

        .page-footer-logo {
            display: inline-block;
            opacity: 0.7;
        }

        .page-footer-logo img {
            padding-top: 3px;
            padding-bottom: 3px;
            height: 14px;
        }

        .header {
            text-align: left;
            margin: 6px 0 12px;
            padding: 0 12px;
        }

        .header h1 {
            font-size: 13px;
            margin: 0;
            color: #0f172a;
        }

        .header h2 {
            font-size: 10px;
            margin: 3px 0 0 0;
            color: #475569;
        }

        .section-block {
            margin: 8px 0 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #f9fafb;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 8px;
            font-weight: 700;
            margin: 0 0 6px;
            padding-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #0f172a;
            border-bottom: 1px solid #cbd5f5;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .info-table td {
            padding: 2px 3px;
            vertical-align: top;
        }

        .info-table td.label {
            width: 22%;
            font-weight: 600;
            color: #1f2933;
        }

        .info-table td.value {
            width: 28%;
            color: #111827;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 4px;
            font-size: 7px;
            page-break-inside: auto;
        }

        .table th,
        .table td {
            border: 1px solid #e2e8f0;
            padding: 3px 2px;
            text-align: left;
            word-wrap: break-word;
        }

        .table thead {
            display: table-header-group;
            background-color: #eef2ff;
        }

        .table th {
            font-weight: 700;
            color: #1f2937;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .badge {
            display: inline-block;
            padding: 2px 4px;
            border-radius: 3px;
            background-color: #e5f3ff;
            border: 1px solid #b3d4ff;
            font-size: 8px;
        }

        .small-text {
            font-size: 7px;
            color: #555555;
        }
    </style>
</head>
@php
    $ac = $affiliationCorporate;
    $serviceProviders = is_array($ac->service_providers ?? null)
        ? implode(', ', array_map(static fn ($v) => (string) $v, $ac->service_providers))
        : ($ac->service_providers ?? null);
@endphp
<body>
    <div class="page-header">
        <div class="page-header-logo">
            <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo">
        </div>
    </div>

    <div class="page-footer">
        <div class="page-footer-inner">
            <div>Generado {{ now()->format('d/m/Y H:i') }} · Código {{ $ac->code ?? '—' }}</div>
            <div class="page-footer-logo">
                <img src="{{ asset('image/logoNewPdf.png') }}" alt="">
            </div>
        </div>
    </div>

    <div class="header">
        <h1>FICHA DE AFILIACIÓN CORPORATIVA</h1>
        <h2>{{ $ac->name_corporate ?? '—' }} @if(filled($ac->code)) · {{ $ac->code }} @endif</h2>
    </div>

    <div class="section-block">
        <div class="section-title">Datos generales</div>
        <table class="info-table">
            <tr>
                <td class="label">Código de afiliación</td>
                <td class="value">@if(filled($ac->code))<span class="badge">{{ $ac->code }}</span>@else — @endif</td>
                <td class="label">RIF</td>
                <td class="value">@if(filled($ac->rif))<span class="badge">{{ \App\Support\AffiliationCorporateRifLabel::withJPrefix($ac->rif) }}</span>@else — @endif</td>
            </tr>
            <tr>
                <td class="label">Estatus</td>
                <td class="value">{{ $ac->status ?? '—' }}</td>
                <td class="label">Tipo</td>
                <td class="value">{{ $ac->type ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono</td>
                <td class="value">{{ $ac->phone ?? '—' }}</td>
                <td class="label">Correo</td>
                <td class="value">{{ $ac->email ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value" colspan="3">{{ $ac->address ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">País</td>
                <td class="value">{{ data_get($ac, 'country.name', '—') }}</td>
                <td class="label">Estado</td>
                <td class="value">{{ data_get($ac, 'state.definition', data_get($ac, 'state.name', '—')) }}</td>
            </tr>
            <tr>
                <td class="label">Ciudad</td>
                <td class="value">{{ data_get($ac, 'city.definition', data_get($ac, 'city.name', '—')) }}</td>
                <td class="label">Región</td>
                <td class="value">{{ data_get($ac, 'region.definition', '—') }}</td>
            </tr>
            <tr>
                <td class="label">Agencia</td>
                <td class="value">{{ data_get($ac, 'agency.name_corporative', '—') }}</td>
                <td class="label">Agente</td>
                <td class="value">{{ data_get($ac, 'agent.name', '—') }}</td>
            </tr>
            <tr>
                <td class="label">Vigencia</td>
                <td class="value">{{ $ac->effective_date ?? '—' }}</td>
                <td class="label">Activación</td>
                <td class="value">{{ $ac->activated_at ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Población</td>
                <td class="value">{{ $ac->poblation ?? '—' }}</td>
                <td class="label">Frecuencia de pago</td>
                <td class="value">{{ $ac->payment_frequency ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Tarifa anual (titular)</td>
                <td class="value">{{ $ac->fee_anual ?? '—' }}</td>
                <td class="label">Monto total</td>
                <td class="value">{{ $ac->total_amount ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Proveedores de servicio</td>
                <td class="value" colspan="3">{{ filled($serviceProviders) ? $serviceProviders : '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Contacto corporativo</div>
        <table class="info-table">
            <tr>
                <td class="label">Nombre contacto</td>
                <td class="value">{{ $ac->full_name_contact ?? '—' }}</td>
                <td class="label">Identificación contacto</td>
                <td class="value">{{ $ac->nro_identificacion_contact ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono contacto</td>
                <td class="value">{{ $ac->phone_contact ?? '—' }}</td>
                <td class="label">Correo contacto</td>
                <td class="value">{{ $ac->email_contact ?? '—' }}</td>
            </tr>
        </table>
    </div>

    @include('documents.partials.billing-collections-ficha-table', ['billingCollections' => $ac->billingCollections])

    @if(filled($ac->observations))
        <div class="section-block">
            <div class="section-title">Observaciones</div>
            <p class="small-text" style="margin:0; white-space: pre-wrap;">{{ $ac->observations }}</p>
        </div>
    @endif

    <div class="section-block" style="page-break-inside: auto;">
        <div class="section-title">Afiliados asociados ({{ $ac->corporateAffiliates->count() }})</div>
        @if ($ac->corporateAffiliates->isEmpty())
            <p class="small-text" style="margin:0;">No hay afiliados registrados para esta afiliación.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>C.I.</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Nac.</th>
                        <th>Edad</th>
                        <th>Sexo</th>
                        <th>Plan</th>
                        <th>Tarifa</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ac->corporateAffiliates as $idx => $row)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>{{ $row->first_name ?? '—' }}</td>
                            <td>{{ $row->nro_identificacion ?? '—' }}</td>
                            <td>{{ $row->email ?? '—' }}</td>
                            <td>{{ $row->phone ?? '—' }}</td>
                            <td>{{ $row->birth_date ?? '—' }}</td>
                            <td>{{ $row->age ?? '—' }}</td>
                            <td>{{ $row->sex ?? '—' }}</td>
                            <td>{{ data_get($row, 'plan.description', '—') }}</td>
                            <td>{{ $row->fee ?? '—' }}</td>
                            <td>{{ $row->status ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>

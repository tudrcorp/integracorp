<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del Proveedor - {{ $supplier->name }}</title>
    <style>
        @page {
            margin: 10mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #333333;
            margin-top: 70px;
            margin-bottom: 50px;
        }

        .page-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
        }

        .page-header-logo {
            text-align: right;
        }

        .page-header-logo img {
            height: 40px;
        }

        .page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 38px;
            padding: 6px 24px 4px;
            border-top: 1.2px solid #0cb7f2;
            font-size: 8px;
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
            padding-top: 5px;
            padding-bottom: 5px;
            height: 16px;
        }

        .header {
            text-align: left;
            margin: 10px 0 16px;
            padding: 0 20px;
        }

        .header h1 {
            font-size: 14px;
            margin: 0;
            color: #0f172a;
        }

        .header h2 {
            font-size: 11px;
            margin: 4px 0 0 0;
            color: #475569;
        }

        .section-block {
            margin: 10px 0 14px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #f9fafb;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            margin: 0 0 8px;
            padding-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #0f172a;
            border-bottom: 1px solid #cbd5f5;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .info-table td {
            padding: 3px 3px;
            vertical-align: top;
        }

        .info-table td.label {
            width: 32%;
            font-weight: 600;
            color: #1f2933;
        }

        .info-table td.value {
            width: 68%;
            color: #111827;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 6px;
            font-size: 8px;
            page-break-inside: auto;
        }

        .table th,
        .table td {
            border: 1px solid #e2e8f0;
            padding: 4px 3px;
            text-align: left;
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
            padding: 2px 5px;
            border-radius: 3px;
            background-color: #e5f3ff;
            border: 1px solid #b3d4ff;
            font-size: 10px;
        }

        .boolean-yes {
            color: #0a7a2a;
            font-weight: bold;
        }

        .boolean-no {
            color: #aa0000;
            font-weight: bold;
        }

        .small-text {
            font-size: 9px;
            color: #555555;
        }

        .mt-10 {
            margin-top: 10px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .infra-check-cell {
            vertical-align: middle;
        }

        .infra-check-badge {
            display: inline-block;
            box-sizing: border-box;
            width: 15px;
            height: 15px;
            line-height: 13px;
            font-size: 9px;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
            border-radius: 50%;
            border: 1.2px solid #059669;
            background-color: #d1fae5;
            color: #047857;
        }

        .infra-cert-columns {
            width: 100%;
            display: table;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 8px 0;
        }

        .infra-cert-col {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
        }

        .infra-cert-col .table {
            width: 100%;
        }
    </style>
</head>
@php
    $typeService = is_array($supplier->type_service ?? null)
        ? implode(', ', $supplier->type_service)
        : ($supplier->type_service ?? null);

    $stateServices = is_array($supplier->state_services ?? null)
        ? implode(', ', $supplier->state_services)
        : ($supplier->state_services ?? null);
@endphp
<body>
    <div class="page-header">
        <div class="page-header-logo">
            <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo">
        </div>
    </div>

    <div class="header">
        <h1>Ficha del Proveedor</h1>
        <h2>{{ $supplier->name ?? '-' }}</h2>
    </div>

    <div class="section-block">
        <div class="section-title">Proveedor - Información General</div>

        <table class="info-table">
            <tr>
                <td class="label">Nombre del Proveedor:</td>
                <td class="value">
                    @if (! empty($supplier->name))
                        <span class="badge">{{ $supplier->name }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">RIF:</td>
                <td class="value">
                    @if (! empty($supplier->rif))
                        <span class="badge">{{ $supplier->rif }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Razón Social:</td>
                <td class="value">
                    {{ $supplier->razon_social ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Estatus del Convenio:</td>
                <td class="value">
                    {{ $supplier->status_convenio ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Estatus del Sistema:</td>
                <td class="value">
                    {{ $supplier->status_sistema ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Clasificación del Proveedor:</td>
                <td class="value">
                    @if (optional($supplier->SupplierClasificacion)->description)
                        <span class="badge">
                            {{ $supplier->SupplierClasificacion->description }}
                        </span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Categoría del Proveedor:</td>
                <td class="value">
                    @if (! empty($supplier->tipo_clinica))
                        <span class="badge">{{ $supplier->tipo_clinica }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Tipo de Servicio:</td>
                <td class="value">
                    @if (! empty($typeService))
                        <span class="badge">{{ $typeService }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Cobertura Geográficas:</td>
                <td class="value">
                    {{ $supplier->tipo_servicio ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Estado:</td>
                <td class="value">
                    {{ optional($supplier->state)->definition ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Ciudad:</td>
                <td class="value">
                    {{ optional($supplier->city)->definition ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Presta Servicios en:</td>
                <td class="value">
                    @if (! empty($stateServices))
                        <span class="badge">{{ $stateServices }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Teléfono Celular:</td>
                <td class="value">
                    {{ $supplier->personal_phone ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Teléfono Local:</td>
                <td class="value">
                    {{ $supplier->local_phone ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Correo Electrónico:</td>
                <td class="value">
                    {{ $supplier->correo_principal ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Fecha de Afiliación del Proveedor:</td>
                <td class="value">
                    @if (! empty($supplier->afiliacion_proveedor))
                        {{ $supplier->afiliacion_proveedor }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Convenio de Pago:</td>
                <td class="value">
                    {{ $supplier->convenio_pago ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Tiempo de Crédito:</td>
                <td class="value">
                    {{ $supplier->tiempo_credito ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Promedio de Costo del Proveedor:</td>
                <td class="value">
                    {{ $supplier->promedio_costo_proveedor ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="label">Dirección Principal:</td>
                <td class="value">
                    {{ $supplier->ubicacion_principal ?? '-' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Contactos Principales</div>

        <table class="table">
            <thead>
                <tr>
                    <th>Departamento</th>
                    <th>Cargo</th>
                    <th>Nombre y Apellido</th>
                    <th>Correo Electrónico</th>
                    <th>Teléfono Celular</th>
                    <th>Teléfono Local</th>
                    <th>Extensión(es)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplier->supplierContactPrincipals ?? [] as $contact)
                    <tr>
                        <td>{{ $contact->departament ?? '-' }}</td>
                        <td>{{ $contact->position ?? '-' }}</td>
                        <td>{{ $contact->name ?? '-' }}</td>
                        <td>{{ $contact->email ?? '-' }}</td>
                        <td>{{ $contact->personal_phone ?? '-' }}</td>
                        <td>{{ $contact->local_phone ?? '-' }}</td>
                        <td>{{ $contact->extensions ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No posee contactos principales</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Información de Sucursales</div>

        <table class="table">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Ciudad</th>
                    <th>Nombre y Apellido</th>
                    <th>Correo Electrónico</th>
                    <th>Teléfono Celular</th>
                    <th>Teléfono Local</th>
                    <th>Dirección de Ubicación</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplier->supplierRedGlobals ?? [] as $branch)
                    <tr>
                        <td>{{ optional($branch->state)->definition ?? '-' }}</td>
                        <td>{{ optional($branch->city)->definition ?? '-' }}</td>
                        <td>{{ $branch->name ?? '-' }}</td>
                        <td>{{ $branch->email ?? '-' }}</td>
                        <td>{{ $branch->personal_phone ?? '-' }}</td>
                        <td>{{ $branch->local_phone ?? '-' }}</td>
                        <td>{{ $branch->address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No posee Sucursales</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Zonas de Cobertura</div>

        <table class="table">
            <thead>
                <tr>
                    <th>Clasificación del Proveedor</th>
                    <th>Tipo de Servicio</th>
                    <th>Estado</th>
                    <th>Ciudad</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplier->SupplierZonaCoberturas ?? [] as $zone)
                    @php
                        $zoneTypeService = is_array($zone->type_service ?? null)
                            ? implode(', ', $zone->type_service)
                            : ($zone->type_service ?? null);
                    @endphp
                    <tr>
                        <td>{{ optional($zone->supplierClasificacion)->description ?? '-' }}</td>
                        <td>{{ $zoneTypeService ?? '-' }}</td>
                        <td>{{ optional($zone->state)->definition ?? '-' }}</td>
                        <td>{{ optional($zone->city)->definition ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No posee Zonas de Cobertura</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Certificación de Infraestructura</div>

        @php
            $infraSi = static fn (mixed $v): bool => filter_var($v, FILTER_VALIDATE_BOOLEAN);
            $oncologiaDispone = $infraSi($supplier->oncologia ?? $supplier->encologogia ?? false);
            $infraestructuraCertificada = collect([
                ['nombre' => 'Urgencias', 'dispone' => $infraSi($supplier->urgen_care ?? false)],
                ['nombre' => 'Consultas APS', 'dispone' => $infraSi($supplier->consulta_aps ?? false)],
                ['nombre' => 'Asistencia médica domiciliaria', 'dispone' => $infraSi($supplier->amd ?? false)],
                ['nombre' => 'Laboratorio en centro', 'dispone' => $infraSi($supplier->laboratorio_centro ?? false)],
                ['nombre' => 'Laboratorio en domicilio', 'dispone' => $infraSi($supplier->laboratorio_domicilio ?? false)],
                ['nombre' => 'Rayos X en centro', 'dispone' => $infraSi($supplier->rx_centro ?? false)],
                ['nombre' => 'Rayos X en domicilio', 'dispone' => $infraSi($supplier->rx_domicilio ?? false)],
                ['nombre' => 'Ecografía abdominal en centro', 'dispone' => $infraSi($supplier->eco_abdominal_centro ?? false)],
                ['nombre' => 'Ecografía abdominal en domicilio', 'dispone' => $infraSi($supplier->eco_abdominal_domicilio ?? false)],
                ['nombre' => 'Electrocardiógrafo en domicilio', 'dispone' => $infraSi($supplier->electrocardiograma_domicilio ?? false)],
                ['nombre' => 'Densitómetro', 'dispone' => $infraSi($supplier->densitometria_osea ?? false)],
                ['nombre' => 'Equipo de diálisis', 'dispone' => $infraSi($supplier->dialisis ?? false)],
                ['nombre' => 'Electrocardiógrafo en centro', 'dispone' => $infraSi($supplier->electrocardiograma_centro ?? false)],
                ['nombre' => 'Equipos especiales de oftalmología', 'dispone' => $infraSi($supplier->equipos_especiales_oftalmologia ?? false)],
                ['nombre' => 'Mamógrafo', 'dispone' => $infraSi($supplier->mamografia ?? false)],
                ['nombre' => 'Quirófanos', 'dispone' => $infraSi($supplier->quirofanos ?? false)],
                ['nombre' => 'Radioterapia intraoperatoria', 'dispone' => $infraSi($supplier->radioterapia_intraoperatoria ?? false)],
                ['nombre' => 'Resonador', 'dispone' => $infraSi($supplier->resonancia ?? false)],
                ['nombre' => 'Tomógrafo', 'dispone' => $infraSi($supplier->tomografo ?? false)],
                ['nombre' => 'Oncología', 'dispone' => $oncologiaDispone],
                ['nombre' => 'UCI UTE', 'dispone' => $infraSi($supplier->uci_uten ?? false)],
                ['nombre' => 'Cuidados neonatales', 'dispone' => $infraSi($supplier->neonatal ?? false)],
                ['nombre' => 'Ambulancias', 'dispone' => $infraSi($supplier->ambulancias ?? false)],
                ['nombre' => 'Odontología', 'dispone' => $infraSi($supplier->odontologia ?? false)],
                ['nombre' => 'Oftalmología', 'dispone' => $infraSi($supplier->oftalmologia ?? false)],
                ['nombre' => 'UCI pediátrica', 'dispone' => $infraSi($supplier->uci_pediatrica ?? false)],
                ['nombre' => 'UCI adulto', 'dispone' => $infraSi($supplier->uci_adulto ?? false)],
                ['nombre' => 'Estacionamiento propio', 'dispone' => $infraSi($supplier->estacionamiento_propio ?? false)],
                ['nombre' => 'Ascensor operativo', 'dispone' => $infraSi($supplier->ascensor ?? false)],
                ['nombre' => 'Equipo de cirugía robótica', 'dispone' => $infraSi($supplier->robotica ?? false)],
                ['nombre' => 'Otras unidades especializadas', 'dispone' => $infraSi($supplier->otras_unidades_especiales ?? false)],
            ])->filter(fn (array $fila): bool => $fila['dispone'])->values();

            $totalInfra = $infraestructuraCertificada->count();
            $tamanoColumnaInfra = (int) max(1, ceil($totalInfra / 3));
            $columnasInfraestructura = collect(range(0, 2))->map(
                fn (int $indice): \Illuminate\Support\Collection => $infraestructuraCertificada->slice(
                    $indice * $tamanoColumnaInfra,
                    $tamanoColumnaInfra,
                ),
            );
        @endphp

        @if ($infraestructuraCertificada->isEmpty())
            <table class="table">
                <tbody>
                    <tr>
                        <td colspan="2">Ninguna infraestructura registrada como «Sí» en certificación.</td>
                    </tr>
                </tbody>
            </table>
        @else
            <div class="infra-cert-columns">
                @foreach ($columnasInfraestructura as $columnaInfraestructura)
                    <div class="infra-cert-col">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Infraestructura</th>
                                    <th>¿Dispone?</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($columnaInfraestructura as $fila)
                                    <tr>
                                        <td>{{ $fila['nombre'] }}</td>
                                        <td class="text-center infra-check-cell">
                                            <span class="infra-check-badge" title="Sí" aria-hidden="true">&#10003;</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Otros servicios</div>

        <table class="info-table">
            <tr>
                <td class="label">Detalle:</td>
                <td class="value">
                    @if (! empty($supplier->otros_servicios))
                        {!! nl2br(e((string) $supplier->otros_servicios)) !!}
                    @else
                        No posee otros servicios.
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Bitácora de Notas y/o Observaciones</div>

        <table class="table">
            <thead>
                <tr>
                    <th>Notas y/o Observación</th>
                    <th>Responsable de la Nota</th>
                    <th>Fecha de la Nota</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplier->supplierObservacions ?? [] as $note)
                    <tr>
                        <td>{{ $note->observation ?? '-' }}</td>
                        <td>{{ $note->created_by ?? '-' }}</td>
                        <td>
                            @if (! empty($note->created_at))
                                {{ \Carbon\Carbon::parse($note->created_at)->format('d/m/Y H:i:s') }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">No posee Notas y/o Observaciones</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-footer">
        <div class="page-footer-inner">
            <div class="page-footer-logo">
                <img src="{{ asset('image/logoNewPdf.png') }}" alt="Integracorp">
            </div>
            <div class="page-footer-text">
                Información generada automáticamente desde @INTEGRACORP V1.0.
                <br>
                Todos los derechos reservados 2026.
                <br>
                Generado el {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>
</body>
</html>


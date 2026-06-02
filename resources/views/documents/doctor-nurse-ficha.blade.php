<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del proveedor natural</title>
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
        .section-block { margin: 10px 0 14px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f9fafb; page-break-inside: avoid; }
        .infra-group { margin-bottom: 10px; }
        .infra-group-title { font-size: 9px; font-weight: 700; color: #14532d; margin: 0 0 6px; text-transform: uppercase; letter-spacing: 0.03em; }
        .table { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9px; }
        .table th, .table td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; vertical-align: middle; }
        .table thead { background-color: #f0fdf4; }
        .table th { font-weight: 700; color: #334155; }
        .text-center { text-align: center; }
        .infra-check-cell { vertical-align: middle; text-align: center; padding: 5px 4px; }
        .infra-cert-badge-img { display: inline-block; width: 22px; height: 22px; vertical-align: middle; }
        .infra-cert-badge-fallback { margin: 0 auto; }
        .infra-cert-badge-fallback-cell {
            width: 22px;
            height: 22px;
            background-color: #16a34a;
            color: #ffffff;
            text-align: center;
            vertical-align: middle;
            font-size: 15px;
            font-weight: bold;
            font-family: DejaVu Sans, sans-serif;
            border-radius: 11px;
            line-height: 22px;
        }
        .infra-cert-columns { width: 100%; display: table; table-layout: fixed; border-collapse: separate; border-spacing: 8px 0; }
        .infra-cert-col { display: table-cell; width: 33.33%; vertical-align: top; }
        .infra-cert-col .table { width: 100%; }
        .infra-equip-desc { margin-top: 3px; font-size: 8px; color: #475569; font-style: italic; line-height: 1.35; white-space: pre-wrap; word-break: break-word; }
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

    $stateLabel = $doctorNurse->state?->definition ?? $doctorNurse->state?->name ?? $doctorNurse->state ?? null;
    $cityLabel = $doctorNurse->city?->definition ?? $doctorNurse->city?->name ?? $doctorNurse->city ?? null;
    $notes = collect($doctorNurse->doctorNurseObservacions ?? [])->sortByDesc('created_at')->values();
    $infraSi = static fn (mixed $v): bool => filter_var($v, FILTER_VALIDATE_BOOLEAN);
    $filtrarCertificados = static function (array $items) use ($doctorNurse, $infraSi): \Illuminate\Support\Collection {
        return collect($items)
            ->filter(fn (array $item): bool => $infraSi($doctorNurse->{$item['field']} ?? false))
            ->map(function (array $item) use ($doctorNurse): array {
                $descField = (string) preg_replace('/^equip_/', 'equip_desc_', $item['field']);
                $descripcion = trim((string) ($doctorNurse->{$descField} ?? ''));

                return [
                    'nombre' => $item['nombre'],
                    'field' => $item['field'],
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                ];
            })
            ->values();
    };
    $gruposEquipamientoDomiciliario = [
        'Instrumental de diagnóstico' => $filtrarCertificados([
            ['nombre' => 'Estetoscopio y tensiómetro', 'field' => 'equip_diag_vital_signs'],
            ['nombre' => 'Oxímetro de pulso', 'field' => 'equip_diag_oximeter'],
            ['nombre' => 'Termómetro digital o infrarrojo', 'field' => 'equip_diag_thermometer'],
            ['nombre' => 'Estuche de diagnóstico (otoscopio/oftalmoscopio)', 'field' => 'equip_diag_exam_kit'],
            ['nombre' => 'Glucómetro', 'field' => 'equip_diag_glucometer'],
            ['nombre' => 'Linterna de exploración y martillo de reflejos', 'field' => 'equip_diag_flashlight_hammer'],
        ]),
        'Material descartable de cura' => $filtrarCertificados([
            ['nombre' => 'Guantes de nitrilo o látex', 'field' => 'equip_care_gloves'],
            ['nombre' => 'Antisépticos y limpieza', 'field' => 'equip_care_antiseptics'],
            ['nombre' => 'Material de cura', 'field' => 'equip_care_supplies'],
            ['nombre' => 'Contenedor de punzocortantes', 'field' => 'equip_care_sharps_container'],
        ]),
        'Equipamiento de apoyo y seguridad' => $filtrarCertificados([
            ['nombre' => 'Desinfectante de manos y jabón', 'field' => 'equip_support_hygiene'],
            ['nombre' => 'Tijeras y pinzas', 'field' => 'equip_support_scissors_forceps'],
            ['nombre' => 'Recetas médicas y sellos profesionales', 'field' => 'equip_support_prescriptions_stamps'],
        ]),
        'Elementos avanzados o de urgencia' => $filtrarCertificados([
            ['nombre' => 'Medicamentos básicos', 'field' => 'equip_adv_basic_medicines'],
            ['nombre' => 'Sondas y material de aspiración', 'field' => 'equip_adv_catheters_aspiration'],
            ['nombre' => 'Maletín de urgencias', 'field' => 'equip_adv_emergency_bag'],
        ]),
    ];
    $equipamientoDomiciliarioCertificado = collect($gruposEquipamientoDomiciliario)
        ->flatMap(fn (\Illuminate\Support\Collection $items): \Illuminate\Support\Collection => $items);
@endphp

<div class="header">
    <div class="brand">
        <div class="brand-logo">
            @if (filled($logoDataUri ?? null))
                <img src="{{ $logoDataUri }}" alt="Tu Dr en Casa">
            @else
                <strong style="font-size:16px;color:#14532d;">Tu Dr en Casa</strong>
            @endif
        </div>
        <div class="brand-text">
            <h1>Ficha del proveedor natural</h1>
            <p>Generado: {{ ($generatedAt ?? now())->format('d/m/Y H:i') }} · {{ config('app.name') }}</p>
        </div>
    </div>
</div>

<h2>1. Identificación y estructura</h2>
<table class="meta">
    <tr><td class="k">Nombre comercial</td><td>{{ $val($doctorNurse->name) }}</td></tr>
    <tr><td class="k">Razón social</td><td>{{ $val($doctorNurse->razon_social) }}</td></tr>
    <tr><td class="k">RIF</td><td>{{ $val($doctorNurse->rif) }}</td></tr>
    <tr><td class="k">Clasificación</td><td>{{ $val($doctorNurse->supplierClasificacion->description ?? null) }}</td></tr>
    <tr><td class="k">Especialidad</td><td>{{ $val($doctorNurse->speciality) }}</td></tr>
    <tr><td class="k">Registro</td><td>{{ $fmtDate($doctorNurse->date_register ?? null) }}</td></tr>
    <tr><td class="k">Alta sistema</td><td>{{ optional($doctorNurse->created_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Última modificación</td><td>{{ optional($doctorNurse->updated_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Creado por / Actualizado por</td><td>{{ $val($doctorNurse->created_by) }} / {{ $val($doctorNurse->updated_by) }}</td></tr>
</table>

<h2>Contacto y ubicación</h2>
<table class="meta">
    <tr><td class="k">Estado</td><td>{{ $val($stateLabel) }}</td></tr>
    <tr><td class="k">Ciudad</td><td>{{ $val($cityLabel) }}</td></tr>
    <tr><td class="k">Zona de cobertura</td><td>{{ $val($doctorNurse->coverage_zone) }}</td></tr>
    <tr><td class="k">Dirección principal</td><td>{{ $val($doctorNurse->ubicacion_principal) }}</td></tr>
    <tr><td class="k">Horario</td><td>{{ $val($doctorNurse->horario) }}</td></tr>
    <tr><td class="k">Teléfono personal</td><td>{{ $val($doctorNurse->personal_phone) }}</td></tr>
    <tr><td class="k">Teléfono local</td><td>{{ $val($doctorNurse->local_phone) }}</td></tr>
    <tr><td class="k">Correo principal</td><td>{{ $val($doctorNurse->correo_principal) }}</td></tr>
</table>

<h2>Condiciones comerciales</h2>
<table class="meta">
    <tr><td class="k">Estatus convenio</td><td>{{ $val($doctorNurse->status_convenio) }}</td></tr>
    <tr><td class="k">Estatus sistema</td><td>{{ $val($doctorNurse->status_sistema) }}</td></tr>
    <tr><td class="k">Convenio de pago</td><td>{{ $val($doctorNurse->convenio_pago) }}</td></tr>
    <tr><td class="k">Tiempo de crédito</td><td>{{ $val($doctorNurse->tiempo_credito) }}</td></tr>
</table>

<h2>Certificación de infraestructura domiciliaria</h2>
<div class="section-block">
    @if ($equipamientoDomiciliarioCertificado->isEmpty())
        <table class="table">
            <tbody>
                <tr>
                    <td>Ningún equipamiento registrado como disponible.</td>
                </tr>
            </tbody>
        </table>
    @else
        @foreach ($gruposEquipamientoDomiciliario as $grupoTitulo => $itemsGrupo)
            @if ($itemsGrupo->isNotEmpty())
                <div class="infra-group">
                    <p class="infra-group-title">{{ $grupoTitulo }}</p>
                    @php
                        $totalGrupo = $itemsGrupo->count();
                        $tamanoColumna = (int) max(1, ceil($totalGrupo / 2));
                        $columnasGrupo = collect(range(0, 1))->map(
                            fn (int $indice): \Illuminate\Support\Collection => $itemsGrupo->slice(
                                $indice * $tamanoColumna,
                                $tamanoColumna,
                            ),
                        )->filter(fn (\Illuminate\Support\Collection $columna): bool => $columna->isNotEmpty());
                    @endphp
                    <div class="infra-cert-columns">
                        @foreach ($columnasGrupo as $columna)
                            <div class="infra-cert-col">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Equipamiento</th>
                                            <th style="width: 18%;">Certificado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($columna as $item)
                                            <tr>
                                                <td>
                                                    {{ $item['nombre'] }}
                                                    @if (filled($item['descripcion'] ?? null))
                                                        <div class="infra-equip-desc">{{ $item['descripcion'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="infra-check-cell">
                                                    @include('documents.partials.infra-cert-check-badge-pdf')
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    @endif
</div>

<div class="section-notes">
    <h2>2. Notas internas (más recientes primero)</h2>
    @if ($notes->isEmpty())
        <p>No hay notas registradas para este proveedor natural.</p>
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
            @foreach ($notes as $note)
                <tr>
                    <td>{{ optional($note->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $val($note->created_by) }}</td>
                    <td class="note-body">{{ $val($note->observation ?? $note->note ?? null) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>


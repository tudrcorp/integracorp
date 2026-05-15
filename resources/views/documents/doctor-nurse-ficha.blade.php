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


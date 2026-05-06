<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha técnica</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
        .muted { color: #475569; }
        .section { margin-top: 14px; }
        .section h2 { font-size: 13px; font-weight: 700; margin: 0 0 6px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .label { width: 32%; font-weight: 700; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="title">Ficha técnica del proveedor natural</div>
    <div class="muted">
        Generado: {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="section">
        <h2>Identidad</h2>
        <table class="grid">
            <tr>
                <td class="label">Nombre comercial</td>
                <td>{{ $doctorNurse->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Razón social</td>
                <td>{{ $doctorNurse->razon_social ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">RIF</td>
                <td>{{ $doctorNurse->rif ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Clasificación</td>
                <td>{{ $doctorNurse->supplierClasificacion->description ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Especialidad</td>
                <td>{{ $doctorNurse->speciality ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Ubicación y operación</h2>
        <table class="grid">
            <tr>
                <td class="label">Estado</td>
                <td>{{ $doctorNurse->state ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Ciudad</td>
                <td>{{ $doctorNurse->city ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Zona de cobertura</td>
                <td>{{ $doctorNurse->coverage_zone ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección principal</td>
                <td>{{ $doctorNurse->ubicacion_principal ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Horario</td>
                <td>{{ $doctorNurse->horario ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Estatus convenio</td>
                <td>{{ $doctorNurse->status_convenio ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Estatus sistema</td>
                <td>{{ $doctorNurse->status_sistema ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Contacto</h2>
        <table class="grid">
            <tr>
                <td class="label">Teléfono personal</td>
                <td>{{ $doctorNurse->personal_phone ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono local</td>
                <td>{{ $doctorNurse->local_phone ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Correo principal</td>
                <td>{{ $doctorNurse->correo_principal ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Convenio de pago</td>
                <td>{{ $doctorNurse->convenio_pago ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Tiempo de crédito</td>
                <td>{{ $doctorNurse->tiempo_credito ?? '—' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>


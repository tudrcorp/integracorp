<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del Prospecto - {{ $prospect->name }}</title>
    <style>
        @page {
            margin: 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #333333;
            margin: 0;
        }

        .header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #052F60;
        }

        .brand-logo {
            display: table-cell;
            width: 40%;
            vertical-align: middle;
        }

        .brand-logo img {
            height: 42px;
        }

        .brand-text {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }

        .brand-text h1 {
            margin: 0;
            font-size: 16px;
            color: #052F60;
        }

        .brand-text p {
            margin: 4px 0 0;
            font-size: 9px;
            color: #64748b;
        }

        .section-block {
            margin: 10px 0 14px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #f8fafc;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            margin: 0 0 8px;
            padding-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #052F60;
            border-bottom: 1px solid #cbd5e1;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 4px;
            vertical-align: top;
        }

        .info-table td.label {
            width: 30%;
            font-weight: 600;
            color: #1e293b;
        }

        .info-table td.value {
            color: #334155;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 8.5px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }

        .data-table th {
            background-color: #052F60;
            color: #ffffff;
            font-weight: 700;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f1f5f9;
        }

        .muted {
            color: #94a3b8;
            font-style: italic;
        }

        .note-body {
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
@php
    use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;

    $val = static fn ($v): string => filled($v) ? (string) $v : '—';
    $contacts = collect($prospect->prospectAgentContacts ?? []);
    $observations = collect($prospect->prospect_agent_observations ?? [])->take(12);
    $tasks = collect($prospect->prospect_agent_tasks ?? [])->take(12);
@endphp

<div class="header">
    <div class="brand-logo">
        @if (filled($logoDataUri ?? null))
            <img src="{{ $logoDataUri }}" alt="Tu Doctor Group">
        @else
            <strong style="font-size:16px;color:#052F60;">Tu Doctor Group</strong>
        @endif
    </div>
    <div class="brand-text">
        <h1>Ficha del Prospecto</h1>
        <p>Generado: {{ ($generatedAt ?? now())->format('d/m/Y H:i') }} · {{ config('app.name') }}</p>
    </div>
</div>

<div class="section-block">
    <p class="section-title">Información general</p>
    <table class="info-table">
        <tr>
            <td class="label">Nombre</td>
            <td class="value">{{ $val($prospect->name) }}</td>
        </tr>
        <tr>
            <td class="label">Tipo</td>
            <td class="value">{{ ProspectAgentLabels::typeLabel($prospect->type) }}</td>
        </tr>
        <tr>
            <td class="label">Estatus</td>
            <td class="value">{{ ProspectAgentLabels::statusLabel($prospect->status) }}</td>
        </tr>
        <tr>
            <td class="label">Clasificación</td>
            <td class="value">{{ $val($prospect->classification) }}</td>
        </tr>
        <tr>
            <td class="label">Referido por</td>
            <td class="value">{{ ProspectAgentLabels::referenceLabel($prospect->reference_by) }}</td>
        </tr>
        <tr>
            <td class="label">Observaciones iniciales</td>
            <td class="value">{{ $val($prospect->initial_observ) }}</td>
        </tr>
        <tr>
            <td class="label">Captado el</td>
            <td class="value">{{ optional($prospect->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Última actualización</td>
            <td class="value">{{ optional($prospect->updated_at)->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Creado por / Actualizado por</td>
            <td class="value">{{ $val($prospect->created_by) }} / {{ $val($prospect->updated_by) }}</td>
        </tr>
    </table>
</div>

<div class="section-block">
    <p class="section-title">Contacto</p>
    <table class="info-table">
        <tr>
            <td class="label">Teléfono principal</td>
            <td class="value">{{ $val($prospect->phone_1) }}</td>
        </tr>
        <tr>
            <td class="label">Teléfono alternativo</td>
            <td class="value">{{ $val($prospect->phone_2) }}</td>
        </tr>
        <tr>
            <td class="label">Correo electrónico</td>
            <td class="value">{{ $val($prospect->email) }}</td>
        </tr>
        <tr>
            <td class="label">Instagram</td>
            <td class="value">{{ $val($prospect->instagram) }}</td>
        </tr>
        <tr>
            <td class="label">Página web</td>
            <td class="value">{{ $val($prospect->website) }}</td>
        </tr>
        <tr>
            <td class="label">Redes sociales</td>
            <td class="value">{{ $val($prospect->social_networks) }}</td>
        </tr>
    </table>
</div>

<div class="section-block">
    <p class="section-title">Personas de contacto</p>
    @if ($contacts->isEmpty())
        <p class="muted">Sin personas de contacto registradas.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Nombre</th>
                    <th style="width: 22%;">Cargo</th>
                    <th style="width: 22%;">Teléfono</th>
                    <th style="width: 28%;">Correo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contacts as $contact)
                    <tr>
                        <td>{{ $val($contact->name) }}</td>
                        <td>{{ $val($contact->position) }}</td>
                        <td>{{ $val($contact->phone) }}</td>
                        <td>{{ $val($contact->email) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="section-block">
    <p class="section-title">Ubicación</p>
    <table class="info-table">
        <tr>
            <td class="label">País</td>
            <td class="value">{{ $val($prospect->country?->name ?? null) }}</td>
        </tr>
        <tr>
            <td class="label">Estado</td>
            <td class="value">{{ $val($prospect->state?->definition ?? $prospect->state?->name ?? null) }}</td>
        </tr>
        <tr>
            <td class="label">Ciudad</td>
            <td class="value">{{ $val($prospect->city?->definition ?? $prospect->city?->name ?? null) }}</td>
        </tr>
        <tr>
            <td class="label">Dirección</td>
            <td class="value">{{ $val($prospect->address) }}</td>
        </tr>
    </table>
</div>

<div class="section-block">
    <p class="section-title">Tareas de seguimiento (últimas {{ $tasks->count() }})</p>
    @if ($tasks->isEmpty())
        <p class="muted">Sin tareas registradas.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 42%;">Tarea</th>
                    <th style="width: 18%;">Responsable</th>
                    <th style="width: 14%;">Estatus</th>
                    <th style="width: 18%;">Creada</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tasks as $task)
                    <tr>
                        <td>#{{ $task->id }}</td>
                        <td class="note-body">{{ $val($task->task) }}</td>
                        <td>{{ $val($task->rrhh_colaborador?->fullName ?? null) }}</td>
                        <td>{{ $val($task->status) }}</td>
                        <td>{{ optional($task->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="section-block">
    <p class="section-title">Observaciones (últimas {{ $observations->count() }})</p>
    @if ($observations->isEmpty())
        <p class="muted">Sin observaciones registradas.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Fecha</th>
                    <th style="width: 18%;">Creado por</th>
                    <th style="width: 64%;">Nota</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($observations as $observation)
                    <tr>
                        <td>{{ optional($observation->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
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

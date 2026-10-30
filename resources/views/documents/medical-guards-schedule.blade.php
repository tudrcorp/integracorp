<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guardias Médicas</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 16px; color: #052F60; margin: 0 0 2px; }
        h2 { font-size: 12px; color: #052F60; margin: 16px 0 6px; }
        p { margin: 0; color: #6b7280; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; word-wrap: break-word; }
        th { background: #052F60; color: #fff; }
    </style>
</head>
<body>
    <h1>Guardias Médicas</h1>
    <p>{{ $monthLabel }}</p>

    @forelse ($sites as $siteName => $guards)
        <h2>{{ $siteName }}</h2>
        <table>
            <thead>
                <tr><th style="width: 18%">Fecha</th><th style="width: 42%">Médico</th><th style="width: 40%">Horario</th></tr>
            </thead>
            <tbody>
                @foreach ($guards as $guard)
                    <tr>
                        <td>{{ $guard->guard_date->format('d/m/Y') }}</td>
                        <td>{{ trim((string) $guard->doctor?->full_name) }}</td>
                        <td>{{ $guard->scheduleLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="margin-top: 16px">No hay guardias cargadas este mes.</p>
    @endforelse
</body>
</html>

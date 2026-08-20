@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.').' US$';
    $palette = match ($status) {
        'valid' => ['#065f46', '#d1fae5', '✓', 'Reporte auténtico'],
        'mismatch' => ['#991b1b', '#fee2e2', '✕', 'La llave no coincide'],
        'not_found' => ['#92400e', '#fef3c7', '!', 'Llave no encontrada'],
        default => ['#1f2937', '#f3f4f6', '?', 'Verificación de reportes'],
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación de reporte — Tu Dr Group</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f8fafc;
               margin: 0; padding: 24px; color: #1f2937; }
        .card { max-width: 620px; margin: 40px auto; background: #fff; border-radius: 18px;
                box-shadow: 0 12px 40px rgba(15,23,42,.08); overflow: hidden; }
        .head { padding: 28px 28px 20px; background: {{ $palette[1] }}; }
        .badge { display: inline-flex; align-items: center; justify-content: center; width: 40px;
                 height: 40px; border-radius: 999px; background: {{ $palette[0] }}; color: #fff;
                 font-size: 20px; font-weight: 700; }
        h1 { margin: 14px 0 4px; font-size: 20px; color: {{ $palette[0] }}; }
        .key { font-family: ui-monospace, monospace; font-size: 15px; letter-spacing: 1.5px;
               color: #111827; font-weight: 700; }
        .body { padding: 24px 28px 30px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
        td:last-child { text-align: right; font-weight: 600; }
        .muted { color: #6b7280; font-size: 13px; line-height: 1.55; }
        form { display: flex; gap: 8px; margin-top: 18px; }
        input { flex: 1; padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; }
        button { padding: 11px 20px; border: 0; border-radius: 10px; background: #052F60; color: #fff;
                 font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <span class="badge">{{ $palette[2] }}</span>
            <h1>{{ $palette[3] }}</h1>
            @if ($key !== '')
                <span class="key">{{ $key }}</span>
            @endif
        </div>
        <div class="body">
            @if ($status === 'valid' && $issue !== null)
                <p class="muted">Este reporte fue emitido por IntegraCorp y su contenido coincide con el registrado.</p>
                <table>
                    <tr><td>Empresa aliada</td><td>{{ $issue['company'] }}</td></tr>
                    <tr><td>Período</td><td>{{ $issue['from'] }} al {{ $issue['to'] }}</td></tr>
                    <tr><td>Afiliaciones</td><td>{{ $issue['rows'] }}</td></tr>
                    <tr><td>Monto total a pagar</td><td>{{ $money($issue['totals']['sale_price'] ?? 0) }}</td></tr>
                    <tr><td>Neta TDG</td><td>{{ $money($issue['totals']['neta_tdg'] ?? 0) }}</td></tr>
                    <tr><td>Neta empresa aliada</td><td>{{ $money($issue['totals']['neta_partner'] ?? 0) }}</td></tr>
                    <tr><td>Emitido</td><td>{{ $issue['issued_at'] }}</td></tr>
                </table>
                <p class="muted" style="margin-top:18px;">
                    Compare estas cifras con las del PDF que recibió. Si difieren, el archivo fue modificado
                    después de su emisión.
                </p>
            @elseif ($status === 'mismatch')
                <p class="muted">
                    Encontramos un reporte con datos similares, pero la llave no corresponde. No podemos
                    certificar este documento: contacte a administración de Tu Dr Group.
                </p>
            @elseif ($status === 'not_found')
                <p class="muted">
                    No existe ningún reporte emitido con esa llave. Verifique que la copió completa, tal
                    como aparece en el pie del PDF.
                </p>
            @else
                <p class="muted">
                    Introduzca la llave que aparece en el pie del reporte para comprobar su autenticidad.
                </p>
            @endif

            <form method="GET" action="{{ route('white-company-sales-report.verify') }}">
                <input type="text" name="llave" value="{{ $key }}" placeholder="TDG-XXXX-XXXX-XXXX-XXXX" required>
                <button type="submit">Verificar</button>
            </form>
        </div>
    </div>
</body>
</html>

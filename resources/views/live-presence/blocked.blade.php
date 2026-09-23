<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso bloqueado · IntegraCorp</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f8fafc; color: #0f172a; }
        .card { max-width: 440px; margin: 24px; padding: 32px; background: #fff; border: 1px solid #e5e7eb; border-top: 4px solid #dc2626; border-radius: 16px; text-align: center; }
        h1 { font-size: 20px; margin: 12px 0 8px; }
        p { color: #475569; line-height: 1.6; margin: 0; }
        .icon { font-size: 40px; }
        @media (prefers-color-scheme: dark) { body { background: #0b1220; color: #e2e8f0; } .card { background: #111a2e; border-color: #1f2a44; } p { color: #94a3b8; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⛔</div>
        <h1>Acceso bloqueado</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>

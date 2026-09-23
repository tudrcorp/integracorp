<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>{{ $title ?? 'Monitor en vivo' }} · IntegraCorp</title>
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}">
    <style>
        html, body { margin: 0; padding: 0; background: #060b16; color: #e2e8f0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        body { min-height: 100vh; }
    </style>
</head>
<body>
    {{ $slot }}

    <script>
        /**
         * La pantalla nunca debe quedarse colgada: si la sesión anónima vence (419),
         * el servidor reinicia o hay un error, se recarga sola a los 5 segundos.
         */
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    preventDefault();
                    setTimeout(() => window.location.reload(), status === 404 ? 60000 : 5000);
                });
            });
        });

        /** Respaldo: recarga completa cada 6 horas para liberar memoria del navegador. */
        setTimeout(() => window.location.reload(), 6 * 60 * 60 * 1000);
    </script>
</body>
</html>

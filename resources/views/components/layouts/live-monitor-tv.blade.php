<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>{{ $title ?? 'Monitor en vivo' }} · IntegraCorp</title>
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    {{-- Solo el CSS (Tailwind + Flux): la TV no necesita el JS del sitio público. --}}
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-950 font-sans text-zinc-100 antialiased">
    {{ $slot }}

    @fluxScripts

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

        /**
         * En una TV nadie hace scroll: si el contenido no cabe, cada 25 s baja
         * suavemente hasta el final y luego vuelve arriba. Si alguien mueve la
         * página a mano, el ciclo se pausa un minuto.
         */
        (() => {
            const stepMs = 25000;
            let pausedUntil = 0;
            let goingDown = true;
            let autoScrolling = false;

            ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach((type) => {
                window.addEventListener(type, () => { pausedUntil = Date.now() + 60000; }, { passive: true });
            });

            setInterval(() => {
                const room = document.documentElement.scrollHeight - window.innerHeight;

                if (room <= 8 || Date.now() < pausedUntil || autoScrolling) {
                    return;
                }

                autoScrolling = true;
                window.scrollTo({ top: goingDown ? room : 0, behavior: 'smooth' });
                goingDown = ! goingDown;
                setTimeout(() => { autoScrolling = false; }, 2000);
            }, stepMs);
        })();

        /** Respaldo: recarga completa cada 6 horas para liberar memoria del navegador. */
        setTimeout(() => window.location.reload(), 6 * 60 * 60 * 1000);
    </script>
</body>
</html>

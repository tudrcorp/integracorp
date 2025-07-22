<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Styles -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

</head>
<body class="bg-gray-300 dark:bg-[#0a0a0a] flex flex-col justify-center items-center min-h-screen text-center px-4">
    <!-- Contenedor principal -->
    <div class="flex flex-col justify-center items-center space-y-6 min-h-screen">
        <!-- Imagen centrada verticalmente -->
        <div class="flex justify-center items-center w-xl">
            <img src="{{ asset('image/logo_new.png') }}" alt="Logo Tu Dr. en Casa">
            </div>

        <!-- Texto de bienvenida -->
        <h1 class="text-sm text-gray-400 dark:text-gray-400">
            ¡Bienvenido a TuDrEnCasa<br>Nos comprometemos a cuidarte hoy y asegurar un mañana en bienestar.!
        </h1>

        <div class="flex items-center justify-center gap-1">
            <nav class="flex items-center justify-end gap-2">
                <a href="{{ route('volt.agency.create') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] bg-[#0064a1] border-[#19140035] hover:bg-[#529471] border text-white dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-xl text-sm leading-normal">
                    REGISTRAR AGENCIA
                </a>
                <a href="{{ route('volt.agent.create') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] bg-[#0064a1] border-[#19140035] hover:bg-[#529471] border text-white dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-xl text-sm leading-normal">
                    REGISTRAR AGENTE
                </a>
            </nav>
        </div>
    </div>

    <!-- Footer -->
    <footer class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-xs text-gray-500">
        &copy; {{ date('Y') }} TuDrenCasa.com Todos los derechos reservados.
    </footer>

</body>
</html>




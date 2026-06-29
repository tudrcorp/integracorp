<!DOCTYPE html>
<html lang="es" class="dark h-dvh overflow-hidden">
<head>
    @include('partials.guia-chat-head')
</head>
<body class="h-dvh overflow-hidden bg-gradient-to-br from-[#0b1f4a] via-[#0d4f6e] to-[#14b8a6]">

    {{ $slot }}

    @include('pwa.install-guia-chat')

    @fluxScripts
    @persist('toast')
        <flux:toast />
    @endpersist
</body>
</html>

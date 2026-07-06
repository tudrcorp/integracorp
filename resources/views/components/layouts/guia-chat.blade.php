<!DOCTYPE html>
<html lang="es" class="dark guia-chat-app overflow-hidden">
<head>
    @include('partials.guia-chat-head')
</head>
<body class="guia-chat-app overflow-hidden bg-[#0b1f4a]">

    {{ $slot }}

    @include('pwa.install-guia-chat')

    @fluxScripts
    @persist('toast')
        <flux:toast />
    @endpersist
</body>
</html>

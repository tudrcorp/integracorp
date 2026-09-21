<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark min-h-dvh">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh overflow-x-hidden overflow-y-auto bg-gradient-to-br from-[#0b1f4a] via-[#0d4f6e] to-[#14b8a6]">

    {{ $slot }}

    @fluxScripts
    @persist('toast')
        <flux:toast /> 
    @endpersist
</body>
</html>


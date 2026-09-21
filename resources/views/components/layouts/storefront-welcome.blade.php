<!DOCTYPE html>
<html lang="es" class="storefront-app">
<head>
    @include('partials.storefront-head', ['title' => $title ?? 'Bienvenida'])
</head>
<body class="storefront-app is-welcome" x-data>
    <div class="storefront-atmosphere" aria-hidden="true">
        <span class="storefront-atmosphere__orb storefront-atmosphere__orb--a"></span>
        <span class="storefront-atmosphere__orb storefront-atmosphere__orb--b"></span>
        <span class="storefront-atmosphere__noise"></span>
    </div>

    <div class="storefront-shell sf-welcome-shell">
        {{ $slot }}
    </div>

    @fluxScripts
    @persist('toast')
        <flux:toast />
    @endpersist
</body>
</html>

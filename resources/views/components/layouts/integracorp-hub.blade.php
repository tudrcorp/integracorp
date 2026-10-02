@php
    $icHubAuthRoute = request()->routeIs('home', 'integracorp.hub.password.request', 'integracorp.hub.password.reset');
@endphp
<!DOCTYPE html>
<html lang="es" @class(['scroll-smooth' => ! $icHubAuthRoute])>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>IntegraCorp - Sistema Integral de Gestión para Empresas</title>
    <meta name="application-name" content="IntegraCorp">
    <meta name="apple-mobile-web-app-title" content="IntegraCorp">
    <meta name="description" content="IntegraCorp — Sistema integral de gestión de Tu Doctor Group.">
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}" type="image/png">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('image/ico_Android_IOS.png') }}">
    <script>
        try {
            if (localStorage.getItem('ic-hub-theme') === 'light') {
                document.documentElement.classList.add('ic-hub-theme-light');
            }
        } catch (error) {
            //
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Manrope:wght@300;400;500;600;700&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @include('integracorp.partials.hub-styles')
    @if ($icHubAuthRoute)
        @include('integracorp.partials.hub-auth-styles')
    @endif
</head>
<body class="ic-hub-body @if ($icHubAuthRoute) ic-hub-body--auth @endif">
    @unless ($icHubAuthRoute)
    <div class="ic-hub-bg" aria-hidden="true">
        <img src="{{ asset('image/i2.jpg') }}" alt="" class="ic-hub-bg__photo ic-hub-bg__photo--auth" />
        <img src="{{ asset('image/hub-bg-light.jpg') }}" alt="" class="ic-hub-bg__photo ic-hub-bg__photo--modules ic-hub-bg__photo--modules-dark" />
        <img src="{{ asset('image/hub-bg-modules-light.jpg') }}" alt="" class="ic-hub-bg__photo ic-hub-bg__photo--modules ic-hub-bg__photo--modules-light" />
        <div class="ic-hub-bg__veil"></div>
        <div class="ic-hub-bg__vignette"></div>
        @include('integracorp.partials.hub-modules-sparkles')
        <div class="ic-hub-bg__grid"></div>
        @include('integracorp.partials.hub-modules-ribbons')
    </div>

    @unless (request()->routeIs('integracorp.hub.modules'))
        @include('integracorp.partials.hub-brand-logo')
    @endunless
    @endunless

    {{ $slot }}

    @unless ($icHubAuthRoute)
    <footer class="ic-hub-footer">
        @include('integracorp.partials.hub-footer-copy')
    </footer>
    @endunless

    @include('integracorp.partials.hub-theme-sync')
    @include('integracorp.partials.hub-theme-toggle-alpine')
    @include('integracorp.partials.hub-hall-clock-alpine')
    @include('integracorp.partials.hub-modules-carousel-alpine')

    @fluxScripts
</body>
</html>

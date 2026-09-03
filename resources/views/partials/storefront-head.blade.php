@php
    $storefrontUrl = url('/app');
    $storefrontDescription = 'Conoce los planes de Tu Dr En Casa, cotiza en minutos y lleva tu asistencia médica en el bolsillo.';
    $storefrontImage = url('/pwa/icon-512.png');
    $pageTitle = $title ?? 'Tu Dr En Casa';
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1" />
<meta name="view-transition" content="same-origin">

<title>{{ $pageTitle }} · Tu Dr En Casa</title>

<meta name="description" content="{{ $storefrontDescription }}">
<meta name="robots" content="index, follow">
<meta name="author" content="Tu Dr En Casa">
<meta name="application-name" content="Tu Dr En Casa">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Tu Dr En Casa">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#020914">
<meta name="format-detection" content="telephone=no">

<link rel="canonical" href="{{ url()->current() }}">

<meta property="og:title" content="{{ $pageTitle }} · Tu Dr En Casa">
<meta property="og:description" content="{{ $storefrontDescription }}">
<meta property="og:image" content="{{ $storefrontImage }}">
<meta property="og:url" content="{{ $storefrontUrl }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_VE">

<link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('pwa/storefront.webmanifest') }}">

<script type="application/ld+json">
{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'Tu Dr En Casa',
    'url' => $storefrontUrl,
    'description' => $storefrontDescription,
    'applicationCategory' => 'HealthApplication',
    'operatingSystem' => 'Web, Android, iOS',
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'USD',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Tu Dr En Casa',
        'url' => url('/'),
    ],
    'image' => $storefrontImage,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|instrument-serif:400,400i,500,600" rel="stylesheet" />

@vite(['resources/css/storefront.css', 'resources/js/app.js'])
@fluxAppearance

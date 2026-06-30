@php
    $guiaChatUrl = url('/chat/publico');
    $guiaChatDescription = 'GUIA-CHAT de Integracorp: asistente guiado para registrar agentes, subagentes y agencias. Disponible en web y como app en tu móvil.';
    $guiaChatImage = url('/pwa/guia-chat/icon-512.png');
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

<title>GUIA-CHAT | Integracorp — Asistente para agentes y agencias</title>

<meta name="description" content="{{ $guiaChatDescription }}">
<meta name="keywords" content="GUIA-CHAT, Integracorp, registro agente, registro agencia, asistente virtual, salud, corretaje, Venezuela">
<meta name="robots" content="index, follow">
<meta name="author" content="Integracorp">
<meta name="application-name" content="GUIA-CHAT">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="GUIA-CHAT">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#0d4f6e">

<link rel="canonical" href="{{ $guiaChatUrl }}">

<meta property="og:title" content="GUIA-CHAT | Integracorp">
<meta property="og:description" content="{{ $guiaChatDescription }}">
<meta property="og:image" content="{{ $guiaChatImage }}">
<meta property="og:url" content="{{ $guiaChatUrl }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Integracorp">
<meta property="og:locale" content="es_VE">

<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="GUIA-CHAT | Integracorp">
<meta name="twitter:description" content="{{ $guiaChatDescription }}">
<meta name="twitter:image" content="{{ $guiaChatImage }}">

<link rel="icon" href="{{ asset('pwa/guia-chat/icon-192.png') }}" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/guia-chat/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('pwa/guia-chat.webmanifest') }}">

<script type="application/ld+json">
{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'GUIA-CHAT Integracorp',
    'alternateName' => 'GUIA-CHAT',
    'url' => $guiaChatUrl,
    'description' => $guiaChatDescription,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web, Android, iOS',
    'browserRequirements' => 'Requires JavaScript. Requires HTML5.',
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'USD',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Integracorp',
        'url' => url('/'),
    ],
    'image' => $guiaChatImage,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<style>
    [x-cloak] {
        display: none !important;
    }

    .guia-chat-composer-input {
        -ms-overflow-style: none;
        scrollbar-width: none;
        box-shadow: none;
    }

    .guia-chat-composer-input::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }

    .guia-chat-composer-input:focus,
    .guia-chat-composer-input:focus-visible {
        outline: none;
        box-shadow: none;
    }

    .guia-chat-typing-dot {
        display: block;
        height: 0.4rem;
        width: 0.4rem;
        border-radius: 9999px;
        background-color: rgb(255 255 255 / 0.92);
        animation: guia-chat-typing-dot 1.2s ease-in-out infinite;
    }

    .guia-chat-typing-dot:nth-child(2) {
        animation-delay: 0.15s;
        background-color: rgb(255 255 255 / 0.72);
    }

    .guia-chat-typing-dot:nth-child(3) {
        animation-delay: 0.3s;
        background-color: rgb(255 255 255 / 0.52);
    }

    @keyframes guia-chat-typing-dot {
        0%,
        60%,
        100% {
            transform: translateY(0);
            opacity: 0.35;
        }

        30% {
            transform: translateY(-4px);
            opacity: 1;
        }
    }
</style>

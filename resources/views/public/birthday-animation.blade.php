<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>
        @if ($guestName !== '')
            Feliz cumpleaños · {{ \Illuminate\Support\Str::limit($guestName, 60) }}
        @else
            Animación cumpleaños · Tu Doctor Group
        @endif
    </title>
    @php($assetBase = '/animacion-cumpleanos')
    <base href="{{ $assetBase }}/">
    <link rel="icon" href="/image/ico_Android_IOS.png" type="image/png">
    <link rel="apple-touch-icon" href="/image/ico_Android_IOS.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            height: 100%;
            background: #17335E;
            overflow: hidden;
        }
        #root {
            width: 100vw;
            height: 100vh;
            background: #17335E;
        }
        [data-om-starter="animations-v3"] {
            background: #17335E !important;
        }
        [data-om-starter="animations-v3"] > div > div:first-child {
            overflow: visible !important;
        }
        [data-om-starter="animations-v3"] svg {
            box-shadow: none !important;
            overflow: visible !important;
        }
        [data-om-starter="animations-v3"] foreignObject > div {
            overflow: visible !important;
        }
        /* Experiencia de tarjeta interactiva, no reproductor de video */
        [data-omelette-chrome] {
            display: none !important;
        }
        .birthday-edge-stars {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 5;
            overflow: hidden;
        }
        .birthday-edge-star {
            position: absolute;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0 6px rgba(255, 255, 255, 0.35);
            opacity: 0;
            will-change: opacity, transform;
            animation: birthday-star-twinkle ease-in-out infinite;
        }
        @keyframes birthday-star-twinkle {
            0%, 100% {
                opacity: 0;
            }
            50% {
                opacity: 0.95;
            }
        }
        @keyframes birthday-mobile-chrome-in {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .birthday-mobile-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            padding: max(10px, env(safe-area-inset-top, 0px)) 16px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            pointer-events: none;
            font-family: Helvetica, 'Helvetica Neue', Arial, sans-serif;
            will-change: opacity, transform;
        }
        .birthday-mobile-header.is-entering {
            animation: birthday-mobile-chrome-in 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .birthday-mobile-header-logo {
            width: 100%;
            max-width: 320px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 52px;
        }
        .birthday-mobile-header-logo img {
            height: clamp(48px, 11vw, 64px);
            width: auto;
            max-width: 92%;
            object-fit: contain;
        }
        .birthday-mobile-header-copy {
            width: 100%;
            max-width: 360px;
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .birthday-mobile-header-script {
            margin: 0;
            font-family: 'Dancing Script', cursive;
            font-weight: 700;
            font-size: clamp(2rem, 8.5vw, 2.75rem);
            line-height: 1.15;
            color: #fff;
            max-width: 100%;
            text-wrap: balance;
        }
        .birthday-mobile-header-headline {
            margin: 0;
            font-size: clamp(1.65rem, 7.5vw, 2.25rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.05;
            color: #fff;
        }
        .birthday-mobile-header-subline {
            margin: 10px 0 0;
            font-family: Verdana, Tahoma, sans-serif;
            font-size: clamp(0.78rem, 3.4vw, 0.95rem);
            line-height: 1.45;
            color: #D7DBDE;
            max-width: 320px;
        }
        .birthday-carousel-host {
            position: fixed;
            left: 0;
            right: 0;
            top: 26%;
            bottom: 22%;
            z-index: 8;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: visible;
            touch-action: none;
            user-select: none;
            -webkit-user-select: none;
        }
        .birthday-carousel-viewport {
            width: 100%;
            height: 100%;
            overflow: visible;
            perspective: clamp(720px, 120vw, 980px);
            perspective-origin: 50% 36%;
            transform-style: preserve-3d;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .birthday-carousel-ring {
            position: relative;
            width: 1px;
            height: 1px;
            transform-style: preserve-3d;
            will-change: transform;
        }
        .birthday-carousel-item {
            position: absolute;
            left: 50%;
            top: 50%;
            transform-style: preserve-3d;
            border-radius: 18px;
            cursor: pointer;
            transition: filter 0.25s ease;
        }
        .birthday-carousel-item--used {
            cursor: default;
            filter: grayscale(0.35);
            opacity: 0.72;
            pointer-events: none;
        }
        .birthday-carousel-item-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
        }
        .birthday-carousel-face {
            position: absolute;
            inset: 0;
            border-radius: 18px;
            overflow: hidden;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            box-shadow: 0 18px 42px rgba(8, 20, 40, 0.42);
            background: #17335E;
        }
        .birthday-carousel-face--back {
            transform: rotateY(180deg);
        }
        .birthday-carousel-face img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            pointer-events: none;
        }
        .birthday-mobile-footer {
            position: fixed;
            left: 0;
            right: auto;
            bottom: 0;
            z-index: 9;
            padding: 0 16px max(14px, env(safe-area-inset-bottom, 0px)) 18px;
            max-width: min(92vw, 320px);
            text-align: left;
            pointer-events: none;
            font-family: Helvetica, 'Helvetica Neue', Arial, sans-serif;
            will-change: opacity, transform;
        }
        .birthday-mobile-footer.is-entering {
            animation: birthday-mobile-chrome-in 0.95s cubic-bezier(0.22, 1, 0.36, 1) 0.12s both;
        }
        @media (prefers-reduced-motion: reduce) {
            .birthday-mobile-header.is-entering,
            .birthday-mobile-footer.is-entering {
                animation: none;
                opacity: 1;
                transform: none;
            }
        }
        .birthday-mobile-footer-sign {
            margin: 0;
            font-size: clamp(0.82rem, 3.6vw, 0.95rem);
            line-height: 1.35;
            color: #fff;
        }
        .birthday-mobile-footer-sign em {
            font-family: 'Dancing Script', cursive;
            font-style: normal;
            font-weight: 700;
            font-size: clamp(1.15rem, 4.8vw, 1.35rem);
        }
        .birthday-mobile-footer-url {
            margin: 6px 0 0;
            font-family: Verdana, Tahoma, sans-serif;
            font-size: clamp(0.72rem, 3vw, 0.82rem);
            line-height: 1.3;
            color: #D7DBDE;
        }
    </style>
    <script>
        try {
            localStorage.removeItem('animstage-v3:t');
        } catch (e) {}
        window.OM_SCENES = @json($scenesJson);
        window.OM_PLAYBACK = @json($playbackJson);
        window.BIRTHDAY_SHOW_TWEAKS = @json($showTweaks);
        window.BIRTHDAY_LOGO_URL = @json(asset('image/logoTDG.png'));
        window.TWEAK_DEFAULTS = { motionEditor: false, nombre: @json($guestName !== '' ? $guestName : 'Sol.') };
        window.BIRTHDAY_COUPON_PAYLOAD = @json($couponPayloadJson ?? []);
    </script>
    <script src="{{ $assetBase }}/vendor/react.production.min.js"></script>
    <script src="{{ $assetBase }}/vendor/react-dom.production.min.js"></script>
    <script src="{{ $assetBase }}/vendor/babel.min.js"></script>
    <script src="{{ $assetBase }}/js/boot.js" defer></script>
</head>
<body>
    <div id="root" aria-busy="true" aria-label="Animación de cumpleaños"></div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Tu Doctor En Viajes' }}</title>
    @php
        $favicon = $faviconUrl ?? asset('image/logo-tdev.png');
    @endphp
    <link rel="icon" href="{{ $favicon }}" type="image/png" sizes="any">
    <link rel="apple-touch-icon" href="{{ $favicon }}">
    <script>
        (function () {
            const storageKey = 'tdev-agent-theme';
            const stored = localStorage.getItem(storageKey);
            const theme = stored ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <style>
        :root,
        html[data-theme='light'] {
            --glass-bg: rgba(255, 255, 255, 0.58);
            --glass-bg-strong: rgba(255, 255, 255, 0.72);
            --glass-border: rgba(255, 255, 255, 0.65);
            --glass-highlight: rgba(255, 255, 255, 0.85);
            --glass-shadow: 0 28px 80px rgba(15, 116, 129, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.7);
            --text-primary: #0b2f34;
            --text-secondary: rgba(11, 47, 52, 0.68);
            --text-muted: rgba(11, 47, 52, 0.45);
            --field-bg: rgba(255, 255, 255, 0.55);
            --field-border: rgba(34, 153, 164, 0.18);
            --field-focus: rgba(34, 153, 164, 0.65);
            --accent: #2299A4;
            --accent-soft: rgba(34, 153, 164, 0.14);
            --accent-strong: #1a7f89;
            --success-bg: rgba(22, 163, 74, 0.12);
            --success-text: #15803d;
            --error-text: #b91c1c;
            --toggle-bg: rgba(255, 255, 255, 0.65);
            --toggle-border: rgba(15, 23, 42, 0.1);
            --orb-a: rgba(34, 153, 164, 0.28);
            --orb-b: rgba(56, 189, 248, 0.18);
            --orb-c: rgba(45, 212, 191, 0.16);
            --page-gradient: linear-gradient(165deg, #e8f7f9 0%, #f4fbfc 42%, #dff3f6 100%);
        }

        html[data-theme='dark'] {
            --glass-bg: rgba(8, 28, 32, 0.55);
            --glass-bg-strong: rgba(10, 36, 40, 0.72);
            --glass-border: rgba(255, 255, 255, 0.12);
            --glass-highlight: rgba(255, 255, 255, 0.08);
            --glass-shadow: 0 28px 80px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.08);
            --text-primary: #f0fbfd;
            --text-secondary: rgba(240, 251, 253, 0.76);
            --text-muted: rgba(240, 251, 253, 0.48);
            --field-bg: rgba(255, 255, 255, 0.05);
            --field-border: rgba(34, 153, 164, 0.28);
            --field-focus: rgba(94, 234, 212, 0.65);
            --accent: #5eead4;
            --accent-soft: rgba(94, 234, 212, 0.14);
            --accent-strong: #2dd4bf;
            --success-bg: rgba(34, 197, 94, 0.16);
            --success-text: #86efac;
            --error-text: #fca5a5;
            --toggle-bg: rgba(8, 28, 32, 0.55);
            --toggle-border: rgba(255, 255, 255, 0.14);
            --orb-a: rgba(34, 153, 164, 0.3);
            --orb-b: rgba(45, 212, 191, 0.18);
            --orb-c: rgba(56, 189, 248, 0.14);
            --page-gradient: linear-gradient(165deg, #031416 0%, #0a2226 48%, #04191c 100%);
        }

        body {
            font-family: "SF Pro Display", "SF Pro Text", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            min-height: 100vh;
            background: var(--page-gradient);
            transition: background 0.4s ease, color 0.35s ease;
            overflow-x: hidden;
        }

        .liquid-orbs {
            pointer-events: none;
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .liquid-orbs span {
            position: absolute;
            border-radius: 9999px;
            filter: blur(48px);
            opacity: 0.9;
            animation: floatOrb 14s ease-in-out infinite;
        }

        .liquid-orbs span:nth-child(1) {
            width: 28rem;
            height: 28rem;
            top: -8rem;
            left: -6rem;
            background: var(--orb-a);
        }

        .liquid-orbs span:nth-child(2) {
            width: 22rem;
            height: 22rem;
            top: 18%;
            right: -5rem;
            background: var(--orb-b);
            animation-delay: -4s;
        }

        .liquid-orbs span:nth-child(3) {
            width: 18rem;
            height: 18rem;
            bottom: 4%;
            left: 28%;
            background: var(--orb-c);
            animation-delay: -8s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(18px, -22px, 0) scale(1.06); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes glassShine {
            0% { transform: translateX(-120%) rotate(18deg); }
            100% { transform: translateX(220%) rotate(18deg); }
        }

        .animate-glass {
            animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        .glass-panel {
            position: relative;
            overflow: hidden;
            border-radius: 2rem;
            border: 1px solid var(--glass-border);
            background: linear-gradient(160deg, var(--glass-bg-strong), var(--glass-bg));
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(40px) saturate(160%);
            -webkit-backdrop-filter: blur(40px) saturate(160%);
        }

        .glass-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(115deg, transparent 30%, var(--glass-highlight) 48%, transparent 62%);
            opacity: 0.35;
            pointer-events: none;
            animation: glassShine 7s ease-in-out infinite;
        }

        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 9999px;
            border: 1px solid var(--toggle-border);
            background: var(--toggle-bg);
            padding: 0.55rem 0.95rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: var(--text-primary);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: var(--glass-shadow);
            transition: transform 0.2s ease;
        }

        .theme-toggle:hover { transform: scale(1.02); }
        .theme-toggle:active { transform: scale(0.98); }
        .theme-toggle svg { width: 1.1rem; height: 1.1rem; }

        .field-input {
            width: 100%;
            border-radius: 1.15rem;
            border: 1px solid var(--field-border);
            background: var(--field-bg);
            padding: 0.9rem 1rem;
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .field-input:focus {
            border-color: var(--field-focus);
            box-shadow: 0 0 0 4px var(--accent-soft);
            transform: translateY(-1px);
        }

        .btn-accent {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 9999px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
            padding: 0.9rem 1.75rem;
            font-size: 0.95rem;
            font-weight: 650;
            color: white;
            box-shadow: 0 14px 34px rgba(34, 153, 164, 0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .btn-accent:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        .btn-accent:active {
            transform: scale(0.97);
        }

        .logo-shell {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1.35rem;
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            padding: 0.75rem 1rem;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: var(--glass-shadow);
        }

        [x-cloak] { display: none !important; }

        @media screen and (max-width: 768px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>

<body class="relative min-h-screen antialiased">
    <div class="liquid-orbs" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="relative z-10">
        {{ $slot }}
    </div>
    @livewireScripts
</body>

</html>

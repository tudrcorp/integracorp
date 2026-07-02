<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asociado · Tu Doctor Group</title>
    <script>
        (function () {
            const storageKey = 'tdg-associate-theme';
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
            --glass-bg: rgba(255, 255, 255, 0.72);
            --glass-border: rgba(255, 255, 255, 0.55);
            --glass-shadow: 0 32px 64px rgba(15, 23, 42, 0.12);
            --text-primary: #0f172a;
            --text-secondary: rgba(15, 23, 42, 0.62);
            --text-muted: rgba(15, 23, 42, 0.45);
            --field-border: rgba(15, 23, 42, 0.12);
            --field-focus: rgba(37, 99, 235, 0.55);
            --accent: #2563eb;
            --success-bg: rgba(22, 163, 74, 0.12);
            --success-text: #15803d;
            --error-bg: rgba(220, 38, 38, 0.1);
            --error-text: #b91c1c;
            --toggle-bg: rgba(255, 255, 255, 0.65);
            --toggle-border: rgba(15, 23, 42, 0.1);
        }

        html[data-theme='dark'] {
            --glass-bg: rgba(15, 23, 42, 0.55);
            --glass-border: rgba(255, 255, 255, 0.12);
            --glass-shadow: 0 32px 64px rgba(0, 0, 0, 0.45);
            --text-primary: #f8fafc;
            --text-secondary: rgba(248, 250, 252, 0.78);
            --text-muted: rgba(248, 250, 252, 0.48);
            --field-border: rgba(255, 255, 255, 0.14);
            --field-focus: rgba(96, 165, 250, 0.65);
            --accent: #60a5fa;
            --success-bg: rgba(34, 197, 94, 0.16);
            --success-text: #86efac;
            --error-bg: rgba(248, 113, 113, 0.14);
            --error-text: #fca5a5;
            --toggle-bg: rgba(15, 23, 42, 0.55);
            --toggle-border: rgba(255, 255, 255, 0.14);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            min-height: 100vh;
            transition: background 0.35s ease, color 0.35s ease;
        }

        html[data-theme='light'] body {
            background:
                radial-gradient(circle at 10% 10%, rgba(59, 130, 246, 0.18), transparent 42%),
                radial-gradient(circle at 90% 0%, rgba(14, 165, 233, 0.14), transparent 38%),
                linear-gradient(180deg, #eef2ff 0%, #f8fafc 45%, #e2e8f0 100%);
        }

        html[data-theme='dark'] body {
            background:
                radial-gradient(circle at 15% 15%, rgba(37, 99, 235, 0.22), transparent 40%),
                radial-gradient(circle at 85% 10%, rgba(14, 165, 233, 0.16), transparent 36%),
                linear-gradient(180deg, #020617 0%, #0f172a 50%, #020617 100%);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.985);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .animate-glass {
            animation: fadeInUp 0.75s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        html[data-theme='light'] .logo-light {
            display: block;
        }

        html[data-theme='light'] .logo-dark {
            display: none;
        }

        html[data-theme='dark'] .logo-light {
            display: none;
        }

        html[data-theme='dark'] .logo-dark {
            display: block;
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
            transition: transform 0.2s ease, background 0.35s ease, border-color 0.35s ease;
        }

        .theme-toggle:hover {
            transform: scale(1.02);
        }

        .theme-toggle:active {
            transform: scale(0.98);
        }

        .theme-toggle svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        [x-cloak] {
            display: none !important;
        }

        @media screen and (max-width: 768px) {

            input,
            select,
            textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>

<body class="min-h-screen antialiased">
    {{ $slot }}
    @livewireScripts
</body>

</html>

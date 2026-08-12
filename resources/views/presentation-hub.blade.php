<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panel de Sistemas — TUDRGROUP · INTEGRACORP</title>
    <link rel="icon" href="{{ asset('image/imagotipo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|ibm-plex-sans:400,500,600" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('systems_panel_theme');
                var theme = (saved === 'dark' || saved === 'light') ? saved : 'light';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <style>
        :root,
        html[data-theme="light"] {
            --bg-0: #F4F5F7;
            --bg-1: #EEF1F6;
            --ink: #14213D;
            --muted: #5B657A;
            --line: rgba(20, 33, 61, 0.12);
            --shell-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(244, 245, 247, 0.96));
            --shell-border: rgba(20, 33, 61, 0.08);
            --shell-shadow: 0 40px 100px rgba(20, 33, 61, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
            --content-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0.18));
            --glass-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.42));
            --glass-border: rgba(255, 255, 255, 0.78);
            --glass-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 10px 30px rgba(20, 33, 61, 0.08);
            --ghost-bg: rgba(255, 255, 255, 0.55);
            --ghost-hover: rgba(255, 255, 255, 0.85);
            --field-bg: rgba(255, 255, 255, 0.72);
            --field-text: #14213D;
            --modal-bg: linear-gradient(160deg, rgba(255, 255, 255, 0.94), rgba(245, 246, 248, 0.98));
            --modal-overlay: rgba(20, 33, 61, 0.28);
            --chip-bg: rgba(20, 33, 61, 0.04);
            --arrow-bg: rgba(20, 33, 61, 0.06);
            --glow: rgba(252, 163, 17, 0.28);
            --accent: #FCA311;
            --teal: #00C2B8;
            --radius: 28px;
            --page-bg:
                radial-gradient(900px 500px at 50% 40%, rgba(252, 163, 17, 0.14), transparent 55%),
                radial-gradient(700px 420px at 80% 80%, rgba(0, 194, 184, 0.08), transparent 50%),
                linear-gradient(160deg, #FFFFFF 0%, #F4F5F7 45%, #E8ECF3 100%);
            --grid-line: rgba(20, 33, 61, 0.045);
            --idle-bg: rgba(251, 113, 133, 0.10);
            --idle-border: rgba(251, 113, 133, 0.35);
            --idle-text: #9f1239;
            --code-chip: #14213D;
        }

        html[data-theme="dark"] {
            --bg-0: #050505;
            --bg-1: #0a0a0c;
            --ink: #F5F5F7;
            --muted: #A1A1A6;
            --line: rgba(255, 255, 255, 0.12);
            --shell-bg: linear-gradient(145deg, rgba(24, 24, 27, 0.92), rgba(10, 10, 12, 0.96));
            --shell-border: rgba(255, 255, 255, 0.08);
            --shell-shadow: 0 40px 100px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.06);
            --content-bg: linear-gradient(180deg, rgba(17, 17, 19, 0.35), rgba(17, 17, 19, 0.05));
            --glass-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02));
            --glass-border: rgba(255, 255, 255, 0.14);
            --glass-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 10px 30px rgba(0, 0, 0, 0.25);
            --ghost-bg: rgba(255, 255, 255, 0.04);
            --ghost-hover: rgba(255, 255, 255, 0.08);
            --field-bg: rgba(0, 0, 0, 0.28);
            --field-text: #fff;
            --modal-bg: linear-gradient(160deg, rgba(30, 30, 32, 0.92), rgba(14, 14, 16, 0.96));
            --modal-overlay: rgba(0, 0, 0, 0.62);
            --chip-bg: rgba(255, 255, 255, 0.04);
            --arrow-bg: rgba(0, 0, 0, 0.35);
            --glow: rgba(252, 163, 17, 0.35);
            --page-bg:
                radial-gradient(900px 500px at 50% 40%, rgba(252, 163, 17, 0.12), transparent 55%),
                radial-gradient(700px 420px at 80% 80%, rgba(0, 194, 184, 0.08), transparent 50%),
                linear-gradient(160deg, #000 0%, #0a0a0c 55%, #050505 100%);
            --grid-line: rgba(255, 255, 255, 0.03);
            --idle-bg: rgba(251, 113, 133, 0.12);
            --idle-border: rgba(251, 113, 133, 0.45);
            --idle-text: #fecdd3;
            --code-chip: #fff;
        }

        * { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;
            color: var(--ink);
            background: var(--page-bg);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            transition: background 0.25s ease, color 0.25s ease;
        }

        [x-cloak] { display: none !important; }

        .page {
            min-height: 100dvh;
            width: 100%;
            display: grid;
            place-items: center;
            padding: clamp(16px, 3vw, 32px);
            position: relative;
        }

        .page::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(var(--grid-line) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse at center, black 20%, transparent 75%);
            pointer-events: none;
        }

        .shell {
            width: min(1180px, 100%);
            min-height: min(720px, calc(100dvh - 32px));
            display: grid;
            grid-template-columns: minmax(280px, 0.95fr) minmax(320px, 1.05fr);
            gap: clamp(18px, 2.5vw, 28px);
            padding: clamp(14px, 1.8vw, 20px);
            border-radius: calc(var(--radius) + 8px);
            background: var(--shell-bg);
            border: 1px solid var(--shell-border);
            box-shadow: var(--shell-shadow);
            position: relative;
            overflow: hidden;
            transition: background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .shell::after {
            content: '';
            position: absolute;
            left: 42%;
            top: 18%;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, var(--glow), transparent 68%);
            filter: blur(8px);
            pointer-events: none;
            z-index: 0;
        }

        .visual,
        .content {
            position: relative;
            z-index: 1;
            border-radius: var(--radius);
            overflow: hidden;
        }

        .visual {
            min-height: 520px;
            background:
                linear-gradient(180deg, rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0.68)),
                url('{{ asset('image/presentaciones-sistemas-bg.png') }}') center / cover no-repeat;
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 22px;
        }

        .brand-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            width: fit-content;
        }

        .brand-chip img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .brand-chip span {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #fff;
        }

        .visual-copy {
            color: #FFFFFF;
            text-shadow: 0 2px 18px rgba(0, 0, 0, 0.45);
        }

        .visual-copy h2 {
            margin: 0 0 8px;
            font-size: clamp(1.4rem, 2.4vw, 1.85rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: #FFFFFF;
        }

        .visual-copy p {
            margin: 0;
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.95rem;
            max-width: 28ch;
            line-height: 1.45;
        }

        .content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(18px, 3vw, 42px) clamp(16px, 2.5vw, 36px);
            padding-top: max(clamp(18px, 3vw, 42px), 58px);
            padding-right: max(clamp(16px, 2.5vw, 36px), 64px);
            background: var(--content-bg);
        }

        .eyebrow {
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .content h1 {
            margin: 0;
            font-size: clamp(1.85rem, 3.4vw, 2.65rem);
            line-height: 1.08;
            letter-spacing: -0.04em;
            font-weight: 800;
            max-width: 14ch;
        }

        .lead {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 0.98rem;
            line-height: 1.5;
            max-width: 36ch;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0 18px;
            flex-wrap: wrap;
        }

        .theme-toggle {
            --glass-orb-size: 46px;
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 5;
            width: var(--glass-orb-size);
            height: var(--glass-orb-size);
            min-width: var(--glass-orb-size);
            padding: 0;
            border: 0;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            cursor: pointer;
            color: var(--ink);
            background:
                radial-gradient(circle at 30% 26%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0.18) 24%, rgba(255, 255, 255, 0.04) 48%, transparent 70%),
                linear-gradient(145deg, rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0.10) 48%, rgba(255, 255, 255, 0.20));
            box-shadow:
                inset 0 1px 1px rgba(255, 255, 255, 0.65),
                inset 0 -8px 14px rgba(20, 33, 61, 0.08),
                inset 0 0 0 1px rgba(255, 255, 255, 0.35),
                0 10px 22px rgba(20, 33, 61, 0.14),
                0 2px 6px rgba(20, 33, 61, 0.08);
            backdrop-filter: blur(16px) saturate(150%);
            -webkit-backdrop-filter: blur(16px) saturate(150%);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            isolation: isolate;
            overflow: hidden;
        }

        .theme-toggle::before {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: inherit;
            background: radial-gradient(circle at 72% 78%, rgba(252, 163, 17, 0.14), transparent 46%);
            pointer-events: none;
            z-index: 0;
        }

        .theme-toggle::after {
            content: '';
            position: absolute;
            left: 22%;
            top: 16%;
            width: 34%;
            height: 18%;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0.05));
            opacity: 0.55;
            pointer-events: none;
            z-index: 1;
        }

        .theme-toggle:hover {
            transform: translateY(-1px) scale(1.04);
            box-shadow:
                inset 0 1px 1px rgba(255, 255, 255, 0.72),
                inset 0 -8px 14px rgba(20, 33, 61, 0.10),
                inset 0 0 0 1px rgba(255, 255, 255, 0.42),
                0 14px 26px rgba(20, 33, 61, 0.16),
                0 4px 10px rgba(20, 33, 61, 0.10);
        }

        .theme-toggle:active {
            transform: translateY(0) scale(0.98);
        }

        .theme-toggle__icon {
            position: relative;
            z-index: 2;
            width: 18px;
            height: 18px;
            display: grid;
            place-items: center;
        }

        html[data-theme="dark"] .theme-toggle {
            color: #F5F5F7;
            background:
                radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.08) 0%, transparent 42%),
                linear-gradient(145deg, rgba(48, 52, 58, 0.92), rgba(28, 30, 34, 0.96) 55%, rgba(36, 38, 44, 0.94));
            box-shadow:
                inset 0 1px 1px rgba(255, 255, 255, 0.08),
                inset 0 -10px 16px rgba(0, 0, 0, 0.45),
                inset 0 0 0 1px rgba(255, 255, 255, 0.08),
                0 12px 26px rgba(0, 0, 0, 0.42),
                0 2px 8px rgba(0, 0, 0, 0.30);
        }

        html[data-theme="dark"] .theme-toggle::before {
            background: radial-gradient(circle at 72% 78%, rgba(252, 163, 17, 0.14), transparent 46%);
        }

        html[data-theme="dark"] .theme-toggle::after {
            display: none;
        }

        html[data-theme="dark"] .theme-toggle:hover {
            box-shadow:
                inset 0 1px 1px rgba(255, 255, 255, 0.12),
                inset 0 -10px 16px rgba(0, 0, 0, 0.48),
                inset 0 0 0 1px rgba(255, 255, 255, 0.12),
                0 14px 28px rgba(0, 0, 0, 0.48),
                0 4px 10px rgba(0, 0, 0, 0.34);
        }

        @media (max-width: 900px) {
            .theme-toggle {
                top: 12px;
                right: 12px;
                --glass-orb-size: 42px;
            }
        }

        .meta-text {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .meta-text strong {
            color: var(--ink);
            font-weight: 600;
        }

        .list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .liquid-glass {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(18px) saturate(140%);
            -webkit-backdrop-filter: blur(18px) saturate(140%);
        }

        .link-card {
            width: 100%;
            text-align: left;
            border-radius: 18px;
            padding: 16px 16px 16px 18px;
            color: inherit;
            cursor: pointer;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 14px;
            align-items: center;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .link-card:hover {
            transform: translateY(-2px);
            border-color: rgba(252, 163, 17, 0.45);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.16),
                0 14px 36px rgba(20, 33, 61, 0.10),
                0 0 0 1px rgba(252, 163, 17, 0.12);
        }

        .link-index {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.9rem;
            background: rgba(252, 163, 17, 0.14);
            color: var(--accent);
            border: 1px solid rgba(252, 163, 17, 0.28);
        }

        .link-card h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .link-card p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .arrow-pill {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: var(--arrow-bg);
            border: 1px solid var(--line);
        }

        .section-card .link-index {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            font-size: 1rem;
        }

        .section-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1px solid var(--line);
            background: var(--chip-bg);
            color: var(--muted);
        }

        .chip--accent {
            color: #9a6200;
            border-color: rgba(252, 163, 17, 0.28);
            background: rgba(252, 163, 17, 0.12);
        }

        html[data-theme="dark"] .chip--accent {
            color: var(--accent);
        }

        .panel-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 14px;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }

        .panel-back:hover {
            color: var(--ink);
        }

        .empty-state {
            border-radius: 18px;
            padding: 18px;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .link-card.is-disabled {
            cursor: default;
            opacity: 0.72;
        }

        .link-card.is-disabled:hover {
            transform: none;
            border-color: var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .content-scroll {
            max-height: min(52vh, 420px);
            overflow: auto;
            padding-right: 4px;
        }

        .footer-note {
            margin-top: 22px;
            color: var(--muted);
            font-size: 0.75rem;
            line-height: 1.4;
            opacity: 0.85;
        }

        .idle-banner {
            margin: 14px 0 0;
            padding: 12px 14px;
            border-color: var(--idle-border);
            color: var(--idle-text);
            background: var(--idle-bg);
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: var(--modal-overlay);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: grid;
            place-items: center;
            padding: 20px;
            z-index: 50;
        }

        .modal {
            width: min(420px, 100%);
            border-radius: 24px;
            padding: 22px;
            background: var(--modal-bg);
            border: 1px solid var(--glass-border);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.25);
        }

        .modal h2 {
            margin: 0;
            font-size: 1.25rem;
            letter-spacing: -0.03em;
        }

        .modal p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .modal code {
            color: var(--code-chip);
        }

        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 18px 0 14px;
        }

        .tab {
            border-radius: 14px;
            border: 1px solid var(--line);
            background: var(--chip-bg);
            color: var(--muted);
            padding: 10px 12px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
        }

        .tab.is-active {
            color: #111;
            background: linear-gradient(135deg, #FCA311, #FFD28A);
            border-color: transparent;
        }

        .field {
            width: 100%;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: var(--field-bg);
            color: var(--field-text);
            padding: 14px 16px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .field:focus {
            border-color: transparent;
            box-shadow:
                0 0 0 1px rgba(0, 194, 184, 0.55),
                0 0 0 1px rgba(163, 230, 53, 0.45),
                0 0 24px rgba(0, 194, 184, 0.2);
        }

        .hint {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.78rem;
        }

        .error {
            margin: 10px 0 0;
            color: #e11d48;
            font-size: 0.85rem;
        }

        html[data-theme="dark"] .error {
            color: #fb7185;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-top: 16px;
        }

        .primary {
            border: 0;
            border-radius: 16px;
            padding: 14px 16px;
            font-weight: 700;
            color: #111;
            background: linear-gradient(135deg, #E5E5E5, #FFFFFF 45%, #D4D4D8);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .primary:disabled {
            opacity: 0.55;
            cursor: wait;
        }

        .primary span.icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #111;
            color: #fff;
            display: grid;
            place-items: center;
        }

        .secondary {
            border-radius: 16px;
            border: 1px solid var(--line);
            background: transparent;
            color: var(--muted);
            padding: 0 16px;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            body { overflow: auto; }
            .shell {
                grid-template-columns: 1fr;
                min-height: auto;
            }
            .visual {
                min-height: 260px;
            }
            .content h1 {
                max-width: none;
            }
        }
    </style>
</head>
<body>
@php
    $sectionsPayload = collect($sections ?? [])->values()->all();
@endphp
<div
    class="page"
    x-data="presentationHub({
        authenticated: @js($authenticated),
        intended: @js($intended),
        sections: @js($sectionsPayload),
        authUrl: @js(url('/dpto-tecnologia-sistemas/auth')),
        csrf: @js(csrf_token()),
    })"
>
    <div class="shell">
        <button
            type="button"
            class="theme-toggle"
            x-on:click="toggleTheme()"
            :title="theme === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
            :aria-label="theme === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
            data-theme-toggle
        >
            <span class="theme-toggle__icon" aria-hidden="true">
                <template x-if="theme === 'dark'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                </template>
                <template x-if="theme !== 'dark'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/>
                    </svg>
                </template>
            </span>
        </button>

        <aside class="visual" aria-label="Departamento de Sistemas">
            <div class="brand-chip">
                <img src="{{ asset('image/imagotipo.png') }}" alt="INTEGRACORP">
                <span>Sistemas · TUDRGROUP</span>
            </div>
            <div class="visual-copy">
                <h2>Departamento de Sistemas</h2>
                <p>INTEGRACORP × TUDRGROUP — panel interno de presentaciones y manuales técnicos.</p>
            </div>
        </aside>

        <section class="content">
            <div class="eyebrow">Panel de Sistemas</div>
            <h1 x-text="activeSection ? activeSection.title : 'Accesos del equipo de tecnología.'"></h1>
            <p class="lead" x-text="activeSection ? activeSection.subtitle : 'Elige una categoría para ver presentaciones o manuales. Validamos tu identidad con cédula o teléfono de colaboradores.'"></p>

            @if (! empty($idleExpired))
                <div class="liquid-glass idle-banner">
                    Tu sesión se cerró por inactividad (10 minutos). Vuelve a identificarte para continuar.
                </div>
            @endif

            <div class="meta-row">
                <button
                    type="button"
                    class="ghost-btn"
                    x-show="activeSection"
                    x-cloak
                    x-on:click="backToSections()"
                    aria-label="Volver a categorías"
                    title="Volver"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <a href="/" class="ghost-btn" x-show="!activeSection" aria-label="Inicio" title="Inicio">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="meta-text">
                    @if ($authenticated)
                        Sesión activa
                        @if (! empty($access['full_name']))
                            : <strong>{{ $access['full_name'] }}</strong>
                        @endif
                        · se cierra a los 10 min de inactividad
                        · <a href="{{ url('/dpto-tecnologia-sistemas/logout') }}" style="color: var(--accent); text-decoration: none;">Cerrar</a>
                    @else
                        Solo colaboradores registrados · cierre por inactividad: 10 min
                    @endif
                </div>
            </div>

            <div class="content-scroll">
                <div class="list" x-show="!activeSection">
                    @foreach ($sections as $index => $section)
                        <button
                            type="button"
                            class="link-card section-card liquid-glass"
                            x-on:click="openSection(sections[{{ $index }}])"
                        >
                            <div class="link-index">{{ $index + 1 }}</div>
                            <div>
                                <h3>{{ $section['title'] }}</h3>
                                <p>{{ $section['subtitle'] }}</p>
                                <div class="section-meta">
                                    <span class="chip chip--accent">{{ $section['eyebrow'] }}</span>
                                    <span class="chip">{{ count($section['items']) }} recurso{{ count($section['items']) === 1 ? '' : 's' }}</span>
                                </div>
                            </div>
                            <div class="arrow-pill" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                            </div>
                        </button>
                    @endforeach
                </div>

                @foreach ($sections as $sectionIndex => $section)
                    <div class="list" x-show="activeSectionId === @js($section['id'])" x-cloak>
                        <button type="button" class="panel-back" x-on:click="backToSections()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                            Todas las categorías
                        </button>

                        @forelse ($section['items'] as $itemIndex => $item)
                            <button
                                type="button"
                                class="link-card liquid-glass {{ ($item['status'] ?? '') !== 'ready' || empty($item['url']) ? 'is-disabled' : '' }}"
                                x-on:click="openResource(sections[{{ $sectionIndex }}].items[{{ $itemIndex }}])"
                            >
                                <div class="link-index">{{ $itemIndex + 1 }}</div>
                                <div>
                                    <h3>{{ $item['title'] }}</h3>
                                    <p>{{ $item['subtitle'] }}</p>
                                    @if (($item['status'] ?? '') !== 'ready' || empty($item['url']))
                                        <div class="section-meta">
                                            <span class="chip">Próximamente</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="arrow-pill" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                                </div>
                            </button>
                        @empty
                            <div class="empty-state liquid-glass">
                                Aún no hay recursos publicados en esta categoría. Cuando se creen, aparecerán aquí automáticamente.
                            </div>
                        @endforelse
                    </div>
                @endforeach
            </div>

            <p class="footer-note">TUDRGROUP · INTEGRACORP · Uso exclusivo del departamento de sistemas.</p>
        </section>
    </div>

    <div class="modal-backdrop" x-show="loginOpen" x-cloak x-on:keydown.escape.window="loginOpen = false">
        <div class="modal liquid-glass" x-on:click.outside="loginOpen = false" role="dialog" aria-modal="true" aria-labelledby="login-title">
            <h2 id="login-title">Verifica tu identidad</h2>
            <p>Ingresa con tu cédula o con un teléfono registrado (<code>telefono</code> / <code>telefonoCorporativo</code>).</p>

            <div class="tabs">
                <button type="button" class="tab" :class="{ 'is-active': method === 'cedula' }" x-on:click="setMethod('cedula')">Cédula</button>
                <button type="button" class="tab" :class="{ 'is-active': method === 'telefono' }" x-on:click="setMethod('telefono')">Teléfono</button>
            </div>

            <form x-on:submit.prevent="submitLogin">
                <template x-if="method === 'cedula'">
                    <div>
                        <input
                            class="field"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="Ej. 16007868"
                            x-model="cedula"
                            x-on:input="cedula = $event.target.value.replace(/[^\d]/g, '').slice(0, 10)"
                            maxlength="10"
                        >
                        <p class="hint">En el sistema figura como V-16007868; solo comparamos los números.</p>
                    </div>
                </template>

                <template x-if="method === 'telefono'">
                    <div>
                        <input
                            class="field"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel"
                            placeholder="+58 412 193 1865"
                            :value="phoneDisplay"
                            x-on:input="onPhoneInput($event)"
                            x-on:keydown="onPhoneKeydown($event)"
                        >
                        <p class="hint">Formato final: <span x-text="phoneValue || '+58XXXXXXXXXX'"></span></p>
                    </div>
                </template>

                <p class="error" x-show="error" x-text="error"></p>

                <div class="actions">
                    <button class="primary" type="submit" :disabled="loading">
                        <span x-text="loading ? 'Validando…' : 'Ingresar a la presentación'"></span>
                        <span class="icon">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                        </span>
                    </button>
                    <button type="button" class="secondary" x-on:click="loginOpen = false">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function presentationHub(config) {
    return {
        authenticated: config.authenticated,
        intended: config.intended || '',
        sections: config.sections || [],
        authUrl: config.authUrl,
        csrf: config.csrf,
        theme: 'light',
        activeSectionId: null,
        loginOpen: false,
        method: 'cedula',
        cedula: '',
        phoneDigits: '',
        pendingPath: '',
        loading: false,
        error: '',

        init() {
            this.theme = this.readTheme();
            this.applyTheme(this.theme);

            if (!this.intended || this.authenticated) {
                return;
            }

            const match = this.findResourceByUrl(this.intended);
            if (!match) {
                return;
            }

            this.activeSectionId = match.section.id;
            this.pendingPath = match.item.url;
            this.loginOpen = true;
        },

        readTheme() {
            try {
                const saved = localStorage.getItem('systems_panel_theme');
                return (saved === 'dark' || saved === 'light') ? saved : 'light';
            } catch (e) {
                return 'light';
            }
        },

        applyTheme(theme) {
            const next = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            try {
                localStorage.setItem('systems_panel_theme', next);
            } catch (e) {
                // ignore storage errors
            }
        },

        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            this.applyTheme(this.theme);
        },

        get activeSection() {
            return this.sections.find((section) => section.id === this.activeSectionId) || null;
        },

        get phoneValue() {
            if (!this.phoneDigits) return '';
            return '+58' + this.phoneDigits;
        },

        get phoneDisplay() {
            const d = this.phoneDigits;
            if (!d) return '+58 ';
            const a = d.slice(0, 3);
            const b = d.slice(3, 6);
            const c = d.slice(6, 10);
            return ('+58 ' + [a, b, c].filter(Boolean).join(' ')).trim();
        },

        findResourceByUrl(url) {
            const normalized = '/' + String(url || '').replace(/^\/+/, '');
            for (const section of this.sections) {
                for (const item of (section.items || [])) {
                    if (!item.url) continue;
                    const itemPath = '/' + String(item.url).replace(/^\/+/, '');
                    if (itemPath === normalized) {
                        return { section, item };
                    }
                }
            }
            return null;
        },

        openSection(section) {
            this.activeSectionId = section.id;
            this.error = '';
        },

        backToSections() {
            this.activeSectionId = null;
            this.error = '';
        },

        setMethod(method) {
            this.method = method;
            this.error = '';
        },

        openResource(item) {
            this.error = '';

            if (!item || item.status !== 'ready' || !item.url) {
                return;
            }

            this.pendingPath = item.url;

            if (this.authenticated || item.requires_auth === false) {
                window.location.href = item.url;
                return;
            }

            this.loginOpen = true;
        },

        onPhoneKeydown(event) {
            if (['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }
            if (!/^\d$/.test(event.key)) {
                event.preventDefault();
            }
        },

        onPhoneInput(event) {
            let raw = String(event.target.value || '').replace(/\D+/g, '');
            if (raw.startsWith('58')) {
                raw = raw.slice(2);
            }
            if (raw.startsWith('0')) {
                raw = raw.slice(1);
            }
            this.phoneDigits = raw.slice(0, 10);
            event.target.value = this.phoneDisplay;
        },

        credential() {
            if (this.method === 'cedula') {
                return this.cedula;
            }
            return this.phoneValue;
        },

        async submitLogin() {
            this.error = '';
            const credential = this.credential();

            if (this.method === 'cedula' && this.cedula.length < 6) {
                this.error = 'Ingresa al menos 6 dígitos de la cédula.';
                return;
            }

            if (this.method === 'telefono' && this.phoneDigits.length !== 10) {
                this.error = 'Completa el teléfono en formato +58 y 10 dígitos.';
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(this.authUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        method: this.method,
                        credential,
                        intended: this.pendingPath || this.intended || null,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.ok) {
                    this.error = data.message || (data.errors?.credential?.[0]) || 'No se pudo validar el acceso.';
                    return;
                }

                window.location.href = data.redirect || this.pendingPath || '/dpto-tecnologia-sistemas';
            } catch (e) {
                this.error = 'Error de conexión. Intenta de nuevo.';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@include('partials.presentation-idle-watchdog', [
    'isAuthenticated' => (bool) $authenticated,
    'idleTimeoutSeconds' => $idleTimeoutSeconds ?? \App\Support\PresentationHubGate::idleTimeoutSeconds(),
])
</body>
</html>

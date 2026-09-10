<style id="presentation-theme-overrides">
    html[data-theme="dark"] {
        color-scheme: dark;
        --bg-0: #0a0a0c;
        --bg-1: #121216;
        --ink: #F5F5F7;
        --ink-soft: rgba(245, 245, 247, 0.68);
        --navy: #F5F5F7;
        --line: rgba(255, 255, 255, 0.12);
        --glass-shadow: rgba(0, 0, 0, 0.42);
    }

    html[data-theme="dark"] body {
        color: var(--ink);
        background:
            radial-gradient(1100px 620px at 8% -8%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%),
            radial-gradient(900px 520px at 96% 4%, rgba(252, 163, 17, 0.08), transparent 52%),
            radial-gradient(700px 420px at 50% 110%, rgba(88, 86, 214, 0.08), transparent 55%),
            linear-gradient(165deg, #050505 0%, var(--bg-0) 42%, var(--bg-1) 100%);
    }

    html[data-theme="dark"] .bg-mesh {
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
    }

    html[data-theme="dark"] .liquid-glass {
        border-color: rgba(255, 255, 255, 0.10);
        background:
            linear-gradient(
                155deg,
                rgba(255, 255, 255, 0.08) 0%,
                rgba(255, 255, 255, 0.04) 48%,
                rgba(255, 255, 255, 0.02) 100%
            );
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.08),
            inset 0 -1px 0 rgba(0, 0, 0, 0.25),
            0 0 0 1px rgba(255, 255, 255, 0.04),
            0 14px 42px var(--glass-shadow),
            0 2px 10px rgba(0, 0, 0, 0.35);
    }

    html[data-theme="dark"] .liquid-glass::before {
        background: linear-gradient(118deg, rgba(255, 255, 255, 0.06) 0%, transparent 48%);
        opacity: 0.55;
    }

    html[data-theme="dark"] .liquid-glass--accent {
        border-color: color-mix(in srgb, var(--accent) 38%, transparent);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.08),
            0 0 0 1px color-mix(in srgb, var(--accent) 22%, transparent),
            0 14px 36px color-mix(in srgb, var(--accent) 16%, transparent);
    }

    html[data-theme="dark"] .liquid-glass--interactive:hover,
    html[data-theme="dark"] .liquid-glass--interactive:focus-visible {
        border-color: color-mix(in srgb, var(--accent) 48%, transparent);
    }

    html[data-theme="dark"] .liquid-glass--interactive.is-active {
        border-color: var(--accent);
        background:
            linear-gradient(
                155deg,
                rgba(255, 255, 255, 0.10) 0%,
                color-mix(in srgb, var(--accent) 14%, rgba(255, 255, 255, 0.04)) 100%
            );
    }

    html[data-theme="dark"] .header-glass,
    html[data-theme="dark"] .footer-glass {
        background: rgba(12, 12, 14, 0.82);
        border-color: rgba(255, 255, 255, 0.08);
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.04), 0 8px 24px rgba(0, 0, 0, 0.45);
    }

    html[data-theme="dark"] .btn-glass {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.06);
        color: var(--ink);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 4px 14px rgba(0, 0, 0, 0.35);
    }

    html[data-theme="dark"] .btn-glass:hover:not(:disabled) {
        background: rgba(255, 255, 255, 0.10);
    }

    html[data-theme="dark"] .dot {
        background: rgba(255, 255, 255, 0.22);
    }

    html[data-theme="dark"] .dot.active {
        background: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 32%, transparent);
    }

    html[data-theme="dark"] .logo-chip {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.06);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 4px 12px rgba(0, 0, 0, 0.35);
    }

    html[data-theme="dark"] .logo-chip span {
        color: var(--ink);
    }

    html[data-theme="dark"] .overview-panel {
        background: rgba(0, 0, 0, 0.62);
    }

    html[data-theme="dark"] .kb-hint,
    html[data-theme="dark"] .presentation-swipe-hint {
        color: rgba(245, 245, 247, 0.42);
    }

    html[data-theme="dark"] .presentation-app-header__brand-text,
    html[data-theme="dark"] .presentation-app-header__counter {
        color: color-mix(in srgb, var(--ink) 72%, transparent);
    }

    html[data-theme="dark"] .presentation-session {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.05);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 4px 12px rgba(0, 0, 0, 0.35);
    }

    html[data-theme="dark"] .presentation-session__label,
    html[data-theme="dark"] .presentation-session__time {
        color: color-mix(in srgb, var(--ink) 52%, transparent);
    }

    html[data-theme="dark"] .presentation-session__name {
        color: var(--ink);
    }

    html[data-theme="dark"] .presentation-session__logout {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.06);
        color: var(--ink);
    }

    html[data-theme="dark"] .hierarchy-legend-item,
    html[data-theme="dark"] .url-path-chip,
    html[data-theme="dark"] .readable-card {
        border-color: rgba(255, 255, 255, 0.10);
        background: rgba(255, 255, 255, 0.04);
    }

    .presentation-theme-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        width: 2.5rem;
        height: 2.5rem;
        padding: 0;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.72);
        background: rgba(255, 255, 255, 0.58);
        color: var(--navy);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85), 0 4px 14px rgba(20, 33, 61, 0.08);
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }

    .presentation-theme-toggle--header {
        position: static;
        width: auto;
        min-width: 2.35rem;
        height: 2.35rem;
        padding: 0 0.7rem;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .presentation-theme-toggle--float {
        position: fixed;
        top: calc(3.35rem + 0.45rem);
        right: 0.75rem;
        z-index: 60;
        backdrop-filter: blur(16px) saturate(160%);
        -webkit-backdrop-filter: blur(16px) saturate(160%);
    }

    .presentation-theme-toggle:hover {
        transform: translateY(-1px);
        background: rgba(255, 255, 255, 0.84);
    }

    .presentation-theme-toggle--header:hover {
        transform: none;
        background: transparent;
    }

    .presentation-theme-toggle svg {
        width: 1.15rem;
        height: 1.15rem;
        flex-shrink: 0;
    }

    .presentation-theme-toggle [data-theme-icon-sun],
    .presentation-theme-toggle [data-theme-icon-moon] {
        display: none;
    }

    html[data-theme="light"] .presentation-theme-toggle [data-theme-icon-moon] {
        display: block;
    }

    html[data-theme="dark"] .presentation-theme-toggle [data-theme-icon-sun] {
        display: block;
    }

    html[data-theme="dark"] .presentation-theme-toggle {
        border-color: rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.07);
        color: var(--ink, #F5F5F7);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 4px 14px rgba(0, 0, 0, 0.4);
    }

    html[data-theme="dark"] .presentation-theme-toggle--header {
        background: transparent;
        border: 0;
        box-shadow: none;
        color: inherit;
    }

    html[data-theme="dark"] .presentation-theme-toggle:hover {
        background: rgba(255, 255, 255, 0.12);
    }

    html[data-theme="dark"] .presentation-theme-toggle--header:hover {
        background: transparent;
    }

    html[data-theme="dark"] .cover-track {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.10);
    }

    html[data-theme="dark"] .hub-status {
        background: color-mix(in srgb, #34C759 18%, transparent);
        color: #7DDA95;
        border-color: color-mix(in srgb, #34C759 28%, transparent);
    }

    .presentation-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.28rem 0.72rem;
        font-size: 10px;
        font-weight: 650;
        letter-spacing: 0.04em;
        line-height: 1.2;
        border: 1px solid rgba(20, 33, 61, 0.08);
        background: rgba(255, 255, 255, 0.72);
        color: rgba(29, 29, 31, 0.68);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
    }

    .presentation-badge--module {
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--accent);
        border-color: color-mix(in srgb, var(--accent) 26%, white);
        background: color-mix(in srgb, var(--accent) 12%, rgba(255, 255, 255, 0.78));
    }

    .presentation-badge--chip {
        font-size: 11px;
        font-weight: 650;
        letter-spacing: 0.01em;
        color: var(--navy);
        background: rgba(255, 255, 255, 0.78);
        border-color: rgba(255, 255, 255, 0.9);
    }

    html[data-theme="dark"] .presentation-badge,
    html[data-theme="dark"] .bg-white\/55.rounded-full,
    html[data-theme="dark"] .bg-white\/70.rounded-full,
    html[data-theme="dark"] .bg-white\/75.rounded-full {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.14);
        color: rgba(245, 245, 247, 0.86);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
    }

    html[data-theme="dark"] .presentation-badge--module,
    html[data-theme="dark"] .slide span.rounded-full.liquid-glass {
        background: color-mix(in srgb, var(--accent) 18%, rgba(255, 255, 255, 0.04));
        border-color: color-mix(in srgb, var(--accent) 42%, transparent);
        color: color-mix(in srgb, var(--accent) 55%, #fff);
        box-shadow:
            inset 0 1px 0 color-mix(in srgb, var(--accent) 22%, transparent),
            0 0 0 1px color-mix(in srgb, var(--accent) 12%, transparent);
    }

    html[data-theme="dark"] .presentation-badge--chip {
        background: color-mix(in srgb, var(--accent) 12%, rgba(255, 255, 255, 0.05));
        border-color: color-mix(in srgb, var(--accent) 28%, rgba(255, 255, 255, 0.08));
        color: rgba(245, 245, 247, 0.92);
    }

    html[data-theme="dark"] .text-\[var\(--navy\)\]\/55 {
        color: rgba(245, 245, 247, 0.82);
    }

    html[data-theme="dark"] .pwa-device__caption {
        color: rgba(245, 245, 247, 0.62);
    }

    @media (min-width: 768px) {
        .presentation-theme-toggle--float {
            top: calc(57px + 0.5rem);
            right: 1rem;
        }
    }
</style>

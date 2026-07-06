<style>
    /* ── Variables por módulo (alineadas al stepper del menú) ── */
    .user-perm-module--negocios { --perm-accent: #075985; --perm-accent-soft: #e0f2fe; --perm-accent-ring: rgba(125, 211, 252, 0.45); --perm-accent-glow: rgba(7, 89, 133, 0.22); }
    .user-perm-module--administracion { --perm-accent: #2563eb; --perm-accent-soft: #dbeafe; --perm-accent-ring: rgba(147, 197, 253, 0.35); --perm-accent-glow: rgba(37, 99, 235, 0.22); }
    .user-perm-module--operaciones { --perm-accent: #16a34a; --perm-accent-soft: #dcfce7; --perm-accent-ring: rgba(34, 197, 94, 0.35); --perm-accent-glow: rgba(22, 163, 74, 0.22); }
    .user-perm-module--marketing { --perm-accent: #d97706; --perm-accent-soft: #fef3c7; --perm-accent-ring: rgba(251, 191, 36, 0.45); --perm-accent-glow: rgba(217, 119, 6, 0.22); }
    .user-perm-module--proyectos { --perm-accent: #3b82f6; --perm-accent-soft: #dbeafe; --perm-accent-ring: rgba(147, 197, 253, 0.35); --perm-accent-glow: rgba(59, 130, 246, 0.22); }

    .dark .user-perm-module--negocios { --perm-accent: #38bdf8; --perm-accent-soft: rgba(12, 74, 110, 0.35); --perm-accent-ring: rgba(56, 189, 248, 0.28); --perm-accent-glow: rgba(56, 189, 248, 0.18); }
    .dark .user-perm-module--administracion { --perm-accent: #60a5fa; --perm-accent-soft: rgba(37, 99, 235, 0.22); --perm-accent-ring: rgba(96, 165, 250, 0.28); --perm-accent-glow: rgba(37, 99, 235, 0.18); }
    .dark .user-perm-module--operaciones { --perm-accent: #4ade80; --perm-accent-soft: rgba(22, 163, 74, 0.22); --perm-accent-ring: rgba(74, 222, 128, 0.28); --perm-accent-glow: rgba(34, 197, 94, 0.18); }
    .dark .user-perm-module--marketing { --perm-accent: #fbbf24; --perm-accent-soft: rgba(217, 119, 6, 0.22); --perm-accent-ring: rgba(251, 191, 36, 0.28); --perm-accent-glow: rgba(217, 119, 6, 0.18); }
    .dark .user-perm-module--proyectos { --perm-accent: #60a5fa; --perm-accent-soft: rgba(59, 130, 246, 0.22); --perm-accent-ring: rgba(96, 165, 250, 0.28); --perm-accent-glow: rgba(59, 130, 246, 0.18); }

    /* ── Intro callout ── */
    .user-perm-intro {
        border-color: rgb(186 230 253 / 0.85);
        background: linear-gradient(135deg, rgb(240 249 255 / 0.95), rgb(255 255 255 / 0.98) 55%, rgb(238 242 255 / 0.85));
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.8);
    }

    .dark .user-perm-intro {
        border-color: rgb(56 189 248 / 0.18);
        background: linear-gradient(145deg, rgb(8 47 73 / 0.55), rgb(15 23 42 / 0.92) 50%, rgb(30 27 75 / 0.35));
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.04), 0 0 0 1px rgb(255 255 255 / 0.03);
    }

    .user-perm-intro-icon {
        background: rgb(14 165 233 / 0.1);
        color: rgb(2 132 199);
        box-shadow: inset 0 0 0 1px rgb(14 165 233 / 0.2);
    }

    .dark .user-perm-intro-icon {
        background: rgb(56 189 248 / 0.12);
        color: rgb(125 211 252);
        box-shadow: 0 0 0 1px rgb(56 189 248 / 0.15);
    }

    .user-perm-intro-title {
        color: rgb(15 23 42);
    }

    .dark .user-perm-intro-title {
        color: rgb(248 250 252);
    }

    .user-perm-intro-body {
        color: rgb(71 85 105);
    }

    .dark .user-perm-intro-body {
        color: rgb(203 213 225);
    }

    .dark .user-perm-intro-body strong {
        color: rgb(226 232 240);
    }

    /* ── Tab inner container ── */
    .dark .user-perm-tab-inner {
        background: rgb(255 255 255 / 0.02) !important;
        border-color: rgb(255 255 255 / 0.06) !important;
    }

    /* ── Module accordion shell ── */
    .user-perm-module-shell {
        border-color: rgb(226 232 240 / 0.95);
        background: linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.92));
        border-radius: 0.625rem;
    }

    .user-perm-module-shell > .fi-section,
    .user-perm-module-shell .fi-section {
        border-radius: inherit;
    }

    .user-perm-module-shell .fi-section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-height: 3.25rem;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .user-perm-module-shell .fi-section-header-text-ctn {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.1rem;
        min-height: 1.75rem;
    }

    .user-perm-module-shell .fi-section-header-heading {
        display: flex;
        align-items: center;
        margin: 0;
        line-height: 1.2;
    }

    .user-perm-module-shell .fi-section-header-description {
        margin: 0;
        font-size: 0.6875rem;
        font-weight: 500;
        line-height: 1.25;
        color: rgb(100 116 139);
    }

    .user-perm-module-shell .fi-section-header .fi-icon-btn,
    .user-perm-module-shell .fi-section-header .fi-section-collapse-btn {
        align-self: center;
        margin-top: 0;
    }

    .user-perm-module-shell .fi-section-header > .fi-icon,
    .user-perm-module-shell .fi-section-header > svg,
    .user-perm-module-shell .fi-section-header .fi-section-header-icon {
        align-self: center;
        flex-shrink: 0;
        color: var(--perm-accent, rgb(100 116 139));
    }

    .dark .user-perm-module-shell {
        border-color: rgb(255 255 255 / 0.07);
        background: linear-gradient(180deg, rgb(15 23 42 / 0.72), rgb(2 6 23 / 0.55));
        box-shadow: 0 12px 40px -18px rgb(0 0 0 / 0.55);
    }

    .dark .user-perm-module-shell .fi-section-header-heading,
    .dark .user-perm-module-shell .fi-section-header .fi-section-header-heading {
        color: rgb(241 245 249);
    }

    .dark .user-perm-module-shell .fi-section-header-description {
        color: rgb(148 163 184);
    }

    .dark .user-perm-module-shell .fi-section-header-btn {
        color: rgb(148 163 184);
    }

    .dark .user-perm-module-shell .fi-section-header-btn:hover {
        color: rgb(226 232 240);
        background: rgb(255 255 255 / 0.06);
    }

    /* ── Borde izquierdo por módulo (colores del menú stepper) ── */
    .user-perm-module--negocios { border-left: 4px solid #075985; }
    .user-perm-module--administracion { border-left: 4px solid #2563eb; }
    .user-perm-module--operaciones { border-left: 4px solid #16a34a; }
    .user-perm-module--marketing { border-left: 4px solid #d97706; }
    .user-perm-module--proyectos { border-left: 4px solid #3b82f6; }

    .dark .user-perm-module--negocios { border-left-color: #38bdf8; }
    .dark .user-perm-module--administracion { border-left-color: #60a5fa; }
    .dark .user-perm-module--operaciones { border-left-color: #4ade80; }
    .dark .user-perm-module--marketing { border-left-color: #fbbf24; }
    .dark .user-perm-module--proyectos { border-left-color: #60a5fa; }

    .user-perm-group-card {
        border-radius: 0.5rem;
    }

    .user-perm-group-card .fi-section-header {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-height: 3rem;
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }

    .user-perm-group-card .fi-section-header-heading {
        display: flex;
        align-items: center;
        width: 100%;
        margin: 0;
        line-height: 1.2;
    }

    .user-perm-group-card .fi-section-header .fi-icon-btn,
    .user-perm-group-card .fi-section-header .fi-section-collapse-btn {
        align-self: center;
    }

    .user-perm-module-badge {
        background: linear-gradient(135deg, var(--perm-accent), color-mix(in srgb, var(--perm-accent) 72%, black));
        color: #fff;
        box-shadow: 0 6px 18px -10px var(--perm-accent-glow);
    }

    .dark .user-perm-module-badge {
        color: #fff;
        font-weight: 800;
    }

    .user-perm-stat-pill {
        background: var(--perm-accent-soft);
        color: var(--perm-accent);
        box-shadow: inset 0 0 0 1px var(--perm-accent-ring);
    }

    .user-perm-group-icon {
        background: var(--perm-accent-soft);
        color: var(--perm-accent);
        box-shadow: inset 0 0 0 1px var(--perm-accent-ring);
    }

    .user-perm-groups-stack {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    /* ── Group cards ── */
    .user-perm-group-card {
        border-color: rgb(226 232 240 / 0.9);
        background: linear-gradient(180deg, rgb(255 255 255 / 0.96), rgb(248 250 252 / 0.88));
    }

    .dark .user-perm-group-card {
        border-color: rgb(255 255 255 / 0.06);
        background: linear-gradient(180deg, rgb(30 41 59 / 0.45), rgb(15 23 42 / 0.35));
    }

    .dark .user-perm-group-card .fi-section-header {
        border-bottom: 1px solid rgb(255 255 255 / 0.05);
    }

    .dark .user-perm-group-card .fi-section-header-btn {
        color: rgb(148 163 184);
    }

    .user-perm-group-card > .fi-section-content-ctn {
        padding-top: 0.25rem;
    }

    .user-perm-group-card .fi-section-header-heading {
        width: 100%;
    }

    .user-perm-group-count {
        background: rgb(241 245 249);
        color: rgb(71 85 105);
    }

    .dark .user-perm-group-count {
        background: rgb(255 255 255 / 0.07);
        color: rgb(203 213 225);
    }

    /* ── Checkbox shell ── */
    .user-perm-checkbox-shell {
        margin-top: 0.35rem;
        padding: 0.85rem;
        border-radius: 1rem;
        border: 1px solid rgb(226 232 240 / 0.85);
        background: rgb(255 255 255 / 0.72);
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.65);
    }

    .dark .user-perm-checkbox-shell {
        border-color: rgb(255 255 255 / 0.06);
        background: rgb(255 255 255 / 0.025);
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.03);
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-actions {
        margin-bottom: 0.75rem;
        padding-bottom: 0.65rem;
        border-bottom: 1px dashed rgb(203 213 225 / 0.85);
    }

    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-actions {
        border-bottom-color: rgb(255 255 255 / 0.08);
    }

    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-actions span,
    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-actions a {
        color: rgb(125 211 252) !important;
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-actions span {
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-options {
        gap: 0.65rem !important;
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-option-ctn {
        height: 100%;
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-option {
        height: 100%;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.85rem 0.95rem;
        border-radius: 0.95rem;
        border: 1px solid rgb(226 232 240 / 0.95);
        background: linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.92));
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
    }

    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-option {
        border-color: rgb(255 255 255 / 0.07);
        background: linear-gradient(180deg, rgb(30 41 59 / 0.55), rgb(15 23 42 / 0.42));
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-option:hover {
        transform: translateY(-1px);
        border-color: rgb(148 163 184 / 0.55);
        box-shadow: 0 10px 24px -18px rgb(15 23 42 / 0.45);
    }

    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-option:hover {
        border-color: rgb(255 255 255 / 0.14);
        background: linear-gradient(180deg, rgb(51 65 85 / 0.55), rgb(30 41 59 / 0.45));
        box-shadow: 0 10px 24px -18px rgb(0 0 0 / 0.65);
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) {
        border-color: rgb(14 165 233 / 0.45);
        background: linear-gradient(180deg, rgb(240 249 255 / 0.98), rgb(224 242 254 / 0.88));
        box-shadow: 0 12px 28px -20px rgb(14 165 233 / 0.55);
    }

    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) {
        border-color: rgb(56 189 248 / 0.42);
        background: linear-gradient(180deg, rgb(8 47 73 / 0.65), rgb(12 74 110 / 0.45));
        box-shadow: 0 0 0 1px rgb(56 189 248 / 0.12), 0 12px 28px -20px rgb(56 189 248 / 0.35);
    }

    .user-perm-checkbox-list .fi-checkbox-input {
        margin-top: 0.15rem;
        width: 1.05rem;
        height: 1.05rem;
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-option-label {
        font-size: 0.84rem;
        font-weight: 600;
        line-height: 1.35;
        color: rgb(15 23 42);
    }

    .dark .user-perm-checkbox-list .fi-fo-checkbox-list-option-label {
        color: rgb(241 245 249);
    }

    .user-perm-checkbox-list .fi-fo-checkbox-list-search-input-wrp {
        margin-bottom: 0.85rem;
    }

    .dark .user-perm-checkbox-list .fi-input {
        background: rgb(15 23 42 / 0.65);
        border-color: rgb(255 255 255 / 0.08);
        color: rgb(241 245 249);
    }

    /* ── Empty state ── */
    .dark .user-perm-empty {
        border-color: rgb(255 255 255 / 0.1);
        background: rgb(255 255 255 / 0.02);
    }
</style>

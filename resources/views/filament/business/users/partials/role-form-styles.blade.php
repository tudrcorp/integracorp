<style>
    /* ── Variables por grupo ── */
    .user-role-group--comerciales { --role-accent: #075985; --role-accent-soft: #e0f2fe; --role-accent-ring: rgba(125, 211, 252, 0.45); }
    .user-role-group--administrativos { --role-accent: #2563eb; --role-accent-soft: #dbeafe; --role-accent-ring: rgba(147, 197, 253, 0.35); }

    .dark .user-role-group--comerciales { --role-accent: #38bdf8; --role-accent-soft: rgba(12, 74, 110, 0.28); --role-accent-ring: rgba(56, 189, 248, 0.28); }
    .dark .user-role-group--administrativos { --role-accent: #60a5fa; --role-accent-soft: rgba(37, 99, 235, 0.22); --role-accent-ring: rgba(96, 165, 250, 0.28); }

    /* ── Grupo ── */
    .user-role-group-shell {
        border-color: rgb(226 232 240 / 0.95);
        background: linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.92));
        border-radius: 0.625rem;
    }

    .user-role-group--comerciales { border-left: 4px solid #075985; }
    .user-role-group--administrativos { border-left: 4px solid #2563eb; }

    .dark .user-role-group--comerciales { border-left-color: #38bdf8; }
    .dark .user-role-group--administrativos { border-left-color: #60a5fa; }

    .dark .user-role-group-shell {
        border-color: rgb(255 255 255 / 0.07);
        background: linear-gradient(180deg, rgb(15 23 42 / 0.72), rgb(2 6 23 / 0.55));
    }

    .user-role-group-shell > .fi-section-header {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .user-role-group-shell .fi-section-header-heading {
        font-size: 0.8125rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .dark .user-role-group-shell .fi-section-header-heading {
        color: rgb(241 245 249);
    }

    .user-role-toggles-grid {
        gap: 0.75rem !important;
    }

    /* ── Tarjeta toggle ── */
    .user-role-toggle-card.fi-fo-field {
        margin: 0;
        padding: 0.9rem 1rem;
        border-radius: 0.875rem;
        border: 1px solid rgb(226 232 240 / 0.95);
        background: linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.92));
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.75);
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
    }

    .dark .user-role-toggle-card.fi-fo-field {
        border-color: rgb(255 255 255 / 0.07);
        background: linear-gradient(180deg, rgb(30 41 59 / 0.55), rgb(15 23 42 / 0.42));
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.03);
    }

    .user-role-toggle-card.fi-fo-field:hover {
        transform: translateY(-1px);
        border-color: rgb(148 163 184 / 0.55);
        box-shadow: 0 10px 24px -18px rgb(15 23 42 / 0.35);
    }

    .dark .user-role-toggle-card.fi-fo-field:hover {
        border-color: rgb(255 255 255 / 0.14);
        box-shadow: 0 10px 24px -18px rgb(0 0 0 / 0.55);
    }

    .user-role-toggle-card.fi-fo-field:has([aria-checked="true"]) {
        border-color: var(--role-accent-ring);
        background: linear-gradient(180deg, var(--role-accent-soft), rgb(255 255 255 / 0.96));
        box-shadow: 0 0 0 1px var(--role-accent-ring), 0 12px 28px -20px color-mix(in srgb, var(--role-accent) 35%, transparent);
    }

    .dark .user-role-toggle-card.fi-fo-field:has([aria-checked="true"]) {
        background: linear-gradient(180deg, color-mix(in srgb, var(--role-accent) 18%, rgb(15 23 42)), rgb(15 23 42 / 0.72));
        box-shadow: 0 0 0 1px var(--role-accent-ring), 0 12px 28px -20px color-mix(in srgb, var(--role-accent) 28%, transparent);
    }

    .user-role-toggle-card.fi-fo-field-has-inline-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-role-toggle-card .fi-fo-field-label-col {
        flex: 1;
        min-width: 0;
    }

    .user-role-toggle-card .fi-fo-field-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-direction: row-reverse;
        gap: 0.85rem;
        width: 100%;
        margin: 0;
        cursor: pointer;
    }

    .user-role-toggle-card .fi-fo-field-label-content {
        display: contents;
    }

    .user-role-toggle-copy {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
        flex: 1;
    }

    .user-role-toggle-icon {
        display: inline-flex;
        height: 2.35rem;
        width: 2.35rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 0.85rem;
        background: var(--role-accent-soft);
        color: var(--role-accent);
        box-shadow: inset 0 0 0 1px var(--role-accent-ring);
    }

    .user-role-toggle-icon svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    .user-role-toggle-text {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 0.15rem;
    }

    .user-role-toggle-title {
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.25;
        color: rgb(15 23 42);
    }

    .dark .user-role-toggle-title {
        color: rgb(241 245 249);
    }

    .user-role-toggle-description {
        font-size: 0.75rem;
        line-height: 1.35;
        color: rgb(100 116 139);
    }

    .dark .user-role-toggle-description {
        color: rgb(148 163 184);
    }

    .user-role-toggle-card .fi-toggle {
        flex-shrink: 0;
    }

    .user-role-toggle-card .fi-fo-field-content-col {
        display: none;
    }
</style>

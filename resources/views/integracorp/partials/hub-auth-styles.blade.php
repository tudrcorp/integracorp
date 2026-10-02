<style>
    html:has(body.ic-hub-body--auth) {
        margin: 0;
        padding: 0;
        height: 100vh;
        height: 100dvh;
        max-height: 100dvh;
        overflow: hidden;
        overscroll-behavior: none;
        background-color: #0f2233;
        transition: none;
    }

    html.ic-hub-theme-light:has(body.ic-hub-body--auth) {
        background-color: #F0F4F8;
    }

    body.ic-hub-body--auth {
        --ic-auth-bg: #0f2233;
        --ic-auth-ink: #efe9df;
        --ic-auth-accent: #d8b878;
        --ic-auth-accent-hover: #e6cb93;
        --ic-auth-muted: rgba(239, 233, 223, 0.55);
        --ic-auth-muted-soft: rgba(239, 233, 223, 0.7);
        --ic-auth-border: rgba(239, 233, 223, 0.2);
        --ic-auth-placeholder: rgba(239, 233, 223, 0.35);
        --ic-auth-gradient-end: #0f2233;
        --ic-auth-submit-ink: #0f2233;
        --ic-auth-hero-surface: #0b1a28;
        position: fixed;
        inset: 0;
        width: 100%;
        margin: 0;
        padding: 0;
        height: 100vh;
        height: 100dvh;
        max-height: 100dvh;
        min-height: 0;
        overflow: hidden;
        overscroll-behavior: none;
        background: var(--ic-auth-bg);
        color: var(--ic-auth-ink);
        font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif;
        color-scheme: dark;
        transition: none;
    }

    body.ic-hub-body--auth .ic-hub-bg,
    body.ic-hub-body--auth > .ic-hub-brand-mark,
    body.ic-hub-body--auth > .ic-hub-footer {
        display: none;
    }

    body.ic-hub-body--auth .ic-hub-page--login {
        position: relative;
        z-index: 1;
        height: 100%;
        min-height: 0;
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0;
        display: block;
        isolation: isolate;
    }

    body.ic-hub-body--auth > div:has(> .ic-auth) {
        height: 100%;
        min-height: 0;
        margin: 0;
        padding: 0;
    }

    .ic-auth {
        position: fixed;
        inset: 0;
        z-index: 1;
        width: 100%;
        height: 100vh;
        height: 100dvh;
        max-height: 100dvh;
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr);
        align-items: stretch;
        background: var(--ic-auth-bg);
        color: var(--ic-auth-ink);
        overflow: hidden;
    }

    @media (min-width: 900px) {
        .ic-auth {
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        }
    }

    .ic-auth__hero {
        display: none;
        position: relative;
        overflow: hidden;
        background: var(--ic-auth-hero-surface);
    }

    @media (min-width: 900px) {
        .ic-auth__hero {
            display: block;
            min-height: 0;
            height: 100%;
        }
    }

    .ic-auth__hero-photo {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scale(1.03);
        filter: grayscale(1) brightness(0.55) contrast(1.1);
    }

    .ic-auth__hero-gradient {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }

    .ic-auth__hero-gradient--horizontal {
        background: linear-gradient(
            90deg,
            rgba(10, 24, 37, 0.2) 0%,
            rgba(15, 34, 51, 0.65) 75%,
            var(--ic-auth-gradient-end) 100%
        );
    }

    .ic-auth__hero-gradient--vertical {
        background: linear-gradient(
            180deg,
            rgba(10, 24, 37, 0.5) 0%,
            transparent 35%,
            rgba(10, 24, 37, 0.9) 100%
        );
    }

    .ic-auth__hero-copy {
        --ic-auth-hero-kicker-ink: var(--ic-auth-accent);
        --ic-auth-hero-tags-ink: var(--ic-auth-muted-soft);
        position: absolute;
        left: clamp(1.75rem, 4vw, 4rem);
        right: clamp(1.75rem, 4vw, 4rem);
        bottom: clamp(1.75rem, 6vh, 4rem);
        display: flex;
        flex-direction: column;
        gap: 1.125rem;
        z-index: 1;
    }

    .ic-auth__hero-kicker {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        font-size: 0.6875rem;
        letter-spacing: 0.36em;
        text-transform: uppercase;
        color: var(--ic-hub-logo-gold);
    }

    .ic-auth__hero-kicker-line {
        width: 2.5rem;
        height: 1px;
        background: var(--ic-hub-logo-gold);
        flex-shrink: 0;
    }

    .ic-auth__hero-title {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-weight: 400;
        font-size: clamp(2.75rem, min(5.5vw, 9vh), 5.5rem);
        line-height: 0.95;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .ic-auth__hero-tags {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.875rem;
        font-size: 0.75rem;
        letter-spacing: 0.32em;
        text-transform: uppercase;
        color: var(--ic-auth-hero-tags-ink);
    }

    .ic-auth__hero-diamond {
        width: 5px;
        height: 5px;
        transform: rotate(45deg);
        background: var(--ic-auth-accent);
        flex-shrink: 0;
    }

    .ic-auth__panel {
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 0;
        height: 100%;
        max-height: 100dvh;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: clamp(1.25rem, 4vh, 2.5rem) clamp(1.5rem, 5vw, 5rem);
    }

    .ic-auth__panel-glow {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: radial-gradient(
            ellipse 80% 50% at 50% 0%,
            rgba(216, 184, 120, 0.1),
            transparent 70%
        );
    }

    .ic-auth__panel-top {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: clamp(0.75rem, 2vw, 1.25rem);
        flex-shrink: 0;
        width: 100%;
    }

    .ic-auth__panel-top .ic-hub-theme-toggle-wrap {
        position: static;
        flex-shrink: 0;
        margin-left: auto;
    }

    .ic-auth__date {
        margin: 0;
        font-size: 0.6875rem;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        color: var(--ic-auth-muted);
        text-align: left;
        flex: 0 1 auto;
        min-width: 0;
        line-height: 1.3;
    }

    .ic-auth__panel-inner {
        position: relative;
        z-index: 1;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 25rem;
        margin-inline: auto;
        min-height: 0;
    }

    .ic-auth__panel-body {
        position: relative;
        flex: 1 1 auto;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        min-height: 0;
        width: 100%;
    }

    .ic-auth__legal {
        position: relative;
        margin: clamp(1rem, 3vh, 1.5rem) 0 0;
        font-size: 0.6875rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--ic-auth-muted);
        text-align: center;
        flex-shrink: 0;
        width: 100%;
    }

    .ic-auth-form-wrap {
        width: 100%;
        max-width: none;
        display: flex;
        flex-direction: column;
        gap: clamp(1.5rem, 4.5vh, 2.75rem);
    }

    .ic-auth-form-logo {
        height: clamp(2.5rem, 6.5vh, 3.75rem);
        width: auto;
        max-width: 100%;
        object-fit: contain;
        align-self: flex-start;
    }

    .ic-auth-form-logo--on-light {
        display: none;
    }

    html.ic-hub-theme-light .ic-auth-form-logo--on-dark {
        display: none;
    }

    html.ic-hub-theme-light .ic-auth-form-logo--on-light {
        display: block;
    }

    .ic-auth-form {
        display: flex;
        flex-direction: column;
        gap: clamp(1.25rem, 3.4vh, 2rem);
    }

    .ic-auth-form__head {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
    }

    .ic-auth-form__title {
        margin: 0;
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-weight: 300;
        font-size: clamp(2.25rem, 5.5vh, 3.25rem);
        line-height: 1;
        letter-spacing: -0.01em;
    }

    .ic-auth-form__title em {
        font-style: italic;
        color: var(--ic-auth-accent);
    }

    .ic-auth-form__lead {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.6;
        color: var(--ic-auth-muted);
        text-wrap: pretty;
    }

    .ic-auth-back {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 0.6875rem;
        letter-spacing: 0.24em;
        text-transform: uppercase;
        color: var(--ic-auth-muted);
        text-decoration: none;
        transition: color 0.25s ease;
    }

    .ic-auth-back__line {
        width: 1.25rem;
        height: 1px;
        background: currentColor;
    }

    .ic-auth-back:hover {
        color: var(--ic-auth-ink);
    }

    .ic-auth-field {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
    }

    .ic-auth-field__label {
        font-size: 0.625rem;
        letter-spacing: 0.3em;
        text-transform: uppercase;
        color: var(--ic-auth-muted);
    }

    .ic-auth-input {
        width: 100%;
        border: none;
        border-bottom: 1px solid var(--ic-auth-border);
        background: transparent;
        color: var(--ic-auth-ink);
        font-family: inherit;
        font-size: 1rem;
        padding: 0.625rem 0 0.75rem;
        outline: none;
        transition: border-color 0.3s ease;
    }

    .ic-auth-input::placeholder {
        color: var(--ic-auth-placeholder);
    }

    .ic-auth-input:focus {
        border-bottom-color: var(--ic-auth-accent);
    }

    .ic-auth-input:-webkit-autofill,
    .ic-auth-input:-webkit-autofill:hover,
    .ic-auth-input:-webkit-autofill:focus {
        -webkit-text-fill-color: var(--ic-auth-ink);
        caret-color: var(--ic-auth-ink);
        box-shadow: 0 0 0 1000px transparent inset;
        transition: background-color 9999s ease-out 0s;
    }

    .ic-auth-input-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-bottom: 1px solid var(--ic-auth-border);
        transition: border-color 0.3s ease;
    }

    .ic-auth-input-row:focus-within {
        border-bottom-color: var(--ic-auth-accent);
    }

    .ic-auth-input-row--password .ic-auth-input {
        flex: 1 1 auto;
        min-width: 0;
        border-bottom: none;
        padding-right: 0;
    }

    .ic-auth-input__reveal {
        flex-shrink: 0;
        border: none;
        background: none;
        padding: 0.375rem 0;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.625rem;
        letter-spacing: 0.24em;
        text-transform: uppercase;
        color: var(--ic-hub-logo-gold);
        transition: color 0.25s ease;
    }

    .ic-auth-input__reveal:hover {
        color: var(--ic-hub-logo-gold-hover);
    }

    .ic-auth-input__reveal:focus-visible {
        outline: 2px solid rgba(216, 184, 120, 0.55);
        outline-offset: 2px;
    }

    .ic-auth-field__error {
        font-size: 0.76rem;
        color: #fecaca;
        line-height: 1.4;
    }

    .ic-auth-status {
        margin: 0;
        padding: 0.65rem 0.75rem;
        border-radius: 0.65rem;
        font-size: 0.8rem;
        line-height: 1.45;
        color: rgba(240, 253, 250, 0.95);
        background: rgba(216, 184, 120, 0.12);
        border: 1px solid rgba(216, 184, 120, 0.28);
    }

    .ic-auth-options {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        font-size: 0.8125rem;
    }

    .ic-auth-remember {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        cursor: pointer;
        color: var(--ic-auth-muted-soft);
        user-select: none;
    }

    .ic-auth-remember__native {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .ic-auth-remember__box {
        width: 0.875rem;
        height: 0.875rem;
        border: 1px solid var(--ic-auth-accent);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .ic-auth-remember__mark {
        width: 0.375rem;
        height: 0.375rem;
        background: var(--ic-auth-accent);
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .ic-auth-remember:has(.ic-auth-remember__native:checked) .ic-auth-remember__mark {
        opacity: 1;
    }

    .ic-auth-remember:has(.ic-auth-remember__native:focus-visible) .ic-auth-remember__box {
        outline: 2px solid rgba(216, 184, 120, 0.55);
        outline-offset: 2px;
    }

    .ic-auth-options .ic-auth-link {
        color: var(--ic-hub-logo-gold);
        text-decoration: none;
        transition: color 0.25s ease;
    }

    .ic-auth-options .ic-auth-link:hover {
        color: var(--ic-hub-logo-gold-hover);
    }

    .ic-auth-link {
        color: var(--ic-auth-accent);
        text-decoration: none;
        transition: color 0.25s ease;
    }

    .ic-auth-link:hover {
        color: var(--ic-auth-accent-hover);
    }

    .ic-auth-submit {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.875rem;
        width: 100%;
        height: 3.375rem;
        margin: 0;
        border: none;
        border-radius: 0.625rem;
        background: var(--ic-hub-logo-gold);
        color: var(--ic-auth-submit-ink);
        font-family: inherit;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        cursor: pointer;
        transition: background 0.3s ease, opacity 0.15s ease;
    }

    .ic-auth-submit__line {
        width: 1.75rem;
        height: 1px;
        background: var(--ic-auth-submit-ink);
        flex-shrink: 0;
    }

    .ic-auth-submit:hover:not(:disabled) {
        background: var(--ic-hub-logo-gold-hover);
    }

    .ic-auth-submit:disabled {
        opacity: 0.65;
        cursor: wait;
    }

    .ic-auth-submit:focus-visible {
        outline: 2px solid rgba(216, 184, 120, 0.65);
        outline-offset: 3px;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth {
        --ic-auth-bg: #F0F4F8;
        --ic-auth-ink: #0B132B;
        --ic-auth-accent: #9a7b22;
        --ic-auth-accent-hover: #b8922a;
        --ic-auth-muted: #64748B;
        --ic-auth-muted-soft: rgba(15, 23, 42, 0.72);
        --ic-auth-border: rgba(15, 23, 42, 0.18);
        --ic-auth-placeholder: rgba(15, 23, 42, 0.35);
        --ic-auth-gradient-end: #F0F4F8;
        --ic-auth-submit-ink: #ffffff;
        --ic-auth-hero-surface: #e8eef4;
        color-scheme: light;
        background-color: #F0F4F8;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth {
        background-color: #F0F4F8;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero {
        --ic-auth-hero-surface: #0b1a28;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__panel {
        background-color: #F0F4F8;
        color: #0B132B;
    }

    html.ic-hub-theme-switching:has(body.ic-hub-body--auth),
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) body.ic-hub-body--auth,
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) .ic-auth,
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) .ic-auth__panel,
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) .ic-auth__panel-glow,
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) .ic-auth__hero-gradient--horizontal,
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) .ic-auth__hero-gradient--vertical,
    html.ic-hub-theme-switching:has(body.ic-hub-body--auth) .ic-auth__hero-photo {
        transition: none !important;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero-copy {
        --ic-auth-hero-tags-ink: rgba(239, 233, 223, 0.82);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero-kicker {
        color: var(--ic-hub-logo-gold);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero-kicker-line {
        background: var(--ic-hub-logo-gold);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-input__reveal {
        color: var(--ic-hub-logo-gold-muted);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-input__reveal:hover {
        color: var(--ic-hub-logo-gold);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-options .ic-auth-link {
        color: var(--ic-hub-logo-gold-muted);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-options .ic-auth-link:hover {
        color: var(--ic-hub-logo-gold);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-submit {
        background: var(--ic-hub-logo-gold);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-submit:hover:not(:disabled) {
        background: var(--ic-hub-logo-gold-hover);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero-title {
        color: #efe9df;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero-gradient--horizontal {
        background: linear-gradient(
            90deg,
            rgba(240, 244, 248, 0.15) 0%,
            rgba(240, 244, 248, 0.72) 72%,
            var(--ic-auth-gradient-end) 100%
        );
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__hero-gradient--vertical {
        background: linear-gradient(
            180deg,
            transparent 0%,
            transparent 38%,
            rgba(10, 24, 37, 0.18) 100%
        );
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__panel-glow {
        background:
            radial-gradient(
                ellipse 130% 90% at 50% -15%,
                rgba(216, 184, 120, 0.22) 0%,
                rgba(216, 184, 120, 0.07) 42%,
                transparent 68%
            );
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__date,
    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth__legal {
        color: rgba(15, 23, 42, 0.45);
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-field__error {
        color: #b91c1c;
    }

    html.ic-hub-theme-light body.ic-hub-body--auth .ic-auth-status {
        color: rgba(15, 23, 42, 0.88);
        background: rgba(154, 123, 34, 0.1);
        border-color: rgba(154, 123, 34, 0.22);
    }
</style>

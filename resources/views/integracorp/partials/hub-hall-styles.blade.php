<style>
    html:has(.ic-hub-page--modules) {
        height: 100%;
        background: #0f2233;
    }

    html.ic-hub-theme-light:has(.ic-hub-page--modules) {
        background: #eef2f6;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) {
        --ic-modules-gap: 0.65rem;
        --ic-hall-accent: #d8b878;
        --ic-hall-ink: #efe9df;
        --ic-hall-radius: 10px;
        height: 100dvh;
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        overflow: hidden;
        background: transparent;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-page.ic-hub-page--modules {
        flex: 1 1 auto;
        align-self: stretch;
        width: 100%;
        max-width: none;
        min-height: 0;
        margin: 0;
        padding: 0;
        justify-content: flex-start;
        align-items: stretch;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-page--modules > .ic-hub-theme-toggle-wrap {
        display: none;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg {
        display: block;
        z-index: 0;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--auth {
        display: none;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules {
        opacity: 1;
        visibility: visible;
        z-index: 1;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark {
        --ic-hub-modules-photo-filter: brightness(0.42) contrast(1.16) saturate(1.32) hue-rotate(8deg);
        opacity: 1;
        filter: var(--ic-hub-modules-photo-filter);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-light {
        opacity: 0;
        filter: brightness(1) contrast(1) saturate(1);
    }

    html:not(.ic-hub-theme-light) body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules {
        filter: var(--ic-hub-modules-photo-filter, brightness(0.42) contrast(1.16) saturate(1.32) hue-rotate(8deg));
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark {
        opacity: 0;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-light {
        opacity: 1;
        filter: brightness(1.02) contrast(1.04) saturate(1.06);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules {
        filter: brightness(1.02) contrast(1.04) saturate(1.06);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__sparkles,
    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__ribbons {
        opacity: 0;
        visibility: hidden;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__grid {
        opacity: 0.06;
        visibility: visible;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__vignette {
        position: absolute;
        inset: 0;
        z-index: 3;
        pointer-events: none;
        background: radial-gradient(
            ellipse 120% 85% at 50% 40%,
            transparent 35%,
            rgba(8, 14, 22, 0.55) 100%
        );
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__vignette {
        background: radial-gradient(
            ellipse 120% 85% at 50% 40%,
            transparent 40%,
            rgba(15, 23, 42, 0.12) 100%
        );
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__veil {
        z-index: 2;
        background: rgba(8, 14, 22, 0.38);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__veil {
        background: rgba(255, 255, 255, 0.58);
    }

    html:not(.ic-hub-theme-light) body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 2;
        pointer-events: none;
        background: linear-gradient(
            180deg,
            rgba(216, 184, 120, 0.14) 0%,
            transparent 42%
        );
    }

    html.ic-hub-theme-switching body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo,
    html.ic-hub-theme-switching body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg::after {
        transition: opacity 0.48s ease, filter 0.48s ease;
    }

    .ic-hall {
        width: 100%;
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        color: var(--ic-hall-ink);
    }

    .ic-hall-shell {
        position: relative;
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        width: 100%;
        isolation: isolate;
    }

    .ic-hall-shell::before {
        display: none;
    }

    .ic-hall-shell > * {
        position: relative;
        z-index: 1;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-topbar {
        border-bottom: none;
    }

    .ic-hall-topbar {
        z-index: 3;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
        margin-top: 0;
        padding: max(0.5rem, env(safe-area-inset-top, 0px)) clamp(1.25rem, 4vw, 4.5rem) clamp(0.65rem, 1.8vh, 1.25rem);
        border-bottom: 1px solid rgba(239, 233, 223, 0.08);
    }

    .ic-hall-topbar__brand {
        grid-column: 1;
        justify-self: start;
        flex-shrink: 0;
    }

    .ic-hub-brand-mark--header-bar {
        position: static;
        transform: none;
    }

    .ic-hub-brand-mark--header-bar:hover {
        transform: translateY(-1px);
    }

    .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo {
        height: clamp(2rem, 5vh, 3rem);
        max-width: min(12rem, 42vw);
        object-fit: contain;
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark {
        display: block;
        filter: drop-shadow(0 4px 14px rgba(0, 0, 0, 0.28));
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar.ic-hub-brand-mark--dark-logo-gold .ic-hub-brand-mark__logo--on-dark {
        display: none;
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark-gold {
        display: none;
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar.ic-hub-brand-mark--dark-logo-gold .ic-hub-brand-mark__logo--on-dark-gold {
        display: block;
        filter: drop-shadow(0 4px 18px rgba(0, 0, 0, 0.42));
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-light {
        display: none;
    }

    html.ic-hub-theme-light .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark {
        display: none;
    }

    html.ic-hub-theme-light .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-light {
        display: block;
        filter: drop-shadow(0 2px 10px rgba(154, 123, 34, 0.22));
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-light {
        display: none;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark {
        display: block;
        filter: drop-shadow(0 4px 14px rgba(0, 0, 0, 0.28));
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark-gold {
        display: none;
    }

    .ic-hall-topbar__clock {
        grid-column: 2;
        justify-self: center;
        display: none;
        align-items: center;
        gap: 0.85rem;
        font-size: 0.68rem;
        font-weight: 500;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        color: rgba(239, 233, 223, 0.55);
        white-space: nowrap;
    }

    @media (min-width: 720px) {
        .ic-hall-topbar__clock {
            display: flex;
        }
    }

    .ic-hall-topbar__clock-dot {
        width: 4px;
        height: 4px;
        transform: rotate(45deg);
        background: var(--ic-hall-accent);
        flex-shrink: 0;
    }

    .ic-hall-topbar__clock-time {
        font-variant-numeric: tabular-nums;
    }

    .ic-hall-topbar__actions {
        grid-column: 3;
        justify-self: end;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: clamp(0.65rem, 2vw, 1.25rem);
        flex-wrap: wrap;
    }

    .ic-hall-topbar__actions .ic-hub-theme-toggle-wrap {
        position: static;
    }

    .ic-hall-topbar__user {
        display: none;
        align-items: center;
        gap: 0.75rem;
    }

    @media (min-width: 640px) {
        .ic-hall-topbar__user {
            display: flex;
        }
    }

    .ic-hall-topbar__avatar {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 9999px;
        border: 1px solid var(--ic-hall-accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 1.05rem;
        color: var(--ic-hall-accent);
    }

    .ic-hall-topbar__user-copy {
        display: flex;
        flex-direction: column;
        gap: 0.12rem;
    }

    .ic-hall-topbar__user-name {
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--ic-hall-ink);
    }

    .ic-hall-topbar__user-meta {
        font-size: 0.68rem;
        color: rgba(239, 233, 223, 0.5);
    }

    .ic-hall-btn-logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(239, 233, 223, 0.18);
        background: transparent;
        color: var(--ic-hall-ink);
        font-family: inherit;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        padding: 0.65rem 1.1rem;
        cursor: pointer;
        transition: border-color 0.25s ease, color 0.25s ease;
    }

    .ic-hall-btn-logout:hover {
        border-color: var(--ic-hall-accent);
        color: var(--ic-hall-accent);
    }

    .ic-hall-hero {
        flex-shrink: 0;
        padding: clamp(0.75rem, 3vh, 2.5rem) clamp(1.25rem, 4vw, 4.5rem) clamp(0.5rem, 1.5vh, 1.25rem);
    }

    .ic-hall-hero__copy {
        max-width: 42rem;
    }

    .ic-hall-hero__greeting {
        margin: 0 0 clamp(0.45rem, 1.2vh, 0.85rem);
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-weight: 300;
        font-size: clamp(2.1rem, min(6vw, 8.5vh), 5.75rem);
        line-height: 0.98;
        letter-spacing: -0.015em;
        text-wrap: balance;
        color: var(--ic-hall-ink);
    }

    .ic-hall-hero__name {
        font-style: italic;
        font-weight: 400;
        color: var(--ic-hall-accent);
    }

    .ic-hall-hero__lead {
        margin: 0;
        max-width: 28rem;
        font-size: 0.94rem;
        line-height: 1.6;
        color: rgba(239, 233, 223, 0.62);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-main {
        flex: 1 1 auto;
        min-height: 0;
        justify-content: center;
        align-items: center;
        padding-top: 0;
        padding-bottom: 0;
        padding-left: clamp(1rem, 4vw, 4.5rem);
        padding-right: clamp(1rem, 4vw, 4.5rem);
        border-top: none;
    }

    .ic-hall-main {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 0 clamp(1rem, 4vw, 4.5rem) clamp(0.85rem, 2.5vh, 1.75rem);
        border-top: 1px solid rgba(239, 233, 223, 0.06);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-stage {
        flex: 0 0 auto;
        width: min(100%, 76rem);
        margin-top: auto;
        margin-bottom: auto;
    }

    .ic-hall-stage {
        width: min(100%, 76rem);
        margin-inline: auto;
        min-height: 0;
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
    }

    .ic-hall-accordion {
        --ic-hall-accordion-collapsed-w: 5.95rem;
        --ic-hall-accordion-spotlight-w: 29.75rem;
        display: flex;
        flex-direction: row;
        align-items: stretch;
        justify-content: center;
        gap: var(--ic-modules-gap);
        width: fit-content;
        max-width: 100%;
        margin-inline: auto;
        height: clamp(14rem, min(42vh, 52vh), 26rem);
        min-height: clamp(14rem, 42vh, 26rem);
        max-height: min(52vh, 26rem);
    }

    .ic-module-card--hall {
        --ic-module-card-ink: #efe9df;
        --ic-module-card-muted: rgba(239, 233, 223, 0.78);
        --ic-module-card-subtle: rgba(239, 233, 223, 0.6);
        --ic-module-card-accent: #d8b878;
        position: relative;
        display: block;
        box-sizing: border-box;
        flex: 0 0 auto;
        width: var(--ic-hall-accordion-collapsed-w);
        height: 100%;
        min-width: 0;
        min-height: 0;
        border-radius: var(--ic-hall-radius);
        overflow: hidden;
        overflow: clip;
        isolation: isolate;
        contain: paint;
        clip-path: inset(0 round var(--ic-hall-radius));
        text-decoration: none;
        color: var(--ic-module-card-ink);
        background: #0b1a28;
        outline: 1px solid rgba(239, 233, 223, 0.1);
        outline-offset: -1px;
        transform: translate3d(0, 0, 0);
        transition:
            width 0.8s cubic-bezier(0.2, 0.8, 0.2, 1),
            transform 0.22s cubic-bezier(0.4, 0, 0.2, 1),
            box-shadow 0.22s ease,
            outline-color 0.35s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .ic-module-card--hall.ic-module-card--spotlight {
        width: var(--ic-hall-accordion-spotlight-w);
        outline-color: var(--ic-hall-accent);
    }

    .ic-module-card--hall.ic-module-card--hall-collapsed {
        width: var(--ic-hall-accordion-collapsed-w);
    }

    .ic-module-card--hall:is(:hover, :focus-visible, .ic-module-card--spotlight):not(.ic-module-card--pressed) {
        transform: translate3d(0, -0.35rem, 0);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
    }

    .ic-module-card--hall:active,
    .ic-module-card--hall.ic-module-card--pressed {
        transform: translate3d(0, 3px, 0) scale(0.985);
        transition-duration: 0.1s;
    }

    .ic-module-card__media {
        position: absolute;
        inset: 0;
        z-index: 0;
        overflow: hidden;
        overflow: clip;
        border-radius: inherit;
        clip-path: inset(0 round var(--ic-hall-radius));
        transform: translateZ(0);
        pointer-events: none;
    }

    .ic-module-card__photo {
        position: absolute;
        inset: -2px;
        width: calc(100% + 4px);
        height: calc(100% + 4px);
        max-width: none;
        object-fit: cover;
        object-position: center;
        transform: translate3d(0, 0, 0) scale(1.04);
        filter: grayscale(1) brightness(0.55) contrast(1.1);
        transition: transform 1.2s cubic-bezier(0.2, 0.8, 0.2, 1), filter 0.65s ease;
    }

    .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__photo {
        filter: grayscale(0) brightness(0.95);
        transform: translate3d(0, 0, 0) scale(1.06);
    }

    .ic-module-card__veil {
        position: absolute;
        inset: 0;
        z-index: 1;
        pointer-events: none;
        background: linear-gradient(
            180deg,
            rgba(10, 24, 37, 0.45) 0%,
            rgba(10, 24, 37, 0.08) 40%,
            rgba(10, 24, 37, 0.88) 100%
        );
    }

    .ic-module-card__title-vertical {
        position: absolute;
        left: 50%;
        bottom: clamp(0.85rem, 2.6vh, 1.75rem);
        z-index: 2;
        transform: translateX(-50%);
        writing-mode: vertical-rl;
        rotate: 180deg;
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: clamp(0.85rem, 2.2vh, 1.45rem);
        letter-spacing: 0.02em;
        white-space: nowrap;
        pointer-events: none;
        color: var(--ic-module-card-ink);
        opacity: 1;
        transition: opacity 0.35s ease;
    }

    .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__title-vertical {
        opacity: 0;
    }

    .ic-module-card__panel {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        border-radius: inherit;
        overflow: hidden;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__panel {
        opacity: 1;
    }

    .ic-module-card__panel-content {
        position: relative;
        z-index: 3;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: clamp(0.35rem, 1vh, 0.65rem);
        padding: clamp(0.85rem, 2.6vh, 1.75rem);
        text-align: left;
        transform: translateY(0.35rem);
        opacity: 0;
        transition:
            transform 0.42s cubic-bezier(0.33, 1.12, 0.48, 1),
            opacity 0.38s cubic-bezier(0.33, 1, 0.48, 1);
    }

    .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__panel-content {
        transform: translateY(0);
        opacity: 1;
    }

    .ic-module-card__badge {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.32em;
        text-transform: uppercase;
        color: var(--ic-module-card-accent);
    }

    .ic-module-card__name {
        margin: 0;
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: clamp(1.5rem, min(3.2vw, 5vh), 3.25rem);
        font-weight: 400;
        line-height: 1.05;
        letter-spacing: -0.01em;
        color: var(--ic-module-card-ink);
    }

    .ic-module-card__objective {
        margin: 0;
        max-width: 22.5rem;
        font-size: 0.86rem;
        line-height: 1.55;
        color: var(--ic-module-card-muted);
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        overflow: hidden;
    }

    .ic-module-card__footer-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        padding-top: clamp(0.45rem, 1.2vh, 0.85rem);
        border-top: 1px solid rgba(239, 233, 223, 0.16);
    }

    .ic-module-card__tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: 0.65rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--ic-module-card-subtle);
    }

    .ic-module-card__tag {
        padding: 0;
        border: none;
        background: transparent;
        font: inherit;
        color: inherit;
    }

    .ic-module-card__enter {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        margin-left: auto;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.24em;
        text-transform: uppercase;
        color: var(--ic-module-card-accent);
    }

    .ic-module-card__enter-line {
        display: block;
        width: 1.75rem;
        height: 1px;
        background: var(--ic-module-card-accent);
    }

    .ic-module-card--enter {
        animation: ic-module-enter 0.65s cubic-bezier(0.33, 1, 0.48, 1) both;
        animation-delay: var(--ic-module-enter-delay, 0ms);
    }

    @keyframes ic-module-enter {
        from {
            opacity: 0;
            transform: translateY(1rem);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (min-width: 900px) {
        .ic-hall-accordion {
            --ic-hall-accordion-collapsed-w: calc((76rem - 7 * var(--ic-modules-gap)) / 12);
            --ic-hall-accordion-spotlight-w: calc(var(--ic-hall-accordion-collapsed-w) * 5);
        }

        .ic-hall-accordion[data-count="2"] {
            justify-content: center;
        }
    }

    @media (max-width: 899px) {
        .ic-hall-topbar {
            grid-template-columns: 1fr;
            justify-items: center;
        }

        .ic-hall-topbar__brand,
        .ic-hall-topbar__clock,
        .ic-hall-topbar__actions {
            grid-column: 1;
            justify-self: center;
        }

        .ic-hall-topbar__actions {
            width: 100%;
            justify-content: center;
        }

        .ic-hall-hero {
            padding: 0.65rem 1.25rem 0.35rem;
            text-align: center;
        }

        .ic-hall-hero__copy {
            max-width: none;
            margin-inline: auto;
        }

        .ic-hall-hero__greeting {
            font-size: clamp(1.65rem, 7.5vw, 2.25rem);
        }

        .ic-hall-main {
            justify-content: flex-start;
            padding-bottom: 1rem;
        }

        .ic-hall-accordion {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            width: 100%;
            height: auto;
            min-height: 0;
            max-height: none;
        }

        .ic-hall-accordion[data-count="1"] {
            grid-template-columns: 1fr;
        }

        .ic-module-card--hall {
            width: 100%;
            aspect-ratio: 4 / 5;
            height: auto;
        }

        .ic-module-card--hall .ic-module-card__title-vertical {
            display: none;
        }

        .ic-module-card--hall .ic-module-card__panel,
        .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__panel {
            opacity: 1;
        }

        .ic-module-card--hall .ic-module-card__panel-content,
        .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__panel-content {
            opacity: 1;
            transform: translateY(0);
            align-items: center;
            text-align: center;
        }

        .ic-module-card--hall .ic-module-card__photo,
        .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__photo {
            filter: grayscale(0) brightness(0.95);
        }

        .ic-module-card--hall .ic-module-card__name {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .ic-module-card--hall .ic-module-card__objective {
            font-size: 0.72rem;
            -webkit-line-clamp: 2;
        }

        .ic-module-card--hall .ic-module-card__enter {
            display: none;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) {
            height: auto;
            min-height: 100dvh;
            overflow-x: hidden;
            overflow-y: auto;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer {
            margin-top: auto;
        }
    }

    html.ic-hub-theme-light .ic-hall {
        --ic-hall-ink: #1a2838;
    }

    html.ic-hub-theme-light .ic-hall-topbar,
    html.ic-hub-theme-light .ic-hall-main {
        border-color: rgba(15, 23, 42, 0.08);
    }

    html.ic-hub-theme-light .ic-hall-topbar__clock {
        color: rgba(15, 23, 42, 0.55);
    }

    html.ic-hub-theme-light .ic-hall-topbar__user-name {
        color: rgba(15, 23, 42, 0.88);
    }

    html.ic-hub-theme-light .ic-hall-topbar__user-meta {
        color: rgba(15, 23, 42, 0.5);
    }

    html.ic-hub-theme-light .ic-hall-btn-logout {
        color: rgba(15, 23, 42, 0.88);
        border-color: rgba(15, 23, 42, 0.18);
    }

    html.ic-hub-theme-light .ic-hall-btn-logout:hover {
        color: #9a7b22;
        border-color: #9a7b22;
    }

    html.ic-hub-theme-light .ic-hall-hero__greeting {
        color: rgba(15, 23, 42, 0.92);
    }

    html.ic-hub-theme-light .ic-hall-hero__name {
        color: #9a7b22;
    }

    html.ic-hub-theme-light .ic-hall-hero__lead {
        color: rgba(15, 23, 42, 0.62);
    }

    html.ic-hub-theme-light .ic-module-card--hall {
        outline-color: rgba(239, 233, 223, 0.14);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer {
        border-top-color: rgba(15, 23, 42, 0.08);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer__text {
        color: rgba(15, 23, 42, 0.45);
    }
</style>

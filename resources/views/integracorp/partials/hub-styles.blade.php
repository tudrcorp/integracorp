<style>
    [x-cloak] {
        display: none !important;
    }

    :root {
        --ic-hub-cyan: #22d3ee;
        --ic-hub-teal: #14b8a6;
        --ic-hub-glow: rgba(34, 211, 238, 0.35);
        --ic-hub-glass: rgba(8, 14, 22, 0.55);
        --ic-hub-border: rgba(255, 255, 255, 0.14);
        --ic-hub-logo-gold: #d29d45;
        --ic-hub-logo-gold-hover: #e0ad58;
        --ic-hub-logo-gold-muted: #b8862f;
    }

    html {
        margin: 0;
        padding: 0;
    }

    .ic-hub-body {
        margin: 0;
        padding: 0;
        min-height: 100dvh;
        font-family: 'Montserrat', ui-sans-serif, system-ui, sans-serif;
        color: #f8fafc;
        background: #020617;
        overflow-x: hidden;
        color-scheme: dark;
        transition: background-color 1s ease, color 0.35s ease;
    }

    .ic-hub-bg {
        position: fixed;
        inset: -1px 0 0 0;
        z-index: 0;
        width: 100%;
        min-height: calc(100dvh + 1px);
        overflow: hidden;
    }

    .ic-hub-bg__photo {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: filter 1s ease, opacity 1s ease;
    }

    /* Login / recuperación: imagen anterior (i2) en ambos modos */
    .ic-hub-bg__photo--auth {
        display: block;
        filter: none;
    }

    /* Módulos: fondo oscuro (filtro) / fondo claro (imagen dedicada) */
    .ic-hub-bg__photo--modules {
        display: none;
    }

    .ic-hub-bg__photo--modules-dark {
        --ic-hub-modules-photo-filter: brightness(0.42) contrast(1.16) saturate(1.32) hue-rotate(8deg);
        filter: var(--ic-hub-modules-photo-filter);
    }

    .ic-hub-bg__photo--modules-light {
        filter: brightness(1) contrast(1) saturate(1) hue-rotate(0deg);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--auth {
        display: none;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark {
        display: block;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark {
        display: none;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-light {
        display: none;
    }

    .ic-hub-bg__veil {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        transition: background 1s ease, opacity 1s ease;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__veil {
        background:
            radial-gradient(
                ellipse 78% 68% at 50% 42%,
                rgba(2, 6, 23, 0.04) 0%,
                rgba(2, 6, 23, 0.22) 52%,
                rgba(2, 6, 23, 0.48) 100%
            );
    }

    .ic-hub-bg__vignette {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background:
            linear-gradient(to right, rgba(0, 0, 0, 0.38) 0%, transparent 26%, transparent 74%, rgba(0, 0, 0, 0.38) 100%),
            linear-gradient(to bottom, rgba(0, 0, 0, 0.28) 0%, transparent 28%, transparent 72%, rgba(0, 0, 0, 0.34) 100%);
        box-shadow:
            inset 0 0 80px 22px rgba(0, 0, 0, 0.22),
            inset 0 0 150px 60px rgba(2, 6, 23, 0.32);
        opacity: 0;
        transition: opacity 1s ease, box-shadow 1s ease;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__vignette {
        opacity: 1;
        transition: opacity 1s ease, box-shadow 1s ease;
    }

    .ic-hub-bg__sparkles {
        position: absolute;
        inset: 0;
        z-index: 3;
        pointer-events: none;
        opacity: 0;
        mix-blend-mode: screen;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__sparkles {
        opacity: 1;
    }

    .ic-hub-bg__sparkles::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 62% 52% at 50% 36%, rgba(186, 230, 253, 0.22) 0%, transparent 70%),
            radial-gradient(ellipse 48% 40% at 12% 68%, rgba(34, 211, 238, 0.14) 0%, transparent 74%),
            radial-gradient(ellipse 44% 38% at 88% 24%, rgba(255, 255, 255, 0.12) 0%, transparent 72%),
            radial-gradient(ellipse 36% 28% at 72% 78%, rgba(20, 184, 166, 0.1) 0%, transparent 68%);
    }

    .ic-hub-bg__sparkles::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 22% 32%, rgba(255, 255, 255, 0.06) 0%, transparent 28%),
            radial-gradient(circle at 68% 52%, rgba(186, 230, 253, 0.05) 0%, transparent 32%),
            radial-gradient(circle at 84% 44%, rgba(255, 255, 255, 0.05) 0%, transparent 26%),
            radial-gradient(circle at 38% 82%, rgba(34, 211, 238, 0.05) 0%, transparent 30%);
        animation: ic-hub-sparkle-ambient 11s ease-in-out infinite;
    }

    .ic-hub-bg__glow {
        position: absolute;
        border-radius: 9999px;
        filter: blur(78px);
        opacity: 0.42;
        animation: ic-hub-glow-pulse 16s ease-in-out infinite;
    }

    .ic-hub-bg__glow--1 {
        width: clamp(12rem, 32vw, 20rem);
        height: clamp(12rem, 32vw, 20rem);
        left: 4%;
        top: 12%;
        background: radial-gradient(circle, rgba(186, 230, 253, 0.28) 0%, rgba(186, 230, 253, 0.08) 42%, transparent 72%);
        animation-delay: 0s;
    }

    .ic-hub-bg__glow--2 {
        width: clamp(14rem, 36vw, 22rem);
        height: clamp(14rem, 36vw, 22rem);
        right: 2%;
        top: 18%;
        background: radial-gradient(circle, rgba(34, 211, 238, 0.22) 0%, rgba(34, 211, 238, 0.06) 45%, transparent 74%);
        animation-delay: -3.5s;
    }

    .ic-hub-bg__glow--3 {
        width: clamp(11rem, 28vw, 18rem);
        height: clamp(11rem, 28vw, 18rem);
        left: 36%;
        bottom: 4%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0.05) 40%, transparent 70%);
        animation-delay: -6.2s;
    }

    .ic-hub-bg__glow--4 {
        width: clamp(10rem, 24vw, 16rem);
        height: clamp(10rem, 24vw, 16rem);
        right: 20%;
        bottom: 16%;
        background: radial-gradient(circle, rgba(20, 184, 166, 0.2) 0%, rgba(20, 184, 166, 0.05) 44%, transparent 72%);
        animation-delay: -8.8s;
    }

    .ic-hub-bg__spark {
        position: absolute;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.72) 0%, rgba(186, 230, 253, 0.32) 40%, transparent 74%);
        filter: blur(1px);
        animation: ic-hub-sparkle-drift 8s ease-in-out infinite;
    }

    .ic-hub-bg__spark--1 {
        width: 0.35rem;
        height: 0.35rem;
        left: 14%;
        top: 22%;
        animation-delay: 0s;
    }

    .ic-hub-bg__spark--2 {
        width: 0.55rem;
        height: 0.55rem;
        left: 78%;
        top: 18%;
        animation-delay: -2.4s;
    }

    .ic-hub-bg__spark--3 {
        width: 0.28rem;
        height: 0.28rem;
        left: 62%;
        top: 44%;
        animation-delay: -4.1s;
    }

    .ic-hub-bg__spark--4 {
        width: 0.42rem;
        height: 0.42rem;
        left: 28%;
        top: 58%;
        animation-delay: -1.2s;
    }

    .ic-hub-bg__spark--5 {
        width: 0.65rem;
        height: 0.65rem;
        left: 88%;
        top: 62%;
        filter: blur(2px);
        animation-delay: -5.6s;
    }

    .ic-hub-bg__spark--6 {
        width: 0.32rem;
        height: 0.32rem;
        left: 8%;
        top: 74%;
        animation-delay: -3.3s;
    }

    .ic-hub-bg__spark--7 {
        width: 0.48rem;
        height: 0.48rem;
        left: 46%;
        top: 12%;
        animation-delay: -6.8s;
    }

    .ic-hub-bg__spark--8 {
        width: 0.38rem;
        height: 0.38rem;
        left: 52%;
        top: 78%;
        animation-delay: -7.5s;
    }

    .ic-hub-bg__spark--9 {
        width: 0.5rem;
        height: 0.5rem;
        left: 22%;
        top: 36%;
        animation-delay: -1.8s;
    }

    .ic-hub-bg__spark--10 {
        width: 0.3rem;
        height: 0.3rem;
        left: 70%;
        top: 68%;
        animation-delay: -4.6s;
    }

    .ic-hub-bg__spark--11 {
        width: 0.44rem;
        height: 0.44rem;
        left: 92%;
        top: 38%;
        animation-delay: -2.9s;
    }

    .ic-hub-bg__spark--12 {
        width: 0.36rem;
        height: 0.36rem;
        left: 4%;
        top: 48%;
        animation-delay: -5.1s;
    }

    .ic-hub-bg__spark--13 {
        width: 0.58rem;
        height: 0.58rem;
        left: 58%;
        top: 26%;
        filter: blur(2px);
        animation-delay: -8.2s;
    }

    .ic-hub-bg__spark--14 {
        width: 0.34rem;
        height: 0.34rem;
        left: 36%;
        top: 86%;
        animation-delay: -6.4s;
    }

    @keyframes ic-hub-sparkle-drift {
        0%,
        100% {
            opacity: 0.45;
            transform: translate3d(0, 0, 0) scale(1);
        }

        45% {
            opacity: 1;
            transform: translate3d(0, -4px, 0) scale(1.12);
        }

        70% {
            opacity: 0.62;
            transform: translate3d(2px, 2px, 0) scale(0.94);
        }
    }

    @keyframes ic-hub-glow-pulse {
        0%,
        100% {
            opacity: 0.34;
            transform: scale(1);
        }

        50% {
            opacity: 0.5;
            transform: scale(1.02);
        }
    }

    @keyframes ic-hub-sparkle-ambient {
        0%,
        100% {
            opacity: 0.75;
        }

        50% {
            opacity: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-hub-bg__spark,
        .ic-hub-bg__glow,
        .ic-hub-bg__sparkles::after {
            animation: none;
        }

        .ic-hub-bg__spark {
            opacity: 0.65;
        }

        .ic-hub-bg__glow {
            opacity: 0.42;
        }

        .ic-hub-bg__ribbon {
            animation: none;
        }
    }

    .ic-hub-bg__ribbons {
        position: absolute;
        inset: 0;
        z-index: 3;
        pointer-events: none;
        opacity: 0;
        mix-blend-mode: screen;
    }

    html:not(.ic-hub-theme-light) body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__ribbons {
        opacity: 1;
    }

    .ic-hub-bg__ribbon {
        position: absolute;
        border-radius: 9999px;
        filter: blur(56px);
        opacity: 0.55;
        animation: ic-hub-ribbon-drift 20s ease-in-out infinite;
    }

    .ic-hub-bg__ribbon--tr {
        width: min(58vw, 36rem);
        height: min(42vh, 22rem);
        right: -6%;
        top: -4%;
        background:
            linear-gradient(
                128deg,
                transparent 18%,
                rgba(196, 181, 253, 0.14) 38%,
                rgba(255, 255, 255, 0.12) 52%,
                rgba(186, 230, 253, 0.08) 62%,
                transparent 82%
            );
        transform: rotate(-18deg);
        animation-delay: 0s;
    }

    .ic-hub-bg__ribbon--bl {
        width: min(52vw, 32rem);
        height: min(38vh, 20rem);
        left: -8%;
        bottom: 2%;
        background:
            linear-gradient(
                42deg,
                transparent 22%,
                rgba(255, 255, 255, 0.1) 44%,
                rgba(226, 232, 240, 0.08) 58%,
                transparent 78%
            );
        transform: rotate(14deg);
        animation-delay: -6.5s;
    }

    .ic-hub-bg__ribbon--br {
        width: min(44vw, 28rem);
        height: min(34vh, 18rem);
        right: 4%;
        bottom: -6%;
        background:
            linear-gradient(
                315deg,
                transparent 20%,
                rgba(186, 230, 253, 0.11) 42%,
                rgba(255, 255, 255, 0.09) 56%,
                transparent 76%
            );
        transform: rotate(-8deg);
        animation-delay: -11s;
    }

    @keyframes ic-hub-ribbon-drift {
        0%,
        100% {
            opacity: 0.42;
            transform: rotate(var(--ic-hub-ribbon-rotate, 0deg)) translate3d(0, 0, 0);
        }

        50% {
            opacity: 0.58;
            transform: rotate(var(--ic-hub-ribbon-rotate, 0deg)) translate3d(6px, -4px, 0);
        }
    }

    .ic-hub-bg__ribbon--tr {
        --ic-hub-ribbon-rotate: -18deg;
    }

    .ic-hub-bg__ribbon--bl {
        --ic-hub-ribbon-rotate: 14deg;
    }

    .ic-hub-bg__ribbon--br {
        --ic-hub-ribbon-rotate: -8deg;
    }

    .ic-hub-bg__grid {
        position: absolute;
        inset: 0;
        opacity: 0.08;
        background-image:
            linear-gradient(rgba(34, 211, 238, 0.07) 1px, transparent 1px),
            linear-gradient(90deg, rgba(34, 211, 238, 0.07) 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, black, transparent);
        transition: opacity 1s ease;
    }

    .ic-hub-page {
        position: relative;
        z-index: 1;
        min-height: calc(100dvh - 4.5rem);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.25rem 1rem;
    }

    .ic-hub-page--modules {
        justify-content: flex-start;
        padding: 0;
    }

    /*
     * Módulos: ocupar el viewport y encoger cards solo si harían scroll.
     * Con pocos módulos, max-height 18.25rem mantiene el tamaño normal.
     */
    html:has(.ic-hub-page--modules) {
        height: 100%;
        background: #0f2233;
        transition: background-color 1s ease;
    }

    html.ic-hub-theme-light:has(.ic-hub-page--modules) {
        background: #F0F4F8;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) {
        --ic-modules-card-gap: clamp(0.75rem, 2vh, 1.25rem);
        --ic-hall-bg: transparent;
        height: 100dvh;
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        gap: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
        background: transparent;
        transition: background-color 1s ease;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-shell {
        margin-top: 0;
        padding-top: 0;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-topbar {
        margin-top: 0;
        padding-top: max(0.5rem, env(safe-area-inset-top, 0px));
        padding-bottom: clamp(0.65rem, 1.8vh, 1.25rem);
        padding-left: clamp(1.25rem, 4vw, 4.5rem);
        padding-right: clamp(1.25rem, 4vw, 4.5rem);
        border-bottom: 1px solid rgba(239, 233, 223, 0.08);
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

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall {
        flex: 1 1 auto;
        min-height: 0;
        margin: 0;
        padding: 0;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo {
        display: none;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__sparkles,
    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__ribbons,
    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__vignette,
    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__grid {
        opacity: 0;
        visibility: hidden;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__veil {
        background: transparent;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-page--modules {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer {
        flex-shrink: 0;
        padding: clamp(0.65rem, 1.8vh, 1.25rem) clamp(1.25rem, 4vw, 4.5rem);
        text-align: center;
        border-top: 1px solid rgba(239, 233, 223, 0.08);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer__text {
        font-size: 0.68rem;
        font-weight: 500;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: rgba(239, 233, 223, 0.4);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero {
        padding-bottom: 0;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__copy {
        max-width: none;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__greeting {
        text-wrap: nowrap;
        white-space: nowrap;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-main {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding-top: clamp(0.65rem, 1.8vh, 1.25rem);
        padding-bottom: clamp(0.65rem, 1.8vh, 1.25rem);
        padding-left: clamp(1rem, 4vw, 4.5rem);
        padding-right: clamp(1rem, 4vw, 4.5rem);
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-stage {
        flex: 0 0 auto;
        width: min(100%, 76rem);
        margin-top: 0;
        margin-bottom: 0;
    }

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-main .ic-modules-grid {
        flex: 0 0 auto;
    }

    .ic-hub-brand-mark {
        position: fixed;
        top: clamp(1rem, 3.2vw, 1.65rem);
        left: clamp(1rem, 3.2vw, 1.65rem);
        z-index: 5;
        display: block;
        line-height: 0;
        text-decoration: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    /* Login: el logo vive dentro del stack; ocultar el del layout */
    body.ic-hub-body:has(.ic-hub-page--login) > .ic-hub-brand-mark {
        display: none;
    }

    .ic-hub-brand-mark:hover {
        opacity: 0.92;
        transform: translateY(-1px);
    }

    .ic-hub-brand-mark__logo {
        display: none;
        width: auto;
        height: clamp(2.15rem, 5.5vw, 3rem);
        max-width: min(11.5rem, 46vw);
        object-fit: contain;
        filter: drop-shadow(0 6px 18px rgba(0, 0, 0, 0.35));
    }

    .ic-hub-brand-mark__logo--on-dark {
        display: block;
    }

    html.ic-hub-theme-light .ic-hub-brand-mark__logo--on-dark {
        display: none;
    }

    html.ic-hub-theme-light .ic-hub-brand-mark__logo--on-light {
        display: block;
    }

    .ic-hub-brand-mark--header-center,
    .ic-hub-brand-mark--login-center {
        position: static;
        transform: none;
    }

    .ic-hub-brand-mark--header-center:hover,
    .ic-hub-brand-mark--login-center:hover {
        transform: translateY(-1px);
    }

    .ic-hub-brand-mark--header-center .ic-hub-brand-mark__logo,
    .ic-hub-brand-mark--login-center .ic-hub-brand-mark__logo {
        height: clamp(3.1rem, 7vh, 4.75rem);
        max-width: min(18rem, 68vw);
        filter: drop-shadow(0 4px 14px rgba(0, 0, 0, 0.22));
    }

    html.ic-hub-theme-light .ic-hub-brand-mark--header-center .ic-hub-brand-mark__logo,
    html.ic-hub-theme-light .ic-hub-brand-mark--login-center .ic-hub-brand-mark__logo {
        filter: drop-shadow(0 2px 10px rgba(15, 23, 42, 0.12));
    }

    .ic-hub-footer {
        position: relative;
        z-index: 2;
        padding: 1.25rem 1rem 1.75rem;
        text-align: center;
    }

    .ic-hub-footer__text {
        margin: 0;
        font-size: clamp(0.72rem, 2.5vw, 0.88rem);
        font-weight: 400;
        letter-spacing: 0.02em;
        color: rgba(248, 250, 252, 0.62);
        line-height: 1.5;
    }

    .ic-hub-page--login {
        position: relative;
        isolation: isolate;
        justify-content: stretch;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    /*
     * Tres filas: zona superior | tarjeta | zona inferior.
     * El logo se centra en la fila superior → a mitad entre borde y contenedor.
     */
    .ic-login-stack {
        flex: 1 1 auto;
        align-self: stretch;
        width: 100%;
        min-height: 0;
        display: grid;
        grid-template-rows: 1fr auto 1fr;
        justify-items: center;
        position: relative;
        z-index: 1;
    }

    .ic-login-stack::after {
        content: '';
        grid-row: 3;
        pointer-events: none;
    }

    .ic-login-stack > .ic-hub-brand-mark--login-center {
        grid-row: 1;
        align-self: center;
        z-index: 2;
    }

    .ic-login-stack > .ic-login-glass {
        grid-row: 2;
    }

    .ic-login-orbs {
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }

    .ic-login-orbs__blob {
        position: absolute;
        border-radius: 9999px;
        filter: blur(48px);
        opacity: 0.55;
    }

    .ic-login-orbs__blob--1 {
        width: clamp(8rem, 28vw, 14rem);
        height: clamp(8rem, 28vw, 14rem);
        left: 12%;
        top: 18%;
        background: rgba(148, 163, 184, 0.45);
    }

    .ic-login-orbs__blob--2 {
        width: clamp(10rem, 32vw, 18rem);
        height: clamp(10rem, 32vw, 18rem);
        right: 8%;
        bottom: 22%;
        background: rgba(100, 116, 139, 0.5);
    }

    .ic-login-orbs__blob--3 {
        width: clamp(6rem, 18vw, 10rem);
        height: clamp(6rem, 18vw, 10rem);
        left: 42%;
        bottom: 8%;
        background: rgba(203, 213, 225, 0.35);
    }

    .ic-login-glass {
        position: relative;
        z-index: 1;
        width: min(100%, 31rem);
        padding: clamp(2rem, 5.5vw, 2.75rem);
        border-radius: 1.85rem;
        border: 1px solid rgba(255, 255, 255, 0.22);
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(14px) saturate(1.25);
        -webkit-backdrop-filter: blur(14px) saturate(1.25);
        box-shadow:
            0 24px 48px rgba(15, 23, 42, 0.35),
            inset 0 1px 0 rgba(255, 255, 255, 0.25);
        opacity: 0;
        animation: ic-login-glass-enter 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    .ic-hub-page--login .ic-login-orbs {
        opacity: 0;
        animation: ic-login-orbs-enter 1.1s ease-out forwards;
    }

    @keyframes ic-login-glass-enter {
        0% {
            opacity: 0;
            filter: blur(16px);
            transform: translateY(20px) scale(0.97);
            backdrop-filter: blur(4px) saturate(1.1);
            -webkit-backdrop-filter: blur(4px) saturate(1.1);
            background: rgba(255, 255, 255, 0.04);
        }

        55% {
            opacity: 0.85;
            filter: blur(6px);
            backdrop-filter: blur(10px) saturate(1.2);
            -webkit-backdrop-filter: blur(10px) saturate(1.2);
        }

        100% {
            opacity: 1;
            filter: blur(0);
            transform: translateY(0) scale(1);
            backdrop-filter: blur(14px) saturate(1.25);
            -webkit-backdrop-filter: blur(14px) saturate(1.25);
            background: rgba(255, 255, 255, 0.1);
        }
    }

    @keyframes ic-login-orbs-enter {
        from {
            opacity: 0;
            filter: blur(8px);
        }

        to {
            opacity: 1;
            filter: blur(0);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-login-glass,
        .ic-hub-page--login .ic-login-orbs {
            animation: none;
            opacity: 1;
            filter: none;
            transform: none;
        }
    }

    .ic-login-glass__head {
        text-align: center;
        margin-bottom: 1.75rem;
    }

    .ic-login-glass__title {
        margin: 0;
        font-size: clamp(1.85rem, 7.5vw, 2.65rem);
        font-weight: 700;
        letter-spacing: 0.04em;
        line-height: 1.08;
        background: linear-gradient(180deg, #ffffff 0%, #d1d5db 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        filter: drop-shadow(0 10px 8px rgb(0 0 0 / 0.04)) drop-shadow(0 4px 3px rgb(0 0 0 / 0.1));
    }

    .ic-login-glass__tagline {
        margin: 0.55rem 0 0;
        font-size: clamp(0.72rem, 2.6vw, 0.82rem);
        font-weight: 400;
        letter-spacing: 0.03em;
        text-transform: lowercase;
        color: rgba(255, 255, 255, 0.78);
        line-height: 1.45;
    }

    .ic-login-glass__subtitle {
        margin: 1.1rem 0 0;
        font-size: clamp(1rem, 3.5vw, 1.15rem);
        font-weight: 600;
        color: #fff;
        letter-spacing: 0.01em;
    }

    .ic-login-back {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 1.15rem;
        font-size: 0.78rem;
        font-weight: 500;
        color: rgba(186, 230, 253, 0.95);
        text-decoration: none;
        transition: opacity 0.2s ease;
    }

    .ic-login-back svg {
        width: 1rem;
        height: 1rem;
    }

    .ic-login-back:hover {
        opacity: 0.8;
        text-decoration: underline;
    }

    .ic-login-status {
        margin: 0 0 1rem;
        padding: 0.65rem 0.75rem;
        border-radius: 0.65rem;
        font-size: 0.8rem;
        line-height: 1.45;
        color: rgba(240, 253, 250, 0.95);
        background: rgba(34, 211, 238, 0.12);
        border: 1px solid rgba(34, 211, 238, 0.28);
    }

    .ic-login-form {
        display: grid;
        gap: 1.35rem;
    }

    .ic-login-field {
        padding-top: 0.35rem;
    }

    .ic-login-input-wrap {
        position: relative;
        display: flex;
        align-items: flex-end;
        border-bottom: 1px solid rgba(255, 255, 255, 0.55);
        padding-top: 1.15rem;
        padding-bottom: 0.45rem;
        min-height: 2.85rem;
        transition: border-color 0.25s ease;
    }

    .ic-login-float-label {
        position: absolute;
        left: 0;
        bottom: 0.5rem;
        max-width: calc(100% - 2rem);
        font-size: 0.92rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.5);
        pointer-events: none;
        transform-origin: left bottom;
        transition:
            transform 0.25s cubic-bezier(0.4, 0, 0.2, 1),
            bottom 0.25s cubic-bezier(0.4, 0, 0.2, 1),
            font-size 0.25s cubic-bezier(0.4, 0, 0.2, 1),
            color 0.25s ease;
    }

    .ic-login-input:focus ~ .ic-login-float-label,
    .ic-login-input:not(:placeholder-shown) ~ .ic-login-float-label,
    .ic-login-input:-webkit-autofill ~ .ic-login-float-label {
        bottom: calc(100% - 0.15rem);
        transform: scale(0.86);
        font-size: 0.78rem;
        color: rgba(255, 255, 255, 0.88);
    }

    .ic-login-input-wrap:focus-within .ic-login-float-label {
        color: rgba(255, 255, 255, 0.88);
    }

    .ic-login-input-wrap:focus-within {
        border-bottom-color: #fff;
    }

    .ic-login-input-wrap--password .ic-login-input {
        padding-right: 4rem;
    }

    .ic-login-input-wrap--password .ic-login-float-label {
        max-width: calc(100% - 4rem);
    }

    .ic-login-input-wrap--password .ic-login-input__actions {
        position: absolute;
        right: 0;
        bottom: 0.42rem;
        display: flex;
        align-items: center;
        gap: 0.2rem;
        height: 1.25rem;
    }

    .ic-login-input-wrap--password .ic-login-input__icon--lock {
        position: static;
        flex-shrink: 0;
    }

    .ic-login-input {
        flex: 1;
        width: 100%;
        border: none;
        background: transparent;
        background-color: transparent;
        padding: 0.15rem 2rem 0.15rem 0;
        font-size: 0.92rem;
        color: #fff;
        outline: none;
        box-shadow: none;
        -webkit-appearance: none;
        appearance: none;
    }

    .ic-login-input:focus {
        outline: none;
        box-shadow: none;
    }

    .ic-login-input:-webkit-autofill,
    .ic-login-input:-webkit-autofill:hover,
    .ic-login-input:-webkit-autofill:focus,
    .ic-login-input:-webkit-autofill:active {
        -webkit-text-fill-color: #fff;
        caret-color: #fff;
        border: none;
        box-shadow: 0 0 0 1000px transparent inset;
        transition: background-color 999999s ease-out 0s;
    }

    .ic-login-input::placeholder {
        color: rgba(255, 255, 255, 0.35);
    }

    .ic-login-input__icon {
        position: absolute;
        right: 0;
        bottom: 0.5rem;
        width: 1.15rem;
        height: 1.15rem;
        color: rgba(255, 255, 255, 0.85);
        pointer-events: none;
    }

    .ic-login-input__toggle {
        position: static;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        padding: 0;
        border: none;
        border-radius: 9999px;
        background: transparent;
        color: rgba(255, 255, 255, 0.82);
        cursor: pointer;
        flex-shrink: 0;
        transition: color 0.2s ease, background 0.2s ease;
    }

    .ic-login-input__toggle svg {
        display: block;
        width: 1.15rem;
        height: 1.15rem;
    }

    .ic-login-input__toggle:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.08);
    }

    .ic-login-input__toggle:focus-visible {
        outline: 2px solid rgba(34, 211, 238, 0.55);
        outline-offset: 2px;
    }

    .ic-login-error {
        margin: 0.4rem 0 0;
        font-size: 0.76rem;
        color: #fecaca;
    }

    .ic-login-options {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem 1rem;
        margin-top: -0.25rem;
    }

    .ic-login-remember {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.76rem;
        color: rgba(255, 255, 255, 0.82);
        cursor: pointer;
        user-select: none;
    }

    .ic-login-remember input {
        width: 0.9rem;
        height: 0.9rem;
        accent-color: #fff;
        cursor: pointer;
    }

    .ic-login-forgot {
        font-size: 0.76rem;
        color: rgba(255, 255, 255, 0.88);
        text-decoration: none;
        transition: opacity 0.2s ease;
    }

    .ic-login-forgot:hover {
        opacity: 0.75;
        text-decoration: underline;
    }

    .ic-login-submit {
        margin-top: 0.35rem;
        width: 100%;
        border: none;
        border-radius: 9999px;
        padding: 0.85rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #0f172a;
        background: #fff;
        cursor: pointer;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.28);
        transition:
            transform 0.22s cubic-bezier(0.4, 0, 0.2, 1),
            box-shadow 0.22s ease,
            background 0.22s ease,
            opacity 0.15s ease;
    }

    .ic-login-submit:hover:not(:disabled) {
        transform: translateY(-2px);
        background: #f8fafc;
        box-shadow:
            0 16px 36px rgba(15, 23, 42, 0.38),
            0 0 0 1px rgba(255, 255, 255, 0.55),
            0 0 28px rgba(34, 211, 238, 0.22);
    }

    .ic-login-submit:active:not(:disabled),
    .ic-login-submit.ic-login-submit--pressed:not(:disabled) {
        transform: translateY(4px) scale(0.982);
        background: #e2e8f0;
        box-shadow:
            0 3px 10px rgba(15, 23, 42, 0.22),
            inset 0 4px 12px rgba(15, 23, 42, 0.14);
        transition-duration: 0.1s;
    }

    .ic-login-submit:disabled {
        opacity: 0.65;
        cursor: wait;
    }

    .ic-alert {
        padding: 0.65rem 0.75rem;
        border-radius: 0.65rem;
        font-size: 0.82rem;
        line-height: 1.45;
        border: 1px solid rgba(248, 113, 113, 0.35);
        background: rgba(127, 29, 29, 0.35);
        color: #fecaca;
    }

    .ic-hall {
        --ic-hall-accent: #d8b878;
        --ic-hall-ink: #efe9df;
        --ic-hall-radius: 10px;
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

    body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 2;
        pointer-events: none;
        background: linear-gradient(
            180deg,
            rgba(216, 184, 120, 0.26) 0%,
            rgba(216, 184, 120, 0.1) 10%,
            rgba(216, 184, 120, 0.03) 24%,
            transparent min(38vh, 21rem)
        );
    }

    .ic-hall-shell > * {
        position: relative;
        z-index: 1;
    }

    .ic-hall-topbar {
        position: relative;
        z-index: 3;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
        padding: clamp(0.85rem, 2.8vh, 1.85rem) clamp(1.25rem, 4vw, 4.5rem);
        border-top: none;
        border-bottom: 1px solid rgba(239, 233, 223, 0.08);
        box-shadow: none;
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
        filter: drop-shadow(0 4px 14px rgba(0, 0, 0, 0.28));
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark {
        filter: drop-shadow(0 4px 14px rgba(0, 0, 0, 0.28));
    }

    .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-dark-gold {
        display: none;
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar.ic-hub-brand-mark--dark-logo-gold .ic-hub-brand-mark__logo--on-dark {
        display: none;
    }

    html:not(.ic-hub-theme-light) .ic-hub-brand-mark--header-bar.ic-hub-brand-mark--dark-logo-gold .ic-hub-brand-mark__logo--on-dark-gold {
        display: block;
        filter: drop-shadow(0 4px 18px rgba(0, 0, 0, 0.42));
    }

    html.ic-hub-theme-light .ic-hub-brand-mark--header-bar .ic-hub-brand-mark__logo--on-light {
        filter: drop-shadow(0 2px 10px rgba(154, 123, 34, 0.22));
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
        text-align: center;
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
        border: 1px solid var(--ic-hub-logo-gold);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 1.05rem;
        color: var(--ic-hub-logo-gold);
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
        border: 1px solid var(--ic-hub-logo-gold);
        background: transparent;
        color: var(--ic-hub-logo-gold);
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
        border-color: var(--ic-hub-logo-gold-hover);
        color: var(--ic-hub-logo-gold-hover);
    }

    .ic-hall-hero {
        flex-shrink: 0;
        padding: clamp(1rem, 4vh, 3.5rem) clamp(1.25rem, 4vw, 4.5rem) clamp(0.75rem, 2vh, 1.75rem);
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
        color: var(--ic-hub-logo-gold);
    }

    .ic-hall-hero__lead {
        margin: 0;
        max-width: 28rem;
        font-size: 0.94rem;
        line-height: 1.6;
        color: rgba(239, 233, 223, 0.62);
    }

    .ic-hall-main {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        align-items: stretch;
        justify-content: center;
        padding: 0 clamp(1rem, 4vw, 4.5rem) clamp(0.85rem, 2.5vh, 1.75rem);
    }

    .ic-hall-stage {
        width: min(100%, 76rem);
        margin-inline: auto;
        min-height: 0;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }

    .ic-modules-grid {
        --ic-modules-gap: 0.65rem;
        flex: 1 1 auto;
        min-height: 0;
        display: grid;
        grid-template-columns: 1fr;
        grid-auto-rows: auto;
        align-content: center;
        justify-items: stretch;
        gap: var(--ic-modules-gap);
    }

    .ic-hall-accordion {
        display: flex;
        flex-direction: row;
        align-items: stretch;
        height: clamp(14rem, min(42vh, 52vh), 26rem);
        min-height: clamp(14rem, 42vh, 26rem);
        max-height: min(52vh, 26rem);
    }

    @media (min-width: 900px) {
        .ic-hall-accordion > .ic-module-card--hall {
            align-self: stretch;
            height: auto;
            min-height: 100%;
        }
    }

    @media (max-width: 899px) {
        .ic-hall-accordion {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            min-height: auto;
            max-height: none;
        }

        .ic-hall-accordion[data-count="1"] {
            grid-template-columns: 1fr;
        }
    }

    @media (min-width: 900px) {
        /*
         * Acordeón: tamaño fijo como fila de 8 (spotlight 5u + collapsed 1u × 7).
         * Con menos módulos, el ancho total encoge y el bloque queda centrado.
         */
        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-accordion {
            --ic-modules-gap: 0.65rem;
            /* Referencia fila de 8: (76rem − 7 gaps) ÷ 12 unidades flex (5 spotlight + 7×1) */
            --ic-hall-accordion-collapsed-w: calc((76rem - 7 * var(--ic-modules-gap)) / 12);
            --ic-hall-accordion-spotlight-w: calc(var(--ic-hall-accordion-collapsed-w) * 5);
            width: fit-content;
            max-width: 100%;
            margin-inline: auto;
            justify-content: center;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall {
            flex: 0 0 auto;
            width: var(--ic-hall-accordion-collapsed-w, 5.95rem);
            max-width: none;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall.ic-module-card--spotlight {
            flex: 0 0 auto;
            width: var(--ic-hall-accordion-spotlight-w, 29.75rem);
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall.ic-module-card--hall-collapsed {
            flex: 0 0 auto;
            width: var(--ic-hall-accordion-collapsed-w, 5.95rem);
        }
    }

    .ic-module-card {
        --ic-module-card-ink: #efe9df;
        --ic-module-card-muted: rgba(239, 233, 223, 0.78);
        --ic-module-card-subtle: rgba(239, 233, 223, 0.6);
        --ic-module-card-max-h: 18.25rem;
        position: relative;
        display: block;
        box-sizing: border-box;
        width: 100%;
        min-height: clamp(12rem, 36vh, 22rem);
        min-width: 0;
        border-radius: var(--ic-hall-radius);
        overflow: hidden;
        overflow: clip;
        clip-path: inset(0 round var(--ic-hall-radius));
        isolation: isolate;
        contain: paint;
        border: none;
        outline: none;
        text-decoration: none;
        color: var(--ic-module-card-ink);
        background: #0b1a28;
        flex: 1 1 0;
        transform: translate3d(0, 0, 0);
        transition:
            width 0.8s cubic-bezier(0.2, 0.8, 0.2, 1),
            flex 0.8s cubic-bezier(0.2, 0.8, 0.2, 1),
            transform 0.22s cubic-bezier(0.4, 0, 0.2, 1),
            box-shadow 0.22s ease,
            filter 0.22s ease;
        box-shadow: none;
        -webkit-tap-highlight-color: transparent;
    }

    .ic-module-card--hall.ic-module-card--spotlight {
        flex: 5 1 0;
    }

    .ic-module-card--hall.ic-module-card--hall-collapsed {
        flex: 1 1 0;
    }

    .ic-module-card.ic-module-card--enter {
        opacity: 0;
        animation: ic-module-card-enter 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        animation-delay: var(--ic-module-enter-delay, 0ms);
    }

    @keyframes ic-module-card-enter {
        from {
            opacity: 0;
            transform: translateY(1.35rem) scale(0.96);
            filter: blur(4px);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-module-card {
            transition: none;
        }

        .ic-module-card.ic-module-card--enter {
            opacity: 1;
            animation: none;
            filter: none;
            transform: none;
        }
    }

    .ic-module-card::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 4;
        border-radius: inherit;
        pointer-events: none;
        opacity: 0;
        box-shadow: inset 0 4px 12px rgba(15, 23, 42, 0.14);
        transition: opacity 0.1s ease;
    }

    .ic-module-card--hall:is(:hover, :focus-visible, .ic-module-card--spotlight):not(.ic-module-card--pressed) {
        box-shadow:
            0 10px 24px rgba(15, 23, 42, 0.12),
            0 2px 8px rgba(15, 23, 42, 0.08);
    }

    .ic-module-card--hall:active,
    .ic-module-card--hall.ic-module-card--pressed {
        transform: scale(0.982);
        filter: brightness(0.92);
        box-shadow:
            0 3px 10px rgba(15, 23, 42, 0.28),
            inset 0 4px 12px rgba(15, 23, 42, 0.18);
        transition-duration: 0.1s;
    }

    .ic-module-card--hall:active::after,
    .ic-module-card--hall.ic-module-card--pressed::after {
        opacity: 1;
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
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        pointer-events: none;
    }

    .ic-module-card__photo {
        position: absolute;
        inset: -3px;
        z-index: 0;
        width: calc(100% + 6px);
        height: calc(100% + 6px);
        max-width: none;
        border-radius: 0;
        object-fit: cover;
        object-position: center center;
        transform: translate3d(0, 0, 0) scale(1.04);
        transform-origin: center center;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        filter: grayscale(1) brightness(0.55) contrast(1.1);
        transition: transform 1.4s cubic-bezier(0.2, 0.8, 0.2, 1), filter 0.8s ease;
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
            rgba(10, 24, 37, 0.55) 0%,
            rgba(10, 24, 37, 0.08) 38%,
            rgba(10, 24, 37, 0.88) 100%
        );
    }

    .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__veil {
        background: linear-gradient(
            180deg,
            rgba(10, 24, 37, 0.12) 0%,
            transparent 42%,
            rgba(10, 24, 37, 0.9) 100%
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
        font-size: clamp(0.95rem, 2.4vh, 1.65rem);
        letter-spacing: 0.01em;
        white-space: nowrap;
        color: var(--ic-module-card-ink);
        pointer-events: none;
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
        transition: opacity 0.45s ease;
    }

    .ic-module-card--hall.ic-module-card--spotlight .ic-module-card__panel {
        opacity: 1;
    }

    .ic-module-card__panel-content {
        --ic-module-title-air: 0.7rem;
        position: relative;
        z-index: 3;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: clamp(0.35rem, 1vh, 0.65rem);
        padding: clamp(0.85rem, 2.6vh, 1.75rem);
        text-align: left;
        transform: translateY(0.55rem);
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
        width: fit-content;
        max-width: 100%;
        padding: 0.38em 0.9em;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.32em;
        text-transform: uppercase;
        line-height: 1.2;
        color: #000;
        background: var(--ic-hall-accent);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
    }

    .ic-module-card__objective {
        margin: 0;
        max-width: 22.5rem;
        font-size: 0.88rem;
        line-height: 1.6;
        color: var(--ic-module-card-muted);
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
    }

    .ic-module-card__footer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        padding-top: clamp(0.5rem, 1.4vh, 1rem);
        border-top: none;
        flex-wrap: wrap;
    }

    .ic-module-card__tags {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 0;
        padding: 0;
        list-style: none;
        flex: 1 1 auto;
        font-size: 0.68rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--ic-module-card-subtle);
    }

    .ic-module-card__tag {
        padding: 0;
        border: none;
        background: transparent;
        font-size: inherit;
        font-weight: 500;
        line-height: 1.25;
        color: inherit;
    }

    .ic-module-card__name {
        margin: 0;
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: clamp(1.65rem, min(3.6vw, 5.5vh), 3.75rem);
        font-weight: 400;
        line-height: 1;
        letter-spacing: -0.01em;
        text-transform: none;
        color: var(--ic-module-card-ink);
    }

    .ic-module-card__enter {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        flex-shrink: 0;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.24em;
        text-transform: uppercase;
        color: var(--ic-hall-accent);
    }

    .ic-module-card__enter-line {
        display: block;
        width: 1.75rem;
        height: 1px;
        background: var(--ic-hall-accent);
    }

    @media (max-width: 899px) {
        .ic-hall-topbar {
            grid-template-columns: 1fr;
            justify-items: center;
        }

        .ic-hall-topbar__brand {
            grid-column: 1;
            justify-self: center;
        }

        .ic-hall-topbar__clock {
            grid-column: 1;
            justify-self: center;
        }

        .ic-hall-topbar__actions {
            grid-column: 1;
            width: 100%;
            justify-content: center;
            justify-self: stretch;
        }

        .ic-module-card--hall {
            flex: initial;
            min-height: auto;
            aspect-ratio: 1 / 1;
            max-height: var(--ic-module-card-max-h);
        }

        .ic-module-card--hall .ic-module-card__title-vertical {
            display: none;
        }

        .ic-module-card--hall .ic-module-card__panel {
            opacity: 1;
        }

        .ic-module-card--hall .ic-module-card__panel-content {
            opacity: 1;
            transform: translateY(0);
            align-items: center;
            text-align: center;
        }

        .ic-module-card--hall .ic-module-card__photo {
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
        }

        .ic-module-card--hall .ic-module-card__enter {
            display: none;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero {
            display: block;
            flex-shrink: 0;
            padding: 0.65rem 1.25rem 0.35rem;
            text-align: center;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__copy {
            max-width: none;
            margin-inline: auto;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__greeting {
            margin: 0;
            font-size: clamp(1.65rem, 7.5vw, 2.25rem);
            line-height: 1.05;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__lead {
            display: none;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-topbar {
            padding-top: max(0.45rem, env(safe-area-inset-top, 0px));
            padding-bottom: 0.55rem;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-main {
            padding-top: 0;
            padding-bottom: 0;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall .ic-module-card__badge,
        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall .ic-module-card__objective,
        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall .ic-module-card__footer-row {
            display: none;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall .ic-module-card__panel-content {
            justify-content: flex-end;
            align-items: center;
            gap: 0;
            padding: 0.55rem 0.45rem 0.7rem;
            min-height: 100%;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall .ic-module-card__name {
            font-size: clamp(0.72rem, 3.1vw, 0.88rem);
            font-weight: 700;
            letter-spacing: 0.05em;
            line-height: 1.15;
            text-transform: uppercase;
            text-align: center;
            text-wrap: balance;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-accordion {
            --ic-modules-gap: 0.55rem;
            height: auto;
            min-height: 0;
            max-height: none;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall {
            max-height: none;
            aspect-ratio: 4 / 5;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) {
            height: auto;
            min-height: 100dvh;
            overflow-x: hidden;
            overflow-y: auto;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-page--modules {
            overflow: visible;
        }

        body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer {
            margin-top: auto;
        }
    }

    .ic-empty {
        border-radius: 1rem;
        border: 1px dashed rgba(255, 255, 255, 0.2);
        padding: 2rem 1.25rem;
        text-align: center;
        color: rgba(248, 250, 252, 0.7);
        font-size: 0.9rem;
        background: rgba(0, 0, 0, 0.25);
        backdrop-filter: blur(10px);
    }

    .ic-login-glass:has(.ic-hub-theme-toggle-wrap) {
        padding-bottom: clamp(4rem, 9vw, 4.5rem);
    }

    .ic-login-glass:has(.ic-hub-theme-toggle-wrap) .ic-login-submit {
        margin-bottom: 1.15rem;
    }

    .ic-hub-theme-toggle-wrap {
        position: absolute;
        bottom: clamp(0.85rem, 3vw, 1.15rem);
        right: clamp(0.85rem, 3vw, 1.15rem);
        top: auto;
        z-index: 6;
    }

    .ic-hub-theme-switch {
        display: block;
        padding: 0;
        margin: 0;
        border: none;
        background: transparent;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }

    .ic-hub-theme-switch:focus-visible .ic-hub-theme-switch__track {
        outline: 2px solid rgba(186, 230, 253, 0.95);
        outline-offset: 3px;
    }

    .ic-hub-theme-switch__track {
        position: relative;
        display: block;
        width: 6rem;
        height: 2.45rem;
        border-radius: 9999px;
        background: rgba(72, 68, 88, 0.72);
        box-shadow:
            0 10px 28px rgba(15, 23, 42, 0.38),
            inset 0 2px 8px rgba(0, 0, 0, 0.35),
            inset 0 -1px 0 rgba(255, 255, 255, 0.12);
        transition: background 0.35s ease, box-shadow 0.35s ease;
    }

    .ic-hub-theme-switch__label {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: rgba(255, 255, 255, 0.92);
        pointer-events: none;
        z-index: 1;
        opacity: 0;
        transition: opacity 0.28s ease;
    }

    .ic-hub-theme-switch__label.is-visible {
        opacity: 1;
    }

    .ic-hub-theme-switch__label--dark {
        left: 0.72rem;
    }

    .ic-hub-theme-switch__label--light {
        right: 0.62rem;
    }

    .ic-hub-theme-switch__thumb {
        --ic-hub-switch-travel: calc(6rem - 2.05rem - 0.28rem);
        position: absolute;
        top: 50%;
        left: 0.14rem;
        width: 2.05rem;
        height: 2.05rem;
        transform: translateY(-50%) translateX(0);
        border-radius: 9999px;
        z-index: 2;
        transition: transform 0.58s cubic-bezier(0.34, 1.55, 0.48, 1);
    }

    .ic-hub-theme-switch__thumb.is-dark {
        transform: translateY(-50%) translateX(var(--ic-hub-switch-travel));
    }

    .ic-hub-theme-switch__thumb-inner {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        transform-origin: center center;
    }

    .ic-hub-theme-switch__thumb-inner.is-jelly {
        animation: ic-hub-switch-jelly 0.68s cubic-bezier(0.34, 1.4, 0.48, 1) both;
    }

    .ic-hub-theme-switch__track.is-jelly {
        animation: ic-hub-switch-track-jelly 0.55s cubic-bezier(0.34, 1.25, 0.48, 1) both;
    }

    @keyframes ic-hub-switch-jelly {
        0% {
            transform: scale(1, 1);
        }

        18% {
            transform: scale(1.14, 0.86);
        }

        36% {
            transform: scale(0.9, 1.12);
        }

        52% {
            transform: scale(1.06, 0.94);
        }

        68% {
            transform: scale(0.96, 1.04);
        }

        84% {
            transform: scale(1.02, 0.98);
        }

        100% {
            transform: scale(1, 1);
        }
    }

    @keyframes ic-hub-switch-track-jelly {
        0% {
            transform: scale(1, 1);
        }

        30% {
            transform: scale(1.04, 0.96);
        }

        55% {
            transform: scale(0.98, 1.02);
        }

        100% {
            transform: scale(1, 1);
        }
    }

    .ic-hub-theme-switch__thumb-glass {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(
            155deg,
            rgba(255, 255, 255, 0.72) 0%,
            rgba(255, 255, 255, 0.18) 42%,
            rgba(255, 255, 255, 0.45) 100%
        );
        backdrop-filter: blur(14px) saturate(1.65);
        -webkit-backdrop-filter: blur(14px) saturate(1.65);
        border: 1px solid rgba(255, 255, 255, 0.58);
        box-shadow:
            0 0 22px rgba(255, 255, 255, 0.35),
            0 8px 22px rgba(15, 23, 42, 0.32),
            inset 0 2px 5px rgba(255, 255, 255, 0.95),
            inset 0 -4px 10px rgba(15, 23, 42, 0.12);
    }

    .ic-hub-theme-switch__thumb-shine {
        position: absolute;
        inset: 0.35rem 0.45rem auto;
        height: 38%;
        border-radius: 9999px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.85), transparent);
        opacity: 0.75;
        pointer-events: none;
        z-index: 1;
    }

    .ic-hub-theme-switch__icon {
        position: absolute;
        top: 50%;
        left: 50%;
        z-index: 3;
        width: 1rem;
        height: 1rem;
        transform: translate(-50%, -50%);
        color: rgba(255, 255, 255, 0.95);
        filter: drop-shadow(0 1px 2px rgba(15, 23, 42, 0.35));
    }

    .ic-hub-theme-switch:hover .ic-hub-theme-switch__thumb-glass {
        box-shadow:
            0 0 26px rgba(255, 255, 255, 0.42),
            0 10px 26px rgba(15, 23, 42, 0.36),
            inset 0 2px 5px rgba(255, 255, 255, 0.95),
            inset 0 -4px 10px rgba(15, 23, 42, 0.12);
    }

    .ic-hub-theme-switch:active .ic-hub-theme-switch__thumb-inner {
        transform: scale(0.94);
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-hub-theme-switch__thumb {
            transition-duration: 0.01ms;
        }

        .ic-hub-theme-switch__thumb-inner.is-jelly,
        .ic-hub-theme-switch__track.is-jelly {
            animation: none;
        }
    }

    html.ic-hub-theme-switching,
    html.ic-hub-theme-switching .ic-hub-body,
    html.ic-hub-theme-switching .ic-login-glass,
    html.ic-hub-theme-switching .ic-hub-bg__veil,
    html.ic-hub-theme-switching .ic-hub-bg__vignette,
    html.ic-hub-theme-switching .ic-hub-bg__grid,
    html.ic-hub-theme-switching .ic-hub-bg__photo,
    html.ic-hub-theme-switching .ic-hub-bg::after {
        transition:
            background-color 1s ease,
            background 1s ease,
            filter 1s ease,
            opacity 1s ease,
            box-shadow 1s ease;
    }

    @media (prefers-reduced-motion: reduce) {
        .ic-hub-body,
        html:has(.ic-hub-page--modules),
        .ic-hub-bg__photo,
        .ic-hub-bg__veil,
        .ic-hub-bg__vignette,
        .ic-hub-bg__grid {
            transition-duration: 0.01ms !important;
        }
    }

    html.ic-hub-theme-light .ic-hub-body {
        color: #0f172a;
        color-scheme: light;
        background: #e8f2fb;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) {
        background: #F0F4F8;
        color-scheme: light;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg {
        background: #F0F4F8;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__greeting {
        color: #0B132B;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__name {
        color: var(--ic-hub-logo-gold-muted);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__lead {
        color: #64748B;
    }

    html.ic-hub-theme-light .ic-hub-bg__veil {
        background: rgba(255, 255, 255, 0.78);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__veil {
        background: transparent;
    }

    html.ic-hub-theme-switching:has(.ic-hub-page--modules) {
        background-color: #0f2233;
    }

    html.ic-hub-theme-switching.ic-hub-theme-light:has(.ic-hub-page--modules) {
        background-color: #F0F4F8;
    }

    html.ic-hub-theme-light .ic-hub-bg__vignette,
    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__vignette {
        opacity: 0;
        box-shadow: none;
        background: none;
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__sparkles,
    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__ribbons {
        opacity: 0;
    }

    html.ic-hub-theme-light .ic-hub-bg__grid {
        opacity: 0.04;
        background-image:
            linear-gradient(rgba(5, 47, 96, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(5, 47, 96, 0.05) 1px, transparent 1px);
    }

    html.ic-hub-theme-light .ic-hub-footer__text {
        color: rgba(15, 23, 42, 0.62);
    }

    html.ic-hub-theme-light .ic-login-glass {
        border-color: rgba(255, 255, 255, 0.85);
        background: rgba(255, 255, 255, 0.52);
        box-shadow:
            0 24px 48px rgba(15, 23, 42, 0.12),
            inset 0 1px 0 rgba(255, 255, 255, 0.95);
    }

    html.ic-hub-theme-light .ic-login-glass__title {
        background: linear-gradient(180deg, #0f172a 0%, #475569 100%);
        -webkit-background-clip: text;
        background-clip: text;
        filter: drop-shadow(0 4px 12px rgba(15, 23, 42, 0.08));
    }

    html.ic-hub-theme-light .ic-login-glass__tagline,
    html.ic-hub-theme-light .ic-login-glass__subtitle {
        color: rgba(15, 23, 42, 0.72);
    }

    html.ic-hub-theme-light .ic-login-float-label,
    html.ic-hub-theme-light .ic-login-field label {
        color: rgba(15, 23, 42, 0.55);
    }

    html.ic-hub-theme-light .ic-login-input:focus ~ .ic-login-float-label,
    html.ic-hub-theme-light .ic-login-input:not(:placeholder-shown) ~ .ic-login-float-label,
    html.ic-hub-theme-light .ic-login-input:-webkit-autofill ~ .ic-login-float-label {
        color: rgba(15, 23, 42, 0.82);
    }

    html.ic-hub-theme-light .ic-login-input-wrap {
        border-bottom-color: rgba(15, 23, 42, 0.45);
    }

    html.ic-hub-theme-light .ic-login-input-wrap:focus-within {
        border-bottom-color: #0f172a;
    }

    html.ic-hub-theme-light .ic-login-input {
        color: #0f172a;
    }

    html.ic-hub-theme-light .ic-login-input:-webkit-autofill,
    html.ic-hub-theme-light .ic-login-input:-webkit-autofill:hover,
    html.ic-hub-theme-light .ic-login-input:-webkit-autofill:focus,
    html.ic-hub-theme-light .ic-login-input:-webkit-autofill:active {
        -webkit-text-fill-color: #0f172a;
        caret-color: #0f172a;
    }

    html.ic-hub-theme-light .ic-login-input__icon,
    html.ic-hub-theme-light .ic-login-input__toggle {
        color: rgba(15, 23, 42, 0.72);
    }

    html.ic-hub-theme-light .ic-login-remember,
    html.ic-hub-theme-light .ic-login-forgot,
    html.ic-hub-theme-light .ic-login-back {
        color: rgba(15, 23, 42, 0.78);
    }

    html.ic-hub-theme-light .ic-login-forgot:hover,
    html.ic-hub-theme-light .ic-login-back:hover {
        color: #0f172a;
    }

    html.ic-hub-theme-light .ic-hub-theme-switch__track {
        background: rgba(186, 180, 210, 0.78);
        box-shadow:
            0 10px 24px rgba(15, 23, 42, 0.14),
            inset 0 2px 7px rgba(15, 23, 42, 0.12),
            inset 0 -1px 0 rgba(255, 255, 255, 0.65);
    }

    html.ic-hub-theme-light .ic-hub-theme-switch__label {
        color: rgba(255, 255, 255, 0.98);
        text-shadow: 0 1px 2px rgba(15, 23, 42, 0.15);
    }

    html.ic-hub-theme-light .ic-hub-theme-switch__icon {
        color: rgba(15, 23, 42, 0.82);
        filter: drop-shadow(0 1px 1px rgba(255, 255, 255, 0.6));
    }

    html.ic-hub-theme-light .ic-hall {
        --ic-hall-ink: #1a2838;
    }

    html.ic-hub-theme-light .ic-hall-topbar {
        border-bottom-color: rgba(15, 23, 42, 0.08);
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
        color: var(--ic-hub-logo-gold-muted);
        border-color: var(--ic-hub-logo-gold-muted);
    }

    html.ic-hub-theme-light .ic-hall-btn-logout:hover {
        color: var(--ic-hub-logo-gold);
        border-color: var(--ic-hub-logo-gold);
    }

    html.ic-hub-theme-light .ic-hall-hero__greeting {
        color: rgba(15, 23, 42, 0.92);
    }

    html.ic-hub-theme-light .ic-hall-hero__name {
        color: var(--ic-hub-logo-gold-muted);
    }

    html.ic-hub-theme-light .ic-hall-hero__lead {
        color: rgba(15, 23, 42, 0.62);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer {
        border-top-color: rgba(15, 23, 42, 0.08);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-topbar {
        border-bottom-color: rgba(15, 23, 42, 0.08);
    }

    html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer__text {
        color: rgba(15, 23, 42, 0.45);
    }

    html.ic-hub-theme-light .ic-hall-topbar {
        border-color: rgba(15, 23, 42, 0.08);
    }

</style>

<style id="presentation-app-chrome">
    .presentation-app-header {
        padding: 0.55rem 0.85rem;
    }

    .presentation-app-header__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem;
    }

    .presentation-app-header__brand {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
    }

    .presentation-app-header__actions {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .presentation-app-header .logo-chip {
        width: auto;
        min-width: 0;
        height: 2.35rem;
        padding: 0.3rem 0.65rem;
    }

    .presentation-app-header .logo-chip--mark {
        text-decoration: none;
        color: inherit;
    }

    .presentation-app-header__brand-text {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: var(--navy);
    }

    .presentation-app-header__icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-width: 2.35rem;
        height: 2.35rem;
        padding: 0 0.7rem;
        font-size: 0.72rem;
        font-weight: 600;
    }

    .presentation-app-header__counter {
        min-width: 2.75rem;
        text-align: right;
        font-size: 0.8rem;
        font-weight: 700;
        color: color-mix(in srgb, var(--navy) 70%, transparent);
    }

    .presentation-session {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        max-width: 15rem;
        min-height: 2.35rem;
        padding: 0.2rem 0.3rem 0.2rem 0.65rem;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.75);
        background: rgba(255, 255, 255, 0.55);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 4px 12px rgba(20, 33, 61, 0.05);
    }

    .presentation-session__label {
        font-size: 0.58rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--navy) 48%, transparent);
        line-height: 1;
    }

    .presentation-session__name {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--navy);
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 9.5rem;
    }

    .presentation-session__time {
        display: none;
        font-size: 0.58rem;
        color: color-mix(in srgb, var(--navy) 48%, transparent);
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }

    .presentation-session__logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        min-width: 2.1rem;
        height: 1.95rem;
        padding: 0 0.55rem;
        border-radius: 9999px;
        border: 1px solid color-mix(in srgb, var(--navy) 14%, white);
        background: rgba(255, 255, 255, 0.72);
        color: var(--navy);
        text-decoration: none;
        font-size: 0.68rem;
        font-weight: 700;
    }

    .presentation-session__logout-icon {
        display: none;
    }

    .presentation-nav-desktop {
        display: none;
    }

    .presentation-swipe-hint {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: color-mix(in srgb, var(--navy) 48%, transparent);
    }

    .presentation-footer-mobile {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        align-items: center;
    }

    #slides-container {
        touch-action: pan-y;
    }

    @media (max-width: 767px) {
        .presentation-app-header {
            padding: 0.45rem 0.7rem;
            padding-top: max(0.45rem, env(safe-area-inset-top));
        }

        .presentation-app-header__brand-text,
        .presentation-app-header__partner,
        .presentation-app-header__btn-label,
        .presentation-session__label,
        .presentation-session__logout-text,
        .presentation-app-header__fullscreen {
            display: none !important;
        }

        .presentation-app-header .logo-chip--mark {
            width: 2.35rem;
            height: 2.35rem;
            padding: 0;
            border-radius: 0.85rem;
        }

        .presentation-session {
            max-width: 8.5rem;
            padding-left: 0.55rem;
        }

        .presentation-session__name {
            max-width: 4.8rem;
            font-size: 0.68rem;
        }

        .presentation-session__logout {
            min-width: 2rem;
            width: 2rem;
            padding: 0;
        }

        .presentation-session__logout-icon {
            display: block;
        }

        .presentation-app-header__icon-btn {
            width: 2.35rem;
            padding: 0;
        }

        #slides-container {
            top: 3.35rem;
            bottom: 3.65rem;
            padding-inline: 0.75rem;
        }

        .footer-glass {
            padding-top: 0.55rem;
            padding-bottom: max(0.55rem, env(safe-area-inset-bottom));
        }

        .presentation-nav-desktop,
        .kb-hint {
            display: none !important;
        }
    }

    @media (min-width: 768px) {
        .presentation-session__time {
            display: block;
        }

        .presentation-session {
            max-width: 18rem;
        }

        .presentation-session__name {
            max-width: 11rem;
            font-size: 0.78rem;
        }

        .presentation-nav-desktop {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
        }

        .presentation-footer-mobile .presentation-swipe-hint {
            display: none;
        }

        .presentation-app-header__fullscreen {
            display: inline-flex;
        }
    }
</style>

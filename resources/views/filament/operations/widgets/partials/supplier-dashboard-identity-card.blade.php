<style>
    .tdg-supplier-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        border-radius: 1.5rem;
        padding: 1.75rem;
        color: #f8fafc;
        background: linear-gradient(135deg, #052f60 0%, #14548f 48%, #2d89ca 100%);
        box-shadow: 0 22px 60px -28px rgba(5, 47, 96, 0.75);
    }

    .tdg-supplier-hero__glow {
        position: absolute;
        inset-block-start: -55%;
        inset-inline-end: -12%;
        width: 26rem;
        height: 26rem;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.28) 0%, rgba(255, 255, 255, 0) 68%);
        pointer-events: none;
        z-index: 0;
    }

    .tdg-supplier-hero__inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1.25rem;
    }

    .tdg-supplier-hero__eyebrow {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: rgba(226, 240, 255, 0.82);
    }

    .tdg-supplier-hero__name {
        margin-top: 0.5rem;
        font-size: clamp(1.5rem, 2.6vw, 2.125rem);
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.02em;
        text-wrap: balance;
    }

    .tdg-supplier-hero__badges {
        margin-top: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .tdg-supplier-hero__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.28);
        background: rgba(255, 255, 255, 0.14);
        padding: 0.3125rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #f1f7ff;
        backdrop-filter: blur(6px);
    }

    .tdg-supplier-hero__badge svg {
        width: 0.875rem;
        height: 0.875rem;
    }

    .tdg-supplier-hero__aside {
        text-align: right;
        min-width: 12rem;
        flex: 1 1 12rem;
    }

    .tdg-supplier-hero__aside-label {
        font-size: 0.6875rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: rgba(226, 240, 255, 0.72);
    }

    .tdg-supplier-hero__aside-value {
        margin-top: 0.125rem;
        font-size: 0.9375rem;
        font-weight: 600;
    }

    .tdg-supplier-hero__aside-date {
        margin-top: 0.375rem;
        font-size: 0.75rem;
        color: rgba(226, 240, 255, 0.72);
    }

    @media (max-width: 640px) {
        .tdg-supplier-hero__aside {
            text-align: left;
        }
    }
</style>

<div class="tdg-supplier-hero">
    <div class="tdg-supplier-hero__glow" aria-hidden="true"></div>

    <div class="tdg-supplier-hero__inner">
        <div style="min-width: 0; flex: 2 1 22rem;">
            <p class="tdg-supplier-hero__eyebrow">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.9375rem; height: 0.9375rem;">
                    <path fill-rule="evenodd" d="M9.661 2.237a.531.531 0 0 1 .678 0 11.947 11.947 0 0 0 7.078 2.749.5.5 0 0 1 .479.425c.069.52.104 1.05.104 1.589 0 5.162-3.26 9.563-7.834 11.256a.48.48 0 0 1-.332 0C5.26 16.564 2 12.163 2 7c0-.538.035-1.069.104-1.589a.5.5 0 0 1 .48-.425 11.947 11.947 0 0 0 7.077-2.75Zm4.196 5.954a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                </svg>
                Espacio operativo del proveedor
            </p>

            <h2 class="tdg-supplier-hero__name">{{ $supplierName }}</h2>

            <div class="tdg-supplier-hero__badges">
                <span class="tdg-supplier-hero__badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                    </svg>
                    Gestión Integracorp activa
                </span>
                <span class="tdg-supplier-hero__badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 3.75a2 2 0 1 0-1.5 3.873V16.5a.75.75 0 0 0 1.5 0V7.623A2 2 0 0 0 10 3.75Z" />
                        <path d="M4.25 6.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-1.5 0v-9a.75.75 0 0 1 .75-.75ZM15.75 6.5a.75.75 0 0 1 .75.75v9a.75.75 0 0 1-1.5 0v-9a.75.75 0 0 1 .75-.75Z" />
                    </svg>
                    {{ number_format($totalCases) }} {{ $totalCases === 1 ? 'caso gestionado' : 'casos gestionados' }}
                </span>
                <span class="tdg-supplier-hero__badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v3.5c0 .28.155.536.402.663l2.5 1.25a.75.75 0 0 0 .67-1.342l-2.072-1.036V6.75Z" clip-rule="evenodd" />
                    </svg>
                    Acceso auditado
                </span>
            </div>
        </div>

        <div class="tdg-supplier-hero__aside">
            <p class="tdg-supplier-hero__aside-label">Analista en sesión</p>
            <p class="tdg-supplier-hero__aside-value">{{ $analystName }}</p>
            <p class="tdg-supplier-hero__aside-date">{{ $date }}</p>
        </div>
    </div>
</div>

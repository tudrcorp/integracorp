<style>
    .integracorp-session-modal__overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 1.25rem;
        background: rgba(15, 23, 42, 0.58);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .integracorp-session-modal__card {
        position: relative;
        z-index: 100000;
        width: 100%;
        max-width: 26rem;
        overflow: hidden;
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.85);
        background: #ffffff;
        box-shadow:
            0 24px 60px rgba(10, 65, 116, 0.18),
            0 8px 24px rgba(15, 23, 42, 0.12);
    }

    .dark .integracorp-session-modal__card {
        border-color: rgba(255, 255, 255, 0.1);
        background: #0f172a;
        box-shadow:
            0 24px 60px rgba(0, 0, 0, 0.45),
            0 8px 24px rgba(0, 0, 0, 0.25);
    }

    .integracorp-session-modal__hero {
        position: relative;
        overflow: hidden;
        padding: 1.5rem 1.5rem 1.25rem;
        background: linear-gradient(135deg, #0a4174 0%, #49769f 52%, #7bbde8 100%);
        text-align: center;
    }

    .integracorp-session-modal__hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 18% 20%, rgba(255, 255, 255, 0.18), transparent 42%),
            radial-gradient(circle at 82% 78%, rgba(189, 216, 233, 0.22), transparent 38%);
        pointer-events: none;
    }

    .integracorp-session-modal__logo {
        position: relative;
        z-index: 1;
        display: block;
        height: 2.25rem;
        width: auto;
        margin: 0 auto 1rem;
        filter: drop-shadow(0 4px 10px rgba(15, 23, 42, 0.18));
    }

    .integracorp-session-modal__icon-wrap {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 4.25rem;
        height: 4.25rem;
        margin: 0 auto 0.85rem;
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        color: #b45309;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
    }

    .integracorp-session-modal__icon-wrap svg {
        width: 2rem;
        height: 2rem;
    }

    .integracorp-session-modal__eyebrow {
        position: relative;
        z-index: 1;
        margin: 0;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: rgba(189, 216, 233, 0.95);
    }

    .integracorp-session-modal__title {
        position: relative;
        z-index: 1;
        margin: 0.35rem 0 0;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
        color: #ffffff;
    }

    .integracorp-session-modal__body {
        padding: 1.25rem 1.5rem 0.5rem;
    }

    .integracorp-session-modal__message {
        margin: 0;
        border-radius: 1rem;
        border: 1px solid rgba(251, 191, 36, 0.35);
        background: linear-gradient(180deg, rgba(255, 251, 235, 0.95) 0%, rgba(254, 243, 199, 0.55) 100%);
        padding: 0.95rem 1rem;
        font-size: 0.875rem;
        line-height: 1.65;
        color: #78350f;
    }

    .dark .integracorp-session-modal__message {
        border-color: rgba(251, 191, 36, 0.28);
        background: linear-gradient(180deg, rgba(120, 53, 15, 0.28) 0%, rgba(69, 26, 3, 0.18) 100%);
        color: #fde68a;
    }

    .integracorp-session-modal__hint {
        margin: 0.85rem 0 0;
        font-size: 0.75rem;
        line-height: 1.5;
        color: #64748b;
        text-align: center;
    }

    .dark .integracorp-session-modal__hint {
        color: #94a3b8;
    }

    .integracorp-session-modal__actions {
        display: flex;
        flex-direction: column-reverse;
        gap: 0.55rem;
        padding: 1rem 1.5rem 1.35rem;
    }

    @media (min-width: 480px) {
        .integracorp-session-modal__actions {
            flex-direction: row;
            justify-content: flex-end;
        }
    }

    .integracorp-session-modal__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 2.65rem;
        border-radius: 0.85rem;
        padding: 0.55rem 1rem;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1;
        transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease, box-shadow 160ms ease;
        cursor: pointer;
    }

    .integracorp-session-modal__btn:hover {
        transform: translateY(-1px);
    }

    .integracorp-session-modal__btn--ghost {
        border: 1px solid rgba(203, 213, 225, 0.95);
        background: #ffffff;
        color: #334155;
    }

    .integracorp-session-modal__btn--ghost:hover {
        background: #f8fafc;
    }

    .dark .integracorp-session-modal__btn--ghost {
        border-color: rgba(255, 255, 255, 0.12);
        background: #111827;
        color: #e2e8f0;
    }

    .dark .integracorp-session-modal__btn--ghost:hover {
        background: #1e293b;
    }

    .integracorp-session-modal__btn--primary {
        border: 1px solid transparent;
        background: linear-gradient(135deg, #0a4174 0%, #305b93 100%);
        color: #bdd8e9;
        box-shadow: 0 10px 22px rgba(10, 65, 116, 0.28);
    }

    .integracorp-session-modal__btn--primary:hover {
        background: linear-gradient(135deg, #08365f 0%, #49769f 100%);
        box-shadow: 0 12px 26px rgba(10, 65, 116, 0.34);
    }

    .integracorp-session-modal__close {
        position: absolute;
        top: 0.85rem;
        right: 0.85rem;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border: 0;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.16);
        color: #ffffff;
        cursor: pointer;
        transition: background-color 160ms ease, transform 160ms ease;
    }

    .integracorp-session-modal__close:hover {
        background: rgba(255, 255, 255, 0.28);
        transform: scale(1.04);
    }

    .integracorp-session-modal__close svg {
        width: 1.1rem;
        height: 1.1rem;
    }
</style>

<div
    wire:ignore
    x-data="{ open: false }"
    x-on:integracorp-session-expired.window="open = true"
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="integracorp-session-modal__overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="integracorp-session-expired-title"
>
    <div class="absolute inset-0" x-on:click="open = false" aria-hidden="true"></div>

    <section
        class="integracorp-session-modal__card"
        x-show="open"
        x-transition:enter="transition ease-out duration-220"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        @click.stop
    >
        <div class="integracorp-session-modal__hero">
            <button
                type="button"
                class="integracorp-session-modal__close"
                x-on:click="open = false"
                aria-label="Cerrar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>

            <img
                src="{{ asset('image/logoNewTDG.png') }}"
                alt="{{ config('app.name') }}"
                class="integracorp-session-modal__logo"
            >

            <div class="integracorp-session-modal__icon-wrap" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>

            <p class="integracorp-session-modal__eyebrow">Seguridad de sesión</p>
            <h2 id="integracorp-session-expired-title" class="integracorp-session-modal__title">
                Tu sesión ha expirado
            </h2>
        </div>

        <div class="integracorp-session-modal__body">
            <p class="integracorp-session-modal__message">
                Por inactividad o por seguridad, tu sesión ya no está activa. Recarga la página para continuar trabajando en <strong>{{ config('app.name') }}</strong>.
            </p>
            <p class="integracorp-session-modal__hint">
                Si tenías cambios sin guardar, es posible que debas volver a ingresarlos después de recargar.
            </p>
        </div>

        <div class="integracorp-session-modal__actions">
            <button
                type="button"
                class="integracorp-session-modal__btn integracorp-session-modal__btn--ghost"
                x-on:click="open = false"
            >
                Cerrar
            </button>
            <button
                type="button"
                class="integracorp-session-modal__btn integracorp-session-modal__btn--primary"
                x-on:click="window.location.reload()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true" style="width:1rem;height:1rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M4.031 9.865H2.985m13.018 0h1.047" />
                </svg>
                Recargar página
            </button>
        </div>
    </section>
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status !== 419) {
                    return;
                }

                preventDefault();
                window.dispatchEvent(new CustomEvent('integracorp-session-expired'));
            });
        });
    });
</script>

{{-- Visible sólo si el médico llegó aquí desde la revisión. --}}
<div
    x-data="{ checking: false }"
    x-show="$wire.consultationReviewVisited"
    x-cloak
    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 dark:border-primary-500/30 dark:bg-primary-500/10"
>
    <span class="text-sm text-primary-900 dark:text-primary-100">Corrija lo que necesite y vuelva a la revisión.</span>
    <button
        type="button"
        x-on:click="
            checking = true;
            $wire.validateStepsBeforeReview()
                .then((target) => {
                    step = getSteps().find((key) => String(key).endsWith(target)) ?? step;
                    scroll();
                })
                .finally(() => { checking = false; });
        "
        x-bind:disabled="checking"
        class="inline-flex items-center gap-1.5 rounded-full bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
    >
        <x-filament::icon icon="heroicon-m-arrow-uturn-right" class="size-4" />
        <span x-show="! checking">Volver a la revisión</span>
        <span x-show="checking" x-cloak>Verificando…</span>
    </button>
</div>

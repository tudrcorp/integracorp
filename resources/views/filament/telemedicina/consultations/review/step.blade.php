@php
    $reviewKey = \App\Support\Telemedicine\TelemedicineConsultationWizardSteps::REVIEW;
@endphp

{{--
    Se arma sólo al entrar a este paso (x-effect sobre `step`, del asistente):
    no suma costo al resto de las interacciones del formulario.
--}}
<div
    x-data="{ html: '', loading: false, failed: false }"
    x-effect="
        if (String(step).endsWith(@js($reviewKey))) {
            loading = true;
            failed = false;
            $wire.consultationReviewHtml()
                .then((result) => { html = result; })
                .catch(() => { failed = true; })
                .finally(() => { loading = false; });
        }
    "
    class="space-y-3"
>
    <div class="flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
        <x-filament::icon icon="heroicon-o-eye" class="mt-0.5 size-5 shrink-0" />
        <p>Revise todo lo que cargó antes de registrar. Para corregir algo pulse <strong>Editar</strong> en la sección; luego use <strong>Volver a la revisión</strong>.</p>
    </div>

    <div x-show="loading" class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-6 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
        <x-filament::loading-indicator class="size-5" />
        Preparando la revisión…
    </div>

    <p x-show="failed" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
        No se pudo preparar la revisión. Vuelva a un paso anterior e intente de nuevo; sus datos no se perdieron.
    </p>

    {{-- wire:ignore: Livewire no debe vaciar el HTML inyectado al redibujar. --}}
    <div wire:ignore x-show="! loading && ! failed" x-html="html"></div>
</div>

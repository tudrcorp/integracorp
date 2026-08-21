@php
    /** @var bool $isSelected */
    /** @var mixed $coverageAmount */
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;

    // La regla vive en el builder para que el PDF y esta vista previa no se
    // separen: con tope se muestra solo el monto, sin tope el check, y un
    // límite en cero cuenta como sin tope.
    $display = PlanGeneratorPreviewBuilder::benefitCellDisplay($isSelected, $coverageAmount ?? null);
    $coverageLabel = $display === 'amount'
        ? PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $coverageAmount)
        : '';
@endphp

<div class="flex flex-col items-center gap-1">
    @if ($display === 'amount')
        <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">US$ {{ $coverageLabel }}</span>
    @elseif ($display === 'check')
        <span
            class="inline-flex size-5 items-center justify-center rounded-full bg-emerald-100 text-[10px] font-bold leading-none text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200"
            aria-label="Beneficio incluido">
            ✓
        </span>
    @else
        <span
            class="inline-flex size-5 items-center justify-center rounded-full bg-rose-100 text-[11px] font-bold leading-none text-rose-700 dark:bg-rose-500/20 dark:text-rose-200"
            aria-label="No incluido">
            −
        </span>
    @endif
</div>

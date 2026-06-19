@php
    /** @var array<int, array<string, mixed>> $columns */
    use App\Support\PlanGenerators\PlanGeneratorMatrixColumnLayout;

    $columns = (array) ($columns ?? []);
    $type = (string) ($type ?? 'rates');
    $usePdfWidths = (bool) ($usePdfWidths ?? false);
    $columnCount = PlanGeneratorMatrixColumnLayout::planColumnCount($columns);
    $planWidthMm = PlanGeneratorMatrixColumnLayout::planColumnWidthMm($columnCount);
@endphp
<colgroup>
    <col
        @if ($usePdfWidths)
            style="width: {{ PlanGeneratorMatrixColumnLayout::rateAgeWidthMm() }}mm"
        @else
            class="pg-col-rate-age"
        @endif
    >
    <col
        @if ($usePdfWidths)
            style="width: {{ PlanGeneratorMatrixColumnLayout::ratePopWidthMm() }}mm"
        @else
            class="pg-col-rate-pop"
        @endif
    >
    @foreach ($columns as $column)
        <col
            @if ($usePdfWidths)
                style="width: {{ $planWidthMm }}mm"
            @else
                class="pg-col-plan"
            @endif
            @if (! $usePdfWidths)
                wire:key="pg-plan-col-{{ $type }}-{{ $column['column_key'] ?? $loop->index }}"
            @endif
        >
    @endforeach
</colgroup>

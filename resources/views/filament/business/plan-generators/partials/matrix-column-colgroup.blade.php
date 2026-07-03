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
    @if ($type === 'group-total')
        <col
            @if ($usePdfWidths)
                style="width: {{ PlanGeneratorMatrixColumnLayout::leadWidthMm() }}mm"
            @else
                class="pg-col-lead"
            @endif
        >
    @else
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
    @endif
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

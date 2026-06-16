@php
    /** @var array<int, array<string, mixed>> $columns */
    $columns = (array) ($columns ?? []);
    $columnCount = max(1, count($columns));
    $planColPercent = 68 / $columnCount;
@endphp
<style>
    .pg-stacked-matrices .pg-matrix-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .pg-stacked-matrices .pg-col-lead { width: 32%; }
    .pg-stacked-matrices .pg-col-rate-age { width: 22%; }
    .pg-stacked-matrices .pg-col-rate-pop { width: 10%; }
    .pg-stacked-matrices .pg-col-plan { width: {{ $planColPercent }}%; }
</style>

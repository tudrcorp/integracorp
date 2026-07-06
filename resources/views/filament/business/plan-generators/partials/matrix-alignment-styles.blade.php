@php
    /** @var array<int, array<string, mixed>> $columns */
    use App\Support\PlanGenerators\PlanGeneratorMatrixColumnLayout;

    $columns = (array) ($columns ?? []);
    $columnCount = PlanGeneratorMatrixColumnLayout::planColumnCount($columns);
@endphp
<style>
    .pg-stacked-matrices {
        --pg-plan-count: {{ $columnCount }};
    }

    .pg-stacked-matrices .pg-matrix-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .pg-stacked-matrices .pg-col-lead {
        width: {{ PlanGeneratorMatrixColumnLayout::LEAD_PERCENT }}%;
    }

    .pg-stacked-matrices .pg-col-rate-age {
        width: {{ PlanGeneratorMatrixColumnLayout::RATE_AGE_PERCENT }}%;
    }

    .pg-stacked-matrices .pg-col-rate-pop {
        width: {{ PlanGeneratorMatrixColumnLayout::RATE_POP_PERCENT }}%;
    }

    .pg-stacked-matrices .pg-col-plan {
        width: calc({{ PlanGeneratorMatrixColumnLayout::PLAN_BLOCK_PERCENT }}% / var(--pg-plan-count));
    }

    .pg-stacked-matrices .pg-matrix-table th,
    .pg-stacked-matrices .pg-matrix-table td {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
</style>

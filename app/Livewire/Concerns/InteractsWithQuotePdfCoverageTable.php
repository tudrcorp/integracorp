<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\QuotePdfCoverageTable;

trait InteractsWithQuotePdfCoverageTable
{
    /** @var list<float> */
    public array $coverageColumns = [];

    public int $coverageCount = 0;

    /** @var list<array{age_range: string, total_persons: int, amounts: array<string, float>}> */
    public array $tableRows = [];

    /** @var array<string, float|null> */
    public array $totals = [];

    protected function buildQuotePdfCoverageTable(mixed $data, int|string|null $planId = null): void
    {
        $resolvedPlanId = is_numeric($planId) ? (int) $planId : QuotePdfCoverageTable::resolvePlanIdFromGroupedData($data);
        $table = QuotePdfCoverageTable::build($data, $resolvedPlanId);

        $this->coverageColumns = $table['coverageColumns'];
        $this->coverageCount = $table['coverageCount'];
        $this->tableRows = $table['rows'];
        $this->totals = $table['totals'];
    }
}

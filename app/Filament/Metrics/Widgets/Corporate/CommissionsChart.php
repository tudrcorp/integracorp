<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class CommissionsChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Comisiones por beneficiario';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Comisiones generadas en el período, en USD.';
    }

    protected function values(): array
    {
        return CorporateFinanceMetrics::commissionsByBeneficiary($this->period());
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function shareText(): string
    {
        return ' de las comisiones';
    }
}

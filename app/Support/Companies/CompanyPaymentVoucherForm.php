<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\Company;
use App\Support\PaymentVoucherFormSchema;

final class CompanyPaymentVoucherForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return PaymentVoucherFormSchema::components(
            baseTotalDefault: fn (Company $record): float => CompanyPaymentAnnualTotalResolver::resolve($record),
            totalHelperText: fn (Company $record): string => CompanyPaymentAnnualTotalResolver::helperText($record),
        );
    }
}

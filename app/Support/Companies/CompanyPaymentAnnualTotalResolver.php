<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\Company;
use App\Support\PlanGenerators\PlanGeneratorCompanyRates;

final class CompanyPaymentAnnualTotalResolver
{
    public static function resolve(Company $company): float
    {
        if ($company->total_amount !== null && (float) $company->total_amount > 0) {
            return (float) $company->total_amount;
        }

        if ($company->plan_generator_id === null) {
            return 0.0;
        }

        $payload = $company->planGenerator !== null
            ? PlanGeneratorCompanyRates::payloadForPlan($company->planGenerator)
            : null;

        if ($payload === null) {
            return 0.0;
        }

        $dataRecord = PlanGeneratorCompanyRates::dataRecordForColumn(
            $payload,
            $company->plan_generator_column_key,
        );

        if ($dataRecord === null) {
            return 0.0;
        }

        $frequency = (string) ($company->payment_frequency ?? 'ANUAL');

        return PlanGeneratorCompanyRates::amountsFor($frequency, $dataRecord)['total_amount'];
    }

    public static function helperText(Company $company): string
    {
        if ($company->plan_generator_id === null) {
            return 'Sin cotización asociada. Ajuste el total manualmente si aplica.';
        }

        $plan = $company->planGenerator;

        if ($plan === null) {
            return 'Sin cotización asociada. Ajuste el total manualmente si aplica.';
        }

        $control = filled($plan->control_number)
            ? ' · Nro. '.$plan->control_number
            : '';

        $planLabel = filled($company->plan_generator_column_label)
            ? ' · Opción: '.$company->plan_generator_column_label
            : '';

        $frequency = strtoupper((string) ($company->payment_frequency ?? 'ANUAL'));

        return 'Cotización: '.$plan->name.$control.$planLabel.' · Frecuencia: '.$frequency;
    }
}

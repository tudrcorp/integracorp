<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Enums\PlanPricingMode;
use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\IndividualQuotePdfGenerator;
use App\Support\IndividualQuotePdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Throwable;

/**
 * PDF de la PWA: una sola hoja compuesta desde la estructura del plan
 * (beneficios, tarifas, rangos y cálculos). No incluye portada ni las
 * páginas extra de la propuesta económica histórica.
 */
final class StorefrontQuotePdf
{
    public static function path(string $code): string
    {
        return public_path('storage/quotes/'.trim($code).'.pdf');
    }

    public static function layoutFor(Plan $plan): string
    {
        if ($plan->isBenefitPackage()) {
            return IndividualQuotePdfLayout::EstructuraPaquete;
        }

        $raw = $plan->getAttributes()['pricing_mode'] ?? null;
        $stored = $raw instanceof PlanPricingMode ? $raw->value : $raw;

        if (PlanPricingMode::fromStored($stored) === null
            && (int) $plan->getKey() === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID) {
            return IndividualQuotePdfLayout::EstructuraPaquete;
        }

        return IndividualQuotePdfLayout::Estructura;
    }

    public static function exists(string $code): bool
    {
        $path = self::path($code);

        return $code !== '' && is_file($path);
    }

    /**
     * Regenera siempre el PDF de una sola hoja. Así una cotización que aún
     * tuviera la propuesta histórica de varias páginas no se sirve al cliente.
     */
    public static function ensure(IndividualQuote $quote): bool
    {
        $code = trim((string) $quote->code);

        if ($code === '') {
            return false;
        }

        return self::generate($quote);
    }

    public static function generate(IndividualQuote $quote): bool
    {
        try {
            $planId = (int) $quote->plan;
            $plan = Plan::query()->find($planId);

            if (! $plan instanceof Plan) {
                return false;
            }

            $layout = self::layoutFor($plan);
            $agentName = self::agentName($quote);
            $details = IndividualQuotePdfGenerator::detailsPayload($quote, $planId, $layout, $agentName);

            if ($details === null) {
                return false;
            }

            $directory = public_path('storage/quotes');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            $groupCollect = collect($details['data'])->groupBy('age_range');
            $code = (string) $details['code'];
            $nameUser = (string) ($details['agent_name'] ?? $agentName);

            $pdf = Pdf::loadView('documents.storefront-quote', [
                'details' => $details,
                'group_collect' => $groupCollect,
                'name_user' => $nameUser,
            ]);
            $pdf->setPaper('a4', 'portrait');
            $pdf->save(self::path($code));

            return self::exists($code);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function controlNumber(string $code): string
    {
        if (Str::contains($code, 'COT-IND-')) {
            return str_replace('COT-IND-', '', $code);
        }

        if (Str::contains($code, 'COT-CORP-')) {
            return str_replace('COT-CORP-', '', $code);
        }

        return $code;
    }

    private static function agentName(IndividualQuote $quote): string
    {
        $quote->loadMissing('agent');

        if (filled($quote->agent?->name)) {
            return (string) $quote->agent->name;
        }

        if (filled($quote->created_by)) {
            return (string) $quote->created_by;
        }

        return 'Tu Dr En Casa';
    }
}

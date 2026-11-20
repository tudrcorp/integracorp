<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\Plan;
use App\Support\IndividualQuotePdfLayout;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Vista previa de un diseño de cotización con lo que el analista tiene en pantalla, sin guardar.
 *
 * Usa las tarifas activas del plan con una persona por rango de edad: así se ve el peor caso de
 * largo de la página y nunca se muestran datos de un cliente real. El PDF queda en el disco
 * privado, a nombre del usuario, y se abre por una URL firmada que vence en 30 minutos.
 */
final class QuoteDesignPreview
{
    public const DIRECTORY = 'quote-design-previews';

    private const MAX_AGE_SECONDS = 7200;

    /**
     * @param  array<string, mixed>  $snapshot  {@see QuoteDesignTheme::snapshotFrom()}
     *
     * @throws RuntimeException con un mensaje para el analista
     */
    public static function render(array $snapshot, int $planId, string $scope = QuoteDocumentLayout::SCOPE_INDIVIDUAL): string
    {
        $details = self::sampleDetails($planId, $scope);
        $theme = QuoteDesignTheme::fromSnapshot($snapshot);
        $files = [];

        try {
            return QuoteDesignedProposal::assemble($scope, [[
                'plan_id' => $planId,
                'details' => $details,
                'theme' => $theme,
                'version' => null,
            ]], $files, $theme);
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Guarda el PDF y devuelve la URL firmada para verlo.
     */
    public static function store(string $document): string
    {
        $userId = (int) Auth::id();
        $token = Str::uuid()->toString();
        $disk = Storage::disk('local');

        self::forgetOld($userId);

        $disk->put(self::path($userId, $token), $document);

        return URL::temporarySignedRoute('business.quote-designs.preview', now()->addMinutes(30), ['token' => $token]);
    }

    public static function path(int $userId, string $token): string
    {
        return self::DIRECTORY.'/'.$userId.'/'.$token.'.pdf';
    }

    /**
     * Payload con la misma forma que el de una cotización real.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public static function sampleDetails(int $planId, string $scope = QuoteDocumentLayout::SCOPE_INDIVIDUAL): array
    {
        $plan = Plan::query()->find($planId);

        if ($plan === null) {
            throw new RuntimeException('El plan elegido no existe.');
        }

        $layout = IndividualQuotePdfLayout::resolveForIndividual($planId);
        $withCoverage = IndividualQuotePdfLayout::usesCoverageBreakdown($layout);

        $query = DB::table('fees')
            ->join('age_ranges', 'fees.age_range_id', '=', 'age_ranges.id')
            ->where('fees.plan_id', $planId)
            ->where('fees.status', 'ACTIVO')
            ->select('fees.plan_id', 'fees.age_range_id', 'fees.coverage_id', 'fees.price', 'age_ranges.range as age_range')
            ->orderBy('age_ranges.id');

        if ($withCoverage) {
            $query->join('coverages', 'fees.coverage_id', '=', 'coverages.id')
                ->addSelect('coverages.price as coverage')
                ->orderBy('coverages.price');
        } else {
            $query->whereNull('fees.coverage_id');
        }

        $data = $query->get()->map(static function (object $fee) use ($plan): object {
            $price = (float) $fee->price;

            return (object) [
                'plan_id' => (int) $fee->plan_id,
                'age_range_id' => (int) $fee->age_range_id,
                'coverage_id' => $fee->coverage_id !== null ? (int) $fee->coverage_id : null,
                'total_persons' => 1,
                'fee' => $price,
                'subtotal_anual' => $price,
                'subtotal_biannual' => $price / 2,
                'subtotal_quarterly' => $price / 4,
                'subtotal_monthly' => $price / 12,
                'plan' => (string) $plan->description,
                'age_range' => (string) $fee->age_range,
                'coverage' => $fee->coverage ?? null,
            ];
        })->all();

        if ($data === []) {
            throw new RuntimeException('El plan «'.$plan->description.'» no tiene tarifas activas: no hay números que mostrar en la vista previa.');
        }

        return [
            'plan' => $planId,
            'layout' => $layout,
            'code' => $scope === QuoteDocumentLayout::SCOPE_CORPORATE ? 'COT-CORP-MUESTRA' : 'COT-IND-MUESTRA',
            'name' => $scope === QuoteDocumentLayout::SCOPE_CORPORATE ? 'Empresa de muestra, C.A.' : 'Cliente de muestra',
            'agent_name' => (string) (Auth::user()?->name ?? 'Agente de muestra'),
            'email' => null,
            'phone' => null,
            'date' => now()->format('d-m-Y'),
            'data' => $data,
            'upgrades' => [],
        ];
    }

    private static function forgetOld(int $userId): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files(self::DIRECTORY.'/'.$userId) as $file) {
            if (time() - $disk->lastModified($file) > self::MAX_AGE_SECONDS) {
                $disk->delete($file);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\BenefitCoverage;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use Illuminate\Support\Str;

/**
 * Beneficios del plan con su cobertura, para la tabla del certificado y la franja
 * principal (hero).
 *
 * - Plan por coberturas: el monto es `benefit_coverages.limit` de la cobertura del
 *   afiliado; `NULL` es «sin límite», que se imprime «Incluido» (no «US$ 0»).
 * - Plan paquete: todo «Incluido».
 * - Respaldo de los certificados anteriores: si el beneficio de emergencias o de
 *   accidentes no tiene fila de límite, se usa el precio de la cobertura.
 */
final class CertificateBenefits
{
    public const INCLUDED = 'Incluido';

    /** Siglas que no deben pasar a minúsculas al normalizar el texto. */
    private const ACRONYMS = ['AMD', 'TDEC', 'UCI'];

    /**
     * @return array{rows: list<array{t: string, cob: string, limited: bool}>, hero: array{label: string, value: string, note: string}, carnet: string, emergency: bool}
     */
    public static function for(?Plan $plan, ?int $coverageId, ?float $coveragePrice): array
    {
        if ($plan === null) {
            return self::summary([]);
        }

        // Mismo respaldo que el calculador de tarifas: sin modo guardado, el plan 1 es paquete.
        $isPackage = $plan->getRawOriginal('pricing_mode') !== null
            ? $plan->isBenefitPackage()
            : (int) $plan->id === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID;

        $limits = $isPackage || ! $coverageId
            ? collect()
            : BenefitCoverage::query()
                ->where('plan_id', $plan->id)
                ->where('coverage_id', $coverageId)
                ->get(['benefit_id', 'limit'])
                ->keyBy('benefit_id');

        $rows = [];

        foreach ($plan->benefitPlans as $benefit) {
            $description = trim((string) ($benefit->pivot?->description ?: $benefit->description ?? ''));

            if ($description === '') {
                continue;
            }

            $kind = self::kind($description);
            $limit = $limits->get($benefit->id)?->limit;
            $amount = $limit !== null ? (float) $limit : null;

            if ($amount === null && ! $isPackage && $kind !== null && $coveragePrice !== null && $coveragePrice > 0) {
                $amount = $coveragePrice;
            }

            $rows[] = [
                't' => self::sentence($description).($kind === 'emergency' ? ' *' : ''),
                'cob' => $amount !== null ? self::money($amount) : self::INCLUDED,
                'limited' => $amount !== null,
                'kind' => $kind,
                'amount' => $amount,
            ];
        }

        return self::summary($rows);
    }

    public static function money(float $amount): string
    {
        $decimals = floor($amount) === $amount ? 0 : 2;

        return 'US$ '.number_format($amount, $decimals, ',', '.');
    }

    /**
     * «ATENCIÓN MÉDICA DOMICILIARIA» → «Atención médica domiciliaria», sin romper siglas.
     */
    public static function sentence(string $text): string
    {
        $lower = Str::ucfirst(mb_strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text)));

        foreach (self::ACRONYMS as $acronym) {
            $lower = preg_replace('/\b'.mb_strtolower($acronym).'\b/u', $acronym, $lower) ?? $lower;
        }

        return $lower;
    }

    private static function kind(string $description): ?string
    {
        $text = Str::upper(Str::ascii($description));

        return match (true) {
            str_contains($text, 'EMERGENCIA') => 'emergency',
            str_contains($text, 'ACCIDENTE') => 'accident',
            str_contains($text, 'HOSPITALIZACION') => 'hospital',
            default => null,
        };
    }

    /**
     * @param  list<array{t: string, cob: string, limited: bool, kind: ?string, amount: ?float}>  $rows
     * @return array{rows: list<array{t: string, cob: string, limited: bool}>, hero: array{label: string, value: string, note: string}, carnet: string, emergency: bool}
     */
    private static function summary(array $rows): array
    {
        $find = static fn (string $kind): ?array => collect($rows)->first(fn (array $row): bool => $row['kind'] === $kind);
        $emergency = $find('emergency');
        $accident = $find('accident');
        $hospital = $find('hospital');

        $hero = match (true) {
            $emergency !== null => ['label' => 'Asistencia por emergencia', 'value' => $emergency['cob'], 'note' => 'Patologías listadas. Excluye preexistencias'],
            $hospital !== null => ['label' => 'Hospitalización', 'value' => $hospital['cob'], 'note' => 'Cirugía y maternidad en centros locales'],
            $accident !== null => ['label' => 'Asistencia por accidentes', 'value' => $accident['cob'], 'note' => 'Monto máximo por período de vigencia'],
            default => ['label' => 'Beneficios incluidos', 'value' => count($rows).' '.(count($rows) === 1 ? 'servicio' : 'servicios'), 'note' => 'Atención en sitio'],
        };

        $carnet = match (true) {
            $emergency !== null && $emergency['limited'] => 'Emergencia '.$emergency['cob'],
            $accident !== null && $accident['limited'] => 'Accidentes '.$accident['cob'],
            default => $hero['label'],
        };

        return [
            'rows' => array_map(
                static fn (array $row): array => ['t' => $row['t'], 'cob' => $row['cob'], 'limited' => $row['limited']],
                $rows,
            ),
            'hero' => $hero,
            'carnet' => $carnet,
            'emergency' => $emergency !== null,
        ];
    }
}

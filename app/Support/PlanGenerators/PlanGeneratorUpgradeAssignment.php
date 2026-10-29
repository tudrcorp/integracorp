<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Upgrade;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Upgrades\UpgradePricing;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Al aprobar una cotización del generador, cada afiliado creado recibe los
 * upgrades cotizados según su edad real (`PlanGeneratorUpgrades::eligibilityForAge()`):
 * nunca alguien fuera de las edades del upgrade. En corporativo los montos ya
 * vienen sumados en la tarifa de cada afiliado (el padrón se ubica por edad
 * real); en individual el total traía un estimado y aquí se ajusta.
 */
final class PlanGeneratorUpgradeAssignment
{
    /**
     * Upgrades de una pre-afiliación individual, a cada familiar por su edad real:
     * nadie fuera de las edades del upgrade lo recibe. El total de la afiliación
     * traía un estimado (precio del rango elegido × personas); se ajusta a lo que
     * de verdad se asignó.
     *
     * @param  array<string, mixed>  $payload  sesión del generador (`PlanGeneratorPreAffiliationSession::get()`)
     * @return array{created: int, annual: float, delta: float}
     */
    public static function assignIndividual(Affiliation $affiliation, array $payload): array
    {
        $lines = array_values(array_filter((array) ($payload['upgrade_lines'] ?? []), 'is_array'));

        if ($lines === []) {
            return ['created' => 0, 'annual' => 0.0, 'delta' => 0.0];
        }

        $rateRows = (array) ($payload['rate_rows'] ?? []);
        $fallbackLabel = (string) ($payload['upgrade_row_label'] ?? '');
        $actor = self::actor();
        $created = 0;
        $annual = 0.0;

        foreach ($affiliation->affiliates()->orderBy('id')->get() as $affiliate) {
            $age = UpgradePricing::ageOf($affiliate);
            $rowLabel = PlanGeneratorUpgrades::rowLabelForAge($rateRows, $age) ?? $fallbackLabel;

            foreach (PlanGeneratorUpgrades::perPersonForAge($lines, $age, $rowLabel)['upgrades'] as $upgrade) {
                AffiliateUpgrade::query()->create([
                    'affiliate_id' => $affiliate->getKey(),
                    ...self::attributes($upgrade, $age, $actor),
                ]);
                $created++;
                $annual += (float) $upgrade['amount'];
            }
        }

        $annual = round($annual, 2);
        $delta = round($annual - (float) ($payload['upgrade_annual_estimate'] ?? 0), 2);

        if (abs($delta) >= 0.01) {
            $feeAnual = max(0.0, round((float) $affiliation->fee_anual + $delta, 2));
            $affiliation->forceFill([
                'fee_anual' => $feeAnual,
                'total_amount' => app(AffiliationAffiliateFeeCalculator::class)
                    ->totalAmountForPaymentFrequency($feeAnual, (string) ($affiliation->payment_frequency ?? 'ANUAL')),
            ])->save();
        }

        return ['created' => $created, 'annual' => $annual, 'delta' => $delta];
    }

    /**
     * Upgrades de cada persona del padrón copiada a la afiliación corporativa.
     *
     * Los afiliados se insertan por lotes, sin ids devueltos: se emparejan en el
     * mismo orden en que se insertaron y se verifica que la cédula coincida en
     * cada par. Cualquier descuadre lanza y revierte toda la creación.
     *
     * @param  list<array{population_id: int, nro_identificacion: mixed, age: mixed}>  $inserted  personas en orden de inserción
     * @param  array<int, array{upgrades?: list<array<string, mixed>>}>  $placements  `affiliationPlacements()['affiliates']`
     * @return int asignaciones creadas
     */
    public static function assignCorporate(AffiliationCorporate $affiliation, array $inserted, array $placements): int
    {
        $hasUpgrades = false;

        foreach ($placements as $placement) {
            if (self::validUpgrades((array) ($placement['upgrades'] ?? [])) !== []) {
                $hasUpgrades = true;

                break;
            }
        }

        if (! $hasUpgrades) {
            return 0;
        }

        $affiliates = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->getKey())
            ->orderBy('id')
            ->get(['id', 'nro_identificacion', 'age']);

        if ($affiliates->count() !== count($inserted)) {
            throw new RuntimeException('No se pudieron asignar los upgrades: la afiliación tiene '.$affiliates->count().' afiliado(s) y se copiaron '.count($inserted).'.');
        }

        $actor = self::actor();
        $now = now();
        $rows = [];

        foreach (array_values($inserted) as $position => $person) {
            $affiliate = $affiliates[$position];

            if (trim((string) $affiliate->nro_identificacion) !== trim((string) $person['nro_identificacion'])) {
                throw new RuntimeException('No se pudieron asignar los upgrades: el orden de los afiliados copiados no coincide con el padrón.');
            }

            foreach (self::validUpgrades((array) ($placements[$person['population_id']]['upgrades'] ?? [])) as $upgrade) {
                $rows[] = [
                    'affiliate_corporate_id' => $affiliate->getKey(),
                    'affiliation_corporate_id' => $affiliation->getKey(),
                    ...self::attributes($upgrade, UpgradePricing::ageOf($affiliate), $actor),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AffiliateCorporateUpgrade::query()->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<int, mixed>  $upgrades
     * @return list<array<string, mixed>>
     */
    private static function validUpgrades(array $upgrades): array
    {
        return array_values(array_filter(
            $upgrades,
            static fn (mixed $upgrade): bool => is_array($upgrade)
                && is_numeric($upgrade['upgrade_id'] ?? null)
                && is_numeric($upgrade['upgrade_coverage_id'] ?? null)
                && is_numeric($upgrade['amount'] ?? null)
                && (float) $upgrade['amount'] > 0,
        ));
    }

    /**
     * @param  array<string, mixed>  $upgrade
     * @return array<string, mixed>
     */
    private static function attributes(array $upgrade, ?int $age, string $actor): array
    {
        return [
            'upgrade_id' => (int) $upgrade['upgrade_id'],
            'upgrade_coverage_id' => (int) $upgrade['upgrade_coverage_id'],
            'upgrade_coverage_age_range_id' => is_numeric($upgrade['upgrade_coverage_age_range_id'] ?? null) ? (int) $upgrade['upgrade_coverage_age_range_id'] : null,
            'name' => UpgradePricing::normalizeName((string) ($upgrade['name'] ?? '')),
            'coverage_amount' => is_numeric($upgrade['coverage_amount'] ?? null) ? round((float) $upgrade['coverage_amount'], 2) : null,
            'age_at_assignment' => $age !== null ? max(0, min(255, $age)) : null,
            'amount' => round((float) $upgrade['amount'], 2),
            'status' => Upgrade::STATUS_ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ];
    }

    private static function actor(): string
    {
        $actor = trim((string) (Auth::user()?->name ?? ''));

        return $actor !== '' ? $actor : 'system';
    }
}

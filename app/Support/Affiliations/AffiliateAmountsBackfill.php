<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Upgrades\IndividualAffiliateUpgradeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Completa plan, cobertura, rango y tarifa de los afiliados de una afiliación individual que
 * quedaron sin montos (pre-afiliaciones de Agentes/Master/General creadas antes del 26/11/2026,
 * cuando el alta no los calculaba). Usa el mismo cálculo que «Asociar montos».
 *
 * Nunca cambia la tarifa ni la cuota de la afiliación: si la suma de los afiliados no cuadra con
 * la tarifa de la afiliación, no toca nada y lo deja para revisión manual.
 */
final class AffiliateAmountsBackfill
{
    public const STATUS_READY = 'LISTA';

    public const STATUS_NOTHING = 'SIN PENDIENTES';

    public const STATUS_NO_FEE = 'SIN TARIFA';

    public const STATUS_MISMATCH = 'NO CUADRA';

    public const LOG_ACTION = 'MONTOS DE AFILIADOS COMPLETADOS';

    public function __construct(private readonly AffiliationAffiliateFeeCalculator $calculator) {}

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * Afiliaciones individuales vigentes (no excluidas) con algún afiliado vigente sin tarifa.
     *
     * @return Collection<int, Affiliation>
     */
    public function affiliationsWithMissingAmounts(): Collection
    {
        return self::current(Affiliation::query())
            ->whereHas('affiliates', fn (Builder $query) => self::missingAmounts(self::current($query)))
            ->orderBy('id')
            ->get();
    }

    /**
     * Qué se haría con la afiliación, sin escribir nada.
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     lines: list<array{affiliate_id: int, name: string, age: int|null, annual_fee: float|null, period_amount: float|null, age_range_id: int|null, coverage_id: int|null}>,
     *     affiliation_fee: float,
     *     affiliates_sum: float|null
     * }
     */
    public function plan(Affiliation $affiliation): array
    {
        $affiliates = self::current(Affiliate::query()->where('affiliation_id', $affiliation->getKey()))
            ->orderBy('id')
            ->get();
        $pending = $affiliates->filter(fn (Affiliate $affiliate): bool => self::isMissing($affiliate));
        $affiliationFee = round((float) $affiliation->fee_anual, 2);
        $frequency = (string) ($affiliation->payment_frequency ?: 'ANUAL');

        if ($pending->isEmpty()) {
            return [
                'status' => self::STATUS_NOTHING,
                'message' => 'Todos los afiliados ya tienen montos.',
                'lines' => [],
                'affiliation_fee' => $affiliationFee,
                'affiliates_sum' => round((float) $affiliates->sum('fee'), 2),
            ];
        }

        $lines = [];
        $withoutFee = [];

        foreach ($pending as $affiliate) {
            $amounts = $this->calculator->calculateAffiliateAmounts($affiliation, $affiliate);
            $annual = $amounts === null
                ? null
                : round((float) $amounts['annual_fee'] + IndividualAffiliateUpgradeManager::activeTotalFor($affiliate), 2);

            if ($amounts === null) {
                $withoutFee[] = trim((string) $affiliate->full_name);
            }

            $lines[] = [
                'affiliate_id' => (int) $affiliate->getKey(),
                'name' => trim((string) $affiliate->full_name),
                'age' => is_numeric($affiliate->age) ? (int) $affiliate->age : null,
                'annual_fee' => $annual,
                'period_amount' => $annual === null ? null : $this->calculator->totalAmountForPaymentFrequency($annual, $frequency),
                'age_range_id' => $amounts['age_range_id'] ?? null,
                'coverage_id' => $amounts['coverage_id'] ?? null,
            ];
        }

        if ($withoutFee !== []) {
            return [
                'status' => self::STATUS_NO_FEE,
                'message' => 'Sin tarifa en este plan y cobertura para: '.implode(', ', $withoutFee).'. Revísela a mano.',
                'lines' => $lines,
                'affiliation_fee' => $affiliationFee,
                'affiliates_sum' => null,
            ];
        }

        $alreadyPriced = (float) $affiliates->reject(fn (Affiliate $affiliate): bool => self::isMissing($affiliate))->sum('fee');
        $sum = round($alreadyPriced + array_sum(array_column($lines, 'annual_fee')), 2);

        if (abs($sum - $affiliationFee) >= 0.01) {
            return [
                'status' => self::STATUS_MISMATCH,
                'message' => 'La suma de los afiliados (US$ '.self::money($sum).') no cuadra con la tarifa de la afiliación (US$ '
                    .self::money($affiliationFee).'). No se cambia nada: revísela a mano.',
                'lines' => $lines,
                'affiliation_fee' => $affiliationFee,
                'affiliates_sum' => $sum,
            ];
        }

        return [
            'status' => self::STATUS_READY,
            'message' => 'Cuadra con la tarifa de la afiliación.',
            'lines' => $lines,
            'affiliation_fee' => $affiliationFee,
            'affiliates_sum' => $sum,
        ];
    }

    /**
     * Completa los afiliados sin montos y deja constancia en la bitácora. Solo actúa si el plan
     * está {@see STATUS_READY}; todo o nada por afiliación.
     *
     * @return array{status: string, message: string, lines: list<array<string, mixed>>, affiliation_fee: float, affiliates_sum: float|null}
     */
    public function apply(Affiliation $affiliation, string $actor): array
    {
        return DB::transaction(function () use ($affiliation, $actor): array {
            /** @var Affiliation $locked */
            $locked = Affiliation::query()->whereKey($affiliation->getKey())->lockForUpdate()->firstOrFail();
            $plan = $this->plan($locked);

            if ($plan['status'] !== self::STATUS_READY) {
                return $plan;
            }

            foreach ($plan['lines'] as $line) {
                $affiliate = Affiliate::query()->whereKey($line['affiliate_id'])->lockForUpdate()->firstOrFail();

                if (! $this->calculator->applyAmountsToAffiliate($locked, $affiliate)) {
                    throw new \RuntimeException('No se pudo calcular la tarifa de '.$line['name'].'.');
                }
            }

            $locked->status_log_affiliations()->create([
                'affiliation_id' => $locked->getKey(),
                'action' => self::LOG_ACTION,
                'observation' => 'Se completaron plan, cobertura, rango y tarifa de: '
                    .implode('; ', array_map(
                        fn (array $line): string => $line['name'].' — US$ '.self::money((float) $line['annual_fee']).' al año',
                        $plan['lines'],
                    ))
                    .'. La tarifa de la afiliación no cambió (US$ '.self::money($plan['affiliation_fee']).').',
                'updated_by' => $actor,
            ]);

            return $plan;
        });
    }

    private static function isMissing(Affiliate $affiliate): bool
    {
        return $affiliate->fee === null || (float) $affiliate->fee <= 0;
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private static function current(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->whereNull('status')->orWhere('status', '!=', 'EXCLUIDO'));
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private static function missingAmounts(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->whereNull('fee')->orWhere('fee', '<=', 0));
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}

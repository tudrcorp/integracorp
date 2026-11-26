<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\DetailIndividualQuote;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Montos de una pre-afiliación individual hecha por la red comercial (agentes, agencias master y
 * general), calculados en el servidor con la tarifa vigente según la edad de cada persona. Es la
 * misma regla de «Asociar montos» de Negocios ({@see AffiliationAffiliateFeeCalculator}).
 *
 * Reglas:
 * - Lo que se guarda en la afiliación es la suma de las tarifas reales de sus afiliados, nunca el
 *   subtotal de la cotización ni lo que mande el navegador.
 * - Si una persona no tiene tarifa (edad fuera de los rangos del plan, sin fecha de nacimiento o
 *   sin cobertura), la pre-afiliación no se guarda: se le dice al agente quién y por qué.
 * - La cotización no se modifica: es la propuesta que recibió el cliente. Si los montos reales
 *   difieren de lo cotizado, queda constancia en la bitácora de la afiliación.
 */
final class IndividualPreAffiliationPricing
{
    public const FREQUENCIES = ['ANUAL', 'SEMESTRAL', 'TRIMESTRAL', 'MENSUAL'];

    public function __construct(private readonly AffiliationAffiliateFeeCalculator $calculator) {}

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * Calcula los montos de cada persona y del grupo.
     *
     * @param  list<array{name?: mixed, birth_date?: mixed}>  $people
     * @return array{
     *     lines: list<array{name: string, age: int|null, annual_fee: float|null, period_amount: float|null, age_range_id: int|null, coverage_id: int|null, problem: string|null}>,
     *     problems: list<string>,
     *     fee_anual: float,
     *     total_amount: float,
     *     persons: int
     * }
     */
    public function price(?int $planId, ?int $coverageId, ?string $frequency, array $people): array
    {
        $frequency = self::frequency($frequency);
        $lines = [];
        $problems = [];
        $annual = 0.0;
        $noCoverages = $planId !== null && $this->calculator->planHasNoCoverages($planId);

        foreach ($people as $index => $person) {
            $name = trim((string) ($person['name'] ?? '')) ?: 'Afiliado '.($index + 1);
            $age = $this->age($person['birth_date'] ?? null);
            $line = ['name' => $name, 'age' => $age, 'annual_fee' => null, 'period_amount' => null, 'age_range_id' => null, 'coverage_id' => null, 'problem' => null];

            if ($planId === null) {
                $line['problem'] = 'falta elegir el plan';
            } elseif (! $noCoverages && $coverageId === null) {
                $line['problem'] = 'falta elegir la cobertura';
            } elseif ($age === null) {
                $line['problem'] = 'falta una fecha de nacimiento válida';
            } else {
                $amounts = $this->calculator->calculateAmountsForPlanCoverageAndAge($planId, $noCoverages ? null : $coverageId, $age, $frequency);

                if ($amounts === null) {
                    $line['problem'] = 'con '.$age.' años no tiene tarifa en este plan y cobertura';
                } else {
                    $line['annual_fee'] = round((float) $amounts['annual_fee'], 2);
                    $line['period_amount'] = round((float) $amounts['period_amount'], 2);
                    $line['age_range_id'] = $amounts['age_range_id'] !== null ? (int) $amounts['age_range_id'] : null;
                    $line['coverage_id'] = $amounts['coverage_id'] !== null ? (int) $amounts['coverage_id'] : null;
                    $annual += $line['annual_fee'];
                }
            }

            if ($line['problem'] !== null) {
                $problems[] = $name.': '.$line['problem'].'.';
            }

            $lines[] = $line;
        }

        if ($people === []) {
            $problems[] = 'No hay personas para afiliar.';
        }

        $annual = round($annual, 2);

        return [
            'lines' => $lines,
            'problems' => $problems,
            'fee_anual' => $annual,
            'total_amount' => $this->calculator->totalAmountForPaymentFrequency($annual, $frequency),
            'persons' => count($people),
        ];
    }

    /**
     * Personas del formulario de pre-afiliación: el titular y, si se incluyen, los afiliados
     * adicionales del repetidor.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{name: mixed, birth_date: mixed}>
     */
    public static function peopleFromFormData(array $data): array
    {
        $people = [['name' => $data['full_name_ti'] ?? null, 'birth_date' => $data['birth_date_ti'] ?? null]];

        if (filter_var($data['feedback'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            foreach ((array) ($data['affiliates'] ?? []) as $affiliate) {
                if (is_array($affiliate)) {
                    $people[] = ['name' => $affiliate['full_name'] ?? null, 'birth_date' => $affiliate['birth_date'] ?? null];
                }
            }
        }

        return $people;
    }

    /**
     * Antes de crear: valida que todas las personas tengan tarifa y fija en los datos los montos
     * calculados (reemplaza lo que haya llegado del navegador).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function applyToFormData(array $data): array
    {
        $result = $this->price(
            self::intOrNull($data['plan_id'] ?? null),
            self::intOrNull($data['coverage_id'] ?? null),
            $data['payment_frequency'] ?? null,
            self::peopleFromFormData($data),
        );

        if ($result['problems'] !== []) {
            throw ValidationException::withMessages([
                'data.fee_anual' => 'No se puede guardar la pre-afiliación: '.implode(' ', $result['problems']),
            ]);
        }

        $data['fee_anual'] = $result['fee_anual'];
        $data['total_amount'] = $result['total_amount'];
        $data['family_members'] = $result['persons'];

        return $data;
    }

    /**
     * Después de crear los afiliados (dentro de la misma transacción): asigna a cada uno su plan,
     * cobertura, rango y tarifa, y deja la afiliación con la suma real.
     *
     * @return array{fee_anual: float, total_amount: float, persons: int, quoted_fee_anual: float|null}
     *
     * @throws ValidationException si algún afiliado quedó sin tarifa
     */
    public function applyToAffiliation(Affiliation $affiliation): array
    {
        $frequency = self::frequency($affiliation->payment_frequency);
        $affiliation->payment_frequency = $frequency;

        $affiliates = Affiliate::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', '!=', 'EXCLUIDO')
            ->orderBy('id')
            ->get();

        if ($affiliates->isEmpty()) {
            throw ValidationException::withMessages(['data.fee_anual' => 'La pre-afiliación quedó sin afiliados.']);
        }

        $missing = [];

        foreach ($affiliates as $affiliate) {
            $age = $this->age($affiliate->birth_date);

            if ($age !== null && (int) $affiliate->age !== $age) {
                $affiliate->age = $age;
                $affiliate->save();
            }

            if (! $this->calculator->applyAmountsToAffiliate($affiliation, $affiliate)) {
                $missing[] = trim((string) $affiliate->full_name).($age !== null ? ' ('.$age.' años)' : ' (sin fecha de nacimiento)');
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'data.fee_anual' => 'No se puede guardar la pre-afiliación: sin tarifa en este plan y cobertura para '.implode(', ', $missing).'.',
            ]);
        }

        $annual = round((float) Affiliate::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', '!=', 'EXCLUIDO')
            ->sum('fee'), 2);

        $affiliation->fee_anual = $annual;
        $affiliation->total_amount = UpgradeFirstPaymentBilling::individualRegular($affiliation, $annual, $frequency);
        $affiliation->family_members = $affiliates->count();
        $affiliation->save();

        $quoted = $this->quotedAnnual($affiliation);

        if ($quoted !== null && abs($quoted - $annual) >= 0.01) {
            $affiliation->status_log_affiliations()->create([
                'affiliation_id' => $affiliation->getKey(),
                'action' => 'MONTOS CALCULADOS POR EDAD',
                'observation' => 'Tarifa anual según las edades de los afiliados: US$ '.number_format($annual, 2, ',', '.')
                    .'. La cotización indicaba US$ '.number_format($quoted, 2, ',', '.').' (las edades o el número de personas cambiaron al pre-afiliar).',
                'updated_by' => (string) (auth()->user()?->name ?? 'SISTEMA'),
            ]);
        }

        return [
            'fee_anual' => $annual,
            'total_amount' => (float) $affiliation->total_amount,
            'persons' => $affiliates->count(),
            'quoted_fee_anual' => $quoted,
        ];
    }

    /**
     * Subtotal anual cotizado para el plan y la cobertura elegidos (suma de los rangos de edad).
     */
    public function quotedAnnual(Affiliation $affiliation): ?float
    {
        return $this->quotedAnnualFor(
            self::intOrNull($affiliation->individual_quote_id),
            self::intOrNull($affiliation->plan_id),
            self::intOrNull($affiliation->coverage_id),
        );
    }

    public function quotedAnnualFor(?int $quoteId, ?int $planId, ?int $coverageId): ?float
    {
        if ($quoteId === null || $planId === null) {
            return null;
        }

        $noCoverages = $this->calculator->planHasNoCoverages($planId);

        if (! $noCoverages && $coverageId === null) {
            return null;
        }

        $rows = DetailIndividualQuote::query()
            ->where('individual_quote_id', $quoteId)
            ->where('plan_id', $planId)
            ->when(! $noCoverages, fn ($query) => $query->where('coverage_id', $coverageId))
            ->get(['subtotal_anual']);

        return $rows->isEmpty() ? null : round((float) $rows->sum('subtotal_anual'), 2);
    }

    /**
     * Edad a hoy desde la fecha de nacimiento del formulario (`d-m-Y`, `d/m/Y` o `Y-m-d`).
     */
    public function age(mixed $birthDate): ?int
    {
        if ($birthDate instanceof \DateTimeInterface) {
            $parsed = Carbon::instance($birthDate);
        } else {
            $parsed = $this->calculator->parseBirthDate(is_scalar($birthDate) ? (string) $birthDate : null);
        }

        if ($parsed === null || $parsed->isFuture()) {
            return null;
        }

        return (int) $parsed->age;
    }

    public static function frequency(mixed $frequency): string
    {
        $frequency = mb_strtoupper(trim((string) $frequency));

        return in_array($frequency, self::FREQUENCIES, true) ? $frequency : 'ANUAL';
    }

    public static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\IndividualQuotes;

use App\Http\Controllers\LogController;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * «Comprar plan» desde una cotización individual (agentes, agencias master y general): aprueba la
 * cotización y deja listo el formulario de pre-afiliación.
 *
 * En `detail_individual_quotes`, `total_persons` cuenta las personas DE ESE RANGO DE EDAD (una fila
 * por plan, cobertura y rango). El total del grupo familiar es la suma de los rangos de una misma
 * cobertura: tomar una sola fila como total hacía que el alta «corrigiera» la cotización con un
 * conteo equivocado en todos los rangos.
 */
final class IndividualQuotePurchase
{
    public const STATUS_APPROVED = 'APROBADA';

    public const STATUS_EXECUTED = 'EJECUTADA';

    /**
     * Personas del grupo familiar cotizado para un plan (y cobertura, si se indica).
     */
    public static function persons(IndividualQuote $quote, ?int $planId = null, ?int $coverageId = null): int
    {
        $rows = DetailIndividualQuote::query()
            ->where('individual_quote_id', $quote->getKey())
            ->get(['plan_id', 'coverage_id', 'age_range_id', 'total_persons']);

        if ($rows->isEmpty()) {
            return 1;
        }

        $planId ??= (int) $rows->first()->plan_id;
        $rows = $rows->where('plan_id', $planId);

        if ($coverageId !== null && $rows->contains('coverage_id', $coverageId)) {
            $rows = $rows->where('coverage_id', $coverageId);
        }

        $persons = $rows->groupBy('age_range_id')
            ->sum(fn ($range): int => (int) $range->max('total_persons'));

        return max(1, $persons);
    }

    /**
     * Deja en la sesión solo lo que el formulario de pre-afiliación necesita de esta cotización.
     * Borra la selección que hubiera quedado de otra cotización.
     */
    public static function prepareSession(IndividualQuote $quote, ?int $planId = null, ?int $coverageId = null): int
    {
        $persons = self::persons($quote, $planId, $coverageId);

        session()->forget('data_records');
        session()->put('persons', $persons);

        return $persons;
    }

    /**
     * Igual que «Comprar plan» del listado: solo mientras la cotización no esté aprobada ni
     * convertida en afiliación.
     */
    public static function canPurchase(IndividualQuote $quote): bool
    {
        return ! in_array($quote->status, [self::STATUS_APPROVED, self::STATUS_EXECUTED], true);
    }

    /**
     * Mismo comportamiento que «Comprar plan» del listado: aprueba la cotización, prepara la sesión
     * y devuelve la URL del formulario de pre-afiliación del panel (con el plan ya elegido si la
     * cotización tiene uno solo).
     *
     * @throws RuntimeException si la cotización ya está aprobada o convertida en afiliación
     */
    public static function start(IndividualQuote $quote, string $panelId): string
    {
        $quote = DB::transaction(function () use ($quote): IndividualQuote {
            $locked = IndividualQuote::query()->whereKey($quote->getKey())->lockForUpdate()->first();

            if (! $locked instanceof IndividualQuote) {
                throw new RuntimeException('La cotización ya no existe.');
            }

            if (! self::canPurchase($locked)) {
                throw new RuntimeException($locked->status === self::STATUS_EXECUTED
                    ? 'Esta cotización ya se convirtió en afiliación.'
                    : 'Esta cotización ya fue aprobada.');
            }

            $locked->status = self::STATUS_APPROVED;
            $locked->save();

            return $locked;
        });

        $plans = DetailIndividualQuote::query()->where('individual_quote_id', $quote->getKey())->distinct()->pluck('plan_id');
        $planId = $plans->count() === 1 ? (int) $plans->first() : null;

        self::prepareSession($quote, $planId);

        if (Auth::id() !== null) {
            LogController::log(Auth::id(), 'Aprobacion directa de la cotizacion Nro.'.$quote->code, 'Modulo Cotizacion Individual', self::STATUS_APPROVED);
        }

        return route('filament.'.$panelId.'.resources.affiliations.create', ['id' => $quote->getKey(), 'plan_id' => $planId]);
    }
}

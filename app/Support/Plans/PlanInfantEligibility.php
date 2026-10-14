<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Models\AgeRange;
use App\Models\Plan;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\PatientAge;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Throwable;

/**
 * Un menor de un año solo entra a un plan que tenga un rango de edad que
 * cubra los 0 años. Si el plan arranca en 1 año (Funerario Corporativo,
 * Escolar AP, Repsol…), se rechaza con un mensaje que dice por qué y qué hacer,
 * en lugar de dejar al afiliado sin tarifa.
 *
 * La edad sale de la fecha de nacimiento (la edad guardada envejece mal: no se
 * actualiza sola). Un plan sin rangos configurados no se bloquea: no hay con
 * qué decidir, y esos planes no tarifan por edad.
 */
final class PlanInfantEligibility
{
    public static function rejectionMessage(mixed $planId, mixed $birthDate, mixed $storedAge = null, mixed $coverageId = null): ?string
    {
        $planId = is_numeric($planId) ? (int) $planId : 0;

        if ($planId <= 0 || ! PatientAge::isUnderOneYear($birthDate, $storedAge)) {
            return null;
        }

        $ranges = AgeRange::query()->where('plan_id', $planId)->get(['id', 'age_init', 'age_end', 'range', 'coverage_id']);

        if ($ranges->isEmpty()) {
            return null;
        }

        $coverageId = is_numeric($coverageId) && (int) $coverageId > 0 ? (int) $coverageId : null;

        if ((new AffiliationAffiliateFeeCalculator)->affiliateAgeFitsPlanAgeRanges($planId, 0, $coverageId)) {
            return null;
        }

        $planName = trim((string) Plan::query()->whereKey($planId)->value('description'));
        $minimum = $ranges->pluck('age_init')->filter(fn (mixed $age): bool => is_numeric($age))->min();

        $start = $minimum !== null
            ? ': su primer rango de edad empieza en '.((int) $minimum === 1 ? '1 año' : ((int) $minimum).' años')
            : '';

        return 'El plan «'.($planName !== '' ? $planName : 'seleccionado').'» no admite afiliados menores de 1 año'.$start
            .'. Elija otro plan o solicite a Negocios un rango de edad desde 0 años.';
    }

    /**
     * Regla lista para `DatePicker::make('birth_date')->rule(...)` en los
     * formularios de afiliados. El plan se toma, en este orden, del campo
     * `plan_id` del mismo formulario, del `plan_id` del formulario padre (el
     * afiliado va en un repeater del asistente de afiliación), del plan del
     * registro dueño del relation manager (la afiliación individual) o del
     * registro que se edita (datos personales del afiliado en Operaciones).
     */
    public static function filamentBirthDateRule(): Closure
    {
        return static fn (Get $get, mixed $livewire): Closure => self::rule(
            static fn (): mixed => self::firstFilled([
                static fn (): mixed => $get('plan_id'),
                static fn (): mixed => $get('../../plan_id'),
                static fn (): mixed => is_object($livewire) && method_exists($livewire, 'getOwnerRecord')
                    ? $livewire->getOwnerRecord()?->getAttribute('plan_id')
                    : null,
                static fn (): mixed => is_object($livewire) && method_exists($livewire, 'getRecord')
                    ? $livewire->getRecord()?->getAttribute('plan_id')
                    : null,
            ]),
            static fn (): mixed => self::firstFilled([
                static fn (): mixed => $get('coverage_id'),
                static fn (): mixed => $get('../../coverage_id'),
            ]),
        );
    }

    /**
     * @param  list<Closure(): mixed>  $candidates
     */
    private static function firstFilled(array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            try {
                $value = $candidate();
            } catch (Throwable) {
                continue;
            }

            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Regla para el campo de fecha de nacimiento de un formulario Filament.
     *
     * @param  Closure(): mixed  $planId  resuelve el plan en el momento de validar
     * @param  (Closure(): mixed)|null  $coverageId
     */
    public static function rule(Closure $planId, ?Closure $coverageId = null): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($planId, $coverageId): void {
            $message = self::rejectionMessage($planId(), $value, null, $coverageId?->__invoke());

            if ($message !== null) {
                $fail($message);
            }
        };
    }
}

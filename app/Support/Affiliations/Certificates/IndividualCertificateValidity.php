<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\Affiliation;
use App\Support\Collections\CollectionDueDate;
use Carbon\CarbonImmutable;

/**
 * Vigencia «Desde / Hasta» del certificado y las tarjetas de una afiliación individual.
 *
 * `effective_date` tiene dos significados en los datos:
 * - Primer año (sin renovaciones): quedó como FIN del año (regla vieja «primer pago + 1 año»),
 *   así que la vigencia va de la emisión (`activated_at`) a esa fecha.
 * - Renovada, o cuando la fecha no queda al menos {@see self::MIN_MONTHS_AFTER_ISSUE} meses
 *   después de la emisión: es el INICIO del ciclo y la vigencia es ese día más un año. En una
 *   renovada la emisión original no sirve: puede ser de hace años.
 * - Si esa fecha ya pasó, tampoco puede ser el fin del primer año de una póliza que sigue
 *   emitiendo documentos: es el inicio del ciclo actual de una renovación que no quedó en el
 *   historial. Leerla como fin imprimiría un certificado vencido.
 */
final class IndividualCertificateValidity
{
    /** Por debajo de esto la fecha es un inicio corrido unos días, no el fin del primer año. */
    public const int MIN_MONTHS_AFTER_ISSUE = 6;

    /**
     * @return array{desde: string, hasta: string}
     */
    public static function forAffiliation(Affiliation $affiliation): array
    {
        return self::resolve(
            $affiliation->activated_at,
            $affiliation->effective_date,
            $affiliation->renovationHistories()->exists(),
        );
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public static function resolve(mixed $activatedAt, mixed $effectiveDate, bool $renewed, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $effective = CollectionDueDate::parse($effectiveDate);

        if ($effective === null) {
            return ['desde' => '', 'hasta' => ''];
        }

        $issued = CollectionDueDate::parse($activatedAt);

        $isFirstYearEnd = ! $renewed
            && $issued !== null
            && $effective->greaterThanOrEqualTo($issued->addMonthsNoOverflow(self::MIN_MONTHS_AFTER_ISSUE))
            && $effective->greaterThanOrEqualTo($today);

        if ($isFirstYearEnd) {
            return [
                'desde' => $issued->format(CollectionDueDate::DISPLAY_FORMAT),
                'hasta' => $effective->format(CollectionDueDate::DISPLAY_FORMAT),
            ];
        }

        return [
            'desde' => $effective->format(CollectionDueDate::DISPLAY_FORMAT),
            'hasta' => $effective->addYearNoOverflow()->format(CollectionDueDate::DISPLAY_FORMAT),
        ];
    }
}

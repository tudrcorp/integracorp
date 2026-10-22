<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateInconsistencies;

use App\Enums\AffiliationDateInconsistencyKind as Kind;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Collections\CollectionDueDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Revisa las fechas de una afiliación activa y devuelve cada inconsistencia explicada:
 * qué está mal (con los datos reales), por qué importa, cómo se repara y qué fecha sugiere.
 *
 * Solo lee. La fuente de verdad del calendario son los avisos POR PAGAR
 * ({@see AffiliationDateCorrector::calendar()}); la vigencia guardada se contrasta contra ellos.
 *
 * Las cuatro inconsistencias de la vigencia (vacía, imposible, como fin del año, no coincide
 * con los avisos) son excluyentes: se informa la más grave. La renovación fuera de fecha solo
 * se informa cuando la vigencia está bien; si no, ya va explicada en la de la vigencia.
 */
final class DateInconsistencyDetector
{
    /** Días entre emisión y primer pago a partir de los cuales la emisión es sospechosa. */
    public const PAYMENT_GAP_DAYS = 30;

    /** Una afiliación cargada después de su emisión es histórica: su primer pago puede ser tardío. */
    public const CREATED_AFTER_EMISSION_TOLERANCE_DAYS = 7;

    /** Más allá de esto, una vigencia sin renovaciones es imposible. */
    public const MAX_FIRST_YEAR_MONTHS = 13;

    /**
     * @return list<array{
     *     kind: string,
     *     severity: string,
     *     title: string,
     *     explanation: string,
     *     consequence: string,
     *     repair: string,
     *     repair_mode: string,
     *     facts: array<string, string>,
     *     suggestions: list<array{date: string, label: string}>,
     *     fingerprint: string
     * }>
     */
    public static function inspect(Model $affiliation, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $subject = DateCorrectionSubject::for($affiliation);
        $calendar = AffiliationDateCorrector::calendar($affiliation);

        $activated = CollectionDueDate::parse($affiliation->activated_at);
        $effectiveRaw = trim((string) $affiliation->effective_date);
        $effective = CollectionDueDate::parse($effectiveRaw);
        $anchor = $calendar['anchor'];
        $evidence = $calendar['aligned'] !== [];
        $current = match (true) {
            $evidence => $calendar['current_start'],
            $calendar['renewed'] => $effective,
            // Sin avisos que lo contradigan, una vigencia que es aniversario exacto de la emisión y ya llegó
            // es el inicio del ciclo actual (pólizas renovadas antes de existir el historial).
            self::isReachedAnniversary($effective, $activated, $today) => $effective,
            default => $activated,
        };
        $offset = $calendar['years_offset'];
        $renewalsAccepted = DB::table($subject->historiesTable)->where($subject->historiesForeignKey, $affiliation->getKey())->count();

        $openRenewals = DB::table($subject->renovationsTable)
            ->where($subject->renovationsForeignKey, $affiliation->getKey())
            ->whereIn('status', [AffiliationDateCorrector::RENOVATION_OPEN, AffiliationDateCorrector::RENOVATION_PERIOD])
            ->orderBy('id')
            ->pluck('date_renewal')
            ->map(fn (mixed $date): ?CarbonImmutable => CollectionDueDate::parse($date))
            ->filter()
            ->values();

        $firstPaymentAt = DB::table($subject->paymentsTable)
            ->where($subject->paymentsForeignKey, $affiliation->getKey())
            ->whereIn('status', AffiliationDateCorrector::APPROVED_PAYMENT_STATUSES)
            ->orderBy('id')
            ->value('created_at');
        $firstPayment = $firstPaymentAt !== null ? CarbonImmutable::parse((string) $firstPaymentAt)->startOfDay() : null;

        $facts = [
            'Tipo de afiliación' => $subject->type === DateCorrectionSubject::TYPE_CORPORATE ? 'Corporativa' : 'Individual',
            'Fecha de emisión' => $activated?->format('d/m/Y') ?? self::raw($affiliation->activated_at),
            'Fecha de vigencia guardada' => $effective?->format('d/m/Y') ?? self::raw($effectiveRaw),
            'Inicio del ciclo según los avisos' => $evidence ? $calendar['current_start']->format('d/m/Y') : 'Sin avisos POR PAGAR que lo indiquen',
            'Renovación que corresponde' => $current?->addYearNoOverflow()->format('d/m/Y') ?? '—',
            'Propuestas de renovación abiertas' => $openRenewals->isEmpty() ? 'Ninguna' : $openRenewals->map(fn (CarbonImmutable $date): string => $date->format('d/m/Y'))->implode(', '),
            'Frecuencia de pago' => (string) ($calendar['frequency'] ?? $affiliation->payment_frequency ?? '—'),
            'Avisos POR PAGAR' => self::invoices($calendar['aligned'], $calendar['misaligned']),
            'Primer pago aprobado' => $firstPayment?->format('d/m/Y') ?? 'Sin pagos aprobados',
            'Renovaciones aceptadas' => (string) $renewalsAccepted,
        ];

        $findings = [];
        $anchorLabel = $anchor?->format('d/m/Y');

        if ($effective === null) {
            $suggestions = self::suggestions([
                [$anchor, 'Según el calendario de los avisos'],
                [$activated, 'Fecha de emisión'],
                [$firstPayment, 'Fecha del primer pago aprobado'],
            ]);

            $findings[] = self::finding(
                Kind::VigenciaVacia,
                'La afiliación no tiene fecha de vigencia'.($effectiveRaw !== '' ? ' legible («'.$effectiveRaw.'»)' : '').'.',
                'Sin vigencia, la afiliación no entra en la renovación automática: nadie recibe el aviso de que se vence, y los cupos clínicos no tienen un período de conteo confiable.',
                $suggestions === []
                    ? 'No hay datos para sugerir una fecha (sin avisos, sin emisión ni pagos). Debe revisarse con Negocios y corregirse con «Corregir emisión y vigencia».'
                    : 'Se fija la vigencia en el inicio del ciclo de cobro (sugerida: '.$suggestions[0]['date'].'), y la renovación queda un año después.',
                $facts,
                $suggestions,
            );
        } elseif (self::isImpossible($effective, $activated, $calendar['renewed'], $today)) {
            $findings[] = self::finding(
                Kind::VigenciaAbsurda,
                'La vigencia guardada ('.$effective->format('d/m/Y').') no es posible para esta póliza: '
                    .self::impossibleReason($effective, $activated, $calendar['renewed'], $today).'.',
                'La renovación se propondría el '.$effective->addYearNoOverflow()->format('d/m/Y').', fuera de toda lógica, y el período de los cupos clínicos queda corrido. Suele ser un error de tipeo (por ejemplo, un año de más).',
                'El sistema no puede saber cuál es la fecha real: elija la correcta. Se mueven la vigencia, los avisos POR PAGAR y la renovación.',
                $facts,
                self::suggestions([
                    [$evidence ? $anchor : null, 'Según el calendario de los avisos'],
                    [self::previousEffectiveOfLastRenewal($affiliation, $subject), 'Vigencia que tenía antes de la última renovación aceptada (posible inicio real del ciclo)'],
                    [self::anniversary($activated, $today, false), 'Último aniversario de la emisión'],
                    [self::anniversary($activated, $today, true), 'Próximo aniversario de la emisión'],
                ], $effective),
            );
        } elseif (! $calendar['renewed'] && $current !== null && $effective->isSameDay($current->addYearNoOverflow())
            && ($evidence || $effective->greaterThan($today))) {
            // Sin avisos que lo confirmen solo es seguro si esa fecha aún no llegó: un ciclo no empieza en el futuro.
            $findings[] = self::finding(
                Kind::VigenciaComoFin,
                'La vigencia guardada ('.$effective->format('d/m/Y').') es la fecha de FIN del año que empezó el '.$current->format('d/m/Y')
                    .'. El sistema la usa como INICIO del ciclo. Viene de la regla vieja del primer pago («hoy + 1 año»).',
                'La renovación se propone un año tarde (el '.$effective->addYearNoOverflow()->format('d/m/Y').' en vez del '.$effective->format('d/m/Y')
                    .'): la póliza puede vencerse sin que nadie reciba el aviso de renovación, y los cupos clínicos se cuentan con el año corrido.',
                'Se deja la vigencia en el inicio del ciclo ('.$current->format('d/m/Y').') y la renovación en el '.$effective->format('d/m/Y').'. Los avisos ya están en ese calendario: no cambian de fecha.',
                $facts,
                self::suggestions([[$anchor, 'Según el calendario de los avisos']]),
            );
        } elseif ($evidence && $current !== null && ! $effective->isSameDay($current)) {
            $findings[] = self::finding(
                Kind::FechasNoCoinciden,
                'La vigencia guardada ('.$effective->format('d/m/Y').') no coincide con el calendario de los avisos POR PAGAR, que empezó el '.$current->format('d/m/Y').'.',
                'La afiliación dice una fecha y la cobranza cobra con otra: la Cobranza por mes puede marcar cuotas como vencidas cuando no lo están (o al revés), y la renovación se propone con la fecha equivocada. Pasa cuando la fecha se edita a mano sin mover los avisos.',
                'Elija cuál es la correcta. Si es la vigencia guardada, se mueven los avisos y la renovación a ella; si es la de los avisos, se corrige la vigencia.',
                $facts,
                self::suggestions([
                    [$anchor, 'Según el calendario de los avisos'],
                    [$effective->subYearsNoOverflow($offset), 'Según la vigencia guardada'],
                ]),
            );
        } elseif ($current !== null && $openRenewals->isNotEmpty() && $openRenewals->contains(fn (CarbonImmutable $date): bool => ! $date->isSameDay($current->addYearNoOverflow()))) {
            $findings[] = self::finding(
                Kind::RenovacionFueraDeFecha,
                'La propuesta de renovación ('.$facts['Propuestas de renovación abiertas'].') no corresponde a la vigencia: debería ser el '.$current->addYearNoOverflow()->format('d/m/Y')
                    .($openRenewals->count() > 1 ? '. Además hay más de una propuesta abierta.' : '.'),
                'Renovaciones avisa y prepara el cobro de la renovación en la fecha equivocada (o dos veces si hay propuestas duplicadas).',
                'Se deja una sola propuesta de renovación en el '.$current->addYearNoOverflow()->format('d/m/Y').' y se anulan las demás (no se borran).',
                $facts,
                self::suggestions([[$evidence ? $anchor : $current->subYearsNoOverflow($offset), 'Inicio del ciclo actual']]),
            );
        }

        if ($calendar['misaligned'] !== []) {
            $list = implode('; ', array_map(fn (array $row): string => self::misalignedLine($row), $calendar['misaligned']));

            $findings[] = self::finding(
                Kind::AvisoFueraDeCalendario,
                'Hay avisos POR PAGAR cuya fecha no sigue el calendario de su ciclo (inicio + cuotas de '.$calendar['months'].' mes(es)): '.$list.'.',
                'Esos avisos se cobran en fechas que no corresponden a ninguna cuota: el reporte los numera mal y la corrección automática no puede moverlos sin adivinar.',
                'No se repara desde aquí: ajuste cada aviso con «Ajustar cuota» en Gestión de Cobranza. Al volver a revisar, la inconsistencia se cierra sola.',
                $facts + ['Avisos fuera de calendario' => $list],
                [],
            );
        }

        if ($evidence && $offset >= 1 && $anchor !== null) {
            $findings[] = self::finding(
                Kind::CobrandoSinRenovar,
                'Los avisos POR PAGAR son del año '.($offset + 1).' del calendario que empezó el '.$anchor->format('d/m/Y').', pero la póliza no registra una renovación aceptada para ese año.',
                'Se está cobrando un año que no pasó por Renovaciones: no se recalcularon tarifas por edad ni plan, y la renovación de ese año nunca se gestionó.',
                'Es una decisión de negocio: revise la póliza en Renovaciones (renovar con el flujo normal) o confirme con Negocios que el cobro es correcto y márquela como no inconsistencia.',
                $facts,
                [],
            );
        }

        if (! $calendar['renewed'] && $firstPayment !== null && $activated !== null
            && $activated->diffInDays($firstPayment, false) > self::PAYMENT_GAP_DAYS
            && self::createdNearEmission($affiliation, $activated)) {
            $findings[] = self::finding(
                Kind::EmisionLejosDelPrimerPago,
                'La emisión cargada ('.$activated->format('d/m/Y').') es '.(int) $activated->diffInDays($firstPayment).' días anterior al primer pago aprobado ('.$firstPayment->format('d/m/Y')
                    .'), y la afiliación se creó en el sistema con esa fecha.',
                'Al aprobar el primer pago, los avisos del año se calcularon desde la emisión cargada. Si esa fecha no era la real, toda la cobranza quedó corrida (así pasó con TDEC-COR-00054: cuotas vencidas que no lo estaban).',
                'Confirme con Negocios la fecha real de emisión. Si es otra, elíjala y se mueven la afiliación, los avisos POR PAGAR y la renovación. Si la emisión es correcta, márquela como no inconsistencia.',
                $facts,
                self::suggestions([
                    [$firstPayment, 'Fecha del primer pago aprobado'],
                    [$activated, 'Emisión cargada (sin cambio)'],
                ]),
            );
        }

        return $findings;
    }

    private static function isImpossible(CarbonImmutable $effective, ?CarbonImmutable $activated, bool $renewed, CarbonImmutable $today): bool
    {
        return self::impossibleReason($effective, $activated, $renewed, $today) !== null;
    }

    private static function impossibleReason(CarbonImmutable $effective, ?CarbonImmutable $activated, bool $renewed, CarbonImmutable $today): ?string
    {
        if ($effective->greaterThan($today->addMonths(self::MAX_FIRST_YEAR_MONTHS))) {
            return 'está a más de '.self::MAX_FIRST_YEAR_MONTHS.' meses en el futuro';
        }

        // N años exactos después de la emisión y ya alcanzada: renovaciones hechas fuera del historial (pólizas antiguas).
        if (! $renewed && $activated !== null && $effective->greaterThan($activated->addMonths(self::MAX_FIRST_YEAR_MONTHS)) && ! self::isReachedAnniversary($effective, $activated, $today)) {
            return 'está a más de un año de la emisión ('.$activated->format('d/m/Y').') y la póliza no tiene renovaciones aceptadas';
        }

        if (! $renewed && $activated !== null && $effective->lessThan($activated)) {
            return 'es anterior a la emisión ('.$activated->format('d/m/Y').')';
        }

        return null;
    }

    /**
     * @param  array{invoice: string, due: string|null, include: string|null, expected: string|null, days_off: int|null, before_cycle: bool}  $row
     */
    private static function misalignedLine(array $row): string
    {
        $line = $row['invoice'].' ('.($row['due'] ?? 'sin fecha').')';

        return match (true) {
            $row['before_cycle'] => $line.': cae antes del inicio de su propio ciclo ('.($row['include'] ?? '—').')',
            $row['expected'] !== null && $row['days_off'] !== null => $line.': le corresponde el '.$row['expected'].' (corrido '.abs($row['days_off']).' día(s) '.($row['days_off'] > 0 ? 'después' : 'antes').')',
            default => $line,
        };
    }

    private static function isReachedAnniversary(?CarbonImmutable $effective, ?CarbonImmutable $activated, CarbonImmutable $today): bool
    {
        return $effective !== null && $activated !== null
            && $effective->greaterThan($activated)
            && $effective->day === $activated->day && $effective->month === $activated->month
            && $effective->lessThanOrEqualTo($today);
    }

    private static function previousEffectiveOfLastRenewal(Model $affiliation, DateCorrectionSubject $subject): ?CarbonImmutable
    {
        $previous = DB::table($subject->historiesTable)
            ->where($subject->historiesForeignKey, $affiliation->getKey())
            ->orderByDesc('id')
            ->value('previous_effective_date');

        return CollectionDueDate::parse($previous);
    }

    /**
     * Aniversario de la emisión más reciente (o el siguiente) respecto de hoy.
     */
    private static function anniversary(?CarbonImmutable $activated, CarbonImmutable $today, bool $next): ?CarbonImmutable
    {
        if ($activated === null || $activated->greaterThan($today)) {
            return $next ? $activated : null;
        }

        $years = (int) $activated->diffInYears($today);
        $last = $activated->addYearsNoOverflow($years);

        if ($last->greaterThan($today)) {
            $last = $activated->addYearsNoOverflow($years - 1);
        }

        return $next ? $last->addYearNoOverflow() : $last;
    }

    private static function createdNearEmission(Model $affiliation, CarbonImmutable $activated): bool
    {
        $created = $affiliation->created_at;

        return $created !== null
            && CarbonImmutable::parse((string) $created)->startOfDay()->lessThanOrEqualTo($activated->addDays(self::CREATED_AFTER_EMISSION_TOLERANCE_DAYS));
    }

    /**
     * Sugerencias de fecha sin repetir y sin la fecha que se está cuestionando.
     *
     * @param  list<array{0: CarbonImmutable|null, 1: string}>  $candidates
     * @return list<array{date: string, label: string}>
     */
    private static function suggestions(array $candidates, ?CarbonImmutable $exclude = null): array
    {
        $suggestions = [];
        $seen = $exclude !== null ? [$exclude->format('d/m/Y') => true] : [];

        foreach ($candidates as [$date, $label]) {
            if (! $date instanceof CarbonImmutable) {
                continue;
            }

            $value = $date->format('d/m/Y');

            if (isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $suggestions[] = ['date' => $value, 'label' => $label];
        }

        return $suggestions;
    }

    /**
     * @param  array<string, string>  $facts
     * @param  list<array{date: string, label: string}>  $suggestions
     * @return array{kind: string, severity: string, title: string, explanation: string, consequence: string, repair: string, repair_mode: string, facts: array<string, string>, suggestions: list<array{date: string, label: string}>, fingerprint: string}
     */
    private static function finding(Kind $kind, string $explanation, string $consequence, string $repair, array $facts, array $suggestions): array
    {
        $mode = $kind->repairMode();

        if ($mode !== Kind::REPAIR_MANUAL && $suggestions === [] && $kind !== Kind::VigenciaAbsurda && $kind !== Kind::EmisionLejosDelPrimerPago) {
            $mode = Kind::REPAIR_MANUAL;
        }

        return [
            'kind' => $kind->value,
            'severity' => $kind->severity(),
            'title' => $kind->label(),
            'explanation' => $explanation,
            'consequence' => $consequence,
            'repair' => $repair,
            'repair_mode' => $mode,
            'facts' => $facts,
            'suggestions' => $suggestions,
            // Solo los datos que explican la inconsistencia: pagar una cuota no debe reabrir lo ya revisado.
            'fingerprint' => sha1($kind->value.'|'.$explanation),
        ];
    }

    /**
     * @param  list<array{invoice: string, due: string}>  $aligned
     * @param  list<array{invoice: string, due: string|null}>  $misaligned
     */
    private static function invoices(array $aligned, array $misaligned): string
    {
        $rows = array_merge(
            array_map(fn (array $row): string => $row['invoice'].' ('.$row['due'].')', $aligned),
            array_map(fn (array $row): string => $row['invoice'].' ('.($row['due'] ?? 'sin fecha').', fuera de calendario)', $misaligned),
        );

        if ($rows === []) {
            return 'Ninguno';
        }

        return count($rows) > 8
            ? implode(', ', array_slice($rows, 0, 8)).' y '.(count($rows) - 8).' más'
            : implode(', ', $rows);
    }

    private static function raw(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'Vacía' : '«'.$value.'» (ilegible)';
    }
}

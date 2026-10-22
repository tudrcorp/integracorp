<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateInconsistencies;

use App\Enums\AffiliationDateInconsistencyKind as Kind;
use App\Models\AffiliationDateCorrection;
use App\Models\AffiliationDateInconsistency as Inconsistency;
use App\Models\AffiliationDateInconsistencyEvent as Event;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionRollback;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Audit\TableRowSnapshot;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Lo que el analista hace con una inconsistencia de fechas: reparar (con resumen previo exacto y
 * nota obligatoria), revertir la reparación, marcarla como «no es una inconsistencia» y reabrirla.
 *
 * La reparación usa {@see AffiliationDateCorrector}: mismos bloqueos, misma foto antes y después,
 * misma reversión. El resumen previo sale de ejecutar la reparación de verdad dentro de una
 * transacción que se deshace; al aprobar se exige la misma huella de datos, así lo aprobado es
 * exactamente lo que se aplica.
 */
final class DateInconsistencyResolver
{
    public const NOTE_MIN_LENGTH = 15;

    public const MAX_BULK = 100;

    private const SIMULATION_REASON = 'Simulación de reparación: no se guarda nada';

    /**
     * @var array<string, string>
     */
    private const COLUMN_LABELS = [
        'activated_at' => 'fecha de emisión',
        'effective_date' => 'fecha de vigencia',
        'next_payment_date' => 'próximo pago',
        'include_date' => 'inicio del ciclo del aviso',
        'date_renewal' => 'fecha de renovación',
        'status' => 'estado',
        'prox_payment_date' => 'próximo pago (informativo)',
        'renewal_date' => 'renovación (informativo)',
    ];

    public static function assertCan(?User $actor): void
    {
        if (! $actor instanceof User || ! UserNavigationAccess::canPerformModuleAction($actor, 'ADMINISTRACION', BusinessFilamentActionPermissionRegistry::REPAIR_DATE_INCONSISTENCIES)) {
            throw new RuntimeException('No tiene permiso para gestionar inconsistencias de fechas.');
        }
    }

    public static function affiliationOf(Inconsistency $inconsistency): ?Model
    {
        return DateCorrectionSubject::forType($inconsistency->affiliation_type)->modelClass()::query()->find($inconsistency->affiliation_id);
    }

    public static function canRepair(Inconsistency $inconsistency): bool
    {
        return $inconsistency->status === Inconsistency::STATUS_OPEN
            && ($inconsistency->details['repair_mode'] ?? Kind::REPAIR_MANUAL) !== Kind::REPAIR_MANUAL;
    }

    public static function revertibleCorrection(Inconsistency $inconsistency): ?AffiliationDateCorrection
    {
        if ($inconsistency->status !== Inconsistency::STATUS_REPAIRED || $inconsistency->correction_id === null) {
            return null;
        }

        $correction = AffiliationDateCorrection::query()->find($inconsistency->correction_id);

        return $correction?->status === AffiliationDateCorrection::STATUS_APPLIED ? $correction : null;
    }

    /**
     * Ejecuta la reparación y la deshace: devuelve exactamente lo que cambiaría y la huella de los
     * datos revisados. No guarda nada.
     *
     * @return array{ok: bool, blockers: list<string>, lines: list<string>, fingerprint: string|null, effective: string|null, renewal: string|null, warnings: list<string>}
     */
    public static function simulate(Inconsistency $inconsistency, string $date, User $actor): array
    {
        self::assertCan($actor);
        $affiliation = self::affiliationOf($inconsistency);

        if (! $affiliation instanceof Model) {
            return ['ok' => false, 'blockers' => ['La afiliación ya no existe.'], 'lines' => [], 'fingerprint' => null, 'effective' => null, 'renewal' => null, 'warnings' => []];
        }

        $plan = AffiliationDateCorrector::plan($affiliation, $date);
        DB::beginTransaction();

        try {
            $correction = AffiliationDateCorrector::apply($affiliation, $date, self::SIMULATION_REASON, $actor);

            $result = [
                'ok' => true,
                'blockers' => [],
                'lines' => self::changeLines($correction),
                'fingerprint' => AffiliationDateCorrector::fingerprint((array) $correction->snapshot_before),
                'effective' => $correction->new_date,
                'renewal' => (string) ($correction->summary['renewal_after'] ?? ''),
                'warnings' => $plan['warnings'],
            ];
        } catch (RuntimeException $exception) {
            $result = ['ok' => false, 'blockers' => array_values(array_filter(explode("\n", $exception->getMessage()))), 'lines' => [], 'fingerprint' => null, 'effective' => null, 'renewal' => null, 'warnings' => []];
        } finally {
            DB::rollBack();
        }

        return $result;
    }

    /**
     * Repara de verdad, solo si los datos siguen siendo los del resumen aprobado.
     *
     * @return array{correction: AffiliationDateCorrection, lines: list<string>, rescan: array{opened: int, updated: int, reopened: int, resolved: int}}
     */
    public static function repair(Inconsistency $inconsistency, string $date, string $note, string $fingerprint, User $actor): array
    {
        self::assertCan($actor);
        $note = self::validNote($note);

        return DB::transaction(function () use ($inconsistency, $date, $note, $fingerprint, $actor): array {
            $locked = Inconsistency::query()->whereKey($inconsistency->getKey())->lockForUpdate()->firstOrFail();

            if (! self::canRepair($locked)) {
                throw new RuntimeException('Esta inconsistencia ya no está abierta o no se repara desde aquí.');
            }

            $affiliation = self::affiliationOf($locked) ?? throw new RuntimeException('La afiliación ya no existe.');
            $correction = AffiliationDateCorrector::apply($affiliation, $date, $note, $actor, $fingerprint);
            $lines = self::changeLines($correction);

            $locked->forceFill([
                'status' => Inconsistency::STATUS_REPAIRED,
                'correction_id' => $correction->getKey(),
                'resolved_at' => now(),
                'resolved_by' => $actor->getKey(),
                'resolved_by_name' => (string) $actor->name,
                'resolution_note' => $note,
            ])->save();

            DateInconsistencyScanner::event($locked, Event::REPAIRED, $note, [
                'correction_id' => $correction->getKey(),
                'date' => $correction->new_date,
                'renewal' => $correction->summary['renewal_after'] ?? null,
                'lines' => $lines,
            ], $actor);

            SecurityAudit::log('AUDIT_DATE_INCONSISTENCY_REPAIRED', 'administration.date-inconsistencies.repair', [
                'inconsistency_id' => $locked->getKey(),
                'kind' => $locked->kind,
                'affiliation_code' => $locked->affiliation_code,
                'correction_id' => $correction->getKey(),
                'note' => $note,
            ], $actor);

            $rescan = DateInconsistencyScanner::scanAffiliation($affiliation->fresh() ?? $affiliation, $actor);

            return ['correction' => $correction, 'lines' => $lines, 'rescan' => $rescan];
        });
    }

    /**
     * Revierte la reparación: la afiliación vuelve a su foto previa y la inconsistencia se reabre.
     */
    public static function revert(Inconsistency $inconsistency, string $note, User $actor): void
    {
        self::assertCan($actor);
        $note = self::validNote($note);

        DB::transaction(function () use ($inconsistency, $note, $actor): void {
            $locked = Inconsistency::query()->whereKey($inconsistency->getKey())->lockForUpdate()->firstOrFail();
            $correction = self::revertibleCorrection($locked) ?? throw new RuntimeException('Esta inconsistencia no tiene una reparación aplicada que revertir.');

            AffiliationDateCorrectionRollback::execute($correction, $actor, $note);

            $locked->forceFill([
                'status' => Inconsistency::STATUS_OPEN,
                'resolved_at' => null,
                'resolved_by' => null,
                'resolved_by_name' => null,
                'resolution_note' => null,
            ])->save();

            DateInconsistencyScanner::event($locked, Event::REVERTED, $note, ['correction_id' => $correction->getKey()], $actor);

            SecurityAudit::log('AUDIT_DATE_INCONSISTENCY_REPAIR_REVERTED', 'administration.date-inconsistencies.revert', [
                'inconsistency_id' => $locked->getKey(),
                'affiliation_code' => $locked->affiliation_code,
                'correction_id' => $correction->getKey(),
                'note' => $note,
            ], $actor);

            $affiliation = self::affiliationOf($locked);

            if ($affiliation instanceof Model) {
                DateInconsistencyScanner::scanAffiliation($affiliation, $actor);
            }
        });
    }

    /**
     * «No es una inconsistencia»: sale de la lista mientras sus datos no cambien.
     */
    public static function dismiss(Inconsistency $inconsistency, string $note, User $actor): void
    {
        self::assertCan($actor);
        $note = self::validNote($note);

        DB::transaction(function () use ($inconsistency, $note, $actor): void {
            $locked = Inconsistency::query()->whereKey($inconsistency->getKey())->lockForUpdate()->firstOrFail();
            self::dismissLocked($locked, $note, $actor);
        });
    }

    /**
     * La misma nota para varias inconsistencias abiertas; todo o nada.
     *
     * @param  list<int>  $ids
     */
    public static function dismissMany(array $ids, string $note, User $actor): int
    {
        self::assertCan($actor);
        $note = self::validNote($note);
        $ids = array_values(array_unique(array_map(intval(...), $ids)));

        if ($ids === []) {
            throw new RuntimeException('Seleccione al menos una inconsistencia.');
        }

        if (count($ids) > self::MAX_BULK) {
            throw new RuntimeException('Puede marcar hasta '.self::MAX_BULK.' por vez. Seleccionó '.count($ids).'.');
        }

        return DB::transaction(function () use ($ids, $note, $actor): int {
            $rows = Inconsistency::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            $notOpen = $rows->where('status', '!=', Inconsistency::STATUS_OPEN)->pluck('affiliation_code')->unique()->values()->all();

            if ($rows->count() !== count($ids) || $notOpen !== []) {
                throw new RuntimeException('No se marcó ninguna: '.($notOpen !== [] ? 'algunas ya no están abiertas ('.implode(', ', $notOpen).')' : 'alguna ya no existe').'. Recargue la lista.');
            }

            foreach ($rows as $row) {
                self::dismissLocked($row, $note, $actor);
            }

            return $rows->count();
        });
    }

    /**
     * Deshace «no es una inconsistencia»: vuelve a la lista de abiertas.
     */
    public static function reopen(Inconsistency $inconsistency, string $note, User $actor): void
    {
        self::assertCan($actor);
        $note = self::validNote($note);

        DB::transaction(function () use ($inconsistency, $note, $actor): void {
            $locked = Inconsistency::query()->whereKey($inconsistency->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Inconsistency::STATUS_DISMISSED) {
                throw new RuntimeException('Solo se reabre una inconsistencia marcada como «no es una inconsistencia».');
            }

            $locked->forceFill([
                'status' => Inconsistency::STATUS_OPEN,
                'dismissed_fingerprint' => null,
                'resolved_at' => null,
                'resolved_by' => null,
                'resolved_by_name' => null,
                'resolution_note' => null,
            ])->save();

            DateInconsistencyScanner::event($locked, Event::REOPENED, $note, null, $actor);
        });
    }

    /**
     * Cambios de una corrección, fila por fila, en lenguaje del analista.
     *
     * @return list<string>
     */
    public static function changeLines(AffiliationDateCorrection $correction): array
    {
        $subject = DateCorrectionSubject::forType($correction->affiliation_type);
        $columns = $subject->columns();
        $invoices = [];

        foreach ((array) ($correction->summary['collections'] ?? []) as $row) {
            $invoices[(int) $row['id']] = (string) $row['invoice'];
        }

        $lines = [];
        $annualTouched = [];

        foreach (TableRowSnapshot::differences($columns, (array) $correction->snapshot_before, (array) $correction->snapshot_after) as $difference) {
            $table = $difference['table'];
            $column = (string) $difference['column'];

            if ($table === 'annual_collections') {
                $annualTouched[$difference['id']] = true;

                continue;
            }

            if (! isset(self::COLUMN_LABELS[$column])) {
                continue;
            }

            $subjectLabel = match ($table) {
                $subject->affiliationTable => 'Afiliación '.$correction->affiliation_code,
                'collections' => 'Aviso '.($invoices[$difference['id']] ?? '#'.$difference['id']),
                $subject->renovationsTable => 'Propuesta de renovación #'.$difference['id'],
                $subject->paymentsTable => 'Pago #'.$difference['id'],
                default => $table.' #'.$difference['id'],
            };

            $lines[] = $subjectLabel.' · '.self::COLUMN_LABELS[$column].': '.self::humanValue($column, $difference['expected']).' → '.self::humanValue($column, $difference['current']);
        }

        foreach (array_keys($annualTouched) as $id) {
            $lines[] = 'Planificación anual #'.$id.' · meses con cuota y próximo pago recalculados';
        }

        return $lines;
    }

    private static function dismissLocked(Inconsistency $locked, string $note, User $actor): void
    {
        if ($locked->status !== Inconsistency::STATUS_OPEN) {
            throw new RuntimeException('Solo se marca una inconsistencia abierta.');
        }

        $locked->forceFill([
            'status' => Inconsistency::STATUS_DISMISSED,
            'dismissed_fingerprint' => $locked->fingerprint,
            'resolved_at' => now(),
            'resolved_by' => $actor->getKey(),
            'resolved_by_name' => (string) $actor->name,
            'resolution_note' => $note,
        ])->save();

        DateInconsistencyScanner::event($locked, Event::DISMISSED, $note, null, $actor);
    }

    private static function validNote(string $note): string
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));

        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('Escriba una nota explicativa (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        return $note;
    }

    private static function humanValue(string $column, ?string $value): string
    {
        if ($value === null || $value === '') {
            return 'vacío';
        }

        if ($column === 'date_renewal') {
            try {
                return \Carbon\CarbonImmutable::parse($value)->format('d/m/Y');
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }
}

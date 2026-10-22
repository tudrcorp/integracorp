<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\DateCorrection;

use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDateCorrection;
use App\Models\Collection;
use App\Models\PaidMembershipCorporate;
use App\Models\User;
use App\Support\Audit\TableRowSnapshot;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Revierte una corrección de vigencia corporativa desde su punto de restauración.
 *
 * Todo o nada: cada fila vuelve a su foto previa, o no se toca ninguna. Se bloquea si algo
 * cambió después de corregir (un aviso pagado o ajustado, una renovación movida, un pago o un
 * aviso nuevo), porque restituir encima pisaría trabajo legítimo. Nada se borra.
 */
final class CorporateDateCorrectionRollback
{
    public const NOTE_MIN_LENGTH = 15;

    public const LOG_ACTION = 'REVIRTIO CORRECCION DE VIGENCIA';

    private const MAX_LISTED_CONFLICTS = 10;

    /**
     * @var array<string, string>
     */
    private const TABLE_LABELS = [
        'affiliation_corporates' => 'Afiliación',
        'collections' => 'Aviso',
        'annual_collections' => 'Planificación anual',
        'renovation_corporates' => 'Renovación',
        'paid_membership_corporates' => 'Pago',
    ];

    public static function latestAppliedFor(AffiliationCorporate $affiliation): ?AffiliationCorporateDateCorrection
    {
        return AffiliationCorporateDateCorrection::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->where('status', AffiliationCorporateDateCorrection::STATUS_APPLIED)
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    public static function blockers(AffiliationCorporateDateCorrection $correction): array
    {
        if ($correction->status !== AffiliationCorporateDateCorrection::STATUS_APPLIED) {
            return ['Esta corrección ya no está aplicada.'];
        }

        $blockers = [];

        $later = AffiliationCorporateDateCorrection::query()
            ->where('affiliation_corporate_id', $correction->affiliation_corporate_id)
            ->where('status', AffiliationCorporateDateCorrection::STATUS_APPLIED)
            ->where('id', '>', $correction->id)
            ->exists();

        if ($later) {
            $blockers[] = 'Hay una corrección posterior aplicada. Revierta primero la más reciente.';
        }

        $after = (array) $correction->snapshot_after;
        $differences = TableRowSnapshot::differences(
            CorporateDateCorrector::COLUMNS,
            $after,
            TableRowSnapshot::capture(CorporateDateCorrector::COLUMNS, TableRowSnapshot::idsOf(CorporateDateCorrector::COLUMNS, $after)),
        );

        if ($differences !== []) {
            $lines = array_map(
                static fn (array $difference): string => (self::TABLE_LABELS[$difference['table']] ?? $difference['table']).' #'.$difference['id']
                    .($difference['column'] === null
                        ? ' ya no existe'
                        : ': '.$difference['column'].' «'.($difference['expected'] ?? 'vacío').'» → «'.($difference['current'] ?? 'vacío').'»'),
                array_slice($differences, 0, self::MAX_LISTED_CONFLICTS),
            );

            if (count($differences) > self::MAX_LISTED_CONFLICTS) {
                $lines[] = 'y '.(count($differences) - self::MAX_LISTED_CONFLICTS).' cambio(s) más';
            }

            $blockers[] = 'Hubo cambios después de corregir que la reversión pisaría: '.implode(' · ', $lines).'.';
        }

        $appliedAt = $correction->applied_at ?? $correction->created_at;

        if ($appliedAt !== null) {
            $newCollections = Collection::query()
                ->where('affiliation_code', $correction->affiliation_code)
                ->where('created_at', '>=', $appliedAt)
                ->count();

            if ($newCollections > 0) {
                $blockers[] = 'Se generaron '.$newCollections.' aviso(s) nuevos después de corregir. Revíselos en Gestión de Cobranza antes de revertir.';
            }

            $newPayments = PaidMembershipCorporate::query()
                ->where('affiliation_corporate_id', $correction->affiliation_corporate_id)
                ->where('created_at', '>=', $appliedAt)
                ->count();

            if ($newPayments > 0) {
                $blockers[] = 'Se registraron '.$newPayments.' pago(s) después de corregir. Revertir descuadraría la cobranza: debe revisarlo Administración.';
            }
        }

        return $blockers;
    }

    /**
     * Revierte la corrección. Lanza RuntimeException (sin escribir nada) si algo lo impide.
     */
    public static function execute(AffiliationCorporateDateCorrection $correction, User $actor, string $note): AffiliationCorporateDateCorrection
    {
        CorporateDateCorrector::assertCan($actor);
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));

        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué se revierte (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($correction, $actor, $note): AffiliationCorporateDateCorrection {
            $locked = AffiliationCorporateDateCorrection::query()->whereKey($correction->getKey())->lockForUpdate()->firstOrFail();
            $affiliation = AffiliationCorporate::query()->whereKey($locked->affiliation_corporate_id)->lockForUpdate()->firstOrFail();
            Collection::query()->where('affiliation_code', $affiliation->code)->orderBy('id')->lockForUpdate()->get(['id']);

            $blockers = self::blockers($locked);

            if ($blockers !== []) {
                throw new RuntimeException(implode("\n", $blockers));
            }

            $before = (array) $locked->snapshot_before;
            $restored = TableRowSnapshot::restore(CorporateDateCorrector::COLUMNS, $before);
            $batch = (string) Str::uuid();

            foreach (array_keys((array) ($before['collections'] ?? [])) as $collectionId) {
                $collection = Collection::query()->find((int) $collectionId);

                if ($collection instanceof Collection) {
                    $previous = (string) ($locked->snapshot_after['collections'][$collectionId]['next_payment_date'] ?? '');

                    CorporateDateCorrector::recordAdjustment(
                        $collection,
                        $batch,
                        ['next_payment_date' => ['before' => $previous, 'after' => (string) $collection->next_payment_date]],
                        'Reversión de corrección de vigencia: '.$note,
                        $actor,
                    );
                }
            }

            $summary = 'Se revirtió la corrección de vigencia del '.$locked->applied_at?->format('d/m/Y H:i')
                .' ('.$locked->new_date.' → '.($locked->activated_at_before ?? 'sin fecha').'). Filas restituidas: '.$restored.'. Motivo: '.$note;

            $affiliation->status_log_corporate_affiliations()->create([
                'affiliation_corporate_id' => $affiliation->id,
                'action' => self::LOG_ACTION,
                'observation' => $summary,
                'updated_by' => (string) $actor->name,
            ]);

            $affiliation->affiliationCorporateObservations()->create([
                'description' => $summary,
                'created_by' => (string) $actor->getKey(),
            ]);

            $locked->forceFill([
                'status' => AffiliationCorporateDateCorrection::STATUS_ROLLED_BACK,
                'rolled_back_by' => $actor->getKey(),
                'rolled_back_by_name' => (string) $actor->name,
                'rolled_back_at' => now(),
                'rollback_note' => $note,
                'rollback_summary' => ['restored_rows' => $restored, 'batch_uuid' => $batch],
            ])->save();

            SecurityAudit::log('AUDIT_CORPORATE_EFFECTIVE_DATE_CORRECTION_ROLLED_BACK', 'administration.affiliation-corporates.correct-effective-date.rollback', [
                'correction_id' => $locked->id,
                'affiliation_code' => $locked->affiliation_code,
                'restored_rows' => $restored,
                'note' => $note,
            ], $actor);

            return $locked;
        });
    }
}

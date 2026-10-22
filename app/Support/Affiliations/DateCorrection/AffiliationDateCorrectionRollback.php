<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateCorrection;

use App\Models\AffiliationDateCorrection;
use App\Models\Collection;
use App\Models\User;
use App\Support\Audit\TableRowSnapshot;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Revierte una corrección de vigencia (individual o corporativa) desde su punto de restauración.
 *
 * Todo o nada: cada fila vuelve a su foto previa, o no se toca ninguna. Se bloquea si algo
 * cambió después de corregir (un aviso pagado o ajustado, una renovación movida, un pago o un
 * aviso nuevo), porque restituir encima pisaría trabajo legítimo. Nada se borra.
 */
final class AffiliationDateCorrectionRollback
{
    public const NOTE_MIN_LENGTH = 15;

    public const LOG_ACTION = 'REVIRTIO CORRECCION DE VIGENCIA';

    private const MAX_LISTED_CONFLICTS = 10;

    public static function latestAppliedFor(Model $affiliation): ?AffiliationDateCorrection
    {
        return AffiliationDateCorrection::query()
            ->where('affiliation_type', DateCorrectionSubject::for($affiliation)->type)
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', AffiliationDateCorrection::STATUS_APPLIED)
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    public static function blockers(AffiliationDateCorrection $correction): array
    {
        if ($correction->status !== AffiliationDateCorrection::STATUS_APPLIED) {
            return ['Esta corrección ya no está aplicada.'];
        }

        $subject = DateCorrectionSubject::forType($correction->affiliation_type);
        $columns = $subject->columns();
        $blockers = [];

        $later = AffiliationDateCorrection::query()
            ->where('affiliation_type', $correction->affiliation_type)
            ->where('affiliation_id', $correction->affiliation_id)
            ->where('status', AffiliationDateCorrection::STATUS_APPLIED)
            ->where('id', '>', $correction->id)
            ->exists();

        if ($later) {
            $blockers[] = 'Hay una corrección posterior aplicada. Revierta primero la más reciente.';
        }

        $after = (array) $correction->snapshot_after;
        $differences = TableRowSnapshot::differences(
            $columns,
            $after,
            TableRowSnapshot::capture($columns, TableRowSnapshot::idsOf($columns, $after)),
        );

        if ($differences !== []) {
            $labels = $subject->tableLabels();
            $lines = array_map(
                static fn (array $difference): string => ($labels[$difference['table']] ?? $difference['table']).' #'.$difference['id']
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
            // Los avisos que movió la propia corrección no son «nuevos», aunque se hayan creado en el mismo segundo.
            $ownCollections = array_map(intval(...), array_keys((array) ($correction->snapshot_before['collections'] ?? [])));
            $newCollections = Collection::query()
                ->where('affiliation_code', $correction->affiliation_code)
                ->whereNotIn('id', $ownCollections !== [] ? $ownCollections : [0])
                ->where('created_at', '>=', $appliedAt)
                ->count();

            if ($newCollections > 0) {
                $blockers[] = 'Se generaron '.$newCollections.' aviso(s) nuevos después de corregir. Revíselos en Gestión de Cobranza antes de revertir.';
            }

            $newPayments = DB::table($subject->paymentsTable)
                ->where($subject->paymentsForeignKey, $correction->affiliation_id)
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
    public static function execute(AffiliationDateCorrection $correction, User $actor, string $note): AffiliationDateCorrection
    {
        $subject = DateCorrectionSubject::forType($correction->affiliation_type);
        AffiliationDateCorrector::assertCan($actor, $subject);
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));

        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué se revierte (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($correction, $subject, $actor, $note): AffiliationDateCorrection {
            $locked = AffiliationDateCorrection::query()->whereKey($correction->getKey())->lockForUpdate()->firstOrFail();
            /** @var Model $affiliation */
            $affiliation = $subject->modelClass()::query()->whereKey($locked->affiliation_id)->lockForUpdate()->firstOrFail();
            Collection::query()->where('affiliation_code', $affiliation->code)->orderBy('id')->lockForUpdate()->get(['id']);

            $blockers = self::blockers($locked);

            if ($blockers !== []) {
                throw new RuntimeException(implode("\n", $blockers));
            }

            $before = (array) $locked->snapshot_before;
            $after = (array) $locked->snapshot_after;
            $restored = TableRowSnapshot::restore($subject->columns(), $before);
            $batch = (string) Str::uuid();

            foreach (array_keys((array) ($before['collections'] ?? [])) as $collectionId) {
                $collection = Collection::query()->find((int) $collectionId);

                if ($collection instanceof Collection) {
                    AffiliationDateCorrector::recordAdjustment($collection, $batch, [
                        'next_payment_date' => [
                            'before' => $after['collections'][$collectionId]['next_payment_date'] ?? null,
                            'after' => (string) $collection->next_payment_date,
                        ],
                    ], 'Reversión de corrección de vigencia: '.$note, $actor);
                }
            }

            $summary = 'Se revirtió la corrección de vigencia del '.$locked->applied_at?->format('d/m/Y H:i')
                .' (fecha corregida '.$locked->new_date.'). Volvió a: emisión '.($locked->activated_at_before ?? '—')
                .', vigencia '.($locked->effective_date_before ?? '—').'. Filas restituidas: '.$restored.'. Motivo: '.$note;

            AffiliationDateCorrector::log($affiliation, $subject, self::LOG_ACTION, $summary, $actor);

            $locked->forceFill([
                'status' => AffiliationDateCorrection::STATUS_ROLLED_BACK,
                'rolled_back_by' => $actor->getKey(),
                'rolled_back_by_name' => (string) $actor->name,
                'rolled_back_at' => now(),
                'rollback_note' => $note,
                'rollback_summary' => ['restored_rows' => $restored, 'batch_uuid' => $batch],
            ])->save();

            SecurityAudit::log('AUDIT_EFFECTIVE_DATE_CORRECTION_ROLLED_BACK', 'affiliations.correct-effective-date.rollback', [
                'correction_id' => $locked->id,
                'type' => $locked->affiliation_type,
                'affiliation_code' => $locked->affiliation_code,
                'restored_rows' => $restored,
                'note' => $note,
            ], $actor);

            return $locked;
        });
    }
}

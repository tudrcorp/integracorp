<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Jobs\PrepareAffiliationRenovations;
use App\Jobs\RegenerateMergedAffiliationDocumentsJob;
use App\Models\Affiliate;
use App\Models\AffiliateClinicalServiceUsage;
use App\Models\Affiliation;
use App\Models\AffiliationFamilyMerge;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\PaidMembership;
use App\Models\User;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Revierte una unificación de grupo familiar desde su punto de restauración.
 *
 * Es todo o nada: cada fila que tocó la unificación vuelve a su foto previa, o no
 * se toca ninguna. Se bloquea si algo cambió después de unificar, porque restituir
 * encima pisaría trabajo legítimo:
 * - una fila registrada difiere de la foto de «después» (cuota pagada, afiliado editado…);
 * - hay cuotas o pagos nuevos en alguna de las pólizas desde la unificación;
 * - entraron afiliados nuevos a alguna de las pólizas;
 * - hay una unificación posterior aplicada sobre las mismas pólizas.
 *
 * Nada se borra: las bitácoras y observaciones de la unificación se conservan y la
 * reversión agrega las suyas.
 */
final class FamilyMergeRollback
{
    public const NOTE_MIN_LENGTH = 15;

    public const ROLLBACK_ACTION = 'REVIRTIO UNIFICACION GRUPO FAMILIAR';

    private const MAX_LISTED_CONFLICTS = 12;

    /**
     * @var array<string, string>
     */
    private const COLUMN_LABELS = [
        'status' => 'estatus',
        'fee_anual' => 'tarifa anual',
        'total_amount' => 'monto',
        'family_members' => 'personas',
        'persons' => 'personas',
        'affiliation_id' => 'póliza',
        'afilliation_id' => 'póliza',
        'relationship' => 'parentesco',
        'fee' => 'tarifa',
        'plan_id' => 'plan',
        'coverage_id' => 'cobertura',
        'payment_frequency' => 'frecuencia de pago',
        'affiliate_status' => 'estatus del afiliado',
        'full_name_ti' => 'nombre del titular',
        'nro_identificacion_ti' => 'cédula del titular',
    ];

    /**
     * Última unificación aplicada en la que participó la afiliación (destino u origen).
     */
    public static function latestAppliedFor(Affiliation $affiliation): ?AffiliationFamilyMerge
    {
        return AffiliationFamilyMerge::query()
            ->appliedInvolving((int) $affiliation->id)
            ->latest('id')
            ->first();
    }

    /**
     * Lo que impide revertir hoy. Lista vacía = se puede revertir.
     *
     * @return list<string>
     */
    public static function blockers(AffiliationFamilyMerge $merge): array
    {
        if ($merge->status !== AffiliationFamilyMerge::STATUS_APPLIED) {
            return ['Esta unificación ya no está aplicada (estatus '.$merge->status.').'];
        }

        $blockers = [];
        $involvedIds = $merge->involvedAffiliationIds();
        $mergedAt = $merge->merged_at ?? $merge->created_at;

        foreach ($involvedIds as $affiliationId) {
            $later = AffiliationFamilyMerge::query()
                ->appliedInvolving($affiliationId)
                ->where('id', '>', $merge->id)
                ->orderBy('id')
                ->first();

            if ($later instanceof AffiliationFamilyMerge) {
                $blockers[] = 'Hay una unificación posterior sobre estas pólizas (en '.$later->target_code
                    .', del '.$later->merged_at?->format('d/m/Y H:i').'). Revierta primero esa.';

                break;
            }
        }

        $after = (array) $merge->snapshot_after;
        $differences = FamilyMergeSnapshot::differences($after, FamilyMergeSnapshot::capture(FamilyMergeSnapshot::idsOf($after)));

        if ($differences !== []) {
            $names = self::rowNames($after);
            $lines = array_map(
                static fn (array $difference): string => self::describeDifference($difference, $names),
                array_slice($differences, 0, self::MAX_LISTED_CONFLICTS),
            );

            if (count($differences) > self::MAX_LISTED_CONFLICTS) {
                $lines[] = 'y '.(count($differences) - self::MAX_LISTED_CONFLICTS).' cambio(s) más.';
            }

            $blockers[] = 'Hubo cambios después de unificar que la reversión pisaría: '.implode(' · ', $lines);
        }

        $codes = Affiliation::query()->whereIn('id', $involvedIds)->pluck('code')->filter()->values()->all();

        if ($codes !== [] && $mergedAt !== null) {
            $ownCollections = array_map(intval(...), array_keys((array) ($after['collections'] ?? [])));
            $newCollections = Collection::query()
                ->whereIn('affiliation_code', $codes)
                ->whereNotIn('id', $ownCollections !== [] ? $ownCollections : [0])
                ->where('created_at', '>=', $mergedAt)
                ->count()
                + AnnualCollection::query()->whereIn('affiliation_code', $codes)->where('created_at', '>', $mergedAt)->count();

            if ($newCollections > 0) {
                $blockers[] = 'Se generaron '.$newCollections.' cuota(s) nuevas en estas pólizas después de unificar. Anúlelas o ajústelas en Administración antes de revertir.';
            }
        }

        if ($mergedAt !== null) {
            $newPayments = PaidMembership::query()
                ->whereIn('affiliation_id', $involvedIds)
                ->where('created_at', '>', $mergedAt)
                ->count();

            if ($newPayments > 0) {
                $blockers[] = 'Se registraron '.$newPayments.' pago(s) en estas pólizas después de unificar. Revertir descuadraría la cobranza: debe revisarlo Administración.';
            }

            $knownAffiliates = array_map(intval(...), array_keys((array) ($after['affiliates'] ?? [])));
            $newAffiliates = Affiliate::query()
                ->whereIn('affiliation_id', $involvedIds)
                ->whereNotIn('id', $knownAffiliates !== [] ? $knownAffiliates : [0])
                ->count();

            if ($newAffiliates > 0) {
                $blockers[] = 'Entraron '.$newAffiliates.' afiliado(s) nuevos a estas pólizas después de unificar. Defina a qué póliza pertenecen antes de revertir.';
            }
        }

        return $blockers;
    }

    /**
     * Avisos que no impiden revertir.
     *
     * @return list<string>
     */
    public static function warnings(AffiliationFamilyMerge $merge): array
    {
        $warnings = [];
        $patientIds = array_map(intval(...), array_keys((array) ($merge->snapshot_before['telemedicine_patients'] ?? [])));
        $mergedAt = $merge->merged_at ?? $merge->created_at;

        if ($patientIds !== [] && $mergedAt !== null) {
            $consumptions = AffiliateClinicalServiceUsage::query()
                ->whereIn('telemedicine_patient_id', $patientIds)
                ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
                ->where('created_at', '>', $mergedAt)
                ->count();

            if ($consumptions > 0) {
                $warnings[] = 'Hay '.$consumptions.' consumo(s) de cupo clínico registrados después de unificar. Se conservan; el período de conteo vuelve al aniversario de cada póliza original.';
            }
        }

        $warnings[] = 'Las bitácoras y observaciones de la unificación no se borran: la reversión agrega las suyas.';
        $warnings[] = 'El certificado y los carnets de cada póliza se regeneran en cola al terminar.';

        return $warnings;
    }

    /**
     * Qué vuelve a su lugar: cada persona y la póliza a la que regresa.
     *
     * @return list<array{name: string, identification: string, from: string, back_to: string, relationship: string}>
     */
    public static function restoredMembers(AffiliationFamilyMerge $merge): array
    {
        $before = (array) ($merge->snapshot_before['affiliates'] ?? []);

        if ($before === []) {
            return [];
        }

        $affiliates = Affiliate::query()
            ->whereIn('id', array_map(intval(...), array_keys($before)))
            ->get(['id', 'full_name', 'nro_identificacion', 'affiliation_id'])
            ->keyBy('id');
        $codes = Affiliation::query()
            ->whereIn('id', $merge->involvedAffiliationIds())
            ->pluck('code', 'id');

        $rows = [];

        foreach ($before as $id => $values) {
            $affiliate = $affiliates->get((int) $id);

            $rows[] = [
                'name' => (string) ($affiliate?->full_name ?? 'Afiliado #'.$id),
                'identification' => (string) ($affiliate?->nro_identificacion ?? ''),
                'from' => (string) ($codes[(int) ($affiliate?->affiliation_id ?? 0)] ?? '—'),
                'back_to' => (string) ($codes[(int) ($values['affiliation_id'] ?? 0)] ?? '—'),
                'relationship' => (string) ($values['relationship'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Revierte la unificación. Lanza RuntimeException (sin escribir nada) si algo lo impide.
     *
     * @return array{restored_rows: int, cancelled_created_collections: int, cancelled_created_renovations: int, affiliations: list<string>, renovations_refreshed: int}
     */
    public static function execute(AffiliationFamilyMerge $merge, User $user, string $note): array
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));

        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué se revierte (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        $summary = DB::transaction(static function () use ($merge, $user, $note): array {
            $locked = AffiliationFamilyMerge::query()->whereKey($merge->getKey())->lockForUpdate()->firstOrFail();
            $involvedIds = $locked->involvedAffiliationIds();

            $affiliations = Affiliation::query()
                ->whereIn('id', $involvedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            Affiliate::query()
                ->whereIn('affiliation_id', $involvedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $blockers = self::blockers($locked);

            if ($blockers !== []) {
                throw new RuntimeException(implode("\n", $blockers));
            }

            $before = (array) $locked->snapshot_before;
            $restoredRows = FamilyMergeSnapshot::restore($before);
            $neutralized = self::neutralizeCreatedRows($before, (array) $locked->snapshot_after, (string) $user->name);

            foreach (array_keys((array) ($before['telemedicine_patients'] ?? [])) as $patientId) {
                TelemedicinePatientPlanBridge::flushCachesFor((int) $patientId);
            }

            $renovationsRefreshed = 0;
            $codes = [];

            foreach ($involvedIds as $affiliationId) {
                $affiliation = Affiliation::query()->find($affiliationId);

                if (! $affiliation instanceof Affiliation) {
                    continue;
                }

                $codes[] = (string) $affiliation->code;
                $renovationsRefreshed += PrepareAffiliationRenovations::refreshForAffiliation($affiliation) ? 1 : 0;

                $observation = 'Se revirtió la unificación de grupo familiar del '
                    .$locked->merged_at?->format('d/m/Y H:i').' (destino '.$locked->target_code
                    .', origen '.implode(', ', (array) $locked->source_codes).'). '
                    .'La póliza volvió a su estado previo. Motivo: '.$note;

                $affiliation->status_log_affiliations()->create([
                    'affiliation_id' => $affiliation->id,
                    'action' => self::ROLLBACK_ACTION,
                    'observation' => $observation,
                    'updated_by' => (string) $user->name,
                ]);

                $affiliation->affiliationObservations()->create([
                    'description' => $observation,
                    'created_by' => (string) $user->getKey(),
                ]);
            }

            $result = [
                'restored_rows' => $restoredRows,
                'cancelled_created_collections' => $neutralized['collections'],
                'cancelled_created_renovations' => $neutralized['renovations'],
                'affiliations' => $codes,
                'renovations_refreshed' => $renovationsRefreshed,
            ];

            $locked->forceFill([
                'status' => AffiliationFamilyMerge::STATUS_ROLLED_BACK,
                'rolled_back_by' => $user->getKey(),
                'rolled_back_by_name' => (string) $user->name,
                'rolled_back_at' => now(),
                'rollback_note' => $note,
                'rollback_summary' => $result,
            ])->save();

            SecurityAudit::log('AUDIT_AFFILIATION_FAMILY_MERGE_ROLLED_BACK', 'affiliations.merge-family.rollback', [
                'merge_id' => $locked->id,
                'target_code' => $locked->target_code,
                'source_codes' => $locked->source_codes,
                'restored_rows' => $restoredRows,
                'note' => $note,
            ], $user);

            $userId = (int) $user->getKey();

            DB::afterCommit(static function () use ($affiliations, $userId): void {
                foreach ($affiliations as $affiliation) {
                    RegenerateMergedAffiliationDocumentsJob::dispatch((int) $affiliation->id, $userId);
                }
            });

            return $result;
        });

        Log::info('Unificación de grupo familiar revertida', [
            'merge_id' => $merge->id,
            'affiliations' => $summary['affiliations'],
            'restored_rows' => $summary['restored_rows'],
        ]);

        return $summary;
    }

    /**
     * Filas que creó la unificación (están en la foto de después y no en la de antes): los avisos
     * de cobro y las propuestas de renovación del calendario nuevo. No se borran: se anulan.
     *
     * @param  array<string, array<int|string, array<string, string|null>>>  $before
     * @param  array<string, array<int|string, array<string, string|null>>>  $after
     * @return array{collections: int, renovations: int}
     */
    private static function neutralizeCreatedRows(array $before, array $after, string $actorName): array
    {
        $created = static fn (string $table): array => array_values(array_diff(
            array_map(intval(...), array_keys((array) ($after[$table] ?? []))),
            array_map(intval(...), array_keys((array) ($before[$table] ?? []))),
        ));

        $collections = $created('collections');
        $renovations = $created('renovations');

        return [
            'collections' => $collections === [] ? 0 : (int) Collection::query()
                ->whereIn('id', $collections)
                ->update(['status' => MergeIndividualAffiliationsService::COLLECTION_CANCELLED_STATUS]),
            'renovations' => $renovations === [] ? 0 : (int) DB::table('renovations')
                ->whereIn('id', $renovations)
                ->update(['status' => MergeIndividualAffiliationsService::RENOVATION_CANCELLED_STATUS, 'updated_by' => $actorName, 'updated_at' => now()]),
        ];
    }

    /**
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     * @return array<string, array<int, string>>
     */
    private static function rowNames(array $snapshot): array
    {
        $ids = FamilyMergeSnapshot::idsOf($snapshot);

        return [
            'affiliations' => Affiliation::query()->whereIn('id', $ids['affiliations'] ?: [0])->pluck('code', 'id')->map(strval(...))->all(),
            'affiliates' => Affiliate::query()->whereIn('id', $ids['affiliates'] ?: [0])->pluck('full_name', 'id')->map(strval(...))->all(),
            'collections' => Collection::query()->whereIn('id', $ids['collections'] ?: [0])->pluck('affiliation_code', 'id')->map(strval(...))->all(),
            'annual_collections' => AnnualCollection::query()->whereIn('id', $ids['annual_collections'] ?: [0])->pluck('affiliation_code', 'id')->map(strval(...))->all(),
        ];
    }

    /**
     * @param  array{table: string, id: int, column: string|null, expected: string|null, current: string|null}  $difference
     * @param  array<string, array<int, string>>  $names
     */
    private static function describeDifference(array $difference, array $names): string
    {
        $table = $difference['table'];
        $label = FamilyMergeSnapshot::LABELS[$table] ?? $table;
        $name = $names[$table][$difference['id']] ?? null;
        $subject = $label.' '.($name !== null && $name !== '' ? $name.' (#'.$difference['id'].')' : '#'.$difference['id']);

        if ($difference['column'] === null) {
            return $subject.' ya no existe';
        }

        $column = self::COLUMN_LABELS[$difference['column']] ?? $difference['column'];

        return $subject.': '.$column.' «'.($difference['expected'] ?? 'vacío').'» → «'.($difference['current'] ?? 'vacío').'»';
    }
}

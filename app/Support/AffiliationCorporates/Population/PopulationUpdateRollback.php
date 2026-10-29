<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster;
use App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Revierte una carga aplicada: cada afiliado vuelve a su foto previa y los
 * totales reciben exactamente lo inverso de lo que sumó la carga.
 *
 * Reglas:
 * - Solo mientras no haya una carga posterior aplicada en la afiliación.
 * - Una fila que alguien cambió después (otro estatus, otra cédula) no se pisa.
 * - Un afiliado agregado o reactivado que ya tuvo actividad clínica después de
 *   aplicar (caso, coordinación o consumo de cupo) no se revierte: se informa.
 * - Un afiliado creado por la carga se borra solo si no tiene nada asociado
 *   (paciente de telemedicina, upgrades, voucher ILS); si lo tiene, queda INACTIVO.
 */
final class PopulationUpdateRollback
{
    public const NOTE_MIN_LENGTH = 15;

    public static function blockingReason(CorporatePopulationUpdate $update): ?string
    {
        if ($update->status !== CorporatePopulationUpdate::STATUS_APPLIED) {
            return 'Solo se revierte una carga aplicada.';
        }

        if (PopulationBillingAdjuster::activeAdjustmentFor($update) !== null) {
            return 'Esta carga ya tiene un ajuste de facturación aplicado en Administración (aviso por ajuste y cuotas ajustadas). Pida que lo reviertan en «Ajustes por población» antes de revertir la población.';
        }

        $later = CorporatePopulationUpdate::query()
            ->where('affiliation_corporate_id', $update->affiliation_corporate_id)
            ->whereKeyNot($update->getKey())
            ->whereIn('status', [CorporatePopulationUpdate::STATUS_APPLIED, CorporatePopulationUpdate::STATUS_APPLYING, CorporatePopulationUpdate::STATUS_ROLLING_BACK])
            ->where(static fn ($query) => $query->where('approved_at', '>', $update->applied_at)->orWhere('applied_at', '>', $update->applied_at))
            ->exists();

        return $later
            ? 'Hay una carga posterior aplicada en esta afiliación. Revierta primero la más reciente.'
            : null;
    }

    public static function start(CorporatePopulationUpdate $update, User $user, string $note): void
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué se revierte (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        DB::transaction(static function () use ($update, $user, $note): void {
            $locked = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();
            $reason = self::blockingReason($locked);
            if ($reason !== null) {
                throw new RuntimeException($reason);
            }

            $locked->forceFill([
                'status' => CorporatePopulationUpdate::STATUS_ROLLING_BACK,
                'rolled_back_by' => $user->getKey(),
                'rolled_back_by_name' => (string) $user->name,
                'rollback_note' => $note,
                'error' => null,
            ])->save();
        });
    }

    public static function rollback(CorporatePopulationUpdate $update): CorporatePopulationUpdate
    {
        $lock = Cache::lock(PopulationUpdateApplier::lockKey((int) $update->affiliation_corporate_id), 600);

        if (! $lock->get()) {
            self::fail($update, 'Hay otra operación de población en curso para esta afiliación. Intente en unos minutos.');

            return $update->fresh() ?? $update;
        }

        try {
            DB::transaction(static function () use ($update): void {
                $locked = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->status !== CorporatePopulationUpdate::STATUS_ROLLING_BACK) {
                    throw new RuntimeException('La carga no está en «Revirtiendo».');
                }

                $owner = AffiliationCorporate::query()->lockForUpdate()->findOrFail($locked->affiliation_corporate_id);
                $planRows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $owner->getKey())->lockForUpdate()->get()->keyBy('id');
                $before = PopulationUpdateApplier::totals($owner, $planRows);

                $items = Item::query()
                    ->where('corporate_population_update_id', $locked->getKey())
                    ->where('apply_status', Item::APPLY_APPLIED)
                    ->orderByDesc('id')
                    ->get()
                    ->sortBy(static fn (Item $item): int => match ($item->action) {
                        Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE => 2,
                        Item::ACTION_FIX_DOCUMENT => 1,
                        default => 0,
                    })
                    ->values();

                $affiliates = AffiliateCorporate::query()
                    ->whereIn('id', $items->pluck('affiliate_corporate_id')->filter()->all() ?: [0])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $linked = PopulationActivityInspector::linkedPatients($owner, $affiliates->values());
                $since = PopulationActivityInspector::activitySince($linked, $locked->applied_at ?? now());

                $delta = ['persons' => 0, 'fee' => 0.0, 'rows' => []];
                $reverted = [];
                $notReverted = [];

                foreach ($items as $item) {
                    $affiliate = $item->affiliate_corporate_id ? $affiliates->get((int) $item->affiliate_corporate_id) : null;
                    $name = (string) ($item->details['base_name'] ?? ($item->file_data['full_name'] ?? 'Afiliado'));
                    $outcome = self::revertItem($item, $affiliate, $linked, $since);

                    if ($outcome['reverted']) {
                        $result = (array) $item->result;
                        $delta['persons'] -= (int) ($result['persons_delta'] ?? 0);
                        $delta['fee'] -= (float) ($result['fee_delta'] ?? 0);
                        $rowId = $result['plan_row_id'] ?? null;
                        if ($rowId !== null && (int) ($result['persons_delta'] ?? 0) !== 0) {
                            $delta['rows'][(int) $rowId] = ($delta['rows'][(int) $rowId] ?? 0) - (int) $result['persons_delta'];
                        }

                        $reverted[] = $name;
                        $item->forceFill(['apply_status' => Item::APPLY_REVERTED, 'result' => $result + ['rollback' => $outcome['detail']]])->save();
                    } else {
                        $notReverted[] = ['name' => $name, 'reason' => $outcome['detail']];
                        $item->forceFill(['apply_status' => Item::APPLY_NOT_REVERTED, 'result' => (array) $item->result + ['rollback' => $outcome['detail']]])->save();
                    }
                }

                PopulationUpdateApplier::applyTotals($owner, $planRows, $delta, 1);
                $after = PopulationUpdateApplier::totals($owner->fresh(), $planRows->map->fresh());

                $locked->forceFill([
                    'status' => CorporatePopulationUpdate::STATUS_ROLLED_BACK,
                    'rolled_back_at' => now(),
                    'rollback_summary' => [
                        'reverted' => count($reverted),
                        'not_reverted' => $notReverted,
                        'totals_before' => $before,
                        'totals_after' => $after,
                    ],
                ])->save();

                SecurityAudit::log('AUDIT_CORPORATE_POPULATION_ROLLED_BACK', 'business.corporate-population.rollback', [
                    'update_id' => $locked->getKey(),
                    'affiliation_code' => $owner->code,
                    'note' => $locked->rollback_note,
                    'reverted' => count($reverted),
                    'not_reverted' => $notReverted,
                    'totals_before' => $before,
                    'totals_after' => $after,
                ], User::query()->find($locked->rolled_back_by));
            });

            self::notify($update->fresh(), true);
        } catch (Throwable $exception) {
            report($exception);
            self::fail($update, $exception instanceof RuntimeException ? $exception->getMessage() : 'Error inesperado al revertir. No se cambió nada.');
            self::notify($update->fresh(), false);
        } finally {
            $lock->release();
        }

        return $update->fresh() ?? $update;
    }

    /**
     * @param  array<int, list<TelemedicinePatient>>  $linked
     * @param  array<int, list<string>>  $since
     * @return array{reverted: bool, detail: string}
     */
    private static function revertItem(Item $item, ?AffiliateCorporate $affiliate, array $linked, array $since): array
    {
        $snapshot = (array) $item->snapshot;
        $before = (array) ($snapshot['affiliate'] ?? []);
        $id = (int) ($affiliate?->getKey() ?? 0);

        if (! $affiliate instanceof AffiliateCorporate) {
            return ['reverted' => false, 'detail' => 'El afiliado ya no existe.'];
        }

        $status = strtoupper(trim((string) $affiliate->status));

        switch ($item->action) {
            case Item::ACTION_ADD:
                if ($status !== 'ACTIVO') {
                    return ['reverted' => false, 'detail' => 'Alguien cambió su estatus después ('.$status.').'];
                }

                if (isset($since[$id])) {
                    return ['reverted' => false, 'detail' => implode('; ', $since[$id]).'. Debe revisarlo Operaciones.'];
                }

                $hasLinks = ($linked[$id] ?? []) !== []
                    || AffiliateCorporateUpgrade::query()->where('affiliate_corporate_id', $id)->exists()
                    || filled($affiliate->vaucherIls);

                if ($hasLinks) {
                    $affiliate->forceFill(['status' => 'INACTIVO'])->save();
                    self::restorePatients($snapshot, $linked[$id] ?? [], 'INACTIVO');

                    return ['reverted' => true, 'detail' => 'Quedó INACTIVO (tiene datos asociados y no se borra).'];
                }

                $affiliate->delete();

                return ['reverted' => true, 'detail' => 'Se eliminó el afiliado creado por la carga.'];

            case Item::ACTION_REACTIVATE:
                if ($status !== 'ACTIVO') {
                    return ['reverted' => false, 'detail' => 'Alguien cambió su estatus después ('.$status.').'];
                }

                if (isset($since[$id])) {
                    return ['reverted' => false, 'detail' => implode('; ', $since[$id]).'. Debe revisarlo Operaciones.'];
                }

                $affiliate->forceFill([
                    'status' => $before['status'] ?? 'INACTIVO',
                    'nro_identificacion' => $before['nro_identificacion'] ?? $affiliate->nro_identificacion,
                ])->save();
                self::restorePatients($snapshot, $linked[$id] ?? []);

                return ['reverted' => true, 'detail' => 'Volvió a '.($before['status'] ?? 'INACTIVO').'.'];

            case Item::ACTION_DEACTIVATE:
            case Item::ACTION_DUPLICATE_BASE:
                if ($status !== 'INACTIVO') {
                    return ['reverted' => false, 'detail' => 'Alguien cambió su estatus después ('.$status.').'];
                }

                try {
                    $affiliate->forceFill(['status' => $before['status'] ?? 'ACTIVO'])->save();
                } catch (DuplicatePersonIdentityException $exception) {
                    $affiliate->forceFill(['status' => $status]);

                    return ['reverted' => false, 'detail' => 'No vuelve a quedar vigente: '.$exception->getMessage()];
                }

                self::restorePatients($snapshot, $linked[$id] ?? []);
                TelemedicinePatientPlanBridge::syncFromAffiliateCorporate($affiliate);

                return ['reverted' => true, 'detail' => 'Volvió a '.($before['status'] ?? 'ACTIVO').'.'];

            case Item::ACTION_FIX_DOCUMENT:
                $applied = (string) ($item->result['document'] ?? '');
                if ((string) $affiliate->nro_identificacion !== $applied) {
                    return ['reverted' => false, 'detail' => 'Alguien volvió a cambiar su cédula después.'];
                }

                $affiliate->forceFill(['nro_identificacion' => $before['nro_identificacion'] ?? $affiliate->nro_identificacion])->save();
                self::restorePatients($snapshot, $linked[$id] ?? []);

                return ['reverted' => true, 'detail' => 'Cédula restituida a «'.($before['nro_identificacion'] ?? '').'».'];
        }

        return ['reverted' => false, 'detail' => 'Acción sin reverso.'];
    }

    /**
     * Devuelve a los pacientes ligados su estatus y cédula previos (o fuerza un estatus).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<TelemedicinePatient>  $current
     */
    private static function restorePatients(array $snapshot, array $current, ?string $forceStatus = null): void
    {
        $previous = collect((array) ($snapshot['patients'] ?? []))->keyBy('id');
        $patients = collect($current)->keyBy(static fn (TelemedicinePatient $p): int => (int) $p->getKey());

        foreach ($previous as $id => $row) {
            $patient = $patients->get((int) $id) ?? TelemedicinePatient::query()->find((int) $id);
            if (! $patient instanceof TelemedicinePatient) {
                continue;
            }

            $changes = ['status_affiliation' => $forceStatus ?? ($row['status_affiliation'] ?? $patient->status_affiliation)];
            $previousDocument = (string) ($row['nro_identificacion'] ?? '');
            if ($previousDocument !== '' && $previousDocument !== (string) $patient->nro_identificacion
                && ! TelemedicinePatient::query()->where('nro_identificacion', $previousDocument)->whereKeyNot($patient->getKey())->exists()) {
                $changes['nro_identificacion'] = $previousDocument;
            }

            $patient->forceFill($changes)->save();
            TelemedicinePatientPlanBridge::flushCachesFor((int) $patient->getKey());
        }

        if ($forceStatus !== null) {
            foreach ($patients as $id => $patient) {
                if (! $previous->has($id)) {
                    $patient->forceFill(['status_affiliation' => $forceStatus])->save();
                    TelemedicinePatientPlanBridge::flushCachesFor((int) $id);
                }
            }
        }
    }

    private static function fail(CorporatePopulationUpdate $update, string $message): void
    {
        CorporatePopulationUpdate::query()->whereKey($update->getKey())->update([
            'status' => CorporatePopulationUpdate::STATUS_APPLIED,
            'error' => 'No se revirtió: '.$message,
            'rolled_back_by' => null,
            'rolled_back_by_name' => null,
            'rollback_note' => null,
            'updated_at' => now(),
        ]);
    }

    private static function notify(?CorporatePopulationUpdate $update, bool $ok): void
    {
        if ($update === null) {
            return;
        }

        $user = User::query()->find($update->rolled_back_by ?? $update->approved_by);
        if ($user === null) {
            return;
        }

        $summary = (array) ($update->rollback_summary ?? []);
        $notReverted = count((array) ($summary['not_reverted'] ?? []));
        $url = PopulationUpdateUrls::detail($update);

        Notification::make()
            ->title($ok ? 'Carga revertida' : 'La carga no se revirtió')
            ->body($ok
                ? 'Se revirtieron '.(int) ($summary['reverted'] ?? 0).' cambio(s).'.($notReverted > 0 ? ' '.$notReverted.' no se revirtieron porque cambiaron después o tienen actividad clínica: revise el detalle.' : '')
                : (string) $update->error.' No se cambió ningún afiliado.')
            ->{$ok ? ($notReverted > 0 ? 'warning' : 'success') : 'danger'}()
            ->actions($url !== null ? [Action::make('open')->label('Ver detalle')->url($url)->button()] : [])
            ->sendToDatabase($user);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Aplica una carga aprobada. Todo o nada, con la afiliación bloqueada.
 *
 * Totales: incrementales. Cada alta o reactivación suma su tarifa y una persona
 * a la afiliación y a su fila de plan; cada baja las resta. No se recalcula la
 * afiliación entera: los totales de muchas afiliaciones ya traen ajustes a mano
 * (afiliados sin edad) que un recálculo global movería sin motivo. El rollback
 * aplica exactamente lo inverso.
 */
final class PopulationUpdateApplier
{
    public static function lockKey(int $affiliationId): string
    {
        return 'corporate-population-update:'.$affiliationId;
    }

    /**
     * Marca la carga para aplicar (se ejecuta en cola). Valida lo que se puede
     * validar en el acto para que el analista vea el error en pantalla.
     */
    public static function approve(CorporatePopulationUpdate $update, User $approver, bool $massConfirmed): void
    {
        DB::transaction(static function () use ($update, $approver, $massConfirmed): void {
            $locked = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== CorporatePopulationUpdate::STATUS_ANALYZED) {
                throw new RuntimeException('Esta carga ya no está lista para aplicar (estatus: '.$locked->statusLabel().').');
            }

            $selected = self::selectedCounts($locked);
            if (array_sum($selected) === 0) {
                throw new RuntimeException('No hay cambios seleccionados para aplicar.');
            }

            $deactivations = ($selected[Item::ACTION_DEACTIVATE] ?? 0) + ($selected[Item::ACTION_DUPLICATE_BASE] ?? 0);
            if (PopulationUpdateAnalyzer::isMassDeactivation($deactivations, (int) ($locked->summary['base_countable'] ?? 0)) && ! $massConfirmed) {
                throw new RuntimeException('Esta carga da de baja a '.$deactivations.' afiliados. Confirme explícitamente que el archivo es la población completa.');
            }

            self::assertNoOtherBusyUpdate($locked);

            $locked->forceFill([
                'status' => CorporatePopulationUpdate::STATUS_APPLYING,
                'approved_by' => $approver->getKey(),
                'approved_by_name' => (string) $approver->name,
                'approved_at' => now(),
                'error' => null,
            ])->save();
        });
    }

    public static function apply(CorporatePopulationUpdate $update): CorporatePopulationUpdate
    {
        $lock = Cache::lock(self::lockKey((int) $update->affiliation_corporate_id), 600);

        if (! $lock->get()) {
            self::fail($update, 'Hay otra actualización de población en curso para esta afiliación. Intente en unos minutos.');

            return $update->fresh() ?? $update;
        }

        try {
            DB::transaction(static function () use ($update): void {
                $locked = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->status !== CorporatePopulationUpdate::STATUS_APPLYING) {
                    throw new RuntimeException('La carga no está en «Aplicando» (estatus: '.$locked->statusLabel().').');
                }

                $owner = AffiliationCorporate::query()->lockForUpdate()->findOrFail($locked->affiliation_corporate_id);
                $planRows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $owner->getKey())->lockForUpdate()->get()->keyBy('id');
                $before = self::totals($owner, $planRows);

                $items = Item::query()
                    ->where('corporate_population_update_id', $locked->getKey())
                    ->where('selected', true)
                    ->whereIn('action', [...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE])
                    ->orderBy('id')
                    ->get()
                    ->sortBy(static fn (Item $item): string => self::applyOrder($item))
                    ->values();

                $affiliates = AffiliateCorporate::query()
                    ->whereIn('id', $items->pluck('affiliate_corporate_id')->filter()->all() ?: [0])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                self::assertFresh($items, $affiliates);

                $delta = ['persons' => 0, 'fee' => 0.0, 'rows' => []];
                $planResolution = CorporateAffiliatePlanSynchronizer::planRowsFor($owner);
                $linked = PopulationActivityInspector::linkedPatients($owner, $affiliates->values());
                $existingDocs = AffiliateCorporate::query()
                    ->where('affiliation_corporate_id', $owner->getKey())
                    ->pluck('nro_identificacion')
                    ->map(static fn (mixed $document): string => PopulationPerson::documentKey((string) $document))
                    ->filter(static fn (string $key): bool => PopulationPerson::isIdentifyingDocument($key))
                    ->flip()
                    ->all();

                foreach ($items as $item) {
                    $affiliate = $item->affiliate_corporate_id ? $affiliates->get((int) $item->affiliate_corporate_id) : null;

                    $result = match ($item->action) {
                        Item::ACTION_ADD => self::add($owner, $item, $planRows, $locked, $existingDocs),
                        Item::ACTION_REACTIVATE => self::reactivate($owner, $item, $affiliate, $planResolution, $linked),
                        Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE => self::deactivate($owner, $item, $affiliate, $planResolution, $linked),
                        Item::ACTION_FIX_DOCUMENT => self::fixDocument($item, $affiliate, $linked),
                        default => null,
                    };

                    if ($result === null) {
                        continue;
                    }

                    $delta['persons'] += $result['persons'];
                    $delta['fee'] += $result['fee'];
                    if ($result['plan_row_id'] !== null && $result['persons'] !== 0) {
                        $delta['rows'][$result['plan_row_id']] = ($delta['rows'][$result['plan_row_id']] ?? 0) + $result['persons'];
                    }

                    $item->forceFill([
                        'snapshot' => $result['snapshot'],
                        'result' => $result['result'] + ['plan_row_id' => $result['plan_row_id'], 'persons_delta' => $result['persons'], 'fee_delta' => $result['fee']],
                        'apply_status' => Item::APPLY_APPLIED,
                    ])->save();
                }

                self::applyTotals($owner, $planRows, $delta, 1);
                $after = self::totals($owner->fresh(), $planRows->map->fresh());

                $locked->forceFill([
                    'status' => CorporatePopulationUpdate::STATUS_APPLIED,
                    'applied_at' => now(),
                    'totals_before' => $before,
                    'totals_after' => $after + ['delta' => $delta],
                    'error' => null,
                ])->save();

                SecurityAudit::log('AUDIT_CORPORATE_POPULATION_APPLIED', 'business.corporate-population.apply', [
                    'update_id' => $locked->getKey(),
                    'affiliation_code' => $owner->code,
                    'applied' => $items->countBy('action')->all(),
                    'totals_before' => $before,
                    'totals_after' => $after,
                ], User::query()->find($locked->approved_by));
            });

            self::notify($update->fresh(), true);
        } catch (Throwable $exception) {
            report($exception);
            self::fail($update, $exception instanceof RuntimeException ? $exception->getMessage() : 'Error inesperado al aplicar. No se cambió nada.');
            self::notify($update->fresh(), false);
        } finally {
            $lock->release();
        }

        return $update->fresh() ?? $update;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AfilliationCorporatePlan>  $planRows
     * @return array{persons: int, fee: float, plan_row_id: int|null, snapshot: array<string, mixed>, result: array<string, mixed>}
     */
    /**
     * @param  array<string, int>  $existingDocs  cédulas ya presentes en la afiliación (se actualiza al crear)
     */
    private static function add(AffiliationCorporate $owner, Item $item, $planRows, CorporatePopulationUpdate $update, array &$existingDocs): array
    {
        $person = (array) $item->file_data;
        $details = (array) $item->details;
        $row = $planRows->get((int) ($details['plan_row_id'] ?? 0));

        if (! $row instanceof AfilliationCorporatePlan) {
            throw new RuntimeException('La fila de plan asignada a «'.($person['full_name'] ?? '').'» ya no existe. Vuelva a analizar el archivo.');
        }

        $document = PopulationPerson::storedDocument((string) ($person['document'] ?? ''));
        $docKey = PopulationPerson::documentKey($document);
        if (PopulationPerson::isIdentifyingDocument($docKey) && ! ($person['minor_document'] ?? false)) {
            if (isset($existingDocs[$docKey])) {
                throw new RuntimeException('«'.($person['full_name'] ?? '').'» ya existe en la afiliación (alguien lo agregó después del análisis). Vuelva a analizar el archivo.');
            }

            $existingDocs[$docKey] = 1;
        }

        $fee = round((float) $row->fee, 2);
        $age = PopulationPerson::ageAt($person['birth_date'] ?? null);

        $affiliate = new AffiliateCorporate;
        $affiliate->forceFill([
            'affiliation_corporate_id' => $owner->getKey(),
            'affiliation_type' => 'ESTANDARD',
            'first_name' => mb_substr((string) ($person['first_name'] ?? ''), 0, 100),
            'last_name' => mb_substr((string) ($person['last_name'] ?? ''), 0, 100),
            'nro_identificacion' => $document,
            'birth_date' => PopulationPerson::toStoredBirthDate($person['birth_date'] ?? null),
            'age' => $age,
            'sex' => $person['sex'] ?? null,
            'relationship' => $person['relationship'] ?? null,
            'condition_medical' => 'SANO',
            'plan_id' => $row->plan_id,
            'coverage_id' => $row->coverage_id,
            'fee' => $fee,
            'subtotal_anual' => $fee,
            'subtotal_payment_frequency' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $owner->payment_frequency), 2),
            'subtotal_daily' => round($fee / 30, 2),
            'payment_frequency' => $owner->payment_frequency,
            'status' => 'ACTIVO',
            'business_unit_id' => $owner->business_unit_id,
            'business_line_id' => $owner->business_line_id,
            'specific_business_unit' => AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($owner->specific_business_unit),
            'created_by' => $update->approved_by,
        ]);
        $affiliate->save();

        $item->affiliate_corporate_id = (int) $affiliate->getKey();

        return [
            'persons' => 1,
            'fee' => $fee,
            'plan_row_id' => (int) $row->getKey(),
            'snapshot' => ['created' => true],
            'result' => ['affiliate_id' => (int) $affiliate->getKey(), 'plan_row_id' => (int) $row->getKey(), 'fee' => $fee],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AfilliationCorporatePlan>  $planResolution
     * @param  array<int, list<TelemedicinePatient>>  $linked
     * @return array{persons: int, fee: float, plan_row_id: int|null, snapshot: array<string, mixed>, result: array<string, mixed>}
     */
    private static function reactivate(AffiliationCorporate $owner, Item $item, ?AffiliateCorporate $affiliate, $planResolution, array $linked): array
    {
        if (! $affiliate instanceof AffiliateCorporate) {
            throw new RuntimeException('Un afiliado a reactivar ya no existe. Vuelva a analizar el archivo.');
        }

        $snapshot = self::snapshot($affiliate, $linked);
        $fixTo = $item->details['fix_document_to'] ?? null;

        $affiliate->forceFill(array_filter([
            'status' => 'ACTIVO',
            'nro_identificacion' => is_string($fixTo) && $fixTo !== '' ? $fixTo : null,
        ], static fn (mixed $value): bool => $value !== null))->save();

        $patients = self::syncPatients($linked[(int) $affiliate->getKey()] ?? [], 'ACTIVO', is_string($fixTo) ? $fixTo : null);
        TelemedicinePatientPlanBridge::syncFromAffiliateCorporate($affiliate);

        return [
            'persons' => 1,
            'fee' => round((float) $affiliate->fee, 2),
            'plan_row_id' => self::planRowIdFor($owner, $affiliate, $planResolution),
            'snapshot' => $snapshot,
            'result' => ['status' => 'ACTIVO', 'document' => $affiliate->nro_identificacion, 'patients' => $patients],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AfilliationCorporatePlan>  $planResolution
     * @param  array<int, list<TelemedicinePatient>>  $linked
     * @return array{persons: int, fee: float, plan_row_id: int|null, snapshot: array<string, mixed>, result: array<string, mixed>}
     */
    private static function deactivate(AffiliationCorporate $owner, Item $item, ?AffiliateCorporate $affiliate, $planResolution, array $linked): array
    {
        if (! $affiliate instanceof AffiliateCorporate) {
            throw new RuntimeException('Un afiliado a dar de baja ya no existe. Vuelva a analizar el archivo.');
        }

        $open = PopulationActivityInspector::openActivity([(int) $affiliate->getKey() => $linked[(int) $affiliate->getKey()] ?? []]);
        if ($open !== []) {
            throw new RuntimeException('«'.trim($affiliate->first_name.' '.$affiliate->last_name).'» abrió un caso después del análisis ('.implode('; ', reset($open)).'). Vuelva a analizar el archivo.');
        }

        $snapshot = self::snapshot($affiliate, $linked);
        $affiliate->forceFill(['status' => 'INACTIVO'])->save();
        $patients = self::syncPatients($linked[(int) $affiliate->getKey()] ?? [], 'INACTIVO', null);

        return [
            'persons' => -1,
            'fee' => -round((float) $affiliate->fee, 2),
            'plan_row_id' => self::planRowIdFor($owner, $affiliate, $planResolution),
            'snapshot' => $snapshot,
            'result' => ['status' => 'INACTIVO', 'patients' => $patients],
        ];
    }

    /**
     * @param  array<int, list<TelemedicinePatient>>  $linked
     * @return array{persons: int, fee: float, plan_row_id: int|null, snapshot: array<string, mixed>, result: array<string, mixed>}
     */
    private static function fixDocument(Item $item, ?AffiliateCorporate $affiliate, array $linked): array
    {
        $fixTo = (string) ($item->details['fix_document_to'] ?? '');
        if (! $affiliate instanceof AffiliateCorporate || $fixTo === '') {
            throw new RuntimeException('Una corrección de cédula ya no es válida. Vuelva a analizar el archivo.');
        }

        $snapshot = self::snapshot($affiliate, $linked);
        $affiliate->forceFill(['nro_identificacion' => $fixTo])->save();
        $patients = self::syncPatients($linked[(int) $affiliate->getKey()] ?? [], null, $fixTo);

        return [
            'persons' => 0,
            'fee' => 0.0,
            'plan_row_id' => null,
            'snapshot' => $snapshot,
            'result' => ['document' => $fixTo, 'patients' => $patients],
        ];
    }

    /**
     * Copia del afiliado y de sus pacientes antes del cambio: con esto se revierte.
     *
     * @param  array<int, list<TelemedicinePatient>>  $linked
     * @return array<string, mixed>
     */
    private static function snapshot(AffiliateCorporate $affiliate, array $linked): array
    {
        return [
            'affiliate' => $affiliate->getAttributes(),
            'patients' => array_map(static fn (TelemedicinePatient $patient): array => [
                'id' => (int) $patient->getKey(),
                'status_affiliation' => $patient->status_affiliation,
                'nro_identificacion' => $patient->nro_identificacion,
            ], $linked[(int) $affiliate->getKey()] ?? []),
        ];
    }

    /**
     * Copia de estatus y cédula en el paciente de telemedicina ligado. La cédula
     * solo se cambia si ningún otro paciente ya la usa (el paciente es único por cédula).
     *
     * @param  list<TelemedicinePatient>  $patients
     * @return list<array{id: int, status: string|null, document: string|null, document_skipped: bool}>
     */
    private static function syncPatients(array $patients, ?string $status, ?string $document): array
    {
        $out = [];

        foreach ($patients as $patient) {
            $changes = [];
            if ($status !== null) {
                $changes['status_affiliation'] = $status;
            }

            $skipped = false;
            if ($document !== null) {
                $normalized = \App\Support\Telemedicine\TelemedicinePatientIdentity::normalizeDocument($document);
                $taken = TelemedicinePatient::query()->where('nro_identificacion', $normalized)->whereKeyNot($patient->getKey())->exists();
                if ($taken) {
                    $skipped = true;
                } else {
                    $changes['nro_identificacion'] = $normalized;
                }
            }

            if ($changes !== []) {
                $patient->forceFill($changes)->save();
                TelemedicinePatientPlanBridge::flushCachesFor((int) $patient->getKey());
            }

            $out[] = ['id' => (int) $patient->getKey(), 'status' => $changes['status_affiliation'] ?? null, 'document' => $changes['nro_identificacion'] ?? null, 'document_skipped' => $skipped];
        }

        return $out;
    }

    /**
     * Fila de plan del afiliado para el ajuste de personas: por edad; si no
     * tiene edad, por plan y cobertura si hay una sola; si no, ninguna.
     *
     * @param  \Illuminate\Support\Collection<int, AfilliationCorporatePlan>  $planResolution
     */
    public static function planRowIdFor(AffiliationCorporate $owner, AffiliateCorporate $affiliate, $planResolution): ?int
    {
        $row = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $affiliate, $planResolution)['row'];
        if ($row instanceof AfilliationCorporatePlan) {
            return (int) $row->getKey();
        }

        $same = $planResolution->filter(static fn (AfilliationCorporatePlan $r): bool => (int) $r->plan_id === (int) $affiliate->plan_id
            && (int) ($r->coverage_id ?? 0) === (int) ($affiliate->coverage_id ?? 0));

        return $same->count() === 1 ? (int) $same->first()->getKey() : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AfilliationCorporatePlan>  $planRows
     * @param  array{persons: int, fee: float, rows: array<int, int>}  $delta
     */
    public static function applyTotals(AffiliationCorporate $owner, $planRows, array $delta, int $sign): void
    {
        $owner->poblation = max(0, (int) $owner->poblation + $sign * $delta['persons']);
        $owner->fee_anual = max(0, round((float) $owner->fee_anual + $sign * $delta['fee'], 2));
        $owner->total_amount = \App\Support\Upgrades\UpgradeFirstPaymentBilling::corporateRegular($owner, (float) $owner->fee_anual);
        $owner->save();

        foreach ($delta['rows'] as $rowId => $persons) {
            $row = $planRows->get((int) $rowId);
            if (! $row instanceof AfilliationCorporatePlan) {
                continue;
            }

            $row->total_persons = max(0, (int) $row->total_persons + $sign * (int) $persons);
            CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);
            $row->save();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AfilliationCorporatePlan>  $planRows
     * @return array<string, mixed>
     */
    public static function totals(AffiliationCorporate $owner, $planRows): array
    {
        return [
            'poblation' => (int) $owner->poblation,
            'fee_anual' => round((float) $owner->fee_anual, 2),
            'total_amount' => round((float) $owner->total_amount, 2),
            'plan_rows' => $planRows->map(static fn (AfilliationCorporatePlan $row): array => [
                'id' => (int) $row->getKey(),
                'total_persons' => (int) $row->total_persons,
                'subtotal_anual' => round((float) $row->subtotal_anual, 2),
            ])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Item>  $items
     * @param  \Illuminate\Support\Collection<int, AffiliateCorporate>  $affiliates
     */
    private static function assertFresh($items, $affiliates): void
    {
        foreach ($items as $item) {
            if ($item->affiliate_corporate_id === null) {
                continue;
            }

            $affiliate = $affiliates->get((int) $item->affiliate_corporate_id);
            $observed = (array) ($item->details['observed'] ?? []);

            if (! $affiliate instanceof AffiliateCorporate || PopulationUpdateAnalyzer::fingerprint($affiliate) !== $observed) {
                throw new RuntimeException('La población cambió después del análisis ('.($item->details['base_name'] ?? 'un afiliado').'). Vuelva a analizar el archivo para no aplicar sobre datos viejos.');
            }
        }
    }

    private static function assertNoOtherBusyUpdate(CorporatePopulationUpdate $update): void
    {
        $busy = CorporatePopulationUpdate::query()
            ->where('affiliation_corporate_id', $update->affiliation_corporate_id)
            ->whereKeyNot($update->getKey())
            ->whereIn('status', [CorporatePopulationUpdate::STATUS_APPLYING, CorporatePopulationUpdate::STATUS_ROLLING_BACK])
            ->exists();

        if ($busy) {
            throw new RuntimeException('Hay otra actualización aplicándose o revirtiéndose en esta afiliación. Espere a que termine.');
        }
    }

    /**
     * @return array<string, int>
     */
    public static function selectedCounts(CorporatePopulationUpdate $update): array
    {
        return Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('selected', true)
            ->whereIn('action', [...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE])
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    private static function fail(CorporatePopulationUpdate $update, string $message): void
    {
        CorporatePopulationUpdate::query()->whereKey($update->getKey())->update([
            'status' => CorporatePopulationUpdate::STATUS_ANALYZED,
            'error' => 'No se aplicó: '.$message,
            'approved_by' => null,
            'approved_by_name' => null,
            'approved_at' => null,
            'updated_at' => now(),
        ]);
    }

    private static function notify(?CorporatePopulationUpdate $update, bool $ok): void
    {
        if ($update === null) {
            return;
        }

        $user = User::query()->find($update->approved_by ?? $update->analyzed_by);
        if ($user === null) {
            return;
        }

        $url = PopulationUpdateUrls::detail($update);

        Notification::make()
            ->title($ok ? 'Población actualizada' : 'La actualización de población no se aplicó')
            ->body($ok
                ? 'Se aplicaron los cambios aprobados de «'.$update->file_name.'». Si algo no está bien, puede revertir la carga desde su detalle.'
                : (string) $update->error.' No se cambió ningún afiliado.')
            ->{$ok ? 'success' : 'danger'}()
            ->actions($url !== null ? [Action::make('open')->label('Ver detalle')->url($url)->button()] : [])
            ->sendToDatabase($user);
    }

    /**
     * Las bajas van primero: si el archivo reemplaza a una persona por otra fila con la cédula bien
     * escrita, el alta no choca con el candado de personas duplicadas por la fila que sale.
     */
    private static function applyOrder(Item $item): string
    {
        $priority = match ($item->action) {
            Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE => 0,
            Item::ACTION_FIX_DOCUMENT => 1,
            default => 2,
        };

        return $priority.'-'.str_pad((string) $item->getKey(), 12, '0', STR_PAD_LEFT);
    }
}

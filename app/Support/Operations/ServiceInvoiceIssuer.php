<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\OperationMedicationInvoiceItem;
use App\Models\User;
use App\Support\BcvOfficialRate;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Factura al paciente de laboratorios, estudios y consultas con especialista NO CUBIERTOS.
 *
 * Decisiones de negocio (07/11/2026):
 * - El precio sale del catálogo (costo × (1 + %)); si falta costo o %, el analista lo carga y
 *   queda guardado en la fila del catálogo.
 * - Facturar NO finaliza el ítem: queda EN GESTION y se crea la orden al proveedor que lo
 *   realiza; se finaliza cuando esa orden se finaliza.
 * - Misma factura interna (correlativo FAC-), cuenta por cobrar, bitácora y auditoría que los
 *   medicamentos. Cantidad 1 por ítem.
 *
 * Corre dentro de la transacción del guardado, con ítems y filas del catálogo bloqueados.
 */
final class ServiceInvoiceIssuer
{
    public const AUDIT_CATALOG_PRICE_LOADED = 'AUDIT_OPERATIONS_CLINICAL_CATALOG_PRICE_LOADED';

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  list<string>  $invoiceKeys
     * @return list<array{key: string, item_id: int, catalog_id: int, new_cost: float|null, new_percentage: float|null}>
     */
    public static function planFromRows(array $rows, array $invoiceKeys): array
    {
        $plan = [];

        foreach ($rows as $row) {
            if (! is_array($row) || (bool) ($row['generate_quote'] ?? false)) {
                continue;
            }

            $key = (string) ($row['key'] ?? '');

            if (! in_array($key, $invoiceKeys, true)) {
                continue;
            }

            $plan[] = [
                'key' => $key,
                'item_id' => ClinicalServiceCatalog::idFromKey($key),
                'catalog_id' => (int) ($row['catalog_id'] ?? 0),
                'new_cost' => is_numeric($row['new_cost'] ?? null) ? round((float) $row['new_cost'], 2) : null,
                'new_percentage' => is_numeric($row['new_percentage'] ?? null) ? round((float) $row['new_percentage'], 2) : null,
            ];
        }

        return $plan;
    }

    /**
     * @param  list<array{key: string, item_id: int, catalog_id: int, new_cost: float|null, new_percentage: float|null}>  $plan
     */
    public static function validationMessage(OperationCoordinationService $record, string $kind, array $plan): ?string
    {
        try {
            self::assertPlan($record, $kind, $plan, lock: false);
        } catch (MedicationInvoiceException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * @param  list<array{key: string, item_id: int, catalog_id: int, new_cost: float|null, new_percentage: float|null}>  $plan
     *
     * @throws MedicationInvoiceException
     */
    public static function issue(OperationCoordinationService $record, string $kind, array $plan, ?User $user = null): OperationMedicationInvoice
    {
        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $definition = ClinicalServiceCatalog::definition($kind);

        $resolved = self::assertPlan($record, $kind, $plan, lock: true);
        $bcvRate = BcvOfficialRate::resolve();

        if ($bcvRate === null || $bcvRate <= 0) {
            throw new MedicationInvoiceException('No fue posible obtener la tasa BCV del día. Intente de nuevo en unos minutos; no se facturó nada.');
        }

        $record->loadMissing(['telemedicinePatient', 'telemedicineCase']);
        $patient = $record->telemedicinePatient;
        $totalUsd = round(array_sum(array_column($resolved['lines'], 'price')), 2);

        $invoice = OperationMedicationInvoice::query()->create([
            'operation_coordination_service_id' => $record->getKey(),
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'patient_name' => TelemedicinePatientDisplayName::forCoordination($record) ?: (string) ($patient?->full_name ?? '—'),
            'patient_identification' => filled($patient?->nro_identificacion) ? (string) $patient->nro_identificacion : null,
            'case_code' => filled($record->telemedicineCase?->code) ? mb_strtoupper((string) $record->telemedicineCase->code) : null,
            'status' => OperationMedicationInvoice::STATUS_PENDING_PAYMENT,
            'invoice_type' => $definition['invoice_type'],
            'total_usd' => $totalUsd,
            'bcv_rate' => round($bcvRate, 4),
            'total_ves' => round($totalUsd * $bcvRate, 2),
            'issued_at' => now(),
            'issued_by_user_id' => $user?->getKey(),
            'issued_by_name' => $userName,
        ]);

        $invoice->forceFill(['invoice_number' => OperationMedicationInvoice::formatNumber((int) $invoice->getKey())])->save();

        $bitacoraLines = [];

        foreach ($resolved['lines'] as $line) {
            /** @var Model $item */
            $item = $line['item'];
            /** @var Model $entry */
            $entry = $line['entry'];

            OperationMedicationInvoiceItem::query()->create([
                'operation_medication_invoice_id' => $invoice->getKey(),
                'item_type' => $definition['invoice_type'],
                'source_item_id' => $item->getKey(),
                'catalog_item_id' => $entry->getKey(),
                'prescribed_name' => trim((string) $item->getAttribute($definition['name_column'])),
                'product_name' => trim((string) $entry->getAttribute('name')),
                'category_name' => $definition['label'],
                'quantity' => 1,
                'unit_cost_usd' => $line['cost'],
                'profit_percentage' => $line['percentage'],
                'unit_price_usd' => $line['price'],
                'line_total_usd' => $line['price'],
            ]);

            $bitacoraLines[] = trim((string) $entry->getAttribute('name')).' — US$ '.number_format($line['price'], 2, '.', ',');
        }

        OperationAccountsReceivable::query()->create([
            'operation_coordination_service_id' => $record->getKey(),
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'operation_medication_invoice_id' => $invoice->getKey(),
            'quote_amount_usd' => $invoice->total_usd,
            'quote_amount_ves' => $invoice->total_ves,
            'bcv_rate' => $invoice->bcv_rate,
            'reassigned_by_user_id' => $user?->getKey(),
            'reassigned_by_analyst_name' => $userName,
            'status' => OperationAccountsReceivable::STATUS_PENDING_PAYMENT,
            'is_courtesy' => false,
            'created_by' => $userName,
            'updated_by' => $userName,
        ]);

        $description = 'Factura '.$invoice->invoice_number.' ('.mb_strtolower($definition['plural']).') emitida por '.$userName.' a '.$invoice->patient_name.': '
            .implode('; ', $bitacoraLines).'. Total US$ '.number_format((float) $invoice->total_usd, 2, '.', ',')
            .' (Bs. '.number_format((float) $invoice->total_ves, 2, '.', ',').' a tasa BCV '.number_format((float) $invoice->bcv_rate, 2, '.', ',').'). '
            .'Los ítems quedan EN GESTION hasta que el proveedor realice el servicio.'
            .($resolved['loaded'] !== []
                ? ' Precios cargados en el catálogo: '.implode('; ', array_map(
                    fn (array $row): string => $row['name'].' (costo US$ '.number_format($row['cost'], 2, '.', ',').', '.number_format($row['percentage'], 2, ',', '.').' %)',
                    $resolved['loaded'],
                )).'.'
                : '');

        OperationMedicationInvoiceEvent::query()->create([
            'operation_medication_invoice_id' => $invoice->getKey(),
            'event' => OperationMedicationInvoiceEvent::EVENT_ISSUED,
            'description' => $description,
            'payload' => [
                'kind' => $kind,
                'item_ids' => array_map(fn (array $line): int => (int) $line['item']->getKey(), $resolved['lines']),
                'total_usd' => (float) $invoice->total_usd,
                'bcv_rate' => (float) $invoice->bcv_rate,
                'loaded_catalog_prices' => $resolved['loaded'],
            ],
            'user_id' => $user?->getKey(),
            'user_name' => $userName,
        ]);

        MedicationInvoiceIssuer::appendCoordinationLog($record, $description, $userName, $user?->getKey());

        $invoiceId = (int) $invoice->getKey();
        $loaded = $resolved['loaded'];

        DB::afterCommit(function () use ($invoiceId, $record, $totalUsd, $user, $loaded, $kind): void {
            foreach ($loaded as $row) {
                SecurityAudit::log(self::AUDIT_CATALOG_PRICE_LOADED, MedicationInvoiceIssuer::AUDIT_ROUTE, [
                    'catalog' => $kind,
                    'catalog_item_id' => $row['id'],
                    'name' => $row['name'],
                    'previous_cost' => $row['previous_cost'],
                    'new_cost' => $row['cost'],
                    'previous_percentage' => $row['previous_percentage'],
                    'new_percentage' => $row['percentage'],
                    'operation_medication_invoice_id' => $invoiceId,
                ], $user);
            }

            SecurityAudit::log(MedicationInvoiceIssuer::AUDIT_ISSUED, MedicationInvoiceIssuer::AUDIT_ROUTE, [
                'operation_medication_invoice_id' => $invoiceId,
                'operation_coordination_service_id' => $record->getKey(),
                'telemedicine_case_id' => $record->telemedicine_case_id,
                'kind' => $kind,
                'total_usd' => $totalUsd,
            ], $user);
        });

        return $invoice->refresh();
    }

    /**
     * @param  list<array{key: string, item_id: int, catalog_id: int, new_cost: float|null, new_percentage: float|null}>  $plan
     * @return array{
     *     lines: list<array{item: Model, entry: Model, cost: float, percentage: float, price: float}>,
     *     loaded: list<array{id: int, name: string, cost: float, percentage: float, previous_cost: float|null, previous_percentage: float|null}>
     * }
     *
     * @throws MedicationInvoiceException
     */
    private static function assertPlan(OperationCoordinationService $record, string $kind, array $plan, bool $lock): array
    {
        if (! ClinicalServiceCatalog::isServiceKind($kind)) {
            throw new MedicationInvoiceException('Este tipo de ítem no se puede facturar al paciente.');
        }

        if ($plan === []) {
            throw new MedicationInvoiceException('No hay ítems para facturar.');
        }

        $definition = ClinicalServiceCatalog::definition($kind);
        $itemIds = array_values(array_unique(array_map(fn (array $entry): int => $entry['item_id'], $plan)));
        sort($itemIds);

        if (in_array(0, $itemIds, true) || count($itemIds) !== count($plan)) {
            throw new MedicationInvoiceException('La selección no es válida. Recargue la página e intente de nuevo.');
        }

        $items = $definition['item']::query()
            ->where('operation_coordination_service_id', $record->getKey())
            ->whereIn('id', $itemIds)
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy('id');

        $nonCoveredKeys = CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys(
            $record,
            array_map(fn (int $id): string => $kind.':'.$id, $itemIds),
        );

        $catalogIds = array_values(array_unique(array_map(fn (array $entry): int => $entry['catalog_id'], $plan)));
        sort($catalogIds);

        $entries = $definition['catalog']::query()
            ->whereIn('id', $catalogIds === [] ? [0] : $catalogIds)
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy('id');

        $lines = [];
        $loaded = [];
        $requested = [];

        foreach ($plan as $entryPlan) {
            $item = $items->get($entryPlan['item_id']);

            if (! $item instanceof Model) {
                throw new MedicationInvoiceException('Uno de los ítems ya no pertenece a este servicio. Recargue la página.');
            }

            $name = trim((string) $item->getAttribute($definition['name_column']));
            $status = mb_strtoupper(trim((string) $item->getAttribute('status')));

            if (in_array($status, ['EN GESTION', 'FINALIZADO', 'CANCELADO', 'CANCELADA', 'CADUCADA'], true)) {
                throw new MedicationInvoiceException('«'.$name.'» ya está '.$status.'. Otro analista pudo gestionarlo; recargue la página.');
            }

            if (! in_array($entryPlan['key'], $nonCoveredKeys, true)) {
                throw new MedicationInvoiceException('«'.$name.'» no es un ítem NO CUBIERTO pendiente. Solo lo no cubierto se factura al paciente.');
            }

            $hasActiveInvoice = OperationMedicationInvoiceItem::query()
                ->where('item_type', $definition['invoice_type'])
                ->where('source_item_id', $item->getKey())
                ->whereHas('invoice', fn ($query) => $query->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED))
                ->exists();

            if ($hasActiveInvoice) {
                throw new MedicationInvoiceException('«'.$name.'» ya tiene una factura vigente.');
            }

            $entry = $entries->get($entryPlan['catalog_id']);

            if (! $entry instanceof Model) {
                throw new MedicationInvoiceException('Elija el ítem del catálogo de «'.$name.'».');
            }

            $entryName = trim((string) $entry->getAttribute('name'));
            $needsCost = ClinicalServiceCatalog::needsCost($entry);
            $needsPercentage = ClinicalServiceCatalog::needsPercentage($entry);

            if ($needsCost || $needsPercentage) {
                $cost = $needsCost ? $entryPlan['new_cost'] : (float) $entry->getAttribute('cost');
                $percentage = $needsPercentage ? $entryPlan['new_percentage'] : (float) $entry->getAttribute('profit_percentage');

                if ($cost === null || $cost <= 0 || $cost > MedicationInvoiceIssuer::MAX_LOADED_COST) {
                    throw new MedicationInvoiceException('Cargue un costo válido (mayor que cero) para «'.$entryName.'».');
                }

                if ($percentage === null || $percentage < 0 || $percentage > ClinicalServiceCatalog::MAX_PROFIT_PERCENTAGE) {
                    throw new MedicationInvoiceException('Cargue un % de utilidad válido (0 a '.ClinicalServiceCatalog::MAX_PROFIT_PERCENTAGE.') para «'.$entryName.'».');
                }

                $signature = $cost.'|'.$percentage;

                if (isset($requested[$entry->getKey()]) && $requested[$entry->getKey()] !== $signature) {
                    throw new MedicationInvoiceException('Indicó dos precios distintos para «'.$entryName.'». Use el mismo en todas sus líneas.');
                }

                if (! isset($requested[$entry->getKey()])) {
                    $requested[$entry->getKey()] = $signature;
                    $previousCost = is_numeric($entry->getAttribute('cost')) ? (float) $entry->getAttribute('cost') : null;
                    $previousPercentage = is_numeric($entry->getAttribute('profit_percentage')) ? (float) $entry->getAttribute('profit_percentage') : null;
                    $updates = array_filter([
                        'cost' => $needsCost ? $cost : null,
                        'profit_percentage' => $needsPercentage ? $percentage : null,
                    ], fn (mixed $value): bool => $value !== null);

                    if ($lock) {
                        $definition['catalog']::query()->whereKey($entry->getKey())->update([...$updates, 'updated_at' => now()]);
                        $loaded[] = [
                            'id' => (int) $entry->getKey(),
                            'name' => $entryName,
                            'cost' => (float) $cost,
                            'percentage' => (float) $percentage,
                            'previous_cost' => $previousCost,
                            'previous_percentage' => $previousPercentage,
                        ];
                    }

                    $entry->forceFill($updates);
                }
            }

            $price = ClinicalServiceCatalog::price($entry);

            if ($price === null) {
                throw new MedicationInvoiceException('«'.$entryName.'» no tiene precio de venta. Revise su costo y % en el catálogo.');
            }

            $lines[] = [
                'item' => $item,
                'entry' => $entry,
                'cost' => round((float) $entry->getAttribute('cost'), 2),
                'percentage' => round((float) $entry->getAttribute('profit_percentage'), 2),
                'price' => $price,
            ];
        }

        return ['lines' => $lines, 'loaded' => $loaded];
    }
}

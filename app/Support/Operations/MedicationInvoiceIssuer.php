<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventory;
use App\Models\OperationInventoryMovement;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryType;
use App\Models\OperationInventoryUbication;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\OperationMedicationInvoiceItem;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Services\OperationInventoryLowStockWatcher;
use App\Services\TelemedicineMedicationInventoryDeductor;
use App\Support\BcvOfficialRate;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Emisión de la factura de medicamentos NO CUBIERTOS despachados desde el inventario.
 *
 * Decisiones de negocio (06/11/2026):
 * - Facturar descuenta el inventario y finaliza el ítem recetado en el mismo acto.
 * - La factura es interna (no fiscal), con correlativo FMP-000001, y nace PENDIENTE POR COBRAR
 *   junto con su cuenta por cobrar.
 * - Costo, % de utilidad, precio y tasa BCV se congelan en la factura.
 *
 * Todo ocurre en una transacción con las filas bloqueadas: dos analistas no pueden vender la
 * misma última unidad ni facturar dos veces el mismo medicamento. Si una sola línea falla,
 * no se guarda nada.
 */
final class MedicationInvoiceIssuer
{
    public const DISPATCH_SOURCE_INVOICE = 'FACTURA';

    public const MAX_PRODUCTS_PER_MEDICATION = 10;

    public const MAX_QUANTITY_PER_LINE = 9999;

    /** Tope de cordura para el costo que carga el analista al facturar. */
    public const MAX_LOADED_COST = 100000;

    public const AUDIT_COST_LOADED = 'AUDIT_OPERATIONS_INVENTORY_PRODUCT_COST_LOADED';

    public const AUDIT_ISSUED = 'AUDIT_OPERATIONS_MEDICATION_INVOICE_ISSUED';

    public const AUDIT_ROUTE = 'operations.medication-invoices';

    /**
     * Normaliza las filas del asistente en un plan: por medicamento recetado, las líneas de
     * producto a facturar. Descarta filas incompletas (el formulario ya exige los campos).
     *
     * @param  array<int|string, mixed>  $rows
     * @param  list<string>  $invoiceKeys  Claves «medication:ID» que se facturan.
     * @return list<array{medication_id: int, key: string, lines: list<array{product_id: int, ubication_id: int, quantity: int, new_cost?: float|null}>}>
     */
    public static function planFromRows(array $rows, array $invoiceKeys): array
    {
        $plan = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = (string) ($row['key'] ?? '');

            if (! in_array($key, $invoiceKeys, true) || (bool) ($row['generate_quote'] ?? false)) {
                continue;
            }

            $lines = [];

            foreach ((array) ($row['products'] ?? []) as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $lines[] = [
                    'product_id' => (int) ($line['product_id'] ?? 0),
                    'ubication_id' => (int) ($line['ubication_id'] ?? 0),
                    'quantity' => (int) ($line['quantity'] ?? 0),
                    'new_cost' => is_numeric($line['new_cost'] ?? null) ? round((float) $line['new_cost'], 2) : null,
                ];
            }

            $plan[] = [
                'medication_id' => MedicationInvoiceWizard::medicationIdFromKey($key),
                'key' => $key,
                'lines' => $lines,
            ];
        }

        return $plan;
    }

    /**
     * Verificación previa (sin bloqueos) para responder rápido al analista.
     *
     * @param  list<array{medication_id: int, key: string, lines: list<array{product_id: int, ubication_id: int, quantity: int, new_cost?: float|null}>}>  $plan
     */
    public static function validationMessage(OperationCoordinationService $record, array $plan): ?string
    {
        try {
            self::assertPlan($record, $plan, lock: false);
        } catch (MedicationInvoiceException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * Emite la factura. Debe llamarse dentro de una transacción.
     *
     * @param  list<array{medication_id: int, key: string, lines: list<array{product_id: int, ubication_id: int, quantity: int, new_cost?: float|null}>}>  $plan
     *
     * @throws MedicationInvoiceException
     */
    public static function issue(OperationCoordinationService $record, array $plan, ?User $user = null): OperationMedicationInvoice
    {
        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';

        $resolved = self::assertPlan($record, $plan, lock: true);
        $bcvRate = BcvOfficialRate::resolve();

        if ($bcvRate === null || $bcvRate <= 0) {
            throw new MedicationInvoiceException('No fue posible obtener la tasa BCV del día. Intente de nuevo en unos minutos; no se facturó nada.');
        }

        $record->loadMissing(['telemedicinePatient', 'telemedicineCase']);
        $patient = $record->telemedicinePatient;
        $totalUsd = round(array_sum(array_column($resolved['lines'], 'line_total_usd')), 2);

        $invoice = OperationMedicationInvoice::query()->create([
            'operation_coordination_service_id' => $record->getKey(),
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'patient_name' => TelemedicinePatientDisplayName::forCoordination($record) ?: (string) ($patient?->full_name ?? '—'),
            'patient_identification' => filled($patient?->nro_identificacion) ? (string) $patient->nro_identificacion : null,
            'case_code' => filled($record->telemedicineCase?->code) ? mb_strtoupper((string) $record->telemedicineCase->code) : null,
            'status' => OperationMedicationInvoice::STATUS_PENDING_PAYMENT,
            'total_usd' => $totalUsd,
            'bcv_rate' => round($bcvRate, 4),
            'total_ves' => round($totalUsd * $bcvRate, 2),
            'issued_at' => now(),
            'issued_by_user_id' => $user?->getKey(),
            'issued_by_name' => $userName,
        ]);

        $invoice->forceFill(['invoice_number' => OperationMedicationInvoice::formatNumber((int) $invoice->getKey())])->save();

        $typeId = (int) (OperationInventoryType::query()->orderBy('id')->value('id') ?? 1);
        $previousTotals = [];
        $bitacoraLines = [];

        foreach ($resolved['lines'] as $line) {
            /** @var TelemedicinePatientMedications $medication */
            $medication = $resolved['medications'][$line['medication_id']];
            /** @var OperationInventory $inventory */
            $inventory = $line['inventory'];
            /** @var OperationInventoryProductStock $stock */
            $stock = $line['stock'];
            $productId = (int) $line['product']->getKey();

            $previousTotals[$productId] ??= (int) OperationInventoryProductStock::query()
                ->where('operation_inventory_product_id', $productId)
                ->sum('existence');

            $inventory->existence = (int) $inventory->existence - $line['quantity'];
            $inventory->updated_by = $userName;
            $inventory->save();

            $stock->existence = (int) $stock->existence - $line['quantity'];
            $stock->updated_by = $userName;
            $stock->save();

            OperationMedicationInvoiceItem::query()->create([
                'operation_medication_invoice_id' => $invoice->getKey(),
                'telemedicine_patient_medication_id' => $medication->getKey(),
                'prescribed_name' => trim((string) $medication->medicine),
                'prescribed_indications' => filled($medication->indications) ? trim((string) $medication->indications) : null,
                'operation_inventory_product_id' => $productId,
                'operation_inventory_id' => $inventory->getKey(),
                'operation_inventory_ubication_id' => $line['ubication']->getKey(),
                'product_code' => filled($line['product']->code) ? (string) $line['product']->code : null,
                'product_name' => trim((string) $line['product']->name),
                'category_name' => $line['product']->category?->name,
                'ubication_name' => (string) $line['ubication']->name,
                'quantity' => $line['quantity'],
                'unit_cost_usd' => $line['unit_cost_usd'],
                'profit_percentage' => $line['profit_percentage'],
                'unit_price_usd' => $line['unit_price_usd'],
                'line_total_usd' => $line['line_total_usd'],
            ]);

            OperationInventoryOutflow::query()->create([
                'operation_inventory_id' => $inventory->getKey(),
                'telemedicine_case_id' => $medication->telemedicine_case_id ?? $record->telemedicine_case_id,
                'operation_inventory_product_id' => $productId,
                'operation_inventory_ubication_id' => $line['ubication']->getKey(),
                'operation_inventory_type_id' => $typeId,
                'quantity' => $line['quantity'],
                'type_entry' => TelemedicineMedicationInventoryDeductor::OUTFLOW_TYPE,
                'observations' => 'Venta al paciente por factura '.$invoice->invoice_number,
                'created_by' => $userName,
            ]);

            OperationInventoryMovement::query()->create([
                'operation_inventory_id' => $inventory->getKey(),
                'telemedicine_patient_id' => (int) ($medication->telemedicine_patient_id ?? $record->telemedicine_patient_id ?? 0),
                'telemedicine_case_id' => (int) ($medication->telemedicine_case_id ?? $record->telemedicine_case_id ?? 0),
                'telemedicine_consultation_id' => (int) ($medication->telemedicine_consultation_patient_id ?? 0),
                'telemedicine_doctor_id' => (int) ($medication->telemedicine_doctor_id ?? 0),
                'business_unit_id' => (int) ($patient?->business_unit_id ?? 0),
                'business_line_id' => (int) ($patient?->business_line_id ?? 0),
                'quantity' => $line['quantity'],
                'unit' => (string) ($inventory->unit ?: 'UND'),
                'type' => TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE,
                'status' => 'DESPACHADO',
                'created_by' => $userName,
            ]);

            $bitacoraLines[] = $line['product']->name.' × '.$line['quantity'].' ('.$line['ubication']->name.') — US$ '.number_format($line['line_total_usd'], 2, '.', ',');
        }

        foreach ($resolved['medications'] as $medication) {
            $medication->forceFill([
                'status' => 'FINALIZADO',
                'inventory_dispatched_at' => now(),
                'inventory_dispatch_source' => self::DISPATCH_SOURCE_INVOICE,
                'inventory_dispatch_order_id' => null,
            ])->save();
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

        $description = 'Factura '.$invoice->invoice_number.' emitida por '.$userName.' a '.$invoice->patient_name.'. '
            .'Medicamentos despachados desde el inventario: '.implode('; ', $bitacoraLines).'. '
            .'Total US$ '.number_format((float) $invoice->total_usd, 2, '.', ',')
            .' (Bs. '.number_format((float) $invoice->total_ves, 2, '.', ',').' a tasa BCV '.number_format((float) $invoice->bcv_rate, 2, '.', ',').'). '
            .'Ítems recetados finalizados.'
            .($resolved['loaded_costs'] !== []
                ? ' Costos cargados al facturar: '.implode('; ', array_map(
                    fn (array $row): string => $row['product_name'].' US$ '.number_format($row['cost'], 2, '.', ','),
                    $resolved['loaded_costs'],
                )).'.'
                : '');

        OperationMedicationInvoiceEvent::query()->create([
            'operation_medication_invoice_id' => $invoice->getKey(),
            'event' => OperationMedicationInvoiceEvent::EVENT_ISSUED,
            'description' => $description,
            'payload' => [
                'medication_ids' => array_keys($resolved['medications']),
                'total_usd' => (float) $invoice->total_usd,
                'bcv_rate' => (float) $invoice->bcv_rate,
                'loaded_costs' => $resolved['loaded_costs'],
            ],
            'user_id' => $user?->getKey(),
            'user_name' => $userName,
        ]);

        self::appendCoordinationLog($record, $description, $userName, $user?->getKey());

        $invoiceId = (int) $invoice->getKey();
        $auditUser = $user;

        $loadedCosts = $resolved['loaded_costs'];

        DB::afterCommit(function () use ($previousTotals, $invoiceId, $record, $totalUsd, $auditUser, $loadedCosts): void {
            foreach ($loadedCosts as $row) {
                SecurityAudit::log(self::AUDIT_COST_LOADED, self::AUDIT_ROUTE, [
                    'operation_inventory_product_id' => $row['product_id'],
                    'product' => $row['product_name'],
                    'previous_cost' => 0,
                    'new_cost' => $row['cost'],
                    'operation_medication_invoice_id' => $invoiceId,
                ], $auditUser);
            }

            $watcher = app(OperationInventoryLowStockWatcher::class);

            foreach ($previousTotals as $productId => $previousTotal) {
                $watcher->dispatchIfCrossedThreshold((int) $productId, $previousTotal);
            }

            SecurityAudit::log(self::AUDIT_ISSUED, self::AUDIT_ROUTE, [
                'operation_medication_invoice_id' => $invoiceId,
                'operation_coordination_service_id' => $record->getKey(),
                'telemedicine_case_id' => $record->telemedicine_case_id,
                'total_usd' => $totalUsd,
            ], $auditUser);
        });

        return $invoice->refresh();
    }

    /**
     * Agrega la línea a la bitácora de la coordinación y del caso, como el resto del módulo.
     */
    public static function appendCoordinationLog(OperationCoordinationService $record, string $description, string $userName, mixed $userId): void
    {
        $previous = trim((string) ($record->observations ?? ''));
        $record->forceFill([
            'observations' => $previous !== '' ? $previous."\n\n".$description : $description,
            'updated_by' => $userName,
        ])->save();

        if (filled($record->telemedicine_case_id)) {
            ObservationCase::query()->create([
                'telemedicine_case_id' => $record->telemedicine_case_id,
                'description' => $description,
                'created_by' => $userId !== null ? (string) $userId : $userName,
            ]);
        }
    }

    /**
     * Valida el plan completo y resuelve modelos y precios. Con `$lock` bloquea medicamentos,
     * inventario y existencias (en orden estable para no provocar interbloqueos).
     *
     * @param  list<array{medication_id: int, key: string, lines: list<array{product_id: int, ubication_id: int, quantity: int, new_cost?: float|null}>}>  $plan
     * @return array{
     *     medications: array<int, TelemedicinePatientMedications>,
     *     lines: list<array{medication_id: int, product: OperationInventoryProduct, ubication: OperationInventoryUbication, inventory: OperationInventory|null, stock: OperationInventoryProductStock|null, quantity: int, unit_cost_usd: float, profit_percentage: float, unit_price_usd: float, line_total_usd: float}>,
     *     loaded_costs: list<array{product_id: int, product_name: string, cost: float}>
     * }
     *
     * @throws MedicationInvoiceException
     */
    private static function assertPlan(OperationCoordinationService $record, array $plan, bool $lock): array
    {
        if ($plan === []) {
            throw new MedicationInvoiceException('No hay medicamentos para facturar.');
        }

        if (MedicationInventoryCatalog::medicationCategoryId() === null) {
            throw new MedicationInvoiceException('No existe la categoría «Medicamentos» en el inventario. Créela antes de facturar.');
        }

        $medicationIds = array_values(array_unique(array_map(fn (array $entry): int => (int) $entry['medication_id'], $plan)));
        sort($medicationIds);

        if (in_array(0, $medicationIds, true) || count($medicationIds) !== count($plan)) {
            throw new MedicationInvoiceException('La selección de medicamentos no es válida. Recargue la página e intente de nuevo.');
        }

        $medications = TelemedicinePatientMedications::query()
            ->where('operation_coordination_service_id', $record->getKey())
            ->whereIn('id', $medicationIds)
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy('id');

        $nonCoveredKeys = CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys(
            $record,
            array_map(fn (int $id): string => 'medication:'.$id, $medicationIds),
        );

        foreach ($plan as $entry) {
            $medication = $medications->get($entry['medication_id']);

            if (! $medication instanceof TelemedicinePatientMedications) {
                throw new MedicationInvoiceException('Uno de los medicamentos ya no pertenece a este servicio. Recargue la página.');
            }

            $name = trim((string) $medication->medicine);
            $status = mb_strtoupper(trim((string) $medication->status));

            if (in_array($status, ['EN GESTION', 'FINALIZADO', 'CANCELADO', 'CANCELADA', 'CADUCADA'], true)) {
                throw new MedicationInvoiceException('«'.$name.'» ya está '.$status.'. Otro analista pudo gestionarlo; recargue la página.');
            }

            if ($medication->inventory_dispatched_at !== null) {
                throw new MedicationInvoiceException('«'.$name.'» ya salió del inventario. No se puede facturar dos veces.');
            }

            if (! in_array($entry['key'], $nonCoveredKeys, true)) {
                throw new MedicationInvoiceException('«'.$name.'» no es un medicamento NO CUBIERTO pendiente. Solo lo no cubierto se factura al paciente.');
            }

            $activeInvoiceItem = OperationMedicationInvoiceItem::query()
                ->where('telemedicine_patient_medication_id', $medication->getKey())
                ->whereHas('invoice', fn ($query) => $query->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED))
                ->exists();

            if ($activeInvoiceItem) {
                throw new MedicationInvoiceException('«'.$name.'» ya tiene una factura vigente.');
            }

            if ($entry['lines'] === []) {
                throw new MedicationInvoiceException('Seleccione al menos un producto del inventario para «'.$name.'», o marque «Generar cotización».');
            }

            if (count($entry['lines']) > self::MAX_PRODUCTS_PER_MEDICATION) {
                throw new MedicationInvoiceException('«'.$name.'» tiene más de '.self::MAX_PRODUCTS_PER_MEDICATION.' productos. Revise la selección.');
            }
        }

        $productIds = [];
        $demand = [];

        foreach ($plan as $entry) {
            $name = trim((string) $medications->get($entry['medication_id'])->medicine);
            $seen = [];

            foreach ($entry['lines'] as $line) {
                if ($line['product_id'] <= 0 || $line['ubication_id'] <= 0) {
                    throw new MedicationInvoiceException('Complete el producto y el almacén de cada línea de «'.$name.'».');
                }

                if ($line['quantity'] < 1 || $line['quantity'] > self::MAX_QUANTITY_PER_LINE) {
                    throw new MedicationInvoiceException('La cantidad de cada producto de «'.$name.'» debe ser un número entero mayor que cero.');
                }

                $pair = $line['product_id'].':'.$line['ubication_id'];

                if (isset($seen[$pair])) {
                    throw new MedicationInvoiceException('«'.$name.'» repite el mismo producto y almacén en dos líneas. Súmelas en una sola.');
                }

                $seen[$pair] = true;
                $productIds[] = $line['product_id'];
                $demand[$pair] = ($demand[$pair] ?? 0) + $line['quantity'];
            }
        }

        $products = MedicationInventoryCatalog::productsById($productIds);
        $loadedCosts = self::applyLoadedCosts($plan, $products, $lock);
        $ubications = OperationInventoryUbication::query()
            ->whereIn('id', array_map(fn (string $pair): int => (int) explode(':', $pair)[1], array_keys($demand)))
            ->get(['id', 'name', 'is_active'])
            ->keyBy('id');

        $inventories = [];
        $stocks = [];
        $pairs = array_keys($demand);
        sort($pairs);

        foreach ($pairs as $pair) {
            [$productId, $ubicationId] = array_map('intval', explode(':', $pair));
            $product = $products->get($productId);
            $ubication = $ubications->get($ubicationId);

            if (! $product instanceof OperationInventoryProduct) {
                throw new MedicationInvoiceException('Uno de los productos seleccionados ya no existe en el inventario.');
            }

            if (! $ubication instanceof OperationInventoryUbication || ! $ubication->is_active) {
                throw new MedicationInvoiceException('El almacén elegido para «'.$product->name.'» no está disponible.');
            }

            $reason = MedicationInventoryCatalog::unsellableReason($product, PHP_INT_MAX);

            if ($reason !== null && MedicationInventoryCatalog::needsCost($product)) {
                $reason = 'Cargue el costo de «'.$product->name.'» para poder facturarlo.';
            }

            if ($reason !== null) {
                throw new MedicationInvoiceException($reason);
            }

            $inventory = OperationInventory::query()
                ->where('operation_inventory_product_id', $productId)
                ->where('operation_inventory_ubication_id', $ubicationId)
                ->orderBy('id')
                ->when($lock, fn ($query) => $query->lockForUpdate())
                ->first();

            $stock = OperationInventoryProductStock::query()
                ->where('operation_inventory_product_id', $productId)
                ->where('operation_inventory_ubication_id', $ubicationId)
                ->orderBy('id')
                ->when($lock, fn ($query) => $query->lockForUpdate())
                ->first();

            $available = $inventory instanceof OperationInventory
                ? MedicationInventoryCatalog::sellableExistence($inventory->existence, $stock?->existence)
                : 0;

            if ($available < $demand[$pair]) {
                throw new MedicationInvoiceException(
                    'Existencia insuficiente de «'.$product->name.'» en '.$ubication->name.': disponible '.$available.', se facturan '.$demand[$pair].'.'
                );
            }

            $inventories[$pair] = $inventory;
            $stocks[$pair] = $stock;
        }

        $lines = [];

        foreach ($plan as $entry) {
            foreach ($entry['lines'] as $line) {
                $pair = $line['product_id'].':'.$line['ubication_id'];
                /** @var OperationInventoryProduct $product */
                $product = $products->get($line['product_id']);
                $unitPrice = (float) MedicationInventoryCatalog::sellingPrice($product);

                $lines[] = [
                    'medication_id' => $entry['medication_id'],
                    'product' => $product,
                    'ubication' => $ubications->get($line['ubication_id']),
                    'inventory' => $inventories[$pair],
                    'stock' => $stocks[$pair],
                    'quantity' => $line['quantity'],
                    'unit_cost_usd' => round((float) $product->cost, 2),
                    'profit_percentage' => round((float) OperationInventoryProductPricing::profitPercentageFor($product), 2),
                    'unit_price_usd' => $unitPrice,
                    'line_total_usd' => round($unitPrice * $line['quantity'], 2),
                ];
            }
        }

        return [
            'medications' => $medications->all(),
            'lines' => $lines,
            'loaded_costs' => $loadedCosts,
        ];
    }

    /**
     * Productos sin costo: aplica el costo que cargó el analista. Con `$lock` lo guarda en el
     * producto (fila bloqueada); sin bloqueo solo lo usa en memoria para validar. Si otro usuario
     * ya cargó un costo entre tanto, se respeta ese y se ignora el del formulario.
     *
     * @param  list<array{medication_id: int, key: string, lines: list<array{product_id: int, ubication_id: int, quantity: int, new_cost?: float|null}>}>  $plan
     * @param  \Illuminate\Support\Collection<int, OperationInventoryProduct>  $products
     * @return list<array{product_id: int, product_name: string, cost: float}>
     *
     * @throws MedicationInvoiceException
     */
    private static function applyLoadedCosts(array $plan, \Illuminate\Support\Collection $products, bool $lock): array
    {
        $requested = [];

        foreach ($plan as $entry) {
            foreach ($entry['lines'] as $line) {
                $product = $products->get($line['product_id']);

                if (! $product instanceof OperationInventoryProduct || ! MedicationInventoryCatalog::needsCost($product)) {
                    continue;
                }

                $cost = $line['new_cost'] ?? null;

                if ($cost === null || $cost <= 0) {
                    throw new MedicationInvoiceException('«'.$product->name.'» no tiene costo. Cárguelo en la línea para poder facturarlo.');
                }

                if ($cost > self::MAX_LOADED_COST) {
                    throw new MedicationInvoiceException('Revise el costo de «'.$product->name.'»: US$ '.number_format($cost, 2, '.', ',').' parece demasiado alto.');
                }

                if (isset($requested[$product->id]) && abs($requested[$product->id] - $cost) > 0.001) {
                    throw new MedicationInvoiceException('Indicó dos costos distintos para «'.$product->name.'». Use el mismo en todas sus líneas.');
                }

                $requested[$product->id] = $cost;
            }
        }

        $loaded = [];
        ksort($requested);

        foreach ($requested as $productId => $cost) {
            $product = $products->get($productId);

            if (! $lock) {
                $product->setAttribute('cost', $cost);

                continue;
            }

            $locked = OperationInventoryProduct::query()->whereKey($productId)->lockForUpdate()->first(['id', 'cost']);

            if ($locked !== null && ! MedicationInventoryCatalog::needsCost($locked)) {
                // Otro usuario cargó el costo mientras tanto: manda el guardado.
                $product->setAttribute('cost', $locked->cost);

                continue;
            }

            OperationInventoryProduct::query()->whereKey($productId)->update(['cost' => $cost, 'updated_at' => now()]);
            $product->setAttribute('cost', $cost);
            $loaded[] = ['product_id' => (int) $productId, 'product_name' => (string) $product->name, 'cost' => (float) $cost];
        }

        return $loaded;
    }
}

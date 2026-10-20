<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventory;
use App\Models\OperationInventoryMovement;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryType;
use App\Models\OperationInventoryUbication;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatientMedications;
use App\Services\OperationInventoryLowStockWatcher;
use App\Services\TelemedicineMedicationInventoryDeductor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Salida de inventario de los medicamentos de una orden de servicio, al entregarlos.
 *
 * Regla de negocio: el stock sale cuando Operaciones entrega, no cuando el médico receta.
 * Solo se descuentan los medicamentos ligados a un producto del inventario y que aún no
 * salieron (`inventory_dispatched_at` nulo); los de texto libre o comprados a una farmacia
 * no tocan el stock. El analista elige desde qué ubicación sale.
 *
 * Corre dentro de la transacción de la finalización y con las filas de inventario
 * bloqueadas: si falta existencia en una sola línea no sale ninguna y la orden no se finaliza.
 */
final class ServiceOrderMedicationInventory
{
    public const DISPATCH_SOURCE_ORDER = 'ORDEN DE SERVICIO';

    public const DISPATCH_SOURCE_CONSULTATION = 'CONSULTA';

    /**
     * Medicamentos de la orden que deben salir del inventario al entregarse.
     *
     * Los ítems creados desde ahora traen el vínculo al medicamento; los anteriores se
     * resuelven por nombre entre los medicamentos en gestión de la coordinación, igual
     * que la sincronización de estatus.
     *
     * @return Collection<int, array{medication: TelemedicinePatientMedications, name: string, quantity: int}>
     */
    public static function pendingLines(OperationServiceOrder $order, bool $lock = false): Collection
    {
        if (mb_strtoupper(trim((string) $order->service_type)) !== 'MEDICAMENTOS') {
            return collect();
        }

        $items = OperationServiceOrderItem::query()
            ->where('operation_service_order_id', $order->getKey())
            ->get(['id', 'telemedicine_patient_medication_id', 'item_name', 'quantity']);

        if ($items->isEmpty()) {
            return collect();
        }

        $linkedIds = $items->pluck('telemedicine_patient_medication_id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
        $legacyNames = $items->whereNull('telemedicine_patient_medication_id')
            ->map(fn (OperationServiceOrderItem $item): string => self::normalizeName($item->item_name))
            ->filter()
            ->all();

        $medications = TelemedicinePatientMedications::query()
            ->where('operation_coordination_service_id', $order->operation_coordination_service_id)
            ->whereNotNull('operation_inventory_id')
            ->whereNull('inventory_dispatched_at')
            ->where(function ($query) use ($linkedIds, $legacyNames): void {
                $query->whereIn('id', $linkedIds === [] ? [0] : $linkedIds);

                if ($legacyNames !== []) {
                    $query->orWhere(fn ($legacy) => $legacy->where('status', 'EN GESTION')
                        ->whereIn(DB::raw('UPPER(TRIM(medicine))'), $legacyNames));
                }
            })
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderBy('id')
            ->get();

        return $medications->map(function (TelemedicinePatientMedications $medication) use ($items): array {
            $item = $items->firstWhere('telemedicine_patient_medication_id', $medication->getKey())
                ?? $items->first(fn (OperationServiceOrderItem $candidate): bool => $candidate->telemedicine_patient_medication_id === null
                    && self::normalizeName($candidate->item_name) === self::normalizeName($medication->medicine));

            return [
                'medication' => $medication,
                'name' => trim((string) $medication->medicine),
                'quantity' => max(1, (int) ($item?->quantity ?? $medication->quantity ?? 1)),
            ];
        })->values();
    }

    public static function requiresUbication(OperationServiceOrder $order): bool
    {
        return self::pendingLines($order)->isNotEmpty();
    }

    /**
     * Ubicación sugerida: la de la orden o, si todos los medicamentos vienen del mismo almacén, esa.
     */
    public static function suggestedUbicationId(OperationServiceOrder $order): ?int
    {
        if (filled($order->operation_inventory_ubication_id)) {
            return (int) $order->operation_inventory_ubication_id;
        }

        $ubications = OperationInventory::query()
            ->whereIn('id', self::pendingLines($order)->map(fn (array $line): int => (int) $line['medication']->operation_inventory_id)->all() ?: [0])
            ->distinct()
            ->pluck('operation_inventory_ubication_id')
            ->filter();

        return $ubications->count() === 1 ? (int) $ubications->first() : null;
    }

    /**
     * @return array<int, string>
     */
    public static function ubicationOptions(): array
    {
        return OperationInventoryUbication::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Resumen legible de lo que saldrá, para mostrarlo antes de finalizar.
     */
    public static function pendingSummary(OperationServiceOrder $order): string
    {
        return self::pendingLines($order)
            ->map(fn (array $line): string => $line['name'].' × '.$line['quantity'])
            ->implode(', ');
    }

    /**
     * Descuenta del inventario los medicamentos pendientes de la orden desde la ubicación elegida.
     *
     * @return list<string> Líneas para la bitácora («Producto × cantidad (ubicación)»).
     *
     * @throws OperationServiceOrderFinalizationException
     */
    public static function dispatch(OperationServiceOrder $order, ?int $ubicationId, string $userName): array
    {
        // Medicamentos bloqueados: dos entregas simultáneas no pueden descontar el mismo dos veces.
        $lines = self::pendingLines($order, lock: true);

        if ($lines->isEmpty()) {
            return [];
        }

        $ubication = $ubicationId !== null && $ubicationId > 0
            ? OperationInventoryUbication::query()->find($ubicationId)
            : null;

        if (! $ubication instanceof OperationInventoryUbication) {
            throw new OperationServiceOrderFinalizationException(
                'Indique la ubicación de inventario desde la que se entregan los medicamentos: '.self::pendingSummary($order).'.'
            );
        }

        $sourceInventories = OperationInventory::query()
            ->whereIn('id', $lines->map(fn (array $line): int => (int) $line['medication']->operation_inventory_id)->all())
            ->get(['id', 'operation_inventory_product_id', 'name'])
            ->keyBy('id');

        // Se agrupa por producto: dos líneas del mismo producto deben caber juntas en la existencia.
        $byProduct = [];

        foreach ($lines as $line) {
            $source = $sourceInventories->get((int) $line['medication']->operation_inventory_id);

            if (! $source instanceof OperationInventory || ! filled($source->operation_inventory_product_id)) {
                throw new OperationServiceOrderFinalizationException(
                    'El medicamento «'.$line['name'].'» está ligado a un producto que ya no existe en el inventario. Corríjalo antes de finalizar.'
                );
            }

            $productId = (int) $source->operation_inventory_product_id;
            $byProduct[$productId]['lines'][] = $line;
            $byProduct[$productId]['quantity'] = ($byProduct[$productId]['quantity'] ?? 0) + $line['quantity'];
        }

        $order->loadMissing('operationCoordinationService');
        $coordination = $order->operationCoordinationService;
        $patient = $coordination?->telemedicinePatient()->first(['id', 'business_unit_id', 'business_line_id']);
        $typeId = (int) (OperationInventoryType::query()->orderBy('id')->value('id') ?? 1);
        $orderLabel = filled($order->order_number) ? (string) $order->order_number : '#'.$order->getKey();
        $previousTotals = [];
        $bitacora = [];

        ksort($byProduct);

        foreach ($byProduct as $productId => $group) {
            $inventory = OperationInventory::query()
                ->where('operation_inventory_product_id', $productId)
                ->where('operation_inventory_ubication_id', $ubication->getKey())
                ->lockForUpdate()
                ->first();

            $name = (string) ($group['lines'][0]['name'] ?? 'Medicamento');

            if (! $inventory instanceof OperationInventory) {
                throw new OperationServiceOrderFinalizationException(
                    'El medicamento «'.$name.'» no está registrado en la ubicación '.$ubication->name.'. Elija la ubicación desde la que realmente se entrega.'
                );
            }

            if ((int) $inventory->existence < $group['quantity']) {
                throw new OperationServiceOrderFinalizationException(
                    'Existencia insuficiente de «'.$inventory->name.'» en '.$ubication->name.': disponible '.(int) $inventory->existence.', se entregan '.$group['quantity'].'.'
                );
            }

            $previousTotals[$productId] = (int) OperationInventoryProductStock::query()
                ->where('operation_inventory_product_id', $productId)
                ->sum('existence');

            $inventory->existence = (int) $inventory->existence - $group['quantity'];
            $inventory->updated_by = $userName;
            $inventory->save();

            $stock = OperationInventoryProductStock::query()
                ->where('operation_inventory_product_id', $productId)
                ->where('operation_inventory_ubication_id', $ubication->getKey())
                ->lockForUpdate()
                ->first();

            if ($stock instanceof OperationInventoryProductStock) {
                $stock->existence = max(0, (int) $stock->existence - $group['quantity']);
                $stock->updated_by = $userName;
                $stock->save();
            }

            foreach ($group['lines'] as $line) {
                /** @var TelemedicinePatientMedications $medication */
                $medication = $line['medication'];

                OperationInventoryOutflow::query()->create([
                    'operation_inventory_id' => $inventory->getKey(),
                    'telemedicine_case_id' => $medication->telemedicine_case_id ?? $coordination?->telemedicine_case_id,
                    'operation_inventory_product_id' => $productId,
                    'operation_inventory_ubication_id' => $ubication->getKey(),
                    'operation_inventory_type_id' => $typeId,
                    'quantity' => $line['quantity'],
                    'type_entry' => TelemedicineMedicationInventoryDeductor::OUTFLOW_TYPE,
                    'observations' => 'Entrega por orden de servicio '.$orderLabel,
                    'created_by' => $userName,
                ]);

                OperationInventoryMovement::query()->create([
                    'operation_inventory_id' => $inventory->getKey(),
                    'telemedicine_patient_id' => (int) ($medication->telemedicine_patient_id ?? $coordination?->telemedicine_patient_id ?? 0),
                    'telemedicine_case_id' => (int) ($medication->telemedicine_case_id ?? $coordination?->telemedicine_case_id ?? 0),
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

                $medication->forceFill([
                    'inventory_dispatched_at' => now(),
                    'inventory_dispatch_source' => self::DISPATCH_SOURCE_ORDER,
                    'inventory_dispatch_order_id' => $order->getKey(),
                ])->saveQuietly();

                $bitacora[] = $line['name'].' × '.$line['quantity'].' ('.$ubication->name.')';
            }
        }

        if ((int) $order->operation_inventory_ubication_id !== (int) $ubication->getKey()) {
            $order->forceFill(['operation_inventory_ubication_id' => $ubication->getKey()])->saveQuietly();
        }

        // El aviso de stock bajo encola trabajo: solo si la entrega quedó confirmada.
        DB::afterCommit(function () use ($previousTotals): void {
            $watcher = app(OperationInventoryLowStockWatcher::class);

            foreach ($previousTotals as $productId => $previousTotal) {
                $watcher->dispatchIfCrossedThreshold((int) $productId, $previousTotal);
            }
        });

        return $bitacora;
    }

    private static function normalizeName(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}

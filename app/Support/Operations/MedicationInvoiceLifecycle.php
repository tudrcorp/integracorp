<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventory;
use App\Models\OperationInventoryEntry;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryType;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\OperationMedicationInvoiceItem;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pago y anulación de una factura de medicamentos. Solo una factura PENDIENTE POR COBRAR puede
 * pagarse o anularse; una pagada queda cerrada.
 *
 * Anular devuelve las unidades al almacén del que salieron, deja el medicamento recetado
 * PENDIENTE (puede gestionarse de nuevo) y reabre la coordinación si estaba finalizada.
 */
final class MedicationInvoiceLifecycle
{
    public const AUDIT_PAID = 'AUDIT_OPERATIONS_MEDICATION_INVOICE_PAID';

    public const AUDIT_ANNULLED = 'AUDIT_OPERATIONS_MEDICATION_INVOICE_ANNULLED';

    public const RESTOCK_ENTRY_TYPE = 'REPOSICIÓN DE INVENTARIO';

    /**
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        return [
            'TRANSFERENCIA_BS' => 'Transferencia en bolívares',
            'PAGO_MOVIL' => 'Pago móvil',
            'PUNTO_DE_VENTA' => 'Punto de venta',
            'EFECTIVO_BS' => 'Efectivo en bolívares',
            'EFECTIVO_USD' => 'Efectivo en dólares',
            'ZELLE' => 'Zelle',
            'TRANSFERENCIA_USD' => 'Transferencia en dólares',
        ];
    }

    public static function paymentMethodLabel(?string $method): string
    {
        return self::paymentMethodOptions()[(string) $method] ?? (filled($method) ? (string) $method : '—');
    }

    public static function paymentMethodRequiresReference(?string $method): bool
    {
        return ! in_array($method, ['EFECTIVO_BS', 'EFECTIVO_USD'], true);
    }

    /**
     * @param  array{paid_at: mixed, payment_method: mixed, payment_reference?: mixed, payment_notes?: mixed}  $data
     *
     * @throws MedicationInvoiceException
     */
    public static function registerPayment(OperationMedicationInvoice $invoice, array $data, ?User $user = null): OperationMedicationInvoice
    {
        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $method = (string) ($data['payment_method'] ?? '');
        $reference = trim((string) ($data['payment_reference'] ?? ''));
        $notes = trim((string) ($data['payment_notes'] ?? ''));
        $paidAt = self::parseDate($data['paid_at'] ?? null);

        if (! array_key_exists($method, self::paymentMethodOptions())) {
            throw new MedicationInvoiceException('Seleccione la forma de pago.');
        }

        if (self::paymentMethodRequiresReference($method) && $reference === '') {
            throw new MedicationInvoiceException('Indique la referencia del pago.');
        }

        if ($paidAt === null) {
            throw new MedicationInvoiceException('Indique la fecha del pago.');
        }

        $result = DB::transaction(function () use ($invoice, $method, $reference, $notes, $paidAt, $userName, $user): OperationMedicationInvoice {
            $locked = self::lockPending($invoice);

            if ($paidAt->isAfter(now()->endOfDay())) {
                throw new MedicationInvoiceException('La fecha del pago no puede ser posterior a hoy.');
            }

            if ($paidAt->lt($locked->issued_at->copy()->startOfDay())) {
                throw new MedicationInvoiceException('La fecha del pago no puede ser anterior a la emisión de la factura ('.$locked->issued_at->format('d/m/Y').').');
            }

            $locked->forceFill([
                'status' => OperationMedicationInvoice::STATUS_PAID,
                'paid_at' => $paidAt->toDateString(),
                'payment_method' => $method,
                'payment_reference' => $reference !== '' ? mb_substr($reference, 0, 120) : null,
                'payment_notes' => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
                'payment_registered_at' => now(),
                'payment_registered_by_name' => $userName,
            ])->save();

            self::syncReceivable($locked, OperationAccountsReceivable::STATUS_PAID, $userName);

            OperationMedicationInvoiceEvent::query()->create([
                'operation_medication_invoice_id' => $locked->getKey(),
                'event' => OperationMedicationInvoiceEvent::EVENT_PAID,
                'description' => 'Pago total registrado por '.$userName.': '.self::paymentMethodLabel($method)
                    .($reference !== '' ? ', referencia '.$reference : '')
                    .', del '.$paidAt->format('d/m/Y').'. Monto US$ '.number_format((float) $locked->total_usd, 2, '.', ',').'.',
                'payload' => ['payment_method' => $method, 'payment_reference' => $reference, 'paid_at' => $paidAt->toDateString()],
                'user_id' => $user?->getKey(),
                'user_name' => $userName,
            ]);

            return $locked;
        });

        SecurityAudit::log(self::AUDIT_PAID, MedicationInvoiceIssuer::AUDIT_ROUTE, [
            'operation_medication_invoice_id' => $result->getKey(),
            'payment_method' => $method,
            'total_usd' => (float) $result->total_usd,
        ], $user);

        return $result;
    }

    /**
     * @throws MedicationInvoiceException
     */
    public static function annul(OperationMedicationInvoice $invoice, string $reason, ?User $user = null): OperationMedicationInvoice
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new MedicationInvoiceException('Explique el motivo de la anulación (al menos 10 caracteres).');
        }

        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';

        $result = DB::transaction(function () use ($invoice, $reason, $userName, $user): OperationMedicationInvoice {
            $locked = self::lockPending($invoice);

            // Lo facturado desde una cotización lo entrega el proveedor: no hay existencia que devolver.
            if (! $locked->isMedications() || $locked->isFromQuote()) {
                return self::annulServiceInvoice($locked, $reason, $userName, $user);
            }

            $items = OperationMedicationInvoiceItem::query()
                ->where('operation_medication_invoice_id', $locked->getKey())
                ->orderBy('operation_inventory_product_id')
                ->orderBy('operation_inventory_ubication_id')
                ->lockForUpdate()
                ->get();

            $typeId = (int) (OperationInventoryType::query()->orderBy('id')->value('id') ?? 1);
            $restockLines = [];

            foreach ($items as $item) {
                if ($item->restocked_at !== null) {
                    continue;
                }

                $inventory = OperationInventory::query()
                    ->where('operation_inventory_product_id', $item->operation_inventory_product_id)
                    ->where('operation_inventory_ubication_id', $item->operation_inventory_ubication_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                $stock = OperationInventoryProductStock::query()
                    ->where('operation_inventory_product_id', $item->operation_inventory_product_id)
                    ->where('operation_inventory_ubication_id', $item->operation_inventory_ubication_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $inventory instanceof OperationInventory || ! $stock instanceof OperationInventoryProductStock) {
                    throw new MedicationInvoiceException(
                        '«'.$item->product_name.'» ya no está registrado en '.($item->ubication_name ?? 'su almacén').'. No se puede devolver la existencia; avise a soporte antes de anular.'
                    );
                }

                $inventory->existence = (int) $inventory->existence + (int) $item->quantity;
                $inventory->updated_by = $userName;
                $inventory->save();

                $stock->existence = (int) $stock->existence + (int) $item->quantity;
                $stock->updated_by = $userName;
                $stock->save();

                OperationInventoryEntry::query()->create([
                    'operation_inventory_id' => $inventory->getKey(),
                    'operation_inventory_product_id' => $item->operation_inventory_product_id,
                    'operation_inventory_ubication_id' => $item->operation_inventory_ubication_id,
                    'quantity' => (int) $item->quantity,
                    'operation_inventory_type_id' => $typeId,
                    'created_by' => $userName,
                    'type_entry' => self::RESTOCK_ENTRY_TYPE,
                ]);

                $item->forceFill(['restocked_at' => now()])->save();
                $restockLines[] = $item->product_name.' × '.$item->quantity.' ('.($item->ubication_name ?? '—').')';
            }

            $medicationIds = $items->pluck('telemedicine_patient_medication_id')->filter()->unique()->values()->all();

            TelemedicinePatientMedications::query()
                ->whereIn('id', $medicationIds === [] ? [0] : $medicationIds)
                ->where('inventory_dispatch_source', MedicationInvoiceIssuer::DISPATCH_SOURCE_INVOICE)
                ->lockForUpdate()
                ->get()
                ->each(fn (TelemedicinePatientMedications $medication) => $medication->forceFill([
                    'status' => 'PENDIENTE',
                    'inventory_dispatched_at' => null,
                    'inventory_dispatch_source' => null,
                    'inventory_dispatch_order_id' => null,
                ])->save());

            $locked->forceFill([
                'status' => OperationMedicationInvoice::STATUS_ANNULLED,
                'annulled_at' => now(),
                'annulled_by_name' => $userName,
                'annulment_reason' => mb_substr($reason, 0, 2000),
            ])->save();

            self::syncReceivable($locked, OperationAccountsReceivable::STATUS_ANNULLED, $userName);

            $description = 'Factura '.$locked->invoice_number.' anulada por '.$userName.'. Motivo: '.$reason.'. '
                .'Existencia devuelta: '.($restockLines !== [] ? implode('; ', $restockLines) : 'ninguna').'. '
                .'Los medicamentos volvieron a PENDIENTE.';

            OperationMedicationInvoiceEvent::query()->create([
                'operation_medication_invoice_id' => $locked->getKey(),
                'event' => OperationMedicationInvoiceEvent::EVENT_ANNULLED,
                'description' => $description,
                'payload' => ['reason' => $reason, 'medication_ids' => $medicationIds],
                'user_id' => $user?->getKey(),
                'user_name' => $userName,
            ]);

            $coordination = OperationCoordinationService::query()
                ->whereKey($locked->operation_coordination_service_id)
                ->lockForUpdate()
                ->first();

            if ($coordination instanceof OperationCoordinationService) {
                if (mb_strtoupper(trim((string) $coordination->status)) === 'FINALIZADO') {
                    $coordination->status = 'EN GESTION';
                }

                MedicationInvoiceIssuer::appendCoordinationLog($coordination, $description, $userName, $user?->getKey());
            }

            return $locked;
        });

        SecurityAudit::log(self::AUDIT_ANNULLED, MedicationInvoiceIssuer::AUDIT_ROUTE, [
            'operation_medication_invoice_id' => $result->getKey(),
            'reason' => $reason,
            'total_usd' => (float) $result->total_usd,
        ], $user);

        return $result;
    }

    /**
     * Anulación de una factura de laboratorios, estudios o especialistas. No hay existencia que
     * devolver y los ítems siguen el curso de su orden: si el servicio ya no se realizará, el
     * analista cancela o anula la orden después (antes no podía: la factura vigente lo impide).
     * Si la orden ya está finalizada, el servicio se realizó y la factura no se anula.
     *
     * @throws MedicationInvoiceException
     */
    private static function annulServiceInvoice(OperationMedicationInvoice $locked, string $reason, string $userName, ?User $user): OperationMedicationInvoice
    {
        $order = filled($locked->operation_service_order_id)
            ? \App\Models\OperationServiceOrder::query()->whereKey($locked->operation_service_order_id)->lockForUpdate()->first()
            : null;

        if ($order !== null && mb_strtoupper(trim((string) $order->status)) === 'FINALIZADO') {
            throw new MedicationInvoiceException(
                'La orden '.($order->order_number ?: '#'.$order->getKey()).' ya está FINALIZADA: el servicio se realizó y la factura no se puede anular.'
            );
        }

        $locked->forceFill([
            'status' => OperationMedicationInvoice::STATUS_ANNULLED,
            'annulled_at' => now(),
            'annulled_by_name' => $userName,
            'annulment_reason' => mb_substr($reason, 0, 2000),
        ])->save();

        self::syncReceivable($locked, OperationAccountsReceivable::STATUS_ANNULLED, $userName);

        $orderActive = $order !== null && ! in_array(mb_strtoupper(trim((string) $order->status)), ['CANCELADA', 'CANCELADO', 'CADUCADA'], true);
        $description = 'Factura '.$locked->invoice_number.' ('.mb_strtolower(OperationMedicationInvoice::typeLabel($locked->invoice_type)).') anulada por '.$userName.'. Motivo: '.$reason.'.'
            .($orderActive
                ? ' La orden '.($order->order_number ?: '#'.$order->getKey()).' sigue activa: si el servicio ya no se realizará, cancélela o anúlela.'
                : '');

        OperationMedicationInvoiceEvent::query()->create([
            'operation_medication_invoice_id' => $locked->getKey(),
            'event' => OperationMedicationInvoiceEvent::EVENT_ANNULLED,
            'description' => $description,
            'payload' => ['reason' => $reason, 'operation_service_order_id' => $order?->getKey(), 'order_active' => $orderActive],
            'user_id' => $user?->getKey(),
            'user_name' => $userName,
        ]);

        $coordination = OperationCoordinationService::query()->whereKey($locked->operation_coordination_service_id)->lockForUpdate()->first();

        if ($coordination instanceof OperationCoordinationService) {
            MedicationInvoiceIssuer::appendCoordinationLog($coordination, $description, $userName, $user?->getKey());
        }

        return $locked;
    }

    /**
     * @throws MedicationInvoiceException
     */
    private static function lockPending(OperationMedicationInvoice $invoice): OperationMedicationInvoice
    {
        $locked = OperationMedicationInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();

        if (! $locked instanceof OperationMedicationInvoice) {
            throw new MedicationInvoiceException('La factura ya no existe.');
        }

        if (! $locked->isPendingPayment()) {
            throw new MedicationInvoiceException('La factura '.$locked->invoice_number.' ya está '.self::statusLabel($locked->status).'. Recargue la página.');
        }

        return $locked;
    }

    private static function syncReceivable(OperationMedicationInvoice $invoice, string $status, string $userName): void
    {
        OperationAccountsReceivable::query()
            ->where('operation_medication_invoice_id', $invoice->getKey())
            ->update(['status' => $status, 'updated_by' => $userName, 'updated_at' => now()]);
    }

    private static function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->startOfDay();
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            OperationMedicationInvoice::STATUS_PENDING_PAYMENT => 'Pendiente por cobrar',
            OperationMedicationInvoice::STATUS_PAID => 'Cobrada',
            OperationMedicationInvoice::STATUS_ANNULLED => 'Anulada',
            default => filled($status) ? (string) $status : '—',
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            OperationMedicationInvoice::STATUS_PENDING_PAYMENT => 'warning',
            OperationMedicationInvoice::STATUS_PAID => 'success',
            OperationMedicationInvoice::STATUS_ANNULLED => 'danger',
            default => 'gray',
        };
    }
}

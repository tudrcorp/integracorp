<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsPayable;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lo que impide eliminar un proveedor, separado en dos grupos:
 *
 * - **Por resolver** (el analista lo resuelve en la misma ventana de eliminación):
 *   - órdenes de servicio vigentes (no cerradas) que el proveedor presta, con su cita;
 *   - citas futuras del proveedor cuya orden ya está cerrada.
 * - **Bloqueos** (se resuelven en su módulo antes de eliminar):
 *   - cuentas por pagar pendientes;
 *   - usuarios del portal activos;
 *   - órdenes vigentes que el proveedor administra desde su portal sin prestarlas;
 *   - órdenes vigentes con factura registrada (no se reasignan ni cancelan);
 *   - servicios médicos abiertos (PENDIENTE / EN GESTIÓN) del proveedor que no se
 *     resuelven con sus órdenes.
 *
 * El historial (órdenes cerradas, citas pasadas, servicios finalizados, casos, informes)
 * ya no impide eliminar: queda apuntando al proveedor, que se ve como «(eliminado)».
 */
final class ProviderPendingWork
{
    /** @var list<string> */
    public const OPEN_COORDINATION_STATUSES = ['PENDIENTE', 'EN GESTION', 'EN GESTIÓN'];

    public const PENDING_PAYABLE_STATUS = 'PENDIENTE POR PAGAR';

    /**
     * Columna de la orden que dice quién presta el servicio, por tipo de proveedor.
     *
     * @var array<string, string>
     */
    private const ORDER_PROVIDER_COLUMN = [
        ProviderDeletion::TYPE_SUPPLIER => 'supplier_id',
        ProviderDeletion::TYPE_DOCTOR_NURSE => 'doctor_nurse_id',
    ];

    /**
     * Columnas del servicio médico que apuntan al proveedor, por tipo.
     *
     * @var array<string, list<string>>
     */
    private const COORDINATION_COLUMNS = [
        ProviderDeletion::TYPE_SUPPLIER => ['supplier_id', 'assigned_supplier_id'],
        ProviderDeletion::TYPE_DOCTOR_NURSE => ['assigned_doctor_nurse_id'],
        ProviderDeletion::TYPE_CORPORATE_ALLY => ['assigned_corporate_ally_id'],
    ];

    /**
     * Columna de «proveedor asignado» del servicio médico, que se actualiza al reasignar
     * o cancelar sus órdenes.
     *
     * @var array<string, string>
     */
    public const COORDINATION_ASSIGNED_COLUMN = [
        ProviderDeletion::TYPE_SUPPLIER => 'assigned_supplier_id',
        ProviderDeletion::TYPE_DOCTOR_NURSE => 'assigned_doctor_nurse_id',
        ProviderDeletion::TYPE_CORPORATE_ALLY => 'assigned_corporate_ally_id',
    ];

    /**
     * @return array{
     *     orders: list<array{id: int, label: string, patient: string, service_type: string, appointment: string|null, appointment_id: int|null, amount: string, coordination_id: int|null}>,
     *     appointments: list<array{id: int, label: string, patient: string, appointment: string, order_label: string}>,
     *     blockers: list<string>
     * }
     */
    public static function forProvider(string $type, Model $provider): array
    {
        $id = (int) $provider->getKey();
        $orders = self::resolvableOrders($type, $id);
        $orderIds = array_column($orders, 'id');
        $resolvedCoordinationIds = array_values(array_filter(array_column($orders, 'coordination_id')));

        return [
            'orders' => $orders,
            'appointments' => self::orphanFutureAppointments($type, $id, $orderIds),
            'blockers' => self::blockers($type, $id, $resolvedCoordinationIds),
        ];
    }

    /**
     * @param  array{orders: list<mixed>, appointments: list<mixed>, blockers: list<string>}  $pending
     */
    public static function isClear(array $pending): bool
    {
        return $pending['orders'] === [] && $pending['appointments'] === [] && $pending['blockers'] === [];
    }

    /**
     * @param  array{orders: list<mixed>, appointments: list<mixed>, blockers: list<string>}  $pending
     */
    public static function hasResolvable(array $pending): bool
    {
        return $pending['orders'] !== [] || $pending['appointments'] !== [];
    }

    /**
     * @return Builder<OperationServiceOrder>
     */
    public static function activeOrdersQuery(): Builder
    {
        return OperationServiceOrder::query()->where(function (Builder $query): void {
            $query->whereNull('status')
                ->orWhereNotIn('status', OperationServiceOrderValidity::closedStatuses());
        });
    }

    public static function isAppointmentCancelled(OperationMedicalAppointment $appointment): bool
    {
        return mb_strtoupper(trim((string) $appointment->status)) === OperationMedicalAppointment::STATUS_CANCELLED;
    }

    /**
     * @return list<array{id: int, label: string, patient: string, service_type: string, appointment: string|null, appointment_id: int|null, amount: string, coordination_id: int|null}>
     */
    private static function resolvableOrders(string $type, int $id): array
    {
        $column = self::ORDER_PROVIDER_COLUMN[$type] ?? null;

        if ($column === null) {
            return [];
        }

        $invoiced = OperationAccountsPayable::query()
            ->whereNotNull('operation_service_order_id')
            ->select('operation_service_order_id');

        return self::activeOrdersQuery()
            ->where($column, $id)
            ->whereNotIn('id', $invoiced)
            ->with(['operationCoordinationService:id,patient,telemedicine_patient_id', 'medicalAppointment'])
            ->orderBy('id')
            ->get()
            ->map(function (OperationServiceOrder $order): array {
                $appointment = $order->medicalAppointment;
                $hasAppointment = $appointment instanceof OperationMedicalAppointment && ! self::isAppointmentCancelled($appointment);

                return [
                    'id' => (int) $order->getKey(),
                    'label' => ServiceOrderFlowTrace::orderLabel($order),
                    'patient' => filled($order->operationCoordinationService?->patient) ? (string) $order->operationCoordinationService->patient : '—',
                    'service_type' => filled($order->service_type) ? (string) $order->service_type : '—',
                    'appointment' => $hasAppointment ? MedicalAppointmentManager::formatAppointmentAt($appointment->appointment_at) : null,
                    'appointment_id' => $hasAppointment ? (int) $appointment->getKey() : null,
                    'amount' => ServiceOrderFlowTrace::money($order->total_amount_usd),
                    'coordination_id' => filled($order->operation_coordination_service_id) ? (int) $order->operation_coordination_service_id : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Citas futuras del proveedor que no viajan con una orden vigente suya.
     *
     * @param  list<int>  $orderIds
     * @return list<array{id: int, label: string, patient: string, appointment: string, order_label: string}>
     */
    private static function orphanFutureAppointments(string $type, int $id, array $orderIds): array
    {
        if ($type !== ProviderDeletion::TYPE_SUPPLIER) {
            return [];
        }

        return OperationMedicalAppointment::query()
            ->where('supplier_id', $id)
            ->where('appointment_at', '>=', Carbon::now())
            ->where(function (Builder $query): void {
                $query->whereNull('status')->orWhere('status', '!=', OperationMedicalAppointment::STATUS_CANCELLED);
            })
            ->when($orderIds !== [], fn (Builder $query): Builder => $query->whereNotIn('operation_service_order_id', $orderIds))
            ->with(['operationServiceOrder:id,order_number', 'operationCoordinationService:id,patient'])
            ->orderBy('appointment_at')
            ->get()
            ->map(fn (OperationMedicalAppointment $appointment): array => [
                'id' => (int) $appointment->getKey(),
                'label' => 'Cita #'.$appointment->getKey(),
                'patient' => filled($appointment->operationCoordinationService?->patient) ? (string) $appointment->operationCoordinationService->patient : '—',
                'appointment' => MedicalAppointmentManager::formatAppointmentAt($appointment->appointment_at),
                'order_label' => $appointment->operationServiceOrder instanceof OperationServiceOrder
                    ? ServiceOrderFlowTrace::orderLabel($appointment->operationServiceOrder)
                    : '—',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $resolvedCoordinationIds  servicios médicos que se resuelven con sus órdenes
     * @return list<string>
     */
    private static function blockers(string $type, int $id, array $resolvedCoordinationIds): array
    {
        $blockers = [];

        if ($type === ProviderDeletion::TYPE_SUPPLIER) {
            $payables = OperationAccountsPayable::query()
                ->where('supplier_id', $id)
                ->where('payment_status', self::PENDING_PAYABLE_STATUS)
                ->count();

            if ($payables > 0) {
                $blockers[] = self::plural($payables, 'cuenta por pagar pendiente', 'cuentas por pagar pendientes').' (Operaciones → Cuentas por pagar).';
            }

            $users = User::query()
                ->where('supplier_id', $id)
                ->whereRaw('UPPER(TRIM(status)) = ?', ['ACTIVO'])
                ->count();

            if ($users > 0) {
                $blockers[] = self::plural($users, 'usuario activo del portal', 'usuarios activos del portal').' (inactívelos desde Usuarios).';
            }

            $managed = self::activeOrdersQuery()
                ->where('telemedicine_supplier_id', $id)
                ->where(fn (Builder $query): Builder => $query->whereNull('supplier_id')->orWhere('supplier_id', '!=', $id))
                ->count();

            if ($managed > 0) {
                $blockers[] = self::plural($managed, 'orden en gestión que administra desde su portal', 'órdenes en gestión que administra desde su portal').' (deben cerrarse antes).';
            }
        }

        $orderColumn = self::ORDER_PROVIDER_COLUMN[$type] ?? null;

        if ($orderColumn !== null) {
            $invoiced = self::activeOrdersQuery()
                ->where($orderColumn, $id)
                ->whereIn('id', OperationAccountsPayable::query()->whereNotNull('operation_service_order_id')->select('operation_service_order_id'))
                ->count();

            if ($invoiced > 0) {
                $blockers[] = self::plural($invoiced, 'orden en gestión con factura registrada', 'órdenes en gestión con factura registrada').' (no se reasignan ni cancelan: ciérrelas desde la orden).';
            }
        }

        $assignedColumn = self::COORDINATION_ASSIGNED_COLUMN[$type];

        $openCoordinations = OperationCoordinationService::query()
            ->whereIn('status', self::OPEN_COORDINATION_STATUSES)
            ->where(function (Builder $query) use ($type, $id, $assignedColumn, $resolvedCoordinationIds): void {
                foreach (self::COORDINATION_COLUMNS[$type] as $column) {
                    if ($column === $assignedColumn && $resolvedCoordinationIds !== []) {
                        $query->orWhere(fn (Builder $query): Builder => $query->where($column, $id)->whereNotIn('id', $resolvedCoordinationIds));

                        continue;
                    }

                    $query->orWhere($column, $id);
                }
            })
            ->count();

        if ($openCoordinations > 0) {
            $blockers[] = self::plural($openCoordinations, 'servicio médico abierto', 'servicios médicos abiertos').' a su nombre (Operaciones → Servicios médicos: finalícelos o asigne otro proveedor).';
        }

        return $blockers;
    }

    private static function plural(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}

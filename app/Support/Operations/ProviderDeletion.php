<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\CorporateAlly;
use App\Models\CorporateAllyObservacion;
use App\Models\DoctorNurse;
use App\Models\DoctorNurseObservacion;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\ProviderDeletionLog;
use App\Models\ProviderDeletionLogItem;
use App\Models\Supplier;
use App\Models\SupplierObservacion;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Eliminación reversible de proveedores jurídicos, naturales y aliados
 * corporativos (Operaciones).
 *
 * Nunca se borra el registro: `deleted_at` lo oculta en todo el sistema y se
 * puede restaurar. Exige el permiso {@see BusinessFilamentActionPermissionRegistry::DELETE_PROVIDERS}.
 *
 * Desde el 04/11/2026 el historial ya no impide eliminar: las órdenes cerradas, citas
 * pasadas, casos e informes siguen apuntando al proveedor (las relaciones usan
 * `withTrashed()` y el nombre sale con «(eliminado)»). Lo vigente sí: ver
 * {@see ProviderPendingWork}. Las órdenes y citas vigentes se reasignan o cancelan en la
 * misma ventana de eliminación; lo demás bloquea hasta resolverlo en su módulo.
 */
final class ProviderDeletion
{
    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_DOCTOR_NURSE = 'doctor_nurse';

    public const TYPE_CORPORATE_ALLY = 'corporate_ally';

    /** Clave del interruptor «Ver solo proveedores eliminados». */
    public const DELETED_FILTER = 'eliminados';

    public const MINIMUM_REASON_LENGTH = 10;

    public const RESOLVE_REASSIGN = 'reassign';

    public const RESOLVE_CANCEL = 'cancel';

    /**
     * @var array<string, array{model: class-string<Model>, name: string, singular: string, plural: string, observation: class-string<Model>, observation_key: string}>
     */
    private const TYPES = [
        self::TYPE_SUPPLIER => [
            'model' => Supplier::class,
            'name' => 'name',
            'singular' => 'proveedor jurídico',
            'plural' => 'proveedores jurídicos',
            'observation' => SupplierObservacion::class,
            'observation_key' => 'supplier_id',
        ],
        self::TYPE_DOCTOR_NURSE => [
            'model' => DoctorNurse::class,
            'name' => 'name',
            'singular' => 'proveedor natural',
            'plural' => 'proveedores naturales',
            'observation' => DoctorNurseObservacion::class,
            'observation_key' => 'doctor_nurse_id',
        ],
        self::TYPE_CORPORATE_ALLY => [
            'model' => CorporateAlly::class,
            'name' => 'company_name',
            'singular' => 'aliado corporativo',
            'plural' => 'aliados corporativos',
            'observation' => CorporateAllyObservacion::class,
            'observation_key' => 'corporate_ally_id',
        ],
    ];

    /**
     * Dónde puede estar usado cada tipo de proveedor. Varias columnas pueden
     * compartir etiqueta: se suman. Sus propios datos (contactos, observaciones,
     * red, zonas de cobertura) no cuentan como servicio.
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>> tipo => [tabla, columna, etiqueta]
     */
    private const REFERENCES = [
        self::TYPE_SUPPLIER => [
            ['operation_service_orders', 'supplier_id', 'órdenes de servicio'],
            ['operation_service_orders', 'telemedicine_supplier_id', 'órdenes de servicio'],
            ['operation_service_order_quotes', 'supplier_id', 'cotizaciones de órdenes'],
            ['operation_coordination_services', 'supplier_id', 'servicios médicos'],
            ['operation_coordination_services', 'assigned_supplier_id', 'servicios médicos'],
            ['operation_accounts_payables', 'supplier_id', 'cuentas por pagar'],
            ['operation_accounts_receivables', 'reassignment_supplier_id', 'cuentas por cobrar'],
            ['operation_medical_appointments', 'supplier_id', 'citas médicas'],
            ['operation_quote_generators', 'supplier_id', 'cotizaciones del generador'],
            ['telemedicine_cases', 'supplier_id', 'casos de telemedicina'],
            ['telemedicine_cases', 'medical_team_supplier_id', 'casos de telemedicina'],
            ['telemedicine_patients', 'supplier_id', 'pacientes de telemedicina'],
            ['telemedicine_history_patients', 'supplier_id', 'historias clínicas'],
            ['telemedicine_amd_informs', 'supplier_id', 'informes AMD'],
            ['telemedicine_doctors', 'supplier_id', 'médicos de telemedicina'],
            ['users', 'supplier_id', 'usuarios del portal'],
        ],
        self::TYPE_DOCTOR_NURSE => [
            ['operation_service_orders', 'doctor_nurse_id', 'órdenes de servicio'],
            ['operation_coordination_services', 'assigned_doctor_nurse_id', 'servicios médicos'],
        ],
        self::TYPE_CORPORATE_ALLY => [
            ['operation_coordination_services', 'assigned_corporate_ally_id', 'servicios médicos'],
        ],
    ];

    public static function userCan(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::DELETE_PROVIDERS);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function references(string $type): array
    {
        return self::REFERENCES[self::assertType($type)];
    }

    /**
     * Uso de cada proveedor, en una consulta agrupada por referencia (sin N+1).
     *
     * Va con el constructor de consultas a propósito: cuenta también filas que
     * los scopes globales ocultan (por ejemplo, trazas de casos eliminados).
     * «Cualquier servicio, histórico incluido» bloquea la eliminación.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, int>> id => [etiqueta => cantidad]
     */
    public static function usageFor(string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        $usage = [];

        if ($ids === []) {
            return $usage;
        }

        foreach (self::references($type) as [$table, $column, $label]) {
            $rows = DB::table($table)
                ->whereIn($column, $ids)
                ->selectRaw($column.' as provider_id, COUNT(*) as total')
                ->groupBy($column)
                ->get();

            foreach ($rows as $row) {
                $providerId = (int) $row->provider_id;
                $usage[$providerId][$label] = ($usage[$providerId][$label] ?? 0) + (int) $row->total;
            }
        }

        return $usage;
    }

    /**
     * @param  array<string, int>  $usage
     */
    public static function usageSummary(array $usage): string
    {
        return implode(' · ', array_map(
            static fn (string $label, int $count): string => $count.' '.$label,
            array_keys($usage),
            array_values($usage),
        ));
    }

    /**
     * Elimina (borrado suave). Antes resuelve, en la misma transacción, las órdenes y citas
     * vigentes según `$resolutions`; si una falla, no se guarda nada y el proveedor queda.
     *
     * @param  list<array{key?: string, action?: string, target?: string|null, cancel_reason?: string|null}>  $resolutions
     * @return array{reassigned: int, cancelled_orders: int, cancelled_appointments: int}
     *
     * @throws InvalidArgumentException si tiene bloqueos, pendientes sin resolver, ya está eliminado o el motivo es corto
     * @throws Throwable
     */
    public static function delete(Model $provider, string $reason, array $resolutions = []): array
    {
        $type = self::typeOf($provider);
        $reason = self::validatedReason($reason);
        $user = Auth::user();
        $userName = (string) ($user->name ?? 'SISTEMA');

        return DB::transaction(function () use ($provider, $type, $reason, $resolutions, $user, $userName): array {
            /** Se relee bloqueado: entre abrir la ventana y confirmar pudo recibir un servicio. */
            $fresh = $provider::query()->withTrashed()->lockForUpdate()->find($provider->getKey());

            if ($fresh === null || $fresh->trashed()) {
                throw new InvalidArgumentException(self::label($provider).' ya está eliminado.');
            }

            $pending = ProviderPendingWork::forProvider($type, $fresh);

            if ($pending['blockers'] !== []) {
                throw new InvalidArgumentException(self::label($fresh).' no se puede eliminar todavía: tiene '.implode(' ', $pending['blockers']));
            }

            $outcome = ['reassigned' => 0, 'cancelled_orders' => 0, 'cancelled_appointments' => 0];

            /** La bitácora es lo que permite deshacer la eliminación completa al restaurar. */
            $log = ProviderDeletionLog::query()->create([
                'provider_type' => $type,
                'provider_id' => (int) $fresh->getKey(),
                'provider_name' => self::name($fresh),
                'reason' => $reason,
                'deleted_by_user_id' => $user?->getAuthIdentifier(),
                'deleted_by_name' => $userName,
            ]);

            if (ProviderPendingWork::hasResolvable($pending)) {
                if ($resolutions === []) {
                    throw new InvalidArgumentException(self::label($fresh).' tiene '.self::pendingSummary($pending).': elimínelo desde su fila para reasignarlas o cancelarlas.');
                }

                $outcome = self::applyResolutions($type, $fresh, $pending, $resolutions, $userName, $log);
                $pending = ProviderPendingWork::forProvider($type, $fresh);

                if (! ProviderPendingWork::isClear($pending)) {
                    throw new InvalidArgumentException(self::label($fresh).' todavía tiene pendientes después de resolver: '.self::pendingSummary($pending).'. No se guardó nada.');
                }
            }

            $fresh->forceFill([
                'deleted_by_user_id' => $user?->getAuthIdentifier(),
                'deleted_by_name' => $userName,
                'deletion_reason' => $reason,
            ])->saveQuietly();

            $fresh->delete();

            $resolved = $outcome['reassigned'] + $outcome['cancelled_orders'] + $outcome['cancelled_appointments'];

            self::writeObservation($type, $fresh, 'PROVEEDOR ELIMINADO · Motivo: '.$reason
                .($resolved > 0 ? ' · Antes de eliminar: '.self::outcomeSummary($outcome).'.' : ''));

            SecurityAudit::log('AUDIT_PROVIDER_DELETED', 'operations.providers.delete', [
                'type' => $type,
                'provider_id' => (int) $fresh->getKey(),
                'provider_name' => self::name($fresh),
                'reason' => $reason,
                ...$outcome,
            ]);

            return $outcome;
        });
    }

    /**
     * @param  array{orders: list<array{id: int, label: string}>, appointments: list<array{id: int, label: string}>, blockers: list<string>}  $pending
     * @param  list<array{key?: string, action?: string, target?: string|null, cancel_reason?: string|null}>  $resolutions
     * @return array{reassigned: int, cancelled_orders: int, cancelled_appointments: int}
     */
    private static function applyResolutions(string $type, Model $provider, array $pending, array $resolutions, string $userName, ProviderDeletionLog $log): array
    {
        $byKey = [];

        foreach ($resolutions as $resolution) {
            $key = (string) ($resolution['key'] ?? '');

            if ($key !== '') {
                $byKey[$key] = $resolution;
            }
        }

        $expected = [
            ...array_map(fn (array $order): string => self::resolutionKey('order', $order['id']), $pending['orders']),
            ...array_map(fn (array $appointment): string => self::resolutionKey('appointment', $appointment['id']), $pending['appointments']),
        ];

        $missing = array_diff($expected, array_keys($byKey));

        if ($missing !== []) {
            throw new InvalidArgumentException('Las órdenes o citas de '.self::name($provider).' cambiaron mientras tenía abierta la ventana. Ciérrela y vuelva a intentarlo.');
        }

        $outcome = ['reassigned' => 0, 'cancelled_orders' => 0, 'cancelled_appointments' => 0];
        $ownKey = OperationServiceOrderReassignment::targetKey($type, (int) $provider->getKey());

        foreach ($pending['orders'] as $order) {
            $resolution = $byKey[self::resolutionKey('order', $order['id'])];
            $model = OperationServiceOrder::query()->lockForUpdate()->find($order['id']);

            if (! $model instanceof OperationServiceOrder) {
                throw new InvalidArgumentException('La orden '.$order['label'].' ya no existe.');
            }

            if (($resolution['action'] ?? null) === self::RESOLVE_REASSIGN) {
                $target = OperationServiceOrderReassignment::parseTarget($resolution['target'] ?? null);

                if (OperationServiceOrderReassignment::targetKey($target['type'], $target['id']) === $ownKey) {
                    throw new InvalidArgumentException('La orden '.$order['label'].' no puede reasignarse al mismo proveedor que se elimina.');
                }

                $snapshot = ProviderDeletionRestoration::captureOrder($model, withClinicalItems: false);

                OperationServiceOrderReassignment::reassign(
                    $model,
                    $target['type'],
                    $target['id'],
                    'Eliminación del proveedor '.self::name($provider).'.',
                    $userName,
                );

                self::logItem($log, ProviderDeletionLogItem::KIND_ORDER, self::RESOLVE_REASSIGN, $snapshot, [
                    'target_type' => $target['type'],
                    'target_id' => $target['id'],
                ], orderId: (int) $model->getKey());
                $outcome['reassigned']++;

                continue;
            }

            if (($resolution['action'] ?? null) === self::RESOLVE_CANCEL) {
                $snapshot = ProviderDeletionRestoration::captureOrder($model, withClinicalItems: true);

                try {
                    OperationServiceOrderViewActions::cancelOrderOrFail(
                        $model,
                        self::resolutionReason($resolution, 'la orden '.$order['label']).' (eliminación del proveedor '.self::name($provider).')',
                        $userName,
                    );
                } catch (ServiceOrderFlowException $exception) {
                    throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
                }

                self::releaseCoordinationAssignment($type, $provider, $model, $userName);
                self::logItem($log, ProviderDeletionLogItem::KIND_ORDER, self::RESOLVE_CANCEL, $snapshot, null, orderId: (int) $model->getKey());
                $outcome['cancelled_orders']++;

                continue;
            }

            throw new InvalidArgumentException('Indique si la orden '.$order['label'].' se reasigna o se cancela.');
        }

        foreach ($pending['appointments'] as $appointment) {
            $resolution = $byKey[self::resolutionKey('appointment', $appointment['id'])];

            if (($resolution['action'] ?? null) !== self::RESOLVE_CANCEL) {
                throw new InvalidArgumentException('La '.mb_strtolower($appointment['label']).' pertenece a una orden cerrada: solo puede cancelarse.');
            }

            $model = OperationMedicalAppointment::query()->lockForUpdate()->find($appointment['id']);

            if (! $model instanceof OperationMedicalAppointment) {
                throw new InvalidArgumentException('La '.mb_strtolower($appointment['label']).' ya no existe.');
            }

            self::logItem(
                $log,
                ProviderDeletionLogItem::KIND_APPOINTMENT,
                self::RESOLVE_CANCEL,
                ProviderDeletionRestoration::captureAppointment($model),
                null,
                orderId: $model->operation_service_order_id !== null ? (int) $model->operation_service_order_id : null,
                appointmentId: (int) $model->getKey(),
            );

            MedicalAppointmentManager::cancel(
                $model,
                self::resolutionReason($resolution, 'la '.mb_strtolower($appointment['label'])).' (eliminación del proveedor '.self::name($provider).')',
            );
            $outcome['cancelled_appointments']++;
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $result
     */
    private static function logItem(
        ProviderDeletionLog $log,
        string $kind,
        string $action,
        array $snapshot,
        ?array $result,
        ?int $orderId = null,
        ?int $appointmentId = null,
    ): void {
        ProviderDeletionLogItem::query()->create([
            'provider_deletion_log_id' => $log->getKey(),
            'kind' => $kind,
            'action' => $action,
            'operation_service_order_id' => $orderId,
            'operation_medical_appointment_id' => $appointmentId,
            'snapshot' => $snapshot,
            'result' => $result,
        ]);
    }

    /**
     * La orden cancelada deja su servicio médico sin el proveedor que se elimina: ese
     * servicio necesita que alguien le asigne otro, y así queda a la vista.
     */
    private static function releaseCoordinationAssignment(string $type, Model $provider, OperationServiceOrder $order, string $userName): void
    {
        if (! filled($order->operation_coordination_service_id)) {
            return;
        }

        $column = ProviderPendingWork::COORDINATION_ASSIGNED_COLUMN[$type];
        $coordination = OperationCoordinationService::query()->withoutGlobalScopes()->lockForUpdate()->find($order->operation_coordination_service_id);

        if (! $coordination instanceof OperationCoordinationService || (int) $coordination->getAttribute($column) !== (int) $provider->getKey()) {
            return;
        }

        $coordination->setAttribute($column, null);
        $coordination->updated_by = $userName;
        $coordination->save();

        OperationServiceOrderViewActions::appendToCoordinationAndCase(
            $order,
            'Proveedor asignado retirado: '.self::name($provider).' se eliminó y su orden '.ServiceOrderFlowTrace::orderLabel($order).' se canceló. El servicio queda sin proveedor asignado.',
            $userName,
        );
    }

    /**
     * @param  array{cancel_reason?: string|null}  $resolution
     */
    private static function resolutionReason(array $resolution, string $what): string
    {
        $reason = trim((string) ($resolution['cancel_reason'] ?? ''));

        if (mb_strlen($reason) < self::MINIMUM_REASON_LENGTH) {
            throw new InvalidArgumentException('Indique por qué se cancela '.$what.' (mínimo '.self::MINIMUM_REASON_LENGTH.' caracteres).');
        }

        return $reason;
    }

    public static function resolutionKey(string $kind, int $id): string
    {
        return $kind.':'.$id;
    }

    /**
     * @param  array{orders: list<mixed>, appointments: list<mixed>, blockers: list<string>}  $pending
     */
    public static function pendingSummary(array $pending): string
    {
        $parts = [];

        if ($pending['orders'] !== []) {
            $parts[] = count($pending['orders']).(count($pending['orders']) === 1 ? ' orden vigente' : ' órdenes vigentes');
        }

        if ($pending['appointments'] !== []) {
            $parts[] = count($pending['appointments']).(count($pending['appointments']) === 1 ? ' cita futura' : ' citas futuras');
        }

        return implode(' y ', [...$parts, ...$pending['blockers']]);
    }

    /**
     * @param  array{reassigned: int, cancelled_orders: int, cancelled_appointments: int}  $outcome
     */
    public static function outcomeSummary(array $outcome): string
    {
        return implode(', ', array_filter([
            $outcome['reassigned'] > 0 ? $outcome['reassigned'].($outcome['reassigned'] === 1 ? ' orden reasignada' : ' órdenes reasignadas') : null,
            $outcome['cancelled_orders'] > 0 ? $outcome['cancelled_orders'].($outcome['cancelled_orders'] === 1 ? ' orden cancelada' : ' órdenes canceladas') : null,
            $outcome['cancelled_appointments'] > 0 ? $outcome['cancelled_appointments'].($outcome['cancelled_appointments'] === 1 ? ' cita cancelada' : ' citas canceladas') : null,
        ]));
    }

    /**
     * Restaura el proveedor y, si su eliminación tiene bitácora, devuelve cada orden y cita
     * que pueda volver sin pisar lo que pasó después ({@see ProviderDeletionRestoration}).
     * Lo que no puede volver se queda como está, con el motivo. Todo en una transacción.
     *
     * @param  bool  $undoResolutions  la restauración masiva no deshace órdenes: omite a quien tenga
     * @return array{restored: int, skipped: int, notes: list<string>, has_log: bool}
     *
     * @throws InvalidArgumentException si no está eliminado, el motivo es corto o hay órdenes por devolver en una restauración masiva
     * @throws Throwable
     */
    public static function restore(Model $provider, string $reason, bool $undoResolutions = true): array
    {
        $type = self::typeOf($provider);
        $reason = self::validatedReason($reason);
        $user = Auth::user();
        $userName = (string) ($user->name ?? 'SISTEMA');

        return DB::transaction(function () use ($provider, $type, $reason, $undoResolutions, $user, $userName): array {
            $fresh = $provider::query()->withTrashed()->lockForUpdate()->find($provider->getKey());

            if ($fresh === null || ! $fresh->trashed()) {
                throw new InvalidArgumentException(self::label($provider).' no está eliminado.');
            }

            $log = ProviderDeletionLog::query()
                ->where('provider_type', $type)
                ->where('provider_id', $fresh->getKey())
                ->whereNull('restored_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($log instanceof ProviderDeletionLog && ! $undoResolutions && $log->items()->exists()) {
                throw new InvalidArgumentException(self::label($fresh).' tiene órdenes o citas que se pueden devolver: restáurelo desde su fila para revisarlas.');
            }

            $deletedBy = (string) ($fresh->deleted_by_name ?? '—');

            $fresh->restore();
            $fresh->forceFill([
                'deleted_by_user_id' => null,
                'deleted_by_name' => null,
                'deletion_reason' => null,
            ])->saveQuietly();

            $summary = ['restored' => 0, 'skipped' => 0, 'notes' => [], 'has_log' => $log instanceof ProviderDeletionLog];

            if ($log instanceof ProviderDeletionLog) {
                foreach ($log->items()->get() as $item) {
                    $verdict = ProviderDeletionRestoration::apply($item, $type, (int) $fresh->getKey(), 'Restauración del proveedor '.self::name($fresh).': '.$reason, $userName);

                    if ($verdict['ok']) {
                        $summary['restored']++;

                        if ($verdict['warning'] !== null) {
                            $summary['notes'][] = $verdict['warning'];
                        }

                        continue;
                    }

                    $summary['skipped']++;
                    $summary['notes'][] = $verdict['note'];
                }

                $log->forceFill([
                    'restored_at' => now(),
                    'restored_by_user_id' => $user?->getAuthIdentifier(),
                    'restored_by_name' => $userName,
                    'restoration_reason' => $reason,
                    'restoration_summary' => ['restored' => $summary['restored'], 'skipped' => $summary['skipped']],
                ])->save();
            }

            self::writeObservation($type, $fresh, 'PROVEEDOR RESTAURADO · Motivo: '.$reason
                .($summary['restored'] + $summary['skipped'] > 0
                    ? ' · Órdenes y citas devueltas: '.$summary['restored'].($summary['skipped'] > 0 ? ' · no se pudieron devolver: '.$summary['skipped'] : '')
                    : ''));

            SecurityAudit::log('AUDIT_PROVIDER_RESTORED', 'operations.providers.restore', [
                'type' => $type,
                'provider_id' => (int) $fresh->getKey(),
                'provider_name' => self::name($fresh),
                'deleted_by' => $deletedBy,
                'reason' => $reason,
                'deletion_log_id' => $log?->getKey(),
                'restored_items' => $summary['restored'],
                'skipped_items' => $summary['skipped'],
            ]);

            return $summary;
        });
    }

    public static function makeDeleteAction(string $type): Action
    {
        return self::configureDeletion(Action::make('delete_provider'), $type, bulk: false)
            ->visible(fn (mixed $livewire, mixed $record): bool => self::userCan()
                && ! self::viewingDeleted($livewire)
                && $record instanceof Model
                && ! $record->trashed())
            ->modalWidth(fn (Model $record): Width => ProviderPendingWork::hasResolvable(self::pendingFor($type, $record)) ? Width::FourExtraLarge : Width::Large)
            ->modalSubmitActionLabel(fn (Model $record): string => ProviderPendingWork::hasResolvable(self::pendingFor($type, $record)) ? 'Resolver y eliminar' : 'Sí, eliminar')
            ->modalSubmitAction(fn (Action $action, Model $record): Action => self::styleButton($action, 'danger')
                ->disabled(self::pendingFor($type, $record)['blockers'] !== []))
            ->fillForm(fn (Model $record): array => [
                'own_key' => OperationServiceOrderReassignment::targetKey($type, (int) $record->getKey()),
                'resolutions' => self::resolutionRows(self::pendingFor($type, $record)),
            ])
            ->schema(fn (Model $record): array => [
                ...self::resolutionComponents($type, $record),
                self::reasonField('deletion_reason', deleting: true),
            ])
            ->action(function (Model $record, array $data) use ($type): void {
                $resolutions = array_values(array_filter((array) ($data['resolutions'] ?? []), 'is_array'));

                self::runOver($type, Collection::make([$record]), (string) ($data['deletion_reason'] ?? ''), deleting: true, resolutions: $resolutions);
            });
    }

    /**
     * Pendientes del proveedor, memorizados por petición: la ventana los consulta para la
     * descripción, el formulario, el botón y el ancho.
     *
     * @return array{orders: list<array<string, mixed>>, appointments: list<array<string, mixed>>, blockers: list<string>}
     */
    public static function pendingFor(string $type, Model $provider): array
    {
        return once(static fn (): array => ProviderPendingWork::forProvider($type, $provider));
    }

    /**
     * @param  array{orders: list<array<string, mixed>>, appointments: list<array<string, mixed>>}  $pending
     * @return list<array<string, mixed>>
     */
    private static function resolutionRows(array $pending): array
    {
        $rows = [];

        foreach ($pending['orders'] as $order) {
            $rows[] = [
                'key' => self::resolutionKey('order', $order['id']),
                'kind' => 'order',
                'title' => 'Orden '.$order['label'],
                'summary' => 'Paciente: '.$order['patient'].' · Servicio: '.$order['service_type']
                    .($order['appointment'] !== null ? ' · Cita: '.$order['appointment'] : '')
                    .' · Monto: '.$order['amount'],
                'action' => null,
                'target' => null,
                'cancel_reason' => null,
            ];
        }

        foreach ($pending['appointments'] as $appointment) {
            $rows[] = [
                'key' => self::resolutionKey('appointment', $appointment['id']),
                'kind' => 'appointment',
                'title' => $appointment['label'].' · '.$appointment['appointment'],
                'summary' => 'Paciente: '.$appointment['patient'].' · Su orden '.$appointment['order_label'].' ya está cerrada: la cita solo puede cancelarse.',
                'action' => self::RESOLVE_CANCEL,
                'target' => null,
                'cancel_reason' => null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private static function resolutionComponents(string $type, Model $provider): array
    {
        $pending = self::pendingFor($type, $provider);

        if (! ProviderPendingWork::hasResolvable($pending) || $pending['blockers'] !== []) {
            return [];
        }

        $hasOrders = $pending['orders'] !== [];

        return [
            Hidden::make('own_key'),
            Section::make('Órdenes y citas por resolver')
                ->description('Decida qué pasa con cada una. Al reasignar, el nuevo proveedor recibe la orden por correo y WhatsApp; al cancelar, la orden y su cita quedan CANCELADAS con su motivo. Si algo falla no se guarda nada.')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columnSpanFull()
                ->schema([
                    Select::make('reassign_all')
                        ->label('Atajo: reasignar todas las órdenes a…')
                        ->helperText('Opcional. Rellena todas las órdenes con este proveedor; después puede cambiar alguna.')
                        ->placeholder('Buscar proveedor jurídico o natural')
                        ->visible($hasOrders && count($pending['orders']) > 1)
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search, Get $get): array => OperationServiceOrderReassignment::searchTargets($search, (string) $get('own_key')))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => OperationServiceOrderReassignment::targetLabel($value))
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                            if (! filled($state)) {
                                return;
                            }

                            $rows = (array) $get('resolutions');

                            foreach ($rows as $index => $row) {
                                if (($row['kind'] ?? null) === 'order') {
                                    $rows[$index]['action'] = self::RESOLVE_REASSIGN;
                                    $rows[$index]['target'] = $state;
                                }
                            }

                            $set('resolutions', $rows);
                        }),
                    Repeater::make('resolutions')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            Hidden::make('key')->required(),
                            Hidden::make('kind'),
                            Hidden::make('title'),
                            Hidden::make('summary'),
                            Placeholder::make('summary_text')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => (string) $get('summary')),
                            ToggleButtons::make('action')
                                ->label('Qué hacer')
                                ->options(fn (Get $get): array => $get('kind') === 'order'
                                    ? [self::RESOLVE_REASSIGN => 'Reasignar a otro proveedor', self::RESOLVE_CANCEL => 'Cancelar']
                                    : [self::RESOLVE_CANCEL => 'Cancelar la cita'])
                                ->colors([self::RESOLVE_REASSIGN => 'info', self::RESOLVE_CANCEL => 'danger'])
                                ->icons([self::RESOLVE_REASSIGN => Heroicon::OutlinedArrowsRightLeft, self::RESOLVE_CANCEL => Heroicon::OutlinedXCircle])
                                ->inline()
                                ->required()
                                ->live()
                                ->validationMessages(['required' => 'Elija si se reasigna o se cancela.']),
                            Select::make('target')
                                ->label('Nuevo proveedor')
                                ->placeholder('Buscar proveedor jurídico o natural')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => OperationServiceOrderReassignment::searchTargets($search, (string) $get('../../own_key')))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => OperationServiceOrderReassignment::targetLabel($value))
                                ->visible(fn (Get $get): bool => $get('action') === self::RESOLVE_REASSIGN)
                                ->required(fn (Get $get): bool => $get('action') === self::RESOLVE_REASSIGN)
                                ->validationMessages(['required' => 'Elija el proveedor que atenderá la orden.']),
                            Textarea::make('cancel_reason')
                                ->label('Motivo de la cancelación')
                                ->placeholder('Ej.: no hay otro proveedor en la zona que preste el servicio…')
                                ->rows(2)
                                ->maxLength(1000)
                                ->visible(fn (Get $get): bool => $get('action') === self::RESOLVE_CANCEL)
                                ->required(fn (Get $get): bool => $get('action') === self::RESOLVE_CANCEL)
                                ->minLength(self::MINIMUM_REASON_LENGTH)
                                ->validationMessages([
                                    'required' => 'Indique por qué se cancela.',
                                    'min' => 'El motivo debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.',
                                    'minLength' => 'El motivo debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.',
                                ]),
                        ]),
                ]),
        ];
    }

    public static function makeDeleteBulkAction(string $type): BulkAction
    {
        return self::configureDeletion(BulkAction::make('delete_providers'), $type, bulk: true)
            ->visible(fn (mixed $livewire): bool => self::userCan() && ! self::viewingDeleted($livewire))
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection|Collection $records, array $data) use ($type): void {
                self::runOver($type, Collection::make($records), (string) ($data['deletion_reason'] ?? ''), deleting: true);
            });
    }

    public static function makeRestoreAction(string $type): Action
    {
        return self::configureRestoration(Action::make('restore_provider'), $type, bulk: false)
            ->visible(fn (mixed $record): bool => self::userCan() && $record instanceof Model && $record->trashed())
            ->modalWidth(fn (Model $record): Width => self::restorationPreview($type, $record) !== [] ? Width::ThreeExtraLarge : Width::Large)
            ->modalSubmitActionLabel(fn (Model $record): string => collect(self::restorationPreview($type, $record))->contains('ok', true) ? 'Restaurar y devolver' : 'Sí, restaurar')
            ->action(function (Model $record, array $data) use ($type): void {
                self::runOver($type, Collection::make([$record]), (string) ($data['restoration_reason'] ?? ''), deleting: false);
            });
    }

    public static function makeRestoreBulkAction(string $type): BulkAction
    {
        return self::configureRestoration(BulkAction::make('restore_providers'), $type, bulk: true)
            ->visible(fn (mixed $livewire): bool => self::userCan() && self::viewingDeleted($livewire))
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection|Collection $records, array $data) use ($type): void {
                self::runOver($type, Collection::make($records), (string) ($data['restoration_reason'] ?? ''), deleting: false, undoResolutions: false);
            });
    }

    /**
     * «Ver solo proveedores eliminados». Fuera de esa vista el borrado suave los oculta.
     */
    public static function makeDeletedFilter(): Filter
    {
        return Filter::make(self::DELETED_FILTER)
            ->label('Ver solo proveedores eliminados')
            ->toggle()
            ->columnSpanFull()
            ->visible(fn (): bool => self::userCan())
            ->baseQuery(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false) === true
                ? $query->onlyTrashed()
                : $query);
    }

    public static function viewingDeleted(mixed $livewire): bool
    {
        return data_get($livewire, 'tableFilters.'.self::DELETED_FILTER.'.isActive') === true && self::userCan();
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function configureDeletion(Action $action, string $type, bool $bulk): Action
    {
        $definition = self::TYPES[self::assertType($type)];

        return $action
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading($bulk ? 'Eliminar '.$definition['plural'] : 'Eliminar '.$definition['singular'])
            ->modalDescription($bulk
                ? fn (EloquentCollection|Collection $records): HtmlString => self::deletionDescription($type, Collection::make($records), canResolve: false)
                : fn (Model $record): HtmlString => self::deletionDescription($type, Collection::make([$record]), canResolve: true))
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => self::styleButton($action, 'danger'))
            ->modalCancelAction(fn (Action $action): Action => self::styleButton($action, 'gray'))
            ->schema([self::reasonField('deletion_reason', deleting: true)]);
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function configureRestoration(Action $action, string $type, bool $bulk): Action
    {
        $definition = self::TYPES[self::assertType($type)];

        return $action
            ->label('Restaurar')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->modalHeading('Restaurar '.$definition['singular'])
            ->modalDescription($bulk
                ? fn (EloquentCollection|Collection $records): HtmlString => self::restorationDescription($type, Collection::make($records), withPreview: false)
                : fn (Model $record): HtmlString => self::restorationDescription($type, Collection::make([$record]), withPreview: true))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, restaurar')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => self::styleButton($action, 'success'))
            ->modalCancelAction(fn (Action $action): Action => self::styleButton($action, 'gray'))
            ->schema([self::reasonField('restoration_reason', deleting: false)]);
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    /**
     * @param  Collection<int, mixed>  $records
     * @param  list<array<string, mixed>>  $resolutions  solo en la eliminación individual
     */
    private static function runOver(string $type, Collection $records, string $reason, bool $deleting, array $resolutions = [], bool $undoResolutions = true): void
    {
        if (! self::userCan()) {
            Notification::make()
                ->title('Acción no autorizada')
                ->body('No tiene permiso para eliminar o restaurar proveedores.')
                ->danger()
                ->send();

            return;
        }

        $processed = 0;
        /** @var list<string> $skipped */
        $skipped = [];
        /** @var list<string> $resolvedNotes */
        $resolvedNotes = [];

        foreach ($records->filter(fn (mixed $record): bool => $record instanceof Model) as $provider) {
            try {
                if ($deleting) {
                    $outcome = self::delete($provider, $reason, $resolutions);
                    $resolvedSummary = self::outcomeSummary($outcome);

                    if ($resolvedSummary !== '') {
                        $resolvedNotes[] = self::name($provider).': '.$resolvedSummary;
                    }
                } else {
                    $restoration = self::restore($provider, $reason, $undoResolutions);

                    if ($restoration['restored'] + $restoration['skipped'] > 0) {
                        $resolvedNotes[] = self::name($provider).': '.$restoration['restored'].' orden(es) o cita(s) devuelta(s)'
                            .($restoration['skipped'] > 0 ? ', '.$restoration['skipped'].' no se pudieron devolver' : '')
                            .($restoration['notes'] !== [] ? ' ('.implode(' ', array_unique($restoration['notes'])).')' : '');
                    }
                }

                $processed++;
            } catch (InvalidArgumentException $exception) {
                $skipped[] = $exception->getMessage();
            } catch (Throwable $exception) {
                Log::error('ProviderDeletion: error', [
                    'type' => $type,
                    'provider_id' => $provider->getKey(),
                    'deleting' => $deleting,
                    'message' => $exception->getMessage(),
                ]);

                $skipped[] = self::label($provider).' no pudo procesarse por un error inesperado.';
            }
        }

        $definition = self::TYPES[$type];
        $noun = $processed === 1 ? $definition['singular'] : $definition['plural'];

        if ($processed === 0) {
            Notification::make()
                ->title($deleting ? 'No se eliminó ningún '.$definition['singular'] : 'No se restauró ningún '.$definition['singular'])
                ->body($skipped === [] ? 'No hubo registros válidos para procesar.' : implode(' ', $skipped))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $body = $deleting
            ? "{$processed} {$noun} eliminado(s). Ya no aparecen en el sistema; se pueden restaurar con «Ver solo proveedores eliminados»."
            : "{$processed} {$noun} restaurado(s). Vuelven a estar disponibles en todo el sistema.";

        if ($resolvedNotes !== []) {
            $body .= $deleting
                ? ' Antes de eliminar: '.implode('; ', $resolvedNotes).'. Los nuevos proveedores reciben la orden por correo y WhatsApp.'
                : ' '.implode('; ', $resolvedNotes).'. Los proveedores involucrados reciben el aviso por correo y WhatsApp.';
        }

        if ($skipped !== []) {
            $body .= ' No se procesaron: '.implode(' ', $skipped);
        }

        $notification = Notification::make()
            ->title($deleting ? 'Eliminación completada' : 'Restauración completada')
            ->body($body)
            ->status($skipped === [] ? 'success' : 'warning');

        if ($skipped !== []) {
            $notification->persistent();
        }

        $notification->send();
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private static function deletionDescription(string $type, Collection $records, bool $canResolve): HtmlString
    {
        $providers = $records->filter(fn (mixed $record): bool => $record instanceof Model)->values();

        if ($providers->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-300">Seleccione al menos un registro.</p>');
        }

        $rows = [];
        $blocked = 0;

        foreach ($providers as $provider) {
            $pending = self::pendingFor($type, $provider);
            $name = '<span class="font-semibold">'.e(self::name($provider)).'</span>';

            if ($pending['blockers'] !== []) {
                $blocked++;
                $rows[] = '<li class="text-amber-700 dark:text-amber-300">'.$name.' — no se puede eliminar todavía. Falta resolver: '
                    .e(implode(' ', $pending['blockers'])).'</li>';

                continue;
            }

            if (ProviderPendingWork::hasResolvable($pending)) {
                if ($canResolve) {
                    $rows[] = '<li class="text-sky-700 dark:text-sky-300">'.$name.' tiene '.e(self::pendingSummary($pending)).'. Resuélvalas abajo: reasígnelas a otro proveedor o cancélelas; después se elimina.</li>';

                    continue;
                }

                $blocked++;
                $rows[] = '<li class="text-amber-700 dark:text-amber-300">'.$name.' — tiene '.e(self::pendingSummary($pending)).': elimínelo desde su fila para reasignarlas o cancelarlas.</li>';

                continue;
            }

            $rows[] = '<li><span class="font-semibold text-gray-900 dark:text-white">'.e(self::name($provider)).'</span>'
                .' <span class="text-xs text-gray-500 dark:text-gray-400">· sin pendientes: se eliminará</span></li>';
        }

        $note = $blocked === 0 || $canResolve
            ? ''
            : '<p class="font-semibold text-amber-700 dark:text-amber-300">'.$blocked.' de '.$providers->count().' no se eliminará(n) en esta selección.</p>';

        return new HtmlString(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">'
            .'<p>El registro dejará de aparecer en tablas, buscadores y selectores de todo el sistema. '
            .'<span class="font-semibold text-gray-900 dark:text-white">Nada se borra de la base de datos</span>: se puede restaurar activando «Ver solo proveedores eliminados».</p>'
            .'<p>Su historial (órdenes cerradas, citas pasadas, casos, informes y cuentas pagadas) se conserva y lo seguirá mostrando como «(eliminado)». '
            .'Las órdenes y citas vigentes deben reasignarse o cancelarse; las cuentas por pagar pendientes, los usuarios activos del portal y los servicios médicos abiertos se resuelven antes en su módulo.</p>'
            .$note
            .'<ul class="list-disc space-y-1 ps-5">'.implode('', $rows).'</ul>'
            .'</div>'
        );
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private static function restorationDescription(string $type, Collection $records, bool $withPreview): HtmlString
    {
        $rows = $records
            ->filter(fn (mixed $record): bool => $record instanceof Model)
            ->map(function (Model $provider) use ($type, $withPreview): string {
                $preview = self::restorationPreview($type, $provider);
                $row = '<li><span class="font-semibold text-gray-900 dark:text-white">'.e(self::name($provider)).'</span>'
                    .'<br><span class="text-xs text-gray-500 dark:text-gray-400">Eliminado por '.e((string) ($provider->deleted_by_name ?? '—'))
                    .($provider->deleted_at !== null ? ' el '.e($provider->deleted_at->format('d/m/Y H:i')) : '')
                    .(filled($provider->deletion_reason) ? ' · Motivo: '.e((string) $provider->deletion_reason) : '')
                    .'</span>';

                if ($preview !== [] && ! $withPreview) {
                    return $row.'<br><span class="text-xs font-semibold text-amber-700 dark:text-amber-300">Tiene órdenes o citas que se pueden devolver: restáurelo desde su fila para revisarlas. No se restaurará en esta selección.</span></li>';
                }

                return $row.'</li>';
            })
            ->implode('');

        $previewHtml = '';

        if ($withPreview) {
            $provider = $records->first(fn (mixed $record): bool => $record instanceof Model);
            $preview = $provider instanceof Model ? self::restorationPreview($type, $provider) : [];
            $hasLog = $provider instanceof Model && ProviderDeletionRestoration::openLogFor($type, $provider) instanceof ProviderDeletionLog;

            if ($preview !== []) {
                $items = array_map(fn (array $item): string => '<li class="'.($item['ok'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300').'">'
                    .'<span class="font-semibold">'.($item['ok'] ? '✓ Vuelve' : '✗ No se puede devolver').' · '.e($item['title']).'</span>'
                    .'<br><span class="text-xs">'.e($item['note']).'</span>'
                    .($item['warning'] !== null ? '<br><span class="text-xs font-semibold text-amber-700 dark:text-amber-300">'.e($item['warning']).'</span>' : '')
                    .'</li>', $preview);

                $previewHtml = '<p class="font-semibold text-gray-900 dark:text-white">Al eliminarlo se reasignaron o cancelaron estas órdenes y citas:</p>'
                    .'<ul class="space-y-2 ps-1">'.implode('', $items).'</ul>'
                    .'<p class="text-xs">Lo que vuelve regresa a como estaba antes de eliminar. Al devolver una orden reasignada, el proveedor original la recibe de nuevo y al que la tenía se le avisa que ya no está a su cargo. Lo que no puede volver se queda como está.</p>';
            } elseif (! $hasLog) {
                $previewHtml = '<p class="text-xs">Este proveedor se eliminó sin reasignar ni cancelar órdenes (o antes de que existiera la restauración completa): solo vuelve el proveedor.</p>';
            }
        }

        return new HtmlString(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">'
            .'<p>Volverá a aparecer en todo el sistema con los mismos datos que tenía.</p>'
            .'<ul class="list-disc space-y-2 ps-5">'.$rows.'</ul>'
            .$previewHtml
            .'</div>'
        );
    }

    /**
     * Veredicto de devolución de cada orden y cita de la última eliminación sin restaurar,
     * memorizado por petición (la ventana lo consulta varias veces).
     *
     * @return list<array{item_id: int, title: string, ok: bool, note: string, warning: string|null}>
     */
    public static function restorationPreview(string $type, Model $provider): array
    {
        return once(static function () use ($type, $provider): array {
            $log = ProviderDeletionRestoration::openLogFor($type, $provider);

            return $log instanceof ProviderDeletionLog ? ProviderDeletionRestoration::preview($log) : [];
        });
    }

    private static function reasonField(string $name, bool $deleting): Textarea
    {
        return Textarea::make($name)
            ->label($deleting ? 'Motivo de la eliminación' : 'Motivo de la restauración')
            ->placeholder($deleting
                ? 'Ej.: registro duplicado, cargado por error, el proveedor ya no existe…'
                : 'Ej.: se eliminó por error, el proveedor vuelve a trabajar con nosotros…')
            ->helperText('Obligatorio, mínimo '.self::MINIMUM_REASON_LENGTH.' caracteres. Queda en las observaciones del proveedor y en la auditoría.')
            ->required()
            ->minLength(self::MINIMUM_REASON_LENGTH)
            ->maxLength(2000)
            ->rows(4)
            ->columnSpanFull()
            ->validationMessages([
                'required' => 'Debe indicar el motivo.',
                'min' => 'El motivo debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.',
                'minLength' => 'El motivo debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.',
            ]);
    }

    private static function writeObservation(string $type, Model $provider, string $text): void
    {
        $definition = self::TYPES[$type];
        $user = Auth::user();

        $definition['observation']::query()->create(array_filter([
            $definition['observation_key'] => $provider->getKey(),
            'observation' => $text,
            'created_by' => (string) ($user->name ?? 'SISTEMA'),
        ], static fn (mixed $value): bool => $value !== null));
    }

    private static function typeOf(Model $provider): string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($provider instanceof $definition['model']) {
                return $type;
            }
        }

        throw new InvalidArgumentException('El registro no es un proveedor.');
    }

    private static function assertType(string $type): string
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Tipo de proveedor desconocido: '.$type);
        }

        return $type;
    }

    /**
     * Nombre para mostrar en el historial: un proveedor eliminado sigue apareciendo en sus
     * órdenes, citas y cuentas viejas, marcado para que nadie lo elija de nuevo.
     */
    public static function displayName(?Model $provider): ?string
    {
        if ($provider === null) {
            return null;
        }

        $name = self::name($provider);

        return method_exists($provider, 'trashed') && $provider->trashed() ? $name.' (eliminado)' : $name;
    }

    private static function name(Model $provider): string
    {
        $type = self::typeOf($provider);
        $name = trim((string) $provider->getAttribute(self::TYPES[$type]['name']));

        return $name !== '' ? $name : 'Registro #'.$provider->getKey();
    }

    private static function label(Model $provider): string
    {
        return 'El '.self::TYPES[self::typeOf($provider)]['singular'].' '.self::name($provider);
    }

    private static function validatedReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MINIMUM_REASON_LENGTH) {
            throw new InvalidArgumentException('Debe indicar el motivo con al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.');
        }

        return $reason;
    }

    private static function styleButton(Action $action, string $color): Action
    {
        return $action
            ->color($color)
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor($color)]);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Reversión de un ítem clínico FINALIZADO asociado a una coordinación (medicamento,
 * laboratorio, estudio o especialista; cubierto o no).
 *
 * Exige el permiso «Reversar ítems finalizados», una nota con el motivo y quién lo
 * autorizó, y la confirmación expresa de contar con autorización: hacerlo sin ella
 * tiene consecuencias laborales y la acción queda registrada en la bitácora del caso,
 * en la coordinación y en la auditoría del sistema.
 *
 * No reversa ítems que se finalizaron con una orden de servicio FINALIZADA: la orden,
 * su factura y la salida de inventario seguirían cerradas y el sistema quedaría
 * descuadrado. Los documentos ya cargados (comprobantes, soportes) no se borran.
 */
final class CoordinationServiceItemFinalizationReversal
{
    public const OBSERVATION_PREFIX = 'REVERSIÓN DE ÍTEM FINALIZADO';

    public const REVERSIBLE_STATUS = 'FINALIZADO';

    public const MINIMUM_OBSERVATION_LENGTH = 20;

    public const AUDIT_REVERSED = 'AUDIT_OPERATIONS_FINALIZED_ITEM_REVERSED';

    public const AUDIT_ROUTE = 'operations.coordination-services.item-reversal';

    public const LABOR_WARNING = 'Reversar un ítem finalizado sin autorización expresa de su supervisor constituye una falta y acarrea consecuencias a nivel laboral. Esta acción queda registrada con su usuario, fecha y hora.';

    /**
     * @var list<string>
     */
    private const CLOSED_ORDER_STATUSES = ['FINALIZADO', 'CANCELADA', 'CANCELADO', 'CADUCADA'];

    public static function userCanReverse(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REVERSE_FINALIZED_ITEMS);
    }

    public static function statusIsReversible(string $status): bool
    {
        return mb_strtoupper(trim($status)) === self::REVERSIBLE_STATUS;
    }

    /**
     * @param  array{id?: int|string, item_type?: string, title?: string, status?: string, coverage?: bool|null, can_reverse?: bool}  $row
     */
    public static function makeReverseAction(array $row): ?Action
    {
        if (! ($row['can_reverse'] ?? false)) {
            return null;
        }

        $itemId = (int) ($row['id'] ?? 0);
        $itemType = (string) ($row['item_type'] ?? '');
        $title = (string) ($row['title'] ?? 'Ítem');

        if ($itemId <= 0 || CoordinationServiceItemExternalManagement::clinicalItemModelClass($itemType) === null) {
            return null;
        }

        return Action::make('reverseFinalizedAssociatedItem_'.$itemType.'_'.$itemId)
            ->label('Reversar finalización')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->iconButton()
            ->tooltip('Reversar finalización')
            ->modalHeading('Reversar ítem finalizado')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<div class="space-y-3 text-left">'
                .'<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'<span class="font-semibold text-gray-900 dark:text-white">'.e($title).'</span> '
                .'(<span class="font-semibold">'.e(CoordinationServiceItemExternalManagement::coverageLabel(array_key_exists('coverage', $row) ? $row['coverage'] : null)).'</span>) '
                .'dejará de estar FINALIZADO y volverá a gestión. Los documentos ya cargados se conservan.'
                .'</p>'
                .'<div class="rounded-xl border border-rose-300/80 bg-rose-50 px-3 py-2.5 text-sm font-medium text-rose-900 dark:border-rose-500/40 dark:bg-rose-950/40 dark:text-rose-100">'
                .'<span class="font-bold">Advertencia:</span> '.e(self::LABOR_WARNING)
                .'</div>'
                .'</div>'
            ))
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, reversar finalización')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->form([
                Textarea::make('reversal_observation')
                    ->label('Motivo de la reversión y quién la autorizó')
                    ->placeholder('Ej.: Se finalizó por error, el paciente aún no recibe el medicamento. Autorizado por …')
                    ->helperText('Campo obligatorio. Mínimo '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres. Se guarda en observaciones de la coordinación, en la bitácora del caso y en la auditoría.')
                    ->required()
                    ->minLength(self::MINIMUM_OBSERVATION_LENGTH)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar el motivo de la reversión.',
                        'min' => 'El motivo debe tener al menos '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres.',
                    ]),
                Checkbox::make('reversal_authorized')
                    ->label('Confirmo que cuento con autorización para realizar esta reversión')
                    ->accepted()
                    ->validationMessages([
                        'accepted' => 'Debe confirmar que cuenta con autorización.',
                    ]),
            ])
            ->action(function (array $data, OperationCoordinationService $record) use ($itemId, $itemType, $title): void {
                self::reverse(
                    $record,
                    $itemType,
                    $itemId,
                    $title,
                    trim((string) ($data['reversal_observation'] ?? '')),
                    (bool) ($data['reversal_authorized'] ?? false),
                );
            });
    }

    /**
     * Revierte el ítem y devuelve true si lo hizo. Notifica al analista en cualquier caso.
     */
    public static function reverse(
        OperationCoordinationService $coordination,
        string $itemType,
        int $itemId,
        string $itemTitle,
        string $observationText,
        bool $authorizationConfirmed,
    ): bool {
        $observationText = trim($observationText);

        // Se valida aquí y no solo en el botón: ninguna vía reversa sin permiso.
        if (! self::userCanReverse()) {
            return self::fail('Sin permiso', 'Reversar ítems finalizados requiere el permiso «Reversar ítems finalizados»; un SUPERADMIN puede asignárselo en Permisos → Operaciones.');
        }

        if (! $authorizationConfirmed) {
            return self::fail('Falta la confirmación', 'Debe confirmar que cuenta con autorización para realizar esta reversión.');
        }

        if (mb_strlen($observationText) < self::MINIMUM_OBSERVATION_LENGTH) {
            return self::fail('Nota incompleta', 'El motivo de la reversión debe tener al menos '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres.');
        }

        $modelClass = CoordinationServiceItemExternalManagement::clinicalItemModelClass($itemType);

        if ($modelClass === null) {
            return self::fail('Ítem no válido', 'No se pudo identificar el tipo de ítem a reversar.');
        }

        $userId = Auth::id();
        $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

        try {
            /** @var array{target_status: string, coverage: string, coordination_reopened: bool}|string $outcome */
            $outcome = DB::transaction(function () use ($coordination, $modelClass, $itemType, $itemId, $itemTitle, $observationText, $userId, $userName): array|string {
                /** @var Model|null $item */
                $item = $modelClass::query()
                    ->where('operation_coordination_service_id', $coordination->getKey())
                    ->whereKey($itemId)
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    return 'El ítem ya no está asociado a esta coordinación.';
                }

                $currentStatus = mb_strtoupper(trim((string) ($item->getAttribute('status') ?? '')));

                if (! self::statusIsReversible($currentStatus)) {
                    return 'Solo se reversan ítems en estado FINALIZADO. Estado actual: '.($currentStatus !== '' ? $currentStatus : '—').'.';
                }

                $finalizedOrder = self::orderContainingItem($coordination, $itemType, $item, closedOnly: true);

                if ($finalizedOrder instanceof OperationServiceOrder) {
                    return 'Este ítem se finalizó con la orden de servicio '.($finalizedOrder->order_number ?: '#'.$finalizedOrder->id)
                        .'. Para reversarlo hay que reversar la orden: la orden, su factura y la salida de inventario seguirían cerradas.';
                }

                // Si el ítem sigue en una orden activa vuelve a EN GESTION para que esa orden lo cierre al finalizarse.
                $targetStatus = self::orderContainingItem($coordination, $itemType, $item, closedOnly: false) instanceof OperationServiceOrder
                    ? 'EN GESTION'
                    : 'PENDIENTE';
                $coverage = CoordinationServiceItemExternalManagement::coverageLabel(CoordinationServiceItemExternalManagement::itemCoverage($item));

                $item->update(['status' => $targetStatus]);

                $lockedCoordination = OperationCoordinationService::query()->whereKey($coordination->getKey())->lockForUpdate()->first() ?? $coordination;
                $coordinationReopened = mb_strtoupper(trim((string) $lockedCoordination->status)) === 'FINALIZADO';
                $bitacora = self::buildBitacoraDescription($itemTitle, $coverage, $targetStatus, $observationText, $userName, $coordinationReopened);

                $previousObservations = trim((string) ($lockedCoordination->observations ?? ''));
                $lockedCoordination->observations = $previousObservations !== '' ? $previousObservations."\n\n".$bitacora : $bitacora;
                $lockedCoordination->updated_by = $userName;

                if ($coordinationReopened) {
                    $lockedCoordination->status = 'EN GESTION';
                }

                $lockedCoordination->save();

                if (filled($lockedCoordination->telemedicine_case_id)) {
                    ObservationCase::query()->create([
                        'telemedicine_case_id' => $lockedCoordination->telemedicine_case_id,
                        'description' => $bitacora,
                        'created_by' => $userId !== null ? (string) $userId : $userName,
                    ]);
                }

                return [
                    'target_status' => $targetStatus,
                    'coverage' => $coverage,
                    'coordination_reopened' => $coordinationReopened,
                ];
            });
        } catch (Throwable $exception) {
            report($exception);

            return self::fail('No se reversó el ítem', 'Ocurrió un error y no se aplicó ningún cambio. Intente nuevamente; si persiste, avise a soporte.', 'danger');
        }

        if (is_string($outcome)) {
            return self::fail('No se puede reversar', $outcome);
        }

        SecurityAudit::log(self::AUDIT_REVERSED, self::AUDIT_ROUTE, [
            'operation_coordination_service_id' => $coordination->getKey(),
            'telemedicine_case_id' => $coordination->telemedicine_case_id,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'item' => $itemTitle,
            'coverage' => $outcome['coverage'],
            'previous_status' => self::REVERSIBLE_STATUS,
            'new_status' => $outcome['target_status'],
            'coordination_reopened' => $outcome['coordination_reopened'],
            'authorization_confirmed' => true,
            'note' => $observationText,
        ]);

        Notification::make()
            ->title('Ítem reversado')
            ->body('El ítem volvió a '.$outcome['target_status'].'.'
                .($outcome['coordination_reopened'] ? ' La coordinación se reabrió en EN GESTION.' : '')
                .' Quedó registrado en la bitácora y en la auditoría.')
            ->success()
            ->send();

        return true;
    }

    public static function buildBitacoraDescription(
        string $itemTitle,
        string $coverageLabel,
        string $targetStatus,
        string $observationText,
        string $userName,
        bool $coordinationReopened = false,
    ): string {
        return self::OBSERVATION_PREFIX."\n"
            .'Ítem: '.trim($itemTitle)."\n"
            .'Cobertura: '.$coverageLabel."\n"
            .'Estatus: FINALIZADO → '.$targetStatus."\n"
            .'Analista: '.$userName."\n"
            .'Autorización: confirmada por el analista'."\n"
            .'Motivo: '.trim($observationText)
            .($coordinationReopened ? "\n".'La coordinación se reabrió en EN GESTION.' : '');
    }

    /**
     * Orden de la coordinación que contiene el ítem: por vínculo directo (medicamentos) o
     * por categoría y nombre, como el resto del módulo.
     */
    public static function orderContainingItem(OperationCoordinationService $coordination, string $itemType, Model $item, bool $closedOnly): ?OperationServiceOrder
    {
        $name = mb_strtoupper(trim((string) ($item->getAttribute(self::itemNameAttribute($itemType)) ?? '')));
        $serviceType = self::serviceTypeFor($itemType);

        return OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $coordination->getKey())
            ->when(
                $closedOnly,
                fn ($query) => $query->where('status', 'FINALIZADO'),
                fn ($query) => $query->whereNotIn('status', self::CLOSED_ORDER_STATUSES),
            )
            ->whereHas('operationServiceOrderItems', function ($query) use ($itemType, $item, $name, $serviceType): void {
                $query->where(function ($match) use ($itemType, $item, $name, $serviceType): void {
                    if ($itemType === 'medication') {
                        $match->where('telemedicine_patient_medication_id', $item->getKey());
                    }

                    if ($name !== '') {
                        $match->orWhere(fn ($byName) => $byName
                            ->where('category', $serviceType)
                            ->where(DB::raw('UPPER(TRIM(item_name))'), $name));
                    }
                });
            })
            ->orderByDesc('id')
            ->first(['id', 'order_number', 'status']);
    }

    private static function fail(string $title, string $body, string $status = 'warning'): bool
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->status($status)
            ->persistent()
            ->send();

        return false;
    }

    private static function itemNameAttribute(string $itemType): string
    {
        return match ($itemType) {
            'medication' => 'medicine',
            'lab' => 'laboratory',
            'study' => 'study',
            default => 'specialty',
        };
    }

    private static function serviceTypeFor(string $itemType): string
    {
        return match ($itemType) {
            'medication' => 'MEDICAMENTOS',
            'lab' => 'LABORATORIOS',
            'study' => 'IMAGENOLOGIA',
            default => 'ESPECIALISTA',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Gestión externa de un ítem clínico asociado a una coordinación: el paciente lo
 * resolvió fuera de la red TDG. Exige nota obligatoria, deja el ítem en FINALIZADO
 * y registra la nota en la coordinación y en la bitácora del caso vinculado.
 *
 * Aplica a laboratorios, estudios, especialistas y medicamentos, cubiertos o no.
 * Un ítem CUBIERTO (o sin dato de cobertura) es un servicio que TDG paga: cerrarlo
 * por fuera exige el permiso «Gestión externa de ítems cubiertos» y admite un
 * soporte opcional que se guarda en los documentos de la coordinación.
 *
 * Nunca se gestiona por fuera un ítem que ya está en una orden activa o en una
 * cotización pendiente: la orden seguiría viva y, si es un medicamento del
 * inventario, al finalizarla descontaría stock de algo que no se entregó.
 */
final class CoordinationServiceItemExternalManagement
{
    public const OBSERVATION_PREFIX = 'Gestión externa de ítem asociado';

    public const FINAL_STATUS = 'FINALIZADO';

    public const SUPPORT_DOCUMENT_LABEL = 'SOPORTE DE GESTIÓN EXTERNA';

    public const AUDIT_COVERED = 'AUDIT_OPERATIONS_COVERED_ITEM_EXTERNAL_MANAGEMENT';

    public const AUDIT_ROUTE = 'operations.coordination-services.external-management';

    public const MINIMUM_OBSERVATION_LENGTH = 10;

    public const MAX_SUPPORT_FILE_KB = 10240;

    /**
     * @return list<string>
     */
    public static function manageableStatuses(): array
    {
        return ['PENDIENTE', 'EN GESTION'];
    }

    public static function statusIsManageable(string $status): bool
    {
        return in_array(mb_strtoupper(trim($status)), self::manageableStatuses(), true);
    }

    /**
     * Un NO CUBIERTO siempre puede gestionarse por fuera. Un cubierto, o uno sin dato
     * de cobertura (que quizá sea cubierto), exige el permiso granular.
     */
    public static function coverageAllowsExternalManagement(?bool $isCovered, ?bool $userCanManageCovered = null): bool
    {
        if ($isCovered === false) {
            return true;
        }

        return $userCanManageCovered ?? self::userCanManageCovered();
    }

    public static function userCanManageCovered(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EXTERNAL_MANAGE_COVERED_ITEMS);
    }

    public static function coverageLabel(?bool $isCovered): string
    {
        return match ($isCovered) {
            true => 'CUBIERTO',
            false => 'NO CUBIERTO',
            default => 'SIN DATO DE COBERTURA',
        };
    }

    /**
     * Cobertura leída del propio ítem (no de la pantalla), con la misma regla del detalle.
     */
    public static function itemCoverage(Model $item): ?bool
    {
        if ($item instanceof TelemedicinePatientMedications) {
            return TelemedicineMedicationCoverage::isCovered($item);
        }

        $type = trim((string) ($item->getAttribute('type') ?? ''));

        return $type === '' ? null : mb_strtoupper($type) === 'CUBIERTO';
    }

    /**
     * Motivo por el que el ítem no puede gestionarse por fuera porque otra gestión lo retiene:
     * una orden de servicio activa o una cotización pendiente / aprobada sin orden.
     * Se consulta en vivo, sin las cachés de la pantalla.
     */
    public static function blockingReason(OperationCoordinationService $coordination, string $itemType, Model $item): ?string
    {
        $order = CoordinationServiceItemFinalizationReversal::orderContainingItem($coordination, $itemType, $item, closedOnly: false);

        if ($order instanceof OperationServiceOrder) {
            return 'El ítem está en la orden de servicio '.($order->order_number ?: '#'.$order->id)
                .' (estatus '.$order->status.'). Anule o cancele la orden antes de gestionarlo externamente.';
        }

        $itemKey = $itemType.':'.$item->getKey();

        $quote = OperationQuoteGenerator::query()
            ->where('operation_coordination_service_id', $coordination->getKey())
            ->where(fn ($query) => $query
                ->where('status', OperationQuoteGenerator::STATUS_PENDING)
                ->orWhere(fn ($approved) => $approved
                    ->where('status', OperationQuoteGenerator::STATUS_APPROVED)
                    ->whereNull('operation_service_order_id')))
            ->orderByDesc('id')
            ->get(['id', 'status', 'items'])
            ->first(fn (OperationQuoteGenerator $quote): bool => collect(is_array($quote->items) ? $quote->items : [])
                ->contains(fn (mixed $line): bool => is_array($line) && (string) ($line['key'] ?? '') === $itemKey));

        if ($quote instanceof OperationQuoteGenerator) {
            return 'El ítem está en la cotización #'.$quote->id.' ('.$quote->status.'). Recházela o quítele el ítem antes de gestionarlo externamente.';
        }

        return null;
    }

    /**
     * @param  array{id?: int|string, item_type?: string, title?: string, status?: string, coverage?: bool|null, can_external_management?: bool}  $row
     */
    public static function makeExternalManagementAction(array $row): ?Action
    {
        if (! ($row['can_external_management'] ?? false)) {
            return null;
        }

        $itemId = (int) ($row['id'] ?? 0);
        $itemType = (string) ($row['item_type'] ?? '');
        $title = (string) ($row['title'] ?? 'Ítem');
        $coverage = array_key_exists('coverage', $row) ? $row['coverage'] : null;
        $isFreeOfCharge = $coverage === false;

        if ($itemId <= 0 || self::clinicalItemModelClass($itemType) === null) {
            return null;
        }

        return Action::make('externalManagementAssociatedItem_'.$itemType.'_'.$itemId)
            ->label('Gestión Externa')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('success')
            ->iconButton()
            ->tooltip('Gestión Externa')
            ->modalHeading('Gestión externa del ítem')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Se marcará como gestionado externamente <span class="font-semibold text-gray-900 dark:text-white">'.e($title).'</span> '
                .'(<span class="font-semibold '.($isFreeOfCharge ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300').'">'.e(self::coverageLabel($coverage)).'</span>) '
                .'y el ítem quedará en <span class="font-semibold text-gray-900 dark:text-white">FINALIZADO</span>. '
                .'Debe indicar la nota de gestión; quedará registrada en las observaciones de la coordinación y en la bitácora del caso vinculado.'
                .($isFreeOfCharge ? '' : ' Al ser un servicio cubierto, adjunte el soporte si lo tiene.')
                .'</p>'
            ))
            ->modalIcon(Heroicon::OutlinedDocumentCheck)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, marcar gestión externa')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->form([
                Textarea::make('external_management_observation')
                    ->label('Nota de la gestión externa')
                    ->placeholder('Ej.: El paciente realizó el estudio por su cuenta en otro centro, se recibió el informe por correo…')
                    ->helperText('Campo obligatorio. Mínimo '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres. Se guarda en observaciones de la coordinación y en la bitácora del caso.')
                    ->required()
                    ->minLength(self::MINIMUM_OBSERVATION_LENGTH)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar la nota de la gestión externa.',
                        'minLength' => 'La nota debe tener al menos '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres.',
                    ]),
                FileUpload::make('external_management_support')
                    ->label('Soporte (opcional)')
                    ->helperText('Informe, factura o constancia de la gestión externa. PDF o imagen, máximo 10 MB. Se guarda en los documentos de la coordinación.')
                    ->disk('public')
                    ->directory(fn (?OperationCoordinationService $record): string => 'operation-coordination-services/'.($record?->id ?? 'tmp').'/documents')
                    ->storeFileNamesIn('external_management_support_name')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(self::MAX_SUPPORT_FILE_KB)
                    ->visible(! $isFreeOfCharge)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, OperationCoordinationService $record) use ($itemId, $itemType, $title): void {
                self::markAsExternallyManaged(
                    $record,
                    $itemType,
                    $itemId,
                    $title,
                    trim((string) ($data['external_management_observation'] ?? '')),
                    self::firstUploadedPath($data['external_management_support'] ?? null),
                    self::firstUploadedPath($data['external_management_support_name'] ?? null),
                );
            });
    }

    public static function markAsExternallyManaged(
        OperationCoordinationService $coordination,
        string $itemType,
        int $itemId,
        string $itemTitle,
        string $observationText,
        ?string $supportPath = null,
        ?string $supportOriginalName = null,
    ): void {
        $observationText = trim($observationText);

        if (mb_strlen($observationText) < self::MINIMUM_OBSERVATION_LENGTH) {
            self::discardSupport($supportPath);

            Notification::make()
                ->title('Nota incompleta')
                ->body('La nota de la gestión externa debe tener al menos '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres.')
                ->danger()
                ->send();

            return;
        }

        $modelClass = self::clinicalItemModelClass($itemType);

        if ($modelClass === null) {
            self::discardSupport($supportPath);

            Notification::make()
                ->title('Ítem no válido')
                ->body('No se pudo identificar el tipo de ítem a gestionar.')
                ->danger()
                ->send();

            return;
        }

        /** @var Model|null $item */
        $item = $modelClass::query()
            ->where('operation_coordination_service_id', $coordination->id)
            ->whereKey($itemId)
            ->first();

        if ($item === null) {
            self::discardSupport($supportPath);

            Notification::make()
                ->title('Ítem no encontrado')
                ->body('El ítem ya no está asociado a esta coordinación.')
                ->danger()
                ->send();

            return;
        }

        $currentStatus = mb_strtoupper(trim((string) ($item->getAttribute('status') ?? '')));

        if (! self::statusIsManageable($currentStatus)) {
            self::discardSupport($supportPath);

            Notification::make()
                ->title('No se puede marcar gestión externa')
                ->body('Solo se pueden gestionar externamente ítems en estado PENDIENTE o EN GESTION. Estado actual: '.$currentStatus.'.')
                ->warning()
                ->send();

            return;
        }

        $coverage = self::itemCoverage($item);

        // El permiso se exige aquí, no solo en el botón: ninguna vía cierra un cubierto sin él.
        if ($coverage !== false && ! self::userCanManageCovered()) {
            self::discardSupport($supportPath);

            Notification::make()
                ->title('Sin permiso')
                ->body(($coverage === true ? 'Este ítem es cubierto' : 'Este ítem no tiene dato de cobertura').'. Gestionarlo externamente requiere el permiso «Gestión externa de ítems cubiertos»; un SUPERADMIN puede asignárselo en Permisos → Operaciones.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $blockingReason = self::blockingReason($coordination, $itemType, $item);

        if ($blockingReason !== null) {
            self::discardSupport($supportPath);

            Notification::make()
                ->title('No se puede marcar gestión externa')
                ->body($blockingReason)
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $supportPath = $coverage !== false ? $supportPath : null;
        $bitacoraDescription = self::buildBitacoraDescription($itemTitle, $observationText, self::coverageLabel($coverage), $supportPath !== null);
        $userId = Auth::id();
        $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

        try {
            DB::transaction(function () use ($coordination, $item, $bitacoraDescription, $userId, $userName, $supportPath, $supportOriginalName, $itemType, $itemTitle): void {
                $item->update(['status' => self::FINAL_STATUS]);

                if ($supportPath !== null) {
                    $existing = is_array($coordination->uploaded_documents) ? $coordination->uploaded_documents : [];
                    $name = trim((string) pathinfo($supportOriginalName ?: $supportPath, PATHINFO_FILENAME));

                    $coordination->uploaded_documents = array_values(array_merge($existing, [[
                        'document_name' => $name !== '' ? $name : basename($supportPath),
                        'file_path' => $supportPath,
                        'document_type_ids' => [],
                        'document_types' => [self::SUPPORT_DOCUMENT_LABEL],
                        'service_item_keys' => [$itemType.':'.$item->getKey()],
                        'services' => [trim($itemTitle)],
                        'service' => trim($itemTitle),
                        'uploaded_at' => now()->toDateTimeString(),
                    ]]));
                }

                $previousObservations = trim((string) ($coordination->observations ?? ''));
                $coordination->observations = $previousObservations !== ''
                    ? $previousObservations."\n\n".$bitacoraDescription
                    : $bitacoraDescription;
                $coordination->updated_by = $userName;
                $coordination->save();

                if (filled($coordination->telemedicine_case_id)) {
                    ObservationCase::query()->create([
                        'telemedicine_case_id' => $coordination->telemedicine_case_id,
                        'description' => $bitacoraDescription,
                        'created_by' => $userId !== null ? (string) $userId : null,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            self::discardSupport($supportPath);
            report($exception);

            Notification::make()
                ->title('No se registró la gestión externa')
                ->body('Ocurrió un error y el ítem no cambió. Intente nuevamente; si persiste, avise a soporte.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($coverage !== false) {
            SecurityAudit::log(self::AUDIT_COVERED, self::AUDIT_ROUTE, [
                'operation_coordination_service_id' => $coordination->getKey(),
                'telemedicine_case_id' => $coordination->telemedicine_case_id,
                'item_type' => $itemType,
                'item_id' => $item->getKey(),
                'item' => $itemTitle,
                'coverage' => self::coverageLabel($coverage),
                'support' => $supportPath,
            ]);
        }

        Notification::make()
            ->title('Gestión externa registrada')
            ->body(
                filled($coordination->telemedicine_case_id)
                    ? 'El ítem quedó en FINALIZADO. La nota se registró en la coordinación y en la bitácora del caso.'
                    : 'El ítem quedó en FINALIZADO. La nota se registró en la coordinación.'
            )
            ->success()
            ->send();
    }

    public static function buildBitacoraDescription(string $itemTitle, string $observationText, ?string $coverageLabel = null, bool $hasSupport = false): string
    {
        return self::OBSERVATION_PREFIX."\n"
            .'Ítem: '.trim($itemTitle)."\n"
            .($coverageLabel !== null ? 'Cobertura: '.$coverageLabel."\n" : '')
            .'Nota: '.trim($observationText)
            .($hasSupport ? "\n".'Soporte: adjunto en documentos de la coordinación' : '');
    }

    private static function firstUploadedPath(mixed $value): ?string
    {
        $value = is_array($value) ? (reset($value) ?: null) : $value;
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function discardSupport(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'operation-coordination-services/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return class-string<Model>|null
     */
    public static function clinicalItemModelClass(string $type): ?string
    {
        return match ($type) {
            'medication' => TelemedicinePatientMedications::class,
            'lab' => TelemedicinePatientLab::class,
            'study' => TelemedicinePatientStudy::class,
            'specialty' => TelemedicinePatientSpecialty::class,
            default => null,
        };
    }
}

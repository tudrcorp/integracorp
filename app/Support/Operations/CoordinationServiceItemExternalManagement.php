<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Gestión externa de un ítem clínico NO CUBIERTO asociado a una coordinación:
 * el paciente lo resolvió fuera de la red TDG. Exige nota obligatoria, deja el
 * ítem en FINALIZADO y registra la nota en la coordinación y en la bitácora
 * del caso vinculado.
 *
 * Los ítems CUBIERTOS quedan fuera a propósito: esos se finalizan cargando su
 * comprobante de entrega con CoordinationServiceCoveredItemsFinalizer, que es
 * el soporte que la operación exige para un servicio que TDG sí pagó.
 */
final class CoordinationServiceItemExternalManagement
{
    public const OBSERVATION_PREFIX = 'Gestión externa de ítem asociado';

    public const FINAL_STATUS = 'FINALIZADO';

    public const MINIMUM_OBSERVATION_LENGTH = 10;

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
     * Solo aplica a ítems explícitamente NO CUBIERTOS. Un `null` es «sin dato»
     * de cobertura, y sin dato no se habilita: finalizar un ítem que quizá sea
     * cubierto saltaría el comprobante de entrega.
     */
    public static function coverageAllowsExternalManagement(?bool $isCovered): bool
    {
        return $isCovered === false;
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
                .'y el ítem quedará en <span class="font-semibold text-gray-900 dark:text-white">FINALIZADO</span>. '
                .'Debe indicar la nota de gestión; quedará registrada en las observaciones de la coordinación y en la bitácora del caso vinculado.'
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
            ])
            ->action(function (array $data, OperationCoordinationService $record) use ($itemId, $itemType, $title): void {
                self::markAsExternallyManaged(
                    $record,
                    $itemType,
                    $itemId,
                    $title,
                    trim((string) ($data['external_management_observation'] ?? '')),
                );
            });
    }

    public static function markAsExternallyManaged(
        OperationCoordinationService $coordination,
        string $itemType,
        int $itemId,
        string $itemTitle,
        string $observationText,
    ): void {
        $observationText = trim($observationText);

        if (mb_strlen($observationText) < self::MINIMUM_OBSERVATION_LENGTH) {
            Notification::make()
                ->title('Nota incompleta')
                ->body('La nota de la gestión externa debe tener al menos '.self::MINIMUM_OBSERVATION_LENGTH.' caracteres.')
                ->danger()
                ->send();

            return;
        }

        $modelClass = self::clinicalItemModelClass($itemType);

        if ($modelClass === null) {
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
            Notification::make()
                ->title('Ítem no encontrado')
                ->body('El ítem ya no está asociado a esta coordinación.')
                ->danger()
                ->send();

            return;
        }

        $currentStatus = mb_strtoupper(trim((string) ($item->getAttribute('status') ?? '')));

        if (! self::statusIsManageable($currentStatus)) {
            Notification::make()
                ->title('No se puede marcar gestión externa')
                ->body('Solo se pueden gestionar externamente ítems en estado PENDIENTE o EN GESTION. Estado actual: '.$currentStatus.'.')
                ->warning()
                ->send();

            return;
        }

        $bitacoraDescription = self::buildBitacoraDescription($itemTitle, $observationText);
        $userId = Auth::id();
        $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

        DB::transaction(function () use ($coordination, $item, $bitacoraDescription, $userId, $userName): void {
            $item->update(['status' => self::FINAL_STATUS]);

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

    public static function buildBitacoraDescription(string $itemTitle, string $observationText): string
    {
        return self::OBSERVATION_PREFIX."\n"
            .'Ítem: '.trim($itemTitle)."\n"
            .'Nota: '.trim($observationText);
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

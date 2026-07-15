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
 * Cancelación de gestión de un ítem clínico asociado a una coordinación,
 * con observación obligatoria en la coordinación y en la bitácora del caso.
 */
final class CoordinationServiceItemCancellation
{
    public const OBSERVATION_PREFIX = 'Cancelación de gestión de ítem asociado';

    /**
     * @return list<string>
     */
    public static function cancellableStatuses(): array
    {
        return ['PENDIENTE', 'EN GESTION'];
    }

    public static function statusIsCancellable(string $status): bool
    {
        return in_array(mb_strtoupper(trim($status)), self::cancellableStatuses(), true);
    }

    /**
     * @param  array{id?: int|string, item_type?: string, title?: string, status?: string, can_cancel?: bool}  $row
     */
    public static function makeCancelAction(array $row): ?Action
    {
        if (! ($row['can_cancel'] ?? false)) {
            return null;
        }

        $itemId = (int) ($row['id'] ?? 0);
        $itemType = (string) ($row['item_type'] ?? '');
        $title = (string) ($row['title'] ?? 'Ítem');

        if ($itemId <= 0 || self::clinicalItemModelClass($itemType) === null) {
            return null;
        }

        return Action::make('cancelAssociatedItemManagement_'.$itemType.'_'.$itemId)
            ->label('Cancelar gestión')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->iconButton()
            ->tooltip('Cancelar gestión')
            ->modalHeading('Cancelar gestión del ítem')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Se cancelará la gestión de <span class="font-semibold text-gray-900 dark:text-white">'.e($title).'</span>. '
                .'Debe indicar el motivo; quedará registrado en las observaciones de la coordinación y en la bitácora del caso vinculado.'
                .'</p>'
            ))
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, cancelar gestión')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->form([
                Textarea::make('cancellation_observation')
                    ->label('Motivo de la cancelación')
                    ->placeholder('Ej.: El paciente desistió del estudio, duplicado por error, ya no aplica por indicación médica…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Se guarda en observaciones de la coordinación y en la bitácora del caso.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar el motivo de la cancelación.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (array $data, OperationCoordinationService $record) use ($itemId, $itemType, $title): void {
                self::cancel(
                    $record,
                    $itemType,
                    $itemId,
                    $title,
                    trim((string) ($data['cancellation_observation'] ?? '')),
                );
            });
    }

    public static function cancel(
        OperationCoordinationService $coordination,
        string $itemType,
        int $itemId,
        string $itemTitle,
        string $observationText,
    ): void {
        $observationText = trim($observationText);

        if (mb_strlen($observationText) < 10) {
            Notification::make()
                ->title('Motivo incompleto')
                ->body('El motivo de la cancelación debe tener al menos 10 caracteres.')
                ->danger()
                ->send();

            return;
        }

        $modelClass = self::clinicalItemModelClass($itemType);

        if ($modelClass === null) {
            Notification::make()
                ->title('Ítem no válido')
                ->body('No se pudo identificar el tipo de ítem a cancelar.')
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

        if (! self::statusIsCancellable($currentStatus)) {
            Notification::make()
                ->title('No se puede cancelar')
                ->body('Solo se pueden cancelar ítems en estado PENDIENTE o EN GESTION. Estado actual: '.$currentStatus.'.')
                ->warning()
                ->send();

            return;
        }

        $bitacoraDescription = self::buildBitacoraDescription($itemTitle, $observationText);
        $userId = Auth::id();
        $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

        DB::transaction(function () use ($coordination, $item, $bitacoraDescription, $userId, $userName): void {
            $item->update(['status' => 'CANCELADA']);

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
            ->title('Gestión cancelada')
            ->body(
                filled($coordination->telemedicine_case_id)
                    ? 'El ítem quedó en CANCELADA. La observación se registró en la coordinación y en la bitácora del caso.'
                    : 'El ítem quedó en CANCELADA. La observación se registró en la coordinación.'
            )
            ->success()
            ->send();
    }

    public static function buildBitacoraDescription(string $itemTitle, string $observationText): string
    {
        return self::OBSERVATION_PREFIX."\n"
            .'Ítem: '.trim($itemTitle)."\n"
            .'Motivo: '.trim($observationText);
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

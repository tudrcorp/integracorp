<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineGeneralServices\Actions;

use App\Models\TelemedicineGeneralService;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

final class DeleteTelemedicineGeneralServiceAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Eliminar servicio general')
            ->modalDescription('El servicio se eliminará del catálogo de Consulta General. Debe indicar el motivo; quedará registrado en las trazas de seguridad.')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(
                fn (DeleteAction $action) => $action
                    ->color('danger')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                    ])
            )
            ->modalCancelAction(
                fn (DeleteAction $action) => $action
                    ->color('gray')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->form([
                Textarea::make('deletion_reason')
                    ->label('Motivo de eliminación')
                    ->placeholder('Explique por qué se elimina este servicio general…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Quedará en los logs de seguridad del sistema.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo de la eliminación.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->using(function (TelemedicineGeneralService $record, array $data): bool {
                SecurityAudit::log(
                    'AUDIT_OPERATIONS_TELEMEDICINE_GENERAL_SERVICE_DELETED',
                    'operations.telemedicine-general-services.delete',
                    [
                        'telemedicine_general_service_id' => $record->id,
                        'name' => $record->name,
                        'description' => $record->description,
                        'status' => $record->status,
                        'deletion_reason' => (string) ($data['deletion_reason'] ?? ''),
                        'deleted_by' => Auth::id(),
                    ],
                );

                return (bool) $record->delete();
            });
    }
}

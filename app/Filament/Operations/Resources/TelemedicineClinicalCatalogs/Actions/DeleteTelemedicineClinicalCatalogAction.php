<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineClinicalCatalogs\Actions;

use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class DeleteTelemedicineClinicalCatalogAction
{
    public static function make(
        string $entityLabel,
        string $auditEvent,
        string $auditAction,
    ): DeleteAction {
        return DeleteAction::make()
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Eliminar '.$entityLabel)
            ->modalDescription('Se quitará del catálogo de la consulta médica. Debe indicar el motivo; quedará en las trazas de seguridad.')
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
                    ->placeholder('Explique por qué se elimina este registro…')
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
            ->using(function (Model $record, array $data) use ($auditEvent, $auditAction): bool {
                SecurityAudit::log(
                    $auditEvent,
                    $auditAction,
                    [
                        'record_id' => $record->getKey(),
                        'name' => $record->getAttribute('name'),
                        'type' => $record->getAttribute('type'),
                        'deletion_reason' => (string) ($data['deletion_reason'] ?? ''),
                        'deleted_by' => Auth::id(),
                    ],
                );

                return (bool) $record->delete();
            });
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts\Actions;

use App\Models\PortalHelpContact;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

final class DeletePortalHelpContactAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Eliminar contacto de ayuda')
            ->modalDescription('El contacto dejará de aparecer en la ayuda del portal del paciente. Debe indicar el motivo; quedará registrado en las trazas de seguridad.')
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
                    ->placeholder('Explique por qué se elimina este contacto de ayuda…')
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
            ->using(function (PortalHelpContact $record, array $data): bool {
                SecurityAudit::log(
                    'AUDIT_OPERATIONS_PORTAL_HELP_CONTACT_DELETED',
                    'operations.portal-help-contacts.delete',
                    [
                        'portal_help_contact_id' => $record->id,
                        'name' => $record->name,
                        'phone' => $record->phone,
                        'sort_order' => $record->sort_order,
                        'status' => $record->status,
                        'deletion_reason' => (string) ($data['deletion_reason'] ?? ''),
                        'deleted_by' => Auth::id(),
                    ],
                );

                return (bool) $record->delete();
            });
    }
}

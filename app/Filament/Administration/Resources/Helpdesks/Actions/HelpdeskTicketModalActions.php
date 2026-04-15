<?php

namespace App\Filament\Administration\Resources\Helpdesks\Actions;

use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskObservationAppender;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

final class HelpdeskTicketModalActions
{
    public const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    public const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function currentUserIsTicketAssignee(HelpDesk $record): bool
    {
        $colaborador = RrhhColaborador::query()
            ->where('user_id', Auth::id())
            ->first();

        if ($colaborador === null) {
            return false;
        }

        $record->loadMissing('rrhhColaboradores');

        return $record->rrhhColaboradores->contains(
            fn (RrhhColaborador $c): bool => (int) $c->getKey() === (int) $colaborador->getKey()
        );
    }

    /**
     * Quien está asignado al ticket solo puede añadir notas cuando el estado es «EN PROCESO».
     * El creador y demás usuarios no asignados no quedan sujetos a esta restricción (salvo ticket cerrado).
     */
    public static function shouldHideAddNoteAction(HelpDesk $record): bool
    {
        if (in_array($record->status, ['TERMINADO', 'CANCELADO'], true)) {
            return true;
        }

        if (self::currentUserIsTicketAssignee($record)) {
            return $record->status !== 'EN PROCESO';
        }

        return false;
    }

    /**
     * @return bool true si puede continuar; false si se mostró aviso y debe abortarse el guardado
     */
    public static function assertMayAddNote(HelpDesk $record): bool
    {
        if (self::currentUserIsTicketAssignee($record) && $record->status !== 'EN PROCESO') {
            Notification::make()
                ->title('Estado requerido')
                ->body('Actualiza el ticket a «En proceso» para poder añadir notas.')
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    public static function makeAddNoteAction(): Action
    {
        return Action::make('addNote')
            ->label('Añadir nota')
            ->icon('heroicon-m-plus-circle')
            ->color('success')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Añadir nota al ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Seguimiento interno · Ticket #'.$record->getKey().' · '.$record->created_by)
            ->modalSubmitActionLabel('Guardar nota')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => self::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->form([
                Section::make('Nueva entrada')
                    ->description('Formato enriquecido: negritas, resaltado, colores, títulos y listas. Los emojis se pueden insertar con el teclado (p. ej. en macOS: Ctrl+Cmd+Espacio). El contenido se añade al historial con fecha y tu nombre.')
                    ->icon('heroicon-m-pencil-square')
                    ->schema([
                        RichEditor::make('note')
                            ->label('Nota')
                            ->placeholder('Describe el avance, acuerdos o el siguiente paso…')
                            ->required()
                            ->fileAttachments(false)
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'highlight', 'textColor'],
                                ['h1', 'h2', 'h3'],
                                ['alignStart', 'alignCenter', 'alignEnd'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['link'],
                                ['undo', 'redo'],
                            ])
                            ->extraInputAttributes([
                                'class' => 'min-h-[12rem]',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),
            ])
            ->successNotification(null)
            ->action(function (HelpDesk $record, array $data): void {
                $user = Auth::user();
                if ($user === null) {
                    return;
                }

                if (! self::assertMayAddNote($record)) {
                    return;
                }

                $noteHtml = (string) ($data['note'] ?? '');
                $plainLength = mb_strlen(trim(html_entity_decode(strip_tags($noteHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($plainLength < 3) {
                    Notification::make()
                        ->title('Nota demasiado corta')
                        ->body('Escribe al menos 3 caracteres de contenido (sin contar solo formato vacío).')
                        ->warning()
                        ->send();

                    return;
                }

                HelpdeskObservationAppender::append($record, $noteHtml, $user->name);

                Notification::make()
                    ->title('Nota guardada')
                    ->body('Se añadió la nota al ticket #'.$record->getKey().'.')
                    ->success()
                    ->send();
            })
            ->hidden(fn (HelpDesk $record): bool => self::shouldHideAddNoteAction($record));
    }

    public static function makeUpdateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Actualizar estado')
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Actualizar estado del ticket')
            ->modalDescription(fn (HelpDesk $record): string => 'Ticket #'.$record->getKey().' · '.$record->created_by)
            ->modalSubmitActionLabel('Guardar estado')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => self::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->fillForm(fn (HelpDesk $record): array => [
                'status' => $record->status,
            ])
            ->form(function (HelpDesk $record): array {
                return [
                    Section::make('Estado')
                        ->description('Solo quien creó el ticket puede marcarlo como Terminado o Cancelado.')
                        ->icon('heroicon-m-flag')
                        ->schema([
                            Select::make('status')
                                ->label('Estado')
                                ->prefixIcon('heroicon-m-flag')
                                ->options(HelpdeskTaskStatusOptions::forSelect($record, Auth::user()?->name))
                                ->required()
                                ->native(true)
                                ->extraInputAttributes([
                                    'class' => 'helpdesk-status-native-select w-full max-w-full min-h-11 text-base sm:text-sm',
                                ]),
                        ])
                        ->columns(1)
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => self::IOS_SECTION_CLASS,
                        ]),
                ];
            })
            ->successNotification(null)
            ->action(function (HelpDesk $record, array $data): void {
                $user = Auth::user();
                if ($user === null) {
                    return;
                }

                $newStatus = (string) ($data['status'] ?? $record->status);
                $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, $newStatus, $user->name);

                if ($sanitized === $record->status) {
                    Notification::make()
                        ->title('Sin cambios')
                        ->body('El estado del ticket no se modificó.')
                        ->info()
                        ->send();

                    return;
                }

                $record->status = $sanitized;
                $record->updated_by = $user->name;
                $record->save();

                Notification::make()
                    ->title('Estado actualizado')
                    ->body('El ticket #'.$record->getKey().' quedó en: '.(HelpdeskTaskStatusOptions::all()[$sanitized] ?? $sanitized).'.')
                    ->success()
                    ->send();
            })
            ->hidden(fn (HelpDesk $record): bool => $record->status === 'TERMINADO');
    }
}

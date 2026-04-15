<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TdevReports\Actions;

use App\Models\TdevReport;
use App\Support\TdevReportProcessObservationAppender;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

final class TdevReportProcessNotesModalActions
{
    public static function makeAddProcessObservationAction(): Action
    {
        return Action::make('addProcessObservationTdev')
            ->label('Añadir observación de proceso')
            ->icon('heroicon-m-plus-circle')
            ->color('success')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Añadir observación de proceso')
            ->modalDescription(fn (TdevReport $record): string => 'Seguimiento interno · Voucher '.$record->vaucher.' · '.$record->pasajero)
            ->modalSubmitActionLabel('Guardar observación')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => TdevReportPaymentModalActions::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => TdevReportPaymentModalActions::IOS_GRAY_BTN,
                    ])
            )
            ->form([
                Section::make('Nueva entrada')
                    ->description('Formato enriquecido: negritas, resaltado, colores, títulos y listas. El contenido se añade al historial con fecha y tu nombre.')
                    ->icon('heroicon-m-pencil-square')
                    ->schema([
                        RichEditor::make('note')
                            ->label('Observación')
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
                        'class' => TdevReportPaymentModalActions::IOS_SECTION_CLASS,
                    ]),
            ])
            ->successNotification(null)
            ->action(function (TdevReport $record, array $data): void {
                $user = Auth::user();
                if ($user === null) {
                    return;
                }

                $noteHtml = (string) ($data['note'] ?? '');
                $plainLength = mb_strlen(trim(html_entity_decode(strip_tags($noteHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($plainLength < 3) {
                    Notification::make()
                        ->title('Observación demasiado corta')
                        ->body('Escribe al menos 3 caracteres de contenido (sin contar solo formato vacío).')
                        ->warning()
                        ->send();

                    return;
                }

                TdevReportProcessObservationAppender::append($record, $noteHtml, $user->name);

                Notification::make()
                    ->title('Observación guardada')
                    ->body('Se añadió la observación al voucher '.$record->vaucher.'.')
                    ->success()
                    ->send();
            });
    }

    public static function makeViewProcessObservationsAction(): Action
    {
        return Action::make('viewProcessObservationsTdev')
            ->label('Ver observaciones de proceso')
            ->icon('heroicon-m-clipboard-document-list')
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Observaciones de proceso')
            ->modalDescription(fn (TdevReport $record): string => 'Seguimiento interno · Voucher '.$record->vaucher)
            ->modalContent(function (TdevReport $record) {
                $updated = $record->updated_at
                    ? Carbon::parse($record->updated_at)->timezone(config('app.timezone'))
                    : now()->timezone(config('app.timezone'));

                $daysElapsed = (int) $updated->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay());

                return view('filament.administration.tdev-reports.process-notes-modal', [
                    'record' => $record,
                    'observation' => $record->observaciones_proceso,
                    'updatedAtFormatted' => $updated->format('d/m/Y H:i'),
                    'updatedRelative' => $updated->diffForHumans(),
                    'daysElapsed' => $daysElapsed,
                    'updatedBy' => null,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(
                Action::make('dismissProcessObservationsTdev')
                    ->label('Listo')
                    ->extraAttributes([
                        'class' => TdevReportPaymentModalActions::IOS_SUCCESS_BTN,
                    ]),
            )
            ->action(fn (): null => null);
    }
}

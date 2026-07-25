<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Actions;

use App\Models\TelemedicineCase;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

final class RegenerateTelemedicineCaseDocumentsAction
{
    public static function make(?callable $beforeAction = null): Action
    {
        return Action::make('regenerate_case_documents')
            ->label('Generar documentos')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('info')
            ->modalHeading('Generar documentos de la consulta')
            ->modalDescription('Seleccione uno o varios documentos para volver a generarlos a partir de la información registrada en el caso.')
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalIconColor('info')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Generar seleccionados')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(
                fn (Action $action) => $action
                    ->color('info')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action) => $action
                    ->color('gray')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->visible(function (?TelemedicineCase $record): bool {
                if ($record === null) {
                    return false;
                }

                return app(TelemedicineCaseDocumentRegenerationService::class)
                    ->availableOptions($record) !== [];
            })
            ->fillForm(function (TelemedicineCase $record): array {
                $options = app(TelemedicineCaseDocumentRegenerationService::class)->availableOptions($record);

                return [
                    'documents' => array_keys($options),
                ];
            })
            ->form(function (TelemedicineCase $record): array {
                $options = app(TelemedicineCaseDocumentRegenerationService::class)->availableOptions($record);

                if ($options === []) {
                    return [
                        Placeholder::make('no_documents')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">Este caso aún no tiene consultas ni datos suficientes para regenerar documentos.</p>'
                            )),
                    ];
                }

                return [
                    CheckboxList::make('documents')
                        ->label('Documentos a generar')
                        ->helperText('Puede seleccionar uno o varios. Se regenerarán en segundo plano y quedarán disponibles para descarga.')
                        ->options($options)
                        ->required()
                        ->columns(1)
                        ->bulkToggleable()
                        ->validationMessages([
                            'required' => 'Debe seleccionar al menos un documento.',
                        ]),
                ];
            })
            ->action(function (TelemedicineCase $record, array $data) use ($beforeAction): void {
                if ($beforeAction !== null && $beforeAction($record) === false) {
                    return;
                }

                $user = Auth::user();

                if ($user === null) {
                    Notification::make()
                        ->title('Sesión inválida')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $selected = app(TelemedicineCaseDocumentRegenerationService::class)->regenerate(
                        $record,
                        array_values(array_filter((array) ($data['documents'] ?? []))),
                        $user,
                    );

                    $count = count($selected);

                    Notification::make()
                        ->title($count === 1 ? 'Documento en generación' : 'Documentos en generación')
                        ->body($count === 1
                            ? 'El documento seleccionado se está regenerando. Recibirá una notificación al finalizar.'
                            : "Se están regenerando {$count} documentos. Recibirá una notificación al finalizar cada uno.")
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Log::error('RegenerateTelemedicineCaseDocumentsAction: error', [
                        'telemedicine_case_id' => $record->id,
                        'message' => $exception->getMessage(),
                    ]);

                    Notification::make()
                        ->title('No se pudieron generar los documentos')
                        ->body($exception->getMessage() !== ''
                            ? $exception->getMessage()
                            : 'Ocurrió un error al regenerar los documentos. Intente de nuevo.')
                        ->danger()
                        ->send();
                }
            });
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Actions;

use App\Models\TelemedicineCase;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineCaseDocumentReadyNotification;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationResult;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Component;
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
            ->modalDescription('Elija la consulta y los documentos que desea volver a generar con la información registrada en ella. Se generan de inmediato, sin depender de la cola de documentos.')
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
                    ->caseHasConsultations($record);
            })
            ->fillForm(function (TelemedicineCase $record): array {
                $service = app(TelemedicineCaseDocumentRegenerationService::class);
                $consultationId = $service->defaultConsultationId($record);
                $consultation = $service->consultationOf($record, $consultationId);

                return [
                    'consultation_id' => $consultationId,
                    'documents' => $consultation !== null
                        ? array_keys($service->availableOptions($consultation))
                        : [],
                ];
            })
            ->form(function (TelemedicineCase $record): array {
                $service = app(TelemedicineCaseDocumentRegenerationService::class);
                $consultationOptions = $service->consultationOptions($record);
                $withoutDoctor = $service->consultationIdsWithoutDoctor($record);

                if ($consultationOptions === [] || count($withoutDoctor) === count($consultationOptions)) {
                    return [
                        Placeholder::make('no_documents')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                                .e($consultationOptions === []
                                    ? 'Este caso aún no tiene consultas ni datos suficientes para regenerar documentos.'
                                    : TelemedicineCaseDocumentRegenerationService::MISSING_DOCTOR_MESSAGE)
                                .'</p>'
                            )),
                    ];
                }

                return [
                    Select::make('consultation_id')
                        ->label('Consulta')
                        ->helperText('Cada consulta genera sus propios documentos, con la firma del médico que la atendió y solo lo que se indicó en ella.')
                        ->options($consultationOptions)
                        ->disableOptionWhen(fn (mixed $value): bool => in_array((int) $value, $withoutDoctor, true))
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set) use ($service, $record): void {
                            $consultation = $service->consultationOf($record, $state);

                            $set('documents', $consultation !== null
                                ? array_keys($service->availableOptions($consultation))
                                : []);
                        })
                        ->validationMessages([
                            'required' => 'Seleccione la consulta cuyos documentos desea generar.',
                        ]),
                    Placeholder::make('consultations_without_doctor')
                        ->hiddenLabel()
                        ->visible($withoutDoctor !== [])
                        ->content(new HtmlString(
                            '<p class="text-sm text-warning-600 dark:text-warning-400">'
                            .e(count($withoutDoctor) === 1
                                ? 'Una consulta de este caso no tiene médico registrado y no se puede regenerar: saldría con la firma de otro médico. Reporte el caso a soporte para corregirla.'
                                : count($withoutDoctor).' consultas de este caso no tienen médico registrado y no se pueden regenerar: saldrían con la firma de otro médico. Reporte el caso a soporte para corregirlas.')
                            .'</p>'
                        )),
                    CheckboxList::make('documents')
                        ->label('Documentos a generar')
                        ->helperText('Puede seleccionar uno o varios. Se generan en el momento, sin pasar por la cola: espere unos segundos sin cerrar la ventana.')
                        ->options(function (Get $get) use ($service, $record): array {
                            $consultation = $service->consultationOf($record, $get('consultation_id'));

                            return $consultation !== null ? $service->availableOptions($consultation) : [];
                        })
                        ->visible(fn (Get $get): bool => filled($get('consultation_id')))
                        ->required()
                        ->columns(1)
                        ->bulkToggleable()
                        ->validationMessages([
                            'required' => 'Debe seleccionar al menos un documento.',
                        ]),
                ];
            })
            ->action(function (TelemedicineCase $record, array $data, Component $livewire) use ($beforeAction): void {
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
                    $result = app(TelemedicineCaseDocumentRegenerationService::class)->regenerate(
                        $record,
                        isset($data['consultation_id']) ? (int) $data['consultation_id'] : null,
                        array_values(array_filter((array) ($data['documents'] ?? []))),
                        $user,
                    );

                    self::notifyResult($result);

                    // Lo generado se consulta en el expediente del caso: se lleva
                    // allí al médico en vez de dejarlo en la tabla buscándolo.
                    // Si no salió ningún documento no se redirige, para que el
                    // aviso de error se lea donde está.
                    $redirectUrl = self::caseDocumentsTabUrl($record);

                    if (! $result->noneGenerated() && filled($redirectUrl)) {
                        $livewire->redirect($redirectUrl);
                    }
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

    /**
     * Ficha del caso abierta en la pestaña de documentos.
     *
     * Reutiliza el enlace que ya usan las notificaciones de documento listo:
     * la pestaña se selecciona con el valor `expediente-documental::tab`, no con
     * el slug a secas.
     */
    public static function caseDocumentsTabUrl(TelemedicineCase $record): ?string
    {
        return TelemedicineCaseDocumentReadyNotification::caseExpedienteDocumentalUrl([
            'telemedicine_case_id' => $record->id,
        ]);
    }

    /**
     * Un plan B silencioso no sirve: el médico tiene que saber exactamente qué
     * documento quedó fuera para poder reintentarlo o escalarlo.
     */
    private static function notifyResult(TelemedicineCaseDocumentRegenerationResult $result): void
    {
        if ($result->noneGenerated()) {
            Notification::make()
                ->title('No se pudo generar ningún documento')
                ->body('Fallaron: '.implode(', ', $result->failedLabels()).'. Intente de nuevo o reporte a soporte.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($result->allGenerated()) {
            $count = $result->generatedCount();

            Notification::make()
                ->title($count === 1 ? 'Documento generado' : 'Documentos generados')
                ->body($count === 1
                    ? 'El documento ya está disponible para descarga.'
                    : "Los {$count} documentos ya están disponibles para descarga.")
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Generación parcial')
            ->body(
                'Se generaron: '.implode(', ', $result->generatedLabels()).'. '
                .'No se pudieron generar: '.implode(', ', $result->failedLabels()).'.'
            )
            ->warning()
            ->persistent()
            ->send();
    }
}
